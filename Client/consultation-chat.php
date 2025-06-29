<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "client") {
    // User is not logged in, redirect to login page
    header("Location: ../config/logout.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];
$success_message = "";
$error_message = "";
$consultation_id = isset($_GET['id']) ? intval($_GET['id']) : 0;


// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get user data
$sql = "SELECT u.*, up.phone, up.address, up.profile_image, up.bio, u.balance
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get site settings
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Validate consultation
if ($consultation_id > 0) {
    // Check if consultation exists and belongs to this client
    $consult_sql = "SELECT c.*, u.full_name as expert_name, u.email as expert_email, u.status as expert_status, 
                    up.profile_image as expert_image, up.bio as expert_bio,
                    ep.id as profile_id, ep.category, ep.subcategory, 
                    cat.name as category_name, sc.name as subcategory_name,
                    cs.id as chat_session_id, ct.id as timer_id, ct.status as timer_status,
                    TIMESTAMPDIFF(SECOND, ct.started_at, NOW()) as elapsed_seconds,
                    p.amount as payment_amount, p.status as payment_status
                    FROM consultations c 
                    JOIN users u ON c.expert_id = u.id 
                    LEFT JOIN user_profiles up ON u.id = up.user_id 
                    LEFT JOIN expert_profiledetails ep ON u.id = ep.user_id
                    LEFT JOIN categories cat ON ep.category = cat.id
                    LEFT JOIN subcategories sc ON ep.subcategory = sc.id
                    LEFT JOIN chat_sessions cs ON c.id = cs.consultation_id
                    LEFT JOIN chat_timers ct ON cs.id = ct.chat_session_id
                    LEFT JOIN payments p ON c.id = p.consultation_id
                    WHERE c.id = ? AND c.client_id = ?";
    $consult_stmt = $conn->prepare($consult_sql);
    $consult_stmt->bind_param("ii", $consultation_id, $user_id);
    $consult_stmt->execute();
    $consult_result = $consult_stmt->get_result();
    
    if ($consult_result->num_rows == 0) {
        // Consultation not found or doesn't belong to this client
        $error_message = "Consultation not found or you don't have permission to access it.";
        // Redirect to consultations page after 3 seconds
        header("Refresh: 3; URL=my-consultations.php");
    } else {
        $consultation = $consult_result->fetch_assoc();
        $chat_session_id = $consultation['chat_session_id'];
        $timer_id = $consultation['timer_id'];
        $timer_status = $consultation['timer_status'];
        $expert_id = $consultation['expert_id'];
        
        // Check if chat session exists
        if (!$chat_session_id) {
            // Wait for expert to start the session
            $error_message = "Waiting for the expert to start the consultation. Please refresh the page in a few moments.";
        } else {
            // Calculate remaining time
            $elapsed_seconds = $consultation['elapsed_seconds'] ?? 0;
            $total_seconds = $consultation['duration'] * 60; // Convert minutes to seconds
            $remaining_seconds = max(0, $total_seconds - $elapsed_seconds);
            
            // Get chat messages
            $messages = [];
            $messages_sql = "SELECT cm.*, u.full_name, up.profile_image, cm.message_type, cm.file_path 
                           FROM chat_messages cm
                           JOIN users u ON cm.sender_id = u.id
                           LEFT JOIN user_profiles up ON u.id = up.user_id
                           WHERE cm.chat_session_id = ?
                           ORDER BY cm.created_at ASC";
            $messages_stmt = $conn->prepare($messages_sql);
            $messages_stmt->bind_param("i", $chat_session_id);
            $messages_stmt->execute();
            $messages_result = $messages_stmt->get_result();
            
            if ($messages_result && $messages_result->num_rows > 0) {
                while ($row = $messages_result->fetch_assoc()) {
                    $messages[] = $row;
                }
            }
            $user_fullname = $user['full_name'];
            // Handle message sending
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
                $message_content = sanitize_input($_POST['message_content']);
                $sender_type = 'client';
                $message_type = 'text';
                $file_path = null;
                
                // Handle file upload
                if (isset($_FILES['message_file']) && $_FILES['message_file']['error'] == 0) {
                    $allowed = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt');
                    $filename = $_FILES['message_file']['name'];
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    
                    if (in_array(strtolower($ext), $allowed)) {
                        $unique_filename = uniqid() . '_' . time() . '.' . $ext;
                        $upload_dir = '../uploads/chat_files/';
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_path = $upload_dir . $unique_filename;
                        
                        if (move_uploaded_file($_FILES['message_file']['tmp_name'], $file_path)) {
                            // Determine message type based on extension
                            if (in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif'])) {
                                $message_type = 'image';
                            } else {
                                $message_type = 'file';
                            }
                        }
                    }
                }
                
                if ((!empty($message_content) || $file_path) && $chat_session_id) {
                    $insert_message_sql = "INSERT INTO chat_messages (sender_id, receiver_id, consultation_id, message, file_path, sender_type, created_at, chat_session_id, message_type) 
                                          VALUES (?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
                    $insert_message_stmt = $conn->prepare($insert_message_sql);
                    $insert_message_stmt->bind_param("iiisssis", $user_id, $expert_id, $consultation_id, $message_content, $file_path, $sender_type, $chat_session_id, $message_type);
                    
                    if ($insert_message_stmt->execute()) {
                      
                        // Redirect to avoid form resubmission
                        header("Location: consultation-chat.php?id=$consultation_id");
                        exit();
                    } else {
                        $error_message = "Failed to send message. Please try again.";
                    }
                }
            }
            
            // Handle end consultation
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['end_consultation'])) {
                // First stop the timer if it's still running
                if ($timer_status === 'running' && $timer_id) {
                    $stop_timer_sql = "UPDATE chat_timers SET ended_at = NOW(), status = 'stopped', 
                                      duration = TIMESTAMPDIFF(SECOND, started_at, NOW()) 
                                      WHERE id = ?";
                    $stop_timer_stmt = $conn->prepare($stop_timer_sql);
                    $stop_timer_stmt->bind_param("i", $timer_id);
                    $stop_timer_stmt->execute();
                }
                
                // Update chat session status to ended
                $update_session_sql = "UPDATE chat_sessions SET ended_at = NOW(), status = 'ended' WHERE id = ?";
                $update_session_stmt = $conn->prepare($update_session_sql);
                $update_session_stmt->bind_param("i", $chat_session_id);
                $update_session_stmt->execute();
                
                // Update consultation status to completed
                $update_consult_sql = "UPDATE consultations SET status = 'completed', updated_at = NOW() WHERE id = ?";
                $update_consult_stmt = $conn->prepare($update_consult_sql);
                $update_consult_stmt->bind_param("i", $consultation_id);
                
                if ($update_consult_stmt->execute()) {
                    // Calculate pro-rated payment based on time used
                    $elapsed_minutes = ceil($elapsed_seconds / 60);
                    $total_minutes = $consultation['duration'];
                    $amount_to_pay = $consultation['payment_amount'];
                    
                    // Calculate the amount to pay based on time used (pro-rated)
                    $amount_to_pay = round($amount_to_pay, 2);
                    
                    
                    // Update payment status to completed with pro-rated amount
                    $update_payment_sql = "UPDATE payments SET amount = ?, status = 'completed' WHERE consultation_id = ? AND status = 'processing'";
                    $update_payment_stmt = $conn->prepare($update_payment_sql);
                    $update_payment_stmt->bind_param("di", $amount_to_pay, $consultation_id);
                    $update_payment_stmt->execute();
                    
                    // Fetch client and expert balances
                    $client_sql = "SELECT balance FROM users WHERE id = ?";
                    $client_stmt = $conn->prepare($client_sql);
                    $client_stmt->bind_param("i", $user_id);
                    $client_stmt->execute();
                    $client_result = $client_stmt->get_result();
                    $client_balance = $client_result->fetch_assoc()['balance'];
                    
                    $expert_sql = "SELECT balance FROM users WHERE id = ?";
                    $expert_stmt = $conn->prepare($expert_sql);
                    $expert_stmt->bind_param("i", $expert_id);
                    $expert_stmt->execute();
                    $expert_result = $expert_stmt->get_result();
                    $expert_balance = $expert_result->fetch_assoc()['balance'];
                    
                  
                    // Update expert balance (add pro-rated amount)
                    $new_expert_balance = $expert_balance + $amount_to_pay;
                    $update_expert_sql = "UPDATE users SET balance = ? WHERE id = ?";
                    $update_expert_stmt = $conn->prepare($update_expert_sql);
                    $update_expert_stmt->bind_param("di", $new_expert_balance, $expert_id);
                    $update_expert_stmt->execute();
                    
                    // Add notification for expert
                    $notification_sql = "INSERT INTO expert_notifications (user_id, profile_id, notification_type, message, related_id, is_read, created_at) 
                                        VALUES (?, ?, 'consultation_ended', '$user_fullname has ended their consultation.', ?, 0, NOW())";
                    $profile_id = $consultation['profile_id'];
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bind_param("iii", $expert_id, $profile_id, $consultation_id);
                    $notification_stmt->execute();
                    
                    // Show completion modal
                    echo "<script>
                        document.addEventListener('DOMContentLoaded', function() {
                            openModal('consultationCompletedModal');
                        });
                    </script>";
                } else {
                    $error_message = "Failed to end consultation. Please try again.";
                }
            }
            
            // Handle rating submission
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_rating'])) {
                $rating = intval($_POST['rating']);
                $comment = sanitize_input($_POST['rating_comment']);
                
                if ($rating >= 1 && $rating <= 5) {
                    // First check if a rating already exists for this consultation
                    $check_rating_sql = "SELECT id FROM expert_ratings WHERE expert_id = ? AND client_id = ? AND consultation_id = ? LIMIT 1";
                    $check_rating_stmt = $conn->prepare($check_rating_sql);
                    $check_rating_stmt->bind_param("iii", $expert_id, $user_id, $consultation_id);
                    $check_rating_stmt->execute();
                    $existing_rating = $check_rating_stmt->get_result();
                    
                    if ($existing_rating->num_rows > 0) {
                        // Update existing rating
                        $rating_id = $existing_rating->fetch_assoc()['id'];
                        $update_rating_sql = "UPDATE expert_ratings SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?";
                        $update_rating_stmt = $conn->prepare($update_rating_sql);
                        $update_rating_stmt->bind_param("isi", $rating, $comment, $rating_id);
                        $rating_success = $update_rating_stmt->execute();
                    } else {
                        // Insert new rating
                        $insert_rating_sql = "INSERT INTO expert_ratings (expert_id, client_id, consultation_id, rating, comment, created_at) 
                                             VALUES (?, ?, ?, ?, ?, NOW())";
                        $insert_rating_stmt = $conn->prepare($insert_rating_sql);
                        $insert_rating_stmt->bind_param("iiiis", $expert_id, $user_id, $consultation_id, $rating, $comment);
                        $rating_success = $insert_rating_stmt->execute();
                    }
                    
                    if ($rating_success) {
                        // Add notification for expert
                        $notification_sql = "INSERT INTO expert_notifications (user_id, profile_id, notification_type, message, related_id, is_read, created_at) 
                                            VALUES (?, ?, 'new_rating', 'You have received a new rating from $user_fullname.', NULL, 0, NOW())";
                        $profile_id = $consultation['profile_id'];
                        $notification_stmt = $conn->prepare($notification_sql);
                        $notification_stmt->bind_param("ii", $expert_id, $profile_id);
                        $notification_stmt->execute();
                        
                        $success_message = "Thank you for your rating!";
                        
                        // We don't redirect here to avoid page refresh
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                showRatingSuccess();
                            });
                        </script>";
                    } else {
                        $error_message = "Failed to submit rating. Please try again.";
                    }
                } else {
                    $error_message = "Invalid rating. Please select a rating between 1 and 5 stars.";
                }
            }
            
            // Handle report submission
            if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_report'])) {
                $report_type = sanitize_input($_POST['report_type']);
                $report_message = sanitize_input($_POST['report_message']);
                
                $report_sql = "INSERT INTO reports (consultation_id, reporter_id, reported_id, report_type, message, created_at, status) 
                              VALUES (?, ?, ?, ?, ?, NOW(), 'pending')";
                $report_stmt = $conn->prepare($report_sql);
                $report_stmt->bind_param("iiiss", $consultation_id, $user_id, $expert_id, $report_type, $report_message);
                
                if ($report_stmt->execute()) {
                    // Add notification for admin
                    $admin_notification_sql = "INSERT INTO admin_notifications (user_id, notification_type, message, related_id, is_read, created_at) 
                                              VALUES (0, '', 'New report from user #$user_id regarding consultation #$consultation_id', NULL, 0, NOW())";
                    $conn->query($admin_notification_sql);
                    
                    $success_message = "Your report has been submitted. Our team will review it shortly.";
                } else {
                    $error_message = "Failed to submit report. Please try again.";
                }
            }
        }
    }
} else {
    $error_message = "Invalid consultation ID.";
    // Redirect to consultations page after 3 seconds
    header("Refresh: 3; URL=my-consultations.php");
}

