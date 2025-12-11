<?php
// admin/cloud.php — Cloud Semua User (Admin Explorer Style)

// Pastikan session & role admin
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    die("Akses ditolak!");
}

// Koneksi DB
require_once __DIR__ . '/../model/Koneksi.php';

// Jika index.php sudah membuat $conn, pakai itu
if (!isset($conn) || !$conn) {
    $db   = new koneksi();
    $conn = $db->getConnection();
}

// Ambil parameter
$selectedUserId = isset($_GET['user']) && ctype_digit($_GET['user']) ? (int)$_GET['user'] : null;
$currentFolderId = isset($_GET['folder']) && ctype_digit($_GET['folder']) ? (int)$_GET['folder'] : null;

// Ambil semua user (untuk dropdown di kiri)
$users = [];
$uRes = $conn->query("SELECT id, display_name, email FROM users ORDER BY display_name ASC");
while ($row = $uRes->fetch_assoc()) {
    $users[] = $row;
}

// Jika belum pilih user, jangan ambil folder/file
$foldersById = [];
$childrenMap = [];   // parent_id => [child_ids]
$currentFolder = null;
$breadcrumb = [];

if ($selectedUserId) {

    // Ambil semua folder milik user
    $stmt = $conn->prepare("SELECT id, name, parent_id, owner_id, created_at FROM folders WHERE owner_id = ? ORDER BY name ASC");
    $stmt->bind_param("i", $selectedUserId);
    $stmt->execute();
    $fRes = $stmt->get_result();
    while ($f = $fRes->fetch_assoc()) {
        $fid = (int)$f['id'];
        $pid = is_null($f['parent_id']) ? 0 : (int)$f['parent_id']; // 0 = root
        $foldersById[$fid] = $f;
        if (!isset($childrenMap[$pid])) $childrenMap[$pid] = [];
        $childrenMap[$pid][] = $fid;
    }
    $stmt->close();

    // Cari folder saat ini (jika ada)
    if ($currentFolderId && isset($foldersById[$currentFolderId])) {
        $currentFolder = $foldersById[$currentFolderId];

        // Bangun breadcrumb dari folder saat ini ke root
        $tmpId = $currentFolderId;
        while ($tmpId && isset($foldersById[$tmpId])) {
            $breadcrumb[] = $foldersById[$tmpId];
            $parentId = $foldersById[$tmpId]['parent_id'];
            if (!$parentId) break;
            $tmpId = (int)$parentId;
        }
        $breadcrumb = array_reverse($breadcrumb);
    }

    // Ambil file di folder saat ini
    if ($currentFolderId) {
        $stmt = $conn->prepare("
            SELECT id, name, size, created_at 
            FROM files 
            WHERE owner_id = ? 
              AND folder_id = ? 
              AND (is_deleted IS NULL OR is_deleted = 0)
            ORDER BY name ASC
        ");
        $stmt->bind_param("ii", $selectedUserId, $currentFolderId);
    } else {
        // Root: folder_id IS NULL
        $stmt = $conn->prepare("
            SELECT id, name, size, created_at 
            FROM files 
            WHERE owner_id = ? 
              AND folder_id IS NULL 
              AND (is_deleted IS NULL OR is_deleted = 0)
            ORDER BY name ASC
        ");
        $stmt->bind_param("i", $selectedUserId);
    }
    $stmt->execute();
    $filesRes = $stmt->get_result();
    $files = [];
    while ($r = $filesRes->fetch_assoc()) {
        $files[] = $r;
    }
    $stmt->close();
}

// Helper format size
// function fmt_size($b){
//     $b = (int)$b;
//     if ($b >= 1073741824) return round($b/1073741824,2).' GB';
//     if ($b >= 1048576)    return round($b/1048576,2).' MB';
//     if ($b >= 1024)       return round($b/1024,2).' KB';
//     return $b . ' B';
// }
if (!function_exists('fmt_size')) {
    function fmt_size($b){
        $b = (int)$b;
        if ($b >= 1073741824) return round($b/1073741824,2).' GB';
        if ($b >= 1048576)    return round($b/1048576,2).' MB';
        if ($b >= 1024)       return round($b/1024,2).' KB';
        return $b . ' B';
    }
}

// Render tree folder secara rekursif
function renderFolderTree($parentId, $childrenMap, $foldersById, $selectedUserId, $currentFolderId, $level = 0) {
    if (!isset($childrenMap[$parentId])) return;
    echo '<ul class="tree-level level-'.$level.'">';
    foreach ($childrenMap[$parentId] as $fid) {
        $f = $foldersById[$fid];
        $isActive = ($currentFolderId == $fid);
        echo '<li>';
        echo '<a href="index.php?manage=cloud&amp;user='.$selectedUserId.'&amp;folder='.$fid.'" class="tree-folder-link'.($isActive?' active':'').'">';
        echo '📁 '.htmlspecialchars($f['name']);
        echo '</a>';
        // recursive children
        renderFolderTree($fid, $childrenMap, $foldersById, $selectedUserId, $currentFolderId, $level+1);
        echo '</li>';
    }
    echo '</ul>';
}

?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Cloud Semua User — Admin</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
<style>
body{
    margin:0;
    font-family:Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    background:#f3f4f6;
    color:#111827;
}
.app-shell{
    display:flex;
    min-height:100vh;
}

/* SIDEBAR KIRI (TREE + USER) */
.left-pane{
    width:280px;
    background:#0f172a;
    color:#e5e7eb;
    padding:16px 14px;
    box-sizing:border-box;
}
.left-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:12px;
}
.left-header h2{
    font-size:16px;
    margin:0;
}
.left-pane select{
    width:100%;
    padding:7px 8px;
    border-radius:8px;
    border:1px solid #1f2937;
    background:#111827;
    color:#e5e7eb;
    font-size:13px;
}
.left-pane select:focus{
    outline:none;
    border-color:#4b91f1;
}
.tree-wrapper{
    margin-top:14px;
    padding:8px;
    background:#020617;
    border-radius:10px;
    max-height:calc(100vh - 120px);
    overflow:auto;
    font-size:13px;
}
.tree-root-link{
    display:block;
    padding:6px 8px;
    border-radius:8px;
    text-decoration:none;
    color:#e5e7eb;
    margin-bottom:4px;
}
.tree-root-link.active,
.tree-root-link:hover{
    background:#1d4ed8;
}
.tree-level{
    list-style:none;
    margin:4px 0 4px 14px;
    padding:0;
}
.tree-folder-link{
    display:block;
    padding:4px 8px;
    border-radius:6px;
    text-decoration:none;
    color:#e5e7eb;
}
.tree-folder-link.active{
    background:#1d4ed8;
}
.tree-folder-link:hover{
    background:#1e293b;
}

/* PANEL KANAN */
.right-pane{
    flex:1;
    padding:18px 22px;
}

/* Top bar */
.top-bar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:14px;
}
.top-title{
    font-size:18px;
    font-weight:600;
}
.top-actions{
    display:flex;
    gap:8px;
}
.btn{
    padding:7px 12px;
    border-radius:8px;
    border:1px solid #d1d5db;
    background:#ffffff;
    cursor:pointer;
    font-size:13px;
}
.btn-primary{
    background:#4b91f1;
    color:#fff;
    border-color:#4b91f1;
}
.btn-primary:hover{
    background:#2563eb;
}
.btn:hover{
    background:#f3f4ff;
}

