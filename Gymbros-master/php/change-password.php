<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/validation.php';

if (!Auth::isLoggedIn()) {
  header("Location: login.php");
  exit();
}

$errors = [];
$success = false;
$user = $_SESSION['user'];
$isSuperAdmin = Auth::isSuperAdmin();
$isAdmin = Auth::isAdmin();

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
  $current_password = $_POST['current_password'] ?? '';
  $new_password = $_POST['new_password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';

  // Fetch current user password hash
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
      $errors[] = "New password is too weak. Must contain uppercase, lowercase, numbers, and special characters.";
    } else {
      $new_password_hash = Security::hashPassword($new_password);
      $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id_number = ?");
      $stmt->bind_param("ss", $new_password_hash, $user['id_number']);

      if ($stmt->execute()) {
        $success = true;
        // Audit log
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userRole = $user['role'] ?? 'user';
        $stmtLog = $conn->prepare("INSERT INTO login_logs (id_number, username, full_name, role, ip_address, time_in) VALUES (?, ?, 'PASSWORD CHANGED', ?, ?, NOW())");
        if ($stmtLog) {
          $stmtLog->bind_param("ssss", $user['id_number'], $user['username'], $userRole, $ip);
          $stmtLog->execute();
          $stmtLog->close();
        }
      } else {
        $errors[] = "Failed to update password. Please try again.";
      }
      $stmt->close();
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
            <?php if ($isSuperAdmin): ?>
              <li>
                <a href="create_account.php">
                  <i class="fas fa-user-plus"></i> <span>Create Account</span>
                </a>
              </li>
            <?php endif; ?>
            <?php if ($isSuperAdmin || Auth::hasPrivilege('can_view_reports')): ?>
              <li>
                <a href="logs.php">
                  <i class="fas fa-history"></i> <span>System Logs</span>
                </a>
              </li>
            <?php endif; ?>
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
                <p style="color: #94a3b8; font-size: 14px; margin-top: 4px; margin-bottom: 0;">Change your administrator credentials to keep your account secure.</p>
              </div>
              <a href="dashboard.php" class="btn-secondary-action" style="font-size: 13px;">
                <i class="fas fa-arrow-left"></i> Dashboard
              </a>
            </div>

            <!-- Password Requirements Box -->
            <div style="background: rgba(255, 94, 0, 0.08); border: 1px solid rgba(255, 94, 0, 0.25); border-radius: 12px; padding: 14px 18px; margin-bottom: 25px;">
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

            <?php if ($success): ?>
              <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.4); color: #6ee7b7; padding: 14px 18px; border-radius: 10px; margin-bottom: 20px; font-size: 14px;">
                <p style="margin: 0;"><i class="fas fa-check-circle"></i> Password changed successfully! Your new password is now active.</p>
              </div>
            <?php endif; ?>

            <form method="POST" action="">
              <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

              <div class="form-field" style="margin-bottom: 18px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                  <i class="fas fa-lock" style="color: var(--accent);"></i> Current Password *
                </label>
                <div style="position: relative;">
                  <input type="password" name="current_password" id="current_password" required
                         style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 42px 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s;"
                         placeholder="Enter current password">
                  <span onclick="togglePassword('current_password')" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8;">
                    <i class="fas fa-eye" id="current_password-icon"></i>
                  </span>
                </div>
              </div>

              <div class="form-field" style="margin-bottom: 18px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                  <i class="fas fa-key" style="color: var(--accent);"></i> New Password *
                </label>
                <div style="position: relative;">
                  <input type="password" name="new_password" id="new_password" required
                         style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 42px 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s;"
                         placeholder="Min 8 chars, uppercase, number & symbol">
                  <span onclick="togglePassword('new_password')" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8;">
                    <i class="fas fa-eye" id="new_password-icon"></i>
                  </span>
                </div>
                <div class="password-strength" id="password-strength" style="margin-top: 6px;"></div>
              </div>

              <div class="form-field" style="margin-bottom: 25px;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: #cbd5e1; margin-bottom: 8px;">
                  <i class="fas fa-check-double" style="color: var(--accent);"></i> Confirm New Password *
                </label>
                <div style="position: relative;">
                  <input type="password" name="confirm_password" id="confirm_password" required
                         style="width: 100%; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255,255,255,0.15); border-radius: 10px; padding: 12px 42px 12px 14px; color: #fff; font-size: 14px; outline: none; transition: border-color 0.2s;"
                         placeholder="Re-enter new password">
                  <span onclick="togglePassword('confirm_password')" style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #94a3b8;">
                    <i class="fas fa-eye" id="confirm_password-icon"></i>
                  </span>
                </div>
                <div class="password-match" id="password-match" style="margin-top: 6px; display: none;"></div>
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

      <?php if (!empty($errors)): ?>
        <div class="error-message">
          <?php foreach ($errors as $error): ?>
            <p><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></p>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($success): ?>
        <div class="success-message">
          <p><i class="fas fa-check-circle"></i> Password changed successfully!</p>
        </div>
      <?php endif; ?>

      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

        <div class="form-group">
          <label class="form-label">Current Password</label>
          <div class="input-with-icon">
            <i class="fas fa-lock input-icon"></i>
            <input type="password" name="current_password" id="current_password" class="form-input" required>
            <span class="password-toggle" onclick="togglePassword('current_password')">
              <i class="fas fa-eye" id="current_password-icon"></i>
            </span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="input-with-icon">
            <i class="fas fa-key input-icon"></i>
            <input type="password" name="new_password" id="new_password" class="form-input" required>
            <span class="password-toggle" onclick="togglePassword('new_password')">
              <i class="fas fa-eye" id="new_password-icon"></i>
            </span>
          </div>
          <div class="password-strength" id="password-strength"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div class="input-with-icon">
            <i class="fas fa-key input-icon"></i>
            <input type="password" name="confirm_password" id="confirm_password" class="form-input" required>
            <span class="password-toggle" onclick="togglePassword('confirm_password')">
              <i class="fas fa-eye" id="confirm_password-icon"></i>
            </span>
          </div>
          <div class="password-match" id="password-match" style="display:none;"></div>
        </div>

        <button type="submit" class="btn">
          <i class="fas fa-save"></i> Update Password
        </button>
      </form>
    </div>

    <footer style="margin-top:40px;padding:16px 0;text-align:center;color:#9ca3af;font-family:'Montserrat',sans-serif;border-top:1px solid #2d3748;">
      &copy; <?php echo date('Y'); ?> GymBros. All rights reserved.
    </footer>
  <?php endif; ?>

  <script src="../js/loader.js?v=<?php echo time(); ?>"></script>
  <script src="../js/validation.js?v=<?php echo time(); ?>"></script>
</body>

</html>