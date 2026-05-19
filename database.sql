-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 18, 2026 at 05:37 PM
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
-- Database: `ghost_incubator`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `role` enum('admin','user') NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `role`, `user_id`, `action`, `details`, `ip_address`, `logged_at`) VALUES
(1, 'user', 1, 'Login', 'User logged in', '::1', '2026-05-09 14:42:19'),
(2, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-09 14:57:24'),
(3, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-09 15:43:49'),
(4, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-09 15:56:40'),
(5, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-09 16:10:03'),
(6, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-09 16:10:04'),
(7, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-09 16:30:38'),
(8, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-09 16:34:32'),
(9, 'user', 1, 'stop_session', 'Session stopped for incubator #1', '::1', '2026-05-09 16:34:46'),
(10, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-09 16:34:46'),
(11, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-09 16:34:48'),
(12, 'user', 1, 'stop_session', 'Session stopped for incubator #1', '::1', '2026-05-09 16:34:51'),
(13, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-09 16:34:51'),
(14, 'user', 1, 'Login', 'User logged in', '::1', '2026-05-13 11:21:49'),
(15, 'user', 1, 'Login', 'User logged in', '::1', '2026-05-18 03:13:35'),
(16, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 04:34:54'),
(17, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 04:34:54'),
(18, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 04:41:43'),
(19, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 04:43:07'),
(20, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 04:57:44'),
(21, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 05:24:05'),
(22, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 05:24:05'),
(23, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 05:27:26'),
(24, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 05:36:28'),
(25, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 05:36:28'),
(26, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 05:39:40'),
(27, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 05:39:42'),
(28, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 05:39:57'),
(29, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 05:49:49'),
(30, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 05:49:49'),
(31, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 05:53:23'),
(32, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 05:53:44'),
(33, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 05:53:53'),
(34, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 05:54:31'),
(35, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 05:54:31'),
(36, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 05:55:05'),
(37, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 05:55:06'),
(38, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 05:55:13'),
(39, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 06:01:04'),
(40, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 06:01:05'),
(41, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 06:01:21'),
(42, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 06:01:28'),
(43, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 06:24:01'),
(44, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 06:24:01'),
(45, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 06:24:14'),
(46, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 06:24:16'),
(47, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 06:24:25'),
(48, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 06:30:57'),
(49, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 06:30:57'),
(50, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 06:31:10'),
(51, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 06:31:18'),
(52, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 06:44:50'),
(53, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 06:44:50'),
(54, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 06:45:06'),
(55, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 06:45:13'),
(56, 'user', 1, 'Log Temperature', 'Logged temp 0°C for incubator ID: 1', '::1', '2026-05-18 07:19:41'),
(57, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 07:22:09'),
(58, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 07:22:09'),
(59, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 07:22:56'),
(60, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 07:22:58'),
(61, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 07:23:02'),
(62, 'user', 1, 'Add Batch', 'Started: wakwak', '::1', '2026-05-18 07:23:33'),
(63, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 08:01:27'),
(64, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 08:01:27'),
(65, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 08:02:18'),
(66, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 08:02:36'),
(67, 'admin', 1, 'Login', 'Admin logged in', '::1', '2026-05-18 08:07:42'),
(68, 'admin', 1, 'Login', 'Admin logged in', '::1', '2026-05-18 08:10:25'),
(69, 'user', 1, 'Login', 'User logged in', '::1', '2026-05-18 08:14:55'),
(70, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 08:15:10'),
(71, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 08:15:16'),
(72, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 08:20:14'),
(73, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 08:20:14'),
(74, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 08:20:40'),
(75, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 08:20:52'),
(76, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 08:24:22'),
(77, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 08:24:22'),
(78, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 08:26:37'),
(79, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 08:26:45'),
(80, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 08:57:22'),
(81, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 08:57:22'),
(82, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 08:57:31'),
(83, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 08:57:40'),
(84, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 09:05:09'),
(85, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 09:05:09'),
(86, 'user', 1, 'Update Settings', 'Updated temp settings for incubator ID: 1', '::1', '2026-05-18 09:05:20'),
(87, 'user', 1, 'manual_hardware_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 09:05:30'),
(88, 'user', 1, 'stop_session', 'Session terminated for incubator #1', '::1', '2026-05-18 09:06:39'),
(89, 'user', 1, 'manual_hardware_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 09:06:39'),
(90, 'user', 1, 'Login', 'User logged in', '::1', '2026-05-18 13:54:01'),
(91, 'user', 1, 'Log Temperature', 'Logged temp 0°C for incubator ID: 1', '::1', '2026-05-18 14:58:45'),
(92, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:03:23'),
(93, 'user', 1, 'relay_test_command', 'Command \'swing_on\' sent to incubator #1', '::1', '2026-05-18 15:03:24'),
(94, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:03:26'),
(95, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:04:24'),
(96, 'user', 1, 'relay_test_command', 'Command \'swing_off\' sent to incubator #1', '::1', '2026-05-18 15:05:33'),
(97, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:05:34'),
(98, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:09:12'),
(99, 'user', 1, 'relay_test_command', 'Command \'swing_off\' sent to incubator #1', '::1', '2026-05-18 15:09:14'),
(100, 'user', 1, 'relay_test_command', 'Command \'swing_off\' sent to incubator #1', '::1', '2026-05-18 15:09:15'),
(101, 'user', 1, 'relay_test_command', 'Command \'swing_off\' sent to incubator #1', '::1', '2026-05-18 15:09:15'),
(102, 'user', 1, 'relay_test_command', 'Command \'swing_off\' sent to incubator #1', '::1', '2026-05-18 15:09:15'),
(103, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:09:16'),
(104, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:09:17'),
(105, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:09:17'),
(106, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:09:17'),
(107, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:09:18'),
(108, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:09:18'),
(109, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:09:19'),
(110, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:09:20'),
(111, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:09:22'),
(112, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:09:22'),
(113, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:09:22'),
(114, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:09:24'),
(115, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:09:24'),
(116, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:09:25'),
(117, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:09:36'),
(118, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:14:51'),
(119, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:14:55'),
(120, 'user', 1, 'relay_test_command', 'Command \'swing_off\' sent to incubator #1', '::1', '2026-05-18 15:14:58'),
(121, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:15:02'),
(122, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:15:03'),
(123, 'user', 1, 'relay_test_command', 'Command \'swing_on\' sent to incubator #1', '::1', '2026-05-18 15:15:05'),
(124, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:15:07'),
(125, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:22:10'),
(126, 'user', 1, 'relay_test_command', 'Command \'swing_on\' sent to incubator #1', '::1', '2026-05-18 15:22:19'),
(127, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:22:20'),
(128, 'user', 1, 'relay_test_command', 'Command \'heater_off\' sent to incubator #1', '::1', '2026-05-18 15:22:20'),
(129, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:22:21'),
(130, 'user', 1, 'relay_test_command', 'Command \'swing_on\' sent to incubator #1', '::1', '2026-05-18 15:22:22'),
(131, 'user', 1, 'relay_test_command', 'Command \'heater_on\' sent to incubator #1', '::1', '2026-05-18 15:22:23'),
(132, 'user', 1, 'relay_test_command', 'Command \'all_off\' sent to incubator #1', '::1', '2026-05-18 15:26:05');

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `email`, `password`, `profile_pic`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Ghost Admin', 'admin@ghost.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'active', '2026-05-09 14:40:48', '2026-05-09 14:40:48');

-- --------------------------------------------------------

--
-- Table structure for table `alerts`
--

CREATE TABLE `alerts` (
  `id` int(11) NOT NULL,
  `incubator_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `alert_type` enum('temp_high','temp_low','humidity_high','humidity_low','schedule_missed','hatch_due','system') DEFAULT 'system',
  `message` text NOT NULL,
  `severity` enum('info','warning','danger') DEFAULT 'info',
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alerts`
--

INSERT INTO `alerts` (`id`, `incubator_id`, `batch_id`, `alert_type`, `message`, `severity`, `is_read`, `created_at`) VALUES
(1, 1, NULL, 'temp_high', 'Temperature exceeded 38°C in Alpha Unit', 'warning', 0, '2026-05-09 14:40:48'),
(2, 3, NULL, 'system', 'Gamma Unit maintenance scheduled', 'info', 0, '2026-05-09 14:40:48'),
(3, 2, NULL, 'hatch_due', 'Batch Beta-01 expected to hatch in 8 days', 'info', 0, '2026-05-09 14:40:48'),
(4, 1, NULL, 'temp_low', 'Hardware: Temperature 34.3°C below min 37.00°C', 'danger', 0, '2026-05-18 04:43:09'),
(5, 1, NULL, 'temp_low', 'Hardware: Temperature 33.8°C below min 37.00°C', 'danger', 0, '2026-05-18 04:57:48'),
(6, 1, NULL, 'humidity_high', 'Hardware: Humidity 62.2% exceeds max 60.00%', 'warning', 0, '2026-05-18 04:57:48'),
(7, 1, NULL, 'temp_low', 'Hardware: Temperature 33.8°C below min 37.00°C', 'danger', 0, '2026-05-18 05:05:28'),
(8, 1, NULL, 'humidity_high', 'Hardware: Humidity 62.8% exceeds max 60.00%', 'warning', 0, '2026-05-18 05:05:28'),
(9, 1, NULL, 'temp_low', 'Hardware: Temperature 33.9°C below min 37.00°C', 'danger', 0, '2026-05-18 05:06:28'),
(10, 1, NULL, 'humidity_high', 'Hardware: Humidity 62.2% exceeds max 60.00%', 'warning', 0, '2026-05-18 05:06:28'),
(11, 1, NULL, 'temp_low', 'Hardware: Temperature 34.4°C below min 37.00°C', 'danger', 0, '2026-05-18 05:07:28'),
(12, 1, NULL, 'humidity_high', 'Hardware: Humidity 61.1% exceeds max 60.00%', 'warning', 0, '2026-05-18 05:07:28'),
(13, 1, NULL, 'temp_low', 'Hardware: Temperature 35.2°C below min 37.00°C', 'danger', 0, '2026-05-18 05:08:28'),
(14, 1, NULL, 'temp_low', 'Hardware: Temperature 36.3°C below min 37.00°C', 'danger', 0, '2026-05-18 05:09:28'),
(15, 1, NULL, 'temp_high', 'Hardware: Temperature 38.4°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 05:11:28'),
(16, 1, NULL, 'temp_high', 'Hardware: Temperature 38.6°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 05:12:28'),
(17, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 05:13:28'),
(18, 1, NULL, 'temp_low', 'Hardware: Temperature 36.9°C below min 37.00°C', 'danger', 0, '2026-05-18 05:19:28'),
(19, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 05:29:26'),
(20, 1, NULL, 'temp_high', 'Hardware: Temperature 38.6°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 05:30:26'),
(21, 1, NULL, 'temp_high', 'Hardware: Temperature 38.5°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 05:31:26'),
(22, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 05:32:26'),
(23, 1, NULL, 'temp_high', 'Hardware: Temperature 38.1°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 05:33:26'),
(24, 1, NULL, 'humidity_low', 'Hardware: Humidity 49.9% below min 50.00%', 'warning', 0, '2026-05-18 05:49:29'),
(25, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 37.00°C', 'warning', 0, '2026-05-18 05:55:16'),
(26, 1, NULL, 'temp_high', 'Hardware: Temperature 38.5°C exceeds max 37.00°C', 'warning', 0, '2026-05-18 05:56:16'),
(27, 1, NULL, 'temp_high', 'Hardware: Temperature 38.4°C exceeds max 37.00°C', 'warning', 0, '2026-05-18 05:57:16'),
(28, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 37.00°C', 'warning', 0, '2026-05-18 05:58:16'),
(29, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 37.00°C', 'warning', 0, '2026-05-18 05:59:16'),
(30, 1, NULL, 'temp_high', 'Hardware: Temperature 38°C exceeds max 37.00°C', 'warning', 0, '2026-05-18 06:00:16'),
(31, 1, NULL, 'temp_high', 'Hardware: Temperature 37.9°C exceeds max 37.00°C', 'warning', 0, '2026-05-18 06:01:16'),
(32, 1, NULL, 'temp_high', 'Hardware: Temperature 37.8°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:02:16'),
(33, 1, NULL, 'temp_high', 'Hardware: Temperature 37.7°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:03:16'),
(34, 1, NULL, 'temp_high', 'Hardware: Temperature 37.7°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:04:16'),
(35, 1, NULL, 'temp_high', 'Hardware: Temperature 37.6°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:05:16'),
(36, 1, NULL, 'temp_high', 'Hardware: Temperature 37.5°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:06:16'),
(37, 1, NULL, 'temp_high', 'Hardware: Temperature 37.5°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:07:17'),
(38, 1, NULL, 'temp_high', 'Hardware: Temperature 37.4°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:08:16'),
(39, 1, NULL, 'temp_high', 'Hardware: Temperature 37.3°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:09:17'),
(40, 1, NULL, 'temp_high', 'Hardware: Temperature 37.3°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:10:17'),
(41, 1, NULL, 'temp_high', 'Hardware: Temperature 37.2°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:11:17'),
(42, 1, NULL, 'temp_high', 'Hardware: Temperature 36.9°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:16:35'),
(43, 1, NULL, 'temp_high', 'Hardware: Temperature 36.9°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:17:35'),
(44, 1, NULL, 'humidity_low', 'Hardware: Humidity 49.9% below min 50.00%', 'warning', 0, '2026-05-18 06:29:14'),
(45, 1, NULL, 'temp_high', 'Hardware: Temperature 39.9°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:31:12'),
(46, 1, NULL, 'humidity_low', 'Hardware: Humidity 46.7% below min 50.00%', 'warning', 0, '2026-05-18 06:31:12'),
(47, 1, NULL, 'temp_high', 'Hardware: Temperature 40.4°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:32:12'),
(48, 1, NULL, 'humidity_low', 'Hardware: Humidity 45.5% below min 50.00%', 'warning', 0, '2026-05-18 06:32:12'),
(49, 1, NULL, 'temp_high', 'Hardware: Temperature 40.3°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:33:12'),
(50, 1, NULL, 'humidity_low', 'Hardware: Humidity 45.5% below min 50.00%', 'warning', 0, '2026-05-18 06:33:12'),
(51, 1, NULL, 'temp_high', 'Hardware: Temperature 39.7°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:34:12'),
(52, 1, NULL, 'humidity_low', 'Hardware: Humidity 46.6% below min 50.00%', 'warning', 0, '2026-05-18 06:34:12'),
(53, 1, NULL, 'temp_high', 'Hardware: Temperature 39.3°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:35:12'),
(54, 1, NULL, 'humidity_low', 'Hardware: Humidity 46.1% below min 50.00%', 'warning', 0, '2026-05-18 06:35:12'),
(55, 1, NULL, 'temp_high', 'Hardware: Temperature 39°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:36:12'),
(56, 1, NULL, 'humidity_low', 'Hardware: Humidity 46.6% below min 50.00%', 'warning', 0, '2026-05-18 06:36:12'),
(57, 1, NULL, 'temp_high', 'Hardware: Temperature 38.6°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:37:12'),
(58, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.3% below min 50.00%', 'warning', 0, '2026-05-18 06:37:12'),
(59, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:38:12'),
(60, 1, NULL, 'humidity_low', 'Hardware: Humidity 46.8% below min 50.00%', 'warning', 0, '2026-05-18 06:38:12'),
(61, 1, NULL, 'temp_high', 'Hardware: Temperature 37.9°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:39:12'),
(62, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.1% below min 50.00%', 'warning', 0, '2026-05-18 06:39:12'),
(63, 1, NULL, 'temp_high', 'Hardware: Temperature 37.8°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:40:12'),
(64, 1, NULL, 'humidity_low', 'Hardware: Humidity 48.3% below min 50.00%', 'warning', 0, '2026-05-18 06:40:12'),
(65, 1, NULL, 'temp_high', 'Hardware: Temperature 37.5°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:41:12'),
(66, 1, NULL, 'temp_high', 'Hardware: Temperature 37.4°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:42:12'),
(67, 1, NULL, 'temp_high', 'Hardware: Temperature 37.3°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:43:12'),
(68, 1, NULL, 'temp_high', 'Hardware: Temperature 37.1°C exceeds max 36.00°C', 'warning', 0, '2026-05-18 06:44:12'),
(69, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 07:20:48'),
(70, 1, NULL, 'humidity_low', 'Hardware: Humidity 48.8% below min 50.00%', 'warning', 0, '2026-05-18 07:20:48'),
(71, 1, NULL, 'temp_high', 'Hardware: Temperature 38.7°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 07:21:49'),
(72, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.7% below min 50.00%', 'warning', 0, '2026-05-18 07:21:49'),
(73, 1, NULL, 'temp_high', 'Hardware: Temperature 38.5°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 07:23:35'),
(74, 1, NULL, 'humidity_low', 'Hardware: Humidity 48.6% below min 50.00%', 'warning', 0, '2026-05-18 07:23:35'),
(75, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:06:44'),
(76, 1, NULL, 'humidity_low', 'Hardware: Humidity 48.6% below min 50.00%', 'warning', 0, '2026-05-18 08:06:44'),
(77, 1, NULL, 'temp_high', 'Hardware: Temperature 38.5°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:07:44'),
(78, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.7% below min 50.00%', 'warning', 0, '2026-05-18 08:07:44'),
(79, 1, NULL, 'temp_high', 'Hardware: Temperature 38.4°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:08:44'),
(80, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.5% below min 50.00%', 'warning', 0, '2026-05-18 08:08:44'),
(81, 1, NULL, 'temp_high', 'Hardware: Temperature 38.1°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:09:44'),
(82, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.9% below min 50.00%', 'warning', 0, '2026-05-18 08:09:44'),
(83, 1, NULL, 'humidity_low', 'Hardware: Humidity 48.1% below min 50.00%', 'warning', 0, '2026-05-18 08:10:44'),
(84, 1, NULL, 'humidity_low', 'Hardware: Humidity 49.7% below min 50.00%', 'warning', 0, '2026-05-18 08:18:20'),
(85, 1, NULL, 'temp_high', 'Hardware: Temperature 38.5°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:19:21'),
(86, 1, NULL, 'humidity_low', 'Hardware: Humidity 49.1% below min 50.00%', 'warning', 0, '2026-05-18 08:19:21'),
(87, 1, NULL, 'temp_high', 'Hardware: Temperature 38.4°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:20:24'),
(88, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.8% below min 50.00%', 'warning', 0, '2026-05-18 08:20:24'),
(89, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:21:21'),
(90, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.9% below min 50.00%', 'warning', 0, '2026-05-18 08:21:21'),
(91, 1, NULL, 'humidity_low', 'Hardware: Humidity 47.7% below min 50.00%', 'warning', 0, '2026-05-18 08:22:20'),
(92, 1, NULL, 'humidity_low', 'Hardware: Humidity 48.2% below min 50.00%', 'warning', 0, '2026-05-18 08:23:21'),
(93, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:30:51'),
(94, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:31:51'),
(95, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:32:51'),
(96, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:33:52'),
(97, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 08:34:52'),
(98, 1, NULL, 'temp_low', 'Hardware: Temperature 35.9°C below min 36.00°C', 'danger', 0, '2026-05-18 08:55:01'),
(99, 1, NULL, 'temp_low', 'Hardware: Temperature 35.9°C below min 36.00°C', 'danger', 0, '2026-05-18 08:56:01'),
(100, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 09:03:02'),
(101, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 09:04:02'),
(102, 1, NULL, 'temp_high', 'Hardware: Temperature 38.3°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 09:05:02'),
(103, 1, NULL, 'temp_high', 'Hardware: Temperature 38.2°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 09:06:02'),
(104, 1, NULL, 'temp_high', 'Hardware: Temperature 38.1°C exceeds max 38.00°C', 'warning', 0, '2026-05-18 09:07:02');

-- --------------------------------------------------------

--
-- Table structure for table `batches`
--

CREATE TABLE `batches` (
  `id` int(11) NOT NULL,
  `incubator_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `batch_name` varchar(100) NOT NULL,
  `egg_type` varchar(100) DEFAULT 'Chicken',
  `egg_count` int(11) DEFAULT 0,
  `start_date` date NOT NULL,
  `expected_hatch_date` date NOT NULL,
  `status` enum('scheduled','incubating','completed','terminated','hatched','failed','cancelled') DEFAULT 'incubating',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `terminated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `batches`
--

INSERT INTO `batches` (`id`, `incubator_id`, `user_id`, `batch_name`, `egg_type`, `egg_count`, `start_date`, `expected_hatch_date`, `status`, `notes`, `created_at`, `completed_at`, `terminated_at`) VALUES
(5, 1, 1, 'Alpha Unit - 0 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 04:57:44', NULL, '2026-05-18 13:24:05'),
(6, 1, 1, 'Alpha Unit - 10 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 05:27:26', NULL, '2026-05-18 13:36:28'),
(7, 1, 1, 'Alpha Unit - 50 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 05:39:57', NULL, '2026-05-18 13:49:49'),
(8, 1, 1, 'Alpha Unit - 10 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 05:53:53', NULL, '2026-05-18 13:54:31'),
(9, 1, 1, 'Alpha Unit - 10 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 05:55:13', NULL, '2026-05-18 14:01:04'),
(10, 1, 1, 'Alpha Unit - 10 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 06:01:28', NULL, '2026-05-18 14:24:01'),
(11, 1, 1, 'Alpha Unit - 10 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 06:24:25', NULL, '2026-05-18 14:30:57'),
(12, 1, 1, 'Alpha Unit - 10 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 06:31:18', NULL, '2026-05-18 14:44:50'),
(13, 1, 1, 'Alpha Unit - 10 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', '', '2026-05-18 06:45:13', NULL, '2026-05-18 15:22:09'),
(14, 1, 1, 'wakwak', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', '', '2026-05-18 07:23:33', NULL, '2026-05-18 16:01:27'),
(15, 1, 1, 'Alpha Unit - 15 eggs', 'Chicken', 15, '2026-05-18', '2026-06-08', 'cancelled', NULL, '2026-05-18 08:02:36', NULL, NULL),
(16, 1, 1, 'Alpha Unit - 15 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 08:15:16', NULL, '2026-05-18 16:20:14'),
(17, 1, 1, 'Alpha Unit - 37 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 08:20:52', NULL, '2026-05-18 16:24:22'),
(18, 1, 1, 'Alpha Unit - 69 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 08:26:45', NULL, '2026-05-18 16:57:22'),
(19, 1, 1, 'Alpha Unit - 69 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 08:57:40', NULL, '2026-05-18 17:05:09'),
(20, 1, 1, 'Alpha Unit - 50 eggs', 'Chicken', 0, '2026-05-18', '2026-06-08', 'terminated', NULL, '2026-05-18 09:05:30', NULL, '2026-05-18 17:06:39');

-- --------------------------------------------------------

--
-- Table structure for table `hardware_commands`
--

CREATE TABLE `hardware_commands` (
  `id` int(11) NOT NULL,
  `incubator_id` int(11) NOT NULL,
  `command` varchar(64) NOT NULL,
  `issued_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `hardware_commands`
--

INSERT INTO `hardware_commands` (`id`, `incubator_id`, `command`, `issued_at`) VALUES
(95, 1, 'all_off', '2026-05-18 23:26:05');

-- --------------------------------------------------------

--
-- Table structure for table `hardware_state`
--

CREATE TABLE `hardware_state` (
  `incubator_id` int(11) NOT NULL,
  `temperature` decimal(5,2) DEFAULT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `device_status` enum('online','offline') NOT NULL DEFAULT 'offline',
  `current_temp` decimal(5,2) DEFAULT NULL,
  `current_humidity` decimal(5,2) DEFAULT NULL,
  `heater_on` tinyint(1) NOT NULL DEFAULT 0,
  `heater_1_status` tinyint(1) NOT NULL DEFAULT 0,
  `heater_2_status` tinyint(1) NOT NULL DEFAULT 0,
  `heater_fan_status` tinyint(1) NOT NULL DEFAULT 0,
  `exhaust_status` tinyint(1) NOT NULL DEFAULT 0,
  `swing_on` tinyint(1) NOT NULL DEFAULT 0,
  `swing_status` tinyint(1) NOT NULL DEFAULT 0,
  `last_seen` timestamp NULL DEFAULT NULL,
  `last_active_at` timestamp NULL DEFAULT NULL,
  `last_server_sync` timestamp NULL DEFAULT NULL,
  `last_egg_turn_at` datetime DEFAULT NULL,
  `running_ops` varchar(255) DEFAULT NULL,
  `wifi_status` enum('connected','disconnected') NOT NULL DEFAULT 'connected',
  `current_mode` enum('idle','incubating','hatching') NOT NULL DEFAULT 'idle',
  `active_session_name` varchar(150) DEFAULT NULL,
  `swing_duration_sec` int(11) NOT NULL DEFAULT 30,
  `session_started_at` datetime DEFAULT NULL,
  `session_ends_at` datetime DEFAULT NULL,
  `session_completed_at` datetime DEFAULT NULL,
  `session_status` enum('idle','running','completed') NOT NULL DEFAULT 'idle',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `hardware_state`
--

INSERT INTO `hardware_state` (`incubator_id`, `temperature`, `humidity`, `device_status`, `current_temp`, `current_humidity`, `heater_on`, `heater_1_status`, `heater_2_status`, `heater_fan_status`, `exhaust_status`, `swing_on`, `swing_status`, `last_seen`, `last_active_at`, `last_server_sync`, `last_egg_turn_at`, `running_ops`, `wifi_status`, `current_mode`, `active_session_name`, `swing_duration_sec`, `session_started_at`, `session_ends_at`, `session_completed_at`, `session_status`, `updated_at`) VALUES
(1, 37.60, 52.70, 'online', 37.60, 52.70, 0, 0, 0, 0, 0, 0, 0, '2026-05-18 03:12:48', '2026-05-18 03:12:48', '2026-05-18 03:12:48', NULL, 'idle', 'connected', 'idle', NULL, 30, NULL, NULL, NULL, 'idle', '2026-05-18 15:31:04');

-- --------------------------------------------------------

--
-- Table structure for table `incubators`
--

CREATE TABLE `incubators` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `model` varchar(100) DEFAULT NULL,
  `capacity` int(11) DEFAULT 0,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('active','idle','maintenance','error') DEFAULT 'idle',
  `owner_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `incubators`
--

INSERT INTO `incubators` (`id`, `name`, `model`, `capacity`, `location`, `status`, `owner_id`, `created_at`, `updated_at`) VALUES
(1, 'Alpha Unit', 'GH-Pro 1000', 100, 'Farm House A', 'active', 1, '2026-05-09 14:40:48', '2026-05-09 14:40:48'),
(2, 'Beta Unit', 'GH-Pro 500', 50, 'Farm House B', 'idle', 1, '2026-05-09 14:40:48', '2026-05-09 14:40:48'),
(3, 'Gamma Unit', 'GH-Mini 200', 200, 'Greenhouse C', 'maintenance', 1, '2026-05-09 14:40:48', '2026-05-09 14:40:48');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `incubator_id` int(11) NOT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time NOT NULL,
  `action_type` enum('turning','temperature_check','humidity_check','candling','maintenance','hatch') DEFAULT 'turning',
  `target_temp` decimal(5,2) DEFAULT NULL,
  `target_humidity` decimal(5,2) DEFAULT NULL,
  `status` enum('pending','done','missed','cancelled') DEFAULT 'pending',
  `created_by_role` enum('admin','user') DEFAULT 'admin',
  `created_by_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `incubator_id`, `batch_id`, `title`, `description`, `scheduled_date`, `scheduled_time`, `action_type`, `target_temp`, `target_humidity`, `status`, `created_by_role`, `created_by_id`, `created_at`) VALUES
(1, 1, NULL, 'Morning Egg Turning', NULL, '2026-05-09', '06:00:00', 'turning', NULL, NULL, 'done', 'admin', 1, '2026-05-09 14:40:48'),
(2, 1, NULL, 'Midday Temperature Check', NULL, '2026-05-09', '12:00:00', 'temperature_check', NULL, NULL, 'pending', 'admin', 1, '2026-05-09 14:40:48'),
(3, 1, NULL, 'Evening Candling Session', NULL, '2026-05-10', '17:00:00', 'candling', NULL, NULL, 'pending', 'admin', 1, '2026-05-09 14:40:48'),
(4, 2, NULL, 'Duck Batch Humidity Check', NULL, '2026-05-09', '09:00:00', 'humidity_check', NULL, NULL, 'done', 'admin', 1, '2026-05-09 14:40:48');

-- --------------------------------------------------------

--
-- Table structure for table `temperature_logs`
--

CREATE TABLE `temperature_logs` (
  `id` int(11) NOT NULL,
  `incubator_id` int(11) NOT NULL,
  `temperature` decimal(5,2) NOT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `recorded_by` enum('admin','user','auto') DEFAULT 'auto',
  `recorded_id` int(11) DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `temperature_logs`
--

INSERT INTO `temperature_logs` (`id`, `incubator_id`, `temperature`, `humidity`, `recorded_by`, `recorded_id`, `recorded_at`) VALUES
(1, 1, 37.40, 54.50, 'auto', NULL, '2026-05-09 08:40:48'),
(2, 1, 37.60, 55.20, 'auto', NULL, '2026-05-09 09:40:48'),
(3, 1, 37.50, 55.00, 'auto', NULL, '2026-05-09 10:40:48'),
(4, 1, 37.70, 55.80, 'auto', NULL, '2026-05-09 11:40:48'),
(5, 1, 37.50, 54.90, 'auto', NULL, '2026-05-09 12:40:48'),
(6, 1, 37.40, 55.10, 'auto', NULL, '2026-05-09 13:40:48'),
(7, 1, 37.60, 55.30, 'auto', NULL, '2026-05-09 14:40:48'),
(8, 1, 30.80, 57.00, 'auto', NULL, '2026-05-09 15:44:20'),
(9, 1, 30.70, 58.00, 'auto', NULL, '2026-05-09 15:45:20'),
(10, 1, 30.70, 58.00, 'auto', NULL, '2026-05-09 15:46:20'),
(11, 1, 30.60, 58.00, 'auto', NULL, '2026-05-09 15:47:20'),
(12, 1, 30.40, 59.00, 'auto', NULL, '2026-05-09 15:48:20'),
(13, 1, 30.30, 59.00, 'auto', NULL, '2026-05-09 15:49:20'),
(14, 1, 30.20, 60.00, 'auto', NULL, '2026-05-09 15:50:20'),
(15, 1, 30.20, 60.00, 'auto', NULL, '2026-05-09 15:51:20'),
(16, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 15:52:20'),
(17, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 15:53:20'),
(18, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 15:54:20'),
(19, 1, 30.20, 60.00, 'auto', NULL, '2026-05-09 15:55:20'),
(20, 1, 30.20, 60.00, 'auto', NULL, '2026-05-09 15:56:20'),
(21, 1, 30.30, 59.00, 'auto', NULL, '2026-05-09 15:57:20'),
(22, 1, 30.40, 59.00, 'auto', NULL, '2026-05-09 15:58:20'),
(23, 1, 30.50, 59.00, 'auto', NULL, '2026-05-09 15:59:20'),
(24, 1, 30.50, 59.00, 'auto', NULL, '2026-05-09 16:00:20'),
(25, 1, 30.40, 59.00, 'auto', NULL, '2026-05-09 16:01:20'),
(26, 1, 30.40, 59.00, 'auto', NULL, '2026-05-09 16:02:20'),
(27, 1, 30.40, 59.00, 'auto', NULL, '2026-05-09 16:03:20'),
(28, 1, 30.40, 59.00, 'auto', NULL, '2026-05-09 16:04:20'),
(29, 1, 30.40, 59.00, 'auto', NULL, '2026-05-09 16:05:21'),
(30, 1, 30.30, 59.00, 'auto', NULL, '2026-05-09 16:06:21'),
(31, 1, 30.10, 59.00, 'auto', NULL, '2026-05-09 16:07:21'),
(32, 1, 30.00, 60.00, 'auto', NULL, '2026-05-09 16:08:21'),
(33, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 16:09:21'),
(34, 1, 30.20, 59.00, 'auto', NULL, '2026-05-09 16:10:21'),
(35, 1, 30.20, 59.00, 'auto', NULL, '2026-05-09 16:11:21'),
(36, 1, 30.20, 59.00, 'auto', NULL, '2026-05-09 16:12:21'),
(37, 1, 30.10, 59.00, 'auto', NULL, '2026-05-09 16:13:21'),
(38, 1, 30.00, 59.00, 'auto', NULL, '2026-05-09 16:14:21'),
(39, 1, 30.00, 60.00, 'auto', NULL, '2026-05-09 16:15:21'),
(40, 1, 30.00, 60.00, 'auto', NULL, '2026-05-09 16:16:21'),
(41, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 16:17:21'),
(42, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 16:18:21'),
(43, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 16:19:21'),
(44, 1, 29.90, 60.00, 'auto', NULL, '2026-05-09 16:20:21'),
(45, 1, 29.80, 60.00, 'auto', NULL, '2026-05-09 16:21:21'),
(46, 1, 29.80, 60.00, 'auto', NULL, '2026-05-09 16:22:22'),
(47, 1, 29.80, 61.00, 'auto', NULL, '2026-05-09 16:23:21'),
(48, 1, 30.00, 61.00, 'auto', NULL, '2026-05-09 16:24:21'),
(49, 1, 30.00, 61.00, 'auto', NULL, '2026-05-09 16:25:22'),
(50, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 16:26:22'),
(51, 1, 30.10, 60.00, 'auto', NULL, '2026-05-09 16:27:22'),
(52, 1, 30.00, 60.00, 'auto', NULL, '2026-05-09 16:28:22'),
(53, 1, 29.90, 61.00, 'auto', NULL, '2026-05-09 16:29:22'),
(54, 1, 29.90, 61.00, 'auto', NULL, '2026-05-09 16:30:22'),
(55, 1, 29.90, 61.00, 'auto', NULL, '2026-05-09 16:31:22'),
(56, 1, 30.00, 61.00, 'auto', NULL, '2026-05-09 16:32:23'),
(57, 1, 30.00, 61.00, 'auto', NULL, '2026-05-09 16:33:22'),
(58, 1, 30.00, 61.00, 'auto', NULL, '2026-05-09 16:34:22'),
(59, 1, 30.10, 61.00, 'auto', NULL, '2026-05-09 16:35:22'),
(60, 1, 30.00, 61.00, 'auto', NULL, '2026-05-09 16:36:23'),
(61, 1, 29.90, 61.00, 'auto', NULL, '2026-05-09 16:37:23'),
(62, 1, 29.80, 61.00, 'auto', NULL, '2026-05-09 16:38:23'),
(63, 1, 29.80, 61.00, 'auto', NULL, '2026-05-09 16:39:23'),
(64, 1, 29.80, 61.00, 'auto', NULL, '2026-05-09 16:40:23'),
(65, 1, 29.80, 61.00, 'auto', NULL, '2026-05-09 16:41:23'),
(66, 1, 29.80, 61.00, 'auto', NULL, '2026-05-09 16:42:23'),
(67, 1, 29.80, 61.00, 'auto', NULL, '2026-05-09 16:43:23'),
(68, 1, 29.90, 61.00, 'auto', NULL, '2026-05-09 16:44:23'),
(69, 1, 34.30, 59.90, 'auto', NULL, '2026-05-18 04:43:09'),
(70, 1, 33.80, 62.20, 'auto', NULL, '2026-05-18 04:57:48'),
(71, 1, 33.80, 62.80, 'auto', NULL, '2026-05-18 05:05:28'),
(72, 1, 33.90, 62.20, 'auto', NULL, '2026-05-18 05:06:28'),
(73, 1, 34.40, 61.10, 'auto', NULL, '2026-05-18 05:07:28'),
(74, 1, 35.20, 58.80, 'auto', NULL, '2026-05-18 05:08:28'),
(75, 1, 36.30, 56.50, 'auto', NULL, '2026-05-18 05:09:28'),
(76, 1, 37.50, 54.30, 'auto', NULL, '2026-05-18 05:10:28'),
(77, 1, 38.40, 52.80, 'auto', NULL, '2026-05-18 05:11:28'),
(78, 1, 38.60, 52.40, 'auto', NULL, '2026-05-18 05:12:28'),
(79, 1, 38.30, 53.00, 'auto', NULL, '2026-05-18 05:13:28'),
(80, 1, 38.00, 53.70, 'auto', NULL, '2026-05-18 05:14:28'),
(81, 1, 37.70, 54.30, 'auto', NULL, '2026-05-18 05:15:28'),
(82, 1, 37.40, 54.90, 'auto', NULL, '2026-05-18 05:16:28'),
(83, 1, 37.20, 55.40, 'auto', NULL, '2026-05-18 05:17:28'),
(84, 1, 37.10, 55.80, 'auto', NULL, '2026-05-18 05:18:28'),
(85, 1, 36.90, 56.20, 'auto', NULL, '2026-05-18 05:19:28'),
(86, 1, 37.10, 55.00, 'auto', NULL, '2026-05-18 05:20:28'),
(87, 1, 37.50, 53.00, 'auto', NULL, '2026-05-18 05:21:28'),
(88, 1, 37.50, 53.40, 'auto', NULL, '2026-05-18 05:22:28'),
(89, 1, 37.70, 52.40, 'auto', NULL, '2026-05-18 05:23:28'),
(90, 1, 38.00, 52.30, 'auto', NULL, '2026-05-18 05:24:28'),
(91, 1, 37.80, 52.70, 'auto', NULL, '2026-05-18 05:27:26'),
(92, 1, 37.80, 53.20, 'auto', NULL, '2026-05-18 05:28:26'),
(93, 1, 38.30, 52.30, 'auto', NULL, '2026-05-18 05:29:26'),
(94, 1, 38.60, 51.40, 'auto', NULL, '2026-05-18 05:30:26'),
(95, 1, 38.50, 51.60, 'auto', NULL, '2026-05-18 05:31:26'),
(96, 1, 38.30, 51.90, 'auto', NULL, '2026-05-18 05:32:26'),
(97, 1, 38.10, 52.30, 'auto', NULL, '2026-05-18 05:33:26'),
(98, 1, 37.90, 52.70, 'auto', NULL, '2026-05-18 05:34:26'),
(99, 1, 37.80, 53.00, 'auto', NULL, '2026-05-18 05:35:26'),
(100, 1, 37.50, 52.70, 'auto', NULL, '2026-05-18 05:36:26'),
(101, 1, 37.10, 54.10, 'auto', NULL, '2026-05-18 05:39:59'),
(102, 1, 37.00, 54.50, 'auto', NULL, '2026-05-18 05:40:59'),
(103, 1, 37.40, 53.40, 'auto', NULL, '2026-05-18 05:41:59'),
(104, 1, 37.90, 51.70, 'auto', NULL, '2026-05-18 05:42:59'),
(105, 1, 38.20, 51.10, 'auto', NULL, '2026-05-18 05:43:59'),
(106, 1, 38.50, 49.90, 'auto', NULL, '2026-05-18 05:49:29'),
(107, 1, 38.00, 50.90, 'auto', NULL, '2026-05-18 05:53:55'),
(108, 1, 38.20, 51.10, 'auto', NULL, '2026-05-18 05:55:15'),
(109, 1, 38.50, 50.60, 'auto', NULL, '2026-05-18 05:56:16'),
(110, 1, 38.40, 50.60, 'auto', NULL, '2026-05-18 05:57:16'),
(111, 1, 38.30, 51.00, 'auto', NULL, '2026-05-18 05:58:16'),
(112, 1, 38.20, 51.10, 'auto', NULL, '2026-05-18 05:59:16'),
(113, 1, 38.00, 51.50, 'auto', NULL, '2026-05-18 06:00:16'),
(114, 1, 37.90, 51.70, 'auto', NULL, '2026-05-18 06:01:16'),
(115, 1, 37.80, 51.90, 'auto', NULL, '2026-05-18 06:02:16'),
(116, 1, 37.70, 52.10, 'auto', NULL, '2026-05-18 06:03:16'),
(117, 1, 37.70, 52.30, 'auto', NULL, '2026-05-18 06:04:16'),
(118, 1, 37.60, 52.50, 'auto', NULL, '2026-05-18 06:05:16'),
(119, 1, 37.50, 52.70, 'auto', NULL, '2026-05-18 06:06:16'),
(120, 1, 37.50, 52.50, 'auto', NULL, '2026-05-18 06:07:17'),
(121, 1, 37.40, 52.80, 'auto', NULL, '2026-05-18 06:08:16'),
(122, 1, 37.30, 52.80, 'auto', NULL, '2026-05-18 06:09:17'),
(123, 1, 37.30, 53.00, 'auto', NULL, '2026-05-18 06:10:17'),
(124, 1, 37.20, 53.20, 'auto', NULL, '2026-05-18 06:11:17'),
(125, 1, 36.90, 53.70, 'auto', NULL, '2026-05-18 06:16:35'),
(126, 1, 36.90, 53.90, 'auto', NULL, '2026-05-18 06:17:35'),
(127, 1, 35.80, 57.20, 'auto', NULL, '2026-05-18 06:24:14'),
(128, 1, 35.80, 56.50, 'auto', NULL, '2026-05-18 06:25:14'),
(129, 1, 36.30, 53.50, 'auto', NULL, '2026-05-18 06:26:14'),
(130, 1, 37.10, 54.50, 'auto', NULL, '2026-05-18 06:27:14'),
(131, 1, 37.50, 52.20, 'auto', NULL, '2026-05-18 06:28:14'),
(132, 1, 38.60, 49.90, 'auto', NULL, '2026-05-18 06:29:14'),
(133, 1, 39.90, 46.70, 'auto', NULL, '2026-05-18 06:31:12'),
(134, 1, 40.40, 45.50, 'auto', NULL, '2026-05-18 06:32:12'),
(135, 1, 40.30, 45.50, 'auto', NULL, '2026-05-18 06:33:12'),
(136, 1, 39.70, 46.60, 'auto', NULL, '2026-05-18 06:34:12'),
(137, 1, 39.30, 46.10, 'auto', NULL, '2026-05-18 06:35:12'),
(138, 1, 39.00, 46.60, 'auto', NULL, '2026-05-18 06:36:12'),
(139, 1, 38.60, 47.30, 'auto', NULL, '2026-05-18 06:37:12'),
(140, 1, 38.20, 46.80, 'auto', NULL, '2026-05-18 06:38:12'),
(141, 1, 37.90, 47.10, 'auto', NULL, '2026-05-18 06:39:12'),
(142, 1, 37.80, 48.30, 'auto', NULL, '2026-05-18 06:40:12'),
(143, 1, 37.50, 50.50, 'auto', NULL, '2026-05-18 06:41:12'),
(144, 1, 37.40, 50.50, 'auto', NULL, '2026-05-18 06:42:12'),
(145, 1, 37.30, 50.70, 'auto', NULL, '2026-05-18 06:43:12'),
(146, 1, 37.10, 50.80, 'auto', NULL, '2026-05-18 06:44:12'),
(147, 1, 36.80, 53.30, 'auto', NULL, '2026-05-18 06:45:16'),
(148, 1, 36.60, 51.70, 'auto', NULL, '2026-05-18 06:46:16'),
(149, 1, 36.80, 51.40, 'auto', NULL, '2026-05-18 06:47:17'),
(150, 1, 37.10, 50.60, 'auto', NULL, '2026-05-18 06:48:17'),
(151, 1, 36.90, 50.80, 'auto', NULL, '2026-05-18 07:17:45'),
(152, 1, 37.00, 50.40, 'auto', NULL, '2026-05-18 07:18:47'),
(153, 1, 0.00, NULL, 'user', 1, '2026-05-18 07:19:41'),
(154, 1, 37.50, 50.10, 'auto', NULL, '2026-05-18 07:19:47'),
(155, 1, 38.30, 48.80, 'auto', NULL, '2026-05-18 07:20:48'),
(156, 1, 38.70, 47.70, 'auto', NULL, '2026-05-18 07:21:49'),
(157, 1, 38.50, 48.60, 'auto', NULL, '2026-05-18 07:23:35'),
(158, 1, 36.10, 54.80, 'auto', NULL, '2026-05-18 08:01:44'),
(159, 1, 36.20, 54.50, 'auto', NULL, '2026-05-18 08:02:44'),
(160, 1, 36.20, 53.10, 'auto', NULL, '2026-05-18 08:03:44'),
(161, 1, 36.80, 51.40, 'auto', NULL, '2026-05-18 08:04:44'),
(162, 1, 37.50, 50.10, 'auto', NULL, '2026-05-18 08:05:44'),
(163, 1, 38.20, 48.60, 'auto', NULL, '2026-05-18 08:06:44'),
(164, 1, 38.50, 47.70, 'auto', NULL, '2026-05-18 08:07:44'),
(165, 1, 38.40, 47.50, 'auto', NULL, '2026-05-18 08:08:44'),
(166, 1, 38.10, 47.90, 'auto', NULL, '2026-05-18 08:09:44'),
(167, 1, 37.80, 48.10, 'auto', NULL, '2026-05-18 08:10:44'),
(168, 1, 37.00, 50.30, 'auto', NULL, '2026-05-18 08:15:23'),
(169, 1, 37.00, 50.80, 'auto', NULL, '2026-05-18 08:16:20'),
(170, 1, 37.40, 51.00, 'auto', NULL, '2026-05-18 08:17:20'),
(171, 1, 38.00, 49.70, 'auto', NULL, '2026-05-18 08:18:20'),
(172, 1, 38.50, 49.10, 'auto', NULL, '2026-05-18 08:19:21'),
(173, 1, 38.40, 47.80, 'auto', NULL, '2026-05-18 08:20:24'),
(174, 1, 38.20, 47.90, 'auto', NULL, '2026-05-18 08:21:21'),
(175, 1, 37.90, 47.70, 'auto', NULL, '2026-05-18 08:22:20'),
(176, 1, 37.70, 48.20, 'auto', NULL, '2026-05-18 08:23:21'),
(177, 1, 37.50, 50.20, 'auto', NULL, '2026-05-18 08:24:30'),
(178, 1, 37.20, 51.90, 'auto', NULL, '2026-05-18 08:26:50'),
(179, 1, 37.40, 51.50, 'auto', NULL, '2026-05-18 08:27:50'),
(180, 1, 37.70, 51.30, 'auto', NULL, '2026-05-18 08:28:50'),
(181, 1, 38.00, 51.20, 'auto', NULL, '2026-05-18 08:29:50'),
(182, 1, 38.30, 50.70, 'auto', NULL, '2026-05-18 08:30:51'),
(183, 1, 38.30, 50.70, 'auto', NULL, '2026-05-18 08:31:51'),
(184, 1, 38.30, 51.10, 'auto', NULL, '2026-05-18 08:32:51'),
(185, 1, 38.20, 51.80, 'auto', NULL, '2026-05-18 08:33:52'),
(186, 1, 38.20, 52.00, 'auto', NULL, '2026-05-18 08:34:52'),
(187, 1, 38.00, 51.90, 'auto', NULL, '2026-05-18 08:35:52'),
(188, 1, 37.90, 51.70, 'auto', NULL, '2026-05-18 08:36:52'),
(189, 1, 37.80, 51.70, 'auto', NULL, '2026-05-18 08:37:52'),
(190, 1, 37.60, 52.00, 'auto', NULL, '2026-05-18 08:38:52'),
(191, 1, 37.50, 52.20, 'auto', NULL, '2026-05-18 08:39:52'),
(192, 1, 37.40, 51.60, 'auto', NULL, '2026-05-18 08:40:52'),
(193, 1, 37.30, 52.00, 'auto', NULL, '2026-05-18 08:41:52'),
(194, 1, 37.20, 51.20, 'auto', NULL, '2026-05-18 08:42:52'),
(195, 1, 37.10, 51.50, 'auto', NULL, '2026-05-18 08:43:52'),
(196, 1, 36.50, 54.60, 'auto', NULL, '2026-05-18 08:49:00'),
(197, 1, 36.40, 54.90, 'auto', NULL, '2026-05-18 08:50:01'),
(198, 1, 36.30, 55.40, 'auto', NULL, '2026-05-18 08:51:01'),
(199, 1, 36.20, 54.30, 'auto', NULL, '2026-05-18 08:52:01'),
(200, 1, 36.10, 55.10, 'auto', NULL, '2026-05-18 08:53:01'),
(201, 1, 36.00, 55.20, 'auto', NULL, '2026-05-18 08:54:01'),
(202, 1, 35.90, 55.20, 'auto', NULL, '2026-05-18 08:55:01'),
(203, 1, 35.90, 56.10, 'auto', NULL, '2026-05-18 08:56:01'),
(204, 1, 36.30, 56.00, 'auto', NULL, '2026-05-18 08:57:01'),
(205, 1, 36.70, 55.20, 'auto', NULL, '2026-05-18 08:58:01'),
(206, 1, 37.00, 54.60, 'auto', NULL, '2026-05-18 08:59:01'),
(207, 1, 37.30, 53.90, 'auto', NULL, '2026-05-18 09:00:01'),
(208, 1, 37.70, 53.00, 'auto', NULL, '2026-05-18 09:01:01'),
(209, 1, 38.00, 52.90, 'auto', NULL, '2026-05-18 09:02:01'),
(210, 1, 38.20, 52.50, 'auto', NULL, '2026-05-18 09:03:02'),
(211, 1, 38.30, 52.40, 'auto', NULL, '2026-05-18 09:04:02'),
(212, 1, 38.30, 52.40, 'auto', NULL, '2026-05-18 09:05:02'),
(213, 1, 38.20, 52.40, 'auto', NULL, '2026-05-18 09:06:02'),
(214, 1, 38.10, 52.20, 'auto', NULL, '2026-05-18 09:07:02'),
(215, 1, 0.00, NULL, 'user', 1, '2026-05-18 14:58:45');

-- --------------------------------------------------------

--
-- Table structure for table `temperature_settings`
--

CREATE TABLE `temperature_settings` (
  `id` int(11) NOT NULL,
  `incubator_id` int(11) NOT NULL,
  `target_temp` decimal(5,2) DEFAULT 37.50,
  `min_temp` decimal(5,2) DEFAULT 37.00,
  `max_temp` decimal(5,2) DEFAULT 38.00,
  `target_humidity` decimal(5,2) DEFAULT 55.00,
  `min_humidity` decimal(5,2) DEFAULT 50.00,
  `max_humidity` decimal(5,2) DEFAULT 60.00,
  `turning_interval` decimal(5,2) DEFAULT 8.00,
  `updated_by_role` enum('admin','user') DEFAULT 'admin',
  `updated_by_id` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `temperature_settings`
--

INSERT INTO `temperature_settings` (`id`, `incubator_id`, `target_temp`, `min_temp`, `max_temp`, `target_humidity`, `min_humidity`, `max_humidity`, `turning_interval`, `updated_by_role`, `updated_by_id`, `updated_at`) VALUES
(1, 1, 38.00, 36.00, 38.00, 55.00, 50.00, 60.00, 8, 'user', 1, '2026-05-18 09:05:20'),
(2, 2, 37.50, 37.00, 38.00, 55.00, 50.00, 60.00, 8, 'admin', NULL, '2026-05-09 14:40:48'),
(3, 3, 37.50, 37.00, 38.00, 55.00, 50.00, 60.00, 8, 'admin', NULL, '2026-05-09 14:40:48');

-- --------------------------------------------------------

--
-- Table structure for table `test_mode_dht`
--

CREATE TABLE `test_mode_dht` (
  `incubator_id` int(11) NOT NULL,
  `temp` decimal(5,2) DEFAULT NULL,
  `humidity` decimal(5,2) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `wifi_ssid` varchar(100) DEFAULT NULL,
  `device_ip` varchar(15) DEFAULT NULL,
  `wifi_connected` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_mode_dht`
--

INSERT INTO `test_mode_dht` (`incubator_id`, `temp`, `humidity`, `updated_at`, `wifi_ssid`, `device_ip`, `wifi_connected`) VALUES
(1, 36.00, 56.80, '2026-05-18 03:50:10', 'Lia', '192.168.0.118', 1);

-- --------------------------------------------------------

--
-- Table structure for table `test_mode_relay`
--

CREATE TABLE `test_mode_relay` (
  `incubator_id` int(11) NOT NULL,
  `relay` varchar(50) NOT NULL,
  `state` tinyint(1) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_mode_relay`
--

INSERT INTO `test_mode_relay` (`incubator_id`, `relay`, `state`, `created_at`, `updated_at`) VALUES
(1, 'heater', 0, '2026-05-18 23:31:04', '2026-05-18 15:31:04');

-- --------------------------------------------------------

--
-- Table structure for table `test_mode_relay_status`
--

CREATE TABLE `test_mode_relay_status` (
  `incubator_id` int(11) NOT NULL,
  `relay` varchar(50) NOT NULL,
  `state` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `test_mode_relay_status`
--

INSERT INTO `test_mode_relay_status` (`incubator_id`, `relay`, `state`, `updated_at`) VALUES
(1, 'eggswing', 0, '2026-05-18 15:31:04'),
(1, 'exhaust', 0, '2026-05-18 15:31:04'),
(1, 'heater', 0, '2026-05-18 15:31:04'),
(1, 'heater_1', 0, '2026-05-18 15:31:05'),
(1, 'heater_2', 0, '2026-05-18 15:31:05'),
(1, 'heater_fan', 0, '2026-05-18 15:31:05');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `full_name`, `email`, `password`, `profile_pic`, `phone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'John Doe', 'user@ghost.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, '09171234567', 'active', '2026-05-09 14:40:48', '2026-05-09 14:40:48');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `alerts`
--
ALTER TABLE `alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incubator_id` (`incubator_id`);

--
-- Indexes for table `batches`
--
ALTER TABLE `batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incubator_id` (`incubator_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `hardware_commands`
--
ALTER TABLE `hardware_commands`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incubator_id` (`incubator_id`);

--
-- Indexes for table `hardware_state`
--
ALTER TABLE `hardware_state`
  ADD PRIMARY KEY (`incubator_id`);

--
-- Indexes for table `incubators`
--
ALTER TABLE `incubators`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incubator_id` (`incubator_id`),
  ADD KEY `batch_id` (`batch_id`);

--
-- Indexes for table `temperature_logs`
--
ALTER TABLE `temperature_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incubator_id` (`incubator_id`);

--
-- Indexes for table `temperature_settings`
--
ALTER TABLE `temperature_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `incubator_id` (`incubator_id`);

--
-- Indexes for table `test_mode_dht`
--
ALTER TABLE `test_mode_dht`
  ADD PRIMARY KEY (`incubator_id`);

--
-- Indexes for table `test_mode_relay`
--
ALTER TABLE `test_mode_relay`
  ADD PRIMARY KEY (`incubator_id`);

--
-- Indexes for table `test_mode_relay_status`
--
ALTER TABLE `test_mode_relay_status`
  ADD PRIMARY KEY (`incubator_id`,`relay`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `alerts`
--
ALTER TABLE `alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=105;

--
-- AUTO_INCREMENT for table `batches`
--
ALTER TABLE `batches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `hardware_commands`
--
ALTER TABLE `hardware_commands`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=96;

--
-- AUTO_INCREMENT for table `incubators`
--
ALTER TABLE `incubators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `temperature_logs`
--
ALTER TABLE `temperature_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=216;

--
-- AUTO_INCREMENT for table `temperature_settings`
--
ALTER TABLE `temperature_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alerts`
--
ALTER TABLE `alerts`
  ADD CONSTRAINT `alerts_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `batches`
--
ALTER TABLE `batches`
  ADD CONSTRAINT `batches_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `batches_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hardware_commands`
--
ALTER TABLE `hardware_commands`
  ADD CONSTRAINT `hardware_commands_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hardware_state`
--
ALTER TABLE `hardware_state`
  ADD CONSTRAINT `hardware_state_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`batch_id`) REFERENCES `batches` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `temperature_logs`
--
ALTER TABLE `temperature_logs`
  ADD CONSTRAINT `temperature_logs_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `temperature_settings`
--
ALTER TABLE `temperature_settings`
  ADD CONSTRAINT `temperature_settings_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_mode_relay`
--
ALTER TABLE `test_mode_relay`
  ADD CONSTRAINT `test_mode_relay_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `test_mode_relay_status`
--
ALTER TABLE `test_mode_relay_status`
  ADD CONSTRAINT `test_mode_relay_status_ibfk_1` FOREIGN KEY (`incubator_id`) REFERENCES `incubators` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
