<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';
require_once '../includes/validation.php';

if (!Auth::isLoggedIn()) {
  header("Location: login.php");
  exit();
}

$errors = [];
$success = false;
$user = $_SESSION['user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCSRFToken($_POST['csrf_token'])) {
  $current_password = $_POST['current_password'];
  $new_password = $_POST['new_password'];
  $confirm_password = $_POST['confirm_password'];

  $db = new Database();
  $conn = $db->getConnection();

  // Verify current password
  $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id_number = ?");
  $stmt->bind_param("s", $user['id_number']);
  $stmt->execute();
  $result = $stmt->get_result();
  $user_data = $result->fetch_assoc();

  if (!password_verify($current_password, $user_data['password_hash'])) {
    $errors[] = "Current password is incorrect";
  } elseif ($new_password !== $confirm_password) {
    $errors[] = "New passwords do not match";
  } else {
    $password_strength = Validation::validatePasswordStrength($new_password);
    if ($password_strength['strength'] === 'weak') {
      $errors[] = "New password is too weak";
    } else {
      $new_password_hash = Security::hashPassword($new_password);

      $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id_number = ?");
      $stmt->bind_param("ss", $new_password_hash, $user['id_number']);

      if ($stmt->execute()) {
        $success = true;
        $_SESSION['success_message'] = "Password changed successfully!";
      } else {
        $errors[] = "Password change failed";
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
  <link
    href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Oswald:wght@500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../css/auth.css">
</head>

<body>
  <!-- Loading Animation 
  <div class="page-loader">
    <div class="loader">
      <div class="dumbbell">
        <div class="bar"></div>
        <div class="weight left"></div>
        <div class="weight right"></div>
      </div>
  <p>Loading GymBros...</p>-->
  </div>
  </div>
  <!--<div class="heading">
    <h1>Gym System</h1>
  </div>-->

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

  <div class="container">
    <div class="form-logo">
      <i class="fas fa-key"></i>
      <h1>Change <span>Password</span></h1>
    </div>

    <h2 class="form-title">Update Your Password</h2>

    <?php if (!empty($errors)): ?>
      <div class="error-message">
        <?php foreach ($errors as $error): ?>
          <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
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

  <script src="../js/validation.js"></script>
  <!--<script src="js/loader.js"></script>-->
  <footer style="margin-top:40px;padding:16px 0;text-align:center;color:#9ca3af;font-family:'Montserrat',sans-serif;border-top:1px solid #2d3748;">
    &copy; <?php echo date('Y'); ?> GymBros. All rights reserved.
  </footer>
</body>

</html>