-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 20, 2025 at 05:34 AM
-- Server version: 8.0.30
-- PHP Version: 8.3.21

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `karangtaruna_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `absensi`
--

CREATE TABLE `absensi` (
  `id` int NOT NULL,
  `anggota_id` int NOT NULL,
  `tanggal_absen` datetime NOT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `absensi`
--

INSERT INTO `absensi` (`id`, `anggota_id`, `tanggal_absen`, `latitude`, `longitude`) VALUES
(11, 3, '2025-08-20 11:08:14', -7.52745950, 110.62882260);

-- --------------------------------------------------------

--
-- Table structure for table `anggota`
--

CREATE TABLE `anggota` (
  `id` int NOT NULL,
  `telegram_id` bigint DEFAULT NULL,
  `nama_lengkap` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `tempat_lahir` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text COLLATE utf8mb4_general_ci,
  `no_hp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `jabatan` varchar(100) COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'Anggota',
  `status_aktif` tinyint(1) NOT NULL DEFAULT '1',
  `bergabung_sejak` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `username` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `anggota`
--

INSERT INTO `anggota` (`id`, `telegram_id`, `nama_lengkap`, `tempat_lahir`, `tanggal_lahir`, `alamat`, `no_hp`, `jabatan`, `status_aktif`, `bergabung_sejak`, `created_at`, `username`) VALUES
(1, NULL, 'Tisu A', NULL, NULL, NULL, NULL, 'Humas', 1, '2025-08-17', '2025-08-19 04:10:03', NULL),
(2, NULL, 'Tisa', NULL, NULL, NULL, NULL, 'Anggota', 1, '2025-08-17', '2025-08-19 04:35:24', NULL),
(3, NULL, 'Tes 5', NULL, NULL, NULL, NULL, 'Ketua', 1, '2025-08-19', '2025-08-19 04:40:52', NULL),
(4, NULL, 'Tisa B', NULL, NULL, NULL, NULL, 'Wakil Ketua', 1, '2025-08-19', '2025-08-19 04:41:05', NULL),
(5, NULL, 'Tisa Anggre', NULL, NULL, NULL, NULL, 'Bendahara', 1, '2025-08-19', '2025-08-19 04:41:15', NULL),
(6, NULL, 'Tes 1', NULL, NULL, NULL, NULL, 'Anggota', 1, '2025-08-19', '2025-08-19 04:41:39', NULL),
(7, NULL, 'Tes 3', NULL, NULL, NULL, NULL, 'Sekretaris', 1, '2025-08-19', '2025-08-19 04:43:35', NULL),
(8, NULL, 'Tes 2', NULL, NULL, NULL, NULL, 'Anggota', 1, '2025-08-19', '2025-08-19 04:43:56', NULL),
(9, NULL, 'Tes 4', NULL, NULL, NULL, NULL, 'Anggota', 1, '2025-08-19', '2025-08-19 04:44:09', NULL),
(10, NULL, 'Tes 3', NULL, NULL, NULL, NULL, 'Anggota', 1, '2025-08-19', '2025-08-19 04:44:19', NULL),
(11, NULL, 'tes 6', NULL, NULL, NULL, NULL, 'Anggota', 1, '2025-08-19', '2025-08-19 04:44:30', NULL),
(12, NULL, 'tiu', NULL, NULL, NULL, NULL, 'Anggota', 1, '2025-08-18', '2025-08-19 07:27:21', NULL);

--
-- Triggers `anggota`
--
DELIMITER $$
CREATE TRIGGER `set_default_bergabung_sejak` BEFORE INSERT ON `anggota` FOR EACH ROW BEGIN
  IF NEW.bergabung_sejak IS NULL THEN
    SET NEW.bergabung_sejak = CURDATE();
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `bot_config`
--

