<?php
session_start();

require_once __DIR__ . '/../model/Koneksi.php';
$db = new koneksi();
$conn = $db->getConnection();

if (!isset($_SESSION['user_id'])) {
    exit("Unauthorized");
}

$userId = (int) $_SESSION['user_id'];

// ===============================
// 1. BUANG FILE KE SAMPAH
// ===============================
if (isset($_GET['file'])) {
    $fileId = (int) $_GET['file'];

    // pastikan file milik user
    $q = $conn->prepare("SELECT * FROM files WHERE id = ? AND owner_id = ? LIMIT 1");
    $q->bind_param('ii', $fileId, $userId);
    $q->execute();
    $file = $q->get_result()->fetch_assoc();

    if (!$file) {
        exit("File tidak ditemukan / bukan milik Anda");
    }

    // tandai file sebagai sampah
    $u = $conn->prepare("UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE id = ?");
    $u->bind_param('i', $fileId);
    $u->execute();

    header("Location: ../index.php?show=trash");
    exit();
}



// ===============================
// 2. PINDAHKAN FOLDER KE SAMPAH
// ===============================
if (isset($_GET['folder'])) {
    $folderId = (int) $_GET['folder'];

    // validasi kepemilikan
    $q = $conn->prepare("SELECT * FROM folders WHERE id = ? AND owner_id = ? LIMIT 1");
    $q->bind_param('ii', $folderId, $userId);
    $q->execute();
    $folder = $q->get_result()->fetch_assoc();

    if (!$folder) {
        exit("Folder tidak ditemukan / bukan milik Anda");
    }

    // Buat status sampah: kita hanya menandai file dalam folder
    $u = $conn->prepare("UPDATE files SET is_deleted = 1, deleted_at = NOW() WHERE folder_id = ?");
    $u->bind_param('i', $folderId);
    $u->execute();

    header("Location: ../index.php?show=trash");
    exit();
}



// ===============================
// 3. RESTORE FILE
// ===============================
if (isset($_GET['restore'])) {
    $fileId = (int) $_GET['restore'];

    $q = $conn->prepare("SELECT * FROM files WHERE id = ? AND owner_id = ? LIMIT 1");
    $q->bind_param('ii', $fileId, $userId);
    $q->execute();
    $file = $q->get_result()->fetch_assoc();

    if (!$file) exit("File tidak ditemukan");

    $u = $conn->prepare("UPDATE files SET is_deleted = 0, deleted_at = NULL WHERE id = ?");
    $u->bind_param('i', $fileId);
    $u->execute();

    header("Location: ../index.php?show=trash");
    exit();
}



// ===============================
// 4. DELETE PERMANENT
// ===============================
if (isset($_GET['delete'])) {
    $fileId = (int) $_GET['delete'];

    $q = $conn->prepare("SELECT * FROM files WHERE id = ? AND owner_id = ? LIMIT 1");
    $q->bind_param('ii', $fileId, $userId);
    $q->execute();
    $file = $q->get_result()->fetch_assoc();

    if (!$file) exit("File tidak ditemukan");

    $filePath = realpath(__DIR__ . '/../' . $file['path']);

    // hapus file fisik
    if (file_exists($filePath)) unlink($filePath);

    // hapus dari database
    $d = $conn->prepare("DELETE FROM files WHERE id = ?");
    $d->bind_param('i', $fileId);
    $d->execute();

    header("Location: ../index.php?show=trash");
    exit();
}

echo "Tidak ada aksi";
