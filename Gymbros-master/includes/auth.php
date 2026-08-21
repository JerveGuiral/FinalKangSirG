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

    $privsRaw = $_SESSION['user']['privileges'] ?? '';
    if (empty($privsRaw)) {
      return false;
    }

    $privs = is_array($privsRaw) ? $privsRaw : json_decode($privsRaw, true);
    return !empty($privs[$privilegeKey]);
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