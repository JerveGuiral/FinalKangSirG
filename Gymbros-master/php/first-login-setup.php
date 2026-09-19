<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/auth.php';
require_once '../includes/validation.php';

if (!Auth::isLoggedIn()) {
  header("Location: login.php");
  exit();
}

$db = new Database();
$conn = $db->getConnection();

$user = $_SESSION['user'];

// Resync fresh user row (must_change_password may have just changed)
$stmtFresh = $conn->prepare("SELECT * FROM users WHERE id_number = ? LIMIT 1");
$stmtFresh->bind_param("s", $user['id_number']);
$stmtFresh->execute();
$freshRes = $stmtFresh->get_result();
if ($freshRes && $freshRes->num_rows === 1) {
  $user = $freshRes->fetch_assoc();
  $_SESSION['user'] = $user;
}
$stmtFresh->close();

// This flag is only ever set by the admin account-provisioning flow, and stays
// set (deliberately) until BOTH onboarding steps below are complete — it is
// the single source of truth for "this account still owes us setup".
$mustChangePassword = !empty($user['must_change_password']);
if (!$mustChangePassword) {
  header("Location: dashboard.php");
  exit();
}

$sqStmt = $conn->prepare("SELECT id FROM security_questions WHERE user_id = ? LIMIT 1");
$sqStmt->bind_param("s", $user['id_number']);
$sqStmt->execute();
$hasSecurityQuestions = $sqStmt->get_result()->num_rows > 0;
$sqStmt->close();

// Self-heal: both steps are actually already done, just the flag wasn't cleared
// (e.g. a prior request's final UPDATE failed) — clear it and move on.
if ($hasSecurityQuestions) {
  $healStmt = $conn->prepare("UPDATE users SET must_change_password = 0 WHERE id_number = ?");
  $healStmt->bind_param("s", $user['id_number']);
  $healStmt->execute();
  $healStmt->close();
  header("Location: dashboard.php");
  exit();
}

$SECURITY_QUESTIONS = [
  'Who is your best friend in Elementary?',
  'What is the name of your favorite pet?',
  'Who is your favorite teacher in high school?'
];

