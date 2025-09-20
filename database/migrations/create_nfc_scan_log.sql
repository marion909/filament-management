-- NFC Scan Log Tabelle für Scanner-Protokollierung
CREATE TABLE IF NOT EXISTS nfc_scan_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nfc_uid VARCHAR(50) NOT NULL,
    scanner_id VARCHAR(50) NOT NULL,
    found_spool_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_nfc_uid (nfc_uid),
    INDEX idx_scanner_id (scanner_id),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (found_spool_id) REFERENCES filament_spools(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;