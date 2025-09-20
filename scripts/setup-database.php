#!/usr/bin/env php
<?php

/**
 * Database Setup Script
 * Sets up the database schema and seeds initial data
 */

require_once __DIR__ . '/../config/config.php';

$config = require __DIR__ . '/../config/config.php';

echo "🚀 Filament Management Database Setup\n";
echo "=====================================\n\n";

try {
    // Connect to MySQL without specifying database first
    $tempConfig = $config['database'];
    $tempConfig['name'] = '';  // Connect without database
    
    $dsn = sprintf(
        'mysql:host=%s;charset=%s',
        $tempConfig['host'],
        $tempConfig['charset']
    );
    
    $pdo = new PDO(
        $dsn,
        $tempConfig['user'],
        $tempConfig['password'],
        $tempConfig['options']
    );
    
    echo "✅ Connected to MySQL server\n";
    
    // Create database if it doesn't exist
    $dbName = $config['database']['name'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✅ Database '{$dbName}' created/verified\n";
    
    // Switch to the database
    $pdo->exec("USE `{$dbName}`");
    echo "✅ Switched to database '{$dbName}'\n";
    
    // Read and execute schema
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("Schema file not found: {$schemaFile}");
    }
    
    $schema = file_get_contents($schemaFile);
    $statements = explode(';', $schema);
    
    echo "\n📋 Creating tables...\n";
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    echo "✅ Schema created successfully\n";
    
    // Read and execute seeds
    $seedsFile = __DIR__ . '/seeds.sql';
    if (file_exists($seedsFile)) {
        echo "\n🌱 Seeding initial data...\n";
        $seeds = file_get_contents($seedsFile);
        $statements = explode(';', $seeds);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Ignore duplicate entry errors (data already exists)
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }
        }
        echo "✅ Seed data inserted successfully\n";
    }
    
    echo "\n🎉 Database setup completed successfully!\n";
    echo "\nDefault accounts created:\n";
    echo "- Admin: admin@filament.neuhauser.cloud (password: admin123)\n";
    echo "- User:  user@example.com (password: user123)\n\n";
    echo "You can now start the application.\n";
    
} catch (Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Please check your database configuration and try again.\n";
    exit(1);
}