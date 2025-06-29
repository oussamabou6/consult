<?php
// Start the session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"])) {
    // Redirect to login page if not logged in
    header("Location: ../login.php");
    exit;
}

// Initialize variables
$error_message = "";
$success_message = "";
$selected_expert_id = isset($_GET['expert_id']) ? (int)$_GET['expert_id'] : 0;
$selected_expert = null;
$messages = [];

// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Get all experts with approved status
$experts_query = "SELECT u.id, u.full_name, p.profile_image, ep.category, u.status,
                 (SELECT COUNT(*) FROM chat_messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) as unread_count,
                 (SELECT message FROM chat_messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message,
                 (SELECT created_at FROM chat_messages WHERE (sender_id = u.id AND receiver_id = ?) OR (sender_id = ? AND receiver_id = u.id) ORDER BY created_at DESC LIMIT 1) as last_message_time
                 FROM users u
                 LEFT JOIN user_profiles p ON u.id = p.user_id
                 LEFT JOIN expert_profiledetails ep ON u.id = ep.user_id
                 WHERE u.role = 'expert' AND ep.status = 'approved'
                 ORDER BY last_message_time DESC";

$stmt = $conn->prepare($experts_query);
$user_id = $_SESSION["user_id"];
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$experts_result = $stmt->get_result();
$experts = [];

if ($experts_result && $experts_result->num_rows > 0) {
    while ($row = $experts_result->fetch_assoc()) {
        // Get category name
        if (!empty($row['category'])) {
            $category_query = "SELECT name FROM categories WHERE id = ?";
            $cat_stmt = $conn->prepare($category_query);
            $cat_stmt->bind_param("i", $row['category']);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result();
            if ($cat_result && $cat_result->num_rows > 0) {
                $row['specialty'] = $cat_result->fetch_assoc()['name'];
            } else {
                $row['specialty'] = "Expert";
            }
        } else {
            $row['specialty'] = "Expert";
        }
        
        $experts[] = $row;
    }
}

// Process sending a new message
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["send_message"]) && $selected_expert_id > 0) {
    $message = trim($_POST["message"]);
    
    if (!empty($message)) {
        // Insert into chat_messages table
        $insert_message_sql = "INSERT INTO chat_messages (sender_id, receiver_id, message, is_read, sender_type, created_at, message_type) 
                              VALUES (?, ?, ?, 0, 'admin', NOW(), 'text')";
        $stmt = $conn->prepare($insert_message_sql);
        $user_id = $_SESSION["user_id"];
        $stmt->bind_param("iis", $user_id, $selected_expert_id, $message);
        
        if ($stmt->execute()) {
            $success_message = "Message sent successfully.";
            // Redirect to avoid form resubmission
            header("Location: expert-messages.php?expert_id=" . $selected_expert_id . "&success=1");
            exit;
        } else {
            $error_message = "Error sending message: " . $conn->error;
        }
    } else {
        $error_message = "Message cannot be empty.";
    }
}

