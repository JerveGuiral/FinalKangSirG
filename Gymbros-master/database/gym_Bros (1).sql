-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 13, 2025 at 05:14 AM
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
-- Database: `gymbros`
--

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `attempt_time`, `success`) VALUES
(9, 'Jerve', '::1', '2025-09-25 10:15:59', 0),
(10, 'Jerve', '::1', '2025-09-25 10:16:04', 0),
(11, 'Jerve', '::1', '2025-09-25 10:16:51', 1),
(12, 'Jerve', '::1', '2025-09-25 10:33:40', 1),
(13, 'Jerve', '::1', '2025-09-25 10:38:41', 0),
(14, 'Jerve', '::1', '2025-09-26 09:23:37', 0),
(15, 'Jerve', '::1', '2025-09-26 09:23:43', 1),
(16, 'Jerve', '::1', '2025-09-27 09:33:16', 0),
(17, 'Jerve', '::1', '2025-09-27 09:33:20', 1),
(18, 'Jerve', '::1', '2025-10-01 07:25:10', 1),
(19, 'Jerve', '::1', '2025-10-01 07:38:15', 1),
(20, 'Jerve', '::1', '2025-10-04 13:10:15', 1),
(21, 'Jerve', '::1', '2025-10-04 15:48:59', 1),
(22, 'Jerve', '::1', '2025-10-04 16:00:46', 1),
(23, 'Jerve', '::1', '2025-10-08 14:03:13', 1),
(24, 'Jerve', '::1', '2025-10-11 05:39:50', 1),
(25, 'Jerve', '::1', '2025-10-13 04:15:48', 1),
(26, 'Jerve', '::1', '2025-10-15 12:58:36', 1),
(27, 'Jerve', '::1', '2025-10-15 13:01:31', 0),
(28, 'Jerve', '::1', '2025-10-15 13:01:35', 0),
(29, 'Jerve', '::1', '2025-10-16 02:59:05', 1),
(30, 'Jerve', '::1', '2025-10-19 16:03:45', 1),
(31, 'Jerve', '::1', '2025-10-22 14:18:12', 1),
(32, 'jerve', '::1', '2025-10-22 14:18:55', 1),
(33, 'jerve', '::1', '2025-10-22 14:19:10', 1),
(34, 'jerve', '::1', '2025-10-25 13:13:31', 0),
(35, 'jerve', '::1', '2025-10-25 13:13:36', 1),
(36, 'jerve', '::1', '2025-10-26 07:08:59', 1),
(37, 'jerve', '::1', '2025-10-26 15:12:51', 1),
(38, 'Okay', '::1', '2025-10-27 12:47:23', 1),
(39, 'jerve', '::1', '2025-10-27 13:18:45', 1),
(40, 'Jerve', '::1', '2025-10-30 06:17:44', 0),
(41, 'Jerve', '::1', '2025-10-30 06:17:55', 0),
(42, 'Jerve', '::1', '2025-10-30 06:18:12', 1),
(43, 'jerve', '::1', '2025-10-30 06:40:47', 0),
(44, 'jovert', '::1', '2025-10-30 06:43:24', 0),
(45, 'jovert', '::1', '2025-10-30 06:43:30', 0),
(46, 'Gwapo', '::1', '2025-10-30 11:26:46', 1),
(47, 'Jerve', '::1', '2025-10-31 13:28:23', 0),
(48, 'Jerve', '::1', '2025-10-31 13:28:27', 0),
(49, 'Jerve', '::1', '2025-10-31 13:28:32', 0),
(50, 'jerve', '::1', '2025-11-01 06:47:37', 1),
(51, 'jerve', '::1', '2025-11-01 06:47:46', 0),
(52, 'jerve', '::1', '2025-11-01 06:47:49', 0),
(53, 'jerve', '::1', '2025-11-01 06:48:04', 0),
(54, 'dfasfa', '::1', '2025-11-01 06:48:35', 0),
(55, 'dfasfa', '::1', '2025-11-01 06:48:42', 0),
(56, 'dfasfa', '::1', '2025-11-01 06:48:46', 0),
(57, 'jovert1234', '::1', '2025-11-01 06:52:57', 0),
(58, 'jovert', '::1', '2025-11-01 06:53:19', 0),
(59, 'jovert', '::1', '2025-11-01 06:53:24', 0),
(60, 'jovert', '::1', '2025-11-01 06:53:29', 0),
(61, 'kapa', '::1', '2025-11-01 07:05:34', 0),
(62, 'kapa', '::1', '2025-11-01 07:05:37', 0),
(63, 'kapa', '::1', '2025-11-01 07:05:39', 0),
(64, 'okay', '::1', '2025-11-01 07:06:00', 0),
(65, 'okay', '::1', '2025-11-01 07:06:04', 0),
(66, 'okay', '::1', '2025-11-01 07:06:07', 0),
(67, 'Jerve', '::1', '2025-11-01 09:41:04', 0),
(68, 'Jerve', '::1', '2025-11-01 09:41:08', 0),
(69, 'Jerve', '::1', '2025-11-01 09:41:12', 0),
(70, 'jovert', '::1', '2025-11-01 09:45:14', 0),
(71, 'jovert', '::1', '2025-11-01 09:45:57', 0),
(72, 'jovert', '::1', '2025-11-01 09:49:45', 0),
(73, 'Perla', '::1', '2025-11-01 09:58:35', 1),
(74, 'Perla', '::1', '2025-11-01 10:00:02', 1),
(75, 'Perla', '::1', '2025-11-01 10:00:58', 1),
(76, 'Jerve', '::1', '2025-11-02 10:42:47', 0),
(77, 'Jerve', '::1', '2025-11-02 10:42:52', 0),
(78, 'Jerve', '::1', '2025-11-02 10:42:56', 0),
(79, 'jerve', '::1', '2025-11-02 10:48:09', 0),
(80, 'jerve', '::1', '2025-11-02 10:48:26', 0),
(81, 'jerve', '::1', '2025-11-02 10:48:47', 0),
(82, 'jerve', '::1', '2025-11-02 10:49:20', 0),
(83, 'jerve', '::1', '2025-11-02 10:49:52', 0),
(84, 'jerve', '::1', '2025-11-02 10:50:35', 0),
(85, 'Perla', '::1', '2025-11-02 10:51:51', 1),
(86, 'Perla', '::1', '2025-11-02 10:52:00', 1),
(87, 'Perla', '::1', '2025-11-02 11:02:57', 1),
(88, 'Kapa', '::1', '2025-11-02 11:41:16', 1),
(89, 'Kapa', '::1', '2025-11-02 11:50:19', 1),
(90, 'Kapa', '::1', '2025-11-02 11:54:42', 0),
(91, 'Kapa', '::1', '2025-11-02 11:54:47', 0),
(92, 'Kapa', '::1', '2025-11-02 11:54:51', 0),
(93, 'Kapa', '::1', '2025-11-02 11:55:09', 0),
(94, 'Kapa', '::1', '2025-11-02 11:55:27', 0),
(95, 'Kapa', '::1', '2025-11-02 11:55:44', 0),
(96, 'Kapa', '::1', '2025-11-02 12:01:41', 0),
(97, 'Kapa', '::1', '2025-11-02 12:02:21', 1),
(98, 'Jerve', '::1', '2025-11-02 12:06:33', 0),
(99, 'Kapa', '::1', '2025-11-02 14:53:15', 1),
(100, 'dddgggg', '::1', '2025-11-02 15:04:34', 0),
(101, 'dfadfaf', '::1', '2025-11-02 15:08:52', 0),
(102, 'dfadfaf', '::1', '2025-11-02 15:09:19', 0),
(103, 'dfadfaf', '::1', '2025-11-02 15:09:27', 0),
(104, 'Kapa', '::1', '2025-11-02 15:09:55', 0),
(105, 'Kapa', '::1', '2025-11-02 15:11:56', 1),
(106, 'Kapa', '::1', '2025-11-02 15:12:21', 0),
(107, 'Kapa', '::1', '2025-11-02 15:15:23', 0),
(108, 'Kapa', '::1', '2025-11-02 15:15:26', 0),
(109, 'Kapa', '::1', '2025-11-02 15:18:17', 0),
(110, 'Kapa', '::1', '2025-11-02 15:31:30', 0),
(111, 'Perla', '::1', '2025-11-04 01:17:54', 1),
(112, 'Perla', '::1', '2025-11-04 01:22:29', 0),
(113, 'Perla', '::1', '2025-11-04 01:22:34', 0),
(114, 'Perla', '::1', '2025-11-04 01:23:37', 0),
(115, 'Perla', '::1', '2025-11-04 10:42:48', 0),
(116, 'Perla', '::1', '2025-11-04 10:42:53', 0),
(117, 'Perla', '::1', '2025-11-04 10:42:57', 0),
(118, 'Perla', '::1', '2025-11-04 10:43:15', 0),
(119, 'perla', '::1', '2025-11-04 11:00:08', 0),
(120, 'perla', '::1', '2025-11-04 11:00:26', 0),
(121, 'Jerve', '::1', '2025-11-04 11:07:08', 0),
(122, 'Jerve', '::1', '2025-11-04 11:07:13', 0),
(123, 'Jerve', '::1', '2025-11-04 11:07:16', 0),
(124, 'Jerve', '::1', '2025-11-04 11:07:37', 0),
(125, 'Perla', '::1', '2025-11-04 11:21:05', 0),
(126, 'fadfadfas', '::1', '2025-11-04 11:21:57', 0),
(127, 'fadfadfas', '::1', '2025-11-04 11:22:02', 0),
(128, 'fadfadfas', '::1', '2025-11-04 11:22:06', 0),
(129, 'Jerve', '::1', '2025-11-04 11:32:51', 0),
(130, 'fadfadfadfaf', '::1', '2025-11-04 11:42:39', 0),
(131, 'fadfadfadfaf', '::1', '2025-11-04 11:42:42', 0),
(132, 'fadfadfadfaf', '::1', '2025-11-04 11:42:46', 0),
(133, 'Kapa', '::1', '2025-11-04 11:43:23', 0),
(134, 'Kapa', '::1', '2025-11-04 11:43:30', 0),
(135, 'Kapa', '::1', '2025-11-04 11:43:34', 0),
(136, 'Kapa', '::1', '2025-11-04 11:44:04', 1),
(137, 'Perla', '::1', '2025-11-05 11:38:43', 1),
(138, 'Perla', '::1', '2025-11-05 11:44:19', 0),
(139, 'Perla', '::1', '2025-11-05 11:44:24', 0),
(140, 'Perla', '::1', '2025-11-05 11:44:28', 0),
(141, 'Perla', '::1', '2025-11-05 11:44:45', 0),
(142, 'Perla', '::1', '2025-11-05 11:45:26', 0),
(143, 'Perla', '::1', '2025-11-05 11:45:47', 0),
(144, 'Perla', '::1', '2025-11-05 11:46:20', 0),
(145, 'Perla', '::1', '2025-11-05 11:47:02', 0),
(146, 'Perla', '::1', '2025-11-05 11:55:28', 0),
(147, 'kapa', '::1', '2025-11-05 11:55:58', 0),
(148, 'dfadfad', '::1', '2025-11-05 11:57:50', 0),
(149, 'Jerve', '::1', '2025-11-05 13:53:51', 0),
(150, 'Perla', '::1', '2025-11-05 14:02:06', 0),
(151, 'Perla', '::1', '2025-11-05 14:02:12', 0),
(152, 'Perla', '::1', '2025-11-05 14:02:16', 0),
(153, 'Perla', '::1', '2025-11-05 14:02:41', 0),
(154, 'Jerve', '::1', '2025-11-05 14:33:56', 0),
(155, 'Perla', '::1', '2025-11-05 14:34:09', 0),
(156, 'Perla', '::1', '2025-11-07 04:28:27', 0),
(157, 'Perla', '::1', '2025-11-07 04:28:31', 0),
(158, 'Perla', '::1', '2025-11-07 04:28:36', 0),
(159, 'Perla', '::1', '2025-11-07 04:28:56', 0),
(160, 'Perla', '::1', '2025-11-07 06:18:30', 0),
(161, 'Perla', '::1', '2025-11-07 06:18:36', 0),
(162, 'Perla', '::1', '2025-11-07 06:18:43', 0),
(163, 'Perla', '::1', '2025-11-07 13:32:45', 0),
(164, 'Perla', '::1', '2025-11-07 13:32:49', 0),
(165, 'Jerve', '::1', '2025-11-07 13:34:34', 0),
(166, 'Jerve', '::1', '2025-11-07 13:34:39', 0),
(167, 'Jerve', '::1', '2025-11-07 13:34:44', 0),
(168, 'Jerve', '::1', '2025-11-07 13:35:06', 0),
(169, 'Kapa', '::1', '2025-11-07 16:11:33', 0),
(170, 'Kapa', '::1', '2025-11-07 16:11:38', 0),
(171, 'Kapa', '::1', '2025-11-09 02:57:03', 0),
(172, 'Kapa', '::1', '2025-11-09 02:57:13', 0),
(173, 'Perla', '::1', '2025-11-09 05:26:34', 0),
(174, 'Perla', '::1', '2025-11-09 05:26:40', 1),
(175, 'Perla', '::1', '2025-11-09 05:36:58', 0),
(176, 'Perla', '::1', '2025-11-09 05:37:01', 0),
(177, 'Perla', '::1', '2025-11-09 05:37:04', 0),
(178, 'Perla', '::1', '2025-11-09 05:37:23', 0),
(179, 'Kapa', '::1', '2025-11-09 05:37:41', 0),
(180, 'Kapa', '::1', '2025-11-09 05:37:45', 0),
(181, 'Kapa', '::1', '2025-11-09 05:37:48', 0),
(182, 'Kapa', '::1', '2025-11-09 05:38:05', 0),
(183, 'Perla', '::1', '2025-11-09 05:41:40', 0),
(184, 'Kapa', '::1', '2025-11-09 05:42:04', 0),
(185, 'Kapa', '::1', '2025-11-09 05:42:21', 0),
(186, 'Kapa', '::1', '2025-11-09 05:43:29', 0),
(187, 'Perla', '::1', '2025-11-09 05:45:32', 0),
(188, 'Perla', '::1', '2025-11-09 05:46:06', 0),
(189, 'Perla', '::1', '2025-11-09 05:47:06', 0),
(190, 'Perla', '::1', '2025-11-09 10:53:43', 0),
(191, 'Perla', '::1', '2025-11-09 10:53:46', 0),
(192, 'Perla', '::1', '2025-11-09 10:53:50', 0),
(193, 'Perla', '::1', '2025-11-09 10:54:08', 0),
(194, 'Perla', '::1', '2025-11-09 10:54:25', 0),
(195, 'Perla', '::1', '2025-11-09 11:11:41', 0),
(196, 'Jerve', '::1', '2025-11-09 11:13:07', 0),
(197, 'Jerve', '::1', '2025-11-09 11:13:13', 0),
(198, 'Jerve', '::1', '2025-11-09 11:13:17', 0),
(199, 'Jerve', '::1', '2025-11-09 11:13:36', 0),
(200, 'Jerve', '::1', '2025-11-09 11:13:40', 0),
(201, 'Jerve', '::1', '2025-11-09 11:13:43', 0),
(202, 'Jerve', '::1', '2025-11-09 12:21:08', 0),
(203, 'Jerve', '::1', '2025-11-09 12:21:23', 0),
(204, 'Jerve', '::1', '2025-11-09 12:22:01', 0),
(205, 'Lincolntzy', '::1', '2025-11-09 12:26:19', 0),
(206, 'Lincolntzy', '::1', '2025-11-09 12:26:22', 1),
(207, 'Lincolntzy', '::1', '2025-11-09 12:26:35', 0),
(208, 'Lincolntzy', '::1', '2025-11-09 12:26:39', 0),
(209, 'Lincolntzy', '::1', '2025-11-09 12:27:19', 1),
(210, 'Dilan', '::1', '2025-11-10 14:06:37', 1),
(211, 'Thomas', '::1', '2025-11-10 14:24:40', 0),
(212, 'Thomas', '::1', '2025-11-10 14:24:49', 0),
(213, 'Thomas', '::1', '2025-11-10 14:24:57', 0),
(214, 'thomas', '::1', '2025-11-10 14:25:20', 0),
(215, 'Perla', '::1', '2025-11-10 14:25:31', 1),
(216, 'Thomas', '::1', '2025-11-10 14:26:45', 0),
(217, 'Dilan', '::1', '2025-11-10 14:26:53', 1),
(218, 'Thomas', '::1', '2025-11-10 14:35:53', 0),
(219, 'Gusion', '::1', '2025-11-10 14:38:09', 1),
(220, 'Gusion', '::1', '2025-11-10 14:54:23', 0),
(221, 'Gusion', '::1', '2025-11-10 14:54:30', 0),
(222, 'Gusion', '::1', '2025-11-10 14:55:16', 1),
(223, 'Gusion', '::1', '2025-11-10 14:58:22', 1),
(224, 'Eudura', '::1', '2025-11-11 00:58:14', 1),
(225, 'Eudura', '::1', '2025-11-11 00:58:20', 0),
(226, 'Eudura', '::1', '2025-11-11 00:58:23', 0),
(227, 'Eudura', '::1', '2025-11-11 00:58:26', 0),
(228, 'Guko', '::1', '2025-11-11 01:10:28', 1),
(229, 'Guko', '::1', '2025-11-11 15:35:14', 0),
(230, 'Guko', '::1', '2025-11-11 15:35:22', 0),
(231, 'Guko', '::1', '2025-11-11 16:38:48', 0),
(232, 'Guko', '::1', '2025-11-11 16:38:59', 0),
(233, 'Guko', '::1', '2025-11-11 16:48:44', 1),
(234, 'Guko', '::1', '2025-11-11 16:53:40', 0),
(235, 'Guko', '::1', '2025-11-11 16:53:44', 0),
(236, 'Tigreal', '::1', '2025-11-12 01:21:40', 1),
(237, 'yin', '::1', '2025-11-12 01:46:49', 1),
(238, 'jerve', '::1', '2025-11-12 01:47:13', 0),
(239, 'jerve', '::1', '2025-11-12 01:47:16', 0),
(240, 'Gusion', '::1', '2025-11-12 13:52:12', 0),
(241, 'Gusion', '::1', '2025-11-12 13:52:19', 0),
(242, 'Perla', '::1', '2025-11-12 13:52:51', 0),
(243, 'Perla', '::1', '2025-11-12 13:52:59', 1),
(244, 'Perla', '::1', '2025-11-12 13:55:58', 0),
(245, 'Perla', '::1', '2025-11-12 13:56:02', 0),
(246, 'Perla', '::1', '2025-11-12 14:02:40', 0),
(247, 'Perla', '::1', '2025-11-12 14:02:57', 1),
(248, 'Perla', '::1', '2025-11-12 14:08:53', 0),
(249, 'Perla', '::1', '2025-11-12 14:08:56', 0),
(250, 'Tigreal', '::1', '2025-11-13 04:06:34', 0),
(251, 'Tigreal', '::1', '2025-11-13 04:06:38', 0),
(252, 'inday', '::1', '2025-11-13 04:08:56', 1),
(253, 'inday', '::1', '2025-11-13 04:09:24', 1);

