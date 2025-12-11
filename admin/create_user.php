<?php
session_start();
require_once("../model/Koneksi.php");

if ($_SESSION['role'] !== 'admin') {
    die("Akses ditolak!");
}

$db = new koneksi();
$conn = $db->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email']);
    $nama = trim($_POST['nama']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (email, display_name, password, role) VALUES (?, ?, ?, 'user')");
    $stmt->bind_param("sss", $email, $nama, $password);
    $stmt->execute();

    header("Location: users.php?success=created");
    exit;
}
?>
