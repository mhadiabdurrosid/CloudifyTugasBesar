<?php
// Setup script to create proper user accounts for Railway deployment
require_once __DIR__ . '/model/Koneksi.php';

try {
    $koneksi = new koneksi();
    $conn = $koneksi->getConnection();

    if (!$conn) {
        die("Database connection failed\n");
    }

    // Create users table if it doesn't exist
    $createTable = "
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
    ";

    if ($conn->query($createTable)) {
        echo "Users table created or already exists\n";
    } else {
        echo "Error creating users table: " . $conn->error . "\n";
    }

    // Hash passwords
    $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
    $userPassword = password_hash('user123', PASSWORD_DEFAULT);

    // Insert/update users
    $insertUsers = "
    INSERT INTO users (username, password, display_name, email, role) VALUES
    ('admin', ?, 'Administrator', 'admin@cloudify.local', 'admin'),
    ('user1', ?, 'User One', 'user1@cloudify.local', 'user')
    ON DUPLICATE KEY UPDATE
    password = VALUES(password),
    display_name = VALUES(display_name),
    email = VALUES(email),
    role = VALUES(role)
    ";

    $stmt = $conn->prepare($insertUsers);
    $stmt->bind_param('ss', $adminPassword, $userPassword);

    if ($stmt->execute()) {
        echo "Users created/updated successfully!\n";
        echo "Login credentials:\n";
        echo "Admin: admin@cloudify.local / admin123\n";
        echo "User: user1@cloudify.local / user123\n";
    } else {
        echo "Error creating users: " . $stmt->error . "\n";
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
