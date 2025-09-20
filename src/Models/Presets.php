<?php

declare(strict_types=1);

namespace Filament\Models;

/**
 * Preset Models for Types, Colors, etc.
 */
class FilamentType extends BaseModel
{
    protected string $table = 'filament_types';
    
    public function getAllTypes(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY name ASC");
        return $stmt->fetchAll();
    }
}

class Color extends BaseModel
{
    protected string $table = 'colors';
    
    public function getAllColors(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY name ASC");
        return $stmt->fetchAll();
    }
}

class SpoolPreset extends BaseModel
{
    protected string $table = 'spool_presets';
    
    public function getAllPresets(): array
    {
        $stmt = $this->db->query("SELECT * FROM {$this->table} ORDER BY grams ASC");
        return $stmt->fetchAll();
    }
}