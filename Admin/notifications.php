<?php
// Start session
session_start();

// Include database connection from config file
require_once "../config/config.php";


function checkAdminAccess() {
    if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
        // Redirect to login page if not logged in or not an admin
        header("Location: ../config/logout.php");
        exit;
    }}

// ========== UTILITY FUNCTIONS ==========
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}

function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'processing':
            return '<span class="status-badge status-processing"><i class="fas fa-spinner fa-spin"></i> Processing</span>';
        case 'completed':
        case 'approved':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Completed</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
        case 'cancelled':
            return '<span class="status-badge status-rejected"><i class="fas fa-ban"></i> Cancelled</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

function calculateCommission($amount, $rate = null) {
    global $conn;
    
    if ($rate === null) {
        // Get commission rate from settings
        $query = "SELECT setting_value FROM settings WHERE setting_key = 'commission_rate'";
        $result = $conn->query($query);
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $rate = (float)$row['setting_value'];
        } else {
            $rate = 10; // Default commission rate if not found
        }
    }
    
    return ($amount * $rate) / 100;
}

function getExpertDetails($expertId) {
    global $conn;
    
    $query = "SELECT u.*, p.*, ep.* 
              FROM users u 
              LEFT JOIN user_profiles p ON u.id = p.user_id 
              LEFT JOIN expert_profiledetails ep ON u.id = ep.expert_id 
              WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $expertId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

function getUserDetails($userId) {
    global $conn;
    
    $query = "SELECT u.*, p.* 
              FROM users u 
              LEFT JOIN user_profiles p ON u.id = p.user_id 
              WHERE u.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    
    return null;
}

function addNotification($userId, $message, $type = '', $relatedId = null, $profileId = 0) {
    global $conn;
    
    $query = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message, related_id, is_read, created_at) 
              VALUES (?, ?, ?, ?, ?, 0, NOW())";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iissi", $userId, $profileId, $type, $message, $relatedId);
    return $stmt->execute();
}

function getCurrency() {
    global $conn;
    
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'currency'";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        return $row['setting_value'];
    }
    
    return 'DA'; // Default currency
}

// Check if user is logged in and has admin privileges
checkAdminAccess();

// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Get currency from settings
$currency = getCurrency();

// Mark all admin notifications as read when the page is loaded
$mark_read_query = "UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0";
$conn->query($mark_read_query);

// Get all notifications, ordered by most recent first
$notifications_query = "SELECT * FROM admin_notifications ORDER BY created_at DESC";
$notifications_result = $conn->query($notifications_query);

// Count total notifications
$total_notifications = $notifications_result->num_rows;

// Count unread notifications (should be 0 after page load, but keeping for reference)
$unread_query = "SELECT COUNT(*) as unread_count FROM admin_notifications WHERE is_read = 0";
$unread_result = $conn->query($unread_query);
$unread_data = $unread_result->fetch_assoc();
$unread_count = $unread_data['unread_count'];

// Get notification types for filtering
$types_query = "SELECT DISTINCT notification_type FROM admin_notifications WHERE notification_type != ''";
$types_result = $conn->query($types_query);
$notification_types = [];
while ($type = $types_result->fetch_assoc()) {
    $notification_types[] = $type['notification_type'];
}

// Handle filtering
$filter_type = isset($_GET['type']) ? sanitizeInput($_GET['type']) : '';
$filter_date = isset($_GET['date']) ? sanitizeInput($_GET['date']) : '';
$search_term = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

$filter_conditions = [];
$filter_params = [];
$filter_types = [];

if (!empty($filter_type)) {
    $filter_conditions[] = "notification_type = ?";
    $filter_params[] = $filter_type;
    $filter_types[] = 's';
}

if (!empty($filter_date)) {
    $filter_conditions[] = "DATE(created_at) = ?";
    $filter_params[] = $filter_date;
    $filter_types[] = 's';
}

if (!empty($search_term)) {
    $filter_conditions[] = "message LIKE ?";
    $filter_params[] = "%$search_term%";
    $filter_types[] = 's';
}

$filtered_query = "SELECT * FROM admin_notifications";
if (!empty($filter_conditions)) {
    $filtered_query .= " WHERE " . implode(" AND ", $filter_conditions);
}
$filtered_query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($filtered_query);
if (!empty($filter_params)) {
    $stmt->bind_param(implode('', $filter_types), ...$filter_params);
}
$stmt->execute();
$notifications_result = $stmt->get_result();


