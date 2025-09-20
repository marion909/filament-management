<?php

declare(strict_types=1);

namespace Filament\Models;

use PDO;

/**
 * Filament/Spool Model
 */
class FilamentSpool extends BaseModel
{
    protected string $table = 'filaments';
    
    /**
     * Find filament by UUID
     */
    public function findByUuid(string $uuid): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE uuid = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$uuid]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Find filament by NFC UID
     */
    public function findByNfcUid(string $nfcUid): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE nfc_uid = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$nfcUid]);
        
        $result = $stmt->fetch();
        return $result ?: null;
    }
    
    /**
     * Get filaments with filtering and pagination
     */
    public function getFilaments(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $offset = ($page - 1) * $limit;
        $conditions = ['is_active = 1'];
        $params = [];
        
        // Build WHERE conditions
        if (!empty($filters['material'])) {
            $conditions[] = 'material LIKE ?';
            $params[] = '%' . $filters['material'] . '%';
        }
        
        if (!empty($filters['type_id'])) {
            $conditions[] = 'type_id = ?';
            $params[] = (int)$filters['type_id'];
        }
        
        if (!empty($filters['color_id'])) {
            $conditions[] = 'color_id = ?';
            $params[] = (int)$filters['color_id'];
        }
        
        if (!empty($filters['location'])) {
            $conditions[] = 'location LIKE ?';
            $params[] = '%' . $filters['location'] . '%';
        }
        
        if (isset($filters['low_stock']) && $filters['low_stock']) {
            $conditions[] = 'remaining_weight < (total_weight * 0.2)'; // Less than 20%
        }
        
        // Build query with joins for readable names
        $sql = "
            SELECT f.*, 
                   ft.name as type_name,
                   c.name as color_name,
                   c.hex as color_hex
            FROM {$this->table} f
            LEFT JOIN filament_types ft ON f.type_id = ft.id
            LEFT JOIN colors c ON f.color_id = c.id
            WHERE " . implode(' AND ', $conditions) . "
            ORDER BY f.created_at DESC
            LIMIT {$limit} OFFSET {$offset}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $spools = $stmt->fetchAll();
        
        // Get total count for pagination
        $countSql = "SELECT COUNT(*) FROM {$this->table} WHERE " . implode(' AND ', $conditions);
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();
        
        return [
            'spools' => $spools,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'total_pages' => ceil($total / $limit)
            ]
        ];
    }
    
    /**
     * Create new filament spool
     */
    public function createSpool(array $data, int $userId): int
    {
        // Generate UUID if not provided
        if (empty($data['uuid'])) {
            $data['uuid'] = $this->generateUuid();
        }
        
        // Set defaults
        $spoolData = [
            'uuid' => $data['uuid'],
            'nfc_uid' => $data['nfc_uid'] ?? null,
            'type_id' => (int)$data['type_id'],
            'material' => $data['material'],
            'color_id' => !empty($data['color_id']) ? (int)$data['color_id'] : null,
            'total_weight' => (int)$data['total_weight'],
            'remaining_weight' => (int)($data['remaining_weight'] ?? $data['total_weight']),
            'diameter' => $data['diameter'] ?? '1.75',
            'purchase_date' => $data['purchase_date'] ?? null,
            'location' => $data['location'] ?? null,
            'batch_number' => $data['batch_number'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
            'is_active' => 1
        ];
        
        return $this->create($spoolData);
    }
    
    /**
     * Update spool weight (for usage tracking)
     */
    public function adjustWeight(int $spoolId, int $deltaGrams, int $userId): bool
    {
        $spool = $this->find($spoolId);
        if (!$spool) {
            return false;
        }
        
        $newWeight = $spool['remaining_weight'] + $deltaGrams; // Delta can be negative
        if ($newWeight < 0) {
            $newWeight = 0;
        }
        
        return $this->update($spoolId, [
            'remaining_weight' => $newWeight,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Bind NFC UID to spool
     */
    public function bindNfc(int $spoolId, string $nfcUid): bool
    {
        // Check if NFC UID is already used
        $existing = $this->findByNfcUid($nfcUid);
        if ($existing && $existing['id'] !== $spoolId) {
            throw new \Exception('NFC UID bereits an andere Spule gebunden');
        }
        
        return $this->update($spoolId, [
            'nfc_uid' => $nfcUid,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Unbind NFC UID from spool
     */
    public function unbindNfc(int $spoolId): bool
    {
        return $this->update($spoolId, [
            'nfc_uid' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get dashboard statistics
     */
    public function getDashboardStats(): array
    {
        // Total spools count
        $totalSpools = $this->count(['is_active' => 1]);
        
        // Low stock spools (less than 20% remaining)
        $lowStockSql = "
            SELECT COUNT(*) 
            FROM {$this->table} 
            WHERE is_active = 1 
            AND remaining_weight < (total_weight * 0.2)
        ";
        $lowStock = (int)$this->db->query($lowStockSql)->fetchColumn();
        
        // Total weight
        $weightSql = "
            SELECT 
                SUM(total_weight) as total_weight,
                SUM(remaining_weight) as remaining_weight
            FROM {$this->table} 
            WHERE is_active = 1
        ";
        $weightStats = $this->db->query($weightSql)->fetch();
        
        // Material distribution
        $materialSql = "
            SELECT material, COUNT(*) as count
            FROM {$this->table}
            WHERE is_active = 1
            GROUP BY material
            ORDER BY count DESC
            LIMIT 5
        ";
        $materialStats = $this->db->query($materialSql)->fetchAll();
        
        return [
            'total_spools' => $totalSpools,
            'low_stock_spools' => $lowStock,
            'total_weight' => (int)($weightStats['total_weight'] ?? 0),
            'remaining_weight' => (int)($weightStats['remaining_weight'] ?? 0),
            'used_weight' => (int)(($weightStats['total_weight'] ?? 0) - ($weightStats['remaining_weight'] ?? 0)),
            'materials' => $materialStats
        ];
    }
    
    /**
     * Generate UUID v4
     */
    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // Version 4
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // Variant
        
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}