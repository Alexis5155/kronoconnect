<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Models\ClientModel;
use KronoConnect\Models\AuthCodeModel;
use KronoConnect\Models\UserModel;
use KronoConnect\Core\Database;
use KronoConnect\Core\Security;
use KronoConnect\Core\Logger;

/**
 * Endpoint stateless server-to-server.
 * Authentification par HMAC-SHA256.
 */
class ApiController extends BaseController
{
    use HmacAuthTrait;

    private ClientModel   $clients;
    private AuthCodeModel $codes;
    private UserModel     $users;
    private Database      $db;

    public function __construct()
    {
        $this->clients = new ClientModel();
        $this->codes   = new AuthCodeModel();
        $this->users   = new UserModel();
        $this->db      = Database::getInstance();
    }

    // ── GET /api/v1/ping ──────────────────────────────────────────

    public function ping(): void
    {
        $appConfig = require CONFIG_PATH . '/app.php';
        $version = $appConfig['version'] ?? '1.0.0';

        $adminModel = new \KronoConnect\Models\AdminModel();
        $settings = $adminModel->getSettings();

        $this->json([
            'status'  => 'ok',
            'service' => 'KronoConnect',
            'version' => $version,
            'identity' => [
                'app_name' => $settings['app_name'] ?? 'KronoConnect',
                'collectivite' => $settings['collectivite'] ?? 'Ma Mairie',
                'logo_url' => !empty($settings['logo_uuid']) ? url('/public/logo') . '?v=' . $settings['logo_uuid'] : null,
            ]
        ]);
    }

    // ── POST /api/token ───────────────────────────────────────────────────

    public function token(): void
    {
        $client = $this->authenticateApiRequest();
        
        // Body was verified by HMAC. Parse it.
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $code = $data['code'] ?? '';

        if ($code === '') {
            $this->json(['error' => 'missing_code'], 400);
        }

        $authCode = $this->codes->findValid($code);
        if (!$authCode || $authCode['client_id'] !== $client['client_id']) {
            $this->json(['error' => 'invalid_code'], 400);
        }

        $this->codes->markUsed((int) $authCode['id']);

        $user = $this->users->findById((int) $authCode['user_id']);
        if (!$user || !$user['is_active']) {
            $this->json(['error' => 'user_not_found'], 404);
        }



        $accessData = $this->calculateAccess($client, $user);

        // Génère ou réutilise un sso_token dédié (distinct du remember_token navigateur).
        // Ce token est renvoyé au client et servira pour les vérifications ultérieures
        // via GET /api/v1/user/{token}. Il n'a pas d'expiration propre (la session KC fait foi).
        $ssoToken = $user['sso_token'] ?? null;
        if (empty($ssoToken)) {
            $ssoToken = bin2hex(random_bytes(32));
            $this->users->setSsoToken((int) $user['id'], $ssoToken);
        }

        $this->json([
            'id'             => $user['id'],
            'email'          => $user['email'],
            'nom'            => $user['nom'],
            'prenom'         => $user['prenom'],
            'role'           => $user['role'],
            'theme'          => $user['theme'] ?? 'system',
            'service_id'     => $user['service_id'],
            'service_name'   => $user['service_name'],
            'access_granted' => $accessData['access_granted'],
            'permissions'    => $accessData['permissions'],
            'sso_token'      => $ssoToken,
        ]);
    }

    public function getServices(): void
    {
        $this->authenticateApiRequest();

        $tServices = $this->db->t('services');
        $services = $this->db->fetchAll("SELECT * FROM `{$tServices}` ORDER BY name ASC");

        $this->json([
            'status'   => 'ok',
            'services' => $services
        ]);
    }

    // ── POST /api/v1/manifest ─────────────────────────────────────────────
    //
    // Reçoit la liste des permissions déclarées par l'instance cliente
    // (agrégat core + modules construit par KronoCore::PermissionService).
    // Upsert dans `permissions` ; les clés disparues sont désactivées
    // (active=0) plutôt que supprimées pour préserver les attributions
    // déjà faites dans group_permissions / user_permissions.

