<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'message' => 'User not logged in'
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$userId = $_SESSION['user_id'];

// Get request data
$data = json_decode(file_get_contents('php://input'), true);

// Mark all unread notifications as read
$updateQuery = "UPDATE client_notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
$stmt = $conn->prepare($updateQuery);
$stmt->bind_param("i", $userId);
$result = $stmt->execute();

if ($result) {
    // Get the number of affected rows
    $affectedRows = $stmt->affected_rows;
    
    $response = [
        'success' => true,
        'message' => 'Notifications marked as read',
        'count' => $affectedRows
    ];
} else {
    $response = [
        'success' => false,
        'message' => 'Failed to mark notifications as read: ' . $conn->error
    ];
}

$stmt->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
