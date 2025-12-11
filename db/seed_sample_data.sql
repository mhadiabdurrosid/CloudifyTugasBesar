-- Seed sample data for Cloudify
-- Run this against the `cloudify` database after you imported schema.

USE `cloudify`;

-- Users
INSERT INTO users (username, password, display_name, email, role)
VALUES
('admin', '$2y$10$aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 'Administrator', 'admin@example.local', 'admin'),
('user1', '$2y$10$bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb', 'User One', 'user1@example.local', 'user')
ON DUPLICATE KEY UPDATE username = username;

-- Folders
INSERT INTO folders (name, parent_id, path, created_by)
VALUES
('root', NULL, 'uploads', 'system'),
('Documents', 1, 'uploads/Documents', 'user1'),
('Images', 1, 'uploads/Images', 'user1')
ON DUPLICATE KEY UPDATE id = id;

-- Files (sample entries) -- ensure files exist under uploads/ to preview
INSERT INTO files (name, folder_id, path, size, mime, extension, uploaded_by, is_favorite)
VALUES
('Sample Image', 3, 'uploads/Images/sample1.jpg', 24576, 'image/jpeg', 'jpg', 'user1', 1),
('Project Doc', 2, 'uploads/Documents/doc1.pdf', 98765, 'application/pdf', 'pdf', 'user1', 0),
('Presentation', 2, 'uploads/Documents/pres1.pptx', 234567, 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx', 'user1', 0),
('Deleted File', 2, 'uploads/Documents/old.txt', 1024, 'text/plain', 'txt', 'user1', 0)
ON DUPLICATE KEY UPDATE id = id;

-- Mark 'Deleted File' as trashed
UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE path = 'uploads/Documents/old.txt';

-- Storage quotas
INSERT INTO storage_quotas (user_identifier, quota_bytes)
VALUES
('admin', 0),
('user1', 10737418240)
ON DUPLICATE KEY UPDATE quota_bytes = VALUES(quota_bytes);

-- Optional upload logs
INSERT INTO upload_logs (action, target, details, ip, user_agent)
VALUES
('upload_file','uploads/Images/sample1.jpg', '{"file":"uploads/Images/sample1.jpg"}','127.0.0.1','seed-script'),
('upload_file','uploads/Documents/doc1.pdf', '{"file":"uploads/Documents/doc1.pdf"}','127.0.0.1','seed-script');

-- End of seed
