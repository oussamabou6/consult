<?php
// Start the session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Redirect to login page if not logged in as admin
    header("Location: ../config/logout.php");
    exit;
}

// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Handle message status updates if submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['message_id'])) {
    $message_id = intval($_POST['message_id']);
    $action = $_POST['action'];
    
    if ($action === 'mark_accepted') {
        // Update message status to accepted
        $update_sql = "UPDATE support_messages SET status = 'accepted', updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $message_id);
        
        if ($update_stmt->execute()) {
            // Get user_id from message
            $user_query = "SELECT user_id FROM support_messages WHERE id = ?";
            $user_stmt = $conn->prepare($user_query);
            $user_stmt->bind_param("i", $message_id);
            $user_stmt->execute();
            $user_result = $user_stmt->get_result();
            
            if ($user_result->num_rows > 0) {
                $user_id = $user_result->fetch_assoc()['user_id'];
                
                // Create notification
                $notification_sql = "INSERT INTO client_notifications (user_id, message, created_at) 
                                    VALUES (?, 'Your support request has been accepted. Thank you for your patience.', NOW())";
                $notification_stmt = $conn->prepare($notification_sql);
                $notification_stmt->bind_param("i", $user_id);
                $notification_stmt->execute();
            }
            
            $_SESSION["admin_message"] = "Message marked as accepted successfully.";
            $_SESSION["admin_message_type"] = "success";
        } else {
            $_SESSION["admin_message"] = "Error updating message status.";
            $_SESSION["admin_message_type"] = "error";
        }
    
    }
    
    // Redirect to avoid form resubmission
    header("Location: client-messages.php");
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$contact_type_filter = isset($_GET['contact_type']) ? $_GET['contact_type'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the query with filters
$query = "SELECT sm.*, u.full_name, u.email, up.phone, up.profile_image
          FROM support_messages sm
          JOIN users u ON sm.user_id = u.id
          LEFT JOIN user_profiles up ON u.id = up.user_id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $query .= " AND sm.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($contact_type_filter)) {
    $query .= " AND sm.contact_type = ?";
    $params[] = $contact_type_filter;
    $types .= "s";
}

if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR sm.subject LIKE ? OR sm.message LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

if (!empty($date_from)) {
    $query .= " AND DATE(sm.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND DATE(sm.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Add sorting
$query .= " ORDER BY sm.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get counts for statistics
$total_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages")->fetch_assoc()['count'];
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];
$accepted_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'accepted'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];

// Get unread notifications count
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get pending withdrawals count
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending fund requests count
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending expert profile count
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];

// Function to get message status badge
function getMessageStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
         case 'accepted':
            return '<span class="status-badge status-accepted"><i class="fas fa-check-circle"></i> Accepted</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

// Function to format date
function formatDate($date, $format = 'd/m/Y H:i') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Function to get contact type label
function getContactTypeLabel($type) {
    switch($type) {
        case 'general':
            return '<span class="contact-type general"><i class="fas fa-info-circle"></i> General</span>';
        case 'technical':
            return '<span class="contact-type technical"><i class="fas fa-tools"></i> Technical</span>';
        case 'billing':
            return '<span class="contact-type billing"><i class="fas fa-credit-card"></i> Billing</span>';
        case 'consultation':
            return '<span class="contact-type consultation"><i class="fas fa-calendar-check"></i> Consultation</span>';
        case 'feedback':
            return '<span class="contact-type feedback"><i class="fas fa-comment-dots"></i> Feedback</span>';
        case 'other':
            return '<span class="contact-type other"><i class="fas fa-question-circle"></i> Other</span>';
        default:
            return '<span class="contact-type">' . ucfirst($type) . '</span>';
    }
}

