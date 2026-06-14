<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

class ErrorController extends BaseController
{
    public function show(int $code, string $message = ''): void
    {
        http_response_code($code);

        $titles = [
            404 => 'Page introuvable',
            403 => 'Accès refusé',
            500 => 'Erreur serveur',
        ];
        $title = $titles[$code] ?? 'Erreur';

        $this->render('errors/generic', compact('code', 'title', 'message'));
    }
}
