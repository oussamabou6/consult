<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to submit a reply.']);
    exit();
}

$userId = $_SESSION['user_id'];

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    if (!isset($_POST['message_id']) || !isset($_POST['reply_text']) || empty($_POST['reply_text'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
        exit();
    }
    
    $messageId = intval($_POST['message_id']);
    $replyText = trim($_POST['reply_text']);
    
    // Verify that the message belongs to the user
    $checkMessageQuery = "SELECT id FROM support_messages WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkMessageQuery);
    $checkStmt->bind_param("ii", $messageId, $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'You do not have permission to reply to this message.']);
        exit();
    }
    
    // Insert the reply
    $insertQuery = "INSERT INTO support_message_replies (message_id, user_id, reply_text, created_at) VALUES (?, ?, ?, NOW())";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param("iis", $messageId, $userId, $replyText);
    
    if ($insertStmt->execute()) {
        // Update the message status to in-progress if it's pending
        $updateQuery = "UPDATE support_messages SET status = 'in-progress', updated_at = NOW() WHERE id = ? AND status = 'pending'";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("i", $messageId);
        $updateStmt->execute();
        
        // Create admin notification
        $notifyQuery = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message, related_id, created_at) 
                        VALUES (?, 0, 'support_reply', 'New reply to support message #" . $messageId . "', ?, NOW())";
        $notifyStmt = $conn->prepare($notifyQuery);
        $notifyStmt->bind_param("ii", $userId, $messageId);
        $notifyStmt->execute();
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Reply submitted successfully.']);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Failed to submit reply. Please try again.']);
    }
} else {
    // Not a POST request
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>
