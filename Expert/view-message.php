<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';
// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in, redirect to login page
    header("Location: ../config/logout.php");
    exit();
}
// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];
$success_message = "";
$error_message = "";
$message_data = null;
$responses = [];
$reply_text = "";

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get user data
$sql = "SELECT u.*, up.phone, up.address, up.profile_image 
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get logo from settings
$logo_sql = "SELECT setting_value FROM settings WHERE setting_key = 'site_logo'";
$logo_result = $conn->query($logo_sql);
$logo_path = "../imgs/logo.png"; // Default logo path
if ($logo_row = $logo_result->fetch_assoc()) {
    if (!empty($logo_row['setting_value'])) {
        $logo_path = $logo_row['setting_value'];
    }
}

// Get site name from settings
$site_name_sql = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_name_result = $conn->query($site_name_sql);
$site_name = "Consult Pro"; // Default site name
if ($site_name_row = $site_name_result->fetch_assoc()) {
    if (!empty($site_name_row['setting_value'])) {
        $site_name = $site_name_row['setting_value'];
    }
}

// Get site description from settings
$site_desc_sql = "SELECT setting_value FROM settings WHERE setting_key = 'site_description'";
$site_desc_result = $conn->query($site_desc_sql);
$site_description = "Consult Pro connects clients with expert consultants across various fields. Our platform makes it easy to find, book, and conduct consultations online."; // Default description
if ($site_desc_row = $site_desc_result->fetch_assoc()) {
    if (!empty($site_desc_row['setting_value'])) {
        $site_description = $site_desc_row['setting_value'];
    }
}

// Get contact information from settings
$contact_email_sql = "SELECT setting_value FROM settings WHERE setting_key = 'contact_email'";
$contact_email_result = $conn->query($contact_email_sql);
$contact_email = "support@consultpro.com"; // Default contact email
if ($contact_email_row = $contact_email_result->fetch_assoc()) {
    if (!empty($contact_email_row['setting_value'])) {
        $contact_email = $contact_email_row['setting_value'];
    }
}

$contact_phone_sql = "SELECT setting_value FROM settings WHERE setting_key = 'contact_phone'";
$contact_phone_result = $conn->query($contact_phone_sql);
$contact_phone = "+1 (555) 123-4567"; // Default contact phone
if ($contact_phone_row = $contact_phone_result->fetch_assoc()) {
    if (!empty($contact_phone_row['setting_value'])) {
        $contact_phone = $contact_phone_row['setting_value'];
    }
}

$contact_address_sql = "SELECT setting_value FROM settings WHERE setting_key = 'contact_address'";
$contact_address_result = $conn->query($contact_address_sql);
$contact_address = "123 Consultation St, Expert City"; // Default contact address
if ($contact_address_row = $contact_address_result->fetch_assoc()) {
    if (!empty($contact_address_row['setting_value'])) {
        $contact_address = $contact_address_row['setting_value'];
    }
}

// Check for unread messages
$unread_messages_count = 0;
$unread_sql = "SELECT COUNT(*) as count FROM support_responses sr 
               JOIN support_messages sm ON sr.message_id = sm.id 
               WHERE sm.user_id = ? AND sr.is_read = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
if ($unread_row = $unread_result->fetch_assoc()) {
    $unread_messages_count = $unread_row['count'];
}

