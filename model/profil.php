<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ===============================================
// 🚨 CEK LOGIN
// ===============================================
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// ===============================================
// 🚀 LOAD DATA USER
// ===============================================
$default_user_data = [
    'username'      => 'User',
    'email'         => 'user@example.com',
    'nama_lengkap'  => 'Pengguna Baru',
    'jabatan'       => 'User',
    'foto_url'      => '',
    'initials'      => 'U',
    'theme'         => 'light',
    'storage_used'  => 0,
    'storage_limit' => 5000 // default 5GB
];

$_SESSION['user_data'] = $_SESSION['user_data'] ?? $default_user_data;
$user = &$_SESSION['user_data'];

// ===============================================
// 📌 SAMBUNG DATABASE UNTUK STORAGE
// ===============================================
$storage_used = $user['storage_used']; // fallback

require_once "../model/Koneksi.php";

try {
    $conn = $koneksi->getConnection();

    // Jika tabel files ada → hitung ukuran file user
    $q = $conn->query("
        SELECT COALESCE(SUM(size),0) AS total 
        FROM files 
        WHERE uploaded_by = '$user_id'
    ");

    if ($q && $r = $q->fetch_assoc()) {
        $storage_used = (int)$r['total'];
        $_SESSION['user_data']['storage_used'] = $storage_used;
    }

} catch (Throwable $e) {
    // fallback tanpa error
}

// ===============================================
// 🧩 INISIAL NAMA
// ===============================================
$nameParts = explode(" ", trim($user['nama_lengkap']));
$initial = strtoupper(substr($nameParts[0], 0, 1));
if (count($nameParts) > 1) {
    $initial .= strtoupper(substr(end($nameParts), 0, 1));
}
if (!$initial) $initial = "U";

$user['initials'] = $initial;

// ===============================================
// 📦 STORAGE
// ===============================================
$used = $storage_used;
$limit = $user['storage_limit'] * 1024 * 1024; // jika limit dalam MB
$percent = ($limit > 0) ? round(($used / $limit) * 100) : 0;

// Format ukuran
function _fmt($b){
    if ($b >= 1073741824) return round($b/1073741824,1).' GB';
    if ($b >= 1048576) return round($b/1048576,1).' MB';
    if ($b >= 1024) return round($b/1024,1).' KB';
    return $b . ' B';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Profil — Cloudify</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
body {
    margin: 0;
    font-family: "Google Sans", "Segoe UI", sans-serif;
    background: #f5f5f5;
}
body.dark { background:#1e1e1e; color:white; }

.sidebar {
    width:250px;
    height:100vh;
    background:#1f2937;
    color:white;
    position:fixed;
    left:0; top:0;
    padding:20px;
}

.menu a {
    display:block;
    padding:12px;
    margin-top:5px;
    color:#cbd5e1;
    text-decoration:none;
    border-radius:8px;
}
.menu a:hover, .menu a.active {
    background:#4b5563;
    transform:translateX(5px);
}

.content {
    margin-left:270px;
    padding:30px;
}

/* CARD PROFIL */
.profile-card {
    width:100%;
    max-width:520px;
    margin:auto;
    background:white;
    border-radius:18px;
    padding:30px;
    text-align:center;
    box-shadow:0 5px 18px rgba(0,0,0,.1);
}
body.dark .profile-card { background:#2d2d2d; }

/* FOTO */
.profile-photo {
    width:110px;
    height:110px;
    border-radius:50%;
    object-fit:cover;
    background:#6366f1;
    border:4px solid #2563eb;
    color:white;
    font-size:48px;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:auto;
}

/* EMAIL */
.email-text {
    margin-top:15px;
    font-size:14px;
    opacity:.7;
}

/* NAMA */
h2.username {
    margin:10px 0 0 0;
    font-size:26px;
}

/* STORAGE BAR */
.storage-box {
    margin-top:25px;
    text-align:left;
}
.storage-bar {
    width:100%;
    height:12px;
    background:#ddd;
    border-radius:10px;
    overflow:hidden;
}
.storage-fill {
    height:100%;
    width:<?= $percent ?>%;
    background:#2563eb;
}
</style>
</head>

<body class="<?= $user['theme'] == 'dark' ? 'dark' : '' ?>">

<div class="sidebar">
    <h2>⚙ Pengaturan</h2>

    <div class="menu">
        <a href="../index.php?show=home">🏠 Beranda</a>
        <a href="../index.php?show=cloud">☁ Cloud Saya</a>
        <a class="active" href="profil.php">👤 Profil</a>
        <a href="pengaturan.php">⚙ Pengaturan</a>
        <a href="../logout.php">🚪 Logout</a>
    </div>
</div>

<div class="content">

    <div class="profile-card">

        <!-- FOTO -->
        <?php if (!empty($user['foto_url'])): ?>
            <img src="../<?= htmlspecialchars($user['foto_url']) ?>" class="profile-photo">
        <?php else: ?>
            <div class="profile-photo"><?= $initial ?></div>
        <?php endif; ?>

        <!-- EMAIL DI ATAS NAMA -->
        <p class="email-text"><?= htmlspecialchars($user['email']) ?></p>

        <!-- NAMA -->
        <h2 class="username">Halo, <?= htmlspecialchars($user['nama_lengkap']) ?>.</h2>

        <!-- JABATAN -->
        <p><b><?= htmlspecialchars($user['jabatan']) ?></b></p>

        <!-- BUTTON -->
        <button class="btn" onclick="window.location.href='pengaturan.php'">
            ✏️ Ubah Profil
        </button>

        <!-- STORAGE -->
        <div class="storage-box">
            <p>☁ <?= _fmt($used) ?> dari <?= _fmt($limit) ?> digunakan</p>
            <div class="storage-bar">
                <div class="storage-fill"></div>
            </div>
        </div>

    </div>
</div>

</body>
</html>
