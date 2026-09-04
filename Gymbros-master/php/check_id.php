<?php
// check_id.php
require_once '../includes/config.php';
require_once '../includes/security.php';

header('Content-Type: application/json');

// Enable CORS for local development
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
  exit(0);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $input = file_get_contents('php://input');
  parse_str($input, $_POST);

  $id_number = $_POST['id_number'] ?? '';

  if (empty($id_number)) {
    echo json_encode(['available' => false, 'error' => 'ID number is required']);
    exit;
  }

  try {
    $db = new Database();
    $conn = $db->getConnection();

    // Check if ID exists
    $stmt = $conn->prepare("SELECT id_number FROM users WHERE id_number = ?");
    if ($stmt) {
      $stmt->bind_param("s", $id_number);
      $stmt->execute();
      $result = $stmt->get_result();

      $exists = $result->num_rows > 0;

      echo json_encode([
        'available' => !$exists,
        'exists' => $exists,
        'id_checked' => $id_number
      ]);

      $stmt->close();
    } else {
      echo json_encode(['available' => false, 'error' => 'Database statement failed']);
    }

    $db->close();

  } catch (Exception $e) {
    error_log("Error checking ID: " . $e->getMessage());
    echo json_encode(['available' => false, 'error' => 'Database error']);
  }
} else {
  echo json_encode(['available' => false, 'error' => 'Invalid request method']);
}
?>