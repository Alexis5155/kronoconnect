<?php
declare(strict_types=1);

namespace KronoConnect\Services;

/**
 * UpdateService
 * ─────────────
 * Gère la vérification, le téléchargement et l'application
 * des mises à jour KronoConnect depuis GitHub Releases.
 */
class UpdateService
{
    private const GH_API     = 'https://api.github.com';
    private const USER_AGENT = 'KronoConnect-Updater/1.0';

    private const STORAGE_DIR    = ROOT_PATH . '/storage/updates';
    private const BACKUP_DIR     = ROOT_PATH . '/storage/updates/backup';
    private const MIGRATIONS_DIR = ROOT_PATH . '/database/migrations/delta';
    private const LOCK_FILE      = ROOT_PATH . '/storage/updates/update.lock';

    private const PROTECTED_PATHS = [
        'app/config/database.php',
        'app/config/app.php',
        'install/install.lock',
        'storage/',
    ];

    private \PDO $pdo;
    private array $config;
    private string $prefix;

    public function __construct(\PDO $pdo, ?array $config = null)
    {
        $this->pdo    = $pdo;
        $this->config = $config ?? (require CONFIG_PATH . '/app.php');
        $dbConfig     = require CONFIG_PATH . '/database.php';
        $this->prefix = $dbConfig['prefix'] ?? '';

        $this->ensureDirectories();
        $this->ensureTable();
    }

    public function check(): array
    {
        $current = $this->currentVersion();

        try {
            $release = $this->fetchLatestRelease();
        } catch (\RuntimeException $e) {
            return [
                'update_available' => false,
                'current_version'  => $current,
                'latest_version'   => $current,
                'release_name'     => '',
                'release_url'      => '',
                'changelog'        => '',
                'published_at'     => '',
                'assets'           => [],
                'has_migrations'   => false,
                'error'            => $e->getMessage(),
            ];
        }

        $latest = ltrim($release['tag_name'], 'v');

        return [
            'update_available' => version_compare($latest, $current, '>'),
            'current_version'  => $current,
            'latest_version'   => $latest,
            'release_name'     => $release['name'] ?? "v{$latest}",
            'release_url'      => $release['html_url'] ?? '',
            'changelog'        => $release['body'] ?? '',
            'published_at'     => $release['published_at'] ?? '',
            'assets'           => $release['assets'] ?? [],
            'has_migrations'   => $this->releaseHasMigrations($release),
            'error'            => null,
        ];
    }

