<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/auth.php';
require_once '../includes/validation.php';
require_once '../includes/otp.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
  header("Location: dashboard.php");
  exit();
}

$errors = [];
$successMessage = '';
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
if ($step < 1 || $step > 3) $step = 1;

$db = new Database();
$conn = $db->getConnection();

// --- Handle Step Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // STEP 1: Find Account & Generate OTP
  if ($action === 'request_otp' || $step === 1) {
    $identifier = trim($db->sanitize($_POST['identifier'] ?? ''));

    if (empty($identifier)) {
      $errors[] = "Please enter your registered email address, username, or Employee ID.";
    } else {
      // Find user across all roles (superadmin, admin, user)
      $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? OR username = ? OR id_number = ? LIMIT 1");
      $stmt->bind_param("sss", $identifier, $identifier, $identifier);
      $stmt->execute();
      $res = $stmt->get_result();

      if ($res->num_rows === 1) {
        $foundUser = $res->fetch_assoc();
        $stmt->close();

        if (($foundUser['status'] ?? 'approved') === 'blocked') {
          $errors[] = "This account has been blocked by an administrator. Please contact support.";
        } else {
          // Generate 6-digit OTP
          $otpCode = OtpService::generateOTP($foundUser['id_number'], $foundUser['email'], 'forgot_password', 10);

          if ($otpCode) {
            // Send email
            $recipientName = trim($foundUser['first_name'] . ' ' . $foundUser['last_name']);
            OtpService::sendEmailOTP($foundUser['email'], $otpCode, $recipientName, 'forgot_password');

            // Save reset state in session
            $_SESSION['otp_reset_user'] = [
              'id_number' => $foundUser['id_number'],
              'email' => $foundUser['email'],
              'username' => $foundUser['username'],
              'first_name' => $foundUser['first_name'],
              'role' => $foundUser['role'] ?? 'user'
            ];
            $_SESSION['otp_demo_display'] = $otpCode; // For local/XAMPP convenience

            $step = 2;
          } else {
            $errors[] = "Failed to generate One-Time PIN. Please try again.";
          }
        }
      } else {
        $stmt->close();
        $errors[] = "No registered account found matching that email, username, or ID number.";
      }
    }
  }

  // STEP 2: Verify OTP
  elseif ($action === 'verify_otp' || $step === 2) {
    if (!isset($_SESSION['otp_reset_user'])) {
      $errors[] = "Session expired. Please start over.";
      $step = 1;
    } else {
      $otpEntered = trim($_POST['otp_code'] ?? '');

      if (empty($otpEntered) || strlen($otpEntered) !== 6 || !ctype_digit($otpEntered)) {
        $errors[] = "Please enter the valid 6-digit numeric One-Time PIN.";
      } else {
        $verifyRes = OtpService::verifyOTP($_SESSION['otp_reset_user']['email'], $otpEntered, 'forgot_password');

        if ($verifyRes['success']) {
          $_SESSION['otp_verified_user_id'] = $_SESSION['otp_reset_user']['id_number'];
          unset($_SESSION['otp_demo_display']);
          $step = 3;
        } else {
          $errors[] = $verifyRes['message'];
        }
      }
    }
  }

  // RESEND OTP ACTION
  elseif ($action === 'resend_otp') {
    if (isset($_SESSION['otp_reset_user'])) {
      $foundUser = $_SESSION['otp_reset_user'];
      $otpCode = OtpService::generateOTP($foundUser['id_number'], $foundUser['email'], 'forgot_password', 10);

      if ($otpCode) {
        $recipientName = trim($foundUser['first_name']);
        OtpService::sendEmailOTP($foundUser['email'], $otpCode, $recipientName, 'forgot_password');
        $_SESSION['otp_demo_display'] = $otpCode;
        $successMessage = "A new 6-digit One-Time PIN has been sent to your registered email.";
        $step = 2;
      } else {
        $errors[] = "Failed to resend OTP. Please try again.";
      }
    } else {
      $step = 1;
    }
  }

  // STEP 3: Reset Password
  elseif ($action === 'reset_password' || $step === 3) {
    if (!isset($_SESSION['otp_verified_user_id'])) {
      $errors[] = "Unauthorized or expired verification session. Please start over.";
      $step = 1;
    } else {
      $newPassword = $_POST['new_password'] ?? '';
      $confirmPassword = $_POST['confirm_password'] ?? '';

      if (empty($newPassword) || empty($confirmPassword)) {
        $errors[] = "Please fill in all password fields.";
      } elseif ($newPassword !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
      } else {
        // Password strength validation
        $strength = Validation::validatePasswordStrength($newPassword);
        if ($strength['strength'] === 'weak') {
          $errors[] = "Password is too weak. " . implode(', ', $strength['feedback']);
        } else {
          $targetUserId = $_SESSION['otp_verified_user_id'];
          $passwordHash = Security::hashPassword($newPassword);

          $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id_number = ?");
          $stmt->bind_param("ss", $passwordHash, $targetUserId);

          if ($stmt->execute()) {
            $stmt->close();
            // Clear session flags
            unset($_SESSION['otp_reset_user']);
            unset($_SESSION['otp_verified_user_id']);
            unset($_SESSION['otp_demo_display']);

            $_SESSION['success_message'] = "Your password has been successfully reset! You can now log in with your new password.";
            header("Location: login.php");
            exit();
          } else {
            $stmt->close();
            $errors[] = "Database update failed. Please try again.";
          }
        }
      }
    }
  }
}

