<?php
$config = require_once 'config/config.php';

try {
    $dbConfig = $config['database'];
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}", 
        $dbConfig['user'], 
        $dbConfig['password'], 
        $dbConfig['options']
    );
    
    echo "Columns in filaments table:\n";
    $stmt = $pdo->query('DESCRIBE filaments');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['Field'] . " (" . $row['Type'] . ")\n";
    }
    
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}