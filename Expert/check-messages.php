<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    // Return empty response if not logged in
    header('Content-Type: application/json');
    echo json_encode(['unread_count' => 0]);
    exit();
}

$user_id = $_SESSION["user_id"];
$response = ['unread_count' => 0];

// Check for unread messages
$unread_sql = "SELECT COUNT(*) as count FROM support_responses sr 
               JOIN support_messages sm ON sr.message_id = sm.id 
               WHERE sm.user_id = ? AND sr.is_read = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();

if ($unread_row = $unread_result->fetch_assoc()) {
    $response['unread_count'] = (int)$unread_row['count'];
}

// Close database connection
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);