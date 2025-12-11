<?php
header("Content-Type: application/json; charset=utf-8");
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Metode harus POST']);
    exit;
}

$folderName = trim($_POST['folder_name'] ?? '');
$parentId   = isset($_POST['parent_id']) && is_numeric($_POST['parent_id'])
                ? (int)$_POST['parent_id']
                : null;

if ($folderName === '') {
    echo json_encode(['success'=>false,'message'=>'Nama folder kosong']);
    exit;
}

// Sanitasi
$cleanName = preg_replace('/[^A-Za-z0-9 _\-.]/', '', $folderName);

$rootDir = realpath(__DIR__.'/../..');
$userDir = $rootDir . '/uploads/' . $userId;

if (!is_dir($userDir)) mkdir($userDir,0755,true);

$finalDir = $userDir . '/' . $cleanName;

if (is_dir($finalDir)) {
    echo json_encode(['success'=>false,'message'=>'Folder sudah ada']);
    exit;
}

mkdir($finalDir,0755,true);

$relative = "uploads/$userId/" . $cleanName;

// Insert folder metadata
require_once $rootDir.'/model/Koneksi.php';
$db = new koneksi();
$conn = $db->getConnection();

$sql = "INSERT INTO folders (name, parent_id, owner_id, path)
        VALUES (?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param('siis', $cleanName, $parentId, $userId, $relative);
$stmt->execute();

// Log
$log = $conn->prepare("INSERT INTO upload_logs (user_id, action, target)
                       VALUES (?, 'create_folder', ?)");
$log->bind_param('is', $userId, $relative);
$log->execute();

echo json_encode(['success'=>true,'message'=>'Folder berhasil dibuat']);
exit;
