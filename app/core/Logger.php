<?php
declare(strict_types=1);

namespace KronoConnect\Core;

class Logger
{
    public static function info(string $message, array $context = []): void
    {
        self::write('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('ERROR', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        // ── 1. Écriture dans le fichier plat (toujours, même si la BDD est indisponible) ──
        $logDir  = ROOT_PATH . '/storage/logs';
        $logFile = $logDir . '/' . date('Y-m-d') . '.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $line = sprintf(
            "[%s] %s : %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            $message,
            $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );

        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);

        // ── 2. Écriture dans KronoConnect_logs (BDD) pour l'affichage dans le panel admin ──
        // On encapsule dans un try/catch pour ne jamais bloquer l'application
        // si la BDD n'est pas encore disponible (ex: pendant l'installation).
        try {
            $db = Database::getInstance();
            $db->insert('logs', [
                'level'   => strtolower($level),
                'message' => $message,
                'context' => $context ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            ]);
        } catch (\Throwable) {
            // BDD indisponible — l'entrée est déjà dans le fichier plat, on continue silencieusement.
        }
    }
}