<?php

session_start();
require_once '../config/config.php';

// Authentication check
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in or not an expert, redirect to login page
    header("Location: ../config/logout.php");
    exit();
}


// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];
$success_message = "";
$error_message = "";

// Update user status to "Online"
$update_status_sql = "UPDATE users SET status = 'Online' WHERE id = ?";
$update_status_stmt = $conn->prepare($update_status_sql);
$update_status_stmt->bind_param("i", $user_id);
$update_status_stmt->execute();
$update_status_stmt->close();

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Function to format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Get user data
$sql = "SELECT u.*, up.phone, up.address, up.profile_image, up.bio 
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

$user_fullname = $user['full_name'];
// Get site settings
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Get expert profile data
$profile_sql = "SELECT * FROM expert_profiledetails WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$profile_id = $profile ? $profile['id'] : 0;
$profile_stmt->close();

// Get currency from settings
$currency = $settings['currency'] ?? 'DA';

// Process consultation actions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['action'])) {
        $consultation_id = isset($_POST['consultation_id']) ? intval($_POST['consultation_id']) : 0;
        
        // Verify the consultation exists and belongs to this expert
        $check_sql = "SELECT * FROM consultations WHERE id = ? AND expert_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $consultation_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $consultation = $check_result->fetch_assoc();
            $client_id = $consultation['client_id'];
            
            // Accept consultation
            if ($_POST['action'] == 'accept') {
                $update_sql = "UPDATE consultations SET status = 'confirmed' WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("i", $consultation_id);
                
                if ($update_stmt->execute()) {
                    // Update payment status to processing
                    $payment_sql = "UPDATE payments SET status = 'processing' WHERE consultation_id = ?";
                    $payment_stmt = $conn->prepare($payment_sql);
                    $payment_stmt->bind_param("i", $consultation_id);
                    $payment_stmt->execute();
                    $payment_stmt->close();
                    
                    // Create a chat session
                    $chat_sql = "INSERT INTO chat_sessions (consultation_id, expert_id, client_id, started_at, status) 
                                VALUES (?, ?, ?, NOW(), 'active')";
                    $chat_stmt = $conn->prepare($chat_sql);
                    $chat_stmt->bind_param("iii", $consultation_id, $user_id, $client_id);
                    $chat_stmt->execute();
                    $chat_session_id = $conn->insert_id;
                    $chat_stmt->close();
                    
                    // Create a chat timer
                    $timer_sql = "INSERT INTO chat_timers (chat_session_id, started_at, status) 
                                 VALUES (?, NOW(), 'running')";
                    $timer_stmt = $conn->prepare($timer_sql);
                    $timer_stmt->bind_param("i", $chat_session_id);
                    $timer_stmt->execute();
                    $timer_stmt->close();
                    
                    // Notify client
                    $notify_sql = "INSERT INTO client_notifications (user_id, message, is_read, created_at) 
                                  VALUES (?, ?, 0, NOW())";
                    $message = "Your consultation on " . date('d M Y', strtotime($consultation['consultation_date'])) . 
                              " has been confirmed by the expert ($user_fullname).";
                    $notify_stmt = $conn->prepare($notify_sql);
                    $notify_stmt->bind_param("is", $client_id, $message);
                    $notify_stmt->execute();
                    $notify_stmt->close();
                    
                    $success_message = "Consultation accepted successfully. You can now start chatting with the client.";
                    
                    // Redirect to chat page
                    header("Location: expert-chat.php?client_id=$client_id&consultation_id=$consultation_id");
                    exit();
                } else {
                    $error_message = "Error accepting consultation: " . $conn->error;
                }
                $update_stmt->close();
            }
            
            // Reject consultation
            else if ($_POST['action'] == 'reject') {
                $rejection_reason = sanitize_input($_POST['rejection_reason']);
                
                $update_sql = "UPDATE consultations SET status = 'rejected', rejection_reason = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $rejection_reason, $consultation_id);
                
                if ($update_stmt->execute()) {
                    // Refund client
                    $refund_sql = "SELECT amount FROM payments WHERE consultation_id = ? AND status = 'pending'";
                    $refund_stmt = $conn->prepare($refund_sql);
                    $refund_stmt->bind_param("i", $consultation_id);
                    $refund_stmt->execute();
                    $refund_result = $refund_stmt->get_result();
                    $refund_stmt->close();
                    
                    if ($refund_result->num_rows > 0) {
                        $payment = $refund_result->fetch_assoc();
                        $amount = $payment['amount'];
                        
                        // Update payment status to rejected
                        $payment_sql = "UPDATE payments SET status = 'rejected' WHERE consultation_id = ?";
                        $payment_stmt = $conn->prepare($payment_sql);
                        $payment_stmt->bind_param("i", $consultation_id);
                        $payment_stmt->execute();
                        $payment_stmt->close();
                        
                        // Refund client balance
                        $balance_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                        $balance_stmt = $conn->prepare($balance_sql);
                        $balance_stmt->bind_param("di", $amount, $client_id);
                        $balance_stmt->execute();
                        $balance_stmt->close();
                    }
                    
                    // Notify client
                    $notify_sql = "INSERT INTO client_notifications (user_id, message, is_read, created_at) 
                                  VALUES (?, ?, 0, NOW())";
                    $message = "Your consultation request has been rejected by the expert ($user_fullname) . Reason: " . $rejection_reason;
                    $notify_stmt = $conn->prepare($notify_sql);
                    $notify_stmt->bind_param("is", $client_id, $message);
                    $notify_stmt->execute();
                    $notify_stmt->close();
                    
                    $success_message = "Consultation rejected successfully.";
                } else {
                    $error_message = "Error rejecting consultation: " . $conn->error;
                }
                $update_stmt->close();
            }
            
            // Send message to client
            else if ($_POST['action'] == 'message') {
                $message_text = sanitize_input($_POST['message_text']);
                
                // Update expert message in consultations table
                $update_message_sql = "UPDATE consultations SET expert_message = ? WHERE id = ?";
                $update_message_stmt = $conn->prepare($update_message_sql);
                $update_message_stmt->bind_param("si", $message_text, $consultation_id);
                
                if ($update_message_stmt->execute()) {
                    // Notify client about new message
                    $notify_sql = "INSERT INTO client_notifications (user_id, message, is_read, created_at) 
                                  VALUES (?, ?, 0, NOW())";
                    $notify_message = "You have a new message from the expert ($user_fullname) regarding your consultation.";
                    $notify_stmt = $conn->prepare($notify_sql);
                    $notify_stmt->bind_param("is", $client_id, $notify_message);
                    $notify_stmt->execute();
                    $notify_stmt->close();
                    
                    $success_message = "Message sent to client successfully.";
                } else {
                    $error_message = "Error sending message: " . $conn->error;
                }
                $update_message_stmt->close();
            }
        } else {
            $error_message = "Invalid consultation or you don't have permission to perform this action.";
        }
        $check_stmt->close();
    }
    
    // Edit expert message
    if (isset($_POST['edit_message']) && isset($_POST['consultation_id'])) {
        $consultation_id = intval($_POST['consultation_id']);
        $expert_message = sanitize_input($_POST['expert_message']);
        
        // Verify the consultation exists and belongs to this expert
        $check_sql = "SELECT * FROM consultations WHERE id = ? AND expert_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $consultation_id, $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();
        
        if ($check_result->num_rows > 0) {
            $update_message_sql = "UPDATE consultations SET expert_message = ? WHERE id = ?";
            $update_message_stmt = $conn->prepare($update_message_sql);
            $update_message_stmt->bind_param("si", $expert_message, $consultation_id);
            
            if ($update_message_stmt->execute()) {
                $success_message = "Expert message updated successfully.";
            } else {
                $error_message = "Error updating expert message: " . $conn->error;
            }
            $update_message_stmt->close();
        } else {
            $error_message = "Invalid consultation or you don't have permission to edit this message.";
        }
    }
}

