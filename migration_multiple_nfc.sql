-- Migration Script: Mehrere NFC-UIDs pro Spule
-- Führe dieses Script aus, wenn du bereits eine Installation hast

-- 1. Neue Tabelle für NFC-UIDs erstellen
CREATE TABLE filament_nfc_uids (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filament_id INT NOT NULL,
  nfc_uid VARCHAR(128) NOT NULL UNIQUE,
  tag_type ENUM('integrated', 'custom', 'unknown') NOT NULL DEFAULT 'unknown',
  tag_position VARCHAR(50) NULL COMMENT 'z.B. "Spulenanfang", "Spulenende", "Etikett"',
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (filament_id) REFERENCES filaments(id) ON DELETE CASCADE,
  
  INDEX idx_filament_id (filament_id),
  INDEX idx_nfc_uid (nfc_uid),
  INDEX idx_tag_type (tag_type),
  INDEX idx_is_primary (is_primary)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Bestehende NFC-UIDs aus filaments Tabelle migrieren
INSERT INTO filament_nfc_uids (filament_id, nfc_uid, tag_type, is_primary)
SELECT id, nfc_uid, 'unknown', 1 
FROM filaments 
WHERE nfc_uid IS NOT NULL AND nfc_uid != '';

-- 3. nfc_uid Spalte aus filaments Tabelle entfernen
ALTER TABLE filaments DROP INDEX idx_nfc_uid;
ALTER TABLE filaments DROP COLUMN nfc_uid;

-- 4. NFC Scan Log Tabelle erweitern
ALTER TABLE nfc_scan_log 
ADD COLUMN found_filament_id INT NULL AFTER found_spool_id,
ADD COLUMN found_nfc_uid_id INT NULL AFTER found_filament_id;

-- 5. Foreign Keys für neue Spalten hinzufügen
ALTER TABLE nfc_scan_log 
ADD FOREIGN KEY (found_filament_id) REFERENCES filaments(id) ON DELETE SET NULL,
ADD FOREIGN KEY (found_nfc_uid_id) REFERENCES filament_nfc_uids(id) ON DELETE SET NULL;

-- 6. Alte found_spool_id Spalte entfernen (falls vorhanden)
-- ALTER TABLE nfc_scan_log DROP FOREIGN KEY nfc_scan_log_ibfk_1; -- Falls FK existiert
-- ALTER TABLE nfc_scan_log DROP COLUMN found_spool_id;

COMMIT;

-- ======================================================================
-- HINWEISE
-- ======================================================================
-- 
-- Nach dieser Migration:
-- - Jede Spule kann mehrere NFC-UIDs haben
-- - Bestehende UIDs wurden als 'unknown' und 'primary' markiert
-- - Scanner kann mehrere Tags pro Spule erkennen
-- - Web-Interface muss für Multiple-UID Support angepasst werden
-- 
-- ======================================================================