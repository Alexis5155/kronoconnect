<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Core\View;
use KronoConnect\Core\Security;

abstract class BaseController
{
    protected function render(string $view, array $data = [], string $layout = 'auth'): void
    {
        if (class_exists(\KronoConnect\Services\DependencyService::class)) {
            $data['missingDependencies'] = \KronoConnect\Services\DependencyService::getCoreMissingDependencies();
        } else {
            $data['missingDependencies'] = [];
        }
        View::render($view, $data, $layout);
    }

    protected function json(mixed $data, int $status = 200): never
    {
        View::json($data, $status);
    }

    protected function verifyCsrf(): void
    {
        Security::verifyCsrf();
    }
}
