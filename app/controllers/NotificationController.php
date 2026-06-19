<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Models\ClientModel;
use KronoConnect\Models\NotificationModel;
use KronoConnect\Models\UserModel;
use KronoConnect\Core\Database;
use KronoConnect\Core\Logger;

/**
 * Hub de notifications stateless server-to-server.
 * Toute requête est authentifiée par HMAC-SHA256 (HmacAuthTrait).
 *
 * Endpoints :
 *   POST /api/v1/notifications              : émettre une notification
 *   GET  /api/v1/notifications              : lire les non lues d'un utilisateur
 *   POST /api/v1/notifications/mark-read    : marquer comme lues
 */
class NotificationController extends BaseController
{
    use HmacAuthTrait;

    private ClientModel       $clients;
    private NotificationModel $notifications;
    private UserModel         $users;
    private Database          $db;

    public function __construct()
    {
        $this->clients       = new ClientModel();
        $this->notifications = new NotificationModel();
        $this->users         = new UserModel();
        $this->db            = Database::getInstance();
    }

    // ── POST /api/v1/notifications ─────────────────────────────────────────
    // Émet une notification destinée à un utilisateur. Identifié par email.

    public function send(): void
    {
        $client = $this->authenticateApiRequest();

        $body = file_get_contents('php://input') ?: '';
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->json(['error' => 'invalid_json'], 400);
        }

        $email      = trim((string) ($data['user_email'] ?? ''));
        $permission = trim((string) ($data['permission'] ?? ''));
        $type       = trim((string) ($data['type']       ?? 'info'));
        $title      = trim((string) ($data['title']      ?? ''));
        $message    = trim((string) ($data['message']    ?? ''));
        $url        = isset($data['url']) && $data['url'] !== '' ? (string) $data['url'] : null;

        if ($title === '' || $message === '') {
            $this->json(['error' => 'missing_fields'], 400);
        }
        if ($email === '' && $permission === '') {
            $this->json(['error' => 'missing_recipient_or_permission'], 400);
        }
        if (!in_array($type, ['success', 'error', 'info', 'warning'], true)) {
            $type = 'info';
        }
        // Sanity caps : on garde une trace lisible, pas un dump (anti-DoS sur l'écriture).
        if (mb_strlen($title)   > NotificationModel::MAX_TITLE_LEN)   { $title   = mb_substr($title,   0, NotificationModel::MAX_TITLE_LEN); }
        if (mb_strlen($message) > NotificationModel::MAX_MESSAGE_LEN) { $message = mb_substr($message, 0, NotificationModel::MAX_MESSAGE_LEN); }

