<?php
// GOOGLE DRIVE STYLE LIST VIEW – FINAL

if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/Koneksi.php');

$db = new koneksi();
$conn = $db->getConnection();

$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$userId = $_SESSION['user_id'] ?? null;

header('Content-Type: text/html; charset=utf-8');

// Batas input search
if (strlen($q) > 200) $q = substr($q, 0, 200);

// Query utama
$sql = "
    SELECT id, name, path, size, mime, extension, created_at
    FROM files
    WHERE owner_id = ?
      AND (is_deleted = 0 OR is_deleted IS NULL)
";

$params = [$userId];
$types  = "i";

if ($q !== "") {
    $sql .= " AND name LIKE ? ";
    $params[] = "%$q%";
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

// BASE URL
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    define("BASE_URL", "$protocol://".$_SERVER['HTTP_HOST']."/Cloudify/Cloudify/");
}

// Jika tidak ada hasil
if ($res->num_rows === 0) {
    ?>
    <div style="padding:28px;text-align:center;color:#555;">
        <i class="fa fa-search" style="font-size:48px;margin-bottom:12px;color:#9aa0a6;"></i>
        <div style="font-size:16px;">Tidak ada file ditemukan untuk:</div>
        <strong><?= htmlspecialchars($q) ?></strong>
    </div>
    <?php
    exit;
}
?>

<style>
/* ===================== LIST VIEW STYLE ====================== */

.drive-list-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 14px;
}

.drive-list-head {
    background: #f3f4f6;
    color: #374151;
    font-weight: 600;
}

.drive-list-head th {
    text-align: left;
    padding: 10px;
}

.drive-row {
    border-bottom: 1px solid #e5e7eb;
    transition: .15s;
    cursor: pointer;
}

.drive-row:hover {
    background: #f9fafb;
    box-shadow: inset 0 0 0 9999px rgba(0,0,0,0.03);
}

.drive-row td {
    padding: 10px;
    vertical-align: middle;
}

.file-icon-small {
    font-size: 20px;
    width: 26px !important;
}

/* MENU */
.list-menu-btn {
    background: none;
    border: 0;
    font-size: 16px;
    cursor: pointer;
    color: #444;
    padding: 6px;
}

.list-menu {
    position: absolute;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    display: none;
    z-index: 40;
}

.list-menu a {
    display: block;
    padding: 10px 14px;
    text-decoration: none;
    color: #333;
    font-size: 14px;
}

.list-menu a:hover {
    background: #f3f4f6;
}

</style>

<table class="drive-list-table">
    <tr class="drive-list-head">
        <th style="width:35px;"></th>
        <th>Nama File</th>
        <th style="width:160px;">Ukuran</th>
        <th style="width:160px;">Dimodifikasi</th>
        <th style="width:60px;"></th>
    </tr>

<?php while ($row = $res->fetch_assoc()): 
    $ext = strtolower($row['extension']);
    $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp','avif','bmp','svg']);

    // URL file final
    $fileUrl = BASE_URL . ltrim($row['path'], '/');

    // Icon file
    $icon = "fa-file";
    if ($ext === "pdf") $icon = "fa-file-pdf";
    elseif (in_array($ext, ['doc','docx'])) $icon = "fa-file-word";
    elseif (in_array($ext, ['xls','xlsx'])) $icon = "fa-file-excel";
    elseif (in_array($ext, ['zip','rar'])) $icon = "fa-file-archive";
    elseif ($isImage) $icon = "fa-image";

    $sizeMB = round($row['size']/1024/1024, 2);
?>
<tr class="drive-row">
    <td>
        <i class="fa <?= $icon ?> file-icon-small"></i>
    </td>

    <td>
        <a href="<?= htmlspecialchars($fileUrl) ?>" target="_blank" style="text-decoration:none;color:#1a73e8;">
            <?= htmlspecialchars($row['name']) ?>
        </a>
    </td>

    <td><?= $sizeMB ?> MB</td>

    <td><?= date("d M Y", strtotime($row['created_at'])) ?></td>

    <td style="position:relative;">
        <button class="list-menu-btn" onclick="toggleListMenu(<?= $row['id'] ?>)">⋮</button>

        <div id="list-menu-<?= $row['id'] ?>" class="list-menu">
            <a href="aksi/download.php?id=<?= $row['id'] ?>">Download</a>
            <a href="aksi/favorit.php?id=<?= $row['id'] ?>">Tambah Favorit</a>
            <a href="aksi/sampah.php?id=<?= $row['id'] ?>">Pindah ke Sampah</a>
        </div>
    </td>
</tr>

<?php endwhile; ?>

</table>

<script>
function toggleListMenu(id){
    document.querySelectorAll('.list-menu').forEach(menu => {
        if (menu.id !== "list-menu-"+id) menu.style.display = "none";
    });

    let menu = document.getElementById("list-menu-"+id);
    menu.style.display = (menu.style.display === "block" ? "none" : "block");
}

// Klik luar → tutup semua menu
document.addEventListener("click", function(e){
    if (!e.target.closest('.list-menu-btn') && !e.target.closest('.list-menu')){
        document.querySelectorAll('.list-menu').forEach(m => m.style.display="none");
    }
});
</script>
