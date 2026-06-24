-- phpMyAdmin SQL Dump
-- Smart Traffic Route Guidance System (STRGS)
-- A Case of Remera Sector, Kigali, Rwanda
--
-- Host: 127.0.0.1
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12
--
-- Scope: Web-based route guidance using GPS and road status updates.
-- Users: Drivers/Road Users, Traffic Police Officers, Administrators.
-- Removed: AI model tables and nationwide synthetic training data.

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `smart_traffic_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `smart_traffic_db`;

-- --------------------------------------------------------
-- Drop legacy / AI tables if present from older dumps
-- --------------------------------------------------------

DROP TABLE IF EXISTS `ai_predictions`;
DROP TABLE IF EXISTS `ai_models`;

-- --------------------------------------------------------
-- Table: users
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone_number` varchar(30) DEFAULT NULL,
  `user_type` enum('driver','traffic_authority','administrator') NOT NULL DEFAULT 'driver',
  `license_id` varchar(50) DEFAULT NULL COMMENT 'Driving license ID for drivers',
  `police_id` varchar(50) DEFAULT NULL COMMENT 'Service ID for traffic police officers',
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','authority','viewer') NOT NULL DEFAULT 'viewer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`),
  UNIQUE KEY `uq_users_phone_number` (`phone_number`),
  UNIQUE KEY `uk_users_license_id` (`license_id`),
  UNIQUE KEY `uk_users_police_id` (`police_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `first_name`, `last_name`, `username`, `email`, `phone_number`, `user_type`, `license_id`, `police_id`, `password_hash`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, 'IZUKONDI', 'Bienvenue', 'Picker', 'izukondibien@gmail.com', '+250790561952', 'traffic_authority', NULL, 'RNP-45201', '$2y$10$CnaQOEYfhWqQPVMa.PZDSOaneH3lE05OBgXRlz6iXBYtvS2OZDOZy', 'authority', 1, '2026-06-23 16:40:54', '2026-04-27 22:56:20', '2026-06-24 08:38:00'),
(3, 'BYIRINGIRO', 'Steven', 'Admin', 'byiringirosteven@gmail.com', '+250782137666', 'administrator', NULL, NULL, '$2y$10$MmrJY2MOJ.k6fwuh7NFJ9O.UlEVZlYHwGMls06oxQ5O7R7rjHIlHe', 'admin', 1, NULL, '2026-04-30 20:30:57', '2026-06-24 08:38:00'),
(4, 'ISHIMWE', 'Elvie', 'elvie', 'ishimweelvie@gmail.com', '+250789126323', 'driver', 'RW-78451236', NULL, '$2y$10$GxgOSxXdH8wMhvFbDxoFW.w7a5u0Wb4zKIWxFjcMDtkBXNT94rC6y', 'viewer', 1, '2026-05-11 00:10:08', '2026-05-10 21:52:41', '2026-06-24 08:38:00'),
(5, 'IZUKONDI', 'Bienvenue', 'Panther', 'izukondibienvenue@gmail.com', '+250792021947', 'driver', 'RW-90817263', NULL, '$2y$10$NjbYzPj9SCJE.ZN.HSOEcun.b0vA.xV09Z5RhpFmUPhI2o1ownCA6', 'viewer', 1, '2026-06-24 07:43:00', '2026-06-23 16:43:57', '2026-06-24 08:38:00');