// Get pending consultation requests
$pending_consultations = [];
$pending_sql = "SELECT c.*, u.full_name as client_name, u.status as client_status, up.profile_image as client_image,
                cat.name as category_name, subcat.name as subcategory_name
                FROM consultations c 
                JOIN users u ON c.client_id = u.id 
                LEFT JOIN user_profiles up ON u.id = up.user_id 
                LEFT JOIN expert_profiledetails ep ON c.expert_id = ep.user_id
                LEFT JOIN categories cat ON ep.category = cat.id
                LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                WHERE c.expert_id = ? AND c.status = 'pending' 
                ORDER BY c.created_at DESC";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();

while ($row = $pending_result->fetch_assoc()) {
    $pending_consultations[] = $row;
}
$pending_stmt->close();

// Get confirmed consultations
$confirmed_consultations = [];
$confirmed_sql = "SELECT c.*, u.full_name as client_name, u.status as client_status, up.profile_image as client_image,
                 cat.name as category_name, subcat.name as subcategory_name
                 FROM consultations c 
                 JOIN users u ON c.client_id = u.id 
                 LEFT JOIN user_profiles up ON u.id = up.user_id 
                 LEFT JOIN expert_profiledetails ep ON c.expert_id = ep.user_id
                 LEFT JOIN categories cat ON ep.category = cat.id
                 LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                 WHERE c.expert_id = ? AND c.status = 'confirmed' 
                 ORDER BY c.consultation_date DESC, c.consultation_time DESC";
$confirmed_stmt = $conn->prepare($confirmed_sql);
$confirmed_stmt->bind_param("i", $user_id);
$confirmed_stmt->execute();
$confirmed_result = $confirmed_stmt->get_result();

while ($row = $confirmed_result->fetch_assoc()) {
    $confirmed_consultations[] = $row;
}
$confirmed_stmt->close();

// Get completed consultations
$completed_consultations = [];
$completed_sql = "SELECT c.*, u.full_name as client_name, up.profile_image as client_image,
                 cat.name as category_name, subcat.name as subcategory_name
                 FROM consultations c 
                 JOIN users u ON c.client_id = u.id 
                 LEFT JOIN user_profiles up ON u.id = up.user_id 
                 LEFT JOIN expert_profiledetails ep ON c.expert_id = ep.user_id
                 LEFT JOIN categories cat ON ep.category = cat.id
                 LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                 WHERE c.expert_id = ? AND c.status = 'completed' 
                 ORDER BY c.consultation_date DESC, c.consultation_time DESC 
                 LIMIT 10";
$completed_stmt = $conn->prepare($completed_sql);
$completed_stmt->bind_param("i", $user_id);
$completed_stmt->execute();
$completed_result = $completed_stmt->get_result();

while ($row = $completed_result->fetch_assoc()) {
    $completed_consultations[] = $row;
}
$completed_stmt->close();

// Get rejected consultations
$rejected_consultations = [];
$rejected_sql = "SELECT c.*, u.full_name as client_name, up.profile_image as client_image,
                cat.name as category_name, subcat.name as subcategory_name
                FROM consultations c 
                JOIN users u ON c.client_id = u.id 
                LEFT JOIN user_profiles up ON u.id = up.user_id 
                LEFT JOIN expert_profiledetails ep ON c.expert_id = ep.user_id
                LEFT JOIN categories cat ON ep.category = cat.id
                LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                WHERE c.expert_id = ? AND c.status = 'rejected' 
                ORDER BY c.created_at DESC 
                LIMIT 10";
$rejected_stmt = $conn->prepare($rejected_sql);
$rejected_stmt->bind_param("i", $user_id);
$rejected_stmt->execute();
$rejected_result = $rejected_stmt->get_result();

while ($row = $rejected_result->fetch_assoc()) {
    $rejected_consultations[] = $row;
}
$rejected_stmt->close();

// Get canceled consultations
$canceled_consultations = [];
$canceled_sql = "SELECT c.*, u.full_name as client_name, up.profile_image as client_image,
                cat.name as category_name, subcat.name as subcategory_name
                FROM consultations c 
                JOIN users u ON c.client_id = u.id 
                LEFT JOIN user_profiles up ON u.id = up.user_id 
                LEFT JOIN expert_profiledetails ep ON c.expert_id = ep.user_id
                LEFT JOIN categories cat ON ep.category = cat.id
                LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                WHERE c.expert_id = ? AND c.status = 'canceled' 
                ORDER BY c.created_at DESC 
                LIMIT 10";
$canceled_stmt = $conn->prepare($canceled_sql);
$canceled_stmt->bind_param("i", $user_id);
$canceled_stmt->execute();
$canceled_result = $canceled_stmt->get_result();

while ($row = $canceled_result->fetch_assoc()) {
    $canceled_consultations[] = $row;
}
$canceled_stmt->close();



$admin_messages = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND receiver_id = ? AND sender_type = 'admin'");
$admin_messages->bind_param("i", $user_id);
$admin_messages->execute();
$admin_messages_result = $admin_messages->get_result();
$admin_messages_count = $admin_messages_result->fetch_assoc()['count'];
$admin_messages->close();

