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
// Check if consultation ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid consultation ID.";
    header("Location: find-experts.php");
    exit();
}

$consultationId = intval($_GET['id']);

// Get consultation details - using simple query to avoid potential issues
$consultationQuery = "SELECT c.*, p.amount as payment_amount, p.id as payment_id, p.status as payment_status, 
                     u.full_name as expert_name, ep.id as profile_id 
                     FROM consultations c
                     LEFT JOIN payments p ON c.id = p.consultation_id
                     JOIN users u ON c.expert_id = u.id
                     JOIN expert_profiledetails ep ON u.id = ep.user_id
                     WHERE c.id = $consultationId AND c.client_id = $userId";

$consultationResult = $conn->query($consultationQuery);

if (!$consultationResult || $consultationResult->num_rows == 0) {
    $_SESSION['error_message'] = "Consultation not found or you don't have permission to cancel it.";
    header("Location: find-experts.php");
    exit();
}

$consultation = $consultationResult->fetch_assoc();

// Check if consultation is in a state that can be canceled (pending)
if ($consultation['status'] != 'pending') {
    $_SESSION['error_message'] = "Only pending consultations can be canceled.";
    header("Location: find-experts.php");
    exit();
}

// Start transaction to ensure all operations succeed or fail together
$conn->begin_transaction();

try {
    // Update consultation status to canceled
    $updateQuery = "UPDATE consultations SET status = 'canceled', canceled_at = NOW() WHERE id = $consultationId";
    if (!$conn->query($updateQuery)) {
        throw new Exception("Error canceling consultation: " . $conn->error);
    }

    // Update payment status to canceled if payment exists
    if (!empty($consultation['payment_id'])) {
        $paymentId = $consultation['payment_id'];
        $updatePaymentQuery = "UPDATE payments SET status = 'canceled' WHERE id = $paymentId";
        if (!$conn->query($updatePaymentQuery)) {
            throw new Exception("Error updating payment status: " . $conn->error);
        }
        
        // Refund the payment amount to user's balance
        if (!empty($consultation['payment_amount'])) {
            $refundAmount = $consultation['payment_amount'];
            
            // Get current user balance
            $balanceQuery = "SELECT balance FROM users WHERE id = $userId";
            $balanceResult = $conn->query($balanceQuery);
            
            if (!$balanceResult) {
                throw new Exception("Error getting user balance: " . $conn->error);
            }
            
            $currentBalance = $balanceResult->fetch_assoc()['balance'];
            $newBalance = $currentBalance + $refundAmount;
            
            // Update user balance
            $updateBalanceQuery = "UPDATE users SET balance = $newBalance WHERE id = $userId";
            if (!$conn->query($updateBalanceQuery)) {
                throw new Exception("Error refunding payment: " . $conn->error);
            }
            
            // Add notification about refund
            $refundNotificationQuery = "INSERT INTO client_notifications 
                                      (user_id, message, created_at) 
                                      VALUES 
                                      ($userId, 'Your canceled consultation has been refunded " . number_format($refundAmount, 2) . " DA to your account balance.', NOW())";
            $conn->query($refundNotificationQuery);
        }
    }

    // Create notification for expert
    $expertId = $consultation['expert_id'];
    $profileId = $consultation['profile_id'];
    $expertMessage = $conn->real_escape_string('A client has canceled their consultation request.');
    $expertNotificationQuery = "INSERT INTO expert_notifications 
                              (user_id, profile_id, notification_type, message, created_at) 
                              VALUES 
                              ($expertId, $profileId, 'consultation_canceled', '$expertMessage', NOW())";
    if (!$conn->query($expertNotificationQuery)) {
        throw new Exception("Error creating expert notification: " . $conn->error);
    }

    // Commit transaction
    $conn->commit();
    
    // Set success message
    $_SESSION['success_message'] = "Consultation successfully canceled. Payment has been refunded to your account balance.";
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    $_SESSION['error_message'] = $e->getMessage();
}

// Redirect back to the referring page or to find-experts.php
$redirectTo = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'find-experts.php';
header("Location: $redirectTo");
exit();
?>
