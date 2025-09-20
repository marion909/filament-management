<?php

declare(strict_types=1);

namespace Filament\Models;

use PDO;

/**
 * Filament Type Model - Manages filament materials (PLA, ABS, PETG, etc.)
 */
class FilamentType extends BaseModel
{
    protected string $table = 'filament_types';
    
    /**
     * Get all filament types
     */
    public function getAllTypes(): array
    {
        return $this->getAll();
    }
    
    /**
     * Get all filament types
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT id, name, diameter, description, created_at 
            FROM {$this->table} 
            ORDER BY name ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find filament type by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, created_at 
            FROM {$this->table} 
            WHERE id = :id
        ");
        
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find filament type by name
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, description, created_at 
            FROM {$this->table} 
            WHERE name = :name
        ");
        
        $stmt->bindValue(':name', $name);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Create new filament type
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (name, description, created_at)
            VALUES (:name, :description, NOW())
        ");
        
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':description', $data['description'] ?? null);
        
        $stmt->execute();
        
        return (int)$this->db->lastInsertId();
    }

    /**
     * Create new filament type with just a name (convenience method)
     */
    public function createType(string $name): int
    {
        return $this->create(['name' => $name]);
    }
    
    /**
     * Update filament type
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        
        foreach ($data as $field => $value) {
            if (in_array($field, ['name', 'description'])) {
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
     * Delete filament type
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Check if filament type is in use
     */
    public function isInUse(int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM filaments 
            WHERE type_id = :type_id
        ");
        
        $stmt->bindValue(':type_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$stmt->fetchColumn() > 0;
    }
    
    /**
     * Get usage count for filament type
     */
    public function getUsageCount(int $id): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM filaments 
            WHERE type_id = :type_id
        ");
        
        $stmt->bindValue(':type_id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        return (int)$stmt->fetchColumn();
    }
}