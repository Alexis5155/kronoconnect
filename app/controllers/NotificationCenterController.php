<?php
declare(strict_types=1);

namespace KronoConnect\Controllers;

use KronoConnect\Core\Security;
use KronoConnect\Core\Session;
use KronoConnect\Models\NotificationModel;

/**
 * Centre de notifications côté utilisateur connecté (session, pas HMAC).
 *
 * Le NotificationController (HMAC) reste dédié aux apps clientes. Ici on
 * expose au navigateur :
 *   GET  /notifications              → page historique
 *   GET  /notifications/unread       → JSON pour la cloche / dropdown
 *   POST /notifications/mark-read    → JSON, marque une ou toutes comme lues
 */
class NotificationCenterController extends BaseController
{
    private NotificationModel $notifications;

    public function __construct()
    {
        if (!Session::isLoggedIn()) {
            if ($this->isAjax()) {
                $this->json(['error' => 'unauthorized'], 401);
            }
            redirect('/login');
        }
        $this->notifications = new NotificationModel();
    }

    // ── GET /notifications ────────────────────────────────────────────────

    public function index(): void
    {
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $userId = (int) Session::userId();
        $result = $this->notifications->getHistory($userId, $page, 20);

        // GC opportuniste (1 chance sur 20) — purge les notifs lues > 60 jours.
        if (random_int(1, 20) === 1) {
            try { $this->notifications->gc(60); } catch (\Throwable) {}
        }

        $this->render('notifications/index', [
            'title'      => 'Centre de notifications',
            'activePage' => 'notifications',
            'result'     => $result,
            'useCard'    => false,
        ], 'main');
    }

    // ── GET /notifications/unread ─────────────────────────────────────────

    public function unread(): void
    {
        $userId = (int) Session::userId();
        $items  = $this->notifications->getUnreadEnriched($userId, 10);
        $count  = $this->notifications->countUnread($userId);

        // Libère le verrou de session pour le polling
        Session::close();

        $this->json([
            'unread_count'  => $count,
            'notifications' => array_map([$this, 'shapeForUi'], $items),
        ]);
    }

    // ── POST /notifications/mark-read ─────────────────────────────────────

    public function markRead(): void
    {
        Security::verifyCsrf();

        $userId = (int) Session::userId();
        $id     = $_POST['id'] ?? null;

        if ($id === 'all') {
            $affected = $this->notifications->markAllRead($userId);
        } elseif (is_numeric($id)) {
            $affected = $this->notifications->markRead($userId, [(int) $id]);
        } else {
            $this->json(['error' => 'invalid_id'], 400);
        }

        $this->json([
            'success'      => true,
            'affected'     => $affected,
            'unread_count' => $this->notifications->countUnread($userId),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Met en forme une notif pour le front : ajoute le libellé et la couleur
     * de l'app émettrice (depuis sso_clients) et nettoie les bruts SQL.
     */
    private function shapeForUi(array $row): array
    {
        // client_id NULL ⇒ émis par KronoConnect lui-même (alerte système).
        $clientId = $row['client_id'] ?? null;
        $isHub    = ($clientId === null || $clientId === '');

        if ($isHub) {
            $appName  = 'KronoConnect';
            $appColor = '#3b5fc0';
            $appIcon  = 'shield-lock-fill';
        } else {
            $appName  = trim((string) ($row['app_name'] ?? '')) !== ''
                      ? (string) $row['app_name']
                      : (string) ($row['app_label'] ?? $clientId);
            $appColor = trim((string) ($row['app_color'] ?? '')) !== ''
                      ? (string) $row['app_color']
                      : '#3b5fc0';
            $appIcon  = trim((string) ($row['app_icon'] ?? '')) !== ''
                      ? (string) $row['app_icon']
                      : 'app-indicator';
            if (str_starts_with($appIcon, 'bi-')) {
                $appIcon = substr($appIcon, 3);
            }
        }

        return [
            'id'         => (int) $row['id'],
            'client_id'  => $isHub ? null : (string) $clientId,
            'type'       => (string) ($row['type'] ?? 'info'),
            'title'      => (string) ($row['title'] ?? ''),
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

    private function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}