// Get currency from settings
$currency = $settings['currency'] ?? 'DA';

// Get notification counts
$notifications_query = "SELECT COUNT(*) as count FROM client_notifications WHERE user_id = ? AND is_read = 0";
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();
$notification_count = $notifications_result->fetch_assoc()['count'];

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Chat - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://gstatic.googleapis.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Primary Colors */
            --primary-50: #eef2ff;
            --primary-100: #e0e7ff;
            --primary-200: #c7d2fe;
            --primary-300: #a5b4fc;
            --primary-400: #818cf8;
            --primary-500: #6366f1;
            --primary-600: #4f46e5;
            --primary-700: #4338ca;
            --primary-800: #3730a3;
            --primary-900: #312e81;
            --primary-950: #1e1b4b;
            
            /* Secondary Colors */
            --secondary-50: #f0fdfa;
            --secondary-100: #ccfbf1;
            --secondary-200: #99f6e4;
            --secondary-300: #5eead4;
            --secondary-400: #2dd4bf;
            --secondary-500: #14b8a6;
            --secondary-600: #0d9488;
            --secondary-700: #0f766e;
            --secondary-800: #115e59;
            --secondary-900: #134e4a;
            --secondary-950: #042f2e;
            
            /* Neutral Colors */
            --neutral-50: #f8fafc;
            --neutral-100: #f1f5f9;
            --neutral-200: #e2e8f0;
            --neutral-300: #cbd5e1;
            --neutral-400: #94a3b8;
            --neutral-500: #64748b;
            --neutral-600: #475569;
            --neutral-700: #334155;
            --neutral-800: #1e293b;
            --neutral-900: #0f172a;
            --neutral-950: #020617;
            
            /* Success, Warning, Danger */
            --success-500: #10b981;
            --warning-500: #f59e0b;
            --danger-500: #ef4444;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            
            /* Fonts */
            --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            --font-display: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: var(--font-sans);
            color: var(--neutral-800);
            background-color: var(--neutral-100);
            line-height: 1.6;
            overflow-x: hidden;
        }
        
        /* Navbar Styles from ../index.php */
        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--box-shadow);
            transition: all 0.4s ease;
            padding: 20px 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }

        .navbar.scrolled {
            padding: 12px 0;
            box-shadow: var(--box-shadow-md);
            background-color: rgba(255, 255, 255, 0.98);
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--primary-600);
            transition: transform 0.3s ease;
            font-family: 'Montserrat', sans-serif;
        }

        .navbar-brand:hover {
            transform: scale(1.05);
            color: var(--primary-700);
        }

        .navbar-brand img {
            height: 40px;
            transition: var(--transition);
        }

        .navbar.scrolled .navbar-brand img {
            height: 35px;
        }

        .nav-link {
            position: relative;
            margin: 0 12px;
            padding: 8px 0;
            font-weight: 600;
            color: var(--gray-700);
            transition: color 0.3s ease;
            font-size: 0.95rem;
        }

        .nav-link:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-600);
            transition: width 0.3s ease;
            border-radius: 2px;
        }

        .nav-link:hover {
            color: var(--primary-600);
        }

        .nav-link:hover:after, .nav-link.active:after {
            width: 100%;
        }

        .nav-link.active {
            color: var(--primary-600);
            font-weight: 700;
        }
        
        /* Main Content Styles */
        .main-container {
            padding: 2rem 0;
            position: relative;
            z-index: 1;
        }
        
        /* Chat Container */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: calc(100vh - 180px);
            background-color: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--neutral-200);
        }
        
        /* Notification Badge Styles */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background-color: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            animation: pulse 1.5s infinite;
            z-index: 10;
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
        
        /* Chat Header */
        .chat-header {
            padding: 1rem 1.5rem;
            background: linear-gradient(to right, var(--primary-600), var(--primary-500));
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--primary-700);
        }
        
        .chat-header-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .chat-header-avatar {
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: var(--shadow-md);
        }
        
        .chat-header-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .chat-header-status {
            font-size: 0.85rem;
            opacity: 0.9;
        }
        
        .chat-timer {
            background-color: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            backdrop-filter: blur(5px);
            box-shadow: var(--shadow-sm);
        }
        
        /* Chat body */
        .chat-body {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            background-color: var(--neutral-50);
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .chat-message {
            max-width: 80%;
            position: relative;
            padding: 0.75rem 1rem;
            border-radius: 1rem;
            box-shadow: var(--shadow-sm);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .chat-message-client {
            align-self: flex-end;
            background-color: var(--primary-500);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }
        
        .chat-message-expert {
            align-self: flex-start;
            background-color: white;
            color: var(--neutral-800);
            border: 1px solid var(--neutral-200);
            border-bottom-left-radius: 0.25rem;
        }
        
        .chat-message-sender {
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            opacity: 0.8;
        }
        
        .chat-message-time {
            font-size: 0.7rem;
            margin-top: 0.25rem;
            text-align: right;
            opacity: 0.7;
        }
        
        .chat-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--neutral-500);
            text-align: center;
            padding: 2rem;
        }
        
        .chat-empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--primary-300);
        }
        
        /* Chat Footer */
        .chat-footer {
            padding: 1rem 1.5rem;
            background-color: white;
            border-top: 1px solid var(--neutral-200);
        }
        
        .chat-input-container {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .chat-input {
            flex-grow: 1;
            border: 1px solid var(--neutral-300);
            border-radius: 2rem;
            padding: 0.75rem 1.25rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }
        
        .chat-input:focus {
            outline: none;
            border-color: var(--primary-400);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        .chat-send-btn {
            background: linear-gradient(to right, var(--primary-600), var(--primary-500));
            color: white;
            border: none;
            border-radius: 50%;
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-md);
        }
        
        .chat-send-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(to right, var(--primary-700), var(--primary-600));
        }
        
        .chat-send-btn:active {
            transform: translateY(0);
        }
        
        .chat-send-btn:disabled {
            background: var(--neutral-400);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* Expert Info Card */
        .expert-info {
            background-color: white;
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
            height: 100%;
            border: 1px solid var(--neutral-200);
            overflow: hidden;
        }
        
        .expert-info-header {
            background: linear-gradient(to right, var(--primary-600), var(--primary-500));
            color: white;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .expert-info-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }
        
        .expert-avatar-lg {
            width: 6rem;
            height: 6rem;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--shadow-lg);
            margin: 0 auto 1rem;
            display: block;
        }
        
        .expert-name {
            font-weight: 700;
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 0.25rem;
        }
        
        .expert-email {
            text-align: center;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .expert-info-body {
            padding: 1.5rem;
        }
        
        .expert-info-section {
            margin-bottom: 1.5rem;
        }
        
        .expert-info-title {
            font-weight: 600;
            color: var(--neutral-700);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--neutral-200);
            font-size: 1.1rem;
        }
        
        .expert-info-item {
            margin-bottom: 1rem;
        }
        
        .expert-info-label {
            font-weight: 500;
            color: var(--neutral-600);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .expert-info-value {
            color: var(--neutral-900);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.confirmed {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-500);
        }
        
        .status-badge.canceled{
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-500);

        }
        
        .status-badge.completed {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-600);
        }
        
        /* Client Actions */
        .client-actions {
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.75rem 1.25rem;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-600), var(--primary-500));
            border: none;
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .btn-primary:hover {
            background: linear-gradient(to right, var(--primary-700), var(--primary-600));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-danger {
            background: linear-gradient(to right, var(--danger-500), #f87171);
            border: none;
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .btn-danger:hover {
            background: linear-gradient(to right, #dc2626, var(--danger-500));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn-warning {
            background: linear-gradient(to right, var(--warning-500), #fbbf24);
            border: none;
            color: white;
            box-shadow: var(--shadow-md);
        }
        
        .btn-warning:hover {
            background: linear-gradient(to right, #d97706, var(--warning-500));
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1050;
            overflow: auto;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background-color: white;
            margin: auto;
            width: 90%;
            max-width: 500px;
            border-radius: 1rem;
            box-shadow: var(--shadow-xl);
            animation: modalFadeIn 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            padding: 1.5rem;
            background: linear-gradient(to right, var(--primary-600), var(--primary-500));
            color: white;
            position: relative;
        }
        
        .modal-title {
            font-weight: 600;
            margin: 0;
            font-size: 1.25rem;
        }
        
        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .modal-close:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            background-color: var(--neutral-50);
            border-top: 1px solid var(--neutral-200);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        /* Form Elements */
        .form-group {
            margin-bottom: 1.25rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--neutral-700);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--neutral-300);
            border-radius: 0.5rem;
            font-size: 0.95rem;
            transition: all 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-400);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        /* Alert Styles */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            border-left: 4px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: alertFadeIn 0.3s ease;
        }
        
        @keyframes alertFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border-color: var(--success-500);
            color: var(--success-500);
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border-color: var(--danger-500);
            color: var(--danger-500);
        }
        
        .alert-icon {
            font-size: 1.25rem;
        }
        
        /* Timer Warning States */
        .timer-warning {
            color: var(--warning-500);
            animation: pulse-warning 1s infinite;
        }
        
        .timer-danger {
            color: var(--danger-500);
            animation: pulse-danger 1s infinite;
        }
        
        @keyframes pulse-warning {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        @keyframes pulse-danger {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }
        
        /* Chat message attachments */
        .chat-message-attachment {
            margin-top: 0.75rem;
            border-radius: 0.5rem;
            overflow: hidden;
        }
        
        .chat-message-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .chat-message-image:hover {
            opacity: 0.9;
        }
        
        .chat-message-file {
            display: flex;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.2);
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 0.5rem;
        }
        
        .chat-message-expert .chat-message-file {
            background-color: rgba(0, 0, 0, 0.05);
            border: 1px solid var(--neutral-200);
        }
        
        .chat-message-file-icon {
            font-size: 1.5rem;
            margin-right: 0.75rem;
            color: var(--primary-300);
        }
        
        .chat-message-expert .chat-message-file-icon {
            color: var(--primary-600);
        }
        
        .chat-message-file-info {
            flex-grow: 1;
        }
        
        .chat-message-file-name {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
            color: white;
        }
        
        .chat-message-expert .chat-message-file-name {
            color: var(--neutral-800);
        }
        
        .chat-message-file-size {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .chat-message-expert .chat-message-file-size {
            color: var(--neutral-500);
        }
        
        .chat-message-file-download {
            color: white;
            font-size: 1.25rem;
            transition: all 0.2s ease;
        }
        
        .chat-message-expert .chat-message-file-download {
            color: var(--primary-600);
        }
        
        .chat-message-file-download:hover {
            transform: translateY(-2px);
        }
        
        .file-upload-btn {
            background-color: var(--neutral-200);
            color: var(--neutral-700);
            border: none;
            border-radius: 50%;
            width: 3rem;
            height: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
            flex-shrink: 0;
            cursor: pointer;
        }
        
        .file-upload-btn:hover {
            background-color: var(--neutral-300);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .file-upload-btn:active {
            transform: translateY(0);
        }
        
        .file-upload-input {
            display: none;
        }
        
        /* Rating Stars */
        .rating-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .rating-stars {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .rating-star {
            font-size: 2rem;
            color: var(--neutral-300);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .rating-star:hover, .rating-star.active {
            color: var(--warning-500);
            transform: scale(1.1);
        }
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .chat-container {
                height: calc(100vh - 220px);
            }
            
            .expert-info {
                margin-bottom: 1.5rem;
            }
        }
        
        @media (max-width: 767.98px) {
            .chat-container {
                height: calc(100vh - 280px);
            }
            
            .chat-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
                padding: 1rem;
            }
            
            .chat-timer {
                align-self: flex-end;
            }
            
            .expert-avatar-lg {
                width: 5rem;
                height: 5rem;
            }
            
            .expert-name {
                font-size: 1.25rem;
            }
            
            .chat-message {
                max-width: 90%;
            }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--neutral-100);
            border-radius: 8px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-300);
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-400);
        }

        /* Ajouter dans la section des styles CSS */
        @keyframes newMessageFadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .new-message-animation {
            animation: newMessageFadeIn 0.5s ease-out;
        }

        #loading-indicator {
            color: var(--neutral-500);
            font-size: 0.9rem;
        }

        #loading-indicator i {
            margin-right: 5px;
        }
    </style>