    public function syncManifest(): void
    {
        $client = $this->authenticateApiRequest();

        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $permissions = $data['permissions'] ?? null;

        if (!is_array($permissions)) {
            $this->json(['error' => 'invalid_payload'], 400);
        }

        $clientId     = (int) $client['id'];
        $tPermissions = $this->db->t('permissions');

        $existing = $this->db->fetchAll(
            "SELECT perm_key FROM `{$tPermissions}` WHERE client_id = ?",
            [$clientId]
        );
        $existingKeys = array_column($existing, 'perm_key');

        $seenKeys = [];
        $synced   = 0;

        foreach ($permissions as $perm) {
            if (!is_array($perm)) continue;
            $key   = trim((string) ($perm['key']   ?? ''));
            $label = trim((string) ($perm['label'] ?? ''));
            $desc  = (string) ($perm['description'] ?? '');

            if ($key === '' || $label === '') continue;
            if (strlen($key) > 100 || strlen($label) > 150) continue;

            $this->db->query(
                "INSERT INTO `{$tPermissions}` (client_id, perm_key, label, description, active, synced_at)
                 VALUES (?, ?, ?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                    label       = VALUES(label),
                    description = VALUES(description),
                    active      = 1,
                    synced_at   = NOW()",
                [$clientId, $key, $label, $desc]
            );

            $seenKeys[] = $key;
            $synced++;
        }

        $missing      = array_values(array_diff($existingKeys, $seenKeys));
        $deactivated  = 0;

        if (!empty($missing)) {
            $placeholders = implode(',', array_fill(0, count($missing), '?'));
            $params       = array_merge([$clientId], $missing);
            $this->db->query(
                "UPDATE `{$tPermissions}`
                    SET active = 0
                  WHERE client_id = ? AND perm_key IN ($placeholders) AND active = 1",
                $params
            );
            $deactivated = count($missing);
        }

        try {
            $this->db->update('sso_clients', [
                'manifest_synced_at' => date('Y-m-d H:i:s'),
            ], ['id' => $clientId]);

            if (isset($data['logo']) && is_string($data['logo'])) {
                $tSettings = $this->db->t('settings');
                $this->db->query(
                    "INSERT INTO `{$tSettings}` (setting_key, setting_value) VALUES ('app_logo', ?) 
                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                    [$data['logo']]
                );
            }
        } catch (\Throwable) {}

        Logger::info('Manifest synchronisé via push', [
            'client_id'   => $client['client_id'],
            'synced'      => $synced,
            'deactivated' => $deactivated,
        ]);

        $this->json([
            'status'      => 'ok',
            'synced'      => $synced,
            'deactivated' => $deactivated,
        ]);
    }

    // ── GET /api/v1/user/{token} ──────────────────────────────────────────

    public function getUserInfo(string $token): void
    {
        $client = $this->authenticateApiRequest();

        // Le token est le sso_token émis lors du dernier échange de code (POST /api/token).
        $user = $this->users->findBy('sso_token', $token);
        if (!$user) {
            $this->json(['error' => 'invalid_token'], 404);
        }

        $accessData = $this->calculateAccess($client, $user);

        $this->json([
            'id'             => $user['id'],
            'email'          => $user['email'],
            'firstname'      => $user['prenom'],
            'lastname'       => $user['nom'],
            'theme'          => $user['theme'] ?? 'system',
            'service_id'     => $user['service_id'],
            'service_name'   => $user['service_name'],
            'permissions'    => $accessData['permissions'],
            'access_granted' => $accessData['access_granted']
        ]);
    }

    private function calculateAccess(array $client, array $user): array
    {
        $clientId   = (int)$client['id'];
        $userId     = (int)$user['id'];
        $accessMode = $client['access_mode'] ?? 'open';

        $tUserAppAccess    = $this->db->t('user_app_access');
        $tGroupAppAccess   = $this->db->t('group_app_access');
        $tGroupMembers     = $this->db->t('group_members');
        $tPermissions      = $this->db->t('permissions');
        $tGroupPermissions = $this->db->t('group_permissions');
        $tUserPermissions  = $this->db->t('user_permissions');

        $accessGranted = false;

        if ($user['role'] === 'super_admin') {
            $accessGranted = true;
        } elseif ($accessMode === 'open') {
            $accessGranted = true;
        } elseif ($accessMode === 'manual') {
            $manualAccess = $this->db->fetchOne(
                "SELECT id FROM `{$tUserAppAccess}` WHERE user_id = ? AND client_id = ?",
                [$userId, $clientId]
            );
            if ($manualAccess) { $accessGranted = true; }
        } elseif ($accessMode === 'group') {
            $groupAccess = $this->db->fetchOne("
                SELECT gaa.group_id
                FROM `{$tGroupAppAccess}` gaa
                JOIN `{$tGroupMembers}` gm ON gaa.group_id = gm.group_id
                WHERE gm.user_id = ? AND gaa.client_id = ?
            ", [$userId, $clientId]);
            if ($groupAccess) { $accessGranted = true; }
        }

        if ($user['role'] === 'super_admin') {
            $allPerms = $this->db->fetchAll(
                "SELECT perm_key FROM `{$tPermissions}` WHERE client_id = ?",
                [$clientId]
            );
            $perms = array_column($allPerms, 'perm_key');
        } else {
            $groupPerms = $this->db->fetchAll("
                SELECT gp.perm_key
                FROM `{$tGroupPermissions}` gp
                JOIN `{$tGroupMembers}` gm ON gp.group_id = gm.group_id
                WHERE gm.user_id = ? AND gp.client_id = ?
            ", [$userId, $clientId]);
            $perms = array_column($groupPerms, 'perm_key');

            $userPerms = $this->db->fetchAll(
                "SELECT perm_key, granted FROM `{$tUserPermissions}` WHERE user_id = ? AND client_id = ?",
                [$userId, $clientId]
            );
            foreach ($userPerms as $up) {
                $key = $up['perm_key'];
                if ($up['granted'] == 1) {
                    if (!in_array($key, $perms)) { $perms[] = $key; }
                } else {
                    $perms = array_diff($perms, [$key]);
                }
            }
        }

        // Load parent mappings for this client
        $clientPermsData = $this->db->fetchAll(
            "SELECT perm_key, parent_key FROM `{$tPermissions}` WHERE client_id = ?",
            [$clientId]
        );
        $parentsMap = [];
        foreach ($clientPermsData as $cpd) {
            if (!empty($cpd['parent_key'])) {
                $parentsMap[$cpd['perm_key']] = $cpd['parent_key'];
            }
        }

        // Pruning loop to resolve dependencies recursively
        $changed = true;
        while ($changed) {
            $changed = false;
            foreach ($perms as $i => $perm) {
                if (isset($parentsMap[$perm])) {
                    $parent = $parentsMap[$perm];
                    if (!in_array($parent, $perms, true)) {
                        unset($perms[$i]);
                        $perms = array_values($perms);
                        $changed = true;
                        break;
                    }
                }
            }
        }

        return [
            'access_granted' => $accessGranted,
            'permissions'    => array_values($perms),
        ];
    }
}
