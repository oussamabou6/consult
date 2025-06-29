<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';

// Initialize response array
$response = [
    'success' => false,
    'account' => null,
    'message' => ''
];

// Check if ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $account_id = (int)$_GET['id'];
    
    // Prepare and execute query
    $query = "SELECT * FROM admin_bank_accounts WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $account_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        // Fetch account data
        $account = $result->fetch_assoc();
        
        // Set success response
        $response['success'] = true;
        $response['account'] = $account;
    } else {
        $response['message'] = 'Bank account not found';
    }
} else {
    $response['message'] = 'Invalid account ID';
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>
