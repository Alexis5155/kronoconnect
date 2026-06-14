<?php
declare(strict_types=1);

namespace KronoConnect\Models;

use KronoConnect\Core\Database;

class CustomLinkModel extends BaseModel
{
    private string $table;
    private string $tGroupAccess;
    private string $tUserAccess;

    public function __construct()
    {
        parent::__construct();
        $this->table = $this->db->t('custom_links');
        $this->tGroupAccess = $this->db->t('custom_link_group_access');
        $this->tUserAccess = $this->db->t('custom_link_user_access');
    }

    public function all(): array
    {
        return $this->db->fetchAll("SELECT * FROM `$this->table` ORDER BY title ASC");
    }

    public function find(int $id): ?array
    {
        return $this->db->fetchOne("SELECT * FROM `$this->table` WHERE id = ?", [$id]);
    }

    public function create(array $data): int
    {
        $this->db->query(
            "INSERT INTO `$this->table` (title, url, icon, color, description, access_mode) VALUES (?, ?, ?, ?, ?, ?)",
            [
                $data['title'],
                $data['url'],
                $data['icon'] ?? 'link-45deg',
                $data['color'] ?? '#3b5fc0',
                $data['description'] ?? null,
                $data['access_mode'] ?? 'open'
            ]
        );
        return (int)$this->db->getRawPdo()->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $this->db->query(
            "UPDATE `$this->table` SET title = ?, url = ?, icon = ?, color = ?, description = ?, access_mode = ? WHERE id = ?",
            [
                $data['title'],
                $data['url'],
                $data['icon'],
                $data['color'],
                $data['description'],
                $data['access_mode'],
                $id
            ]
        );
    }

    public function delete(int $id): void
    {
        $this->db->query("DELETE FROM `$this->table` WHERE id = ?", [$id]);
    }

    public function getGroupAccess(int $linkId): array
    {
        return $this->db->fetchAll(
            "SELECT group_id FROM `$this->tGroupAccess` WHERE link_id = ?",
            [$linkId],
            \PDO::FETCH_COLUMN
        );
    }

    public function setGroupAccess(int $linkId, array $groupIds): void
    {
        $this->db->query("DELETE FROM `$this->tGroupAccess` WHERE link_id = ?", [$linkId]);
        foreach ($groupIds as $groupId) {
            $this->db->query(
                "INSERT INTO `$this->tGroupAccess` (link_id, group_id) VALUES (?, ?)",
                [$linkId, $groupId]
            );
        }
    }

    public function getUserAccess(int $linkId): array
    {
        return $this->db->fetchAll(
            "SELECT user_id FROM `$this->tUserAccess` WHERE link_id = ?",
            [$linkId],
            \PDO::FETCH_COLUMN
        );
    }

    public function setUserAccess(int $linkId, array $userIds): void
    {
        $this->db->query("DELETE FROM `$this->tUserAccess` WHERE link_id = ?", [$linkId]);
        foreach ($userIds as $userId) {
            $this->db->query(
                "INSERT INTO `$this->tUserAccess` (link_id, user_id) VALUES (?, ?)",
                [$linkId, $userId]
            );
        }
    }

    /**
     * Retourne les liens accessibles pour un utilisateur donné.
     */
    public function getForUser(int $userId, string $role): array
    {
        if ($role === 'super_admin') {
            return $this->all();
        }

        $tGroupMembers = $this->db->t('group_members');

        return $this->db->fetchAll("
            SELECT DISTINCT l.*
            FROM `$this->table` l
            LEFT JOIN `$this->tUserAccess` lua ON l.id = lua.link_id AND lua.user_id = ?
            LEFT JOIN `$this->tGroupAccess` lga ON l.id = lga.link_id
            LEFT JOIN `$tGroupMembers` gm ON lga.group_id = gm.group_id AND gm.user_id = ?
            WHERE l.access_mode = 'open'
               OR (l.access_mode = 'manual' AND lua.user_id IS NOT NULL)
               OR (l.access_mode = 'group' AND gm.user_id IS NOT NULL)
            ORDER BY l.title ASC
        ", [$userId, $userId]);
    }
}
