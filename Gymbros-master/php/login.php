<?php
require_once '../includes/config.php';
require_once '../includes/security.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (Auth::isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$errors = [];
$loginAttempts = 0;
$lockoutTime = 0;
$showForgotPassword = false;

// Get username from POST or from session for persistence
$username_from_post = $_POST['username'] ?? '';
$current_username = $username_from_post;

// If no POST data, try to get the username from session (for page refresh)
if (empty($current_username) && isset($_SESSION['last_login_attempt'])) {
    $current_username = $_SESSION['last_login_attempt'];
}

// ALWAYS check lockout status for the current username
if (!empty($current_username)) {
    $consecutiveAttempts = Security::getConsecutiveFailedAttempts($current_username);
    $loginAttempts = $consecutiveAttempts;

    // NEW: Only get lockout time if we should show it (at thresholds)
    $shouldShowLockout = Security::shouldShowLockout($current_username);
    $lockoutTime = $shouldShowLockout ? Security::getLockoutTime($current_username) : 0;

    $showForgotPassword = ($consecutiveAttempts >= 2);

    // Debug info
    error_log("User: $current_username, Consecutive: $consecutiveAttempts, Show Lockout: " . ($shouldShowLockout ? 'Yes' : 'No') . ", Lockout Time: $lockoutTime");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Security::verifyCSRFToken($_POST['csrf_token'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Store the username in session for refresh persistence
    $_SESSION['last_login_attempt'] = $username;

    // Add input validation
    if (strlen($username) < 3 || strlen($username) > 20 || !preg_match('/^[a-zA-Z0-9]+$/', $username)) {
        $errors[] = "Username must be 3-20 characters (letters and numbers only)";
    }

    if (strlen($password) < 8 || strlen($password) > 128) {
        $errors[] = "Password must be 8-128 characters long";
    }

    // Check if user is locked out
    $lockoutTime = Security::getLockoutTime($username);
    if ($lockoutTime > 0) {
        $errors[] = "Too many failed attempts. Please try again in {$lockoutTime} seconds.";
    }
    // Only proceed if no validation errors and not locked out
    elseif (empty($errors)) {
        $db = new Database();
        $conn = $db->getConnection();

        // Log login attempt
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $success = false;

        // Authenticate user
        $user = Auth::authenticate($username, $password);
        if ($user) {
            $userStatus = $user['status'] ?? 'approved';
            if ($userStatus === 'pending') {
                $errors[] = "Your account registration is pending approval by an administrator.";
            } elseif ($userStatus === 'blocked') {
                $errors[] = "Your account has been blocked by an administrator.";
            } else {
                $success = true;
                $_SESSION['user'] = $user;
                $_SESSION['login_time'] = time();

                // Clear the stored username on successful login
                unset($_SESSION['last_login_attempt']);

                // Log successful attempt
                $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, 1)");
                $stmt->bind_param("ss", $username, $ip_address);
                $stmt->execute();

                header("Location: dashboard.php");
                exit();
            }
        } else {
            $errors[] = "Invalid username or password";

            // Log failed attempt
            $stmt = $conn->prepare("INSERT INTO login_attempts (username, ip_address, success) VALUES (?, ?, 0)");
            $stmt->bind_param("ss", $username, $ip_address);
            $stmt->execute();

            // Get updated consecutive attempts
            $consecutiveAttempts = Security::getConsecutiveFailedAttempts($username);
            $loginAttempts = $consecutiveAttempts;

            // NEW: Only show lockout if at threshold
            $shouldShowLockout = Security::shouldShowLockout($username);
            $lockoutTime = $shouldShowLockout ? Security::getLockoutTime($username) : 0;

            $showForgotPassword = ($consecutiveAttempts >= 2);

            error_log("Login failed. Consecutive: $consecutiveAttempts, Show Lockout: " . ($shouldShowLockout ? 'Yes' : 'No'));
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
    <title>Login | GymBros</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&family=Oswald:wght@500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/auth.css">
</head>

<body>
    <header>
        <div class="logo">
            <h1>Gym<span>Bros</span></h1>
        </div>
        <div class="navBar">
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="register.php"><i class="fas fa-user-plus"></i> Registered</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <div class="form-logo">
            <i class="fas fa-dumbbell"></i>
            <h1>Gym<span>Bros</span></h1>
        </div>

        <h2 class="form-title">Login to Your Account</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-message">
                <?php foreach ($errors as $error): ?>
                    <p><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success-message">
                <p><i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success_message'];
                    unset($_SESSION['success_message']); ?>
                </p>
            </div>
        <?php endif; ?>

        <form id="login-form" method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" id="lockout_time" value="<?php echo $lockoutTime; ?>">
            <input type="hidden" id="login_attempts" value="<?php echo $loginAttempts; ?>">

            <div class="form-group">
                <label class="form-label">Username</label>
                <div class="input-with-icon">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" name="username" id="username" class="form-input"
                        placeholder="Enter your username" required <?php echo $lockoutTime > 0 ? 'readonly' : ''; ?>
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="input-with-icon">
                    <i class="fas fa-key input-icon"></i>
                    <input type="password" name="password" id="password" class="form-input"
                        placeholder="Enter your password" required <?php echo $lockoutTime > 0 ? 'readonly' : ''; ?>>
                    <span class="password-toggle" onclick="togglePassword()">
                        <i class="fas fa-eye" id="password-icon"></i>
                    </span>
                </div>
            </div>

            <button type="submit" class="btn" id="login-btn" <?php echo $lockoutTime > 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-sign-in-alt"></i>
                <span id="login-text">Login</span>
                <span id="countdown" style="display: none;">Please wait <span id="timer">0</span>s</span>
            </button>

            <?php if ($showForgotPassword): ?>
                <div class="forgot-password">
                    <span>Forgot Password?</span>
                    <a href="forgot-password.php" id="forgot-password-link">
                        <i class="fas fa-question-circle"></i> Reset Here
                    </a>
                </div>
            <?php endif; ?>

            <div class="register-link">
                <p>Don't have an account? <a href="register.php" id="register-link">Register here</a></p>
            </div>
        </form>
    </div>

    <script src="../js/auth.js"></script>
    <script src="../js/timer.js"></script>
    <footer style="margin-top:40px;padding:16px 0;text-align:center;color:#9ca3af;font-family:'Montserrat',sans-serif;border-top:1px solid #2d3748;">
        &copy; <?php echo date('Y'); ?> GymBros. All rights reserved.
    </footer>
</body>

</html>