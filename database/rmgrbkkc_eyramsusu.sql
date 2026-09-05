-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 04, 2026 at 11:11 PM
-- Server version: 11.4.12-MariaDB-cll-lve-log
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rmgrbkkc_eyramsusu`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL COMMENT 'e.g. login, logout, failed_login, open_card',
  `details` varchar(255) DEFAULT NULL COMMENT 'Human-readable context',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Supports both IPv4 and IPv6',
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lightweight audit trail for login, logout, and critical system events';

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `details`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 15:49:06'),
(2, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 15:49:21'),
(3, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 15:49:28'),
(4, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 15:57:38'),
(5, NULL, 'failed_login', 'Failed login attempt for username: pe***', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 15:57:52'),
(6, NULL, 'failed_login', 'Failed login attempt for username: pe***', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 15:58:17'),
(7, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 15:58:24'),
(8, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 15:59:43'),
(9, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:00:23'),
(10, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.162.39.138', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Mobile Safari/537.36', '2026-09-04 16:05:53'),
(11, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:20:47'),
(12, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:28:53'),
(13, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:28:57'),
(14, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:29:00'),
(15, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:33:51'),
(16, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:33:54'),
(17, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:34:16'),
(18, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 16:34:19'),
(19, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 17:02:21'),
(20, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 17:08:52'),
(21, NULL, 'failed_login', 'Failed login attempt for username: Pe***', '154.161.125.165', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-09-04 18:37:58'),
(22, NULL, 'failed_login', 'Failed login attempt for username: Pe***', '154.161.125.165', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-09-04 18:38:05'),
(23, NULL, 'failed_login', 'Failed login attempt for username: Pe***', '154.161.125.165', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-09-04 18:38:27'),
(24, NULL, 'failed_login', 'Failed login attempt for username: Pe***', '154.161.125.165', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-09-04 18:39:08'),
(25, NULL, 'failed_login', 'Failed login attempt for username: Pe***', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:39:09'),
(26, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:39:48'),
(27, NULL, 'failed_login', 'Failed login attempt for username: pe***', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:45:32'),
(28, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:45:43'),
(29, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:46:43'),
(30, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:46:56'),
(31, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:47:24'),
(32, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:47:36'),
(33, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:48:31'),
(34, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:48:34'),
(35, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:49:23'),
(36, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:49:29'),
(37, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:49:58'),
(38, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:50:02'),
(39, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:50:59'),
(40, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:51:05'),
(41, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:51:05'),
(42, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:51:08'),
(43, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:52:09'),
(44, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:52:15'),
(45, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:54:05'),
(46, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:54:12'),
(47, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:56:47'),
(48, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:56:47'),
(49, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 18:56:54'),
(50, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 18:56:59'),
(51, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36', '2026-09-04 18:57:20'),
(52, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 19:00:04'),
(53, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 19:00:08'),
(54, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 19:01:31'),
(55, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '102.205.89.215', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-04 19:01:35'),
(56, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.162.6.99', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Mobile Safari/537.36', '2026-09-04 19:17:49'),
(57, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 22:06:58'),
(58, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-04 22:50:51'),
(59, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-04 22:52:00'),
(60, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:20:11'),
(61, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:21:18'),
(62, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:21:22'),
(63, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:27:51'),
(64, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:30:11'),
(65, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:47:55'),
(66, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 00:47:58'),
(67, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-05 00:47:59'),
(68, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-05 00:58:12'),
(69, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-05 01:01:12'),
(70, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 01:02:46'),
(71, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.162.61.106', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 01:02:49'),
(72, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-05 01:03:13'),
(73, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-05 01:03:24'),
(74, 2, 'login', 'Successful login: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-05 01:04:51'),
(75, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_7 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.7.6 Mobile/15E148 Safari/604.1', '2026-09-05 01:14:28'),
(76, 2, 'logout', 'User signed out: Kuddy Peggy (collector)', '154.161.125.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-05 01:14:31'),
(77, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-05 01:14:38'),
(78, 1, 'logout', 'User signed out: Agbenyenuse Stanley (admin)', '154.161.125.165', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36 Edg/152.0.0.0', '2026-09-05 01:25:01'),
(79, 1, 'login', 'Successful login: Agbenyenuse Stanley (admin)', '154.162.118.103', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36', '2026-09-05 02:29:59');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `account_number` varchar(20) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
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

INSERT INTO `customers` (`id`, `account_number`, `full_name`, `gender`, `phone`, `location`, `assigned_collector_id`, `change_balance`, `is_active`, `created_at`) VALUES
(1, '35', 'Kottoh Patience', 'F', '0242057910', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-02 15:29:52'),
(2, '36', 'Soglo Vivian', 'F', '0592663701', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-02 15:29:52'),
(3, '5', 'Kudi Lucky', 'M', '0545482671', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-02 15:29:52'),
(4, '22', 'Wase Yaovi', 'M', '0241164340', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-02 15:29:52'),
(5, '21', 'Kpedo Bismark', 'M', '0546249032', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-02 15:29:52'),
(6, '43', 'Anyadi Emmanuel', 'M', '0597515726', 'Adaklu Waya', 2, 0.00, 1, '2026-09-02 15:29:52'),
(7, '4', 'Deku Wonder', 'F', '0249771299', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-02 15:29:52'),
(35, '1', 'Agbenyenuse Philomena', 'F', '0245832311', 'Adaklu Waya, Round About', 2, 0.00, 1, '2026-09-04 13:39:03'),
(36, '2', 'Zuh William', 'M', '0246703454', 'Adaklu Waya, Round About', 2, 0.00, 1, '2026-09-04 13:39:03'),
(37, '3', 'Kettey Delight', 'F', '0240054489', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(38, '6', 'Amuzu Sefakor', 'F', '0535866971', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(39, '7', 'Dzodanu Irene', 'F', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(40, '8', 'Teli Charlotte', 'F', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(41, '9', 'Salu Sedinam', 'F', '0545262985', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(42, '10', 'Ketteh Delasi', 'F', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(43, '11', 'Agbo Divine Elikem', 'M', '0557739951', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(44, '12', 'Afedo Akorfa', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1, '2026-09-04 13:39:03'),
(45, '13', 'Doe Patrick', 'M', '', 'Adaklu Waya, Avegame', 2, 0.00, 1, '2026-09-04 13:39:03'),
(46, '14', 'Aklika Dodzi', 'M', '0247200062', 'Adaklu Waya, Market', 2, 0.00, 1, '2026-09-04 13:39:03'),
(47, '15', 'Agbenyegah Patricia', 'F', '0599876237', 'Adaklu Waya, Market', 2, 0.00, 1, '2026-09-04 13:39:03'),
(48, '16', 'Sunday', 'M', '0241038417', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(49, '17', 'Adzator Bolgah', 'M', '0559140151', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(50, '18', 'Razak Vulga', 'M', '0247200098', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(51, '19', 'Akpakra Prince', 'M', '0508186059', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(52, '20', 'Kufe Edem', 'F', '0540673172', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(53, '23', 'Dzordzorme Peace', 'F', '0241774219', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(54, '24', 'Aglah Rose', 'F', '0240431020', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(55, '25', 'Agbeve Faith', 'F', '0247632534', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(56, '26', 'Agbeve Judith', 'F', '0558884157', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(57, '27', 'Anyormi Samuel', 'M', '', 'Adaklu Waya, Avegame', 2, 0.00, 1, '2026-09-04 13:39:03'),
(58, '28', 'Mrs. Klutse Stella', 'F', '0249274249', 'Adaklu Waya, E.P Church', 2, 0.00, 1, '2026-09-04 13:39:03'),
(59, '29', 'Ali Fitter', 'M', '0594087133', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(60, '30', 'Man Greato', 'M', '0553985681', 'Gbagbadeve', 2, 0.00, 1, '2026-09-04 13:39:03'),
(61, '31', 'Goka Colins', 'M', '', 'Adaklu Waya, Roadleader', 2, 0.00, 1, '2026-09-04 13:39:03'),
(62, '32', 'Donkor Daniel', 'M', '0246618331', 'Adaklu Waya, Market', 2, 0.00, 1, '2026-09-04 13:39:03'),
(63, '33', 'Kudzrame Stephen', 'M', '0552159918', 'Adaklu Waya, Market', 2, 0.00, 1, '2026-09-04 13:39:03'),
(64, '34', 'Donkor Enyonam', 'F', '0534821113', 'Adklu Waya, Market', 2, 0.00, 1, '2026-09-04 13:39:03'),
(65, '37', 'Master Kekeli', 'M', '0532421931', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(66, '38', 'Tanti', 'F', '', 'Adaklu Waya, Awudu House', 2, 0.00, 1, '2026-09-04 13:39:03'),
(67, '39', 'Blewusi Emil', 'M', '0242005979', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(68, '40', 'Mohammed Aisha', 'F', '0245590270', 'Adaklu Waya, Market', 2, 0.00, 1, '2026-09-04 13:39:03'),
(69, '41', 'Wordi Gifty', 'F', '0548528682', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(70, '42', 'Segbefia Vivian', 'F', '', 'Adaklu Waya, Adesec', 2, 0.00, 1, '2026-09-04 13:39:03'),
(71, '44', 'Dawuda Mary', 'F', '0547762651', 'Adaklu Waya, E.P Church', 2, 0.00, 1, '2026-09-04 13:39:03'),
(72, '45', 'Agbenyenuse Bless', 'M', '0558056197', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(73, '46', 'Akpabla Mawusi', 'F', '0549108393', 'Adaklu Waya, Xalavia', 2, 0.00, 1, '2026-09-04 13:39:03'),
(74, '47', 'Deku Jemima', 'F', '0551484002', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(75, '48', 'Addu-Danquah Ivy', 'F', '0247338421', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(76, '49', 'Aheto Hannah', 'F', '0551273482', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(77, '50', 'Amedzro Patricia', 'F', '0249666393', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(78, '51', 'Ametor Saviour', 'F', '0552805709', 'Adaklu Waya', 2, 0.00, 1, '2026-09-04 13:39:03'),
(79, '52', 'Agbenyenuse Daniel', 'M', '0599035189', 'Adaklu Waya, Yellow House', 2, 0.00, 1, '2026-09-04 13:39:03'),
(80, '53', 'Koboe Emmanuel', 'M', '0556130550', 'Adaklu Waya, Abaya', 2, 0.00, 1, '2026-09-04 13:39:03'),
(81, '54', 'Akumani Dzigbordi', 'F', '0541439375', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(82, '55', 'Agbo Fanuel', 'M', '0247219844', 'Adaklu Waya, Agbo Feme', 2, 0.00, 1, '2026-09-04 13:39:03'),
(83, '56', 'Amadu Believe', NULL, '', 'Adaklu Waya, Avegame', 2, 0.00, 1, '2026-09-04 13:39:03'),
(84, '57', 'Agbi Harriet', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1, '2026-09-04 13:39:03'),
(85, '58', 'Donkor Norvinyo', 'M', '0240213409', 'Adaklu Waya, Round About', 2, 0.00, 1, '2026-09-04 13:39:03'),
(86, '59', 'Anyagli Beauty', 'F', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(87, '60', 'Dzah Mable', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1, '2026-09-04 13:39:03'),
(88, '61', 'Kartey Emmanuella', 'F', '0539104457', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(89, '62', 'Gati Pearl Afi Ntifafa', 'F', '0597167794', 'Adaklu Waya, Abaya', 2, 0.00, 1, '2026-09-04 13:39:03'),
(90, '63', 'Agbogah Augusta', 'F', '5553384947', 'Adaklu Waya, Round About', 2, 0.00, 1, '2026-09-04 13:39:03'),
(91, '64', 'Dzah Pearl', 'F', '', 'Adaklu Waya, E.P Church', 2, 0.00, 1, '2026-09-04 13:39:03'),
(92, '65', 'Asinyo Bernice', 'F', '0546602557', 'Adaklu Waya, E.P Church', 2, 0.00, 1, '2026-09-04 13:39:03'),
(93, '66', 'Master Awudu', 'M', '0247292081', 'Adaklu Waya, Awudu House', 2, 0.00, 1, '2026-09-04 13:39:03'),
(94, '67', 'Dufe Mawufemor', 'F', '0591739243', 'Adklu Waya, Round About', 2, 0.00, 1, '2026-09-04 13:39:03'),
(95, '68', 'Soti Precious', 'F', '0599224654', 'Adaklu Anfoe', 2, 0.00, 1, '2026-09-04 13:39:03'),
(96, '69', 'Agbotey Kofi Richard', 'M', '', 'Adaklu Waya', 2, 0.00, 1, '2026-09-04 13:39:03'),
(97, '70', 'Amegboe Juliet', 'F', '0246238501', 'Adaklu Waya, Xalavia', 2, 0.00, 1, '2026-09-04 13:39:03'),
(98, '71', 'Kugbeadzor Sena', 'M', '0244961386', 'Adaklu Waya, E.P Church', 2, 0.00, 1, '2026-09-04 13:39:03'),
(99, '72', 'Dzah Vicentia', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1, '2026-09-04 13:39:03'),
(100, '73', 'Bansah Abigail', 'F', '0536406284', 'Adaklu Waya, Round About', 2, 0.00, 1, '2026-09-04 13:39:03'),
(101, '74', 'Avor Kobby', 'M', '', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(102, '75', 'Deku Sewa', 'F', '0538853655', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(103, '76', 'Sokpah Eunice', 'F', '0535920034', 'Adaklu Waya, Awudu House', 2, 0.00, 1, '2026-09-04 13:39:03'),
(104, '77', 'Tsigbey Evelyn', 'F', '', 'Adaklu Waya, Avegame', 2, 0.00, 1, '2026-09-04 13:39:03'),
(105, '78', 'Adzalo Norvinyo', 'F', '', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(106, '79', 'Ametefe Comfort', 'F', '0543476887', 'Adaklu Waya, Kpota', 2, 0.00, 1, '2026-09-04 13:39:03'),
(107, '80', 'Akpakpa Christiana', 'F', '0539119858', 'Adaklu Sofa', 2, 0.00, 1, '2026-09-04 13:39:03'),
(108, '81', 'Aklamanu Jenet', 'F', '0540307079', 'Adaklu Waya, Kpota', 2, 0.00, 1, '2026-09-04 13:39:03'),
(109, '82', 'Morti Faustine', 'F', '', 'Adaklu Sofa', 2, 0.00, 1, '2026-09-04 13:39:03'),
(110, '83', 'Adanu Venunye', 'F', '0257176465', 'Adaklu Sofa', 2, 0.00, 1, '2026-09-04 13:39:03'),
(111, '84', 'Ofori Ernest', 'M', '', 'Adklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(112, '85', 'Dzah Gina', 'F', '0257992243', 'Adklu Waya, Round About', 2, 0.00, 1, '2026-09-04 13:39:03'),
(113, '86', 'Kpedo Mawuli', 'M', '0257933252', 'Adaklu Waya, Opp. Dzidzorkporkpor', 2, 0.00, 1, '2026-09-04 13:39:03'),
(114, '87', 'Anyormi Comfort', 'F', '0550373213', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(115, '88', 'Deku Irene', 'F', '', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(116, '89', 'Deku Agness', 'F', '0538510114', 'Adaklu Waya, Yellow House', 2, 0.00, 1, '2026-09-04 13:39:03'),
(117, '90', 'Tormeti Freda', 'F', '0534237824', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(118, '91', 'Akpatsa Dasha', 'F', '0247632534', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(119, '92', 'Kpedo Gifty', 'F', '0558095854', 'Adaklu Waya, Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03'),
(120, '93', 'Misrowoda Joanita', 'F', '', 'Adaklu Waya, Agbo Feme', 2, 0.00, 1, '2026-09-04 13:39:03'),
(121, '94', 'Avor Linda', 'F', '0599448224', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(122, '95', 'Adanu Diana', 'F', '0543741439', 'Adaklu Sofa', 2, 0.00, 1, '2026-09-04 13:39:03'),
(123, '96', 'Galah Joyce', 'F', '0548441093', 'Adaklu Sofa', 2, 0.00, 1, '2026-09-04 13:39:03'),
(124, '97', 'Soglo Believe', 'F', '', 'Adaklu Sofa', 2, 0.00, 1, '2026-09-04 13:39:03'),
(125, '98', 'Mexatsor Rebecca', 'F', '0530596534', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(126, '99', 'Dotsey Worlasi', 'F', '0543522969', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(127, '100', 'Amafu Noah', 'F', '', 'Adaklu Waya, Xalavia', 2, 0.00, 1, '2026-09-04 13:39:03'),
(128, '101', 'Seidu Mariam', 'F', '0591015459', 'Adaklu Waya, Round About', 2, 0.00, 1, '2026-09-04 13:39:03'),
(129, '102', 'Geh Sylvia', 'F', '', 'Adaklu Kpodzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(130, '103', 'Aboni Dogbeda', 'M', '0544539611', 'Adaklu Waya, Xalavia', 2, 0.00, 1, '2026-09-04 13:39:03'),
(131, '104', 'Amegboe Sabastian', 'M', '0548423264', 'Adaklu Waya, Xalavia', 2, 0.00, 1, '2026-09-04 13:39:03'),
(132, '105', 'Dzah Flourence', 'F', '0543066010', 'Adaklu Waya, Market', 2, 0.00, 1, '2026-09-04 13:39:03'),
(133, '106', 'Kugbeadzor Angela', 'F', '0559193436', 'Adaklu Waya, Agedzi', 2, 0.00, 1, '2026-09-04 13:39:03'),
(134, '107', 'Dzah Wisdom', 'M', '0240220204', 'Adaklu Waya, E.P Church', 2, 0.00, 1, '2026-09-04 13:39:03'),
(135, '108', 'Klu Martinos', 'M', '', 'Adaklu Waya, Old Ablorme', 2, 0.00, 1, '2026-09-04 13:39:03'),
(136, '109', 'Agbenyenuse Sampsom', 'M', '0596640303', 'Adaklu Waya, Off Adasec Road', 2, 0.00, 1, '2026-09-04 13:39:03'),
(137, '110', 'Avor Esther', 'F', '0248531636', 'Adaklu Waya, Old Ablorme', 2, 0.00, 1, '2026-09-04 13:39:03'),
(138, '111', 'Akpabla Beauty', 'F', '0240143994', 'Adaklu Waya Roundabout', 2, 0.00, 1, '2026-09-04 13:39:03');

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

--
-- Dumping data for table `daily_handovers`
--

INSERT INTO `daily_handovers` (`id`, `collector_id`, `handover_date`, `expected_cash`, `cash_received`, `difference`, `status`, `collector_note`, `admin_note`, `approved_by`, `submitted_at`, `approved_at`) VALUES
(1, 2, '2026-09-05', 12560.00, 12560.00, 0.00, 'approved', '', 'Cash counted and verified.', 1, '2026-09-05 00:04:20', '2026-09-05 00:04:48'),
(2, 2, '2026-09-05', 1480.00, 1480.00, 0.00, 'approved', '', 'Cash counted and verified.', 1, '2026-09-05 00:16:44', '2026-09-05 00:17:17');

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

--
-- Dumping data for table `deposits`
--

INSERT INTO `deposits` (`id`, `card_id`, `customer_id`, `collector_id`, `space_number`, `amount`, `deposit_date`, `handover_id`, `created_at`) VALUES
(1, 2, 47, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(2, 2, 47, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(3, 2, 47, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(4, 2, 47, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(5, 2, 47, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(6, 2, 47, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(7, 2, 47, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(8, 2, 47, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(9, 2, 47, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 18:58:59'),
(10, 3, 67, 2, 1, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(11, 3, 67, 2, 2, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(12, 3, 67, 2, 3, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(13, 3, 67, 2, 4, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(14, 3, 67, 2, 5, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(15, 3, 67, 2, 6, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(16, 3, 67, 2, 7, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(17, 3, 67, 2, 8, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(18, 3, 67, 2, 9, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(19, 3, 67, 2, 10, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(20, 3, 67, 2, 11, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(21, 3, 67, 2, 12, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(22, 3, 67, 2, 13, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(23, 3, 67, 2, 14, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(24, 3, 67, 2, 15, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(25, 3, 67, 2, 16, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(26, 3, 67, 2, 17, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(27, 3, 67, 2, 18, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(28, 3, 67, 2, 19, 50.00, '2026-09-04', 1, '2026-09-04 22:55:27'),
(29, 4, 1, 2, 1, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(30, 4, 1, 2, 2, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(31, 4, 1, 2, 3, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(32, 4, 1, 2, 4, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(33, 4, 1, 2, 5, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(34, 4, 1, 2, 6, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(35, 4, 1, 2, 7, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(36, 4, 1, 2, 8, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(37, 4, 1, 2, 9, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(38, 4, 1, 2, 10, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(39, 4, 1, 2, 11, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(40, 4, 1, 2, 12, 50.00, '2026-09-04', 1, '2026-09-04 22:59:50'),
(41, 5, 2, 2, 1, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(42, 5, 2, 2, 2, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(43, 5, 2, 2, 3, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(44, 5, 2, 2, 4, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(45, 5, 2, 2, 5, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(46, 5, 2, 2, 6, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(47, 5, 2, 2, 7, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(48, 5, 2, 2, 8, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(49, 5, 2, 2, 9, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(50, 5, 2, 2, 10, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(51, 5, 2, 2, 11, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(52, 5, 2, 2, 12, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(53, 5, 2, 2, 13, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(54, 5, 2, 2, 14, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(55, 5, 2, 2, 15, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(56, 5, 2, 2, 16, 50.00, '2026-09-04', 1, '2026-09-04 23:01:36'),
(57, 6, 121, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(58, 6, 121, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(59, 6, 121, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(60, 6, 121, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(61, 6, 121, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(62, 6, 121, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(63, 6, 121, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(64, 6, 121, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(65, 6, 121, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(66, 6, 121, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(67, 6, 121, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(68, 6, 121, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(69, 6, 121, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(70, 6, 121, 2, 14, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(71, 6, 121, 2, 15, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(72, 6, 121, 2, 16, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(73, 6, 121, 2, 17, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(74, 6, 121, 2, 18, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(75, 6, 121, 2, 19, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(76, 6, 121, 2, 20, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(77, 6, 121, 2, 21, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(78, 6, 121, 2, 22, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(79, 6, 121, 2, 23, 10.00, '2026-09-04', 1, '2026-09-04 23:04:02'),
(80, 7, 5, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(81, 7, 5, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(82, 7, 5, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(83, 7, 5, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(84, 7, 5, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(85, 7, 5, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(86, 7, 5, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(87, 7, 5, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(88, 7, 5, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(89, 7, 5, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(90, 7, 5, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(91, 7, 5, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(92, 7, 5, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(93, 7, 5, 2, 14, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(94, 7, 5, 2, 15, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(95, 7, 5, 2, 16, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(96, 7, 5, 2, 17, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(97, 7, 5, 2, 18, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(98, 7, 5, 2, 19, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(99, 7, 5, 2, 20, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(100, 7, 5, 2, 21, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(101, 7, 5, 2, 22, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(102, 7, 5, 2, 23, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(103, 7, 5, 2, 24, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(104, 7, 5, 2, 25, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(105, 7, 5, 2, 26, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(106, 7, 5, 2, 27, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(107, 7, 5, 2, 28, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(108, 7, 5, 2, 29, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(109, 7, 5, 2, 30, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(110, 7, 5, 2, 31, 10.00, '2026-09-04', 1, '2026-09-04 23:05:31'),
(111, 8, 5, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(112, 8, 5, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(113, 8, 5, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(114, 8, 5, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(115, 8, 5, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(116, 8, 5, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(117, 8, 5, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(118, 8, 5, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(119, 8, 5, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(120, 8, 5, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(121, 8, 5, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(122, 8, 5, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(123, 8, 5, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(124, 8, 5, 2, 14, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(125, 8, 5, 2, 15, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(126, 8, 5, 2, 16, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(127, 8, 5, 2, 17, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(128, 8, 5, 2, 18, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(129, 8, 5, 2, 19, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(130, 8, 5, 2, 20, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(131, 8, 5, 2, 21, 10.00, '2026-09-04', 1, '2026-09-04 23:08:09'),
(132, 9, 93, 2, 1, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(133, 9, 93, 2, 2, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(134, 9, 93, 2, 3, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(135, 9, 93, 2, 4, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(136, 9, 93, 2, 5, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(137, 9, 93, 2, 6, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(138, 9, 93, 2, 7, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(139, 9, 93, 2, 8, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(140, 9, 93, 2, 9, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(141, 9, 93, 2, 10, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(142, 9, 93, 2, 11, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(143, 9, 93, 2, 12, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(144, 9, 93, 2, 13, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(145, 9, 93, 2, 14, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(146, 9, 93, 2, 15, 50.00, '2026-09-04', 1, '2026-09-04 23:10:48'),
(147, 10, 83, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:17:33'),
(148, 10, 83, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:17:33'),
(149, 10, 83, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:17:33'),
(150, 10, 83, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:17:33'),
(151, 11, 134, 2, 1, 50.00, '2026-09-04', 1, '2026-09-04 23:18:01'),
(152, 11, 134, 2, 2, 50.00, '2026-09-04', 1, '2026-09-04 23:18:01'),
(153, 11, 134, 2, 3, 50.00, '2026-09-04', 1, '2026-09-04 23:18:01'),
(154, 11, 134, 2, 4, 50.00, '2026-09-04', 1, '2026-09-04 23:18:01'),
(155, 11, 134, 2, 5, 50.00, '2026-09-04', 1, '2026-09-04 23:18:01'),
(156, 12, 92, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(157, 12, 92, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(158, 12, 92, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(159, 12, 92, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(160, 12, 92, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(161, 12, 92, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(162, 12, 92, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(163, 12, 92, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(164, 12, 92, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(165, 12, 92, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(166, 12, 92, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(167, 12, 92, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(168, 12, 92, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(169, 12, 92, 2, 14, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(170, 12, 92, 2, 15, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(171, 12, 92, 2, 16, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(172, 12, 92, 2, 17, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(173, 12, 92, 2, 18, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(174, 12, 92, 2, 19, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(175, 12, 92, 2, 20, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(176, 12, 92, 2, 21, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(177, 12, 92, 2, 22, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(178, 12, 92, 2, 23, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(179, 12, 92, 2, 24, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(180, 12, 92, 2, 25, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(181, 12, 92, 2, 26, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(182, 12, 92, 2, 27, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(183, 12, 92, 2, 28, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(184, 12, 92, 2, 29, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(185, 12, 92, 2, 30, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(186, 12, 92, 2, 31, 10.00, '2026-09-04', 1, '2026-09-04 23:21:55'),
(187, 13, 92, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(188, 13, 92, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(189, 13, 92, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(190, 13, 92, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(191, 13, 92, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(192, 13, 92, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(193, 13, 92, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(194, 13, 92, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(195, 13, 92, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(196, 13, 92, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(197, 13, 92, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(198, 13, 92, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(199, 13, 92, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:23:09'),
(200, 14, 80, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(201, 14, 80, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(202, 14, 80, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(203, 14, 80, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(204, 14, 80, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(205, 14, 80, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(206, 14, 80, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(207, 14, 80, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(208, 14, 80, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(209, 14, 80, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(210, 14, 80, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(211, 14, 80, 2, 12, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(212, 14, 80, 2, 13, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(213, 14, 80, 2, 14, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(214, 14, 80, 2, 15, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(215, 14, 80, 2, 16, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(216, 14, 80, 2, 17, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(217, 14, 80, 2, 18, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(218, 14, 80, 2, 19, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(219, 14, 80, 2, 20, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(220, 14, 80, 2, 21, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(221, 14, 80, 2, 22, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(222, 14, 80, 2, 23, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(223, 14, 80, 2, 24, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(224, 14, 80, 2, 25, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(225, 14, 80, 2, 26, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(226, 14, 80, 2, 27, 20.00, '2026-09-04', 1, '2026-09-04 23:24:37'),
(227, 15, 135, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:26:05'),
(228, 15, 135, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:26:05'),
(229, 15, 135, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:26:05'),
(230, 15, 135, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:26:05'),
(231, 15, 135, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:26:05'),
(232, 16, 112, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(233, 16, 112, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(234, 16, 112, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(235, 16, 112, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(236, 16, 112, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(237, 16, 112, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(238, 16, 112, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(239, 16, 112, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(240, 16, 112, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(241, 16, 112, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(242, 16, 112, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(243, 16, 112, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(244, 16, 112, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(245, 16, 112, 2, 14, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(246, 16, 112, 2, 15, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(247, 16, 112, 2, 16, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(248, 16, 112, 2, 17, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(249, 16, 112, 2, 18, 10.00, '2026-09-04', 1, '2026-09-04 23:27:17'),
(250, 17, 132, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:30:34'),
(251, 17, 132, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:30:34'),
(252, 17, 132, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:30:34'),
(253, 18, 44, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(254, 18, 44, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(255, 18, 44, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(256, 18, 44, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(257, 18, 44, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(258, 18, 44, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(259, 18, 44, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(260, 18, 44, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(261, 18, 44, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(262, 18, 44, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(263, 18, 44, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(264, 18, 44, 2, 12, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(265, 18, 44, 2, 13, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(266, 18, 44, 2, 14, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(267, 18, 44, 2, 15, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(268, 18, 44, 2, 16, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(269, 18, 44, 2, 17, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(270, 18, 44, 2, 18, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(271, 18, 44, 2, 19, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(272, 18, 44, 2, 20, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(273, 18, 44, 2, 21, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(274, 18, 44, 2, 22, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(275, 18, 44, 2, 23, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(276, 18, 44, 2, 24, 20.00, '2026-09-04', 1, '2026-09-04 23:32:43'),
(277, 19, 57, 2, 1, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(278, 19, 57, 2, 2, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(279, 19, 57, 2, 3, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(280, 19, 57, 2, 4, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(281, 19, 57, 2, 5, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(282, 19, 57, 2, 6, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(283, 19, 57, 2, 7, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(284, 19, 57, 2, 8, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(285, 19, 57, 2, 9, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(286, 19, 57, 2, 10, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(287, 19, 57, 2, 11, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(288, 19, 57, 2, 12, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(289, 19, 57, 2, 13, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(290, 19, 57, 2, 14, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(291, 19, 57, 2, 15, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(292, 19, 57, 2, 16, 5.00, '2026-09-04', 1, '2026-09-04 23:34:43'),
(293, 20, 45, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:36:04'),
(294, 20, 45, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:36:04'),
(295, 20, 45, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:36:04'),
(296, 20, 45, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:36:04'),
(297, 20, 45, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:36:04'),
(298, 21, 126, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(299, 21, 126, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(300, 21, 126, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(301, 21, 126, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(302, 21, 126, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(303, 21, 126, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(304, 21, 126, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(305, 21, 126, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(306, 21, 126, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:37:57'),
(307, 22, 99, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(308, 22, 99, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(309, 22, 99, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(310, 22, 99, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(311, 22, 99, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(312, 22, 99, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(313, 22, 99, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(314, 22, 99, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(315, 22, 99, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(316, 22, 99, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(317, 22, 99, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(318, 22, 99, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(319, 22, 99, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(320, 22, 99, 2, 14, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(321, 22, 99, 2, 15, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(322, 22, 99, 2, 16, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(323, 22, 99, 2, 17, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(324, 22, 99, 2, 18, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(325, 22, 99, 2, 19, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(326, 22, 99, 2, 20, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(327, 22, 99, 2, 21, 10.00, '2026-09-04', 1, '2026-09-04 23:39:23'),
(328, 23, 73, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:41:36'),
(329, 23, 73, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:41:36'),
(330, 23, 73, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:41:36'),
(331, 23, 73, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:41:36'),
(332, 23, 73, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:41:36'),
(333, 23, 73, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:41:36'),
(334, 23, 73, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:41:36'),
(335, 24, 114, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(336, 24, 114, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(337, 24, 114, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(338, 24, 114, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(339, 24, 114, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(340, 24, 114, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(341, 24, 114, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(342, 24, 114, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(343, 24, 114, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(344, 24, 114, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(345, 24, 114, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(346, 24, 114, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(347, 24, 114, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(348, 24, 114, 2, 14, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(349, 24, 114, 2, 15, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(350, 24, 114, 2, 16, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(351, 24, 114, 2, 17, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(352, 24, 114, 2, 18, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(353, 24, 114, 2, 19, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(354, 24, 114, 2, 20, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(355, 24, 114, 2, 21, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(356, 24, 114, 2, 22, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(357, 24, 114, 2, 23, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(358, 24, 114, 2, 24, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(359, 24, 114, 2, 25, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(360, 24, 114, 2, 26, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(361, 24, 114, 2, 27, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(362, 24, 114, 2, 28, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(363, 24, 114, 2, 29, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(364, 24, 114, 2, 30, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(365, 24, 114, 2, 31, 10.00, '2026-09-04', 1, '2026-09-04 23:47:53'),
(366, 26, 54, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(367, 26, 54, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(368, 26, 54, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(369, 26, 54, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(370, 26, 54, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(371, 26, 54, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(372, 26, 54, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(373, 26, 54, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(374, 26, 54, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(375, 26, 54, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(376, 26, 54, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(377, 26, 54, 2, 12, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(378, 26, 54, 2, 13, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(379, 26, 54, 2, 14, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(380, 26, 54, 2, 15, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(381, 26, 54, 2, 16, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(382, 26, 54, 2, 17, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(383, 26, 54, 2, 18, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(384, 26, 54, 2, 19, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(385, 26, 54, 2, 20, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(386, 26, 54, 2, 21, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(387, 26, 54, 2, 22, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(388, 26, 54, 2, 23, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(389, 26, 54, 2, 24, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(390, 26, 54, 2, 25, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(391, 26, 54, 2, 26, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(392, 26, 54, 2, 27, 20.00, '2026-09-04', 1, '2026-09-04 23:50:55'),
(393, 27, 55, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(394, 27, 55, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(395, 27, 55, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(396, 27, 55, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(397, 27, 55, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(398, 27, 55, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(399, 27, 55, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(400, 27, 55, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(401, 27, 55, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(402, 27, 55, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(403, 27, 55, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(404, 27, 55, 2, 12, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(405, 27, 55, 2, 13, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(406, 27, 55, 2, 14, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(407, 27, 55, 2, 15, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(408, 27, 55, 2, 16, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(409, 27, 55, 2, 17, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(410, 27, 55, 2, 18, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(411, 27, 55, 2, 19, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(412, 27, 55, 2, 20, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(413, 27, 55, 2, 21, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(414, 27, 55, 2, 22, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(415, 27, 55, 2, 23, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(416, 27, 55, 2, 24, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(417, 27, 55, 2, 25, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(418, 27, 55, 2, 26, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(419, 27, 55, 2, 27, 20.00, '2026-09-04', 1, '2026-09-04 23:51:41'),
(420, 28, 56, 2, 1, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(421, 28, 56, 2, 2, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(422, 28, 56, 2, 3, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(423, 28, 56, 2, 4, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(424, 28, 56, 2, 5, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(425, 28, 56, 2, 6, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(426, 28, 56, 2, 7, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(427, 28, 56, 2, 8, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(428, 28, 56, 2, 9, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(429, 28, 56, 2, 10, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(430, 28, 56, 2, 11, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(431, 28, 56, 2, 12, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(432, 28, 56, 2, 13, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(433, 28, 56, 2, 14, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(434, 28, 56, 2, 15, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(435, 28, 56, 2, 16, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(436, 28, 56, 2, 17, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(437, 28, 56, 2, 18, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(438, 28, 56, 2, 19, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(439, 28, 56, 2, 20, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(440, 28, 56, 2, 21, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(441, 28, 56, 2, 22, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(442, 28, 56, 2, 23, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(443, 28, 56, 2, 24, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(444, 28, 56, 2, 25, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(445, 28, 56, 2, 26, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(446, 28, 56, 2, 27, 30.00, '2026-09-04', 1, '2026-09-04 23:52:33'),
(447, 29, 118, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(448, 29, 118, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(449, 29, 118, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(450, 29, 118, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(451, 29, 118, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(452, 29, 118, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(453, 29, 118, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(454, 29, 118, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(455, 29, 118, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(456, 29, 118, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(457, 29, 118, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(458, 29, 118, 2, 12, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(459, 29, 118, 2, 13, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(460, 29, 118, 2, 14, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(461, 29, 118, 2, 15, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(462, 29, 118, 2, 16, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(463, 29, 118, 2, 17, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(464, 29, 118, 2, 18, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(465, 29, 118, 2, 19, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(466, 29, 118, 2, 20, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(467, 29, 118, 2, 21, 20.00, '2026-09-04', 1, '2026-09-04 23:53:15'),
(468, 30, 102, 2, 1, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(469, 30, 102, 2, 2, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(470, 30, 102, 2, 3, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(471, 30, 102, 2, 4, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(472, 30, 102, 2, 5, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(473, 30, 102, 2, 6, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(474, 30, 102, 2, 7, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(475, 30, 102, 2, 8, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(476, 30, 102, 2, 9, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(477, 30, 102, 2, 10, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(478, 30, 102, 2, 11, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(479, 30, 102, 2, 12, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(480, 30, 102, 2, 13, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(481, 30, 102, 2, 14, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(482, 30, 102, 2, 15, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(483, 30, 102, 2, 16, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(484, 30, 102, 2, 17, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(485, 30, 102, 2, 18, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(486, 30, 102, 2, 19, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(487, 30, 102, 2, 20, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(488, 30, 102, 2, 21, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(489, 30, 102, 2, 22, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(490, 30, 102, 2, 23, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(491, 30, 102, 2, 24, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(492, 30, 102, 2, 25, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(493, 30, 102, 2, 26, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(494, 30, 102, 2, 27, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(495, 30, 102, 2, 28, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(496, 30, 102, 2, 29, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(497, 30, 102, 2, 30, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(498, 30, 102, 2, 31, 10.00, '2026-09-04', 1, '2026-09-04 23:54:18'),
(499, 31, 102, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(500, 31, 102, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(501, 31, 102, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(502, 31, 102, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(503, 31, 102, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(504, 31, 102, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(505, 31, 102, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(506, 31, 102, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(507, 31, 102, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(508, 31, 102, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(509, 31, 102, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:55:04'),
(510, 32, 53, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(511, 32, 53, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(512, 32, 53, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(513, 32, 53, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(514, 32, 53, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(515, 32, 53, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(516, 32, 53, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(517, 32, 53, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(518, 32, 53, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(519, 32, 53, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(520, 32, 53, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(521, 32, 53, 2, 12, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(522, 32, 53, 2, 13, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(523, 32, 53, 2, 14, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(524, 32, 53, 2, 15, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(525, 32, 53, 2, 16, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(526, 32, 53, 2, 17, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(527, 32, 53, 2, 18, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(528, 32, 53, 2, 19, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(529, 32, 53, 2, 20, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(530, 32, 53, 2, 21, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(531, 32, 53, 2, 22, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(532, 32, 53, 2, 23, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(533, 32, 53, 2, 24, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(534, 32, 53, 2, 25, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(535, 32, 53, 2, 26, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(536, 32, 53, 2, 27, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(537, 32, 53, 2, 28, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(538, 32, 53, 2, 29, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(539, 32, 53, 2, 30, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(540, 32, 53, 2, 31, 20.00, '2026-09-04', 1, '2026-09-04 23:55:42'),
(541, 33, 53, 2, 1, 50.00, '2026-09-04', 1, '2026-09-04 23:56:17'),
(542, 33, 53, 2, 2, 50.00, '2026-09-04', 1, '2026-09-04 23:56:17'),
(543, 33, 53, 2, 3, 50.00, '2026-09-04', 1, '2026-09-04 23:56:17'),
(544, 33, 53, 2, 4, 50.00, '2026-09-04', 1, '2026-09-04 23:56:17'),
(545, 33, 53, 2, 5, 50.00, '2026-09-04', 1, '2026-09-04 23:56:17'),
(546, 33, 53, 2, 6, 50.00, '2026-09-04', 1, '2026-09-04 23:56:17'),
(547, 34, 119, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(548, 34, 119, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(549, 34, 119, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(550, 34, 119, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(551, 34, 119, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(552, 34, 119, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(553, 34, 119, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(554, 34, 119, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(555, 34, 119, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(556, 34, 119, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(557, 34, 119, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(558, 34, 119, 2, 12, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(559, 34, 119, 2, 13, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(560, 34, 119, 2, 14, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(561, 34, 119, 2, 15, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(562, 34, 119, 2, 16, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(563, 34, 119, 2, 17, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(564, 34, 119, 2, 18, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(565, 34, 119, 2, 19, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(566, 34, 119, 2, 20, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(567, 34, 119, 2, 21, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(568, 34, 119, 2, 22, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(569, 34, 119, 2, 23, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(570, 34, 119, 2, 24, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(571, 34, 119, 2, 25, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(572, 34, 119, 2, 26, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(573, 34, 119, 2, 27, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(574, 34, 119, 2, 28, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(575, 34, 119, 2, 29, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(576, 34, 119, 2, 30, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(577, 34, 119, 2, 31, 20.00, '2026-09-04', 1, '2026-09-04 23:57:48'),
(578, 36, 111, 2, 1, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(579, 36, 111, 2, 2, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(580, 36, 111, 2, 3, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(581, 36, 111, 2, 4, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(582, 36, 111, 2, 5, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(583, 36, 111, 2, 6, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(584, 36, 111, 2, 7, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(585, 36, 111, 2, 8, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(586, 36, 111, 2, 9, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(587, 36, 111, 2, 10, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(588, 36, 111, 2, 11, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(589, 36, 111, 2, 12, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(590, 36, 111, 2, 13, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(591, 36, 111, 2, 14, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(592, 36, 111, 2, 15, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(593, 36, 111, 2, 16, 20.00, '2026-09-04', 1, '2026-09-04 23:59:41'),
(594, 37, 48, 2, 1, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(595, 37, 48, 2, 2, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(596, 37, 48, 2, 3, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(597, 37, 48, 2, 4, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(598, 37, 48, 2, 5, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(599, 37, 48, 2, 6, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(600, 37, 48, 2, 7, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(601, 37, 48, 2, 8, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(602, 37, 48, 2, 9, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(603, 37, 48, 2, 10, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(604, 37, 48, 2, 11, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(605, 37, 48, 2, 12, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(606, 37, 48, 2, 13, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(607, 37, 48, 2, 14, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(608, 37, 48, 2, 15, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(609, 37, 48, 2, 16, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(610, 37, 48, 2, 17, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(611, 37, 48, 2, 18, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(612, 37, 48, 2, 19, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(613, 37, 48, 2, 20, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(614, 37, 48, 2, 21, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(615, 37, 48, 2, 22, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(616, 37, 48, 2, 23, 30.00, '2026-09-05', 1, '2026-09-05 00:00:33'),
(617, 38, 59, 2, 1, 30.00, '2026-09-05', 1, '2026-09-05 00:01:18'),
(618, 38, 59, 2, 2, 30.00, '2026-09-05', 1, '2026-09-05 00:01:18'),
(619, 38, 59, 2, 3, 30.00, '2026-09-05', 1, '2026-09-05 00:01:18'),
(620, 38, 59, 2, 4, 30.00, '2026-09-05', 1, '2026-09-05 00:01:18'),
(621, 38, 59, 2, 5, 30.00, '2026-09-05', 1, '2026-09-05 00:01:18'),
(622, 38, 59, 2, 6, 30.00, '2026-09-05', 1, '2026-09-05 00:01:18'),
(623, 39, 78, 2, 1, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(624, 39, 78, 2, 2, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(625, 39, 78, 2, 3, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(626, 39, 78, 2, 4, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(627, 39, 78, 2, 5, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(628, 39, 78, 2, 6, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(629, 39, 78, 2, 7, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(630, 39, 78, 2, 8, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(631, 39, 78, 2, 9, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(632, 39, 78, 2, 10, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(633, 39, 78, 2, 11, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(634, 39, 78, 2, 12, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(635, 39, 78, 2, 13, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(636, 39, 78, 2, 14, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(637, 39, 78, 2, 15, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(638, 39, 78, 2, 16, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(639, 39, 78, 2, 17, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(640, 39, 78, 2, 18, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(641, 39, 78, 2, 19, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(642, 39, 78, 2, 20, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(643, 39, 78, 2, 21, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(644, 39, 78, 2, 22, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(645, 39, 78, 2, 23, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(646, 39, 78, 2, 24, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(647, 39, 78, 2, 25, 10.00, '2026-09-05', 2, '2026-09-05 00:10:08'),
(648, 40, 7, 2, 1, 20.00, '2026-09-05', 2, '2026-09-05 00:11:19'),
(649, 40, 7, 2, 2, 20.00, '2026-09-05', 2, '2026-09-05 00:11:19'),
(650, 40, 7, 2, 3, 20.00, '2026-09-05', 2, '2026-09-05 00:11:19'),
(651, 40, 7, 2, 4, 20.00, '2026-09-05', 2, '2026-09-05 00:11:19'),
(652, 41, 95, 2, 1, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(653, 41, 95, 2, 2, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(654, 41, 95, 2, 3, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(655, 41, 95, 2, 4, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(656, 41, 95, 2, 5, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(657, 41, 95, 2, 6, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(658, 41, 95, 2, 7, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(659, 41, 95, 2, 8, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(660, 41, 95, 2, 9, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(661, 41, 95, 2, 10, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(662, 41, 95, 2, 11, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(663, 41, 95, 2, 12, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(664, 41, 95, 2, 13, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(665, 41, 95, 2, 14, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(666, 41, 95, 2, 15, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(667, 41, 95, 2, 16, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(668, 41, 95, 2, 17, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(669, 41, 95, 2, 18, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(670, 41, 95, 2, 19, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(671, 41, 95, 2, 20, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(672, 41, 95, 2, 21, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(673, 41, 95, 2, 22, 20.00, '2026-09-05', 2, '2026-09-05 00:12:02'),
(674, 42, 72, 2, 1, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(675, 42, 72, 2, 2, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(676, 42, 72, 2, 3, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(677, 42, 72, 2, 4, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(678, 42, 72, 2, 5, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(679, 42, 72, 2, 6, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(680, 42, 72, 2, 7, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(681, 42, 72, 2, 8, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(682, 42, 72, 2, 9, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(683, 42, 72, 2, 10, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(684, 42, 72, 2, 11, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(685, 42, 72, 2, 12, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(686, 42, 72, 2, 13, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(687, 42, 72, 2, 14, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(688, 42, 72, 2, 15, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(689, 42, 72, 2, 16, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(690, 42, 72, 2, 17, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(691, 42, 72, 2, 18, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(692, 42, 72, 2, 19, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(693, 42, 72, 2, 20, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(694, 42, 72, 2, 21, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(695, 42, 72, 2, 22, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(696, 42, 72, 2, 23, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(697, 42, 72, 2, 24, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(698, 42, 72, 2, 25, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(699, 42, 72, 2, 26, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(700, 42, 72, 2, 27, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(701, 42, 72, 2, 28, 20.00, '2026-09-05', 2, '2026-09-05 00:13:12'),
(702, 44, 6, 2, 1, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(703, 44, 6, 2, 2, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(704, 44, 6, 2, 3, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(705, 44, 6, 2, 4, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(706, 44, 6, 2, 5, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(707, 44, 6, 2, 6, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(708, 44, 6, 2, 7, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(709, 44, 6, 2, 8, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(710, 44, 6, 2, 9, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(711, 44, 6, 2, 10, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(712, 44, 6, 2, 11, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(713, 44, 6, 2, 12, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(714, 44, 6, 2, 13, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(715, 44, 6, 2, 14, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21'),
(716, 44, 6, 2, 15, 10.00, '2026-09-05', 2, '2026-09-05 00:15:21');

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

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`) VALUES
(1, 2, 'customer_assigned', 'Route Assignment on Reactivation', '111 clients assigned to your route.', 'customers.php', 1, '2026-09-04 18:46:27'),
(2, NULL, 'warning', 'New Card Needed: Zuh William', 'Collector Kuddy Peggy is with Zuh William (#2) who needs a new Susu Card.', 'record_deposit.php?customer_id=36', 1, '2026-09-04 18:49:08'),
(3, NULL, 'warning', 'New Card Needed: Agbenyegah Patricia', 'Collector Kuddy Peggy is with Agbenyegah Patricia (#15) who needs a new Susu Card.', 'record_deposit.php?customer_id=47', 1, '2026-09-04 18:54:02'),
(4, NULL, 'warning', 'New Card Needed: Blewusi Emil', 'Collector Kuddy Peggy is with Blewusi Emil (#39) who needs a new Susu Card.', 'record_deposit.php?customer_id=67', 1, '2026-09-04 22:52:06'),
(5, NULL, 'warning', 'New Card Needed: Kottoh Patience', 'Collector Kuddy Peggy is with Kottoh Patience (#35) who needs a new Susu Card.', 'record_deposit.php?customer_id=1', 1, '2026-09-04 22:56:36'),
(6, NULL, 'warning', 'New Card Needed: Soglo Vivian', 'Collector Kuddy Peggy is with Soglo Vivian (#36) who needs a new Susu Card.', 'record_deposit.php?customer_id=2', 1, '2026-09-04 23:00:14'),
(7, NULL, 'warning', 'New Card Needed: Avor Linda', 'Collector Kuddy Peggy is with Avor Linda (#94) who needs a new Susu Card.', 'record_deposit.php?customer_id=121', 1, '2026-09-04 23:02:43'),
(8, NULL, 'warning', 'New Card Needed: Kpedo Bismark', 'Collector Kuddy Peggy is with Kpedo Bismark (#21) who needs a new Susu Card.', 'record_deposit.php?customer_id=5', 1, '2026-09-04 23:04:32'),
(9, NULL, 'warning', 'New Card Needed: Kpedo Bismark', 'Collector Kuddy Peggy is with Kpedo Bismark (#21) who needs a new Susu Card.', 'record_deposit.php?customer_id=5', 1, '2026-09-04 23:06:10'),
(10, NULL, 'warning', 'New Card Needed: Master Awudu', 'Collector Kuddy Peggy is with Master Awudu (#66) who needs a new Susu Card.', 'record_deposit.php?customer_id=93', 1, '2026-09-04 23:09:11'),
(11, NULL, 'warning', 'New Card Needed: Amadu Believe', 'Collector Kuddy Peggy is with Amadu Believe (#56) who needs a new Susu Card.', 'record_deposit.php?customer_id=83', 1, '2026-09-04 23:11:34'),
(12, NULL, 'warning', 'New Card Needed: Dzah Wisdom', 'Collector Kuddy Peggy is with Dzah Wisdom (#107) who needs a new Susu Card.', 'record_deposit.php?customer_id=134', 1, '2026-09-04 23:16:48'),
(13, NULL, 'warning', 'New Card Needed: Asinyo Bernice', 'Collector Kuddy Peggy is with Asinyo Bernice (#65) who needs a new Susu Card.', 'record_deposit.php?customer_id=92', 1, '2026-09-04 23:18:37'),
(14, NULL, 'warning', 'New Card Needed: Asinyo Bernice', 'Collector Kuddy Peggy is with Asinyo Bernice (#65) who needs a new Susu Card.', 'record_deposit.php?customer_id=92', 1, '2026-09-04 23:22:21'),
(15, NULL, 'warning', 'New Card Needed: Koboe Emmanuel', 'Collector Kuddy Peggy is with Koboe Emmanuel (#53) who needs a new Susu Card.', 'record_deposit.php?customer_id=80', 1, '2026-09-04 23:23:28'),
(16, NULL, 'warning', 'New Card Needed: Klu Martinos', 'Collector Kuddy Peggy is with Klu Martinos (#108) who needs a new Susu Card.', 'record_deposit.php?customer_id=135', 1, '2026-09-04 23:25:24'),
(17, NULL, 'warning', 'New Card Needed: Dzah Gina', 'Collector Kuddy Peggy is with Dzah Gina (#85) who needs a new Susu Card.', 'record_deposit.php?customer_id=112', 1, '2026-09-04 23:26:20'),
(18, NULL, 'warning', 'New Card Needed: Dzah Flourence', 'Collector Kuddy Peggy is with Dzah Flourence (#105) who needs a new Susu Card.', 'record_deposit.php?customer_id=132', 1, '2026-09-04 23:27:52'),
(19, NULL, 'warning', 'New Card Needed: Afedo Akorfa', 'Collector Kuddy Peggy is with Afedo Akorfa (#12) who needs a new Susu Card.', 'record_deposit.php?customer_id=44', 1, '2026-09-04 23:31:33'),
(20, NULL, 'warning', 'New Card Needed: Anyormi Samuel', 'Collector Kuddy Peggy is with Anyormi Samuel (#27) who needs a new Susu Card.', 'record_deposit.php?customer_id=57', 1, '2026-09-04 23:32:58'),
(21, NULL, 'warning', 'New Card Needed: Doe Patrick', 'Collector Kuddy Peggy is with Doe Patrick (#13) who needs a new Susu Card.', 'record_deposit.php?customer_id=45', 1, '2026-09-04 23:35:18'),
(22, NULL, 'warning', 'New Card Needed: Dotsey Worlasi', 'Collector Kuddy Peggy is with Dotsey Worlasi (#99) who needs a new Susu Card.', 'record_deposit.php?customer_id=126', 1, '2026-09-04 23:36:19'),
(23, NULL, 'warning', 'New Card Needed: Dzah Vicentia', 'Collector Kuddy Peggy is with Dzah Vicentia (#72) who needs a new Susu Card.', 'record_deposit.php?customer_id=99', 1, '2026-09-04 23:38:31'),
(24, NULL, 'warning', 'New Card Needed: Akpabla Mawusi', 'Collector Kuddy Peggy is with Akpabla Mawusi (#46) who needs a new Susu Card.', 'record_deposit.php?customer_id=73', 1, '2026-09-04 23:39:43'),
(25, NULL, 'warning', 'New Card Needed: Anyormi Comfort', 'Collector Kuddy Peggy is with Anyormi Comfort (#87) who needs a new Susu Card.', 'record_deposit.php?customer_id=114', 1, '2026-09-04 23:43:37'),
(26, NULL, 'warning', 'New Card Needed: Anyormi Comfort', 'Collector Kuddy Peggy is with Anyormi Comfort (#87) who needs a new Susu Card.', 'record_deposit.php?customer_id=114', 1, '2026-09-04 23:48:31'),
(27, NULL, 'warning', 'New Card Needed: Aglah Rose', 'Collector Kuddy Peggy is with Aglah Rose (#24) who needs a new Susu Card.', 'record_deposit.php?customer_id=54', 1, '2026-09-04 23:49:46'),
(28, NULL, 'warning', 'New Card Needed: Agbeve Faith', 'Collector Kuddy Peggy is with Agbeve Faith (#25) who needs a new Susu Card.', 'record_deposit.php?customer_id=55', 1, '2026-09-04 23:51:10'),
(29, NULL, 'warning', 'New Card Needed: Agbeve Judith', 'Collector Kuddy Peggy is with Agbeve Judith (#26) who needs a new Susu Card.', 'record_deposit.php?customer_id=56', 1, '2026-09-04 23:52:01'),
(30, NULL, 'warning', 'New Card Needed: Akpatsa Dasha', 'Collector Kuddy Peggy is with Akpatsa Dasha (#91) who needs a new Susu Card.', 'record_deposit.php?customer_id=118', 1, '2026-09-04 23:52:48'),
(31, NULL, 'warning', 'New Card Needed: Deku Sewa', 'Collector Kuddy Peggy is with Deku Sewa (#75) who needs a new Susu Card.', 'record_deposit.php?customer_id=102', 1, '2026-09-04 23:53:25'),
(32, NULL, 'warning', 'New Card Needed: Deku Sewa', 'Collector Kuddy Peggy is with Deku Sewa (#75) who needs a new Susu Card.', 'record_deposit.php?customer_id=102', 1, '2026-09-04 23:54:30'),
(33, NULL, 'warning', 'New Card Needed: Dzordzorme Peace', 'Collector Kuddy Peggy is with Dzordzorme Peace (#23) who needs a new Susu Card.', 'record_deposit.php?customer_id=53', 1, '2026-09-04 23:55:17'),
(34, NULL, 'warning', 'New Card Needed: Dzordzorme Peace', 'Collector Kuddy Peggy is with Dzordzorme Peace (#23) who needs a new Susu Card.', 'record_deposit.php?customer_id=53', 1, '2026-09-04 23:55:51'),
(35, NULL, 'warning', 'New Card Needed: Kpedo Gifty', 'Collector Kuddy Peggy is with Kpedo Gifty (#92) who needs a new Susu Card.', 'record_deposit.php?customer_id=119', 1, '2026-09-04 23:56:34'),
(36, NULL, 'warning', 'New Card Needed: Kpedo Gifty', 'Collector Kuddy Peggy is with Kpedo Gifty (#92) who needs a new Susu Card.', 'record_deposit.php?customer_id=119', 1, '2026-09-04 23:58:02'),
(37, NULL, 'warning', 'New Card Needed: Ofori Ernest', 'Collector Kuddy Peggy is with Ofori Ernest (#84) who needs a new Susu Card.', 'record_deposit.php?customer_id=111', 1, '2026-09-04 23:59:03'),
(38, NULL, 'warning', 'New Card Needed: Sunday', 'Collector Kuddy Peggy is with Sunday (#16) who needs a new Susu Card.', 'record_deposit.php?customer_id=48', 1, '2026-09-04 23:59:51'),
(39, NULL, 'warning', 'New Card Needed: Ali Fitter', 'Collector Kuddy Peggy is with Ali Fitter (#29) who needs a new Susu Card.', 'record_deposit.php?customer_id=59', 1, '2026-09-05 00:00:41'),
(40, NULL, 'handover_submitted', 'Daily Cash Handover Submitted', 'Kuddy Peggy submitted cash handover of GH₵ 12,560.00 (Expected: GH₵ 12,560.00).', 'daily_handover.php', 1, '2026-09-05 00:04:20'),
(41, 2, 'handover_approved', 'Cash Handover Approved', 'Your daily cash handover #1 of GH₵ 12,560.00 was approved. Liability cleared.', 'daily_handover.php', 1, '2026-09-05 00:04:48'),
(42, NULL, 'warning', 'New Card Needed: Ametor Saviour', 'Collector Kuddy Peggy is with Ametor Saviour (#51) who needs a new Susu Card.', 'record_deposit.php?customer_id=78', 1, '2026-09-05 00:08:38'),
(43, NULL, 'warning', 'New Card Needed: Deku Wonder', 'Collector Kuddy Peggy is with Deku Wonder (#4) who needs a new Susu Card.', 'record_deposit.php?customer_id=7', 1, '2026-09-05 00:10:20'),
(44, NULL, 'warning', 'New Card Needed: Soti Precious', 'Collector Kuddy Peggy is with Soti Precious (#68) who needs a new Susu Card.', 'record_deposit.php?customer_id=95', 1, '2026-09-05 00:11:28'),
(45, NULL, 'warning', 'New Card Needed: Agbenyenuse Bless', 'Collector Kuddy Peggy is with Agbenyenuse Bless (#45) who needs a new Susu Card.', 'record_deposit.php?customer_id=72', 1, '2026-09-05 00:12:21'),
(46, NULL, 'warning', 'New Card Needed: Kudi Lucky', 'Collector Kuddy Peggy is with Kudi Lucky (#5) who needs a new Susu Card.', 'record_deposit.php?customer_id=3', 1, '2026-09-05 00:13:37'),
(47, NULL, 'warning', 'New Card Needed: Anyadi Emmanuel', 'Collector Kuddy Peggy is with Anyadi Emmanuel (#43) who needs a new Susu Card.', 'record_deposit.php?customer_id=6', 1, '2026-09-05 00:14:31'),
(48, NULL, 'handover_submitted', 'Daily Cash Handover Submitted', 'Kuddy Peggy submitted cash handover of GH₵ 1,480.00 (Expected: GH₵ 1,480.00).', 'daily_handover.php', 1, '2026-09-05 00:16:44'),
(49, 2, 'handover_approved', 'Cash Handover Approved', 'Your daily cash handover #2 of GH₵ 1,480.00 was approved. Liability cleared.', 'daily_handover.php', 1, '2026-09-05 00:17:17');

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
(1, 36, 1, 20.00, 31, 0, 0.00, 'active', '2026-09-04 18:49:54', NULL),
(2, 47, 1, 20.00, 31, 9, 180.00, 'active', '2026-09-04 18:54:21', NULL),
(3, 67, 1, 50.00, 31, 19, 950.00, 'active', '2026-09-04 22:53:11', NULL),
(4, 1, 1, 50.00, 31, 12, 600.00, 'active', '2026-09-04 22:57:55', NULL),
(5, 2, 1, 50.00, 31, 16, 800.00, 'active', '2026-09-04 23:00:48', NULL),
(6, 121, 1, 10.00, 31, 23, 230.00, 'active', '2026-09-04 23:03:32', NULL),
(7, 5, 1, 10.00, 31, 31, 310.00, 'completed', '2026-09-04 23:04:51', '2026-09-05 03:05:31'),
(8, 5, 2, 10.00, 31, 21, 210.00, 'active', '2026-09-04 23:07:27', NULL),
(9, 93, 1, 50.00, 31, 15, 750.00, 'active', '2026-09-04 23:09:48', NULL),
(10, 83, 1, 20.00, 31, 4, 80.00, 'active', '2026-09-04 23:12:51', NULL),
(11, 134, 1, 50.00, 31, 5, 250.00, 'active', '2026-09-04 23:17:12', NULL),
(12, 92, 1, 10.00, 31, 31, 310.00, 'completed', '2026-09-04 23:21:06', '2026-09-05 03:21:55'),
(13, 92, 2, 10.00, 31, 13, 130.00, 'active', '2026-09-04 23:22:37', NULL),
(14, 80, 1, 20.00, 31, 27, 540.00, 'active', '2026-09-04 23:23:38', NULL),
(15, 135, 1, 20.00, 31, 5, 100.00, 'active', '2026-09-04 23:25:43', NULL),
(16, 112, 1, 10.00, 31, 18, 180.00, 'active', '2026-09-04 23:26:42', NULL),
(17, 132, 1, 10.00, 31, 3, 30.00, 'active', '2026-09-04 23:30:21', NULL),
(18, 44, 1, 20.00, 31, 24, 480.00, 'active', '2026-09-04 23:31:51', NULL),
(19, 57, 1, 5.00, 31, 16, 80.00, 'active', '2026-09-04 23:33:14', NULL),
(20, 45, 1, 20.00, 31, 5, 100.00, 'active', '2026-09-04 23:35:47', NULL),
(21, 126, 1, 10.00, 31, 9, 90.00, 'active', '2026-09-04 23:37:05', NULL),
(22, 99, 1, 10.00, 31, 21, 210.00, 'active', '2026-09-04 23:39:01', NULL),
(23, 73, 1, 10.00, 31, 7, 70.00, 'active', '2026-09-04 23:40:22', NULL),
(24, 114, 1, 10.00, 31, 31, 310.00, 'completed', '2026-09-04 23:44:09', '2026-09-05 03:47:53'),
(25, 114, 2, 10.00, 31, 0, 0.00, 'active', '2026-09-04 23:48:55', NULL),
(26, 54, 1, 20.00, 31, 27, 540.00, 'active', '2026-09-04 23:50:25', NULL),
(27, 55, 1, 20.00, 31, 27, 540.00, 'active', '2026-09-04 23:51:24', NULL),
(28, 56, 1, 30.00, 31, 27, 810.00, 'active', '2026-09-04 23:52:15', NULL),
(29, 118, 1, 20.00, 31, 21, 420.00, 'active', '2026-09-04 23:52:58', NULL),
(30, 102, 1, 10.00, 31, 31, 310.00, 'completed', '2026-09-04 23:53:54', '2026-09-05 03:54:18'),
(31, 102, 2, 20.00, 31, 11, 220.00, 'active', '2026-09-04 23:54:47', NULL),
(32, 53, 1, 20.00, 31, 31, 620.00, 'completed', '2026-09-04 23:55:28', '2026-09-05 03:55:42'),
(33, 53, 2, 50.00, 31, 6, 300.00, 'active', '2026-09-04 23:56:03', NULL),
(34, 119, 1, 20.00, 31, 31, 620.00, 'completed', '2026-09-04 23:57:28', '2026-09-05 03:57:48'),
(35, 119, 2, 20.00, 31, 0, 0.00, 'active', '2026-09-04 23:58:20', NULL),
(36, 111, 1, 20.00, 31, 16, 320.00, 'active', '2026-09-04 23:59:25', NULL),
(37, 48, 1, 30.00, 31, 23, 690.00, 'active', '2026-09-05 00:00:08', NULL),
(38, 59, 1, 30.00, 31, 6, 180.00, 'active', '2026-09-05 00:00:56', NULL),
(39, 78, 1, 10.00, 31, 25, 250.00, 'active', '2026-09-05 00:09:04', NULL),
(40, 7, 1, 20.00, 31, 4, 80.00, 'active', '2026-09-05 00:11:05', NULL),
(41, 95, 1, 20.00, 31, 22, 440.00, 'active', '2026-09-05 00:11:48', NULL),
(42, 72, 1, 20.00, 31, 28, 560.00, 'active', '2026-09-05 00:12:52', NULL),
(43, 3, 1, 100.00, 31, 0, 0.00, 'active', '2026-09-05 00:14:17', NULL),
(44, 6, 1, 10.00, 31, 15, 150.00, 'active', '2026-09-05 00:14:45', NULL);

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
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action`),
  ADD KEY `idx_audit_created` (`created_at`);

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
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=80;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `daily_handovers`
--
ALTER TABLE `daily_handovers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `deposits`
--
ALTER TABLE `deposits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=717;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `payouts`
--
ALTER TABLE `payouts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `susu_cards`
--
ALTER TABLE `susu_cards`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
