<?php
// Memuat file konfigurasi yang berisi detail database dan nama organisasi
require_once 'config.php';

// Penting: Baris ini memberitahu browser untuk menggunakan UTF-8
header('Content-Type: text/html; charset=UTF-8');

// Memulai sesi (tetap diperlukan untuk pesan notifikasi, meskipun user_id tidak digunakan untuk absen)
session_start();

// =================================================================
// Konfigurasi dan Koneksi Database
// =================================================================
$servername = DB_SERVERNAME;
$username = DB_USERNAME;
$password = DB_PASSWORD;
$dbname = DB_NAME;

// Membuat koneksi
$conn = new mysqli($servername, $username, $password, $dbname);
// Memeriksa koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// =================================================================
// Fungsi-Fungsi Pembantu
// =================================================================
/**
 * Mengubah angka menjadi format Rupiah.
 * @param int|float $amount Jumlah uang.
 * @return string Mengembalikan string format Rupiah.
 */
function formatRupiah($amount) {
    return 'Rp' . number_format($amount, 0, ',', '.');
}

/**
 * Menghitung jarak antara dua titik GPS menggunakan Rumus Haversine.
 * @param float $lat1 Latitude titik 1.
 * @param float $lon1 Longitude titik 1.
 * @param float $lat2 Latitude titik 2.
 * @param float $lon2 Longitude titik 2.
 * @return float Jarak dalam meter.
 */
function haversineGreatCircleDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Jari-jari Bumi dalam meter
    
    // Mengubah koordinat dari derajat ke radian
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);

    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;
    $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
        cos($latFrom) * cos($latTo) * pow(sin(abs($lonDelta) / 2), 2)));
    return $angle * $earthRadius;
}

/**
 * Mengambil data dengan paginasi dan pencarian.
 *
 * @param mysqli $conn Koneksi database.
 * @param string $tableName Nama tabel.
 * @param int $start Index awal data.
 * @param int $limit Jumlah data per halaman.
 * @param string|null $searchTerm Kata kunci pencarian.
 * @param int|null $filterYear Tahun filter (untuk keuangan/iuran).
 * @return array Data yang sudah difilter dan dipaginasi.
 */
function fetchDataWithPagination($conn, $tableName, $start, $limit, $searchTerm = null, $filterYear = null) {
    $data = [];
    $sql = "";
    $conditions = [];
    $params = [];
    $types = '';
    $orderBy = '';

    // Logika JOIN dan ORDER BY khusus untuk tabel tertentu
    if ($tableName === 'keuangan') {
        $sql = "SELECT k.*, a.nama_lengkap AS dicatat_oleh_nama FROM keuangan k LEFT JOIN anggota a ON k.dicatat_oleh_id = a.id";
        $orderBy = 'k.tanggal_transaksi DESC';
    } elseif ($tableName === 'iuran') {
        $sql = "SELECT a.id AS anggota_id, a.nama_lengkap, a.bergabung_sejak, COALESCE(SUM(i.jumlah_bayar), 0) AS total_bayar FROM anggota AS a LEFT JOIN iuran AS i ON a.id = i.anggota_id";
        $orderBy = "FIELD(a.jabatan, 'Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Humas', 'Anggota'), a.nama_lengkap ASC";
    } elseif ($tableName === 'anggota') {
        $sql = "SELECT * FROM `anggota`";
        $orderBy = "FIELD(jabatan, 'Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Humas', 'Anggota'), nama_lengkap ASC";
    } elseif ($tableName === 'absensi') {
        // PERBAIKAN: Gunakan JOIN untuk absensi agar dapat mencari berdasarkan nama anggota
        $sql = "SELECT a.*, ab.tanggal_absen, ab.id as absensi_id FROM anggota a LEFT JOIN absensi ab ON a.id = ab.anggota_id AND DATE(ab.tanggal_absen) = CURDATE()";
        $orderBy = "FIELD(a.jabatan, 'Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Humas', 'Anggota'), a.nama_lengkap ASC";
    } elseif ($tableName === 'kegiatan') {
        $sql = "SELECT * FROM `kegiatan`";
        $orderBy = 'tanggal_mulai DESC';
    }

    // Kondisi filter tahun
    if ($filterYear) {
        if ($tableName === 'keuangan') {
            $conditions[] = "YEAR(k.tanggal_transaksi) = ?";
            $params[] = $filterYear;
            $types .= 'i';
        } elseif ($tableName === 'iuran') {
            $sql = "SELECT a.id AS anggota_id, a.nama_lengkap, a.bergabung_sejak, COALESCE(SUM(i.jumlah_bayar), 0) AS total_bayar FROM anggota AS a LEFT JOIN iuran AS i ON a.id = i.anggota_id AND YEAR(i.tanggal_bayar) = ?";
            $params[] = $filterYear;
            $types .= 'i';
        }
    }

    // Kondisi pencarian
    if ($searchTerm) {
        $searchTermLike = '%' . $searchTerm . '%';
        if ($tableName === 'anggota') {
             // PERBAIKAN: Hapus alias 'a' karena query utama tidak menggunakannya
            $conditions[] = "(nama_lengkap LIKE ? OR jabatan LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'ss';
        } elseif ($tableName === 'absensi') {
             // PERBAIKAN: Pertahankan alias 'a' karena query utama menggunakannya (JOIN)
            $conditions[] = "(a.nama_lengkap LIKE ? OR a.jabatan LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'ss';
        } elseif ($tableName === 'kegiatan') {
            $conditions[] = "(nama_kegiatan LIKE ? OR deskripsi LIKE ? OR lokasi LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'sss';
        } elseif ($tableName === 'keuangan') {
            $conditions[] = "(k.jenis_transaksi LIKE ? OR k.deskripsi LIKE ? OR a.nama_lengkap LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'sss';
        } elseif ($tableName === 'iuran') {
            $conditions[] = "(a.nama_lengkap LIKE ?)";
            $params[] = $searchTermLike;
            $types .= 's';
        }
    }

    if (!empty($conditions)) {
        if ($tableName === 'iuran' && strpos($sql, 'WHERE') === false && strpos($sql, 'AND YEAR(i.tanggal_bayar)') !== false) {
            $sql .= " AND " . implode(" AND ", $conditions);
        } elseif (strpos($sql, 'WHERE') === false) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        } else {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
    }
    
    if ($tableName === 'iuran') {
        $sql .= " GROUP BY a.id, a.nama_lengkap, a.bergabung_sejak";
    }

    $sql .= " ORDER BY $orderBy LIMIT ? OFFSET ?";
    
    $params[] = $limit;
    $params[] = $start;
    $types .= 'ii';

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
        $stmt->close();
    }
    return $data;
}

