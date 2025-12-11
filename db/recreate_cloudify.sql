-- Recreate script for Cloudify (drops existing database and recreates minimal schema)
-- WARNING: This will DELETE existing database `cloudify`. BACKUP first.

DROP DATABASE IF EXISTS `cloudify`;
CREATE DATABASE `cloudify` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cloudify`;

-- folders
CREATE TABLE `folders` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `parent_id` INT UNSIGNED DEFAULT NULL,
  `path` VARCHAR(1024) NOT NULL,
  `created_by` VARCHAR(100) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- files (with trash + favorite)
CREATE TABLE `files` (
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

-- upload logs
CREATE TABLE `upload_logs` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `action` VARCHAR(50) NOT NULL,
  `target` VARCHAR(1024) DEFAULT NULL,
  `details` TEXT,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users (simple)
CREATE TABLE `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `display_name` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `role` VARCHAR(50) DEFAULT 'user',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- storage quotas
CREATE TABLE `storage_quotas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_identifier` VARCHAR(255) NOT NULL,
  `quota_bytes` BIGINT UNSIGNED DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- settings
CREATE TABLE `settings` (
  `k` VARCHAR(100) NOT NULL PRIMARY KEY,
  `v` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- view: storage usage
DROP VIEW IF EXISTS `storage_usage`;
CREATE VIEW `storage_usage` AS
SELECT
  f.uploaded_by AS user_identifier,
  COALESCE(SUM(f.size),0) AS used_bytes
FROM files f
WHERE (f.is_deleted IS NULL OR f.is_deleted = 0)
GROUP BY f.uploaded_by;

-- Seed: root folder
INSERT INTO folders (id, name, parent_id, path, created_by)
VALUES (1, 'root', NULL, 'uploads', 'system')
ON DUPLICATE KEY UPDATE id = id;

-- Note: do not seed users with real password here; create user via application or replace placeholder
-- End of recreate script