// Handle AJAX request for new messages
if (isset($_GET['action']) && $_GET['action'] == 'get_new_messages' && isset($_GET['expert_id']) && isset($_GET['last_id'])) {
    $expert_id = (int)$_GET['expert_id'];
    $last_id = (int)$_GET['last_id'];
    $user_id = $_SESSION["user_id"];
    
    $new_messages_query = "SELECT * FROM chat_messages 
                          WHERE ((sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)) 
                          AND id > ? 
                          ORDER BY created_at ASC";
    $stmt = $conn->prepare($new_messages_query);
    $stmt->bind_param("iiiii", $user_id, $expert_id, $expert_id, $user_id, $last_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $new_messages = [];
    while ($row = $result->fetch_assoc()) {
        $new_messages[] = $row;
    }
    
    // Mark messages as read if they are from expert
    if (!empty($new_messages)) {
        $mark_read_query = "UPDATE chat_messages 
                           SET is_read = 1 
                           WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
        $stmt = $conn->prepare($mark_read_query);
        $stmt->bind_param("ii", $expert_id, $user_id);
        $stmt->execute();
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($new_messages);
    exit;
}

// Handle file upload
if (isset($_POST['action']) && $_POST['action'] == 'upload_file' && isset($_POST['expert_id'])) {
    $expert_id = (int)$_POST['expert_id'];
    $user_id = $_SESSION["user_id"];
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
                                  VALUES (?, ?, ?, ?, 0, 'admin', NOW(), ?)";
            $stmt = $conn->prepare($insert_message_sql);
            $stmt->bind_param("iisss", $user_id, $expert_id, $file_name, $upload_path, $message_type);
            
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

// Mark messages as read when clicking on an expert's chat
if (isset($_GET['mark_read']) && isset($_GET['expert_id'])) {
    $expert_id = (int)$_GET['expert_id'];
    $user_id = $_SESSION["user_id"];
    
    $mark_read_query = "UPDATE chat_messages 
                       SET is_read = 1 
                       WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
    $stmt = $conn->prepare($mark_read_query);
    $stmt->bind_param("ii", $expert_id, $user_id);
    $stmt->execute();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
    exit;
}

// If an expert is selected, get their details and messages
if ($selected_expert_id > 0) {
    // Get expert details
    $expert_query = "SELECT u.*, p.profile_image, p.bio, ep.category 
                    FROM users u 
                    LEFT JOIN user_profiles p ON u.id = p.user_id 
                    LEFT JOIN expert_profiledetails ep ON u.id = ep.user_id 
                    WHERE u.id = ? AND u.role = 'expert'";
    $stmt = $conn->prepare($expert_query);
    $stmt->bind_param("i", $selected_expert_id);
    $stmt->execute();
    $expert_result = $stmt->get_result();
    
    if ($expert_result && $expert_result->num_rows > 0) {
        $selected_expert = $expert_result->fetch_assoc();
        
        // Get category name
        if (!empty($selected_expert['category'])) {
            $category_query = "SELECT name FROM categories WHERE id = ?";
            $cat_stmt = $conn->prepare($category_query);
            $cat_stmt->bind_param("i", $selected_expert['category']);
            $cat_stmt->execute();
            $cat_result = $cat_stmt->get_result();
            if ($cat_result && $cat_result->num_rows > 0) {
                $selected_expert['specialty'] = $cat_result->fetch_assoc()['name'];
            } else {
                $selected_expert['specialty'] = "Expert";
            }
        } else {
            $selected_expert['specialty'] = "Expert";
        }
        
        // Get messages between user and expert
        $user_id = $_SESSION["user_id"];
        $messages_query = "SELECT * FROM chat_messages 
                          WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?) 
                          ORDER BY created_at ASC";
        $stmt = $conn->prepare($messages_query);
        $stmt->bind_param("iiii", $user_id, $selected_expert_id, $selected_expert_id, $user_id);
        $stmt->execute();
        $messages_result = $stmt->get_result();
        
        if ($messages_result) {
            while ($row = $messages_result->fetch_assoc()) {
                $messages[] = $row;
            }
            
            // Mark messages as read
            $mark_read_query = "UPDATE chat_messages 
                               SET is_read = 1 
                               WHERE sender_id = ? AND receiver_id = ? AND is_read = 0";
            $stmt = $conn->prepare($mark_read_query);
            $stmt->bind_param("ii", $selected_expert_id, $user_id);
            $stmt->execute();
        }
    } else {
        $error_message = "Expert not found.";
    }
}

// Get unread notifications count
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get pending withdrawals count
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending fund requests count
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending expert profile count
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];
 
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];

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

// Function to get user status badge
function getUserStatusBadge($role) {
    switch($role) {
        case 'admin':
            return '<span class="status-badge status-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
        case 'expert':
            return '<span class="status-badge status-expert"><i class="fas fa-user-tie"></i> Expert</span>';
        case 'client':
            return '<span class="status-badge status-client"><i class="fas fa-user"></i> Client</span>';
        default:
            return '<span class="status-badge">' . ucfirst($role) . '</span>';
    }
}