/**
 * Mengambil rekapitulasi iuran per anggota secara rinci, dengan filter tahun.
 * @param mysqli $conn Objek koneksi database.
 * @param int $anggotaId ID anggota.
 * @param int|null $year Tahun yang akan difilter. Jika null, ambil semua data.
 * @param int $monthlyFee Iuran bulanan yang diharapkan.
 * @return array|null Mengembalikan array data rekapitulasi atau null jika tidak ditemukan.
 */
function fetchMemberDuesBreakdownWithYear($conn, $anggotaId, $year = null, $monthlyFee = 10000) {
    $memberData = null;
    $duesData = [];
    $sqlMember = "SELECT nama_lengkap, bergabung_sejak FROM anggota WHERE id = ?";
    $stmt = $conn->prepare($sqlMember);
    $stmt->bind_param("i", $anggotaId);
    $stmt->execute();
    $resultMember = $stmt->get_result();
    if ($resultMember->num_rows > 0) {
        $memberData = $resultMember->fetch_assoc();
    } else {
        return null;
    }
    $stmt->close();
    
    $sqlDues = "SELECT jumlah_bayar, tanggal_bayar FROM iuran WHERE anggota_id = ?";
    $params = [$anggotaId];
    $types = "i";
    
    if ($year !== null && is_numeric($year)) {
        $sqlDues .= " AND YEAR(tanggal_bayar) = ?";
        $params[] = $year;
        $types .= "i";
    }
    $sqlDues .= " ORDER BY tanggal_bayar ASC";

    $stmt = $conn->prepare($sqlDues);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $resultDues = $stmt->get_result();
    $payments = [];
    while ($row = $resultDues->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();

    $joinDate = new DateTime($memberData['bergabung_sejak']);
    $today = new DateTime();
    $startYear = ($year !== null) ? $year : intval($joinDate->format('Y'));
    $endYear = ($year !== null) ? $year : intval($today->format('Y'));
    $currentMonth = new DateTime("{$startYear}-01-01");
    if ($currentMonth < $joinDate) {
        $currentMonth = $joinDate;
    }

    $endOfMonthLoop = ($year !== null) ? new DateTime("{$year}-12-31") : $today;
    
    $totalPaid = 0;
    $totalExpected = 0;
    while ($currentMonth <= $endOfMonthLoop) {
        $month = $currentMonth->format('F Y');
        $monthKey = $currentMonth->format('Y-m');
        $paymentForThisMonth = 0;
        $notes = '';

        foreach ($payments as $payment) {
            $paymentDate = new DateTime($payment['tanggal_bayar']);
            if ($paymentDate->format('Y-m') === $monthKey) {
                $paymentForThisMonth += $payment['jumlah_bayar'];
            }
        }

        $status = 'Belum Bayar';
        if ($paymentForThisMonth >= $monthlyFee) {
            $status = 'Lunas';
        } elseif ($paymentForThisMonth > 0) {
            $status = 'Kurang';
            $kekurangan = $monthlyFee - $paymentForThisMonth;
            $notes = 'Kurang ' . formatRupiah($kekurangan);
        }

        $totalPaid += $paymentForThisMonth;
        $totalExpected += $monthlyFee;
        $duesData[] = [
            'month' => $month,
            'paid' => $paymentForThisMonth,
            'status' => $status,
            'notes' => $notes
        ];
        $currentMonth->modify('+1 month');
    }

    $kekurangan = $totalExpected - $totalPaid;
    return [
        'member' => $memberData,
        'breakdown' => $duesData,
        'summary' => [
            'total_paid' => $totalPaid,
            'total_expected' => $totalExpected,
            'shortfall' => $kekurangan
        ]
    ];
}

/**
 * Fungsi untuk menentukan status pembayaran dan kelas badge.
 * @param int $totalPaid Jumlah yang telah dibayar.
 * @param int $totalExpected Jumlah yang seharusnya dibayar.
 * @return array Mengembalikan array berisi string status dan kelas CSS.
 */
function getPaymentStatus($totalPaid, $totalExpected) {
    if ($totalPaid >= $totalExpected) {
        return ['status' => 'Lunas', 'class' => 'bg-success'];
    } else {
        return ['status' => 'Kurang', 'class' => 'bg-danger'];
    }
}

/**
 * Menghitung total baris untuk paginasi.
 * @param mysqli $conn Koneksi database.
 * @param string $tableName Nama tabel.
 * @param string|null $searchTerm Kata kunci pencarian.
 * @param int|null $filterYear Tahun filter.
 * @return int Total baris.
 */
function countRowsWithFilter($conn, $tableName, $searchTerm = null, $filterYear = null) {
    $sql = "";
    $conditions = [];
    $params = [];
    $types = '';

    if ($tableName === 'keuangan') {
        $sql = "SELECT COUNT(*) AS total FROM keuangan k LEFT JOIN anggota a ON k.dicatat_oleh_id = a.id";
    } elseif ($tableName === 'iuran' || $tableName === 'absensi') {
        $sql = "SELECT COUNT(*) AS total FROM anggota";
    } else {
        $sql = "SELECT COUNT(*) AS total FROM `$tableName`";
    }

    if ($filterYear) {
        if ($tableName === 'keuangan') {
            $conditions[] = "YEAR(k.tanggal_transaksi) = ?";
            $params[] = $filterYear;
            $types .= 'i';
        }
    }
    if ($searchTerm) {
        $searchTermLike = '%' . $searchTerm . '%';
        if ($tableName === 'anggota' || $tableName === 'absensi') {
             // PERBAIKAN: Hapus alias 'a' karena query utama tidak menggunakannya
            $conditions[] = "(nama_lengkap LIKE ? OR jabatan LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'ss';
        } elseif ($tableName === 'kegiatan') {
            $conditions[] = "(nama_kegiatan LIKE ? OR deskripsi LIKE ? OR lokasi LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'sss';
        } elseif ($tableName === 'keuangan') {
            $conditions[] = "(k.jenis_transaksi LIKE ? OR k.deskripsi LIKE ? OR a.nama_lengkap LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'sss';
        } elseif ($tableName === 'iuran') {
            $sql = "SELECT COUNT(*) AS total FROM anggota a WHERE nama_lengkap LIKE ?";
            $params[] = $searchTermLike;
            $types .= 's';
        }
    }
    if (!empty($conditions)) {
        if (strpos($sql, 'WHERE') === false) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        } else {
            $sql .= " AND " . implode(" AND ", $conditions);
        }
    }
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['total'];
    }
    return 0;
}

