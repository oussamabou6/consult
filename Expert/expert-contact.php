<?php
// Start the session
session_start();
require_once '../config/config.php';

// Check if user is logged in and is an expert
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
$messages = [];

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

// Process sending a new message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["send_message"])) {
    $message = trim($_POST["message"]);
    
    if (!empty($message)) {
        // Get admin ID (assuming admin ID is 1, adjust as needed)
        $admin_id = 1;
        
        // Insert into chat_messages table
        $insert_message_sql = "INSERT INTO chat_messages (sender_id, receiver_id, message, is_read, sender_type, created_at, message_type) 
                              VALUES (?, ?, ?, 0, 'expert', NOW(), 'text')";
        $stmt = $conn->prepare($insert_message_sql);
        $stmt->bind_param("iis", $user_id, $admin_id, $message);
        
        if ($stmt->execute()) {
            $success_message = "Message sent successfully.";
            // Redirect to avoid form resubmission
            header("Location: expert-contact.php?success=1");
            exit;
        } else {
            $error_message = "Error sending message: " . $conn->error;
        }
    } else {
        $error_message = "Message cannot be empty.";
    }
}

// Handle AJAX request for new messages
if (isset($_GET['action']) && $_GET['action'] == 'get_new_messages' && isset($_GET['last_id'])) {
    $last_id = (int)$_GET['last_id'];
    
    // Get admin ID (assuming admin ID is 1, adjust as needed)
    $admin_id = 1;
    
    $new_messages_query = "SELECT * FROM chat_messages 
                          WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
                          AND id > ? 
                          ORDER BY created_at ASC";
    $stmt = $conn->prepare($new_messages_query);
    $stmt->bind_param("iiiii", $user_id, $admin_id, $admin_id, $user_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $new_messages = [];
    while ($row = $result->fetch_assoc()) {
        $new_messages[] = $row;
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($new_messages);
    exit;
}

// Handle file upload
if (isset($_POST['action']) && $_POST['action'] == 'upload_file') {
    // Get admin ID (assuming admin ID is 1, adjust as needed)
    $admin_id = 1;
    
    $response = ['success' => false, 'message' => ''];
    
    if (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $file = $_FILES['file'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check file size (limit to 10MB)
        if ($file_size > 10485760) {
            $response['message'] = 'File size too large. Maximum 10MB allowed.';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // Check file extension
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
        if (!in_array($file_ext, $allowed_extensions)) {
            $response['message'] = 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions);
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }
        
        // Generate unique filename
        $unique_name = uniqid() . '_' . time() . '.' . $file_ext;
        $upload_dir = '../uploads/chat_files/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $upload_path = $upload_dir . $unique_name;
        
        if (move_uploaded_file($file_tmp, $upload_path)) {
            // Determine message type based on file extension
            $message_type = 'file';
            if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                $message_type = 'image';
            }
            
            // Insert into chat_messages table
            $insert_message_sql = "INSERT INTO chat_messages (sender_id, receiver_id, message, file_path, is_read, sender_type, created_at, message_type) 
                                  VALUES (?, ?, ?, ?, 0, 'expert', NOW(), ?)";
            $stmt = $conn->prepare($insert_message_sql);
            $stmt->bind_param("iisss", $user_id, $admin_id, $file_name, $upload_path, $message_type);
            
            if ($stmt->execute()) {
                $message_id = $conn->insert_id;
                
                // Get the inserted message
                $get_message_sql = "SELECT * FROM chat_messages WHERE id = ?";
                $stmt = $conn->prepare($get_message_sql);
                $stmt->bind_param("i", $message_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $new_message = $result->fetch_assoc();
                
                $response['success'] = true;
                $response['message'] = 'File sent successfully.';
                $response['data'] = $new_message;
            } else {
                $response['message'] = 'Error sending file: ' . $conn->error;
            }
        } else {
            $response['message'] = 'Error uploading file.';
        }
    } else {
        $response['message'] = 'No file uploaded or file upload error.';
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Mark messages as read when clicking on the chat
if (isset($_GET['mark_read'])) {
    // Get admin ID (assuming admin ID is 1, adjust as needed)
    $admin_id = 1;
    
    $mark_read_query = "UPDATE chat_messages 
                       SET is_read = 1 
                       WHERE sender_id = ? AND receiver_id = ? AND is_read = 0 AND sender_type = 'admin'";
    $stmt = $conn->prepare($mark_read_query);
    $stmt->bind_param("ii", $admin_id, $user_id);
    $stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// Get messages between user and admin
$admin_id = 1; // Assuming admin ID is 1, adjust as needed
$messages_query = "SELECT * FROM chat_messages 
                  WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                  ORDER BY created_at ASC";
$stmt = $conn->prepare($messages_query);
$stmt->bind_param("iiii", $user_id, $admin_id, $admin_id, $user_id);
$stmt->execute();
$messages_result = $stmt->get_result();

if ($messages_result) {
    while ($row = $messages_result->fetch_assoc()) {
        $messages[] = $row;
    }
}

// Get notification counts

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
$pending_consultations = $conn->prepare("SELECT COUNT(*) as count FROM consultations WHERE expert_id = ? AND status = 'pending'");
$pending_consultations->bind_param("i", $user_id);
$pending_consultations->execute();
$pending_consultations_result = $pending_consultations->get_result();
$pending_consultations_count = $pending_consultations_result->fetch_assoc()['count'];
$pending_consultations->close();

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
// Function to format date
function formatDate($date) {
    $now = new DateTime();
    $messageDate = new DateTime($date);
    $diff = $now->diff($messageDate);
    
    if ($diff->y > 0) {
        return $messageDate->format('M j, Y, g:i a');
    } elseif ($diff->m > 0 || $diff->d > 7) {
        return $messageDate->format('M j, g:i a');
    } elseif ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Admin - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
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
        
        /* Chat Container */
        .chat-container {
            background-color: #ffffff;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            height: calc(100vh - 250px);
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            background-color: #fafbfc;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .admin-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            border: 2px solid white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .admin-info {
            flex-grow: 1;
        }
        
        .admin-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }
        
        .admin-status {
            font-size: 0.9rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 0.5rem;
        }
        
        .status-online {
            background-color: var(--success-color);
        }
        
        .status-offline {
            background-color: var(--text-muted);
        }
        
        .chat-messages {
            flex: 1;
            padding: 1.25rem;
            overflow-y: auto;
            background-color: #f8fafc;
        }
        
        .message {
            display: flex;
            margin-bottom: 1.25rem;
            position: relative;
        }
        
        .message-incoming {
            justify-content: flex-start;
        }
        
        .message-outgoing {
            justify-content: flex-end;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 0.75rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
        }
        
        .message-outgoing .message-avatar {
            order: 1;
            margin-right: 0;
            margin-left: 0.75rem;
            background: linear-gradient(135deg, var(--secondary-color), var(--info-color));
        }
        
        .message-content {
            max-width: 70%;
            padding: 1rem;
            border-radius: 15px;
            position: relative;
        }
        
        .message-incoming .message-content {
            background-color: white;
            border: 1px solid rgba(226, 232, 240, 0.7);
            border-top-left-radius: 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .message-outgoing .message-content {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border-top-right-radius: 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .message-text {
            margin-bottom: 0.5rem;
            word-wrap: break-word;
        }
        
        .message-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: right;
        }
        
        .message-outgoing .message-time {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .message-image {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .message-image:hover {
            opacity: 0.9;
        }
        
        .message-file {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background-color: rgba(226, 232, 240, 0.5);
            border-radius: 10px;
            margin-bottom: 0.5rem;
        }
        
        .message-outgoing .message-file {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .message-file i {
            font-size: 1.5rem;
        }
        
        .message-file-info {
            flex: 1;
        }
        
        .message-file-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
            word-break: break-all;
        }
        
        .message-file-size {
            font-size: 0.75rem;
            opacity: 0.7;
        }
        
        .message-file-download {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .message-outgoing .message-file-download {
            color: white;
        }
        
        .message-file-download:hover {
            text-decoration: underline;
        }
        
        .chat-input {
            padding: 1.25rem;
            border-top: 1px solid rgba(226, 232, 240, 0.7);
            background-color: white;
        }
        
        .chat-form {
            display: flex;
            gap: 1rem;
            align-items: flex-end;
        }
        
        .chat-input-container {
            flex: 1;
            position: relative;
        }
        
        .chat-input-field {
            width: 100%;
            padding: 0.75rem 3rem 0.75rem 1rem;
            border: 1px solid rgba(226, 232, 240, 0.7);
            border-radius: 10px;
            resize: none;
            min-height: 50px;
            max-height: 150px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .chat-input-field:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .chat-input-actions {
            position: absolute;
            right: 1rem;
            bottom: 0.75rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .chat-input-action {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.25rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .chat-input-action:hover {
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .chat-send-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chat-send-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(99, 102, 241, 0.3);
        }
        
        .chat-send-btn i {
            font-size: 1.1rem;
        }
        
        .no-messages {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            padding: 2rem;
            text-align: center;
            color: var(--text-muted);
        }
        
        .no-messages i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: rgba(99, 102, 241, 0.2);
        }
        
        .no-messages h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }
        
        .file-preview {
            margin-top: 1rem;
            padding: 0.75rem;
            background-color: #f8fafc;
            border-radius: 10px;
            border: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .file-preview i {
            font-size: 1.5rem;
            color: var(--primary-color);
        }
        
        .file-preview-info {
            flex: 1;
        }
        
        .file-preview-name {
            font-weight: 500;
            margin-bottom: 0.25rem;
            word-break: break-all;
        }
        
        .file-preview-size {
            font-size: 0.75rem;
            color: var(--text-muted);
        }
        
        .file-preview-remove {
            color: var(--danger-color);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .file-preview-remove:hover {
            transform: scale(1.1);
        }
        
        .date-divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }
        
        .date-divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background-color: rgba(226, 232, 240, 0.7);
            z-index: 1;
        }
        
        .date-divider span {
            background-color: #f8fafc;
            padding: 0 1rem;
            position: relative;
            z-index: 2;
            color: var(--text-muted);
            font-size: 0.85rem;
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
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .chat-container {
                height: calc(100vh - 200px);
            }
        }
        
        @media (max-width: 767.98px) {
            .chat-container {
                height: calc(100vh - 180px);
            }
            
            .message-content {
                max-width: 85%;
            }
        }
        
        @media (max-width: 575.98px) {
            .chat-header {
                padding: 1rem;
            }
            
            .chat-messages {
                padding: 1rem;
            }
            
            .chat-input {
                padding: 1rem;
            }
            
            .message-content {
                max-width: 90%;
                padding: 0.75rem;
            }
            
            .message-avatar {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
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
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-consultations.php">
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
                    <a class="nav-link d-flex flex-column align-items-center active" href="expert-contact.php">
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
                        <i class="fas fa-envelope me-2 text-primary"></i> Contact Admin
                    </h1>
                </div>
                <div class="dashboard-card-body">
                    <p class="text-muted">
                        Use this page to communicate with the site administrators. You can ask questions, report issues, or request assistance.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chat Section -->
    <div class="row">
        <div class="col-12">
            <div class="chat-container" id="chatContainer">
                <div class="chat-header">
                    <div class="admin-avatar">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="admin-info">
                        <h3 class="admin-name">Admin Support</h3>
                        <div class="admin-status">
                            <span class="status-indicator status-online"></span> Available to help
                        </div>
                    </div>
                </div>
                
                <div class="chat-messages" id="chatMessages">
                    <?php if (empty($messages)): ?>
                        <div class="no-messages">
                            <i class="fas fa-comments"></i>
                            <h3>No messages yet</h3>
                            <p>Start a conversation with the admin by sending a message below.</p>
                        </div>
                    <?php else: ?>
                        <?php 
                        $currentDate = '';
                        foreach ($messages as $message): 
                            $messageDate = date('Y-m-d', strtotime($message['created_at']));
                            if ($currentDate != $messageDate):
                                $currentDate = $messageDate;
                                $formattedDate = date('F j, Y', strtotime($message['created_at']));
                        ?>
                            <div class="date-divider">
                                <span><?php echo $formattedDate; ?></span>
                            </div>
                            <?php endif; ?>
                        
                            <div class="message <?php echo ($message['sender_type'] == 'admin') ? 'message-incoming' : 'message-outgoing'; ?>" data-id="<?php echo $message['id']; ?>">
                            <div class="message-avatar">
                                <?php if ($message['sender_type'] == 'admin'): ?>
                                    <i class="fas fa-user-shield"></i>
                                <?php else: ?>
                                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="message-content">
                                <?php if ($message['message_type'] == 'image'): ?>
                                    <img src="<?php echo htmlspecialchars($message['file_path']); ?>" alt="Image" class="message-image" onclick="window.open('<?php echo htmlspecialchars($message['file_path']); ?>')">
                                    <?php if (!empty($message['message'])): ?>
                                        <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                    <?php endif; ?>
                                <?php elseif ($message['message_type'] == 'file'): ?>
                                    <div class="message-file">
                                        <i class="fas fa-file"></i>
                                        <div class="message-file-info">
                                            <div class="message-file-name"><?php echo htmlspecialchars($message['message']); ?></div>
                                            <a href="<?php echo htmlspecialchars($message['file_path']); ?>" download class="message-file-download">Download</a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                <?php endif; ?>
                                <div class="message-time">
                                    <?php echo date('g:i a', strtotime($message['created_at'])); ?>
                                    <?php if ($message['sender_type'] == 'expert'): ?>
                                        <i class="fas <?php echo $message['is_read'] ? 'fa-check-double' : 'fa-check'; ?>" title="<?php echo $message['is_read'] ? 'Read' : 'Sent'; ?>"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <div class="chat-input">
                    <form class="chat-form" id="messageForm" method="post" action="expert-contact.php">
                        <div class="chat-input-container">
                            <textarea class="chat-input-field" name="message" id="messageInput" placeholder="Type your message here..." required></textarea>
                            <div class="chat-input-actions">
                                <label for="fileInput" class="chat-input-action" title="Attach File">
                                    <i class="fas fa-paperclip"></i>
                                    <input type="file" id="fileInput" style="display: none;">
                                </label>
                            </div>
                        </div>
                        <button type="submit" name="send_message" class="chat-send-btn">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </form>
                    <div id="filePreviewContainer" style="display: none;"></div>
                </div>
            </div>
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
                    <li><a href="expert-experts.php"><i class="fas fa-users"></i> Experts</a></li>
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
    // Mark messages as read when the chat is opened
    fetch('expert-contact.php?mark_read=1')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('Messages marked as read');
                // Update notification badge
                updateNotificationBadge('.admin-messages-badge', 0);
            }
        })
        .catch(error => console.error('Error marking messages as read:', error));
    
    // Scroll to bottom of chat
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Auto-resize textarea
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight < 150 ? this.scrollHeight : 150) + 'px';
        });
    }
    
    // Handle file input
    const fileInput = document.getElementById('fileInput');
    const filePreviewContainer = document.getElementById('filePreviewContainer');
    let selectedFile = null;
    
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                selectedFile = this.files[0];
                
                // Check file size (max 10MB)
                if (selectedFile.size > 10 * 1024 * 1024) {
                    alert('File size too large. Maximum 10MB allowed.');
                    this.value = '';
                    selectedFile = null;
                    return;
                }
                
                // Check file type
                const fileType = selectedFile.type.split('/')[0];
                const fileExtension = selectedFile.name.split('.').pop().toLowerCase();
                const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
                
                if (!allowedExtensions.includes(fileExtension)) {
                    alert('Invalid file type. Allowed types: ' + allowedExtensions.join(', '));
                    this.value = '';
                    selectedFile = null;
                    return;
                }
                
                // Create file preview
                let fileIcon = 'fa-file';
                if (fileType === 'image') {
                    fileIcon = 'fa-file-image';
                } else if (fileExtension === 'pdf') {
                    fileIcon = 'fa-file-pdf';
                } else if (['doc', 'docx'].includes(fileExtension)) {
                    fileIcon = 'fa-file-word';
                } else if (['xls', 'xlsx'].includes(fileExtension)) {
                    fileIcon = 'fa-file-excel';
                } else if (fileExtension === 'txt') {
                    fileIcon = 'fa-file-alt';
                }
                
                filePreviewContainer.innerHTML = `
                    <div class="file-preview">
                        <i class="fas ${fileIcon}"></i>
                        <div class="file-preview-info">
                            <div class="file-preview-name">${selectedFile.name}</div>
                            <div class="file-preview-size">${formatFileSize(selectedFile.size)}</div>
                        </div>
                        <button type="button" class="file-preview-remove" onclick="removeFilePreview()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                filePreviewContainer.style.display = 'block';
                
                // Upload file
                uploadFile();
            }
        });
    }
    
    // Function to format file size
    function formatFileSize(bytes) {
        if (bytes < 1024) {
            return bytes + ' bytes';
        } else if (bytes < 1048576) {
            return (bytes / 1024).toFixed(2) + ' KB';
        } else {
            return (bytes / 1048576).toFixed(2) + ' MB';
        }
    }
    
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
    
    // Set interval to fetch notifications every 5 seconds
    setInterval(fetchNotifications, 1000);
    
    // Poll for new messages
    let lastMessageId = <?php echo !empty($messages) ? end($messages)['id'] : 0; ?>;
    
    setInterval(function() {
        fetch(`expert-contact.php?action=get_new_messages&last_id=${lastMessageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.length > 0) {
                    // Update last message ID
                    lastMessageId = data[data.length - 1].id;
                    
                    // Add new messages to chat
                    const chatMessages = document.getElementById('chatMessages');
                    let currentDate = document.querySelector('.date-divider:last-child span')?.textContent || '';
                    
                    data.forEach(message => {
                        const messageDate = new Date(message.created_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
                        
                        // Add date divider if needed
                        if (messageDate !== currentDate) {
                            currentDate = messageDate;
                            const dateDivider = document.createElement('div');
                            dateDivider.className = 'date-divider';
                            dateDivider.innerHTML = `<span>${messageDate}</span>`;
                            chatMessages.appendChild(dateDivider);
                        }
                        
                        // Create message element
                        const messageElement = document.createElement('div');
                        messageElement.className = `message ${message.sender_type === 'admin' ? 'message-incoming' : 'message-outgoing'}`;
                        messageElement.dataset.id = message.id;
                        
                        // Create message content
                        let messageContent = '';
                        
                        // Avatar
                        messageContent += `
                            <div class="message-avatar">
                                ${message.sender_type === 'admin' ? '<i class="fas fa-user-shield"></i>' : '<?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>'}
                            </div>
                        `;
                        
                        // Message content
                        messageContent += '<div class="message-content">';
                        
                        if (message.message_type === 'image') {
                            messageContent += `
                                <img src="${message.file_path}" alt="Image" class="message-image" onclick="window.open('${message.file_path}')">
                            `;
                            if (message.message) {
                                messageContent += `<div class="message-text">${message.message}</div>`;
                            }
                        } else if (message.message_type === 'file') {
                            messageContent += `
                                <div class="message-file">
                                    <i class="fas fa-file"></i>
                                    <div class="message-file-info">
                                        <div class="message-file-name">${message.message}</div>
                                        <a href="${message.file_path}" download class="message-file-download">Download</a>
                                    </div>
                                </div>
                            `;
                        } else {
                            messageContent += `<div class="message-text">${message.message}</div>`;
                        }
                        
                        // Message time
                        const messageTime = new Date(message.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
                        messageContent += `
                            <div class="message-time">
                                ${messageTime}
                                ${message.sender_type === 'expert' ? `<i class="fas ${message.is_read ? 'fa-check-double' : 'fa-check'}" title="${message.is_read ? 'Read' : 'Sent'}"></i>` : ''}
                            </div>
                        `;
                        
                        messageContent += '</div>';
                        
                        messageElement.innerHTML = messageContent;
                        chatMessages.appendChild(messageElement);
                    });
                    
                    // Scroll to bottom
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                    
                    // If new message is from admin, mark as read
                    const adminMessages = data.filter(message => message.sender_type === 'admin');
                    if (adminMessages.length > 0) {
                        fetch('expert-contact.php?mark_read=1')
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    console.log('Messages marked as read');
                                    // Update notification badge
                                    updateNotificationBadge('.admin-messages-badge', 0);
                                }
                            })
                            .catch(error => console.error('Error marking messages as read:', error));
                    }
                }
            })
            .catch(error => console.error('Error fetching new messages:', error));
    }, 500); // Check every 3 seconds
});

