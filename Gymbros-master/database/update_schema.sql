-- GymBros Database Schema Update for Super Admin & Admin Roles

USE `gym_Bros`;

-- Add role, status, and privileges columns to users table if not existing
SET @dbname = DATABASE();
SET @tablename = "users";

-- Add 'role' column if missing
SET @columnname = "role";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `users` ADD COLUMN `role` ENUM('superadmin', 'admin', 'user') NOT NULL DEFAULT 'user' AFTER `zip_code`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add 'status' column if missing
SET @columnname = "status";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `users` ADD COLUMN `status` ENUM('pending', 'approved', 'blocked') NOT NULL DEFAULT 'approved' AFTER `role`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add 'privileges' column if missing
SET @columnname = "privileges";
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  "SELECT 1",
  "ALTER TABLE `users` ADD COLUMN `privileges` TEXT NULL AFTER `status`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create delete_requests table if not existing
CREATE TABLE IF NOT EXISTS `delete_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `requested_by` varchar(20) NOT NULL,
  `target_user_id` varchar(20) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_by` varchar(20) DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `requested_by` (`requested_by`),
  KEY `target_user_id` (`target_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insert default Super Admin user if not exists (username: superadmin, password: SuperAdmin123!)
INSERT INTO `users` (
  `id_number`, `username`, `password_hash`, `first_name`, `middle_name`, `last_name`,
  `extension_name`, `birthdate`, `age`, `email`, `sex`, `purok_street`, `barangay`,
  `city_municipality`, `province`, `country`, `zip_code`, `role`, `status`, `privileges`
) 
SELECT 
  'SA-2025-001', 'superadmin', '$2y$10$f0I.B435m65VZbAt.3thce7NiR62w7rzF24UkE7cpBp0XM8UKWoEi', 
  'Super', 'Admin', 'User', '', '1990-01-01', 35, 'superadmin@gymbros.com', 'male',
  'Admin HQ', 'Central', 'Bayugan', 'Agusan del Sur', 'Philippines', '8513',
  'superadmin', 'approved', '{"can_approve_users":true,"can_manage_roles":true,"can_give_privileges":true,"can_update_info":true,"can_delete_users":true}'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'superadmin' OR `id_number` = 'SA-2025-001');

-- Create login_logs table to record login/logout activity
CREATE TABLE IF NOT EXISTS `login_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_number` varchar(50) NOT NULL,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','user') NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `time_in` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `time_out` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_id_number` (`id_number`),
  KEY `idx_role` (`role`),
  KEY `idx_time_in` (`time_in`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create otp_codes table for OTP validation & password reset
CREATE TABLE IF NOT EXISTS `otp_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `purpose` enum('forgot_password','account_verification','login_auth') NOT NULL DEFAULT 'forgot_password',
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `attempts` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email` (`email`),
  KEY `idx_otp` (`otp_code`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- User profile columns
SET @columnname = "membership_tier";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE `users` ADD COLUMN `membership_tier` ENUM('silver', 'gold', 'platinum') NOT NULL DEFAULT 'gold' AFTER `zip_code`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "phone_number";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE `users` ADD COLUMN `phone_number` VARCHAR(30) NULL AFTER `email`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "bio";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE `users` ADD COLUMN `bio` TEXT NULL AFTER `privileges`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

SET @columnname = "fitness_goal";
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  "SELECT 1",
  "ALTER TABLE `users` ADD COLUMN `fitness_goal` VARCHAR(100) NOT NULL DEFAULT 'Muscle Building & Fitness' AFTER `bio`"
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create user_workouts table
CREATE TABLE IF NOT EXISTS `user_workouts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `workout_name` varchar(150) NOT NULL,
  `muscle_group` varchar(50) NOT NULL,
  `duration_minutes` int(11) NOT NULL DEFAULT 30,
  `calories_burned` int(11) NOT NULL DEFAULT 150,
  `sets_count` int(11) NOT NULL DEFAULT 3,
  `reps_count` int(11) NOT NULL DEFAULT 10,
  `weight_lifted` decimal(6,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `workout_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_workout` (`user_id`, `workout_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create gym_classes table
CREATE TABLE IF NOT EXISTS `gym_classes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `class_name` varchar(100) NOT NULL,
  `instructor` varchar(100) NOT NULL,
  `category` varchar(50) NOT NULL,
  `schedule_day` varchar(20) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `max_capacity` int(11) NOT NULL DEFAULT 20,
  `room` varchar(50) NOT NULL DEFAULT 'Main Studio',
  `difficulty` enum('Beginner', 'Intermediate', 'Advanced', 'All Levels') NOT NULL DEFAULT 'All Levels',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed default gym classes if not exists
INSERT INTO `gym_classes` (`id`, `class_name`, `instructor`, `category`, `schedule_day`, `start_time`, `end_time`, `max_capacity`, `room`, `difficulty`)
SELECT 1, 'CrossFit Intensity & Conditioning', 'Coach Marcus Vance', 'CrossFit', 'Monday', '07:00:00', '08:00:00', 15, 'Functional Arena', 'Advanced'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `gym_classes` WHERE `id` = 1);

INSERT INTO `gym_classes` (`id`, `class_name`, `instructor`, `category`, `schedule_day`, `start_time`, `end_time`, `max_capacity`, `room`, `difficulty`)
SELECT 2, 'Heavy Strength & Hypertrophy', 'Coach Arnold Stone', 'Strength', 'Tuesday', '17:30:00', '19:00:00', 12, 'Heavy Iron Room', 'Intermediate'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `gym_classes` WHERE `id` = 2);

INSERT INTO `gym_classes` (`id`, `class_name`, `instructor`, `category`, `schedule_day`, `start_time`, `end_time`, `max_capacity`, `room`, `difficulty`)
SELECT 3, 'HIIT Metabolic Burn', 'Coach Sarah Connor', 'Cardio / HIIT', 'Wednesday', '06:30:00', '07:30:00', 20, 'Cardio Deck', 'All Levels'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `gym_classes` WHERE `id` = 3);

INSERT INTO `gym_classes` (`id`, `class_name`, `instructor`, `category`, `schedule_day`, `start_time`, `end_time`, `max_capacity`, `room`, `difficulty`)
SELECT 4, 'Muay Thai & Boxing Fundamentals', 'Coach Dave Briggs', 'Combat Sports', 'Thursday', '18:00:00', '19:30:00', 16, 'Combat Zone', 'All Levels'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `gym_classes` WHERE `id` = 4);

INSERT INTO `gym_classes` (`id`, `class_name`, `instructor`, `category`, `schedule_day`, `start_time`, `end_time`, `max_capacity`, `room`, `difficulty`)
SELECT 5, 'Power Yoga & Mobility Flow', 'Coach Elena Rostova', 'Mobility', 'Friday', '08:00:00', '09:00:00', 25, 'Zen Studio', 'Beginner'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `gym_classes` WHERE `id` = 5);

INSERT INTO `gym_classes` (`id`, `class_name`, `instructor`, `category`, `schedule_day`, `start_time`, `end_time`, `max_capacity`, `room`, `difficulty`)
SELECT 6, 'Weekend Warrior Full Body Blitz', 'Coach Marcus Vance', 'Bootcamp', 'Saturday', '09:00:00', '10:30:00', 20, 'Outdoor Rig', 'All Levels'
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `gym_classes` WHERE `id` = 6);

-- Create class_bookings table
CREATE TABLE IF NOT EXISTS `class_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `class_id` int(11) NOT NULL,
  `booking_date` date NOT NULL,
  `status` enum('booked','cancelled','attended') NOT NULL DEFAULT 'booked',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_booking_user` (`user_id`),
  KEY `idx_booking_class` (`class_id`),
  KEY `idx_booking_date` (`booking_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Create user_body_metrics table
CREATE TABLE IF NOT EXISTS `user_body_metrics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` varchar(50) NOT NULL,
  `weight_kg` decimal(5,2) NOT NULL,
  `height_cm` decimal(5,2) NOT NULL,
  `target_weight_kg` decimal(5,2) DEFAULT NULL,
  `fitness_goal` varchar(100) DEFAULT 'General Fitness',
  `bmi` decimal(5,2) NOT NULL,
  `body_fat_percentage` decimal(4,1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_metrics_user` (`user_id`, `recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
