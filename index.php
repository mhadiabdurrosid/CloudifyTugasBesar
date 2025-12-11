<?php
session_start();

// =======================
// 1. CEK LOGIN
// =======================
if (!isset($_SESSION['user_id'])) {
    header("Location: admin/login.php");
    exit();
}

// =======================
// 2. INISIALISASI
// =======================
define('BASE_URL', rtrim(dirname($_SERVER['SCRIPT_NAME']), '/') . '/');


// Ambil koneksi database
require_once __DIR__ . '/model/Koneksi.php';    
$koneksi = new koneksi();
$conn = $koneksi->getConnection();

// Ambil data user
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

$userData = [
    'username' => $_SESSION['nama'] ?? 'User',
    'email' => $_SESSION['email'] ?? '',
    'nama_lengkap' => $_SESSION['nama'] ?? 'User',
    'jabatan' => $_SESSION['role'] ?? 'User',
    'foto_url' => '',
];

// Ambil data profil dari DB
try {
    if ($conn) {
        $stmt = $conn->prepare('SELECT username, email, display_name, role FROM users WHERE id = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('i', $currentUserId);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $row = $res->fetch_assoc()) {
                $userData = array_merge($userData, [
                    'username' => $row['username'],
                    'email' => $row['email'],
                    'nama_lengkap' => $row['display_name'],
                    'jabatan' => $row['role']
                ]);
            }
            $stmt->close();
        }
    }
} catch (Throwable $e) {
    // fallback ke default
}

// Hitung inisial nama
$nameParts = explode(' ', $userData['nama_lengkap']);
$initial = strtoupper(substr($nameParts[0],0,1)) . (isset($nameParts[1]) ? strtoupper(substr($nameParts[1],0,1)) : '');

// =======================
// 3. AMBIL DATA STORAGE (sesuai DB baru: owner_id & storage_quotas.user_id)
// =======================
$usedBytesSidebar = 0;
$quotaBytesSidebar = null;

try {
    if ($conn) {
        // total penggunaan (gunakan owner_id)
        $s = $conn->prepare('SELECT COALESCE(SUM(size),0) AS total FROM files WHERE (is_deleted IS NULL OR is_deleted = 0) AND owner_id = ?');
        if ($s) {
            $s->bind_param('i', $currentUserId);
            $s->execute();
            $res = $s->get_result();
            if ($res && $r = $res->fetch_assoc()) $usedBytesSidebar = (int)$r['total'];
            $s->close();
        }

        // quota (sesuai kolom user_id)
        $q = $conn->prepare('SELECT quota_bytes FROM storage_quotas WHERE user_id = ? LIMIT 1');
        if ($q) {
            $q->bind_param('i', $currentUserId);
            $q->execute();
            $res = $q->get_result();
            if ($res && $row = $res->fetch_assoc()) $quotaBytesSidebar = (int)$row['quota_bytes'];
            $q->close();
        }
    }
} catch (Throwable $e) {
    $usedBytesSidebar = 0;
    $quotaBytesSidebar = null;
}

// default kuota jika tidak ada (kecuali admin) => 30GB
if ($quotaBytesSidebar === null && ($userData['jabatan'] ?? '') !== 'admin') {
    $quotaBytesSidebar = 32212254720;
}

// helper format size (hindari redeclare)
if (!function_exists('_fmt_size_short')) {
    function _fmt_size_short($b){
        if ($b === null) return '—';
        $b = (int)$b;
        if ($b >= 1073741824) return round($b/1073741824,1) . ' GB';
        if ($b >= 1048576) return round($b/1048576,1) . ' MB';
        if ($b >= 1024) return round($b/1024,1) . ' KB';
        return $b . ' B';
    }
}

$percentModal = ($quotaBytesSidebar && $quotaBytesSidebar > 0) ? ($usedBytesSidebar / $quotaBytesSidebar) * 100 : 0;
$pctSidebar = ($quotaBytesSidebar && $quotaBytesSidebar > 0) ? min(100, ($usedBytesSidebar / $quotaBytesSidebar) * 100) : 100;
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>::. Administrator .::</title>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="style.css">

