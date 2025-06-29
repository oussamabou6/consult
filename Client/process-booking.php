<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Set content type to JSON for all responses
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => "You must be logged in to book a consultation.",
        'redirect' => "../config/logout.php"
    ]);
    exit();
}

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode([
        'success' => false,
        'message' => "Invalid request method.",
        'redirect' => "find-experts.php"
    ]);
    exit();
}

// Get user ID from session
$userId = $_SESSION['user_id'];

// Get form data
$expertId = isset($_POST['expert_id']) ? intval($_POST['expert_id']) : 0;
$profileId = isset($_POST['profile_id']) ? intval($_POST['profile_id']) : 0;
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$duration = isset($_POST['duration']) ? intval($_POST['duration']) : 0;
$basePrice = isset($_POST['base_price']) ? intval($_POST['base_price']) : 0;
$baseDuration = isset($_POST['base_duration']) ? intval($_POST['base_duration']) : 0;

// Validate form data
if ($expertId <= 0 || $profileId <= 0 || empty($message) || $duration <= 0 || $basePrice <= 0 || $baseDuration <= 0) {
    echo json_encode([
        'success' => false,
        'message' => "All fields are required.",
        'redirect' => "find-experts.php"
    ]);
    exit();
}

// Check if expert exists and is online
$expertQuery = "SELECT u.id, u.status, ep.id as profile_id, bi.consultation_price, bi.consultation_minutes 
                FROM users u 
                JOIN expert_profiledetails ep ON u.id = ep.user_id 
                JOIN banking_information bi ON ep.id = bi.profile_id
                WHERE u.id = ? AND u.role = 'expert' AND ep.status = 'approved'";
$stmt = $conn->prepare($expertQuery);
$stmt->bind_param("i", $expertId);
$stmt->execute();
$expertResult = $stmt->get_result();

if (!$expertResult || $expertResult->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => "Expert not found.",
        'redirect' => "find-experts.php"
    ]);
    exit();
}

$expertData = $expertResult->fetch_assoc();

// Check if expert is online
if ($expertData['status'] != 'Online') {
    echo json_encode([
        'success' => false,
        'message' => "This expert is currently offline and cannot accept consultations.",
        'redirect' => "find-experts.php"
    ]);
    exit();
}

// Verify the profile ID matches
if ($expertData['profile_id'] != $profileId) {
    echo json_encode([
        'success' => false,
        'message' => "Invalid expert profile.",
        'redirect' => "find-experts.php"
    ]);
    exit();
}

// Verify the base price and duration match what's in the database
if ($expertData['consultation_price'] != $basePrice || $expertData['consultation_minutes'] != $baseDuration) {
    echo json_encode([
        'success' => false,
        'message' => "Consultation pricing information has changed. Please try again.",
        'redirect' => "find-experts.php"
    ]);
    exit();
}

// Calculate total price
$pricePerMinute = $basePrice / $baseDuration;
$totalPrice = round($pricePerMinute * $duration);

// Check if user has sufficient balance
$balanceQuery = "SELECT balance FROM users WHERE id = ?";
$balanceStmt = $conn->prepare($balanceQuery);
$balanceStmt->bind_param("i", $userId);
$balanceStmt->execute();
$balanceResult = $balanceStmt->get_result();

if (!$balanceResult || $balanceResult->num_rows == 0) {
    echo json_encode([
        'success' => false,
        'message' => "Unable to verify your account balance.",
        'redirect' => "find-experts.php"
    ]);
    exit();
}

$userBalance = $balanceResult->fetch_assoc()['balance'];

if ($userBalance < $totalPrice) {
    echo json_encode([
        'success' => false,
        'message' => "Insufficient balance. Please add funds to your account.",
        'redirect' => "add-fund.php"
    ]);
    exit();
}

// Get current date and time
$currentDate = date('Y-m-d');
$currentTime = date('H:i:s');

// Begin transaction
$conn->begin_transaction();

