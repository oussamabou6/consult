<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$consultation_id = isset($_GET['consultation_id']) ? intval($_GET['consultation_id']) : 0;

// Validate input
if ($consultation_id <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Invalid consultation ID']);
    exit;
}

// Check if user has access to this consultation
$access_sql = "SELECT id FROM consultations WHERE id = ? AND (client_id = ? OR expert_id = ?)";
$access_stmt = $conn->prepare($access_sql);
$access_stmt->bind_param("iii", $consultation_id, $user_id, $user_id);
$access_stmt->execute();
$access_result = $access_stmt->get_result();

if ($access_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

// Get consultation status
$status_sql = "SELECT c.status as consultation_status, 
              cs.status as chat_status,
              ct.status as timer_status,
              TIMESTAMPDIFF(SECOND, ct.started_at, NOW()) as elapsed_seconds,
              c.duration,
              u.status as expert_status
              FROM consultations c 
              LEFT JOIN chat_sessions cs ON c.id = cs.consultation_id
              LEFT JOIN chat_timers ct ON cs.id = ct.chat_session_id
              LEFT JOIN users u ON c.expert_id = u.id
              WHERE c.id = ?";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("i", $consultation_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();

if ($status_result && $status_result->num_rows > 0) {
    $status_data = $status_result->fetch_assoc();
    
    // Calculate remaining time
    $elapsed_seconds = $status_data['elapsed_seconds'] ?? 0;
    $total_seconds = $status_data['duration'] * 60; // Convert minutes to seconds
    $remaining_seconds = max(0, $total_seconds - $elapsed_seconds);
    
    $status_data['remaining_seconds'] = $remaining_seconds;
    $status_data['minutes'] = floor($remaining_seconds / 60);
    $status_data['seconds'] = $remaining_seconds % 60;
    $status_data['formatted_time'] = sprintf('%02d:%02d', $status_data['minutes'], $status_data['seconds']);
    $status_data['timestamp'] = date('Y-m-d H:i:s');
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'status' => $status_data]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Consultation not found']);
}

// Close database connection
$conn->close();
?>
