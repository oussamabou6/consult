<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in, redirect to login page
    header("Location: ../pages/login.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];
$success_message = "";
$error_message = "";
$client_id = isset($_GET['client_id']) ? intval($_GET['client_id']) : 0;
$consultation_id = isset($_GET['consultation_id']) ? intval($_GET['consultation_id']) : 0;

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
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

// Validate consultation and client
if ($consultation_id > 0 && $client_id > 0) {
    // Check if consultation exists and belongs to this expert
    $consult_sql = "SELECT c.*, u.full_name as client_name, u.email as client_email, u.status as client_status, 
                    up.profile_image as client_image, up.bio as client_bio,

                    cs.id as chat_session_id, ct.id as timer_id, ct.status as timer_status,
                    TIMESTAMPDIFF(SECOND, ct.started_at, NOW()) as elapsed_seconds
                    FROM consultations c 
                    JOIN users u ON c.client_id = u.id 
                    LEFT JOIN user_profiles up ON u.id = up.user_id 
                    LEFT JOIN chat_sessions cs ON c.id = cs.consultation_id
                    LEFT JOIN chat_timers ct ON cs.id = ct.chat_session_id
                    WHERE c.id = ? AND c.expert_id = ? AND c.client_id = ?";
    $consult_stmt = $conn->prepare($consult_sql);
    $consult_stmt->bind_param("iii", $consultation_id, $user_id, $client_id);
    $consult_stmt->execute();
    $consult_result = $consult_stmt->get_result();
    
    if ($consult_result->num_rows == 0) {
        // Consultation not found or doesn't belong to this expert
        $error_message = "Consultation not found or you don't have permission to access it.";
        // Redirect to consultations page after 3 seconds
        header("Refresh: 3; URL=expert-consultations.php");
    } else {
        $consultation = $consult_result->fetch_assoc();
        $chat_session_id = $consultation['chat_session_id'];
        $timer_id = $consultation['timer_id'];
        $timer_status = $consultation['timer_status'];
        
        // Check if chat session exists, create one if not
        if (!$chat_session_id) {
            // Create a new chat session
            $create_session_sql = "INSERT INTO chat_sessions (consultation_id, expert_id, client_id, started_at, status) 
                                  VALUES (?, ?, ?, NOW(), 'active')";
            $create_session_stmt = $conn->prepare($create_session_sql);
            $create_session_stmt->bind_param("iii", $consultation_id, $user_id, $client_id);
            
            if ($create_session_stmt->execute()) {
                $chat_session_id = $conn->insert_id;
                
                // Create a timer for the session
                $create_timer_sql = "INSERT INTO chat_timers (chat_session_id, started_at, status) 
                                    VALUES (?, NOW(), 'running')";
                $create_timer_stmt = $conn->prepare($create_timer_sql);
                $create_timer_stmt->bind_param("i", $chat_session_id);
                
                if ($create_timer_stmt->execute()) {
                    $timer_id = $conn->insert_id;
                    $timer_status = 'running';
                }
                
                
            }
        }
        
        // Calculate remaining time
        $elapsed_seconds = $consultation['elapsed_seconds'] ?? 0;
        $total_seconds = $consultation['duration'] * 60; // Convert minutes to seconds
        $remaining_seconds = max(0, $total_seconds - $elapsed_seconds);
        
        $expert_sql = "SELECT * FROM users WHERE id = $user_id";
        $expert_stmt = $conn->prepare($expert_sql);
        $expert_stmt->execute();
        $expert_result = $expert_stmt->get_result();
        if ($expert_result && $expert_result->num_rows > 0) {
            $expert = $expert_result->fetch_assoc();
            $expert_full_name = $expert['full_name'];
        }


        // Get chat messages
        $messages = [];
        if ($chat_session_id) {
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
        }
        
        // Handle message sending
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['send_message'])) {
            $message_content = sanitize_input($_POST['message_content']);
            $sender_type = 'expert';
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
                $insert_message_stmt->bind_param("iiisssis", $user_id, $client_id, $consultation_id, $message_content, $file_path, $sender_type, $chat_session_id, $message_type);
                
                if ($insert_message_stmt->execute()) {
                
                    // Redirect to avoid form resubmission
                    header("Location: expert-chat.php?client_id=$client_id&consultation_id=$consultation_id");
                    exit();
                } else {
                    $error_message = "Failed to send message. Please try again.";
                }
            }
        }
        
        // Handle timer actions
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            // End consultation
            if (isset($_POST['end_consultation'])) {
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
                $update_consult_sql = "UPDATE consultations SET status = 'canceled', updated_at = NOW() WHERE id = ? AND status = 'confirmed'";
                $update_consult_stmt = $conn->prepare($update_consult_sql);
                $update_consult_stmt->bind_param("i", $consultation_id);
                
                if ($update_consult_stmt->execute()) {

                    $update_payments_sql = "UPDATE payments SET status = 'canceled', updated_at = NOW() WHERE consultation_id = ? AND status = 'processing'";
                    $update_payments_stmt = $conn->prepare($update_payments_sql);
                    $update_payments_stmt->bind_param("i", $consultation_id);
                    $update_payments_stmt->execute();

                    // Return client's balance
                    $get_payment_sql = "SELECT amount FROM payments WHERE consultation_id = ? AND status = 'canceled'";
                    $get_payment_stmt = $conn->prepare($get_payment_sql);
                    $get_payment_stmt->bind_param("i", $consultation_id);
                    $get_payment_stmt->execute();
                    $payment_result = $get_payment_stmt->get_result();
                    
                    if ($payment_result && $payment_result->num_rows > 0) {
                        $payment_amount = $payment_result->fetch_assoc()['amount'];
                        
                        // Update client balance
                        $update_balance_sql = "UPDATE users SET balance = balance + ? WHERE id = ? AND role = 'client'";
                        $update_balance_stmt = $conn->prepare($update_balance_sql);
                        $update_balance_stmt->bind_param("di", $payment_amount, $client_id);
                        $update_balance_stmt->execute();
                    }

                    // Add notification for client
                    $notification_sql = "INSERT INTO client_notifications (user_id, message, is_read, created_at) 
                                        VALUES (?, ?, 0, NOW())";
                    $notification_msg = "Your consultation has been canceled by the expert : $expert_full_name.";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bind_param("is", $client_id, $notification_msg);
                    $notification_stmt->execute();
                    
                    $success_message = "Consultation has been canceled and client has been refunded.";
                   
                    // Redirect to consultations page after 5 seconds
                    header("Refresh: 5; URL=expert-consultations.php");
                } else {
                    $error_message = "Failed to complete consultation. Please try again.";
                }
            }
            
            // Block client and report
            if (isset($_POST['block_client'])) {
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
                $update_consult_sql = "UPDATE consultations SET status = 'canceled', updated_at = NOW() WHERE id = ? AND status = 'confirmed'";
                $update_consult_stmt = $conn->prepare($update_consult_sql);
                $update_consult_stmt->bind_param("i", $consultation_id);
                
                if ($update_consult_stmt->execute()) {

                    $update_payments_sql = "UPDATE payments SET status = 'canceled', updated_at = NOW() WHERE consultation_id = ? AND status = 'processing'";
                    $update_payments_stmt = $conn->prepare($update_payments_sql);
                    $update_payments_stmt->bind_param("i", $consultation_id);
                    $update_payments_stmt->execute();

                    // Return client's balance
                    $get_payment_sql = "SELECT amount FROM payments WHERE consultation_id = ? AND status = 'canceled'";
                    $get_payment_stmt = $conn->prepare($get_payment_sql);
                    $get_payment_stmt->bind_param("i", $consultation_id);
                    $get_payment_stmt->execute();
                    $payment_result = $get_payment_stmt->get_result();
                    
                    if ($payment_result && $payment_result->num_rows > 0) {
                        $payment_amount = $payment_result->fetch_assoc()['amount'];
                        
                        // Update client balance
                        $update_balance_sql = "UPDATE users SET balance = balance + ? WHERE id = ? AND role = 'client'";
                        $update_balance_stmt = $conn->prepare($update_balance_sql);
                        $update_balance_stmt->bind_param("di", $payment_amount, $client_id);
                        $update_balance_stmt->execute();
                    }
                }
                // Add report to the reports table
                $report_type = sanitize_input($_POST['report_type']);
                $report_message = sanitize_input($_POST['report_message']);
                
                $report_sql = "INSERT INTO reports (consultation_id, reporter_id, reported_id, report_type, message, status, created_at) 
                              VALUES (?, ?, ?, ?, ?, 'pending', NOW())";
                $report_stmt = $conn->prepare($report_sql);
                $report_stmt->bind_param("iiiss", $consultation_id, $user_id, $client_id, $report_type, $report_message);
                
                if ($report_stmt->execute()) {
                    // Add notification for admin
                    $admin_notification_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message, related_id, is_read, created_at) 
                                              VALUES (?, ?, 'report', ?, ?, 0, NOW())";
                    $admin_notification_msg = "New report from expert #$user_id regarding client #$client_id";
                    $admin_notification_stmt = $conn->prepare($admin_notification_sql);
                    $admin_notification_stmt->bind_param("iisi", $user_id, $profile_id, $admin_notification_msg, $consultation_id);
                    $admin_notification_stmt->execute();
                    
                    // Add notification for client
                    $notification_sql = "INSERT INTO client_notifications (user_id, message, is_read, created_at) 
                                        VALUES (?, ?, 0, NOW())";
                    $notification_msg = "Your consultation has been ended by the expert : $expert_full_name.";
                    $notification_stmt = $conn->prepare($notification_sql);
                    $notification_stmt->bind_param("is", $client_id, $notification_msg);
                    $notification_stmt->execute();
                    
                    $success_message = "Client reported and consultation has been ended.";
                    // Redirect to consultations page after 5 seconds
                    header("Refresh: 5; URL=expert-consultations.php");
                } else {
                    $error_message = "Failed to report client. Please try again.";
                }
            }
        }
    }
} else {
    $error_message = "Invalid consultation or client ID.";
    // Redirect to consultations page after 3 seconds
    header("Refresh: 3; URL=expert-consultations.php");
}

