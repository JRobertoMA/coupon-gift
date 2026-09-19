<?php

declare(strict_types=1);

abstract class Model
{
    protected PDO    $db;
    protected string $table;

    public function __construct()
    {
        $this->db = getDB();
    }

    public function find(int $id): ?array
    {
        return $this->queryOne("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function all(string $orderBy = 'id ASC'): array
    {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY $orderBy")->fetchAll();
    }

    public function delete(int $id): void
    {
        $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?")->execute([$id]);
    }

    public function query(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: null;
    }

    /** Ejecuta y devuelve el último id insertado. */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $this->db->lastInsertId();
    }

    /** Acceso al PDO para que el controlador orqueste transacciones. */
    public function pdo(): PDO
    {
        return $this->db;
    }
}
