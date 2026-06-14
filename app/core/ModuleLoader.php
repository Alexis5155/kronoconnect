<?php
declare(strict_types=1);

namespace KronoConnect\Core;

class ModuleLoader
{
    private static array $moduleData = [];

    public static function init(string $jsonPath): void
    {
        if (!file_exists($jsonPath)) {
            throw new \RuntimeException("Fichier module.json introuvable au chemin : " . $jsonPath);
        }

        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Erreur de syntaxe JSON dans module.json.");
        }

        if (empty($data['name']) || empty($data['requires_KronoConnectcore'])) {
            throw new \RuntimeException("module.json invalide : les champs 'name' et 'requires_KronoConnectcore' sont obligatoires.");
        }

        self::$moduleData = $data;

        // Vérification de compatibilité
        $appConfig = file_exists(CONFIG_PATH . '/app.php') ? require CONFIG_PATH . '/app.php' : [];
        $KronoConnectVersion = $appConfig['version'] ?? '1.0.0';
        
        $required = $data['requires_KronoConnectcore'];
        $requiredVer = preg_replace('/[^0-9\.]/', '', $required); // Extraction simple de la version
        
        if (version_compare($KronoConnectVersion, $requiredVer, '<')) {
            throw new \RuntimeException(sprintf(
                "Incompatibilité : le module '%s' nécessite KronoConnectCore >= %s, mais la version installée est %s.",
                $data['name'],
                $requiredVer,
                $KronoConnectVersion
            ));
        }
    }

    public static function get(string $key = null): mixed
    {
        if ($key === null) {
            return self::$moduleData;
        }
        return self::$moduleData[$key] ?? null;
    }
}
