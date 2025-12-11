<?php
// Generic soft-delete endpoint for CRUD items
// Usage: trash_generic.php?table=produk&id=123
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../model/Koneksi.php';

$allowed = [
    'produk','toko','artikel','kategori','promo','about','pesanan'
];

$table = isset($_GET['table']) ? basename($_GET['table']) : null;
$id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$table || !in_array($table, $allowed) || $id <= 0) {
    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
    exit;
}

$db = $koneksi ?? new koneksi();
$conn = $db->getConnection();

try {
    // try soft delete: set is_deleted and deleted_at
    $sql = "UPDATE `$table` SET is_deleted = 1, deleted_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
    } else {
        // fallback: perform hard delete
        $del = $conn->prepare("DELETE FROM `$table` WHERE id = ?");
        if ($del) { $del->bind_param('i', $id); $del->execute(); $del->close(); }
    }
} catch (Throwable $e) {
    // ignore and fallback to redirect
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '../index.php'));
exit;
