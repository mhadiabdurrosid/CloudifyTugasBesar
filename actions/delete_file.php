<?php
// actions/delete_file.php
session_start();
require_once __DIR__ . '/../model/Koneksi.php';

if (!isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>'Unauthorized']);
        exit;
    }
    exit("Unauthorized");
}

$userId = (int) $_SESSION['user_id'];
$fileId = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : 0;
if ($fileId <= 0) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>'Invalid ID']);
    } else {
        exit("ID tidak valid");
    }
    exit;
}

$db = new koneksi();
$conn = $db->getConnection();

// cek kepemilikan
$stmt = $conn->prepare("SELECT id, name FROM files WHERE id = ? AND owner_id = ? LIMIT 1");
$stmt->bind_param('ii', $fileId, $userId);
$stmt->execute();
$file = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$file) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success'=>false,'message'=>'File tidak ditemukan atau bukan milik Anda']);
    } else {
        exit("File tidak ditemukan atau bukan milik Anda");
    }
    exit;
}

// logging
$log = __DIR__ . '/../logs/delete_actions.log';
file_put_contents($log, "[".date('Y-m-d H:i:s')."] USER:$userId TRASH FILE:{$file['name']} (ID:$fileId)\n", FILE_APPEND);

// pindah ke sampah (soft delete)
$u = $conn->prepare("UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
$u->bind_param('i', $fileId);
$u->execute();
$u->close();

if (isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success'=>true,'message'=>'File dipindahkan ke sampah']);
    exit;
}

// non-AJAX fallback: redirect ke halaman trash
header("Location: ../index.php?show=trash");
exit;
