<?php
session_start();
require_once __DIR__ . '/../model/Koneksi.php';

if (!isset($_SESSION['user_id'])) exit("Akses ditolak");

$userId = (int)$_SESSION['user_id'];

$db  = new koneksi();
$conn = $db->getConnection();

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

/* -------------------------
   HAPUS FAVORIT (BY fav_id)
------------------------- */
if (isset($_GET['hapus'])) {
    $favId = (int)$_GET['hapus'];

    $del = $conn->prepare("DELETE FROM favorites WHERE id=? AND user_id=?");
    $del->bind_param("ii", $favId, $userId);
    $del->execute();

    if ($isAjax) {
        echo "OK";
        exit;
    }

    header("Location: ../index.php?show=favorit");
    exit;
}

/* -------------------------
   TOGGLE FAVORIT FILE
   (dipanggil dari CloudSaya & AJAX grid)
------------------------- */
if (isset($_GET['id'])) {
    $fileId = (int)$_GET['id'];

    // pastikan file milik user
    $cek = $conn->prepare("SELECT id FROM files WHERE id=? AND owner_id=?");
    $cek->bind_param("ii", $fileId, $userId);
    $cek->execute();
    if (!$cek->get_result()->fetch_assoc()) {
        if ($isAjax) {
            echo "ERROR";
            exit;
        }
        exit("File tidak ditemukan");
    }

    // cek apakah sudah favorit
    $cekFav = $conn->prepare("SELECT id FROM favorites WHERE user_id=? AND file_id=?");
    $cekFav->bind_param("ii", $userId, $fileId);
    $cekFav->execute();
    $ex = $cekFav->get_result()->fetch_assoc();

    if ($ex) {
        $del = $conn->prepare("DELETE FROM favorites WHERE id=?");
        $del->bind_param("i", $ex['id']);
        $del->execute();
        if ($isAjax) {
            echo "REMOVED";
            exit;
        }
    } else {
        $ins = $conn->prepare("INSERT INTO favorites(user_id,file_id,created_at) VALUES(?,?,NOW())");
        $ins->bind_param("ii", $userId, $fileId);
        $ins->execute();
        if ($isAjax) {
            echo "ADDED";
            exit;
        }
    }

    // NON-AJAX: balik ke cloud (atau referer)
    $target = "../index.php?show=cloud";
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $target = $_SERVER['HTTP_REFERER'];
    }
    header("Location: ".$target);
    exit;
}

/* -------------------------
   FAVORIT FOLDER (klik di CloudSaya)
------------------------- */
if (isset($_GET['folder'])) {
    $folderId = (int)$_GET['folder'];

    $cek = $conn->prepare("SELECT id FROM folders WHERE id=? AND owner_id=?");
    $cek->bind_param("ii", $folderId, $userId);
    $cek->execute();
    if (!$cek->get_result()->fetch_assoc()) {
        exit("Folder tidak ditemukan");
    }

    // Cek apakah sudah favorit
    $cekFav = $conn->prepare("SELECT id FROM favorites WHERE user_id=? AND folder_id=?");
    $cekFav->bind_param("ii", $userId, $folderId);
    $cekFav->execute();
    $ex = $cekFav->get_result()->fetch_assoc();

    if ($ex) {
        $del = $conn->prepare("DELETE FROM favorites WHERE id=?");
        $del->bind_param("i", $ex['id']);
        $del->execute();
    } else {
        $ins = $conn->prepare("INSERT INTO favorites(user_id,folder_id,created_at) VALUES(?,?,NOW())");
        $ins->bind_param("ii", $userId, $folderId);
        $ins->execute();
    }

    // Folder favorit dipakai via klik biasa, cukup redirect
    header("Location: ../index.php?show=cloud");
    exit;
}

echo "INVALID";