// Get pending withdrawals count
$pending_withdrawals = $conn->prepare("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending' AND user_id = ?");
$pending_withdrawals->bind_param("i", $user_id);
$pending_withdrawals->execute();
$pending_withdrawals_result = $pending_withdrawals->get_result();
$pending_withdrawals_count = $pending_withdrawals_result->fetch_assoc()['count'];
$pending_withdrawals->close();

// Get pending consultation count
$pending_consultations_count = count($pending_consultations);

$reviews_not_read = $conn->prepare("SELECT COUNT(*) as count FROM expert_ratings WHERE is_read = 0 AND expert_id = ?");
$reviews_not_read->bind_param("i", $user_id);
$reviews_not_read->execute();
$reviews_not_read_result = $reviews_not_read->get_result();
$reviews_not_read_count = $reviews_not_read_result->fetch_assoc()['count'];
$reviews_not_read->close();


$notifictaions_not_read = $conn->prepare("SELECT COUNT(*) as count FROM expert_notifications WHERE is_read = 0 AND user_id = ? ");
$notifictaions_not_read->bind_param("i", $user_id);
$notifictaions_not_read->execute();
$notifictaions_not_read_result = $notifictaions_not_read->get_result();
$notifictaions_not_read_count = $notifictaions_not_read_result->fetch_assoc()['count'];
$notifictaions_not_read->close();

