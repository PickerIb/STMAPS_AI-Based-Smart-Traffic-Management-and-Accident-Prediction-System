-- Driver dashboard features: travel history and profile picture
USE `smart_traffic_db`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `profile_picture` varchar(255) DEFAULT NULL AFTER `police_id`;

CREATE TABLE IF NOT EXISTS `route_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `origin_label` varchar(150) DEFAULT NULL,
  `origin_lat` decimal(10,7) NOT NULL,
  `origin_lng` decimal(10,7) NOT NULL,
  `destination_label` varchar(150) NOT NULL,
  `destination_lat` decimal(10,7) NOT NULL,
  `destination_lng` decimal(10,7) NOT NULL,
  `route_name` varchar(100) DEFAULT 'primary',
  `distance_km` decimal(8,2) DEFAULT NULL,
  `estimated_minutes` int(10) UNSIGNED DEFAULT NULL,
  `congestion_level` enum('low','moderate','high','severe') DEFAULT 'moderate',
  `segment_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`segment_ids`)),
  `route_summary` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`route_summary`)),
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_route_requests_user` (`user_id`),
  KEY `idx_route_requests_created` (`created_at`),
  CONSTRAINT `fk_route_requests_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
