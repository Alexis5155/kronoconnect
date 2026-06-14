<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Gestionnaire de CAPTCHA unifié.
 * Supporte : image locale (fallback), reCAPTCHA, hCaptcha, Turnstile.
 */
class Captcha
{
    private static bool $scriptRendered = false;

    /**
     * Retourne le code HTML à injecter dans le formulaire.
     */
    public static function render(string $context = 'login'): string
    {
        $settings = \KronoConnect\Core\Cache::remember('settings', 300, fn() => (new \KronoConnect\Models\AdminModel())->getSettings());
        $provider = $settings['captcha_provider'] ?? 'none';
        
        if ($provider === 'none') return '';

        // Vérification si activé pour ce contexte
        $enabled = false;
        if ($context === 'login') {
            $enabled = ($settings['captcha_login'] ?? '0') === '1';
        } elseif ($context === 'register') {
            $enabled = ($settings['captcha_register'] ?? '1') === '1';
        } elseif ($context === 'reset') {
            $enabled = ($settings['captcha_reset'] ?? '1') === '1';
        }

        if (!$enabled) return '';

        $siteKey  = $settings['captcha_site_key'] ?? '';

        $script = '';
        if (!self::$scriptRendered) {
            $script = match ($provider) {
                'recaptcha' => '<script src="https://www.google.com/recaptcha/api.js" async defer></script>',
                'hcaptcha'  => '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>',
                'turnstile' => '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>',
                default     => ''
            };
            if ($script !== '') {
                self::$scriptRendered = true;
            }
        }

        switch ($provider) {
            case 'recaptcha':
                return '<div class="g-recaptcha" data-sitekey="' . htmlspecialchars($siteKey) . '" style="margin-bottom: 1rem;"></div>' . $script;
            case 'hcaptcha':
                return '<div class="h-captcha" data-sitekey="' . htmlspecialchars($siteKey) . '" style="margin-bottom: 1rem;"></div>' . $script;
            case 'turnstile':
                return '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars($siteKey) . '" style="margin-bottom: 1rem;"></div>' . $script;
            case 'image':
                return '<div style="margin-bottom: 1rem;">' .
                       '<img src="' . url('/captcha/image') . '" alt="CAPTCHA" style="border-radius: 4px; cursor: pointer; border: 1px solid var(--krono-border);" onclick="this.src=\'' . url('/captcha/image') . '?\' + Math.random()">' .
                       '<div class="auth-field" style="margin-top: 0.5rem;">' .
                       '<input type="text" name="captcha" class="auth-input" placeholder="Saisir le code" required>' .
                       '<i class="bi bi-shield-lock auth-field-icon"></i></div></div>';
            default:
                return ''; // none
        }
    }

