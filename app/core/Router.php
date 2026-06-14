<?php
declare(strict_types=1);

namespace KronoConnect\Core;

use KronoConnect\Controllers\ErrorController;

/**
 * Routeur regex léger.
 * 
 * Supporte :
 *   - Routes statiques : GET /dashboard
 *   - Routes dynamiques : GET /admin/users/{id}
 *   - Middlewares par route
 *   - Groupes de routes (prefix + middleware commun)
 */
class Router
{
    private array $routes    = [];
    private string $prefix   = '';
    private array  $groupMiddlewares = [];

    // ── Enregistrement des routes ─────────────────────────────────────────

    public function get(string $uri, array $action, array $middlewares = []): void
    {
        $this->addRoute('GET', $uri, $action, $middlewares);
    }

    public function post(string $uri, array $action, array $middlewares = []): void
    {
        $this->addRoute('POST', $uri, $action, $middlewares);
    }

    public function put(string $uri, array $action, array $middlewares = []): void
    {
        $this->addRoute('PUT', $uri, $action, $middlewares);
    }

    public function delete(string $uri, array $action, array $middlewares = []): void
    {
        $this->addRoute('DELETE', $uri, $action, $middlewares);
    }

    /**
     * Groupe de routes avec préfixe et/ou middlewares communs.
     * 
     * Exemple :
     *   $router->group(['prefix' => '/admin', 'middleware' => ['auth', 'admin']], function($r) {
     *       $r->get('/users', [AdminController::class, 'users']);
     *   });
     */
    public function group(array $options, callable $callback): void
    {
        $previousPrefix     = $this->prefix;
        $previousMiddlewares = $this->groupMiddlewares;

        $this->prefix            = $previousPrefix . ($options['prefix'] ?? '');
        $this->groupMiddlewares  = array_merge($previousMiddlewares, $options['middleware'] ?? []);

        $callback($this);

        // Restauration après le groupe
        $this->prefix           = $previousPrefix;
        $this->groupMiddlewares = $previousMiddlewares;
    }

    // ── Dispatch ──────────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri    = $this->parseUri();

        // Simulation de PUT/DELETE via POST + _method (hébergement mutualisé)
        if ($method === 'POST' && isset($_POST['_method'])) {
            $method = strtoupper($_POST['_method']);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            $params = $this->matchRoute($route['uri'], $uri);

            if ($params === false) {
                continue;
            }

            // ── Exécution des middlewares ─────────────────────────────
            foreach ($route['middlewares'] as $middlewareName) {
                $middleware = $this->resolveMiddleware($middlewareName);
                $middleware->handle();
            }

            // ── Appel du contrôleur ───────────────────────────────────
            [$controllerClass, $controllerMethod] = $route['action'];

            if (!class_exists($controllerClass)) {
                $this->abort(500, "Contrôleur introuvable : $controllerClass");
                return;
            }

            $controller = new $controllerClass();

            if (!method_exists($controller, $controllerMethod)) {
                $this->abort(500, "Méthode introuvable : $controllerClass::$controllerMethod");
                return;
            }

            $controller->$controllerMethod(...array_values($params));
            return;
        }

        // Aucune route trouvée → 404
        $this->abort(404);
    }

    // ── Méthodes privées ──────────────────────────────────────────────────

    private function addRoute(string $method, string $uri, array $action, array $middlewares): void
    {
        $this->routes[] = [
            'method'      => $method,
            'uri'         => $this->prefix . $uri,
            'action'      => $action,
            'middlewares' => array_merge($this->groupMiddlewares, $middlewares),
        ];
    }

    /**
     * Nettoie l'URI courante (retire la query string et le sous-dossier éventuel).
     */
    private function parseUri(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $uri = strtok($uri, '?'); // Retire ?param=value

        // Support sous-dossier (ex: /KronoConnectcore/dashboard → /dashboard)
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($basePath !== '' && str_starts_with($uri, $basePath)) {
            $uri = substr($uri, strlen($basePath));
        }

        return '/' . trim($uri, '/') ?: '/';
    }

    /**
     * Compare l'URI de la route (avec {param}) à l'URI courante.
     * Retourne un tableau de paramètres extraits, ou false si pas de match.
     */
    private function matchRoute(string $routeUri, string $requestUri): array|false
    {
        // Convertit /admin/users/{id} en regex /admin/users/([^/]+)
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^/]+)', $routeUri);
        $pattern = '#^' . $pattern . '$#';

        if (!preg_match($pattern, $requestUri, $matches)) {
            return false;
        }

        // Extrait les noms des paramètres depuis la route
        preg_match_all('/\{([a-zA-Z_]+)\}/', $routeUri, $paramNames);
        array_shift($matches); // Retire le match complet

        return array_combine($paramNames[1], $matches) ?: [];
    }

    /**
     * Résout un nom de middleware vers sa classe.
     */
    private function resolveMiddleware(string $name): object
    {
        $map = [
            'auth'    => \KronoConnect\Middleware\AuthMiddleware::class,
            'admin'   => \KronoConnect\Middleware\RoleMiddleware::class . ':admin',
            'install' => \KronoConnect\Middleware\InstallMiddleware::class,
        ];

        if (!isset($map[$name])) {
            throw new \RuntimeException("Middleware inconnu : $name");
        }

        // Support paramètre (ex: "RoleMiddleware:admin")
        [$class, $param] = array_pad(explode(':', $map[$name], 2), 2, null);

        return $param ? new $class($param) : new $class();
    }

    private function abort(int $code, string $message = ''): void
    {
        http_response_code($code);
        $controller = new ErrorController();
        $controller->show($code, $message);
    }
}