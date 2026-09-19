<?php
/**
 * ActivityLogger
 * Handles logging account and system activities for audit and tracking.
 */
class ActivityLogger
{
    /**
     * Get real client IP address
     */
    public static function getClientIP()
    {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ipList[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        return substr(filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1', 0, 45);
    }

    /**
     * Log an activity record into the database
     *
     * @param string $action Short action code/name (e.g., 'LOGIN', 'LOGOUT', 'UPDATE_STATUS', 'LOG_WORKOUT')
     * @param string $details Human readable description of what happened
     * @param string $category Category (e.g., 'Authentication', 'User Management', 'Fitness Tracking', 'Class Booking', 'Security', 'Profile', 'Account')
     * @param array|null $userOverride Specific user array if not logging from current session
     * @param string $status Status of the action ('SUCCESS', 'FAILED', 'WARNING')
     * @return bool
     */
    public static function log($action, $details, $category = 'General', $userOverride = null, $status = 'SUCCESS')
    {
        try {
            $idNumber = 'SYSTEM';
            $username = 'system';
            $fullName = 'System Event';
            $role = 'user';

            if (!empty($userOverride) && is_array($userOverride)) {
                $idNumber = $userOverride['id_number'] ?? ($userOverride['user_id'] ?? 'N/A');
                $username = $userOverride['username'] ?? 'unknown';
                $role = $userOverride['role'] ?? 'user';
                
                if (!empty($userOverride['full_name'])) {
                    $fullName = $userOverride['full_name'];
                } else {
                    $fname = $userOverride['first_name'] ?? '';
                    $mname = $userOverride['middle_name'] ?? '';
                    $lname = $userOverride['last_name'] ?? '';
                    $ext = $userOverride['extension_name'] ?? '';
                    $parts = array_filter([$fname, $mname, $lname, $ext], function ($p) {
                        return !empty(trim($p));
                    });
                    $fullName = !empty($parts) ? implode(' ', $parts) : ($userOverride['username'] ?? 'User');
                }
            } elseif (isset($_SESSION['user']) && !empty($_SESSION['user'])) {
                $u = $_SESSION['user'];
                $idNumber = $u['id_number'] ?? 'N/A';
                $username = $u['username'] ?? 'unknown';
                $role = $u['role'] ?? 'user';

                $fname = $u['first_name'] ?? '';
                $mname = $u['middle_name'] ?? '';
                $lname = $u['last_name'] ?? '';
                $ext = $u['extension_name'] ?? '';
                $parts = array_filter([$fname, $mname, $lname, $ext], function ($p) {
                    return !empty(trim($p));
                });
                $fullName = !empty($parts) ? implode(' ', $parts) : ($u['username'] ?? 'User');
            }

            // Valid roles only
            if (!in_array($role, ['superadmin', 'admin', 'user'])) {
                $role = 'user';
            }

            $ipAddress = self::getClientIP();

            $db = new Database();
            $conn = $db->getConnection();

            $stmt = $conn->prepare("INSERT INTO activity_logs (id_number, username, full_name, role, action, action_category, details, ip_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("sssssssss", $idNumber, $username, $fullName, $role, $action, $category, $details, $ipAddress, $status);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
            return false;
        } catch (Exception $e) {
            error_log("ActivityLogger Error: " . $e->getMessage());
            return false;
        }
    }
}
?>