<style>
/* Modal overlay for +Baru popup */
#modal-overlay {
    position: fixed;
    inset: 0;
    background: transparent;
    display: none;
    align-items: flex-start;
    justify-content: flex-start;
    z-index: 10000;
    pointer-events: none;
}
#modal-overlay.active { display: block; pointer-events: auto; }
#modal {
    position: absolute;
    top: 72px;
    left: 18px;
    background: transparent;
    border-radius: 8px;
    max-width: 340px;
    width: auto;
    box-shadow: none;
    overflow: visible;
}
#modal .modal-inner { background: #fff; border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.18); overflow: auto; max-height: 80vh; }
#modal .modal-header { padding: 8px 12px; border-bottom: 1px solid #eee; display:flex; align-items:center; gap:8px; }
#modal .modal-header strong { display: none; }
#modal .modal-body { padding: 0; }
#modal .modal-close { margin-left: auto; background:transparent; border:0; font-size:18px; cursor:pointer; padding:8px; }
#modal .gd-dropdown { position: relative; box-shadow: none; border-radius: 0; }

/* --------------------------------------------------------- */
/* STYLE KHUSUS POPUP PROFIL */
/* --------------------------------------------------------- */

#profile-modal-overlay {
    position: fixed;
    inset: 0;
    background: transparent;
    display: none;
    align-items: flex-start;
    justify-content: flex-end;
    z-index: 10001;
    pointer-events: none;
}
#profile-modal-overlay.active { display: flex; pointer-events: auto; }

