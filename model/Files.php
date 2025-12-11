<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once(__DIR__ . '/Koneksi.php');

class Files {
    private $conn;
    private $baseUrl;

    public function __construct($dbConnection = null) {
        global $koneksi;

        $this->conn = $dbConnection 
            ? $dbConnection 
            : $koneksi->getConnection();

        $this->baseUrl = defined('BASE_URL')
            ? BASE_URL
            : 'http://localhost/Cloudify/Cloudify/';
    }

    /* ============================================================
       FIX 1: Selalu ambil file TERBARU + tidak gunakan LIMIT
       FIX 2: Selalu filter 100% berdasarkan user login
    ============================================================ */
    public function getAll($search = "") {

        if (!isset($_SESSION['user_id'])) return [];
        $userId = (int)$_SESSION['user_id'];

        $rows = [];
        $imgExts = ["jpg","jpeg","png","gif","webp","bmp","svg","avif"];

        $sql = "
            SELECT 
                id, name, extension, path, size, mime, owner_id,
                COALESCE(is_favorite,0) AS is_favorite,
                created_at
            FROM files
            WHERE owner_id = ? AND is_deleted = 0
        ";

        $params = [$userId];
        $types = "i";

        if (!empty($search)) {
            $sql .= " AND name LIKE ?";
            $params[] = "%$search%";
            $types .= "s";
        }

        // FIX 3: ORDER BY timestamp real supaya stabil
        $sql .= " ORDER BY created_at DESC, id DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        while ($r = $res->fetch_assoc()) {

            // FIX 4: Path harus SELALU benar
            $path = str_replace("\\", "/", $r["path"]);
            if (!str_starts_with($path, "uploads")) {
                $path = "uploads/" . ltrim($path, "/");
            }

            $rows[] = [
                "id" => $r["id"],
                "name" => $r["name"],
                "extension" => $r["extension"],
                "is_image" => in_array(strtolower($r["extension"]), $imgExts),
                "file_url" => $this->baseUrl . $path,
                "size" => $this->formatSize($r["size"]),
                "raw_size" => $r["size"],
                "owner_id" => $r["owner_id"],
                "is_favorite" => (int)$r["is_favorite"],
                "created_at" => $r["created_at"]
            ];
        }

        return $rows;
    }

    /* ============================================================
       Ambil file milik user saja (bukan user lain)
    ============================================================ */
    public function getById($id) {
        if (!isset($_SESSION['user_id'])) return false;

        $userId = (int)$_SESSION['user_id'];

        $stmt = $this->conn->prepare("
            SELECT * FROM files 
            WHERE id=? AND owner_id=? AND is_deleted=0 LIMIT 1
        ");
        $stmt->bind_param("ii", $id, $userId);
        $stmt->execute();

        return $stmt->get_result()->fetch_assoc();
    }

    /* ============================================================ */
    public function toggleFavorite($id) {

        if (!isset($_SESSION['user_id'])) return false;
        $userId = (int)$_SESSION['user_id'];

        $check = $this->conn->prepare("
            SELECT id FROM files WHERE id=? AND owner_id=? LIMIT 1
        ");
        $check->bind_param("ii", $id, $userId);
        $check->execute();

        if (!$check->get_result()->fetch_assoc()) return false;

        $stmt = $this->conn->prepare("
            UPDATE files SET is_favorite = 1 - is_favorite WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /* ============================================================ */
    public function softDelete($id) {

        if (!isset($_SESSION['user_id'])) return false;
        $userId = (int)$_SESSION['user_id'];

        $check = $this->conn->prepare("
            SELECT id FROM files WHERE id=? AND owner_id=? LIMIT 1
        ");
        $check->bind_param("ii", $id, $userId);
        $check->execute();

        if (!$check->get_result()->fetch_assoc()) return false;

        $stmt = $this->conn->prepare("
            UPDATE files 
            SET is_deleted = 1, deleted_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }

    /* ============================================================ */
    private function formatSize($bytes) {
        if ($bytes >= 1073741824) return round($bytes/1073741824, 2)." GB";
        if ($bytes >= 1048576) return round($bytes/1048576, 2)." MB";
        if ($bytes >= 1024) return round($bytes/1024, 2)." KB";
        return $bytes." B";
    }
}
?>
