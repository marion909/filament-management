<?php
require_once __DIR__ . '/config/config.php';

$config = require __DIR__ . '/config/config.php';
$dbConfig = $config['database'];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password']
    );

    // Prüfe Tabelle
    $result = $pdo->query('SHOW TABLES LIKE "filament_nfc_uids"');
    echo 'Table exists: ' . ($result->rowCount() > 0 ? 'YES' : 'NO') . "\n";
    
    if ($result->rowCount() > 0) {
        $result = $pdo->query('SELECT COUNT(*) as count FROM filament_nfc_uids');
        echo 'Records in table: ' . $result->fetch()['count'] . "\n";
        
        $result = $pdo->query('DESCRIBE filament_nfc_uids');
        echo "\nTable structure:\n";
        foreach ($result as $row) {
            echo "- {$row['Field']}: {$row['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}