-- init_penyimpanan.sql
-- Membuat tabel storage_quotas dan data contoh untuk fitur Penyimpanan

CREATE TABLE IF NOT EXISTS `storage_quotas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_identifier` VARCHAR(191) NOT NULL,
  `quota_bytes` BIGINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_identifier` (`user_identifier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contoh data: admin tanpa batas (0 berarti tidak dibatasi), user1 diberi 30 GB
INSERT INTO `storage_quotas` (user_identifier, quota_bytes)
VALUES
('admin', 0),
('user1', 32212254720)
ON DUPLICATE KEY UPDATE quota_bytes = VALUES(quota_bytes), updated_at = CURRENT_TIMESTAMP;

-- Catatan: pastikan tabel `files` sudah ada dan berisi kolom `size`, `uploaded_by`, `is_deleted`.
-- Jika ingin reset/initialize, jalankan file ini via phpMyAdmin atau MySQL CLI:
-- mysql -u root -p cloudify < init_penyimpanan.sql
