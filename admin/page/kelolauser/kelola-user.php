<?php
require_once(__DIR__ . '/../../../model/auth.php');

$auth = new Auth();

// data tabel user
$dataUser = $auth->getAll();

// dashboard
$jumlahUser = $auth->countAll();
$jumlahUserAktif = $auth->countActive();
$jumlahUserNonAktif = $auth->countInactive();
?>

<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Kelola User</title>
  <link rel="stylesheet" href="../asset/dashboard.css">
  <link rel="stylesheet" href="../asset/dashboard-main.css">
  <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</head>
<body>

<h2 class="dashboard-title"><i class="fas fa-users"></i> Kelola User</h2>

<div class="alert-box">
  <strong>Hi,</strong> Selamat Datang Admin — halaman ini untuk mengelola user.
  <span class="close-btn" onclick="this.parentElement.style.display='none';">&times;</span>
</div>

<!-- Statistik -->
<div class="dashboard-cards">

  <div class="card card-blue">
    <div class="card-info">
      <div class="card-title">Total User</div>
      <div class="card-count"><?= $jumlahUser; ?></div>
    </div>
    <div class="card-icon"><i class="fas fa-user-friends"></i></div>
  </div>

  <div class="card card-green">
    <div class="card-info">
      <div class="card-title">User Aktif</div>
      <div class="card-count"><?= $jumlahUserAktif; ?></div>
    </div>
    <div class="card-icon"><i class="fas fa-user-check"></i></div>
  </div>

  <div class="card card-red">
    <div class="card-info">
      <div class="card-title">User Non-Aktif</div>
      <div class="card-count"><?= $jumlahUserNonAktif; ?></div>
    </div>
    <div class="card-icon"><i class="fas fa-user-slash"></i></div>
  </div>

</div>

<!-- Tombol Tambah User -->
<div style="margin-top: 25px; margin-bottom: 15px;">
  <button class="btn-add" onclick="openAddModal()"> 
    <i class="fas fa-user-plus"></i> Tambah User
  
</div>

<!-- Tabel User -->
<div class="table-container">
<table class="custom-table">
  <thead>
    <tr>
      <th>No</th>
      <th>Nama</th>
      <th>Email</th>
      <th>Level</th>
      <th>Status</th>
      <th>Aksi</th>
    </tr>
  </thead>

  <tbody>
  <?php 
  $no = 1;
  foreach ($dataUser as $u): ?>
    <tr>
      <td><?= $no++; ?></td>
      <td><?= $u['nama']; ?></td>
      <td><?= $u['email']; ?></td>
      <td><?= ucfirst($u['level']); ?></td>
      <td>
        <?php if ($u['status'] == 1): ?>
          <span class="status-active">Aktif</span>
        <?php else: ?>
          <span class="status-nonactive">Tidak Aktif</span>
        <?php endif; ?>
      </td>
      <td>  
        <!-- <a href="/crud/dashboardmain/edit-user.php?id=<?= $u['id_pengguna']; ?>" class="btn-edit"><i class="fas fa-edit"></i></a> -->
         <button class="btn-edit" onclick="openEditModal(<?= $u['id_pengguna']; ?>, '<?= $u['nama']; ?>', '<?= $u['email']; ?>', '<?= $u['level']; ?>', <?= $u['status']; ?>)">
        <a href="/crud/dashboardmain/hapus-user.php?id=<?= $u['id_pengguna']; ?>" class="btn-delete" onclick="return confirm('Hapus user ini?');"><i class="fas fa-trash"></i></a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

<div id="userModal" class="modal-overlay" style="display:none;">
  <div class="modal-box">
    <h3 id="modal-title">Tambah User</h3>

    <form method="POST" id="userForm">

      <input type="hidden" name="id_pengguna" id="id_pengguna">

      <label>Nama</label>
      <input type="text" name="nama" id="nama" required>

      <label>Email</label>
      <input type="email" name="email" id="email" required>

      <label>Password</label>
      <input type="password" name="password" id="password">

      <label>Level</label>
      <select name="level" id="level" required>
        <option value="admin">Admin</option>
        <option value="pengguna">Pengguna</option>
      </select>

      <label>Status</label>
      <select name="status" id="status" required>
        <option value="1">Aktif</option>
        <option value="0">Tidak Aktif</option>
      </select>

      <button type="submit" name="simpan" class="btn-submit">Simpan</button>
      <button type="button" class="btn-close" onclick="closeModal()">Tutup</button>
    </form>
  </div>
</div>

<script>
function openAddModal() {
    document.getElementById("modal-title").innerText = "Tambah User";
    document.getElementById("userForm").action = "/crud/dashboardmain/tambah-user.php";

    // kosongkan field
    document.getElementById("id_pengguna").value = "";
    document.getElementById("nama").value = "";
    document.getElementById("email").value = "";
    document.getElementById("password").required = true;
    document.getElementById("password").value = "";
    document.getElementById("level").value = "pengguna";
    document.getElementById("status").value = "1";

    document.getElementById("userModal").style.display = "flex";
}

function openEditModal(id, nama, email, level, status) {
    document.getElementById("modal-title").innerText = "Edit User";
    document.getElementById("userForm").action = "/crud/dashboardmain/update-user.php";

    document.getElementById("id_pengguna").value = id;
    document.getElementById("nama").value = nama;
    document.getElementById("email").value = email;
    document.getElementById("password").required = false;
    document.getElementById("password").value = "";
    document.getElementById("level").value = level;
    document.getElementById("status").value = status;

    document.getElementById("userModal").style.display = "flex";
}

function closeModal() {
    document.getElementById("userModal").style.display = "none";
}
</script>
</body>
</html>
