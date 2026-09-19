<?php
class Security
{
  public static function generateCSRFToken()
  {
    if (empty($_SESSION['csrf_token'])) {
      $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
  }

  public static function verifyCSRFToken($token)
  {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
  }

  public static function hashPassword($password)
  {
    return password_hash($password, PASSWORD_DEFAULT);
  }

  public static function verifyPassword($password, $hash)
  {
    return password_verify($password, $hash);
  }

  // Generates a random password that satisfies validatePasswordStrength() (upper+lower+digit+symbol, 12 chars)
  public static function generateTempPassword($length = 12)
  {
    $uppers = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $lowers = 'abcdefghijkmnpqrstuvwxyz';
    $digits = '23456789';
    $symbols = '!@#$%&*?';
    $all = $uppers . $lowers . $digits . $symbols;

    $password = [
      $uppers[random_int(0, strlen($uppers) - 1)],
      $lowers[random_int(0, strlen($lowers) - 1)],
      $digits[random_int(0, strlen($digits) - 1)],
      $symbols[random_int(0, strlen($symbols) - 1)],
    ];

    for ($i = count($password); $i < $length; $i++) {
      $password[] = $all[random_int(0, strlen($all) - 1)];
    }

    // Fisher-Yates shuffle using a CSPRNG (shuffle() itself is not cryptographically secure)
    for ($i = count($password) - 1; $i > 0; $i--) {
      $j = random_int(0, $i);
      [$password[$i], $password[$j]] = [$password[$j], $password[$i]];
    }

    return implode('', $password);
  }

  public static function checkLockout($username)
  {
    return self::getLockoutTime($username) > 0;
  }

  public static function getLockoutTime($username)
  {
    $db = new Database();
    $conn = $db->getConnection();

    // Get ALL attempts within the last hour, ordered by most recent first
    $stmt = $conn->prepare("SELECT success, UNIX_TIMESTAMP(attempt_time) as ts 
                               FROM login_attempts 
                               WHERE username = ? 
                               AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                               ORDER BY attempt_time DESC");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $consecutiveFailed = 0;
    $mostRecentFailedTs = null;
    $foundSuccess = false;

    // Count consecutive failures starting from the most recent attempt
    while ($row = $result->fetch_assoc()) {
      if ($row['success'] == 0) {
        if (!$foundSuccess) {
          $consecutiveFailed++;
          if ($mostRecentFailedTs === null) {
            $mostRecentFailedTs = (int) $row['ts'];
          }
        }
      } else {
        $foundSuccess = true;
        break;
      }
    }

    $stmt->close();
    $db->close();

    // NEW: Only show lockout at threshold points (3, 6, 9)
    $lockoutDuration = 0;
    $shouldShowLockout = false;

    if ($consecutiveFailed >= 9) {
      $lockoutDuration = 60;
      $shouldShowLockout = ($consecutiveFailed == 9); // Only show on exactly 9th failure
    } elseif ($consecutiveFailed >= 6) {
      $lockoutDuration = 30;
      $shouldShowLockout = ($consecutiveFailed == 6); // Only show on exactly 6th failure
    } elseif ($consecutiveFailed >= 3) {
      $lockoutDuration = 15;
      $shouldShowLockout = ($consecutiveFailed == 3); // Only show on exactly 3rd failure
    }

    // Calculate remaining lockout time
    if ($lockoutDuration > 0 && $mostRecentFailedTs !== null && $shouldShowLockout) {
      $now = time();
      $elapsed = $now - $mostRecentFailedTs;
      $remaining = $lockoutDuration - $elapsed;

      return $remaining > 0 ? $remaining : 0;
    }

    return 0;
  }

  // Get consecutive failed attempts count
  public static function getConsecutiveFailedAttempts($username)
  {
    $db = new Database();
    $conn = $db->getConnection();

    $stmt = $conn->prepare("SELECT success 
                               FROM login_attempts 
                               WHERE username = ? 
                               AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
                               ORDER BY attempt_time DESC");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    $consecutiveFailed = 0;
    $foundSuccess = false;

    while ($row = $result->fetch_assoc()) {
      if ($row['success'] == 0) {
        if (!$foundSuccess) {
          $consecutiveFailed++;
        }
      } else {
        $foundSuccess = true;
        break;
      }
    }

    $stmt->close();
    $db->close();

    return $consecutiveFailed;
  }

  // NEW METHOD: Check if we should show lockout timer
  public static function shouldShowLockout($username)
  {
    $consecutiveFailed = self::getConsecutiveFailedAttempts($username);

    // Only show lockout at exact threshold points
    return $consecutiveFailed == 3 || $consecutiveFailed == 6 || $consecutiveFailed == 9;
  }
}
?>