<?php
session_start();
require_once __DIR__ . '/../model/Koneksi.php';

if (!isset($_SESSION['user_id'])) {
    exit("Tidak ada akses");
}

$userId = (int) $_SESSION['user_id'];

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit("ID file tidak valid");
}

$fileId = (int) $_GET['id'];

$db = new koneksi();
$conn = $db->getConnection();

// Ambil file berdasarkan owner
$sql = "SELECT name, path FROM files WHERE id = ? AND owner_id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $fileId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if (!$result || !$row = $result->fetch_assoc()) {
    exit("File tidak ditemukan atau bukan milik Anda");
}

$realPath = realpath(__DIR__ . '/../' . $row['path']);
$filename = $row['name'];

if (!$realPath || !file_exists($realPath)) {
    exit("File tidak ditemukan di server");
}

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: ' . filesize($realPath));

readfile($realPath);
exit;
