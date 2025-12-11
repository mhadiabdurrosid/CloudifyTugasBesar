<?php
session_start();

// Hapus semua session
session_unset();
session_destroy();

// Pastikan tidak ada cache halaman
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Redirect ke halaman login
header("Location: login.php");
exit;
?>