        // Validation de l'URL : seuls http(s) et chemins relatifs autorisés (anti-XSS via href).
        $url = NotificationModel::sanitizeUrl($url);
        if ($url === false) {
            $this->json(['error' => 'invalid_url'], 400);
        }

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->json(['error' => 'invalid_email'], 400);
            }
            $user = $this->users->findByEmail($email);
            if (!$user) {
                $this->json(['error' => 'user_not_found'], 404);
            }

            try {
                $id = $this->notifications->create(
                    (int) $user['id'],
                    (string) $client['client_id'],
                    $type,
                    $title,
                    $message,
                    $url
                );
            } catch (\Throwable $e) {
                Logger::error('Notifications: échec création', [
                    'client_id' => $client['client_id'],
                    'email'     => $email,
                    'error'     => $e->getMessage(),
                ]);
                $this->json(['error' => 'storage_failure'], 500);
            }

            $this->json(['status' => 'ok', 'notification_id' => $id]);
        } else {
            // Loop through all active users, calculate access, and notify if they have the permission
            $tUsers = $this->db->t('users');
            $tServices = $this->db->t('services');
            $tGroupMembers = $this->db->t('group_members');
            $tGroups = $this->db->t('groups');

            $activeUsers = $this->db->fetchAll("
                SELECT u.*, s.name as service_name, g.tech_name AS role, g.name AS group_name, g.id AS group_id
                FROM `{$tUsers}` u 
                LEFT JOIN `{$tServices}` s ON u.service_id = s.id 
                LEFT JOIN `{$tGroupMembers}` gm ON u.id = gm.user_id
                LEFT JOIN `{$tGroups}` g ON gm.group_id = g.id
                WHERE u.is_active = 1 AND u.status = 'actif'
            ");

            $notifiedCount = 0;
            $errors = [];
            foreach ($activeUsers as $user) {
                $access = $this->calculateAccess($client, $user);
                if ($access['access_granted'] && in_array($permission, $access['permissions'], true)) {
                    try {
                        $this->notifications->create(
                            (int) $user['id'],
                            (string) $client['client_id'],
                            $type,
                            $title,
                            $message,
                            $url
                        );
                        $notifiedCount++;
                    } catch (\Throwable $e) {
                        $errors[] = $e->getMessage();
                    }
                }
            }
            if (!empty($errors)) {
                Logger::error('Notifications: échecs créations par permission', [
                    'client_id'  => $client['client_id'],
                    'permission' => $permission,
                    'errors'     => $errors,
                ]);
            }
            $this->json(['status' => 'ok', 'notified_count' => $notifiedCount]);
        }
    }

    // ── GET /api/v1/notifications ──────────────────────────────────────────
    // Liste les notifications non lues d'un utilisateur (tous clients confondus).

    public function listUnread(): void
    {
        $this->authenticateApiRequest();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $email = trim((string) ($input['user_email'] ?? ''));
            $limit = (int) ($input['limit'] ?? 20);
        } else {
            $email = trim((string) ($_GET['user_email'] ?? ''));
            $limit = (int) ($_GET['limit'] ?? 20);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'invalid_email'], 400);
        }

        $user = $this->users->findByEmail($email);
        if (!$user) {
            // Pas d'erreur révélatrice : on retourne une liste vide.
            $this->json(['notifications' => [], 'unread_count' => 0]);
        }

        $limit = max(1, min(100, $limit));
        $items = $this->notifications->getUnreadEnriched((int) $user['id'], $limit);
        $count = $this->notifications->countUnread((int) $user['id']);

        $this->json([
            'notifications' => array_map([$this, 'shapeRow'], $items),
            'unread_count'  => $count,
        ]);
    }

    // ── GET /api/v1/notifications/history ──────────────────────────────────
    // Historique complet paginé (lues + non lues) joint avec sso_clients.

    public function history(): void
    {
        $this->authenticateApiRequest();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $email   = trim((string) ($input['user_email'] ?? ''));
            $page    = max(1, (int) ($input['page']     ?? 1));
            $perPage = (int) ($input['per_page'] ?? 20);
        } else {
            $email   = trim((string) ($_GET['user_email'] ?? ''));
            $page    = max(1, (int) ($_GET['page']     ?? 1));
            $perPage = (int) ($_GET['per_page'] ?? 20);
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'invalid_email'], 400);
        }

        $user = $this->users->findByEmail($email);
        if (!$user) {
            $this->json([
                'rows'       => [],
                'total'      => 0,
                'page'       => $page,
                'perPage'    => $perPage,
                'totalPages' => 1,
            ]);
        }

        $perPage = max(1, min(100, $perPage));
        $result  = $this->notifications->getHistory((int) $user['id'], $page, $perPage);
        $result['rows'] = array_map([$this, 'shapeRow'], $result['rows']);

        $this->json($result);
    }

    /**
     * Met en forme une ligne SQL enrichie en payload public stable pour les
     * apps clientes : type/title/message/url/app_*.
     */
    private function shapeRow(array $row): array
    {
        $clientId = $row['client_id'] ?? null;
        $isHub    = (bool) ($row['is_hub'] ?? ($clientId === null || $clientId === ''));

        if ($isHub) {
            $appName  = 'KronoConnect';
            $appColor = '#3b5fc0';
            $appIcon  = 'shield-lock-fill';
        } else {
            $appName  = trim((string) ($row['app_name'] ?? ''));
            if ($appName === '') {
                $appName = trim((string) ($row['app_label'] ?? ''));
            }
            if ($appName === '') {
                $appName = 'Application';
            }
            $appColor = trim((string) ($row['app_color'] ?? '')) ?: '#3b5fc0';
            $appIcon  = trim((string) ($row['app_icon']  ?? '')) ?: 'app-indicator';
            if (str_starts_with($appIcon, 'bi-')) {
                $appIcon = substr($appIcon, 3);
            }
        }

        return [
            'id'         => (int) $row['id'],
            'client_id'  => $isHub ? null : (string) $clientId,
            'type'       => (string) ($row['type'] ?? 'info'),
            'title'      => (string) ($row['title']   ?? ''),
            'message'    => (string) ($row['message'] ?? ''),
            'url'        => $row['url'] ?? null,
            'read_at'    => $row['read_at'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'app_name'   => $appName,
            'app_color'  => $appColor,
            'app_icon'   => $appIcon,
            'is_hub'     => $isHub,
        ];
    }

    // ── POST /api/v1/notifications/mark-read ───────────────────────────────
    // Marque une liste de notifications (ou toutes) comme lues.

    public function markRead(): void
    {
        $this->authenticateApiRequest();

        $body = file_get_contents('php://input') ?: '';
        $data = json_decode($body, true);
        if (!is_array($data)) {
            $this->json(['error' => 'invalid_json'], 400);
        }

        $email   = trim((string) ($data['user_email'] ?? ''));
        $markAll = !empty($data['mark_all']);
        $ids     = (array) ($data['notification_ids'] ?? []);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'invalid_email'], 400);
        }

        $user = $this->users->findByEmail($email);
        if (!$user) {
            $this->json(['error' => 'user_not_found'], 404);
        }

        $userId = (int) $user['id'];
        $affected = $markAll
            ? $this->notifications->markAllRead($userId)
            : $this->notifications->markRead($userId, $ids);

        $this->json([
            'status'    => 'ok',
            'affected'  => $affected,
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
