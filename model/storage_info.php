<?php
session_start();
header('Content-Type: application/json');

require_once(__DIR__ . '/Koneksi.php');
$conn = (new Koneksi())->getConnection();

$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode(['success'=>false,'message'=>'User tidak ditemukan']);
    exit;
}

function fmt($b){
    if ($b >= 1073741824) return round($b/1073741824,1) . " GB";
    if ($b >= 1048576) return round($b/1048576,1) . " MB";
    if ($b >= 1024) return round($b/1024,1) . " KB";
    return $b . " B";
}

// 1. SUM FILES
$stmt = $conn->prepare("SELECT COALESCE(SUM(size),0) AS total FROM files WHERE owner_id = ? AND (is_deleted = 0 OR is_deleted IS NULL)");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$used = (int)$stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// 2. QUOTA
$stmt2 = $conn->prepare("SELECT quota_bytes FROM storage_quotas WHERE user_id = ? LIMIT 1");
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$res = $stmt2->get_result()->fetch_assoc();
$quota = $res['quota_bytes'] ?? 32212254720; // default 30GB
$stmt2->close();

$pct = $quota > 0 ? ($used / $quota) * 100 : 0;

echo json_encode([
    "success" => true,
    "used_human" => fmt($used),
    "quota_human" => fmt($quota),
    "pct" => $pct
]);