// Function to remove file preview
function removeFilePreview() {
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreviewContainer').innerHTML = '';
    document.getElementById('filePreviewContainer').style.display = 'none';
}

// Function to upload file
function uploadFile() {
    const fileInput = document.getElementById('fileInput');
    if (!fileInput.files.length) return;
    
    const file = fileInput.files[0];
    const formData = new FormData();
    formData.append('action', 'upload_file');
    formData.append('file', file);
    
    fetch('expert-contact.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Clear file input and preview
            removeFilePreview();
            
            // Add the new message to the chat
            const chatMessages = document.getElementById('chatMessages');
            const messageElement = document.createElement('div');
            messageElement.className = 'message message-outgoing';
            messageElement.dataset.id = data.data.id;
            
            // Create message content
            let messageContent = '';
            
            // Avatar
            messageContent += `
                <div class="message-avatar">
                    <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                </div>
            `;
            
            // Message content
            messageContent += '<div class="message-content">';
            
            if (data.data.message_type === 'image') {
                messageContent += `
                    <img src="${data.data.file_path}" alt="Image" class="message-image" onclick="window.open('${data.data.file_path}')">
                `;
                if (data.data.message) {
                    messageContent += `<div class="message-text">${data.data.message}</div>`;
                }
            } else if (data.data.message_type === 'file') {
                messageContent += `
                    <div class="message-file">
                        <i class="fas fa-file"></i>
                        <div class="message-file-info">
                            <div class="message-file-name">${data.data.message}</div>
                            <a href="${data.data.file_path}" download class="message-file-download">Download</a>
                        </div>
                    </div>
                `;
            }
            
            // Message time
            const messageTime = new Date(data.data.created_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: 'numeric', hour12: true });
            messageContent += `
                <div class="message-time">
                    ${messageTime}
                    <i class="fas fa-check" title="Sent"></i>
                </div>
            `;
            
            messageContent += '</div>';
            
            messageElement.innerHTML = messageContent;
            
            // If there are no messages, remove the no-messages div
            const noMessages = document.querySelector('.no-messages');
            if (noMessages) {
                noMessages.remove();
            }
            
            chatMessages.appendChild(messageElement);
            
            // Scroll to bottom
            chatMessages.scrollTop = chatMessages.scrollHeight;
        } else {
            alert('Error uploading file: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while uploading the file.');
    });
}
</script>
</body>
</html>
