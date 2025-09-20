<?php

declare(strict_types=1);

namespace Filament\Models;

use PDO;

/**
 * Color Model - Manages filament colors
 */
class Color extends BaseModel
{
    protected string $table = 'colors';
    
    /**
     * Get all colors
     */
    public function getAllColors(): array
    {
        return $this->getAll();
    }
    
    /**
     * Get all colors
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT id, name, hex, created_at 
            FROM {$this->table} 
            ORDER BY name ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find color by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, hex, created_at 
            FROM {$this->table} 
            WHERE id = :id
        ");
        
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find color by name
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, hex, created_at 
            FROM {$this->table} 
            WHERE name = :name
        ");
        
        $stmt->bindValue(':name', $name);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Create new color
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (name, hex, created_at)
            VALUES (:name, :hex, NOW())
        ");

        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':hex', $data['hex'] ?? null);

        $stmt->execute();

        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Create new color by name only
     */
    public function createColor(string $name): int
    {
        return $this->create(['name' => $name]);
    }    /**
     * Update color
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        
        foreach ($data as $field => $value) {
            if (in_array($field, ['name', 'hex'])) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        return $stmt->execute();
    }
    
    /**
     * Delete color
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Check if color is in use
     */
    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM filaments 
            WHERE color_id = :color_id
        ");
        
        $stmt->bindValue(':color_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$stmt->fetchColumn() > 0;
    }
    
    /**
     * Get usage count for color
     */
    public function getUsageCount(int $id): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM filaments 
            WHERE color_id = :color_id
        ");
        
        $stmt->bindValue(':color_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$stmt->fetchColumn();
    }
}