</head>
<body>
<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand" href="../index.php">
            <?php if(isset($settings['site_image']) && !empty($settings['site_image'])): ?>
                <img src="../uploads/<?php echo $settings['site_image']; ?>" alt="<?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>" height="40">
            <?php else: ?>
                <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="find-experts.php">Find Experts</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="my-consultations.php">Consultations</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="how-it-works.php">How It Works</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact-support.php">Contact Support</a>
                </li>
            </ul>
            <div class="d-flex align-items-center">
                <div class="dropdown me-3">
                    <a class="position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-bell fs-5 text-gray-700"></i>
                        <span class="notification-badge" id="notification-badge"><?php echo $notification_count; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" style="border-radius: 12px;">
                        <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
                        <div id="notifications-container" style="font-size:12px;">
                            <li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>
                        </div>
                        <li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="notifications.php">View All</a></li>
                    </ul>
                </div>
                <div class="dropdown">
                    <a class="btn btn-outline-primary dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="rounded-circle" width="30" height="30">
                        <?php else: ?>
                            <i class="fas fa-user-circle"></i>
                        <?php endif; ?>
                        <span class="d-none d-md-inline">
                            <?php echo htmlspecialchars($user['full_name']); ?>
                            <span class="badge bg-success ms-2"><?php echo number_format($user['balance'], 2) . ' ' . $currency; ?></span>
                        </span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="userDropdown" style="border-radius: 12px;">
                        <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
                        <li><a class="dropdown-item py-2" href="add-fund.php"><i class="fas fa-wallet me-2 text-primary"></i> Add Fund: <span class="badge bg-success float-end"><?php echo number_format($user['balance'], 2) . ' ' . $currency; ?></span></a></li>
                        <li><a class="dropdown-item py-2" href="my-consultations.php"><i class="fas fa-calendar-check me-2 text-primary"></i> My Consultations</a></li>
                        <li><a class="dropdown-item py-2" href="messages.php"><i class="fas fa-envelope me-2 text-primary"></i> Messages</a></li>
                        <li><a class="dropdown-item py-2" href="my-reports.php"><i class="fas fa-flag me-2 text-primary"></i> My Reports</a></li>
                                                    <li><a class="dropdown-item py-2" href="history-ratings.php"><i class="fas fa-solid fa-star text-primary"></i> Ratings</a></li>

                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2" href="../config/logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container main-container">
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle alert-icon"></i>
            <div><?php echo $success_message; ?></div>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle alert-icon"></i>
            <div><?php echo $error_message; ?></div>
        </div>
    <?php endif; ?>

    <?php if ($consultation['status'] === 'canceled'): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle alert-icon"></i>
            <div>This Consultation has been canceled by expert</div>
        </div>
        <script>
            // Stop timer and redirect to consultations page after 3 seconds
            if (window.timerInterval) {
                clearInterval(window.timerInterval);
                document.getElementById('timer-display').innerHTML = '00:00';
            }
            setTimeout(function() {
                window.location.href = "my-consultations.php";
            }, 3000);
        </script>
    <?php endif; ?>

    
    <?php if (isset($consultation)): ?>
        <div class="row">
            <!-- Chat Section -->
            <div class="col-lg-8 mb-4">
                <div class="chat-container">
                    <div class="chat-header">
                        <div class="chat-header-info">
                            <?php if (!empty($consultation['expert_image'])): ?>
                                <img src="<?php echo htmlspecialchars($consultation['expert_image']); ?>" alt="<?php echo htmlspecialchars($consultation['expert_name']); ?>" class="chat-header-avatar">
                            <?php else: ?>
                                <div class="chat-header-avatar d-flex align-items-center justify-content-center bg-primary">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h5 class="chat-header-name"><?php echo htmlspecialchars($consultation['expert_name']); ?></h5>
                                <div class="chat-header-status">
                                    <span class="badge <?php echo $consultation['expert_status'] === 'Online' ? 'bg-success' : 'bg-secondary'; ?>">
                                        <?php echo $consultation['expert_status']; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="chat-timer" id="consultation-timer">
                            <i class="fas fa-clock timer-icon"></i>
                            <span id="timer-display">
                                <?php 
                                $minutes = floor($remaining_seconds / 60);
                                $seconds = $remaining_seconds % 60;
                                echo sprintf('%02d:%02d', $minutes, $seconds);
                                ?>
                            </span>
                        </div>
                    </div>
                    <div class="chat-body" id="chat-messages">
                        <?php if(empty($messages)): ?>
                            <div class="chat-empty-state">
                                <i class="fas fa-comments chat-empty-icon"></i>
                                <h4>Your consultation has started</h4>
                                <p>Send a message to begin the conversation with your expert.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($messages as $message): ?>
                                <div class="chat-message <?php echo $message['sender_id'] == $user_id ? 'chat-message-client' : 'chat-message-expert'; ?>" data-message-id="<?php echo $message['id']; ?>">
                                    <div class="chat-message-sender">
                                        <?php echo htmlspecialchars($message['full_name']); ?>
                                    </div>
                                    <?php echo htmlspecialchars($message['message']); ?>
                                    
                                    <?php if($message['message_type'] === 'image' && !empty($message['file_path'])): ?>
                                        <div class="chat-message-attachment">
                                            <img src="<?php echo $message['file_path']; ?>" alt="Attached image" class="chat-message-image" onclick="openImageModal('<?php echo $message['file_path']; ?>')">
                                        </div>
                                    <?php elseif($message['message_type'] === 'file' && !empty($message['file_path'])): ?>
                                        <div class="chat-message-file">
                                            <i class="fas fa-file-alt chat-message-file-icon"></i>
                                            <div class="chat-message-file-info">
                                                <div class="chat-message-file-name"><?php echo basename($message['file_path']); ?></div>
                                                <div class="chat-message-file-size">File attachment</div>
                                            </div>
                                            <a href="<?php echo $message['file_path']; ?>" download class="chat-message-file-download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="chat-message-time">
                                        <?php echo date('h:i A', strtotime($message['created_at'])); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="chat-footer">
                        <form action="" method="POST" class="chat-input-container" enctype="multipart/form-data">
                            <label for="message_file" class="file-upload-btn">
                                <i class="fas fa-paperclip"></i>
                                <input type="file" name="message_file" id="message_file" class="file-upload-input" accept="image/*,.pdf,.doc,.docx,.txt">
                            </label>
                            <input type="text" name="message_content" class="chat-input" placeholder="Type your message..." <?php echo $consultation['status'] === 'completed' ? 'disabled' : ''; ?>>
                            <button type="submit" name="send_message" class="chat-send-btn" <?php echo $consultation['status'] === 'completed' ? 'disabled' : ''; ?>>
                                <i class="fas fa-paper-plane"></i>
                            </button>
                        </form>
                        <div id="selected-file-info" class="mt-2 small text-muted" style="display: none;"></div>
                    </div>
                </div>
            </div>
            
            <!-- Expert Info Section -->
            <div class="col-lg-4">
                <div class="expert-info" id="consultation-details">
                    <div class="expert-info-header">
                        <?php if (!empty($consultation['expert_image'])): ?>
                            <img src="<?php echo htmlspecialchars($consultation['expert_image']); ?>" alt="<?php echo htmlspecialchars($consultation['expert_name']); ?>" class="expert-avatar-lg">
                        <?php else: ?>
                            <div class="expert-avatar-lg d-flex align-items-center justify-content-center bg-primary mx-auto">
                                <i class="fas fa-user fa-2x text-white"></i>
                            </div>
                        <?php endif; ?>
                        <h3 class="expert-name"><?php echo htmlspecialchars($consultation['expert_name']); ?></h3>
                        <p class="expert-email"><?php echo htmlspecialchars($consultation['expert_email']); ?></p>
                    </div>
                    
                    <div class="expert-info-body">
                        <div class="expert-info-section">
                            <h4 class="expert-info-title">Consultation Details</h4>
                            
                            <div class="expert-info-item">
                                <div class="expert-info-label">Status</div>
                                <div class="expert-info-value">
                                    <?php if($consultation['status'] === 'confirmed'): ?>
                                        <span class="status-badge confirmed">Confirmed</span>
                                    <?php elseif($consultation['status'] === 'canceled'): ?>
                                        <span class="status-badge canceled">Canceled</span>
                                    <?php elseif($consultation['status'] === 'completed'): ?>
                                        <span class="status-badge completed">Completed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="expert-info-item">
                                <div class="expert-info-label">Category</div>
                                <div class="expert-info-value"><?php echo htmlspecialchars($consultation['category_name'] ?? 'N/A'); ?></div>
                            </div>
                            
                            <div class="expert-info-item">
                                <div class="expert-info-label">Subcategory</div>
                                <div class="expert-info-value"><?php echo htmlspecialchars($consultation['subcategory_name'] ?? 'N/A'); ?></div>
                            </div>
                            
                            <div class="expert-info-item">
                                <div class="expert-info-label">Duration</div>
                                <div class="expert-info-value"><?php echo htmlspecialchars($consultation['duration']); ?> minutes</div>
                            </div>
                            
                            <div class="expert-info-item">
                                <div class="expert-info-label">Amount</div>
                                <div class="expert-info-value"><?php echo htmlspecialchars(number_format($consultation['payment_amount'], 2)); ?> <?php echo htmlspecialchars($currency); ?></div>
                            </div>
                            
                            <div class="expert-info-item">
                                <div class="expert-info-label">Date</div>
                                <div class="expert-info-value"><?php echo date('F j, Y', strtotime($consultation['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <div class="client-actions">
                            <?php if($consultation['status'] === 'confirmed'): ?>
                                <form action="" method="POST">
                                    <button type="submit" name="end_consultation" class="btn btn-danger w-100">
                                        <i class="fas fa-times-circle"></i> End Consultation
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<!-- Image Preview Modal -->
<div class="modal" id="imagePreviewModal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h5 class="modal-title">Image Preview</h5>
            <button class="modal-close" onclick="closeModal('imagePreviewModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body text-center p-0">
            <img id="previewImage" src="/placeholder.svg" alt="Preview" style="max-width: 100%; max-height: 70vh;">
        </div>
    </div>
</div>

<!-- Consultation Completed Modal -->
<div class="modal" id="consultationCompletedModal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Consultation Completed</h5>
            <button class="modal-close" onclick="closeModal('consultationCompletedModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="text-center mb-4">
                <i class="fas fa-check-circle" style="font-size: 4rem; color: var(--success-500);"></i>
                <h4 class="mt-3">Consultation Successfully Completed</h4>
                <p>Your consultation has been ended.</p>
                <div class="mt-3 p-3 bg-light rounded">
                    <p class="mb-1"><strong>Amount paid:</strong> <span class="text-danger"><?php echo isset($amount_to_pay) ? number_format($amount_to_pay, 2) . ' ' . $currency : ''; ?></span></p>
                </div>
            </div>
            
            <div class="rating-container">
                <h5>Rate Your Experience</h5>
                <div class="rating-stars">
                    <i class="fas fa-star rating-star" data-rating="1"></i>
                    <i class="fas fa-star rating-star" data-rating="2"></i>
                    <i class="fas fa-star rating-star" data-rating="3"></i>
                    <i class="fas fa-star rating-star" data-rating="4"></i>
                    <i class="fas fa-star rating-star" data-rating="5"></i>
                </div>
                <form action="" method="POST" id="ratingForm">
                    <input type="hidden" name="rating" id="rating_value" value="0">
                    <div class="form-group">
                        <textarea name="rating_comment" class="form-control" placeholder="Share your experience with this expert (optional)"></textarea>
                    </div>
                    <button type="submit" name="submit_rating" class="btn btn-primary w-100">Submit Rating</button>
                </form>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <button class="btn btn-warning" onclick="openModal('reportModal')">
                    <i class="fas fa-flag"></i> Report Issue
                </button>
                <a href="find-experts.php" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i> New Consultation
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Report Modal -->
<div class="modal" id="reportModal">
    <div class="modal-content">
        <div class="modal-header">
            <h5 class="modal-title">Report an Issue</h5>
            <button class="modal-close" onclick="closeModal('reportModal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form action="" method="POST" id="reportForm">
                <div class="form-group">
                    <label for="report_type" class="form-label">Issue Type</label>
                    <select name="report_type" id="report_type" class="form-control" required>
                        <option value="">Select an issue type</option>
                        <option value="inappropriate_behavior">Inappropriate Behavior</option>
                        <option value="technical_issues">Technical Issues</option>
                        <option value="payment_issues">Payment Issues</option>
                        <option value="quality_concerns">Quality Concerns</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="report_message" class="form-label">Describe the Issue</label>
                    <textarea name="report_message" id="report_message" class="form-control" rows="5" required placeholder="Please provide details about the issue you experienced..."></textarea>
                </div>
                <button type="submit" name="submit_report" class="btn btn-primary w-100">Submit Report</button>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Navbar Scroll Effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    // Timer functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize timer
        let remainingSeconds = <?php echo $remaining_seconds ?? 0; ?>;
        const timerDisplay = document.getElementById('timer-display');
        const chatMessages = document.getElementById('chat-messages');
        let timerInterval;
        
        // Scroll to bottom of chat
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Start timer if consultation is in progress
        if (remainingSeconds > 0 && '<?php echo $consultation['status'] ?? ''; ?>' !== 'completed') {
            startTimer();
        }
        
        function startTimer() {
            timerInterval = setInterval(function() {
                remainingSeconds--;
                
                if (remainingSeconds <= 0) {
                    clearInterval(timerInterval);
                    timerDisplay.innerHTML = '00:00';
                    
                    // Auto-end consultation when timer reaches zero
                    endConsultation();
                    
                    // Show completed modal
                    openModal('consultationCompletedModal');
                } else {
                    // Update timer display
                    const minutes = Math.floor(remainingSeconds / 60);
                    const seconds = remainingSeconds % 60;
                    timerDisplay.innerHTML = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                    
                    // Add warning classes when time is running low
                    if (remainingSeconds <= 60) {
                        timerDisplay.classList.add('timer-danger');
                    } else if (remainingSeconds <= 300) {
                        timerDisplay.classList.add('timer-warning');
                    }
                }
            }, 1000);
        }
        
        // End consultation function
        function endConsultation() {
            // Create a form to submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'end_consultation';
            input.value = '1';
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        // Rating submission via AJAX
        const ratingForm = document.getElementById('ratingForm');
        if (ratingForm) {
            ratingForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const rating = document.getElementById('rating_value').value;
                const comment = this.querySelector('textarea[name="rating_comment"]').value;
                
                // Create form data
                const formData = new FormData();
                formData.append('submit_rating', '1');
                formData.append('rating', rating);
                formData.append('rating_comment', comment);
                
                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    showRatingSuccess();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        }
        
        // Show rating success message
        function showRatingSuccess() {
            const ratingContainer = document.querySelector('.rating-container');
            if (ratingContainer) {
                ratingContainer.innerHTML = `
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Thank you for your rating! Your feedback has been submitted.</span>
            </div>
            <div class="mt-3">
                <a href="my-consultations.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> View All Consultations
                </a>
                
            </div>
        `;
            }
            
            // Ne pas fermer le modal ni rediriger automatiquement
            // L'utilisateur décidera quand quitter la page
        }
        
        // File upload preview
        const fileInput = document.getElementById('message_file');
        const fileInfo = document.getElementById('selected-file-info');
        
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    fileInfo.textContent = `Selected file: ${file.name} (${formatFileSize(file.size)})`;
                    fileInfo.style.display = 'block';
                } else {
                    fileInfo.style.display = 'none';
                }
            });
        }
        
        // Format file size
        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' bytes';
            else if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
            else return (bytes / 1048576).toFixed(1) + ' MB';
        }
        
        // Rating stars functionality
        const ratingStars = document.querySelectorAll('.rating-star');
        const ratingValue = document.getElementById('rating_value');
        
        ratingStars.forEach(star => {
            star.addEventListener('click', function() {
                const rating = this.getAttribute('data-rating');
                ratingValue.value = rating;
                
                // Reset all stars
                ratingStars.forEach(s => s.classList.remove('active'));
                
                // Activate clicked star and all stars before it
                ratingStars.forEach(s => {
                    if (s.getAttribute('data-rating') <= rating) {
                        s.classList.add('active');
                    }
                });
            });
        });
        
        // Auto-refresh chat messages every 500ms (like in expert-chat.php)
        setInterval(function() {
            fetch(window.location.href)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const newMessages = doc.getElementById('chat-messages');
                    
                    if (newMessages && chatMessages && chatMessages.innerHTML !== newMessages.innerHTML) {
                        chatMessages.innerHTML = newMessages.innerHTML;
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                })
                .catch(error => console.error('Error refreshing chat:', error));
        }, 500);
    });
    
    // Modal functions
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('active');
        document.body.style.overflow = 'auto';
    }
    
    // Image preview modal
    function openImageModal(imageSrc) {
        document.getElementById('previewImage').src = imageSrc;
        openModal('imagePreviewModal');
    }
    
    // Close modals when clicking outside
    window.addEventListener('click', function(event) {
        const modals = document.querySelectorAll('.modal.active');
        modals.forEach(modal => {
            if (event.target === modal) {
                closeModal(modal.id);
            }
        });
    });
