<?php

declare(strict_types=1);

namespace Filament\Models;

/**
 * Usage Log Model
 */
class UsageLog extends BaseModel
{
    protected string $table = 'usage_logs';
    
    /**
     * Log usage for a spool
     */
    public function logUsage(int $spoolId, int $usedGrams, int $userId, ?string $jobName = null, ?string $jobId = null, ?string $note = null): int
    {
        $data = [
            'filament_id' => $spoolId,
            'used_grams' => $usedGrams,
            'job_name' => $jobName,
            'job_id' => $jobId,
            'note' => $note,
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        return $this->create($data);
    }
    
    /**
     * Get usage history for a spool
     */
    public function getSpoolHistory(int $spoolId, int $limit = 50): array
    {
        $sql = "
            SELECT ul.*, u.name as user_name
            FROM {$this->table} ul
            LEFT JOIN users u ON ul.created_by = u.id
            WHERE ul.filament_id = ?
            ORDER BY ul.created_at DESC
            LIMIT {$limit}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$spoolId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get usage statistics
     */
    public function getUsageStats(int $days = 30): array
    {
        $startDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        // Daily usage
        $dailySql = "
            SELECT DATE(created_at) as date, SUM(used_grams) as grams
            FROM {$this->table}
            WHERE created_at >= ?
            GROUP BY DATE(created_at)
            ORDER BY date DESC
        ";
        
        $stmt = $this->db->prepare($dailySql);
        $stmt->execute([$startDate]);
        $dailyUsage = $stmt->fetchAll();
        
        // Total usage in period
        $totalSql = "SELECT SUM(used_grams) as total FROM {$this->table} WHERE created_at >= ?";
        $stmt = $this->db->prepare($totalSql);
        $stmt->execute([$startDate]);
        $total = (int)$stmt->fetchColumn();
        
        // Most used materials
        $materialSql = "
            SELECT f.material, SUM(ul.used_grams) as grams
            FROM {$this->table} ul
            INNER JOIN filaments f ON ul.filament_id = f.id
            WHERE ul.created_at >= ?
            GROUP BY f.material
            ORDER BY grams DESC
            LIMIT 5
        ";
        
        $stmt = $this->db->prepare($materialSql);
        $stmt->execute([$startDate]);
        $materials = $stmt->fetchAll();
        
        return [
            'daily_usage' => $dailyUsage,
            'total_used' => $total,
            'top_materials' => $materials,
            'period_days' => $days
        ];
    }
}