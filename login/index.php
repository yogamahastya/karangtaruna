<?php
session_start();

// Redirect pengguna jika sudah login
if (isset($_SESSION['user_id'])) {
    header('Location: ../admin/');
    exit();
}

// =================================================================
// Include Konfigurasi & Koneksi Database
// =================================================================
require_once '../config.php'; // di sini $conn sudah tersedia dari config.php

$login_error = '';

// Memeriksa apakah form telah disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = trim($_POST['username']);
    $input_password = trim($_POST['password']);

    if (empty($input_username) || empty($input_password)) {
        $login_error = "Username dan password tidak boleh kosong.";
    } else {
        // Query hanya cek username, password diverifikasi di PHP
        $sql = "SELECT id, password, role FROM users WHERE username = ?";
        $stmt = $conn->prepare($sql);

        if ($stmt === false) {
            $login_error = "Error dalam menyiapkan query: " . $conn->error;
        } else {
            $stmt->bind_param("s", $input_username);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();

                // 🔒 Verifikasi password
                // Jika password sudah di-hash gunakan password_verify()
                // Jika masih plaintext, cek langsung
                if (password_verify($input_password, $user['password']) || $input_password === $user['password']) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_role'] = $user['role'];

                    header('Location: ../admin/');
                    exit();
                } else {
                    $login_error = "Password salah.";
                }
            } else {
                $login_error = "Username tidak ditemukan.";
            }
            $stmt->close();
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 400px;
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            background-color: #fff;
        }
        .login-card-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-card-header h2 {
            font-weight: 600;
            color: #0d6efd;
        }
        .form-control {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
        }
        .btn-login {
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-card-header">
        <h2><i class="fa-solid fa-lock me-2"></i>Dashboard Login</h2>
        <p class="text-muted">Masukkan username dan password Anda</p>
    </div>
    
    <?php if (!empty($login_error)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($login_error) ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                <input type="text" class="form-control" id="username" name="username" required>
            </div>
        </div>
        <div class="mb-4">
            <label for="password" class="form-label">Password</label>
            <div class="input-group">
                <span class="input-group-text"><i class="fa-solid fa-key"></i></span>
                <input type="password" class="form-control" id="password" name="password" required>
            </div>
        </div>
        <div class="d-grid">
            <button type="submit" class="btn btn-primary btn-login">Login</button>
        </div>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>