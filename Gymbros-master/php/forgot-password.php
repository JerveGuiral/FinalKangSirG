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

$method = isset($_GET['method']) ? $_GET['method'] : ($_SESSION['recovery_method'] ?? 'otp');
if (!in_array($method, ['otp', 'questions'])) $method = 'otp';

$db = new Database();
$conn = $db->getConnection();

// --- Handle Step Submissions ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // STEP 1: Find Account & Initiate Verification (OTP or Questions)
  if ($action === 'request_reset' || $action === 'request_otp' || $step === 1) {
    $identifier = trim($db->sanitize($_POST['identifier'] ?? ''));
    $chosenMethod = $_POST['recovery_method'] ?? 'otp';
    if (!in_array($chosenMethod, ['otp', 'questions'])) $chosenMethod = 'otp';

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
          $userRole = $foundUser['role'] ?? 'user';
          // Admin and Superadmin accounts strictly use secure Email OTP verification
          if (in_array($userRole, ['superadmin', 'admin'])) {
            $chosenMethod = 'otp';
          }

          $_SESSION['otp_reset_user'] = [
            'id_number' => $foundUser['id_number'],
            'email' => $foundUser['email'],
            'username' => $foundUser['username'],
            'first_name' => $foundUser['first_name'],
            'last_name' => $foundUser['last_name'] ?? '',
            'role' => $userRole
          ];
          $_SESSION['recovery_method'] = $chosenMethod;
          $method = $chosenMethod;

          if ($chosenMethod === 'questions') {
            // Load Security Questions from database
            $stmtQ = $conn->prepare("SELECT question1, question2, question3 FROM security_questions WHERE user_id = ? LIMIT 1");
            $stmtQ->bind_param("s", $foundUser['id_number']);
            $stmtQ->execute();
            $resQ = $stmtQ->get_result();

            if ($resQ->num_rows === 1) {
              $_SESSION['sec_questions'] = $resQ->fetch_assoc();
              $stmtQ->close();
              $step = 2;
            } else {
              $stmtQ->close();
              $errors[] = "No security questions found for this account. Please use Email OTP recovery instead.";
            }
          } else {
            // Generate 6-digit OTP and send via email
            $otpCode = OtpService::generateOTP($foundUser['id_number'], $foundUser['email'], 'forgot_password', 15);

            if ($otpCode) {
              $recipientName = trim($foundUser['first_name'] . ' ' . $foundUser['last_name']);
              OtpService::sendEmailOTP($foundUser['email'], $otpCode, $recipientName, 'forgot_password');
              $step = 2;
            } else {
              $errors[] = "Failed to generate One-Time PIN. Please try again.";
            }
          }
        }
      } else {
        $stmt->close();
        $errors[] = "No registered account found matching that email, username, or ID number.";
      }
    }
  }

  // STEP 2A: Verify OTP
  elseif ($action === 'verify_otp' || ($step === 2 && $method === 'otp')) {
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
          $step = 3;
        } else {
          $errors[] = $verifyRes['message'];
        }
      }
    }
  }

  // STEP 2B: Verify Secret Questions (2 out of 3 correct required)
  elseif ($action === 'verify_questions' || ($step === 2 && $method === 'questions')) {
    if (!isset($_SESSION['otp_reset_user']) || !isset($_SESSION['sec_questions'])) {
      $errors[] = "Session expired. Please start over.";
      $step = 1;
    } else {
      $ans1 = trim($_POST['security_answer1'] ?? '');
      $ans2 = trim($_POST['security_answer2'] ?? '');
      $ans3 = trim($_POST['security_answer3'] ?? '');

      if (empty($ans1) || empty($ans2) || empty($ans3)) {
        $errors[] = "Please provide answers to all 3 security questions.";
      } else {
        $userId = $_SESSION['otp_reset_user']['id_number'];
        $isCorrect = Auth::verifySecurityAnswers($userId, [$ans1, $ans2, $ans3]);

        if ($isCorrect) {
          $_SESSION['otp_verified_user_id'] = $userId;
          $step = 3;
        } else {
          $errors[] = "Security answer verification failed. You must answer at least 2 out of 3 questions correctly to proceed.";
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
        $recipientName = trim($foundUser['first_name'] . ' ' . ($foundUser['last_name'] ?? ''));
        OtpService::sendEmailOTP($foundUser['email'], $otpCode, $recipientName, 'forgot_password');
        $successMessage = "A new 6-digit One-Time PIN has been sent to your registered email address.";
        $step = 2;
        $method = 'otp';
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

            // Log activity
            $resetUser = $_SESSION['otp_reset_user'] ?? ['id_number' => $targetUserId, 'username' => 'user', 'role' => 'user'];
            ActivityLogger::log('RESET_PASSWORD', "User @{$resetUser['username']} ({$targetUserId}) reset password via {$method}.", 'Security', $resetUser);

            // Clear session flags
            unset($_SESSION['otp_reset_user']);
            unset($_SESSION['otp_verified_user_id']);
            unset($_SESSION['sec_questions']);
            unset($_SESSION['recovery_method']);

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
  <title>Password Recovery | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/forgot-password.css">
  <style>
    .method-selector {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
      margin: 15px 0 20px 0;
    }
    .method-card {
      background: rgba(15, 23, 42, 0.7);
      border: 1px solid rgba(255, 255, 255, 0.12);
      border-radius: 10px;
      padding: 12px;
      cursor: pointer;
      text-align: center;
      transition: all 0.2s ease;
    }
    .method-card:hover {
      border-color: #ff5e00;
      background: rgba(255, 94, 0, 0.08);
    }
    .method-card input[type="radio"] {
      display: none;
    }
    .method-card.selected {
      border-color: #ff5e00;
      background: rgba(255, 94, 0, 0.15);
      box-shadow: 0 0 10px rgba(255, 94, 0, 0.3);
    }
    .method-card i {
      font-size: 20px;
      color: #ff7b00;
      margin-bottom: 6px;
      display: block;
    }
    .method-card .title {
      font-size: 13px;
      font-weight: 600;
      color: #f8fafc;
    }
    .method-card .desc {
      font-size: 11px;
      color: #94a3b8;
      margin-top: 2px;
    }
  </style>
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
        <i class="fas <?php echo $step === 1 ? 'fa-shield-alt' : ($step === 2 ? ($method === 'questions' ? 'fa-question-circle' : 'fa-envelope-open-text') : 'fa-lock'); ?>"></i>
      </div>
      <h1>Password <span>Recovery</span></h1>
      <p>Secure identity validation using One-Time PIN or Secret Questions</p>
    </div>

    <!-- Step Progress Tracker -->
    <div class="step-tracker">
      <div class="step-dot <?php echo $step === 1 ? 'active' : ($step > 1 ? 'completed' : ''); ?>">
        <span class="dot-num"><?php echo $step > 1 ? '<i class="fas fa-check"></i>' : '1'; ?></span> Account ID
      </div>
      <div class="step-line"></div>
      <div class="step-dot <?php echo $step === 2 ? 'active' : ($step > 2 ? 'completed' : ''); ?>">
        <span class="dot-num"><?php echo $step > 2 ? '<i class="fas fa-check"></i>' : '2'; ?></span> <?php echo $method === 'questions' ? 'Questions' : 'OTP PIN'; ?>
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

    <!-- STEP 1: Enter Identifier & Choose Recovery Method -->
    <?php if ($step === 1): ?>
      <div style="background: rgba(255, 94, 0, 0.08); border: 1px solid rgba(255, 94, 0, 0.25); border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; font-size: 12.5px; color: #cbd5e1; display: flex; align-items: center; gap: 10px;">
        <i class="fas fa-user-shield" style="color: var(--accent, #ff5e00); font-size: 18px; flex-shrink: 0;"></i>
        <span><strong>Admin & Superadmin Accounts:</strong> Enter your administrator email, username, or Employee ID below to receive your secure One-Time PIN (OTP).</span>
      </div>

      <form method="POST" action="forgot-password.php?step=1">
        <input type="hidden" name="action" value="request_reset">
        
        <div class="form-group">
          <label class="form-label">Registered Email / Username / Employee ID</label>
          <div class="input-with-icon">
            <i class="fas fa-user-circle input-icon"></i>
            <input type="text" name="identifier" class="form-input" placeholder="e.g. admin@gymbros.com, username or ID" required autofocus>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Select Recovery Verification Method</label>
          <div class="method-selector">
            <label class="method-card selected" id="card-otp" onclick="selectMethod('otp')">
              <input type="radio" name="recovery_method" value="otp" checked>
              <i class="fas fa-paper-plane"></i>
              <div class="title">Email OTP</div>
              <div class="desc">6-digit PIN to email</div>
            </label>
            <label class="method-card" id="card-questions" onclick="selectMethod('questions')">
              <input type="radio" name="recovery_method" value="questions">
              <i class="fas fa-question-circle"></i>
              <div class="title">Secret Questions</div>
              <div class="desc">2 of 3 correct answers</div>
            </label>
          </div>
        </div>

        <button type="submit" class="btn"><i class="fas fa-arrow-right"></i> Proceed to Verification</button>
      </form>

    <!-- STEP 2A: Verify via OTP -->
    <?php elseif ($step === 2 && $method === 'otp' && isset($_SESSION['otp_reset_user'])): ?>
      
      <?php if (isset($_SESSION['otp_reset_user']['role']) && in_array($_SESSION['otp_reset_user']['role'], ['superadmin', 'admin'])): ?>
        <div style="text-align: center; margin-bottom: 14px;">
          <?php if ($_SESSION['otp_reset_user']['role'] === 'superadmin'): ?>
            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; padding: 5px 14px; border-radius: 20px; background: rgba(168, 85, 247, 0.25); border: 1px solid rgba(168, 85, 247, 0.5); color: #e9d5ff; letter-spacing: 0.5px;">
              <i class="fas fa-crown" style="color: #fbbf24;"></i> Super Administrator Account Recovery
            </span>
          <?php else: ?>
            <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 700; text-transform: uppercase; padding: 5px 14px; border-radius: 20px; background: rgba(255, 94, 0, 0.2); border: 1px solid rgba(255, 94, 0, 0.5); color: #ff7b00; letter-spacing: 0.5px;">
              <i class="fas fa-shield-alt" style="color: var(--accent, #ff5e00);"></i> Administrator Account Recovery
            </span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div style="background: rgba(255, 94, 0, 0.08); border: 1px solid rgba(255, 94, 0, 0.3); border-radius: 12px; padding: 16px; margin-bottom: 20px; text-align: center;">
        <i class="fas fa-envelope-open-text" style="font-size: 24px; color: #ff5e00; margin-bottom: 8px; display: inline-block;"></i>
        <p style="font-size: 13px; color: #cbd5e1; margin: 0; line-height: 1.5;">
          A 6-digit authentication PIN has been dispatched to your email:<br>
          <strong style="color: #ff7b00; font-size: 15px; letter-spacing: 0.5px;"><?php echo htmlspecialchars($maskedEmail); ?></strong>
        </p>
        <p style="font-size: 11px; color: #94a3b8; margin: 8px 0 0 0;">
          <i class="fas fa-clock"></i> Code expires in 15 minutes. Please check your inbox and spam folder.
        </p>
      </div>

      <form method="POST" action="forgot-password.php?step=2&method=otp">
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
        <a href="forgot-password.php?step=1" class="btn-secondary-link"><i class="fas fa-arrow-left"></i> Change Account</a>
      </div>

    <!-- STEP 2B: Verify via Secret Security Questions (2 out of 3 required) -->
    <?php elseif ($step === 2 && $method === 'questions' && isset($_SESSION['sec_questions'])): ?>
      
      <div style="background: rgba(30, 41, 59, 0.8); border: 1px solid rgba(255, 94, 0, 0.3); border-radius: 12px; padding: 14px 16px; margin-bottom: 20px;">
        <p style="font-size: 13px; color: #cbd5e1; margin: 0; line-height: 1.5;">
          <i class="fas fa-info-circle" style="color: #ff5e00;"></i> 
          Please answer your registered secret questions. <strong>At least 2 out of 3 answers must be correct</strong> to reset your password.
        </p>
      </div>

      <form method="POST" action="forgot-password.php?step=2&method=questions">
        <input type="hidden" name="action" value="verify_questions">

        <!-- Question 1 -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 13px; color: #ff7b00;">
            1. <?php echo htmlspecialchars($_SESSION['sec_questions']['question1']); ?>
          </label>
          <div class="input-with-icon">
            <i class="fas fa-shield-alt input-icon"></i>
            <input type="password" name="security_answer1" id="security_answer1" class="form-input" placeholder="Enter answer..." required autofocus>
            <span class="password-toggle" onclick="togglePassword('security_answer1')">
              <i class="fas fa-eye" id="security_answer1-icon"></i>
            </span>
          </div>
        </div>

        <!-- Question 2 -->
        <div class="form-group" style="margin-bottom: 16px;">
          <label class="form-label" style="font-size: 13px; color: #ff7b00;">
            2. <?php echo htmlspecialchars($_SESSION['sec_questions']['question2']); ?>
          </label>
          <div class="input-with-icon">
            <i class="fas fa-shield-alt input-icon"></i>
            <input type="password" name="security_answer2" id="security_answer2" class="form-input" placeholder="Enter answer..." required>
            <span class="password-toggle" onclick="togglePassword('security_answer2')">
              <i class="fas fa-eye" id="security_answer2-icon"></i>
            </span>
          </div>
        </div>

        <!-- Question 3 -->
        <div class="form-group" style="margin-bottom: 20px;">
          <label class="form-label" style="font-size: 13px; color: #ff7b00;">
            3. <?php echo htmlspecialchars($_SESSION['sec_questions']['question3']); ?>
          </label>
          <div class="input-with-icon">
            <i class="fas fa-shield-alt input-icon"></i>
            <input type="password" name="security_answer3" id="security_answer3" class="form-input" placeholder="Enter answer..." required>
            <span class="password-toggle" onclick="togglePassword('security_answer3')">
              <i class="fas fa-eye" id="security_answer3-icon"></i>
            </span>
          </div>
        </div>

        <button type="submit" class="btn"><i class="fas fa-check-circle"></i> Validate Secret Answers</button>
      </form>

      <div style="text-align: center; margin-top: 15px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.08);">
        <a href="forgot-password.php?step=1" class="btn-secondary-link"><i class="fas fa-arrow-left"></i> Change Account / Recovery Method</a>
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
    function selectMethod(m) {
      document.querySelectorAll('.method-card').forEach(c => c.classList.remove('selected'));
      const card = document.getElementById('card-' + m);
      if (card) {
        card.classList.add('selected');
        const radio = card.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
      }
    }

    function togglePassword(inputId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(inputId + '-icon');
      if (input && icon) {
        if (input.type === 'password') {
          input.type = 'text';
          icon.classList.remove('fa-eye');
          icon.classList.add('fa-eye-slash');
        } else {
          input.type = 'password';
          icon.classList.remove('fa-eye-slash');
          icon.classList.add('fa-eye');
        }
      }
    }

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