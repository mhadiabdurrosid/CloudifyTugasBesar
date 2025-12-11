<?php
session_start();
require_once __DIR__ . '/../model/Koneksi.php';

$koneksi = new koneksi();
$conn = $koneksi->getConnection();

$userId = (int)($_SESSION['user_id'] ?? 0);
$folderId = (int)($_POST['id'] ?? 0);
$newName = trim($_POST['name'] ?? '');

if ($folderId <= 0 || $newName == '') {
    die(json_encode(['success'=>false,'message'=>'Nama folder tidak valid']));
}

$stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ? AND owner_id = ?");
$stmt->bind_param("sii", $newName, $folderId, $userId);
$stmt->execute();

echo json_encode(['success'=>true]);
