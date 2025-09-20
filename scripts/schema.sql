-- Filament Management System - Database Schema
-- MySQL/MariaDB Schema

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
  nfc_uid VARCHAR(128) UNIQUE NULL,
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
  INDEX idx_nfc_uid (nfc_uid),
  INDEX idx_material (material),
  INDEX idx_location (location),
  INDEX idx_is_active (is_active),
  INDEX idx_created_by (created_by)
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