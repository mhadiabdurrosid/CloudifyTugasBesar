<?php
require_once __DIR__ . '/Koneksi.php'; // pastikan path sesuai
$koneksi = new koneksi();

$newPassword = 'admin123'; // password baru admin
$hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

$sql = "UPDATE users SET password = ? WHERE username = 'admin'";
$stmt = $koneksi->getConnection()->prepare($sql);

if ($stmt) {
    $stmt->bind_param("s", $hashedPassword);
    if ($stmt->execute()) {
        echo "Password admin berhasil direset!<br>";
        echo "Email: admin@example.local<br>";
        echo "Password baru: $newPassword";
    } else {
        echo "Gagal mengeksekusi query: " . $stmt->error;
    }
} else {
    echo "Gagal prepare statement: " . $koneksi->getConnection()->error;
}
?>
