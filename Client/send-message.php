<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Redirect if not logged in
if ($isLoggedIn) {
    if ($_SESSION['user_role'] != 'client') {
        header("Location: ../config/logout.php");
        exit;
    }
}
// Get user profile if logged in
$userProfile = null;
if ($isLoggedIn) {
    $userProfileQuery = "SELECT * FROM user_profiles WHERE user_id = $userId";
    $userProfileResult = $conn->query($userProfileQuery);
    
    if ($userProfileResult && $userProfileResult->num_rows > 0) {
        $userProfile = $userProfileResult->fetch_assoc();
    }
}

// Handle message submission
$messageSuccess = '';
$messageError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $expertId = $conn->real_escape_string($_POST['expert_id']);
    $messageContent = $conn->real_escape_string($_POST['message_content']);
    
    // Validate expert exists
    $expertQuery = "SELECT id, full_name FROM users WHERE id = $expertId AND role = 'expert'";
    $expertResult = $conn->query($expertQuery);
    
    if ($expertResult && $expertResult->num_rows > 0) {
        $expert = $expertResult->fetch_assoc();
        
        // Check if a chat session already exists
        $chatSessionQuery = "SELECT id FROM chat_sessions 
                           WHERE (expert_id = $expertId AND client_id = $userId) 
                           OR (expert_id = $userId AND client_id = $expertId)";
        $chatSessionResult = $conn->query($chatSessionQuery);
        
        if ($chatSessionResult && $chatSessionResult->num_rows > 0) {
            $chatSession = $chatSessionResult->fetch_assoc();
            $chatSessionId = $chatSession['id'];
        } else {
            // Create a new consultation record first (required for chat_sessions)
            $createConsultationQuery = "INSERT INTO consultations (client_id, expert_id, consultation_date, consultation_time, duration, status, notes, consultation_type) 
                                      VALUES ($userId, $expertId, CURDATE(), CURTIME(), 0, 'pending', '$messageContent', 'video')";
            
            if ($conn->query($createConsultationQuery)) {
                $consultationId = $conn->insert_id;
                
                // Create new chat session
                $createChatSessionQuery = "INSERT INTO chat_sessions (consultation_id, expert_id, client_id, started_at, status) 
                                         VALUES ($consultationId, $expertId, $userId, NOW(), 'active')";
                
                if ($conn->query($createChatSessionQuery)) {
                    $chatSessionId = $conn->insert_id;
                } else {
                    $messageError = "Failed to create chat session. Please try again.";
                    // Redirect back with error
                    header("Location: find-experts.php?message_error=" . urlencode($messageError));
                    exit();
                }
            } else {
                $messageError = "Failed to create consultation. Please try again.";
                // Redirect back with error
                header("Location: find-experts.php?message_error=" . urlencode($messageError));
                exit();
            }
        }
        
        // Insert the message
        $insertMessageQuery = "INSERT INTO chat_messages (sender_id, receiver_id, message, is_read, sender_type, created_at, chat_session_id) 
                             VALUES ($userId, $expertId, '$messageContent', 0, 'client', NOW(), $chatSessionId)";
        
        if ($conn->query($insertMessageQuery)) {
            $messageSuccess = "Your message has been sent to " . $expert['full_name'] . ".";
            
            // Send notification to expert
            $notificationQuery = "INSERT INTO expert_notifications (user_id, message, is_read, created_at) 
                                VALUES ($expertId, 'You have received a new message.', 0, NOW())";
            $conn->query($notificationQuery);
            
            // Redirect to messages page
            header("Location: messages.php?chat_session_id=$chatSessionId&message_success=" . urlencode($messageSuccess));
            exit();
        } else {
            $messageError = "Failed to send message. Please try again.";
            // Redirect back with error
            header("Location: find-experts.php?message_error=" . urlencode($messageError));
            exit();
        }
    } else {
        $messageError = "Expert not found. Please try again.";
        // Redirect back with error
        header("Location: find-experts.php?message_error=" . urlencode($messageError));
        exit();
    }
} else {
    // If accessed directly without POST data, redirect to find experts
    header("Location: find-experts.php");
    exit();
}
?>
