<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get consultation IDs from request
$data = json_decode(file_get_contents('php://input'), true);
$consultationIds = isset($data['consultation_ids']) ? $data['consultation_ids'] : [];

if (empty($consultationIds)) {
    echo json_encode(['error' => 'No consultation IDs provided']);
    exit();
}

// Prepare the query with placeholders for each ID
$placeholders = implode(',', array_fill(0, count($consultationIds), '?'));
$query = "SELECT c.id, c.status, c.expert_id, u.full_name as expert_name, u.status as expert_status
          FROM consultations c
          JOIN users u ON c.expert_id = u.id
          WHERE c.client_id = ? AND c.id IN ($placeholders)";

// Prepare statement
$stmt = $conn->prepare($query);

// Bind parameters - first the user ID, then all consultation IDs
$types = 'i' . str_repeat('i', count($consultationIds));
$params = array_merge([$userId], $consultationIds);

// Create a reference array for bind_param
$bindParams = [];
$bindParams[] = &$types;
foreach ($params as $key => $value) {
    $bindParams[] = &$params[$key];
}

// Call bind_param with dynamic parameters
call_user_func_array([$stmt, 'bind_param'], $bindParams);

// Execute the query
$stmt->execute();
$result = $stmt->get_result();

$consultations = [];
while ($row = $result->fetch_assoc()) {
    $consultations[] = [
        'id' => $row['id'],
        'status' => $row['status'],
        'expert_id' => $row['expert_id'],
        'expert_name' => $row['expert_name'],
        'expert_status' => $row['expert_status']
    ];
}

// Return the results
echo json_encode([
    'consultations' => $consultations
]);

// Close database connection
$conn->close();
?>
