<?php

declare(strict_types=1);

namespace Models;

/**
 * Generic CRUD and pagination helpers for a single database table.
 */
abstract class BaseModel
{
    /** @var \Utils\MysqliDbExtended */
    protected $db;

    protected string $table;

    protected string $primaryKey = 'id';

    public function __construct()
    {
        $this->db = db();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id, string $columns = '*'): ?array
    {
        $this->db->where($this->primaryKey, $id);
        $row = $this->db->getOne($this->table, $columns);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $conditions
     *
     * @return array<int, array<string, mixed>>
     */
    public function findWhere(array $conditions, string $columns = '*'): array
    {
        foreach ($conditions as $column => $value) {
            $this->db->where($column, $value);
        }

        return $this->db->get($this->table, null, $columns) ?: [];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        return (int) $this->db->insert($this->table, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateById(int $id, array $data): bool
    {
        $this->db->where($this->primaryKey, $id);

        return (bool) $this->db->update($this->table, $data);
    }

    /**
     * @param array<string, mixed> $queryParams
     * @param list<string> $searchFields
     *
     * @return array{data: array<int, mixed>, pagination: array<string, mixed>}
     */
    public function paginate(array $queryParams, array $searchFields, string $columns = '*'): array
    {
        $this->db->setPagination($queryParams, $searchFields);

        return $this->db->getPagination($this->table, $columns);
    }
}