// Check if message ID is provided
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $message_id = sanitize_input($_GET['id']);
    
    // Get message details
    $message_sql = "SELECT sm.*, 
                   u.full_name as user_name,
                   up.profile_image as user_image
                   FROM support_messages sm
                   JOIN users u ON sm.user_id = u.id
                   LEFT JOIN user_profiles up ON u.id = up.user_id
                   WHERE sm.id = ? AND sm.user_id = ?";
    
    $message_stmt = $conn->prepare($message_sql);
    $message_stmt->bind_param("ii", $message_id, $user_id);
    $message_stmt->execute();
    $message_result = $message_stmt->get_result();
    
    if ($message_result->num_rows > 0) {
        $message_data = $message_result->fetch_assoc();
        
        // Get responses
        $responses_sql = "SELECT sr.*, 
                         u.full_name as admin_name,
                         u.role as admin_role,
                         up.profile_image as admin_image
                         FROM support_responses sr
                         JOIN users u ON sr.admin_id = u.id
                         LEFT JOIN user_profiles up ON u.id = up.user_id
                         WHERE sr.message_id = ?
                         ORDER BY sr.created_at ASC";
        
        $responses_stmt = $conn->prepare($responses_sql);
        $responses_stmt->bind_param("i", $message_id);
        $responses_stmt->execute();
        $responses_result = $responses_stmt->get_result();
        
        while ($row = $responses_result->fetch_assoc()) {
            $responses[] = $row;
        }
        
        // Check if form is submitted (user reply)
        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["reply"])) {
            // Only allow replies if the message is not closed
            if ($message_data['status'] != 'closed') {
                $reply_text = sanitize_input($_POST["reply"]);
                
                if (empty($reply_text)) {
                    $error_message = "Reply message cannot be empty.";
                } else {
                    // Insert user reply
                    $reply_sql = "INSERT INTO support_message_replies (message_id, user_id, reply_text, created_at) 
                                 VALUES (?, ?, ?, NOW())";
                    $reply_stmt = $conn->prepare($reply_sql);
                    $reply_stmt->bind_param("iis", $message_id, $user_id, $reply_text);
                    
                    if ($reply_stmt->execute()) {
                        // Update message status to pending if it was resolved
                        if ($message_data['status'] == 'resolved') {
                            $update_sql = "UPDATE support_messages SET status = 'pending', updated_at = NOW() WHERE id = ?";
                            $update_stmt = $conn->prepare($update_sql);
                            $update_stmt->bind_param("i", $message_id);
                            $update_stmt->execute();
                            $message_data['status'] = 'pending'; // Update local status
                        }
                        
                        $success_message = "Your reply has been sent successfully.";
                        $reply_text = ""; // Clear form
                        
                        // Fetch the newly added reply
                        $new_reply_sql = "SELECT smr.*, 
                                         u.full_name as user_name,
                                         up.profile_image as user_image
                                         FROM support_message_replies smr
                                         JOIN users u ON smr.user_id = u.id
                                         LEFT JOIN user_profiles up ON u.id = up.user_id
                                         WHERE smr.message_id = ? AND smr.user_id = ?
                                         ORDER BY smr.created_at DESC
                                         LIMIT 1";
                        
                        $new_reply_stmt = $conn->prepare($new_reply_sql);
                        $new_reply_stmt->bind_param("ii", $message_id, $user_id);
                        $new_reply_stmt->execute();
                        $new_reply_result = $new_reply_stmt->get_result();
                        
                        if ($new_reply_result->num_rows > 0) {
                            $new_reply = $new_reply_result->fetch_assoc();
                            $new_reply['is_user'] = true;
                            $responses[] = $new_reply;
                        }
                    } else {
                        $error_message = "Failed to send reply. Please try again.";
                    }
                }
            } else {
                $error_message = "This conversation is closed. You cannot add more replies.";
            }
        }
        
        // Get user replies
        $user_replies_sql = "SELECT smr.*, 
                            u.full_name as user_name,
                            up.profile_image as user_image
                            FROM support_message_replies smr
                            JOIN users u ON smr.user_id = u.id
                            LEFT JOIN user_profiles up ON u.id = up.user_id
                            WHERE smr.message_id = ? AND smr.user_id = ?
                            ORDER BY smr.created_at ASC";
        
        $user_replies_stmt = $conn->prepare($user_replies_sql);
        $user_replies_stmt->bind_param("ii", $message_id, $user_id);
        $user_replies_stmt->execute();
        $user_replies_result = $user_replies_stmt->get_result();
        
        while ($row = $user_replies_result->fetch_assoc()) {
            $row['is_user'] = true;
            $responses[] = $row;
        }
        
        // Sort all responses by created_at
        usort($responses, function($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });
        
    } else {
        $error_message = "Message not found or you don't have permission to view it.";
    }
} else {
    $error_message = "Message ID is required.";
}

