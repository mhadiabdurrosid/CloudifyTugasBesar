<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../model/Koneksi.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php');
    exit;
}
$id = (int) $_GET['id'];

try {
    $db = $koneksi ?? new koneksi();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    }
} catch (Throwable $e) {
    // ignore
}

header('Location: ../index.php?module=pages&page=Menu-Utama');
exit;
