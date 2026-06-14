<?php
declare(strict_types=1);

/**
 * Fonctions globales utilitaires.
 * Chargé une fois depuis index.php — disponibles partout sans namespace.
 */

use KronoConnect\Core\Security;
use KronoConnect\Core\Session;

// ── Sécurité & Sortie ─────────────────────────────────────────────────────

/**
 * Échappe une valeur pour une sortie HTML sécurisée.
 */
function e(mixed $value): string
{
    return Security::escape($value);
}

/**
 * Retourne le token CSRF sous forme de champ hidden.
 */
function csrf(): string
{
    return Security::csrfField();
}

/**
 * Retourne uniquement la valeur du token CSRF.
 */
function csrf_token(): string
{
    return Security::csrfToken();
}

// ── URLs & Assets ─────────────────────────────────────────────────────────

/**
 * Retourne l'URL de base détectée ou configurée.
 */
function get_base_url(): string
{
    static $basePath = null;

    if ($basePath === null) {
        $config = require CONFIG_PATH . '/app.php';
        $basePath = rtrim($config['base_url'] ?? '', '/');

        // Si vide, auto-détection
        if (empty($basePath) && isset($_SERVER['HTTP_HOST'])) {
            $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
            $host     = $_SERVER['HTTP_HOST'];
            $script   = dirname($_SERVER['SCRIPT_NAME']);
            $basePath = rtrim($protocol . '://' . $host . $script, '/\\');
        }
    }

    return $basePath ?: 'http://localhost';
}

/**
 * Génère une URL absolue.
 */
function url(string $path = '/'): string
{
    return get_base_url() . '/' . ltrim($path, '/');
}

/**
 * Génère une URL vers un asset statique.
 */
function asset(string $path): string
{
    return url('/assets/' . ltrim($path, '/'));
}

// ── Redirections ──────────────────────────────────────────────────────────

/**
 * Redirige vers une URL et stoppe l'exécution.
 */
function redirect(string $path, array $flash = []): never
{
    foreach ($flash as $key => $message) {
        Session::flash($key, $message);
    }
    
    $url = $path;
    if (!str_starts_with($path, 'http://') && !str_starts_with($path, 'https://')) {
        $url = url($path);
    }
    
    header('Location: ' . $url);
    exit;
}

/**
 * Redirige vers la page précédente (ou fallback si pas de Referer).
 */
