<?php
// Tentukan base URL secara otomatis
$base_url = "http://" . $_SERVER['HTTP_HOST']; // akan otomatis menyesuaikan domain atau localhost
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Akses Ditolak</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height: 100vh;">
    <div class="text-center">
        <h1 class="display-4 text-danger">Akses Ditolak!</h1>
        <p class="lead">Anda tidak memiliki izin untuk mengakses halaman ini.</p>
        <a href="<?= $base_url ?>/index.php" class="btn btn-secondary">Kembali ke Beranda</a>
    </div>
</body>
</html>
