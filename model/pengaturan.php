<?php
session_start();
// Hapus pesan error lama jika ada
error_reporting(E_ALL);
ini_set('display_errors', 1);

// =========================================================
// PERBAIKAN KRUSIAL: KEAMANAN & PENCEGAHAN CACHING
// =========================================================

// Mencegah browser menyimpan halaman di cache
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ASUMSI: Sesi login menggunakan $_SESSION['user_id']
if (!isset($_SESSION['user_id'])) {
    // Jika user_id belum diset, tetapkan user_id simulasi (untuk dev)
    $_SESSION['user_id'] = 1;
}

// =========================================================
// KONEKSI & INISIALISASI DATA
// =========================================================
// ASUMSI: File Koneksi.php ada di folder yang sama (model/)
require_once "Koneksi.php";

$conn = null;
try {
    if (isset($koneksi) && is_object($koneksi) && method_exists($koneksi, 'getConnection')) {
        $conn = $koneksi->getConnection();
    }
} catch (Throwable $e) {
    error_log("Gagal mendapatkan koneksi DB: " . $e->getMessage());
}

// Data user default (Digunakan jika sesi baru)
$default_user_data = [
    'username' => 'hadi',
    'email'    => 'hadi@example.com',
    'nama_lengkap' => 'Hadi Pratama',
    'jabatan' => 'Admin Cloudify',
    'foto_url' => '', // Kosongkan agar inisial muncul
    'theme'    => 'light',
    'storage_used' => 3250,
    'storage_limit' => 5000
];

// Muat data dari SESSION agar perubahan persisten
$_SESSION['user_data'] = $_SESSION['user_data'] ?? $default_user_data;
$user = &$_SESSION['user_data']; // referensi agar update langsung tersimpan ke session
$user['user_id'] = (int)$_SESSION['user_id'];

// Ambil inisial untuk avatar
$nameParts = explode(' ', $user['nama_lengkap'] ?? '');
$initial = '';
if (!empty($nameParts[0])) $initial .= strtoupper(substr($nameParts[0], 0, 1));
if (count($nameParts) > 1) $initial .= strtoupper(substr(end($nameParts), 0, 1));
if (empty($initial)) $initial = 'U';

$alert_message = '';

// Helper format size
function _fmt_size_cfg($b){
    if ($b >= 1073741824) return round($b/1073741824,1) . ' GB';
    if ($b >= 1048576) return round($b/1048576,1) . ' MB';
    if ($b >= 1024) return round($b/1024,1) . ' KB';
    return $b . ' B';
}

// =========================
// ACTIONS: logout via GET, toggle_theme via POST
// =========================
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Fallback logout handler: hapus session dan redirect ke login
    session_unset();
    session_destroy();
    header("Location: ../login.php");
    exit();
}

// Toggle theme (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_theme'])) {
    $user['theme'] = ($user['theme'] ?? 'light') === 'dark' ? 'light' : 'dark';
    $_SESSION['user_data'] = $user;
    // PRG
    header("Location: pengaturan.php");
    exit();
}

