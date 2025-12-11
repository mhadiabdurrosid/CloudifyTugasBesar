<?php
// Pastikan sesi dimulai
if (session_status() === PHP_SESSION_NONE) session_start();

require_once(__DIR__ . '/Koneksi.php');

$conn = $koneksi->getConnection();

// Identifikasi user
$user_id = $_SESSION['user_id'] ?? 0;

// Jika tidak login
if (!$user_id) {
    echo "<div style='padding:20px;color:red;'>User tidak terdeteksi. Login ulang.</div>";
    exit;
}

// Helper format ukuran
function fmt($b){
    if ($b >= 1073741824) return round($b/1073741824,2) . " GB";
    if ($b >= 1048576) return round($b/1048576,2) . " MB";
    if ($b >= 1024) return round($b/1024,2) . " KB";
    return $b . " B";
}

// =======================================================
// 1. AMBIL KUOTA USER (default 30GB jika tidak ada)
// =======================================================
$quota = 32212254720; // 30GB

$q = $conn->prepare("SELECT quota_bytes FROM storage_quotas WHERE user_id = ? LIMIT 1");
$q->bind_param("i", $user_id);
$q->execute();
$res = $q->get_result();
if ($res && $row = $res->fetch_assoc()) {
    $quota = (int)$row['quota_bytes'];
}
$q->close();

// =======================================================
// 2. TOTAL PEMAKAIAN
// =======================================================
$totalUsed = 0;

$q2 = $conn->prepare("
    SELECT COALESCE(SUM(size),0) AS total 
    FROM files 
    WHERE owner_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)
");
$q2->bind_param("i", $user_id);
$q2->execute();
$totalUsed = (int) $q2->get_result()->fetch_assoc()['total'];
$q2->close();

$pct = $quota > 0 ? round(($totalUsed / $quota) * 100, 1) : 0;
if ($pct > 100) $pct = 100;

// =======================================================
// 3. KATEGORI FILE (Gambar, Video, PDF, Dokumen)
// =======================================================
$extImg = ["jpg","jpeg","png","gif","webp","avif"];
$extVid = ["mp4","mkv","mov","avi"];
$extPdf = ["pdf"];
$extDoc = ["doc","docx","xls","xlsx","ppt","pptx","txt","csv","zip","rar"];

$kategori = [
    "gambar" => $extImg,
    "video"  => $extVid,
    "pdf"    => $extPdf,
    "doc"    => $extDoc
];

$stat = [];
foreach ($kategori as $key => $exts) {
    $place = implode(",", array_fill(0, count($exts), "?"));
    $sql = "SELECT COALESCE(SUM(size),0) AS t FROM files 
            WHERE owner_id = ? AND extension IN ($place)
            AND (is_deleted = 0 OR is_deleted IS NULL)";

    $stmt = $conn->prepare($sql);

    $types = "i" . str_repeat("s", count($exts));
    $params = array_merge([$user_id], $exts);

    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $stat[$key] = (int)$stmt->get_result()->fetch_assoc()['t'];
    $stmt->close();
}