// Get currency from settings
$currency = $settings['currency'] ?? 'DA';
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
// Get notification counts
$message_pending = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND receiver_id = ? AND chat_session_id = 0 AND sender_type = 'expert'");
$message_pending->bind_param("i", $user_id);
$message_pending->execute();
$message_pending_result = $message_pending->get_result();
$message_pending_count = $message_pending_result->fetch_assoc()['count'];
$message_pending->close();

$forums_pending = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND receiver_id = ? AND chat_session_id = 0 AND sender_type = 'expert'");
$forums_pending->bind_param("i", $user_id);
$forums_pending->execute();
$forums_pending_result = $forums_pending->get_result();
$forums_pending_count = $forums_pending_result->fetch_assoc()['count'];
$forums_pending->close();

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

// Handle AJAX request for notifications
if (isset($_GET['fetch_notifications'])) {
    $response = [
        'pending_consultations' => $pending_consultations_count,
        'pending_withdrawals' => $pending_withdrawals_count,
        'admin_messages' => $admin_messages_count,
        'reviews' => $reviews_not_read_count,
        'total' => $pending_consultations_count + $pending_withdrawals_count+ $admin_messages_count + $reviews_not_read_count
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat with Client - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
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
            display: none;
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background-color:  #ef4444 ;
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
        
        .timer-icon {
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Chat Body */
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
        
        .chat-message-expert {
            align-self: flex-end;
            background-color: var(--primary-500);
            color: white;
            border-bottom-right-radius: 0.25rem;
        }
        
        .chat-message-client {
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
        
        /* Client Info Card */
        .client-info {
            background-color: white;
            border-radius: 1rem;
            box-shadow: var(--shadow-md);
            height: 100%;
            border: 1px solid var(--neutral-200);
            overflow: hidden;
        }
        
        .client-info-header {
            background: linear-gradient(to right, var(--primary-600), var(--primary-500));
            color: white;
            padding: 1.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .client-info-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 70%);
            transform: rotate(30deg);
        }
        
        .client-avatar-lg {
            width: 6rem;
            height: 6rem;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--shadow-lg);
            margin: 0 auto 1rem;
            display: block;
        }
        
        .client-name {
            font-weight: 700;
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 0.25rem;
        }
        
        .client-email {
            text-align: center;
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .client-info-body {
            padding: 1.5rem;
        }
        
        .client-info-section {
            margin-bottom: 1.5rem;
        }
        
        .client-info-title {
            font-weight: 600;
            color: var(--neutral-700);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--neutral-200);
            font-size: 1.1rem;
        }
        
        .client-info-item {
            margin-bottom: 1rem;
        }
        
        .client-info-label {
            font-weight: 500;
            color: var(--neutral-600);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .client-info-value {
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
        .status-badge.canceled {
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
        
        .chat-message-client .chat-message-file {
            background-color: rgba(0, 0, 0, 0.05);
            border: 1px solid var(--neutral-200);
        }
        
        .chat-message-file-icon {
            font-size: 1.5rem;
            margin-right: 0.75rem;
            color: var(--primary-300);
        }
        
        .chat-message-client .chat-message-file-icon {
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
        
        .chat-message-client .chat-message-file-name {
            color: var(--neutral-800);
        }
        
        .chat-message-file-size {
            font-size: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .chat-message-client .chat-message-file-size {
            color: var(--neutral-500);
        }
        
        .chat-message-file-download {
            color: white;
            font-size: 1.25rem;
            transition: all 0.2s ease;
        }
        
        .chat-message-client .chat-message-file-download {
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
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .chat-container {
                height: calc(100vh - 220px);
            }
            
            .client-info {
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
            
            .client-avatar-lg {
                width: 5rem;
                height: 5rem;
            }
            
            .client-name {
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
    </style>
</head>
<body>
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
        
        <?php if (isset($consultation)): ?>
            <div class="row">
                <!-- Chat Section -->
                <div class="col-lg-8 mb-4">
                    <div class="chat-container">
                        <div class="chat-header">
                            <div class="chat-header-info">
                                <?php if (!empty($consultation['client_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="<?php echo htmlspecialchars($consultation['client_name']); ?>" class="chat-header-avatar">
                                <?php else: ?>
                                    <div class="chat-header-avatar d-flex align-items-center justify-content-center bg-primary">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <h5 class="chat-header-name"><?php echo htmlspecialchars($consultation['client_name']); ?></h5>
                                    <div class="chat-header-status">
                                        <span class="badge <?php echo $consultation['client_status'] === 'Online' ? 'bg-success' : 'bg-secondary'; ?>">
                                            <?php echo $consultation['client_status']; ?>
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
                                    <p>Send a message to begin the conversation with your client.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($messages as $message): ?>
                                    <div class="chat-message <?php echo $message['sender_id'] == $user_id ? 'chat-message-expert' : 'chat-message-client'; ?>">
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
                
                <!-- Client Info Section -->
                <div class="col-lg-4">
                    <div class="client-info" id="consultation-details">
                        <div class="client-info-header">
                            <?php if (!empty($consultation['client_image'])): ?>
                                <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="<?php echo htmlspecialchars($consultation['client_name']); ?>" class="client-avatar-lg">
                            <?php else: ?>
                                <div class="client-avatar-lg d-flex align-items-center justify-content-center bg-primary mx-auto">
                                    <i class="fas fa-user fa-2x text-white"></i>
                                </div>
                            <?php endif; ?>
                            <h3 class="client-name"><?php echo htmlspecialchars($consultation['client_name']); ?></h3>
                            <p class="client-email"><?php echo htmlspecialchars($consultation['client_email']); ?></p>
                        </div>
                        
                        <div class="client-info-body">
                            <div class="client-info-section">
                                <h4 class="client-info-title">Consultation Details</h4>
                                
                                <div class="client-info-item">
                                    <div class="client-info-label">Status</div>
                                    <div class="client-info-value">
                                        <?php if($consultation['status'] === 'confirmed'): ?>
                                            <span class="status-badge confirmed">Confirmed</span>
                                        <?php elseif($consultation['status'] === 'canceled'): ?>
                                            <span class="status-badge canceled">Canceled</span>
                                        <?php elseif($consultation['status'] === 'completed'): ?>
                                            <span class="status-badge completed">Completed</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="client-info-item">
                                    <div class="client-info-label">Date & Time</div>
                                    <div class="client-info-value">
                                        <i class="far fa-calendar-alt me-2 text-primary-500"></i>
                                        <?php echo date('F d, Y', strtotime($consultation['consultation_date'])); ?> at 
                                        <?php echo date('h:i A', strtotime($consultation['consultation_time'])); ?>
                                    </div>
                                </div>
                                
                                <div class="client-info-item">
                                    <div class="client-info-label">Duration</div>
                                    <div class="client-info-value">
                                        <i class="far fa-clock me-2 text-primary-500"></i>
                                        <?php echo $consultation['duration']; ?> minutes
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($consultation['notes'])): ?>
                            <div class="client-info-section">
                                <h4 class="client-info-title">Client's Notes</h4>
                                <div class="client-info-value p-3 bg-neutral-50 rounded-3 border">
                                    <?php echo htmlspecialchars($consultation['notes']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($consultation['client_bio'])): ?>
                            <div class="client-info-section">
                                <h4 class="client-info-title">About Client</h4>
                                <div class="client-info-value">
                                    <?php echo htmlspecialchars($consultation['client_bio']); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($consultation['status'] !== 'completed'): ?>
                                <div class="client-actions">
                                    <form action="" method="POST" id="endConsultationForm">
                                        <input type="hidden" name="end_consultation" value="1">
                                        <button type="button" id="endConsultationBtn" class="btn btn-danger w-100 mb-3">
                                            <i class="fas fa-times-circle me-2"></i> End Consultation
                                        </button>
                                    </form>
                                    
                                    <button type="button" class="btn btn-warning w-100" onclick="openModal('blockModal')">
                                    <i class="fas fa-ban me-2"></i> Signal User
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i> This consultation has ended.
                                </div>
                                
                                <a href="expert-consultations.php" class="btn btn-primary w-100">
                                    <i class="fas fa-arrow-left me-2"></i> Back to Consultations
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Block Modal -->
    <div class="modal" id="blockModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Signal User</h3>
                <button type="button" class="modal-close" onclick="closeModal('blockModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-user-slash text-warning fa-3x mb-3"></i>
                    <h4>Report And Signal User</h4>
                    <p class="text-muted">Please provide a reason for blocking this client. This report will be sent to the administrator.</p>
                </div>
                
                <form action="" method="post" id="blockForm">
                    <input type="hidden" name="block_client" value="1">
                    
                    <div class="form-group mb-3">
                        <label for="report_type" class="form-label">Subject:</label>
                        <input type="text" name="report_type" id="report_type" class="form-control" required>
                    </div>
                    
                    <div class="form-group mb-3">
                        <label for="report_message" class="form-label">Message:</label>
                        <textarea name="report_message" id="report_message" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('blockModal')">Cancel</button>
                        <button type="submit" class="btn btn-danger">Submit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- End Consultation Confirmation Modal -->
    <div class="modal" id="endConsultationConfirmModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">End Consultation</h3>
                <button type="button" class="modal-close" onclick="closeModal('endConsultationConfirmModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-exclamation-triangle text-warning fa-3x mb-3"></i>
                    <h4>Are you sure you want to end this consultation?</h4>
                    <p class="text-muted">This action cannot be undone and will close the chat session.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('endConsultationConfirmModal')">Cancel</button>
                    <button type="button" id="confirmEndConsultation" class="btn btn-danger">End Consultation</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Consultation Completed Modal -->
    <div class="modal" id="consultationCompletedModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Consultation Completed</h3>
                <button type="button" class="modal-close" onclick="closeModal('consultationCompletedModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <i class="fas fa-check-circle text-success fa-4x mb-3"></i>
                    <h4>Consultation Successfully Completed</h4>
                    <p class="text-muted">The consultation time has ended and payment has been processed.</p>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i> The client has been notified that the consultation is complete.
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="expert-consultations.php" class="btn btn-outline-primary">
                        <i class="fas fa-list me-2"></i> View All Consultations
                    </a>
                    <a href="expert-earnings.php" class="btn btn-primary">
                        <i class="fas fa-chart-line me-2"></i> View Earnings
                    </a>
                </div>
            </div>
        </div>
    </div>




    <!-- Image Preview Modal -->
    <div class="modal" id="imagePreviewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Image Preview</h3>
                <button type="button" class="modal-close" onclick="closeModal('imagePreviewModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" src="/placeholder.svg" alt="Preview" style="max-width: 100%; max-height: 70vh;">
            </div>
            <div class="modal-footer">
                <a id="downloadImageLink" href="" download class="btn btn-primary">
                    <i class="fas fa-download me-2"></i> Download
                </a>
                <button type="button" class="btn btn-secondary" onclick="closeModal('imagePreviewModal')">Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Fetch notifications function
    function fetchNotifications() {
        fetch('expert-chat.php?fetch_notifications=true')
            .then(response => response.json())
            .then(data => {
                // Update notification badges
                updateNotificationBadge('.pending-consultations-badge', data.pending_consultations);
                updateNotificationBadge('.pending-withdrawals-badge', data.pending_withdrawals);
                updateNotificationBadge('.admin-messages-badge', data.admin_messages);
                updateNotificationBadge('.community-messages-badge', data.community_messages);
                updateNotificationBadge('.forums_messages-badge', data.forums_messages);
                updateNotificationBadge('.reviews-badge', data.reviews);
                updateNotificationBadge('.total-notifications-badge', data.total);
            })
            .catch(error => console.error('Error fetching notifications:', error));
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
    
            // Timer functionality
            const timerDisplay = document.getElementById('timer-display');
            const timerStatus = '<?php echo $timer_status ?? ""; ?>';
            let remainingSeconds = <?php echo $remaining_seconds ?? 0; ?>;
            let timerInterval;
            
            // Function to format time as MM:SS
            function formatTime(seconds) {
                const minutes = Math.floor(seconds / 60);
                const secs = seconds % 60;
                return `${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
            }
            
            // Function to update timer display
            function updateTimer() {
                if (remainingSeconds <= 0) {
                    clearInterval(timerInterval);
                    timerDisplay.textContent = '00:00';
                    
                    // Show consultation completed alert
                    const consultationStatus = '<?php echo $consultation['status'] ?? ""; ?>';
                    if (consultationStatus !== 'completed') {
                        // Submit form to end consultation and show modal
                        endConsultation();
                    }
                } else {
                    timerDisplay.textContent = formatTime(remainingSeconds);
                    
                    // Add visual warning when time is running low
                    if (remainingSeconds <= 60) { // Last minute
                        timerDisplay.classList.add('timer-danger');
                        timerDisplay.classList.remove('timer-warning');
                    } else if (remainingSeconds <= 180) { // Last 3 minutes
                        timerDisplay.classList.add('timer-warning');
                        timerDisplay.classList.remove('timer-danger');
                    } else {
                        timerDisplay.classList.remove('timer-warning', 'timer-danger');
                    }
                    
                    remainingSeconds--;
                }
            }
            
            // Function to end consultation via AJAX
            function endConsultation() {
                const formData = new FormData();
                formData.append('end_consultation', '1');
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(() => {
                    // Disable chat input
                    const chatInput = document.querySelector('.chat-input');
                    const chatSendBtn = document.querySelector('.chat-send-btn');
                    const fileUploadBtn = document.querySelector('.file-upload-btn');
                    if (chatInput) chatInput.disabled = true;
                    if (chatSendBtn) chatSendBtn.disabled = true;
                    if (fileUploadBtn) fileUploadBtn.style.pointerEvents = 'none';
                    
                    // Show completion modal
                    openModal('consultationCompletedModal');
                    
                    // Redirect after modal is closed
                    setTimeout(() => {
                        window.location.href = 'expert-consultations.php';
                    }, 5000);
                })
                .catch(error => {
                    console.error('Error ending consultation:', error);
                });
            }
            
            // Start timer if it's running
            if (timerStatus === 'running') {
                updateTimer(); // Update immediately
                timerInterval = setInterval(updateTimer, 1000); // Then update every second
            } else {
                timerDisplay.textContent = formatTime(remainingSeconds);
            }
            
            // Scroll chat to bottom
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
            
            // Auto-refresh chat messages every 5 seconds
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
            
            // Modal functions
            window.openModal = function(modalId) {
                document.getElementById(modalId).classList.add('active');
            }
            
            window.closeModal = function(modalId) {
                document.getElementById(modalId).classList.remove('active');
            }
            
            // End Consultation button handler
            const endConsultationBtn = document.getElementById('endConsultationBtn');
            if (endConsultationBtn) {
                endConsultationBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Show confirmation dialog
                    openModal('endConsultationConfirmModal');
                });
            }
            
            // Block form handler
            const blockForm = document.getElementById('blockForm');
            if (blockForm) {
                blockForm.addEventListener('submit', function(e) {
                    // Stop the timer immediately
                    clearInterval(timerInterval);
                    
                    // Freeze the timer display at its current value
                    const currentTime = timerDisplay.textContent;
                    timerDisplay.textContent = currentTime;
                    
                    // Add a class to visually indicate the timer is stopped
                    timerDisplay.classList.add('text-danger');
                    
                    // Disable the button to prevent multiple clicks
                    const submitBtn = this.querySelector('button[type="submit"]');
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing...';
                });
            }
            
            // Confirm end consultation handler
            const confirmEndConsultationBtn = document.getElementById('confirmEndConsultation');
            if (confirmEndConsultationBtn) {
                confirmEndConsultationBtn.addEventListener('click', function() {
                    // Stop the timer immediately
                    clearInterval(timerInterval);
                    
                    // Freeze the timer display at its current value
                    const currentTime = timerDisplay.textContent;
                    timerDisplay.textContent = currentTime;
                    
                    // Add a class to visually indicate the timer is stopped
                    timerDisplay.classList.add('text-danger');
                    
                    // Disable the button to prevent multiple clicks
                    this.disabled = true;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Ending...';
                    
                    // Submit the form to end consultation
                    document.getElementById('endConsultationForm').submit();
                });
            }
            
            // File upload handling
            const fileInput = document.getElementById('message_file');
            const fileInfoDiv = document.getElementById('selected-file-info');
            
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        const fileName = this.files[0].name;
                        const fileSize = (this.files[0].size / 1024).toFixed(2) + ' KB';
                        
                        fileInfoDiv.innerHTML = `<i class="fas fa-paperclip me-1"></i> ${fileName} (${fileSize}) <button type="button" class="btn btn-sm text-danger" id="remove-file"><i class="fas fa-times"></i></button>`;
                        fileInfoDiv.style.display = 'block';
                        
                        // Add event listener to remove file button
                        document.getElementById('remove-file').addEventListener('click', function() {
                            fileInput.value = '';
                            fileInfoDiv.style.display = 'none';
                        });
                    } else {
                        fileInfoDiv.style.display = 'none';
                    }
                });
            }
            
            // Image preview modal
            window.openImageModal = function(imageSrc) {
                const previewImage = document.getElementById('previewImage');
                const downloadLink = document.getElementById('downloadImageLink');
                
                if (previewImage && downloadLink) {
                    previewImage.src = imageSrc;
                    downloadLink.href = imageSrc;
                    
                    openModal('imagePreviewModal');
                }
            };

            function refreshConsultationDetails() {
                const consultationId = <?php echo $consultation_id ?? 0; ?>;
                const clientId = <?php echo $client_id ?? 0; ?>;
                if (!consultationId || !clientId) return;
                
                fetch(`expert-chat.php?client_id=${clientId}&consultation_id=${consultationId}`)
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
                            
                            // Check if consultation status is completed
                            const statusBadge = newDetails.querySelector('.status-badge');
                            if (statusBadge && statusBadge.classList.contains('completed')) {
                                console.log('Consultation has been completed by client');
                                
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
                                        <div>This consultation has been completed by client.</div>
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
                                    window.location.href = "expert-consultations.php";
                                }, 7000);
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
            refreshConsultationDetails();
        });
    </script>
</body>
</html>
