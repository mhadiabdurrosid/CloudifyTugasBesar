<?php
class Koneksi {
    private $conn;

    public function __construct() {

        // 🔍 DETEKSI KONEKSI LOKAL
        $isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', [
            'localhost',
            '127.0.0.1'
        ]);

        if ($isLocal) {
            // 💻 KONEKSI LOCAL
            $host = "localhost";
            $user = "root";
            $pass = "";
            $db   = "cloudify";
            $port = 3306;
        } else {
            // ☁️ KONEKSI PRODUKSI (Railway) - menggunakan DATABASE_URL
            $databaseUrl = getenv("DATABASE_URL");
            if ($databaseUrl) {
                $urlParts = parse_url($databaseUrl);
                $host = $urlParts['host'] ?? "localhost";
                $user = $urlParts['user'] ?? "root";
                $pass = $urlParts['pass'] ?? "";
                $db   = ltrim($urlParts['path'] ?? "/cloudify", '/');
                $port = $urlParts['port'] ?? 3306;
            } else {
                // Fallback ke env vars individual jika DATABASE_URL tidak ada
                $host = getenv("MYSQLHOST") ?: "localhost";
                $user = getenv("MYSQLUSER") ?: "root";
                $pass = getenv("MYSQLPASSWORD") ?: "";
                $db   = getenv("MYSQLDATABASE") ?: "cloudify";
                $port = getenv("MYSQLPORT") ?: 3306;
            }
        }

        // 🔗 Buat koneksi MySQL
        $this->conn = new mysqli($host, $user, $pass, $db, $port);

        if ($this->conn->connect_error) {
            die("Koneksi gagal: " . $this->conn->connect_error);
        }

        $this->conn->set_charset("utf8mb4");
    }

    public function getConnection() {
        return $this->conn;
    }
}

$koneksi = new Koneksi();
?>
