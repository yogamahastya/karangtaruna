<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
include '../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ../login/');
    exit();
}

$allowed_roles = ['sekretaris', 'bendahara', 'admin', 'superadmin'];
if (!in_array($_SESSION['user_role'], $allowed_roles)) {
    header('Location: akses_ditolak.php');
    exit();
}

header('Content-Type: text/html; charset=UTF-8');

// =================================================================
// Konfigurasi dan Koneksi Database
// =================================================================

// Membuat koneksi ke database menggunakan konstanta dari config.php
$conn = new mysqli(DB_SERVERNAME, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// =================================================================
// Logika untuk Menangani Operasi CRUD
// =================================================================

// Fungsi baru untuk mendapatkan anggota_id dari user_id
function getAnggotaIdFromUserId($conn, $userId) {
    $sql = "SELECT anggota_id FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['anggota_id'];
    }
    return null;
}

function getParamTypes($data) {
    $types = '';
    foreach ($data as $key => $value) {
        if ($value === '' || !is_numeric($value)) {
            $types .= 's';
        } elseif (filter_var($value, FILTER_VALIDATE_INT) !== false && strpos($value, '.') === false) {
            $types .= 'i';
        } elseif (filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    return $types;
}

function handleAdd($conn, $tableName, $data) {
    if ($tableName === 'iuran') {
        $data['periode'] = $data['tanggal_bayar'];
        if (!isset($data['keterangan'])) {
            $data['keterangan'] = '';
        }
    } elseif ($tableName === 'keuangan') {
        $anggotaId = getAnggotaIdFromUserId($conn, $_SESSION['user_id']);
        if ($anggotaId) {
            $data['dicatat_oleh_id'] = $anggotaId;
        } else {
            // Fallback jika user_id tidak terkait dengan anggota_id, gunakan user_id dari sesi
            $data['dicatat_oleh_id'] = $_SESSION['user_id'];
        }
    } elseif ($tableName === 'users') {
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
    }
    
    $columns = implode(", ", array_keys($data));
    $placeholders = implode(", ", array_fill(0, count($data), "?"));
    $sql = "INSERT INTO `$tableName` ($columns) VALUES ($placeholders)";
    $stmt = $conn->prepare($sql);
    $types = getParamTypes($data);
    $params = array_values($data);
    
    $stmt->bind_param($types, ...$params);
    
    return $stmt->execute();
}

function handleEdit($conn, $tableName, $id, $data) {
    if ($tableName === 'iuran') {
        if (isset($data['tanggal_bayar'])) {
            $data['periode'] = $data['tanggal_bayar'];
        }
    } elseif ($tableName === 'users') {
        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        } else {
            unset($data['password']); // Jangan update password jika kosong
        }
    }
    
    $setClause = implode("=?, ", array_keys($data)) . "=?";
    $sql = "UPDATE `$tableName` SET $setClause WHERE id = ?";
    $stmt = $conn->prepare($sql);

    $types = getParamTypes($data);
    $types .= "i";
    $params = array_values($data);
    $params[] = $id;

    $stmt->bind_param($types, ...$params);

    return $stmt->execute();
}

function handleDelete($conn, $tableName, $id) {
    $sql = "DELETE FROM `$tableName` WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

/**
 * Menangani pembaruan lokasi absensi di tabel lokasi_absensi.
 * Akan melakukan INSERT jika data belum ada (misal, untuk ID=1), atau UPDATE jika sudah ada.
 */
function handleUpdateLocation($conn, $latitude, $longitude, $toleransi) {
    // Diasumsikan hanya ada satu entri lokasi absensi utama dengan id = 1
    $sql = "INSERT INTO lokasi_absensi (id, latitude, longitude, toleransi_jarak)
            VALUES (1, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            toleransi_jarak = VALUES(toleransi_jarak)";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
        return false;
    }
    $stmt->bind_param("ddi", $latitude, $longitude, $toleransi);
    return $stmt->execute();
}


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action = $_POST['action'];
    $tab = $_POST['tab'] ?? ''; // Baris yang sudah diperbaiki dari diskusi sebelumnya
    $success = false;
    $message = "";

    // Perbaikan: Tambahkan pengecekan sebelum menggunakan $_POST['data']
    $data = [];
    if (isset($_POST['data']) && is_array($_POST['data'])) {
        $data = array_map('trim', $_POST['data']);
    }

    if ($action == 'add') {
        $success = handleAdd($conn, $tab, $data);
    } elseif ($action == 'edit') {
        $id = $_POST['id'];
        $success = handleEdit($conn, $tab, $id, $data);
    } elseif ($action == 'delete') {
        $id = $_POST['id'];
        $success = handleDelete($conn, $tab, $id);
    } elseif ($action == 'update_location') {
        $latitude = filter_var($_POST['lokasi_latitude'], FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($_POST['lokasi_longitude'], FILTER_VALIDATE_FLOAT);
        $toleransi = filter_var($_POST['jarak_toleransi'], FILTER_VALIDATE_INT);

        if ($latitude === false || $longitude === false || $toleransi === false) {
            $success = false;
            $message = "Operasi update lokasi gagal: Data tidak valid.";
        } else {
            $success = handleUpdateLocation($conn, $latitude, $longitude, $toleransi);
        }
        $tab = 'users'; // Kembali ke tab users setelah update lokasi
    }

    if ($success) {
        $message = "Operasi $action berhasil!";
    } else {
        $message = "Operasi $action gagal: " . $conn->error;
        if (isset($stmt) && $stmt->error) {
            $message .= " (" . $stmt->error . ")";
        }
    }
}

// =================================================================
// FUNGSI PENGAMBILAN DATA BARU DENGAN PAGINASI
// =================================================================

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
    $sql = "SELECT * FROM `$tableName`";
    $conditions = [];
    $params = [];
    $types = '';
    $orderBy = 'id ASC';

    // Logika JOIN khusus untuk tabel tertentu
    if ($tableName === 'keuangan') {
        $sql = "SELECT k.*, a.nama_lengkap AS dicatat_oleh_nama FROM keuangan k LEFT JOIN anggota a ON k.dicatat_oleh_id = a.id";
        $orderBy = 'k.tanggal_transaksi ASC';
    } elseif ($tableName === 'iuran') {
        $sql = "SELECT i.*, a.nama_lengkap AS anggota_nama FROM iuran i LEFT JOIN anggota a ON i.anggota_id = a.id";
        $orderBy = 'i.tanggal_bayar ASC';
    } elseif ($tableName === 'anggota') {
        // Logika pengurutan khusus untuk anggota
        $orderBy = "FIELD(jabatan, 'Ketua', 'Wakil Ketua', 'Sekretaris', 'Bendahara', 'Humas', 'Anggota'), nama_lengkap ASC";
    } elseif ($tableName === 'kegiatan') {
        $orderBy = 'tanggal_mulai DESC';
    } elseif ($tableName === 'users') {
        $orderBy = 'id ASC';
    }

    // Kondisi filter tahun
    if ($filterYear) {
        if ($tableName === 'keuangan') {
            $conditions[] = "YEAR(k.tanggal_transaksi) = ?";
            $params[] = $filterYear;
            $types .= 'i';
        } elseif ($tableName === 'iuran') {
            $conditions[] = "YEAR(i.tanggal_bayar) = ?";
            $params[] = $filterYear;
            $types .= 'i';
        }
    }

    // Kondisi pencarian
    if ($searchTerm) {
        $searchTermLike = '%' . $searchTerm . '%';
        if ($tableName === 'anggota') {
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
            $conditions[] = "(a.nama_lengkap LIKE ? OR i.keterangan LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'ss';
        } elseif ($tableName === 'users') {
            $conditions[] = "(username LIKE ? OR role LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'ss';
        }
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
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
    }
    // Perbaikan: Pastikan $stmt ditutup di sini setelah semua data diambil atau kesalahan terjadi
    if ($stmt) {
        $stmt->close();
    }
    return $data;
}

/**
 * Menghitung total baris untuk paginasi.
 *
 * @param mysqli $conn Koneksi database.
 * @param string $tableName Nama tabel.
 * @param string|null $searchTerm Kata kunci pencarian.
 * @param int|null $filterYear Tahun filter.
 * @return int Total baris.
 */
function countRowsWithFilter($conn, $tableName, $searchTerm = null, $filterYear = null) {
    $sql = "SELECT COUNT(*) AS total FROM `$tableName`";
    $conditions = [];
    $params = [];
    $types = '';

    // Logika JOIN khusus untuk tabel tertentu
    if ($tableName === 'keuangan') {
        $sql = "SELECT COUNT(*) AS total FROM keuangan k LEFT JOIN anggota a ON k.dicatat_oleh_id = a.id";
    } elseif ($tableName === 'iuran') {
        $sql = "SELECT COUNT(*) AS total FROM iuran i LEFT JOIN anggota a ON i.anggota_id = a.id";
    }

    // Kondisi filter tahun
    if ($filterYear) {
        if ($tableName === 'keuangan') {
            $conditions[] = "YEAR(k.tanggal_transaksi) = ?";
            $params[] = $filterYear;
            $types .= 'i';
        } elseif ($tableName === 'iuran') {
            $conditions[] = "YEAR(i.tanggal_bayar) = ?";
            $params[] = $filterYear;
            $types .= 'i';
        }
    }

    // Kondisi pencarian
    if ($searchTerm) {
        $searchTermLike = '%' . $searchTerm . '%';
        if ($tableName === 'anggota') {
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
            $conditions[] = "(a.nama_lengkap LIKE ? OR i.keterangan LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'ss';
        } elseif ($tableName === 'users') {
            $conditions[] = "(username LIKE ? OR role LIKE ?)";
            $params[] = $searchTermLike;
            $params[] = $searchTermLike;
            $types .= 'ss';
        }
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        // Perbaikan: Pastikan $stmt ditutup di sini
        $stmt->close();
        return $row['total'];
    }
    return 0;
}

// =================================================================
// Logika Tampilan & Paginasi Utama
// =================================================================

$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'anggota';
$currentYear = date('Y');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';

// Pengaturan Paginasi
$limit = 10; // Jumlah data per halaman
// PERBAIKAN: Pastikan $_GET['page'] yang digunakan di intval()
// Dan pastikan $page minimal 1
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1; 
$start = ($page - 1) * $limit; // Ini akan selalu >= 0

$total_rows = countRowsWithFilter($conn, $active_tab, $searchTerm, $selectedYear);
$total_pages = ceil($total_rows / $limit);

// Ambil data yang sudah dipaginasi
$anggota = [];
$kegiatan = [];
$keuangan = [];
$iuran = [];
$users = [];

if ($active_tab === 'anggota') {
    $anggota = fetchDataWithPagination($conn, 'anggota', $start, $limit, $searchTerm, $selectedYear);
} elseif ($active_tab === 'kegiatan') {
    $kegiatan = fetchDataWithPagination($conn, 'kegiatan', $start, $limit, $searchTerm, $selectedYear);
} elseif ($active_tab === 'keuangan') {
    $keuangan = fetchDataWithPagination($conn, 'keuangan', $start, $limit, $searchTerm, $selectedYear);
} elseif ($active_tab === 'iuran') {
    $iuran = fetchDataWithPagination($conn, 'iuran', $start, $limit, $searchTerm, $selectedYear);
} elseif ($active_tab === 'users') {
    $users = fetchDataWithPagination($conn, 'users', $start, $limit, $searchTerm, $selectedYear);
}

// Data total untuk dashboard (tidak dipaginasi)
$totalAnggota = countRowsWithFilter($conn, 'anggota');
$totalPemasukan = 0;
$totalPengeluaran = 0;
// Perbaikan: Pastikan fetchDataWithPagination di sini juga menggunakan parameter yang benar
$allKeuangan = fetchDataWithPagination($conn, 'keuangan', 0, 999999, null, $selectedYear);
foreach ($allKeuangan as $transaksi) {
    if ($transaksi['jenis_transaksi'] == 'pemasukan') {
        $totalPemasukan += $transaksi['jumlah'];
    } else {
        $totalPengeluaran += $transaksi['jumlah'];
    }
}
$saldo = $totalPemasukan - $totalPengeluaran;
$totalIuran = 0;
// Perbaikan: Pastikan fetchDataWithPagination di sini juga menggunakan parameter yang benar
$allIuran = fetchDataWithPagination($conn, 'iuran', 0, 999999, null, $selectedYear);
foreach ($allIuran as $transaksi) {
    $totalIuran += $transaksi['jumlah_bayar'];
}

// Perbaikan: Pastikan fetchDataWithPagination di sini juga menggunakan parameter yang benar
$anggotaList = fetchDataWithPagination($conn, 'anggota', 0, 999999, null, null); // Untuk dropdown di modal

// Ambil data lokasi absensi saat ini dari database untuk pre-fill modal
$current_latitude = -7.527444; // Default jika belum ada di DB
$current_longitude = 110.628819; // Default jika belum ada di DB
$current_tolerance = 50; // Default jika belum ada di DB

$sql_lokasi = "SELECT latitude, longitude, toleransi_jarak FROM lokasi_absensi WHERE id = 1 LIMIT 1";
$result_lokasi = $conn->query($sql_lokasi);
if ($result_lokasi && $result_lokasi->num_rows > 0) {
    $lokasi_data = $result_lokasi->fetch_assoc();
    $current_latitude = $lokasi_data['latitude'];
    $current_longitude = $lokasi_data['longitude'];
    $current_tolerance = $lokasi_data['toleransi_jarak'];
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard <?= htmlspecialchars(ORGANIZATION_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/styleadmin.css">
    <link rel="stylesheet" href="../assets/css/styleindex.css"> 
</head>
<body>

<div class="container py-5">
    <header class="hero-section">
        <h1 class="display-5"><i class="fa-solid fa-tachometer-alt me-3"></i>Admin Dashboard <?= htmlspecialchars(ORGANIZATION_NAME) ?></h1>
        <p class="fs-6 mt-2">Kelola data <?= htmlspecialchars(ORGANIZATION_NAME) ?></p>
	<a href="../logout.php" class="btn btn-danger mt-3" style="border-radius: 0.75rem; padding: 0.75rem 1.5rem;">
        <i class="fa-solid fa-sign-out-alt me-2"></i> Logout
    </a>
    </header>

    <?php if (isset($message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="mb-5">
        <ul class="nav nav-pills nav-justified nav-pills-custom" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'anggota') ? 'active' : '' ?>" href="?tab=anggota<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>">
                    <i class="fa-solid fa-users me-2"></i> Anggota
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'kegiatan') ? 'active' : '' ?>" href="?tab=kegiatan<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>">
                    <i class="fa-solid fa-calendar-alt me-2"></i> Kegiatan
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'keuangan') ? 'active' : '' ?>" href="?tab=keuangan<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>">
                    <i class="fa-solid fa-wallet me-2"></i> Keuangan
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'iuran') ? 'active' : '' ?>" href="?tab=iuran<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>">
                    <i class="fa-solid fa-receipt me-2"></i> Iuran 
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($active_tab == 'users') ? 'active' : '' ?>" href="?tab=users<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>">
                    <i class="fa-solid fa-user-circle me-2"></i> Users & Lokasi
                </a>
            </li>
        </ul>
    </div>

    <div class="content-card">
        <?php if ($active_tab == 'anggota'): ?>
            <h2 class="mb-4 text-primary"><i class="fa-solid fa-user-group me-2"></i>Kelola Data Anggota</h2>
            <div class="row mb-3 align-items-center">
                <div class="col-md-6">
                    <p class="fs-5 mb-0">Total Anggota: <span class="badge bg-primary"><?= $totalAnggota ?></span></p>
                </div>
                <div class="col-md-6 text-end">
                    <form action="" method="GET" class="d-inline-flex">
                        <input type="hidden" name="tab" value="anggota">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Cari anggota..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="?tab=anggota" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addAnggotaModal">
                <i class="fa-solid fa-plus-circle me-2"></i> Tambah Anggota
            </button>
            <div class="table-responsive">
                <table class="table table-hover table-striped d-none d-md-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Nama Lengkap</th>
                            <th scope="col">Jabatan</th>
                            <th scope="col">Bergabung Sejak</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($anggota) > 0): ?>
                            <?php foreach ($anggota as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($row['nama_lengkap']) ?></td>
                                    <td><?= htmlspecialchars($row['jabatan']) ?></td>
                                    <td><?= htmlspecialchars($row['bergabung_sejak']) ?></td>
                                    <td>
                                        <button class="btn-action btn-warning-custom me-2 edit-btn" data-bs-toggle="modal" data-bs-target="#editAnggotaModal" data-id="<?= $row['id'] ?>" data-nama="<?= $row['nama_lengkap'] ?>" data-jabatan="<?= $row['jabatan'] ?>" data-sejak="<?= $row['bergabung_sejak'] ?>">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tab" value="anggota">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted">Tidak ada data anggota.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation example" class="mt-4 d-none d-md-block">
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
            <?php endif; ?>
                <div class="d-md-none">
                    <?php if (count($anggota) > 0): ?>
                        <?php foreach ($anggota as $row): ?>
                            <div class="card-item">
                                <div>
                                    <span class="label">ID</span>
                                    <span class="value"><?= htmlspecialchars($row['id']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Nama Lengkap</span>
                                    <span class="value"><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($row['nama_lengkap']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Jabatan</span>
                                    <span class="value"><?= htmlspecialchars($row['jabatan']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Bergabung Sejak</span>
                                    <span class="value"><?= htmlspecialchars($row['bergabung_sejak']) ?></span>
                                </div>
                                <div class="btn-group">
                                    <button class="btn-action btn-warning-custom edit-btn" data-bs-toggle="modal" data-bs-target="#editAnggotaModal" data-id="<?= $row['id'] ?>" data-nama="<?= $row['nama_lengkap'] ?>" data-jabatan="<?= $row['jabatan'] ?>" data-sejak="<?= $row['bergabung_sejak'] ?>">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <form action="" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tab" value="anggota">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted">Tidak ada data anggota.</div>
                    <?php endif; ?>
                </div>
                
            </div>
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation example" class="mt-4 d-md-none">
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
            <?php endif; ?>
        <?php elseif ($active_tab == 'kegiatan'): ?>
            <h2 class="mb-4 text-primary"><i class="fa-solid fa-calendar-alt me-2"></i>Kelola Data Kegiatan</h2>
            <div class="row mb-3">
                <div class="col-md-12 text-end">
                    <form action="" method="GET" class="d-inline-flex">
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
            <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addKegiatanModal">
                <i class="fa-solid fa-plus-circle me-2"></i> Tambah Kegiatan
            </button>
            <div class="table-responsive">
                <table class="table table-hover table-striped d-none d-md-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Nama Kegiatan</th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col">Lokasi</th>
                            <th scope="col">Tanggal Mulai</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($kegiatan) > 0): ?>
                            <?php foreach ($kegiatan as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['nama_kegiatan']) ?></td>
                                    <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                                    <td><?= htmlspecialchars($row['lokasi']) ?></td>
                                    <td><?= htmlspecialchars($row['tanggal_mulai']) ?></td>
                                    <td>
                                        <button class="btn-action btn-warning-custom me-2 edit-btn" data-bs-toggle="modal" data-bs-target="#editKegiatanModal" data-id="<?= $row['id'] ?>" data-nama="<?= $row['nama_kegiatan'] ?>" data-deskripsi="<?= $row['deskripsi'] ?>" data-lokasi="<?= $row['lokasi'] ?>" data-tanggal="<?= $row['tanggal_mulai'] ?>">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tab" value="kegiatan">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Tidak ada data kegiatan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation example" class="mt-4 d-none d-md-block">
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
            <?php endif; ?>
                <div class="d-md-none">
                    <?php if (count($kegiatan) > 0): ?>
                        <?php foreach ($kegiatan as $row): ?>
                            <div class="card-item">
                                <div>
                                    <span class="label">ID</span>
                                    <span class="value"><?= htmlspecialchars($row['id']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Nama Kegiatan</span>
                                    <span class="value"><?= htmlspecialchars($row['nama_kegiatan']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Deskripsi</span>
                                    <span class="value"><?= htmlspecialchars($row['deskripsi']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Lokasi</span>
                                    <span class="value"><?= htmlspecialchars($row['lokasi']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Tanggal Mulai</span>
                                    <span class="value"><?= htmlspecialchars($row['tanggal_mulai']) ?></span>
                                </div>
                                <div class="btn-group">
                                    <button class="btn-action btn-warning-custom edit-btn" data-bs-toggle="modal" data-bs-target="#editKegiatanModal" data-id="<?= $row['id'] ?>" data-nama="<?= $row['nama_kegiatan'] ?>" data-deskripsi="<?= $row['deskripsi'] ?>" data-lokasi="<?= $row['lokasi'] ?>" data-tanggal="<?= $row['tanggal_mulai'] ?>">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <form action="" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tab" value="kegiatan">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted">Tidak ada data kegiatan.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation example" class="mt-4 d-md-none">
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
            <?php endif; ?>
        <?php elseif ($active_tab == 'keuangan'): ?>
            <h2 class="mb-4 text-primary"><i class="fa-solid fa-wallet me-2"></i>Kelola Laporan Keuangan</h2>
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-calendar-alt"></i></span>
                        <select class="form-select" onchange="window.location.href = '?tab=<?= $active_tab ?>&year=' + this.value + '<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>'">
                            <?php
                            $minYearQuery = "SELECT MIN(YEAR(tanggal_transaksi)) AS min_year FROM keuangan";
                            $minYearResult = $conn->query($minYearQuery);
                            $minYearRow = $minYearResult->fetch_assoc();
                            $minYear = $minYearRow['min_year'] ? $minYearRow['min_year'] : date('Y');
                            for ($year = date('Y'); $year >= $minYear; $year--):
                            ?>
                                <option value="<?= $year ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>>
                                    Tahun <?= $year ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <form action="" method="GET" class="d-inline-flex">
                        <input type="hidden" name="tab" value="keuangan">
                        <input type="hidden" name="year" value="<?= $selectedYear ?>">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Cari transaksi..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="?tab=keuangan&year=<?= $selectedYear ?>" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Total Pemasukan</h5>
                            <p class="card-text fs-4">Rp<?= number_format($totalPemasukan, 0, ',', '.') ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h5 class="card-title">Total Pengeluaran</h5>
                            <p class="card-text fs-4">Rp<?= number_format($totalPengeluaran, 0, ',', '.') ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-white bg-primary">
                        <div class="card-body">
                            <h5 class="card-title">Sisa Saldo</h5>
                            <p class="card-text fs-4">Rp<?= number_format($saldo, 0, ',', '.') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addKeuanganModal">
                <i class="fa-solid fa-plus-circle me-2"></i> Tambah Transaksi
            </button>
            <div class="table-responsive">
                <table class="table table-hover table-striped d-none d-md-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">Jenis Transaksi</th>
                            <th scope="col">Jumlah</th>
                            <th scope="col">Deskripsi</th>
                            <th scope="col">Tanggal</th>
                            <th scope="col">Dicatat Oleh</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($keuangan) > 0): ?>
                            <?php foreach ($keuangan as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td>
                                        <span class="badge rounded-pill <?= ($row['jenis_transaksi'] == 'pemasukan') ? 'bg-success' : 'bg-danger' ?>">
                                            <?= htmlspecialchars(ucfirst($row['jenis_transaksi'])) ?>
                                        </span>
                                    </td>
                                    <td>Rp<?= htmlspecialchars(number_format($row['jumlah'], 0, ',', '.')) ?></td>
                                    <td><?= htmlspecialchars($row['deskripsi']) ?></td>
                                    <td><?= htmlspecialchars($row['tanggal_transaksi']) ?></td>
                                    <td><?= htmlspecialchars($row['dicatat_oleh_nama']) ?></td>
                                    <td>
                                        <button class="btn-action btn-warning-custom me-2 edit-btn" data-bs-toggle="modal" data-bs-target="#editKeuanganModal" data-id="<?= $row['id'] ?>" data-jenis="<?= $row['jenis_transaksi'] ?>" data-jumlah="<?= $row['jumlah'] ?>" data-deskripsi="<?= $row['deskripsi'] ?>" data-tanggal="<?= $row['tanggal_transaksi'] ?>">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tab" value="keuangan">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">Tidak ada data keuangan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation example" class="mt-4 d-none d-md-block">
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
            <?php endif; ?>

                <div class="d-md-none">
                    <?php if (count($keuangan) > 0): ?>
                        <?php foreach ($keuangan as $row): ?>
                            <div class="card-item">
                                <div>
                                    <span class="label">ID</span>
                                    <span class="value"><?= htmlspecialchars($row['id']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Jenis Transaksi</span>
                                    <span class="value">
                                        <span class="badge rounded-pill <?= ($row['jenis_transaksi'] == 'pemasukan') ? 'bg-success' : 'bg-danger' ?>">
                                            <?= htmlspecialchars(ucfirst($row['jenis_transaksi'])) ?>
                                        </span>
                                    </span>
                                </div>
                                <div>
                                    <span class="label">Jumlah</span>
                                    <span class="value">Rp<?= htmlspecialchars(number_format($row['jumlah'], 0, ',', '.')) ?></span>
                                </div>
                                <div>
                                    <span class="label">Deskripsi</span>
                                    <span class="value"><?= htmlspecialchars($row['deskripsi']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Tanggal</span>
                                    <span class="value"><?= htmlspecialchars($row['tanggal_transaksi']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Dicatat Oleh</span>
                                    <span class="value"><?= htmlspecialchars($row['dicatat_oleh_nama']) ?></span>
                                </div>
                                <div class="btn-group">
                                    <button class="btn-action btn-warning-custom edit-btn" data-bs-toggle="modal" data-bs-target="#editKeuanganModal" data-id="<?= $row['id'] ?>" data-jenis="<?= $row['jenis_transaksi'] ?>" data-jumlah="<?= $row['jumlah'] ?>" data-deskripsi="<?= $row['deskripsi'] ?>" data-tanggal="<?= $row['tanggal_transaksi'] ?>">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <form action="" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tab" value="keuangan">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted">Tidak ada data keuangan.</div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation example" class="mt-4 d-md-none">
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
            <?php endif; ?>
        <?php elseif ($active_tab == 'iuran'): ?>
            <h2 class="mb-4 text-primary"><i class="fa-solid fa-receipt me-2"></i>Kelola Data Iuran</h2>
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fa-solid fa-calendar-alt"></i></span>
                        <select class="form-select" onchange="window.location.href = '?tab=<?= $active_tab ?>&year=' + this.value + '<?= !empty($searchTerm) ? '&search=' . urlencode($searchTerm) : '' ?>'">
                            <?php
                            $minYearQuery = "SELECT MIN(YEAR(tanggal_bayar)) AS min_year FROM iuran";
                            $minYearResult = $conn->query($minYearQuery);
                            $minYearRow = $minYearResult->fetch_assoc();
                            $minYear = $minYearRow['min_year'] ? $minYearRow['min_year'] : date('Y');
                            for ($year = date('Y'); $year >= $minYear; $year--):
                            ?>
                                <option value="<?= $year ?>" <?= ($year == $selectedYear) ? 'selected' : '' ?>>
                                    Tahun <?= $year ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <form action="" method="GET" class="d-inline-flex">
                        <input type="hidden" name="tab" value="iuran">
                        <input type="hidden" name="year" value="<?= $selectedYear ?>">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Cari iuran..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                            <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                            <?php if (!empty($searchTerm)): ?>
                                <a href="?tab=iuran&year=<?= $selectedYear ?>" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Total Pemasukan Iuran</h5>
                            <p class="card-text fs-4">Rp<?= number_format($totalIuran, 0, ',', '.') ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addIuranModal">
                <i class="fa-solid fa-plus-circle me-2"></i> Tambah Pembayaran Iuran
            </button>
            <div class="table-responsive">
                <table class="table table-hover table-striped d-none d-md-table">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">ID Anggota</th>
                            <th scope="col">Nama Anggota</th>
                            <th scope="col">Tanggal Bayar</th>
                            <th scope="col" class="text-end">Jumlah Bayar</th>
                            <th scope="col">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($iuran) > 0): ?>
                            <?php foreach ($iuran as $row): ?>
                                <?php
                                $anggotaName = 'Tidak Ditemukan';
                                // Periksa jika ada anggota_nama dari join (jika search term diterapkan)
                                if (isset($row['anggota_nama'])) {
                                    $anggotaName = $row['anggota_nama'];
                                } else {
                                    // Jika tidak ada join, cari manual dari anggotaList
                                    foreach ($anggotaList as $member) {
                                        if ($member['id'] == $row['anggota_id']) {
                                            $anggotaName = $member['nama_lengkap'];
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['id']) ?></td>
                                    <td><?= htmlspecialchars($row['anggota_id']) ?></td>
                                    <td><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($anggotaName) ?></td>
                                    <td><?= htmlspecialchars($row['tanggal_bayar']) ?></td>
                                    <td class="text-end">Rp<?= htmlspecialchars(number_format($row['jumlah_bayar'], 0, ',', '.')) ?></td>
                                    <td>
                                        <button class="btn-action btn-warning-custom me-2 edit-btn" data-bs-toggle="modal" data-bs-target="#editIuranModal" data-id="<?= $row['id'] ?>" data-anggota-id="<?= $row['anggota_id'] ?>" data-tanggal="<?= $row['tanggal_bayar'] ?>" data-jumlah="<?= $row['jumlah_bayar'] ?>" data-keterangan="<?= $row['keterangan'] ?>">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tab" value="iuran">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Tidak ada data iuran.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation example" class="mt-4 d-none d-md-block">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=iuran&year=<?= $selectedYear ?>&page=<?= $page - 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>" tabindex="-1">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?tab=iuran&year=<?= $selectedYear ?>&page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=iuran&year=<?= $selectedYear ?>&page=<?= $page + 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
                <div class="d-md-none">
                    <?php if (count($iuran) > 0): ?>
                        <?php foreach ($iuran as $row): ?>
                            <?php
                            $anggotaName = 'Tidak Ditemukan';
                            if (isset($row['anggota_nama'])) {
                                $anggotaName = $row['anggota_nama'];
                            } else {
                                foreach ($anggotaList as $member) {
                                    if ($member['id'] == $row['anggota_id']) {
                                        $anggotaName = $member['nama_lengkap'];
                                        break;
                                    }
                                }
                            }
                            ?>
                            <div class="card-item">
                                <div>
                                    <span class="label">ID</span>
                                    <span class="value"><?= htmlspecialchars($row['id']) ?></span>
                                </div>
                                <div>
                                    <span class="label">ID Anggota</span>
                                    <span class="value"><?= htmlspecialchars($row['anggota_id']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Nama Anggota</span>
                                    <span class="value"><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($anggotaName) ?></span>
                                </div>
                                <div>
                                    <span class="label">Tanggal Bayar</span>
                                    <span class="value"><?= htmlspecialchars($row['tanggal_bayar']) ?></span>
                                </div>
                                <div>
                                    <span class="label">Jumlah Bayar</span>
                                    <span class="value">Rp<?= htmlspecialchars(number_format($row['jumlah_bayar'], 0, ',', '.')) ?></span>
                                </div>
                                <div class="btn-group">
                                    <button class="btn-action btn-warning-custom edit-btn" data-bs-toggle="modal" data-bs-target="#editIuranModal" data-id="<?= $row['id'] ?>" data-anggota-id="<?= $row['anggota_id'] ?>" data-tanggal="<?= $row['tanggal_bayar'] ?>" data-jumlah="<?= $row['jumlah_bayar'] ?>" data-keterangan="<?= $row['keterangan'] ?>">
                                        <i class="fa-solid fa-edit"></i>
                                    </button>
                                    <form action="" method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="tab" value="iuran">
                                        <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-center text-muted">Tidak ada data iuran.</div>
                    <?php endif; ?>
                </div>
            </div>
             <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation example" class="mt-4 d-md-none">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=iuran&year=<?= $selectedYear ?>&page=<?= $page - 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>" tabindex="-1">Previous</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?tab=iuran&year=<?= $selectedYear ?>&page=<?= $i ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?tab=iuran&year=<?= $selectedYear ?>&page=<?= $page + 1 ?><?= !empty($searchTerm) ? '&search=' . htmlspecialchars($searchTerm) : '' ?>">Next</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
            <?php elseif ($active_tab == 'users'): ?>
                <h2 class="mb-4 text-primary"><i class="fa-solid fa-user-circle me-2"></i>Kelola Data Users</h2>
                <div class="row mb-3">
                    <div class="col-md-12 text-end">
                        <form action="" method="GET" class="d-inline-flex">
                            <input type="hidden" name="tab" value="users">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Cari user..." name="search" value="<?= htmlspecialchars($searchTerm) ?>">
                                <button class="btn btn-outline-primary" type="submit"><i class="fas fa-search"></i></button>
                                <?php if (!empty($searchTerm)): ?>
                                    <a href="?tab=users" class="btn btn-outline-secondary" title="Hapus Pencarian"><i class="fas fa-times"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
                <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addUsersModal">
                    <i class="fa-solid fa-plus-circle me-2"></i> Tambah User
                </button>
                <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addLokasiModal">
                    <i class="fa-solid fa-map-marker-alt me-2"></i> Atur Lokasi Absensi
                </button>
                <div class="table-responsive">
                    <table class="table table-hover table-striped d-none d-md-table">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">Username</th>
                                <th scope="col">Role</th>
                                <th scope="col">Anggota Terkait</th>
                                <th scope="col">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) > 0): ?>
                                <?php foreach ($users as $row): ?>
                                    <?php
                                    $anggotaName = 'Tidak Terkait';
                                    if ($row['anggota_id'] !== NULL) {
                                        foreach ($anggotaList as $member) {
                                            if ($member['id'] == $row['anggota_id']) {
                                                $anggotaName = $member['nama_lengkap'];
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($row['id']) ?></td>
                                        <td><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($row['username']) ?></td>
                                        <td><?= htmlspecialchars($row['role']) ?></td>
                                        <td><?= htmlspecialchars($anggotaName) ?></td>
                                        <td>
                                            <button class="btn-action btn-warning-custom me-2 edit-btn" data-bs-toggle="modal" data-bs-target="#editUsersModal" data-id="<?= $row['id'] ?>" data-username="<?= $row['username'] ?>" data-role="<?= $row['role'] ?>" data-anggota-id="<?= $row['anggota_id'] ?>">
                                                <i class="fa-solid fa-edit"></i>
                                            </button>
                                            <form action="" method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="tab" value="users">
                                                <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                                <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">Tidak ada data user.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    <div class="d-md-none">
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $row): ?>
                                <?php
                                $anggotaName = 'Tidak Terkait';
                                if ($row['anggota_id'] !== NULL) {
                                    foreach ($anggotaList as $member) {
                                        if ($member['id'] == $row['anggota_id']) {
                                            $anggotaName = $member['nama_lengkap'];
                                            break;
                                        }
                                    }
                                }
                                ?>
                                <div class="card-item">
                                    <div>
                                        <span class="label">ID</span>
                                        <span class="value"><?= htmlspecialchars($row['id']) ?></span>
                                    </div>
                                    <div>
                                        <span class="label">Username</span>
                                        <span class="value"><?= htmlspecialchars($row['username']) ?></span>
                                    </div>
                                    <div>
                                        <span class="label">Role</span>
                                        <span class="value"><?= htmlspecialchars($row['role']) ?></span>
                                    </div>
                                    <div>
                                        <span class="label">Anggota Terkait</span>
                                        <span class="value"><i class="fa-solid fa-user-circle me-2"></i><?= htmlspecialchars($anggotaName) ?></span>
                                    </div>
                                    <div class="btn-group">
                                        <button class="btn-action btn-warning-custom edit-btn" data-bs-toggle="modal" data-bs-target="#editUsersModal" data-id="<?= $row['id'] ?>" data-username="<?= $row['username'] ?>" data-role="<?= $row['role'] ?>" data-anggota-id="<?= $row['anggota_id'] ?>">
                                            <i class="fa-solid fa-edit"></i>
                                        </button>
                                        <form action="" method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tab" value="users">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" class="btn btn-danger btn-action" onclick="return confirm('Apakah Anda yakin ingin menghapus data ini?')"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted">Tidak ada data user.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

                <div class="modal fade" id="addLokasiModal" tabindex="-1" aria-labelledby="addLokasiModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="addLokasiModalLabel"><i class="fa-solid fa-map-marker-alt me-2"></i>Atur Lokasi Absensi</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form action="" method="POST">
                                <div class="modal-body">
                                    <input type="hidden" name="action" value="update_location">
                                    
                                    <div class="mb-3">
                                        <button type="button" class="btn btn-info" id="detect-device-location-btn">
                                            <i class="fas fa-crosshairs me-2"></i>Gunakan Lokasi Perangkat Sekarang
                                        </button>
                                    </div>

                                    <div class="mb-3">
                                        <label for="lokasi_latitude" class="form-label">Latitude</label>
                                        <input type="text" class="form-control" id="lokasi_latitude" name="lokasi_latitude" required value="<?= htmlspecialchars($current_latitude) ?>" readonly>
                                        <div class="form-text">Latitude akan otomatis terisi setelah deteksi lokasi perangkat.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="lokasi_longitude" class="form-label">Longitude</label>
                                        <input type="text" class="form-control" id="lokasi_longitude" name="lokasi_longitude" required value="<?= htmlspecialchars($current_longitude) ?>" readonly>
                                        <div class="form-text">Longitude akan otomatis terisi setelah deteksi lokasi perangkat.</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="jarak_toleransi" class="form-label">Jarak Toleransi (meter)</label>
                                        <input type="number" class="form-control" id="jarak_toleransi" name="jarak_toleransi" required value="<?= htmlspecialchars($current_tolerance) ?>">
                                        <div class="form-text">Jarak maksimal dari lokasi yang diizinkan untuk absensi.</div>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-primary">Simpan Lokasi</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>      
            </div>
        </div>      
    </div>
</div>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toast-container"></div>
<div class="modal fade" id="addAnggotaModal" tabindex="-1" aria-labelledby="addAnggotaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAnggotaModalLabel">Tambah Anggota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tab" value="anggota">
                    <div class="mb-3">
                        <label for="add-nama-lengkap" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="add-nama-lengkap" name="data[nama_lengkap]" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-jabatan" class="form-label">Jabatan</label>
                        <select class="form-control" id="add-jabatan" name="data[jabatan]" required>
                            <option value="">-- Pilih Jabatan --</option>
                            <?php foreach (JABATAN_OPTIONS as $jabatan): ?>
                                <option value="<?= htmlspecialchars($jabatan) ?>">
                                    <?= htmlspecialchars($jabatan) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add-bergabung-sejak" class="form-label">Bergabung Sejak</label>
                        <input type="date" class="form-control" id="add-bergabung-sejak" name="data[bergabung_sejak]" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editAnggotaModal" tabindex="-1" aria-labelledby="editAnggotaModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editAnggotaModalLabel">Edit Anggota</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tab" value="anggota">
                    <input type="hidden" name="id" id="edit-anggota-id">
                    <div class="mb-3">
                        <label for="edit-nama-lengkap" class="form-label">Nama Lengkap</label>
                        <input type="text" class="form-control" id="edit-nama-lengkap" name="data[nama_lengkap]" required>
                    </div>
                    <?php 
                    $jabatan_anggota = isset($anggota['jabatan']) ? $anggota['jabatan'] : '';
                    ?>
                    <div class="mb-3">
                        <label for="edit-jabatan" class="form-label">Jabatan</label>
                        <select class="form-control" id="edit-jabatan" name="data[jabatan]" required>
                            <option value="">-- Pilih Jabatan --</option>
                            <?php foreach (JABATAN_OPTIONS as $jabatan): ?>
                                <option value="<?= htmlspecialchars($jabatan) ?>" 
                                    <?= ($jabatan_anggota === $jabatan) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($jabatan) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-bergabung-sejak" class="form-label">Bergabung Sejak</label>
                        <input type="date" class="form-control" id="edit-bergabung-sejak" name="data[bergabung_sejak]" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addKegiatanModal" tabindex="-1" aria-labelledby="addKegiatanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addKegiatanModalLabel">Tambah Kegiatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tab" value="kegiatan">
                    <div class="mb-3">
                        <label for="add-nama-kegiatan" class="form-label">Nama Kegiatan</label>
                        <input type="text" class="form-control" id="add-nama-kegiatan" name="data[nama_kegiatan]" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="add-deskripsi" name="data[deskripsi]" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="add-lokasi" class="form-label">Lokasi</label>
                        <input type="text" class="form-control" id="add-lokasi" name="data[lokasi]">
                    </div>
                    <div class="mb-3">
                        <label for="add-tanggal-mulai" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="add-tanggal-mulai" name="data[tanggal_mulai]" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editKegiatanModal" tabindex="-1" aria-labelledby="editKegiatanModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editKegiatanModalLabel">Edit Kegiatan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tab" value="kegiatan">
                    <input type="hidden" name="id" id="edit-kegiatan-id">
                    <div class="mb-3">
                        <label for="edit-nama-kegiatan" class="form-label">Nama Kegiatan</label>
                        <input type="text" class="form-control" id="edit-nama-kegiatan" name="data[nama_kegiatan]" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-deskripsi" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit-deskripsi" name="data[deskripsi]" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-lokasi" class="form-label">Lokasi</label>
                        <input type="text" class="form-control" id="edit-lokasi" name="data[lokasi]">
                    </div>
                    <div class="mb-3">
                        <label for="edit-tanggal-mulai" class="form-label">Tanggal Mulai</label>
                        <input type="date" class="form-control" id="edit-tanggal-mulai" name="data[tanggal_mulai]" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addKeuanganModal" tabindex="-1" aria-labelledby="addKeuanganModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addKeuanganModalLabel">Tambah Transaksi Keuangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tab" value="keuangan">
                    <div class="mb-3">
                        <label for="add-jenis-transaksi" class="form-label">Jenis Transaksi</label>
                        <select class="form-select" id="add-jenis-transaksi" name="data[jenis_transaksi]" required>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add-jumlah-keuangan" class="form-label">Jumlah</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="add-jumlah-keuangan" name="data[jumlah]" required min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="add-deskripsi-keuangan" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="add-deskripsi-keuangan" name="data[deskripsi]" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="add-tanggal-transaksi" class="form-label">Tanggal Transaksi</label>
                        <input type="date" class="form-control" id="add-tanggal-transaksi" name="data[tanggal_transaksi]" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editKeuanganModal" tabindex="-1" aria-labelledby="editKeuanganModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editKeuanganModalLabel">Edit Transaksi Keuangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tab" value="keuangan">
                    <input type="hidden" name="id" id="edit-keuangan-id">
                    <div class="mb-3">
                        <label for="edit-jenis-transaksi" class="form-label">Jenis Transaksi</label>
                        <select class="form-select" id="edit-jenis-transaksi" name="data[jenis_transaksi]" required>
                            <option value="pemasukan">Pemasukan</option>
                            <option value="pengeluaran">Pengeluaran</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-jumlah-keuangan" class="form-label">Jumlah</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="edit-jumlah-keuangan" name="data[jumlah]" required min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-deskripsi-keuangan" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="edit-deskripsi-keuangan" name="data[deskripsi]" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-tanggal-transaksi" class="form-label">Tanggal Transaksi</label>
                        <input type="date" class="form-control" id="edit-tanggal-transaksi" name="data[tanggal_transaksi]" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addIuranModal" tabindex="-1" aria-labelledby="addIuranModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addIuranModalLabel">Tambah Pembayaran Iuran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST" id="addIuranForm">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tab" value="iuran">

                    <div class="mb-3">
                        <label for="search-anggota-iuran" class="form-label">Nama Anggota</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="search-anggota-iuran" placeholder="Cari anggota...">
                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                        </div>
                        <div id="search-results-iuran" class="list-group mt-2" style="max-height: 200px; overflow-y: auto;">
                            </div>
                        <input type="hidden" name="data[anggota_id]" id="add-anggota-id-iuran" required>
                    </div>

                    <div class="mb-3">
                        <label for="add-tanggal-bayar" class="form-label">Tanggal Bayar</label>
                        <input type="date" class="form-control" id="add-tanggal-bayar" name="data[tanggal_bayar]" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-jumlah-iuran" class="form-label">Jumlah Bayar</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="add-jumlah-iuran" name="data[jumlah_bayar]" required min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="add-keterangan" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="add-keterangan" name="data[keterangan]" rows="2"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editIuranModal" tabindex="-1" aria-labelledby="editIuranModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editIuranModalLabel">Edit Pembayaran Iuran</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tab" value="iuran">
                    <input type="hidden" name="id" id="edit-iuran-id">
                    <div class="mb-3">
                        <label for="edit-anggota-id" class="form-label">Nama Anggota</label>
                        <select class="form-select" id="edit-anggota-id" name="data[anggota_id]" required>
                            <option value="">Pilih Anggota</option>
                            <?php foreach ($anggotaList as $anggota): ?>
                                <option value="<?= $anggota['id'] ?>"><?= htmlspecialchars($anggota['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-tanggal-bayar" class="form-label">Tanggal Bayar</label>
                        <input type="date" class="form-control" id="edit-tanggal-bayar" name="data[tanggal_bayar]" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-jumlah-iuran" class="form-label">Jumlah Bayar</label>
                        <div class="input-group">
                            <span class="input-group-text">Rp</span>
                            <input type="number" class="form-control" id="edit-jumlah-iuran" name="data[jumlah_bayar]" required min="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="edit-keterangan" class="form-label">Keterangan</label>
                        <textarea class="form-control" id="edit-keterangan" name="data[keterangan]" rows="2"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addUsersModal" tabindex="-1" aria-labelledby="addUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUsersModalLabel">Tambah User Baru</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="tab" value="users">
                    <div class="mb-3">
                        <label for="add-username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="add-username" name="data[username]" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="add-password" name="data[password]" required>
                    </div>
                    <div class="mb-3">
                        <label for="add-role" class="form-label">Role</label>
                        <select class="form-select" id="add-role" name="data[role]" required>
                            <option value="anggota">Anggota</option>
                            <option value="sekretaris">Sekretaris</option>
                            <option value="bendahara">Bendahara</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="add-anggota-id-user" class="form-label">Anggota Terkait (opsional)</label>
                        <select class="form-select" id="add-anggota-id-user" name="data[anggota_id]">
                            <option value="">Tidak Terkait</option>
                            <?php foreach ($anggotaList as $anggota): ?>
                                <option value="<?= $anggota['id'] ?>"><?= htmlspecialchars($anggota['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editUsersModal" tabindex="-1" aria-labelledby="editUsersModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editUsersModalLabel">Edit User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="tab" value="users">
                    <input type="hidden" name="id" id="edit-users-id">
                    <div class="mb-3">
                        <label for="edit-username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="edit-username" name="data[username]" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-password" class="form-label">Password (kosongkan jika tidak diubah)</label>
                        <input type="password" class="form-control" id="edit-password" name="data[password]">
                    </div>
                    <div class="mb-3">
                        <label for="edit-role" class="form-label">Role</label>
                        <select class="form-select" id="edit-role" name="data[role]" required>
                            <option value="anggota">Anggota</option>
                            <option value="sekretaris">Sekretaris</option>
                            <option value="bendahara">Bendahara</option>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit-anggota-id-user" class="form-label">Anggota Terkait (opsional)</label>
                        <select class="form-select" id="edit-anggota-id-user" name="data[anggota_id]">
                            <option value="">Tidak Terkait</option>
                            <?php foreach ($anggotaList as $anggota): ?>
                                <option value="<?= $anggota['id'] ?>"><?= htmlspecialchars($anggota['nama_lengkap']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Logika untuk mengisi data modal Edit Anggota
        var editAnggotaModal = document.getElementById('editAnggotaModal');
        editAnggotaModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');
            var jabatan = button.getAttribute('data-jabatan');
            var sejak = button.getAttribute('data-sejak');
            var modal = this;
            modal.querySelector('#edit-anggota-id').value = id;
            modal.querySelector('#edit-nama-lengkap').value = nama;
            modal.querySelector('#edit-jabatan').value = jabatan;
            modal.querySelector('#edit-bergabung-sejak').value = sejak;
        });

        // Logika untuk mengisi data modal Edit Kegiatan
        var editKegiatanModal = document.getElementById('editKegiatanModal');
        editKegiatanModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var nama = button.getAttribute('data-nama');
            var deskripsi = button.getAttribute('data-deskripsi');
            var lokasi = button.getAttribute('data-lokasi');
            var tanggal = button.getAttribute('data-tanggal');
            var modal = this;
            modal.querySelector('#edit-kegiatan-id').value = id;
            modal.querySelector('#edit-nama-kegiatan').value = nama;
            modal.querySelector('#edit-deskripsi').value = deskripsi;
            modal.querySelector('#edit-lokasi').value = lokasi;
            modal.querySelector('#edit-tanggal-mulai').value = tanggal;
        });

        // Logika untuk mengisi data modal Edit Keuangan
        var editKeuanganModal = document.getElementById('editKeuanganModal');
        editKeuanganModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var jenis = button.getAttribute('data-jenis');
            var jumlah = button.getAttribute('data-jumlah');
            var deskripsi = button.getAttribute('data-deskripsi');
            var tanggal = button.getAttribute('data-tanggal');
            var modal = this;
            modal.querySelector('#edit-keuangan-id').value = id;
            modal.querySelector('#edit-jenis-transaksi').value = jenis;
            modal.querySelector('#edit-jumlah-keuangan').value = jumlah;
            modal.querySelector('#edit-deskripsi-keuangan').value = deskripsi;
            modal.querySelector('#edit-tanggal-transaksi').value = tanggal;
        });

        // Logika untuk mengisi data modal Edit Iuran
        var editIuranModal = document.getElementById('editIuranModal');
        editIuranModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var anggotaId = button.getAttribute('data-anggota-id');
            var tanggal = button.getAttribute('data-tanggal');
            var jumlah = button.getAttribute('data-jumlah');
            var keterangan = button.getAttribute('data-keterangan');
            var modal = this;
            modal.querySelector('#edit-iuran-id').value = id;
            modal.querySelector('#edit-anggota-id').value = anggotaId;
            modal.querySelector('#edit-tanggal-bayar').value = tanggal;
            modal.querySelector('#edit-jumlah-iuran').value = jumlah;
            modal.querySelector('#edit-keterangan').value = keterangan;
        });

        // Logika untuk mengisi data modal Edit Users
        var editUsersModal = document.getElementById('editUsersModal');
        editUsersModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var id = button.getAttribute('data-id');
            var username = button.getAttribute('data-username');
            var role = button.getAttribute('data-role');
            var anggotaId = button.getAttribute('data-anggota-id');
            var modal = this;
            modal.querySelector('#edit-users-id').value = id;
            modal.querySelector('#edit-username').value = username;
            modal.querySelector('#edit-role').value = role;
            modal.querySelector('#edit-anggota-id-user').value = anggotaId;
            modal.querySelector('#edit-password').value = ''; // Kosongkan password untuk keamanan
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Ambil daftar anggota dari PHP dan konversi ke JavaScript
        const anggotaList = <?= json_encode($anggotaList) ?>;

        const searchInput = document.getElementById('search-anggota-iuran');
        const searchResultsDiv = document.getElementById('search-results-iuran');
        const anggotaIdInput = document.getElementById('add-anggota-id-iuran');
        const form = document.getElementById('addIuranForm');

        // Fungsi untuk menampilkan hasil pencarian
        const displayResults = (results) => {
            searchResultsDiv.innerHTML = ''; // Hapus hasil sebelumnya
            if (results.length > 0) {
                results.forEach(anggota => {
                    const resultItem = document.createElement('a');
                    resultItem.href = '#';
                    resultItem.classList.add('list-group-item', 'list-group-item-action');
                    resultItem.textContent = anggota.nama_lengkap;
                    resultItem.setAttribute('data-id', anggota.id);
                    searchResultsDiv.appendChild(resultItem);
                });
                searchResultsDiv.style.display = 'block';
            } else {
                searchResultsDiv.style.display = 'none';
            }
        };

        // Event listener saat pengguna mengetik
        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            if (query.length > 0) {
                const filteredResults = anggotaList.filter(anggota =>
                    anggota.nama_lengkap.toLowerCase().includes(query)
                );
                displayResults(filteredResults);
            } else {
                searchResultsDiv.innerHTML = '';
                searchResultsDiv.style.display = 'none';
            }
        });

        // Event listener saat hasil pencarian diklik
        searchResultsDiv.addEventListener('click', function(e) {
            if (e.target.tagName === 'A') {
                e.preventDefault();
                const selectedId = e.target.getAttribute('data-id');
                const selectedNama = e.target.textContent;

                // Isi input pencarian dan input tersembunyi
                anggotaIdInput.value = selectedId;
                searchInput.value = selectedNama;

                // Sembunyikan hasil pencarian
                searchResultsDiv.innerHTML = '';
                searchResultsDiv.style.display = 'none';
            }
        });
        
        // Sembunyikan hasil pencarian jika klik di luar area input
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !searchResultsDiv.contains(e.target)) {
                searchResultsDiv.style.display = 'none';
            }
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const detectBtn = document.getElementById('detect-coords-btn');
        const gmapsUrlInput = document.getElementById('gmaps-url');
        const latitudeInput = document.getElementById('lokasi_latitude');
        const longitudeInput = document.getElementById('lokasi_longitude');

        detectBtn.addEventListener('click', function() {
            const gmapsUrl = gmapsUrlInput.value;
            const regex = /@(-?\d+\.\d+),(-?\d+\.\d+)/;
            const match = gmapsUrl.match(regex);
            
            let message = "";
            let isSuccess = false;

            if (match && match.length >= 3) {
                const lat = match[1];
                const lon = match[2];

                latitudeInput.value = lat;
                longitudeInput.value = lon;
                
                message = "Koordinat berhasil dideteksi dan diisi.";
                isSuccess = true;
            } else {
                message = "Tautan Google Maps tidak valid. Pastikan tautan memiliki format yang benar.";
                isSuccess = false;
            }

            // Tampilkan notifikasi toast
            const toastContainer = document.getElementById('toast-container');
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white ${isSuccess ? 'bg-success' : 'bg-danger'} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            toastContainer.appendChild(toast);
            const bootstrapToast = new bootstrap.Toast(toast);
            bootstrapToast.show();
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const detectDeviceLocationBtn = document.getElementById('detect-device-location-btn');
        const latitudeInput = document.getElementById('lokasi_latitude');
        const longitudeInput = document.getElementById('lokasi_longitude');
        const toastContainer = document.getElementById('toast-container'); // Pastikan ini ada di HTML Anda

        detectDeviceLocationBtn.addEventListener('click', function() {
            // Periksa apakah browser mendukung Geolocation API
            if (navigator.geolocation) {
                // Tampilkan pesan loading
                showToast("Mencari lokasi perangkat...", false, 'bg-secondary');

                // Dapatkan lokasi saat ini
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        // Callback sukses
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;

                        latitudeInput.value = lat;
                        longitudeInput.value = lon;
                        
                        showToast("Lokasi perangkat berhasil dideteksi dan diisi.", true);
                    },
                    function(error) {
                        // Callback error
                        let errorMessage = "Gagal mendeteksi lokasi perangkat: ";
                        switch(error.code) {
                            case error.PERMISSION_DENIED:
                                errorMessage += "Pengguna menolak permintaan Geolocation.";
                                break;
                            case error.POSITION_UNAVAILABLE:
                                errorMessage += "Informasi lokasi tidak tersedia.";
                                break;
                            case error.TIMEOUT:
                                errorMessage += "Permintaan lokasi habis waktu.";
                                break;
                            case error.UNKNOWN_ERROR:
                                errorMessage += "Terjadi kesalahan tidak dikenal.";
                                break;
                        }
                        showToast(errorMessage, false);
                    },
                    {
                        // Opsi konfigurasi Geolocation
                        enableHighAccuracy: true, // Mencoba mendapatkan lokasi seakurat mungkin
                        timeout: 10000,           // Waktu maksimum (ms) untuk menunggu hasil
                        maximumAge: 0             // Jangan gunakan cache lokasi yang sudah tua
                    }
                );
            } else {
                showToast("Browser Anda tidak mendukung Geolocation API.", false);
            }
        });

        // Fungsi bantu untuk menampilkan Toast
        function showToast(message, isSuccess, customBgClass = null) {
            const toast = document.createElement('div');
            let bgClass = customBgClass ? customBgClass : (isSuccess ? 'bg-success' : 'bg-danger');

            toast.className = `toast align-items-center text-white ${bgClass} border-0`;
            toast.setAttribute('role', 'alert');
            toast.setAttribute('aria-live', 'assertive');
            toast.setAttribute('aria-atomic', 'true');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            `;
            toastContainer.appendChild(toast);
            const bootstrapToast = new bootstrap.Toast(toast, { delay: 5000 }); // Toast hilang setelah 5 detik
            bootstrapToast.show();
            // Hapus toast dari DOM setelah disembunyikan untuk menjaga kebersihan
            toast.addEventListener('hidden.bs.toast', function () {
                toast.remove();
            });
        }
    });
</script>
</body>
</html>