<?php
header('Content-Type: application/json; charset=utf-8');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$parentFolder = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;

$rootDir = realpath(__DIR__ . '/../..');
$userDir = $rootDir . '/uploads/' . $userId;

if (!is_dir($userDir)) mkdir($userDir, 0755, true);

$maxZipSize  = 100 * 1024 * 1024;
$maxFileSize = 10 * 1024 * 1024;
$allowed     = ['jpg','jpeg','png','gif','webp','avif','pdf','doc','docx','txt','zip','rar','csv','xlsx','xls','ppt','pptx','mp3','mp4','mov','avi'];

require_once $rootDir . '/model/Koneksi.php';
$db = new koneksi();
$conn = $db->getConnection();

function insertFileMeta($conn, $folderId, $userId, $name, $path, $size, $mime, $ext) {
    $sql = "INSERT INTO files (folder_id, owner_id, name, path, size, mime, extension)
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('iississ', $folderId, $userId, $name, $path, $size, $mime, $ext);
    $stmt->execute();
}

// ================= ZIP Upload ====================
if (isset($_FILES['zip']) && $_FILES['zip']['error'] === UPLOAD_ERR_OK) {

    $zip = $_FILES['zip'];
    if ($zip['size'] > $maxZipSize) {
        echo json_encode(['success'=>false,'message'=>'ZIP terlalu besar']);
        exit;
    }

    $zipName = preg_replace('/[^A-Za-z0-9 _\-.]/','',basename($zip['name']));
    $tmp     = $zip['tmp_name'];

    $folderName = pathinfo($zipName, PATHINFO_FILENAME);
    $extractDir = $userDir . '/' . $folderName;

    if (!is_dir($extractDir)) mkdir($extractDir,0755,true);

    $za = new ZipArchive();
    if ($za->open($tmp) !== true) {
        echo json_encode(['success'=>false,'message'=>'Gagal membuka ZIP']);
        exit;
    }

    $za->extractTo($extractDir);
    $za->close();

    // Insert folder into DB
    $relativeFolder = "uploads/$userId/" . $folderName;

    $folderStmt = $conn->prepare("INSERT INTO folders (name, parent_id, owner_id, path)
                                  VALUES (?, ?, ?, ?)");
    $folderStmt->bind_param('siis', $folderName, $parentFolder, $userId, $relativeFolder);
    $folderStmt->execute();
    $newFolderId = $folderStmt->insert_id;

    // Iterate all extracted files
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($extractDir));

    foreach ($rii as $file) {
        if ($file->isDir()) continue;
        $full = $file->getPathname();
        $name = $file->getFilename();
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) continue;

        $rel = "uploads/$userId/" . $folderName . "/" . $name;
        $mime = mime_content_type($full);
        $size = filesize($full);

        insertFileMeta($conn, $newFolderId, $userId, $name, $rel, $size, $mime, $ext);
    }

    echo json_encode(['success'=>true,'message'=>'Folder ZIP berhasil diupload']);
    exit;
}

// ================= Folder Upload (webkitdirectory) ====================
if (isset($_FILES['files'])) {

    $files = $_FILES['files'];
    $count = count($files['name']);
    $saved = 0;

    for ($i=0; $i<$count; $i++) {

        if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;

        $originalName = $files['name'][$i];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;

        if ($files['size'][$i] > $maxFileSize) continue;

        $target = $userDir . '/' . $originalName;
        $dir = dirname($target);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        if (move_uploaded_file($files['tmp_name'][$i], $target)) {
            $saved++;

            $relative = "uploads/$userId/" . $originalName;
            $size = $files['size'][$i];
            $mime = mime_content_type($target);

            insertFileMeta($conn, $parentFolder, $userId, $originalName, $relative, $size, $mime, $ext);
        }
    }

    echo json_encode(['success'=>true,'message'=>"$saved file berhasil diupload"]);
    exit;
}

echo json_encode(['success'=>false,'message'=>'Tidak ada file untuk upload']);
exit;
    