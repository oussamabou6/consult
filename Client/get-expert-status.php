<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Query to get all experts with their current status
$expertsQuery = "SELECT id, status FROM users WHERE role = 'expert'";
$expertsResult = $conn->query($expertsQuery);

$experts = [];

if ($expertsResult && $expertsResult->num_rows > 0) {
    while ($row = $expertsResult->fetch_assoc()) {
        $experts[] = [
            'id' => $row['id'],
            'status' => $row['status']
        ];
    }
}

// Return the experts data as JSON
header('Content-Type: application/json');
echo json_encode(['experts' => $experts]);
?>
