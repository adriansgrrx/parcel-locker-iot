-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 16, 2025 at 06:55 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `locker_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `compartments`
--

CREATE TABLE `compartments` (
  `compartment_id` varchar(10) NOT NULL,
  `distance_cm` float NOT NULL DEFAULT 0,
  `is_parcel_detected` tinyint(1) NOT NULL DEFAULT 0,
  `is_security_mode` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('Empty','Occupied','Theft','Retrieved') NOT NULL DEFAULT 'Empty',
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `compartments`
--

INSERT INTO `compartments` (`compartment_id`, `distance_cm`, `is_parcel_detected`, `is_security_mode`, `status`, `timestamp`) VALUES
('C1', 1.99, 0, 0, 'Occupied', '2025-06-16 11:03:53'),
('C2', 3.21, 0, 0, 'Occupied', '2025-06-16 11:06:15');

-- --------------------------------------------------------

--
-- Table structure for table `control_flags`
--

CREATE TABLE `control_flags` (
  `id` int(11) NOT NULL,
  `permission_granted` tinyint(1) NOT NULL DEFAULT 0,
  `reset_triggered` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_delivery` tinyint(1) NOT NULL DEFAULT 0,
  `alert_user` tinyint(1) NOT NULL DEFAULT 0,
  `parcel_count_c1` int(11) DEFAULT 0,
  `parcel_count_c2` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `control_flags`
--

INSERT INTO `control_flags` (`id`, `permission_granted`, `reset_triggered`, `submitted_delivery`, `alert_user`, `parcel_count_c1`, `parcel_count_c2`) VALUES
(1, 0, 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Stand-in structure for view `current_compartments`
-- (See below for the actual view)
--
CREATE TABLE `current_compartments` (
`compartment_id` varchar(10)
,`distance_cm` float
,`is_parcel_detected` tinyint(1)
,`is_security_mode` tinyint(1)
,`status` enum('Empty','Occupied','Theft','Retrieved')
,`timestamp` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `events_log`
--

CREATE TABLE `events_log` (
  `id` int(11) NOT NULL,
  `event_type` enum('parcel_detected','parcel_removed','theft_alert','permission_granted','security_reset','system_startup') NOT NULL,
  `compartment_id` varchar(10) DEFAULT NULL,
  `status` enum('Empty','Occupied','Theft','Retrieved') NOT NULL,
  `permission_granted` tinyint(1) NOT NULL DEFAULT 0,
  `reset_triggered` tinyint(1) NOT NULL DEFAULT 0,
  `distance_cm` float NOT NULL DEFAULT 0,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events_log`
--

INSERT INTO `events_log` (`id`, `event_type`, `compartment_id`, `status`, `permission_granted`, `reset_triggered`, `distance_cm`, `timestamp`) VALUES
(1, 'system_startup', NULL, 'Empty', 0, 0, 0, '2025-06-08 05:37:04'),

-- --------------------------------------------------------

--
-- Stand-in structure for view `recent_events`
-- (See below for the actual view)
--
CREATE TABLE `recent_events` (
`id` int(11)
,`event_type` enum('parcel_detected','parcel_removed','theft_alert','permission_granted','security_reset','system_startup')
,`compartment_id` varchar(10)
,`status` enum('Empty','Occupied','Theft','Retrieved')
,`permission_granted` tinyint(1)
,`reset_triggered` tinyint(1)
,`distance_cm` float
,`timestamp` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `system_status`
--

CREATE TABLE `system_status` (
  `id` int(11) NOT NULL DEFAULT 1,
  `solenoid_state` enum('LOCKED','UNLOCKED') NOT NULL DEFAULT 'LOCKED',
  `buzzer_state` enum('ON','OFF') NOT NULL DEFAULT 'OFF',
  `last_permission` tinyint(1) NOT NULL DEFAULT 0,
  `last_reset` tinyint(1) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `system_status`
--

INSERT INTO `system_status` (`id`, `solenoid_state`, `buzzer_state`, `last_permission`, `last_reset`, `updated_at`) VALUES
(1, 'LOCKED', 'OFF', 0, 0, '2025-06-08 05:37:04');

-- --------------------------------------------------------

--
-- Structure for view `current_compartments`
--
DROP TABLE IF EXISTS `current_compartments`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `current_compartments`  AS SELECT `compartments`.`compartment_id` AS `compartment_id`, `compartments`.`distance_cm` AS `distance_cm`, `compartments`.`is_parcel_detected` AS `is_parcel_detected`, `compartments`.`is_security_mode` AS `is_security_mode`, `compartments`.`status` AS `status`, `compartments`.`timestamp` AS `timestamp` FROM `compartments` ;

-- --------------------------------------------------------

--
-- Structure for view `recent_events`
--
DROP TABLE IF EXISTS `recent_events`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `recent_events`  AS SELECT `events_log`.`id` AS `id`, `events_log`.`event_type` AS `event_type`, `events_log`.`compartment_id` AS `compartment_id`, `events_log`.`status` AS `status`, `events_log`.`permission_granted` AS `permission_granted`, `events_log`.`reset_triggered` AS `reset_triggered`, `events_log`.`distance_cm` AS `distance_cm`, `events_log`.`timestamp` AS `timestamp` FROM `events_log` ORDER BY `events_log`.`timestamp` DESC LIMIT 0, 50 ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `compartments`
--
ALTER TABLE `compartments`
  ADD PRIMARY KEY (`compartment_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `control_flags`
--
ALTER TABLE `control_flags`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events_log`
--
ALTER TABLE `events_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_compartment` (`compartment_id`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `system_status`
--
ALTER TABLE `system_status`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `control_flags`
--
ALTER TABLE `control_flags`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `events_log`
--
ALTER TABLE `events_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68798;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `events_log`
--
ALTER TABLE `events_log`
  ADD CONSTRAINT `events_log_ibfk_1` FOREIGN KEY (`compartment_id`) REFERENCES `compartments` (`compartment_id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;