// =======================================================
// 4. LIST FILE PAGINATION
// =======================================================
$limit = 20;
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$stmtTotal = $conn->prepare("SELECT COUNT(*) AS t FROM files WHERE owner_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
$stmtTotal->bind_param("i", $user_id);
$stmtTotal->execute();
$totalFiles = $stmtTotal->get_result()->fetch_assoc()['t'];
$stmtTotal->close();

$totalPages = ceil($totalFiles / $limit);

$stmt = $conn->prepare("
    SELECT id, name, path, size, extension, created_at 
    FROM files 
    WHERE owner_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("iii", $user_id, $limit, $offset);
$stmt->execute();
$files = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<style>
/* Modern Google Drive UI */
.storage-box {
    padding: 25px;
    font-family: "Inter", sans-serif;
}

.progress-card {
    background: #fff;
    padding: 18px;
    border-radius: 14px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.05);
}

.bar { height: 13px; border-radius: 10px; background: #E0E7FF; overflow: hidden; }
.bar > div { height: 100%; background: #6366F1; width: 0; transition: .9s; }

.cat-section { display:flex; gap:20px; margin-top:20px; }
.cat { flex:1; font-size:14px; }
.cat small { color:#555; }

/* File Cards */
.file-grid {
    margin-top: 20px;
    display:grid;
    grid-template-columns: repeat(auto-fill, minmax(330px,1fr));
    gap:14px;
}

.file-card{
    background:white;
    padding:12px;
    border-radius:12px;
    display:flex;
    gap:14px;
    box-shadow:0 4px 14px rgba(0,0,0,.05);
}
.file-card i{ font-size:26px; color:#6366F1; }

/* Pagination */
.pagination{ margin-top:20px; text-align:center; }
.pagination a{
    padding:8px 14px; background:#6366F1; color:white;
    border-radius:6px; text-decoration:none; margin:0 4px;
}
.pagination .active{ background:#ccc;color:#333; }
</style>

<div class="storage-box">

    <h2>Penyimpanan Cloud</h2>

    <div class="progress-card">

        <div style="display:flex;justify-content:space-between;">
            <div>
                <strong><?= fmt($totalUsed) ?></strong> dari <strong><?= fmt($quota) ?></strong> digunakan
            </div>
            <div style="font-size:20px;font-weight:700;"><?= $pct ?>%</div>
        </div>

        <div class="bar"><div id="mainBar"></div></div>

        <div class="cat-section">
            <div class="cat">
                <strong>Gambar</strong> <br><small><?= fmt($stat['gambar']) ?></small>
                <div class="bar"><div style="background:#3B82F6;width:0" id="barImg"></div></div>
            </div>

            <div class="cat">
                <strong>Video</strong> <br><small><?= fmt($stat['video']) ?></small>
                <div class="bar"><div style="background:#8B5CF6;width:0" id="barVid"></div></div>
            </div>

            <div class="cat">
                <strong>PDF</strong> <br><small><?= fmt($stat['pdf']) ?></small>
                <div class="bar"><div style="background:#EC4899;width:0" id="barPdf"></div></div>
            </div>

            <div class="cat">
                <strong>Dokumen</strong> <br><small><?= fmt($stat['doc']) ?></small>
                <div class="bar"><div style="background:#F97316;width:0" id="barDoc"></div></div>
            </div>
        </div>
    </div>

    <h3 style="margin-top:25px;">Daftar File (<?= $totalFiles ?>)</h3>

    <?php if(empty($files)): ?>
        <div style="padding:25px;background:#f9f9f9;border-radius:10px;text-align:center;color:#666;">
            Tidak ada file di penyimpanan.
        </div>
    <?php else: ?>

        <div class="file-grid">
            <?php foreach($files as $f): ?>
                <div class="file-card">
                    <i class="fa fa-file"></i>
                    <div style="flex:1;">
                        <div><strong><?= htmlspecialchars($f['name']) ?></strong></div>
                        <small><?= fmt($f['size']) ?> • <?= date("d M Y", strtotime($f['created_at'])) ?></small>
                        <br>
                        <a href="<?= $f['path'] ?>" target="_blank" style="color:#6366F1;font-size:14px;">Buka</a> |
                        <a href="crud/trash.php?id=<?= $f['id'] ?>" style="color:#dc2626;font-size:14px;">Sampah</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php for($i=1;$i<=$totalPages;$i++): ?>
                    <a href="?show=penyimpanan&page=<?= $i ?>" class="<?= $i==$page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<script>
document.getElementById("mainBar").style.width = "<?= $pct ?>%";

let used = <?= $totalUsed ?>;
let stat = <?= json_encode($stat) ?>;

document.getElementById("barImg").style.width = (stat.gambar/used*100) + "%";
document.getElementById("barVid").style.width = (stat.video/used*100) + "%";
document.getElementById("barPdf").style.width = (stat.pdf/used*100) + "%";
document.getElementById("barDoc").style.width = (stat.doc/used*100) + "%";
</script>