// Mask email for display in Step 2 (e.g. s***@gymbros.com)
$maskedEmail = '';
if (isset($_SESSION['otp_reset_user']['email'])) {
  $rawEmail = $_SESSION['otp_reset_user']['email'];
  $parts = explode('@', $rawEmail);
  if (count($parts) === 2) {
    $name = $parts[0];
    $domain = $parts[1];
    $maskedName = substr($name, 0, 1) . str_repeat('*', max(3, strlen($name) - 2)) . (strlen($name) > 1 ? substr($name, -1) : '');
    $maskedEmail = $maskedName . '@' . $domain;
  } else {
    $maskedEmail = $rawEmail;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Password Recovery with OTP | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/forgot-password.css">
</head>

<body>
  <header>
    <div class="logo">
      <h1>Gym<span>Bros</span></h1>
    </div>
    <div class="navBar">
      <ul>
        <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
        <li><a href="register.php"><i class="fas fa-user-plus"></i> Register</a></li>
      </ul>
    </div>
  </header>

  <div class="container">
    <div class="form-header">
      <div class="icon-circle">
        <i class="fas <?php echo $step === 1 ? 'fa-envelope-open-text' : ($step === 2 ? 'fa-shield-alt' : 'fa-lock'); ?>"></i>
      </div>
      <h1>Password <span>Recovery</span></h1>
      <p>Secure identity validation using One-Time PIN (OTP)</p>
    </div>

    <!-- Step Progress Tracker -->
    <div class="step-tracker">
      <div class="step-dot <?php echo $step === 1 ? 'active' : ($step > 1 ? 'completed' : ''); ?>">
        <span class="dot-num"><?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?></span> Email ID
      </div>
      <div class="step-line"></div>
      <div class="step-dot <?php echo $step === 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">
        <span class="dot-num"><?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?></span> OTP Auth
      </div>
      <div class="step-line"></div>
      <div class="step-dot <?php echo $step === 3 ? 'active' : ''; ?>">
        <span class="dot-num">3</span> New Password
      </div>
    </div>

    <!-- Messages -->
    <?php if (!empty($errors)): ?>
      <div class="error">
        <?php foreach ($errors as $error): ?>
          <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($successMessage)): ?>
      <div class="success">
        <p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></p>
      </div>
    <?php endif; ?>

    <!-- STEP 1: Enter Registered Email / ID Number / Username -->
    <?php if ($step === 1): ?>
      <form method="POST" action="forgot-password.php?step=1">
        <input type="hidden" name="action" value="request_otp">
        <div class="form-group">
          <label class="form-label">Registered Email / Username / Employee ID</label>
          <div class="input-with-icon">
            <i class="fas fa-user-circle input-icon"></i>
            <input type="text" name="identifier" class="form-input" placeholder="e.g. user@gymbros.com or username" required autofocus>
          </div>
          <p style="font-size: 12px; color: #94a3b8; margin-top: 6px;">
            <i class="fas fa-info-circle"></i> Works for Super Administrators, Administrators, and Gym Members.
          </p>
        </div>
        <button type="submit" class="btn"><i class="fas fa-paper-plane"></i> Send One-Time PIN</button>
      </form>

    <!-- STEP 2: Enter OTP & Verify Account Validity -->
    <?php elseif ($step === 2 && isset($_SESSION['otp_reset_user'])): ?>
      
      <?php if (!empty($_SESSION['otp_demo_display'])): ?>
        <!-- On-Screen OTP Notification Simulation (Guarantees smooth testability on localhost/XAMPP) -->
        <div class="otp-demo-alert">
          <div>
            <strong><i class="fas fa-shield-alt"></i> Security OTP Generated:</strong>
            <div style="font-size: 11px; color: #a7f3d0; margin-top: 2px;">Use this 6-digit pin to authenticate validity:</div>
          </div>
          <span class="otp-number-badge"><?php echo htmlspecialchars($_SESSION['otp_demo_display']); ?></span>
        </div>
      <?php endif; ?>

      <p style="font-size: 13px; color: #cbd5e1; margin-bottom: 15px; text-align: center;">
        A 6-digit authentication PIN has been sent to your registered email: <br>
        <strong style="color: var(--accent, #ff5e00); font-size: 14px;"><?php echo htmlspecialchars($maskedEmail); ?></strong>
      </p>

      <form method="POST" action="forgot-password.php?step=2">
        <input type="hidden" name="action" value="verify_otp">
        
        <div class="form-group">
          <label class="form-label" style="text-align:center;">Enter 6-Digit One-Time PIN (OTP)</label>
          <div class="otp-input-wrapper">
            <input type="text" name="otp_code" id="otp_code" class="otp-box" maxlength="6" pattern="[0-9]{6}" placeholder="------" autocomplete="one-time-code" required autofocus>
          </div>
        </div>

        <button type="submit" class="btn"><i class="fas fa-check-circle"></i> Verify & Authenticate</button>
      </form>

      <!-- Resend OTP & Start Over Buttons -->
      <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid rgba(255,255,255,0.08);">
        <form method="POST" action="forgot-password.php" style="display:inline;">
          <input type="hidden" name="action" value="resend_otp">
          <button type="submit" class="btn-secondary-link"><i class="fas fa-redo-alt"></i> Resend OTP Code</button>
        </form>
        <a href="forgot-password.php?step=1" class="btn-secondary-link"><i class="fas fa-arrow-left"></i> Change Email</a>
      </div>

    <!-- STEP 3: Set New Password -->
    <?php elseif ($step === 3 && isset($_SESSION['otp_verified_user_id'])): ?>
      <p style="font-size: 13px; color: #4ade80; margin-bottom: 20px; text-align: center;">
        <i class="fas fa-shield-check"></i> Account identity confirmed! Please create a new secure password.
      </p>

      <form method="POST" action="forgot-password.php?step=3">
        <input type="hidden" name="action" value="reset_password">

        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="input-with-icon">
            <i class="fas fa-key input-icon"></i>
            <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Min. 8 characters with upper/lower & symbols" required autofocus>
            <span class="password-toggle" onclick="togglePassword('new_password')">
              <i class="fas fa-eye" id="new_password-icon"></i>
            </span>
          </div>
          <div class="password-strength" id="password-strength"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div class="input-with-icon">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Re-enter your new password" required>
            <span class="password-toggle" onclick="togglePassword('confirm_password')">
              <i class="fas fa-eye" id="confirm_password-icon"></i>
            </span>
          </div>
          <div class="password-match" id="password-match" style="display:none;"></div>
        </div>

        <button type="submit" class="btn"><i class="fas fa-save"></i> Save New Password</button>
      </form>
    <?php endif; ?>

    <div class="text-center" style="margin-top: 2rem;">
      <p style="font-size: 13px; color: #94a3b8;">Remember your password? <a href="login.php" style="color: #ff5e00; font-weight: 600;">Log In</a></p>
    </div>
  </div>

  <script src="../js/validation.js"></script>
  <script>
    // Format OTP input to digits only
    const otpInput = document.getElementById('otp_code');
    if (otpInput) {
      otpInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
      });
    }
  </script>

  <footer>
    &copy; <?php echo date('Y'); ?> GymBros. All rights reserved.
  </footer>
</body>

</html>