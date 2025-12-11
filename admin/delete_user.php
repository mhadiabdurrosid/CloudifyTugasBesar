<?php
session_start();
if ($_SESSION['role'] !== 'admin') die("Akses ditolak");

require_once __DIR__ . '/../model/Koneksi.php';
$db = new koneksi();
$conn = $db->getConnection();

$id = $_GET['id'];

$conn->query("DELETE FROM users WHERE id=$id");
header("Location: index.php?manage=users&deleted=1");
