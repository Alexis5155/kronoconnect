<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Système de hooks/events léger.
 * Permet aux modules de réagir aux événements du core sans le modifier.
 */
class EventEmitter
{
    /**
     * @var array<string, callable[]>
     */
    private static array $listeners = [];

    /**
     * Enregistre un listener pour un événement donné.
     *
     * @param string   $event    Nom de l'événement (ex: 'user.created')
     * @param callable $callback Fonction à exécuter
     */
    public static function on(string $event, callable $callback): void
    {
        if (!isset(self::$listeners[$event])) {
            self::$listeners[$event] = [];
        }
        self::$listeners[$event][] = $callback;
    }

    /**
     * Déclenche un événement et appelle tous ses listeners de manière synchrone.
     * Chaque listener est exécuté dans un bloc try/catch pour éviter qu'une
     * exception dans l'un d'eux n'interrompe l'exécution des autres ou du core.
     *
     * @param string $event Nom de l'événement
     * @param mixed  $data  Données associées à l'événement
     */
    public static function emit(string $event, mixed $data = null): void
    {
        if (empty(self::$listeners[$event])) {
            return;
        }

        foreach (self::$listeners[$event] as $callback) {
            try {
                $callback($data);
            } catch (\Throwable $e) {
                // Enregistre l'erreur mais ne bloque pas la suite
                Logger::error("Erreur dans le listener de l'événement '{$event}'", [
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