// Password step is tracked for THIS login session only: once changed, stay on
// step 2 until security questions are saved, even across page reloads.
$currentStep = !empty($_SESSION['first_login_password_step_done']) ? 2 : 1;
$errors = [];
$successMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    $errors[] = "Security token invalid. Please try again.";
  } else {
    $formStep = $_POST['form_step'] ?? '';

    if ($formStep === '1') {
      $newPassword = $_POST['new_password'] ?? '';
      $confirmPassword = $_POST['confirm_password'] ?? '';

      $strength = Validation::validatePasswordStrength($newPassword);
      if ($strength['strength'] === 'weak') {
        $errors[] = "Password is too weak. " . implode(', ', $strength['feedback']);
      }
      if ($newPassword !== $confirmPassword) {
        $errors[] = "Passwords do not match";
      }

      if (empty($errors)) {
        $newHash = Security::hashPassword($newPassword);
        $upd = $conn->prepare("UPDATE users SET password_hash = ? WHERE id_number = ?");
        $upd->bind_param("ss", $newHash, $user['id_number']);
        $upd->execute();
        $upd->close();

        ActivityLogger::log('CHANGE_PASSWORD', "User @{$user['username']} set a new password on first login.", 'Authentication');

        $_SESSION['first_login_password_step_done'] = true;
        $currentStep = 2;
        $successMessage = "Password updated successfully. Now set up your 3 security questions.";
      }
    } elseif ($formStep === '2') {
      $q1 = trim($_POST['security_question1'] ?? '');
      $a1 = trim($_POST['security_answer1'] ?? '');
      $q2 = trim($_POST['security_question2'] ?? '');
      $a2 = trim($_POST['security_answer2'] ?? '');
      $q3 = trim($_POST['security_question3'] ?? '');
      $a3 = trim($_POST['security_answer3'] ?? '');

      if (empty($q1) || empty($a1) || empty($q2) || empty($a2) || empty($q3) || empty($a3)) {
        $errors[] = "Please select and answer all 3 security questions";
      } elseif (!in_array($q1, $SECURITY_QUESTIONS) || !in_array($q2, $SECURITY_QUESTIONS) || !in_array($q3, $SECURITY_QUESTIONS)) {
        $errors[] = "Invalid security question selected";
      } elseif ($q1 === $q2 || $q1 === $q3 || $q2 === $q3) {
        $errors[] = "Please select 3 different security questions";
      }

      if (empty($errors)) {
        $h1 = Security::hashPassword($a1);
        $h2 = Security::hashPassword($a2);
        $h3 = Security::hashPassword($a3);

        $ins = $conn->prepare("INSERT INTO security_questions (user_id, question1, answer1_hash, question2, answer2_hash, question3, answer3_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $ins->bind_param("sssssss", $user['id_number'], $q1, $h1, $q2, $h2, $q3, $h3);
        $ins->execute();
        $ins->close();

        $doneStmt = $conn->prepare("UPDATE users SET must_change_password = 0 WHERE id_number = ?");
        $doneStmt->bind_param("s", $user['id_number']);
        $doneStmt->execute();
        $doneStmt->close();

        ActivityLogger::log('SETUP_SECURITY_QUESTIONS', "User @{$user['username']} configured security questions on first login.", 'Authentication');

        unset($_SESSION['first_login_password_step_done']);
        $user['must_change_password'] = 0;
        $_SESSION['user'] = $user;

        header("Location: dashboard.php");
        exit();
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
  <title>Complete Your Account Setup | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Oswald:wght@500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/auth.css">
</head>

<body>
  <header>
    <div class="logo">
      <h1>Gym<span>Bros</span></h1>
    </div>
  </header>

  <div class="container">
    <div class="form-logo">
      <i class="fas fa-user-shield"></i>
      <h1>Gym<span>Bros</span></h1>
    </div>

    <h2 class="form-title">
      <?php echo $currentStep === 1 ? 'Set Your Password' : 'Set Up Security Questions'; ?>
    </h2>

    <p style="text-align: center; color: #94a3b8; font-size: 13px; margin: -10px 0 20px 0;">
      Step <?php echo $currentStep; ?> of 2 &mdash; Required before you can access your account
    </p>

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

    <?php if ($currentStep === 1): ?>
      <!-- STEP 1: Forced Password Change -->
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="form_step" value="1">

        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="input-with-icon">
            <i class="fas fa-key input-icon"></i>
            <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Min 8 characters" required>
            <span class="password-toggle" onclick="togglePassword('new_password')">
              <i class="fas fa-eye" id="new_password-icon"></i>
            </span>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <div class="input-with-icon">
            <i class="fas fa-key input-icon"></i>
            <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Re-enter new password" required>
            <span class="password-toggle" onclick="togglePassword('confirm_password')">
              <i class="fas fa-eye" id="confirm_password-icon"></i>
            </span>
          </div>
        </div>

        <button type="submit" class="btn"><i class="fas fa-arrow-right"></i> Continue</button>
      </form>
    <?php else: ?>
      <!-- STEP 2: Security Questions Setup -->
      <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="form_step" value="2">

        <?php for ($i = 1; $i <= 3; $i++): ?>
          <div class="form-group">
            <label class="form-label">Security Question <?php echo $i; ?></label>
            <select name="security_question<?php echo $i; ?>" id="security_question<?php echo $i; ?>" class="form-input" required style="margin-bottom: 10px;">
              <option value="">Select a question</option>
              <?php foreach ($SECURITY_QUESTIONS as $q): ?>
                <option value="<?php echo htmlspecialchars($q); ?>" <?php echo (isset($_POST["security_question$i"]) && $_POST["security_question$i"] === $q) ? 'selected' : ''; ?>><?php echo htmlspecialchars($q); ?></option>
              <?php endforeach; ?>
            </select>

            <div class="input-with-icon">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="security_answer<?php echo $i; ?>" id="security_answer<?php echo $i; ?>" class="form-input" placeholder="Enter your answer" required>
              <span class="password-toggle" onclick="togglePassword('security_answer<?php echo $i; ?>')">
                <i class="fas fa-eye" id="security_answer<?php echo $i; ?>-icon"></i>
              </span>
            </div>
          </div>
        <?php endfor; ?>

        <button type="submit" class="btn"><i class="fas fa-check"></i> Finish Setup</button>
      </form>
    <?php endif; ?>
  </div>

  <script>
    function togglePassword(inputId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(inputId + '-icon');
      if (!input || !icon) return;
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
  </script>

  <footer style="margin-top:40px;padding:16px 0;text-align:center;color:#9ca3af;font-family:'Montserrat',sans-serif;border-top:1px solid #2d3748;">
    &copy; <?php echo date('Y'); ?> GymBros. All rights reserved.
  </footer>
</body>

</html>
