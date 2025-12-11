<?php
// model/sampah.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Koneksi.php';

$koneksi = new koneksi();
$conn = $koneksi->getConnection();

// pastikan user login
$userId = (int)($_SESSION['user_id'] ?? 0);

// ambil item yang sudah dihapus milik user (is_deleted = 1)
$items = [];
try {
    $stmt = $conn->prepare("SELECT id, name, path, size, extension, deleted_at FROM files WHERE is_deleted = 1 AND owner_id = ? ORDER BY deleted_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) $items[] = $r;
    $stmt->close();
} catch (Throwable $e) {
    $items = [];
}

// helper formatting size
function fmt_size($b){
    if ($b === null) return '—';
    $b = (int)$b;
    if ($b >= 1073741824) return round($b/1073741824,1).' GB';
    if ($b >= 1048576) return round($b/1048576,1).' MB';
    if ($b >= 1024) return round($b/1024,1).' KB';
    return $b . ' B';
}

// helper days diff
function days_since($ts){
    $deleted = strtotime($ts);
    if (!$deleted) return null;
    $now = time();
    $diff = max(0, floor(($now - $deleted) / 86400));
    return $diff;
}
?>

<!-- macOS Finder Style Sampah (Premium) -->
<style>
/* Container */
.trash-root {
    padding: 18px;
    animation: fadeIn .28s ease;
}

/* Header */
.trash-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom: 18px;
}
.trash-header h2 {
    margin:0;
    font-size:24px;
    font-weight:700;
    color:#111;
}
.trash-sub {
    color:#6b7280;
    font-size:14px;
}

/* Grid like Finder icons */
.trash-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 16px;
}

/* Card (macOS-like) */
.trash-card {
    background: linear-gradient(180deg, rgba(255,255,255,0.85), rgba(250,250,250,0.85));
    border-radius: 14px;
    padding: 14px;
    box-shadow:
        0 6px 18px rgba(15,15,15,0.06),
        inset 0 1px 0 rgba(255,255,255,0.6);
    display:flex;
    gap:12px;
    align-items:flex-start;
    transition: transform .18s ease, box-shadow .18s ease;
    border: 1px solid rgba(0,0,0,0.04);
    position: relative;
}
.trash-card:hover {
    transform: translateY(-6px);
    box-shadow:
        0 18px 40px rgba(15,15,15,0.10);
}

