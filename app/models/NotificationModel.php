<?php
declare(strict_types=1);

namespace KronoConnect\Models;

/**
 * Hub de notifications centralisé : agrège les notifications destinées
 * à un utilisateur, émises par n'importe quelle instance cliente SSO.
 */
class NotificationModel extends BaseModel
{
    protected string $table = 'notifications';

    /** Caps anti-DoS appliqués au moment de l'écriture. */
    public const MAX_TITLE_LEN   = 255;
    public const MAX_MESSAGE_LEN = 10000;
    public const MAX_URL_LEN     = 500;

    /**
     * Valide une URL de notification : seuls les chemins relatifs (/...) et
     * les URLs absolues http/https sont autorisés. Bloque `javascript:`,
     * `data:`, etc. — surface XSS principale via l'attribut `href`.
     *
     * @return string|null  L'URL trimée si valide, null si vide, ou false-equivalent
     *                      en cas d'invalidité (à traiter par l'appelant).
     */
    public static function sanitizeUrl(?string $url): string|null|false
    {
        if ($url === null) { return null; }
        $url = trim($url);
        if ($url === '') { return null; }

        if (mb_strlen($url) > self::MAX_URL_LEN) {
            return false;
        }

        // Chemin relatif : on le convertit en absolu pour qu'il soit valide depuis les apps clientes.
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            return function_exists('url') ? url($url) : $url;
        }

        // URL absolue : http/https uniquement.
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (is_string($scheme) && in_array(strtolower($scheme), ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false) {
            return $url;
        }

        return false;
    }

    /**
     * Crée une notification pour un utilisateur, émise par un client SSO donné.
     *
     * @return int  L'ID de la notification créée
     */
    /**
     * Crée une notification pour un utilisateur.
     *
     * @param string|null $clientId  client_id de l'app émettrice, ou NULL
     *                               si la notification est émise par
     *                               KronoConnect lui-même (alertes système).
     * @return int  L'ID de la notification créée
     */
    public function create(
        int $userId,
        ?string $clientId,
        string $type,
        string $title,
        string $message,
        ?string $url = null
    ): int {
        return $this->db->insert('notifications', [
            'user_id'   => $userId,
            'client_id' => $clientId,
            'is_hub'    => $clientId === null ? 1 : 0,
            'type'      => $type,
            'title'     => $title,
            'message'   => $message,
            'url'       => $url,
        ]);
    }

    /**
     * Récupère les notifications non lues d'un utilisateur (tous clients confondus).
     *
     * @return array<int,array<string,mixed>>
     */
    public function getUnread(int $userId, int $limit = 20): array
    {
        $t = $this->db->t($this->table);
        $limit = max(1, min(100, $limit));

        $stmt = $this->db->getRawPdo()->prepare(
            "SELECT id, user_id, client_id, is_hub, type, title, message, url, read_at, created_at
             FROM `{$t}`
             WHERE user_id = ? AND read_at IS NULL
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Liste enrichie pour le centre web : joint sso_clients pour exposer
     * le nom et la couleur de l'application émettrice.
     *
     * @return array<int,array<string,mixed>>
     */
    public function getUnreadEnriched(int $userId, int $limit = 20): array
    {
        $t  = $this->db->t($this->table);
        $tc = $this->db->t('sso_clients');
        $limit = max(1, min(100, $limit));

        $stmt = $this->db->getRawPdo()->prepare(
            "SELECT n.id, n.client_id, n.is_hub, n.type, n.title, n.message, n.url, n.read_at, n.created_at,
                    c.name AS app_label, c.app_name, c.app_color, c.app_icon
             FROM `{$t}` n
             LEFT JOIN `{$tc}` c ON c.client_id = n.client_id
             WHERE n.user_id = ? AND n.read_at IS NULL
             ORDER BY n.created_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    /**
     * Historique paginé (lues + non lues), joint avec sso_clients.
     *
     * @return array{rows: array<int,array<string,mixed>>, total:int, page:int, perPage:int, totalPages:int}
     */
    public function getHistory(int $userId, int $page = 1, int $perPage = 20): array
    {
        $t  = $this->db->t($this->table);
        $tc = $this->db->t('sso_clients');
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $total = (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM `{$t}` WHERE user_id = ?",
            [$userId]
        )['n'] ?? 0);

        $stmt = $this->db->getRawPdo()->prepare(
            "SELECT n.id, n.client_id, n.is_hub, n.type, n.title, n.message, n.url, n.read_at, n.created_at,
                    c.name AS app_label, c.app_name, c.app_color, c.app_icon
             FROM `{$t}` n
             LEFT JOIN `{$tc}` c ON c.client_id = n.client_id
             WHERE n.user_id = ?
             ORDER BY n.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
        $stmt->execute([$userId]);

        return [
            'rows'       => $stmt->fetchAll(),
            'total'      => $total,
            'page'       => $page,
            'perPage'    => $perPage,
            'totalPages' => (int) max(1, ceil($total / $perPage)),
        ];
    }

    /**
     * Compte les notifications non lues d'un utilisateur.
     */
    public function countUnread(int $userId): int
    {
        $t = $this->db->t($this->table);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS n FROM `{$t}` WHERE user_id = ? AND read_at IS NULL",
            [$userId]
        );
        return (int) ($row['n'] ?? 0);
    }

    /**
     * Marque une liste de notifications comme lues, en s'assurant qu'elles appartiennent au user.
     * Retourne le nombre de lignes affectées.
     */
    public function markRead(int $userId, array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($i) => $i > 0)));
        if (empty($ids)) {
            return 0;
        }
        $t = $this->db->t($this->table);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$userId], $ids);

        return $this->db->query(
            "UPDATE `{$t}` SET read_at = NOW()
             WHERE user_id = ? AND read_at IS NULL AND id IN ({$placeholders})",
            $params
        )->rowCount();
    }

    /**
     * Marque toutes les notifications non lues comme lues pour un utilisateur.
     * Retourne le nombre de lignes affectées.
     */
    public function markAllRead(int $userId): int
    {
        $t = $this->db->t($this->table);
        return $this->db->query(
            "UPDATE `{$t}` SET read_at = NOW() WHERE user_id = ? AND read_at IS NULL",
            [$userId]
        )->rowCount();
    }

    /**
     * Purge les notifications déjà lues plus anciennes que $days jours.
     * À appeler périodiquement (CRON ou opportuniste).
     *
     * @return int  Nombre de lignes supprimées.
     */
    public function gc(int $days = 60): int
    {
        $days = max(7, $days);
        $t = $this->db->t($this->table);
        return $this->db->query(
            "DELETE FROM `{$t}`
              WHERE read_at IS NOT NULL
                AND read_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)"
        )->rowCount();
    }
}
