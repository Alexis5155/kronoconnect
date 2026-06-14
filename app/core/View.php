<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Moteur de templates léger.
 *
 * Usage depuis un contrôleur :
 *   $this->render('dashboard/index', ['title' => 'Tableau de bord']);
 *   $this->render('auth/login', $data, 'auth');  // layout auth
 */
class View
{
    /**
     * Rend une vue dans un layout.
     *
     * @param string $view    Chemin relatif à app/views/ (sans .php)
     * @param array  $data    Variables injectées dans la vue
     * @param string $layout  Nom du layout dans app/views/layouts/ (sans .php)
     */
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $appConfig = require CONFIG_PATH . '/app.php';

        // Les settings sauvegardés en BDD surchargent les valeurs statiques de app.php,
        // ce qui permet à l'admin de changer le nom sans toucher aux fichiers.
        if (file_exists(ROOT_PATH . '/install/install.lock')) {
            try {
                $dbSettings = \KronoConnect\Core\Cache::remember('app_config_settings', 300, function() {
                    $adminModel = new \KronoConnect\Models\AdminModel();
                    return $adminModel->getSettings();
                });
                
                $textMap = [
                    'app_name'    => 'name',
                    'collectivite'=> 'module_name',
                    'subtitle'    => 'subtitle',
                ];
                
                foreach ($dbSettings as $k => $v) {
                    if (isset($textMap[$k])) {
                        $appConfig[$textMap[$k]] = $v;
                    } elseif ($k === 'registration') {
                        $appConfig['features'][$k] = $v === '1';
                    }
                }
            } catch (\Throwable) {
                // DB pas encore disponible (install en cours) — on garde app.php
            }
        }

        $data['appConfig'] = $appConfig;

        // Extraction des variables dans le scope local
        extract($data, EXTR_SKIP);

        // Capture du contenu de la vue (Priorité: Module > Core)
        $moduleViewFile = APP_PATH . '/module/Views/' . $view . '.php';
        $coreViewFile   = VIEW_PATH . '/' . $view . '.php';
        
        $viewFile = file_exists($moduleViewFile) ? $moduleViewFile : $coreViewFile;

        if (!file_exists($viewFile)) {
            throw new \RuntimeException("Vue introuvable : {$viewFile}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Rendu dans le layout
        $layoutFile = VIEW_PATH . '/layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout introuvable : {$layoutFile}");
        }

        require $layoutFile;
    }

    /**
     * Rend une vue partielle (sans layout).
     * Utile pour les includes dans les vues.
     */
    public static function partial(string $partial, array $data = []): string
    {
        extract($data, EXTR_SKIP);
        
        $moduleFile = APP_PATH . '/module/Views/partials/' . $partial . '.php';
        $coreFile   = VIEW_PATH . '/partials/' . $partial . '.php';
        
        $file = file_exists($moduleFile) ? $moduleFile : $coreFile;

        if (!file_exists($file)) {
            throw new \RuntimeException("Partiel introuvable : {$file}");
        }
        ob_start();
        require $file;
        return ob_get_clean();
    }

    /**
     * Rend une réponse JSON et stoppe l'exécution.
     */
    public static function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}