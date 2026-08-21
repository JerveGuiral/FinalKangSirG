<?php
require_once '../includes/config.php';
require_once '../includes/security.php';

// Simple Auth function since class is missing
function verifySecurityAnswers($user_id, $answers)
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

    return $correct >= 2;
  }
  return false;
}

$errors = [];
$success = false;
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $db = new Database();
  $conn = $db->getConnection();

  if ($step === 1) {
    // Step 1: Verify username
    $id_number = $db->sanitize($_POST['id_number']);

    $stmt = $conn->prepare("SELECT u.*, sq.question1, sq.question2, sq.question3 
                               FROM users u 
                               JOIN security_questions sq ON u.id_number = sq.user_id 
                               WHERE u.id_number = ?");
    $stmt->bind_param("s", $id_number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
      $user = $result->fetch_assoc();
      $_SESSION['reset_user'] = $user;
      $step = 2;
    } else {
      $errors[] = "ID number not found";
    }
  } elseif ($step === 2) {
    // Step 2: Verify security questions
    if (isset($_SESSION['reset_user'])) {
      $a1 = $db->sanitize($_POST['answer1']);
      $a1r = $db->sanitize($_POST['answer1_repeat']);
      $a2 = $db->sanitize($_POST['answer2']);
      $a2r = $db->sanitize($_POST['answer2_repeat']);
      $a3 = $db->sanitize($_POST['answer3']);
      $a3r = $db->sanitize($_POST['answer3_repeat']);

      if ($a1 !== $a1r) {
        $errors[] = "Answer 1 does not match";
      }
      if ($a2 !== $a2r) {
        $errors[] = "Answer 2 does not match";
      }
      if ($a3 !== $a3r) {
        $errors[] = "Answer 3 does not match";
      }

      $answers = [$a1, $a2, $a3];

      if (empty($errors) && verifySecurityAnswers($_SESSION['reset_user']['id_number'], $answers)) {
        $_SESSION['reset_verified'] = true;
        $step = 3;
      } else {
        if (empty($errors)) {
          $errors[] = "One or more answers are incorrect";
        }
      }
    }
  } elseif ($step === 3) {
    // Step 3: Reset password
    if (isset($_SESSION['reset_verified']) && $_SESSION['reset_verified']) {
      $new_password = $_POST['new_password'];
      $confirm_password = $_POST['confirm_password'];

      if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match";
      } else {
        // Simple password strength check
        if (strlen($new_password) < 8) {
          $errors[] = "Password must be at least 8 characters long";
        } else {
          $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
          $user_id = $_SESSION['reset_user']['id_number'];

          $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id_number = ?");
          $stmt->bind_param("ss", $password_hash, $user_id);

          if ($stmt->execute()) {
            $success = true;
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['success_message'] = "Password reset successfully! You can now login with your new password.";
            header("Location: login.php");
            exit();
          } else {
            $errors[] = "Password reset failed";
          }
        }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | GymBros</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link
    href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Oswald:wght@500;600;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="../css/forgot-password.css">
</head>

<body>

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
        <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Log-in</a></li>
        <li><a href="register.php"><i class="fas fa-user-plus"></i> Registered</a></li>
      </ul>
    </div>
  </header>

  <div class="container">
    <div class="text-center" style="margin-bottom: 2rem;">
      <i class="fas fa-key" style="font-size: 3rem; color: #ff5e00;"></i>
      <h1>Password <span>Recovery</span></h1>
      <h2>Reset Your Password</h2>
    </div>

    <?php if (!empty($errors)): ?>
      <div class="error">
        <?php foreach ($errors as $error): ?>
          <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
      <form method="POST" action="?step=1">
        <div class="form-group">
          <label class="form-label">Enter Your ID Number</label>
          <input type="text" name="id_number" class="form-input" placeholder="Your ID number" required>
        </div>
        <button type="submit" class="btn">Continue</button>
      </form>

    <?php elseif ($step === 2 && isset($_SESSION['reset_user'])): ?>
      <form method="POST" action="?step=2">
        <div class="form-group">
          <label class="form-label"><?php echo htmlspecialchars(htmlspecialchars_decode($_SESSION['reset_user']['question1']), ENT_QUOTES, 'UTF-8'); ?></label>
          <div class="answers-row">
            <div class="input-with-icon" style="flex:1;">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="answer1" id="answer1" class="form-input" placeholder="Your answer" required>
              <span class="password-toggle" onclick="togglePassword('answer1')">
                <i class="fas fa-eye" id="answer1-icon"></i>
              </span>
            </div>
            <div class="input-with-icon" style="flex:1;">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="answer1_repeat" id="answer1_repeat" class="form-input" placeholder="Re-enter answer" required>
              <span class="password-toggle" onclick="togglePassword('answer1_repeat')">
                <i class="fas fa-eye" id="answer1_repeat-icon"></i>
              </span>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo htmlspecialchars(htmlspecialchars_decode($_SESSION['reset_user']['question2']), ENT_QUOTES, 'UTF-8'); ?></label>
          <div class="answers-row">
            <div class="input-with-icon" style="flex:1;">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="answer2" id="answer2" class="form-input" placeholder="Your answer" required>
              <span class="password-toggle" onclick="togglePassword('answer2')">
                <i class="fas fa-eye" id="answer2-icon"></i>
              </span>
            </div>
            <div class="input-with-icon" style="flex:1;">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="answer2_repeat" id="answer2_repeat" class="form-input" placeholder="Re-enter answer" required>
              <span class="password-toggle" onclick="togglePassword('answer2_repeat')">
                <i class="fas fa-eye" id="answer2_repeat-icon"></i>
              </span>
            </div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label"><?php echo htmlspecialchars(htmlspecialchars_decode($_SESSION['reset_user']['question3']), ENT_QUOTES, 'UTF-8'); ?></label>
          <div class="answers-row">
            <div class="input-with-icon" style="flex:1;">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="answer3" id="answer3" class="form-input" placeholder="Your answer" required>
              <span class="password-toggle" onclick="togglePassword('answer3')">
                <i class="fas fa-eye" id="answer3-icon"></i>
              </span>
            </div>
            <div class="input-with-icon" style="flex:1;">
              <i class="fas fa-key input-icon"></i>
              <input type="password" name="answer3_repeat" id="answer3_repeat" class="form-input" placeholder="Re-enter answer" required>
              <span class="password-toggle" onclick="togglePassword('answer3_repeat')">
                <i class="fas fa-eye" id="answer3_repeat-icon"></i>
              </span>
            </div>
          </div>
        </div>
        <button type="submit" class="btn">Verify Answers</button>
      </form>

    <?php elseif ($step === 3 && isset($_SESSION['reset_verified'])): ?>
      <form method="POST" action="?step=3">
        <div class="form-group">
          <label class="form-label">New Password</label>
          <div class="input-with-icon">
            <i class="fas fa-key input-icon"></i>
            <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Enter new password" required>
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
            <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Confirm new password" required>
            <span class="password-toggle" onclick="togglePassword('confirm_password')">
              <i class="fas fa-eye" id="confirm_password-icon"></i>
            </span>
          </div>
          <div class="password-match" id="password-match" style="display:none;"></div>
        </div>
        <button type="submit" class="btn">Reset Password</button>
      </form>
    <?php endif; ?>

    <div class="text-center" style="margin-top: 2rem;">
      <p>Remember your password? <a href="login.php" style="color: #ff5e00;">Login here</a></p>
    </div>
  </div>
  <script src="../js/validation.js"></script>
  <footer style="margin-top:40px;padding:16px 0;text-align:center;color:#9ca3af;font-family:'Montserrat',sans-serif;border-top:1px solid #2d3748;">
    &copy; <?php echo date('Y'); ?> GymBros. All rights reserved.
  </footer>
</body>

</html>