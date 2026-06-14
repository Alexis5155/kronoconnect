<?php
declare(strict_types=1);

namespace KronoConnect\Core;

class Migration
{
    private static array $paths = [
        ROOT_PATH . '/database/migrations'
    ];

    /**
     * Enregistre un nouveau dossier contenant des migrations SQL.
     */
    public static function registerPath(string $path): void
    {
        if (is_dir($path) && !in_array($path, self::$paths)) {
            self::$paths[] = $path;
        }
    }

    private static function prefix(): string
    {
        static $prefix = null;
        if ($prefix === null) {
            $cfg = require CONFIG_PATH . '/database.php';
            $prefix = $cfg['prefix'] ?? '';
        }
        return $prefix;
    }

    private static function ensureTable(): void
    {
        $db    = Database::getInstance()->getRawPdo();
        $table = self::prefix() . 'migrations';
        $db->exec("
            CREATE TABLE IF NOT EXISTS `{$table}` (
                id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                applied_at     DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Retourne la liste de toutes les migrations trouvées et leur statut.
     *
     * @return array<int, array{filename: string, path: string, applied: bool}>
     */
    public static function status(): array
    {
        self::ensureTable();
        $db = Database::getInstance()->getRawPdo();

        $migrationsTable = self::prefix() . 'migrations';
        $stmt = $db->query("SELECT migration_name FROM `{$migrationsTable}`");
        $applied = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        $appliedSet = array_flip($applied);

        $files = [];
        foreach (self::$paths as $path) {
            $jsonFile = $path . '/migrations.json';
            if (is_file($jsonFile)) {
                $data = json_decode(file_get_contents($jsonFile), true);
                if (is_array($data) && !empty($data['migrations'])) {
                    foreach ($data['migrations'] as $file) {
                        $fullPath = $path . '/' . $file;
                        if (is_file($fullPath)) {
                            $files[] = $fullPath;
                        }
                    }
                }
            }
        }


        // Éliminer les doublons éventuels de noms de fichiers
        $uniqueFiles = [];
        foreach ($files as $file) {
            $uniqueFiles[basename($file)] = $file;
        }

        $status = [];
        foreach ($uniqueFiles as $filename => $path) {
            $status[] = [
                'filename' => $filename,
                'path'     => $path,
                'applied'  => isset($appliedSet[$filename])
            ];
        }

        return $status;
    }

    /**
     * Exécute toutes les migrations en attente.
     *
     * @return array<string> Liste des fichiers exécutés
     * @throws \RuntimeException Si une migration échoue
     */
    public static function run(): array
    {
        $statusList = self::status();
        $db = Database::getInstance()->getRawPdo();
        $executed = [];

        foreach ($statusList as $item) {
            if (!$item['applied']) {
                $filename = $item['filename'];
                $path = $item['path'];

                try {
                    $sql = str_replace('{PREFIX}', self::prefix(), file_get_contents($path));
                    $db->beginTransaction();

                    $statements = array_filter(array_map('trim', explode(';', $sql)));
                    foreach ($statements as $stmt) {
                        if (!empty($stmt)) {
                            $db->exec($stmt);
                        }
                    }

                    $migrationsTable = self::prefix() . 'migrations';
                    $insertStmt = $db->prepare("INSERT INTO `{$migrationsTable}` (migration_name, applied_at) VALUES (?, NOW())");
                    $insertStmt->execute([$filename]);
                    
                    $db->commit();
                    $executed[] = $filename;
                } catch (\Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    throw new \RuntimeException("Erreur lors de l'exécution de la migration {$filename} : " . $e->getMessage());
                }
            }
        }

        return $executed;
    }
}