/* Icon box */
.icon-box {
    width:64px;
    height:64px;
    border-radius:12px;
    background: linear-gradient(135deg,#f3f4f6,#ffffff);
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:26px;
    color:#2b6cb0;
    flex-shrink:0;
    box-shadow: 0 4px 10px rgba(18,38,63,0.06);
}

/* Content */
.trash-content {
    flex:1;
}
.trash-name {
    font-weight:600;
    font-size:15px;
    color:#0f172a;
    margin-bottom:6px;
    text-decoration:none;
}
.trash-meta {
    color:#475569;
    font-size:13px;
    line-height:1.35;
}

/* Actions */
.trash-actions {
    display:flex;
    gap:8px;
    margin-top:12px;
}
.btn {
    padding:8px 12px;
    border-radius:10px;
    font-size:13px;
    text-decoration:none;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:8px;
    border: none;
}
.btn-restore {
    background: linear-gradient(180deg,#ecfdf5,#d1fae5);
    color:#065f46;
    border:1px solid rgba(6,95,70,0.08);
}
.btn-delete {
    background: linear-gradient(180deg,#fff1f2,#fee2e2);
    color:#b91c1c;
    border:1px solid rgba(185,28,28,0.08);
}

/* small meta row on top-right */
.card-top-meta {
    position:absolute;
    top:10px;
    right:10px;
    font-size:12px;
    color:#94a3b8;
}

/* Empty state */
.empty-state {
    text-align:center;
    padding:36px;
    color:#64748b;
}

/* Modal */
.modal-mask {
    display:none;
    position:fixed;
    inset:0;
    background:rgba(8,15,25,0.45);
    z-index:12000;
    justify-content:center;
    align-items:center;
}
.modal {
    width:360px;
    background: linear-gradient(180deg,#ffffff,#fbfbfd);
    border-radius:12px;
    padding:18px;
    box-shadow: 0 20px 56px rgba(2,6,23,0.45);
    text-align:center;
}
.modal h3 { margin:0 0 8px 0; font-size:18px; color:#081027; }
.modal p { color:#475569; font-size:14px; margin:0 0 14px 0; }
.modal-actions { display:flex; gap:10px; justify-content:center; margin-top:12px; }
.modal-btn { padding:8px 12px; border-radius:10px; border:none; cursor:pointer; font-size:14px; }
.modal-cancel { background:#e6eef8; color:#0f172a; }
.modal-danger { background:#fee2e2; color:#9b1c1c; }

/* animations */
@keyframes fadeIn { from {opacity:0; transform: translateY(6px)} to {opacity:1; transform: translateY(0);} }
</style>

<div class="trash-root">
    <div class="trash-header">
        <div>
            <h2>🗑 Sampah</h2>
            <div class="trash-sub">File yang dihapus akan disimpan selama 30 hari sebelum dihapus permanen.</div>
        </div>
        <div style="text-align:right; color:#64748b; font-size:13px;">
            <div>Total: <strong><?= count($items) ?></strong></div>
        </div>
    </div>

<?php if (empty($items)): ?>
    <div class="empty-state">
        <img src="img/toko.avif" alt="empty" style="width:160px; opacity:.85;">
        <h3>Sampah Kosong</h3>
        <p>Belum ada file yang dipindahkan ke sampah.</p>
    </div>
<?php else: ?>

    <div class="trash-grid">
        <?php foreach ($items as $it): 
            $days = days_since($it['deleted_at']);
            $remain = max(0, 30 - $days);
            $size = fmt_size($it['size']);
            $ext = strtolower(pathinfo($it['name'], PATHINFO_EXTENSION));
            // small icon based on type
            $icon = in_array($ext, ['jpg','jpeg','png','gif','webp','avif']) ? 'fa-image' : (in_array($ext, ['mp4','mov','avi','mkv']) ? 'fa-film' : (in_array($ext, ['mp3','wav','ogg']) ? 'fa-music' : 'fa-file'));
        ?>
        <div class="trash-card" role="article">
            <div class="icon-box"><i class="fa <?= $icon ?>" aria-hidden="true"></i></div>

            <div class="trash-content">
                <div class="trash-name"><?= htmlspecialchars($it['name']) ?></div>
                <div class="trash-meta">
                    Dihapus: <?= date('d M Y', strtotime($it['deleted_at'])) ?> • Ukuran: <?= $size ?><br>
                    <strong><?= $remain ?> hari</strong> tersisa sebelum dihapus permanen
                </div>

                <div class="trash-actions">
                    <a class="btn btn-restore" href="aksi/restore.php?id=<?= (int)$it['id'] ?>">
                        <i class="fa fa-undo" aria-hidden="true"></i> Pulihkan
                    </a>

                    <button class="btn btn-delete" onclick="openDeleteModal(<?= (int)$it['id'] ?>, '<?= addslashes(htmlspecialchars($it['name'])) ?>')">
                        <i class="fa fa-trash" aria-hidden="true"></i> Hapus Permanen
                    </button>
                </div>
            </div>

            <div class="card-top-meta"><?= htmlspecialchars($size) ?></div>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
</div>

<!-- MODAL: Hapus Permanen -->
<div id="modalDelete" class="modal-mask" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal" role="document">
        <h3>Hapus Permanen</h3>
        <p id="modalText">Anda akan menghapus file secara permanen. Tindakan ini tidak dapat dibatalkan.</p>

        <div class="modal-actions">
            <button class="modal-btn modal-cancel" onclick="closeDeleteModal()">Batal</button>
            <a id="modalDeleteConfirm" class="modal-btn modal-danger" href="#">Hapus Permanen</a>
        </div>
    </div>
</div>

<script>
function openDeleteModal(id, name){
    const mask = document.getElementById('modalDelete');
    const txt = document.getElementById('modalText');
    const btn = document.getElementById('modalDeleteConfirm');

    txt.innerHTML = "Hapus permanen: <strong>" + (name || 'file') + "</strong>? Aksi ini tidak dapat dikembalikan.";
    btn.href = "aksi/delete_permanent.php?id=" + encodeURIComponent(id);

    mask.style.display = "flex";
    mask.setAttribute('aria-hidden','false');
}

function closeDeleteModal(){
    const mask = document.getElementById('modalDelete');
    mask.style.display = "none";
    mask.setAttribute('aria-hidden','true');
}

// close modal on ESC
document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') closeDeleteModal();
});

// click outside to close
document.getElementById('modalDelete').addEventListener('click', function(e){
    if(e.target === this) closeDeleteModal();
});
</script>
