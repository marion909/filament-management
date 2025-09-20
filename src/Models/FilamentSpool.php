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
     * Find filament by NFC UID (backward compatibility - uses new Multiple NFC-UIDs system)
     */
    public function findByNfcUid(string $nfcUid): ?array
    {
        return $this->findByAnyNfcUid($nfcUid);
    }
    
    /**
     * Override find to include NFC-UIDs
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT f.*, ft.name as type_name, c.name as color_name, c.hex as color_hex
            FROM {$this->table} f
            LEFT JOIN filament_types ft ON f.type_id = ft.id
            LEFT JOIN colors c ON f.color_id = c.id
            WHERE f.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        if (!$result) return null;
        
        // Load NFC-UIDs
        $result['nfc_uids'] = $this->getNfcUids($id);
        // For backward compatibility, add primary NFC-UID
        $primaryNfc = array_filter($result['nfc_uids'], function($nfc) {
            return $nfc['is_primary'];
        });
        $result['nfc_uid'] = !empty($primaryNfc) ? array_values($primaryNfc)[0]['nfc_uid'] : null;
        
        return $result;
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
        
        // Load NFC-UIDs for each spool
        if (!empty($spools)) {
            foreach ($spools as &$spool) {
                $spool['nfc_uids'] = $this->getNfcUids((int)$spool['id']);
                // For backward compatibility, add primary NFC-UID
                $primaryNfc = array_filter($spool['nfc_uids'], function($nfc) {
                    return $nfc['is_primary'];
                });
                $spool['nfc_uid'] = !empty($primaryNfc) ? array_values($primaryNfc)[0]['nfc_uid'] : null;
            }
            unset($spool); // Break reference
        }
        
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
    
    // ========================================
    // MULTIPLE NFC-UIDs METHODS
    // ========================================
    
    /**
     * Get all NFC-UIDs for a specific filament
     */
    public function getNfcUids(int $filamentId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, nfc_uid, tag_type, tag_position, is_primary, created_at 
            FROM filament_nfc_uids 
            WHERE filament_id = ? 
            ORDER BY is_primary DESC, created_at ASC
        ");
        $stmt->execute([$filamentId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Add NFC-UID to filament
     */
    public function addNfcUid(int $filamentId, string $nfcUid, string $tagType = 'unknown', ?string $tagPosition = null, bool $isPrimary = false): bool
    {
        // If this is set as primary, remove primary flag from others
        if ($isPrimary) {
            $stmt = $this->db->prepare("UPDATE filament_nfc_uids SET is_primary = 0 WHERE filament_id = ?");
            $stmt->execute([$filamentId]);
        }
        
        $stmt = $this->db->prepare("
            INSERT INTO filament_nfc_uids (filament_id, nfc_uid, tag_type, tag_position, is_primary) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([$filamentId, $nfcUid, $tagType, $tagPosition, $isPrimary ? 1 : 0]);
    }
    
    /**
     * Save/Update multiple NFC-UIDs for filament
     */
    public function saveNfcUids(int $filamentId, array $nfcUids): bool
    {
        try {
            $this->db->beginTransaction();
            
            // Delete existing UIDs
            $stmt = $this->db->prepare("DELETE FROM filament_nfc_uids WHERE filament_id = ?");
            $stmt->execute([$filamentId]);
            
            // Insert new UIDs
            if (!empty($nfcUids)) {
                $stmt = $this->db->prepare("
                    INSERT INTO filament_nfc_uids (filament_id, nfc_uid, tag_type, tag_position, is_primary) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                
                $hasPrimary = false;
                foreach ($nfcUids as $uid) {
                    if (empty($uid['uid'])) continue; // Skip empty UIDs
                    
                    $isPrimary = !empty($uid['is_primary']) && !$hasPrimary;
                    if ($isPrimary) $hasPrimary = true;
                    
                    $stmt->execute([
                        $filamentId,
                        $uid['uid'],
                        $uid['tag_type'] ?? 'unknown',
                        $uid['tag_position'] ?? null,
                        $isPrimary ? 1 : 0
                    ]);
                }
                
                // Ensure at least one is primary if any exist
                if (!$hasPrimary && !empty($nfcUids)) {
                    $stmt = $this->db->prepare("UPDATE filament_nfc_uids SET is_primary = 1 WHERE filament_id = ? LIMIT 1");
                    $stmt->execute([$filamentId]);
                }
            }
            
            $this->db->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
    
    /**
     * Find filament by ANY of its NFC-UIDs (replaces old findByNfcUid)
     */
    public function findByAnyNfcUid(string $nfcUid): ?array
    {
        $stmt = $this->db->prepare("
            SELECT f.*, ft.name as type_name, c.name as color_name, c.hex as color_hex,
                   nfc.nfc_uid, nfc.tag_type, nfc.tag_position, nfc.is_primary
            FROM {$this->table} f
            LEFT JOIN filament_types ft ON f.type_id = ft.id
            LEFT JOIN colors c ON f.color_id = c.id
            INNER JOIN filament_nfc_uids nfc ON f.id = nfc.filament_id
            WHERE nfc.nfc_uid = ? AND is_active = 1 
            LIMIT 1
        ");
        $stmt->execute([$nfcUid]);
        
        $result = $stmt->fetch();
        if (!$result) return null;
        
        // Add all NFC-UIDs for this filament
        $result['nfc_uids'] = $this->getNfcUids((int)$result['id']);
        
        return $result;
    }
    
    /**
     * Update existing NFC-UID
     */
    public function updateNfcUid(int $nfcUidId, array $data): bool
    {
        $fields = [];
        $params = [];
        
        if (isset($data['nfc_uid'])) {
            $fields[] = 'nfc_uid = ?';
            $params[] = $data['nfc_uid'];
        }
        
        if (isset($data['tag_type'])) {
            $fields[] = 'tag_type = ?';
            $params[] = $data['tag_type'];
        }
        
        if (isset($data['tag_position'])) {
            $fields[] = 'tag_position = ?';
            $params[] = $data['tag_position'];
        }
        
        if (isset($data['is_primary'])) {
            // If setting as primary, first remove primary from others
            if ($data['is_primary']) {
                $getFilamentStmt = $this->db->prepare("SELECT filament_id FROM filament_nfc_uids WHERE id = ?");
                $getFilamentStmt->execute([$nfcUidId]);
                $filamentId = $getFilamentStmt->fetchColumn();
                
                if ($filamentId) {
                    $clearPrimaryStmt = $this->db->prepare("UPDATE filament_nfc_uids SET is_primary = 0 WHERE filament_id = ?");
                    $clearPrimaryStmt->execute([$filamentId]);
                }
            }
            
            $fields[] = 'is_primary = ?';
            $params[] = $data['is_primary'] ? 1 : 0;
        }
        
        if (empty($fields)) return true;
        
        $params[] = $nfcUidId;
        $sql = "UPDATE filament_nfc_uids SET " . implode(', ', $fields) . " WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }
    
    /**
     * Delete NFC-UID
     */
    public function deleteNfcUid(int $nfcUidId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM filament_nfc_uids WHERE id = ?");
        return $stmt->execute([$nfcUidId]);
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