<?php
session_start();
if ($_SESSION['role'] !== 'admin') die("Akses ditolak");

require_once __DIR__ . '/../model/Koneksi.php';
$db = new koneksi();
$conn = $db->getConnection();

$id = $_POST['id'];
$nama = $_POST['nama'];
$email = $_POST['email'];
$role = $_POST['role'];

$stmt = $conn->prepare("UPDATE users SET display_name=?, email=?, role=? WHERE id=?");
$stmt->bind_param("sssi",$nama,$email,$role,$id);
$stmt->execute();

header("Location: index.php?manage=users&updated=1");
