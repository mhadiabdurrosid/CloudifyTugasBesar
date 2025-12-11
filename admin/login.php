<?php
session_start();

// Jika sudah login → redirect sesuai role
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === "admin") {
        header("Location: ../admin/");
    } else {
        header("Location: ../index.php");
    }
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cloudify – Login</title>

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
/* css tetap sama seperti punya kamu */
* {margin:0;padding:0;box-sizing:border-box}
body {
    font-family:'Poppins',sans-serif;
    background: linear-gradient(135deg, #4b91f1, #6ec6ff);
    height:100vh; display:flex; justify-content:center; align-items:center;
}
.login-wrapper {
    width:100%; max-width:420px;
    background:#fff;
    padding:2.8rem;
    border-radius:22px;
    box-shadow:0 15px 40px rgba(0,0,0,0.15);
    animation:fade .5s ease;
}
@keyframes fade {
    from {opacity:0; transform:translateY(20px)}
    to   {opacity:1; transform:translateY(0)}
}
.logo-area {text-align:center; margin-bottom:1rem}
.logo-area img {width:95px; filter:drop-shadow(0 6px 10px rgba(0,0,0,0.15))}
h2 {text-align:center;margin-bottom:1.5rem;font-size:1.8rem;font-weight:600;color:#2d2d2d}
label {font-weight:500;margin:7px 0;display:block}
input {width:100%;padding:1rem;border-radius:14px;font-size:1rem;border:1.5px solid #cfcfcf;background:#fafafa;margin-bottom:1rem;transition:.25s}
input:focus {border-color:#4b91f1;background:#fff;box-shadow:0 0 8px rgba(75,145,241,0.28);outline:none}
.btn {width:100%;padding:.9rem;border:none;border-radius:14px;font-size:1.1rem;font-weight:600;cursor:pointer;transition:.25s}
.btn-login {background:#4b91f1;color:white;box-shadow:0 4px 18px rgba(75,145,241,0.35)}
.btn-login:hover {background:#3478de;transform:translateY(-2px)}
.btn-secondary {background:#e5e5e5;color:#444}
.btn-secondary:hover {background:#d3d3d3}
</style>
</head>

<body>

<div class="login-wrapper">
    <div class="logo-area">
        <img src="../img/Logo Cloudify.png" alt="Cloudify Logo">
    </div>

    <h2>Masuk ke Cloudify</h2>

    <form id="loginForm">
        <label>Email</label>
        <input type="email" id="email" placeholder="Masukkan email..." required>

        <label>Password</label>
        <input type="password" id="password" placeholder="Masukkan password..." required>

        <button class="btn btn-login" type="submit">Masuk</button>
        <button class="btn btn-secondary" onclick="window.location.href='../index.php'" type="button">Batal</button>
    </form>
</div>

<script>
// === SISTEM LOGIN BARU ===
document.getElementById("loginForm").addEventListener("submit", async function(e){
    e.preventDefault();

    let email = document.getElementById("email").value.trim();
    let password = document.getElementById("password").value.trim();

    let response = await fetch("proses-login.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: new URLSearchParams({email, password})
    });

    let text = await response.text();
    console.log("Raw response:", text);

    let data;
    try { data = JSON.parse(text); }
    catch(err) {
        Swal.fire("Error", "Server mengirim data tidak valid.", "error");
        return;
    }

    // sukses
    if (data.success) {
        Swal.fire({
            icon: "success",
            title: "Login Berhasil!",
            text: "Selamat datang " + data.nama,
            timer: 1200,
            showConfirmButton: false
        }).then(() => {
            if (data.role === "admin") {
                window.location.href = "../admin/";
            } else {
                window.location.href = "../index.php";
            }
        });
    }
    else {
        Swal.fire("Login Gagal", data.message, "error");
    }
});
</script>

</body>
</html>
