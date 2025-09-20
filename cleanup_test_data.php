<?php
// Cleanup Test-Daten
try {
    $pdo = new PDO(
        'mysql:host=localhost;port=3306;dbname=filament;charset=utf8mb4',
        'filament', 
        '7fLy2Ckr2NhyJYrA'
    );
    
    // Test-Filament entfernen
    $stmt = $pdo->prepare('DELETE FROM filaments WHERE nfc_uid = ?');
    $result = $stmt->execute(['TEST-NFC-123']);
    
    if ($stmt->rowCount() > 0) {
        echo "✅ Test-Filament mit NFC-UID TEST-NFC-123 entfernt\n";
    } else {
        echo "ℹ️  Kein Test-Filament gefunden\n";
    }
    
    // Test-Admin-User entfernen (optional, falls gewünscht)
    // $stmt = $pdo->prepare('DELETE FROM users WHERE email = ?');
    // $stmt->execute(['admin@test.com']);
    
    echo "🧹 Cleanup abgeschlossen\n";
    
} catch (PDOException $e) {
    echo "❌ Fehler: " . $e->getMessage() . "\n";
}
?>