// Format date function
function formatDate($date) {
    return date('F j, Y \a\t g:i a', strtotime($date));
}
// Get site settings
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Message - <?php echo htmlspecialchars($site_name); ?></title>
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

        /* Responsive navbar adjustments */
        @media (max-width: 991.98px) {
            .navbar-nav {
                margin-top: 1rem;
                gap: 0.2rem;
            }
            
            .navbar-light .navbar-nav .nav-link {
                flex-direction: row;
                justify-content: flex-start;
                padding: 0.8rem 1rem;
            }
            
            .navbar-light .navbar-nav .nav-link i {
                margin-right: 10px;
                width: 20px;
                text-align: center;
            }
            
            .nav-user-section {
                margin-left: 0;
                border-left: none;
                padding-left: 0;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(226, 232, 240, 0.8);
            }
        }
        
        
        /* Main Content Styles */
        .main-container {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-top: 2rem;
            margin-bottom: 2rem;
            padding: 2rem;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
        }
        
        .main-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid;
            border-image: linear-gradient(to right, var(--primary-color), var(--accent-color)) 1;
            display: inline-block;
        }
        
        .message-header {
            background: rgba(248, 250, 252, 0.8);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            border: 1px solid rgba(226, 232, 240, 0.7);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .message-header:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }
        
        .message-subject {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
        }
        
        .message-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .message-info {
            display: flex;
            align-items: center;
        }
        
        .message-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 1rem;
            border: 2px solid white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .message-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-light), var(--accent-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            margin-right: 1rem;
            border: 2px solid white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .message-user {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .message-date {
            font-size: 0.875rem;
            color: var(--text-muted);
        }
        
        .status-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .status-pending {
            background: linear-gradient(to right, var(--warning-color), #fbbf24);
            color: white;
        }
        
        .status-in-progress {
            background: linear-gradient(to right, var(--info-color), #60a5fa);
            color: white;
        }
        
        .status-resolved {
            background: linear-gradient(to right, var(--success-color), #34d399);
            color: white;
        }
        
        .status-closed {
            background: linear-gradient(to right, var(--text-muted), #94a3b8);
            color: white;
        }
        
        .contact-type-badge {
            padding: 0.35rem 0.65rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 0.5rem;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }
        
        .contact-type-support {
            background: linear-gradient(to right, var(--info-color), #60a5fa);
            color: white;
        }
        
        .contact-type-billing {
            background: linear-gradient(to right, var(--secondary-color), var(--secondary-light));
            color: white;
        }
        
        .contact-type-technical {
            background: linear-gradient(to right, var(--text-muted), #94a3b8);
            color: white;
        }
        
        .contact-type-feedback {
            background: linear-gradient(to right, var(--success-color), #34d399);
            color: white;
        }
        
        .contact-type-other {
            background: linear-gradient(to right, var(--accent-color), var(--accent-light));
            color: white;
        }
        
        .message-content {
            margin-top: 1rem;
            white-space: pre-line;
            line-height: 1.7;
        }
        
        .conversation {
            margin-bottom: 2rem;
        }
        
        .conversation-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            position: relative;
            display: inline-block;
        }
        
        .conversation-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 50px;
            height: 2px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            transition: width 0.3s ease;
        }
        
        .conversation-title:hover::after {
            width: 100%;
        }
        
        .message-bubble {
            display: flex;
            margin-bottom: 1.5rem;
            animation: fadeIn 0.5s ease;
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
        
        .message-bubble.admin {
            flex-direction: row;
        }
        
        .message-bubble.user {
            flex-direction: row-reverse;
        }
        
        .bubble-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .bubble-avatar-placeholder {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-light), var(--accent-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: white;
            border: 2px solid white;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }
        
        .bubble-content {
            max-width: 70%;
            padding: 1rem;
            border-radius: 15px;
            position: relative;
            margin: 0 1rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
        }
        
        .bubble-content:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
        }
        
        .message-bubble.admin .bubble-content {
            background: rgba(248, 250, 252, 0.9);
            border: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .message-bubble.user .bubble-content {
            background: linear-gradient(to right bottom, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            border: 1px solid rgba(99, 102, 241, 0.2);
        }
        
        .bubble-sender {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }
        
        .bubble-text {
            margin-bottom: 0.5rem;
            white-space: pre-line;
            line-height: 1.7;
        }
        
        .bubble-time {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-align: right;
        }
        
        .reply-form {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .reply-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--primary-color);
            position: relative;
            display: inline-block;
        }
        
        .reply-title::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 40px;
            height: 2px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            transition: width 0.3s ease;
        }
        
        .reply-title:hover::after {
            width: 100%;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
            background-color: white;
        }
        
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
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
            background: linear-gradient(to right, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.2));
            transition: all 0.4s ease;
            z-index: -1;
        }
        
        .btn:hover::before {
            left: 0;
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border-color: transparent;
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
        
        .alert {
            border-radius: 15px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .alert-success::before {
            background: var(--success-color);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .alert-danger::before {
            background: var(--danger-color);
        }
        
        .closed-message {
            background: rgba(248, 250, 252, 0.8);
            border: 1px solid rgba(226, 232, 240, 0.7);
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            margin-top: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
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
            font-size: 0.95rem;
            color: var(--text-muted);
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: 5px;
            right: 5px;
            background: linear-gradient(45deg, var(--danger-color), #f87171);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(239, 68, 68, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(239, 68, 68, 0);
            }
        }
        
        .nav-link-with-badge {
            position: relative;
            padding-right: 1.5rem !important;
        }
        
        /* Responsive Styles */
        @media (max-width: 767.98px) {
            .message-meta {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .message-meta > div:first-child {
                margin-bottom: 0.5rem;
            }
            
            .bubble-content {
                max-width: 85%;
            }
            
            .main-container {
                padding: 1.5rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .message-subject {
                font-size: 1.3rem;
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
        <div class="background-shapes">
            <div class="shape shape-1" data-speed="1.5"></div>
            <div class="shape shape-2" data-speed="1"></div>
            <div class="shape shape-3" data-speed="2"></div>
            <div class="shape shape-4" data-speed="1.2"></div>
            <div class="shape shape-5" data-speed="1.8"></div>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="#">
            <?php if (!empty($settings['site_image'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($settings['site_image']); ?>" alt="<?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?> Logo" class="me-2" style="height: 40px;">
            <?php else: ?>
                <div class="logo-text">
                    <span class="fw-bold">C</span>
                    <span class="logo-subtitle">CONSULT PRO</span>
                </div>
            <?php endif; ?>
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link" href="home-profile.php">
                        <i class="fas fa-home"></i>
                        <span>Home</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expert-profile.php">
                        <i class="fas fa-user"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expert-consultations.php">
                        <i class="fas fa-laptop-code"></i>
                        <span>Consultations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expert-earnings.php">
                        <i class="fas fa-chart-line"></i>
                        <span>Earnings</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="expert-avis.php">
                        <i class="fas fa-star"></i>
                        <span>Reviews</span>
                    </a>
                </li>
                <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="expertsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-users"></i>
                            <span>Community</span>
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="expertsDropdown">
                            <li><a class="dropdown-item" href="expert-experts.php"><i class="fas fa-search"></i> Find Experts</a></li>
                            <li><a class="dropdown-item" href="expert-discussions.php"><i class="fas fa-comments"></i> Forums</a></li>
                        </ul>
                    </li>
                <li class="nav-item">
                    <a class="nav-link" href="expert-contact.php">
                        <i class="fas fa-envelope"></i>
                        <span>Contact</span>
                    </a>
                </li>
            </ul>
            
            <div class="nav-user-section">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <?php if (!empty($user['profile_image'])): ?>
                            <img src="../uploads/<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                        <?php else: ?>
                            <i class="fas fa-user-circle me-2"></i>
                        <?php endif; ?>
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
    <div class="container">
        <div class="main-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="page-title mb-0">View Message</h1>
                <a href="expert-contact.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-1"></i> Back to Messages
                </a>
            </div>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($message_data): ?>
                <!-- Message Header -->
                <div class="message-header">
                    <div class="message-subject"><?php echo htmlspecialchars($message_data['subject']); ?></div>
                    
                    <div class="message-meta">
                        <div class="message-info">
                            <?php if (!empty($message_data['user_image'])): ?>
                                <img src="<?php echo htmlspecialchars($message_data['user_image']); ?>" alt="<?php echo htmlspecialchars($message_data['user_name']); ?>" class="message-avatar">
                            <?php else: ?>
                                <div class="message-avatar-placeholder">
                                    <i class="fas fa-user"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div>
                                <div class="message-user"><?php echo htmlspecialchars($message_data['user_name']); ?></div>
                                <div class="message-date"><?php echo formatDate($message_data['created_at']); ?></div>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <span class="contact-type-badge contact-type-<?php echo htmlspecialchars($message_data['contact_type']); ?>">
                                <?php 
                                $contact_type_label = "Support";
                                switch($message_data['contact_type']) {
                                    case 'technical':
                                        $contact_type_label = "Technical";
                                        break;
                                    case 'billing':
                                        $contact_type_label = "Billing";
                                        break;
                                    case 'feedback':
                                        $contact_type_label = "Feedback";
                                        break;
                                    case 'other':
                                        $contact_type_label = "Other";
                                        break;
                                }
                                echo $contact_type_label;
                                ?>
                            </span>
                            
                            <span class="status-badge status-<?php echo strtolower($message_data['status']); ?>">
                                <?php echo ucfirst($message_data['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="message-content">
                        <?php echo nl2br(htmlspecialchars($message_data['message'])); ?>
                    </div>
                </div>
                
                <!-- Conversation -->
                <div class="conversation">
                    <h2 class="conversation-title">Conversation</h2>
                    
                    <?php if (empty($responses)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="far fa-comment-dots fa-3x mb-3"></i>
                            <p>No responses yet. Our team will respond to your message soon.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($responses as $response): ?>
                            <?php $is_user = isset($response['is_user']) && $response['is_user']; ?>
                            <div class="message-bubble <?php echo $is_user ? 'user' : 'admin'; ?>">
                                <?php if ($is_user): ?>
                                    <?php if (!empty($response['user_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($response['user_image']); ?>" alt="<?php echo htmlspecialchars($response['user_name']); ?>" class="bubble-avatar">
                                    <?php else: ?>
                                        <div class="bubble-avatar-placeholder">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <?php if (!empty($response['admin_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($response['admin_image']); ?>" alt="<?php echo htmlspecialchars($response['admin_name']); ?>" class="bubble-avatar">
                                    <?php else: ?>
                                        <div class="bubble-avatar-placeholder">
                                            <i class="fas fa-headset"></i>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <div class="bubble-content">
                                    <div class="bubble-sender">
                                        <?php if ($is_user): ?>
                                            <?php echo htmlspecialchars($response['user_name']); ?> (You)
                                        <?php else: ?>
                                            <?php echo htmlspecialchars($response['admin_name']); ?> 
                                            <span class="text-muted">(<?php echo htmlspecialchars(ucfirst($response['admin_role'])); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="bubble-text">
                                        <?php echo nl2br(htmlspecialchars($is_user ? $response['reply_text'] : $response['response'])); ?>
                                    </div>
                                    <div class="bubble-time">
                                        <?php echo formatDate($response['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Reply Form -->
                <?php if ($message_data['status'] != 'closed'): ?>
                    <div class="reply-form">
                        <h3 class="reply-title">Add Reply</h3>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $message_id); ?>">
                            <div class="mb-3">
                                <textarea class="form-control" id="reply" name="reply" rows="4" placeholder="Type your reply here..." required><?php echo htmlspecialchars($reply_text); ?></textarea>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane me-2"></i> Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="closed-message">
                        <i class="fas fa-lock me-2"></i>
                        This conversation is closed. If you need further assistance, please create a new support request.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
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
        // Add subtle parallax effect to background elements
        if (window.innerWidth > 768) {
            document.addEventListener('mousemove', (e) => {
                const moveX = (e.clientX - window.innerWidth / 2) / 30;
                const moveY = (e.clientY - window.innerHeight / 2) / 30;
                
                document.querySelectorAll('.shape').forEach((shape) => {
                    const speed = parseFloat(shape.getAttribute('data-speed') || 1);
                    shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
                });
            });
        }
        
        // Check for new messages periodically
        function checkNewMessages() {
            fetch('check-messages.php')
                .then(response => response.json())
                .then(data => {
                    if (data.unread_count > 0) {
                        // Update the notification badge if it exists
                        const contactLink = document.querySelector('.nav-link-with-badge');
                        if (contactLink) {
                            // Remove existing badge if any
                            const existingBadge = contactLink.querySelector('.notification-badge');
                            if (existingBadge) {
                                existingBadge.remove();
                            }
                            
                            // Add new badge
                            const badge = document.createElement('span');
                            badge.className = 'notification-badge';
                            badge.textContent = data.unread_count;
                            contactLink.appendChild(badge);
                        }
                    }
                })
                .catch(error => console.error('Error checking messages:', error));
        }
        
        // Check for new messages every 30 seconds
        setInterval(checkNewMessages, 30000);
    </script>
</body>
</html>
