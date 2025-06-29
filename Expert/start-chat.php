<?php
// start-chat.php
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in, redirect to login page
    header("Location: ../config/logout.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$success_message = "";
$error_message = "";

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate and sanitize inputs
    if (isset($_POST["expert_id"]) && isset($_POST["chat_subject"]) && isset($_POST["initial_message"])) {
        $expert_id = sanitize_input($_POST["expert_id"]);
        $chat_subject = sanitize_input($_POST["chat_subject"]);
        $initial_message = sanitize_input($_POST["initial_message"]);
        
        // Validate expert exists
        $check_expert_sql = "SELECT id FROM users WHERE id = ? AND role = 'expert'";
        $check_stmt = $conn->prepare($check_expert_sql);
        $check_stmt->bind_param("i", $expert_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 0) {
            $error_message = "Invalid expert selected.";
        } else {
            // Check if conversation already exists
            $check_conv_sql = "SELECT id FROM chat_conversations 
                              WHERE (user_id = ? AND expert_id = ?) OR (user_id = ? AND expert_id = ?)";
            $check_conv_stmt = $conn->prepare($check_conv_sql);
            $check_conv_stmt->bind_param("iiii", $user_id, $expert_id, $expert_id, $user_id);
            $check_conv_stmt->execute();
            $check_conv_result = $check_conv_stmt->get_result();
            
            if ($check_conv_result->num_rows > 0) {
                // Conversation exists, get the conversation ID
                $conversation = $check_conv_result->fetch_assoc();
                $conversation_id = $conversation['id'];
            } else {
                // Create new conversation
                $create_conv_sql = "INSERT INTO chat_conversations (user_id, expert_id, subject, created_at) 
                                   VALUES (?, ?, ?, NOW())";
                $create_conv_stmt = $conn->prepare($create_conv_sql);
                $create_conv_stmt->bind_param("iis", $user_id, $expert_id, $chat_subject);
                
                if ($create_conv_stmt->execute()) {
                    $conversation_id = $conn->insert_id;
                } else {
                    $error_message = "Failed to create conversation. Please try again.";
                }
            }
            
            // If we have a conversation ID, add the message
            if (isset($conversation_id) && empty($error_message)) {
                // Modified query to match your table structure
                $add_msg_sql = "INSERT INTO chat_messages (sender_id, receiver_id, message, message_type, is_read, created_at) 
                               VALUES (?, ?, ?, 'text', 0, NOW())";
                $add_msg_stmt = $conn->prepare($add_msg_sql);
                $add_msg_stmt->bind_param("iis", $user_id, $expert_id, $initial_message);
                
                if ($add_msg_stmt->execute()) {
                    // Success! Redirect to the chat page
                    $_SESSION['success_message'] = "Message sent successfully!";
                    header("Location: expert-discussions.php?conversation=" . $conversation_id);
                    exit();
                } else {
                    $error_message = "Failed to send message. Please try again.";
                }
            }
        }
    } else {
        $error_message = "All fields are required.";
    }
}

// If we reach here, there was an error
$_SESSION['error_message'] = $error_message;
header("Location: expert-experts.php");
exit();
?>