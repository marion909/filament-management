<?php

declare(strict_types=1);

namespace Filament\Models;

use PDO;
use Exception;

/**
 * Base Database Model Class
 */
abstract class BaseModel
{
    protected PDO $db;
    protected string $table;
    
    public function __construct(PDO $db)
    {
        $this->db = $db;
    }
    
    /**
     * Find record by ID
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Find all records with optional conditions
     */
    public function findAll(array $conditions = [], int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $column => $value) {
                $whereClauses[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $sql .= " LIMIT {$limit} OFFSET {$offset}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Create new record
     */
    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Update record by ID
     */
    public function update(int $id, array $data): bool
    {
        $columns = array_keys($data);
        $setClauses = array_map(fn($col) => "{$col} = ?", $columns);
        
        $sql = sprintf(
            "UPDATE %s SET %s WHERE id = ?",
            $this->table,
            implode(', ', $setClauses)
        );
        
        $params = array_values($data);
        $params[] = $id;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete record by ID
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    /**
     * Soft delete (set is_active = 0)
     */
    public function softDelete(int $id): bool
    {
        return $this->update($id, ['is_active' => 0]);
    }
    
    /**
     * Count records with optional conditions
     */
    public function count(array $conditions = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$this->table}";
        $params = [];
        
        if (!empty($conditions)) {
            $whereClauses = [];
            foreach ($conditions as $column => $value) {
                $whereClauses[] = "{$column} = ?";
                $params[] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
    }
}