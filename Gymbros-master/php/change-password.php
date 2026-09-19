<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/validation.php';
require_once __DIR__ . '/../includes/otp.php';

if (!Auth::isLoggedIn()) {
  header("Location: login.php");
  exit();
}

$errors = [];
$successMessage = '';
$user = $_SESSION['user'];
$isSuperAdmin = Auth::isSuperAdmin();
$isAdmin = Auth::isAdmin();
$activeMode = 'current'; // 'current' or 'otp'
$otpSent = false;

$db = new Database();
$conn = $db->getConnection();

// Calculate pending requests count for sidebar badge if Admin
$pendingRequestsTotal = 0;
if ($isAdmin) {
  $resP = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE status = 'pending'");
  if ($resP) $pendingRequestsTotal += (int)$resP->fetch_assoc()['cnt'];
  $resD = $conn->query("SELECT COUNT(*) as cnt FROM delete_requests WHERE status = 'pending'");
  if ($resD) $pendingRequestsTotal += (int)$resD->fetch_assoc()['cnt'];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
  $actionType = $_POST['action_type'] ?? 'current';

  if ($actionType === 'send_otp') {
    $activeMode = 'otp';
    $otpCode = OtpService::generateOTP($user['id_number'], $user['email'], 'forgot_password', 15);
    if ($otpCode) {
      $recipientName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
      OtpService::sendEmailOTP($user['email'], $otpCode, $recipientName, 'forgot_password');
      $otpSent = true;
      $successMessage = "A 6-digit One-Time PIN has been dispatched to " . htmlspecialchars($user['email']) . ". Please enter it below along with your new password.";
    } else {
      $errors[] = "Failed to generate One-Time PIN. Please try again.";
    }
  } elseif ($actionType === 'reset_with_otp') {
    $activeMode = 'otp';
    $otpEntered = trim($_POST['otp_code'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($otpEntered) || strlen($otpEntered) !== 6 || !ctype_digit($otpEntered)) {
      $errors[] = "Please enter the valid 6-digit numeric One-Time PIN sent to your email.";
    } elseif (empty($new_password) || empty($confirm_password)) {
      $errors[] = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
      $errors[] = "New password and confirmation password do not match.";
    } elseif (strlen($new_password) < 8) {
      $errors[] = "New password must be at least 8 characters long.";
    } else {
      $verifyRes = OtpService::verifyOTP($user['email'], $otpEntered, 'forgot_password');
      if (!$verifyRes['success']) {
        $errors[] = $verifyRes['message'];
      } else {
        $password_strength = Validation::validatePasswordStrength($new_password);
        if ($password_strength['strength'] === 'weak') {
          $errors[] = "New password is too weak. " . implode(', ', $password_strength['feedback']);
        } else {
          $new_password_hash = Security::hashPassword($new_password);
          $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id_number = ?");
          $stmt->bind_param("ss", $new_password_hash, $user['id_number']);

          if ($stmt->execute()) {
            $successMessage = "Password successfully reset via Email OTP! Your new credentials are active.";
            ActivityLogger::log('RESET_PASSWORD_OTP', "User @{$user['username']} ({$user['role']}) reset password via in-console Email OTP.", 'Security');
          } else {
            $errors[] = "Failed to update password. Please try again.";
          }
          $stmt->close();
        }
      }
    }
  } else {
    // Current Password Mode
    $activeMode = 'current';
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id_number = ? LIMIT 1");
    $stmt->bind_param("s", $user['id_number']);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();

    if (!$user_data || !password_verify($current_password, $user_data['password_hash'])) {
      $errors[] = "Current password is incorrect.";
    } elseif ($new_password !== $confirm_password) {
      $errors[] = "New password and confirmation password do not match.";
    } elseif (strlen($new_password) < 8) {
      $errors[] = "New password must be at least 8 characters long.";
    } else {
      $password_strength = Validation::validatePasswordStrength($new_password);
      if ($password_strength['strength'] === 'weak') {
        $errors[] = "New password is too weak. " . implode(', ', $password_strength['feedback']);
      } else {
        $new_password_hash = Security::hashPassword($new_password);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id_number = ?");
        $stmt->bind_param("ss", $new_password_hash, $user['id_number']);

        if ($stmt->execute()) {
          $successMessage = "Password changed successfully! Your new password is now active.";
          ActivityLogger::log('CHANGE_PASSWORD', "User @{$user['username']} changed account password.", 'Security');
        } else {
          $errors[] = "Failed to update password. Please try again.";
        }
        $stmt->close();
      }
    }
  }
}

$csrf_token = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Change Password | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css?v=<?php echo time(); ?>">
  <?php if ($isAdmin): ?>
    <link rel="stylesheet" href="../css/admin.css?v=<?php echo time(); ?>">
  <?php else: ?>
    <link rel="stylesheet" href="../css/dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../css/auth.css?v=<?php echo time(); ?>">
  <?php endif; ?>
  <style>
    .mode-tab-bar {
      display: flex;
      gap: 10px;
      margin-bottom: 25px;
      background: rgba(0, 0, 0, 0.35);
      padding: 6px;
      border-radius: 12px;
      border: 1px solid rgba(255, 255, 255, 0.1);
    }
    .mode-tab-btn {
      flex: 1;
      background: transparent;
      border: none;
      color: #94a3b8;
      padding: 10px 16px;
      border-radius: 8px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      font-family: 'Montserrat', sans-serif;
      transition: all 0.25s ease;
    }
    .mode-tab-btn:hover {
      color: #fff;
      background: rgba(255, 255, 255, 0.06);
    }
    .mode-tab-btn.active {
      background: linear-gradient(135deg, var(--accent, #ff5e00), #ff7b00);
      color: #fff;
      box-shadow: 0 4px 15px rgba(255, 94, 0, 0.35);
    }
  </style>
</head>

<body>
  <!-- Loading Animation -->
  <div class="page-loader">
    <div class="loader">
      <div class="dumbbell">
        <div class="bar"></div>
        <div class="weight left"></div>
        <div class="weight right"></div>
      </div>
      <p><?php echo $isAdmin ? 'Loading Console...' : 'Loading...'; ?></p>
    </div>
  </div>

  <?php if ($isAdmin): ?>
    <!-- ADMIN / SUPERADMIN DASHBOARD LAYOUT -->
    <div class="admin-layout-wrapper">
      <!-- SIDEBAR -->
      <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-brand">
          <div class="logo">
            <h1>Gym<span>Bros</span></h1>
          </div>
          <div class="role-badge-pill">
            <?php if ($isSuperAdmin): ?>
              <span class="badge-superadmin"><i class="fas fa-crown"></i> SUPER ADMIN</span>
            <?php else: ?>
              <span class="badge-admin"><i class="fas fa-shield-alt"></i> ADMIN PORTAL</span>
            <?php endif; ?>
          </div>
        </div>

        <div class="sidebar-user">
          <div class="user-avatar"><i class="fas fa-user-shield"></i></div>
          <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
            <div class="user-id">ID: <?php echo htmlspecialchars($user['id_number']); ?></div>
          </div>
        </div>

        <nav class="sidebar-nav">
          <ul>
            <li>
              <a href="dashboard.php">
                <i class="fas fa-users-cog"></i> <span>Accounts Console</span>
              </a>
            </li>
            <li>
              <a href="admin_requests.php">
                <i class="fas fa-clipboard-list"></i> <span>Requests Queue</span>
                <?php if ($pendingRequestsTotal > 0): ?>
                  <span class="nav-badge"><?php echo $pendingRequestsTotal; ?></span>
                <?php endif; ?>
              </a>
            </li>
            <li>
              <a href="privileges.php">
                <i class="fas fa-user-shield"></i> <span>Privileges</span>
              </a>
            </li>
            <?php if ($isSuperAdmin): ?>
              <li>
                <a href="create_account.php">
                  <i class="fas fa-user-plus"></i> <span>Create Account</span>
                </a>
              </li>
            <?php endif; ?>
            <li>
              <a href="logs.php">
                <i class="fas fa-history"></i> <span>System Logs</span>
              </a>
            </li>
            <li class="nav-divider"></li>
            <li>
              <a href="change-password.php" class="active">
                <i class="fas fa-key"></i> <span>Change Password</span>
              </a>
            </li>
            <li>
              <a href="logout.php" class="nav-logout">
                <i class="fas fa-sign-out-alt"></i> <span>Logout</span>
              </a>
            </li>
          </ul>
        </nav>
      </aside>

      <!-- MAIN CONTENT WRAPPER -->
      <div class="admin-main-wrapper">
        <header class="admin-topbar">
          <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar Navigation">
            <i class="fas fa-bars"></i>
          </button>
          <div class="topbar-title">
            <h3><i class="fas fa-key"></i> Account Security & Password</h3>
          </div>
          <div class="topbar-right">
            <div class="topbar-user-chip">
              <i class="fas fa-user-shield"></i>
              <span>@<?php echo htmlspecialchars($user['username']); ?></span>
            </div>
            <a href="logout.php" class="btn-topbar-logout">
              <i class="fas fa-sign-out-alt"></i> Logout
            </a>
          </div>
        </header>

        <div class="admin-page-content">
          <div style="background: rgba(26, 31, 59, 0.75); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 20px; padding: 35px; max-width: 680px; margin: 0 auto; box-shadow: 0 15px 40px rgba(0,0,0,0.5);">
            
            <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; margin-bottom: 25px;">
              <div>
                <h2 style="font-family: 'Oswald', sans-serif; color: var(--accent); font-size: 26px; text-transform: uppercase; letter-spacing: 1px; display: flex; align-items: center; gap: 12px; margin: 0;">
                  <i class="fas fa-lock"></i> Update Account Password
                </h2>
                <p style="color: #94a3b8; font-size: 14px; margin-top: 4px; margin-bottom: 0;">Change or recover your administrator credentials with current password or Email OTP.</p>
              </div>
              <a href="dashboard.php" class="btn-secondary-action" style="font-size: 13px;">
                <i class="fas fa-arrow-left"></i> Dashboard
              </a>
            </div>

            <!-- Mode Selector Tabs -->
            <div class="mode-tab-bar">
              <button type="button" id="admin-tab-current" class="mode-tab-btn <?php echo $activeMode === 'current' ? 'active' : ''; ?>" onclick="switchAdminPasswordMode('current')">
                <i class="fas fa-key"></i> Current Password
              </button>
              <button type="button" id="admin-tab-otp" class="mode-tab-btn <?php echo $activeMode === 'otp' ? 'active' : ''; ?>" onclick="switchAdminPasswordMode('otp')">
                <i class="fas fa-paper-plane"></i> Reset via Email OTP
              </button>
            </div>

            <!-- Password Requirements Box -->
            <div style="background: rgba(255, 94, 0, 0.08); border: 1px solid rgba(255, 94, 0, 0.25); border-radius: 12px; padding: 14px 18px; margin-bottom: 22px;">
              <h4 style="color: var(--accent); font-size: 14px; font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                <i class="fas fa-shield-alt"></i> Password Security Requirements
              </h4>
              <ul style="color: #cbd5e1; font-size: 12px; margin: 0; padding-left: 20px; line-height: 1.6;">
                <li>Minimum 8 characters in length</li>
                <li>At least one uppercase letter (A-Z) and one lowercase letter (a-z)</li>
                <li>At least one number (0-9) and one special symbol (!@#$%^&*)</li>
              </ul>
            </div>

            <?php if (!empty($errors)): ?>
              <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid rgba(239, 68, 68, 0.4); color: #fca5a5; padding: 12px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 13px;">
                <?php foreach ($errors as $error): ?>
                  <p style="margin: 3px 0;"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>

            <?php if (!empty($successMessage)): ?>
              <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px;">
                <p style="margin: 0;"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></p>
              </div>
            <?php endif; ?>

            <!-- MODE 1: CURRENT PASSWORD FORM -->
            <div id="panel-admin-current" style="<?php echo $activeMode === 'current' ? '' : 'display: none;'; ?>">
              <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action_type" value="current">

                <div class="form-field" style="margin-bottom: 18px;">
                  <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                    <i class="fas fa-lock" style="color: var(--accent);"></i> Current Password *
                  </label>
                  <div style="position: relative;">
                    <input type="password" name="current_password" id="admin_current_password" required
                           style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 42px 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s;"
                           placeholder="Enter current password">
                    <span onclick="togglePassword('admin_current_password')" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8;">
                      <i class="fas fa-eye" id="admin_current_password-icon"></i>
                    </span>
                  </div>
                  <div style="margin-top: 6px;">
                    <a href="javascript:void(0)" onclick="switchAdminPasswordMode('otp')" style="font-size: 12px; color: var(--accent); text-decoration: none;">
                      <i class="fas fa-paper-plane"></i> Forgot current password? Reset with Email OTP
                    </a>
                  </div>
                </div>

                <div class="form-field" style="margin-bottom: 18px;">
                  <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                    <i class="fas fa-key" style="color: var(--accent);"></i> New Password *
                  </label>
                  <div style="position: relative;">
                    <input type="password" name="new_password" id="admin_new_password" required
                           style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 42px 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s;"
                           placeholder="Min 8 chars, uppercase, number & symbol">
                    <span onclick="togglePassword('admin_new_password')" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8;">
                      <i class="fas fa-eye" id="admin_new_password-icon"></i>
                    </span>
                  </div>
                  <div class="password-strength" id="admin_password_strength" style="margin-top: 6px;"></div>
                </div>

                <div class="form-field" style="margin-bottom: 25px;">
                  <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                    <i class="fas fa-check-double" style="color: var(--accent);"></i> Confirm New Password *
                  </label>
                  <div style="position: relative;">
                    <input type="password" name="confirm_password" id="admin_confirm_password" required
                           style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 42px 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s;"
                           placeholder="Re-enter new password">
                    <span onclick="togglePassword('admin_confirm_password')" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8;">
                      <i class="fas fa-eye" id="admin_confirm_password-icon"></i>
                    </span>
                  </div>
                  <div class="password-match" id="admin_password_match" style="margin-top: 6px; display: none;"></div>
                </div>

                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                  <a href="dashboard.php" class="btn-secondary-action" style="padding: 12px 24px; text-decoration: none; border-radius: 10px;">
                    Cancel
                  </a>
                  <button type="submit" class="btn-primary-action" style="padding: 12px 28px; font-size: 14px; border-radius: 10px; cursor: pointer;">
                    <i class="fas fa-save"></i> Update Password
                  </button>
                </div>
              </form>
            </div>

            <!-- MODE 2: EMAIL OTP RESET FORM -->
            <div id="panel-admin-otp" style="<?php echo $activeMode === 'otp' ? '' : 'display: none;'; ?>">
              <div style="background: rgba(255, 94, 0, 0.08); border: 1px solid rgba(255, 94, 0, 0.3); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                  <div>
                    <h5 style="margin: 0; color: #fff; font-size: 14px;"><i class="fas fa-envelope-open-text" style="color: var(--accent);"></i> One-Time PIN Authentication</h5>
                    <p style="margin: 4px 0 0 0; color: #94a3b8; font-size: 12.5px;">Registered Email: <strong style="color: #ff7b00;"><?php echo htmlspecialchars($user['email']); ?></strong></p>
                  </div>
                  <form method="POST" action="" style="margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action_type" value="send_otp">
                    <button type="submit" class="btn-primary-action" style="padding: 9px 18px; font-size: 12.5px; border-radius: 8px; cursor: pointer;">
                      <i class="fas fa-paper-plane"></i> <?php echo $otpSent ? 'Resend OTP' : 'Send 6-Digit OTP'; ?>
                    </button>
                  </form>
                </div>
              </div>

              <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action_type" value="reset_with_otp">

                <div class="form-field" style="margin-bottom: 18px;">
                  <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                    <i class="fas fa-shield-alt" style="color: var(--accent);"></i> 6-Digit One-Time PIN (OTP) *
                  </label>
                  <input type="text" name="otp_code" id="admin_otp_code" maxlength="6" pattern="[0-9]{6}" required
                         style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 14px; color: #fff; font-size: 16px; letter-spacing: 4px; font-family: monospace; outline: none; transition: border-color 0.2s;"
                         placeholder="Enter 6-digit OTP from email">
                </div>

                <div class="form-field" style="margin-bottom: 18px;">
                  <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                    <i class="fas fa-key" style="color: var(--accent);"></i> New Password *
                  </label>
                  <div style="position: relative;">
                    <input type="password" name="new_password" id="admin_otp_new_password" required
                           style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 42px 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s;"
                           placeholder="Min 8 chars, uppercase, number & symbol">
                    <span onclick="togglePassword('admin_otp_new_password')" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8;">
                      <i class="fas fa-eye" id="admin_otp_new_password-icon"></i>
                    </span>
                  </div>
                </div>

                <div class="form-field" style="margin-bottom: 25px;">
                  <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                    <i class="fas fa-check-double" style="color: var(--accent);"></i> Confirm New Password *
                  </label>
                  <div style="position: relative;">
                    <input type="password" name="confirm_password" id="admin_otp_confirm_password" required
                           style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 42px 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s;"
                           placeholder="Re-enter new password">
                    <span onclick="togglePassword('admin_otp_confirm_password')" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8;">
                      <i class="fas fa-eye" id="admin_otp_confirm_password-icon"></i>
                    </span>
                  </div>
                </div>

                <div style="display: flex; gap: 15px; justify-content: flex-end;">
                  <button type="button" class="btn-secondary-action" onclick="switchAdminPasswordMode('current')" style="padding: 12px 24px; text-decoration: none; border-radius: 10px; cursor: pointer;">
                    Cancel
                  </button>
                  <button type="submit" class="btn-primary-action" style="padding: 12px 28px; font-size: 14px; border-radius: 10px; cursor: pointer;">
                    <i class="fas fa-shield-alt"></i> Verify OTP & Reset Password
                  </button>
                </div>
              </form>
            </div>

          </div>
        </div>

        <footer class="admin-footer">
          <div class="admin-footer-brand">
            <div class="logo"><h1>Gym<span>Bros</span></h1></div>
            <p>Management & Administration Portal</p>
          </div>
          <div class="admin-footer-copy">
            <p>© <?php echo date('Y'); ?> GymBros. All rights reserved.</p>
          </div>
        </footer>
      </div>
    </div>
    <script src="../js/admin.js?v=<?php echo time(); ?>"></script>

  <?php else: ?>

    <!-- REGULAR USER MEMBER HEADER & LAYOUT -->
    <header>
      <div class="logo">
        <h1>Gym<span>Bros</span></h1>
      </div>
      <div class="navBar">
        <ul>
          <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
          <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
          <li><a href="change-password.php" class="active"><i class="fas fa-key"></i> Change Password</a></li>
          <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
      </div>
    </header>

    <div class="container" style="margin-top: 40px;">
      <div class="form-logo">
        <i class="fas fa-key"></i>
        <h1>Change <span>Password</span></h1>
      </div>

      <h2 class="form-title">Update Your Password</h2>

      <!-- Mode Selector Tabs -->
      <div class="mode-tab-bar" style="margin-bottom: 20px;">
        <button type="button" id="user-tab-current" class="mode-tab-btn <?php echo $activeMode === 'current' ? 'active' : ''; ?>" onclick="switchUserPasswordMode('current')">
          <i class="fas fa-key"></i> Current Password
        </button>
        <button type="button" id="user-tab-otp" class="mode-tab-btn <?php echo $activeMode === 'otp' ? 'active' : ''; ?>" onclick="switchUserPasswordMode('otp')">
          <i class="fas fa-paper-plane"></i> Reset with Email OTP
        </button>
      </div>

      <?php if (!empty($errors)): ?>
        <div class="error-message">
          <?php foreach ($errors as $error): ?>
            <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($successMessage)): ?>
        <div class="success-message">
          <p><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($successMessage); ?></p>
        </div>
      <?php endif; ?>

      <!-- USER MODE 1: CURRENT PASSWORD -->
      <div id="panel-user-current" style="<?php echo $activeMode === 'current' ? '' : 'display: none;'; ?>">
        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="action_type" value="current">

          <div class="form-group">
            <label class="form-label">Current Password</label>
            <div class="input-with-icon">
              <i class="fas fa-lock input-icon"></i>
              <input type="password" name="current_password" id="user_current_password" class="form-input" required>
              <span class="password-toggle" onclick="togglePassword('user_current_password')">
                <i class="fas fa-eye" id="user_current_password-icon"></i>
              </span>
            </div>
            <div style="margin-top: 6px;">
              <a href="javascript:void(0)" onclick="switchUserPasswordMode('otp')" style="font-size: 12px; color: var(--accent, #ff5e00); text-decoration: none;">
                <i class="fas fa-paper-plane"></i> Forgot password? Reset via Email OTP
              </a>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">New Password</label>
            <div class="input-with-icon">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="new_password" id="user_new_password" class="form-input" required>
              <span class="password-toggle" onclick="togglePassword('user_new_password')">
                <i class="fas fa-eye" id="user_new_password-icon"></i>
              </span>
            </div>
            <div class="password-strength" id="user_password_strength"></div>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <div class="input-with-icon">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="confirm_password" id="user_confirm_password" class="form-input" required>
              <span class="password-toggle" onclick="togglePassword('user_confirm_password')">
                <i class="fas fa-eye" id="user_confirm_password-icon"></i>
              </span>
            </div>
            <div class="password-match" id="user_password_match" style="display:none;"></div>
          </div>

          <button type="submit" class="btn">
            <i class="fas fa-save"></i> Update Password
          </button>
        </form>
      </div>

      <!-- USER MODE 2: EMAIL OTP -->
      <div id="panel-user-otp" style="<?php echo $activeMode === 'otp' ? '' : 'display: none;'; ?>">
        <div style="background: rgba(255, 94, 0, 0.08); border: 1px solid rgba(255, 94, 0, 0.3); border-radius: 12px; padding: 14px; margin-bottom: 18px; text-align: center;">
          <p style="margin: 0 0 10px 0; font-size: 13px; color: #cbd5e1;">
            Send a 6-digit OTP verification code to: <br><strong style="color: #ff7b00;"><?php echo htmlspecialchars($user['email']); ?></strong>
          </p>
          <form method="POST" action="" style="display: inline;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action_type" value="send_otp">
            <button type="submit" class="btn" style="padding: 8px 16px; font-size: 12px; width: auto;">
              <i class="fas fa-paper-plane"></i> <?php echo $otpSent ? 'Resend OTP' : 'Send 6-Digit OTP'; ?>
            </button>
          </form>
        </div>

        <form method="POST" action="">
          <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
          <input type="hidden" name="action_type" value="reset_with_otp">

          <div class="form-group">
            <label class="form-label">6-Digit One-Time PIN (OTP)</label>
            <div class="input-with-icon">
              <i class="fas fa-shield-alt input-icon"></i>
              <input type="text" name="otp_code" id="user_otp_code" class="form-input" maxlength="6" pattern="[0-9]{6}" placeholder="Enter 6-digit OTP" required>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">New Password</label>
            <div class="input-with-icon">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="new_password" id="user_otp_new_password" class="form-input" required>
              <span class="password-toggle" onclick="togglePassword('user_otp_new_password')">
                <i class="fas fa-eye" id="user_otp_new_password-icon"></i>
              </span>
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm New Password</label>
            <div class="input-with-icon">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="confirm_password" id="user_otp_confirm_password" class="form-input" required>
              <span class="password-toggle" onclick="togglePassword('user_otp_confirm_password')">
                <i class="fas fa-eye" id="user_otp_confirm_password-icon"></i>
              </span>
            </div>
          </div>

          <button type="submit" class="btn">
            <i class="fas fa-shield-alt"></i> Verify OTP & Reset Password
          </button>
        </form>
      </div>

    </div>

    <footer style="margin-top:40px;padding:16px 0;text-align:center;color:#9ca3af;font-family:'Montserrat',sans-serif;border-top:1px solid #2d3748;">
      &copy; <?php echo date('Y'); ?> GymBros. All rights reserved.
    </footer>
  <?php endif; ?>

  <script src="../js/loader.js?v=<?php echo time(); ?>"></script>
  <script src="../js/validation.js?v=<?php echo time(); ?>"></script>
  <script>
    function switchAdminPasswordMode(mode) {
      document.getElementById('admin-tab-current').classList.toggle('active', mode === 'current');
      document.getElementById('admin-tab-otp').classList.toggle('active', mode === 'otp');
      document.getElementById('panel-admin-current').style.display = (mode === 'current') ? 'block' : 'none';
      document.getElementById('panel-admin-otp').style.display = (mode === 'otp') ? 'block' : 'none';
    }

    function switchUserPasswordMode(mode) {
      document.getElementById('user-tab-current').classList.toggle('active', mode === 'current');
      document.getElementById('user-tab-otp').classList.toggle('active', mode === 'otp');
      document.getElementById('panel-user-current').style.display = (mode === 'current') ? 'block' : 'none';
      document.getElementById('panel-user-otp').style.display = (mode === 'otp') ? 'block' : 'none';
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
  </script>
</body>

</html>