-- --------------------------------------------------------

--
-- Table structure for table `security_questions`
--

CREATE TABLE `security_questions` (
  `id` int(11) NOT NULL,
  `user_id` varchar(20) NOT NULL,
  `question1` varchar(255) NOT NULL,
  `answer1_hash` varchar(255) NOT NULL,
  `question2` varchar(255) NOT NULL,
  `answer2_hash` varchar(255) NOT NULL,
  `question3` varchar(255) NOT NULL,
  `answer3_hash` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_questions`
--

INSERT INTO `security_questions` (`id`, `user_id`, `question1`, `answer1_hash`, `question2`, `answer2_hash`, `question3`, `answer3_hash`) VALUES
(1, '2023-0021', 'Who is your best friend in Elementary?', '$2y$10$QEuhqX5EJS08i.Pbr15vo.ML83mn.isO5VZlAc54xmGFl19VWQ0Jq', 'What is the name of your favorite pet?', '$2y$10$GIQP1/Ywc7aCo8lTIMTqseqV5vXEIllsBvVIQVHRiTgpPwkgHdx/W', 'Who is your favorite teacher in high school?', '$2y$10$liBd1DxgKNJDneP1yac04uwsJEI5SsjBD5ckNnCvj3tJEKBOKFH3a'),
(2, '2023-0032', 'Who is your best friend in Elementary?', '$2y$10$X4Vbu79.CurP.3BV0l2RwOWar2x6bnahIVfTuNKVidIXbOcpjeosG', 'What is the name of your favorite pet?', '$2y$10$0IPLb91/BS.47Q4pSYN3Y.f7tqAnczRoGFqA/hzcPks.M12xMkmNa', 'Who is your favorite teacher in high school?', '$2y$10$lqPu/U3pSCWNBMmqLKYJVO3MaL0A6JrfmxuynhDTnN.d0w5UcbfeO'),
(3, '2024-0024', 'Who is your best friend in Elementary?', '$2y$10$ZgsvZrHvTIgczqfxss95U.gfMUqYd.Rvu/5mVqhUQ9NdFtSoPKQx2', 'What is the name of your favorite pet?', '$2y$10$pIUynATV/Hv1p0uIfjyqJOoJ2qZ.SOmireOpbNjn6lfJW64btJwSq', 'Who is your favorite teacher in high school?', '$2y$10$2jHwr.8P1tge90ncFegc9ev7jWsmoGRB.imCuHmgZCw2vRAPNv6tK'),
(4, '2023-4421', 'Who is your best friend in Elementary?', '$2y$10$UOmymVrj30LoNa9wNvU3OOP6PxsLyAIXS.e9DkabxCHzR0zkEGYGe', 'What is the name of your favorite pet?', '$2y$10$wP73AAolT8dh4SA6yx.Kt.M4B/O9ZNhC6o8swSIPMOggQP9yQfdYG', 'Who is your favorite teacher in high school?', '$2y$10$dDRGCyCnXVUWmL/2Fa.8YeLpYP63L6WoocDZtcTFpxJtphWIlBWTm'),
(5, '2023-4425', 'Who is your best friend in Elementary?', '$2y$10$bt9aswhrtCvQEhitibMDAub3miZYKawabRVAXCusMMIZ79KPN4VlS', 'What is the name of your favorite pet?', '$2y$10$/I2APNDGMN0WwROVP2YlMuXqD7fCMORJTqyDc3819AJrmyXwffGou', 'Who is your favorite teacher in high school?', '$2y$10$fgJ8wkJdPOmSv1DSc2p88.f555f91wtJ6XTwCdszlN7Z10BLlW80u'),
(6, '2023-0043', 'Who is your best friend in Elementary?', '$2y$10$b4tDpPd6oXgfijJg10MLkuX/OtEj5wJ1S4baSjjVmWvN9s8tNbl52', 'What is the name of your favorite pet?', '$2y$10$2dthnVJPIc/M/.eDRbUSTODOshUzQI0wmcchp6PWU8YGYO0mtb/Ey', 'Who is your favorite teacher in high school?', '$2y$10$ChCwWZClyzCCxFVZjp6ic.vSIbFMrGrXp7iT4byWv1N0C0.UdvagK'),
(7, '2023-4401', 'Who is your best friend in Elementary?', '$2y$10$4O3Po2IuztGZT3TO5wuF4u2h25omUxCdF7CHsXGQ6zcSKOSu0jRnu', 'What is the name of your favorite pet?', '$2y$10$.64O1G4M9UWBYBJuiXP63eDjCPrEYtVhE2JAaVFQfLcCmXZS7eeI2', 'Who is your favorite teacher in high school?', '$2y$10$2ATeo9HFLGNfRP14Q7LoHOACShVJ4lLNygcifB/TEAdrEz6oOSTJ6'),
(8, '2023-5541', 'Who is your best friend in Elementary?', '$2y$10$KUlAsYSYTZtNsKLqMcTFTeX8iwyn/YBsdXiFDsUFvyCXZ4EbrhMXu', 'What is the name of your favorite pet?', '$2y$10$dGDCnBWpbRpM6XQ1XhO/oO8V8kCT003sQWH8.R6Ao2ARW0o5PbJo6', 'Who is your favorite teacher in high school?', '$2y$10$XyQWB6NTqHwIxs/RYgTAIOjzxkg5C3CdA8qIdIleIHqPZR9jRlxZa'),
(9, '2023-0052', 'Who is your best friend in Elementary?', '$2y$10$.tocu3cxpVYJB8v2LdLi9.ucquqoKmf9N5Y67fpDE30wf2lTK5hKq', 'What is the name of your favorite pet?', '$2y$10$MV9ifxeNb2W8V4LpIDndp.jEhFwxfn.r83.qugzHiX92sez5/hcZ.', 'Who is your favorite teacher in high school?', '$2y$10$Q127r25EZOq3rNSlQZU0IOfcgTUDKNq7gyQhAJgWEhSzR4iGnOC32'),
(10, '2024-0026', 'Who is your best friend in Elementary?', '$2y$10$n5uCDbVFP8TuwpZxM9nrsOg0CcmszyW08FXzh8NwoIIk35XOgbDDm', 'What is the name of your favorite pet?', '$2y$10$5yn0NcH9dO45hy.9BwPyPOMvL7rieuebZcwkmGiSCQZNXKaOKxEIq', 'Who is your favorite teacher in high school?', '$2y$10$GighfArxuiL.vB08wL5KMeSXmdbJo.pca8hrRB9AXmJMvKRyL4Z.W'),
(11, '1232-2323', 'Who is your best friend in Elementary?', '$2y$10$ewxGDwJJiSJtdfsFkoxTf.lqsEqqUAazUlvGklSDvWEsglnG9lOhq', 'Who is your best friend in Elementary?', '$2y$10$kBTo.hl1acdr.0BBRwpk8ef/JdWq83UCQA8KOBSj/xKFDYgJHFMje', 'Who is your best friend in Elementary?', '$2y$10$VCn2TpksTNJxseqhFV4unetSPdXrC6fYO3PapiPlPPi0TPVe83I.q'),
(12, '2023-4445', 'Who is your best friend in Elementary?', '$2y$10$.djcTRflW3KpJCV08tRvW.P5JveKpsGE3584P1IXk.lqKS02iU1kK', 'What is the name of your favorite pet?', '$2y$10$s/eoS/hsH9RQ4zxbgvhZ3OR0Tb.oKQ8YAd/g1U.DKIoEV.dHmf6e6', 'Who is your favorite teacher in high school?', '$2y$10$aSDoa9V.hl5n9QDgW70MiOZUl9DjeioD2NlFvDey4hEyKW3OIztXO'),
(13, '2332-3221', 'What was the name of your first pet?', '$2y$10$mSx14Tu.h2MBYH..f7HWKOQXANspo8cWvnB6yF1fCB9FHdoh4Nmt6', 'What is your mother&#039;s maiden name?', '$2y$10$PdKfM.RlY71gJESVhOF6y.ZEsGQjihGR5nHVcQJfRAbHNuK1ST9Vi', 'What is your mother&#039;s maiden name?', '$2y$10$jozLt8EeWjt1dFq2GfOjY.mIdpc/HWVTiYuUCMM7UZM8HZz5rs/c.'),
(14, '2323-2332', 'What was the name of your first pet?', '$2y$10$HVSCeOAF195UNR.mKJSPPudu0ArCtwoR85lrnRjqFVZD.MdgResTG', 'What was the name of your first pet?', '$2y$10$vZVYsW3BCS3urwKDa8rjzOdHy6HYDM76NwChKbgpkBP3iWATNnCAu', 'What was the name of your first pet?', '$2y$10$xuCs1hQBR7nKn5bXFPuqROBYiz8VB5yflwkm0yDsiSHO/np37YLCi'),
(15, '3344-4444', 'What was the name of your first pet?', '$2y$10$FdODHHs1UyqkpJtX6B8p8.2AkpOOBj74j/bZ6Iv4KdSqYC5AabEvq', 'What is your mother&#039;s maiden name?', '$2y$10$Qd/d9o6NvZKOWUYVrdaqM.igYQcb1C9rKvszzjtIVljxCnEtUOhby', 'What was your favorite school teacher&#039;s name?', '$2y$10$Dw0PSt1v2BYED1vCboaQMuScDjzrc1faTbOLvxJRNysfvHG615lQ2'),
(16, '2024-0034', 'Who is your best friend in Elementary?', '$2y$10$pRJW6IhdFKdzrMgzTUzl2u06F3.94PAJxKpbxGpe/4aOyHR7smxJa', 'What is the name of your favorite pet?', '$2y$10$WSCCqR8zyJ5JBClyb6RTk.3GXBRJjdX7/BTLL1/eXQVA0e9VLGpZu', 'Who is your favorite teacher in high school?', '$2y$10$YiyDybATBcu/zj8zr1aq2unTFq4vlcWAlff/6q7Z3ltxJXYMuXo3W'),
(17, '2312-3123', 'Who is your best friend in Elementary?', '$2y$10$VpfYWQ5mz6OlJlJ5iD/YHeGvEuwDsAuBbzKjxUVvp7Lt4roxStKdW', 'What is the name of your favorite pet?', '$2y$10$.KGO0qHyS3MV0rain5AA8uUcM56cJtvxvrnkIVCE6OjwE7sRMS2t2', 'Who is your favorite teacher in high school?', '$2y$10$F0rv2wV/qQEhl7if3iJooOwGBBbfGrX7uTSoBN/k6gI/xmU9HzaaK'),
(18, '2323-2132', 'Who is your best friend in Elementary?', '$2y$10$1VpHgGVUAZaCGkaQBkYHM.cW1bstvkIox9XZRln9CrLjzFLBssXfq', 'What is the name of your favorite pet?', '$2y$10$qtM3UlT3ZdTaPqu.tc6P1O4SHFblyNpMd15VQfNM9Wc8SwN3tmbBG', 'Who is your best friend in Elementary?', '$2y$10$DOqO9MOyuoifSuU1hWAF1eRCU2Ft88L8kIPe1iV0Jai3uV4bpRdTS'),
(19, '3334-4444', 'Who is your best friend in Elementary?', '$2y$10$.hhzJzxJxxat8ZNg44/ykuf66yRvFIGLt2uh/winlrqO3oPzZ5d2q', 'Who is your favorite teacher in high school?', '$2y$10$OrFujRtXoA7BQOOGsRGGR.9uXE75A28fDlCKV0yN7ModADSYHok8e', 'Who is your favorite teacher in high school?', '$2y$10$arqnmE7YQGz1CPtyyXy2puolkOEylnrUaC.qPRpGdSqnHzFabrkXi'),
(20, '3334-4432', 'Who is your best friend in Elementary?', '$2y$10$E.DlCAm9hD0bVQVBavC9p.bKtD2I.EV68W7tkQfrln2qvJMu.VBCC', 'What is the name of your favorite pet?', '$2y$10$JP1op7B1bC1R8MFCzYgHNe4/OR9a0etlhqM0kTkiIChFrzw/qHY8m', 'Who is your favorite teacher in high school?', '$2y$10$.74uMV1CnT/nmnQQSfVEmO8jTlJNl4/xLUOH/jdGOuGt/6Q5qBW8a'),
(21, '2023-0054', 'Who is your best friend in Elementary?', '$2y$10$pgHeYqefWAo49WBS3aNfau23n5z1WTLI5JsZWKuifRpzLTBnmMUaO', 'What is the name of your favorite pet?', '$2y$10$MD1DyRqQw2q0pHKoQOG9ZOaR4Ge4zCpCvDoPi36SAB7hlOlUQ.L4K', 'Who is your favorite teacher in high school?', '$2y$10$/3/ueWB3hXbuCWQlwTWh7O0QYLRk.K4UGV5bIiDLroKQ8QZ9FCgee'),
(22, '3422-1422', 'Who is your best friend in Elementary?', '$2y$10$qx8U8pKQltB6XjC8hEWHxOmN3LooJi7UcQfFDMRWURHjBcAocrS5G', 'What is the name of your favorite pet?', '$2y$10$Uu27hHBLk3ZquiDtC2dXpe6VCM0fc.Zb/2Q6YE9MdT5jsOIkZdj0a', 'Who is your favorite teacher in high school?', '$2y$10$S2wut6AFnijpQOuXY/.JbeFSdxCGM954qblm00HTaVS1O6u5iAydS'),
(23, '2023-4444', 'Who is your best friend in Elementary?', '$2y$10$SbmKDgqEIqNwrwKpZpnY3.A56NeeTXYVVIZ50Fnp57fFsMfQp9EDe', 'What is the name of your favorite pet?', '$2y$10$0Q9T29P6O0qj.C/JHcjzuuOqhlIBM59JDkpd0mCM1tEKzF5ICdWBe', 'Who is your favorite teacher in high school?', '$2y$10$BMhsPKbOilxl4oZFTLgCn.U4o5mmyrmQk7j9MBjblNzpUcPIOFFze');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id_number` varchar(20) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `middle_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) NOT NULL,
  `extension_name` varchar(10) DEFAULT NULL,
  `birthdate` date NOT NULL,
  `age` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `sex` enum('male','female','other') NOT NULL,
  `purok_street` varchar(100) NOT NULL,
  `barangay` varchar(50) NOT NULL,
  `city_municipality` varchar(50) NOT NULL,
  `province` varchar(50) NOT NULL,
  `country` varchar(50) NOT NULL,
  `zip_code` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id_number`, `username`, `password_hash`, `first_name`, `middle_name`, `last_name`, `extension_name`, `birthdate`, `age`, `email`, `sex`, `purok_street`, `barangay`, `city_municipality`, `province`, `country`, `zip_code`, `created_at`) VALUES
