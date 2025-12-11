<?php
session_start();
if ($_SESSION['role'] !== 'admin') die("Akses ditolak");

require_once __DIR__ . '/../model/Koneksi.php';
$db = new koneksi();
$conn = $db->getConnection();

$nama = $_POST['nama'];
$email = $_POST['email'];
$pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
$role = $_POST['role'];

$stmt = $conn->prepare("INSERT INTO users(display_name,email,password,role) VALUES (?,?,?,?)");
$stmt->bind_param("ssss",$nama,$email,$pass,$role);
$stmt->execute();

header("Location: index.php?manage=users&ok=1");
