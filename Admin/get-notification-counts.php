
<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Return error if not logged in as admin
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

// Get notification counts
$unread_notifications = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];

// Prepare response
$response = [
    'unread_notifications' => (int)$unread_notifications,
    'pending_withdrawals' => (int)$pending_withdrawals,
    'pending_fund_requests' => (int)$pending_fund_requests,
    'pending_review_profiles' => (int)$pending_review_profiles,
    'pending_messages' => (int)$pending_messages,
    'pending_reports' => (int)$pending_reports
];

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
?>
