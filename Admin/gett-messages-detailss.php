<?php
// Start the session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Check if message ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message ID is required']);
    exit;
}

$message_id = intval($_GET['id']);

// Get message details with user information
$query = "SELECT sm.*, u.full_name, u.email, up.phone, up.profile_image, 
          DATE_FORMAT(sm.created_at, '%d/%m/%Y %H:%i') as created_at_formatted
          FROM support_messages sm
          JOIN users u ON sm.user_id = u.id
          LEFT JOIN user_profiles up ON u.id = up.user_id
          WHERE sm.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $message = $result->fetch_assoc();
    
    // Check for attachments if you have an attachments table
    $attachments = [];
    
    // Example: If you have a support_message_attachments table
    // Uncomment and modify this code if you have attachments
    /*
    $attachments_query = "SELECT * FROM support_message_attachments WHERE message_id = ?";
    $attachments_stmt = $conn->prepare($attachments_query);
    $attachments_stmt->bind_param("i", $message_id);
    $attachments_stmt->execute();
    $attachments_result = $attachments_stmt->get_result();
    
    if ($attachments_result && $attachments_result->num_rows > 0) {
        while ($attachment = $attachments_result->fetch_assoc()) {
            $attachments[] = $attachment;
        }
    }
    */
    
    // Return success response with message details
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'message' => $message,
        'attachments' => $attachments
    ]);
} else {
    // Return error response if message not found
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Message not found']);
}
