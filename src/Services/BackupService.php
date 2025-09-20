<?php

declare(strict_types=1);

namespace Filament\Services;

use PDO;
use Exception;

/**
 * Backup Service - Handles database backups and restoration
 * 
 * Features:
 * - Create compressed database dumps
 * - List available backups
 * - Delete old backups
 * - Backup verification
 * - Rotation management
 */
class BackupService
{
    private PDO $db;
    private array $config;
    private string $backupDir;
    
    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
        $this->backupDir = __DIR__ . '/../../storage/backups/';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0755, true);
        }
    }
    
    /**
     * Create a new database backup
     */
    public function createBackup(): array
    {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "filament_backup_{$timestamp}.sql.gz";
            $filepath = $this->backupDir . $filename;
            
            // Get database name from config or connection
            $dbName = $this->config['database']['name'] ?? 'filament_management';
            
            // Create SQL dump
            $sqlContent = $this->createSqlDump($dbName);
            
            if (empty($sqlContent)) {
                return [
                    'success' => false,
                    'error' => 'Failed to create SQL dump'
                ];
            }
            
            // Compress and save
            $compressed = gzcompress($sqlContent, 9);
            
            if ($compressed === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to compress backup'
                ];
            }
            
            $bytesWritten = file_put_contents($filepath, $compressed);
            
            if ($bytesWritten === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to write backup file'
                ];
            }
            
            // Verify backup
            if (!$this->verifyBackup($filepath)) {
                unlink($filepath);
                return [
                    'success' => false,
                    'error' => 'Backup verification failed'
                ];
            }
            
            // Clean old backups (keep last 10)
            $this->cleanOldBackups(10);
            
            return [
                'success' => true,
                'filename' => $filename,
                'size' => $bytesWritten,
                'path' => $filepath
            ];
            
        } catch (Exception $e) {
            error_log("Backup creation failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Backup creation failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Create SQL dump of all tables
     */
    private function createSqlDump(string $dbName): string
    {
        $sql = '';
        
        // Add header comments
        $sql .= "-- Filament Management System Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- Database: {$dbName}\n\n";
        
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n";
        $sql .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
        $sql .= "SET time_zone = \"+00:00\";\n\n";
        
        try {
            // Get all tables
            $stmt = $this->db->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                $sql .= $this->dumpTable($table);
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            
            return $sql;
            
        } catch (Exception $e) {
            error_log("SQL dump failed: " . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Dump single table structure and data
     */
    private function dumpTable(string $tableName): string
    {
        $sql = "\n-- Table: {$tableName}\n";
        
        try {
            // Drop table statement
            $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            
            // Create table statement
            $stmt = $this->db->query("SHOW CREATE TABLE `{$tableName}`");
            $createTable = $stmt->fetch(PDO::FETCH_ASSOC);
            $sql .= $createTable['Create Table'] . ";\n\n";
            
            // Table data
            $stmt = $this->db->query("SELECT * FROM `{$tableName}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                // Get column names
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                $sql .= "INSERT INTO `{$tableName}` ({$columnList}) VALUES\n";
                
                $insertRows = [];
                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $this->db->quote((string)$value);
                        }
                    }
                    $insertRows[] = '(' . implode(', ', $values) . ')';
                }
                
                $sql .= implode(",\n", $insertRows) . ";\n\n";
            }
            
            return $sql;
            
        } catch (Exception $e) {
            error_log("Table dump failed for {$tableName}: " . $e->getMessage());
            return "-- Error dumping table {$tableName}: " . $e->getMessage() . "\n\n";
        }
    }
    
    /**
     * List all available backups
     */
    public function listBackups(): array
    {
        $backups = [];
        
        try {
            $files = glob($this->backupDir . '*.sql.gz');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $stats = stat($file);
                
                $backups[] = [
                    'filename' => $filename,
                    'size' => $stats['size'],
                    'size_formatted' => $this->formatBytes($stats['size']),
                    'created' => date('Y-m-d H:i:s', $stats['mtime']),
                    'created_timestamp' => $stats['mtime']
                ];
            }
            
            // Sort by creation time (newest first)
            usort($backups, function($a, $b) {
                return $b['created_timestamp'] - $a['created_timestamp'];
            });
            
            return $backups;
            
        } catch (Exception $e) {
            error_log("List backups failed: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Download backup file
     */
    public function downloadBackup(string $filename): array
    {
        $filepath = $this->backupDir . $filename;
        
        // Validate filename (security check)
        if (!preg_match('/^filament_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $filename)) {
            return [
                'success' => false,
                'error' => 'Invalid backup filename'
            ];
        }
        
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'Backup file not found'
            ];
        }
        
        $size = filesize($filepath);
        
        if ($size === false) {
            return [
                'success' => false,
                'error' => 'Cannot read backup file'
            ];
        }
        
        return [
            'success' => true,
            'path' => $filepath,
            'size' => $size
        ];
    }
    
    /**
     * Delete backup file
     */
    public function deleteBackup(string $filename): array
    {
        $filepath = $this->backupDir . $filename;
        
        // Validate filename (security check)
        if (!preg_match('/^filament_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $filename)) {
            return [
                'success' => false,
                'error' => 'Invalid backup filename'
            ];
        }
        
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'Backup file not found'
            ];
        }
        
        try {
            $deleted = unlink($filepath);
            
            if ($deleted) {
                return [
                    'success' => true,
                    'message' => 'Backup deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to delete backup file'
                ];
            }
            
        } catch (Exception $e) {
            error_log("Delete backup failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to delete backup: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify backup file integrity
     */
    private function verifyBackup(string $filepath): bool
    {
        try {
            // Check if file exists and is readable
            if (!is_readable($filepath)) {
                return false;
            }
            
            // Check if file is not empty
            if (filesize($filepath) === 0) {
                return false;
            }
            
            // Try to read and decompress first few bytes
            $handle = fopen($filepath, 'rb');
            if (!$handle) {
                return false;
            }
            
            $chunk = fread($handle, 1024);
            fclose($handle);
            
            if ($chunk === false) {
                return false;
            }
            
            // Try to decompress
            $decompressed = gzuncompress($chunk);
            
            if ($decompressed === false) {
                return false;
            }
            
            // Check if decompressed content looks like SQL
            if (!str_contains($decompressed, 'CREATE TABLE') && !str_contains($decompressed, 'INSERT INTO')) {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("Backup verification failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean old backup files, keeping only the specified number
     */
    public function cleanOldBackups(int $keepCount = 10): int
    {
        try {
            $backups = $this->listBackups();
            
            if (count($backups) <= $keepCount) {
                return 0;
            }
            
            $toDelete = array_slice($backups, $keepCount);
            $deletedCount = 0;
            
            foreach ($toDelete as $backup) {
                $result = $this->deleteBackup($backup['filename']);
                if ($result['success']) {
                    $deletedCount++;
                }
            }
            
            return $deletedCount;
            
        } catch (Exception $e) {
            error_log("Clean old backups failed: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Restore database from backup (DANGEROUS - use with caution)
     */
    public function restoreBackup(string $filename): array
    {
        $filepath = $this->backupDir . $filename;
        
        // Validate filename
        if (!preg_match('/^filament_backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}\.sql\.gz$/', $filename)) {
            return [
                'success' => false,
                'error' => 'Invalid backup filename'
            ];
        }
        
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => 'Backup file not found'
            ];
        }
        
        try {
            // Read and decompress backup
            $compressed = file_get_contents($filepath);
            if ($compressed === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to read backup file'
                ];
            }
            
            $sql = gzuncompress($compressed);
            if ($sql === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to decompress backup'
                ];
            }
            
            // Execute SQL statements
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 0');
            
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !str_starts_with($stmt, '--');
                }
            );
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->db->exec($statement);
                }
            }
            
            $this->db->exec('SET FOREIGN_KEY_CHECKS = 1');
            
            return [
                'success' => true,
                'message' => 'Database restored successfully'
            ];
            
        } catch (Exception $e) {
            error_log("Restore backup failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Restore failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Schedule automatic backup (creates cron job)
     */
    public function scheduleAutoBackup(string $schedule = '0 2 * * *'): array
    {
        try {
            $scriptPath = __DIR__ . '/../../scripts/backup-cron.php';
            $cronLine = "{$schedule} /usr/bin/php {$scriptPath} > /dev/null 2>&1";
            
            // Note: This requires proper permissions and cron setup
            // In production, this should be handled by system administrator
            
            return [
                'success' => true,
                'message' => 'Auto-backup scheduling prepared',
                'cron_line' => $cronLine,
                'note' => 'Add this line to your crontab manually'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to schedule backup: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Get backup directory path
     */
    public function getBackupDirectory(): string
    {
        return $this->backupDir;
    }
    
    /**
     * Get total backup storage usage
     */
    public function getStorageUsage(): array
    {
        try {
            $backups = $this->listBackups();
            $totalSize = array_sum(array_column($backups, 'size'));
            
            return [
                'total_backups' => count($backups),
                'total_size' => $totalSize,
                'total_size_formatted' => $this->formatBytes($totalSize),
                'average_size' => count($backups) > 0 ? round($totalSize / count($backups)) : 0
            ];
            
        } catch (Exception $e) {
            error_log("Get storage usage failed: " . $e->getMessage());
            return [
                'total_backups' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'average_size' => 0
            ];
        }
    }
}