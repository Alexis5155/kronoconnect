<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Autoloader PSR-4 maison.
 * 
 * Mappe les namespaces vers les dossiers :
 *   KronoConnect\Core\Router       → app/core/Router.php
 *   KronoConnect\Controllers\Auth  → app/controllers/AuthController.php
 *   KronoConnect\Models\User       → app/models/UserModel.php
 *   KronoConnect\Middleware\Auth   → app/middleware/AuthMiddleware.php
 */
class Autoloader
{
    /**
     * Map namespace prefix → répertoire physique
     */
    private array $prefixes = [];

    public function __construct()
    {
        // Enregistrement des namespaces de base
        $this->addNamespace('KronoConnect\\Core',        APP_PATH . '/core');
        $this->addNamespace('KronoConnect\\Controllers', APP_PATH . '/controllers');
        $this->addNamespace('KronoConnect\\Models',      APP_PATH . '/models');
        $this->addNamespace('KronoConnect\\Middleware',  APP_PATH . '/middleware');
        $this->addNamespace('KronoConnect\\Services',    APP_PATH . '/services');

        // Namespace de fallback pour le module applicatif actif
        $this->addNamespace('Module\\Controllers', APP_PATH . '/module/Controllers');
        $this->addNamespace('Module\\Models',      APP_PATH . '/module/Models');

        // Support pour PHPMailer (fallback si Composer n'est pas utilisé)
        $this->addNamespace('PHPMailer\\PHPMailer', ROOT_PATH . '/vendor/phpmailer/phpmailer/src');
    }

    /**
     * Ajoute un mapping namespace → dossier.
     * Utilisé par les modules pour enregistrer leurs propres namespaces.
     */
    public function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix  = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, '/') . '/';
        $this->prefixes[$prefix] = $baseDir;
    }

    /**
     * Enregistre l'autoloader dans la pile SPL.
     */
    public function register(): void
    {
        spl_autoload_register([$this, 'loadClass']);
    }

    /**
     * Charge le fichier correspondant à la classe demandée.
     */
    public function loadClass(string $class): bool
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }

            $relativeClass = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

            if (file_exists($file)) {
                require $file;
                return true;
            }
        }

        return false;
    }
}