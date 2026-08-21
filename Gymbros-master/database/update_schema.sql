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
