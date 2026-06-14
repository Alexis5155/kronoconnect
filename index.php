<?php
declare(strict_types=1);

// ─── Constantes de chemins ─────────────────────────────────────────────────
define('ROOT_PATH',    __DIR__);
define('APP_PATH',     ROOT_PATH . '/app');
define('VIEW_PATH',    APP_PATH  . '/views');
define('CONFIG_PATH',  APP_PATH  . '/config');
define('STORAGE_PATH', ROOT_PATH . '/storage');

// ── Vérification installation (à faire avant tout require de config) ──────
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$_basePath  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

// Retire le préfixe du sous-dossier avant de tester /install
// (en sous-dossier, $requestUri vaut /kronoconnect/install/ et non /install/)
$_localUri  = ($_basePath !== '' && str_starts_with($requestUri, $_basePath))
              ? substr($requestUri, strlen($_basePath))
              : $requestUri;
$isInstall  = str_starts_with($_localUri, '/install');

if (!file_exists(ROOT_PATH . '/install/install.lock') && !$isInstall) {
    header('Location: ' . $_basePath . '/install/');
    exit;
}

// ─── Autoloader PSR-4 maison ───────────────────────────────────────────────
// DOIT être le premier require, avant tout appel de classe
require_once APP_PATH . '/core/Autoloader.php';

$autoloader = new \KronoConnect\Core\Autoloader();
$autoloader->register();

// Autoloader Composer (si présent)
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// ─── Helpers globaux (après l'autoloader) ─────────────────────────────────
require_once APP_PATH . '/core/helpers.php';

// ─── Gestion des erreurs PHP (avant tout le reste) ────────────────────────
$appConfigRaw = require CONFIG_PATH . '/app.php';

if (($appConfigRaw['debug'] ?? false) === true) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

date_default_timezone_set($appConfigRaw['timezone'] ?? 'Europe/Paris');

// ─── Headers de sécurité (avant tout output) ──────────────────────────────
\KronoConnect\Core\Security::sendSecurityHeaders();

// ─── Session sécurisée ─────────────────────────────────────────────────────
\KronoConnect\Core\Session::start();

// ─── Vérification "Remember Me" ────────────────────────────────────────────
if (!\KronoConnect\Core\Session::isLoggedIn()) {
    $cookieName = $appConfigRaw['remember_me']['cookie_name'] ?? 'KronoConnect_remember';
    if (!empty($_COOKIE[$cookieName])) {
        $userModel = new \KronoConnect\Models\UserModel();
        $user = $userModel->findByRememberToken($_COOKIE[$cookieName]);
        if ($user && !empty($user['is_active'])) {
            \KronoConnect\Core\Session::login($user);
        } else {
            setcookie($cookieName, '', time() - 3600, '/');
        }
    }
}

// ─── Mode Maintenance ───────────────────────────────────────────────────────
if (file_exists(ROOT_PATH . '/install/install.lock')) {
    try {
        $dbSettings = \KronoConnect\Core\Cache::remember('app_config_settings', 300, function() {
            return (new \KronoConnect\Models\AdminModel())->getSettings();
        });

        if (!empty($dbSettings['maintenance_mode']) && $dbSettings['maintenance_mode'] === '1') {
            $isAdmin = \KronoConnect\Core\Session::isLoggedIn() && \KronoConnect\Core\Session::hasPermission('kc.toggle.maintenance');
            
            if (!$isAdmin) {
                $_localUri = ($_basePath !== '' && str_starts_with($requestUri, $_basePath))
                              ? substr($requestUri, strlen($_basePath))
                              : $requestUri;
                $_localUri = '/' . trim($_localUri, '/');
                
                // Routes autorisées en cours de maintenance (auth, captcha, API oauth)
                $allowedRoutes = ['/login', '/logout', '/api/token', '/api/v1/ping', '/captcha/image'];
                $isAllowed = false;
                foreach ($allowedRoutes as $ar) {
                    if (str_starts_with($_localUri, $ar)) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    if (str_starts_with($_localUri, '/api/')) {
                        http_response_code(503);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['error' => 'Service en cours de maintenance.'], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    
                    http_response_code(503);
                    \KronoConnect\Core\View::render('errors/maintenance', [
                        'title' => 'Maintenance en cours'
                    ], 'auth');
                    exit;
                }
            }
        }
    } catch (\Throwable $e) {
        // Ignorer en cas d'erreur de base de données (ex: installation)
    }
}

// ─── Vérification de l'état du compte (Actif / Existant) ──────────────────────
if (\KronoConnect\Core\Session::isLoggedIn()) {
    try {
        $userId = \KronoConnect\Core\Session::userId();
        if ($userId) {
            $db = \KronoConnect\Core\Database::getInstance();
            $tUsers = $db->t('users');
            $user = $db->fetchOne("SELECT is_active FROM `{$tUsers}` WHERE id = ?", [$userId]);
            if (!$user || !$user['is_active']) {
                // L'utilisateur a été supprimé ou désactivé, on détruit sa session
                \KronoConnect\Core\Session::logout();
                
                $_localUri = ($_basePath !== '' && str_starts_with($requestUri, $_basePath))
                              ? substr($requestUri, strlen($_basePath))
                              : $requestUri;
                $_localUri = '/' . trim($_localUri, '/');
                
                if (str_starts_with($_localUri, '/api/')) {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => 'Compte désactivé ou inexistant.'], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                
                redirect('/login', ['error' => 'Votre compte a été désactivé ou supprimé par un administrateur.']);
            }
        }
    } catch (\Throwable $e) {
        // Ignorer en cas d'erreur temporaire de base de données
    }
}

// ─── Routeur ───────────────────────────────────────────────────────────────
$router = new \KronoConnect\Core\Router();
require CONFIG_PATH . '/routes.php';
$router->dispatch();