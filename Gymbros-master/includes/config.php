<?php
session_start();
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'localhost');
define('DB_USER', 'root'); // Change if different
define('DB_PASS', ''); // Change if you have a password
define('DB_NAME', 'gym_Bros');
define('MAX_LOGIN_ATTEMPTS', 3);
define('LOCKOUT_TIMES', [15, 30, 60]);

// SMTP & Email Configuration
// To use Gmail SMTP: Host='smtp.gmail.com', Port=587, Secure='tls', User=your_gmail@gmail.com, Pass=your_16_char_app_password
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // 'tls' (port 587) or 'ssl' (port 465)
define('SMTP_USER', 'jervecute123@gmail.com'); // Enter your sender email (e.g., your-email@gmail.com)
define('SMTP_PASS', 'cgbn jrry fdfs hqtb'); // Enter your SMTP / Google App Password
define('SMTP_FROM_EMAIL', 'jervecute123@gmail.com');
define('SMTP_FROM_NAME', 'GymBros Security');


class Database
{
  private $connection;

  public function __construct()
  {
    $this->connection = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($this->connection->connect_error) {
      error_log("Database connection failed: " . $this->connection->connect_error);
      die("Database connection failed. Please check your configuration.");
    }

    // Set charset to UTF-8
    $this->connection->set_charset("utf8mb4");

    // Auto-migrate schema if needed
    $this->ensureSchemaUpToDate();
  }

