<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Classe de sécurité centralisée.
 *
 * Responsabilités :
 *   - Protection CSRF (génération + vérification de token)
 *   - Échappement XSS (sortie HTML)
 *   - Envoi des headers HTTP de sécurité
 *   - Nettoyage des entrées utilisateur
 */
class Security
{
    // ── CSRF ──────────────────────────────────────────────────────────────

    private const CSRF_SESSION_KEY  = '_csrf_token';
    private const CSRF_TOKEN_LENGTH = 32; // octets → 64 hex chars

    /**
     * Génère (ou récupère) le token CSRF de la session courante.
     * Un seul token par session (pattern "synchronizer token").
     */
    public static function csrfToken(): string
    {
        if (!Session::has(self::CSRF_SESSION_KEY)) {
            Session::set(
                self::CSRF_SESSION_KEY,
                bin2hex(random_bytes(self::CSRF_TOKEN_LENGTH))
            );
        }
        return Session::get(self::CSRF_SESSION_KEY);
    }

    /**
     * Retourne un champ HTML hidden prêt à être inséré dans un formulaire.
     *
     * Usage dans une vue :
     *   <?= \KronoConnect\Core\Security::csrfField() ?>
     */
    public static function csrfField(): string
    {
        $token = self::csrfToken();
        return '<input type="hidden" name="_csrf_token" value="' . $token . '">';
    }

    /**
     * Vérifie le token CSRF soumis via POST ou via Header (Ajax).
     * Doit être appelé dans tous les contrôleurs traitant un POST.
     *
     * @throws \RuntimeException si le token est invalide (attaque CSRF détectée)
     */
    public static function verifyCsrf(): void
    {
        $submitted = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $expected  = Session::get(self::CSRF_SESSION_KEY, '');

        if (empty($submitted) || !hash_equals($expected, $submitted)) {
            http_response_code(403);
            Logger::warning('CSRF token invalide', [
                'ip'  => self::getClientIp(),
                'uri' => $_SERVER['REQUEST_URI'] ?? '',
            ]);

            // Si c'est un appel AJAX, on répond en JSON pour ne pas casser le client
            if (!empty($_SERVER['HTTP_X_CSRF_TOKEN']) || !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Session expirée ou jeton CSRF invalide.']);
                exit;
            }

            die('Requête invalide : token CSRF manquant ou incorrect. <a href="javascript:history.back()">Retour</a>');
        }

        // Note : on ne supprime plus le token après vérification.
        // La rotation à chaque requête cassait les multi-onglets (false positifs CSRF).
        // La sécurité est assurée par : la régénération à la connexion (session_regenerate_id)
        // et le cookie de session HttpOnly qui empêche la lecture du token côté JS tiers.
    }

    // ── XSS ───────────────────────────────────────────────────────────────

    /**
     * Échappe une valeur pour une sortie HTML sécurisée.
     * À utiliser PARTOUT dans les vues : <?= e($variable) ?>
     *
     * Alias global : fonction e() dans helpers.php
     */
    public static function escape(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Échappe pour un attribut HTML (guillemets doubles seulement).
     */
    public static function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Échappe pour une URL (encode les caractères dangereux).
     */
    public static function escapeUrl(string $url): string
    {
        // Bloque javascript: et data: URIs
        $url = trim($url);
        if (preg_match('/^(javascript|data|vbscript):/i', $url)) {
            return '#';
        }
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }

    // ── Headers HTTP ──────────────────────────────────────────────────────

    /**
     * Envoie les headers de sécurité HTTP.
     * Appelé une fois au bootstrap, avant tout output.
     */
    public static function sendSecurityHeaders(): void
    {
        // Empêche le clickjacking
        header('X-Frame-Options: SAMEORIGIN');

        // Empêche le MIME sniffing
        header('X-Content-Type-Options: nosniff');

        // Active le filtre XSS du navigateur (legacy, toujours utile)
        header('X-XSS-Protection: 1; mode=block');

        // Politique de référent
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Content Security Policy : ajuste selon tes besoins
        // 'unsafe-inline' nécessaire pour le CSS/JS inline actuellement
        // À renforcer progressivement (nonces) une fois le CSS externalisé
        header("Content-Security-Policy: " . implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://www.google.com https://www.gstatic.com https://js.hcaptcha.com https://hcaptcha.com https://*.hcaptcha.com https://challenges.cloudflare.com",
            "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://hcaptcha.com https://*.hcaptcha.com https://fonts.googleapis.com https://api.fontshare.com",
            "style-src-elem 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://hcaptcha.com https://*.hcaptcha.com https://fonts.googleapis.com https://api.fontshare.com",
            "img-src 'self' data: blob: https://hcaptcha.com https://*.hcaptcha.com https://quickchart.io",
            "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com https://api.fontshare.com https://cdn.fontshare.com",
            "font-src-elem 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com https://api.fontshare.com https://cdn.fontshare.com",
            "connect-src 'self' https://hcaptcha.com https://*.hcaptcha.com",
            "frame-src 'self' https://www.google.com https://hcaptcha.com https://*.hcaptcha.com https://challenges.cloudflare.com",
            "frame-ancestors 'self'",
            "form-action 'self'",
        ]));