// =================================================================
// Logika Paginasi dan Pengambilan Data Utama
// =================================================================
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'anggota';
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$limit = 10;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$start = ($page - 1) * $limit;
$anggota = [];
$kegiatan = [];
$keuangan = [];
$iuran = [];
$total_rows = countRowsWithFilter($conn, $active_tab, $searchTerm, $selectedYear);
$total_pages = ceil($total_rows / $limit);
if ($active_tab == 'anggota') {
    $anggota = fetchDataWithPagination($conn, 'anggota', $start, $limit, $searchTerm);
    $total_anggota = $total_rows;
} elseif ($active_tab == 'absensi') {
    $anggota = fetchDataWithPagination($conn, 'absensi', $start, $limit, $searchTerm);
} elseif ($active_tab == 'kegiatan') {
    $kegiatan = fetchDataWithPagination($conn, 'kegiatan', $start, $limit, $searchTerm);
    $total_kegiatan = $total_rows;
} elseif ($active_tab == 'keuangan') {
    $keuangan = fetchDataWithPagination($conn, 'keuangan', $start, $limit, $searchTerm, $selectedYear);
} elseif ($active_tab == 'iuran') {
    $iuran = fetchDataWithPagination($conn, 'iuran', $start, $limit, $searchTerm, $selectedYear);
}

