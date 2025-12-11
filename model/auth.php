<?php
require_once __DIR__ . '/Koneksi.php';

class auth {

    private $conn;

    public function __construct() {
        $db = new koneksi();
        $this->conn = $db->getConnection();
    }

    public function login($emailOrUsername, $password) {

        $sql = "SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1";
        $stmt = $this->conn->prepare($sql);

        if (!$stmt) {
            return false;
        }

        $stmt->bind_param("ss", $emailOrUsername, $emailOrUsername);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();

        if (!$user) return false;

        if (!password_verify($password, $user['password'])) {
            return false;
        }

        return $user;
    }
}
?>