/* Breadcrumb */
.breadcrumb{
    font-size:13px;
    color:#4b5563;
    margin-bottom:12px;
}
.breadcrumb a{
    color:#2563eb;
    text-decoration:none;
}
.breadcrumb a:hover{
    text-decoration:underline;
}
.breadcrumb span.sep{
    margin:0 4px;
}

/* Card */
.card{
    background:#ffffff;
    border-radius:12px;
    padding:14px 16px;
    box-shadow:0 4px 14px rgba(15,23,42,0.08);
    margin-bottom:16px;
}

/* Folder grid (Hybrid: folder = grid) */
.folder-grid{
    display:grid;
    grid-template-columns:repeat(auto-fill,minmax(140px,1fr));
    gap:10px;
}
.folder-card{
    background:#f9fafb;
    border-radius:10px;
    padding:10px;
    cursor:pointer;
    border:1px solid #e5e7eb;
    display:flex;
    flex-direction:column;
    gap:4px;
}
.folder-card:hover{
    background:#eef2ff;
    border-color:#6366f1;
}
.folder-name{
    font-weight:600;
    font-size:14px;
    display:flex;
    align-items:center;
    gap:6px;
}
.folder-meta{
    font-size:11px;
    color:#6b7280;
}

/* File table (Hybrid: file = list) */
.table{
    width:100%;
    border-collapse:collapse;
    font-size:13px;
}
.table th,
.table td{
    padding:8px 10px;
    border-bottom:1px solid #e5e7eb;
    text-align:left;
}
.table th{
    background:#f9fafb;
    font-weight:600;
    color:#4b5563;
}
.badge-empty{
    font-size:13px;
    color:#6b7280;
}

