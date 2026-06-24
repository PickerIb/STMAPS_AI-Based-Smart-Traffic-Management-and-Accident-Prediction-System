-- Track which driver notifications each user has viewed
CREATE TABLE IF NOT EXISTS `driver_notification_reads` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NOT NULL,
  `notification_key` varchar(64) NOT NULL,
  `read_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_driver_notification_read` (`user_id`, `notification_key`),
  KEY `idx_driver_notification_reads_user` (`user_id`),
  CONSTRAINT `fk_driver_notification_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
