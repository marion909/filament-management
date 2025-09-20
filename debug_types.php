<?php
$config = require_once 'config/config.php';

try {
    $pdo = new PDO(
        'mysql:host=' . $config['database']['host'] . ';dbname=' . $config['database']['name'], 
        $config['database']['user'], 
        $config['database']['password']
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Filament Types:\n";
    $stmt = $pdo->query('SELECT * FROM filament_types ORDER BY id');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Name: {$row['name']}\n";
    }
    
    echo "\nLast 5 Spools:\n";
    $stmt = $pdo->query('SELECT id, type_id, material, created_at FROM filaments ORDER BY id DESC LIMIT 5');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$row['id']}, Type_ID: {$row['type_id']}, Material: {$row['material']}, Created: {$row['created_at']}\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}