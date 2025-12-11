<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once(__DIR__ . '/Koneksi.php');

if (!isset($koneksi) || !is_object($koneksi)) {
    $koneksi = new koneksi();
}

$conn = $koneksi->getConnection();

// User aktif
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

// Base URL (optional)
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    define('BASE_URL', $protocol . '://' . $host . '/');
}

// Format size helper
if (!function_exists('_fmt_size_short')) {
    function _fmt_size_short($b){
        if ($b >= 1073741824) return round($b/1073741824,1) . " GB";
        if ($b >= 1048576) return round($b/1048576,1) . " MB";
        if ($b >= 1024) return round($b/1024,1) . " KB";
        return $b . " B";
    }
}

$folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
$folders = [];
$items = [];

/* ---------------------------------------------------
   GET FOLDERS (per user)
--------------------------------------------------- */
try {
    if ($folderId === null) {
        $sql = "SELECT id, name, parent_id, path, owner_id, created_at
                FROM folders
                WHERE owner_id = ? AND parent_id IS NULL
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $currentUserId);
    } else {
        $sql = "SELECT id, name, parent_id, path, owner_id, created_at
                FROM folders
                WHERE owner_id = ? AND parent_id = ?
                ORDER BY created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $currentUserId, $folderId);
    }

    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $folders[] = $row;

    $stmt->close();
} catch (Throwable $e) {
    $folders = [];
}

/* ---------------------------------------------------
   GET FILES (per user + status favorit)
--------------------------------------------------- */
try {
    if ($folderId === null) {
        $sql = "
        SELECT 
            f.id, f.name, f.path, f.size, f.mime, f.extension, 
            f.owner_id, f.created_at,
            (fav.id IS NOT NULL) AS is_favorite
        FROM files f
        LEFT JOIN favorites fav 
               ON fav.file_id = f.id AND fav.user_id = ?
        WHERE f.owner_id = ? 
          AND f.folder_id IS NULL 
          AND f.is_deleted = 0
        ORDER BY f.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $currentUserId, $currentUserId);
    } else {
        $sql = "
        SELECT 
            f.id, f.name, f.path, f.size, f.mime, f.extension, 
            f.owner_id, f.created_at,
            (fav.id IS NOT NULL) AS is_favorite
        FROM files f
        LEFT JOIN favorites fav 
               ON fav.file_id = f.id AND fav.user_id = ?
        WHERE f.owner_id = ? 
          AND f.folder_id = ? 
          AND f.is_deleted = 0
        ORDER BY f.created_at DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $currentUserId, $currentUserId, $folderId);
    }

    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $items[] = $row;

    $stmt->close();
} catch (Throwable $e) {
    $items = [];
}

/* ---------------------------------------------------
   BREADCRUMB
--------------------------------------------------- */
$breadcrumb = [];
if ($folderId !== null) {
    $cur = $folderId;
    $limit = 30;

    while ($cur && $limit-- > 0) {
        $stmt = $conn->prepare(
            "SELECT id, name, parent_id 
             FROM folders 
             WHERE id = ? AND owner_id = ? 
             LIMIT 1"
        );
        $stmt->bind_param("ii", $cur, $currentUserId);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$res) break;

        $breadcrumb[] = $res;
        $cur = $res["parent_id"] ?? null;
    }

    $breadcrumb = array_reverse($breadcrumb);
}
?>

<!-- ============================================= -->
<!-- ========= PREMIUM CLOUD DRIVE UI CSS ========= -->
<!-- ============================================= -->

<style>
.drive-header h2 {
    font-size: 26px;
    font-weight: 700;
    color: #111;
    margin-bottom: 10px;
}

/* Breadcrumb */
.breadcrumb {
    margin-bottom: 18px;
    font-size: 14px;
}
.breadcrumb a {
    color: #1a73e8;
    text-decoration: none;
}
.breadcrumb span {
    color: #555;
}

/* GRID */
.drive-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
    gap: 18px;
}

/* CARD */
.item-card {
    background: #fff;
    padding: 16px;
    border-radius: 16px;
    display: flex;
    gap: 14px;
    box-shadow: 0 6px 20px rgba(0,0,0,0.06);
    border: 1px solid #f1f1f1;
    position: relative;
    transition: .25s ease;
}
.item-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 12px 34px rgba(0,0,0,0.12);
}

/* ICON */
.card-icon {
    font-size: 38px;
    color: #4285f4;
}

/* TEXT */
.card-name a {
    font-size: 15px;
    font-weight: 600;
    color: #111;
    text-decoration: none;
}
.card-name a:hover {
    color: #2563eb;
}
.card-meta {
    margin-top: 4px;
    font-size: 13px;
    color: #666;
}

