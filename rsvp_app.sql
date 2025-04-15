-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 15, 2025 at 06:43 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `rsvp_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_name` varchar(255) NOT NULL,
  `custom_message` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `actual_thaal_count` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_name`, `custom_message`, `is_active`, `actual_thaal_count`, `created_at`) VALUES
(1, 'Default Event', 'Salaam {NAME},\r\n\r\nThank you for your RSVP for the {EVENT_NAME}.\r\nITS: {ITS}\r\nAttendees: {COUNT}\r\n\r\nWe look forward to seeing you!', 0, 2, '2025-04-10 09:46:55'),
(2, 'test5253', 'Salaam {NAME},\r\n\r\nThank you for your RSVP for the {EVENT_NAME}.  test 5253\r\nITS: {ITS}\r\nAttendees: {COUNT}\r\n\r\nWe look forward to seeing you!', 0, NULL, '2025-04-10 10:43:20'),
(3, 'Zohar Asr Namaz 14 04 2025', 'Salaam {NAME},\r\n\r\nThank you for your RSVP for the {EVENT_NAME}.\r\n\r\nAttendees: {COUNT}\r\n\r\nWe look forward to seeing you!', 0, NULL, '2025-04-14 06:10:27'),
(4, 'Magrib Esha 14 04 2025', 'Salaam {NAME},\r\n\r\nThank you for your RSVP for the {EVENT_NAME}.\r\nITS: {ITS}\r\nAttendees: {COUNT}\r\n\r\nWe look forward to seeing you!', 1, NULL, '2025-04-14 08:44:00');

-- --------------------------------------------------------

--
-- Table structure for table `heads_of_family`
--

CREATE TABLE `heads_of_family` (
  `id` int(11) NOT NULL,
  `its_number` varchar(20) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `whatsapp_number` varchar(20) DEFAULT NULL,
  `telegram_chat_id` varchar(50) DEFAULT NULL,
  `sabil_number` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `heads_of_family`
--

INSERT INTO `heads_of_family` (`id`, `its_number`, `name`, `email`, `whatsapp_number`, `telegram_chat_id`, `sabil_number`, `created_at`, `updated_at`) VALUES
(2, '87654321', 'Jane Smith', 'jane.smith@example.com', '+919876543210', NULL, 'SBL002', '2025-04-10 04:10:15', '2025-04-10 04:10:15'),
(3, '30419920', 'Murtaza Gandhi', 'murtaza.i.gandhi@gmail.com', '+917567365118', '984369519', '555', '2025-04-10 04:22:04', '2025-04-10 05:58:16'),
(4, '99999', 'Murtaza Baroda', NULL, NULL, NULL, '012', '2025-04-14 06:11:33', '2025-04-14 06:11:33'),
(5, '123', 'Taher Godhrawala', NULL, NULL, NULL, '0123', '2025-04-14 06:12:03', '2025-04-14 06:12:34'),
(6, '7777777', 'Shabbir Gandhi', NULL, NULL, NULL, '456', '2025-04-14 06:13:06', '2025-04-14 06:13:06'),
(7, '444', 'Hasan bhai Indorewala', NULL, NULL, NULL, '123', '2025-04-14 06:14:17', '2025-04-14 06:14:17'),
(8, '333', 'Mustafa Indore wala', NULL, NULL, NULL, '3456', '2025-04-14 06:14:42', '2025-04-14 06:14:42'),
(9, '666', 'Mufaddal Indore wala', NULL, NULL, NULL, '3456', '2025-04-14 06:15:09', '2025-04-14 06:15:09'),
(10, '44467', 'Moiz Rampurawala', NULL, NULL, NULL, '231', '2025-04-14 06:16:47', '2025-04-14 06:16:47'),
(11, '999124', 'Mustafa masvi', NULL, NULL, NULL, '7', '2025-04-14 07:41:20', '2025-04-14 07:41:20'),
(12, '999999999', 'Shabbir bhai shahiwala, kikabhai', NULL, NULL, NULL, '90', '2025-04-14 07:46:36', '2025-04-14 07:46:36'),
(13, '1', 'guest1', NULL, NULL, NULL, NULL, '2025-04-14 12:22:56', '2025-04-14 12:22:56'),
(14, '2', 'guest2', NULL, NULL, NULL, NULL, '2025-04-14 12:23:05', '2025-04-14 12:23:05'),
(15, '3', 'guest3', NULL, NULL, NULL, NULL, '2025-04-14 12:23:12', '2025-04-14 12:23:12'),
(16, '4', 'guest4', NULL, NULL, NULL, NULL, '2025-04-14 12:23:23', '2025-04-14 12:23:23'),
(17, '5', 'guest5', NULL, NULL, NULL, NULL, '2025-04-14 12:23:36', '2025-04-14 12:23:36'),
(18, '6', 'guest6', NULL, NULL, NULL, NULL, '2025-04-14 12:23:50', '2025-04-14 12:23:50'),
(19, '7', 'guest7', NULL, NULL, NULL, NULL, '2025-04-14 12:23:57', '2025-04-14 12:23:57'),
(20, '8', 'guest8', NULL, NULL, NULL, NULL, '2025-04-14 12:24:07', '2025-04-14 12:24:07'),
(21, '9', 'guest9', NULL, NULL, NULL, NULL, '2025-04-14 12:24:16', '2025-04-14 12:24:16'),
(22, '10', 'guest10', NULL, NULL, NULL, NULL, '2025-04-14 12:24:30', '2025-04-14 12:24:30'),
(23, '11', 'guest11', NULL, NULL, NULL, NULL, '2025-04-14 12:24:40', '2025-04-14 12:24:40'),
(24, '12', 'guest12', NULL, NULL, NULL, NULL, '2025-04-14 12:24:48', '2025-04-14 12:24:48'),
(25, '13', 'guest13', NULL, NULL, NULL, NULL, '2025-04-14 12:24:56', '2025-04-14 12:24:56'),
(26, '14', 'guest14', NULL, NULL, NULL, NULL, '2025-04-14 12:25:03', '2025-04-14 12:25:03'),
(27, '15', 'guest15', NULL, NULL, NULL, NULL, '2025-04-14 12:25:12', '2025-04-14 12:25:12'),
(28, '16', 'guest16', NULL, NULL, NULL, NULL, '2025-04-14 12:25:21', '2025-04-14 12:25:21'),
(29, '456', 'Murtaza bhai Kapadvanjwala', NULL, NULL, NULL, NULL, '2025-04-14 12:54:11', '2025-04-14 12:54:11'),
(30, '30431616', 'Kutub bhai primuswaa prime', NULL, NULL, NULL, '625', '2025-04-14 15:02:43', '2025-04-14 15:02:43');

-- --------------------------------------------------------

--
-- Table structure for table `rsvps`
--

CREATE TABLE `rsvps` (
  `id` int(11) NOT NULL,
  `hof_id` int(11) NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `attendee_count` int(11) NOT NULL,
  `confirmation_method` enum('whatsapp','sms','email','none') DEFAULT NULL,
  `confirmation_sent_at` timestamp NULL DEFAULT NULL,
  `rsvp_timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `rsvps`
