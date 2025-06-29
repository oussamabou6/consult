<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

if (!$isLoggedIn) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Get the new status from the request
$newStatus = isset($_POST['status']) ? $_POST['status'] : null;

if (!$newStatus || !in_array($newStatus, ['Online', 'Offline', 'Busy'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Update user status in the database
$updateQuery = "UPDATE users SET status = '$newStatus' WHERE id = $userId";
$result = $conn->query($updateQuery);

if ($result) {
    $response = ['success' => true, 'message' => 'Status updated successfully'];
    
    // If status changed to Offline, cancel all pending consultations
    if ($newStatus == 'Offline') {
        // Get user's name
        $nameQuery = "SELECT full_name FROM users WHERE id = $userId";
        $nameResult = $conn->query($nameQuery);
        $expertName = ($nameResult && $nameResult->num_rows > 0) ? $nameResult->fetch_assoc()['full_name'] : 'Expert';
        
        // Get all pending consultations for this expert
        $pendingQuery = "SELECT c.*, u.full_name as client_name, u.email as client_email 
                        FROM consultations c 
                        JOIN users u ON c.client_id = u.id 
                        WHERE c.expert_id = $userId AND c.status = 'pending'";
        $pendingResult = $conn->query($pendingQuery);
        
        $cancelledConsultations = [];
        
        if ($pendingResult && $pendingResult->num_rows > 0) {
            while ($consultation = $pendingResult->fetch_assoc()) {
                // Update consultation status to cancelled
                $updateConsultationQuery = "UPDATE consultations SET 
                               status = 'cancelled', 
                               notes = CONCAT(IFNULL(notes, ''), ' Automatically cancelled because expert went offline.') 
                               WHERE id = " . $consultation['id'];
                $conn->query($updateConsultationQuery);
                
                // Refund client's balance
                $refundQuery = "UPDATE users SET balance = balance + " . $consultation['price'] . " WHERE id = " . $consultation['client_id'];
                $conn->query($refundQuery);
                
                // Add notification for client
                $notificationMessage = "Your consultation with $expertName was automatically cancelled because the expert went offline. Your balance has been refunded.";
                $notificationQuery = "INSERT INTO client_notifications (user_id, message, type, is_read, created_at) 
                                    VALUES (" . $consultation['client_id'] . ", '$notificationMessage', 'consultation_cancelled', 0, NOW())";
                $conn->query($notificationQuery);
                
                // Add to cancelled consultations array for response
                $cancelledConsultations[] = [
                    'id' => $consultation['id'],
                    'client_name' => $consultation['client_name']
                ];
            }
            
            $response['cancelled_consultations'] = $cancelledConsultations;
            $response['cancelled_count'] = count($cancelledConsultations);
        }
    }
    
    echo json_encode($response);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $conn->error]);
}
?>
