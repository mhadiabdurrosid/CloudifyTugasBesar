<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../model/Koneksi.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success"=>false,"message"=>"Unauthorized"]);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$fileId   = (int)($_GET['file_id']   ?? 0);
$folderId = (int)($_GET['folder_id'] ?? 0);

$db  = new koneksi();
$conn = $db->getConnection();

/* -----------------------------
   TOGGLE FILE FAVORITE
------------------------------*/
if ($fileId > 0) {

    $cek = $conn->prepare("SELECT id FROM files WHERE id=? AND owner_id=? LIMIT 1");
    $cek->bind_param("ii",$fileId,$userId);
    $cek->execute();
    if (!$cek->get_result()->fetch_assoc()) {
        echo json_encode(["success"=>false,"message"=>"Not allowed"]);
        exit;
    }

    $cekFav = $conn->prepare("SELECT id FROM favorites WHERE user_id=? AND file_id=? LIMIT 1");
    $cekFav->bind_param("ii",$userId,$fileId);
    $cekFav->execute();
    $fav = $cekFav->get_result()->fetch_assoc();

    if ($fav) {
        $del = $conn->prepare("DELETE FROM favorites WHERE id=?");
        $del->bind_param("i",$fav['id']);
        $del->execute();
        echo json_encode(["success"=>true,"favorited"=>false]);
    } else {
        $ins = $conn->prepare("INSERT INTO favorites(user_id,file_id,created_at) VALUES(?,?,NOW())");
        $ins->bind_param("ii",$userId,$fileId);
        $ins->execute();
        echo json_encode(["success"=>true,"favorited"=>true]);
    }
    exit;
}

/* -----------------------------
   TOGGLE FOLDER FAVORITE
------------------------------*/
if ($folderId > 0) {

    $cek = $conn->prepare("SELECT id FROM folders WHERE id=? AND owner_id=? LIMIT 1");
    $cek->bind_param("ii",$folderId,$userId);
    $cek->execute();
    if (!$cek->get_result()->fetch_assoc()) {
        echo json_encode(["success"=>false,"message"=>"Not allowed"]);
        exit;
    }

    $cekFav = $conn->prepare("SELECT id FROM favorites WHERE user_id=? AND folder_id=? LIMIT 1");
    $cekFav->bind_param("ii",$userId,$folderId);
    $cekFav->execute();
    $fav = $cekFav->get_result()->fetch_assoc();

    if ($fav) {
        $del = $conn->prepare("DELETE FROM favorites WHERE id=?");
        $del->bind_param("i",$fav['id']);
        $del->execute();
        echo json_encode(["success"=>true,"favorited"=>false]);
    } else {
        $ins = $conn->prepare("INSERT INTO favorites(user_id,folder_id,created_at) VALUES(?,?,NOW())");
        $ins->bind_param("ii",$userId,$folderId);
        $ins->execute();
        echo json_encode(["success"=>true,"favorited"=>true]);
    }
    exit;
}

echo json_encode(["success"=>false,"message"=>"Invalid"]);