// Function to truncate text
function truncateText($text, $length = 100) {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    return substr($text, 0, $length) . '...';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Messages - <?php echo htmlspecialchars($site_name); ?></title>
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
        
        
        
        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--border-color);
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
        }
        
        .card:hover::before {
            opacity: 1;
        }
        
        .card-header {
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(255, 255, 255, 0.8));
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-header h2 i {
            color: var(--primary-color);
            background: var(--primary-bg);
            padding: 0.5rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-footer {
            background-color: var(--light-color);
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }
        
        .filter-container {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .filter-label {
            font-weight: 500;
            color: var(--text-color-dark);
            font-size: 0.875rem;
        }
        
        .filter-select {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--card-bg);
            color: var(--text-color);
            font-size: 0.875rem;
            transition: var(--transition-fast);
            min-width: 150px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.1);
        }
        
        .search-container {
            display: flex;
            gap: 0.5rem;
            margin-left: auto;
        }
        
        .search-input {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--card-bg);
            color: var(--text-color);
            font-size: 0.875rem;
            transition: var(--transition-fast);
            min-width: 200px;
        }
        
        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.1);
        }
        
        .search-button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: var(--radius);
            background: var(--primary-gradient);
            color: white;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .search-button:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .messages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .message-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            height: 100%;
        }
        
        .message-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-5px);
            border-color: var(--primary-color);
        }
        
        .message-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
        }
        
        .message-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
        }
        
        .message-card:hover .message-header::before {
            opacity: 1;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-full);
            object-fit: cover;
            border: 3px solid var(--primary-light);
            box-shadow: var(--shadow);
            background-color: var(--light-color-2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--primary-color);
            flex-shrink: 0;
        }
        
        .user-info {
            flex: 1;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1.1rem;
            margin-bottom: 0.25rem;
        }
        
        .user-contact {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            font-size: 0.8rem;
            color: var(--text-color-light);
        }
        
        .user-contact span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .user-contact i {
            color: var(--primary-color);
            font-size: 0.9rem;
        }
        
        .message-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
        
        .message-body {
            padding: 1.25rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .message-subject {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
        }
        
        .message-text {
            color: var(--text-color);
            font-size: 0.9rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            flex: 1;
        }
        
        .message-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.25rem;
            background-color: var(--light-color);
            border-top: 1px solid var(--border-color);
            font-size: 0.8rem;
            color: var(--text-color-light);
        }
        
        .message-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .message-date i {
            color: var(--primary-color);
        }
        
        .contact-type {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            gap: 0.375rem;
            box-shadow: var(--shadow-sm);
        }
        
        .contact-type.general {
            background: linear-gradient(135deg, var(--info-light) 0%, var(--info-dark) 100%);
            color: white;
        }
        
        .contact-type.technical {
            background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .contact-type.billing {
            background: linear-gradient(135deg, var(--warning-light) 0%, var(--warning-dark) 100%);
            color: white;
        }
        
        .contact-type.consultation {
            background: linear-gradient(135deg, var(--success-light) 0%, var(--success-dark) 100%);
            color: white;
        }
        
        .contact-type.feedback {
            background: linear-gradient(135deg, var(--secondary-light) 0%, var(--secondary-dark) 100%);
            color: white;
        }
        
        .contact-type.other {
            background: linear-gradient(135deg, var(--dark-color) 0%, var(--dark-color-2) 100%);
            color: white;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            gap: 0.375rem;
            box-shadow: var(--shadow-sm);
        }
        
        .status-pending {
            background: var(--warning-gradient);
            color: white;
        }
        
        .status-in-progress {
            background: linear-gradient(135deg, var(--info-light) 0%, var(--info-dark) 100%);
            color: white;
        }
        
        .status-accepted {
            background: var(--success-gradient);
            color: white;
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
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%);
            transition: var(--transition);
            z-index: -1;
        }
        
        .btn:hover::before {
            transform: translateX(0);
        }
        
        .btn::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10, 10);
            opacity: 0;
            transition: transform 0.5s, opacity 1s;
        }
        
        .btn:active::after {
            transform: scale(0, 0);
            opacity: 0.3;
            transition: 0s;
        }
        
        .btn i {
            font-size: 1rem;
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
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-warning {
            background: var(--warning-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
        }
        
        .btn-warning:hover {
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: var(--z-modal);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }
        
        .modal.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-20px);
            transition: var(--transition);
        }
        
        .modal.active .modal-content {
            transform: translateY(0);
        }
        
        .modal-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-color-light);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition-fast);
        }
        
        .modal-close:hover {
            color: var(--danger-color);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--light-color);
            color: var(--text-color);
            transition: var(--transition-fast);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.1);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .pagination-item {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
            background-color: var(--card-bg);
            color: var(--text-color);
            font-weight: 500;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }
        
        .pagination-item:hover {
            background-color: var(--primary-bg);
            color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .pagination-item.active {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary-color);
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
        
        @media (max-width: 1200px) {
            .messages-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .messages-grid {
                grid-template-columns: repeat(1, 1fr);
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
            
            .filter-container {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-container {
                width: 100%;
                margin-left: 0;
            }
            
            .search-input {
                flex: 1;
            }
        }

        .stat-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.stat-card {
    background-color: var(--card-bg);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
    transition: var(--transition);
    border: 1px solid var(--border-color);
    position: relative;
    overflow: hidden;
    text-decoration: none;
    color: var(--text-color);
    cursor: pointer;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 5px;
    height: 100%;
    transition: var(--transition);
}

.stat-card.total::before {
    background: var(--primary-gradient);
}

.stat-card.pending::before {
    background: var(--warning-gradient);
}

.stat-card.in-progress::before {
    background: var(--info-gradient);
}

.stat-card.accepted::before {
    background: var(--success-gradient);
}

.stat-card-title {
    font-size: 0.875rem;
    color: var(--text-color-light);
    margin-bottom: 0.5rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stat-card-title i {
    font-size: 1rem;
}

.stat-card.total .stat-card-title i {
    color: var(--primary-color);
}

.stat-card.pending .stat-card-title i {
    color: var(--warning-color);
}

.stat-card.in-progress .stat-card-title i {
    color: var(--info-color);
}

.stat-card.accepted .stat-card-title i {
    color: var(--success-color);
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-card.total .stat-card-value {
    color: var(--primary-color);
}

.stat-card.pending .stat-card-value {
    color: var(--warning-color);
}

.stat-card.in-progress .stat-card-value {
    color: var(--info-color);
}

.stat-card.accepted .stat-card-value {
    color: var(--success-color);
}

.stat-card-desc {
    font-size: 0.75rem;
    color: var(--text-color-light);
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
                
                <a href="expert-messages.php" class="menu-item">
                    <i class="fas fa-comments"></i> Expert Messages
                </a>
                <a href="client-messages.php" class="menu-item active">
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
                
                <h1><i class="fas fa-comments"></i> Client Messages</h1>
            </div>
            
            <?php if (isset($_SESSION["admin_message"])): ?>
                <div class="alert alert-<?php echo $_SESSION["admin_message_type"]; ?>">
                    <i class="fas fa-<?php echo $_SESSION["admin_message_type"] === "success" ? "check-circle" : "exclamation-circle"; ?>"></i>
                    <div><?php echo $_SESSION["admin_message"]; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
                <?php unset($_SESSION["admin_message"]); unset($_SESSION["admin_message_type"]); ?>
            <?php endif; ?>
            
            <!-- Stats Section -->
            <div class="stat-cards">
    <a href="client-messages.php" class="stat-card total" title="View all messages">
        <div class="stat-card-title">
            <i class="fas fa-comments"></i> Total Messages
        </div>
        <div class="stat-card-value"><?php echo $total_messages; ?></div>
        <div class="stat-card-desc">All support messages</div>
    </a>
    
    <a href="client-messages.php?status=pending" class="stat-card pending" title="View pending messages">
        <div class="stat-card-title">
            <i class="fas fa-clock"></i> Pending
        </div>
        <div class="stat-card-value"><?php echo $pending_messages; ?></div>
        <div class="stat-card-desc">Awaiting response</div>
    </a>
    
  
    
    <a href="client-messages.php?status=accepted" class="stat-card accepted" title="View accepted messages">
        <div class="stat-card-title">
            <i class="fas fa-check-circle"></i> accepted
        </div>
        <div class="stat-card-value"><?php echo $accepted_messages; ?></div>
        <div class="stat-card-desc">Successfully accepted</div>
    </a>
</div>
            
            <!-- Filter Section -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-filter"></i> Filter Messages</h2>
                </div>
                <div class="card-body">
                    <form action="" method="get">
                        <div class="filter-container">
                            <div class="filter-group">
                                <label for="status" class="filter-label">Status:</label>
                                <select name="status" id="status" class="filter-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="accepted" <?php echo $status_filter === 'accepted' ? 'selected' : ''; ?>>accepted</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="contact_type" class="filter-label">Type:</label>
                                <select name="contact_type" id="contact_type" class="filter-select">
                                    <option value="">All Types</option>
                                    <option value="general" <?php echo $contact_type_filter === 'general' ? 'selected' : ''; ?>>General</option>
                                    <option value="technical" <?php echo $contact_type_filter === 'technical' ? 'selected' : ''; ?>>Technical</option>
                                    <option value="billing" <?php echo $contact_type_filter === 'billing' ? 'selected' : ''; ?>>Billing</option>
                                    <option value="consultation" <?php echo $contact_type_filter === 'consultation' ? 'selected' : ''; ?>>Consultation</option>
                                    <option value="feedback" <?php echo $contact_type_filter === 'feedback' ? 'selected' : ''; ?>>Feedback</option>
                                    <option value="other" <?php echo $contact_type_filter === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_from" class="filter-label">From:</label>
                                <input type="date" name="date_from" id="date_from" class="filter-select" value="<?php echo $date_from; ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_to" class="filter-label">To:</label>
                                <input type="date" name="date_to" id="date_to" class="filter-select" value="<?php echo $date_to; ?>">
                            </div>
                            
                            <div class="search-container">
                                <input type="text" name="search" placeholder="Search by name, email, subject or message" class="search-input" value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="search-button">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Messages Grid -->
            <div class="messages-grid">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($message = $result->fetch_assoc()): ?>
                        <div class="message-card">
                            <div class="message-header">
                                <?php if (!empty($message['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($message['profile_image']); ?>" alt="<?php echo htmlspecialchars($message['full_name']); ?>" class="user-avatar">
                                <?php else: ?>
                                    <div class="user-avatar">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="user-info">
                                    <div class="user-name"><?php echo htmlspecialchars($message['full_name']); ?></div>
                                    <div class="user-contact">
                                        <span><i class="fas fa-envelope"></i>
                                            <a href="mailto: <?php echo htmlspecialchars($message['contact_email'] ?? $message['email']); ?>" style="color: var(--primary-color);">
                                            <?php echo htmlspecialchars($message['contact_email'] ?? $message['email']); ?></a>
                                    </span>

                                            <span><i class="fas fa-phone"></i> <?php echo htmlspecialchars($message['phone']); ?></span>
                                       
                                    </div>
                                </div>
                                
                                <div class="message-status">
                                    <?php echo getMessageStatusBadge($message['status']); ?>
                                </div>
                            </div>
                            
                            <div class="message-body">
                                <div>
                                    <div class="message-subject"><?php echo htmlspecialchars($message['subject']); ?></div>
                                    <div class="message-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                </div>
                                
                                <div class="action-buttons">
                                    <button class="btn btn-primary btn-sm" onclick="openViewModal(<?php echo $message['id']; ?>, '<?php echo addslashes(htmlspecialchars($message['subject'])); ?>')">
                                        <i class="fas fa-eye"></i> View Details
                                    </button>
                                    
                                    
                                    <?php if ($message['status'] !== 'accepted'): ?>
                                    <button class="btn btn-success btn-sm" onclick="openResolveModal(<?php echo $message['id']; ?>, '<?php echo addslashes(htmlspecialchars($message['subject'])); ?>')">
                                        <i class="fas fa-check-circle"></i> Accept
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="message-meta">
                                <div class="message-date">
                                    <i class="fas fa-calendar-alt"></i> <?php echo formatDate($message['created_at']); ?>
                                </div>
                                <div>
                                    <?php echo getContactTypeLabel($message['contact_type']); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem;">
                        <i class="fas fa-inbox" style="font-size: 3rem; color: var(--text-color-light); margin-bottom: 1rem;"></i>
                        <h3>No Messages Found</h3>
                        <p>There are no support messages matching your criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <a href="#" class="pagination-item">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="#" class="pagination-item active">1</a>
                <a href="#" class="pagination-item">2</a>
                <a href="#" class="pagination-item">3</a>
                <a href="#" class="pagination-item">
                    <i class="fas fa-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>
    
    <!-- View Message Modal -->
    <div class="modal" id="viewModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="viewModalTitle">View Message</h3>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" onclick="closeModal('viewModal')">Close</button>
            </div>
        </div>
    </div>
    
    
    
    <!-- Resolve Modal -->
    <div class="modal" id="resolveModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Mark Message as accepted</h3>
                <button class="modal-close" onclick="closeModal('resolveModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to mark this message as accepted?</p>
                <p>Subject: <span id="resolveSubject" class="font-semibold"></span></p>
                
                <form action="" method="post" id="resolveForm">
                    <input type="hidden" name="action" value="mark_accepted">
                    <input type="hidden" name="message_id" id="resolveMessageId">
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="closeModal('resolveModal')">Cancel</button>
                        <button type="submit" class="btn btn-success">Accept</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        // Modal functionality
        
// Replace the openViewModal function with this updated version
function openViewModal(messageId, subject) {
    document.getElementById('viewModalTitle').textContent = 'Message: ' + subject;
    
    // Show loading state
    document.getElementById('viewModalBody').innerHTML = `
        <div class="p-4">
            <p class="mb-4">Loading message details...</p>
            <div class="text-center">
                <i class="fas fa-spinner fa-spin text-primary" style="font-size: 2rem;"></i>
            </div>
        </div>
    `;
    
    document.getElementById('viewModal').classList.add('active');
    
    // Fetch message details
    fetch('gett-messages-detailss.php?id=' + messageId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const message = data.message;
                const attachments = data.attachments || [];
                
                // Use email from message object, with fallback
                const contactEmail = message.email || '';
                
                let attachmentsHtml = '';
                if (attachments && attachments.length > 0) {
                    attachmentsHtml = `
                        <div class="mb-4">
                            <h4 class="text-lg font-semibold mb-2">Attachments</h4>
                            <div class="attachment-list">
                                ${attachments.map(attachment => `
                                    <div class="attachment-item">
                                        <a href="../uploads/support_attachments/${attachment.file_name}" target="_blank" class="btn btn-sm btn-primary mb-2">
                                            <i class="fas fa-paperclip me-2"></i> ${attachment.file_name}
                                        </a>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                }
                
                document.getElementById('viewModalBody').innerHTML = `
                    <div class="p-4">
                        <div class="mb-4">
                            <h4 class="text-lg font-semibold mb-2">Client Information</h4>
                            <p><strong>Name:</strong> ${message.full_name}</p>
                            <p><strong>Email:</strong> <a href="mailto:${contactEmail}" style="color: var(--primary-color);">${contactEmail}</a></p>
                            ${message.phone ? `<p><strong>Phone:</strong> ${message.phone}</p>` : ''}
                        </div>
                        <div class="mb-4">
                            <h4 class="text-lg font-semibold mb-2">Message Details</h4>
                            <p><strong>Subject:</strong> ${message.subject}</p>
                            <p><strong>Type:</strong> ${message.contact_type.charAt(0).toUpperCase() + message.contact_type.slice(1)}</p>
                            <p><strong>Status:</strong> ${message.status.charAt(0).toUpperCase() + message.status.slice(1)}</p>
                            <p><strong>Sent on:</strong> ${message.created_at_formatted}</p>
                        </div>
                        <div class="mb-4">
                            <h4 class="text-lg font-semibold mb-2">Message Content</h4>
                            <div class="message-content p-3" style="background-color: var(--light-color-2); border-radius: var(--radius); border: 1px solid var(--border-color);">
                                ${message.message.replace(/\n/g, '<br>')}
                            </div>
                        </div>
                        ${attachmentsHtml}
                    </div>
                `;
                
                // Add a button to mark as accepted if not already accepted
                if (message.status !== 'accepted') {
                    const footerBtn = document.querySelector('#viewModal .modal-footer');
                    const resolveBtn = document.createElement('button');
                    resolveBtn.type = 'button';
                    resolveBtn.className = 'btn btn-success';
                    resolveBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i> Mark as accepted';
                    resolveBtn.onclick = function() {
                        closeModal('viewModal');
                        openResolveModal(messageId, subject);
                    };
                    footerBtn.prepend(resolveBtn);
                }
            } else {
                document.getElementById('viewModalBody').innerHTML = `
                    <div class="p-4">
                        <div class="alert alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <div>Error loading message details: ${data.message || 'Unknown error'}</div>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('viewModalBody').innerHTML = `
                <div class="p-4">
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>Error loading message details. Please try again later.</div>
                        <div class="mt-2 text-sm">${error.message}</div>
                    </div>
                </div>
            `;
            console.error('Error fetching message details:', error);
        });
}

// Add this to the DOMContentLoaded event listener to clean up modal footer when closing
document.addEventListener('DOMContentLoaded', function() {
    // Add event to reset modal footer when closing
    const viewModal = document.getElementById('viewModal');
    viewModal.addEventListener('transitionend', function(e) {
        if (!viewModal.classList.contains('active')) {
            const footerBtn = viewModal.querySelector('.modal-footer');
            // Keep only the close button
            while (footerBtn.children.length > 1) {
                footerBtn.removeChild(footerBtn.firstChild);
            }
        }
    });
});
        
        
        function openResolveModal(messageId, subject) {
            document.getElementById('resolveMessageId').value = messageId;
            document.getElementById('resolveSubject').textContent = subject;
            document.getElementById('resolveModal').classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(event) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (event.target === modal) {
                    modal.classList.remove('active');
                }
            });
        });
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // Add pulse animation to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.querySelector('.stat-icon').classList.add('pulse');
                });
                
                card.addEventListener('mouseleave', function() {
                    this.querySelector('.stat-icon').classList.remove('pulse');
                });
            });
            
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
                // Function to refresh notification badges
                function refreshNotificationBadges() {
                fetch('get-notification-counts.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update notification badges
                        updateBadge('unread_notifications', data.unread_notifications);
                        updateBadge('pending_withdrawals', data.pending_withdrawals);
                        updateBadge('pending_fund_requests', data.pending_fund_requests);
                        updateBadge('pending_review_profiles', data.pending_review_profiles);
                        updateBadge('pending_messages', data.pending_messages);
                        updateBadge('pending_reports', data.pending_reports);
                    })
                    .catch(error => console.error('Error fetching notification counts:', error));
            }

            // Function to update a specific badge
            function updateBadge(type, count) {
                const badges = document.querySelectorAll(`.menu-item:has(i.fas.fa-${getBadgeIcon(type)}) .notification-badge`);
                
                badges.forEach(badge => {
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            }

            // Helper function to get icon name based on notification type
            function getBadgeIcon(type) {
                switch(type) {
                    case 'unread_notifications': return 'bell';
                    case 'pending_withdrawals': return 'money-bill-wave';
                    case 'pending_fund_requests': return 'wallet';
                    case 'pending_review_profiles': return 'clock';
                    case 'pending_messages': return 'comments';
                    case 'pending_reports': return 'flag';
                    default: return '';
                }
            }

            // Refresh notification badges every 1 second
            setInterval(refreshNotificationBadges, 1000);
        
        });
    </script>
</body>
</html>
