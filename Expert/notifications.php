<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"])) {
    // User is not logged in, redirect to login page
    header("Location: ../config/logout.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$user_role = $_SESSION["user_role"] ?? 'client';
$notifications = [];
$success_message = "";
$error_message = "";
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$total_notifications = 0;

// Handle AJAX requests
if (isset($_POST['action'])) {
    $response = ['success' => false, 'message' => 'Unknown action'];
    
    if ($_POST['action'] === 'mark_read') {
        if (isset($_POST['notification_id'])) {
            $notification_id = (int)$_POST['notification_id'];
            
            // Mark single notification as read
            $mark_read_sql = "UPDATE expert_notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
            $mark_read_stmt = $conn->prepare($mark_read_sql);
            $mark_read_stmt->bind_param("ii", $notification_id, $user_id);
            
            if ($mark_read_stmt->execute()) {
                $response = ['success' => true, 'message' => 'Notification marked as read'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to mark notification as read'];
            }
        } elseif (isset($_POST['mark_all'])) {
            // Mark all notifications as read
            $mark_all_sql = "UPDATE expert_notifications SET is_read = 1 WHERE user_id = ?";
            $mark_all_stmt = $conn->prepare($mark_all_sql);
            $mark_all_stmt->bind_param("i", $user_id);
            
            if ($mark_all_stmt->execute()) {
                $response = ['success' => true, 'message' => 'All notifications marked as read'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to mark all notifications as read'];
            }
        }
    } elseif ($_POST['action'] === 'delete') {
        if (isset($_POST['notification_id'])) {
            $notification_id = (int)$_POST['notification_id'];
            
            // Delete single notification
            $delete_sql = "DELETE FROM expert_notifications WHERE id = ? AND user_id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("ii", $notification_id, $user_id);
            
            if ($delete_stmt->execute()) {
                $response = ['success' => true, 'message' => 'Notification deleted'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to delete notification'];
            }
        } elseif (isset($_POST['delete_all'])) {
            // Delete all notifications
            $delete_all_sql = "DELETE FROM expert_notifications WHERE user_id = ?";
            $delete_all_stmt = $conn->prepare($delete_all_sql);
            $delete_all_stmt->bind_param("i", $user_id);
            
            if ($delete_all_stmt->execute()) {
                $response = ['success' => true, 'message' => 'All notifications deleted'];
            } else {
                $response = ['success' => false, 'message' => 'Failed to delete all notifications'];
            }
        }
    }
    
    // Return JSON response for AJAX requests
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Build the SQL query based on filter
$where_clause = "WHERE user_id = ?";
if ($filter === 'unread') {
    $where_clause .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $where_clause .= " AND is_read = 1";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM expert_notifications $where_clause";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_notifications = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_notifications / $per_page);

// Get notifications with pagination
$notifications_sql = "SELECT * FROM expert_notifications $where_clause ORDER BY created_at DESC LIMIT ? OFFSET ?";
$notifications_stmt = $conn->prepare($notifications_sql);
$notifications_stmt->bind_param("iii", $user_id, $per_page, $offset);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();

while ($row = $notifications_result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get unread count for badge
$unread_sql = "SELECT COUNT(*) as unread FROM expert_notifications WHERE user_id = ? AND is_read = 0";
$unread_stmt = $conn->prepare($unread_sql);
$unread_stmt->bind_param("i", $user_id);
$unread_stmt->execute();
$unread_result = $unread_stmt->get_result();
$unread_count = $unread_result->fetch_assoc()['unread'];

// Get user data
$user_sql = "SELECT u.*, up.profile_image 
             FROM users u 
             LEFT JOIN user_profiles up ON u.id = up.user_id 
             WHERE u.id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user = $user_result->fetch_assoc();

// Get site settings
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Function to get notification icon based on type
function getNotificationIcon($type) {
    switch ($type) {
        case 'message':
            return '<i class="fas fa-envelope"></i>';
        case 'consultation_request':
            return '<i class="fas fa-calendar-check"></i>';
        case 'consultation_accepted':
            return '<i class="fas fa-check-circle"></i>';
        case 'consultation_rejected':
            return '<i class="fas fa-times-circle"></i>';
        case 'consultation_completed':
            return '<i class="fas fa-flag-checkered"></i>';
        case 'payment_received':
            return '<i class="fas fa-money-bill-wave"></i>';
        case 'payment_sent':
            return '<i class="fas fa-paper-plane"></i>';
        case 'review_received':
            return '<i class="fas fa-star"></i>';
        case 'profile_update':
            return '<i class="fas fa-user-edit"></i>';
        case 'system':
            return '<i class="fas fa-cog"></i>';
        default:
            return '<i class="fas fa-bell"></i>';
    }
}

// Function to format date
function formatDate($date) {
    $timestamp = strtotime($date);
    $now = time();
    $diff = $now - $timestamp;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $timestamp);
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
// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
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
        
      
        
        /* Notification Badge Styles */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background-color: var(--danger-color);
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
        
        /* Main Content Styles */
        .main-container {
            padding: 2rem 0;
            position: relative;
            z-index: 1;
        }
        
        /* Notifications Container */
        .notifications-container {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .notifications-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.12);
        }
        
        .notifications-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            padding: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .notifications-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
        }
        
        .notifications-header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
        }
        
        .notifications-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .notifications-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .notifications-content {
            padding: 2rem;
        }
        
        /* Filter and Actions Bar */
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .filter-options {
            display: flex;
            gap: 0.5rem;
        }
        
        .filter-btn {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        .filter-btn:hover {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        /* Notification Item Styles */
        .notification-list {
            max-height: 600px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }
        
        .notification-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .notification-list::-webkit-scrollbar-track {
            background: rgba(226, 232, 240, 0.5);
            border-radius: 10px;
        }
        
        .notification-list::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.3);
            border-radius: 10px;
        }
        
        .notification-list::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.5);
        }
        
        .notification-item {
            padding: 1.25rem;
            border-radius: 15px;
            margin-bottom: 1rem;
            background-color: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }
        
        .notification-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        }
        
        .notification-item.unread {
            background-color: rgba(99, 102, 241, 0.05);
            border-left: 4px solid var(--primary-color);
        }
        
        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .notification-content {
            flex-grow: 1;
        }
        
        .notification-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
            color: var(--dark-color);
        }
        
        .notification-message {
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        .notification-time {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .notification-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0.25rem;
            border-radius: 5px;
        }
        
        .notification-btn:hover {
            color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.1);
        }
        
        .notification-btn.delete:hover {
            color: var(--danger-color);
            background-color: rgba(239, 68, 68, 0.1);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
        }
        
        .empty-icon {
            font-size: 4rem;
            color: var(--text-muted);
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .empty-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .empty-message {
            color: var(--text-muted);
            max-width: 500px;
            margin: 0 auto 1.5rem;
        }
        
        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
        }
        
        .page-item .page-link {
            border-radius: 10px;
            color: var(--text-color);
            border: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .page-item .page-link:hover {
            background-color: rgba(99, 102, 241, 0.1);
            color: var(--primary-color);
            transform: translateY(-2px);
        }
        
        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .page-item.disabled .page-link {
            color: var(--text-muted);
            pointer-events: none;
            background-color: rgba(226, 232, 240, 0.5);
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
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 1rem;
            margin-bottom: 1rem;
            min-width: 300px;
            max-width: 400px;
            animation: slideIn 0.3s ease forwards;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .toast-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .toast-title {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .toast-close {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }
        
        .toast-close:hover {
            color: var(--danger-color);
        }
        
        .toast-body {
            color: var(--text-color);
        }
        
        .toast.success {
            border-left: 4px solid var(--success-color);
        }
        
        .toast.error {
            border-left: 4px solid var(--danger-color);
        }
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .notifications-title {
                font-size: 1.8rem;
            }
            
            .notifications-subtitle {
                font-size: 1rem;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .filter-options, .action-buttons {
                width: 100%;
                justify-content: space-between;
            }
        }
        
        @media (max-width: 767.98px) {
            .notifications-header {
                padding: 1.75rem;
            }
            
            .notifications-content {
                padding: 1.5rem;
            }
            
            .notifications-title {
                font-size: 1.6rem;
            }
            
            .notification-item {
                padding: 1rem;
                flex-direction: column;
            }
            
            .notification-icon {
                width: 40px;
                height: 40px;
                margin-bottom: 0.5rem;
                align-self: flex-start;
            }
            
            .notification-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .notification-actions {
                align-self: flex-end;
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
    </style>
</head>
<body>
    <!-- Background Elements -->
    <div class="background-container">
        <div class="background-gradient"></div>
        <div class="background-pattern"></div>
        <div class="background-grid"></div>
        <div class="animated-gradient"></div>
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
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown"> <li><a class="dropdown-item" href="notifications.php"><i class="fa-solid fa-bell"></i> Notifications
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
            <div class="alert alert-success fade-in">
                <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger fade-in">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="notifications-container fade-in glow">
            <div class="notifications-header">
                <h1 class="notifications-title">Notifications</h1>
                <p class="notifications-subtitle">Stay updated with your latest activities and interactions</p>
            </div>
            
            <div class="notifications-content">
                <div class="filter-bar">
                    <div class="filter-options">
                        <a href="?filter=all" class="filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                            <i class="fas fa-list-ul me-2"></i> All
                        </a>
                        <a href="?filter=unread" class="filter-btn <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope me-2"></i> Unread
                            <?php if ($unread_count > 0): ?>
                                <span class="badge bg-danger ms-1"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="?filter=read" class="filter-btn <?php echo $filter === 'read' ? 'active' : ''; ?>">
                            <i class="fas fa-envelope-open me-2"></i> Read
                        </a>
                    </div>
                    
                    <div class="action-buttons">
                        <?php if ($unread_count > 0): ?>
                            <button class="btn btn-outline-primary mark-all-read">
                                <i class="fas fa-check-double me-2"></i> Mark All as Read
                            </button>
                        <?php endif; ?>
                        <?php if ($total_notifications > 0): ?>
                            <button class="btn btn-outline-danger delete-all">
                                <i class="fas fa-trash-alt me-2"></i> Delete All
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="notification-list">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <i class="fas fa-bell-slash empty-icon"></i>
                            <h3 class="empty-title">No notifications found</h3>
                            <p class="empty-message">You don't have any notifications at the moment. Check back later for updates on your activities.</p>
                            <a href="<?php echo $user_role === 'expert' ? 'home-profile.php' : 'home.php'; ?>" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i> Go to Home
                            </a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>" data-id="<?php echo $notification['id']; ?>">
                                <div class="notification-icon">
                                    <?php echo getNotificationIcon($notification['notification_type']); ?>
                                </div>
                                <div class="notification-content">
                                    <h4 class="notification-title">
                                        <?php echo ucfirst(str_replace('_', ' ', $notification['notification_type'])); ?>
                                        <?php if (!$notification['is_read']): ?>
                                            <span class="badge bg-primary ms-2">New</span>
                                        <?php endif; ?>
                                    </h4>
                                    <p class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <div class="notification-meta">
                                        <div class="notification-time">
                                            <i class="far fa-clock"></i> <?php echo $notification['created_at']; ?>
                                        </div>
                                        <div class="notification-actions">
                                            <?php if (!$notification['is_read']): ?>
                                                <button class="notification-btn mark-read" data-id="<?php echo $notification['id']; ?>" title="Mark as Read">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($notification['related_id']): ?>
                                                <a href="#" class="notification-btn view-details" data-id="<?php echo $notification['related_id']; ?>" title="View Details">
                                                    <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button class="notification-btn delete" data-id="<?php echo $notification['id']; ?>" title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($total_pages > 1): ?>
                    <div class="pagination-container">
                        <ul class="pagination">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Toast Container for Notifications -->
    <div class="toast-container"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show toast notification
            function showToast(message, type = 'success') {
                const toastContainer = document.querySelector('.toast-container');
                const toast = document.createElement('div');
                toast.className = `toast ${type}`;
                toast.innerHTML = `
                    <div class="toast-header">
                        <span class="toast-title">${type === 'success' ? 'Success' : 'Error'}</span>
                        <button class="toast-close">&times;</button>
                    </div>
                    <div class="toast-body">${message}</div>
                `;
                
                toastContainer.appendChild(toast);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 5000);
                
                // Close button functionality
                toast.querySelector('.toast-close').addEventListener('click', function() {
                    toast.style.opacity = '0';
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                });
            }
            
            // Mark single notification as read
            document.querySelectorAll('.mark-read').forEach(button => {
                button.addEventListener('click', function() {
                    const notificationId = this.getAttribute('data-id');
                    const notificationItem = this.closest('.notification-item');
                    
                    fetch('notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=mark_read&notification_id=${notificationId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            notificationItem.classList.remove('unread');
                            this.remove();
                            
                            // Update unread count
                            const badge = document.querySelector('.notification-badge');
                            if (badge) {
                                const currentCount = parseInt(badge.textContent);
                                if (currentCount > 1) {
                                    badge.textContent = currentCount - 1;
                                } else {
                                    badge.remove();
                                }
                            }
                            
                            showToast(data.message);
                        } else {
                            showToast(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showToast('An error occurred. Please try again.', 'error');
                    });
                });
            });
            
            // Mark all notifications as read
            const markAllButton = document.querySelector('.mark-all-read');
            if (markAllButton) {
                markAllButton.addEventListener('click', function() {
                    fetch('notifications.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=mark_read&mark_all=1'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update UI
                            document.querySelectorAll('.notification-item.unread').forEach(item => {
                                item.classList.remove('unread');
                                const markReadBtn = item.querySelector('.mark-read');
                                if (markReadBtn) {
                                    markReadBtn.remove();
                                }
                            });
                            
                            // Remove badge
                            const badge = document.querySelector('.notification-badge');
                            if (badge) {
                                badge.remove();
                            }
                            
                            // Hide mark all button
                            this.style.display = 'none';
                            
                            showToast(data.message);
                        } else {
                            showToast(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        showToast('An error occurred. Please try again.', 'error');
                    });
                });
            }
            
            // Delete single notification
            document.querySelectorAll('.delete').forEach(button => {
                button.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete this notification?')) {
                        const notificationId = this.getAttribute('data-id');
                        const notificationItem = this.closest('.notification-item');
                        
                        fetch('notifications.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=delete&notification_id=${notificationId}`
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                notificationItem.style.opacity = '0';
                                setTimeout(() => {
                                    notificationItem.remove();
                                    
                                    // Check if there are no more notifications
                                    if (document.querySelectorAll('.notification-item').length === 0) {
                                        location.reload(); // Reload to show empty state
                                    }
                                }, 300);
                                
                                showToast(data.message);
                            } else {
                                showToast(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            showToast('An error occurred. Please try again.', 'error');
                        });
                    }
                });
            });
            
            // Delete all notifications
            const deleteAllButton = document.querySelector('.delete-all');
            if (deleteAllButton) {
                deleteAllButton.addEventListener('click', function() {
                    if (confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
                        fetch('notifications.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: 'action=delete&delete_all=1'
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                location.reload(); // Reload to show empty state
                            } else {
                                showToast(data.message, 'error');
                            }
                        })
                        .catch(error => {
                            showToast('An error occurred. Please try again.', 'error');
                        });
                    }
                });
            }
            
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
            
            document.querySelectorAll('.notification-item').forEach((el, index) => {
                el.classList.add(`delay-${(index % 4) + 1}`);
                observer.observe(el);
            });

               // Fetch notifications function
    function fetchNotifications() {
        fetch('notifications.php?fetch_notifications=true')
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
    
    // Set interval to fetch notifications every second
    setInterval(fetchNotifications, 1000);

        });


    </script>
</body>
</html>