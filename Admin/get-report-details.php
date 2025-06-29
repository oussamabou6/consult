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

// Check if report ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report ID is required']);
    exit;
}

$report_id = intval($_GET['id']);

// Get report details
$report_query = "SELECT r.*, 
                reporter.full_name AS reporter_name, 
                reporter.email AS reporter_email,
                reporter.role AS reporter_type,
                reported.full_name AS reported_name,
                reported.email AS reported_email,
                c.consultation_date, c.consultation_time, c.duration
                FROM reports r
                JOIN users reporter ON r.reporter_id = reporter.id
                JOIN users reported ON r.reported_id = reported.id
                LEFT JOIN consultations c ON r.consultation_id = c.id
                WHERE r.id = ?";
$report_stmt = $conn->prepare($report_query);
$report_stmt->bind_param("i", $report_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();

if ($report_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Report not found']);
    exit;
}

$report = $report_result->fetch_assoc();

// Return success response with report details
header('Content-Type: application/json');
echo json_encode(['success' => true, 'report' => $report]);
exit;
?>
