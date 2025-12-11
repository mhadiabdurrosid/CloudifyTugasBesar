<?php
require_once(__DIR__ . '/../../model/auth.php');

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
  <a href="/crud/dashboardmain/tambah-user.php" class="btn-add">
    <i class="fas fa-user-plus"></i> Tambah User
  </a>
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
        <a href="/crud/dashboardmain/edit-user.php?id=<?= $u['id_pengguna']; ?>" class="btn-edit"><i class="fas fa-edit"></i></a>
        <a href="/crud/dashboardmain/hapus-user.php?id=<?= $u['id_pengguna']; ?>" class="btn-delete" onclick="return confirm('Hapus user ini?');"><i class="fas fa-trash"></i></a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</div>

</body>
</html>