--

INSERT INTO `rsvps` (`id`, `hof_id`, `event_id`, `attendee_count`, `confirmation_method`, `confirmation_sent_at`, `rsvp_timestamp`) VALUES
(30, 3, 1, 2, '', '2025-04-10 07:30:11', '2025-04-10 11:00:10'),
(31, 3, 2, 3, '', '2025-04-10 07:16:30', '2025-04-10 10:46:29'),
(32, 3, 3, 1, '', '2025-04-14 02:52:49', '2025-04-14 06:22:47'),
(33, 6, 3, 1, 'none', NULL, '2025-04-14 06:23:05'),
(34, 10, 3, 1, 'none', NULL, '2025-04-14 07:43:20'),
(35, 12, 3, 2, 'none', NULL, '2025-04-14 07:59:29'),
(36, 3, 4, 1, '', '2025-04-14 09:08:05', '2025-04-14 12:38:04'),
(37, 13, 4, 1, 'none', NULL, '2025-04-14 12:25:58'),
(38, 14, 4, 1, 'none', NULL, '2025-04-14 12:26:09'),
(39, 15, 4, 1, 'none', NULL, '2025-04-14 12:26:20'),
(40, 16, 4, 1, 'none', NULL, '2025-04-14 12:26:31'),
(41, 17, 4, 1, 'none', NULL, '2025-04-14 12:26:40'),
(42, 18, 4, 1, 'none', NULL, '2025-04-14 12:26:51'),
(43, 19, 4, 1, 'none', NULL, '2025-04-14 12:27:03'),
(44, 20, 4, 1, 'none', NULL, '2025-04-14 12:27:16'),
(45, 21, 4, 1, 'none', NULL, '2025-04-14 12:27:27'),
(46, 22, 4, 1, 'none', NULL, '2025-04-14 12:27:39'),
(47, 23, 4, 1, 'none', NULL, '2025-04-14 12:27:48'),
(48, 24, 4, 1, 'none', NULL, '2025-04-14 12:27:59'),
(49, 25, 4, 1, 'none', NULL, '2025-04-14 12:28:19'),
(50, 26, 4, 1, 'none', NULL, '2025-04-14 12:28:30'),
(51, 27, 4, 1, 'none', NULL, '2025-04-14 12:28:40'),
(52, 28, 4, 1, 'none', NULL, '2025-04-14 12:28:53'),
(53, 10, 4, 1, 'none', NULL, '2025-04-14 12:48:22'),
(54, 12, 4, 1, 'none', NULL, '2025-04-14 13:51:18'),
(55, 7, 4, 1, 'none', NULL, '2025-04-14 13:51:32'),
(56, 11, 4, 1, 'none', NULL, '2025-04-14 13:56:50'),
(57, 30, 4, 2, 'none', NULL, '2025-04-14 15:04:33');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_name` (`event_name`);

--
-- Indexes for table `heads_of_family`
--
ALTER TABLE `heads_of_family`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `its_number` (`its_number`),
  ADD KEY `idx_its_number` (`its_number`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `rsvps`
--
ALTER TABLE `rsvps`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_rsvp_per_event` (`hof_id`,`event_id`),
  ADD KEY `idx_hof_id` (`hof_id`),
  ADD KEY `idx_eventid` (`event_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `heads_of_family`
--
ALTER TABLE `heads_of_family`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `rsvps`
--
ALTER TABLE `rsvps`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `rsvps`
--
ALTER TABLE `rsvps`
  ADD CONSTRAINT `FK_eventId_Id` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
