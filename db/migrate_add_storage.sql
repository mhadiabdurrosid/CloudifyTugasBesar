-- Migration: add storage quota table and safe alters for existing installations
-- Run this after you imported/are using an existing schema to add storage support.

-- Add columns if missing (MySQL 8+ supports IF NOT EXISTS; if your server is older, run manually)
ALTER TABLE `files`
  ADD COLUMN IF NOT EXISTS `is_deleted` TINYINT(1) DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `deleted_at` DATETIME DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `is_favorite` TINYINT(1) DEFAULT 0;

-- Create storage_quotas table if not exists
CREATE TABLE IF NOT EXISTS `storage_quotas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_identifier` VARCHAR(255) NOT NULL,
  `quota_bytes` BIGINT UNSIGNED DEFAULT 0,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user` (`user_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create settings table if not exists
CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(100) NOT NULL PRIMARY KEY,
  `v` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create view for storage usage
DROP VIEW IF EXISTS `storage_usage`;
CREATE VIEW `storage_usage` AS
SELECT
  f.uploaded_by AS user_identifier,
  COALESCE(SUM(f.size),0) AS used_bytes
FROM files f
WHERE (f.is_deleted IS NULL OR f.is_deleted = 0)
GROUP BY f.uploaded_by;

-- Seed example quota entries (no-op if exists)
INSERT INTO storage_quotas (user_identifier, quota_bytes)
VALUES ('admin',0), ('system', 10737418240)
ON DUPLICATE KEY UPDATE quota_bytes = VALUES(quota_bytes);
