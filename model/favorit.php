<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/Koneksi.php');

$koneksi = new koneksi();
$conn    = $koneksi->getConnection();
$userId  = (int)($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    echo "<p>Silakan login terlebih dahulu.</p>";
    exit;
}

$items = [];

/* ---------------------------------------------------
   AMBIL FAVORIT (folder + file) MILIK USER LOGIN
--------------------------------------------------- */
$sql = "
SELECT f.id AS fav_id, f.created_at,

       fl.id   AS folder_id,
       fl.name AS folder_name,

       fi.id   AS file_id,
       fi.name AS file_name,
       fi.size,
       fi.extension,
       fi.path

FROM favorites f
LEFT JOIN folders fl ON f.folder_id = fl.id
LEFT JOIN files   fi ON f.file_id   = fi.id
WHERE f.user_id = ?
ORDER BY f.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) $items[] = $r;
$stmt->close();

/* Format size */
function fav_fmt_size($b){
    if(!$b) return "—";
    if($b>=1073741824) return round($b/1073741824,1)." GB";
    if($b>=1048576)  return round($b/1048576,1)." MB";
    if($b>=1024)     return round($b/1024,1)." KB";
    return $b." B";
}

$BASE = defined("BASE_URL") ? BASE_URL : "http://localhost/Cloudify/Cloudify/";
?>

<style>
/* ===========================
   Header
=========================== */
.fav-title {
    font-size: 26px;
    font-weight: 700;
    margin-bottom: 18px;
    color: #111;
}

/* ===========================
   Grid Container
=========================== */
.fav-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 20px;
}

/* ===========================
   CARD
=========================== */
.fav-card {
    background: #ffffff;
    padding: 18px;
    border-radius: 18px;
    border: 1px solid #e5e7eb;
    box-shadow: 0 6px 20px rgba(0,0,0,.06);
    display: flex;
    gap: 14px;
    position: relative;
    transition: .25s ease;
}
.fav-card:hover {
    transform: translateY(-6px);
    box-shadow: 0 14px 34px rgba(0,0,0,.12);
}

/* ICON */
.fav-icon {
    font-size: 40px;
    color: #ffb400;
}

/* NAME + Meta */
.fav-name a {
    font-weight: 600;
    font-size: 16px;
    color: #111;
    text-decoration: none;
}
.fav-name a:hover { color: #2563eb; }

.fav-meta {
    margin-top: 6px;
    font-size: 13px;
    color: #555;
}

/* ===========================
   MENU BUTTON (titik 3)
=========================== */
.fav-menu-btn {
    position: absolute;
    top: 8px;
    right: 12px;
    cursor: pointer;
    font-size: 18px;
    color: #666;
    padding: 6px;
    transition: .2s;
}
.fav-menu-btn:hover { color: #000; }

/* ===========================
   POPUP MENU
=========================== */
.fav-menu {
    position: absolute;
    top: 40px;
    right: 10px;
    background: #fff;
    border-radius: 12px;
    width: 180px;
    padding: 6px 0;
    display: none;
    box-shadow: 0 12px 30px rgba(0,0,0,.14);
    animation: favFadeIn .15s ease-out;
    z-index: 9999;
}
.fav-menu a {
    display: block;
    padding: 10px 16px;
    text-decoration: none;
    font-size: 14px;
    color: #222;
}
.fav-menu a:hover { background: #f1f3f4; }

/* empty state */
.fav-empty {
    text-align: center;
    padding: 40px;
    opacity: .75;
}

@keyframes favFadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to   { opacity: 1; transform: translateY(0); }
}
</style>

<script>
// =========================
// Toggle popup titik 3
// =========================
function favToggleMenu(id, e){
    e.stopPropagation();
    document.querySelectorAll(".fav-menu").forEach(m=>m.style.display="none");
    let el = document.getElementById("fav-menu-"+id);
    if (el) {
        el.style.display = (el.style.display === "block") ? "none" : "block";
    }
}

// Close popup kalau klik di luar
document.addEventListener("click", ()=> {
    document.querySelectorAll(".fav-menu").forEach(m=>m.style.display="none");
});

// =========================
// Hapus dari favorit (AJAX)
// =========================
function favRemove(favId){
    if (!confirm("Hapus item ini dari Favorit?")) return;

    fetch("aksi/favorit.php?hapus=" + favId, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.text())
    .then(txt => {
        // kalau OK, hapus card dari DOM
        let card = document.querySelector(".fav-card[data-fav-id='"+favId+"']");
        if (card) card.remove();

        // kalau semua habis, bisa reload / tampil pesan kosong
        if (document.querySelectorAll(".fav-card").length === 0) {
            location.reload();
        }
    })
    .catch(err => {
        console.error(err);
        alert("Gagal menghapus dari favorit.");
    });
}
</script>

<div class="fav-title">⭐ Favorit</div>

<?php if(empty($items)): ?>
    <div class="fav-empty">
        <img src="img/empty.png" width="180">
        <h3>Belum ada item favorit</h3>
    </div>

<?php else: ?>
<div class="fav-grid">
<?php foreach($items as $i): 
    $favId = (int)$i["fav_id"];
    $isFolder = !empty($i["folder_id"]);
?>
<div class="fav-card" data-fav-id="<?= $favId ?>">

    <!-- titik 3 -->
    <div class="fav-menu-btn fa fa-ellipsis-v"
         onclick="favToggleMenu('<?= $favId ?>', event)">
    </div>

    <!-- dropdown -->
    <div class="fav-menu" id="fav-menu-<?= $favId ?>">
        <?php if($isFolder): ?>
            <a href="index.php?show=cloud&folder_id=<?= (int)$i['folder_id'] ?>">Buka Folder</a>
        <?php else: ?>
            <a href="<?= htmlspecialchars($BASE . ltrim($i['path'],'/')) ?>" target="_blank">Buka File</a>
        <?php endif; ?>

        <a href="javascript:void(0)" onclick="favRemove(<?= $favId ?>)">
            Hapus dari Favorit
        </a>
    </div>

    <div class="fav-icon">
        <i class="fa <?= $isFolder ? "fa-folder" : "fa-file" ?>"></i>
    </div>

    <div>
        <div class="fav-name">
            <?php if($isFolder): ?>
                <a href="index.php?show=cloud&folder_id=<?= (int)$i['folder_id'] ?>">
                    <?= htmlspecialchars($i["folder_name"]) ?>
                </a>
            <?php else: ?>
                <a href="<?= htmlspecialchars($BASE . ltrim($i["path"],'/')) ?>" target="_blank">
                    <?= htmlspecialchars($i["file_name"]) ?>
                </a>
            <?php endif; ?>
        </div>
        <div class="fav-meta">
            <?= $i['file_id'] ? fav_fmt_size($i['size']) : "Folder" ?><br>
            Ditandai: <?= date("d M Y", strtotime($i["created_at"])) ?>
        </div>
    </div>

</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
