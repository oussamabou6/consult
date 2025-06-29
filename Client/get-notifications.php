<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $response = [
        'success' => false,
        'message' => 'User not logged in',
        'notifications' => [],
        'count' => 0
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$userId = $_SESSION['user_id'];

// Get unread notification count
$countQuery = "SELECT COUNT(*) as count FROM client_notifications WHERE user_id = ? AND is_read = 0";
$countStmt = $conn->prepare($countQuery);
$countStmt->bind_param("i", $userId);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult->fetch_assoc();
$notificationCount = $countRow['count'];
$countStmt->close();

// Get recent notifications
$notificationsQuery = "SELECT * FROM client_notifications 
                      WHERE user_id = ? 
                      ORDER BY is_read ASC, created_at DESC
                      LIMIT 5";
$notificationsStmt = $conn->prepare($notificationsQuery);
$notificationsStmt->bind_param("i", $userId);
$notificationsStmt->execute();
$notificationsResult = $notificationsStmt->get_result();

$notifications = [];
while ($row = $notificationsResult->fetch_assoc()) {
    $notifications[] = [
        'id' => $row['id'],
        'message' => $row['message'],
        'created_at' => date('M d, H:i', strtotime($row['created_at'])),
        'is_read' => (bool)$row['is_read']
    ];
}
$notificationsStmt->close();

$response = [
    'success' => true,
    'notifications' => $notifications,
    'count' => $notificationCount
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
