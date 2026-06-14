<?php
declare(strict_types=1);

namespace KronoConnect\Core;

class FileManager
{
    /**
     * Uploade un fichier de manière sécurisée.
     *
     * @param array  $file   Le tableau du fichier (ex: $_FILES['mon_champ'])
     * @param string $module Le nom du module qui effectue l'upload (ex: 'KronoConnectinstances')
     * @return string L'UUID généré pour ce fichier
     * @throws \RuntimeException Si le fichier est invalide, trop lourd ou que le type n'est pas autorisé
     */
    public static function upload(array $file, string $module): string
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \RuntimeException('Paramètres de fichier invalides.');
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new \RuntimeException('Aucun fichier n\'a été envoyé.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new \RuntimeException('Le fichier dépasse la taille maximale autorisée.');
            default:
                throw new \RuntimeException('Erreur inconnue lors du téléchargement.');
        }

        $config = require CONFIG_PATH . '/app.php';
        
        $maxSize = $config['files']['max_size'] ?? 10 * 1024 * 1024; // 10 Mo par défaut
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('Taille du fichier dépassée.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if ($mime === false) {
            throw new \RuntimeException('Impossible de déterminer le type MIME.');
        }

        // On utilise l'extension originale (nettoyée) juste pour garder une trace logique
        // La vraie sécurité se fait sur le MIME, mais on limite aussi l'extension finale
        $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        $allowedExts = $config['files']['allowed_ext'] ?? ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'odt', 'ods', 'jpg', 'jpeg', 'png'];
        if (!in_array($originalExt, $allowedExts, true)) {
            throw new \RuntimeException('Type de fichier non autorisé.');
        }

        // Génération d'un UUID v4
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

        $storageDir = ROOT_PATH . '/storage/files';
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $destPath = $storageDir . '/' . $uuid . '.' . $originalExt;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Impossible de déplacer le fichier téléchargé.');
        }

        $db = Database::getInstance();
        $tableName = $db->t('kronoconnect_files');
        $stmt = $db->getRawPdo()->prepare(
            "INSERT INTO `{$tableName}` (uuid, original_name, mime_type, extension, size, module, uploaded_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );

        $userId = Session::isLoggedIn() ? Session::userId() : null;

        $stmt->execute([
            $uuid,
            basename($file['name']), // Nom original nettoyé (pour éviter un path traversal dans la DB bien que ce soit juste un string)
            $mime,
            $originalExt,
            $file['size'],
            $module,
            $userId
        ]);

        Logger::info('Fichier uploadé', ['uuid' => $uuid, 'module' => $module, 'user_id' => $userId]);

        return $uuid;
    }

    /**
     * Force le téléchargement d'un fichier.
     *
     * @param string   $uuid   L'identifiant unique du fichier
     * @param int|null $userId ID de l'utilisateur demandant le téléchargement (pour les logs, sécurité gérée en amont)
     */
    public static function download(string $uuid, ?int $userId = null): void
    {
        $db = Database::getInstance();
        $tableName = $db->t('kronoconnect_files');
        $stmt = $db->getRawPdo()->prepare("SELECT * FROM `{$tableName}` WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $fileInfo = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$fileInfo) {
            http_response_code(404);
            echo "Fichier introuvable.";
            exit;
        }

        $path = ROOT_PATH . '/storage/files/' . $uuid . '.' . $fileInfo['extension'];

        if (!file_exists($path)) {
            http_response_code(404);
            echo "Fichier introuvable sur le disque.";
            exit;
        }

        Logger::info('Fichier téléchargé', ['uuid' => $uuid, 'user_id' => $userId]);

        // Nettoyage du buffer de sortie pour éviter de corrompre le fichier (ex: espaces blancs avant <?php)
        if (ob_get_level()) {
            ob_end_clean();
        }

        header('Content-Description: File Transfer');
        header('Content-Type: ' . $fileInfo['mime_type']);
        header('Content-Disposition: attachment; filename="' . addslashes($fileInfo['original_name']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path));
        
        readfile($path);
        exit;
    }

    /**
     * Supprime un fichier du disque et de la base de données.
     *
     * @param string $uuid
     * @return bool
     */
    public static function delete(string $uuid): bool
    {
        $db = Database::getInstance();
        $tableName = $db->t('kronoconnect_files');
        $stmt = $db->getRawPdo()->prepare("SELECT extension FROM `{$tableName}` WHERE uuid = ?");
        $stmt->execute([$uuid]);
        $ext = $stmt->fetchColumn();

        if (!$ext) {
            return false;
        }

        $path = ROOT_PATH . '/storage/files/' . $uuid . '.' . $ext;

        $deletedFromDisk = true;
        if (file_exists($path)) {
            $deletedFromDisk = unlink($path);
        }

        if ($deletedFromDisk) {
            $stmt = $db->getRawPdo()->prepare("DELETE FROM `{$tableName}` WHERE uuid = ?");
            $stmt->execute([$uuid]);
            Logger::warning('Fichier supprimé', ['uuid' => $uuid, 'by_user_id' => Session::userId()]);
            return true;
        }

        return false;
    }
}
