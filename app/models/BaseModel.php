<?php
declare(strict_types=1);

namespace KronoConnect\Models;

use KronoConnect\Core\Database;

abstract class BaseModel
{
    protected Database $db;
    protected string $table = '';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    protected function find(int $id): ?array
    {
        $t = $this->db->t($this->table);
        return $this->db->fetchOne(
            "SELECT * FROM `{$t}` WHERE `id` = ?",
            [$id]
        ) ?: null;
    }

    protected function findBy(string $column, mixed $value): ?array
    {
        $t = $this->db->t($this->table);
        return $this->db->fetchOne(
            "SELECT * FROM `{$t}` WHERE `{$column}` = ?",
            [$value]
        ) ?: null;
    }
}
