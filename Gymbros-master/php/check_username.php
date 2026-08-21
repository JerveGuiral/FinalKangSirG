<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $db = new Database();
  $conn = $db->getConnection();

  $username = $db->sanitize($_POST['username']);

  $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
  $stmt->bind_param("s", $username);
  $stmt->execute();
  $result = $stmt->get_result();

  echo json_encode(['exists' => $result->num_rows > 0]);
  exit();
}

echo json_encode(['exists' => false]);
?>