-- Init script for Cloudify application
-- Creates database `cloudify` and required tables with sensible defaults
-- Run this file in MySQL (phpMyAdmin or mysql CLI)

CREATE DATABASE IF NOT EXISTS `cloudify` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cloudify`;

-- Table: folders
CREATE TABLE IF NOT EXISTS `folders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `created_by` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: files
CREATE TABLE IF NOT EXISTS `files` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `folder_id` INT UNSIGNED DEFAULT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `size` BIGINT UNSIGNED DEFAULT 0,
  `mime` VARCHAR(255) DEFAULT NULL,
  `extension` VARCHAR(50) DEFAULT NULL,
  `uploaded_by` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_deleted` TINYINT(1) DEFAULT 0,
  `deleted_at` DATETIME DEFAULT NULL,
  `is_favorite` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_folder` (`folder_id`),
  KEY `idx_deleted` (`is_deleted`),
  CONSTRAINT `fk_files_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: upload_logs
CREATE TABLE IF NOT EXISTS `upload_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` VARCHAR(50) NOT NULL,
  `target` VARCHAR(1024) DEFAULT NULL,
  `details` TEXT,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users (simple auth store)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `role` VARCHAR(50) DEFAULT 'user',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: root folder
INSERT INTO folders (name, parent_id, path, created_by) VALUES
('root', NULL, 'uploads', 'system')
ON DUPLICATE KEY UPDATE id = id;

-- Seed: example file (if not present)
INSERT INTO files (name, folder_id, path, size, mime, extension, uploaded_by)
SELECT 'example.pdf', (SELECT id FROM folders WHERE name='root' LIMIT 1), 'uploads/example.pdf', 12345, 'application/pdf', 'pdf', 'system'
WHERE NOT EXISTS (SELECT 1 FROM files WHERE path = 'uploads/example.pdf');

-- Optional: small user for testing (password placeholder — replace with a real hash)
INSERT INTO users (username, password, display_name, email, role)
SELECT 'admin', '$2y$10$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Administrator', 'admin@example.local', 'admin'
WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = 'admin');

-- End of init

-- Storage / Quota table: track per-user quota (bytes)
CREATE TABLE IF NOT EXISTS `storage_quotas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_identifier` VARCHAR(255) NOT NULL, -- matches files.uploaded_by (username or id string)
  `quota_bytes` BIGINT UNSIGNED DEFAULT 0, -- 0 = unlimited
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- View: storage usage per uploader (sums sizes of non-deleted files)
DROP VIEW IF EXISTS `storage_usage`;
CREATE VIEW `storage_usage` AS
SELECT
  f.uploaded_by AS user_identifier,
  COALESCE(SUM(f.size),0) AS used_bytes
FROM files f
WHERE (f.is_deleted IS NULL OR f.is_deleted = 0)
GROUP BY f.uploaded_by;

-- Helpful settings table
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(100) NOT NULL PRIMARY KEY,
  `v` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example quota: set admin to unlimited (0), user 'system' 10 GB
INSERT INTO storage_quotas (user_identifier, quota_bytes)
VALUES
('admin', 0),
('system', 10737418240)
ON DUPLICATE KEY UPDATE quota_bytes = VALUES(quota_bytes);
