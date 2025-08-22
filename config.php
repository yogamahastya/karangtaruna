<?php
// File: config.php
// Konfigurasi Nama Organisasi
define('ORGANIZATION_NAME', 'Karang Taruna Apa Aja Lah');
// Ganti dengan jumlah iuran bulanan yang sebenarnya
define('DUES_MONTHLY_FEE', 10000); 
// Konfigurasi Koneksi Database
define('DB_SERVERNAME', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'karangtaruna_db');
// Buat koneksi mysqli
$conn = new mysqli(DB_SERVERNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}
// **START MODIFIKASI: Tambahkan definisi jabatan**
define('JABATAN_OPTIONS', [
    'Anggota',
    'Ketua',
    'Wakil Ketua',
    'Bendahara',
    'Sekretaris',
    'Humas'
]);
// **END MODIFIKASI**
?>