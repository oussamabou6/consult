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

// Check if consultation ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Consultation ID is required']);
    exit;
}

$consultation_id = intval($_GET['id']);

// Get consultation details
$consultation_query = "SELECT c.*, 
                      client.full_name AS client_name, 
                      expert.full_name AS expert_name
                      FROM consultations c
                      JOIN users client ON c.client_id = client.id
                      JOIN users expert ON c.expert_id = expert.id
                      WHERE c.id = ?";
$consultation_stmt = $conn->prepare($consultation_query);
$consultation_stmt->bind_param("i", $consultation_id);
$consultation_stmt->execute();
$consultation_result = $consultation_stmt->get_result();

if ($consultation_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Consultation not found']);
    exit;
}

$consultation = $consultation_result->fetch_assoc();

// Get chat messages
$messages_query = "SELECT cm.*, 
                  sender.full_name AS sender_name,
                  receiver.full_name AS receiver_name
                  FROM chat_messages cm
                  JOIN users sender ON cm.sender_id = sender.id
                  JOIN users receiver ON cm.receiver_id = receiver.id
                  WHERE cm.consultation_id = ?
                  ORDER BY cm.created_at ASC";
$messages_stmt = $conn->prepare($messages_query);
$messages_stmt->bind_param("i", $consultation_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();

$messages = [];
while ($message = $messages_result->fetch_assoc()) {
    $messages[] = $message;
}

// Return success response with consultation and messages
header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'consultation' => $consultation,
    'messages' => $messages
]);
exit;
?>
