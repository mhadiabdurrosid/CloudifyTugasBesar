<?php
if (session_status() === PHP_SESSION_NONE) session_start();
error_reporting(E_ALL); 
ini_set('display_errors',1);

// 🔐 Akses admin saja
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    echo "Akses ditolak.";
    exit;
}

// 🔌 Koneksi DB
require_once __DIR__ . '/../model/Koneksi.php';
$db = new koneksi();
$conn = $db->getConnection();

// -----------------------------------------------------
// Ambil data file (semua, termasuk yang terhapus)
// -----------------------------------------------------
$files = [];
$q = $conn->prepare("
    SELECT f.id, f.name, f.size, f.owner_id, f.path, f.is_deleted, 
           f.created_at, u.display_name 
    FROM files f 
    LEFT JOIN users u ON u.id = f.owner_id 
    ORDER BY f.created_at DESC
");
$q->execute();
$res = $q->get_result();
while ($r = $res->fetch_assoc()) $files[] = $r;
$q->close();

// Format ukuran file
function fmt($b){
    $b = (int)$b;
    if($b >= 1073741824) return round($b/1073741824,2)." GB";
    if($b >= 1048576)  return round($b/1048576,2)." MB";
    if($b >= 1024)     return round($b/1024,2)." KB";
    return $b." B";
}

// Data profil admin
$userData = $_SESSION['user_data'] ?? [
    'nama_lengkap' => $_SESSION['nama'] ?? 'Administrator',
    'email'        => $_SESSION['email'] ?? '',
    'foto_url'     => $_SESSION['foto_url'] ?? '',
];
$initial = strtoupper(substr($userData['nama_lengkap'],0,1));
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Files — Cloudify Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

<style>
:root{
    --bg:#f4f6fb;
    --sidebar:#0f172a;
    --card:rgba(255,255,255,0.82);
    --blur:blur(14px);
    --accent:#6366f1;
    --muted:#6b7280;
}
body{
    margin:0;
    background:var(--bg);
    font-family:Inter, sans-serif;
}
.app{
    display:flex;
    min-height:100vh;
}

/* SIDEBAR */
.sidebar{
    width:260px;
    padding:24px 20px;
    background:var(--sidebar);
    color:white;
    box-shadow:4px 0 18px rgba(0,0,0,.15);
}
.logo{
    display:flex;align-items:center;gap:12px;margin-bottom:20px;
}
.logo img{
    width:40px;height:40px;
}
.logo h1{
    font-size:18px;font-weight:600;margin:0;
}
.nav a{
    display:block;
    padding:12px;
    margin-bottom:6px;
    border-radius:10px;
    text-decoration:none;
    font-size:15px;
    color:rgba(255,255,255,0.85);
    transition:.25s;
}
.nav a:hover,
.nav a.active{
    background:rgba(255,255,255,.08);
    transform:translateX(4px);
}

/* PROFILE MINI */
.profile-mini{
    background:rgba(255,255,255,.08);
    padding:14px;
    border-radius:12px;
    display:flex;
    gap:12px;
    align-items:center;
    margin-bottom:20px;
}
.profile-mini .pic{
    width:46px;height:46px;
    background:var(--accent);
    border-radius:50%;
    display:flex;justify-content:center;align-items:center;
    color:white;font-weight:600;font-size:18px;
}

/* MAIN */
.main{
    flex:1;
    padding:28px;
}

/* CARD GLASS UI */
.card{
    background:var(--card);
    padding:22px;
    border-radius:16px;
    backdrop-filter:var(--blur);
    box-shadow:0 8px 32px rgba(0,0,0,.10);
    margin-bottom:20px;
    animation:fade .5s ease;
}
@keyframes fade{
    from{opacity:0; transform:translateY(10px);}
    to{opacity:1; transform:translateY(0);}
}

.table{
    width:100%;
    border-collapse:collapse;
    border-radius:12px;
    overflow:hidden;
}
.table th{
    background:var(--accent);
    color:white;
    padding:14px;
    font-size:14px;
}
.table td{
    padding:14px;
    background:white;
    font-size:14px;
}
.table tr:hover td{
    background:#f1f3ff;
}

/* Buttons */
.btn{
    padding:7px 12px;
    border-radius:6px;
    border:none;
    cursor:pointer;
    font-size:13px;
    transition:.25s;
}
.btn-download{background:#10b981;color:white;}
.btn-download:hover{background:#059669;}

.btn-delete{background:#ef4444;color:white;}
.btn-delete:hover{background:#dc2626;}

.btn-restore{background:#6366f1;color:white;}
.btn-restore:hover{background:#4f46e5;}

.status-active{
    padding:4px 10px;
    border-radius:8px;
    background:#d1fae5;
    color:#065f46;
    font-size:13px;
}
.status-deleted{
    padding:4px 10px;
    border-radius:8px;
    background:#fee2e2;
    color:#991b1b;
    font-size:13px;
}
</style>
</head>

<body>
<div class="app">

<!-- SIDEBAR -->
<aside class="sidebar">
    <div class="logo">
        <img src="../img/Logo Cloudify.png">
        <h1>Cloudify Admin</h1>
    </div>

    <div class="profile-mini">
        <div class="pic"><?= $initial ?></div>
        <div>
            <div style="font-weight:600"><?= htmlspecialchars($userData['nama_lengkap']) ?></div>
            <div style="font-size:13px;opacity:.8"><?= htmlspecialchars($userData['email']) ?></div>
        </div>
    </div>

    <nav class="nav">
        <a href="index.php"><i class="fa fa-house"></i> Dashboard</a>
        <a href="index.php?manage=users"><i class="fa fa-users"></i> Manage Users</a>
        <a href="files.php" class="active"><i class="fa fa-file"></i> Manage Files</a>
        <a href="index.php?manage=storage"><i class="fa fa-database"></i> Storage</a>
        <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
</aside>

<!-- MAIN CONTENT -->
<main class="main">

    <div class="card">
        <h2 style="margin-top:0;">Kelola File</h2>
        <p style="margin-top:-6px;color:var(--muted)">Menghapus / mengembalikan / menghapus permanen file secara cepat.</p>

        <div style="overflow-x:auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Ukuran</th>
                        <th>Owner</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($files as $f): ?>
                    <tr data-id="<?= $f['id'] ?>">
                        <td><?= htmlspecialchars($f['name']) ?></td>
                        <td><?= fmt($f['size']) ?></td>
                        <td><?= htmlspecialchars($f['display_name'] ?: $f['owner_id']) ?></td>
                        <td><?= htmlspecialchars($f['created_at']) ?></td>

                        <td class="status">
                            <?= $f['is_deleted'] ? 
                            '<span class="status-deleted">Deleted</span>' :
                            '<span class="status-active">Active</span>' ?>
                        </td>

                        <td class="actions">
                        <?php if (!$f['is_deleted']): ?>
                            <?php if (!empty($f['path'])): ?>
                                <a class="btn btn-download" href="<?= '../'.ltrim($f['path'],'/') ?>" download>Download</a>
                            <?php endif; ?>

                            <button class="btn btn-delete action-delete" data-id="<?= $f['id'] ?>">Delete</button>

                        <?php else: ?>
                            <button class="btn btn-restore action-restore" data-id="<?= $f['id'] ?>">Restore</button>
                            <button class="btn btn-delete action-delete-permanent" data-id="<?= $f['id'] ?>">Hapus Permanen</button>
                        <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>

<script>
// AJAX Handler untuk Delete / Restore / Delete Permanent
function ajaxAction(id, action){
    if (!confirm("Yakin ingin melakukan aksi ini?")) return;

    const form = new FormData();
    form.append("file_id", id);
    form.append("action", action);

    fetch("files_action.php", {
        method: "POST",
        body: form
    })
    .then(r => r.text())
    .then(resp => {
        if (resp.trim() === "OK") {
            location.reload();
        } else {
            alert("Gagal: " + resp);
        }
    });
}

// Event Listener Buttons
document.addEventListener("click", e => {
    if (e.target.classList.contains("action-delete")) 
        ajaxAction(e.target.dataset.id, "delete");

    if (e.target.classList.contains("action-restore"))
        ajaxAction(e.target.dataset.id, "restore");

    if (e.target.classList.contains("action-delete-permanent"))
        ajaxAction(e.target.dataset.id, "delete_permanent");
});
</script>

</body>
</html>
