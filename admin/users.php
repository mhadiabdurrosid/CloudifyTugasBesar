<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("Akses ditolak.");
}

require_once __DIR__ . '/../model/Koneksi.php';
$db = new koneksi();
$conn = $db->getConnection();

// Fetch semua user
$users = $conn->query("SELECT id, display_name, email, role FROM users ORDER BY id ASC");

$userData = $_SESSION['user_data'] ?? [
    'nama_lengkap' => $_SESSION['nama'] ?? 'Admin',
    'email' => $_SESSION['email'] ?? '',
    'foto_url' => $_SESSION['foto_url'] ?? '',
];
$initial = strtoupper(substr($userData['nama_lengkap'],0,1));
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Admin — Cloudify</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<style>
:root{--bg:#f6f8fb; --card:#fff; --muted:#6b7280; --accent:#6366f1;}
body{margin:0;font-family:Inter,sans-serif;background:var(--bg);color:#111}
.app{display:flex;min-height:100vh;}
.sidebar{width:260px;background:#0f1724;color:#fff;padding:22px 18px;flex-shrink:0}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.logo img{width:38px;height:38px;object-fit:contain}
.logo h1{font-size:18px;margin:0;font-weight:600}
.nav a{display:block;color:rgba(255,255,255,.85);text-decoration:none;padding:10px;border-radius:8px;margin-bottom:6px}
.nav a.active,.nav a:hover{background:rgba(255,255,255,.06)}
.profile-mini{display:flex;align-items:center;gap:12px;margin-top:14px;padding:12px;background:rgba(255,255,255,.04);border-radius:10px}
.profile-mini .pic{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--accent);font-weight:600;color:#fff;font-size:18px}
.main{flex:1;padding:22px 28px}
h3{margin-top:0;margin-bottom:12px}
.card{background:var(--card);padding:20px;border-radius:12px;box-shadow:0 6px 18px rgba(0,0,0,0.06)}
.btn{padding:6px 12px;border-radius:8px;border:0;cursor:pointer;font-size:13px;transition:all 0.2s}
.btn-add{background:#6366f1;color:#fff;margin-bottom:12px}
.btn-add:hover{background:#4f46e5}
.btn-edit{background:#0ea5e9}
.btn-edit:hover{background:#0284c7}
.btn-reset{background:#f59e0b}
.btn-reset:hover{background:#d97706}
.btn-del{background:#ef4444}
.btn-del:hover{background:#dc2626}

/* Table Users */
.table-users{width:100%;border-collapse:collapse;box-shadow:0 4px 16px rgba(0,0,0,0.05);border-radius:12px;overflow:hidden}
.table-users th{background:var(--accent);color:white;padding:12px;text-align:left;font-size:14px}
.table-users td{background:white;padding:12px;border-bottom:1px solid #f2f2f7;font-size:14px}
.table-users tr:hover{background:#f3f4f6}

/* Modal */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,0.45);display:none;align-items:center;justify-content:center;z-index:2000}
.modal-box{background:white;width:360px;padding:22px;border-radius:14px;box-shadow:0 8px 30px rgba(0,0,0,0.2)}
.modal-box h3{margin-top:0;margin-bottom:14px;font-size:18px}
.modal-box input,.modal-box select{width:100%;padding:10px;border-radius:8px;border:1px solid #ddd;margin-bottom:10px}
.modal-actions{display:flex;justify-content:space-between}
.modal-cancel{background:#e5e7eb;padding:8px 12px;border-radius:8px;cursor:pointer}
</style>
</head>
<body>
<div class="app">
  <aside class="sidebar">
    <div class="logo">
      <img src="../img/Logo Cloudify.png" alt="logo">
      <h1>Cloudify Admin</h1>
    </div>
    <div class="profile-mini">
      <div class="pic"><?=htmlspecialchars($initial)?></div>
      <div>
        <div style="font-weight:600"><?=htmlspecialchars($userData['nama_lengkap'])?></div>
        <div style="font-size:12px;color:rgba(255,255,255,0.75)"><?=htmlspecialchars($userData['email'])?></div>
      </div>
    </div>
    <nav class="nav">
      <a href="index.php" class="active"><i class="fa fa-house"></i> Dashboard</a>
      <!-- <a href="../index.php?show=cloud"><i class="fa fa-cloud"></i> Cloud Saya</a> -->
      <a href="?manage=users"><i class="fa fa-users"></i> Manage Users</a>
      <a href="?manage=files"><i class="fa fa-file"></i> Manage Files</a>
      <a href="?manage=storage"><i class="fa fa-database"></i> Storage</a>
      <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <main class="main">
    <div class="card">
      <h3>👥 Kelola User</h3>
      <button class="btn btn-add" onclick="openAddModal()">+ Tambah User Baru</button>

      <div style="overflow-x:auto">
      <table class="table-users">
        <thead>
          <tr>
            <th>ID</th>
            <th>Nama</th>
            <th>Email</th>
            <th>Role</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php while ($u = $users->fetch_assoc()): ?>
          <tr>
            <td><?= $u['id'] ?></td>
            <td><?= htmlspecialchars($u['display_name']) ?></td>
            <td><?= htmlspecialchars($u['email']) ?></td>
            <td><?= $u['role'] ?></td>
            <td>
              <button class="btn btn-edit" onclick="openEditModal(<?= $u['id'] ?>,'<?= $u['display_name'] ?>','<?= $u['email'] ?>','<?= $u['role'] ?>')">Edit</button>
              <button class="btn btn-reset" onclick="resetPass(<?= $u['id'] ?>)">Reset</button>
              <button class="btn btn-del" onclick="hapusUser(<?= $u['id'] ?>)">Hapus</button>
            </td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
      </div>
    </div>
  </main>
</div>

<!-- MODAL ADD -->
<div id="modal-add" class="modal-overlay">
  <div class="modal-box">
    <h3>Tambah User</h3>
    <form action="save_user.php" method="post">
        <input type="text" name="nama" placeholder="Nama lengkap" required>
        <input type="email" name="email" placeholder="Email login" required>
        <input type="password" name="password" placeholder="Password awal" required>
        <select name="role">
            <option value="user">User</option>
            <option value="pegawai">Pegawai</option>
        </select>
        <div class="modal-actions">
            <button class="btn btn-add">Simpan</button>
            <button type="button" class="modal-cancel" onclick="closeAddModal()">Batal</button>
        </div>
    </form>
  </div>
</div>

<!-- MODAL EDIT -->
<div id="modal-edit" class="modal-overlay">
  <div class="modal-box">
    <h3>Edit User</h3>
    <form action="update_user.php" method="post">
        <input type="hidden" id="edit-id" name="id">
        <input type="text" id="edit-nama" name="nama" required>
        <input type="email" id="edit-email" name="email" required>
        <select name="role" id="edit-role">
            <option value="user">User</option>
            <option value="pegawai">Pegawai</option>
        </select>
        <div class="modal-actions">
            <button class="btn btn-edit">Update</button>
            <button type="button" class="modal-cancel" onclick="closeEditModal()">Batal</button>
        </div>
    </form>
  </div>
</div>

<script>
// Modal Add
function openAddModal(){ document.getElementById('modal-add').style.display='flex'; }
function closeAddModal(){ document.getElementById('modal-add').style.display='none'; }

// Modal Edit
function openEditModal(id,nama,email,role){
    document.getElementById('edit-id').value=id;
    document.getElementById('edit-nama').value=nama;
    document.getElementById('edit-email').value=email;
    document.getElementById('edit-role').value=role;
    document.getElementById('modal-edit').style.display='flex';
}
function closeEditModal(){ document.getElementById('modal-edit').style.display='none'; }

// Hapus User
function hapusUser(id){ if(confirm("Hapus user ini?")) location.href="delete_user.php?id="+id; }

// Reset Password
function resetPass(id){ if(confirm("Reset password user ini?")) location.href="reset_pass.php?id="+id; }
</script>
</body>
</html>
