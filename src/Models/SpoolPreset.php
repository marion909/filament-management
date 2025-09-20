<?php

declare(strict_types=1);

namespace Filament\Models;

use PDO;

/**
 * Spool Preset Model - Manages spool weight presets
 */
class SpoolPreset extends BaseModel
{
    protected string $table = 'spool_presets';
    
    /**
     * Get all spool presets
     */
    public function getAll(): array
    {
        $stmt = $this->db->query("
            SELECT id, name, grams 
            FROM {$this->table} 
            ORDER BY grams ASC, name ASC
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Find spool preset by ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, grams 
            FROM {$this->table} 
            WHERE id = :id
        ");
        
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Find spool preset by name
     */
    public function findByName(string $name): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, grams 
            FROM {$this->table} 
            WHERE name = :name
        ");
        
        $stmt->bindValue(':name', $name);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }
    
    /**
     * Create new spool preset
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO {$this->table} (name, grams)
            VALUES (:name, :grams)
        ");
        
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':grams', $data['grams'], PDO::PARAM_INT);
        
        $stmt->execute();
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Update spool preset
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        
        foreach ($data as $field => $value) {
            if (in_array($field, ['name', 'grams'])) {
                $fields[] = "{$field} = :{$field}";
                if ($field === 'grams') {
                    $params[":{$field}"] = (int)$value;
                } else {
                    $params[":{$field}"] = $value;
                }
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            if ($key === ':grams' || $key === ':id') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        return $stmt->execute();
    }
    
    /**
     * Delete spool preset
     */
    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        
        return $stmt->execute();
    }
    
    /**
     * Get presets grouped by weight ranges
     */
    public function getGroupedByWeight(): array
    {
        $stmt = $this->db->query("
            SELECT 
                id, name, grams,
                CASE 
                    WHEN grams <= 250 THEN 'Small (≤ 250g)'
                    WHEN grams <= 500 THEN 'Medium (251-500g)'  
                    WHEN grams <= 1000 THEN 'Large (501-1000g)'
                    ELSE 'XL (> 1000g)'
                END as weight_category
            FROM {$this->table} 
            ORDER BY grams ASC, name ASC
        ");
        
        $presets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $grouped = [];
        
        foreach ($presets as $preset) {
            $category = $preset['weight_category'];
            unset($preset['weight_category']);
            
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            
            $grouped[$category][] = $preset;
        }
        
        return $grouped;
    }
    
    /**
     * Find closest preset by weight
     */
    public function findClosestByWeight(int $grams): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, grams,
                   ABS(grams - :target_grams) as weight_diff
            FROM {$this->table} 
            ORDER BY weight_diff ASC 
            LIMIT 1
        ");
        
        $stmt->bindValue(':target_grams', $grams, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            unset($result['weight_diff']);
            return $result;
        }
        
        return null;
    }
    
    /**
     * Get statistics about preset usage
     */
    public function getUsageStats(): array
    {
        $stmt = $this->db->query("
            SELECT 
                COUNT(*) as total_presets,
                MIN(grams) as min_weight,
                MAX(grams) as max_weight,
                AVG(grams) as avg_weight,
                SUM(CASE WHEN grams <= 250 THEN 1 ELSE 0 END) as small_presets,
                SUM(CASE WHEN grams > 250 AND grams <= 500 THEN 1 ELSE 0 END) as medium_presets,
                SUM(CASE WHEN grams > 500 AND grams <= 1000 THEN 1 ELSE 0 END) as large_presets,
                SUM(CASE WHEN grams > 1000 THEN 1 ELSE 0 END) as xl_presets
            FROM {$this->table}
        ");
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            $result['avg_weight'] = round((float)$result['avg_weight'], 2);
            return $result;
        }
        
        return [
            'total_presets' => 0,
            'min_weight' => 0,
            'max_weight' => 0,
            'avg_weight' => 0,
            'small_presets' => 0,
            'medium_presets' => 0,
            'large_presets' => 0,
            'xl_presets' => 0
        ];
    }
}