('1232-2323', 'Lincolntzy', '$2y$10$Jx1hkxjerX9E.gpaJGO.3eBCJQMkP92z2VPzKBiGzKqy/Y6tnEr9W', 'John Lincoln', 'Cudog', 'Bihag', '', '2004-12-12', 20, '2lincoln@gmail.com', 'male', '21324', 'cubo', 'bayugan', 'esperanza', 'Philippines', '8513', '2025-11-09 12:25:52'),
('2023-0021', 'Jerve', '$2y$10$E1yfi09.miH7jV28/JxX.O21P/LPwMwSJ1g9MBap9Fa3xXq93bVvG', 'Jerve', 'Sanchez', 'Guiral', '', '2004-10-05', 20, 'jerve@gmail.com', 'male', 'purok-2', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-09-25 10:04:52'),
('2023-0032', 'Okay', '$2y$10$P4IS39Btfr5IyWmHK0fK2u.3vvamRoOnIYJ7NmP/f5ent2Xp7Q5vm', 'Kapa', '', 'Gwapo', '', '2004-10-05', 21, 'jerve.guiral@csucc.edu.ph', 'male', 'Purok-3', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-10-27 12:47:01'),
('2023-0043', 'Kapa', '$2y$10$.vqxU7uKGFmjilZZ/owdJeNLnl5PXLRaxUZIucEzMGvZvJbArSrKW', 'Kapa', 'Kapa', 'Gwapo', '', '2000-10-10', 25, 'jervecute123@gmail.com', 'male', 'Purok-3', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-11-02 11:41:00'),
('2023-0052', 'Jovelyn', '$2y$10$v93bGdwcL72ohI2nB49W3ex.j4dC/Sv1vANOkIkc4Fs83MX4vGEvq', 'Jerve', 'Panda', 'Sulicar', 'Jr', '1993-02-10', 32, 'jover.sulicar@csucc.edu.ph', 'male', 'purok2', 'cubo', 'bayugan', 'esperanza', 'Philippines', '8513', '2025-11-07 15:52:39'),
('2023-0054', 'Tigreal', '$2y$10$6W3MikBb/2P/uHe7soWMWe6DY6Yf/a9RT9txYTbF4NspV8KgJh.He', 'Jerve', 'Panda', 'Kapa', '', '2000-10-10', 25, 'gwapoko@gmail.com', 'male', 'Purok-3', 'Dato', 'cabadbaran', 'nato', 'Philippines', '8532', '2025-11-12 01:21:26'),
('2023-4401', 'Doris', '$2y$10$4rhiND7EK6/AzvjK8QPKiOvokMUF6ikVFj7l2baiOLzDPKH9RNsDe', 'Doris', 'Sanchez', 'Guiral', '', '1998-05-30', 27, 'doris123@gmail.com', 'male', 'Purok2', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-11-07 05:09:01'),
('2023-4421', 'Gwapo', '$2y$10$z.6DWiCRUc4WZmapk5q/I.wpkpybDUV3HXBCVtDd0dTDB8wQ2Tpvy', 'Okay', 'Kapa', 'Gwapo', '', '2003-10-10', 22, 'jervecute123@gmail.com', 'male', 'Purok-3', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-10-30 11:26:29'),
('2023-4425', 'Perla', '$2y$10$EiReN/KqOBX8crtvk6eQWOk0E097Gksu9f6O58G7aCmfVjbndYlVm', 'Perla', 'Lapiz', 'Sanchez', 'Jr', '1999-10-10', 26, 'perla@gmail.com', 'female', 'Purok-3', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-11-01 09:58:20'),
('2023-4444', 'Inday', '$2y$10$Wm94/jKFDVlKgbMLbLEx2OVxgNvTwfsAMLDPbQNDVQBAG6y3aJbGa', 'Jerve', 'Panda', 'Kapa', '', '2000-10-10', 25, 'gwapoko@gmail.com', 'male', 'Purok-3', 'Dato', 'cabadbaran', 'nato', 'Philippines', '8532', '2025-11-13 04:08:47'),
('2023-4445', 'Jason', '$2y$10$YI8MFxC6aQTynHZi3kFz3.B0iMdU3.nLlnOjthJ.1FjE9xPiAkXH6', 'Kapa', 'Lapiz', 'Sanchez', 'Jr', '1999-04-10', 26, 'doris123@gmail.com', 'male', 'Purok-3', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-11-09 19:15:34'),
('2023-5541', 'Lamok', '$2y$10$i/.qK5nlTLXiAEXErKet7uuNEfu.ULkJTphe/GnP4cAVMrXjnL27u', 'Jerve', 'Panda', 'Sulicar', 'Jr', '1993-02-10', 32, 'jover.sulicar@csucc.edu.ph', 'male', 'purok2', 'cubo', 'bayugan', 'esperanza', 'Philippines', '8513', '2025-11-07 13:10:07'),
('2024-0024', 'Jovert', '$2y$10$9adPipsvCd/8MFfIb8arteC7UhX/cPez09aFnhZH3LHee2wXSJmhy', 'Jovert', 'Panda', 'Sulicar', '', '2006-06-14', 19, 'jover.sulicar@csucc.edu.ph', 'male', 'Purok-3', 'dato', 'cabadbaran', 'nato', 'Philippines', '8532', '2025-10-27 23:03:40'),
('2024-0026', 'Rolando', '$2y$10$JzoRAkaBn1JEtXbK3sCMZefVBDlLzrKSXY/0aN0oscs8SaSXOCE.S', 'Jerve', 'Panda', 'Kapa', 'Jr', '1999-10-14', 26, 'jervecute123@gmail.com', 'male', 'purok2', 'cubo', 'bayugan', 'esperanza', 'Philippines', '8513', '2025-11-09 05:24:44'),
('2024-0034', 'Thamus', '$2y$10$Wc7i9DBjr5gYqombbII8iePLtpWNR8Ip3urjn0TDPG7pE7YOX2No.', 'John Lincoln', 'Cudog', 'Bihag', '', '2004-12-12', 20, '2lincoln@gmail.com', 'male', '21324', 'cubo', 'bayugan', 'esperanza', 'Philippines', '8513', '2025-11-10 22:15:36'),
('2312-3123', 'Guinever', '$2y$10$pXbe0n.xoVzQewjd6ZxB2uKWqr0qcJWBWUj8OnniQToOewnXnVsu2', 'John Lincoln', 'Cudog', 'Bihag', 'Jr', '2004-12-12', 20, '2lincoln@gmail.com', 'male', '21324', 'cubo', 'bayugan', 'esperanza', 'Philippines', '8513', '2025-11-10 23:18:31'),
('2323-2132', 'Eudura', '$2y$10$qclRambLYcdg28ILIXF4nezyPcthh/Fp9HZJt/SNgXSGioeiDOnQm', 'Jerve', 'Panda', 'Kapa', 'I', '1999-10-10', 26, 'jervecute123@gmail.com', 'male', 'Purok-3', 'dato', 'cabadbaran', 'nato', 'Philippines', '8532', '2025-11-11 00:57:59'),
('2323-2332', 'Thomas', '$2y$10$6r5Xsrh1hosrTFViCYNGseX9DS8Wtz8yF24iWiGMqmb3kTV.4H/nO', 'Kapa', 'Kapa', 'Gwapo', 'Jr', '2000-10-10', 0, 'thomas@gmail.com', 'male', 'Purok-3', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-11-10 14:24:28'),
('2332-3221', 'Dilan', '$2y$10$HwgBDWdhBCIIZfNRAjGV7uDB6VXeGqhMb1ma3furRll1qwD4Qggjq', 'Kapa', 'Kapa', 'Gwapo', 'Jr', '1999-10-10', 0, 'dilan@csucc.edu.ph', 'male', 'Purok-3', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-11-10 14:06:10'),
('3334-4432', 'Guin', '$2y$10$IBcRWAB5.1oK/GXBckGVY.oG138YUGDgcC9oRFE/61R3HU4y1Tgo2', 'Jerve', 'Panda', 'Kapa', '', '2000-10-10', 25, 'jervecute123@gmail.com', 'male', 'Purok-3', 'dato', 'cabadbaran', 'nato', 'Philippines', '8532', '2025-11-11 14:02:22'),
('3334-4444', 'Guko', '$2y$10$ZaGHlxRA5QCUgz0EzPnNqORKCOfqIS.2E1F4G6RfQNpmPjV/3Aoc2', 'Jerve', 'Panda', 'Kapa', 'Jr.', '1999-10-10', 26, 'jervecute123@gmail.com', 'male', '21324', 'cubo', 'bayugan', 'esperanza', 'Philippines', '8513', '2025-11-11 01:09:39'),
('3344-4444', 'Gusion', '$2y$10$2Cp5pSuiBDJgi885/2hOzO0n99/BO2tiCv8565sxDBNlu0lKdqLnC', 'Gusion', 'Kapa', 'Guiral', 'Jr', '2000-10-10', 0, 'gusion@gmail.com', 'male', 'Purok-3', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-11-10 14:37:59'),
('3422-1422', 'Yin', '$2y$10$xI9bBuXiWBNNZtSEWk3U0e3UZq5y5Er5BAgQsxBf4ZOba.SVqSb6G', 'Jerve', 'Sanchez', 'Guiral', 'I', '2000-10-10', 25, 'jerve@gmail.com', 'male', 'purok-2', 'Cubo', 'Bayugan', 'Esperanza', 'Philippines', '8513', '2025-11-12 01:46:41');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `security_questions`
--
ALTER TABLE `security_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id_number`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=254;

--
-- AUTO_INCREMENT for table `security_questions`
--
ALTER TABLE `security_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `security_questions`
--
ALTER TABLE `security_questions`
  ADD CONSTRAINT `security_questions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id_number`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
