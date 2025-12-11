-- init_combined_cloudify.sql
-- Combined initialization script for Cloudify
-- This merges schema + migrations + seeds from the repo into a single idempotent file
-- Run in phpMyAdmin or via MySQL CLI. Review before running on production.

-- Create database if missing
CREATE DATABASE IF NOT EXISTS `cloudify` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cloudify`;

-- =========================================================================
-- Core tables: folders, files, upload_logs, users
-- =========================================================================
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
  KEY `idx_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FK if folders table existed before (safe: only create if not exists)
ALTER TABLE `files` 
  ADD CONSTRAINT IF NOT EXISTS `fk_files_folder` FOREIGN KEY (`folder_id`) REFERENCES `folders` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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

-- =========================================================================
-- Storage / quotas, settings and views (from migrations/init_penyimpanan)
-- =========================================================================
CREATE TABLE IF NOT EXISTS `storage_quotas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_identifier` VARCHAR(255) NOT NULL,
  `quota_bytes` BIGINT UNSIGNED DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_identifier` (`user_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `settings` (
  `k` VARCHAR(100) NOT NULL PRIMARY KEY,
  `v` TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- view: aggregate storage usage per uploader
DROP VIEW IF EXISTS `storage_usage`;
CREATE VIEW `storage_usage` AS
SELECT
  f.uploaded_by AS user_identifier,
  COALESCE(SUM(f.size),0) AS used_bytes
FROM files f
WHERE (f.is_deleted IS NULL OR f.is_deleted = 0)
GROUP BY f.uploaded_by;

-- =========================================================================
-- Seeds (idempotent): folders, sample users, sample files, quotas, logs
-- =========================================================================
-- root folder
INSERT INTO folders (name, parent_id, path, created_by)
VALUES ('root', NULL, 'uploads', 'system')
ON DUPLICATE KEY UPDATE path = VALUES(path);

-- Seed sample users (password placeholders — replace with real password hashes)
INSERT INTO users (username, password, display_name, email, role)
VALUES
('admin', '$2y$10$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Administrator', 'admin@example.local', 'admin'),
('user1', '$2y$10$bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'User One', 'user1@example.local', 'user')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- Seed example folders
INSERT INTO folders (name, parent_id, path, created_by)
SELECT 'Documents', id, 'uploads/Documents', 'user1' FROM folders WHERE name = 'root' LIMIT 1
ON DUPLICATE KEY UPDATE path = VALUES(path);

INSERT INTO folders (name, parent_id, path, created_by)
SELECT 'Images', id, 'uploads/Images', 'user1' FROM folders WHERE name = 'root' LIMIT 1
ON DUPLICATE KEY UPDATE path = VALUES(path);

-- Seed sample files referencing folder ids dynamically
INSERT INTO files (name, folder_id, path, size, mime, extension, uploaded_by, created_at, is_deleted, is_favorite)
SELECT * FROM (
  SELECT 'sample-photo.jpg' AS name, (SELECT id FROM folders WHERE path = 'uploads/hadi' LIMIT 1) AS folder_id, 'uploads/hadi/sample-photo.jpg' AS path, 125829 AS size, 'image/jpeg' AS mime, 'jpg' AS extension, 'user1' AS uploaded_by, NOW() AS created_at, 0 AS is_deleted, 0 AS is_favorite
  UNION ALL
  SELECT 'manual.pdf', (SELECT id FROM folders WHERE path = 'uploads/hadi' LIMIT 1), 'uploads/hadi/manual.pdf', 452198, 'application/pdf', 'pdf', 'user1', NOW(), 0, 1
) AS tmp
WHERE tmp.folder_id IS NOT NULL
ON DUPLICATE KEY UPDATE name = VALUES(name), path = VALUES(path), size = VALUES(size);

-- Additional sample files from seed_sample_data (if their folders exist)
INSERT INTO files (name, folder_id, path, size, mime, extension, uploaded_by, is_favorite)
SELECT 'Sample Image', (SELECT id FROM folders WHERE name='Images' LIMIT 1), 'uploads/Images/sample1.jpg', 24576, 'image/jpeg', 'jpg', 'user1', 1
WHERE (SELECT id FROM folders WHERE name='Images' LIMIT 1) IS NOT NULL
ON DUPLICATE KEY UPDATE path = VALUES(path);

INSERT INTO files (name, folder_id, path, size, mime, extension, uploaded_by, is_favorite)
SELECT 'Project Doc', (SELECT id FROM folders WHERE name='Documents' LIMIT 1), 'uploads/Documents/doc1.pdf', 98765, 'application/pdf', 'pdf', 'user1', 0
WHERE (SELECT id FROM folders WHERE name='Documents' LIMIT 1) IS NOT NULL
ON DUPLICATE KEY UPDATE path = VALUES(path);

INSERT INTO files (name, folder_id, path, size, mime, extension, uploaded_by, is_favorite)
SELECT 'Presentation', (SELECT id FROM folders WHERE name='Documents' LIMIT 1), 'uploads/Documents/pres1.pptx', 234567, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx', 'user1', 0
WHERE (SELECT id FROM folders WHERE name='Documents' LIMIT 1) IS NOT NULL
ON DUPLICATE KEY UPDATE path = VALUES(path);

-- Optionally mark a sample file as deleted (trashed)
UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE path = 'uploads/Documents/old.txt';

-- Storage quotas (demo)
INSERT INTO storage_quotas (user_identifier, quota_bytes)
VALUES
('admin', 0),
('system', 10737418240),
('user1', 32212254720)
ON DUPLICATE KEY UPDATE quota_bytes = VALUES(quota_bytes), updated_at = CURRENT_TIMESTAMP;

-- Sample upload logs (optional)
INSERT INTO upload_logs (action, target, details, ip, user_agent)
VALUES
('upload_file','uploads/Images/sample1.jpg', '{"file":"uploads/Images/sample1.jpg"}','127.0.0.1','seed-script'),
('upload_file','uploads/Documents/doc1.pdf', '{"file":"uploads/Documents/doc1.pdf"}','127.0.0.1','seed-script')
ON DUPLICATE KEY UPDATE id = id;

-- =========================================================================
-- End of combined init script
-- Notes:
-- 1) Password values above are placeholders; replace with proper hashed passwords for real users.
-- 2) Some INSERTs assume the presence of specific folder paths (e.g. uploads/hadi). If those folders do not exist
--    in `folders` table the SELECT-based INSERTs will skip. You can create folders first or adjust paths.
-- 3) If you want to drop and recreate DB, use the provided `recreate_cloudify.sql` instead (it drops DB).
