#!/usr/bin/env php
<?php
/**
 * Automated Backup Cron Script for Filament Management System
 * 
 * Usage: Add to crontab for automatic daily backups
 * Example: 0 2 * * * /usr/bin/php /path/to/backup-cron.php
 */

declare(strict_types=1);

// Set error reporting for cron
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Include autoloader
require_once __DIR__ . '/../src/autoload.php';

use Filament\Services\BackupService;

/**
 * Log function for cron output
 */
function logMessage(string $message): void
{
    $timestamp = date('Y-m-d H:i:s');
    echo "[{$timestamp}] {$message}\n";
    error_log("[Backup Cron] {$message}");
}

try {
    logMessage("Starting automated backup process");
    
    // Load configuration
    $config = require_once __DIR__ . '/../config/config.php';
    
    // Database connection
    $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['name']};charset=utf8mb4";
    $pdo = new PDO(
        $dsn,
        $config['database']['user'],
        $config['database']['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    logMessage("Database connection established");
    
    // Initialize backup service
    $backupService = new BackupService($pdo, $config);
    
    // Create backup
    $result = $backupService->createBackup();
    
    if ($result['success']) {
        logMessage("Backup created successfully: {$result['filename']} ({$result['size']} bytes)");
        
        // Clean old backups (keep last 7 daily backups)
        $cleaned = $backupService->cleanOldBackups(7);
        if ($cleaned > 0) {
            logMessage("Cleaned {$cleaned} old backup files");
        }
        
        // Log storage usage
        $usage = $backupService->getStorageUsage();
        logMessage("Storage usage: {$usage['total_backups']} files, {$usage['total_size_formatted']} total");
        
    } else {
        logMessage("Backup failed: {$result['error']}");
        exit(1);
    }
    
    logMessage("Automated backup process completed successfully");
    
} catch (Exception $e) {
    logMessage("Backup process failed with exception: " . $e->getMessage());
    logMessage("Stack trace: " . $e->getTraceAsString());
    exit(1);
}