// Handle AJAX request for notifications
if (isset($_GET['fetch_notifications'])) {
    $response = [
        'pending_consultations' => $pending_consultations_count,
        'pending_withdrawals' => $pending_withdrawals_count,
        'admin_messages' => $admin_messages_count,
        'reviews' => $reviews_not_read_count,
        'notifications_not_read' => $notifictaions_not_read_count,
        'total' => $pending_consultations_count + $pending_withdrawals_count + $admin_messages_count + $reviews_not_read_count + $notifictaions_not_read_count,
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Expert Consultations - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=JetBrains+Mono:wght@400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --secondary-color: #06b6d4;
            --secondary-light: #22d3ee;
            --secondary-dark: #0891b2;
            --accent-color: #8b5cf6;
            --accent-light: #a78bfa;
            --accent-dark: #7c3aed;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --info-color: #3b82f6;
            --light-color: #f8fafc;
            --dark-color: #1e293b;
            --border-color: #e2e8f0;
            --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            --text-color: #334155;
            --text-muted: #64748b;
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --code-font: 'JetBrains Mono', monospace;
            --body-font: 'Poppins', sans-serif;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--body-font);
            color: var(--text-color);
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
            background-color: #f1f5f9;
            overflow-x: hidden;
        }
        
        /* Enhanced Background Design */
        .background-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        
        .background-gradient {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 50%, #dbeafe 100%);
            opacity: 0.8;
        }
        
        .background-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25px 25px, rgba(99, 102, 241, 0.15) 2px, transparent 0),
                radial-gradient(circle at 75px 75px, rgba(139, 92, 246, 0.1) 2px, transparent 0);
            background-size: 100px 100px;
            opacity: 0.6;
            background-attachment: fixed;
        }
        
        .background-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(rgba(99, 102, 241, 0.1) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(99, 102, 241, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            background-attachment: fixed;
        }
        
        .background-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(70px);
            opacity: 0.2;
            animation: float 20s infinite ease-in-out;
        }
        
        .shape-1 {
            width: 600px;
            height: 600px;
            background: rgba(99, 102, 241, 0.5);
            top: -200px;
            right: -100px;
            animation-delay: 0s;
        }
        
        .shape-2 {
            width: 500px;
            height: 500px;
            background: rgba(6, 182, 212, 0.4);
            bottom: -150px;
            left: -150px;
            animation-delay: -5s;
        }
        
        .shape-3 {
            width: 400px;
            height: 400px;
            background: rgba(139, 92, 246, 0.3);
            top: 30%;
            left: 30%;
            animation-delay: -10s;
        }
        
        .shape-4 {
            width: 350px;
            height: 350px;
            background: rgba(245, 158, 11, 0.2);
            bottom: 20%;
            right: 20%;
            animation-delay: -7s;
        }
        
        .shape-5 {
            width: 300px;
            height: 300px;
            background: rgba(16, 185, 129, 0.3);
            top: 10%;
            left: 20%;
            animation-delay: -3s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) scale(1);
            }
            50% {
                transform: translateY(-40px) scale(1.05);
            }
        }
        
        /* Animated Gradient Background */
        .animated-gradient {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(-45deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1), rgba(6, 182, 212, 0.1), rgba(16, 185, 129, 0.1));
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
            z-index: -1;
        }
        
        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        /* Navbar Styles */
        .navbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 0.8rem 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        .logo-text .fw-bold {
            font-size: 1.8rem;
            color: var(--primary-color);
        }

        .logo-subtitle {
            font-size: 0.7rem;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .navbar-nav {
            gap: 0.5rem;
        }

        .navbar-nav .nav-item {
            position: relative;
        }

        .navbar-light .navbar-nav .nav-link {
            color: var(--text-color);
            font-weight: 500;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.9rem;
        }

        .navbar-light .navbar-nav .nav-link i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .navbar-light .navbar-nav .nav-link:hover {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.08);
        }

        .navbar-light .navbar-nav .nav-link:hover i {
            transform: translateY(-3px);
        }

        .navbar-light .navbar-nav .active > .nav-link,
        .navbar-light .navbar-nav .nav-link.active {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
            font-weight: 600;
        }

        .navbar-light .navbar-nav .active > .nav-link i,
        .navbar-light .navbar-nav .nav-link.active i {
            color: var(--primary-color);
        }

        .nav-user-section {
            margin-left: 1rem;
            border-left: 1px solid rgba(226, 232, 240, 0.8);
            padding-left: 1rem;
        }

        
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 0;
            margin-top: 0.5rem;
            animation: dropdown-fade 0.3s ease;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.7);
            min-width: 200px;
        }

        @keyframes dropdown-fade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .dropdown-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            z-index: -1;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .dropdown-item:hover::before {
            transform: translateX(0);
        }

        .dropdown-item:hover {
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .dropdown-item:active {
            background-color: var(--primary-color);
            color: white;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .dropdown-item:hover i {
            transform: scale(1.2);
        }
        
        /* Main Content Styles */
        .main-container {
            padding: 2rem 0;
            position: relative;
            z-index: 1;
        }
        
        /* Dashboard Cards */
        .dashboard-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            height: 100%;
            margin-bottom: 2rem;
        }
        
        .dashboard-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }
        
        .dashboard-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fafbfc;
        }
        
        .dashboard-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0;
        }
        
        .dashboard-card-body {
            padding: 1.5rem;
        }
        
        /* Consultation Request Card */
        .consultation-request {
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
        }
        
        .consultation-request:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .consultation-header {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .client-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--text-muted);
            border: 2px solid white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .client-info {
            flex-grow: 1;
        }
        
        .client-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }
        
        .consultation-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .consultation-meta i {
            color: var(--primary-color);
        }
        
        .client-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .client-status.online {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .client-status.offline {
            background-color: rgba(100, 116, 139, 0.1);
            color: var(--text-muted);
        }
        
        .consultation-body {
            padding: 1.25rem;
        }
        
        .consultation-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .consultation-message {
            background-color: rgba(241, 245, 249, 0.5);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .consultation-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 10px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.3));
            transition: all 0.4s ease;
            z-index: -1;
        }
        
        .btn:hover::before {
            left: 0;
        }
        
        .btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, var(--primary-dark), var(--accent-dark));
            border: none;
            color: white;
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(to right, var(--success-color), #34d399);
            border: none;
            color: white;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }
        
        .btn-success:hover {
            background: linear-gradient(to right, #059669, var(--success-color));
            border: none;
            color: white;
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(to right, var(--danger-color), #f87171);
            border: none;
            color: white;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.2);
        }
        
        .btn-danger:hover {
            background: linear-gradient(to right, #dc2626, var(--danger-color));
            border: none;
            color: white;
            box-shadow: 0 15px 30px rgba(239, 68, 68, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(to right, var(--warning-color), #fbbf24);
            border: none;
            color: white;
            box-shadow: 0 10px 20px rgba(245, 158, 11, 0.2);
        }
        
        .btn-warning:hover {
            background: linear-gradient(to right, #d97706, var(--warning-color));
            border: none;
            color: white;
            box-shadow: 0 15px 30px rgba(245, 158, 11, 0.3);
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        /* Tabs */
        .nav-tabs {
            border-bottom: none;
            margin-bottom: 1.5rem;
            gap: 0.5rem;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: var(--text-color);
            transition: all 0.3s ease;
            background-color: rgba(241, 245, 249, 0.5);
        }
        
        .nav-tabs .nav-link:hover {
            background-color: rgba(241, 245, 249, 0.8);
            transform: translateY(-3px);
        }
        
        .nav-tabs .nav-link.active {
            color: white;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }
        
        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            color: white;
            border-bottom: none;
            padding: 1.5rem;
        }
        
        .modal-title {
            font-weight: 700;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid rgba(226, 232, 240, 0.7);
            padding: 1.25rem 1.5rem;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1.5rem;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1.5rem;
            opacity: 0.5;
        }
        
        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.75rem;
        }
        
        .empty-state-description {
            color: var(--text-muted);
            max-width: 500px;
            margin: 0 auto 1.5rem;
        }
        
         /* Footer */
         footer {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: var(--text-color);
            padding: 3rem 0 0;
            margin-top: 3rem;
            position: relative;
            overflow: hidden;
            border-top: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .footer-content {
            position: relative;
            z-index: 1;
        }
        
        footer h5 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
            color: var(--dark-color);
        }
        
        footer h5::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border-radius: 3px;
            transition: width 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        footer h5:hover::after {
            width: 100%;
        }
        
        .footer-links {
            list-style: none;
            padding-left: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-block;
            font-weight: 500;
            position: relative;
            padding-left: 20px;
        }
        
        .footer-links a i {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .footer-links a:hover {
            color: var(--primary-color);
            transform: translateX(10px);
        }
        
        .footer-links a:hover i {
            color: var(--accent-color);
            transform: translateY(-50%) scale(1.2);
        }
        
        .social-icons {
            display: flex;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .social-icons a {
            color: var(--text-color);
            font-size: 1.5rem;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(248, 250, 252, 0.8);
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            z-index: 1;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .social-icons a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }
        
        .social-icons a:hover::before {
            opacity: 1;
        }
        
        .social-icons a:hover {
            color: white;
            transform: translateY(-8px) rotate(10deg);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }
       
        .footer-bottom {
            background: rgba(248, 250, 252, 0.8);
            padding: 1.5rem 0;
            margin-top: 3rem;
            position: relative;
            z-index: 1;
            border-top: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .footer-bottom p {
            margin-bottom: 0;
            text-align: center;
            color: rgba(0, 0, 0, 0.6);
        }
        
        .modal-backdrop {
            position: relative;
        }
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .consultation-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .client-avatar {
                margin-bottom: 1rem;
            }
            
            .consultation-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
        }
        
        @media (max-width: 767.98px) {
            .consultation-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .consultation-actions .btn {
                width: 100%;
            }
            
            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                padding-bottom: 0.5rem;
            }
            
            .nav-tabs .nav-link {
                white-space: nowrap;
            }
        }
        
        @media (max-width: 575.98px) {
            .dashboard-card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .dashboard-card-header .btn {
                width: 100%;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .fade-in {
            animation: fadeIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        
        .delay-1 {
            animation-delay: 0.1s;
        }
        
        .delay-2 {
            animation-delay: 0.2s;
        }
        
        .delay-3 {
            animation-delay: 0.3s;
        }
        
        .delay-4 {
            animation-delay: 0.4s;
        }
        .bg-danger{
    padding: 0 5px;
    height: 18px;
    min-width: 18px;
    border-radius: 9px;
    background:  var(--danger-color);
    font-size: 0.7rem;
    line-height: 18px;

    text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            animation: pulse 1.5s infinite;
    font-weight: 700;
    z-index: 10;
        }
        /* Notification Badge Styles */
        .notification-badge {
            display: none;
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background-color: var(--danger-color) ;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            animation: pulse 1.5s infinite;
            z-index: 10;
        }

        .notification-badge.show {
            display: block;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, var(--primary-light), var(--accent-light));
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
        }
    </style>
</head>
<body>
<!-- Background Elements -->
<div class="background-container">
    <div class="background-gradient"></div>
    <div class="background-pattern"></div>
    <div class="background-grid"></div>
    <div class="animated-gradient"></div>
    <div class="background-shapes">
        <div class="shape shape-1" data-speed="1.5"></div>
        <div class="shape shape-2" data-speed="1"></div>
        <div class="shape shape-3" data-speed="2"></div>
        <div class="shape shape-4" data-speed="1.2"></div>
        <div class="shape shape-5" data-speed="1.8"></div>
    </div>
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand" href="#">
            <?php if (!empty($settings['site_image'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($settings['site_image']); ?>" alt="<?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?> Logo" style="height: 40px;">
            <?php else: ?>
                <span class="fw-bold"><?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="home-profile.php">
                        <i class="fas fa-home mb-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-profile.php">
                        <i class="fas fa-user mb-1"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center active" href="expert-consultations.php">
                        <i class="fas fa-laptop-code mb-1"></i> Consultations
                        <span class="notification-badge pending-consultations-badge"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-earnings.php">
                        <i class="fas fa-chart-line mb-1"></i> Earnings
                        <span class="notification-badge pending-withdrawals-badge"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-avis.php">
                        <i class="fas fa-star mb-1"></i> Reviews

                        <span class="notification-badge reviews-badge"></span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-contact.php">
                        <i class="fas fa-envelope mb-1"></i> Contact
                        <span class="notification-badge admin-messages-badge"></span>
                    </a>
                </li>
            </ul>
            <div class="nav-user-section">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="position-relative">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user-circle me-2"></i>
                            <?php endif; ?>
                            <span class="notification-badge total-notifications-badge"></span>
                        </div>
                        <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user['full_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="notifications.php"><i class="fa-solid fa-bell"></i> Notifications
                        <span class="notification-badge notifications-not-read-badge" style="margin-top: 10px;margin-right: 10px;"></span></a></li>
                    <li><a class="dropdown-item" href="expert-settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../config/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container main-container">
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success mt-3 fade-in">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger mt-3 fade-in">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="dashboard-card fade-in">
                <div class="dashboard-card-header">
                    <h1 class="dashboard-card-title">
                        <i class="fas fa-laptop-code me-2 text-primary"></i> Consultation Requests
                    </h1>
                </div>
                <div class="dashboard-card-body">
                    <p class="text-muted">
                        Manage your consultation requests from clients. Accept, reject, or send messages to clients about their consultation requests.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Consultation Tabs -->
    <ul class="nav nav-tabs" id="consultationTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
                <i class="fas fa-clock me-2"></i> Pending Requests
                
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="confirmed-tab" data-bs-toggle="tab" data-bs-target="#confirmed" type="button" role="tab" aria-controls="confirmed" aria-selected="false">
                <i class="fas fa-check-circle me-2"></i> Current
                <?php if (count($confirmed_consultations) > 0): ?>
                    <span class="badge bg-primary ms-2"><?php echo count($confirmed_consultations); ?></span>
                <?php endif; ?>
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">
                <i class="fas fa-check-double me-2"></i> Completed
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab" aria-controls="rejected" aria-selected="false">
                <i class="fas fa-times-circle me-2"></i> Rejected
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="canceled-tab" data-bs-toggle="tab" data-bs-target="#canceled" type="button" role="tab" aria-controls="canceled" aria-selected="false">
                <i class="fas fa-ban me-2"></i> Canceled
            </button>
        </li>
    </ul>
    
    <!-- Tab Content -->
    <div class="tab-content" id="consultationTabsContent">
        <!-- Pending Requests Tab -->
        <div class="tab-pane fade show active" id="pending" role="tabpanel" aria-labelledby="pending-tab">
            <?php if (count($pending_consultations) > 0): ?>
                <?php foreach ($pending_consultations as $consultation): ?>
                    <div class="consultation-request fade-in">
                        <div class="consultation-header">
                            <?php if (!empty($consultation['client_image'])): ?>
                                <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="<?php echo htmlspecialchars($consultation['client_name']); ?>" class="client-avatar">
                            <?php else: ?>
                                <div class="client-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="client-info">
                                <h3 class="client-name"><?php echo htmlspecialchars($consultation['client_name']); ?></h3>
                                <div class="consultation-meta">
                                    <span><i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($consultation['consultation_date']); ?> at <?php echo formatTime($consultation['consultation_time']); ?></span>
                                    <span><i class="far fa-clock me-1"></i> Requested: <?php echo date('M d, Y - H:i', strtotime($consultation['created_at'])); ?></span>
                                    <span class="client-status <?php echo strtolower($consultation['client_status']); ?>">
                                        <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i> <?php echo $consultation['client_status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="consultation-body">
                            <div class="consultation-details">
                                <div class="detail-item">
                                    <span class="detail-label">Duration</span>
                                    <span class="detail-value"><?php echo $consultation['duration']; ?> minutes</span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Category</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($consultation['category_name'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Subcategory</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($consultation['subcategory_name'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <?php
                                // Get consultation price from payments table
                                $price_sql = "SELECT amount FROM payments WHERE consultation_id = ? LIMIT 1";
                                $price_stmt = $conn->prepare($price_sql);
                                $price_stmt->bind_param("i", $consultation['id']);
                                $price_stmt->execute();
                                $price_result = $price_stmt->get_result();
                                $price = $price_result->fetch_assoc();
                                $price_stmt->close();
                                ?>
                                
                                <?php if ($price): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Price</span>
                                    <span class="detail-value"><?php echo number_format($price['amount']); ?> <?php echo $currency; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="consultation-message">
                                <h5 class="mb-2">Client Message:</h5>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></p>
                            </div>
                            
                            <?php if (!empty($consultation['expert_message'])): ?>
                                <div class="consultation-message mt-3" style="background-color: rgba(100, 116, 139, 0.1); border-color: rgba(100, 116, 139, 0.2);">
                                    <h5 class="mb-2">Your Message:</h5>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['expert_message'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <div class="consultation-actions">
                                <form action="expert-consultations.php" method="post">
                                    <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-check me-2"></i> Accept & Chat
                                    </button>
                                </form>
                                
                                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $consultation['id']; ?>">
                                    <i class="fas fa-times me-2"></i> Reject
                                </button>
                                
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#messageModal<?php echo $consultation['id']; ?>">
                                    <i class="fas fa-envelope me-2"></i> Send Message
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Reject Modal -->
                    <div class="modal fade" id="rejectModal<?php echo $consultation['id']; ?>" tabindex="-1" aria-labelledby="rejectModalLabel<?php echo $consultation['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="rejectModalLabel<?php echo $consultation['id']; ?>">Reject Consultation Request</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="expert-consultations.php" method="post">
                                    <div class="modal-body">
                                        <p>You are about to reject the consultation request from <strong><?php echo htmlspecialchars($consultation['client_name']); ?></strong>.</p>
                                        <div class="mb-3">
                                            <label for="rejection_reason<?php echo $consultation['id']; ?>" class="form-label">Rejection Reason</label>
                                            <textarea class="form-control" id="rejection_reason<?php echo $consultation['id']; ?>" name="rejection_reason" rows="4" required></textarea>
                                            <div class="form-text">Please provide a reason for rejecting this consultation request. This will be sent to the client.</div>
                                        </div>
                                        <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-danger">Reject Request</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Message Modal -->
                    <div class="modal fade" id="messageModal<?php echo $consultation['id']; ?>" tabindex="-1" aria-labelledby="messageModalLabel<?php echo $consultation['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="messageModalLabel<?php echo $consultation['id']; ?>">Send Message to Client</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <form action="expert-consultations.php" method="post">
                                    <div class="modal-body">
                                        <p>Send a message to <strong><?php echo htmlspecialchars($consultation['client_name']); ?></strong> about their consultation request.</p>
                                        <div class="mb-3">
                                            <label for="message_text<?php echo $consultation['id']; ?>" class="form-label">Message</label>
                                            <textarea class="form-control" id="message_text<?php echo $consultation['id']; ?>" name="message_text" rows="4" required></textarea>
                                            <div class="form-text">
                                                Example: "I'm currently busy with other consultations. I'll accept your request as soon as I'm available."
                                            </div>
                                        </div>
                                        <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                                        <input type="hidden" name="action" value="message">
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Send Message</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3 class="empty-state-title">No Pending Requests</h3>
                    <p class="empty-state-description">You don't have any pending consultation requests at the moment. When clients request consultations, they will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Confirmed Consultations Tab -->
        <div class="tab-pane fade" id="confirmed" role="tabpanel" aria-labelledby="confirmed-tab">
            <?php if (count($confirmed_consultations) > 0): ?>
                <?php foreach ($confirmed_consultations as $consultation): ?>
                    <div class="consultation-request fade-in">
                        <div class="consultation-header">
                            <?php if (!empty($consultation['client_image'])): ?>
                                <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="<?php echo htmlspecialchars($consultation['client_name']); ?>" class="client-avatar">
                            <?php else: ?>
                                <div class="client-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="client-info">
                                <h3 class="client-name"><?php echo htmlspecialchars($consultation['client_name']); ?></h3>
                                <div class="consultation-meta">
                                    <span><i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($consultation['consultation_date']); ?> at <?php echo formatTime($consultation['consultation_time']); ?></span>
                                    <span><i class="far fa-check-circle me-1"></i> Confirmed</span>
                                    <span class="client-status <?php echo strtolower($consultation['client_status']); ?>">
                                        <i class="fas fa-circle me-1" style="font-size: 0.6rem;"></i> <?php echo $consultation['client_status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="consultation-body">
                            <div class="consultation-details">
                                <div class="detail-item">
                                    <span class="detail-label">Duration</span>
                                    <span class="detail-value"><?php echo $consultation['duration']; ?> minutes</span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Category</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($consultation['category_name'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Subcategory</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($consultation['subcategory_name'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <?php
                                // Get consultation price from payments table
                                $price_sql = "SELECT amount FROM payments WHERE consultation_id = ? LIMIT 1";
                                $price_stmt = $conn->prepare($price_sql);
                                $price_stmt->bind_param("i", $consultation['id']);
                                $price_stmt->execute();
                                $price_result = $price_stmt->get_result();
                                $price = $price_result->fetch_assoc();
                                $price_stmt->close();
                                ?>
                                
                                <?php if ($price): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Price</span>
                                    <span class="detail-value"><?php echo number_format($price['amount']); ?> <?php echo $currency; ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="consultation-message">
                                <h5 class="mb-2">Client Message:</h5>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></p>
                            </div>
                            
                            <?php if (!empty($consultation['expert_message'])): ?>
                                <div class="consultation-message mt-3" style="background-color: rgba(100, 116, 139, 0.1); border-color: rgba(100, 116, 139, 0.2);">
                                    <h5 class="mb-2">Your Message:</h5>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['expert_message'])); ?></p>
                                </div>
                            <?php endif; ?>

                            <!-- Edit Expert Message Form -->
                            <form action="expert-consultations.php" method="post">
                                <div class="mb-3">
                                    <label for="expert_message<?php echo $consultation['id']; ?>" class="form-label">Edit Your Message</label>
                                    <textarea class="form-control" id="expert_message<?php echo $consultation['id']; ?>" name="expert_message" rows="3"><?php echo htmlspecialchars($consultation['expert_message'] ?? ''); ?></textarea>
                                </div>
                                <input type="hidden" name="consultation_id" value="<?php echo $consultation['id']; ?>">
                                <button type="submit" name="edit_message" class="btn btn-sm btn-primary">Save Changes</button>
                            </form>
                            
                            <div class="consultation-actions mt-3">
                                <a href="expert-chat.php?client_id=<?php echo $consultation['client_id']; ?>&consultation_id=<?php echo $consultation['id']; ?>" class="btn btn-primary">
                                    <i class="fas fa-comments me-2"></i> Go to Chat
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 class="empty-state-title">No Confirmed Consultations</h3>
                    <p class="empty-state-description">You don't have any confirmed consultations at the moment. When you accept consultation requests, they will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Completed Consultations Tab -->
        <div class="tab-pane fade" id="completed" role="tabpanel" aria-labelledby="completed-tab">
            <?php if (count($completed_consultations) > 0): ?>
                <?php foreach ($completed_consultations as $consultation): ?>
                    <div class="consultation-request fade-in">
                        <div class="consultation-header">
                            <?php if (!empty($consultation['client_image'])): ?>
                                <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="<?php echo htmlspecialchars($consultation['client_name']); ?>" class="client-avatar">
                            <?php else: ?>
                                <div class="client-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="client-info">
                                <h3 class="client-name"><?php echo htmlspecialchars($consultation['client_name']); ?></h3>
                                <div class="consultation-meta">
                                    <span><i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($consultation['consultation_date']); ?> at <?php echo formatTime($consultation['consultation_time']); ?></span>
                                    <span><i class="fas fa-check-double me-1"></i> Completed</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="consultation-body">
                            <div class="consultation-details">
                                <div class="detail-item">
                                    <span class="detail-label">Duration</span>
                                    <span class="detail-value"><?php echo $consultation['duration']; ?> minutes</span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Category</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($consultation['category_name'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Subcategory</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($consultation['subcategory_name'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <?php
                                // Get consultation price from payments table
                                $price_sql = "SELECT amount FROM payments WHERE consultation_id = ? LIMIT 1";
                                $price_stmt = $conn->prepare($price_sql);
                                $price_stmt->bind_param("i", $consultation['id']);
                                $price_stmt->execute();
                                $price_result = $price_stmt->get_result();
                                $price = $price_result->fetch_assoc();
                                $price_stmt->close();
                                ?>
                                
                                <?php if ($price): ?>
                                <div class="detail-item">
                                    <span class="detail-label">Price</span>
                                    <span class="detail-value"><?php echo number_format($price['amount']); ?> <?php echo $currency; ?></span>
                                </div>
                                <?php endif; ?>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Completed On</span>
                                    <span class="detail-value"><?php echo date('M d, Y - H:i', strtotime($consultation['updated_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="consultation-message">
                                <h5 class="mb-2">Client Message:</h5>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></p>
                            </div>
                            
                            <div class="consultation-actions">
    <a href="expert-chat.php?client_id=<?php echo $consultation['client_id']; ?>&consultation_id=<?php echo $consultation['id']; ?>" class="btn btn-outline-primary">
        <i class="fas fa-history me-2"></i> View Chat History
    </a>
</div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-check-double"></i>
                    </div>
                    <h3 class="empty-state-title">No Completed Consultations</h3>
                    <p class="empty-state-description">You don't have any completed consultations yet. Once consultations are completed, they will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Rejected Consultations Tab -->
        <div class="tab-pane fade" id="rejected" role="tabpanel" aria-labelledby="rejected-tab">
            <?php if (count($rejected_consultations) > 0): ?>
                <?php foreach ($rejected_consultations as $consultation): ?>
                    <div class="consultation-request fade-in">
                        <div class="consultation-header">
                            <?php if (!empty($consultation['client_image'])): ?>
                                <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="<?php echo htmlspecialchars($consultation['client_name']); ?>" class="client-avatar">
                            <?php else: ?>
                                <div class="client-avatar">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="client-info">
                                <h3 class="client-name"><?php echo htmlspecialchars($consultation['client_name']); ?></h3>
                                <div class="consultation-meta">
                                    <span><i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($consultation['consultation_date']); ?> at <?php echo formatTime($consultation['consultation_time']); ?></span>
                                    <span><i class="fas fa-times-circle me-1"></i> Rejected</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="consultation-body">
                            <div class="consultation-details">
                                <div class="detail-item">
                                    <span class="detail-label">Duration</span>
                                    <span class="detail-value"><?php echo $consultation['duration']; ?> minutes</span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Category</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($consultation['category_name'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Subcategory</span>
                                    <span class="detail-value"><?php echo htmlspecialchars($consultation['subcategory_name'] ?? 'N/A'); ?></span>
                                </div>
                                
                                <div class="detail-item">
                                    <span class="detail-label">Rejected On</span>
                                    <span class="detail-value"><?php echo date('M d, Y - H:i', strtotime($consultation['updated_at'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="consultation-message">
                                <h5 class="mb-2">Client Message:</h5>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></p>
                            </div>
                            
                            <?php if (!empty($consultation['rejection_reason'])): ?>
                            <div class="consultation-message mt-3" style="background-color: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2);">
                                <h5 class="mb-2 text-danger">Rejection Reason:</h5>
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['rejection_reason'])); ?></p>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h3 class="empty-state-title">No Rejected Consultations</h3>
                    <p class="empty-state-description">You don't have any rejected consultations. When you reject consultation requests, they will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
        <!-- Canceled Consultations Tab -->
<div class="tab-pane fade" id="canceled" role="tabpanel" aria-labelledby="canceled-tab">
    <?php if (count($canceled_consultations) > 0): ?>
        <?php foreach ($canceled_consultations as $consultation): ?>
            <div class="consultation-request fade-in">
                <div class="consultation-header">
                    <?php if (!empty($consultation['client_image'])): ?>
                        <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="<?php echo htmlspecialchars($consultation['client_name']); ?>" class="client-avatar">
                    <?php else: ?>
                        <div class="client-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                    <?php endif; ?>
                    
                    <div class="client-info">
                        <h3 class="client-name"><?php echo htmlspecialchars($consultation['client_name']); ?></h3>
                        <div class="consultation-meta">
                            <span><i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($consultation['consultation_date']); ?> at <?php echo formatTime($consultation['consultation_time']); ?></span>
                            <span><i class="fas fa-ban me-1"></i> Canceled</span>
                        </div>
                    </div>
                </div>
                
                <div class="consultation-body">
                    <div class="consultation-details">
                        <div class="detail-item">
                            <span class="detail-label">Duration</span>
                            <span class="detail-value"><?php echo $consultation['duration']; ?> minutes</span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Category</span>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['category_name'] ?? 'N/A'); ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Subcategory</span>
                            <span class="detail-value"><?php echo htmlspecialchars($consultation['subcategory_name'] ?? 'N/A'); ?></span>
                        </div>
                        
                        <div class="detail-item">
                            <span class="detail-label">Canceled On</span>
                            <span class="detail-value"><?php echo date('M d, Y - H:i', strtotime($consultation['canceled_at'])); ?></span>
                        </div>
                    </div>
                    
                    <div class="consultation-message">
                        <h5 class="mb-2">Client Message:</h5>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($consultation['notes'])); ?></p>
                    </div>
                    
                    <?php
                    // Check if there's a report for this consultation
                    $report_sql = "SELECT * FROM reports WHERE consultation_id = ? LIMIT 1";
                    $report_stmt = $conn->prepare($report_sql);
                    $report_stmt->bind_param("i", $consultation['id']);
                    $report_stmt->execute();
                    $report_result = $report_stmt->get_result();
                    $report = $report_result->fetch_assoc();
                    $report_stmt->close();
                    
                    if ($report): ?>
                    <div class="consultation-message mt-3" style="background-color: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2);">
                        <h5 class="mb-2 text-danger">Report Information:</h5>
                        <p><strong>Type:</strong> <?php echo htmlspecialchars($report['report_type']); ?></p>
                        <p><strong>Message:</strong> <?php echo nl2br(htmlspecialchars($report['message'])); ?></p>
                        <p><strong>Status:</strong> <?php echo ucfirst(htmlspecialchars($report['status'])); ?></p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="consultation-actions">
                        <a href="expert-chat.php?client_id=<?php echo $consultation['client_id']; ?>&consultation_id=<?php echo $consultation['id']; ?>" class="btn btn-outline-primary">
                            <i class="fas fa-history me-2"></i> View Chat History
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon">
                <i class="fas fa-ban"></i>
            </div>
            <h3 class="empty-state-title">No Canceled Consultations</h3>
            <p class="empty-state-description">You don't have any canceled consultations. When clients cancel their consultation requests, they will appear here.</p>
        </div>
    <?php endif; ?>
</div>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="container footer-content">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5>About <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></h5>
                <p class="mb-4"><?php echo htmlspecialchars($settings['site_description'] ?? 'Expert Consultation Platform connecting experts with clients for professional consultations.'); ?></p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="home-profile.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="expert-profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="expert-consultations.php"><i class="fas fa-laptop-code"></i> Consultations</a></li>
                    <li><a href="expert-earnings.php"><i class="fas fa-chart-line"></i> Earnings</a></li>
                    <li><a href="expert-avis.php"><i class="fas fa-star"></i> Reviews</a></li>
                    <li><a href="expert-contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Contact</h5>
                <ul class="footer-links">
                    <?php if (!empty($settings['site_name'])): ?>
                        <li><i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($settings['site_name']); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['site_email'])): ?>
                        <li><a href="mailto:<?php echo htmlspecialchars($settings['site_email']); ?>"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($settings['site_email']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['phone_number1'])): ?>
                        <li><a href="tel:<?php echo htmlspecialchars($settings['phone_number1']); ?>"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($settings['phone_number1']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['phone_number2'])): ?>
                        <li><a href="tel:<?php echo htmlspecialchars($settings['phone_number2']); ?>"><i class="fas fa-phone-alt me-2"></i> <?php echo htmlspecialchars($settings['phone_number2']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['facebook_url'])): ?>
                        <li><a href="<?php echo htmlspecialchars($settings['facebook_url']); ?>" target="_blank"><i class="fab fa-facebook me-2"></i> Facebook</a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['instagram_url'])): ?>
                        <li><a href="<?php echo htmlspecialchars($settings['instagram_url']); ?>" target="_blank"><i class="fab fa-instagram me-2"></i> Instagram</a></li>
                    <?php endif; ?>
                </ul>
                <p class="mt-3 mb-0">Need help? <a href="expert-contact.php" class="text-primary font-weight-bold">Contact Us</a></p>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo isset($settings['site_name']) ? htmlspecialchars($settings['site_name']) : ' '; ?>. All rights reserved. </p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Store the previous pending consultations count
    let previousPendingCount = 0;

    // Fetch notifications function
    function fetchNotifications() {
        fetch('expert-consultations.php?fetch_notifications=true')
            .then(response => response.json())
            .then(data => {
                // Update notification badges
                updateNotificationBadge('.pending-consultations-badge', data.pending_consultations);
                updateNotificationBadge('.pending-withdrawals-badge', data.pending_withdrawals);
                updateNotificationBadge('.admin-messages-badge', data.admin_messages);
                updateNotificationBadge('.community-messages-badge', data.community_messages);
                updateNotificationBadge('.forums_messages-badge', data.forums_messages);
                updateNotificationBadge('.reviews-badge', data.reviews);
                updateNotificationBadge('.notifications-not-read-badge', data.notifications_not_read);
                updateNotificationBadge('.total-notifications-badge', data.total);
            
            // Check if pending consultations count has changed
            if (data.pending_consultations !== previousPendingCount) {
                
                refreshPendingRequestsSection();
                
                // Update the previous count
                previousPendingCount = data.pending_consultations;
            }
        })
        .catch(error => console.error('Error fetching notifications:', error));
}

// Function to refresh only the Pending Requests section
function refreshPendingRequestsSection() {
    fetch('expert-consultations.php')
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newPendingSection = doc.querySelector('#pending');
            
            if (newPendingSection) {
                const currentPendingSection = document.querySelector('#pending');
                
if (currentPendingSection) {
                    currentPendingSection.innerHTML = newPendingSection.innerHTML;
                    
                    // Reinitialize any event listeners or Bootstrap components
                    initializeModalEventListeners();
                }
            }
        })
        .catch(error => console.error('Error refreshing pending requests:', error));
}

// Function to initialize event listeners for modals
function initializeModalEventListeners() {
    // Reinitialize Bootstrap modals
    document.querySelectorAll('[data-bs-toggle="modal"]').forEach(button => {
        button.addEventListener('click', function() {
            const target = this.getAttribute('data-bs-target');
            const modal = new bootstrap.Modal(document.querySelector(target));
            modal.show();
        });
    });
    
    // Reinitialize form submissions
    document.querySelectorAll('#pending form').forEach(form => {
        form.addEventListener('submit', function() {
            const submitButton = this.querySelector('button[type="submit"]');
            if (submitButton) {
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...';
                submitButton.disabled = true;
            }
        });
    });
}
    
    // Update notification badge function
    function updateNotificationBadge(selector, count) {
        const badge = document.querySelector(selector);
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.add('show');
            } else {
                badge.textContent = '';
                badge.classList.remove('show');
            }
        }
    }
    
    // Initial fetch
    fetchNotifications();
    
    // Set interval to fetch notifications every second
    setInterval(fetchNotifications, 1000);
    
    // Add animation classes on scroll
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, {
        threshold: 0.1
    });
    
    document.querySelectorAll('.consultation-request, .dashboard-card').forEach(el => {
        observer.observe(el);
    });
    
    // Only create these elements on desktop
    if (window.innerWidth > 768) {
            document.addEventListener("DOMContentLoaded", () => {
                // Parallax effect for shapes
                if (document.querySelector(".shape")) {
                  document.addEventListener("mousemove", (e) => {
                    const moveX = (e.clientX - window.innerWidth / 2) / 30
                    const moveY = (e.clientY - window.innerHeight / 2) / 30
              
                    document.querySelectorAll(".shape").forEach((shape) => {
                      const speed = Number.parseFloat(shape.getAttribute("data-speed") || 1)
                      shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`
                    })
                  })
                }
              
                // We no longer need to refresh the entire page since we're refreshing the pending section dynamically
                // setInterval(() => {
                //   if (!document.querySelector(".modal.show")) {
                //     location.reload()
                //   }
                // }, 10000)
              
                // Handle form submissions with loading state
                document.querySelectorAll("form").forEach((form) => {
                  form.addEventListener("submit", function () {
                    const submitButton = this.querySelector('button[type="submit"]')
                    if (submitButton) {
                      submitButton.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...'
                      submitButton.disabled = true
                    }
                  })
                })
              
                // Auto-hide alerts after 5 seconds
                setTimeout(() => {
                  document.querySelectorAll(".alert").forEach((alert) => {
                    alert.classList.add("fade")
                    setTimeout(() => {
                      alert.remove()
                    }, 500)
                  })
                }, 5000)
              
                // Initialize tooltips
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
                tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl))
              })
    }
});
</script>
</body>
</html>
<?php
$conn->close();
?>
