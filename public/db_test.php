<?php
// Einfacher Datenbankverbindungstest
require_once __DIR__ . '/../config/config.php';

$config = require __DIR__ . '/../config/config.php';

try {
    $dsn = "mysql:host={$config['database']['host']};dbname={$config['database']['name']};charset={$config['database']['charset']}";
    $pdo = new PDO($dsn, $config['database']['user'], $config['database']['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "✅ Datenbankverbindung erfolgreich!\n";
    
    // Teste eine einfache Abfrage
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM filament_spools");
    $result = $stmt->fetch();
    echo "📊 Anzahl Spulen in DB: " . $result['count'] . "\n";
    
} catch (PDOException $e) {
    echo "❌ Datenbankfehler: " . $e->getMessage() . "\n";
    echo "🔧 Überprüfen Sie:\n";
    echo "   - MySQL Server läuft\n";
    echo "   - Datenbank 'filament' existiert\n";
    echo "   - Benutzer '{$config['database']['user']}' hat Zugriffsrechte\n";
}
?>