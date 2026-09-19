<?php
require_once '../includes/config.php';

// Record logout time in login_logs
$db = new Database();
$conn = $db->getConnection();

if (isset($_SESSION['login_log_id'])) {
    $logId = (int)$_SESSION['login_log_id'];
    $stmt = $conn->prepare("UPDATE login_logs SET time_out = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $logId);
        $stmt->execute();
        $stmt->close();
    }
} elseif (isset($_SESSION['user']['id_number'])) {
    $userId = $_SESSION['user']['id_number'];
    $stmt = $conn->prepare("UPDATE login_logs SET time_out = NOW() WHERE id_number = ? AND time_out IS NULL ORDER BY time_in DESC LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $stmt->close();
    }
}

if (isset($_SESSION['user'])) {
    $u = $_SESSION['user'];
    ActivityLogger::log('LOGOUT', "User @{$u['username']} signed out.", 'Authentication', $u);
}

session_unset();
session_destroy();

header("Location: login.php");
exit();
?>