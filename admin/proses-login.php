<?php
session_start();
header("Content-Type: application/json"); // wajib agar fetch tidak error JSON

require_once "../model/Koneksi.php";

$db = new koneksi();
$conn = $db->getConnection();

$email = $_POST['email'] ?? "";
$password = $_POST['password'] ?? "";

// VALIDASI DASAR
if (empty($email) || empty($password)) {
    echo json_encode(["success"=>false, "message"=>"Email dan password wajib diisi"]);
    exit;
}

// QUERY (username DIHAPUS, jadi JANGAN dipanggil)
$stmt = $conn->prepare("SELECT id, email, display_name, role, password FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();

// EMAIL TIDAK ADA
if ($res->num_rows === 0) {
    echo json_encode(["success"=>false, "message"=>"Email tidak ditemukan"]);
    exit;
}

$user = $res->fetch_assoc();

// PASSWORD SALAH
if (!password_verify($password, $user['password'])) {
    echo json_encode(["success"=>false, "message"=>"Password salah"]);
    exit;
}

// LOGIN BERHASIL → SET SESSION
$_SESSION['user_id'] = $user['id'];
$_SESSION['email']   = $user['email'];
$_SESSION['nama']    = $user['display_name'];
$_SESSION['role']    = $user['role']; // user / admin / pegawai

echo json_encode([
    "success" => true,
    "nama"    => $user['display_name'],
    "role"    => $user['role']
]);
exit;
