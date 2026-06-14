<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Gestion sécurisée des sessions PHP.
 *
 * Centralise toute interaction avec $_SESSION.
 * Ne jamais appeler session_start() en dehors de cette classe.
 */
class Session
{
    private static bool $started = false;

    /**
     * Démarre la session avec les paramètres de sécurité définis dans app.php.
     * Appelé une seule fois depuis index.php.
     */
    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $config = require CONFIG_PATH . '/app.php';
        $s      = $config['session'];

        // N'activer Secure que si on est réellement sur HTTPS.
        // Sur HTTP (dev XAMPP), les navigateurs modernes refusent les cookies Secure
        // et la session ne persiste plus entre les requêtes.
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;

        session_set_cookie_params([
            'lifetime' => $s['lifetime'],
            'path'     => '/',
            'domain'   => '',
            'secure'   => $s['secure'] && $isHttps,
            'httponly' => $s['httponly'],
            'samesite' => $s['samesite'],
        ]);

        session_name($s['name']);
        session_start();
        self::$started = true;

        // Régénération périodique de l'ID de session (anti-fixation)
        self::regenerateIfNeeded();
    }

    /**
     * Ferme la session en écriture pour libérer le verrou.
     * À appeler dans les scripts longs ou les polling API pour ne pas bloquer la navigation.
     */
    public static function close(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
            self::$started = false;
        }
    }

    /**
     * S'assure que la session est active (et la réouvre si nécessaire).
     */
    private static function ensureActive(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }
    }

    // ── Lecture / Écriture ────────────────────────────────────────────────

    public static function set(string $key, mixed $value): void
    {
        self::ensureActive();
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function remove(string $key): void
    {
        self::ensureActive();
        unset($_SESSION[$key]);
    }

    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    // ── Messages Flash ────────────────────────────────────────────────────
    // Un message flash est lu une seule fois puis supprimé automatiquement.

    public static function flash(string $key, string $message): void
    {
        self::ensureActive();
        $_SESSION['_flash'][$key] = $message;
    }

    public static function getFlash(string $key): ?string
    {
        self::ensureActive();
        $value = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function hasFlash(string $key): bool
    {
        return isset($_SESSION['_flash'][$key]);
    }

    /**
     * Retourne tous les messages flash et les supprime.
     * Utilisé dans les layouts pour afficher les alertes.
     */
    public static function pullFlashes(): array
    {
        self::ensureActive();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flashes;
    }

    // ── Authentification ──────────────────────────────────────────────────

    public static function login(array $user): void
    {
        self::ensureActive();
        // Régénération impérative lors d'une connexion (anti-fixation de session)
        session_regenerate_id(true);

        self::set('user_id',          $user['id']);
        self::set('user_nom',         $user['nom']);
        self::set('user_prenom',      $user['prenom']);
        self::set('user_email',       $user['email']);
        self::set('user_role',        $user['role']);
        $perms = $user['permissions'] ?? [];
        if (is_string($perms)) {
            $perms = json_decode($perms, true) ?? [];
        }
        self::set('user_permissions', $perms);
        self::set('user_theme',       $user['theme'] ?? 'system');
        self::set('logged_in',        true);
        self::set('login_time',       time());
    }

    public static function logout(): void
    {
        self::ensureActive();
        // Vide toutes les variables de session
        $_SESSION = [];

        // Supprime le cookie de session côté navigateur
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 3600, // Date dans le passé pour forcer la suppression du cookie
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    public static function isLoggedIn(): bool
    {
        return self::get('logged_in', false) === true;
    }

    public static function userId(): ?int
    {
        $id = self::get('user_id');
        return $id !== null ? (int) $id : null;
    }

    public static function userRole(): ?string
    {
        return self::get('user_role');
    }

    public static function userPermissions(): array
    {
        if (self::hasRole('super_admin')) {
            $configPath = CONFIG_PATH . '/permissions.php';
            if (file_exists($configPath)) {
                $kcPermissions = require $configPath;
                return array_column($kcPermissions, 'key');
            }
        }
        $perms = self::get('user_permissions', []);
        if (is_string($perms)) {
            $perms = json_decode($perms, true) ?? [];
        }
        return $perms ?: [];
    }

    public static function user(): array
    {
        return [
            'id'          => self::userId(),
            'nom'         => self::get('user_nom'),
            'prenom'      => self::get('user_prenom'),
            'email'       => self::get('user_email'),
            'role'        => self::userRole(),
            'permissions' => self::userPermissions(),
            'theme'       => self::get('user_theme', 'system'),
        ];
    }

    public static function hasRole(string ...$roles): bool
    {
        return in_array(self::userRole(), $roles, true);
    }

    /**
     * Vérifie si l'utilisateur a au moins l'une des permissions demandées.
     * Le rôle 'super_admin' a automatiquement toutes les permissions.
     */
    public static function hasPermission(string ...$permissions): bool
    {
        $userPerms = self::userPermissions();
        foreach ($permissions as $p) {
            if (in_array($p, $userPerms, true)) {
                return true;
            }
        }
        return false;
    }

    // ── Sécurité interne ──────────────────────────────────────────────────

    /**
     * Régénère l'ID de session toutes les 30 minutes pour les sessions actives.
     * Réduit la fenêtre d'exploitation d'un ID de session volé.
     */
    private static function regenerateIfNeeded(): void
    {
        $lastRegen = self::get('_last_regen');
        $interval  = 1800; // 30 minutes

        if ($lastRegen === null || (time() - $lastRegen) > $interval) {
            if (self::isLoggedIn()) {
                session_regenerate_id(true);
            }
            self::set('_last_regen', time());
        }
    }
}