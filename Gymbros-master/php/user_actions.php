<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

// Check authentication
if (!Auth::isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit();
}

if (Auth::needsFirstLoginSetup()) {
    echo json_encode(['success' => false, 'message' => 'Please complete your account setup (password change & security questions) first.']);
    exit();
}

$currentUser = $_SESSION['user'];
$userId = $currentUser['id_number'];

// Read input (JSON or POST)
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true) ?: $_POST;

$action = $input['action'] ?? '';
$csrfToken = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

if (!Security::verifyCSRFToken($csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid security CSRF token. Please refresh the page.']);
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Helper to calculate user live fitness stats
if (!function_exists('getUserStats')) {
function getUserStats($conn, $userId) {
    // Total workouts and calories
    $stmt = $conn->prepare("SELECT COUNT(*) as total_workouts, COALESCE(SUM(calories_burned), 0) as total_calories FROM user_workouts WHERE user_id = ?");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Calculate real workout streak
    $streak = 0;
    $stmt = $conn->prepare("SELECT DISTINCT workout_date FROM user_workouts WHERE user_id = ? ORDER BY workout_date DESC");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $dates = [];
    while ($row = $res->fetch_assoc()) {
        $dates[] = $row['workout_date'];
    }
    $stmt->close();

    if (!empty($dates)) {
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $checkDate = in_array($today, $dates) ? $today : (in_array($yesterday, $dates) ? $yesterday : null);

        if ($checkDate) {
            $curr = new DateTime($checkDate);
            $streak = 1;
            while (true) {
                $curr->modify('-1 day');
                $prevDateStr = $curr->format('Y-m-d');
                if (in_array($prevDateStr, $dates)) {
                    $streak++;
                } else {
                    break;
                }
            }
        }
    }

    // Latest BMI & weight
    $latestMetric = null;
    $stmt = $conn->prepare("SELECT * FROM user_body_metrics WHERE user_id = ? ORDER BY recorded_at DESC LIMIT 1");
    $stmt->bind_param("s", $userId);
    $stmt->execute();
    $metricRes = $stmt->get_result();
    if ($metricRes && $metricRes->num_rows > 0) {
        $latestMetric = $metricRes->fetch_assoc();
    }
    $stmt->close();

    return [
        'total_workouts' => (int)$stats['total_workouts'],
        'total_calories' => (int)$stats['total_calories'],
        'streak_days' => $streak,
        'latest_metric' => $latestMetric
    ];
}
}

switch ($action) {
    case 'log_workout':
        $workout_name = $db->sanitize($input['workout_name'] ?? '');
        $muscle_group = $db->sanitize($input['muscle_group'] ?? 'Full Body');
        $duration_minutes = max(1, (int)($input['duration_minutes'] ?? 30));
        $calories_burned = max(1, (int)($input['calories_burned'] ?? 150));
        $sets_count = max(1, (int)($input['sets_count'] ?? 3));
        $reps_count = max(1, (int)($input['reps_count'] ?? 10));
        $weight_lifted = (float)($input['weight_lifted'] ?? 0);
        $notes = $db->sanitize($input['notes'] ?? '');
        $workout_date = !empty($input['workout_date']) ? $db->sanitize($input['workout_date']) : date('Y-m-d');

        if (empty($workout_name)) {
            echo json_encode(['success' => false, 'message' => 'Please enter a workout or exercise name.']);
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO user_workouts (user_id, workout_name, muscle_group, duration_minutes, calories_burned, sets_count, reps_count, weight_lifted, notes, workout_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssiiiidss", $userId, $workout_name, $muscle_group, $duration_minutes, $calories_burned, $sets_count, $reps_count, $weight_lifted, $notes, $workout_date);
        
        if ($stmt->execute()) {
            $insertedId = $stmt->insert_id;
            $stmt->close();
            ActivityLogger::log('LOG_WORKOUT', "Logged workout: '{$workout_name}' ({$muscle_group}, {$duration_minutes} mins, {$calories_burned} kcal, {$sets_count} sets x {$reps_count} reps).", 'Fitness Tracking');
            $stats = getUserStats($conn, $userId);
            echo json_encode([
                'success' => true,
                'message' => 'Workout logged successfully! Keep pushing!',
                'workout_id' => $insertedId,
                'stats' => $stats
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to record workout: ' . $conn->error]);
        }
        break;

    case 'delete_workout':
        $workout_id = (int)($input['workout_id'] ?? 0);
        if ($workout_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid workout entry.']);
            exit();
        }

        $stmt = $conn->prepare("DELETE FROM user_workouts WHERE id = ? AND user_id = ?");
        $stmt->bind_param("is", $workout_id, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            ActivityLogger::log('DELETE_WORKOUT', "Deleted workout entry #{$workout_id}.", 'Fitness Tracking');
            $stats = getUserStats($conn, $userId);
            echo json_encode(['success' => true, 'message' => 'Workout entry deleted.', 'stats' => $stats]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Workout not found or already removed.']);
        }
        break;

    case 'get_workouts':
        $limit = min(50, max(1, (int)($input['limit'] ?? 15)));
        $stmt = $conn->prepare("SELECT * FROM user_workouts WHERE user_id = ? ORDER BY workout_date DESC, created_at DESC LIMIT ?");
        $stmt->bind_param("si", $userId, $limit);
        $stmt->execute();
        $res = $stmt->get_result();
        $workouts = [];
        while ($row = $res->fetch_assoc()) {
            $workouts[] = $row;
        }
        $stmt->close();
        echo json_encode(['success' => true, 'workouts' => $workouts]);
        break;

    case 'book_class':
        $class_id = (int)($input['class_id'] ?? 0);
        $booking_date = !empty($input['booking_date']) ? $db->sanitize($input['booking_date']) : date('Y-m-d');

        if ($class_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please select a valid fitness class.']);
            exit();
        }

        // Verify class exists & get max capacity
        $stmt = $conn->prepare("SELECT * FROM gym_classes WHERE id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $classRes = $stmt->get_result();
        if ($classRes->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Class not found.']);
            exit();
        }
        $classInfo = $classRes->fetch_assoc();
        $stmt->close();

        // Check if user already booked this class for the same date
        $stmt = $conn->prepare("SELECT id FROM class_bookings WHERE user_id = ? AND class_id = ? AND booking_date = ? AND status = 'booked'");
        $stmt->bind_param("sis", $userId, $class_id, $booking_date);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'You already have an active booking for this class on this date.']);
            exit();
        }
        $stmt->close();

        // Check current capacity
        $stmt = $conn->prepare("SELECT COUNT(*) as booked_count FROM class_bookings WHERE class_id = ? AND booking_date = ? AND status = 'booked'");
        $stmt->bind_param("is", $class_id, $booking_date);
        $stmt->execute();
        $currentBooked = (int)$stmt->get_result()->fetch_assoc()['booked_count'];
        $stmt->close();

        if ($currentBooked >= $classInfo['max_capacity']) {
            echo json_encode(['success' => false, 'message' => 'Sorry, this class is fully booked for this date.']);
            exit();
        }

        // Book slot
        $stmt = $conn->prepare("INSERT INTO class_bookings (user_id, class_id, booking_date, status) VALUES (?, ?, ?, 'booked')");
        $stmt->bind_param("sis", $userId, $class_id, $booking_date);
        if ($stmt->execute()) {
            $bookingId = $stmt->insert_id;
            $stmt->close();
            ActivityLogger::log('BOOK_CLASS', "Booked class '{$classInfo['class_name']}' for {$booking_date}.", 'Class Booking');
            echo json_encode([
                'success' => true,
                'message' => 'Confirmed! Slot reserved for ' . htmlspecialchars($classInfo['class_name']) . '.',
                'booking_id' => $bookingId,
                'class_name' => $classInfo['class_name']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking failed: ' . $conn->error]);
        }
        break;

    case 'cancel_booking':
        $booking_id = (int)($input['booking_id'] ?? 0);
        if ($booking_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking ID.']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE class_bookings SET status = 'cancelled' WHERE id = ? AND user_id = ?");
        $stmt->bind_param("is", $booking_id, $userId);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            ActivityLogger::log('CANCEL_BOOKING', "Cancelled class booking #{$booking_id}.", 'Class Booking');
            echo json_encode(['success' => true, 'message' => 'Class booking successfully cancelled.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Booking not found or already cancelled.']);
        }
        break;

    case 'log_body_metric':
        $weight_kg = (float)($input['weight_kg'] ?? 0);
        $height_cm = (float)($input['height_cm'] ?? 0);
        $target_weight_kg = !empty($input['target_weight_kg']) ? (float)$input['target_weight_kg'] : null;
        $fitness_goal = $db->sanitize($input['fitness_goal'] ?? 'Muscle Building & Fitness');
        $body_fat = !empty($input['body_fat_percentage']) ? (float)$input['body_fat_percentage'] : null;
        $notes = $db->sanitize($input['notes'] ?? '');

        if ($weight_kg <= 20 || $weight_kg > 300) {
            echo json_encode(['success' => false, 'message' => 'Please enter a realistic weight in kg (20 - 300 kg).']);
            exit();
        }
        if ($height_cm <= 50 || $height_cm > 260) {
            echo json_encode(['success' => false, 'message' => 'Please enter a realistic height in cm (50 - 260 cm).']);
            exit();
        }

        // Calculate BMI: weight / (height_in_meters ^ 2)
        $height_m = $height_cm / 100;
        $bmi = round($weight_kg / ($height_m * $height_m), 2);

        // Determine BMI Category
        $category = 'Normal Weight';
        if ($bmi < 18.5) {
            $category = 'Underweight';
        } elseif ($bmi < 25.0) {
            $category = 'Normal Weight';
        } elseif ($bmi < 30.0) {
            $category = 'Overweight';
        } else {
            $category = 'Obese';
        }

        $stmt = $conn->prepare("INSERT INTO user_body_metrics (user_id, weight_kg, height_cm, target_weight_kg, fitness_goal, bmi, body_fat_percentage, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sdddsdds", $userId, $weight_kg, $height_cm, $target_weight_kg, $fitness_goal, $bmi, $body_fat, $notes);
        
        if ($stmt->execute()) {
            $stmt->close();

            // Update fitness goal in users table
            $stmtGoal = $conn->prepare("UPDATE users SET fitness_goal = ? WHERE id_number = ?");
            $stmtGoal->bind_param("ss", $fitness_goal, $userId);
            $stmtGoal->execute();
            $stmtGoal->close();
            $_SESSION['user']['fitness_goal'] = $fitness_goal;

            ActivityLogger::log('LOG_BODY_METRIC', "Logged body metrics: Weight {$weight_kg}kg, Height {$height_cm}cm, BMI {$bmi} ({$category}).", 'Fitness Tracking');

            echo json_encode([
                'success' => true,
                'message' => 'Body metrics & BMI recorded successfully!',
                'bmi' => $bmi,
                'category' => $category,
                'weight_kg' => $weight_kg,
                'height_cm' => $height_cm,
                'target_weight_kg' => $target_weight_kg
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to save body metrics: ' . $conn->error]);
        }
        break;

    case 'update_profile':
        $phone_number = $db->sanitize($input['phone_number'] ?? '');
        $bio = $db->sanitize($input['bio'] ?? '');
        $fitness_goal = $db->sanitize($input['fitness_goal'] ?? 'Muscle Building & Fitness');
        $purok_street = $db->sanitize($input['purok_street'] ?? '');
        $barangay = $db->sanitize($input['barangay'] ?? '');
        $city_municipality = $db->sanitize($input['city_municipality'] ?? '');
        $province = $db->sanitize($input['province'] ?? '');
        $zip_code = $db->sanitize($input['zip_code'] ?? '');

        $stmt = $conn->prepare("UPDATE users SET phone_number = ?, bio = ?, fitness_goal = ?, purok_street = ?, barangay = ?, city_municipality = ?, province = ?, zip_code = ? WHERE id_number = ?");
        $stmt->bind_param("sssssssss", $phone_number, $bio, $fitness_goal, $purok_street, $barangay, $city_municipality, $province, $zip_code, $userId);

        if ($stmt->execute()) {
            $stmt->close();
            ActivityLogger::log('UPDATE_PROFILE', "Updated personal profile and contact information.", 'Profile');
            // Sync session
            $_SESSION['user']['phone_number'] = $phone_number;
            $_SESSION['user']['bio'] = $bio;
            $_SESSION['user']['fitness_goal'] = $fitness_goal;
            $_SESSION['user']['purok_street'] = $purok_street;
            $_SESSION['user']['barangay'] = $barangay;
            $_SESSION['user']['city_municipality'] = $city_municipality;
            $_SESSION['user']['province'] = $province;
            $_SESSION['user']['zip_code'] = $zip_code;

            echo json_encode([
                'success' => true,
                'message' => 'Profile details updated successfully!',
                'user' => $_SESSION['user']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $conn->error]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid or unknown user action']);
        break;
}
