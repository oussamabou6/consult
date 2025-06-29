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

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build the query with filters
$query = "SELECT c.*, 
          u_client.full_name as client_name, u_client.email as client_email,
          u_expert.full_name as expert_name, u_expert.email as expert_email
          FROM consultations c
          JOIN users u_client ON c.client_id = u_client.id
          JOIN users u_expert ON c.expert_id = u_expert.id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($status_filter)) {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($date_from)) {
    $query .= " AND c.consultation_date >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $query .= " AND c.consultation_date <= ?";
    $params[] = $date_to;
    $types .= "s";
}

if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (u_client.full_name LIKE ? OR u_expert.full_name LIKE ? OR u_client.email LIKE ? OR u_expert.email LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ssss";
}

// Add sorting
$query .= " ORDER BY c.consultation_date DESC, c.consultation_time DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Get counts for statistics
$total_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations")->fetch_assoc()['count'];
$pending_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'pending'")->fetch_assoc()['count'];
$confirmed_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'confirmed'")->fetch_assoc()['count'];
$completed_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'completed'")->fetch_assoc()['count'];
$rejected_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'rejected'")->fetch_assoc()['count'];

// Get unread notifications count
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get today's consultations
$today = date('Y-m-d');
$today_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE consultation_date = '$today'")->fetch_assoc()['count'];

// Get this week's consultations
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$week_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE consultation_date BETWEEN '$week_start' AND '$week_end'")->fetch_assoc()['count'];

// Function to get consultation status badge
function getConsultationStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'confirmed':
            return '<span class="status-badge status-info"><i class="fas fa-calendar-check"></i> Confirmed</span>';
        case 'completed':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Completed</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
        case 'canceled':
            return '<span class="status-badge status-danger"><i class="fas fa-ban"></i> Canceled</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

