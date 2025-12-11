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

$check = $conn->prepare("SELECT id FROM favorites WHERE folder_id = ? AND user_id = ?");
$check->bind_param("ii", $folderId, $userId);
$check->execute();
$res = $check->get_result()->fetch_assoc();

if ($res) {
    // hapus
    $del = $conn->prepare("DELETE FROM favorites WHERE id = ?");
    $del->bind_param("i", $res['id']);
    $del->execute();
    echo json_encode(['success'=>true,'favorited'=>false]);
} else {
    // tambah
    $add = $conn->prepare("INSERT INTO favorites(user_id, folder_id) VALUES(?,?)");
    $add->bind_param("ii", $userId, $folderId);
    $add->execute();
    echo json_encode(['success'=>true,'favorited'=>true]);
}
