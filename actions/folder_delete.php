<?php
session_start();
require_once __DIR__ . '/../model/Koneksi.php';

$koneksi = new koneksi();
$conn = $koneksi->getConnection();

$userId = (int)($_SESSION['user_id'] ?? 0);
$folderId = (int)($_GET['id'] ?? 0);

if ($folderId <= 0) {
    die(json_encode(['success'=>false,'message'=>'Folder tidak ditemukan']));
}

$stmt = $conn->prepare("UPDATE folders SET is_deleted = 1 WHERE id = ? AND owner_id = ?");
$stmt->bind_param("ii", $folderId, $userId);
$stmt->execute();

echo json_encode(['success'=>true, 'message'=>'Folder dipindahkan ke sampah']);