// =================================================================
// Logika untuk memproses absensi
// =================================================================
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['absen_submit'])) {
    $anggotaId = isset($_POST['anggota_id']) ? intval($_POST['anggota_id']) : 0;
    $userLat = isset($_POST['latitude']) ? floatval($_POST['latitude']) : 0;
    $userLon = isset($_POST['longitude']) ? floatval($_POST['longitude']) : 0;

    // --- Ambil IP Address Klien ---
    $clientIp = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $clientIp = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    }

    // --- Cooldown (Rate Limiting) ---
    $cooldownSeconds = 43200;
    $canProceed = true;
    
    $stmt = $conn->prepare("SELECT last_attempt_time FROM ip_attendance_cooldown WHERE ip_address = ?");
    $stmt->bind_param("s", $clientIp);
    $stmt->execute();
    $result = $stmt->get_result();
    $lastAttempt = $result->fetch_assoc();
    $stmt->close();

    if ($lastAttempt) {
        $lastAttemptTime = new DateTime($lastAttempt['last_attempt_time']);
        $currentTime = new DateTime();
        $interval = $currentTime->getTimestamp() - $lastAttemptTime->getTimestamp();

        if ($interval < $cooldownSeconds) {
            $message = "Anda sudah absen. Harap tunggu " . ($cooldownSeconds - $interval) . " detik sebelum mencoba lagi.";
            $messageType = "danger";
            $canProceed = false;
        }
    }

    // --- Ambil data lokasi absensi dari database ---
    $stmt = $conn->prepare("SELECT latitude, longitude, toleransi_jarak FROM lokasi_absensi WHERE id = 1 LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $lokasiDb = $result->fetch_assoc();
    $stmt->close();

    // Gunakan data dari database, jika tidak ada, gunakan nilai default sebagai fallback
    $jarakToleransi = $lokasiDb['toleransi_jarak'] ?? 50; 
    $lokasiPerkumpulan = [
        'latitude' => $lokasiDb['latitude'] ?? -7.527444,
        'longitude' => $lokasiDb['longitude'] ?? 110.628819
    ];

    if ($canProceed) {
        if ($anggotaId === 0 || empty($userLat) || empty($userLon)) {
            $message = "Gagal absen. Data absen tidak lengkap (ID anggota, lokasi tidak valid).";
            $messageType = "danger";
        } else {
            // Cek apakah anggota sudah absen hari ini
            $stmt = $conn->prepare("SELECT COUNT(*) FROM absensi WHERE anggota_id = ? AND DATE(tanggal_absen) = CURDATE()");
            $stmt->bind_param("i", $anggotaId);
            $stmt->execute();
            $stmt->bind_result($absenCount);
            $stmt->fetch();
            $stmt->close();

            if ($absenCount > 0) {
                $message = "Anda sudah melakukan absensi untuk hari ini.";
                $messageType = "danger";
                // Update cooldown record karena sudah mencoba absen
                $stmt = $conn->prepare("INSERT INTO ip_attendance_cooldown (ip_address, last_attempt_time) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_attempt_time = NOW()");
                $stmt->bind_param("s", $clientIp);
                $stmt->execute();
                $stmt->close();
            } else {
                // Hitung jarak
                $distance = haversineGreatCircleDistance(
                    $lokasiPerkumpulan['latitude'],
                    $lokasiPerkumpulan['longitude'],
                    $userLat,
                    $userLon
                );

                // Gunakan flag boolean untuk kejelasan
                $isWithinTolerance = ($distance <= $jarakToleransi);

                if ($isWithinTolerance) {
                    // Simpan absensi ke database
                    $stmt = $conn->prepare("INSERT INTO absensi (anggota_id, tanggal_absen, latitude, longitude) VALUES (?, NOW(), ?, ?)");
                    $stmt->bind_param("idd", $anggotaId, $userLat, $userLon);
                    if ($stmt->execute()) {
                        $message = "Absensi berhasil! Selamat datang di perkumpulan.";
                        $messageType = "success";
                    } else {
                        $message = "Terjadi kesalahan saat menyimpan data: " . $stmt->error;
                        $messageType = "danger";
                    }
                    $stmt->close();

                    // Update cooldown record hanya jika absensi berhasil disimpan
                    $stmt = $conn->prepare("INSERT INTO ip_attendance_cooldown (ip_address, last_attempt_time) VALUES (?, NOW()) ON DUPLICATE KEY UPDATE last_attempt_time = NOW()");
                    $stmt->bind_param("s", $clientIp);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $message = "Gagal absen. Anda berada terlalu jauh dari lokasi perkumpulan. Jarak Anda: " . round($distance, 2) . " meter.";
                    $messageType = "danger";
                    // Tidak ada update cooldown di sini
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ORGANIZATION_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/styleindex.css"> 
</head>
<body>

<div class="container py-5">
    <header class="hero-section">
    <h1 class="display-4"><i class="fa-solid fa-people-group me-3"></i><?= ORGANIZATION_NAME ?></h1>
    <p class="fs-5 mt-3">Satu visi, satu aksi, untuk kemajuan bersama.</p>
</header>
    <div class="mb-5">
        <ul class="nav nav-pills nav-justified nav-pills-custom" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'anggota') ? 'active' : '' ?>" href="?tab=anggota&year=<?= $selectedYear ?>">
                    <i class="fa-solid fa-users icon me-2"></i> Anggota
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 
'absensi') ? 'active' : '' ?>" href="?tab=absensi">
                    <i class="fa-solid fa-user-check me-2"></i> Absensi
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'kegiatan') ?
'active' : '' ?>" href="?tab=kegiatan&year=<?= $selectedYear ?>">
                    <i class="fa-solid fa-calendar-alt icon me-2"></i> Kegiatan
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'keuangan') ?
'active' : '' ?>" href="?tab=keuangan&year=<?= $selectedYear ?>">
                    <i class="fa-solid fa-wallet icon me-2"></i> Keuangan
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'iuran') ?
'active' : '' ?>" href="?tab=iuran&year=<?= $selectedYear ?>">
                    <i class="fa-solid fa-receipt icon me-2"></i> Iuran
                </a>
            </li>
        </ul>
    </div>

    <div class="content-card">
        <?php if ($active_tab == 'anggota'): ?>
            <h2 class="mb-4 text-primary"><i class="fa-solid fa-user-group me-2"></i>Data Anggota</h2>
            <div class="row mb-3 align-items-center gy-2">
                <div class="col-12 col-md-4">
                    <p class="fs-5 mb-0">Total Anggota: <span class="badge bg-primary"><?= $total_anggota ?></span></p>
                </div>
                <div class="col-12 col-md-8 text-md-end">
                    <form action="" method="GET" class="d-flex justify-content-start justify-content-md-end">
                        <input type="hidden" name="tab" value="anggota">
                        <div class="input-group search-input-desktop">
                            <input type="text" class="form-control" placeholder="Cari anggota..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="?tab=anggota" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <style>
                /* CSS Kustom untuk mengontrol lebar di desktop */
                @media (min-width: 768px) {
                    .search-input-desktop {
                        max-width: 300px;
                    }
                }
            </style>
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="anggotaTable">
                    <thead>
                        <tr>
                            <th scope="col">Nama Lengkap</th>
                            <th scope="col">Jabatan</th>
                            <th scope="col">Bergabung Sejak</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($anggota) > 0): ?>
                            <?php foreach ($anggota as $row): ?>
                                <tr>
                                    <td data-label="Nama Lengkap"><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td data-label="Jabatan"><?= htmlspecialchars($row['jabatan']) ?></td>
                                    <td data-label="Bergabung Sejak"><?= htmlspecialchars($row['bergabung_sejak']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">Tidak ada data anggota.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Page navigation example">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=anggota&page=<?= $page - 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>" tabindex="-1">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=anggota&page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=anggota&page=<?= $page + 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php elseif ($active_tab == 'absensi'): ?>
            <h2 class="mb-4 text-primary"><i class="fa-solid fa-user-check me-2"></i>Absensi Perkumpulan Hari Ini</h2>
            <div class="row mb-3 gy-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="alert alert-info mb-0" role="alert">
                        <i class="fa-solid fa-location-dot me-2"></i> Pilih nama Anda untuk absen. Pastikan GPS aktif.
                    </div>
                </div>
                <div class="col-12 col-md-6">
                    <form action="" method="GET" class="d-flex w-100 justify-content-end">
                        <input type="hidden" name="tab" value="absensi">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Cari anggota..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="?tab=absensi" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?= $messageType ?> mt-3 mb-4" role="alert">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            <?php if (count($anggota) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th scope="col">Nama Lengkap</th>
                                <th scope="col">Status Absensi</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            foreach ($anggota as $row): 
                                $stmt = $conn->prepare("SELECT COUNT(*) FROM absensi WHERE anggota_id = ? AND DATE(tanggal_absen) = CURDATE()");
                                if (!$stmt) {
                                    die("Prepare failed for absensi check: " . $conn->error);
                                }
                                $stmt->bind_param("i", $row['id']);
                                $stmt->execute();
                                $stmt->bind_result($isAbsent);
                                $stmt->fetch();
                                $stmt->close();
                            ?>
                                <tr>
                                    <td data-label="Nama Lengkap"><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td data-label="Status Absensi">
                                        <?php if ($isAbsent > 0): ?>
                                            <span class="badge bg-success">Hadir</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Belum Hadir</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Aksi">
                                        <?php if ($isAbsent == 0): ?>
                                            <form id="formAbsen_<?= $row['id'] ?>" method="POST">
                                                <input type="hidden" name="absen_submit" value="1">
                                                <input type="hidden" name="anggota_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="latitude" id="userLat_<?= $row['id'] ?>">
                                                <input type="hidden" name="longitude" id="userLon_<?= $row['id'] ?>">
                                                <button type="button" class="btn btn-sm btn-primary" onclick="getLocationAndSubmit(<?= $row['id'] ?>)">
                                                    Absen
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-secondary" disabled>Sudah Absen</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fa-solid fa-exclamation-triangle me-2"></i> **Peringatan:** Bagian ini kosong karena **tabel 'anggota' di database Anda tidak memiliki data**. Silakan tambahkan anggota terlebih dahulu.
                </div>
            <?php endif; ?>
            <nav aria-label="Page navigation example">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=absensi&page=<?= $page - 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>" tabindex="-1">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=absensi&page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=absensi&page=<?= $page + 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <script>
                function getLocationAndSubmit(anggotaId) {
                    if (navigator.geolocation) {
                        navigator.geolocation.getCurrentPosition(
                            position => {
                                document.getElementById('userLat_' + anggotaId).value = position.coords.latitude;
                                document.getElementById('userLon_' + anggotaId).value = position.coords.longitude;
                                document.getElementById('formAbsen_' + anggotaId).submit();
                            },
                            error => {
                                let errorMessage = "";
                                switch(error.code) {
                                    case error.PERMISSION_DENIED:
                                        errorMessage = "Izin lokasi ditolak. Silakan aktifkan GPS dan berikan izin.";
                                        break;
                                    case error.POSITION_UNAVAILABLE:
                                        errorMessage = "Informasi lokasi tidak tersedia. Coba lagi.";
                                        break;
                                    case error.TIMEOUT:
                                        errorMessage = "Permintaan lokasi habis waktu. Coba lagi.";
                                        break;
                                    case error.UNKNOWN_ERROR:
                                        errorMessage = "Terjadi kesalahan yang tidak diketahui.";
                                        break;
                                }
                                alert("Gagal mendapatkan lokasi. " + errorMessage);
                            }
                        );
                    } else {
                        alert("Geolocation tidak didukung oleh browser ini.");
                    }
                }
            </script>
        <?php elseif ($active_tab == 'kegiatan'): ?>
            <h2 class="mb-4 text-primary"><i class="fa-solid fa-calendar-alt me-2"></i>Daftar Kegiatan</h2>
            <div class="row mb-3 gy-2 align-items-center">
                <div class="col-12 col-md-6">
                    <p class="fs-5 mb-0">Total Kegiatan: <span class="badge bg-primary"><?= $total_kegiatan ?></span></p>
                </div>
                <div class="col-12 col-md-6">
                    <form action="" method="GET" class="d-flex w-100 justify-content-end">
                        <input type="hidden" name="tab" value="kegiatan">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Cari kegiatan..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="?tab=kegiatan" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="kegiatanTable">
                    <thead>
                        <tr>
                            <th scope="col">Nama Kegiatan</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Tanggal Mulai</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($kegiatan) > 0): ?>
                            <?php foreach ($kegiatan as $row): ?>
                                <tr>
                                    <td data-label="Nama Kegiatan"><?= htmlspecialchars($row['nama_kegiatan']) ?></td>
                                    <td data-label="Lokasi"><?= htmlspecialchars($row['lokasi']) ?></td>
                                    <td data-label="Tanggal Mulai"><?= htmlspecialchars($row['tanggal_mulai']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted">Tidak ada data kegiatan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Page navigation example">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=kegiatan&page=<?= $page - 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>" tabindex="-1">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=kegiatan&page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=kegiatan&page=<?= $page + 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php elseif ($active_tab == 'keuangan'): ?>
            <h2 class="mb-4 text-primary"><i class="fa-solid fa-wallet me-2"></i>Laporan Keuangan</h2>
            <div class="row mb-3 gy-2 align-items-center">
                <div class="col-12 col-md-6">
                    <form action="" method="GET" class="d-flex align-items-center w-100">
                        <input type="hidden" name="tab" value="keuangan">
                        <label for="year-keuangan" class="form-label mb-0 me-2 fw-bold">Pilih Tahun:</label>
                        <select class="form-select w-auto" id="year-keuangan" name="year" onchange="this.form.submit()">
                            <?php
                            $currentYear = date('Y');
                            $resultYears = $conn->query("SELECT DISTINCT YEAR(tanggal_transaksi) AS year FROM keuangan ORDER BY year DESC");
                            $years = [];
                            if ($resultYears) {
                                while ($row = $resultYears->fetch_assoc()) {
                                    $years[] = $row['year'];
                                }
                            }
                            if (!in_array($currentYear, $years)) {
                                $years[] = $currentYear;
                                rsort($years);
                            }

                            foreach ($years as $year):
                            ?>
                                <option value="<?= $year ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-12 col-md-6">
                    <form action="" method="GET" class="d-flex w-100 justify-content-end">
                        <input type="hidden" name="tab" value="keuangan">
                        <input type="hidden" name="year" value="<?= $selectedYear ?>">
                        <div class="input-group">
                            <input type="text" id="searchInputKeuangan" class="form-control" placeholder="Cari deskripsi..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="?tab=keuangan&year=<?= $selectedYear ?>" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-striped" id="keuanganTable">
                    <thead>
                        <tr>
                            <th scope="col">Jenis Transaksi</th>
                            <th scope="col">Jumlah</th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col">Tanggal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($keuangan) > 0): ?>
                            <?php foreach ($keuangan as $row): ?>
                                <tr>
                                    <td data-label="Jenis Transaksi">
                                        <span class="badge rounded-pill <?= ($row['jenis_transaksi'] == 'pemasukan') ? 'bg-success' : 'bg-danger' ?>">
                                            <?= htmlspecialchars(ucfirst($row['jenis_transaksi'])) ?>
                                        </span>
                                    </td>
                                    <td data-label="Jumlah"><?= htmlspecialchars(formatRupiah($row['jumlah'])) ?></td>
                                    <td data-label="Deskripsi"><?= htmlspecialchars($row['deskripsi']) ?></td>
                                    <td data-label="Tanggal"><?= htmlspecialchars($row['tanggal_transaksi']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted">Tidak ada data keuangan untuk tahun ini.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <nav aria-label="Page navigation example">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=keuangan&year=<?= $selectedYear ?>&page=<?= $page - 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>" tabindex="-1">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=keuangan&year=<?= $selectedYear ?>&page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=keuangan&year=<?= $selectedYear ?>&page=<?= $page + 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>">Next</a>
                    </li>
                </ul>
            </nav>
        <?php elseif ($active_tab == 'iuran'): ?>
            <?php 
                $memberId = isset($_GET['member_id']) ? intval($_GET['member_id']) : null;
                $memberDuesBreakdown = null;
                if ($memberId) {
                    $memberDuesBreakdown = fetchMemberDuesBreakdownWithYear($conn, $memberId, $selectedYear);
                }
            ?>
            <?php if ($memberDuesBreakdown): ?>
                <a href="?tab=iuran&year=<?= $selectedYear ?>" class="btn btn-outline-primary mb-4">
                    <i class="fa-solid fa-arrow-left me-2"></i> Kembali ke Daftar Iuran
                </a>
                <h2 class="mb-4 text-primary"><i class="fa-solid fa-user me-2"></i>Rekapitulasi Iuran Anggota</h2>
                <div class="card detail-card mb-4">
                    <div class="card-body">
                        <h4 class="card-title fw-bold"><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($memberDuesBreakdown['member']['nama_lengkap']) ?></h4>
                        <p class="card-text text-muted">
                            <i class="fa-solid fa-calendar me-2"></i>Bergabung Sejak: <?= htmlspecialchars($memberDuesBreakdown['member']['bergabung_sejak']) ?>
                        </p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-7 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fa-solid fa-list-check me-2"></i>Rincian Bulanan (Tahun <?= $selectedYear ?>)</h5>
                                <ul class="list-group list-group-flush">
                                    <?php if (count($memberDuesBreakdown['breakdown']) > 0): ?>
                                        <?php foreach ($memberDuesBreakdown['breakdown'] as $item): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <span>
                                                    <strong><?= htmlspecialchars($item['month']) ?>:</strong>
                                                    <?= formatRupiah($item['paid']) ?>
                                                    <?php if (!empty($item['notes'])): ?>
                                                        <small class="text-danger fw-bold ms-2">(<?= htmlspecialchars($item['notes']) ?>)</small>
                                                    <?php endif; ?>
                                                </span>
                                                <span>
                                                    <?php if ($item['status'] == 'Lunas'): ?>
                                                        <span class="badge bg-success">Lunas</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Kurang</span>
                                                    <?php endif; ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-center text-muted">Tidak ada data iuran yang tercatat untuk tahun ini.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-5 mb-4">
                        <div class="card h-100 shadow-sm">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fa-solid fa-chart-pie me-2"></i>Ringkasan</h5>
                                <ul class="list-group list-group-flush">
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Total Pembayaran</span>
                                        <span class="fw-bold text-success"><?= formatRupiah($memberDuesBreakdown['summary']['total_paid']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Total Seharusnya</span>
                                        <span class="fw-bold text-primary"><?= formatRupiah($memberDuesBreakdown['summary']['total_expected']) ?></span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between">
                                        <span>Kekurangan</span>
                                        <span class="fw-bold text-danger"><?= formatRupiah($memberDuesBreakdown['summary']['shortfall']) ?></span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <h2 class="mb-4 text-primary"><i class="fa-solid fa-receipt me-2"></i>Rekapitulasi Iuran</h2>
                <div class="row mb-3 gy-2 align-items-center">
        <div class="col-12 col-md-6">
        <form action="" method="GET" class="d-flex align-items-center w-100">
            <input type="hidden" name="tab" value="iuran">
            <label for="year-iuran" class="form-label mb-0 me-2 fw-bold">Pilih Tahun:</label>
            <select class="form-select w-auto" id="year-iuran" name="year" onchange="this.form.submit()">
                <?php
                $currentYear = date('Y');
                $resultYears = $conn->query("SELECT DISTINCT YEAR(tanggal_bayar) AS year FROM iuran ORDER BY year DESC");
                $years = [];
                if ($resultYears) {
                    while ($row = $resultYears->fetch_assoc()) {
                        $years[] = $row['year'];
                    }
                }
                if (!in_array($currentYear, $years)) {
                    $years[] = $currentYear;
                    rsort($years);
                }

                foreach ($years as $year):
                ?>
                    <option value="<?= $year ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>><?= $year ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        </div>
            <div class="col-12 col-md-6">
                <form action="" method="GET" class="d-flex w-100 justify-content-end">
                    <input type="hidden" name="tab" value="iuran">
                    <input type="hidden" name="year" value="<?= $selectedYear ?>">
                    <div class="input-group">
                        <input type="text" id="searchInputIuran" class="form-control" placeholder="Cari anggota..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                        <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                        <?php if (!empty($searchTerm)): ?>
                            <a href="?tab=iuran&year=<?= $selectedYear ?>" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped iuran-table">
                        <thead>
                            <tr>
                                <th scope="col">ID Anggota</th>
                                <th scope="col">Nama Anggota</th>
                                <th scope="col">Total Bayar</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($iuran) > 0): ?>
                                <?php foreach ($iuran as $row): ?>
                                    <?php 
                                        $monthlyFee = 10000;
                                        $stmt = $conn->prepare("SELECT bergabung_sejak FROM anggota WHERE id = ?");
                                        $stmt->bind_param("i", $row['anggota_id']);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        $anggotaRow = $result->fetch_assoc();
                                        $stmt->close();
                                        
                                        $joinDate = new DateTime($anggotaRow['bergabung_sejak']);
                                        $totalSeharusnya = 0;
                                        $startOfSelectedYear = new DateTime("{$selectedYear}-01-01");
                                        $endOfSelectedYear = new DateTime("{$selectedYear}-12-31");
                                        
                                        $currentDate = new DateTime();
                                        $startDate = max($joinDate, $startOfSelectedYear);
                                        $endDate = min($currentDate, $endOfSelectedYear);

                                        $interval = $startDate->diff($endDate);
                                        $months = ($interval->y * 12) + $interval->m;
                                        if ($startDate <= $endDate) {
                                            $months += 1;
                                        }

                                        $totalSeharusnya = $months * $monthlyFee;
                                        $statusData = getPaymentStatus($row['total_bayar'], $totalSeharusnya);
                                        $status = $statusData['status'];
                                        $badgeClass = $statusData['class'];
                                    ?>
                                    <tr>
                                        <td data-label="ID Anggota"><?= htmlspecialchars($row['anggota_id']) ?></td>
                                        <td data-label="Nama Anggota">
                                            <a href="?tab=iuran&member_id=<?= htmlspecialchars($row['anggota_id']) ?>&year=<?= $selectedYear ?>">
                                                <i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($row['nama_lengkap']) ?>
                                            </a>
                                        </td>
                                        <td data-label="Total Bayar"><?= htmlspecialchars(formatRupiah($row['total_bayar'])) ?></td>
                                        <td data-label="Status">
                                            <span class="badge rounded-pill <?= $badgeClass ?>">
                                                <?= $status ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">Tidak ada data iuran untuk tahun ini.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Page navigation example">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=iuran&page=<?= $page - 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>&year=<?= $selectedYear ?>" tabindex="-1">Previous</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                            <a class="page-link" href="?tab=iuran&page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>&year=<?= $selectedYear ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                        <a class="page-link" href="?tab=iuran&page=<?= $page + 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>&year=<?= $selectedYear ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
        <footer class="text-center mt-5">
            <div class="copyright-box">
                <p class="copyright-text" style="font-size: 0.8rem;">
                    &copy; <?= date('Y') ?> <a href="http://nuxera.my.id" target="_blank" style="color: inherit; text-decoration: none;">nuxera.my.id</a>
                </p>
            </div>
        </footer>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>