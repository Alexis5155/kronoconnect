<?php
declare(strict_types=1);

namespace KronoConnect\Core;

/**
 * Système de cache fichier simple.
 */
class Cache
{
    private static function getDir(): string
    {
        $dir = ROOT_PATH . '/storage/cache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        return $dir;
    }

    private static function getPath(string $key): string
    {
        return self::getDir() . '/' . md5($key) . '.cache';
    }

    /**
     * Stocke une donnée en cache.
     *
     * @param string $key La clé de cache.
     * @param mixed  $data La donnée à stocker.
     * @param int    $ttl Durée de vie en secondes.
     */
    public static function set(string $key, mixed $data, int $ttl = 300): void
    {
        $file = self::getPath($key);
        $payload = [
            'expires_at' => time() + $ttl,
            'data'       => $data,
        ];
        
        file_put_contents($file, serialize($payload), LOCK_EX);
    }

    /**
     * Récupère une donnée en cache si elle existe et n'a pas expiré.
     *
     * @param string $key La clé de cache.
     * @return mixed Les données ou null si introuvable ou expiré.
     */
    public static function get(string $key): mixed
    {
        $file = self::getPath($key);

        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $payload = @unserialize($content);
        
        // Structure invalide ou expiration
        if (!is_array($payload) || !isset($payload['expires_at']) || !isset($payload['data'])) {
            @unlink($file);
            return null;
        }

        if (time() > $payload['expires_at']) {
            @unlink($file);
            return null;
        }

        return $payload['data'];
    }

    /**
     * Récupère une valeur en cache ou exécute un callback pour la stocker.
     *
     * @param string   $key La clé de cache.
     * @param int      $ttl Durée de vie en secondes.
     * @param callable $callback Fonction retournant la valeur à stocker si absente.
     * @return mixed
     */
    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        $data = self::get($key);

        if ($data !== null) {
            return $data;
        }

        $data = $callback();
        self::set($key, $data, $ttl);

        return $data;
    }

    /**
     * Supprime une clé spécifique du cache.
     *
     * @param string $key La clé à supprimer.
     */
    public static function forget(string $key): void
    {
        $file = self::getPath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Vide l'intégralité du cache.
     */
    public static function flush(): void
    {
        $dir = self::getDir();
        $files = glob($dir . '/*.cache');
        if ($files) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
    }
}
