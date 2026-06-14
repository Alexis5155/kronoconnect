<?php
declare(strict_types=1);

namespace KronoConnect\Services;

class DependencyService
{
    /**
     * Vérifie une liste de dépendances (extensions PHP et packages Composer).
     * @param array $requirements ['extensions' => [...], 'packages' => [...]]
     * @return array Liste des dépendances manquantes (sous forme de chaînes de caractères).
     */
    public static function checkDependencies(array $requirements): array
    {
        $missing = [];

        // Vérification des extensions PHP
        if (isset($requirements['extensions']) && is_array($requirements['extensions'])) {
            foreach ($requirements['extensions'] as $ext) {
                if (!extension_loaded($ext)) {
                    $missing[] = "Extension PHP : $ext";
                }
            }
        }

        // Vérification des packages Composer
        if (isset($requirements['packages']) && is_array($requirements['packages'])) {
            foreach ($requirements['packages'] as $pkg => $version) {
                // Si la clé est un entier, c'est une liste indexée [ "phpmailer/phpmailer" ]
                if (is_int($pkg)) {
                    $pkg = $version;
                }
                
                // On vérifie l'existence du dossier dans vendor
                if (!is_dir(ROOT_PATH . '/vendor/' . $pkg)) {
                    $missing[] = "Package : $pkg";
                }
            }
        }

        return $missing;
    }

    /**
     * Retourne les dépendances manquantes pour le cœur de KronoConnect.
     */
    public static function getCoreMissingDependencies(): array
    {
        return self::checkDependencies([
            'packages' => [
                'phpmailer/phpmailer'
            ]
        ]);
    }
}
