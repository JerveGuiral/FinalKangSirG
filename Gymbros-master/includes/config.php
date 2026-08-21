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