/* Modal */
.modal-overlay{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,0.45);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:2000;
}
.modal-box{
    background:#ffffff;
    width:360px;
    max-width:95vw;
    padding:18px;
    border-radius:12px;
    box-shadow:0 12px 32px rgba(15,23,42,0.25);
}
.modal-box h3{
    margin:0 0 8px;
    font-size:16px;
}
.modal-box label{
    display:block;
    margin-top:8px;
    margin-bottom:4px;
    font-size:13px;
    font-weight:600;
}
.modal-box input,
.modal-box select{
    width:100%;
    padding:8px 10px;
    border-radius:8px;
    border:1px solid #d1d5db;
    font-size:13px;
    box-sizing:border-box;
}
.modal-btn-row{
    display:flex;
    justify-content:flex-end;
    gap:8px;
    margin-top:14px;
}

/* Responsive */
@media(max-width:900px){
    .app-shell{
        flex-direction:column;
    }
    .left-pane{
        width:100%;
        max-height:260px;
        overflow:auto;
    }
}
</style>
</head>
<body>

<div class="app-shell">

    <!-- LEFT: User selector + Folder Tree -->
    <aside class="left-pane">
        <div class="left-header">
            <h2>Cloud Admin</h2>
            <a href="index.php" style="font-size:11px;color:#9ca3af;text-decoration:none;">Dashboard</a>
        </div>

        <div>
            <label style="font-size:12px;margin-bottom:3px;display:block;">Pilih User</label>
            <form method="get" action="index.php">
                <input type="hidden" name="manage" value="cloud">
                <select name="user" onchange="this.form.submit()">
                    <option value="">-- Pilih user --</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"
                            <?= $selectedUserId == $u['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($u['display_name']) ?> (<?= htmlspecialchars($u['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if ($selectedUserId): ?>
        <div class="tree-wrapper">
            <?php
            $rootActive = $currentFolderId ? false : true;
            ?>
            <a class="tree-root-link <?= $rootActive?'active':'' ?>"
               href="index.php?manage=cloud&amp;user=<?= $selectedUserId ?>">
                <i class="fa fa-folder-open"></i> Root
            </a>
            <?php
            // root parent_id = 0
            renderFolderTree(0, $childrenMap, $foldersById, $selectedUserId, $currentFolderId);
            ?>
        </div>
        <?php endif; ?>
    </aside>

    <!-- RIGHT: Content -->
    <main class="right-pane">

        <div class="top-bar">
            <div class="top-title">
                📁 Cloud Semua User
                <?php if ($selectedUserId): ?>
                    <?php
                        $userLabel = '';
                        foreach ($users as $u) {
                            if ($u['id'] == $selectedUserId) {
                                $userLabel = $u['display_name'];
                                break;
                            }
                        }
                    ?>
                    <span style="font-size:13px;font-weight:500;color:#6b7280;">
                        — <?= htmlspecialchars($userLabel) ?>
                    </span>
                <?php endif; ?>
            </div>
            <div class="top-actions">
                <?php if ($selectedUserId): ?>
                    <button class="btn" onclick="location.reload()"><i class="fa fa-rotate"></i> Refresh</button>
                    <button class="btn btn-primary" onclick="openCreateFolderModal()">
                        <i class="fa fa-folder-plus"></i> Folder di sini
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$selectedUserId): ?>
            <div class="card">
                <p>Pilih user di panel kiri untuk melihat dan mengelola file/folder.</p>
            </div>
        <?php else: ?>

            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="index.php?manage=cloud&amp;user=<?= $selectedUserId ?>">Root</a>
                <?php foreach ($breadcrumb as $b): ?>
                    <span class="sep">›</span>
                    <a href="index.php?manage=cloud&amp;user=<?= $selectedUserId ?>&amp;folder=<?= (int)$b['id'] ?>">
                        <?= htmlspecialchars($b['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Folder section (grid) -->
            <section class="card">
                <h3 style="margin:0 0 10px;font-size:15px;">Folder</h3>
                <?php
                    $subFolders = [];
                    if (isset($childrenMap[$currentFolderId ? $currentFolderId : 0])) {
                        foreach ($childrenMap[$currentFolderId ? $currentFolderId : 0] as $fid) {
                            $subFolders[] = $foldersById[$fid];
                        }
                    }
                ?>
                <?php if (count($subFolders) === 0): ?>
                    <div class="badge-empty">Tidak ada folder di lokasi ini.</div>
                <?php else: ?>
                    <div class="folder-grid">
                        <?php foreach ($subFolders as $f): ?>
                            <div class="folder-card"
                                 onclick="location.href='index.php?manage=cloud&amp;user=<?= $selectedUserId ?>&amp;folder=<?= (int)$f['id'] ?>'">
                                <div class="folder-name">
                                    📁 <span><?= htmlspecialchars($f['name']) ?></span>
                                </div>
                                <div class="folder-meta">
                                    ID: <?= (int)$f['id'] ?> · Dibuat: <?= htmlspecialchars(substr($f['created_at'],0,10)) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- File section (list) -->
            <section class="card">
                <h3 style="margin:0 0 10px;font-size:15px;">File</h3>

                <?php if (empty($files)): ?>
                    <div class="badge-empty">Tidak ada file di lokasi ini.</div>
                <?php else: ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nama File</th>
                                <th>Ukuran</th>
                                <th>Uploaded</th>
                                <th style="width:140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $f): ?>
                                <tr>
                                    <td>📄 <?= htmlspecialchars($f['name']) ?></td>
                                    <td><?= fmt_size($f['size']) ?></td>
                                    <td><?= htmlspecialchars(substr($f['created_at'],0,19)) ?></td>
                                    <td>
                                        <button class="btn" onclick="openMoveFileModal(<?= (int)$f['id'] ?>)">Pindah</button>
                                        <button class="btn" onclick="location.href='../download.php?id=<?= (int)$f['id'] ?>'">Download</button>
                                        <button class="btn" onclick="if(confirm('Hapus file ini?')) location.href='../delete.php?id=<?= (int)$f['id'] ?>'">Hapus</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

        <?php endif; ?>

    </main>
</div>

<?php if ($selectedUserId): ?>
<!-- MODAL: BUAT FOLDER -->
<div class="modal-overlay" id="createFolderModal">
    <div class="modal-box">
        <h3>Buat Folder Baru</h3>
        <input type="hidden" id="cf-owner-id" value="<?= (int)$selectedUserId ?>">
        <input type="hidden" id="cf-parent-id" value="<?= $currentFolderId ? (int)$currentFolderId : '' ?>">

        <label>Nama Folder</label>
        <input type="text" id="cf-name" placeholder="Misal: Dokumen Penting">

        <div class="modal-btn-row">
            <button class="btn" onclick="closeCreateFolderModal()">Batal</button>
            <button class="btn btn-primary" onclick="submitCreateFolder()">Buat</button>
        </div>
    </div>
</div>

<!-- MODAL: PINDAH FILE -->
<div class="modal-overlay" id="moveFileModal">
    <div class="modal-box">
        <h3>Pindahkan File</h3>
        <input type="hidden" id="mf-file-id">

        <label>Folder Tujuan</label>
        <select id="mf-folder-select">
            <option value="">(Root)</option>
        </select>

        <div class="modal-btn-row">
            <button class="btn" onclick="closeMoveFileModal()">Batal</button>
            <button class="btn btn-primary" onclick="submitMoveFile()">Pindahkan</button>
        </div>
    </div>
</div>

<script>
const CURRENT_USER_ID  = <?= (int)$selectedUserId ?>;
const CURRENT_FOLDER_ID = <?= $currentFolderId ? (int)$currentFolderId : 'null' ?>;

// ---------- Modal Create Folder ----------
function openCreateFolderModal(){
    document.getElementById('cf-name').value = '';
    document.getElementById('createFolderModal').style.display = 'flex';
}
function closeCreateFolderModal(){
    document.getElementById('createFolderModal').style.display = 'none';
}
function submitCreateFolder(){
    const name    = document.getElementById('cf-name').value.trim();
    const ownerId = document.getElementById('cf-owner-id').value;
    const parentId= document.getElementById('cf-parent-id').value;

    if (!name){
        alert('Nama folder wajib diisi.');
        return;
    }

    fetch('folders_api.php?action=create', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            name: name,
            owner_id: ownerId,
            parent_id: parentId
        })
    })
    .then(r=>r.json())
    .then(j=>{
        if (!j.ok){
            alert(j.message || 'Gagal membuat folder.');
            return;
        }
        location.reload();
    })
    .catch(e=>{
        console.error(e);
        alert('Terjadi kesalahan.');
    });
}