  private function ensureSchemaUpToDate()
  {
    // Check if role column exists in users table
    $result = $this->connection->query("SHOW COLUMNS FROM `users` LIKE 'role'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("ALTER TABLE `users` ADD COLUMN `role` ENUM('superadmin', 'admin', 'user') NOT NULL DEFAULT 'user' AFTER `zip_code`");
    }

    // Check if status column exists in users table
    $result = $this->connection->query("SHOW COLUMNS FROM `users` LIKE 'status'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("ALTER TABLE `users` ADD COLUMN `status` ENUM('pending', 'approved', 'blocked') NOT NULL DEFAULT 'approved' AFTER `role`");
    }

    // Check if privileges column exists in users table
    $result = $this->connection->query("SHOW COLUMNS FROM `users` LIKE 'privileges'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("ALTER TABLE `users` ADD COLUMN `privileges` TEXT NULL AFTER `status`");
    }

    // Check if delete_requests table exists
    $result = $this->connection->query("SHOW TABLES LIKE 'delete_requests'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("CREATE TABLE `delete_requests` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    // Check if login_logs table exists
    $result = $this->connection->query("SHOW TABLES LIKE 'login_logs'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("CREATE TABLE `login_logs` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `id_number` varchar(50) NOT NULL,
        `username` varchar(50) NOT NULL,
        `full_name` varchar(255) NOT NULL,
        `role` enum('superadmin','admin','user') NOT NULL,
        `ip_address` varchar(45) DEFAULT NULL,
        `time_in` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `time_out` datetime DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_id_number` (`id_number`),
        KEY `idx_role` (`role`),
        KEY `idx_time_in` (`time_in`),
        KEY `idx_created_at` (`created_at`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    // Check if otp_codes table exists
    $result = $this->connection->query("SHOW TABLES LIKE 'otp_codes'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("CREATE TABLE `otp_codes` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` varchar(50) DEFAULT NULL,
        `email` varchar(100) NOT NULL,
        `otp_code` varchar(10) NOT NULL,
        `purpose` enum('forgot_password','account_verification','login_auth') NOT NULL DEFAULT 'forgot_password',
        `expires_at` datetime NOT NULL,
        `is_used` tinyint(1) NOT NULL DEFAULT 0,
        `attempts` int(11) NOT NULL DEFAULT 0,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `idx_email` (`email`),
        KEY `idx_otp` (`otp_code`),
        KEY `idx_expires` (`expires_at`)
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    // Check & Add membership_tier, phone_number, bio, fitness_goal to users table
    $colCheck = $this->connection->query("SHOW COLUMNS FROM `users` LIKE 'membership_tier'");
    if ($colCheck && $colCheck->num_rows === 0) {
      $this->connection->query("ALTER TABLE `users` ADD COLUMN `membership_tier` ENUM('silver', 'gold', 'platinum') NOT NULL DEFAULT 'gold' AFTER `zip_code`");
    }
    $colCheck = $this->connection->query("SHOW COLUMNS FROM `users` LIKE 'phone_number'");
    if ($colCheck && $colCheck->num_rows === 0) {
      $this->connection->query("ALTER TABLE `users` ADD COLUMN `phone_number` VARCHAR(30) NULL AFTER `email`");
    }
    $colCheck = $this->connection->query("SHOW COLUMNS FROM `users` LIKE 'bio'");
    if ($colCheck && $colCheck->num_rows === 0) {
      $this->connection->query("ALTER TABLE `users` ADD COLUMN `bio` TEXT NULL AFTER `privileges`");
    }
    $colCheck = $this->connection->query("SHOW COLUMNS FROM `users` LIKE 'fitness_goal'");
    if ($colCheck && $colCheck->num_rows === 0) {
      $this->connection->query("ALTER TABLE `users` ADD COLUMN `fitness_goal` VARCHAR(100) NOT NULL DEFAULT 'Muscle Building & Fitness' AFTER `bio`");
    }

    // Check if user_workouts table exists
    $result = $this->connection->query("SHOW TABLES LIKE 'user_workouts'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("CREATE TABLE `user_workouts` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    // Check if gym_classes table exists
    $result = $this->connection->query("SHOW TABLES LIKE 'gym_classes'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("CREATE TABLE `gym_classes` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

      // Seed initial gym classes
      $this->connection->query("INSERT INTO `gym_classes` (`class_name`, `instructor`, `category`, `schedule_day`, `start_time`, `end_time`, `max_capacity`, `room`, `difficulty`) VALUES
        ('CrossFit Intensity & Conditioning', 'Coach Marcus Vance', 'CrossFit', 'Monday', '07:00:00', '08:00:00', 15, 'Functional Arena', 'Advanced'),
        ('Heavy Strength & Hypertrophy', 'Coach Arnold Stone', 'Strength', 'Tuesday', '17:30:00', '19:00:00', 12, 'Heavy Iron Room', 'Intermediate'),
        ('HIIT Metabolic Burn', 'Coach Sarah Connor', 'Cardio / HIIT', 'Wednesday', '06:30:00', '07:30:00', 20, 'Cardio Deck', 'All Levels'),
        ('Muay Thai & Boxing Fundamentals', 'Coach Dave Briggs', 'Combat Sports', 'Thursday', '18:00:00', '19:30:00', 16, 'Combat Zone', 'All Levels'),
        ('Power Yoga & Mobility Flow', 'Coach Elena Rostova', 'Mobility', 'Friday', '08:00:00', '09:00:00', 25, 'Zen Studio', 'Beginner'),
        ('Weekend Warrior Full Body Blitz', 'Coach Marcus Vance', 'Bootcamp', 'Saturday', '09:00:00', '10:30:00', 20, 'Outdoor Rig', 'All Levels');");
    }

    // Check if class_bookings table exists
    $result = $this->connection->query("SHOW TABLES LIKE 'class_bookings'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("CREATE TABLE `class_bookings` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    // Check if user_body_metrics table exists
    $result = $this->connection->query("SHOW TABLES LIKE 'user_body_metrics'");
    if ($result && $result->num_rows === 0) {
      $this->connection->query("CREATE TABLE `user_body_metrics` (
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
      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    // Ensure default Super Admin exists
    $result = $this->connection->query("SELECT id_number FROM `users` WHERE `username` = 'superadmin'");
    if ($result && $result->num_rows === 0) {
      $passHash = password_hash('SuperAdmin123!', PASSWORD_DEFAULT);
      $privs = json_encode([
        'can_approve_users' => true,
        'can_manage_roles' => true,
        'can_give_privileges' => true,
        'can_update_info' => true,
        'can_delete_users' => true
      ]);
      $stmt = $this->connection->prepare("INSERT INTO `users` (
        `id_number`, `username`, `password_hash`, `first_name`, `middle_name`, `last_name`,
        `extension_name`, `birthdate`, `age`, `email`, `sex`, `purok_street`, `barangay`,
        `city_municipality`, `province`, `country`, `zip_code`, `role`, `status`, `privileges`
      ) VALUES (
        'SA-2025-001', 'superadmin', ?, 'Super', 'Admin', 'User', '', '1990-01-01', 35, 
        'superadmin@gymbros.com', 'male', 'Admin HQ', 'Central', 'Bayugan', 'Agusan del Sur', 
        'Philippines', '8513', 'superadmin', 'approved', ?
      )");
      if ($stmt) {
        $stmt->bind_param("ss", $passHash, $privs);
        $stmt->execute();
        $stmt->close();
      }
    }
  }

  public function getConnection()
  {
    return $this->connection;
  }

  public function sanitize($data)
  {
    if (empty($data))
      return $data;
    return $this->connection->real_escape_string(htmlspecialchars(trim($data)));
  }

  public function close()
  {
    if ($this->connection) {
      $this->connection->close();
    }
  }
}
?>