// =========================
// 1. HANDLE PENYIMPANAN DATA (POST REQUEST) & UPLOAD FOTO
// =========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {

    // Sanitasi input teks
    $nama_lengkap = trim(htmlspecialchars($_POST['nama_lengkap'] ?? ''));
    $jabatan = trim(htmlspecialchars($_POST['jabatan'] ?? ''));

    $alert_type = 'success';
    $alert_text = "✅ Profil Nama Lengkap dan Jabatan berhasil diperbarui.";
    $photo_uploaded = false;

    // --- LOGIKA UPLOAD FOTO PROFIL ---
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_picture'];

        // Path yang benar berdasarkan struktur folder: file ini berada di /model/, uploads berada di root ../uploads/
        $upload_dir = '../uploads/avatars/';

        // 1. Pastikan direktori ada
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                $alert_type = 'error';
                $alert_text = "❌ Gagal membuat folder unggahan. Pastikan izin folder sudah benar.";
                goto end_upload; // Langsung ke akhir blok upload
            }
        }

        // 2. Validasi File
        $finfo_type = mime_content_type($file['tmp_name']);
        $allowed_types = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // Maksimum 5 MB

        if (!in_array($finfo_type, $allowed_types)) {
            $alert_type = 'error';
            $alert_text = "❌ Gagal unggah: Hanya file JPG, PNG, atau WEBP yang diperbolehkan.";
        } elseif ($file['size'] > $max_size) {
            $alert_type = 'error';
            $alert_text = "❌ Gagal unggah: Ukuran file melebihi 5MB.";
        } else {
            // 3. Buat nama file unik (e.g., 1_1678888888.png)
            $file_ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $new_file_name = $user['user_id'] . '_' . time() . '.' . $file_ext;

            $target_file = $upload_dir . $new_file_name;
            // Path yang akan disimpan di DB/Sesi (relatif dari root project agar bisa diakses dari index.php)
            $web_path = 'uploads/avatars/' . $new_file_name;

            // 4. Pindahkan file
            if (move_uploaded_file($file['tmp_name'], $target_file)) {

                // 5. Hapus foto lama di server (jika path ada dan valid)
                if (!empty($user['foto_url'])) {
                    $old_path_root = '../' . $user['foto_url'];
                    if (file_exists($old_path_root) && is_file($old_path_root)) {
                        @unlink($old_path_root);
                    }
                }

                // 6. SIMPAN PATH BARU KE SESI (relatif dari root)
                $user['foto_url'] = $web_path;
                $alert_text .= " Foto profil berhasil diperbarui.";
                $photo_uploaded = true;

                // Jika ada koneksi DB, coba simpan path ke tabel users (opsional, tidak wajib)
                if ($conn) {
                    try {
                        // Pastikan tabel dan kolom sesuai environment.
                        $uid = $user['user_id'];
                        $sql = "UPDATE users SET foto_url = ? WHERE id = ?";
                        if ($stmt = $conn->prepare($sql)) {
                            $stmt->bind_param('si', $user['foto_url'], $uid);
                            $stmt->execute();
                            $stmt->close();
                        }
                    } catch (Throwable $e) {
                        // Jangan ganggu flow jika DB tidak sinkron
                        error_log("Tidak dapat memperbarui foto di DB: " . $e->getMessage());
                    }
                }
            } else {
                $alert_type = 'error';
                $alert_text = "❌ Gagal memproses unggahan file.";
            }
        }
    }
    // Label end_upload digunakan untuk 'goto' saat error di pembuatan folder
    end_upload:

    // Update nama dan jabatan jika tidak ada error upload kritis
    if ($conn) {
        try {
            $nama_db = $conn->real_escape_string($nama_lengkap);
            $jabatan_db = $conn->real_escape_string($jabatan);
            $uid = $user['user_id'];
            // Jika tabel users ada, update nama/jabatan juga (opsional)
            $sql = "UPDATE users SET nama_lengkap = ?, jabatan = ? WHERE id = ?";
            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param('ssi', $nama_db, $jabatan_db, $uid);
                $stmt->execute();
                $stmt->close();
            }
        } catch (Throwable $e) {
            // Kalau gagal, tetap lanjut — session akan diupdate
            error_log("Gagal update DB profil: " . $e->getMessage());
        }
    }

    // Update session user
    $user['nama_lengkap'] = $nama_lengkap;
    $user['jabatan'] = $jabatan;

    // Update inisial
    $parts = explode(' ', $nama_lengkap);
    $user['initials'] = strtoupper(substr($parts[0] ?? '', 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));

    $_SESSION['user_data'] = $user;

    // Simpan pesan status ke Sesi
    $_SESSION['alert_message'] = "<div class='alert $alert_type'>{$alert_text}</div>";

    // IMPLEMENTASI PRG: REDIRECT KE HALAMAN YANG SAMA
    header("Location: pengaturan.php");
    exit();
}

// --- 2. AMBIL DAN HAPUS PESAN DARI SESI (GET REQUEST) ---
if (isset($_SESSION['alert_message'])) {
    $alert_message = $_SESSION['alert_message'];
    unset($_SESSION['alert_message']);
}