CREATE TABLE `bot_config` (
  `id` int NOT NULL,
  `config_key` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `config_value` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ip_attendance_cooldown`
--

CREATE TABLE `ip_attendance_cooldown` (
  `ip_address` varchar(45) NOT NULL,
  `last_attempt_time` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ip_attendance_cooldown`
--

INSERT INTO `ip_attendance_cooldown` (`ip_address`, `last_attempt_time`) VALUES
('::1', '2025-08-20 11:08:14');

-- --------------------------------------------------------

--
-- Table structure for table `iuran`
--

CREATE TABLE `iuran` (
  `id` int NOT NULL,
  `anggota_id` int NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `periode` date NOT NULL,
  `jumlah_bayar` decimal(10,2) NOT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `iuran`
--

INSERT INTO `iuran` (`id`, `anggota_id`, `tanggal_bayar`, `periode`, `jumlah_bayar`, `keterangan`, `created_at`) VALUES
(6, 6, '2025-08-19', '2025-08-19', 5000.00, '', '2025-08-19 08:37:28'),
(7, 6, '2025-08-12', '2025-08-12', 50000.00, '', '2025-08-19 08:38:57'),
(8, 2, '2025-08-19', '2025-08-19', 50000.00, '', '2025-08-19 14:58:37'),
(9, 4, '2025-08-19', '2025-08-19', 5000.00, '', '2025-08-19 14:58:52'),
(10, 4, '2025-08-17', '2025-08-17', 5000.00, '', '2025-08-19 15:00:06'),
(11, 4, '2025-08-04', '2025-08-04', 5000.00, '', '2025-08-19 15:00:26'),
(12, 5, '2025-08-19', '2025-08-19', 5000.00, '', '2025-08-19 15:00:44'),
(13, 5, '2025-07-29', '2025-07-29', 5000.00, '', '2025-08-19 15:01:08'),
(14, 9, '2025-08-19', '2025-08-19', 5000.00, '', '2025-08-19 15:01:29'),
(16, 6, '2025-07-22', '2025-07-22', 5000.00, '', '2025-08-19 15:02:04'),
(17, 8, '2025-08-19', '2025-08-19', 5000.00, '', '2025-08-19 15:02:25'),
(18, 7, '2025-08-19', '2025-08-19', 5000.00, '', '2025-08-19 15:02:38');

-- --------------------------------------------------------

--
-- Table structure for table `kegiatan`
--

CREATE TABLE `kegiatan` (
  `id` int NOT NULL,
  `nama_kegiatan` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci,
  `tanggal_mulai` datetime NOT NULL,
  `tanggal_selesai` datetime DEFAULT NULL,
  `lokasi` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `penanggung_jawab_id` int DEFAULT NULL,
  `status_kegiatan` enum('terencana','berlangsung','selesai','ditunda','dibatalkan') COLLATE utf8mb4_general_ci NOT NULL DEFAULT 'terencana',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `keuangan`
--

CREATE TABLE `keuangan` (
  `id` int NOT NULL,
  `jenis_transaksi` enum('pemasukan','pengeluaran') COLLATE utf8mb4_general_ci NOT NULL,
  `jumlah` decimal(15,2) NOT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci NOT NULL,
  `tanggal_transaksi` date NOT NULL,
  `kegiatan_id` int DEFAULT NULL,
  `dicatat_oleh_id` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `keuangan`
--

INSERT INTO `keuangan` (`id`, `jenis_transaksi`, `jumlah`, `deskripsi`, `tanggal_transaksi`, `kegiatan_id`, `dicatat_oleh_id`, `created_at`) VALUES
(1, 'pemasukan', 500000.00, 'Kas', '2025-08-19', NULL, 1, '2025-08-19 16:52:50'),
(3, 'pengeluaran', 20000.00, 'tes', '2025-08-20', NULL, 1, '2025-08-20 05:09:46');

-- --------------------------------------------------------

--
-- Table structure for table `lokasi_absensi`
--

CREATE TABLE `lokasi_absensi` (
  `id` int NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `toleransi_jarak` int NOT NULL DEFAULT '50'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lokasi_absensi`
--

INSERT INTO `lokasi_absensi` (`id`, `latitude`, `longitude`, `toleransi_jarak`) VALUES
(1, -6.98380930, 110.40998930, 50);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `role` enum('sekretaris','bendahara','admin','superadmin') COLLATE utf8mb4_general_ci NOT NULL,
  `anggota_id` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `anggota_id`) VALUES
(1, 'nxr', 'nxr25', 'superadmin', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `anggota_id` (`anggota_id`);

--
-- Indexes for table `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `telegram_id` (`telegram_id`);

--
-- Indexes for table `bot_config`
--
ALTER TABLE `bot_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `config_key` (`config_key`);

--
-- Indexes for table `ip_attendance_cooldown`
--
ALTER TABLE `ip_attendance_cooldown`
  ADD PRIMARY KEY (`ip_address`);

--
-- Indexes for table `iuran`
--
ALTER TABLE `iuran`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_payment` (`anggota_id`,`periode`);

--
-- Indexes for table `kegiatan`
--
ALTER TABLE `kegiatan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `penanggung_jawab_id` (`penanggung_jawab_id`);

--
-- Indexes for table `keuangan`
--
ALTER TABLE `keuangan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kegiatan_id` (`kegiatan_id`),
  ADD KEY `dicatat_oleh_id` (`dicatat_oleh_id`);

--
-- Indexes for table `lokasi_absensi`
--
ALTER TABLE `lokasi_absensi`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `bot_config`
--
ALTER TABLE `bot_config`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `iuran`
--
ALTER TABLE `iuran`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `kegiatan`
--
ALTER TABLE `kegiatan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `keuangan`
--
ALTER TABLE `keuangan`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lokasi_absensi`
--
ALTER TABLE `lokasi_absensi`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `iuran`
--
ALTER TABLE `iuran`
  ADD CONSTRAINT `iuran_ibfk_1` FOREIGN KEY (`anggota_id`) REFERENCES `anggota` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kegiatan`
--
ALTER TABLE `kegiatan`
  ADD CONSTRAINT `fk_kegiatan_pj` FOREIGN KEY (`penanggung_jawab_id`) REFERENCES `anggota` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `keuangan`
--
ALTER TABLE `keuangan`
  ADD CONSTRAINT `fk_keuangan_dicatat_oleh` FOREIGN KEY (`dicatat_oleh_id`) REFERENCES `anggota` (`id`),
  ADD CONSTRAINT `fk_keuangan_kegiatan` FOREIGN KEY (`kegiatan_id`) REFERENCES `kegiatan` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
