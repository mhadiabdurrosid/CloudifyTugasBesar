<?php
if (session_status()===PHP_SESSION_NONE) session_start();
require_once __DIR__.'/../model/Koneksi.php';
$db = new koneksi(); $conn = $db->getConnection();

if(!isset($_SESSION['user_id']) || strtolower($_SESSION['role']??'')!=='admin'){
    http_response_code(403); echo "Akses ditolak"; exit;
}

$fileId = (int)($_POST['file_id'] ?? 0);
$action = $_POST['action'] ?? '';

if(!$fileId || !$action){ echo "Invalid"; exit; }

if($action==='delete'){
    $stmt = $conn->prepare("UPDATE files SET is_deleted=1 WHERE id=?");
    $stmt->bind_param('i',$fileId); $stmt->execute(); $stmt->close();
    echo "OK"; exit;
}
if($action==='restore'){
    $stmt = $conn->prepare("UPDATE files SET is_deleted=0 WHERE id=?");
    $stmt->bind_param('i',$fileId); $stmt->execute(); $stmt->close();
    echo "OK"; exit;
}
if($action==='delete_permanent'){
    $stmt = $conn->prepare("SELECT path FROM files WHERE id=? LIMIT 1");
    $stmt->bind_param('i',$fileId); $stmt->execute(); $res=$stmt->get_result();
    $path = ($res && $r=$res->fetch_assoc()) ? $r['path'] : null;
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM files WHERE id=?");
    $stmt->bind_param('i',$fileId); $stmt->execute(); $stmt->close();

    if($path){ $full=__DIR__.'/../'.ltrim($path,'/'); if(file_exists($full)) @unlink($full); }
    echo "OK"; exit;
}

echo "Unknown action";