// Hitung ulang persentase penyimpanan untuk ditampilkan
$used = $user['storage_used'] ?? 0;
$limit = $user['storage_limit'] ?? 0;
$percent = ($limit > 0) ? ($used / $limit) * 100 : 0;

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Pengaturan Akun — Cloudify</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">

    <style>
        /* CSS UMUM */
        body { margin: 0; font-family: "Segoe UI", sans-serif; background: #f5f5f5; transition: .3s; }
        body.dark { background: #1e1e1e; color: #eee; }
        .sidebar { width: 250px; height: 100vh; background: #1f2937; color: white; position: fixed; top: 0; left: 0; padding: 20px; box-sizing: border-box; }
        .sidebar h2 { font-size: 22px; text-align: center; }
        .menu a { display: block; padding: 12px; margin-top: 5px; color: #cbd5e1; text-decoration: none; border-radius: 8px; transition: .2s; }
        .menu a:hover, .menu a.active { background: #4b5563; color: #fff; transform: translateX(5px); }
        .content { margin-left: 270px; padding: 25px; }
        .card { background: white; padding: 20px; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 25px; }
        body.dark .card { background: #2d2d2d; }
        .card h3 { margin-top: 0; border-bottom: 2px solid #eee; padding-bottom: 8px; }
        input, select { width: 100%; padding: 12px; margin-top: 8px; border-radius: 10px; border: 1px solid #ccc; box-sizing: border-box; }
        button { padding: 10px 18px; border: none; border-radius: 10px; background: #2563eb; color: white; cursor: pointer; margin-top: 10px; }
        button:hover { background: #1d4ed8; }
        .progress { width: 100%; height: 14px; background: #ddd; border-radius: 10px; overflow: hidden; }
        .progress-bar { height: 100%; background: #2563eb; transition: .5s; }
        body.dark .progress { background:#444; }

        /* NOTIFIKASI */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: bold; }
        .alert.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        /* PROFILE TRIGGER (SIDEBAR) */
        .profile-trigger {
            display: flex; align-items: center; padding: 15px; margin-bottom: 20px;
            background: #4b5563; border-radius: 10px; cursor: pointer; transition: .2s;
        }
        .profile-trigger:hover { background: #6b7280; }
        .profile-trigger .avatar {
            width: 50px; height: 50px; border-radius: 50%; margin-right: 15px;
            object-fit: cover; border: 2px solid white;
            display: flex; justify-content: center; align-items: center;
            background: #6366f1; color: white; font-size: 20px; font-weight: bold;
        }
        .profile-trigger div p { margin: 0; line-height: 1.2; font-size: 14px; color: #d1d5db; }
        .profile-trigger div strong { display: block; font-size: 16px; color: white; }

        /* MODAL POPUP (Disesuaikan agar mirip index.php) */
        .modal {
            display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: transparent;
        }
        .modal-content {
            background-color: #fefefe; margin: 0; padding: 0; border-radius: 20px; width: 350px;
            max-width: 90vw; box-shadow: 0 8px 30px rgba(0,0,0,0.2);
            position: absolute; top: 60px; right: 20px;
            animation: none;
        }
        body.dark .modal-content { background-color: #2d2d2d; }
        .close-btn {
            position: absolute; top: 10px; right: 10px; color: #aaa; font-size: 20px; font-weight: bold; cursor: pointer; background: none; border: none;
        }

        /* Gaya baru untuk modal content */
        .profile-header {
            padding: 24px 24px 16px;
            text-align: center;
            border-bottom: 1px solid #eee;
        }
        .profile-popup-img {
            width: 96px; height: 96px; border-radius: 50%;
            margin: 0 auto 10px; object-fit: cover;
            background: #b0a8a8; color: white; font-size: 40px; font-weight: 500;
            display: flex; justify-content: center; align-items: center;
        }
        .profile-header h4 { margin: 0; font-size: 14px; font-weight: 400; color: #3c4043; }
        .profile-header h3 { margin: 4px 0 10px 0; font-size: 22px; font-weight: 500; color: #202124; }

        .btn-manage-cloudify {
            display: inline-block;
            padding: 8px 16px;
            background: none;
            color: #1a73e8;
            border: 1px solid #dadce0;
            text-decoration: none;
            border-radius: 20px;
            font-size: 14px;
            transition: background 0.2s;
            cursor: pointer;
        }
        .btn-manage-cloudify:hover { background: #f1f3f4; }

        .profile-actions {
            padding: 16px 24px;
            display: flex;
            justify-content: space-between;
            gap: 16px;
        }
        .profile-actions button {
            flex: 1;
            padding: 10px 15px;
            border-radius: 20px;
            background: none;
            border: 1px solid #dadce0;
            color: #3c4043;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s, border-color 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 0;
        }
        .profile-actions .btn-logout {
             border-color: #f44336;
             color: #f44336;
        }
        .profile-actions .btn-logout:hover {
            background: #ffebee;
            border-color: #e53935;
        }

        .storage-info-modal {
            padding: 16px 24px;
            font-size: 14px;
            color: #5f6368;
            display: flex;
            align-items: center;
            gap: 12px;
            border-top: 1px solid #eee;
        }
        .storage-info-modal i {
            color: #1a73e8;
        }

    </style>

    <script>
        function toggleTheme(){
            // Submit form untuk toggle theme (server-side toggle)
            const f = document.getElementById('themeForm');
            if (f) f.submit();
        }

        function openProfileModal() {
            document.getElementById('profileModal').style.display = 'block';
        }

        function closeProfileModal() {
            document.getElementById('profileModal').style.display = 'none';
        }

        function handleLogout() {
            if (confirm('Apakah Anda yakin ingin keluar?')) {
                // arahkan ke logout.php jika ada, otherwise gunakan action=logout
                window.location.href = '../logout.php';
            }
        }

        function previewFile() {
            const preview = document.getElementById('previewImage');
            const previewDiv = document.getElementById('previewImageDiv');
            const file = document.getElementById('profilePictureInput').files[0];
            const reader = new FileReader();

            reader.onloadend = function () {
                preview.src = reader.result;
                preview.style.display = 'flex';
                if (previewDiv) previewDiv.style.display = 'none';
            }

            if (file) {
                reader.readAsDataURL(file);
            } else {
                if (previewDiv) {
                    if ('<?= $user['foto_url'] ?>' === '') {
                        previewDiv.style.display = 'flex';
                    }
                    preview.style.display = 'none';
                    preview.src = "";
                }
            }
        }

        // Tutup modal jika klik di luar
        window.onclick = function(event) {
            const modal = document.getElementById('profileModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
            // Tutup account popup jika klik di luar (jika ada)
            const popup = document.getElementById('accountPopup');
            const avatar = document.querySelector('.account-avatar');
            if (popup && popup.style.display === 'block' && !popup.contains(event.target) && !avatar.contains(event.target)) {
                popup.style.display = 'none';
                if (avatar) avatar.style.boxShadow = 'none';
            }
        }
    </script>
</head>

<body class="<?= ($user['theme'] ?? 'light') == 'dark' ? 'dark' : '' ?>">

    <div class="sidebar">
        <div class="profile-trigger" onclick="openProfileModal()">
            <?php if (!empty($user['foto_url'])): ?>
                <img src="../<?= htmlspecialchars($user['foto_url']) ?>" alt="Foto Profil" class="avatar">
            <?php else: ?>
                <div class="avatar"><?= htmlspecialchars($initial) ?></div>
            <?php endif; ?>
            <div>
                <strong><?= htmlspecialchars($user['nama_lengkap']) ?></strong>
                <p><?= htmlspecialchars($user['jabatan']) ?></p>
            </div>
        </div>

        <h2>⚙ Pengaturan</h2>
        <div class="menu">
            <a href="../index.php?show=home"><i class="fa fa-house"></i> Beranda</a>
            <a href="../index.php?show=cloud"><i class="fa fa-cloud"></i> Cloud Saya</a>
            <a class="active" href="pengaturan.php"><i class="fa fa-gear"></i> Pengaturan</a>
            <a href="../logout.php"><i class="fa fa-sign-out-alt"></i> Keluar</a>
        </div>
    </div>

    <div class="content">

        <?= $alert_message ?>

        <div class="card">
            <h3><i class="fa fa-user"></i> Informasi Akun</h3>

            <form method="POST" action="pengaturan.php" enctype="multipart/form-data">
                <input type="hidden" name="save_profile" value="1">

                <div class="form-group" style="text-align: center;">

                    <?php
                    $current_foto_url = !empty($user['foto_url']) ? "../" . htmlspecialchars($user['foto_url']) : "";
                    ?>

                    <?php if (!empty($current_foto_url)): ?>
                        <img src="<?= $current_foto_url ?>" alt="Foto Profil" class="profile-popup-img" id="previewImage" style="margin-top: 0;">
                        <div class="profile-popup-img" id="previewImageDiv" style="display:none; margin-top: 0;"><?= htmlspecialchars($initial) ?></div>
                    <?php else: ?>
                        <div class="profile-popup-img" id="previewImageDiv" style="margin-top: 0;"><?= htmlspecialchars($initial) ?></div>
                        <img src="" alt="Foto Profil" class="profile-popup-img" id="previewImage" style="display:none; margin-top: 0;">
                    <?php endif; ?>

                    <label style="text-align: center; color: #2563eb; cursor: pointer;">
                        <i class="fa fa-camera"></i> Ubah Foto
                        <input
                            type="file"
                            name="profile_picture"
                            id="profilePictureInput"
                            style="display: none;"
                            accept="image/jpeg, image/png, image/webp"
                            onchange="previewFile()"
                        >
                    </label>
                </div>

                <div class="form-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama_lengkap" value="<?= htmlspecialchars($user['nama_lengkap']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Jabatan/Role</label>
                    <input type="text" name="jabatan" value="<?= htmlspecialchars($user['jabatan']) ?>" required>
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" readonly style="background: #f0f0f0;">
                </div>

                <button type="submit"><i class="fa fa-save"></i> Simpan Perubahan Profil</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="fa fa-moon"></i> Tema Tampilan</h3>
            <p>Pilih tema Light / Dark Mode.</p>
            <form id="themeForm" method="POST" action="pengaturan.php">
                <input type="hidden" name="toggle_theme" value="1">
                <button type="button" onclick="toggleTheme()"><i class="fa fa-adjust"></i> Ganti Tema</button>
            </form>
        </div>

        <div class="card">
            <h3><i class="fa fa-database"></i> Penyimpanan Cloud</h3>

            <p><b><?= _fmt_size_cfg($used) ?></b> dari <b><?= _fmt_size_cfg($limit) ?></b> digunakan</p>

            <div class="progress">
                <div class="progress-bar" style="width: <?= round($percent,2) ?>%;"></div>
            </div>
        </div>

        <div class="card">
            <h3><i class="fa fa-shield-alt"></i> Pengaturan Sistem</h3>
            <form method="POST" action="pengaturan.php">
                <div class="form-group">
                    <label>Bahasa</label>
                    <select name="language" onchange="this.form.submit()">
                        <option <?= (($_SESSION['lang'] ?? 'Indonesia') == 'Indonesia') ? 'selected' : '' ?>>Indonesia</option>
                        <option <?= (($_SESSION['lang'] ?? '') == 'English') ? 'selected' : '' ?>>English</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notifikasi</label>
                    <select name="notif" onchange="this.form.submit()">
                        <option <?= (($_SESSION['notif'] ?? 'Aktif') == 'Aktif') ? 'selected' : '' ?>>Aktif</option>
                        <option <?= (($_SESSION['notif'] ?? '') == 'Nonaktif') ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <button type="submit"><i class="fa fa-save"></i> Simpan Pengaturan Sistem</button>
            </form>
        </div>
    </div>

    <div id="profileModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeProfileModal()" aria-label="Tutup">&times;</button>

            <div class="profile-header">
                <h4><?= htmlspecialchars($user['email']) ?></h4>

                <?php if (!empty($user['foto_url'])): ?>
                    <img src="../<?= htmlspecialchars($user['foto_url']) ?>" alt="Foto Profil User" class="profile-popup-img">
                <?php else: ?>
                    <div class="profile-popup-img"><?= htmlspecialchars($initial) ?></div>
                <?php endif; ?>

                <h3>Halo, <?= htmlspecialchars($user['nama_lengkap']) ?>.</h3>

                <a href="pengaturan.php" class="btn-manage-cloudify"><i class="fa fa-gear"></i> Kelola Cloudify Anda</a>
            </div>

            <div class="profile-actions">
                <button onclick="window.location.href='pengaturan.php'"><i class="fa fa-user-edit"></i> Ubah Nama</button>

                <button class="btn-logout" onclick="handleLogout()"><i class="fa fa-sign-out-alt"></i> Logout</button>
            </div>

            <div class="storage-info-modal">
                <i class="fa fa-cloud"></i>
                <span id="modal-storage-text"><?= round($percent) ?>% dari <?= _fmt_size_cfg($limit) ?> telah digunakan</span>
            </div>

        </div>
    </div>

    <!-- optional account popup (small) -->
    <div id="accountPopup" style="display:none;">
        <div style="padding:16px; width:320px; background:white; border-radius:8px; box-shadow:0 8px 20px rgba(0,0,0,0.12);">
            <div style="display:flex; gap:12px; align-items:center;">
                <div style="width:48px; height:48px; border-radius:50%; overflow:hidden;">
                    <?php if (!empty($user['foto_url'])): ?>
                        <img src="../<?= htmlspecialchars($user['foto_url']) ?>" style="width:100%;height:100%;object-fit:cover;">
                    <?php else: ?>
                        <div style="width:48px;height:48px;background:#6366f1;color:white;display:flex;align-items:center;justify-content:center;font-weight:600;">
                            <?= htmlspecialchars($initial) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div>
                    <div style="font-weight:600"><?= htmlspecialchars($user['nama_lengkap']) ?></div>
                    <div style="font-size:13px;color:#666"><?= htmlspecialchars($user['email']) ?></div>
                </div>
            </div>

            <div style="margin-top:12px; display:flex; gap:8px;">
                <a href="pengaturan.php" style="flex:1; padding:8px 10px; text-align:center; border-radius:8px; border:1px solid #ddd; text-decoration:none; color:#1a73e8;">Kelola Akun</a>
                <a href="../logout.php" style="flex:1; padding:8px 10px; text-align:center; border-radius:8px; background:#f44336; color:white; text-decoration:none;">Logout</a>
            </div>
        </div>
    </div>

</body>
</html>