</script>
<?php
// Add these endpoints before the closing PHP tag at the top of the file
if (isset($_GET['action']) && $_GET['action'] === 'get_new_messages') {
    header('Content-Type: application/json');
    
    // Reconnect to database since we closed it earlier
    require_once '../config/config.php';
    
    $chat_session_id = isset($_GET['chat_session_id']) ? intval($_GET['chat_session_id']) : 0;
    $last_message_id = isset($_GET['last_message_id']) ? intval($_GET['last_message_id']) : 0;
    
    if ($chat_session_id > 0) {
        $messages = [];
        $messages_sql = "SELECT cm.*, u.full_name, up.profile_image, cm.message_type, cm.file_path 
                       FROM chat_messages cm
                       JOIN users u ON cm.sender_id = u.id
                       LEFT JOIN user_profiles up ON u.id = u.id
                       WHERE cm.chat_session_id = ? AND cm.id > ?
                       ORDER BY cm.created_at ASC";
        $messages_stmt = $conn->prepare($messages_sql);
        $messages_stmt->bind_param("ii", $chat_session_id, $last_message_id);
        $messages_stmt->execute();
        $messages_result = $messages_stmt->get_result();
        
        if ($messages_result && $messages_result->num_rows > 0) {
            while ($row = $messages_result->fetch_assoc()) {
                $messages[] = $row;
            }
        }
        
        echo json_encode(['success' => true, 'messages' => $messages]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid chat session ID']);
    }
    
    $conn->close();
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_consultation_status') {
    header('Content-Type: application/json');
    
    // Reconnect to database since we closed it earlier
    require_once '../config/config.php';
    
    $consultation_id = isset($_GET['consultation_id']) ? intval($_GET['consultation_id']) : 0;
    
    if ($consultation_id > 0) {
        $status_sql = "SELECT c.status as consultation_status, 
                      cs.status as chat_status,
                      ct.status as timer_status,
                      TIMESTAMPDIFF(SECOND, ct.started_at, NOW()) as elapsed_seconds,
                      c.duration
                      FROM consultations c 
                      LEFT JOIN chat_sessions cs ON c.id = cs.consultation_id
                      LEFT JOIN chat_timers ct ON cs.id = ct.chat_session_id
                      WHERE c.id = ?";
        $status_stmt = $conn->prepare($status_sql);
        $status_stmt->bind_param("i", $consultation_id);
        $status_stmt->execute();
        $status_result = $status_stmt->get_result();
        
        if ($status_result && $status_result->num_rows > 0) {
            $status_data = $status_result->fetch_assoc();
            
            // Calculate remaining time
            $elapsed_seconds = $status_data['elapsed_seconds'] ?? 0;
            $total_seconds = $status_data['duration'] * 60; // Convert minutes to seconds
            $remaining_seconds = max(0, $total_seconds - $elapsed_seconds);
            
            $status_data['remaining_seconds'] = $remaining_seconds;
            $status_data['minutes'] = floor($remaining_seconds / 60);
            $status_data['seconds'] = $remaining_seconds % 60;
            
            echo json_encode(['success' => true, 'status' => $status_data]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Consultation not found']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid consultation ID']);
    }
    
    $conn->close();
    exit;
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Timer functionality
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize variables
        const chatMessages = document.getElementById('chat-messages');
        const timerDisplay = document.getElementById('timer-display');
        let lastMessageId = 0;
        let consultationId = <?php echo $consultation_id ?? 0; ?>;
        let chatSessionId = <?php echo $chat_session_id ?? 0; ?>;
        let remainingSeconds = <?php echo $remaining_seconds ?? 0; ?>;
        let consultationStatus = '<?php echo $consultation['status'] ?? ''; ?>';
        let timerInterval;
        
        // Get the last message ID if messages exist
        const messageElements = document.querySelectorAll('.chat-message');
        if (messageElements.length > 0) {
            // Try to get the last message ID from a data attribute
            // You would need to add this attribute to the message elements
            const lastMessage = messageElements[messageElements.length - 1];
            if (lastMessage.dataset.messageId) {
                lastMessageId = parseInt(lastMessage.dataset.messageId);
            }
        }
        
        // Scroll to bottom of chat
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Start timer if consultation is in progress
        if (remainingSeconds > 0 && consultationStatus !== 'completed') {
            startTimer();
        }
        
        // Set up intervals for fetching new messages and status updates
        if (chatSessionId > 0 && consultationStatus !== 'completed') {
            // Fetch new messages every 5 seconds
            // setInterval(fetchNewMessages, 5000);
            let messageRefreshInterval = setInterval(fetchNewMessages, 500);
            // Fetch immediately on page load
            fetchNewMessages();

            // Add event visibility change detection to pause/resume fetching when tab is inactive
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    // Page is hidden, clear interval to save resources
                    clearInterval(messageRefreshInterval);
                } else {
                    // Page is visible again, restart interval and fetch immediately
                    clearInterval(messageRefreshInterval);
                    messageRefreshInterval = setInterval(fetchNewMessages, 5000);
                    fetchNewMessages(); // Fetch immediately when returning to tab
                }
            });
            
            // Update consultation status every 2 seconds to quickly detect status changes
            setInterval(updateConsultationStatus, 2000);
            // Check status immediately on page load
            updateConsultationStatus();
        }
        
        function startTimer() {
            timerInterval = setInterval(function() {
                remainingSeconds--;
                
                if (remainingSeconds <= 0) {
                    clearInterval(timerInterval);
                    timerDisplay.innerHTML = '00:00';
                    
                    // Auto-end consultation when timer reaches zero
                    endConsultation();
                    
                    // Show completed modal
                    openModal('consultationCompletedModal');
                } else {
                    // Update timer display
                    const minutes = Math.floor(remainingSeconds / 60);
                    const seconds = remainingSeconds % 60;
                    timerDisplay.innerHTML = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                    
                    // Add warning classes when time is running low
                    if (remainingSeconds <= 60) {
                        timerDisplay.classList.add('timer-danger');
                    } else if (remainingSeconds <= 300) {
                        timerDisplay.classList.add('timer-warning');
                    }
                }
            }, 1000);
        }
        
        // Fetch new messages from the server
        function fetchNewMessages() {
            if (!chatSessionId) return;
            
            // Show loading indicator
            const loadingIndicator = document.getElementById('loading-indicator');
            if (loadingIndicator) loadingIndicator.style.display = 'block';
            
            fetch(`consultation-chat.php?action=get_new_messages&chat_session_id=${chatSessionId}&last_message_id=${lastMessageId}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    // Hide loading indicator
                    if (loadingIndicator) loadingIndicator.style.display = 'none';
                    
                    if (data.success && data.messages && data.messages.length > 0) {
                        console.log(`Received ${data.messages.length} new messages`);
                        
                        // Update the last message ID
                        lastMessageId = data.messages[data.messages.length - 1].id;
                        
                        // Add new messages to the chat
                        data.messages.forEach(message => {
                            appendMessage(message);
                        });
                        
                        // Play notification sound for new messages
                        playNotificationSound();
                        
                        // Scroll to bottom of chat
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                })
                .catch(error => {
                    console.error('Error fetching new messages:', error);
                    // Hide loading indicator on error
                    if (loadingIndicator) loadingIndicator.style.display = 'none';
                    
                    // Retry after a short delay if there was an error
                    setTimeout(fetchNewMessages, 2000);
                });
        }

        // Play notification sound when new messages arrive
        function playNotificationSound() {
            // Create an audio element
            const audio = new Audio();
            audio.src = '../assets/sounds/notification.mp3'; // Make sure this path exists
            audio.volume = 0.5;
            audio.play().catch(e => console.log('Audio play failed:', e));
        }
        
        // Update consultation status
        function updateConsultationStatus() {
            if (!consultationId) return;
            
            fetch(`consultation-chat.php?action=get_consultation_status&consultation_id=${consultationId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.status) {
                        // Update consultation status
                        const newStatus = data.status.consultation_status;
                        if (newStatus !== consultationStatus) {
                            consultationStatus = newStatus;
                            
                            // If consultation is completed or canceled, handle accordingly
                             if (newStatus === 'canceled' || newStatus === 'completed') {
                                // Stop the timer
                                if (timerInterval) {
                                    clearInterval(timerInterval);
                                    timerDisplay.innerHTML = '00:00';
                                }
                                
                                // Show canceled alert if not already shown
                                if (!document.querySelector('.alert-danger')) {
                                    const alertDiv = document.createElement('div');
                                    alertDiv.className = 'alert alert-danger';
                                    alertDiv.innerHTML = `
                                        <i class="fas fa-exclamation-circle alert-icon"></i>
                                        <div>This Consultation has been canceled by expert. Redirecting to your consultations...</div>
                                    `;
                                    document.querySelector('.main-container').prepend(alertDiv);
                                }
                                
                                // Disable chat input
                                const chatInput = document.querySelector('.chat-input');
                                const sendButton = document.querySelector('.chat-send-btn');
                                const fileUploadBtn = document.querySelector('.file-upload-btn');
                                
                                if (chatInput) chatInput.disabled = true;
                                if (sendButton) sendButton.disabled = true;
                                if (fileUploadBtn) fileUploadBtn.style.pointerEvents = 'none';
                                
                                // Redirect after a delay
                                setTimeout(() => {
                                    window.location.href = "my-consultations.php";
                                }, 3000);
                            }
                        }
                        
                        // Update timer if needed
                        if (data.status.remaining_seconds !== remainingSeconds && consultationStatus !== 'canceled') {
                            remainingSeconds = data.status.remaining_seconds;
                            
                            // Update timer display
                            const minutes = data.status.minutes;
                            const seconds = data.status.seconds;
                            timerDisplay.innerHTML = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
                            
                            // Add warning classes when time is running low
                            if (remainingSeconds <= 60) {
                                timerDisplay.classList.add('timer-danger');
                            } else if (remainingSeconds <= 300) {
                                timerDisplay.classList.add('timer-warning');
                            }
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating consultation status:', error);
                });
        }
        
        // Append a new message to the chat
        function appendMessage(message) {
            // Check if message already exists to prevent duplicates
            if (document.querySelector(`.chat-message[data-message-id="${message.id}"]`)) {
                return; // Skip if message already exists
            }
            
            const messageElement = document.createElement('div');
            messageElement.className = `chat-message ${message.sender_id == <?php echo $user_id; ?> ? 'chat-message-client' : 'chat-message-expert'}`;
            messageElement.dataset.messageId = message.id;
            
            // Add animation class for new messages
            messageElement.classList.add('new-message-animation');
            
            let messageContent = `
                <div class="chat-message-sender">
                    ${escapeHtml(message.full_name)}
                </div>
                ${escapeHtml(message.message)}
                <div class="chat-message-time">
                    ${formatTime(message.created_at)}
                </div>
            `;
            
            // Add attachments if present
            if (message.message_type === 'image' && message.file_path) {
                messageContent += `
                    <div class="chat-message-attachment">
                        <img src="${escapeHtml(message.file_path)}" alt="Attached image" class="chat-message-image" onclick="openImageModal('${escapeHtml(message.file_path)}')">
                    </div>
                `;
            } else if (message.message_type === 'file' && message.file_path) {
                messageContent += `
                    <div class="chat-message-file">
                        <i class="fas fa-file-alt chat-message-file-icon"></i>
                        <div class="chat-message-file-info">
                            <div class="chat-message-file-name">${escapeHtml(getFileName(message.file_path))}</div>
                            <div class="chat-message-file-size">File attachment</div>
                        </div>
                        <a href="${escapeHtml(message.file_path)}" download class="chat-message-file-download">
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                `;
            }
            
            messageElement.innerHTML = messageContent;
            chatMessages.appendChild(messageElement);
            
            // Remove animation class after animation completes
            setTimeout(() => {
                messageElement.classList.remove('new-message-animation');
            }, 1000);
        }

        // Helper function to escape HTML to prevent XSS
        function escapeHtml(unsafe) {
            if (typeof unsafe !== 'string') return unsafe;
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
        
        // Format time for display
        function formatTime(timestamp) {
            const date = new Date(timestamp);
            const hours = date.getHours();
            const minutes = date.getMinutes();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const formattedHours = hours % 12 || 12;
            const formattedMinutes = String(minutes).padStart(2, '0');
            return `${formattedHours}:${formattedMinutes} ${ampm}`;
        }
        
        // Get file name from path
        function getFileName(path) {
            return path.split('/').pop();
        }
        
        // End consultation function
        function endConsultation() {
            // Create a form to submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'end_consultation';
            input.value = '1';
            
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
        }

        // Rating submission via AJAX
        const ratingForm = document.getElementById('ratingForm');
        if (ratingForm) {
            ratingForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const rating = document.getElementById('rating_value').value;
                const comment = this.querySelector('textarea[name="rating_comment"]').value;
                
                // Create form data
                const formData = new FormData();
                formData.append('submit_rating', '1');
                formData.append('rating', rating);
                formData.append('rating_comment', comment);
                
                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    showRatingSuccess();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        }
        
        // Report submission via AJAX
        const reportForm = document.getElementById('reportForm');
        if (reportForm) {
            reportForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const reportType = this.querySelector('select[name="report_type"]').value;
                const reportMessage = this.querySelector('textarea[name="report_message"]').value;
                
                // Create form data
                const formData = new FormData();
                formData.append('submit_report', '1');
                formData.append('report_type', reportType);
                formData.append('report_message', reportMessage);
                
                // Send AJAX request
                fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Show success message
                    const reportModalBody = document.querySelector('#reportModal .modal-body');

                    reportModalBody.innerHTML = `
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span>Your report has been submitted successfully. Our team will review it shortly.</span>
                        </div>
                    `;
                    
                    // Close the modal after a delay
                    setTimeout(() => {
                        closeModal('reportModal');
                    }, 3000);
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        }
        
        // Function to refresh consultation details
        function refreshConsultationDetails() {
            const consultationId = <?php echo $consultation_id ?? 0; ?>;
            if (!consultationId) return;
            
            fetch(`consultation-chat.php?id=${consultationId}`)
            .then(response => response.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newDetails = doc.getElementById('consultation-details');
                const currentDetails = document.getElementById('consultation-details');
                
                if (newDetails && currentDetails) {
                    // Only update if there are changes
                    if (newDetails.innerHTML !== currentDetails.innerHTML) {
                        currentDetails.innerHTML = newDetails.innerHTML;
                        console.log('Consultation details updated');
                        
                        const statusBadge = newDetails.querySelector('.status-badge');
if (statusBadge) {
    if (statusBadge.classList.contains('canceled')) {
        console.log('Consultation has been canceled, handling cancellation...');

        // Stop the timer
        if (timerInterval) {
            clearInterval(timerInterval);
            document.getElementById('timer-display').innerHTML = '00:00';
        }

        // Show alert if not already shown
        if (!document.querySelector('.alert-danger')) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-circle alert-icon"></i>
                <div>This Consultation has been canceled by expert. Redirecting to your consultations...</div>
            `;
            document.querySelector('.main-container').prepend(alertDiv);
        }

        // Disable chat input
        const chatInput = document.querySelector('.chat-input');
        const sendButton = document.querySelector('.chat-send-btn');
        const fileUploadBtn = document.querySelector('.file-upload-btn');

        if (chatInput) chatInput.disabled = true;
        if (sendButton) sendButton.disabled = true;
        if (fileUploadBtn) fileUploadBtn.style.pointerEvents = 'none';

        // Redirect after a delay
        setTimeout(() => {
            window.location.href = "my-consultations.php";
        }, 7000);
    }

    // === New block for completed status ===
   if (statusBadge.classList.contains('completed')) {
    console.log('Consultation has been completed, handling completion...');

    // Stop the timer
    if (timerInterval) {
        clearInterval(timerInterval);
        document.getElementById('timer-display').innerHTML = '00:00';
    }

    // Show alert if not already shown
    if (!document.querySelector('.alert-success')) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success';
        alertDiv.innerHTML = `
            <i class="fas fa-check-circle alert-icon"></i>
            <div>Consultation completed.</div>
        `;
        document.querySelector('.main-container').prepend(alertDiv);
    }

    // Disable chat input
    const chatInput = document.querySelector('.chat-input');
    const sendButton = document.querySelector('.chat-send-btn');
    const fileUploadBtn = document.querySelector('.file-upload-btn');

    if (chatInput) chatInput.disabled = true;
    if (sendButton) sendButton.disabled = true;
    if (fileUploadBtn) fileUploadBtn.style.pointerEvents = 'none';

    // Function to handle the redirection
    const handleRedirection = () => {
        const activeModals = document.querySelectorAll('.modal.active');
        if (activeModals.length === 0) {
            window.location.href = "my-consultations.php";
        } else {
            // If modal is still active, check again after 1 second
            setTimeout(handleRedirection, 1000);
        }
    };

    // Start checking for modal status after 7 seconds
    setTimeout(handleRedirection, 7000);
}
                        }
                    }
                }
            })
            .catch(error => {
                console.error('Error refreshing consultation details:', error);
            });
        }
        
        // Set interval to refresh consultation details every 2 seconds
        setInterval(refreshConsultationDetails, 2000);
        
        // Call immediately on page load
        document.addEventListener('DOMContentLoaded', function() {
            refreshConsultationDetails();
        });
    });
</script>
</body>
</html>