// Function to get profile status badge
function getProfileStatusBadge($status) {
    switch($status) {
        case 'approved':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Approved</span>';
        case 'pending_review':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'pending_revision':
            return '<span class="status-badge status-revision"><i class="fas fa-edit"></i> Revision</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert Messages - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Main Colors */
            --primary-color: #7C3AED;
            --primary-light: #A78BFA;
            --primary-dark: #6D28D9;
            --primary-bg: rgba(124, 58, 237, 0.1);
            --primary-gradient: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
            
            --secondary-color: #64748b;
            --secondary-light: #94a3b8;
            --secondary-dark: #475569;
            --secondary-bg: rgba(100, 116, 139, 0.1);
            
            --success-color: #10b981;
            --success-light: #34d399;
            --success-dark: #059669;
            --success-bg: rgba(16, 185, 129, 0.1);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            
            --warning-color: #f59e0b;
            --warning-light: #fbbf24;
            --warning-dark: #d97706;
            --warning-bg: rgba(245, 158, 11, 0.1);
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            
            --danger-color: #ef4444;
            --danger-light: #f87171;
            --danger-dark: #dc2626;
            --danger-bg: rgba(239, 68, 44, 0.1);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            
            --info-color: #06b6d4;
            --info-light: #22d3ee;
            --info-dark: #0891b2;
            --info-bg: rgba(6, 182, 212, 0.1);
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            
            /* Neutral Colors */
            --light-color: #f8fafc;
            --light-color-2: #f1f5f9;
            --light-color-3: #e2e8f0;
            
            --dark-color: #0f172a;
            --dark-color-2: #1e293b;
            --dark-color-3: #334155;
            
            --border-color: #e2e8f0;
            --border-color-dark: #cbd5e1;
            
            /* Background Colors */
            --card-bg: #ffffff;
            --body-bg: #f8fafc;
            --body-bg-gradient: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            
            /* Text Colors */
            --text-color: #334155;
            --text-color-light: #64748b;
            --text-color-lighter: #94a3b8;
            --text-color-dark: #1e293b;
            --text-color-darker: #0f172a;
            
            /* Shadow Variables */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 6px 10px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
            --shadow-outline: 0 0 0 3px rgba(124, 58, 237, 0.2);
            
            /* Border Radius */
            --radius-sm: 0.25rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-full: 9999px;
            
            /* Transitions */
            --transition: all 0.3s ease;
            --transition-slow: all 0.5s ease;
            --transition-fast: all 0.15s ease;
            --transition-bounce: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            
            /* Z-index */
            --z-negative: -1;
            --z-normal: 1;
            --z-tooltip: 10;
            --z-fixed: 100;
            --z-modal: 1000;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        
        body {
            background: var(--body-bg-gradient);
            color: var(--text-color);
            line-height: 1.6;
            font-size: 0.95rem;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%237C3AED' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            z-index: var(--z-negative);
            pointer-events: none;
        }
        
        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition-fast);
        }
        
        a:hover {
            color: var(--primary-dark);
        }
        
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: var(--dark-color-2);
            color: white;
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: var(--z-fixed);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: var(--dark-color-3);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: var(--radius-full);
        }
        
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .sidebar-header p {
            font-size: 0.875rem;
            opacity: 0.7;
        }
        
        .sidebar-menu {
            padding: 1.5rem 0;
        }
        
        .menu-item {
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            transition: var(--transition);
            text-decoration: none;
            color: var(--light-color-3);
            position: relative;
            overflow: hidden;
        }
        
        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--primary-gradient);
            transform: scaleY(0);
            transition: var(--transition);
        }
        
        .menu-item:hover, .menu-item.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .menu-item:hover::before, .menu-item.active::before {
            transform: scaleY(1);
        }
        
        .menu-item.active {
            background-color: rgba(124, 58, 237, 0.1);
            font-weight: 500;
        }
        
        .menu-item i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
            color: var(--primary-light);
            transition: var(--transition);
        }
        
        .menu-item:hover i, .menu-item.active i {
            color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 0.5rem;
            right: 1.5rem;
            background: var(--danger-gradient);
            color: white;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.1rem 0.4rem;
            min-width: 1.2rem;
            text-align: center;
            box-shadow: var(--shadow);
            animation: pulse 2s infinite;
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: var(--transition);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: var(--radius-full);
        }
        
        .header h1 {
            color: var(--dark-color);
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .header h1 i {
            color: var(--primary-color);
            background: var(--primary-bg);
            padding: 0.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 1rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-full);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .user-info:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 50%);
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
        }
        
        .user-role {
            color: var(--text-color-light);
            font-size: 0.75rem;
        }
        
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInDown 0.5s ease;
            position: relative;
            box-shadow: var(--shadow);
            border-left: 4px solid transparent;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }
        
        .alert-error {
            background-color: var(--danger-bg);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
        }
        
        .alert-close {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: var(--transition-fast);
        }
        
        .alert-close:hover {
            opacity: 1;
            transform: translateY(-50%) rotate(90deg);
        }
        
        .chat-container {
            display: flex;
            height: calc(100vh - 120px);
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }
        
        .chat-sidebar {
            width: 300px;
            background-color: var(--light-color-2);
            border-right: 1px solid var(--border-color);
            overflow-y: auto;
        }
        
        .chat-sidebar-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            background-color: var(--light-color);
        }
        
        .chat-sidebar-header h3 {
            font-size: 18px;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .chat-search {
            padding: 10px 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .search-input {
            width: 100%;
            padding: 8px 15px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-full);
            font-size: 14px;
        }
        
        .chat-contacts {
            overflow-y: auto;
        }
        
        .chat-contact {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            color: var(--text-color);
        }
        
        .chat-contact:hover, .chat-contact.active {
            background-color: var(--primary-bg);
        }
        
        .chat-contact.active {
            border-left: 3px solid var(--primary-color);
        }
        
        .contact-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 600;
            margin-right: 15px;
            position: relative;
        }
        
        .contact-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .contact-status {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        .status-online {
            background-color: var(--success-color);
        }
        
        .status-offline {
            background-color: var(--secondary-color);
        }
        
        .contact-info {
            flex: 1;
            min-width: 0;
        }
        
        .contact-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .contact-specialty {
            font-size: 12px;
            color: var(--secondary-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .contact-last-message {
            font-size: 12px;
            color: var(--secondary-color);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-top: 4px;
        }
        
        .contact-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            margin-left: 8px;
        }
        
        .contact-time {
            font-size: 11px;
            color: var(--secondary-color);
            margin-bottom: 4px;
        }
        
        .contact-unread {
            background-color: var(--primary-color);
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: var(--radius-full);
            min-width: 18px;
            text-align: center;
        }
        
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--light-color);
        }
        
        .chat-header-info {
            display: flex;
            align-items: center;
        }
        
        .chat-header-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 600;
            margin-right: 15px;
        }
        
        .chat-header-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .chat-header-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 18px;
        }
        
        .chat-header-status {
            font-size: 13px;
            color: var(--secondary-color);
        }
        
        .chat-messages {
            flex: 1;
            padding: 15px;
            overflow-y: auto;
            background-color: var(--light-color-2);
            display: flex;
            flex-direction: column;
        }
        
        .message {
            max-width: 70%;
            margin-bottom: 15px;
            position: relative;
        }
        
        .message-incoming {
            align-self: flex-start;
        }
        
        .message-outgoing {
            align-self: flex-end;
        }
        
        .message-content {
            padding: 10px 15px;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow-wrap: break-word;
            word-wrap: break-word;
            word-break: break-word;
        }
        
        .message-incoming .message-content {
            background-color: white;
            color: var(--text-color);
            border-bottom-left-radius: 0;
        }
        
        .message-outgoing .message-content {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border-bottom-right-radius: 0;
        }
        
        .message-time {
            font-size: 11px;
            margin-top: 4px;
            opacity: 0.7;
            text-align: right;
        }
        
        .message-incoming .message-time {
            color: var(--secondary-color);
        }
        
        .message-outgoing .message-time {
            color: white;
        }
        
        .message-date {
            text-align: center;
            margin: 15px 0;
            font-size: 13px;
            color: var(--secondary-color);
            position: relative;
        }
        
        .message-date::before, .message-date::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 100px;
            height: 1px;
            background-color: var(--border-color);
        }
        
        .message-date::before {
            right: calc(50% + 15px);
        }
        
        .message-date::after {
            left: calc(50% + 15px);
        }
        
        .chat-input {
            padding: 15px;
            border-top: 1px solid var(--border-color);
            background-color: white;
        }
        
        .chat-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }
        
        .chat-input-container {
            flex: 1;
            position: relative;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-full);
            background-color: white;
        }
        
        .chat-input-field {
            width: 100%;
            padding: 12px 40px 12px 15px;
            border: none;
            border-radius: var(--radius-full);
            font-size: 14px;
            resize: none;
            max-height: 120px;
            min-height: 45px;
            overflow-y: auto;
        }
        
        .chat-input-field:focus {
            outline: none;
        }
        
        .chat-input-actions {
            position: absolute;
            right: 15px;
            bottom: 12px;
            display: flex;
            gap: 8px;
        }
        
        .file-upload-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background-color: var(--light-color-2);
            color: var(--text-color);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .file-upload-btn:hover {
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }
        
        .chat-send-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .chat-send-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .message-file {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius);
            margin-top: 5px;
        }
        
        .message-file i {
            font-size: 24px;
        }
        
        .message-file-info {
            display: flex;
            flex-direction: column;
        }
        
        .message-file-name {
            font-weight: 500;
            word-break: break-all;
        }
        
        .message-file-size {
            font-size: 12px;
            opacity: 0.7;
        }
        
        .message-image {
            max-width: 250px;
            max-height: 200px;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
        }
        
        .message-image:hover {
            opacity: 0.9;
        }
        
        .file-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            margin-top: 10px;
            position: relative;
        }
        
        .file-preview-info {
            flex: 1;
            overflow: hidden;
        }
        
        .file-preview-name {
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .file-preview-size {
            font-size: 12px;
            color: var(--secondary-color);
        }
        
        .file-preview-remove {
            cursor: pointer;
            color: var(--danger-color);
            transition: var(--transition);
        }
        
        .file-preview-remove:hover {
            transform: scale(1.1);
        }
        
        .loading-indicator {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        .no-messages {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--secondary-color);
            text-align: center;
            padding: 30px;
        }
        
        .no-messages i {
            font-size: 64px;
            color: var(--primary-light);
            margin-bottom: 15px;
        }
        
        .no-messages h3 {
            font-size: 24px;
            color: var(--dark-color);
            margin-bottom: 8px;
        }
        
        .no-expert-selected {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: var(--secondary-color);
            text-align: center;
            padding: 30px;
            background-color: var(--light-color-2);
        }
        
        .no-expert-selected i {
            font-size: 80px;
            color: var(--primary-light);
            margin-bottom: 20px;
        }
        
        .no-expert-selected h2 {
            font-size: 28px;
            color: var(--dark-color);
            margin-bottom: 8px;
        }
        
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
            z-index: var(--z-normal);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.3);
        }
        
        .btn-primary:hover {
            box-shadow: 0 6px 15px rgba(124, 58, 237, 0.4);
            transform: translateY(-2px);
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 1200px) {
            .main-content {
                padding: 1.5rem;
            }
        }
        
        @media (max-width: 992px) {
            .chat-container {
                height: calc(100vh - 100px);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                overflow-y: hidden;
                max-height: 300px;
                transition: max-height 0.3s ease;
            }
            
            .sidebar.expanded {
                max-height: 100vh;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .chat-container {
                flex-direction: column;
                height: calc(100vh - 100px);
            }
            
            .chat-sidebar {
                width: 100%;
                height: 300px;
                border-right: none;
                border-bottom: 1px solid var(--border-color);
            }
            
            .chat-main {
                height: calc(100% - 300px);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo htmlspecialchars($site_name); ?></h2>
                <p>Admin Dashboard</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="menu-item">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="expert-profiles.php" class="menu-item">
                    <i class="fas fa-user-tie"></i> Expert Profiles
                    <?php if ($pending_review_profiles > 0): ?>
                        <span class="notification-badge"><?php echo $pending_review_profiles; ?></span>
                    <?php endif; ?>
                </a>
                <a href="expert-messages.php" class="menu-item active">
                    <i class="fas fa-comments"></i> Expert Messages
                </a>
                <a href="client-messages.php" class="menu-item">
                    <i class="fas fa-comments"></i> Client Messages
                    <?php if ($pending_messages > 0): ?>
                        <span class="notification-badge"><?php echo $pending_messages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-flag"></i> Reports
                    <?php if ($pending_reports > 0): ?>
                        <span class="notification-badge"><?php echo $pending_reports; ?></span>
                    <?php endif; ?>
                </a>
                <a href="withdrawal-requests.php" class="menu-item">
                    <i class="fas fa-money-bill-wave"></i> Withdrawal Requests
                    <?php if ($pending_withdrawals > 0): ?>
                        <span class="notification-badge"><?php echo $pending_withdrawals; ?></span>
                    <?php endif; ?>
                </a>
                <a href="fund-requests.php" class="menu-item">
                    <i class="fas fa-wallet"></i> Fund Requests
                    <?php if ($pending_fund_requests > 0): ?>
                        <span class="notification-badge"><?php echo $pending_fund_requests; ?></span>
                    <?php endif; ?>
                </a>
                <a href="consultations.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i> Consultations
                </a>
                <a href="transactions.php" class="menu-item">
                    <i class="fas fa-exchange-alt"></i> Transactions
                </a>
                <a href="categories.php" class="menu-item">
                    <i class="fas fa-tags"></i> Categories
                </a>
                <a href="notifications.php" class="menu-item">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="../config/logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-comments"></i> Expert Messages</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if (isset($_SESSION["full_name"])): ?>
                            <?php echo strtoupper(substr($_SESSION["full_name"], 0, 1)); ?>
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION["full_name"] ?? "Admin"); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            
            <div class="chat-container">
                <aside class="chat-sidebar">
                    <div class="chat-sidebar-header">
                        <h3><i class="fas fa-users"></i> Experts</h3>
                    </div>
                    <div class="chat-search">
                        <input type="text" class="search-input" placeholder="Search experts..." id="expertSearch">
                    </div>
                    <div class="chat-contacts">
                        <?php if (!empty($experts)): ?>
                            <?php foreach ($experts as $expert): ?>
                                <a href="expert-messages.php?expert_id=<?php echo $expert['id']; ?>" class="chat-contact <?php if ($selected_expert_id == $expert['id']) echo 'active'; ?>">
                                    <div class="contact-avatar">
                                        <?php if (!empty($expert['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($expert['profile_image']); ?>" alt="<?php echo htmlspecialchars($expert['full_name']); ?>">
                                        <?php else: ?>
                                            <?php
                                            $name_parts = explode(" ", $expert['full_name']);
                                            $initials = strtoupper(substr($name_parts[0], 0, 1) . (count($name_parts) > 1 ? substr(end($name_parts), 0, 1) : ""));
                                            echo htmlspecialchars($initials);
                                            ?>
                                        <?php endif; ?>
                                        <span class="contact-status status-<?php echo strtolower($expert['status']) == 'online' ? 'online' : 'offline'; ?>"></span>
                                    </div>
                                    <div class="contact-info">
                                        <h4 class="contact-name"><?php echo htmlspecialchars($expert['full_name']); ?></h4>
                                        <p class="contact-specialty"><?php echo htmlspecialchars($expert['specialty']); ?></p>
                                        <p class="contact-last-message"><?php echo !empty($expert['last_message']) ? htmlspecialchars(substr($expert['last_message'], 0, 30)) . (strlen($expert['last_message']) > 30 ? '...' : '') : 'No messages yet'; ?></p>
                                    </div>
                                    <div class="contact-meta">
                                        <span class="contact-time"><?php echo !empty($expert['last_message_time']) ? formatDate($expert['last_message_time']) : ''; ?></span>
                                        <?php if ($expert['unread_count'] > 0): ?>
                                            <span class="contact-unread"><?php echo $expert['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 15px; text-align: center;">No experts found.</div>
                        <?php endif; ?>
                    </div>
                </aside>
                
                <main class="chat-main">
                    <?php if ($selected_expert): ?>
                        <div class="chat-header">
                            <div class="chat-header-info">
                                <div class="chat-header-avatar">
                                    <?php if (!empty($selected_expert['profile_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($selected_expert['profile_image']); ?>" alt="<?php echo htmlspecialchars($selected_expert['full_name']); ?>">
                                    <?php else: ?>
                                        <?php
                                        $name_parts = explode(" ", $selected_expert['full_name']);
                                        $initials = strtoupper(substr($name_parts[0], 0, 1) . (count($name_parts) > 1 ? substr(end($name_parts), 0, 1) : ""));
                                        echo htmlspecialchars($initials);
                                        ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="chat-header-name"><?php echo htmlspecialchars($selected_expert['full_name']); ?></div>
                                    <div class="chat-header-status">
                                        <?php echo strtolower($selected_expert['status']) == 'online' ? '<span style="color: var(--success-color);"><i class="fas fa-circle"></i> Online</span>' : '<span style="color: var(--secondary-color);"><i class="far fa-circle"></i> Offline</span>'; ?>
                                        • <?php echo htmlspecialchars($selected_expert['specialty']); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($messages)): ?>
                                <div class="no-messages">
                                    <i class="fas fa-comments"></i>
                                    <h3>No messages yet</h3>
                                    <p>Start the conversation by sending a message to this expert.</p>
                                </div>
                            <?php else: ?>
                                <?php 
                                $currentDate = '';
                                $user_id = $_SESSION["user_id"];
                                foreach ($messages as $message): 
                                    $messageDate = date('Y-m-d', strtotime($message['created_at']));
                                    if ($currentDate != $messageDate):
                                        $currentDate = $messageDate;
                                        $formattedDate = date('F j, Y', strtotime($message['created_at']));
                                ?>
                                    <div class="message-date"><?php echo $formattedDate; ?></div>
                                <?php endif; ?>
                                
                                <div class="message <?php echo ($message['sender_id'] == $selected_expert_id) ? 'message-incoming' : 'message-outgoing'; ?>" data-id="<?php echo $message['id']; ?>">
                                    <div class="message-content">
                                        <?php if ($message['message_type'] == 'image'): ?>
                                            <img src="<?php echo htmlspecialchars($message['file_path']); ?>" alt="Image" class="message-image" onclick="window.open('<?php echo htmlspecialchars($message['file_path']); ?>')">
                                            <?php if (!empty($message['message'])): ?>
                                                <div class="message-caption"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
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
                                            <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="message-time">
                                        <?php echo date('g:i a', strtotime($message['created_at'])); ?>
                                        <?php if ($message['sender_id'] == $user_id): ?>
                                            <i class="fas <?php echo $message['is_read'] ? 'fa-check-double' : 'fa-check'; ?>" title="<?php echo $message['is_read'] ? 'Read' : 'Sent'; ?>"></i>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <div class="chat-input">
                            <form class="chat-form" id="messageForm" method="post" action="expert-messages.php?expert_id=<?php echo $selected_expert_id; ?>">
                                <div class="chat-input-container">
                                    <textarea class="chat-input-field" name="message" id="messageInput" placeholder="Type a message..." required></textarea>
                                    <div class="chat-input-actions">
                                        <label for="fileInput" class="file-upload-btn" title="Attach File">
                                            <i class="fas fa-paperclip"></i>
                                            <input type="file" id="fileInput" style="display: none;">
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" name="send_message" class="chat-send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                            <div id="filePreviewContainer" style="display: none;"></div>
                        </div>
                    <?php else: ?>
                        <div class="no-expert-selected">
                            <i class="fas fa-user-circle"></i>
                            <h2>Select an Expert</h2>
                            <p>Choose an expert from the list to start a conversation.</p>
                        </div>
                    <?php endif; ?>
                </main>
            </div>
        </div>
    </div>

    <script>
        // Auto-resize textarea
        const textarea = document.querySelector('.chat-input-field');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight < 120 ? this.scrollHeight : 120) + 'px';
            });
        }
        
        // Scroll to bottom of chat
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }
        
        // Expert search functionality
        const expertSearch = document.getElementById('expertSearch');
        if (expertSearch) {
            expertSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const contacts = document.querySelectorAll('.chat-contact');
                
                contacts.forEach(function(contact) {
                    const name = contact.querySelector('.contact-name').textContent.toLowerCase();
                    const specialty = contact.querySelector('.contact-specialty').textContent.toLowerCase();
                    
                    if (name.includes(searchTerm) || specialty.includes(searchTerm)) {
                        contact.style.display = 'flex';
                    } else {
                        contact.style.display = 'none';
                    }
                });
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
                    
                    // Create file preview
                    filePreviewContainer.innerHTML = `
                        <div class="file-preview">
                            <i class="fas fa-file"></i>
                            <div class="file-preview-info">
                                <div class="file-preview-name">${selectedFile.name}</div>
                                <div class="file-preview-size">${formatFileSize(selectedFile.size)}</div>
                            </div>
                            <i class="fas fa-times file-preview-remove" onclick="removeFilePreview()"></i>
                        </div>
                    `;
                    filePreviewContainer.style.display = 'block';
                    
                    // Submit file
                    uploadFile();
                }
            });
        }
        
        // Function to remove file preview
        function removeFilePreview() {
            fileInput.value = '';
            selectedFile = null;
            filePreviewContainer.innerHTML = '';
            filePreviewContainer.style.display = 'none';
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
        
        // Function to upload file
        function uploadFile() {
            if (!selectedFile) return;
            
            const expertId = <?php echo $selected_expert_id ?: 0; ?>;
            if (expertId === 0) {
                alert('Please select an expert to send a file.');
                removeFilePreview();
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('expert_id', expertId);
            formData.append('file', selectedFile);
            
            fetch('expert-messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Clear file input and preview
                    removeFilePreview();
                    
                    // Reload page to show new message
                    window.location.reload();
                } else {
                    alert('Error sending file: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while sending the file.');
            });
        }
        
        // Poll for new messages
        let lastMessageId = <?php echo !empty($messages) ? end($messages)['id'] : 0; ?>;
        const expertId = <?php echo $selected_expert_id ?: 0; ?>;
        
        if (expertId > 0) {
            setInterval(function() {
                fetch(`expert-messages.php?action=get_new_messages&expert_id=${expertId}&last_id=${lastMessageId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.length > 0) {
                            // Update last message ID
                            lastMessageId = data[data.length - 1].id;
                            
                            // Reload page to show new messages
                            window.location.reload();
                        }
                    })
                    .catch(error => console.error('Error fetching new messages:', error));
            }, 500); // Check every 5 seconds
        }
        
        // Mobile sidebar toggle
        const toggleSidebar = () => {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('expanded');
        };
        
        // Create mobile toggle button if needed
        if (window.innerWidth <= 768) {
            const sidebar = document.querySelector('.sidebar');
            const toggleBtn = document.createElement('button');
            toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
            toggleBtn.classList.add('sidebar-toggle');
            toggleBtn.style.position = 'absolute';
            toggleBtn.style.top = '1rem';
            toggleBtn.style.right = '1rem';
            toggleBtn.style.background = 'var(--primary-gradient)';
            toggleBtn.style.color = 'white';
            toggleBtn.style.border = 'none';
            toggleBtn.style.borderRadius = 'var(--radius)';
            toggleBtn.style.padding = '0.5rem';
            toggleBtn.style.cursor = 'pointer';
            toggleBtn.style.zIndex = '1000';
            toggleBtn.style.boxShadow = 'var(--shadow)';
            
            toggleBtn.addEventListener('click', toggleSidebar);
            sidebar.appendChild(toggleBtn);
        }
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 5000);
        });
    </script>
</body>
</html>
