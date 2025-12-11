<?php
// admin/folders_api.php
session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../model/Koneksi.php';
$db   = new koneksi();
$conn = $db->getConnection();

$action = $_REQUEST['action'] ?? '';

function json_fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

// -------------------- LIST FOLDER --------------------
if ($action === 'list') {
    $ownerId = isset($_GET['owner_id']) && $_GET['owner_id'] !== ''
        ? (int)$_GET['owner_id']
        : null;

    try {
        if ($ownerId) {
            $stmt = $conn->prepare("SELECT id, name, parent_id, owner_id FROM folders WHERE owner_id = ? ORDER BY name ASC");
            $stmt->bind_param('i', $ownerId);
        } else {
            $stmt = $conn->prepare("SELECT id, name, parent_id, owner_id FROM folders ORDER BY name ASC");
        }
        $stmt->execute();
        $res   = $stmt->get_result();
        $rows  = [];
        while ($r = $res->fetch_assoc()) $rows[] = $r;
        $stmt->close();

        echo json_encode(['ok' => true, 'folders' => $rows]);
        exit;
    } catch (Throwable $e) {
        json_fail('Gagal mengambil folder.');
    }
}

// -------------------- CREATE FOLDER --------------------
if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name      = trim($_POST['name'] ?? '');
    $ownerId   = $_POST['owner_id'] !== '' ? (int)$_POST['owner_id'] : null;
    $parentId  = $_POST['parent_id'] !== '' ? (int)$_POST['parent_id'] : null;

    if ($name === '') json_fail('Nama folder wajib diisi.');

    try {
        if ($parentId && $ownerId === null) {
            // fallback: jika pakai parent, tapi owner kosong → ambil owner parent
            $q = $conn->prepare("SELECT owner_id FROM folders WHERE id = ?");
            $q->bind_param('i', $parentId);
            $q->execute();
            $r = $q->get_result()->fetch_assoc();
            $q->close();
            if ($r) $ownerId = (int)$r['owner_id'];
        }

        $stmt = $conn->prepare("INSERT INTO folders(name, parent_id, owner_id, path) VALUES (?,?,?,NULL)");
        $pId  = $parentId ?: null;
        $oId  = $ownerId ?: null;
        $stmt->bind_param('sii', $name, $pId, $oId);
        $stmt->execute();
        $newId = $stmt->insert_id;
        $stmt->close();

        echo json_encode(['ok' => true, 'id' => $newId, 'message' => 'Folder berhasil dibuat.']);
        exit;
    } catch (Throwable $e) {
        json_fail('Gagal membuat folder.');
    }
}

// -------------------- RENAME FOLDER --------------------
if ($action === 'rename' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');

    if ($id <= 0) json_fail('ID folder tidak valid.');
    if ($name === '') json_fail('Nama folder tidak boleh kosong.');

    try {
        $stmt = $conn->prepare("UPDATE folders SET name = ? WHERE id = ?");
        $stmt->bind_param('si', $name, $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['ok' => true, 'message' => 'Folder berhasil diubah.']);
        exit;
    } catch (Throwable $e) {
        json_fail('Gagal mengubah folder.');
    }
}

// -------------------- DELETE FOLDER --------------------
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) json_fail('ID folder tidak valid.');

    try {
        // Cek anak folder
        $q = $conn->prepare("SELECT COUNT(*) AS c FROM folders WHERE parent_id = ?");
        $q->bind_param('i', $id);
        $q->execute();
        $r  = $q->get_result()->fetch_assoc();
        $q->close();
        if (!empty($r['c'])) json_fail('Folder masih memiliki sub-folder.');

        // Cek file di folder
        $q = $conn->prepare("SELECT COUNT(*) AS c FROM files WHERE folder_id = ?");
        $q->bind_param('i', $id);
        $q->execute();
        $r  = $q->get_result()->fetch_assoc();
        $q->close();
        if (!empty($r['c'])) json_fail('Masih ada file di dalam folder ini.');

        // Hapus folder
        $stmt = $conn->prepare("DELETE FROM folders WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['ok' => true, 'message' => 'Folder berhasil dihapus.']);
        exit;
    } catch (Throwable $e) {
        json_fail('Gagal menghapus folder.');
    }
}

// -------------------- MOVE FILE KE FOLDER --------------------
if ($action === 'move-file' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileId   = (int)($_POST['file_id'] ?? 0);
    $folderId = $_POST['folder_id'] === '' ? null : (int)$_POST['folder_id'];

    if ($fileId <= 0) json_fail('ID file tidak valid.');

    try {
        // Optional: validasi folder
        if ($folderId) {
            $q = $conn->prepare("SELECT id FROM folders WHERE id = ?");
            $q->bind_param('i', $folderId);
            $q->execute();
            $r = $q->get_result()->fetch_assoc();
            $q->close();
            if (!$r) json_fail('Folder tidak ditemukan.');
        }

        if ($folderId) {
            $stmt = $conn->prepare("UPDATE files SET folder_id = ? WHERE id = ?");
            $stmt->bind_param('ii', $folderId, $fileId);
        } else {
            // pindahkan ke root (tanpa folder)
            $stmt = $conn->prepare("UPDATE files SET folder_id = NULL WHERE id = ?");
            $stmt->bind_param('i', $fileId);
        }

        $stmt->execute();
        $stmt->close();

        echo json_encode(['ok' => true, 'message' => 'File berhasil dipindahkan.']);
        exit;
    } catch (Throwable $e) {
        json_fail('Gagal memindahkan file.');
    }
}

// -------------------- Fallback --------------------
json_fail('Aksi tidak dikenal.', 404);
