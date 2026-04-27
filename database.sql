-- AI-Based Smart Traffic Management and Accident Prediction System
-- Database schema (MySQL/MariaDB)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Create database
CREATE DATABASE IF NOT EXISTS `smart_traffic_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `smart_traffic_db`;

-- ----------------------------
-- Users (authentication)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','authority','viewer') NOT NULL DEFAULT 'authority',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_username` (`username`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Road segments / zones
-- ----------------------------
CREATE TABLE IF NOT EXISTS `road_segments` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `zone` VARCHAR(120) NOT NULL,
  `start_lat` DECIMAL(10,7) NULL,
  `start_lng` DECIMAL(10,7) NULL,
  `end_lat` DECIMAL(10,7) NULL,
  `end_lng` DECIMAL(10,7) NULL,
  `speed_limit_kmh` SMALLINT UNSIGNED NOT NULL DEFAULT 50,
  `lanes` TINYINT UNSIGNED NOT NULL DEFAULT 2,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_road_segments_zone` (`zone`),
  KEY `idx_road_segments_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Traffic readings (real-time + historical)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `traffic_readings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` INT UNSIGNED NOT NULL,
  `reading_time` DATETIME NOT NULL,
  `vehicle_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `avg_speed_kmh` DECIMAL(6,2) NOT NULL DEFAULT 0,
  `density_veh_per_km` DECIMAL(8,2) NOT NULL DEFAULT 0,
  `occupancy_pct` DECIMAL(5,2) NULL,
  `weather` ENUM('clear','rain','fog','storm','snow','unknown') NOT NULL DEFAULT 'unknown',
  `visibility_m` INT UNSIGNED NULL,
  `temperature_c` DECIMAL(5,2) NULL,
  `source` VARCHAR(80) NULL, -- sensor/camera/api/manual
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_traffic_readings_segment_time` (`segment_id`, `reading_time`),
  CONSTRAINT `fk_traffic_readings_segment` FOREIGN KEY (`segment_id`) REFERENCES `road_segments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Accidents (ground truth)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `accidents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` INT UNSIGNED NOT NULL,
  `accident_time` DATETIME NOT NULL,
  `severity` ENUM('minor','moderate','severe','fatal') NOT NULL DEFAULT 'minor',
  `cause` VARCHAR(190) NULL,
  `vehicles_involved` TINYINT UNSIGNED NULL,
  `injuries` SMALLINT UNSIGNED NULL,
  `fatalities` SMALLINT UNSIGNED NULL,
  `notes` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_accidents_segment_time` (`segment_id`, `accident_time`),
  CONSTRAINT `fk_accidents_segment` FOREIGN KEY (`segment_id`) REFERENCES `road_segments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- AI models (metadata)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `ai_models` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `task` ENUM('congestion','accident_risk') NOT NULL,
  `algorithm` VARCHAR(100) NOT NULL,
  `params_json` JSON NULL,
  `metrics_json` JSON NULL,
  `artifact_path` VARCHAR(255) NULL,
  `is_production` TINYINT(1) NOT NULL DEFAULT 0,
  `version` VARCHAR(40) NOT NULL DEFAULT '1.0.0',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_models_task_prod` (`task`, `is_production`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Predictions (congestion + accident probability)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `ai_predictions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` INT UNSIGNED NOT NULL,
  `model_id` INT UNSIGNED NULL,
  `prediction_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `input_json` JSON NOT NULL,
  `congestion_level` ENUM('low','moderate','high','severe') NOT NULL,
  `accident_probability` DECIMAL(5,4) NOT NULL DEFAULT 0, -- 0.0000 - 1.0000
  `confidence` DECIMAL(5,4) NOT NULL DEFAULT 0,
  `explanations_json` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_predictions_segment_time` (`segment_id`, `prediction_time`),
  KEY `idx_ai_predictions_model` (`model_id`),
  CONSTRAINT `fk_ai_predictions_segment` FOREIGN KEY (`segment_id`) REFERENCES `road_segments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ai_predictions_model` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Alerts (for authorities / future driver integration)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `alerts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` INT UNSIGNED NOT NULL,
  `alert_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` ENUM('congestion','accident_risk','incident','system') NOT NULL,
  `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
  `message` VARCHAR(255) NOT NULL,
  `is_acknowledged` TINYINT(1) NOT NULL DEFAULT 0,
  `acknowledged_by` INT UNSIGNED NULL,
  `acknowledged_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alerts_segment_time` (`segment_id`, `alert_time`),
  KEY `idx_alerts_ack` (`is_acknowledged`),
  CONSTRAINT `fk_alerts_segment` FOREIGN KEY (`segment_id`) REFERENCES `road_segments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alerts_ack_user` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Audit log (activity feed)
-- ----------------------------
CREATE TABLE IF NOT EXISTS `audit_log` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(80) NOT NULL,
  `table_name` VARCHAR(80) NULL,
  `record_id` BIGINT UNSIGNED NULL,
  `new_values` JSON NULL,
  `old_values` JSON NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_log_time` (`created_at`),
  KEY `idx_audit_log_user` (`user_id`),
  CONSTRAINT `fk_audit_log_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------
-- Seed sample data (minimal)
-- ----------------------------
INSERT INTO `road_segments` (`name`, `zone`, `start_lat`, `start_lng`, `end_lat`, `end_lng`, `speed_limit_kmh`, `lanes`)
VALUES
  ('Central Ave - Northbound', 'CBD', -1.286389, 36.817223, -1.280000, 36.820000, 50, 3),
  ('Ring Road - East', 'Industrial', -1.300000, 36.830000, -1.310000, 36.850000, 60, 2)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

INSERT INTO `ai_models` (`name`, `task`, `algorithm`, `metrics_json`, `is_production`, `version`)
VALUES
  ('Baseline Congestion Model', 'congestion', 'HeuristicScoring', JSON_OBJECT('note','rule-based baseline'), 1, '1.0.0'),
  ('Baseline Accident Risk Model', 'accident_risk', 'HeuristicScoring', JSON_OBJECT('note','rule-based baseline'), 1, '1.0.0');

