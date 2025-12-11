<?php
// admin/index.php — Dashboard Admin + Manajemen Folder
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ----------------------------------------------------
// AUTH: pastikan user login & role = admin
// ----------------------------------------------------
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// ----------------------------------------------------
// KONEKSI DB
// ----------------------------------------------------
require_once __DIR__ . '/../model/Koneksi.php';
$conn = null;
try {
    if (class_exists('koneksi')) {
        $db = new koneksi();
        $conn = $db->getConnection();
    }
} catch (Throwable $e) {
    error_log("DB connect error: " . $e->getMessage());
    $conn = null;
    die("Koneksi database gagal.");
}

// ----------------------------------------------------
// HELPER: format size
// ----------------------------------------------------
function fmt_size($b){
    if ($b === null) return '—';
    $b = (int)$b;
    if ($b >= 1073741824) return round($b/1073741824,2).' GB';
    if ($b >= 1048576)    return round($b/1048576,2).' MB';
    if ($b >= 1024)       return round($b/1024,2).' KB';
    return $b . ' B';
}

// ----------------------------------------------------
// INTERNAL AJAX API: action=stats | action=recent
// ----------------------------------------------------
if (isset($_GET['action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_GET['action'];

    if ($action === 'stats') {
        $totalUsers = 0;
        $totalFiles = 0;
        $usedBytes  = 0;
        $quotaBytes = null;

        try {
            if ($conn) {
                // total users
                $q = $conn->prepare("SELECT COUNT(*) AS c FROM users");
                $q->execute();
                $res = $q->get_result();
                if ($res && $r = $res->fetch_assoc()) {
                    $totalUsers = (int)$r['c'];
                }
                $q->close();

                // total files + used size
                $q = $conn->prepare("SELECT COUNT(*) AS c, COALESCE(SUM(size),0) AS s FROM files WHERE (is_deleted IS NULL OR is_deleted = 0)");
                $q->execute();
                $res = $q->get_result();
                if ($res && $r = $res->fetch_assoc()) {
                    $totalFiles = (int)$r['c'];
                    $usedBytes  = (int)$r['s'];
                }
                $q->close();

                // storage quota per admin (jika ada)
                $adminId = (int)$_SESSION['user_id'];
                $q = $conn->prepare("SELECT quota_bytes FROM storage_quotas WHERE user_id = ? LIMIT 1");
                $q->bind_param('i', $adminId);
                $q->execute();
                $res = $q->get_result();
                if ($res && $r = $res->fetch_assoc()) {
                    $quotaBytes = (int)$r['quota_bytes'];
                }
                $q->close();
            }
        } catch (Throwable $e) {
            // abaikan, pakai default
        }

        if ($quotaBytes === null) {
            // default 30GB
            $quotaBytes = 30 * 1024 * 1024 * 1024;
        }

        $pct = ($quotaBytes > 0) ? round(($usedBytes / $quotaBytes) * 100, 2) : 0;

        echo json_encode([
            'ok'          => true,
            'totalUsers'  => $totalUsers,
            'totalFiles'  => $totalFiles,
            'usedBytes'   => $usedBytes,
            'quotaBytes'  => $quotaBytes,
            'usedHuman'   => fmt_size($usedBytes),
            'quotaHuman'  => fmt_size($quotaBytes),
            'pct'         => $pct
        ]);
        exit;
    }

    if ($action === 'recent') {
        $rows = [];
        try {
            if ($conn) {
                // Ambil 10 file terbaru
                $q = $conn->prepare("
                    SELECT f.id, f.name, f.size, f.owner_id, 
                           u.display_name AS owner_name, 
                           f.created_at 
                    FROM files f 
                    LEFT JOIN users u ON u.id = f.owner_id 
                    WHERE (f.is_deleted IS NULL OR f.is_deleted = 0)
                    ORDER BY f.created_at DESC 
                    LIMIT 10
                ");
                $q->execute();
                $res = $q->get_result();
                while ($r = $res->fetch_assoc()) {
                    $rows[] = $r;
                }
                $q->close();
            }
        } catch (Throwable $e) {
            // ignore
        }
        echo json_encode(['ok'=>true,'rows'=>$rows]);
        exit;
    }

    echo json_encode(['ok'=>false,'message'=>'unknown action']);
    exit;
}

// ----------------------------------------------------
// ROUTER: handle ?manage=users | files | storage
// ----------------------------------------------------
if (isset($_GET['manage'])) {
    $page = $_GET['manage'];

    if ($page === 'users') {
        require __DIR__ . '/users.php';
        exit;
    }
    if ($page === 'files') {
        require __DIR__ . '/files.php';
        exit;
    }
    if ($page === 'storage') {
        require __DIR__ . '/quota.php';
        exit;
    }
    if ($page === 'cloud') {
    require __DIR__ . '/cloud.php';
    exit;
    }

    echo "<h2>Halaman tidak ditemukan</h2>";
    exit;
}

// ----------------------------------------------------
// Ambil daftar user (untuk pemilik folder)
// ----------------------------------------------------
$allUsers = [];
if ($conn) {
    try {
        $q = $conn->query("SELECT id, display_name, email FROM users ORDER BY display_name ASC");
        while ($r = $q->fetch_assoc()) {
            $allUsers[] = $r;
        }
    } catch (Throwable $e) {}
}

// ----------------------------------------------------
// Ambil data user dari session
// ----------------------------------------------------
$userData = [
    'nama_lengkap' => $_SESSION['nama'] ?? 'Admin',
    'email'        => $_SESSION['email'] ?? '',
    'foto_url'     => $_SESSION['foto_url'] ?? ''
];
$nameParts = explode(' ', trim($userData['nama_lengkap']));
$initial   = strtoupper(substr($nameParts[0] ?? 'A', 0, 1));
if (count($nameParts) > 1) {
    $initial .= strtoupper(substr(end($nameParts), 0, 1));
}
if (!$initial) $initial = 'A';

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Dashboard — Cloudify</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<style>
:root{
  --bg:#f6f8fb; --card:#fff; --muted:#6b7280; --accent:#6366f1;
}
*{box-sizing:border-box}
body{margin:0;font-family:Inter, "Segoe UI", Roboto, sans-serif;background:var(--bg);color:#111}
.app{display:flex;min-height:100vh}

/* SIDEBAR */
.sidebar{
  width:260px;background:#0f1724;color:#fff;padding:22px 18px;flex-shrink:0;
}
.logo{display:flex;align-items:center;gap:12px;margin-bottom:18px}
.logo img{width:38px;height:38px;object-fit:contain}
.logo h1{font-size:18px;margin:0;font-weight:600}
.nav{margin-top:18px}
.nav a{display:block;color:rgba(255,255,255,.85);text-decoration:none;padding:10px;border-radius:8px;margin-bottom:6px}
.nav a.active,.nav a:hover{background:rgba(255,255,255,.06)}
.profile-mini{display:flex;align-items:center;gap:12px;margin-top:14px;padding:12px;background:rgba(255,255,255,.04);border-radius:10px}
.profile-mini .pic{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:var(--accent);color:#fff;font-weight:600}

/* MAIN */
.main{flex:1;padding:22px 28px}
.topbar{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
.topbar .search{flex:1;max-width:540px;background:var(--card);padding:8px 12px;border-radius:10px;display:flex;gap:8px;align-items:center;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
.topbar .search input{border:0;outline:0;width:100%;padding:8px;background:transparent}
.topbar .actions{display:flex;gap:12px;align-items:center}
.topbar .actions button{background:transparent;border:0;cursor:pointer;padding:8px;border-radius:8px}

.grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:18px}
.card{background:var(--card);padding:16px;border-radius:12px;box-shadow:0 6px 18px rgba(15,23,36,0.06)}
.stat{display:flex;justify-content:space-between;align-items:center}
.stat .meta{font-size:13px;color:var(--muted)}
.stat .value{font-size:20px;font-weight:600;margin-top:6px}

/* TABLE */
.panel{margin-top:8px}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:10px;text-align:left;border-bottom:1px solid #f2f4f6;font-size:14px}
.table th{font-size:13px;color:var(--muted);font-weight:600}

/* PROGRESS */
.progress{height:10px;background:#eef2ff;border-radius:8px;overflow:hidden}
.progress > i{display:block;height:100%;background:var(--accent);width:0%}

/* BUTTONS */
.btn-action{padding:8px 12px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;cursor:pointer;font-size:13px}
.btn-action:hover{background:#f3f4ff}

/* MODAL */
.modal-bg{
  position:fixed;inset:0;background:rgba(0,0,0,0.45);
  display:none;align-items:center;justify-content:center;z-index:2000;
}
.modal-box{
  background:#fff;width:360px;padding:18px;border-radius:12px;
  box-shadow:0 12px 40px rgba(0,0,0,0.3);
}
.modal-box h3{margin-top:0;margin-bottom:12px}
.modal-box label{display:block;font-size:13px;font-weight:600;margin:6px 0 4px}
.modal-box input,.modal-box select{
  width:100%;padding:9px 10px;border-radius:8px;border:1px solid #e5e7eb;margin-bottom:8px
}
.modal-btns{display:flex;justify-content:flex-end;gap:8px;margin-top:10px}
.modal-btn{padding:7px 12px;border-radius:8px;border:0;cursor:pointer;font-size:13px}
.modal-btn.primary{background:var(--accent);color:#fff}
.modal-btn.secondary{background:#e5e7eb}

/* RESPONSIVE */
@media (max-width:1100px){
  .grid{grid-template-columns:repeat(2,1fr)}
}
@media (max-width:700px){
  .sidebar{display:none}
  .grid{grid-template-columns:1fr}
}
</style>
</head>
<body>
<div class="app">

  <!-- SIDEBAR -->
  <aside class="sidebar">
    <div class="logo">
      <img src="../img/Logo Cloudify.png" alt="logo">
      <h1>Cloudify Admin</h1>
    </div>

    <div class="profile-mini">
      <div class="pic"><?= htmlspecialchars($initial) ?></div>
      <div>
        <div style="font-weight:600"><?= htmlspecialchars($userData['nama_lengkap']) ?></div>
        <div style="font-size:12px;color:rgba(255,255,255,0.75)"><?= htmlspecialchars($userData['email']) ?></div>
      </div>
    </div>

    <nav class="nav">
      <a href="index.php" class="active"><i class="fa fa-house"></i> Dashboard</a>
      <!-- <a href="../index.php?show=cloud"><i class="fa fa-cloud"></i> Cloud Saya</a> -->
      <a href="?manage=cloud"><i class="fa fa-folder-open"></i> Cloud Semua User</a>
      <a href="?manage=users"><i class="fa fa-users"></i> Manage Users</a>
      <a href="?manage=files"><i class="fa fa-file"></i> Manage Files</a>
      <a href="?manage=storage"><i class="fa fa-database"></i> Storage</a>
      <a href="logout.php"><i class="fa fa-sign-out-alt"></i> Logout</a>
    </nav>
  </aside>

  <!-- MAIN -->
  <main class="main">
    <div class="topbar">
      <div class="search">
        <i class="fa fa-search" style="color:var(--muted)"></i>
        <input id="quick-search" placeholder="Cari file, user, folder...">
      </div>
      <div class="actions">
        <button id="refresh-stats" title="Refresh stats"><i class="fa fa-sync"></i></button>
        <button id="open-profile" title="Profile"><i class="fa fa-user-circle"></i></button>
      </div>
    </div>

    <div class="grid">
      <div class="card">
        <div class="stat">
          <div>
            <div class="meta">Total Users</div>
            <div id="stat-users" class="value">—</div>
          </div>
          <div style="font-size:26px;color:var(--accent)"><i class="fa fa-users"></i></div>
        </div>
      </div>

      <div class="card">
        <div class="stat">
          <div>
            <div class="meta">Total Files</div>
            <div id="stat-files" class="value">—</div>
          </div>
          <div style="font-size:26px;color:var(--accent)"><i class="fa fa-file"></i></div>
        </div>
      </div>

      <div class="card">
        <div class="meta">Storage Used</div>
        <div style="margin-top:8px">
          <div style="display:flex;justify-content:space-between;align-items:center">
            <div id="stat-storage-human" style="font-weight:600">—</div>
            <div id="stat-storage-pct" style="color:var(--muted)">—%</div>
          </div>
          <div class="progress" style="margin-top:8px"><i id="stat-storage-bar"></i></div>
        </div>
      </div>

      <div class="card">
        <div class="meta">Quick Actions</div>
        <div style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn-action" data-action="folder">📁 Buat Folder</button>
          <button class="btn-action" data-action="users">👥 Kelola Users</button>
        </div>
      </div>
    </div>

    <div class="card panel">
      <h3 style="margin-top:0">Recent Uploads</h3>
      <div id="recent-placeholder" style="padding:12px;color:var(--muted)">Memuat...</div>
      <table class="table" id="recent-table" style="display:none">
        <thead>
          <tr>
            <th>Nama File</th>
            <th>Ukuran</th>
            <th>Owner</th>
            <th>Uploaded</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody id="recent-body"></tbody>
      </table>
    </div>
  </main>
</div>

<!-- MODAL: BUAT FOLDER -->
<div class="modal-bg" id="modalCreateFolder">
  <div class="modal-box">
    <h3>Buat Folder Baru</h3>
    <label>Nama Folder</label>
    <input type="text" id="folder-name" placeholder="Nama folder">

    <label>Pemilik Folder (User)</label>
    <select id="folder-owner">
      <option value="">-- Pilih User --</option>
      <?php foreach ($allUsers as $u): ?>
        <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['display_name']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
      <?php endforeach; ?>
    </select>

    <label>Parent Folder (Opsional)</label>
    <select id="folder-parent">
      <option value="">(Root)</option>
      <!-- akan diisi via JS -->
    </select>

    <div class="modal-btns">
      <button class="modal-btn secondary" onclick="closeCreateFolder()">Batal</button>
      <button class="modal-btn primary" onclick="submitCreateFolder()">Buat</button>
    </div>
  </div>
</div>

<!-- MODAL: PINDAH FILE -->
<div class="modal-bg" id="modalMoveFile">
  <div class="modal-box">
    <h3>Pindahkan File</h3>
    <input type="hidden" id="move-file-id">

    <label>Pilih Folder Tujuan</label>
    <select id="move-folder">
      <option value="">(Root)</option>
      <!-- diisi via JS -->
    </select>

    <div class="modal-btns">
      <button class="modal-btn secondary" onclick="closeMoveFile()">Batal</button>
      <button class="modal-btn primary" onclick="submitMoveFile()">Pindahkan</button>
    </div>
  </div>
</div>

<script>
// ================== UTIL ==================
function formatSize(s){
  s = parseInt(s)||0;
  if (s >= 1073741824) return (s/1073741824).toFixed(2)+' GB';
  if (s >= 1048576)    return (s/1048576).toFixed(2)+' MB';
  if (s >= 1024)       return (s/1024).toFixed(2)+' KB';
  return s+' B';
}
function escapeHtml(str){
  if (!str) return '';
  return str.replace(/[&<>"']/g, function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c] || c;
  });
}

// ================== STATS ==================
function fetchStats(){
  fetch('?action=stats')
    .then(r=>r.json())
    .then(j=>{
      if (!j.ok) return;
      document.getElementById('stat-users').textContent = j.totalUsers;
      document.getElementById('stat-files').textContent = j.totalFiles;
      document.getElementById('stat-storage-human').textContent = j.usedHuman + ' / ' + j.quotaHuman;
      document.getElementById('stat-storage-pct').textContent   = j.pct + '%';
      document.getElementById('stat-storage-bar').style.width   = Math.min(100, j.pct) + '%';
    })
    .catch(console.error);
}

// ================== RECENT UPLOADS ==================
function fetchRecent(){
  fetch('?action=recent')
    .then(r=>r.json())
    .then(j=>{
      const body = document.getElementById('recent-body');
      const placeholder = document.getElementById('recent-placeholder');
      const table = document.getElementById('recent-table');

      body.innerHTML = '';

      if (!j.ok || !j.rows || j.rows.length === 0) {
        placeholder.textContent = 'Belum ada upload.';
        placeholder.style.display = 'block';
        table.style.display = 'none';
        return;
      }

      placeholder.style.display = 'none';
      table.style.display = 'table';

      j.rows.forEach(row=>{
        const tr = document.createElement('tr');
        const size = formatSize(row.size);
        tr.innerHTML = `
          <td>${escapeHtml(row.name)}</td>
          <td>${size}</td>
          <td>${escapeHtml(row.owner_name || row.owner_id)}</td>
          <td>${escapeHtml(row.created_at)}</td>
          <td>
            <button style="padding:6px 10px;border-radius:6px;border:1px solid #e5e7eb;background:#fff;cursor:pointer"
              onclick="openMoveFile(${row.id})">
              Pindah
            </button>
          </td>
        `;
        body.appendChild(tr);
      });
    })
    .catch(e=>{
      console.error(e);
      document.getElementById('recent-placeholder').textContent = 'Gagal memuat.';
    });
}

// ================== MODAL BUAT FOLDER ==================
const modalCreateFolder = document.getElementById('modalCreateFolder');
function openCreateFolder(){
  document.getElementById('folder-name').value = '';
  document.getElementById('folder-owner').value = '';
  loadFoldersIntoSelect('folder-parent');
  modalCreateFolder.style.display = 'flex';
}
function closeCreateFolder(){
  modalCreateFolder.style.display = 'none';
}

function submitCreateFolder(){
  const name   = document.getElementById('folder-name').value.trim();
  const owner  = document.getElementById('folder-owner').value;
  const parent = document.getElementById('folder-parent').value;

  if (!name)  return alert('Nama folder wajib diisi.');
  if (!owner) return alert('Pilih pemilik folder.');

  fetch('folders_api.php?action=create', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      name: name,
      owner_id: owner,
      parent_id: parent
    })
  })
  .then(r=>r.json())
  .then(j=>{
    if (!j.ok) {
      alert(j.message || 'Gagal membuat folder.');
      return;
    }
    alert('Folder berhasil dibuat.');
    closeCreateFolder();
    // opsional: fetch folder lagi / recent
  })
  .catch(e=>{
    console.error(e);
    alert('Terjadi kesalahan saat membuat folder.');
  });
}

// ================== MODAL PINDAH FILE ==================
const modalMoveFile = document.getElementById('modalMoveFile');

function openMoveFile(fileId){
  document.getElementById('move-file-id').value = fileId;
  loadFoldersIntoSelect('move-folder');
  modalMoveFile.style.display = 'flex';
}
function closeMoveFile(){
  modalMoveFile.style.display = 'none';
}

function submitMoveFile(){
  const fileId   = document.getElementById('move-file-id').value;
  const folderId = document.getElementById('move-folder').value;

  fetch('folders_api.php?action=moveFile', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: new URLSearchParams({
      file_id: fileId,
      folder_id: folderId
    })
  })
  .then(r=>r.json())
  .then(j=>{
    if (!j.ok) {
      alert(j.message || 'Gagal memindahkan file.');
      return;
    }
    alert('File berhasil dipindahkan.');
    closeMoveFile();
    fetchRecent();
  })
  .catch(e=>{
    console.error(e);
    alert('Terjadi kesalahan saat memindahkan file.');
  });
}

// ================== LOAD FOLDERS KE SELECT ==================
function loadFoldersIntoSelect(selectId){
  fetch('folders_api.php?action=list')
    .then(r=>r.json())
    .then(j=>{
      const sel = document.getElementById(selectId);
      sel.innerHTML = '<option value=\"\">(Root)</option>';

      if (!j.ok || !j.rows) return;
      j.rows.forEach(f=>{
        const text = `${f.name} (User ID: ${f.owner_id})`;
        const opt  = document.createElement('option');
        opt.value  = f.id;
        opt.textContent = text;
        sel.appendChild(opt);
      });
    })
    .catch(console.error);
}

// ================== INIT ==================
document.addEventListener('DOMContentLoaded', ()=>{
  fetchStats();
  fetchRecent();

  document.getElementById('refresh-stats').addEventListener('click', ()=>{
    fetchStats();
    fetchRecent();
  });

  document.querySelectorAll('.btn-action').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const act = btn.getAttribute('data-action');
      if (act === 'folder') {
        openCreateFolder();
      } else if (act === 'users') {
        location.href = '?manage=users';
      }
    });
  });
});
</script>
</body>
</html>
