<?php
declare(strict_types=1);

namespace KronoConnect\Core;

class Database
{
    private static ?self $instance = null;
    private \PDO $pdo;
    private string $prefix = '';

    private function __construct()
    {
        $cfg = require CONFIG_PATH . '/database.php';
        $this->prefix = $cfg['prefix'] ?? '';

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $cfg['host'],
            $cfg['port'] ?? 3306,
            $cfg['database']
        );

        $this->pdo = new \PDO(
            $dsn,
            $cfg['username'],
            $cfg['password'],
            [
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }

    /**
     * Retourne l'instance singleton Database.
     * Alias de getInstance() — conservé pour la rétrocompatibilité.
     */
    public static function getPDO(): self
    {
        return self::getInstance();
    }

    /**
     * Retourne l'instance singleton Database.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Expose l'objet PDO sous-jacent.
     * Utilisé par les services qui ont besoin d'un PDO natif (ex: UpdateService).
     */
    public function getRawPdo(): \PDO
    {
        return $this->pdo;
    }

    public function t(string $table): string
    {
        return $this->prefix . $table;
    }

    /**
     * Retourne l'ID du dernier enregistrement inséré.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    public function fetchOne(string $sql, array $params = []): array|false
    {
        return $this->query($sql, $params)->fetch();
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function insert(string $table, array $data): int
    {
        $table  = $this->t($table);
        $cols   = implode('`, `', array_keys($data));
        $places = implode(', ', array_fill(0, count($data), '?'));
        $this->query(
            "INSERT INTO `{$table}` (`{$cols}`) VALUES ({$places})",
            array_values($data)
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function update(string $table, array $data, array $where): bool
    {
        $table  = $this->t($table);
        $sets   = implode(', ', array_map(fn($k) => "`{$k}` = ?", array_keys($data)));
        $wheres = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        return $this->query(
            "UPDATE `{$table}` SET {$sets} WHERE {$wheres}",
            array_merge(array_values($data), array_values($where))
        )->rowCount() > 0;
    }

    public function delete(string $table, array $where): bool
    {
        $table  = $this->t($table);
        $wheres = implode(' AND ', array_map(fn($k) => "`{$k}` = ?", array_keys($where)));
        return $this->query(
            "DELETE FROM `{$table}` WHERE {$wheres}",
            array_values($where)
        )->rowCount() > 0;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void           { $this->pdo->commit(); }
    public function rollBack(): void         { $this->pdo->rollBack(); }
}