    public function download(string $version): string
    {
        if (file_exists(self::LOCK_FILE)) {
            throw new \RuntimeException("Une mise à jour est déjà en cours.");
        }

        $repo = $this->config['update']['github_repo'] ?? 'Alexis5155/kronoconnect';
        $zipUrl   = "https://github.com/{$repo}/archive/refs/tags/v{$version}.zip";
        $destFile = self::STORAGE_DIR . "/kronoconnect-{$version}.zip";

        if (file_exists($destFile)) {
            return $destFile;
        }

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'header'          => "User-Agent: " . self::USER_AGENT . "\r\n",
                'follow_location' => true,
                'timeout'         => 60,
            ],
            'ssl' => ['verify_peer' => true],
        ]);

        $data = @file_get_contents($zipUrl, false, $ctx);
        if ($data === false) {
            throw new \RuntimeException("Impossible de télécharger la release v{$version} depuis GitHub.");
        }

        if (file_put_contents($destFile, $data) === false) {
            throw new \RuntimeException("Impossible d'écrire le fichier : {$destFile}");
        }

        return $destFile;
    }

    public function apply(string $zipPath, string $newVersion, callable $log): void
    {
        if (file_exists(self::LOCK_FILE)) {
            throw new \RuntimeException("Une mise à jour est déjà en cours.");
        }
        file_put_contents(self::LOCK_FILE, time());
        
        try {
            $log('info', 'Extraction de l\'archive…');
            $extractDir = self::STORAGE_DIR . '/extract_' . time();
            $this->extractZip($zipPath, $extractDir);
            $log('success', 'Archive extraite.');

            $repo = $this->config['update']['github_repo'] ?? 'Alexis5155/kronoconnect';
            $repoName = explode('/', $repo)[1] ?? 'kronoconnect';
            $sourceDir = $extractDir . '/' . $repoName . '-' . $newVersion;
            if (!is_dir($sourceDir)) {
                $dirs = glob($extractDir . '/*', GLOB_ONLYDIR);
                $sourceDir = $dirs[0] ?? $extractDir;
            }

            $log('info', 'Sauvegarde des fichiers actuels…');
            $current = $this->currentVersion();
            $backupPath = self::BACKUP_DIR . "/backup_" . $current . '_' . date('Ymd_His');
            $this->backupCurrentFiles($backupPath);
            $log('success', "Backup créé : {$backupPath}");

            $log('info', 'Application des fichiers de la nouvelle version…');
            $copied = $this->copyFiles($sourceDir, ROOT_PATH);
            $log('success', "{$copied} fichier(s) mis à jour.");

            $migrationsDir = $sourceDir . '/database/migrations';
            if (is_dir($migrationsDir)) {
                $log('info', 'Application des migrations SQL…');
                $this->applyMigrations($migrationsDir, $log);
            } else {
                $log('info', 'Aucune migration SQL pour cette version.');
            }

            $log('info', 'Mise à jour du numéro de version…');
            $this->updateVersionInConfig($newVersion);
            $this->recordUpdate($current, $newVersion);
            $log('success', "Version mise à jour : v{$newVersion}");

            $log('info', 'Nettoyage des fichiers temporaires…');
            $this->deleteDirectory($extractDir);
            @unlink($zipPath);
            $log('success', 'Nettoyage terminé.');
        } finally {
            @unlink(self::LOCK_FILE);
        }
    }

    public function history(): array
    {
        $stmt = $this->pdo->query(
            "SELECT * FROM {$this->prefix}updates ORDER BY applied_at DESC LIMIT 20"
        );
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function backups(): array
    {
        if (!is_dir(self::BACKUP_DIR)) return [];
        $dirs = glob(self::BACKUP_DIR . '/backup_*', GLOB_ONLYDIR);
        return array_map(fn($d) => [
            'path'    => $d,
            'name'    => basename($d),
            'size'    => $this->dirSize($d),
            'created' => filemtime($d),
        ], $dirs ?: []);
    }

    private function fetchLatestRelease(): array
    {
        $repo = $this->config['update']['github_repo'] ?? 'Alexis5155/kronoconnect';
        $url  = self::GH_API . '/repos/' . $repo . '/releases/latest';
        $ctx  = stream_context_create(['http' => [
            'method'  => 'GET',
            'header'  => implode("\r\n", [
                'User-Agent: ' . self::USER_AGENT,
                'Accept: application/vnd.github+json',
                'X-GitHub-Api-Version: 2022-11-28',
            ]),
            'timeout' => 10,
        ]]);

        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            throw new \RuntimeException('Impossible de contacter l\'API GitHub. Vérifiez la connexion réseau.');
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || empty($data['tag_name'])) {
            throw new \RuntimeException('Réponse GitHub invalide ou aucune release publiée.');
        }

        return $data;
    }

    private function releaseHasMigrations(array $release): bool
    {
        foreach ($release['assets'] ?? [] as $asset) {
            if (str_contains(strtolower($asset['name']), 'migration') ||
                str_ends_with($asset['name'], '.sql')) {
                return true;
            }
        }
        return false;
    }

    private function extractZip(string $zipPath, string $destDir): void
    {
        if (!extension_loaded('zip')) {
            throw new \RuntimeException('L\'extension PHP zip est requise pour appliquer les mises à jour.');
        }

        $zip = new \ZipArchive();
        $res = $zip->open($zipPath);
        if ($res !== true) {
            throw new \RuntimeException("Impossible d'ouvrir l'archive ZIP (code : {$res}).");
        }

        @mkdir($destDir, 0755, true);
        $zip->extractTo($destDir);
        $zip->close();
    }

    private function copyFiles(string $sourceDir, string $destDir): int
    {
        $count         = 0;
        $realSourceDir = realpath($sourceDir);
        $realDestDir   = realpath($destDir);

        if (!$realSourceDir || !$realDestDir) {
            throw new \RuntimeException("Répertoire source ou destination invalide.");
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($sourceDir) + 1);
            $relative = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $relative);

            if ($this->isProtected($relative)) continue;

            $dest = $destDir . DIRECTORY_SEPARATOR . $relative;

            $destParent = $item->isDir() ? $dest : dirname($dest);
            @mkdir($destParent, 0755, true);
            $realDest = realpath($destParent);

            if (!$realDest || !str_starts_with($realDest, $realDestDir)) {
                error_log("UpdateService : path traversal détecté et ignoré : {$relative}");
                continue;
            }

            if (!$item->isDir()) {
                if (copy($item->getPathname(), $dest)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function isProtected(string $relativePath): bool
    {
        $relativePath = str_replace('\\', '/', $relativePath);
        foreach (self::PROTECTED_PATHS as $protected) {
            if (str_starts_with($relativePath, $protected) || $relativePath === rtrim($protected, '/')) {
                return true;
            }
        }
        return false;
    }

    private function applyMigrations(string $migrationsDir, callable $log): void
    {
        $jsonFile = $migrationsDir . '/migrations.json';
        if (!is_file($jsonFile)) {
            $log('info', 'Aucun fichier migrations.json trouvé.');
            return;
        }

        $data = json_decode(file_get_contents($jsonFile), true);
        if (!is_array($data) || empty($data['migrations'])) {
            $log('info', 'Aucune migration listée dans migrations.json.');
            return;
        }

        foreach ($data['migrations'] as $name) {
            $file = $migrationsDir . '/' . $name;
            if (!is_file($file)) {
                $log('error', "Fichier de migration introuvable : {$name}");
                continue;
            }

            if ($this->isMigrationApplied($name)) {
                continue;
            }

            $log('info', "Migration : {$name}…");
            try {
                $sql = file_get_contents($file);
                // Remplacement du prefix
                $sql = str_replace('{PREFIX}', $this->prefix, $sql);
                
                $this->pdo->beginTransaction();
                foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                    if ($stmt) $this->pdo->exec($stmt);
                }
                $this->pdo->commit();
                $this->recordMigration($name);
                $log('success', "{$name} appliquée.");
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                throw new \RuntimeException("Échec de la migration {$name} : " . $e->getMessage());
            }
        }
    }


    private function isMigrationApplied(string $filename): bool
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM {$this->prefix}migrations WHERE migration_name = ?"
        );
        $stmt->execute([$filename]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function recordMigration(string $filename): void
    {
        $this->pdo->prepare(
            "INSERT INTO {$this->prefix}migrations (migration_name, applied_at) VALUES (?, NOW())"
        )->execute([$filename]);
    }

    private function recordUpdate(string $fromVersion, string $toVersion): void
    {
        $this->pdo->prepare(
            "INSERT INTO {$this->prefix}updates (from_version, to_version, applied_at) VALUES (?, ?, NOW())"
        )->execute([$fromVersion, $toVersion]);
    }

    private function backupCurrentFiles(string $backupPath): void
    {
        @mkdir($backupPath, 0755, true);

        $toBackup = ['app', 'assets', 'database', 'public', 'index.php', '.htaccess'];
        foreach ($toBackup as $item) {
            $src  = ROOT_PATH . '/' . $item;
            $dest = $backupPath . '/' . $item;
            if (is_dir($src))  $this->copyDirectory($src, $dest);
            elseif (is_file($src)) copy($src, $dest);
        }
    }

    private function copyDirectory(string $src, string $dest): void
    {
        @mkdir($dest, 0755, true);
        $srcLen = strlen(rtrim($src, DIRECTORY_SEPARATOR)) + 1;

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($it as $item) {
            $relative = substr($item->getPathname(), $srcLen);
            $target   = $dest . DIRECTORY_SEPARATOR . $relative;
            $item->isDir() ? @mkdir($target, 0755, true) : copy($item->getPathname(), $target);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private function updateVersionInConfig(string $newVersion): void
    {
        $appConfig = ROOT_PATH . '/app/config/app.php';
        if (!file_exists($appConfig)) return;
        $content = file_get_contents($appConfig);
        $content = preg_replace(
            "/'version'\s*=>\s*'[^']*'/",
            "'version' => '{$newVersion}'",
            $content
        );
        file_put_contents($appConfig, $content);
    }

    private function currentVersion(): string
    {
        $cfg = @include ROOT_PATH . '/app/config/app.php';
        return $cfg['version'] ?? '1.0.0';
    }

    private function ensureDirectories(): void
    {
        foreach ([self::STORAGE_DIR, self::BACKUP_DIR, self::MIGRATIONS_DIR] as $dir) {
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
        }
    }

    private function ensureTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->prefix}migrations (
                id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(255) NOT NULL UNIQUE,
                applied_at     DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->prefix}updates (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                from_version VARCHAR(20) NOT NULL,
                to_version   VARCHAR(20) NOT NULL,
                applied_at   DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    private function dirSize(string $dir): int
    {
        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)) as $f) {
            $size += $f->getSize();
        }
        return $size;
    }
}