        // Force HTTPS si en production (décommenter en prod)
        // header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

        // Permissions Policy (désactive les APIs navigateur non utilisées)
        header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    }

    // ── Nettoyage des entrées ─────────────────────────────────────────────

    /**
     * Nettoie une chaîne en entrée (trim + strip_tags).
     * NE remplace pas l'échappement en sortie — les deux sont complémentaires.
     */
    public static function sanitize(string $input): string
    {
        return trim(strip_tags($input));
    }

    /**
     * Nettoie et valide un email.
     */
    public static function sanitizeEmail(string $email): string|false
    {
        $clean = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($clean, FILTER_VALIDATE_EMAIL) ? strtolower($clean) : false;
    }

    /**
     * Retourne l'IP cliente (supporte les proxies avec X-Forwarded-For).
     * Ne pas utiliser comme identifiant unique — sert uniquement aux logs.
     */
    public static function getClientIp(): string
    {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For peut contenir plusieurs IPs : prendre la première
                return trim(explode(',', $_SERVER[$header])[0]);
            }
        }
        return 'unknown';
    }

    // ── Mots de passe ─────────────────────────────────────────────────────

    /**
     * Hache un mot de passe avec bcrypt (coût 12).
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Vérifie un mot de passe contre son hash.
     * Gère aussi la mise à jour automatique du hash si le coût change.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Indique si le hash doit être regénéré (changement de coût/algo).
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    // ── Génération de tokens ──────────────────────────────────────────────

    /**
     * Génère un token cryptographiquement sûr (pour reset password, remember me…).
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    // ── Vérification d'IP ──────────────────────────────────────────────────

    /**
     * Vérifie si une adresse IP correspond à une liste d'IPs ou de blocs CIDR autorisés.
     * La liste est une chaîne séparée par des virgules.
     * Si la liste est vide, toutes les IPs sont considérées comme autorisées.
     */
    public static function isIpAllowed(string $ip, ?string $allowedIpsRaw): bool
    {
        if (empty(trim((string)$allowedIpsRaw))) {
            return true; // Aucune restriction
        }

        $allowedList = array_map('trim', explode(',', $allowedIpsRaw));
        
        // Si l'IP cliente est inconnue (par ex. en CLI local), on pourrait avoir "unknown"
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach ($allowedList as $allowed) {
            if (empty($allowed)) {
                continue;
            }

            // Si c'est une notation CIDR (ex: 192.168.1.0/24)
            if (strpos($allowed, '/') !== false) {
                list($subnet, $bits) = explode('/', $allowed);
                $bits = (int)$bits;
                
                // IPv4 CIDR
                if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipLong = ip2long($ip);
                    $subnetLong = ip2long($subnet);
                    $mask = -1 << (32 - $bits);
                    $subnetLong &= $mask;
                    
                    if (($ipLong & $mask) === $subnetLong) {
                        return true;
                    }
                }
                // IPv6 CIDR - support minimal
                elseif (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $ipBin = inet_pton($ip);
                    $subnetBin = inet_pton($subnet);
                    if ($ipBin === false || $subnetBin === false) {
                        continue;
                    }
                    $maskStr = str_repeat("f", $bits >> 2);
                    switch ($bits % 4) {
                        case 1: $maskStr .= "8"; break;
                        case 2: $maskStr .= "c"; break;
                        case 3: $maskStr .= "e"; break;
                    }
                    $maskStr = str_pad($maskStr, 32, "0");
                    $maskBin = pack("H*", $maskStr);
                    
                    if (($ipBin & $maskBin) === ($subnetBin & $maskBin)) {
                        return true;
                    }
                }
            } else {
                // IP exacte
                if ($ip === $allowed) {
                    return true;
                }
            }
        }

        return false;
    }
}
// Cache invalidation
