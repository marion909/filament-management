<?php

declare(strict_types=1);

namespace Filament\Controllers;

use Filament\Models\User;
use Filament\Models\FilamentType;
use Filament\Models\Color;
use Filament\Models\SpoolPreset;
use Filament\Services\AuthService;
use Filament\Services\BackupService;
use PDO;
use Exception;

/**
 * Admin Controller - Handles administrative functions
 * 
 * Provides endpoints for:
 * - User Management (CRUD operations)
 * - Preset Administration (Types, Colors, Spool Presets)
 * - System Administration (Backups, Settings)
 * - Activity Monitoring
 */
class AdminController
{
    private User $userModel;
    private FilamentType $typeModel;
    private Color $colorModel; 
    private SpoolPreset $presetModel;
    private AuthService $authService;
    private BackupService $backupService;
    private PDO $db;
    
    public function __construct(
        User $userModel,
        FilamentType $typeModel,
        Color $colorModel,
        SpoolPreset $presetModel,
        AuthService $authService,
        BackupService $backupService,
        PDO $db
    ) {
        $this->userModel = $userModel;
        $this->typeModel = $typeModel;
        $this->colorModel = $colorModel;
        $this->presetModel = $presetModel;
        $this->authService = $authService;
        $this->backupService = $backupService;
        $this->db = $db;
    }

    // === User Management ===
    
    /**
     * Get all users with pagination and filtering
     */
    public function getUsers(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = min(100, max(10, (int)($_GET['limit'] ?? 20)));
            $search = $_GET['search'] ?? '';
            $role = $_GET['role'] ?? '';
            $status = $_GET['status'] ?? '';
            
            $offset = ($page - 1) * $limit;
            
            // Build query with filters
            $whereClause = [];
            $params = [];
            
            if ($search) {
                $whereClause[] = "(name LIKE :search OR email LIKE :search)";
                $params['search'] = "%{$search}%";
            }
            
            if ($role) {
                $whereClause[] = "role = :role";
                $params['role'] = $role;
            }
            
            if ($status === 'active') {
                $whereClause[] = "verified_at IS NOT NULL";
            } elseif ($status === 'unverified') {
                $whereClause[] = "verified_at IS NULL";
            }
            
            $where = $whereClause ? 'WHERE ' . implode(' AND ', $whereClause) : '';
            
            // Get users
            $sql = "SELECT id, name, email, role, created_at, verified_at, last_login_at 
                    FROM users {$where} 
                    ORDER BY created_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get total count
            $countSql = "SELECT COUNT(*) FROM users {$where}";
            $countStmt = $this->db->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue(":{$key}", $value);
            }
            $countStmt->execute();
            $total = (int)$countStmt->fetchColumn();
            
