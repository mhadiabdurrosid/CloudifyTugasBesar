<?php
// Hapus favorit: set is_favorite = 0
require_once __DIR__ . '/../../model/Koneksi.php';
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) die('ID tidak valid');
$id = (int)$_GET['id'];
$conn = $koneksi->getConnection();
$stmt = $conn->prepare('UPDATE files SET is_favorite = 0 WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();
header('Location: ../../index.php?page=favorit');
exit;