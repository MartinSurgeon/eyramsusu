-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 02, 2026 at 07:03 PM
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
-- Database: `eyramsusu`
--

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `account_number` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `assigned_collector_id` int(11) DEFAULT NULL,
  `change_balance` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `account_number`, `full_name`, `phone`, `location`, `assigned_collector_id`, `change_balance`, `is_active`, `created_at`) VALUES
(1, '0035', 'kottoh Patience', '0242057910', 'Adaklu Waya', 2, 0.00, 1, '2026-09-02 15:29:52'),
(2, '0036', 'Soglo Vivian', '0592663701', 'Adaklu Waya', 2, 0.00, 1, '2026-09-02 15:29:52'),
(3, '0005', 'Kudi Lucky', '0545482671', 'Adaklu Waya', 2, 0.00, 1, '2026-09-02 15:29:52'),
(4, '0022', 'Wase Yaovi', '0241164340', 'Adaklu Waya', 2, 0.00, 1, '2026-09-02 15:29:52'),
(5, '0021', 'Kpedo Bismarck', '0546249032', 'Adaklu Waya', 2, 0.00, 1, '2026-09-02 15:29:52'),
(6, '0043', 'Anyadi Emmanuel', '0597515726', 'Adaklu Waya', 2, 0.00, 1, '2026-09-02 15:29:52'),
(7, '0004', 'Deku Wonder', '0249771299', 'Adaklu Waya', 2, 0.00, 1, '2026-09-02 15:29:52');

-- --------------------------------------------------------

--
-- Table structure for table `daily_handovers`
--

CREATE TABLE `daily_handovers` (
  `id` int(11) NOT NULL,
  `collector_id` int(11) NOT NULL,
  `handover_date` date NOT NULL,
  `expected_cash` decimal(10,2) NOT NULL,
  `cash_received` decimal(10,2) NOT NULL,
  `difference` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('submitted','approved','has_difference') NOT NULL DEFAULT 'submitted',
  `collector_note` text DEFAULT NULL,
  `admin_note` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deposits`
--

CREATE TABLE `deposits` (
  `id` int(11) NOT NULL,
  `card_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `collector_id` int(11) NOT NULL,
  `space_number` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `deposit_date` date NOT NULL,
  `handover_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payouts`
--

CREATE TABLE `payouts` (
  `id` int(11) NOT NULL,
  `card_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `collector_id` int(11) NOT NULL,
  `total_saved` decimal(10,2) NOT NULL,
  `business_fee` decimal(10,2) NOT NULL,
  `change_refunded` decimal(10,2) NOT NULL DEFAULT 0.00,
  `customer_payout` decimal(10,2) NOT NULL,
  `status` enum('pending','approved','paid') NOT NULL DEFAULT 'pending',
  `reason` varchar(255) DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `susu_cards`
--

CREATE TABLE `susu_cards` (
  `id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `card_number` int(11) NOT NULL DEFAULT 1,
  `daily_amount` decimal(10,2) NOT NULL,
  `total_spaces` int(11) NOT NULL DEFAULT 31,
  `spaces_filled` int(11) NOT NULL DEFAULT 0,
  `total_saved` decimal(10,2) NOT NULL DEFAULT 0.00,
  `status` enum('active','completed','closed_early') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `susu_cards`
--

INSERT INTO `susu_cards` (`id`, `customer_id`, `card_number`, `daily_amount`, `total_spaces`, `spaces_filled`, `total_saved`, `status`, `created_at`, `closed_at`) VALUES
(1, 1, 1, 50.00, 31, 0, 0.00, 'active', '2026-09-02 15:29:52', NULL),
(2, 2, 1, 50.00, 31, 0, 0.00, 'active', '2026-09-02 15:29:52', NULL),
(3, 3, 1, 100.00, 31, 0, 0.00, 'active', '2026-09-02 15:29:52', NULL),
(4, 4, 1, 20.00, 31, 0, 0.00, 'active', '2026-09-02 15:29:52', NULL),
(5, 5, 1, 10.00, 31, 0, 0.00, 'active', '2026-09-02 15:29:52', NULL),
(6, 6, 1, 10.00, 31, 0, 0.00, 'active', '2026-09-02 15:29:52', NULL),
(7, 7, 1, 20.00, 31, 0, 0.00, 'active', '2026-09-02 15:29:52', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','collector') NOT NULL DEFAULT 'collector',
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `username`, `password_hash`, `role`, `phone`, `is_active`, `created_at`) VALUES
(1, 'Agbenyenuse Stanley', 'Eyram', '$2y$10$GeSPgMkxJusvpQyuR8ZzMuq7YKELDOSh23zlv5nu4IKnbAhs42r66', 'admin', '0553224837', 1, '2026-09-02 15:29:52'),
(2, 'Kuddy Peggy', 'Peggy', '$2y$10$InPQvWuQ1pcoop9GQkkBzeJWEibTfVoXGLudRR19RaJjHsb97aoSO', 'collector', '0555495796', 1, '2026-09-02 15:29:52');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `account_number` (`account_number`),
  ADD KEY `assigned_collector_id` (`assigned_collector_id`);

--
-- Indexes for table `daily_handovers`
--
ALTER TABLE `daily_handovers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `collector_id` (`collector_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `deposits`
--
ALTER TABLE `deposits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `card_id` (`card_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `collector_id` (`collector_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`);

--
-- Indexes for table `payouts`
--
ALTER TABLE `payouts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `card_id` (`card_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `collector_id` (`collector_id`),
  ADD KEY `approved_by` (`approved_by`);

--
-- Indexes for table `susu_cards`
--
ALTER TABLE `susu_cards`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

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
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `daily_handovers`
--
ALTER TABLE `daily_handovers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payouts`
--
ALTER TABLE `payouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `susu_cards`
--
ALTER TABLE `susu_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`assigned_collector_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `daily_handovers`
--
ALTER TABLE `daily_handovers`
  ADD CONSTRAINT `daily_handovers_ibfk_1` FOREIGN KEY (`collector_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `daily_handovers_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `deposits`
--
ALTER TABLE `deposits`
  ADD CONSTRAINT `deposits_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `susu_cards` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposits_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `deposits_ibfk_3` FOREIGN KEY (`collector_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payouts`
--
ALTER TABLE `payouts`
  ADD CONSTRAINT `payouts_ibfk_1` FOREIGN KEY (`card_id`) REFERENCES `susu_cards` (`id`),
  ADD CONSTRAINT `payouts_ibfk_2` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `payouts_ibfk_3` FOREIGN KEY (`collector_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `payouts_ibfk_4` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `susu_cards`
--
ALTER TABLE `susu_cards`
  ADD CONSTRAINT `susu_cards_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