-- --------------------------------------------------------
-- Table: user_sessions (password reset tokens)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `session_token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_sessions_token` (`session_token`),
  KEY `idx_user_sessions_user` (`user_id`),
  CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table: road_segments (Remera Sector network)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `road_segments` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(190) NOT NULL,
  `zone` varchar(120) NOT NULL DEFAULT 'Remera Sector',
  `start_lat` decimal(10,7) DEFAULT NULL,
  `start_lng` decimal(10,7) DEFAULT NULL,
  `end_lat` decimal(10,7) DEFAULT NULL,
  `end_lng` decimal(10,7) DEFAULT NULL,
  `speed_limit_kmh` smallint(5) UNSIGNED NOT NULL DEFAULT 50,
  `lanes` tinyint(3) UNSIGNED NOT NULL DEFAULT 2,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_road_segments_zone` (`zone`),
  KEY `idx_road_segments_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `road_segments` (`id`, `name`, `zone`, `start_lat`, `start_lng`, `end_lat`, `end_lng`, `speed_limit_kmh`, `lanes`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'KG 11 Avenue (Remera)', 'Remera Sector', -1.9538000, 30.1045000, -1.9562000, 30.1088000, 50, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(2, 'KN 3 Road (Remera)', 'Remera Sector', -1.9512000, 30.1021000, -1.9548000, 30.1065000, 50, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(3, 'Stadium Road (Amahoro)', 'Remera Sector', -1.9505000, 30.1112000, -1.9529000, 30.1148000, 40, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(4, 'Sonatubes - Remera Taxi Park', 'Remera Sector', -1.9571000, 30.1074000, -1.9593000, 30.1102000, 40, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(5, 'Remera Hospital Road', 'Remera Sector', -1.9556000, 30.1098000, -1.9580000, 30.1125000, 40, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(6, 'Giporoso Main Road', 'Remera Sector', -1.9488000, 30.0995000, -1.9524000, 30.1038000, 50, 3, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(7, 'Kisimenti - Remera Link', 'Remera Sector', -1.9465000, 30.1012000, -1.9501000, 30.1056000, 50, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(8, 'Airport Road Junction (Remera)', 'Remera Sector', -1.9602000, 30.1135000, -1.9631000, 30.1172000, 60, 3, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(9, 'BPR Roundabout - Remera', 'Remera Sector', -1.9543000, 30.1059000, -1.9567000, 30.1083000, 40, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(10, 'KK 15 Avenue', 'Remera Sector', -1.9497000, 30.1078000, -1.9525000, 30.1110000, 50, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(11, 'Remera - Kimironko Road', 'Remera Sector', -1.9478000, 30.1095000, -1.9442000, 30.1142000, 50, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(12, 'Mount Kigali Road Approach', 'Remera Sector', -1.9615000, 30.1008000, -1.9588000, 30.1042000, 50, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(13, 'RNP Remera Post Access', 'Remera Sector', -1.9531000, 30.1106000, -1.9550000, 30.1130000, 30, 1, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(14, 'Remera Commercial Strip', 'Remera Sector', -1.9520000, 30.1062000, -1.9546000, 30.1090000, 40, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00'),
(15, 'Nyabisindu - Remera Connector', 'Remera Sector', -1.9458000, 30.1128000, -1.9489000, 30.1165000, 50, 2, 1, '2026-06-24 08:00:00', '2026-06-24 08:00:00');

-- --------------------------------------------------------
-- Table: traffic_readings (road status / congestion data)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `traffic_readings` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` int(10) UNSIGNED NOT NULL,
  `reading_time` datetime NOT NULL,
  `vehicle_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `avg_speed_kmh` decimal(6,2) NOT NULL DEFAULT 0.00,
  `density_veh_per_km` decimal(8,2) NOT NULL DEFAULT 0.00,
  `occupancy_pct` decimal(5,2) DEFAULT NULL,
  `weather` enum('clear','rain','fog','storm','snow','unknown') NOT NULL DEFAULT 'unknown',
  `visibility_m` int(10) UNSIGNED DEFAULT NULL,
  `temperature_c` decimal(5,2) DEFAULT NULL,
  `source` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_traffic_readings_segment_time` (`segment_id`,`reading_time`),
  CONSTRAINT `fk_traffic_readings_segment` FOREIGN KEY (`segment_id`) REFERENCES `road_segments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `traffic_readings` (`id`, `segment_id`, `reading_time`, `vehicle_count`, `avg_speed_kmh`, `density_veh_per_km`, `occupancy_pct`, `weather`, `visibility_m`, `temperature_c`, `source`, `created_at`) VALUES
(1, 1, '2026-06-24 07:00:00', 85, 28.50, 42.00, 68.00, 'clear', 8000, 22.00, 'CCTV-Analytics', '2026-06-24 08:00:00'),
(2, 1, '2026-06-24 08:00:00', 142, 18.20, 71.00, 82.00, 'clear', 8000, 24.00, 'CCTV-Analytics', '2026-06-24 08:05:00'),
(3, 2, '2026-06-24 07:30:00', 62, 35.00, 31.00, 55.00, 'clear', 9000, 23.00, 'MobileProbe', '2026-06-24 08:00:00'),
(4, 2, '2026-06-24 08:15:00', 98, 22.40, 49.00, 74.00, 'rain', 5000, 21.00, 'IoT-Sensor', '2026-06-24 08:10:00'),
(5, 3, '2026-06-24 08:00:00', 120, 15.80, 60.00, 88.00, 'clear', 8000, 24.00, 'CCTV-Analytics', '2026-06-24 08:00:00'),
(6, 4, '2026-06-24 07:45:00', 175, 12.50, 87.00, 91.00, 'clear', 7500, 23.50, 'CCTV-Analytics', '2026-06-24 08:00:00'),
(7, 5, '2026-06-24 08:00:00', 45, 38.00, 22.00, 42.00, 'clear', 9000, 24.00, 'MobileProbe', '2026-06-24 08:00:00'),
(8, 6, '2026-06-24 08:10:00', 110, 20.60, 55.00, 76.00, 'rain', 4500, 20.00, 'IoT-Sensor', '2026-06-24 08:10:00'),
(9, 7, '2026-06-24 08:00:00', 78, 32.00, 39.00, 58.00, 'clear', 8500, 23.00, 'MobileProbe', '2026-06-24 08:00:00'),
(10, 8, '2026-06-24 07:30:00', 95, 42.00, 47.00, 52.00, 'clear', 10000, 22.00, 'CCTV-Analytics', '2026-06-24 08:00:00'),
(11, 9, '2026-06-24 08:20:00', 156, 14.00, 78.00, 89.00, 'clear', 8000, 25.00, 'CCTV-Analytics', '2026-06-24 08:15:00'),
(12, 10, '2026-06-24 08:00:00', 68, 30.50, 34.00, 61.00, 'clear', 9000, 24.00, 'MobileProbe', '2026-06-24 08:00:00'),
(13, 11, '2026-06-24 07:00:00', 52, 40.00, 26.00, 48.00, 'clear', 10000, 21.00, 'MobileProbe', '2026-06-24 08:00:00'),
(14, 12, '2026-06-24 08:00:00', 88, 25.00, 44.00, 65.00, 'fog', 3000, 19.00, 'IoT-Sensor', '2026-06-24 08:00:00'),
(15, 14, '2026-06-24 08:30:00', 132, 16.80, 66.00, 84.00, 'clear', 8000, 25.00, 'CCTV-Analytics', '2026-06-24 08:25:00');

-- --------------------------------------------------------
-- Table: accidents (incidents reported by traffic police)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `accidents` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` int(10) UNSIGNED NOT NULL,
  `accident_time` datetime NOT NULL,
  `severity` enum('minor','moderate','severe','fatal') NOT NULL DEFAULT 'minor',
  `cause` varchar(190) DEFAULT NULL,
  `vehicles_involved` tinyint(3) UNSIGNED DEFAULT NULL,
  `injuries` smallint(5) UNSIGNED DEFAULT NULL,
  `fatalities` smallint(5) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reported_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_accidents_segment_time` (`segment_id`,`accident_time`),
  KEY `idx_accidents_reported_by` (`reported_by`),
  CONSTRAINT `fk_accidents_segment` FOREIGN KEY (`segment_id`) REFERENCES `road_segments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_accidents_reporter` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `accidents` (`id`, `segment_id`, `accident_time`, `severity`, `cause`, `vehicles_involved`, `injuries`, `fatalities`, `notes`, `reported_by`, `created_at`) VALUES
(1, 4, '2026-06-23 17:30:00', 'minor', 'Rear-end collision near taxi park', 2, 0, 0, 'Minor bumper damage, traffic slowed for 25 minutes', 1, '2026-06-23 17:35:00'),
(2, 9, '2026-06-22 08:15:00', 'moderate', 'Motorcycle vs vehicle at roundabout', 2, 1, 0, 'Injured rider taken to Remera Hospital', 1, '2026-06-22 08:20:00'),
(3, 3, '2026-06-21 19:00:00', 'minor', 'Pedestrian crossing near stadium', 1, 0, 0, 'No injuries, road partially blocked during match exit', 1, '2026-06-21 19:05:00'),
(4, 6, '2026-06-20 07:45:00', 'severe', 'Overspeeding during morning rush', 3, 2, 0, 'Two lanes blocked, alternative route via KK 15 advised', 1, '2026-06-20 07:50:00'),
(5, 1, '2026-06-19 14:20:00', 'minor', 'Vehicle breakdown on KG 11', 1, 0, 0, 'Breakdown cleared within 40 minutes', 1, '2026-06-19 14:25:00');

-- --------------------------------------------------------
-- Table: alerts (congestion, incidents, road blockages)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `alerts` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` int(10) UNSIGNED NOT NULL,
  `alert_time` datetime NOT NULL DEFAULT current_timestamp(),
  `type` enum('congestion','incident','road_blockage','system') NOT NULL DEFAULT 'congestion',
  `severity` enum('info','warning','critical') NOT NULL DEFAULT 'info',
  `message` varchar(255) NOT NULL,
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  `acknowledged_by` int(10) UNSIGNED DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_alerts_segment_time` (`segment_id`,`alert_time`),
  KEY `idx_alerts_ack` (`is_acknowledged`),
  KEY `fk_alerts_ack_user` (`acknowledged_by`),
  CONSTRAINT `fk_alerts_ack_user` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_alerts_segment` FOREIGN KEY (`segment_id`) REFERENCES `road_segments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `alerts` (`id`, `segment_id`, `alert_time`, `type`, `severity`, `message`, `is_acknowledged`, `acknowledged_by`, `acknowledged_at`, `created_at`) VALUES
(1, 4, '2026-06-24 08:00:00', 'congestion', 'warning', 'Heavy congestion at Sonatubes - Remera Taxi Park. Expect delays.', 0, NULL, NULL, '2026-06-24 08:00:00'),
(2, 9, '2026-06-24 08:15:00', 'congestion', 'critical', 'BPR Roundabout jammed — use KN 3 Road or KK 15 Avenue.', 0, NULL, NULL, '2026-06-24 08:15:00'),
(3, 3, '2026-06-24 07:30:00', 'incident', 'warning', 'Slow traffic near Amahoro Stadium due to event exit.', 1, 1, '2026-06-24 07:45:00', '2026-06-24 07:30:00'),
(4, 6, '2026-06-24 08:10:00', 'road_blockage', 'critical', 'Partial lane closure on Giporoso Main Road — road works in progress.', 0, NULL, NULL, '2026-06-24 08:10:00'),
(5, 12, '2026-06-24 08:00:00', 'incident', 'info', 'Reduced visibility on Mount Kigali Road Approach due to fog.', 0, NULL, NULL, '2026-06-24 08:00:00'),
(6, 1, '2026-06-24 08:20:00', 'congestion', 'warning', 'Morning peak congestion on KG 11 Avenue (Remera).', 0, NULL, NULL, '2026-06-24 08:20:00'),
(7, 8, '2026-06-24 07:00:00', 'congestion', 'info', 'Moderate traffic at Airport Road Junction — normal for hour.', 1, 3, '2026-06-24 07:15:00', '2026-06-24 07:00:00'),
(8, 14, '2026-06-24 08:30:00', 'congestion', 'warning', 'Commercial strip congestion — consider Remera Hospital Road.', 0, NULL, NULL, '2026-06-24 08:30:00');

-- --------------------------------------------------------
-- Table: audit_log (system activity & route guidance history)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `table_name` varchar(80) DEFAULT NULL,
  `record_id` bigint(20) UNSIGNED DEFAULT NULL,
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_log_time` (`created_at`),
  KEY `idx_audit_log_user` (`user_id`),
  KEY `idx_audit_log_action` (`action`),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `table_name`, `record_id`, `new_values`, `old_values`, `created_at`) VALUES
(1, 3, 'system_init', 'road_segments', NULL, '{\"note\":\"STRGS Remera Sector database initialized\"}', NULL, '2026-06-24 08:00:00'),
(2, 4, 'route_guidance', 'route_guidance', NULL, '{\"segment_id\":11,\"congestion_level\":\"low\",\"origin\":\"Kisimenti\",\"destination\":\"Kimironko\",\"confidence\":0.86}', NULL, '2026-06-23 09:15:00'),
(3, 1, 'road_status_update', 'traffic_readings', 6, '{\"segment_id\":4,\"vehicle_count\":175,\"avg_speed_kmh\":12.5,\"source\":\"traffic_police\"}', NULL, '2026-06-24 07:50:00'),
(4, 1, 'incident_report', 'accidents', 1, '{\"segment_id\":4,\"severity\":\"minor\",\"cause\":\"Rear-end collision\"}', NULL, '2026-06-23 17:35:00'),
(5, 5, 'route_guidance', 'route_guidance', NULL, '{\"segment_id\":2,\"congestion_level\":\"moderate\",\"origin\":\"Remera\",\"destination\":\"City Centre\",\"confidence\":0.78}', NULL, '2026-06-24 07:30:00'),
(6, 3, 'upload_csv', 'traffic_readings', NULL, '{\"imported\":15,\"zone\":\"Remera Sector\"}', NULL, '2026-06-24 08:05:00');

-- --------------------------------------------------------
-- API registry tables (driver licenses & police IDs)
-- --------------------------------------------------------

CREATE TABLE IF NOT EXISTS `api_driver_licenses` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `license_id` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_driver_license_id` (`license_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `api_police_ids` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `police_id` varchar(20) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_police_id` (`police_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

INSERT INTO `api_driver_licenses` (`id`, `name`, `license_id`) VALUES
(1, 'IZUKONDI Emmanuel', 'RW-10000001'),
(2, 'BYIRINGIRO Theo', 'RW-10000002'),
(3, 'ISHIMWE Honorine', 'RW-10000003'),
(4, 'MUKAMANA Liliane', 'RW-10000004'),
(5, 'UWIMANA Diane', 'RW-10000005'),
(6, 'KALISA Kevin', 'RW-10000006'),
(7, 'HABIMANA Rose', 'RW-10000007'),
(8, 'NIYONSHUTI Yvette', 'RW-10000008'),
(9, 'MUGISHA Florence', 'RW-10000009'),
(10, 'TWIZEYIMANA Elvie', 'RW-10000010'),
(11, 'RUKUNDO Divine', 'RW-10000011'),
(12, 'NDAYISABA Sandrine', 'RW-10000012'),
(13, 'SHEMA Providence', 'RW-10000013'),
(14, 'BIZIMANA Innocent', 'RW-10000014'),
(15, 'GATARSI Clement', 'RW-10000015'),
(16, 'KAYITARE Jacqueline', 'RW-10000016'),
(17, 'MUREKATETE Quentin', 'RW-10000017'),
(18, 'UWASE Xavier', 'RW-10000018'),
(19, 'NIYONSABA Evariste', 'RW-10000019'),
(20, 'HATEGEKIMANA Steven', 'RW-10000020'),
(21, 'UWINGABIRE Eric', 'RW-10000021'),
(22, 'MUTABAZI Daniel', 'RW-10000022'),
(23, 'SIBOMANA Didier', 'RW-10000023'),
(24, 'NDUWAYEZU Valentin', 'RW-10000024'),
(25, 'HABYARIMANA Bosco', 'RW-10000025'),
(26, 'KANAMU Isaac', 'RW-10000026'),
(27, 'MUSONI Pascaline', 'RW-10000027'),
(28, 'NSENGIYUMVA William', 'RW-10000028'),
(29, 'RUTAYISIRE Delphine', 'RW-10000029'),
(30, 'BERWA Bienvenue', 'RW-10000030'),
(31, 'MUKESHIMANA Grace', 'RW-10000031'),
(32, 'NYIRAMAHIRE Olive', 'RW-10000032'),
(33, 'HABYARIMANA Vestine', 'RW-10000033'),
(34, 'KARANGWA Consolee', 'RW-10000034'),
(35, 'MUNEZERO Ange', 'RW-10000035'),
(36, 'NIYITEGEKA Helene', 'RW-10000036'),
(37, 'HABIMANA Oscar', 'RW-10000037'),
(38, 'UWAMAHORO Viateur', 'RW-10000038'),
(39, 'MUKANDORI Cedric', 'RW-10000039'),
(40, 'CYIZA Justin', 'RW-10000040'),
(41, 'GATABAZI Patrick', 'RW-10000041'),
(42, 'NIYONKURU Fabrice', 'RW-10000042'),
(43, 'MUGABO Pacifique', 'RW-10000043'),
(44, 'UWAMARIYA Richard', 'RW-10000044'),
(45, 'HABINEZA Samuel', 'RW-10000045'),
(46, 'NIYONIZEYE Gaspard', 'RW-10000046'),
(47, 'MURENZI Nadine', 'RW-10000047'),
(48, 'UWIHOREYE Urbain', 'RW-10000048'),
(49, 'SHEMA Benigne', 'RW-10000049'),
(50, 'KAYONDE Irene', 'RW-10000050'),
(51, 'MUKASINE Clarisse', 'RW-10000051'),
(52, 'NIYIBIZI Alice', 'RW-10000052'),
(53, 'HAGENIMANA Agnes', 'RW-10000053'),
(54, 'UWAMUNGU Josiane', 'RW-10000054'),
(55, 'MUTONI Rebecca', 'RW-10000055'),
(56, 'CYUBAHIRO Francine', 'RW-10000056'),
(57, 'IZUKONDI Michel', 'RW-10000057'),
(58, 'BYIRINGIRO Therese', 'RW-10000058'),
(59, 'ISHIMWE Aimable', 'RW-10000059'),
(60, 'MUKAMANA Henriette', 'RW-10000060'),
(61, 'UWIMANA Marie', 'RW-10000061'),
(62, 'KALISA Chantal', 'RW-10000062'),
(63, 'HABIMANA Yves', 'RW-10000063'),
(64, 'NIYONSHUTI Olivier', 'RW-10000064'),
(65, 'MUGISHA Moise', 'RW-10000065'),
(66, 'TWIZEYIMANA Edouard', 'RW-10000066'),
(67, 'RUKUNDO Laurence', 'RW-10000067'),
(68, 'NDAYISABA Serge', 'RW-10000068'),
(69, 'SHEMA Zacharie', 'RW-10000069'),
(70, 'BIZIMANA Gisele', 'RW-10000070'),
(71, 'GATARSI Jean', 'RW-10000071'),
(72, 'KAYITARE Emmanuel', 'RW-10000072'),
(73, 'MUREKATETE Theo', 'RW-10000073'),
(74, 'UWASE Honorine', 'RW-10000074'),
(75, 'NIYONSABA Liliane', 'RW-10000075'),
(76, 'HATEGEKIMANA Diane', 'RW-10000076'),
(77, 'UWINGABIRE Kevin', 'RW-10000077'),
(78, 'MUTABAZI Rose', 'RW-10000078'),
(79, 'SIBOMANA Yvette', 'RW-10000079'),
(80, 'NDUWAYEZU Florence', 'RW-10000080'),
(81, 'HABYARIMANA Elvie', 'RW-10000081'),
(82, 'KANAMU Divine', 'RW-10000082'),
(83, 'MUSONI Sandrine', 'RW-10000083'),
(84, 'NSENGIYUMVA Providence', 'RW-10000084'),
(85, 'RUTAYISIRE Innocent', 'RW-10000085'),
(86, 'BERWA Clement', 'RW-10000086'),
(87, 'MUKESHIMANA Jacqueline', 'RW-10000087'),
(88, 'NYIRAMAHIRE Quentin', 'RW-10000088'),
(89, 'HABYARIMANA Xavier', 'RW-10000089'),
(90, 'KARANGWA Evariste', 'RW-10000090'),
(91, 'MUNEZERO Steven', 'RW-10000091'),
(92, 'NIYITEGEKA Eric', 'RW-10000092'),
(93, 'HABIMANA Daniel', 'RW-10000093'),
(94, 'UWAMAHORO Didier', 'RW-10000094'),
(95, 'MUKANDORI Valentin', 'RW-10000095'),
(96, 'CYIZA Bosco', 'RW-10000096'),
(97, 'GATABAZI Isaac', 'RW-10000097'),
(98, 'NIYONKURU Pascaline', 'RW-10000098'),
(99, 'MUGABO William', 'RW-10000099'),
(100, 'UWAMARIYA Delphine', 'RW-10000100'),
(101, 'ISHIMWE Elvie', 'RW-78451236'),
(102, 'IZUKONDI Bienvenue', 'RW-90817263');

INSERT INTO `api_police_ids` (`id`, `name`, `police_id`) VALUES
(1, 'IZUKONDI Emmanuel', 'RNP-10001'),
(2, 'BYIRINGIRO Theo', 'RNP-10002'),
(3, 'ISHIMWE Honorine', 'RNP-10003'),
(4, 'MUKAMANA Liliane', 'RNP-10004'),
(5, 'UWIMANA Diane', 'RNP-10005'),
(6, 'KALISA Kevin', 'RNP-10006'),
(7, 'HABIMANA Rose', 'RNP-10007'),
(8, 'NIYONSHUTI Yvette', 'RNP-10008'),
(9, 'MUGISHA Florence', 'RNP-10009'),
(10, 'TWIZEYIMANA Elvie', 'RNP-10010'),
(11, 'RUKUNDO Divine', 'RNP-10011'),
(12, 'NDAYISABA Sandrine', 'RNP-10012'),
(13, 'SHEMA Providence', 'RNP-10013'),
(14, 'BIZIMANA Innocent', 'RNP-10014'),
(15, 'GATARSI Clement', 'RNP-10015'),
(16, 'KAYITARE Jacqueline', 'RNP-10016'),
(17, 'MUREKATETE Quentin', 'RNP-10017'),
(18, 'UWASE Xavier', 'RNP-10018'),
(19, 'NIYONSABA Evariste', 'RNP-10019'),
(20, 'HATEGEKIMANA Steven', 'RNP-10020'),
(21, 'UWINGABIRE Eric', 'RNP-10021'),
(22, 'MUTABAZI Daniel', 'RNP-10022'),
(23, 'SIBOMANA Didier', 'RNP-10023'),
(24, 'NDUWAYEZU Valentin', 'RNP-10024'),
(25, 'HABYARIMANA Bosco', 'RNP-10025'),
(26, 'KANAMU Isaac', 'RNP-10026'),
(27, 'MUSONI Pascaline', 'RNP-10027'),
(28, 'NSENGIYUMVA William', 'RNP-10028'),
(29, 'RUTAYISIRE Delphine', 'RNP-10029'),
(30, 'BERWA Bienvenue', 'RNP-10030'),
(31, 'MUKESHIMANA Grace', 'RNP-10031'),
(32, 'NYIRAMAHIRE Olive', 'RNP-10032'),
(33, 'HABYARIMANA Vestine', 'RNP-10033'),
(34, 'KARANGWA Consolee', 'RNP-10034'),
(35, 'MUNEZERO Ange', 'RNP-10035'),
(36, 'NIYITEGEKA Helene', 'RNP-10036'),
(37, 'HABIMANA Oscar', 'RNP-10037'),
(38, 'UWAMAHORO Viateur', 'RNP-10038'),
(39, 'MUKANDORI Cedric', 'RNP-10039'),
(40, 'CYIZA Justin', 'RNP-10040'),
(41, 'GATABAZI Patrick', 'RNP-10041'),
(42, 'NIYONKURU Fabrice', 'RNP-10042'),
(43, 'MUGABO Pacifique', 'RNP-10043'),
(44, 'UWAMARIYA Richard', 'RNP-10044'),
(45, 'HABINEZA Samuel', 'RNP-10045'),
(46, 'NIYONIZEYE Gaspard', 'RNP-10046'),
(47, 'MURENZI Nadine', 'RNP-10047'),
(48, 'UWIHOREYE Urbain', 'RNP-10048'),
(49, 'SHEMA Benigne', 'RNP-10049'),
(50, 'KAYONDE Irene', 'RNP-10050'),
(51, 'MUKASINE Clarisse', 'RNP-10051'),
(52, 'NIYIBIZI Alice', 'RNP-10052'),
(53, 'HAGENIMANA Agnes', 'RNP-10053'),
(54, 'UWAMUNGU Josiane', 'RNP-10054'),
(55, 'MUTONI Rebecca', 'RNP-10055'),
(56, 'CYUBAHIRO Francine', 'RNP-10056'),
(57, 'IZUKONDI Michel', 'RNP-10057'),
(58, 'BYIRINGIRO Therese', 'RNP-10058'),
(59, 'ISHIMWE Aimable', 'RNP-10059'),
(60, 'MUKAMANA Henriette', 'RNP-10060'),
(61, 'UWIMANA Marie', 'RNP-10061'),
(62, 'KALISA Chantal', 'RNP-10062'),
(63, 'HABIMANA Yves', 'RNP-10063'),
(64, 'NIYONSHUTI Olivier', 'RNP-10064'),
(65, 'MUGISHA Moise', 'RNP-10065'),
(66, 'TWIZEYIMANA Edouard', 'RNP-10066'),
(67, 'RUKUNDO Laurence', 'RNP-10067'),
(68, 'NDAYISABA Serge', 'RNP-10068'),
(69, 'SHEMA Zacharie', 'RNP-10069'),
(70, 'BIZIMANA Gisele', 'RNP-10070'),
(71, 'GATARSI Jean', 'RNP-10071'),
(72, 'KAYITARE Emmanuel', 'RNP-10072'),
(73, 'MUREKATETE Theo', 'RNP-10073'),
(74, 'UWASE Honorine', 'RNP-10074'),
(75, 'NIYONSABA Liliane', 'RNP-10075'),
(76, 'HATEGEKIMANA Diane', 'RNP-10076'),
(77, 'UWINGABIRE Kevin', 'RNP-10077'),
(78, 'MUTABAZI Rose', 'RNP-10078'),
(79, 'SIBOMANA Yvette', 'RNP-10079'),
(80, 'NDUWAYEZU Florence', 'RNP-10080'),
(81, 'HABYARIMANA Elvie', 'RNP-10081'),
(82, 'KANAMU Divine', 'RNP-10082'),
(83, 'MUSONI Sandrine', 'RNP-10083'),
(84, 'NSENGIYUMVA Providence', 'RNP-10084'),
(85, 'RUTAYISIRE Innocent', 'RNP-10085'),
(86, 'BERWA Clement', 'RNP-10086'),
(87, 'MUKESHIMANA Jacqueline', 'RNP-10087'),
(88, 'NYIRAMAHIRE Quentin', 'RNP-10088'),
(89, 'HABYARIMANA Xavier', 'RNP-10089'),
(90, 'KARANGWA Evariste', 'RNP-10090'),
(91, 'MUNEZERO Steven', 'RNP-10091'),
(92, 'NIYITEGEKA Eric', 'RNP-10092'),
(93, 'HABIMANA Daniel', 'RNP-10093'),
(94, 'UWAMAHORO Didier', 'RNP-10094'),
(95, 'MUKANDORI Valentin', 'RNP-10095'),
(96, 'CYIZA Bosco', 'RNP-10096'),
(97, 'GATABAZI Isaac', 'RNP-10097'),
(98, 'NIYONKURU Pascaline', 'RNP-10098'),
(99, 'MUGABO William', 'RNP-10099'),
(100, 'UWAMARIYA Delphine', 'RNP-10100'),
(101, 'IZUKONDI Bienvenue', 'RNP-45201');

-- AUTO_INCREMENT values
-- --------------------------------------------------------

ALTER TABLE `users` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `road_segments` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
ALTER TABLE `traffic_readings` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
ALTER TABLE `accidents` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;
ALTER TABLE `alerts` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;
ALTER TABLE `audit_log` MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
ALTER TABLE `api_driver_licenses` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=103;
ALTER TABLE `api_police_ids` MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