function back(string $fallback = '/', array $flash = []): never
{
    foreach ($flash as $key => $message) {
        Session::flash($key, $message);
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $baseUrl = get_base_url();

    // Valider que le Referer appartient à notre domaine
    if ($referer && !str_starts_with($referer, $baseUrl . '/') && $referer !== $baseUrl) {
        $referer = '';
    }

    header('Location: ' . ($referer ?: url($fallback)));
    exit;
}

// ── Session & Auth ────────────────────────────────────────────────────────

/**
 * Retourne l'utilisateur connecté ou null.
 */
function auth(): ?array
{
    if (!Session::isLoggedIn()) {
        return null;
    }
    return Session::user();
}

/**
 * Vérifie si l'utilisateur a au moins l'une des permissions données.
 */
function can(string ...$permissions): bool
{
    return Session::hasPermission(...$permissions);
}

// ── Formatage ─────────────────────────────────────────────────────────────

/**
 * Formate une date MySQL en format français.
 */
function dateFormat(?string $date, bool $withTime = false): string
{
    if (!$date) return '—';
    try {
        $dt     = new DateTimeImmutable($date);
        $format = $withTime ? 'd/m/Y \à H\hi' : 'd/m/Y';
        return $dt->format($format);
    } catch (\Exception) {
        return '—';
    }
}

/**
 * Tronque un texte à N caractères avec ellipse.
 */
function truncate(string $text, int $max = 100, string $suffix = '…'): string
{
    if (mb_strlen($text) <= $max) return $text;
    return mb_substr($text, 0, $max) . $suffix;
}

// ── Couleurs ──────────────────────────────────────────────────────────────

/**
 * Convertit un HEX en RGB array.
 */
function hexToRgb(string $hex): array
{
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return [$r, $g, $b];
}

/**
 * Éclaircit ou obscurcit une couleur HEX.
 */
function adjustBrightness(string $hex, int $percent): string
{
    [$r, $g, $b] = hexToRgb($hex);
    $r = max(0, min(255, $r + ($r * $percent / 100)));
    $g = max(0, min(255, $g + ($g * $percent / 100)));
    $b = max(0, min(255, $b + ($b * $percent / 100)));
    return sprintf("#%02x%02x%02x", (int)$r, (int)$g, (int)$b);
}

/**
 * Mélange une couleur HEX avec du blanc ou du noir.
 */
function colorMix(string $hex, string $mixWith, int $weight): string
{
    [$r1, $g1, $b1] = hexToRgb($hex);
    [$r2, $g2, $b2] = hexToRgb($mixWith);

    $r = round($r1 * ($weight / 100) + $r2 * (1 - $weight / 100));
    $g = round($g1 * ($weight / 100) + $g2 * (1 - $weight / 100));
    $b = round($b1 * ($weight / 100) + $b2 * (1 - $weight / 100));

    return sprintf("#%02x%02x%02x", (int)$r, (int)$g, (int)$b);
}

/**
 * Récupère la valeur d'un paramètre système (avec cache ou en base).
 */
function setting(string $key, string $default = ''): string
{
    static $settings = null;
    if ($settings === null) {
        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();
    }
    return $settings[$key] ?? $default;
}

/**
 * Génère une salutation dynamique et personnalisée en fonction de l'heure, du jour et d'autres paramètres.
 */
function get_dynamic_greeting(string $firstName): string
{
    $hour = (int)date('G');
    $dayOfWeek = (int)date('N'); // 1 (lundi) à 7 (dimanche)
    
    // Détermination de la période de la journée
    if ($hour >= 5 && $hour < 12) {
        $period = 'morning';
    } elseif ($hour >= 12 && $hour < 18) {
        $period = 'afternoon';
    } else {
        $period = 'evening';
    }

    $greetings = [];

    // Messages spécifiques aux jours/heures particuliers
    if ($dayOfWeek === 1 && $period === 'morning') {
        $greetings[] = "Bon début de semaine, {$firstName} ! Prêt pour une nouvelle semaine ?";
        $greetings[] = "Bonjour {$firstName}, bon lundi et bon début de semaine !";
    }
    
    if ($dayOfWeek === 5 && $hour >= 14) {
        $greetings[] = "Bon vendredi après-midi {$firstName}, le week-end est tout proche !";
        $greetings[] = "Bientôt le week-end, {$firstName} !";
    }
    
    if ($dayOfWeek === 6 || $dayOfWeek === 7) {
        $greetings[] = "Bon week-end, {$firstName} ! Profitez bien de vos moments de repos.";
        $greetings[] = "Ravi de vous croiser ce week-end, {$firstName} !";
    }

    // Si aucun message spécifique n'est applicable ou pour rajouter de la variété
    if (empty($greetings)) {
        if ($period === 'morning') {
            $greetings = [
                "Bonjour {$firstName}, passez une excellente journée !",
                "Bon matin {$firstName}, ravi de vous revoir !",
                "Bonjour {$firstName}. C'est une belle journée pour avancer !",
                "Content de vous revoir ce matin, {$firstName}."
            ];
        } elseif ($period === 'afternoon') {
            $greetings = [
                "Bonjour {$firstName}, ravi de vous revoir !",
                "Bon après-midi, {$firstName} !",
                "Content de vous revoir, {$firstName}.",
                "Bonjour {$firstName}. Comment se passe votre journée ?"
            ];
        } else {
            $greetings = [
                "Bonsoir {$firstName}, ravi de vous revoir !",
                "Bonne fin de journée, {$firstName}.",
                "Bonsoir {$firstName}. Finissez bien votre journée !",
                "Ravi de vous retrouver ce soir, {$firstName}."
            ];
        }
    }

    // Sélection déterministe basée sur le jour de l'année + l'heure pour éviter le scintillement à chaque clic
    $dayOfYear = (int)date('z');
    $index = ($dayOfYear + $hour) % count($greetings);
    
    return $greetings[$index];
}

// ── Débogage (dev uniquement) ─────────────────────────────────────────────

/**
 * Dump stylisé d'une variable.
 */
function dd(mixed ...$vars): never
{
    echo '<pre style="background:#1e1e2e;color:#cdd6f4;padding:1rem;border-radius:8px;font-size:.85rem;overflow:auto;">';
    foreach ($vars as $var) {
        var_dump($var);
    }
    echo '</pre>';
    exit;
}