// Get unread notifications count
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get pending withdrawals count
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending fund requests count
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending expert profile count
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
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
            width: 100%;
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
            width: 100%;
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
            transform: translateX(-100%);
        }
        
        .sidebar.active {
            transform: translateX(0);
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
            width: 100%;
            padding: 1rem;
            transition: var(--transition);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            flex-wrap: wrap;
            gap: 1rem;
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
            font-size: 1.5rem;
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
        
        .alert-danger {
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
        
        /* Dashboard Grid and Stat Card Styles from dashboard.php */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
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

        .stat-card.users::before {
            background: var(--primary-gradient);
        }

        .stat-card.experts::before {
            background: var(--info-gradient);
        }

        .stat-card.clients::before {
            background: var(--success-gradient);
        }

        .stat-card.admins::before {
            background: var(--dark-color);
        }

        .stat-card.pending::before {
            background: var(--warning-gradient);
        }

        .stat-card.approved::before {
            background: var(--success-gradient);
        }

        .stat-card.rejected::before {
            background: var(--danger-gradient);
        }

        .stat-card.earnings::before {
            background: linear-gradient(135deg, #4ade80 0%, #10b981 100%);
        }

        .stat-card.withdrawals::before {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        }

        .stat-card.funds::before {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
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

        .stat-card.users .stat-card-title i {
            color: var(--primary-color);
        }

        .stat-card.pending .stat-card-title i {
            color: var(--warning-color);
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .stat-card.users .stat-card-value {
            color: var(--primary-color);
        }

        .stat-card.pending .stat-card-value {
            color: var(--warning-color);
        }

        .stat-card-desc {
            font-size: 0.75rem;
            color: var(--text-color-light);
        }

        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }

        @media (max-width: 768px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
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
            width: 100%;
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
        
        .filters {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            width: 100%;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            width: 100%;
        }
        
        .filter-label {
            font-weight: 600;
            color: var(--text-color-dark);
            font-size: 0.875rem;
            min-width: 60px;
        }
        
        .filter-select, .search-input, .date-input {
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--text-color);
            background-color: white;
            transition: var(--transition-fast);
            box-shadow: var(--shadow-sm);
            width: 100%;
        }
        
        .filter-select:focus, .search-input:focus, .date-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }
        
        .search-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }
        
        .search-input {
            width: 100%;
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
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
        
        .btn-sm {
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
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
        
        .btn-info {
            background: var(--info-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(6, 182, 212, 0.3);
        }
        
        .btn-info:hover {
            box-shadow: 0 6px 15px rgba(6, 182, 212, 0.4);
            transform: translateY(-2px);
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
            white-space: nowrap;
        }
        
        .status-approved {
            background: var(--success-gradient);
            color: white;
        }
        
        .status-pending {
            background: var(--warning-gradient);
            color: white;
        }
        
        .status-processing {
            background: var(--info-gradient);
            color: white;
        }
        
        .status-rejected {
            background: var(--danger-gradient);
            color: white;
        }
        
        /* Notification specific styles */
        .notification-container {
            margin-top: 20px;
        }
        
        .notification-item {
            padding: 15px;
            border-radius: var(--radius);
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--card-bg);
            border-left: 4px solid var(--primary-color);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .notification-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .notification-item.unread {
            background-color: var(--primary-bg);
            border-left: 4px solid var(--primary-color);
            font-weight: 500;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-time {
            color: var(--text-color-light);
            font-size: 0.85rem;
            margin-top: 5px;
        }
        
        .notification-actions {
            margin-left: 15px;
        }
        
        .notification-type {
            display: inline-block;
            padding: 3px 8px;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            margin-right: 8px;
            color: white;
            font-weight: 600;
        }
        
        .type-withdrawal_request {
            background-color: var(--warning-color);
        }
        
        .type-fund_request {
            background-color: var(--success-color);
        }
        
        .type-new_profile {
            background-color: var(--info-color);
        }
        
        .type-banking_submitted {
            background-color: var(--primary-color);
        }
        
        .type-support_request {
            background-color: var(--danger-color);
        }
        
        .type-support_reply {
            background-color: var(--secondary-color);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--text-color-lighter);
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            font-size: 1.25rem;
            color: var(--text-color-dark);
            margin-bottom: 0.5rem;
        }
        
        .empty-state p {
            color: var(--text-color-light);
        }
        
        .menu-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: var(--z-fixed);
            box-shadow: var(--shadow);
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
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @media (min-width: 768px) {
            .main-content {
                padding: 2rem;
                margin-left: 280px;
            }
            
            .sidebar {
                transform: translateX(0);
            }
            
            .menu-toggle {
                display: none;
            }
            
            .filters {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .search-form {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .filter-group {
                width: auto;
            }
            
            .header h1 {
                font-size: 1.75rem;
            }
        }
        
        @media (max-width: 767px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar {
                width: 250px;
            }
            
            .notification-item {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .notification-actions {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
                display: flex;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
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
                <a href="notifications.php" class="menu-item active">
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
                <h1><i class="fas fa-bell"></i> Notifications</h1>
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
            
            <!-- Statistics -->
            <h2 style="margin-bottom: 1rem; color: var(--dark-color); font-size: 1.25rem;">Notification Statistics</h2>
            <div class="dashboard-grid">
                <div class="stat-card users">
                    <div class="stat-card-title">
                        <i class="fas fa-bell"></i> Total Notifications
                    </div>
                    <div class="stat-card-value"><?php echo $total_notifications; ?></div>
                    <div class="stat-card-desc">All system notifications</div>
                </div>
                <div class="stat-card pending">
                    <div class="stat-card-title">
                        <i class="fas fa-bell-slash"></i> Unread Notifications
                    </div>
                    <div class="stat-card-value"><?php echo $unread_count; ?></div>
                    <div class="stat-card-desc">Notifications not yet read</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="search-form">
                    <div class="filter-group">
                        <label class="filter-label" for="type">Type:</label>
                        <select name="type" id="type" class="filter-select">
                            <option value="">All Types</option>
                            <?php foreach ($notification_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $filter_type === $type ? "selected" : ""; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label" for="date">Date:</label>
                        <input type="text" id="date" name="date" class="date-input" placeholder="Select date" value="<?php echo $filter_date; ?>">
                    </div>
                    
                    <input type="text" name="search" class="search-input" placeholder="Search in notifications" value="<?php echo htmlspecialchars($search_term); ?>">
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($filter_type) || !empty($filter_date) || !empty($search_term)): ?>
                            <a href="notifications.php" class="btn btn-warning">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- Notifications List -->
            <?php if ($notifications_result->num_rows > 0): ?>
                <div class="notification-container">
                    <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                        <div class="notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>">
                            <div class="notification-content">
                                <?php if (!empty($notification['notification_type'])): ?>
                                    <span class="notification-type type-<?php echo htmlspecialchars($notification['notification_type']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $notification['notification_type'])); ?>
                                    </span>
                                <?php endif; ?>
                                
                                <div><?php echo htmlspecialchars($notification['message']); ?></div>
                                
                                <div class="notification-time">
                                    <i class="far fa-clock"></i> <?php echo formatDate($notification['created_at']); ?>
                                </div>
                            </div>
                            
                            <div class="notification-actions">
                                <?php
                                switch ($notification['notification_type']) {
                                    case 'withdrawal_request':
                                        echo '<a href="view-withdrawal.php?id=' . $notification['related_id'] . '" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Request
                                        </a>';
                                        break;
                                    case 'fund_request':
                                        echo '<a href="fund-requests.php?id=' . $notification['related_id'] . '" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Request
                                        </a>';
                                        break;
                                    case 'new_profile':
                                        echo '<a href="expert-profiles.php?id=' . $notification['profile_id'] . '" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Profile
                                        </a>';
                                        break;
                                    case 'banking_submitted':
                                        echo '<a href="expert-banking.php?id=' . $notification['profile_id'] . '" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Banking Info
                                        </a>';
                                        break;
                                    case 'support_request':
                                    case 'support_reply':
                                        echo '<a href="client-messages.php?id=' . $notification['related_id'] . '" class="btn btn-primary btn-sm">
                                            <i class="fas fa-eye"></i> View Ticket
                                        </a>';
                                        break;
                                    
                                }
                                ?>
                               
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bell-slash"></i>
                    <h3>No Notifications Found</h3>
                    <p>There are no notifications matching your criteria.</p>
                    <?php if (!empty($filter_type) || !empty($filter_date) || !empty($search_term)): ?>
                        <a href="notifications.php" class="btn btn-primary">
                            <i class="fas fa-redo"></i> Reset Filters
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Initialize date picker
        flatpickr("#date", {
            dateFormat: "Y-m-d",
            allowInput: true
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
            
            // Mobile sidebar toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.sidebar');
        
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 768 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target) && 
                    sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
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
