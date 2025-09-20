<?php
require_once __DIR__ . '/config/config.php';

$config = require __DIR__ . '/config/config.php';
$dbConfig = $config['database'];

try {
    $pdo = new PDO(
        "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset=utf8mb4",
        $dbConfig['user'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    echo "Database connection established.\n";

    // Beginne Transaktion
    $pdo->beginTransaction();

    // 1. Prüfe ob die Tabelle bereits existiert
    $stmt = $pdo->query("SHOW TABLES LIKE 'filament_nfc_uids'");
    if ($stmt->rowCount() > 0) {
        echo "Table 'filament_nfc_uids' already exists. Skipping migration.\n";
        $pdo->rollback();
        exit(0);
    }

    echo "Creating filament_nfc_uids table...\n";

    // 1. Neue Tabelle für NFC-UIDs erstellen
    $createTableSQL = "
    CREATE TABLE filament_nfc_uids (
      id INT AUTO_INCREMENT PRIMARY KEY,
      filament_id INT NOT NULL,
      nfc_uid VARCHAR(128) NOT NULL UNIQUE,
      tag_type ENUM('integrated', 'custom', 'unknown') NOT NULL DEFAULT 'unknown',
      tag_position VARCHAR(50) NULL COMMENT 'z.B. \"Spulenanfang\", \"Spulenende\", \"Etikett\"',
      is_primary TINYINT(1) NOT NULL DEFAULT 0,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      
      FOREIGN KEY (filament_id) REFERENCES filaments(id) ON DELETE CASCADE,
      
      INDEX idx_filament_id (filament_id),
      INDEX idx_nfc_uid (nfc_uid),
      INDEX idx_tag_type (tag_type),
      INDEX idx_is_primary (is_primary)
    ) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";

    $pdo->exec($createTableSQL);
    echo "✓ Table created successfully\n";

    // 2. Bestehende NFC-UIDs migrieren (falls nfc_uid Spalte existiert)
    $stmt = $pdo->query("SHOW COLUMNS FROM filaments LIKE 'nfc_uid'");
    if ($stmt->rowCount() > 0) {
        echo "Migrating existing NFC-UIDs...\n";
        
        $migrateSQL = "
        INSERT INTO filament_nfc_uids (filament_id, nfc_uid, tag_type, is_primary)
        SELECT id, nfc_uid, 'unknown', 1 
        FROM filaments 
        WHERE nfc_uid IS NOT NULL AND nfc_uid != ''";
        
        $stmt = $pdo->exec($migrateSQL);
        echo "✓ Migrated {$stmt} existing NFC-UIDs\n";

        // 3. Alte nfc_uid Spalte entfernen
        echo "Removing old nfc_uid column...\n";
        $pdo->exec("ALTER TABLE filaments DROP INDEX idx_nfc_uid");
        $pdo->exec("ALTER TABLE filaments DROP COLUMN nfc_uid");
        echo "✓ Old column removed\n";
    } else {
        echo "No existing nfc_uid column found to migrate.\n";
    }

    // 4. NFC Scan Log Tabelle erweitern (falls sie existiert)
    $stmt = $pdo->query("SHOW TABLES LIKE 'nfc_scan_log'");
    if ($stmt->rowCount() > 0) {
        echo "Extending nfc_scan_log table...\n";
        
        // Prüfe ob die Spalten bereits existieren
        $stmt = $pdo->query("SHOW COLUMNS FROM nfc_scan_log LIKE 'found_filament_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE nfc_scan_log ADD COLUMN found_filament_id INT NULL AFTER found_spool_id");
            echo "✓ Added found_filament_id column\n";
        }
        
        $stmt = $pdo->query("SHOW COLUMNS FROM nfc_scan_log LIKE 'found_nfc_uid_id'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE nfc_scan_log ADD COLUMN found_nfc_uid_id INT NULL AFTER found_filament_id");
            echo "✓ Added found_nfc_uid_id column\n";
        }

        // 5. Foreign Keys hinzufügen
        try {
            $pdo->exec("ALTER TABLE nfc_scan_log ADD FOREIGN KEY (found_filament_id) REFERENCES filaments(id) ON DELETE SET NULL");
            echo "✓ Added foreign key for found_filament_id\n";
        } catch (Exception $e) {
            echo "Note: Foreign key for found_filament_id might already exist\n";
        }

        try {
            $pdo->exec("ALTER TABLE nfc_scan_log ADD FOREIGN KEY (found_nfc_uid_id) REFERENCES filament_nfc_uids(id) ON DELETE SET NULL");
            echo "✓ Added foreign key for found_nfc_uid_id\n";
        } catch (Exception $e) {
            echo "Note: Foreign key for found_nfc_uid_id might already exist\n";
        }
    }

    // Migration erfolgreich
    $pdo->commit();
    echo "\n🎉 Migration completed successfully!\n\n";
    echo "Summary:\n";
    echo "- Multiple NFC-UIDs per spool are now supported\n";
    echo "- Existing UIDs were migrated as 'unknown' and 'primary'\n";
    echo "- Scanner can now recognize multiple tags per spool\n";
    echo "- Web interface ready for Multiple-UID support\n\n";

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollback();
    }
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}