<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../model/Koneksi.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: ../index.php');
    exit;
}
$id = (int) $_GET['id'];

try {
    $db = $koneksi ?? new koneksi();
    $conn = $db->getConnection();

    // first fetch path so we can unlink file
    $stmt = $conn->prepare("SELECT path FROM files WHERE id = ?");
    $path = null;
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) $path = $row['path'];
        $stmt->close();
    }

    // delete DB record
    $del = $conn->prepare("DELETE FROM files WHERE id = ?");
    if ($del) {
        $del->bind_param('i', $id);
        $del->execute();
        $del->close();
    }

    // attempt to unlink physical file (path stored relative)
    if ($path) {
        $projectRoot = realpath(__DIR__ . '/..');
        $fs = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (file_exists($fs)) @unlink($fs);
    }

} catch (Throwable $e) {
    // ignore
}

header('Location: ../index.php?module=pages&page=Menu-Utama');
exit;