/* MENU BUTTON */
.card-menu-btn {
    position: absolute;
    top: 6px;
    right: 10px;
    font-size: 20px;
    border: none;
    cursor: pointer;
    color: #777;
    background: none;
}
.card-menu-btn:hover { color: #111; }

/* POPUP MENU */
.menu-popup {
    position: absolute;
    top: 36px;
    right: 6px;
    background: #fff;
    width: 190px;
    border-radius: 12px;
    box-shadow: 0 10px 26px rgba(0,0,0,0.14);
    padding: 6px 0;
    display: none;
    z-index: 99999;
}
.menu-popup a {
    padding: 9px 14px;
    color: #222;
    text-decoration: none;
    display: block;
    font-size: 14px;
}
.menu-popup a:hover {
    background: #f1f3f4;
}

/* EMPTY STATE */
.empty-state {
    text-align: center;
    padding: 40px;
    color: #777;
}
.empty-state img {
    width: 180px;
    opacity: .75;
}
</style>

<!-- ============================================= -->
<!-- ================ JS MENU + FAVORIT ========== -->
<!-- ============================================= -->
<script>
function toggleMenu(id, e){
    e.stopPropagation();

    document.querySelectorAll(".menu-popup").forEach(m => {
        if (m.id !== "menu-"+id) m.style.display = "none";
    });

    let el = document.getElementById("menu-"+id);
    el.style.display = el.style.display === "block" ? "none" : "block";
}

document.addEventListener("click", function(e){
    if (!e.target.closest(".card-menu-btn") &&
        !e.target.closest(".menu-popup")) {
        document.querySelectorAll(".menu-popup").forEach(m => m.style.display = "none");
    }
});

// AJAX toggle favorit file (pakai actions/toggle_favorite.php)
function toggleFileFavorite(fileId, linkEl){
    // feedback kecil
    linkEl.style.opacity = 0.6;

    fetch("actions/toggle_favorite.php?id=" + fileId, {
        headers: { "X-Requested-With": "XMLHttpRequest" }
    })
    .then(r => r.json())
    .then(data => {
        linkEl.style.opacity = 1;

        if (!data.success) {
            alert(data.message || "Gagal mengubah favorit");
            return;
        }

        if (data.favorited) {
            linkEl.textContent = "Hapus dari Favorit";
        } else {
            linkEl.textContent = "Tambah Favorit";
        }
    })
    .catch(err => {
        linkEl.style.opacity = 1;
        console.error(err);
        alert("Terjadi kesalahan koneksi");
    });
}
</script>

<!-- ============================================= -->
<!-- =============== HTML VIEW =================== -->
<!-- ============================================= -->

<div class="drive-header">
    <h2>Drive Saya</h2>
</div>

<!-- Breadcrumb -->
<div class="breadcrumb">
    <a href="index.php?show=cloud">Drive Saya</a>
    <?php foreach ($breadcrumb as $b): ?>
        <span> / </span>
        <a href="index.php?show=cloud&folder_id=<?= $b['id'] ?>">
            <?= htmlspecialchars($b['name']) ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if (empty($folders) && empty($items)): ?>
<div class="empty-state">
    <img src="img/empty.png" alt="empty">
    <h3>Belum ada file</h3>
    <p>Unggah file atau buat folder baru.</p>
</div>

<?php else: ?>

<!-- FOLDER LIST -->
<?php if (!empty($folders)): ?>
<strong>Folder</strong>
<div class="drive-grid">
    <?php foreach ($folders as $f): ?>
    <div class="item-card">

        <button class="fa fa-ellipsis-v card-menu-btn"
            onclick="toggleMenu('folder<?= $f['id'] ?>', event)">
        </button>

        <div class="menu-popup" id="menu-folder<?= $f['id'] ?>">
            <a href="index.php?show=cloud&folder_id=<?= $f['id'] ?>">Buka Folder</a>
            <!-- Folder favorit tetap pakai aksi/ langsung (boleh dikembangkan AJAX terpisah) -->
            <a href="aksi/favorit.php?folder=<?= $f['id'] ?>">Tambah Favorit</a>
            <a href="aksi/sampah.php?folder=<?= $f['id'] ?>">Pindah ke Sampah</a>
        </div>

        <div class="card-icon"><i class="fa fa-folder"></i></div>

        <div class="card-body">
            <div class="card-name">
                <a href="index.php?show=cloud&folder_id=<?= $f['id'] ?>">
                    <?= htmlspecialchars($f['name']) ?>
                </a>
            </div>
            <div class="card-meta">
                <?= date("d M Y", strtotime($f['created_at'])) ?>
            </div>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- FILE LIST -->
<?php if (!empty($items)): ?>
<strong>File</strong>
<div class="drive-grid">
    <?php foreach ($items as $i): 
        $isFav = !empty($i['is_favorite']);
    ?>
    <div class="item-card">

        <button class="fa fa-ellipsis-v card-menu-btn"
            onclick="toggleMenu('file<?= $i['id'] ?>', event)">
        </button>

        <div class="menu-popup" id="menu-file<?= $i['id'] ?>">
            <a href="aksi/download.php?id=<?= $i['id'] ?>">Download</a>

            <!-- FAVORIT VIA AJAX -->
            <a href="javascript:void(0)"
               onclick="toggleFileFavorite(<?= $i['id'] ?>, this)">
                <?= $isFav ? 'Hapus dari Favorit' : 'Tambah Favorit' ?>
            </a>

            <a href="aksi/sampah.php?file=<?= $i['id'] ?>">Buang ke Sampah</a>
        </div>

        <div class="card-icon">
            <i class="fa <?= in_array($i['extension'], ['jpg','jpeg','png','gif','webp','avif']) ? 'fa-image' : 'fa-file' ?>"></i>
        </div>

        <div class="card-body">
            <div class="card-name">
                <a href="<?= htmlspecialchars($i['path']) ?>" target="_blank">
                    <?= htmlspecialchars($i['name']) ?>
                </a>
            </div>
            <div class="card-meta">
                <?= date("d M Y", strtotime($i['created_at'])) ?> • <?= _fmt_size_short($i['size']) ?>
            </div>
        </div>

    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>