    /**
     * Valide la réponse du CAPTCHA soumise dans le POST.
     */
    public static function validate(string $context = 'login'): bool
    {
        $settings = \KronoConnect\Core\Cache::remember('settings', 300, fn() => (new \KronoConnect\Models\AdminModel())->getSettings());
        $provider = $settings['captcha_provider'] ?? 'none';
        
        if ($provider === 'none') return true;

        // Vérification si activé pour ce contexte
        $enabled = false;
        if ($context === 'login') {
            $enabled = ($settings['captcha_login'] ?? '0') === '1';
        } elseif ($context === 'register') {
            $enabled = ($settings['captcha_register'] ?? '1') === '1';
        } elseif ($context === 'reset') {
            $enabled = ($settings['captcha_reset'] ?? '1') === '1';
        }

        if (!$enabled) return true;

        $secret   = $settings['captcha_secret_key'] ?? '';

        if ($provider === 'image') {
            $userCode = trim($_POST['captcha'] ?? '');
            $sessionCode = Session::get('captcha_code');
            // La vérification n'est valide qu'une fois
            Session::remove('captcha_code');
            return !empty($sessionCode) && strcasecmp($userCode, (string)$sessionCode) === 0;
        }

        $postKey = match ($provider) {
            'recaptcha' => 'g-recaptcha-response',
            'hcaptcha'  => 'h-captcha-response',
            'turnstile' => 'cf-turnstile-response',
            default     => null,
        };

        if (!$postKey) return false;

        $response = $_POST[$postKey] ?? '';
        if (empty($response)) return false;

        $verifyUrl = match ($provider) {
            'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
            'hcaptcha'  => 'https://hcaptcha.com/siteverify',
            'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        };

        $data = [
            'secret'   => $secret,
            'response' => $response,
            'remoteip' => Security::getClientIp()
        ];

        $options = [
            'http' => [
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'method'  => 'POST',
                'content' => http_build_query($data),
                'timeout' => 5
            ],
            'ssl' => ['verify_peer' => true]
        ];

        // Libère le verrou de session pendant la requête HTTP externe vers le fournisseur CAPTCHA
        Session::close();

        $context  = stream_context_create($options);
        $result = @file_get_contents($verifyUrl, false, $context);
        
        if ($result === false) {
            Logger::error('Erreur API Captcha', ['provider' => $provider]);
            return false;
        }

        $json = json_decode($result, true);
        return isset($json['success']) && $json['success'] === true;
    }

    /**
     * Génère une image locale sous forme de SVG moderne et sécurisé,
     * sans nécessiter la bibliothèque GD.
     */
    public static function generateImage(): void
    {
        // Génération d'un code de 5 caractères lisibles (sans les caractères ambigus)
        $chars = 'CDEFHJKMNPRTUVWXY34679';
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }

        // Sauvegarde du code en session
        Session::set('captcha_code', $code);

        // Bruit de fond : lignes aléatoires
        $noiseLines = '';
        $colors = ['#38bdf8', '#a855f7', '#f43f5e', '#10b981', '#fbbf24'];
        for ($i = 0; $i < 6; $i++) {
            $x1 = random_int(0, 40);
            $y1 = random_int(0, 40);
            $x2 = random_int(80, 120);
            $y2 = random_int(0, 40);
            $color = $colors[random_int(0, count($colors) - 1)];
            $strokeWidth = random_int(1, 2);
            $noiseLines .= "<line x1=\"$x1\" y1=\"$y1\" x2=\"$x2\" y2=\"$y2\" stroke=\"$color\" stroke-width=\"$strokeWidth\" opacity=\"0.4\" />";
        }

        // Bruit de fond : cercles aléatoires
        $noiseDots = '';
        $dotColors = ['#38bdf8', '#a855f7', '#f43f5e', '#ffffff'];
        for ($i = 0; $i < 25; $i++) {
            $cx = random_int(0, 120);
            $cy = random_int(0, 40);
            $r = random_int(1, 2);
            $color = $dotColors[random_int(0, count($dotColors) - 1)];
            $noiseDots .= "<circle cx=\"$cx\" cy=\"$cy\" r=\"$r\" fill=\"$color\" opacity=\"0.4\" />";
        }

        // Rendu des caractères déformés et inclinés
        $textGroup = '';
        $textColors = ['#f8fafc', '#38bdf8', '#a855f7', '#34d399', '#f472b6', '#fbbf24'];
        $letterSpacing = 20;
        $startX = 14;
        for ($i = 0; $i < 5; $i++) {
            $char = $code[$i];
            $x = $startX + ($i * $letterSpacing) + random_int(-2, 2);
            $y = 27 + random_int(-3, 3);
            $angle = random_int(-20, 20);
            $fontSize = random_int(18, 22);
            $color = $textColors[random_int(0, count($textColors) - 1)];
            
            $textGroup .= "<text x=\"$x\" y=\"$y\" font-family=\"Impact, Arial Black, sans-serif\" font-size=\"$fontSize\" font-weight=\"bold\" fill=\"$color\" transform=\"rotate($angle, $x, $y)\" style=\"user-select: none;\">$char</text>";
        }

        // Construction du SVG final
        $svg = <<<SVG
<svg width="120" height="40" viewBox="0 0 120 40" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="bgGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#0f172a" />
      <stop offset="100%" stop-color="#1e293b" />
    </linearGradient>
  </defs>
  <rect width="120" height="40" rx="4" fill="url(#bgGrad)" />
  $noiseLines
  $noiseDots
  $textGroup
</svg>
SVG;

        header('Content-Type: image/svg+xml');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        echo $svg;
        exit;
    }
}
