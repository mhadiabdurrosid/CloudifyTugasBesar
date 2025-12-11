<?php
class koneksi {
    private $host = "localhost";
    private $user = "root";
    private $password = "";
    private $dbname = "cloudify";

    public $conn;

    public function __construct() {
        $this->conn = new mysqli($this->host, $this->user, $this->password, $this->dbname);
        if ($this->conn->connect_error) {
            die('Koneksi gagal: ' . $this->conn->connect_error);
        }
        $this->conn->set_charset('utf8mb4');
    }

    public function getConnection() {
        return $this->conn;
    }
}

$koneksi = new koneksi();
?>