            $this->jsonResponse([
                'users' => $users,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => $total,
                    'pages' => (int)ceil($total / $limit)
                ]
            ]);
            
        } catch (Exception $e) {
            error_log("Admin getUsers error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Get single user details
     */
    public function getUser(int $userId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $user = $this->userModel->findById($userId);
            
            if (!$user) {
                $this->jsonResponse(['error' => 'User not found'], 404);
                return;
            }
            
            // Get user statistics
            $stats = $this->getUserStats($userId);
            
            $this->jsonResponse([
                'user' => $user,
                'stats' => $stats
            ]);
            
        } catch (Exception $e) {
            error_log("Admin getUser error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Update user details and permissions
     */
    public function updateUser(int $userId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $data = $this->getJsonInput();
            
            $currentUser = $this->authService->getCurrentUser();
            
            // Prevent admins from demoting themselves
            if ($userId === $currentUser['id'] && isset($data['role']) && $data['role'] !== 'admin') {
                $this->jsonResponse(['error' => 'Cannot change your own role'], 400);
                return;
            }
            
            $allowedFields = ['name', 'email', 'role'];
            $updateData = [];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
            
            if (empty($updateData)) {
                $this->jsonResponse(['error' => 'No valid fields to update'], 400);
                return;
            }
            
            // Validate role
            if (isset($updateData['role']) && !in_array($updateData['role'], ['user', 'admin'])) {
                $this->jsonResponse(['error' => 'Invalid role'], 400);
                return;
            }
            
            // Validate email uniqueness
            if (isset($updateData['email'])) {
                $existing = $this->userModel->findByEmail($updateData['email']);
                if ($existing && $existing['id'] !== $userId) {
                    $this->jsonResponse(['error' => 'Email already exists'], 409);
                    return;
                }
            }
            
            $success = $this->userModel->update($userId, $updateData);
            
            if ($success) {
                $user = $this->userModel->findById($userId);
                $this->jsonResponse(['user' => $user]);
            } else {
                $this->jsonResponse(['error' => 'Update failed'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Admin updateUser error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Delete/deactivate user
     */
    public function deleteUser(int $userId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $currentUser = $this->authService->getCurrentUser();
            
            // Prevent admins from deleting themselves
            if ($userId === $currentUser['id']) {
                $this->jsonResponse(['error' => 'Cannot delete your own account'], 400);
                return;
            }
            
            $user = $this->userModel->findById($userId);
            if (!$user) {
                $this->jsonResponse(['error' => 'User not found'], 404);
                return;
            }
            
            // Soft delete by setting verified_at to NULL and adding deleted prefix
            $deletedEmail = 'deleted_' . time() . '_' . $user['email'];
            
            $success = $this->userModel->update($userId, [
                'email' => $deletedEmail,
                'verified_at' => null,
                'name' => '[DELETED] ' . $user['name']
            ]);
            
            if ($success) {
                $this->jsonResponse(['message' => 'User deleted']);
            } else {
                $this->jsonResponse(['error' => 'Delete failed'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Admin deleteUser error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    // === Preset Management ===
    
    /**
     * Get all filament types
     */
    public function getFilamentTypes(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $types = $this->typeModel->getAll();
            $this->jsonResponse(['types' => $types]);
            
        } catch (Exception $e) {
            error_log("Admin getFilamentTypes error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Create new filament type
     */
    public function createFilamentType(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $data = $this->getJsonInput();
            
            if (empty($data['name'])) {
                $this->jsonResponse(['error' => 'Name is required'], 400);
                return;
            }
            
            $typeData = [
                'name' => trim($data['name']),
                'description' => $data['description'] ?? null
            ];
            
            $typeId = $this->typeModel->create($typeData);
            
            if ($typeId) {
                $type = $this->typeModel->findById($typeId);
                $this->jsonResponse(['type' => $type], 201);
            } else {
                $this->jsonResponse(['error' => 'Create failed'], 500);
            }
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->jsonResponse(['error' => 'Type name already exists'], 409);
            } else {
                error_log("Admin createFilamentType error: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Database error'], 500);
            }
        }
    }
    
    /**
     * Update filament type
     */
    public function updateFilamentType(int $typeId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $data = $this->getJsonInput();
            
            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['description'])) {
                $updateData['description'] = $data['description'];
            }
            
            if (empty($updateData)) {
                $this->jsonResponse(['error' => 'No fields to update'], 400);
                return;
            }
            
            $success = $this->typeModel->update($typeId, $updateData);
            
            if ($success) {
                $type = $this->typeModel->findById($typeId);
                $this->jsonResponse(['type' => $type]);
            } else {
                $this->jsonResponse(['error' => 'Update failed'], 500);
            }
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->jsonResponse(['error' => 'Type name already exists'], 409);
            } else {
                error_log("Admin updateFilamentType error: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Database error'], 500);
            }
        }
    }
    
    /**
     * Delete filament type
     */
    public function deleteFilamentType(int $typeId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            // Check if type is in use
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM filaments WHERE type_id = :type_id");
            $stmt->bindValue(':type_id', $typeId, PDO::PARAM_INT);
            $stmt->execute();
            $usage = (int)$stmt->fetchColumn();
            
            if ($usage > 0) {
                $this->jsonResponse(['error' => "Cannot delete type: {$usage} spools are using this type"], 409);
                return;
            }
            
            $success = $this->typeModel->delete($typeId);
            
            if ($success) {
                $this->jsonResponse(['message' => 'Type deleted']);
            } else {
                $this->jsonResponse(['error' => 'Delete failed'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Admin deleteFilamentType error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Get all colors
     */
    public function getColors(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $colors = $this->colorModel->getAll();
            $this->jsonResponse(['colors' => $colors]);
            
        } catch (Exception $e) {
            error_log("Admin getColors error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Create new color
     */
    public function createColor(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $data = $this->getJsonInput();
            
            if (empty($data['name'])) {
                $this->jsonResponse(['error' => 'Name is required'], 400);
                return;
            }
            
            $colorData = [
                'name' => trim($data['name']),
                'hex_code' => $data['hex_code'] ?? null
            ];
            
            // Validate hex code format
            if ($colorData['hex_code'] && !preg_match('/^#[0-9A-Fa-f]{6}$/', $colorData['hex_code'])) {
                $this->jsonResponse(['error' => 'Invalid hex color code'], 400);
                return;
            }
            
            $colorId = $this->colorModel->create($colorData);
            
            if ($colorId) {
                $color = $this->colorModel->findById($colorId);
                $this->jsonResponse(['color' => $color], 201);
            } else {
                $this->jsonResponse(['error' => 'Create failed'], 500);
            }
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->jsonResponse(['error' => 'Color name already exists'], 409);
            } else {
                error_log("Admin createColor error: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Database error'], 500);
            }
        }
    }
    
    /**
     * Update color
     */
    public function updateColor(int $colorId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $data = $this->getJsonInput();
            
            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['hex_code'])) {
                $updateData['hex_code'] = $data['hex_code'];
                
                // Validate hex code format
                if ($updateData['hex_code'] && !preg_match('/^#[0-9A-Fa-f]{6}$/', $updateData['hex_code'])) {
                    $this->jsonResponse(['error' => 'Invalid hex color code'], 400);
                    return;
                }
            }
            
            if (empty($updateData)) {
                $this->jsonResponse(['error' => 'No fields to update'], 400);
                return;
            }
            
            $success = $this->colorModel->update($colorId, $updateData);
            
            if ($success) {
                $color = $this->colorModel->findById($colorId);
                $this->jsonResponse(['color' => $color]);
            } else {
                $this->jsonResponse(['error' => 'Update failed'], 500);
            }
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->jsonResponse(['error' => 'Color name already exists'], 409);
            } else {
                error_log("Admin updateColor error: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Database error'], 500);
            }
        }
    }
    
    /**
     * Delete color
     */
    public function deleteColor(int $colorId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            // Check if color is in use
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM filaments WHERE color_id = :color_id");
            $stmt->bindValue(':color_id', $colorId, PDO::PARAM_INT);
            $stmt->execute();
            $usage = (int)$stmt->fetchColumn();
            
            if ($usage > 0) {
                $this->jsonResponse(['error' => "Cannot delete color: {$usage} spools are using this color"], 409);
                return;
            }
            
            $success = $this->colorModel->delete($colorId);
            
            if ($success) {
                $this->jsonResponse(['message' => 'Color deleted']);
            } else {
                $this->jsonResponse(['error' => 'Delete failed'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Admin deleteColor error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Get all spool presets
     */
    public function getSpoolPresets(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $presets = $this->presetModel->getAll();
            $this->jsonResponse(['presets' => $presets]);
            
        } catch (Exception $e) {
            error_log("Admin getSpoolPresets error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Create spool preset
     */
    public function createSpoolPreset(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $data = $this->getJsonInput();
            
            if (empty($data['name']) || !isset($data['grams'])) {
                $this->jsonResponse(['error' => 'Name and grams are required'], 400);
                return;
            }
            
            $presetData = [
                'name' => trim($data['name']),
                'grams' => (int)$data['grams']
            ];
            
            if ($presetData['grams'] <= 0) {
                $this->jsonResponse(['error' => 'Grams must be positive'], 400);
                return;
            }
            
            $presetId = $this->presetModel->create($presetData);
            
            if ($presetId) {
                $preset = $this->presetModel->findById($presetId);
                $this->jsonResponse(['preset' => $preset], 201);
            } else {
                $this->jsonResponse(['error' => 'Create failed'], 500);
            }
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->jsonResponse(['error' => 'Preset name already exists'], 409);
            } else {
                error_log("Admin createSpoolPreset error: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Database error'], 500);
            }
        }
    }
    
    /**
     * Update spool preset
     */
    public function updateSpoolPreset(int $presetId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $data = $this->getJsonInput();
            
            $updateData = [];
            if (isset($data['name'])) {
                $updateData['name'] = trim($data['name']);
            }
            if (isset($data['grams'])) {
                $updateData['grams'] = (int)$data['grams'];
                
                if ($updateData['grams'] <= 0) {
                    $this->jsonResponse(['error' => 'Grams must be positive'], 400);
                    return;
                }
            }
            
            if (empty($updateData)) {
                $this->jsonResponse(['error' => 'No fields to update'], 400);
                return;
            }
            
            $success = $this->presetModel->update($presetId, $updateData);
            
            if ($success) {
                $preset = $this->presetModel->findById($presetId);
                $this->jsonResponse(['preset' => $preset]);
            } else {
                $this->jsonResponse(['error' => 'Update failed'], 500);
            }
            
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                $this->jsonResponse(['error' => 'Preset name already exists'], 409);
            } else {
                error_log("Admin updateSpoolPreset error: " . $e->getMessage());
                $this->jsonResponse(['error' => 'Database error'], 500);
            }
        }
    }
    
    /**
     * Delete spool preset
     */
    public function deleteSpoolPreset(int $presetId): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $success = $this->presetModel->delete($presetId);
            
            if ($success) {
                $this->jsonResponse(['message' => 'Preset deleted']);
            } else {
                $this->jsonResponse(['error' => 'Delete failed'], 500);
            }
            
        } catch (Exception $e) {
            error_log("Admin deleteSpoolPreset error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    // === System Administration ===
    
    /**
     * Get system statistics and health info
     */
    public function getSystemStats(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $stats = [];
            
            // User statistics
            $stmt = $this->db->query("SELECT 
                COUNT(*) as total_users,
                COUNT(CASE WHEN verified_at IS NOT NULL THEN 1 END) as verified_users,
                COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_users
                FROM users");
            $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Spool statistics
            $stmt = $this->db->query("SELECT 
                COUNT(*) as total_spools,
                COUNT(CASE WHEN remaining_weight > 0 THEN 1 END) as active_spools,
                SUM(total_weight) as total_material_grams,
                SUM(remaining_weight) as remaining_material_grams
                FROM filaments");
            $stats['spools'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Usage statistics
            $stmt = $this->db->query("SELECT 
                COUNT(*) as total_adjustments,
                SUM(ABS(delta_grams)) as total_usage_grams
                FROM usage_logs
                WHERE delta_grams < 0");
            $stats['usage'] = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // System info
            $stats['system'] = [
                'php_version' => PHP_VERSION,
                'database_size' => $this->getDatabaseSize(),
                'uptime' => $this->getSystemUptime(),
                'disk_usage' => $this->getDiskUsage()
            ];
            
            $this->jsonResponse(['stats' => $stats]);
            
        } catch (Exception $e) {
            error_log("Admin getSystemStats error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Database error'], 500);
        }
    }
    
    /**
     * Create database backup
     */
    public function createBackup(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $backupResult = $this->backupService->createBackup();
            
            if ($backupResult['success']) {
                $this->jsonResponse([
                    'message' => 'Backup created successfully',
                    'filename' => $backupResult['filename'],
                    'size' => $backupResult['size']
                ]);
            } else {
                $this->jsonResponse([
                    'error' => $backupResult['error']
                ], 500);
            }
            
        } catch (Exception $e) {
            error_log("Admin createBackup error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Backup failed'], 500);
        }
    }
    
    /**
     * Get backup list
     */
    public function getBackups(): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $backups = $this->backupService->listBackups();
            $this->jsonResponse(['backups' => $backups]);
            
        } catch (Exception $e) {
            error_log("Admin getBackups error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Failed to list backups'], 500);
        }
    }
    
    /**
     * Download backup file
     */
    public function downloadBackup(string $filename): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $result = $this->backupService->downloadBackup($filename);
            
            if ($result['success']) {
                header('Content-Type: application/gzip');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . $result['size']);
                readfile($result['path']);
                exit;
            } else {
                $this->jsonResponse(['error' => $result['error']], 404);
            }
            
        } catch (Exception $e) {
            error_log("Admin downloadBackup error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Download failed'], 500);
        }
    }
    
    /**
     * Delete backup file
     */
    public function deleteBackup(string $filename): void
    {
        if (!$this->requireAdmin()) return;
        
        try {
            $result = $this->backupService->deleteBackup($filename);
            
            if ($result['success']) {
                $this->jsonResponse(['message' => 'Backup deleted']);
            } else {
                $this->jsonResponse(['error' => $result['error']], 400);
            }
            
        } catch (Exception $e) {
            error_log("Admin deleteBackup error: " . $e->getMessage());
            $this->jsonResponse(['error' => 'Delete failed'], 500);
        }
    }
    
    // === Helper Methods ===
    
    /**
     * Require admin authentication
     */
    private function requireAdmin(): bool
    {
        $user = $this->authService->getCurrentUser();
        
        if (!$user) {
            $this->jsonResponse(['error' => 'Authentication required'], 401);
            return false;
        }
        
        if ($user['role'] !== 'admin') {
            $this->jsonResponse(['error' => 'Admin access required'], 403);
            return false;
        }
        
        return true;
    }
    
    /**
     * Get user statistics
     */
    private function getUserStats(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT 
            COUNT(*) as total_spools,
            SUM(total_weight) as total_weight,
            SUM(remaining_weight) as remaining_weight
            FROM filaments 
            WHERE created_by = :user_id");
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_spools' => 0,
            'total_weight' => 0,
            'remaining_weight' => 0
        ];
    }
    
    /**
     * Get database size in MB
     */
    private function getDatabaseSize(): float
    {
        try {
            $stmt = $this->db->query("SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (float)($result['size_mb'] ?? 0);
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get system uptime
     */
    private function getSystemUptime(): string
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                $seconds = (int)floatval($uptime);
                return $this->formatUptime($seconds);
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Format uptime seconds to readable string
     */
    private function formatUptime(int $seconds): string
    {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return sprintf('%d days, %d hours, %d minutes', $days, $hours, $minutes);
    }
    
    /**
     * Get disk usage percentage
     */
    private function getDiskUsage(): array
    {
        $path = __DIR__ . '/../../';
        
        $bytes = disk_total_space($path);
        $free = disk_free_space($path);
        
        if ($bytes !== false && $free !== false) {
            $used = $bytes - $free;
            return [
                'total' => $bytes,
                'used' => $used,
                'free' => $free,
                'percentage' => round(($used / $bytes) * 100, 2)
            ];
        }
        
        return [
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'percentage' => 0
        ];
    }
    
    /**
     * Get JSON input data
     */
    private function getJsonInput(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['error' => 'Invalid JSON'], 400);
            exit;
        }
        
        return $data ?? [];
    }
    
    /**
     * Send JSON response
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}