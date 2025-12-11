-- Seed sample users, folders and files for Beranda / CloudSaya
USE `cloudify`;

-- Sample user
INSERT INTO `users` (`username`, `password`, `display_name`, `email`, `role`)
VALUES
('user1', 'password-placeholder', 'User Satu', 'user1@example.local', 'user')
ON DUPLICATE KEY UPDATE username = username;

-- Sample folder (root child)
INSERT INTO `folders` (`name`, `parent_id`, `path`, `created_by`)
VALUES
('My Documents', 1, 'uploads/hadi', 'user1')
ON DUPLICATE KEY UPDATE name = name;

-- Sample files (ensure files exist under uploads/ or CloudSaya will sync files on first load)
-- Insert files referencing the folder id dynamically (if folder exists)
INSERT INTO `files` (`name`, `folder_id`, `path`, `size`, `mime`, `extension`, `uploaded_by`, `created_at`, `is_deleted`, `is_favorite`)
SELECT * FROM (
	SELECT 'sample-photo.jpg' AS name, (SELECT id FROM folders WHERE path = 'uploads/hadi' LIMIT 1) AS folder_id, 'uploads/hadi/sample-photo.jpg' AS path, 125829 AS size, 'image/jpeg' AS mime, 'jpg' AS extension, 'user1' AS uploaded_by, NOW() AS created_at, 0 AS is_deleted, 0 AS is_favorite
	UNION ALL
	SELECT 'manual.pdf', (SELECT id FROM folders WHERE path = 'uploads/hadi' LIMIT 1), 'uploads/hadi/manual.pdf', 452198, 'application/pdf', 'pdf', 'user1', NOW(), 0, 1
) AS tmp
WHERE tmp.folder_id IS NOT NULL
ON DUPLICATE KEY UPDATE name = VALUES(name), path = VALUES(path), size = VALUES(size);

-- Storage quota for demo user (30 GB)
INSERT INTO `storage_quotas` (`user_identifier`, `quota_bytes`) VALUES ('user1', 32212254720)
ON DUPLICATE KEY UPDATE quota_bytes = VALUES(quota_bytes);

-- Note: if you don't have the physical files under uploads/hadi/, CloudSaya.php will scan the uploads folder and attempt to insert any files it finds. Place some demo files in `uploads/hadi/` to see thumbnails in Beranda.
