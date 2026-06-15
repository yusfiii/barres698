-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 12, 2026 at 12:19 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `barres698_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `anggota`
--

CREATE TABLE `anggota` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `no_kontak` varchar(15) DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `bpk_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bpk`
--

CREATE TABLE `bpk` (
  `id` int(11) NOT NULL,
  `nama_bpk` varchar(100) NOT NULL,
  `alamat` text DEFAULT NULL,
  `tahun_berdiri` year(4) DEFAULT NULL,
  `nama_ketua` varchar(100) DEFAULT NULL,
  `jumlah_anggota` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `bpk`
--

INSERT INTO `bpk` (`id`, `nama_bpk`, `alamat`, `tahun_berdiri`, `nama_ketua`, `jumlah_anggota`, `created_at`) VALUES
(1, 'BPK Banjarbaru Utara', 'Jl. A Yani Km 35, Banjarbaru', '2015', 'Ahmad Fauzi', 45, '2026-04-12 09:58:36'),
(2, 'BPK Banjarbaru Selatan', 'Jl. Trikora No 12, Banjarbaru', '2016', 'Siti Rahmah', 38, '2026-04-12 09:58:36'),
(3, 'BPK Cempaka', 'Jl. Cempaka Raya No 5, Banjarbaru', '2018', 'Muhammad Ridwan', 30, '2026-04-12 09:58:36');

-- --------------------------------------------------------

--
-- Table structure for table `heatmap_settings`
--

CREATE TABLE `heatmap_settings` (
  `id` int(11) NOT NULL,
  `radius` int(11) DEFAULT 25,
  `blur` int(11) DEFAULT 15,
  `intensity` int(11) DEFAULT 70,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `heatmap_settings`
--

INSERT INTO `heatmap_settings` (`id`, `radius`, `blur`, `intensity`, `updated_at`) VALUES
(1, 25, 15, 70, '2026-04-12 09:58:36');

-- --------------------------------------------------------

--
-- Table structure for table `kejadian_kebakaran`
--

CREATE TABLE `kejadian_kebakaran` (
  `id` int(11) NOT NULL,
  `waktu` datetime NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `alamat` text NOT NULL,
  `kecamatan` varchar(50) DEFAULT NULL,
  `kelurahan` varchar(50) DEFAULT NULL,
  `jumlah_bangunan` int(11) DEFAULT 0,
  `jumlah_KK` int(11) DEFAULT 0,
  `jumlah_individu` int(11) DEFAULT 0,
  `korban_luka` int(11) DEFAULT 0,
  `korban_jiwa` int(11) DEFAULT 0,
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kejadian_kebakaran`
--

INSERT INTO `kejadian_kebakaran` (`id`, `waktu`, `latitude`, `longitude`, `alamat`, `kecamatan`, `kelurahan`, `jumlah_bangunan`, `jumlah_KK`, `jumlah_individu`, `korban_luka`, `korban_jiwa`, `foto`, `created_at`) VALUES
(1, '2024-01-15 14:30:00', -3.45800000, 114.82500000, 'Jl. Panglima Batur No 45', 'Banjarbaru Utara', 'Loktabat Utara', 3, 5, 18, 2, 0, NULL, '2026-04-12 09:58:37'),
(2, '2024-02-20 09:15:00', -3.46200000, 114.83200000, 'Jl. Karang Anyar No 12', 'Banjarbaru Utara', 'Mentaos', 1, 2, 7, 1, 0, NULL, '2026-04-12 09:58:37'),
(3, '2024-03-10 20:45:00', -3.47500000, 114.84500000, 'Jl. Trikora No 78', 'Banjarbaru Selatan', 'Sungai Besar', 2, 3, 11, 0, 1, NULL, '2026-04-12 09:58:37'),
(4, '2024-03-25 03:20:00', -3.48200000, 114.85000000, 'Jl. Cempaka No 34', 'Cempaka', 'Cempaka', 4, 8, 25, 3, 0, NULL, '2026-04-12 09:58:37'),
(5, '2024-04-05 11:00:00', -3.45500000, 114.81800000, 'Jl. A Yani Km 36', 'Banjarbaru Utara', 'Guntung Manggis', 1, 1, 4, 0, 0, NULL, '2026-04-12 09:58:37'),
(6, '2024-04-18 16:30:00', -3.46800000, 114.83800000, 'Jl. Pangeran Hidayat No 23', 'Banjarbaru Selatan', 'Loktabat Selatan', 2, 4, 14, 1, 0, NULL, '2026-04-12 09:58:37'),
(7, '2024-05-12 22:10:00', -3.49000000, 114.85500000, 'Jl. Banjarbaru Kusan No 8', 'Cempaka', 'Bangkal', 3, 6, 20, 2, 1, NULL, '2026-04-12 09:58:37');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `no_hp` varchar(15) DEFAULT NULL,
  `role` enum('super_admin','admin_bpk') NOT NULL,
  `bpk_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `no_hp`, `role`, `bpk_id`, `created_at`) VALUES
(1, 'superadmin', '$2y$10$YourHashedPasswordHere', '081234567890', 'super_admin', NULL, '2026-04-12 09:58:36'),
(2, 'admin_bpk1', '$2y$10$YourHashedPasswordHere', '081234567891', 'admin_bpk', 1, '2026-04-12 09:58:36'),
(3, 'admin_bpk2', '$2y$10$YourHashedPasswordHere', '081234567892', 'admin_bpk', 2, '2026-04-12 09:58:36'),
(4, 'admin_bpk3', '$2y$10$YourHashedPasswordHere', '081234567893', 'admin_bpk', 3, '2026-04-12 09:58:36');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `anggota`
--
ALTER TABLE `anggota`
  ADD PRIMARY KEY (`id`),
  ADD KEY `bpk_id` (`bpk_id`);

--
-- Indexes for table `bpk`
--
ALTER TABLE `bpk`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `heatmap_settings`
--
ALTER TABLE `heatmap_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kejadian_kebakaran`
--
ALTER TABLE `kejadian_kebakaran`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_location` (`latitude`,`longitude`),
  ADD KEY `idx_waktu` (`waktu`);

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
-- AUTO_INCREMENT for table `anggota`
--
ALTER TABLE `anggota`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bpk`
--
ALTER TABLE `bpk`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `heatmap_settings`
--
ALTER TABLE `heatmap_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `kejadian_kebakaran`
--
ALTER TABLE `kejadian_kebakaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `anggota`
--
ALTER TABLE `anggota`
  ADD CONSTRAINT `anggota_ibfk_1` FOREIGN KEY (`bpk_id`) REFERENCES `bpk` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
