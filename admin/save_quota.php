<?php
session_start();
require_once(__DIR__ . '/../model/Koneksi.php');

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$db = new koneksi();
$conn = $db->getConnection();

// DELETE KUOTA
if (isset($_GET['delete'])) {
    $uid = (int)$_GET['delete'];
    $conn->query("DELETE FROM storage_quotas WHERE user_id = $uid");
    header("Location: quota.php?deleted=1");
    exit;
}

// UPDATE KUOTA
if (!isset($_POST['user_id']) || !isset($_POST['quota_gb'])) {
    die("Data tidak lengkap.");
}

$user_id   = (int)$_POST['user_id'];
$quota_gb  = (int)$_POST['quota_gb'];

// Validasi kuota
if ($quota_gb < 10 || $quota_gb > 200) {
    die("Kuota wajib antara 10–200GB.");
}

$quota_bytes = $quota_gb * 1024 * 1024 * 1024;

// CRUD kuota
$stmt = $conn->prepare("
    INSERT INTO storage_quotas (user_id, quota_bytes)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE quota_bytes = VALUES(quota_bytes)
");
$stmt->bind_param("ii", $user_id, $quota_bytes);
$stmt->execute();

header("Location: quota.php?updated=1");
exit;