// ---------- Modal Move File ----------
function openMoveFileModal(fileId){
    document.getElementById('mf-file-id').value = fileId;
    loadFoldersForMove();
    document.getElementById('moveFileModal').style.display = 'flex';
}
function closeMoveFileModal(){
    document.getElementById('moveFileModal').style.display = 'none';
}

function loadFoldersForMove(){
    fetch('folders_api.php?action=list')
        .then(r=>r.json())
        .then(j=>{
            const sel = document.getElementById('mf-folder-select');
            sel.innerHTML = '<option value="">(Root)</option>';

            if (!j.ok || !j.rows) return;

            j.rows
              .filter(f => parseInt(f.owner_id) === CURRENT_USER_ID)
              .forEach(f=>{
                  const opt = document.createElement('option');
                  opt.value = f.id;
                  opt.textContent = f.name + ' (ID ' + f.id + ')';
                  if (CURRENT_FOLDER_ID && parseInt(CURRENT_FOLDER_ID) === parseInt(f.id)){
                      opt.selected = true;
                  }
                  sel.appendChild(opt);
              });
        })
        .catch(console.error);
}

function submitMoveFile(){
    const fileId   = document.getElementById('mf-file-id').value;
    const folderId = document.getElementById('mf-folder-select').value;

    fetch('folders_api.php?action=moveFile', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:new URLSearchParams({
            file_id: fileId,
            folder_id: folderId
        })
    })
    .then(r=>r.json())
    .then(j=>{
        if (!j.ok){
            alert(j.message || 'Gagal memindahkan file.');
            return;
        }
        location.reload();
    })
    .catch(e=>{
        console.error(e);
        alert('Terjadi kesalahan saat memindahkan file.');
    });
}
</script>
<?php endif; ?>

</body>
</html>