try {
    // Create consultation record
    $consultationQuery = "INSERT INTO consultations (client_id, expert_id, consultation_date, consultation_time, duration, status, notes, created_at) 
                         VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())";
    
    $stmt = $conn->prepare($consultationQuery);
    $stmt->bind_param("iissis", $userId, $expertId, $currentDate, $currentTime, $duration, $message);
    $stmt->execute();
    
    $consultationId = $conn->insert_id;
    
    if ($consultationId <= 0) {
        throw new Exception("Failed to create consultation record.");
    }
    
    // Check if user has other pending consultations with different experts
    $otherPendingQuery = "SELECT c.id, c.expert_id, u.full_name as expert_name 
                         FROM consultations c
                         JOIN users u ON c.expert_id = u.id
                         WHERE c.client_id = ? AND c.status = 'pending' AND c.id != ?";
    $otherPendingStmt = $conn->prepare($otherPendingQuery);
    $otherPendingStmt->bind_param("ii", $userId, $consultationId);
    $otherPendingStmt->execute();
    $otherPendingResult = $otherPendingStmt->get_result();

    // Store the IDs of other pending consultations
    $otherPendingIds = [];
    $otherPendingExperts = [];

    if ($otherPendingResult && $otherPendingResult->num_rows > 0) {
        while ($row = $otherPendingResult->fetch_assoc()) {
            $otherPendingIds[] = $row['id'];
            $otherPendingExperts[$row['id']] = $row['expert_name'];
        }
    }

    // Add a listener for when this consultation gets confirmed
    if (!empty($otherPendingIds)) {
        $listenerQuery = "INSERT INTO consultation_confirmation_listeners 
                         (consultation_id, client_id, created_at) 
                         VALUES (?, ?, NOW())";
        $listenerStmt = $conn->prepare($listenerQuery);
        $listenerStmt->bind_param("ii", $consultationId, $userId);
        $listenerStmt->execute();
    }
    
    // Deduct amount from user's balance
    $updateBalanceQuery = "UPDATE users SET balance = balance - ? WHERE id = ?";
    $updateBalanceStmt = $conn->prepare($updateBalanceQuery);
    $updateBalanceStmt->bind_param("di", $totalPrice, $userId);
    
    if (!$updateBalanceStmt->execute()) {
        throw new Exception("Failed to update user balance.");
    }
    
    // Create payment record
    $paymentQuery = "INSERT INTO payments (consultation_id, client_id, expert_id, amount, status,created_at) 
                    VALUES (?, ?, ?, ?, 'pending', NOW())";
    
    $paymentStmt = $conn->prepare($paymentQuery);
    $paymentStmt->bind_param("iiid", $consultationId, $userId, $expertId, $totalPrice);
    
    if (!$paymentStmt->execute()) {
        throw new Exception("Failed to create payment record.");
    }
    
    // Create notification for expert
    $notificationQuery = "INSERT INTO expert_notifications (user_id, profile_id, notification_type, message, created_at) 
                         VALUES (?, ?, 'new_consultation', 'You have a new consultation request.', NOW())";
    
    $notificationStmt = $conn->prepare($notificationQuery);
    $notificationStmt->bind_param("ii", $expertId, $profileId);
    $notificationStmt->execute(); // Not critical if this fails
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => "Consultation request sent successfully! The expert will confirm your booking soon.",
        'consultation_id' => $consultationId,
        'redirect' => "my-consultations.php"
    ]);
    exit();
    
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => "An error occurred: " . $e->getMessage(),
        'redirect' => "find-experts.php"
    ]);
    exit();
}
if ($status === 'confirmed') {
    // Get client ID from the consultation
    $clientIdQuery = "SELECT client_id FROM consultations WHERE id = $consultationId";
    $clientIdResult = $conn->query($clientIdQuery);
    
    if ($clientIdResult && $clientIdResult->num_rows > 0) {
        $clientId = $clientIdResult->fetch_assoc()['client_id'];
        
        // Find all other pending consultations from this client
        $pendingConsultationsQuery = "SELECT c.id, c.expert_id, u.full_name as expert_name 
                                     FROM consultations c
                                     JOIN users u ON c.expert_id = u.id
                                     WHERE c.client_id = $clientId 
                                     AND c.status = 'pending'
                                     AND c.id != $consultationId";
        $pendingConsultationsResult = $conn->query($pendingConsultationsQuery);
        
        if ($pendingConsultationsResult && $pendingConsultationsResult->num_rows > 0) {
            while ($pendingConsultation = $pendingConsultationsResult->fetch_assoc()) {
                $pendingId = $pendingConsultation['id'];
                $expertName = $pendingConsultation['expert_name'];
                
                // Cancel the pending consultation
                $cancelQuery = "UPDATE consultations SET status = 'cancelled', 
                               updated_at = NOW(),
                               cancellation_reason = 'Auto-cancelled because another consultation was confirmed'
                               WHERE id = $pendingId";
                $conn->query($cancelQuery);
                
                // Add notification for the client
                $notificationMessage = "Your consultation request with $expertName was automatically cancelled because another consultation was confirmed.";
                $notificationQuery = "INSERT INTO client_notifications (user_id, message, type, is_read, created_at)
                                    VALUES ($clientId, '$notificationMessage', 'consultation_cancelled', 0, NOW())";
                $conn->query($notificationQuery);
            }
        }
    }
}

?>
