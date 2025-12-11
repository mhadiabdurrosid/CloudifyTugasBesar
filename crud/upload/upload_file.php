<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$userId   = (int)$_SESSION['user_id'];
$folderId = isset($_POST['folder_id']) && is_numeric($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Metode harus POST']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['success'=>false,'message'=>'Tidak ada file dikirim']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success'=>false,'message'=>'Upload error']);
    exit;
}

// Validate extension & size
$maxSize = 10 * 1024 * 1024;
$allowed = ['jpg','jpeg','png','gif','webp','avif','pdf','doc','docx','txt','zip','rar','csv','xlsx','xls','ppt','pptx','mp3','mp4','mov','avi'];

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($ext, $allowed)) {
    echo json_encode(['success'=>false,'message'=>'Ekstensi tidak diizinkan']);
    exit;
}

if ($file['size'] > $maxSize) {
    echo json_encode(['success'=>false,'message'=>'File terlalu besar']);
    exit;
}

// SAVE FILE KE uploads/ SAJA (tanpa subfolder userId)
$rootDir = realpath(__DIR__ . '/../..');
$uploadDir = $rootDir . "/uploads/";

if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// Sanitasi nama file
$safeName = preg_replace('/[^A-Za-z0-9 _\-.]/', '', basename($file['name']));

$dest = $uploadDir . $safeName;
if (!move_uploaded_file($file['tmp_name'], $dest)) {
    echo json_encode(['success'=>false,'message'=>'Gagal menyimpan file']);
    exit;
}

// DB path menjadi: uploads/nama.jpg
$relative = "uploads/" . $safeName;
$mime     = mime_content_type($dest);
$size     = filesize($dest);

// Insert metadata into DB
require_once $rootDir . "/model/Koneksi.php";
$db  = new koneksi();
$conn = $db->getConnection();

$sql = "INSERT INTO files (folder_id, owner_id, name, path, size, mime, extension)
        VALUES (?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('iississ', $folderId, $userId, $safeName, $relative, $size, $mime, $ext);
$stmt->execute();

$fileId = $stmt->insert_id;

// Log upload
$log = $conn->prepare("INSERT INTO upload_logs (user_id, file_id, action, target)
                       VALUES (?, ?, 'upload_file', ?)");
$log->bind_param('iis', $userId, $fileId, $relative);
$log->execute();

echo json_encode(['success'=>true,'message'=>'Upload berhasil']);
exit;