// Function to format date
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Function to format time
function formatTime($time, $format = 'H:i') {
    if (!$time) return 'N/A';
    return date($format, strtotime($time));
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

// Handle consultation actions
if (isset($_POST['action'])) {
    $consultation_id = isset($_POST['consultation_id']) ? $_POST['consultation_id'] : 0;
    
    if ($_POST['action'] == 'update_status') {
        $new_status = $_POST['new_status'];
        $update_query = "UPDATE consultations SET status = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("si", $new_status, $consultation_id);
        
        if ($update_stmt->execute()) {
            $_SESSION["admin_message"] = "Consultation status updated successfully.";
            $_SESSION["admin_message_type"] = "success";
        } else {
            $_SESSION["admin_message"] = "Error updating consultation status.";
            $_SESSION["admin_message_type"] = "error";
        }
        
        // Redirect to refresh the page
        header("Location: consultations.php" . (empty($_SERVER['QUERY_STRING']) ? "" : "?" . $_SERVER['QUERY_STRING']));
        exit;
    }
    
    if ($_POST['action'] == 'delete_consultation') {
        $delete_query = "DELETE FROM consultations WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bind_param("i", $consultation_id);
        
        if ($delete_stmt->execute()) {
            $_SESSION["admin_message"] = "Consultation deleted successfully.";
            $_SESSION["admin_message_type"] = "success";
        } else {
            $_SESSION["admin_message"] = "Error deleting consultation.";
            $_SESSION["admin_message_type"] = "error";
        }
        
        // Redirect to refresh the page
        header("Location: consultations.php" . (empty($_SERVER['QUERY_STRING']) ? "" : "?" . $_SERVER['QUERY_STRING']));
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultations - <?php echo htmlspecialchars($site_name); ?></title>
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
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-5px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
        }

        .stat-card.purple::before {
            background: var(--primary-gradient);
        }

        .stat-card.blue::before {
            background: var(--info-gradient);
        }

        .stat-card.green::before {
            background: var(--success-gradient);
        }

        .stat-card.orange::before {
            background: var(--warning-gradient);
        }

        .stat-card.red::before {
            background: var(--danger-gradient);
        }

        .stat-card-content {
            padding: 1.25rem 1.25rem 1.25rem 1.5rem;
        }

        .stat-card-header {
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .stat-card-header i {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }

        .stat-card.purple .stat-card-header i {
            color: var(--primary-color);
        }

        .stat-card.blue .stat-card-header i {
            color: var(--info-color);
        }

        .stat-card.green .stat-card-header i {
            color: var(--success-color);
        }

        .stat-card.orange .stat-card-header i {
            color: var(--warning-color);
        }

        .stat-card.red .stat-card-header i {
            color: var(--danger-color);
        }

        .stat-card-title {
            font-weight: 600;
            color: var(--text-color-dark);
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card.purple .stat-card-value {
            color: var(--primary-color);
        }

        .stat-card.blue .stat-card-value {
            color: var(--info-color);
        }

        .stat-card.green .stat-card-value {
            color: var(--success-color);
        }

        .stat-card.orange .stat-card-value {
            color: var(--warning-color);
        }

        .stat-card.red .stat-card-value {
            color: var(--danger-color);
        }

        .stat-card-description {
            font-size: 0.875rem;
            color: var(--text-color-light);
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
        
        .filter-select, .filter-input {
            padding: 0.5rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--card-bg);
            color: var(--text-color);
            font-size: 0.875rem;
            transition: var(--transition-fast);
            min-width: 150px;
        }
        
        .filter-select:focus, .filter-input:focus {
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
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 1rem;
            text-align: left;
        }
        
        .table th {
            font-weight: 600;
            color: var(--text-color-dark);
            background-color: var(--light-color-2);
            position: relative;
        }
        
        .table th:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 1px;
            background: var(--border-color-dark);
        }
        
        .table tr {
            border-bottom: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .table tr:last-child {
            border-bottom: none;
        }
        
        .table tr:hover {
            background-color: var(--light-color-2);
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
        
        .status-admin {
            background: linear-gradient(135deg, var(--dark-color) 0%, var(--dark-color-2) 100%);
            color: white;
        }
        
        .status-expert {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .status-client {
            background: linear-gradient(135deg, var(--success-color) 0%, var(--success-dark) 100%);
            color: white;
        }
        
        .status-active {
            background: var(--success-gradient);
            color: white;
        }
        
        .status-inactive {
            background: var(--danger-gradient);
            color: white;
        }
        
        .status-pending {
            background: var(--warning-gradient);
            color: white;
        }
        
        .status-revision {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--secondary-dark) 100%);
            color: white;
        }
        
        .status-approved {
            background: var(--success-gradient);
            color: white;
        }
        
        .status-rejected {
            background: var(--danger-gradient);
            color: white;
        }
        .status-danger{
            background: var(--danger-gradient);
            color: white;
        }
        .status-info {
            background: var(--info-gradient);
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
        
        .btn-info {
            background: var(--info-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(6, 182, 212, 0.3);
        }
        
        .btn-info:hover {
            box-shadow: 0 6px 15px rgba(6, 182, 212, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
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
        
        .calendar-view {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .calendar-day {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            padding: 0.5rem;
            min-height: 100px;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }
        
        .calendar-day:hover {
            box-shadow: var(--shadow);
            border-color: var(--primary-color);
        }
        
        .calendar-day-header {
            font-weight: 600;
            text-align: center;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .calendar-day-content {
            font-size: 0.75rem;
        }
        
        .calendar-event {
            background-color: var(--primary-bg);
            color: var(--primary-color);
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            margin-bottom: 0.25rem;
            font-size: 0.7rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .calendar-event:hover {
            background-color: var(--primary-color);
            color: white;
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
            .stats-container {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
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
            
            .stats-container {
                grid-template-columns: 1fr;
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
            
            .table-responsive {
                overflow-x: auto;
            }
            
            .calendar-view {
                grid-template-columns: repeat(1, 1fr);
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


                <a href="consultations.php" class="menu-item active">
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
                <h1><i class="fas fa-calendar-check"></i> Consultations</h1>
                
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
            <div class="stats-container">
                <a href="?status=" class="stat-card purple" title="View all consultations">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="stat-card-title">Total Consultations</span>
                        </div>
                        <div class="stat-card-value"><?php echo $total_consultations; ?></div>
                        <div class="stat-card-description">All consultations in the system</div>
                    </div>
                </a>
                
                <a href="?status=pending" class="stat-card orange" title="View pending consultations">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-clock"></i>
                            <span class="stat-card-title">Pending</span>
                        </div>
                        <div class="stat-card-value"><?php echo $pending_consultations; ?></div>
                        <div class="stat-card-description">Awaiting confirmation</div>
                    </div>
                </a>
                
                <a href="?status=confirmed" class="stat-card blue" title="View confirmed consultations">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-calendar-check"></i>
                            <span class="stat-card-title">Confirmed</span>
                        </div>
                        <div class="stat-card-value"><?php echo $confirmed_consultations; ?></div>
                        <div class="stat-card-description">Ready to proceed</div>
                    </div>
                </a>
                
                <a href="?status=completed" class="stat-card green" title="View completed consultations">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-check-circle"></i>
                            <span class="stat-card-title">Completed</span>
                        </div>
                        <div class="stat-card-value"><?php echo $completed_consultations; ?></div>
                        <div class="stat-card-description">Successfully finished</div>
                    </div>
                </a>
                
                <a href="?status=rejected" class="stat-card red" title="View rejected consultations">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-times-circle"></i>
                            <span class="stat-card-title">Rejected</span>
                        </div>
                        <div class="stat-card-value"><?php echo $rejected_consultations; ?></div>
                        <div class="stat-card-description">Declined consultations</div>
                    </div>
                </a>
                
                <a href="?date_from=<?php echo $today; ?>&date_to=<?php echo $today; ?>" class="stat-card orange" title="View today's consultations">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-calendar-day"></i>
                            <span class="stat-card-title">Today</span>
                        </div>
                        <div class="stat-card-value"><?php echo $today_consultations; ?></div>
                        <div class="stat-card-description">Today's consultations</div>
                    </div>
                </a>
                
                <a href="?date_from=<?php echo $week_start; ?>&date_to=<?php echo $week_end; ?>" class="stat-card blue" title="View this week's consultations">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-calendar-week"></i>
                            <span class="stat-card-title">This Week</span>
                        </div>
                        <div class="stat-card-value"><?php echo $week_consultations; ?></div>
                        <div class="stat-card-description">Consultations this week</div>
                    </div>
                </a>
            </div>
            
            <!-- Filter Section -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-filter"></i> Filter Consultations</h2>
                </div>
                <div class="card-body">
                    <form action="" method="get">
                        <div class="filter-container">
                            <div class="filter-group">
                                <label for="status" class="filter-label">Status:</label>
                                <select name="status" id="status" class="filter-select">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_from" class="filter-label">From:</label>
                                <input type="date" name="date_from" id="date_from" class="filter-input" value="<?php echo $date_from; ?>">
                            </div>
                            
                            <div class="filter-group">
                                <label for="date_to" class="filter-label">To:</label>
                                <input type="date" name="date_to" id="date_to" class="filter-input" value="<?php echo $date_to; ?>">
                            </div>
                            
                            <div class="search-container">
                                <input type="text" name="search" class="search-input" placeholder="Search by name or email" value="<?php echo htmlspecialchars($search); ?>">
                                <button type="submit" class="search-button">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Consultations Table -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-list"></i> Consultations List</h2>
                    <div>
                        <button class="btn btn-primary" onclick="window.location.href='consultations.php'">
                            <i class="fas fa-sync-alt"></i> Reset Filters
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <?php if ($result && $result->num_rows > 0): ?>
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Client</th>
                                        <th>Expert</th>
                                        <th>Date & Time</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo $row['id']; ?></td>
                                            <td>
                                                <div><strong><?php echo htmlspecialchars($row['client_name']); ?></strong></div>
                                                <div><small><?php echo htmlspecialchars($row['client_email']); ?></small></div>
                                            </td>
                                            <td>
                                                <div><strong><?php echo htmlspecialchars($row['expert_name']); ?></strong></div>
                                                <div><small><?php echo htmlspecialchars($row['expert_email']); ?></small></div>
                                            </td>
                                            <td>
                                                <div><?php echo formatDate($row['consultation_date']); ?></div>
                                                <div><small><?php echo formatTime($row['consultation_time']); ?></small></div>
                                            </td>
                                            <td><?php echo $row['duration']; ?> minutes</td>
                                            <td><?php echo getConsultationStatusBadge($row['status']); ?></td>
                                           
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div style="text-align: center; padding: 2rem;">
                                <i class="fas fa-calendar-times" style="font-size: 3rem; color: var(--text-color-lighter); margin-bottom: 1rem;"></i>
                                <p>No consultations found matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    
    <!-- Update Status Modal -->
    <div id="updateStatusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Update Consultation Status</h3>
                <button class="modal-close" onclick="closeStatusModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new_status" class="form-label">Status:</label>
                        <select name="new_status" id="new_status" class="form-control" required>
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeStatusModal()">Cancel</button>
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="consultation_id" id="status_consultation_id">
                    <button type="submit" class="btn btn-primary">Update Status</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Consultation Modal -->
    <div id="deleteConsultationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Delete Consultation</h3>
                <button class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to delete this consultation?</p>
                    <p style="color: var(--danger-color); font-weight: 500;">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeDeleteModal()">Cancel</button>
                    <input type="hidden" name="action" value="delete_consultation">
                    <input type="hidden" name="consultation_id" id="delete_consultation_id">
                    <button type="submit" class="btn btn-danger">Delete</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let currentConsultationId = 0;
        let currentConsultationStatus = '';
        
        // View consultation details
        function viewConsultation(id) {
            currentConsultationId = id;
            document.getElementById('consultationDetails').innerHTML = 'Loading...';
            document.getElementById('viewConsultationModal').classList.add('active');
            
            // Fetch consultation details via AJAX
            fetch('get_consultation_details.php?id=' + id)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('consultationDetails').innerHTML = data;
                    // Extract status from the returned data
                    const statusElement = document.querySelector('#consultationDetails .status-badge');
                    if (statusElement) {
                        const statusText = statusElement.textContent.trim().toLowerCase();
                        if (statusText.includes('pending')) currentConsultationStatus = 'pending';
                        else if (statusText.includes('confirmed')) currentConsultationStatus = 'confirmed';
                        else if (statusText.includes('completed')) currentConsultationStatus = 'completed';
                        else if (statusText.includes('rejected')) currentConsultationStatus = 'rejected';
                    }
                })
                .catch(error => {
                    document.getElementById('consultationDetails').innerHTML = 'Error loading consultation details: ' + error;
                });
        }
        
        function closeViewModal() {
            document.getElementById('viewConsultationModal').classList.remove('active');
        }
        
        // Update status modal
        function openStatusModal(id, status) {
            document.getElementById('status_consultation_id').value = id;
            document.getElementById('new_status').value = status;
            document.getElementById('updateStatusModal').classList.add('active');
            closeViewModal(); // Close view modal if open
        }
        
        function closeStatusModal() {
            document.getElementById('updateStatusModal').classList.remove('active');
        }
        
        // Delete consultation modal
        function openDeleteModal(id) {
            document.getElementById('delete_consultation_id').value = id;
            document.getElementById('deleteConsultationModal').classList.add('active');
            closeViewModal(); // Close view modal if open
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteConsultationModal').classList.remove('active');
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].classList.remove('active');
                }
            }
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>
