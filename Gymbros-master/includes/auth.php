<?php
class Auth
{
  public static function isLoggedIn()
  {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
  }

  public static function authenticate($username, $password)
  {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $user = $result->fetch_assoc();
      if (password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']); // Remove password from session
        return $user;
      }
    }
    return false;
  }

  public static function verifySecurityAnswers($user_id, $answers)
  {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT answer1_hash, answer2_hash, answer3_hash FROM security_questions WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $questionData = $result->fetch_assoc();
      $correct = 0;

      for ($i = 1; $i <= 3; $i++) {
        if (password_verify($answers[$i - 1], $questionData["answer{$i}_hash"])) {
          $correct++;
        }
      }

      return $correct >= 2; // Require at least 2 correct answers
    }
    return false;
  }

  public static function isSuperAdmin()
  {
    return self::isLoggedIn() && (($_SESSION['user']['role'] ?? 'user') === 'superadmin');
  }

  public static function isAdmin()
  {
    return self::isLoggedIn() && (in_array($_SESSION['user']['role'] ?? 'user', ['superadmin', 'admin']));
  }

  public static function hasRole($role)
  {
    return self::isLoggedIn() && (($_SESSION['user']['role'] ?? 'user') === $role);
  }

  public static function hasPrivilege($privilegeKey)
  {
    if (!self::isLoggedIn()) {
      return false;
    }
    // Super admin has all privileges by default
    if (self::isSuperAdmin()) {
      return true;
    }

    // Fetch latest privileges directly from DB for immediate synchronization
    $userId = $_SESSION['user']['id_number'] ?? '';
    if (!empty($userId)) {
      $db = new Database();
      $conn = $db->getConnection();
      $stmt = $conn->prepare("SELECT privileges FROM users WHERE id_number = ? LIMIT 1");
      if ($stmt) {
        $stmt->bind_param("s", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
          $row = $res->fetch_assoc();
          $_SESSION['user']['privileges'] = $row['privileges'];
        }
        $stmt->close();
      }
    }

    $privsRaw = $_SESSION['user']['privileges'] ?? '';
    if (empty($privsRaw)) {
      return false;
    }

    $privs = is_array($privsRaw) ? $privsRaw : json_decode($privsRaw, true);
    if (!is_array($privs)) {
      return false;
    }

    // Handle alias for logs privilege
    if ($privilegeKey === 'can_view_reports' || $privilegeKey === 'can_view_logs') {
      return !empty($privs['can_view_reports']) || !empty($privs['can_view_logs']);
    }

    return !empty($privs[$privilegeKey]);
  }

  public static function getAllPrivileges()
  {
    return [
      'user_management' => [
        'title' => 'User & Account Access Control',
        'icon' => 'fas fa-users-cog',
        'items' => [
          'can_approve_users' => [
            'title' => 'Approve & Activate Registrations',
            'desc' => 'Accept pending user registrations and unblock accounts',
            'icon' => 'fas fa-user-check'
          ],
          'can_block_users' => [
            'title' => 'Block & Suspend Accounts',
            'desc' => 'Restrict or suspend active user and administrator accounts',
            'icon' => 'fas fa-user-slash'
          ],
          'can_update_info' => [
            'title' => 'Update Account Information',
            'desc' => 'Edit profile details, addresses, birthdays, and credentials',
            'icon' => 'fas fa-user-edit'
          ],
          'can_manage_roles' => [
            'title' => 'Role Assignment & Control',
            'desc' => 'Change member roles between Standard User and Administrator',
            'icon' => 'fas fa-user-tag'
          ],
          'can_create_accounts' => [
            'title' => 'Provision New Accounts',
            'desc' => 'Directly create new Administrator and Member accounts',
            'icon' => 'fas fa-user-plus'
          ]
        ]
      ],
      'superadmin_authority' => [
        'title' => 'Super Administrator Authority',
        'icon' => 'fas fa-crown',
        'items' => [
          'can_delete_users' => [
            'title' => 'Direct Account Deletion',
            'desc' => 'Permanently delete accounts immediately without request review',
            'icon' => 'fas fa-trash-alt'
          ],
          'can_manage_requests' => [
            'title' => 'Review Requests Queue',
            'desc' => 'Review, approve, or reject account deletion requests',
            'icon' => 'fas fa-clipboard-check'
          ],
          'can_give_privileges' => [
            'title' => 'Manage Privileges & Permissions',
            'desc' => 'Assign, modify, and customize privileges for other accounts',
            'icon' => 'fas fa-key'
          ]
        ]
      ],
      'logs_and_security' => [
        'title' => 'System Logs & Audit Trails',
        'icon' => 'fas fa-shield-alt',
        'items' => [
          'can_view_reports' => [
            'title' => 'View System Logs & Activity',
            'desc' => 'Access system activity logs, login trails, and security events',
            'icon' => 'fas fa-history'
          ],
          'can_export_logs' => [
            'title' => 'Export Audit Logs & Reports',
            'desc' => 'Export and download audit trails and system activity logs',
            'icon' => 'fas fa-file-export'
          ]
        ]
      ],
      'gym_operations' => [
        'title' => 'Gym Facility & Class Operations',
        'icon' => 'fas fa-dumbbell',
        'items' => [
          'can_manage_classes' => [
            'title' => 'Manage Gym Classes & Schedules',
            'desc' => 'Create, edit, and organize fitness classes, coaches, and times',
            'icon' => 'fas fa-calendar-alt'
          ],
          'can_manage_bookings' => [
            'title' => 'Manage Member Bookings',
            'desc' => 'Monitor, approve, and oversee member class reservations',
            'icon' => 'fas fa-clipboard-list'
          ],
          'can_manage_metrics' => [
            'title' => 'Oversee Fitness Metrics',
            'desc' => 'Review member workout logs, body metrics, and BMI statistics',
            'icon' => 'fas fa-heartbeat'
          ]
        ]
      ]
    ];
  }

  public static function getAllPrivilegeKeys()
  {
    $keys = [];
    foreach (self::getAllPrivileges() as $category) {
      foreach ($category['items'] as $key => $item) {
        $keys[] = $key;
      }
    }
    return $keys;
  }

  public static function getDefaultAdminPrivileges()
  {
    return [
      'can_approve_users' => true,
      'can_block_users' => true,
      'can_update_info' => true,
      'can_manage_roles' => true,
      'can_create_accounts' => true,
      'can_delete_users' => false,
      'can_manage_requests' => true,
      'can_give_privileges' => false,
      'can_view_reports' => true,
      'can_export_logs' => true,
      'can_manage_classes' => true,
      'can_manage_bookings' => true,
      'can_manage_metrics' => true
    ];
  }

  public static function getDefaultPrivilegesForRole($role)
  {
    if ($role === 'superadmin') {
      $allKeys = self::getAllPrivilegeKeys();
      return array_fill_keys($allKeys, true);
    }
    if ($role === 'admin') {
      return self::getDefaultAdminPrivileges();
    }
    return [];
  }

  public static function getUserByUsername($username)
  {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      return $result->fetch_assoc();
    }
    return false;
  }
}
?>