#profile-modal {
    position: absolute;
    top: 60px;
    right: 20px;
    background: #fff;
    border-radius: 20px;
    max-width: 350px;
    width: 100%;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
    overflow: hidden;
    padding: 0;
    text-align: center;
    pointer-events: auto;
}
.profile-header {
    padding: 24px 24px 16px;
    text-align: center;
    border-bottom: 1px solid #eee;
}
.profile-img-circle {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    margin: 0 auto 10px;
    object-fit: cover;
    background: #6366f1;
    color: white;
    font-size: 40px;
    font-weight: 500;
    display: flex;
    justify-content: center;
    align-items: center;
}
.profile-header h4 { margin: 0; font-size: 14px; font-weight: 400; color: #3c4043;} /* Email */
.profile-header h3 { margin: 4px 0 10px 0; font-size: 22px; font-weight: 500; color: #202124;} /* Nama Lengkap */
.profile-modal-info p { margin: 0 0 5px 0; font-size: 13px; color: #666; }
.btn-manage-cloudify {
    display: inline-block; padding: 8px 16px; background: none; color: #1a73e8;
    border: 1px solid #dadce0; text-decoration: none; border-radius: 20px;
    font-size: 14px; transition: background 0.2s; cursor: pointer;
}
.btn-manage-cloudify:hover { background: #f1f3f4; }

.profile-actions {
    padding: 16px 24px; display: flex; justify-content: space-between; gap: 16px;
}
.profile-actions button {
    flex: 1; padding: 10px 15px; border-radius: 20px; background: none;
    border: 1px solid #dadce0; color: #3c4043; font-size: 14px; cursor: pointer;
    transition: background 0.2s, border-color 0.2s; display: flex;
    align-items: center; justify-content: center; gap: 8px; margin-top: 0;
}
.profile-actions .btn-logout { border-color: #f44336; color: #f44336; }
.profile-actions .btn-logout:hover { background: #ffebee; border-color: #e53935; }

.storage-info-modal {
    padding: 16px 24px; font-size: 14px; color: #5f6368; display: flex;
    align-items: center; gap: 12px; border-top: 1px solid #eee;
}
.storage-info-modal i { color: #1a73e8; }

/* small helpers */
.hidden { display: none !important; }
.menu li.active > a { font-weight: 600; color: #111; }
</style>
</head>
<body>

<div id="profile-modal-overlay" aria-hidden="true">
    <div id="profile-modal" role="dialog" aria-modal="true">

        <div class="profile-header">
            <h4><?= htmlspecialchars($userData['email']) ?></h4>

            <?php if (!empty($userData['foto_url'])): ?>
                <img src="<?= htmlspecialchars($userData['foto_url']) ?>" alt="Foto Profil User" class="profile-img-circle">
            <?php else: ?>
                <div class="profile-img-circle"><?= htmlspecialchars($initial) ?></div>
            <?php endif; ?>

            <h3>Halo, <?= htmlspecialchars($userData['nama_lengkap']) ?>.</h3>

            <a href="model/pengaturan.php" class="btn-manage-cloudify"><i class="fa fa-gear"></i> Kelola Cloudify Anda</a>
        </div>

        <div class="profile-actions">
            <button onclick="window.location.href='model/pengaturan.php'"><i class="fa fa-user-edit"></i> Ubah Nama</button>

            <button class="btn-logout" onclick="handleLogout()"><i class="fa fa-sign-out-alt"></i> Logout</button>
        </div>

        <div class="storage-info-modal">
            <i class="fa fa-cloud"></i>
            <span id="modal-storage-text"><?= round($percentModal) ?>% dari <?= _fmt_size_short($quotaBytesSidebar) ?> telah digunakan</span>
        </div>

    </div>
</div>

<div class="sidebar">
    <div class="logo">
        <img src="img/Logo Cloudify.png" alt="Cloudify Logo">
    </div>

    <button id="btn-baru" class="btn-new" type="button" aria-label="Baru"><i class="fa fa-plus"></i> Baru</button>

<ul class="menu">
    <li class="active">
        <a href="?show=home">
            <i class="fa fa-house"></i> Beranda
        </a>
    </li>
    <li>
        <a href="?show=cloud">
            <i class="fa fa-cloud"></i> Cloud Saya
        </a>
    </li>
    <li>
        <a href="?show=favorit">
            <i class="fa fa-star"></i> Favorit
        </a>
    </li>
    <li>
        <a href="?show=trash">
            <i class="fa fa-trash"></i> Sampah
        </a>
    </li>
    <li>
        <a href="?show=penyimpanan">
            <i class="fa fa-database"></i> Penyimpanan
        </a>
    </li>
</ul>


    <div class="storage">
        <?php
          // progress persen
          if ($quotaBytesSidebar && $quotaBytesSidebar > 0) {
            $pctSidebar = min(100, ($usedBytesSidebar / $quotaBytesSidebar) * 100);
          } else {
            $pctSidebar = 100; // tanpa batas
          }
        ?>
        <div id="sidebar-storage" style="">
            <div id="sidebar-progress" style="background:#eef2ff;border-radius:6px;height:8px;overflow:hidden">
                <div id="sidebar-progress-fill" style="width:<?= htmlspecialchars(round($pctSidebar,1)) ?>%;height:8px;background:#6366f1"></div>
            </div>
            <p id="sidebar-storage-text"><?= _fmt_size_short($usedBytesSidebar) ?> <?= ($quotaBytesSidebar ? 'dari '. _fmt_size_short($quotaBytesSidebar) : '') ?> Terpakai</p>
        </div>
    </div>
</div>

<div id="modal-overlay" aria-hidden="true">
    <div id="modal" role="dialog" aria-modal="true">
        <div class="modal-inner">
            <div class="modal-header">
                <button class="modal-close" id="modal-close" aria-label="Tutup">×</button>
            </div>
            <div class="modal-body" id="modal-content">
                <div style="padding:18px;color:#666">Memuat...</div>
            </div>
            <div id="baru-fallback" style="display:none">
                <?php if (file_exists(__DIR__ . '/model/Baru.php')) include __DIR__ . '/model/Baru.php'; ?>
            </div>
        </div>
    </div>
</div>

<div class="main">

    <div class="top-header">

    <form class="search-box" id="search-form" method="GET" action="index.php">
        <input type="text" name="q" id="search-input" placeholder="Telusuri file" value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
        <button type="submit" id="search-button" style="background: none; border: none; padding: 0; margin: 0; cursor: pointer;">
            <i class="fa fa-search"></i>
        </button>
        <i class="fa fa-sliders-h"></i>
    </form>

<div class="header-right">

    <a href="model/pengaturan.php" class="header-icon" style="cursor:pointer; text-decoration: none;">
    <i class="fa fa-gear"></i>
</a>

    <a id="profile-trigger" class="header-icon user" aria-label="Profil Saya" style="cursor:pointer;">
        <i class="fa fa-user"></i>
    </a>

</div>


</div>

    <div class="content-wrapper">
        <div id="search-results-container" style="display: none; padding-top: 15px;">
            </div>

        <div id="main-content-area">
            <?php
                // Default page (Beranda)
                $page = '/pages/Menu-Utama.php';
                $search_term = $_GET['q'] ?? ''; // Ambil query dari URL

                // Routing menggunakan parameter ?show=
                if (isset($_GET['show'])) {

                    switch ($_GET['show']) {
                        case 'home':
                            require_once __DIR__ . '/pages/Menu-Utama.php';
                            break;

                        case 'cloud':
                            // Gunakan $search_term di CloudSaya.php (meskipun AJAX sudah menggunakannya, ini untuk fallback/direct link)
                            require_once __DIR__ . '/model/CloudSaya.php';
                            break;

                        case 'trash':
                            require_once __DIR__ . '/model/sampah.php';
                            break;

                        case 'favorit':
                            require_once __DIR__ . '/model/favorit.php';
                            break;

                        case 'penyimpanan':
                            require_once __DIR__ . '/model/penyimpanan.php';
                            break;

                        default:
                            echo "<p>Halaman tidak ditemukan.</p>";
                            break;
                    }

                } else {
                    // Support routing lama via ?module=...&page=...
                    if (isset($_GET['module']) && isset($_GET['page'])) {
                        $module = basename($_GET['module']);
                        $pageName = basename($_GET['page']);

                        $candidate = "page/$module/$pageName.php";

                        if (file_exists(__DIR__ . '/' . $candidate)) {
                            $page = $candidate;
                        }
                    }

                    require __DIR__ . '/' . $page;
                }
            ?>
        </div>
    </div>


    </div>

    <script>
    // Popup +Baru & Profil
    (function(){
      // Logika Popup +Baru
      const btn = document.getElementById('btn-baru');
      const overlay = document.getElementById('modal-overlay');
      const closeBtn = document.getElementById('modal-close');
      const modalContent = document.getElementById('modal-content');
      const contentWrapper = document.querySelector('.content-wrapper');

      function openOverlay(){ overlay.classList.add('active'); overlay.setAttribute('aria-hidden','false'); }
      function closeOverlay(){ overlay.classList.remove('active'); overlay.setAttribute('aria-hidden','true'); }

      // Logika Popup Profil
      const profileTrigger = document.getElementById('profile-trigger');
      const profileOverlay = document.getElementById('profile-modal-overlay');

      function openProfileModal() {
          profileOverlay.classList.add('active');
          profileOverlay.setAttribute('aria-hidden', 'false');
      }

      function closeProfileModal() {
          profileOverlay.classList.remove('active');
          profileOverlay.setAttribute('aria-hidden', 'true');
      }

      window.handleLogout = function() { // Pindahkan ke window agar bisa diakses dari inline onclick
          if (confirm('Apakah Anda yakin ingin keluar?')) {
              window.location.href = 'admin/logout.php'; // Asumsi logout.php ada di direktori utama
          }
      }

      profileTrigger && profileTrigger.addEventListener('click', openProfileModal);

      profileOverlay && profileOverlay.addEventListener('click', function(e) {
          if (e.target === profileOverlay) {
              closeProfileModal();
          }
      });
      // End Logika Popup Profil

      // Load partial model via AJAX
      function loadContent(show){
        const map = {
          'cloud': 'CloudSaya.php',
          'favorit': 'favorit.php',
          'trash': 'sampah.php',
          'penyimpanan': 'penyimpanan.php',
          'home': 'pages/Menu-Utama.php' // Tambahkan home
        };
        const file = map[show] || map['home'];

        // Cek apakah mode pencarian sedang aktif
        const searchContainer = document.getElementById('search-results-container');
        const mainContentArea = document.getElementById('main-content-area');

        if (searchContainer && mainContentArea) {
            searchContainer.style.display = 'none';
            mainContentArea.style.display = 'block';
            mainContentArea.innerHTML = '<div style="padding:20px;text-align:center;">⏳ Memuat halaman...</div>';
        }

        const url = (show === 'home' ? '' : 'model/') + file; // Adjust path for home

        return fetch(url).then(r => r.text()).then(html => {
          if (mainContentArea) mainContentArea.innerHTML = html;

          // APPLY VIEW MODE after content load (so list/grid mode persists)
          try { if (typeof applyViewMode === "function") applyViewMode(); } catch(e){}

          try { history.pushState(null, '', 'index.php?show=' + show); } catch(e){}
          return html;
        }).catch(err => { console.error('Gagal memuat', url, err); throw err; });
      }

      // Event Listener Sidebar untuk AJAX
      document.querySelectorAll('.menu a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = new URL(link.href);
            const show = url.searchParams.get('show');

            // Hapus kelas aktif dari semua, tambahkan ke yang diklik
            document.querySelectorAll('.menu li').forEach(li => li.classList.remove('active'));
            link.closest('li').classList.add('active');

            loadContent(show);
        });
      });


      // Update sidebar storage info by calling a JSON endpoint
      function updateSidebarStorage(){
        // Asumsi Anda memiliki file model/storage_info.php yang mengembalikan {used_human, quota_human, pct}
        fetch('model/storage_info.php')
          .then(r => r.json())
          .then(data => {
            if (!data) return;
            const fill = document.getElementById('sidebar-progress-fill');
            const text = document.getElementById('sidebar-storage-text');
            if (fill && typeof data.pct !== 'undefined') fill.style.width = Math.max(0, Math.min(100, data.pct)) + '%';
            if (text) text.textContent = data.used_human + (data.quota_human ? ' dari ' + data.quota_human + ' Terpakai' : ' Terpakai');
          }).catch(err => { /* ignore */ });
      }

      // Logika Pop-up +Baru (create folder / upload file)
      btn && btn.addEventListener('click', function(){
        fetch('model/Baru.php')
          .then(r => r.text())
          .then(html => {
            modalContent.innerHTML = html;
            openOverlay();
          })
          .catch(err => {
            const fallback = document.getElementById('baru-fallback');
            if (fallback) {
              modalContent.innerHTML = fallback.innerHTML;
            } else {
              modalContent.innerHTML = '<div style="padding:18px;color:#b00">Gagal memuat konten.</div>';
            }
            openOverlay();
            console.error(err);
          });
      });

      closeBtn && closeBtn.addEventListener('click', closeOverlay);
      overlay && overlay.addEventListener('click', function(e){ if (e.target === overlay) closeOverlay(); });

      // Delegasi: tangani elemen yang memiliki data-modal di dalam konten yang dimuat
      modalContent && modalContent.addEventListener('click', function(e){
        const item = e.target.closest('.gd-item');
        if (!item) return;
        const modalName = item.getAttribute('data-modal');
        if (!modalName) return;

        if (modalName === 'modalFolder') {
          modalContent.innerHTML = `
            <div style="padding:12px">
              <h3>Buat Folder Baru</h3>
              <p>Isi nama folder:</p>
              <input id="baru-folder-name" type="text" placeholder="Nama folder" style="width:100%;padding:8px;margin-bottom:8px;border:1px solid #ddd;border-radius:6px">
              <div style="display:flex;gap:8px;justify-content:flex-end">
                <button id="back-btn" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;background:#fff">Kembali</button>
                <button id="create-folder" style="padding:8px 12px;border-radius:6px;border:0;background:#4285f4;color:#fff">Buat</button>
              </div>
            </div>
          `;
          document.getElementById('back-btn').addEventListener('click', function(){ btn.click(); });
          document.getElementById('create-folder').addEventListener('click', function(){
            const name = document.getElementById('baru-folder-name').value.trim();
            if (!name) return alert('Masukkan nama folder.');
            const form = new FormData();
            form.append('folder_name', name);
            fetch('crud/upload/create_folder.php', { method: 'POST', body: form })
              .then(r => r.json()).then(resp => {
                  if (resp.success) {
                  modalContent.innerHTML = '<div style="padding:18px;color:green">' + resp.message + '</div>';
                  setTimeout(() => { closeOverlay(); loadContent('cloud').then(() => updateSidebarStorage()).catch(()=>{}); }, 900);
                } else {
                  alert(resp.message || 'Gagal');
                }
              }).catch(err => { console.error(err); alert('Kesalahan server'); });
          });
        }

        if (modalName === 'modalUploadFile') {
          modalContent.innerHTML = `
            <div style="padding:12px">
              <h3>Upload File</h3>
              <input type="file" id="file-input" style="display:block;margin-bottom:8px">
              <small style="color:#666;display:block;margin-bottom:8px">Maks 10 MB per file. Tipe diizinkan: jpg, jpeg, png, gif, webp, avif, pdf, doc, docx, txt, zip, rar, csv, xlsx, xls, ppt, pptx, mp3, mp4, mov, avi.</small>
              <div style="display:flex;gap:8px;justify-content:flex-end">
                <button id="back2-btn" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;background:#fff">Kembali</button>
                <button id="upload-file" style="padding:8px 12px;border-radius:6px;border:0;background:#4285f4;color:#fff">Upload</button>
              </div>
            </div>
          `;
          document.getElementById('back2-btn').addEventListener('click', function(){ btn.click(); });
          document.getElementById('upload-file').addEventListener('click', function(){
            const f = document.getElementById('file-input').files[0];
            if (!f) return alert('Pilih file untuk diupload.');
            const fd = new FormData();
            fd.append('file', f);
            fetch('crud/upload/upload_file.php', { method: 'POST', body: fd })
              .then(r => r.json()).then(resp => {
                  if (resp.success) {
                  modalContent.innerHTML = '<div style="padding:18px;color:green">' + resp.message + '</div>';
                  setTimeout(() => { closeOverlay(); loadContent('cloud').then(() => updateSidebarStorage()).catch(()=>{}); }, 900);
                } else {
                  alert(resp.message || 'Gagal upload');
                }
              }).catch(err => { console.error(err); alert('Kesalahan server'); });
          });
        }

        if (modalName === 'modalUploadFolder') {
          modalContent.innerHTML = `
            <div style="padding:12px">
              <h3>Upload Folder / ZIP</h3>
              <p>Unggah ZIP (direkomendasikan) atau pilih banyak file.</p>
              <small style="color:#666;display:block;margin-bottom:8px">ZIP maks 100 MB. Setiap file harus <= 10 MB.</small>
              <input type="file" id="folder-input" webkitdirectory directory multiple style="display:block;margin-bottom:12px">
              <div style="display:flex;gap:8px;justify-content:flex-end">
                <button id="back3-btn" style="padding:8px 12px;border-radius:6px;border:1px solid #ccc;background:#fff">Kembali</button>
                <button id="upload-folder" style="padding:8px 12px;border-radius:6px;border:0;background:#4285f4;color:#fff">Upload</button>
              </div>
            </div>
          `;
          document.getElementById('back3-btn').addEventListener('click', function(){ btn.click(); });
          document.getElementById('upload-folder').addEventListener('click', function(){
            const files = document.getElementById('folder-input').files;
            if (!files || files.length === 0) return alert('Pilih folder atau file ZIP.');
            if (files.length === 1 && files[0].name.match(/\.zip$/i)) {
              const fd = new FormData();
              fd.append('zip', files[0]);
              fetch('crud/upload/upload_folder.php', { method: 'POST', body: fd })
                .then(r => r.json()).then(resp => {
                  if (resp.success) {
                  modalContent.innerHTML = '<div style="padding:18px;color:green">' + resp.message + '</div>';
                    setTimeout(() => { closeOverlay(); loadContent('cloud').then(() => updateSidebarStorage()).catch(()=>{}); }, 900);
                  } else {
                    alert(resp.message || 'Gagal');
                  }
                }).catch(err => { console.error(err); alert('Kesalahan server'); });
              return;
            }

            const fd = new FormData();
            for (let i=0;i<files.length;i++) fd.append('files[]', files[i]);
            fetch('crud/upload/upload_folder.php', { method: 'POST', body: fd })
              .then(r => r.json()).then(resp => {
                  if (resp.success) {
                  modalContent.innerHTML = '<div style="padding:18px;color:green">' + resp.message + '</div>';
                  setTimeout(() => { closeOverlay(); loadContent('cloud').then(() => updateSidebarStorage()).catch(()=>{}); }, 900);
                } else {
                  alert(resp.message || 'Gagal');
                }
              }).catch(err => { console.error(err); alert('Kesalahan server'); });
          });
        }
      });

      // Initial sidebar update on page load
      try { updateSidebarStorage(); } catch(e){}
      
      // -----------------------------------------------------------------
      // 3. LOGIKA PENCARIAN AJAX (BARU)
      // -----------------------------------------------------------------
      
      const searchForm = document.getElementById('search-form');
      const searchInput = document.getElementById('search-input');
      const searchContainer = document.getElementById('search-results-container');
      const mainContentArea = document.getElementById('main-content-area');

      function executeSearch(query) {
          // Sembunyikan semua menu popup saat memulai pencarian baru
          document.querySelectorAll('.menu-popup').forEach(m => m.style.display = "none"); 

          if (query.length > 0) {
              // Tampilkan container hasil pencarian dan sembunyikan konten utama
              searchContainer.style.display = 'block';
              if (mainContentArea) mainContentArea.style.display = 'none';
              searchContainer.innerHTML = '<div style="padding:20px;text-align:center;">🔍 Mencari file...</div>';

              // Panggil model/search_files.php
              fetch('model/search_files.php?q=' + encodeURIComponent(query))
                  .then(response => response.text())
                  .then(html => {
                      searchContainer.innerHTML = html;
                      // Update URL
                      try { history.pushState(null, '', 'index.php?show=search&q=' + encodeURIComponent(query)); } catch(e){}
                  })
                  .catch(error => {
                      searchContainer.innerHTML = '<div style="padding:20px;text-align:center;color:red;">❌ Gagal memuat hasil pencarian.</div>';
                  });
          } else {
              // Jika input kosong, sembunyikan hasil pencarian dan tampilkan konten utama
              searchContainer.style.display = 'none';
              if (mainContentArea) mainContentArea.style.display = 'block';
              // Hapus parameter pencarian dari URL
              const currentShow = new URLSearchParams(window.location.search).get('show') || 'home';
              try { history.pushState(null, '', 'index.php?show=' + currentShow); } catch(e){}
          }
      }

      // 1. Tangani pengiriman (submit) form
      searchForm && searchForm.addEventListener('submit', function(e) {
          e.preventDefault();
          executeSearch(searchInput.value.trim());
      });

      // 2. Tangani load awal halaman jika ada parameter ?q= di URL
      const urlParams = new URLSearchParams(window.location.search);
      const initialQuery = urlParams.get('q') || '';

      if (initialQuery) {
          searchInput.value = initialQuery;
          // Hanya jalankan search AJAX jika halaman sedang dalam mode 'search' (ini agar routing PHP lama tetap berfungsi)
          if (urlParams.get('show') === 'search' || !urlParams.get('show')) { // Tambahkan kondisi !urlParams.get('show')
             executeSearch(initialQuery);
          }
      }

      // 3. Pastikan fungsi toggleMenu tersedia untuk hasil pencarian AJAX
      window.toggleMenu = function(id, buttonEl) {
          document.querySelectorAll('.menu-popup').forEach(m => {
              if (m.id !== 'menu-' + id) {
                  m.style.display = "none";
              }
          });
          let el = document.getElementById("menu-" + id);
          el.style.display = (el.style.display === "block" ? "none" : "block");
      };

    })();
    </script>

<!-- ============================
     GLOBAL VIEW / LIST-GRID FIX
     ============================ -->
<script>
// =====================================================
// GLOBAL: setView / applyViewMode
// - solves "setView is not defined" and preserves user preference
// =====================================================
window.setView = function(mode) {
    const wrapper = document.getElementById("fileWrapper");
    const header  = document.getElementById("listHeader");

    // If no file wrapper (page may not show files), just store preference
    if (!wrapper) {
        localStorage.setItem("viewMode", mode);
        return;
    }

    if (mode === "list") {
        wrapper.classList.remove("grid-view");
        wrapper.classList.add("list-view");
        if (header) header.classList.remove("hidden");
    } else {
        wrapper.classList.remove("list-view");
        wrapper.classList.add("grid-view");
        if (header) header.classList.add("hidden");
    }

    // Persist preference
    localStorage.setItem("viewMode", mode);
};

// Apply saved mode to currently-loaded content (call after AJAX loads)
window.applyViewMode = function() {
    const savedMode = localStorage.getItem("viewMode") || "grid";
    try {
        window.setView(savedMode);
    } catch(e) {
        console.warn("applyViewMode failed", e);
    }
};

// Ensure the saved mode is applied on initial load
document.addEventListener("DOMContentLoaded", function() {
    try { window.applyViewMode(); } catch(e){}
});
</script>

</body>
</html>
