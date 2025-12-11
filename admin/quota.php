<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/../model/Koneksi.php');

// Hanya admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

$db   = new koneksi();
$conn = $db->getConnection();

// Ambil semua user
$users = $conn->query("SELECT id, display_name, email, role FROM users ORDER BY id ASC");

// Ambil total digunakan
function getUsed($conn, $uid){
    $st = $conn->prepare("SELECT COALESCE(SUM(size),0) AS used FROM files WHERE owner_id = ?");
    $st->bind_param("i",$uid);
    $st->execute();
    return (int)$st->get_result()->fetch_assoc()['used'];
}

// Ambil kuota
function getQuota($conn, $uid){
    $st = $conn->prepare("SELECT quota_bytes FROM storage_quotas WHERE user_id = ? LIMIT 1");
    $st->bind_param("i",$uid);
    $st->execute();
    $res = $st->get_result()->fetch_assoc();
    return $res["quota_bytes"] ?? null;
}

function fmt($b){
    if ($b >= 1073741824) return round($b/1073741824,1)." GB";
    if ($b >= 1048576)   return round($b/1048576,1)." MB";
    return $b." B";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pengaturan Kuota User — Cloudify Admin</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap">

<style>
body{
    font-family:'Inter',sans-serif;
    background:#f4f6fb;
    margin:0; padding:25px;
}
h2{
    font-size:26px; margin-bottom:20px; font-weight:700;
}
.box{
    background:white;
    padding:20px;
    border-radius:16px;
    box-shadow:0 8px 28px rgba(0,0,0,.08);
}
table{
    width:100%; border-collapse:collapse; margin-top:10px;
}
th{
    background:#6366f1; color:white;
    padding:14px;
    font-size:14px; text-align:left;
}
td{
    padding:14px; background:white;
    border-bottom:1px solid #eee;
}
input[type='number']{
    padding:8px 10px;
    width:110px;
    border-radius:8px;
    border:1px solid #ccc;
}
.save-btn{
    background:#4f46e5;
    padding:8px 16px;
    color:white; border:0;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
}
.save-btn:hover{ background:#3f37d2; }

.del-btn{
    background:#ef4444; 
    padding:6px 12px;
    border-radius:8px;
    border:0;
    cursor:pointer; color:white;
}
.del-btn:hover{ background:#dc2626; }

.badge{
    padding:4px 10px; border-radius:8px;
    font-size:12px; font-weight:600;
}
.role-admin{ background:#dc2626; color:white; }
.role-user{ background:#2563eb; color:white; }
.role-pegawai{ background:#16a34a; color:white; }

.msg{
    background:#d1fae5;
    color:#047857;
    padding:12px;
    border-radius:8px;
    margin-bottom:12px;
    font-weight:600;
}
</style>
</head>

<body>

<?php if(isset($_GET['updated'])): ?>
<div class="msg"><i class="fa fa-check"></i> Kuota berhasil diperbarui!</div>
<?php endif; ?>

<?php if(isset($_GET['deleted'])): ?>
<div class="msg" style="background:#fee2e2;color:#b91c1c">
    <i class="fa fa-trash"></i> Kuota user berhasil dihapus!
</div>
<?php endif; ?>

<h2>⚙️ Pengaturan Kuota Penyimpanan User</h2>

<div class="box">
<table>
    <tr>
        <th>ID</th>
        <th>Nama</th>
        <th>Role</th>
        <th>Dipakai</th>
        <th>Kuota Saat Ini</th>
        <th>Set Kuota Baru (GB)</th>
        <th>Aksi</th>
    </tr>

<?php while($u = $users->fetch_assoc()): 
    $used  = getUsed($conn, $u["id"]);
    $quota = getQuota($conn, $u["id"]);
?>
<tr>
    <form action="save_quota.php" method="POST">

        <td><?= $u['id'] ?></td>
        <td><?= htmlspecialchars($u['display_name']) ?></td>

        <td><span class="badge role-<?= strtolower($u['role']) ?>">
            <?= ucfirst($u['role']) ?>
        </span></td>

        <td><?= fmt($used) ?></td>

        <td>
            <?= $quota ? fmt($quota) : "<i style='color:#999'>Belum Diatur</i>" ?>
        </td>

        <td>
            <input type="number" min="10" max="200" name="quota_gb"
                   placeholder="10 - 200 GB" required>
        </td>

        <td>
            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
            <button class="save-btn"><i class="fa fa-save"></i> Simpan</button>

            <?php if($quota): ?>
                <a href="save_quota.php?delete=<?= $u['id'] ?>"
                   class="del-btn"
                   onclick="return confirm('Hapus kuota user ini?')">
                   <i class="fa fa-trash"></i>
                </a>
            <?php endif; ?>
        </td>

    </form>
</tr>
<?php endwhile; ?>
</table>
</div>

</body>
</html>
