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

        $email   = trim((string) ($data['user_email'] ?? ''));
        $type    = trim((string) ($data['type']       ?? 'info'));
        $title   = trim((string) ($data['title']      ?? ''));
        $message = trim((string) ($data['message']    ?? ''));
        $url     = isset($data['url']) && $data['url'] !== '' ? (string) $data['url'] : null;

        if ($email === '' || $title === '' || $message === '') {
            $this->json(['error' => 'missing_fields'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['error' => 'invalid_email'], 400);
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
}
