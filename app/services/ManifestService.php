<?php
declare(strict_types=1);

namespace KronoConnect\Services;

use KronoConnect\Models\ClientModel;
use KronoConnect\Core\Database;
use KronoConnect\Core\Logger;

class ManifestService
{
    private ClientModel $clients;
    private Database $db;

    public function __construct()
    {
        $this->clients = new ClientModel();
        $this->db = Database::getInstance();
    }

    /**
     * Synchronise le manifest d'un client SSO
     */
    public function sync(int $id): bool
    {
        $tSsoClients  = $this->db->t('sso_clients');
        $tPermissions = $this->db->t('permissions');

        $client = $this->db->fetchOne("SELECT * FROM `{$tSsoClients}` WHERE id = ?", [$id]);
        if (!$client) {
            return false;
        }

        $uris = json_decode($client['redirect_uris'], true);
        if (empty($uris)) {
            Logger::error("ManifestService: Aucune URI de redirection pour le client #$id");
            return false;
        }

        // Déduire l'URL de base à partir de la première redirect_uri
        $parsed = parse_url($uris[0]);
        if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
            Logger::error("ManifestService: URI de redirection invalide pour le client #$id ({$uris[0]})");
            return false;
        }

        $baseUrl = $parsed['scheme'] . '://' . $parsed['host'];
        if (!empty($parsed['port'])) {
            $baseUrl .= ':' . $parsed['port'];
        }
        
        // Si le chemin contient un sous-dossier, on tente de le conserver pour la racine de l'app
        $path = $parsed['path'] ?? '';
        $pathParts = explode('/', trim($path, '/'));
        if (count($pathParts) > 1) {
            $baseUrl .= '/' . $pathParts[0];
        }

        $manifestUrl = rtrim($baseUrl, '/') . '/kronoconnect/manifest';

        // Appel HTTP vers le client avec User-Agent
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'header' => "User-Agent: KronoConnect-ManifestService/1.0\r\n",
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($manifestUrl, false, $context);
        if ($response === false) {
            Logger::error("ManifestService: Impossible de contacter $manifestUrl pour le client #$id");
            return false;
        }

        $data = json_decode($response, true);
        if (!is_array($data) || !isset($data['name'], $data['permissions']) || !is_array($data['permissions'])) {
            Logger::error("ManifestService: Format de manifest invalide depuis $manifestUrl");
            return false;
        }

        // Mise à jour du client
        $rawIcon = $data['icon'] ?? 'app-indicator';
        $cleanIcon = preg_replace('/^bi-/i', '', trim($rawIcon)) ?: 'app-indicator';

        $updateData = [
            'app_name'           => $data['name'],
            'app_description'    => $data['description'] ?? '',
            'app_icon'           => $cleanIcon,
            'app_color'          => $data['color'] ?? '#3B82F6',
            'manifest_synced_at' => date('Y-m-d H:i:s')
        ];

        // Mettre à jour l'URL de logout si fournie et différente (priorité au manifest)
        if (!empty($data['logout_url'])) {
            $updateData['logout_url'] = rtrim((string)$data['logout_url'], '/');
        }

        $this->db->update('sso_clients', $updateData, ['id' => $id]);

        // Mise à jour des permissions (UPSERT)
        $existingPerms = $this->db->fetchAll("SELECT perm_key FROM `{$tPermissions}` WHERE client_id = ?", [$id]);
        $existingKeys = array_column($existingPerms, 'perm_key');
        $newKeys = [];

        foreach ($data['permissions'] as $perm) {
            if (empty($perm['key']) || empty($perm['label'])) continue;
            $key = $perm['key'];
            $newKeys[] = $key;

            $this->db->query(
                "INSERT INTO `{$tPermissions}` (client_id, perm_key, label, description, parent_key, synced_at)
                 VALUES (?, ?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE label = VALUES(label), description = VALUES(description), parent_key = VALUES(parent_key), synced_at = NOW()",
                [$id, $key, $perm['label'], $perm['description'] ?? '', $perm['parent_key'] ?? null]
            );
        }

        // Supprimer les permissions qui n'existent plus dans le manifest
        $toDelete = array_diff($existingKeys, $newKeys);
        if (!empty($toDelete)) {
            $placeholders = implode(',', array_fill(0, count($toDelete), '?'));
            $params = array_merge([$id], $toDelete);
            $this->db->query("DELETE FROM `{$tPermissions}` WHERE client_id = ? AND perm_key IN ($placeholders)", $params);
        }

        // Audit Log
        try {
            $this->db->insert('logs', [
                'level'      => 'info',
                'user_id'    => \KronoConnect\Core\Session::userId() ?: null,
                'action'     => 'sync_manifest',
                'message'    => 'Manifest synchronisé : ' . ($data['name'] ?? ''),
                'context'    => json_encode(['client_id' => $client['client_id'], 'app_name' => $data['name'] ?? '']),
                'ip_address' => \KronoConnect\Core\Security::getClientIp(),
            ]);
        } catch (\Throwable) {}

        return true;
    }

    private function getHeaderValue(array $headers, string $key): ?string
    {
        $key = strtolower($key);
        foreach ($headers as $header) {
            $parts = explode(':', $header, 2);
            if (count($parts) === 2 && strtolower(trim($parts[0])) === $key) {
                return trim($parts[1]);
            }
        }
        return null;
    }
}