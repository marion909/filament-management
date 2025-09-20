-- Filament Management System - Complete Database Schema
-- MySQL/MariaDB Schema mit allen Tabellen und Seed-Daten
-- Ohne Demo-User - nur Struktur und Preset-Daten

-- ======================================================================
-- TABELLEN ERSTELLEN
-- ======================================================================

-- Users table for authentication
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(150),
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  verified_at DATETIME NULL,
  verification_token VARCHAR(128) NULL,
  reset_token VARCHAR(128) NULL,
  reset_expires DATETIME NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login DATETIME NULL,
  
  INDEX idx_email (email),
  INDEX idx_verification_token (verification_token),
  INDEX idx_reset_token (reset_token)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Filament types preset table
CREATE TABLE filament_types (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  diameter VARCHAR(20),
  description TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Colors preset table
CREATE TABLE colors (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  hex VARCHAR(7) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Spool presets table
CREATE TABLE spool_presets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  grams INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Main filaments/spools table
CREATE TABLE filaments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  uuid CHAR(36) NOT NULL UNIQUE,
  type_id INT NOT NULL,
  material VARCHAR(100) NOT NULL,
  color_id INT NULL,
  total_weight INT NOT NULL,
  remaining_weight INT NOT NULL,
  diameter VARCHAR(20),
  purchase_date DATE NULL,
  location VARCHAR(255) NULL,
  batch_number VARCHAR(255) NULL,
  notes TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  
  FOREIGN KEY (type_id) REFERENCES filament_types(id) ON DELETE RESTRICT,
  FOREIGN KEY (color_id) REFERENCES colors(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  
  INDEX idx_uuid (uuid),
  INDEX idx_material (material),
  INDEX idx_location (location),
  INDEX idx_is_active (is_active),
  INDEX idx_created_by (created_by)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- NFC UIDs table - Eine Spule kann mehrere NFC-Tags haben
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

-- Usage log table for tracking consumption
CREATE TABLE usage_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  filament_id INT NOT NULL,
  used_grams INT NOT NULL,
  job_name VARCHAR(255) NULL,
  job_id VARCHAR(255) NULL,
  note TEXT NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (filament_id) REFERENCES filaments(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  
  INDEX idx_filament_id (filament_id),
  INDEX idx_created_at (created_at),
  INDEX idx_job_name (job_name)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- NFC Scan Log table für Scanner-Protokollierung
CREATE TABLE nfc_scan_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nfc_uid VARCHAR(50) NOT NULL,
    scanner_id VARCHAR(50) NOT NULL,
    found_filament_id INT NULL,
    found_nfc_uid_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_nfc_uid (nfc_uid),
    INDEX idx_scanner_id (scanner_id),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (found_filament_id) REFERENCES filaments(id) ON DELETE SET NULL,
    FOREIGN KEY (found_nfc_uid_id) REFERENCES filament_nfc_uids(id) ON DELETE SET NULL
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ======================================================================
-- PRESET-DATEN EINFÜGEN (ohne Demo-User)
-- ======================================================================

-- Standard Filament-Typen
INSERT INTO filament_types (name, diameter, description) VALUES
('PLA', '1.75', 'Polylactic Acid - einfach zu drucken, umweltfreundlich'),
('PETG', '1.75', 'Gute Festigkeit, chemikalienbeständig, transparent möglich'),
('ABS', '1.75', 'Hochtemperaturbeständig, schlagzäh, löslich in Aceton'),
('TPU', '1.75', 'Thermoplastisches Polyurethan - flexibel und elastisch'),
('Nylon', '1.75', 'Sehr zäh und verschleißfest, hohe Drucktemperatur'),
('ASA', '1.75', 'UV-beständig, wetterbeständig, ähnlich ABS'),
('PVA', '1.75', 'Wasserlöslich, ideal als Stützmaterial'),
('PC', '1.75', 'Polycarbonat - sehr stabil, transparent, hohe Temperatur'),
('Wood-Fill', '1.75', 'Holz-gefüllter PLA, schleif- und bearbeitbar'),
('Carbon-Fill', '1.75', 'Kohlefaser-verstärkt, sehr stabil und leicht'),
('Metal-Fill', '1.75', 'Metall-gefülltes Filament, polierbar'),
('HIPS', '1.75', 'Leicht, löslich in Limonen, gutes Stützmaterial'),
('PLA+', '1.75', 'Verbesserter PLA mit höherer Festigkeit'),
('PETG+', '1.75', 'Verstärktes PETG mit besseren mechanischen Eigenschaften');

-- Standard Farben
INSERT INTO colors (name, hex) VALUES
('Schwarz', '#000000'),
('Weiß', '#FFFFFF'),
('Natur/Transparent', '#F5F5F5'),
('Grau', '#808080'),
('Rot', '#FF0000'),
('Blau', '#0000FF'),
('Grün', '#00FF00'),
('Gelb', '#FFFF00'),
('Orange', '#FFA500'),
('Lila', '#800080'),
('Pink', '#FF69B4'),
('Braun', '#A52A2A'),
('Silber', '#C0C0C0'),
('Gold', '#FFD700'),
('Bronze', '#CD7F32'),
('Türkis', '#40E0D0'),
('Lime', '#00FF00'),
('Magenta', '#FF00FF'),
('Cyan', '#00FFFF'),
('Dunkelblau', '#000080'),
('Dunkelgrün', '#006400'),
('Dunkelrot', '#8B0000'),
('Beige', '#F5F5DC'),
('Khaki', '#F0E68C');

-- Standard Spulen-Größen
INSERT INTO spool_presets (name, grams) VALUES
('250g Spule', 250),
('500g Spule', 500),
('750g Spule', 750),
('1kg Spule', 1000),
('1.75kg Spule', 1750),
('2kg Spule', 2000),
('2.5kg Spule', 2500),
('5kg Spule', 5000);

-- ======================================================================
-- HINWEISE ZUR NFC-INTEGRATION
-- ======================================================================
-- 
-- Diese Schema-Datei enthält:
-- - Alle Tabellen-Definitionen mit korrekten Foreign Keys
-- - Alle Indizes für Performance-Optimierung
-- - Standard-Presets für Materialien, Farben und Spulengrößen
-- - UTF-8 Zeichensatz-Support für deutsche Umlaute
-- - NFC-Integration Tabellen für mehrere UIDs pro Spule
-- 
-- NFC-STRUKTUR:
-- - Eine Filament-Spule kann mehrere NFC-Tags haben (filament_nfc_uids)
-- - Tags können werkseitig integriert oder nachträglich hinzugefügt sein
-- - Jede Spule kann einen primären NFC-Tag haben (is_primary = 1)
-- - Tag-Positionen werden zur besseren Identifikation gespeichert
-- 
-- BEISPIELE:
-- - Bambu Lab Spulen: Oft 2 integrierte NFC-Tags (Anfang + Ende)
-- - Prusament: 1 integrierter NFC-Tag am Spulenkern  
-- - Custom Tags: Nachträglich hinzugefügte NFC-Etiketten
-- 
-- KEINE Demo-User enthalten - Admin-User muss separat erstellt werden:
-- php create_admin.php
-- 
-- ======================================================================