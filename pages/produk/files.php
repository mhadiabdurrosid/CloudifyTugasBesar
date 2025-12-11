<?php
// model/Files.php
// Adapter model untuk tabel files — kompatibel dengan schema lama dan baru

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/Koneksi.php';

class Files {
    private $conn;
    private $cols = [];

    public function __construct(){
        $db = new Koneksi();
        $this->conn = $db->getConnection();
        $this->detectColumns();
    }

    // detect columns existing in 'files' table
    private function detectColumns(){
        $this->cols = [];
        if (!$this->conn) return;
        $sql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'files'";
        $res = $this->conn->query($sql);
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                $this->cols[] = $r['COLUMN_NAME'];
            }
        }
    }

    private function has($name){
        return in_array($name, $this->cols);
    }

    // Standardize a result row to our canonical keys
    private function normalizeRow($r){
        // possible legacy names mapping
        $id = $r['id'] ?? $r['id_file'] ?? null;
        $name = $r['name'] ?? $r['file_name'] ?? $r['filename'] ?? null;
        $path = $r['path'] ?? $r['file_path'] ?? $r['file_url'] ?? null;
        $size = isset($r['size']) ? (int)$r['size'] : (isset($r['file_size']) ? (int)$r['file_size'] : null);
        $mime = $r['mime'] ?? $r['file_type'] ?? null;
        $ext = $r['extension'] ?? pathinfo($name ?? '', PATHINFO_EXTENSION) ?? null;
        $owner = $r['owner_id'] ?? $r['uploaded_by'] ?? $r['user_id'] ?? null;
        $created = $r['created_at'] ?? $r['uploaded_at'] ?? $r['created'] ?? null;
        $is_fav = null;
        if (isset($r['is_favorit'])) $is_fav = (int)$r['is_favorit'] === 1;
        if (isset($r['is_favorite'])) $is_fav = (int)$r['is_favorite'] === 1;

        // build file_url if path exists and not absolute url
        $file_url = $path;
        if ($file_url && !preg_match('#^https?://#i', $file_url)) {
            // try to compute relative to BASE_URL if defined
            if (defined('BASE_URL')) {
                // ensure no double slash
                $file_url = rtrim(BASE_URL, '/') . '/' . ltrim($file_url, '/');
            } else {
                // fallback: leave as-is
            }
        }

        // determine is_image
        $is_image = false;
        if ($mime && stripos($mime, 'image/') === 0) $is_image = true;
        elseif (in_array(strtolower($ext), ['jpg','jpeg','png','gif','webp','avif'])) $is_image = true;

        return [
            'id' => $id,
            'name' => $name,
            'filename' => $name,
            'path' => $path,
            'file_url' => $file_url,
            'size' => $size,
            'mime' => $mime,
            'extension' => $ext,
            'owner_id' => $owner,
            'created_at' => $created,
            'is_favorite' => $is_fav,
            'is_image' => $is_image,
        ];
    }

    // Get all files for a user (optionally folder-aware)
    public function getAll($userId = null, $folderId = null){
        if (!$this->conn) return [];
        // Determine which column names to select from DB (use aliases)
        $select = [];
        // id
        if ($this->has('id')) $select[] = 'id';
        elseif ($this->has('id_file')) $select[] = 'id_file';
        else $select[] = 'id';

        // name variants
        if ($this->has('name')) $select[] = 'name';
        elseif ($this->has('file_name')) $select[] = 'file_name';
        elseif ($this->has('filename')) $select[] = 'filename';
        else $select[] = "'' AS name";

        // path
        if ($this->has('path')) $select[] = 'path';
        elseif ($this->has('file_path')) $select[] = 'file_path';
        elseif ($this->has('file_url')) $select[] = 'file_url';
        else $select[] = "'' AS path";

        // size
        if ($this->has('size')) $select[] = 'size';
        elseif ($this->has('file_size')) $select[] = 'file_size';
        else $select[] = "0 AS size";

        // mime
        if ($this->has('mime')) $select[] = 'mime';
        elseif ($this->has('file_type')) $select[] = 'file_type';
        else $select[] = "'' AS mime";

        // extension
        if ($this->has('extension')) $select[] = 'extension';
        else $select[] = "'' AS extension";

        // owner / uploaded_by
        if ($this->has('owner_id')) $select[] = 'owner_id';
        elseif ($this->has('uploaded_by')) $select[] = 'uploaded_by';
        elseif ($this->has('user_id')) $select[] = 'user_id';
        else $select[] = "NULL AS owner_id";

        // created_at / uploaded_at
        if ($this->has('created_at')) $select[] = 'created_at';
        elseif ($this->has('uploaded_at')) $select[] = 'uploaded_at';
        elseif ($this->has('created')) $select[] = 'created';
        else $select[] = "NULL AS created_at";

        // favorite flag
        if ($this->has('is_favorit')) $select[] = 'is_favorit';
        elseif ($this->has('is_favorite')) $select[] = 'is_favorite';
        else $select[] = "0 AS is_favorit";

        // is_deleted
        if ($this->has('is_deleted')) $select[] = 'is_deleted';
        else $select[] = "0 AS is_deleted";

        $sql = "SELECT " . implode(", ", $select) . " FROM files WHERE 1=1";

        // filter by user if possible
        $params = [];
        $types = '';
        if ($userId !== null) {
            // prefer owner_id or uploaded_by
            if ($this->has('owner_id')) {
                $sql .= " AND owner_id = ?";
                $params[] = $userId; $types .= 'i';
            } elseif ($this->has('uploaded_by')) {
                // uploaded_by may be username => accept both numeric id or username
                $sql .= " AND uploaded_by = ?";
                $params[] = $userId; $types .= 's';
            }
        }

        // filter folder if possible
        if ($folderId !== null && $this->has('folder_id')) {
            $sql .= " AND folder_id = ?";
            $params[] = $folderId; $types .= 'i';
        }

        // skip deleted if column exists
        if ($this->has('is_deleted')) {
            $sql .= " AND (is_deleted = 0 OR is_deleted IS NULL)";
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];

        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()){
            $out[] = $this->normalizeRow($row);
        }
        $stmt->close();
        return $out;
    }

    // get single file by id
    public function getById($id){
        if (!$this->conn) return null;
        // try both id and id_file
        $sql = null;
        if ($this->has('id')) $sql = "SELECT * FROM files WHERE id = ? LIMIT 1";
        elseif ($this->has('id_file')) $sql = "SELECT * FROM files WHERE id_file = ? LIMIT 1";
        else $sql = "SELECT * FROM files WHERE id = ? LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return null;
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $r ? $this->normalizeRow($r) : null;
    }

    // search (by name) for a given user
    public function searchByName($q, $user = null){
        if (!$this->conn) return [];
        $like = '%' . $q . '%';

        // determine column for name
        $nameCol = $this->has('name') ? 'name' : ($this->has('file_name') ? 'file_name' : ($this->has('filename') ? 'filename' : null));
        if (!$nameCol) return [];

        $sql = "SELECT * FROM files WHERE {$nameCol} LIKE ? ";
        $params = [$like];
        $types = 's';

        if ($user !== null) {
            if ($this->has('owner_id')) {
                $sql .= " AND owner_id = ? ";
                $params[] = $user; $types .= 'i';
            } elseif ($this->has('uploaded_by')) {
                $sql .= " AND uploaded_by = ? ";
                $params[] = $user; $types .= 's';
            }
        }

        if ($this->has('is_deleted')) $sql .= " AND (is_deleted = 0 OR is_deleted IS NULL)";

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->conn->prepare($sql);
        if (!$stmt) return [];
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $out = [];
        while ($row = $res->fetch_assoc()) $out[] = $this->normalizeRow($row);
        $stmt->close();
        return $out;
    }

    // toggle favorite (example: if you store favorit in separate table you should adapt)
    public function toggleFavorite($fileId, $userId){
        // If table 'favorites' exists, use it; else try toggle field 'is_favorit' in files
        if ($this->hasTable('favorites')) {
            // check exists
            $check = $this->conn->prepare("SELECT id FROM favorites WHERE user_id = ? AND file_id = ? LIMIT 1");
            $check->bind_param('ii', $userId, $fileId);
            $check->execute();
            $res = $check->get_result();
            if ($res && $res->fetch_assoc()) {
                // delete
                $del = $this->conn->prepare("DELETE FROM favorites WHERE user_id = ? AND file_id = ? LIMIT 1");
                $del->bind_param('ii', $userId, $fileId);
                $ok = $del->execute();
                $del->close();
                $check->close();
                return $ok ? false : null; // now unfavorited
            } else {
                $ins = $this->conn->prepare("INSERT INTO favorites (user_id, file_id) VALUES (?,?)");
                $ins->bind_param('ii', $userId, $fileId);
                $ok = $ins->execute();
                $ins->close();
                $check->close();
                return $ok ? true : null;
            }
        } else if ($this->has('is_favorit')) {
            // flip flag in files
            $cur = $this->conn->prepare("SELECT is_favorit FROM files WHERE id = ? LIMIT 1");
            $cur->bind_param('i', $fileId);
            $cur->execute();
            $r = $cur->get_result()->fetch_assoc();
            $cur->close();
            $now = (isset($r['is_favorit']) && $r['is_favorit']) ? 0 : 1;
            $upd = $this->conn->prepare("UPDATE files SET is_favorit = ? WHERE id = ?");
            $upd->bind_param('ii', $now, $fileId);
            $ok = $upd->execute();
            $upd->close();
            return $ok ? (bool)$now : null;
        }
        return null;
    }

    private function hasTable($name){
        $res = $this->conn->query("SHOW TABLES LIKE '".$this->conn->real_escape_string($name)."'");
        return $res && $res->num_rows > 0;
    }
}
