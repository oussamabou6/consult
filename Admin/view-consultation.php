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

// Check if consultation ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION["admin_message"] = "Consultation ID is required.";
    $_SESSION["admin_message_type"] = "error";
    header("Location: consultations.php");
    exit;
}

$consultation_id = intval($_GET['id']);

// Get consultation details
$consultation_sql = "SELECT c.*, 
                    u_client.full_name as client_name, u_client.email as client_email, up_client.profile_image as client_image,
                    u_expert.full_name as expert_name, u_expert.email as expert_email, up_expert.profile_image as expert_image,
                    ep.category as category_id, ep.subcategory as subcategory_id, 
                    cat.name as category_name, subcat.name as subcategory_name,
                    bi.consultation_price, bi.consultation_minutes,
                    cs.id as chat_session_id, 
                    ct.started_at as timer_start_time, ct.ended_at as timer_end_time, ct.duration as timer_duration
                    FROM consultations c
                    JOIN users u_client ON c.client_id = u_client.id
                    LEFT JOIN user_profiles up_client ON u_client.id = up_client.user_id
                    JOIN users u_expert ON c.expert_id = u_expert.id
                    LEFT JOIN user_profiles up_expert ON u_expert.id = up_expert.user_id
                    LEFT JOIN expert_profiledetails ep ON c.expert_id = ep.user_id
                    LEFT JOIN categories cat ON ep.category = cat.id
                    LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                    LEFT JOIN banking_information bi ON ep.id = bi.profile_id
                    LEFT JOIN chat_sessions cs ON c.id = cs.consultation_id
                    LEFT JOIN chat_timers ct ON cs.id = ct.chat_session_id
                    WHERE c.id = ?";

$consultation_stmt = $conn->prepare($consultation_sql);
$consultation_stmt->bind_param("i", $consultation_id);
$consultation_stmt->execute();
$consultation_result = $consultation_stmt->get_result();

if ($consultation_result->num_rows === 0) {
    $_SESSION["admin_message"] = "Consultation not found.";
    $_SESSION["admin_message_type"] = "error";
    header("Location: consultations.php");
    exit;
}

$consultation = $consultation_result->fetch_assoc();
$client_id = $consultation['client_id'];
$expert_id = $consultation['expert_id'];

// Get consultation messages
$messages_sql = "SELECT cm.*, 
                u.full_name, up.profile_image, u.role
                FROM chat_messages cm
                JOIN user_profiles up ON cm.sender_id = up.user_id
                JOIN users u ON cm.sender_id = u.id
                WHERE cm.consultation_id = ?
                ORDER BY cm.created_at ASC";

$messages_stmt = $conn->prepare($messages_sql);
$messages_stmt->bind_param("i", $consultation_id);
$messages_stmt->execute();
$messages_result = $messages_stmt->get_result();

// Get payment information
$payment_sql = "SELECT p.*, p.created_at as payment_date
               FROM payments p WHERE p.consultation_id = ?
               ORDER BY p.created_at DESC
               LIMIT 1";

$payment_stmt = $conn->prepare($payment_sql);
$payment_stmt->bind_param("i", $consultation_id);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$payment = $payment_result->fetch_assoc();

// Calculate consultation duration
$duration = "N/A";
$duration_secodes = 0;

if ($consultation['status'] === 'completed' && !empty($consultation['timer_start_time']) && !empty($consultation['timer_end_time'])) {
    if (!empty($consultation['timer_duration'])) {
        // Use the duration from chat_timers if available
        $duration_secodes = $consultation['timer_duration'];
        $duration_minutes = floor($duration_secodes / 60);

        $duration_hours = floor($duration_minutes / 60);
        $duration_minutes_remainder = $duration_minutes % 60;
        
        $duration = "";
        if ($duration_hours > 0) {
            $duration .= $duration_hours . " hour" . ($duration_hours > 1 ? "s" : "") . " ";
        }else if ($duration_minutes_remainder > 0) {
            $duration .= $duration_minutes_remainder . " minute" . ($duration_minutes_remainder > 1 ? "s" : "") . " ";
        } else if ($duration_secodes > 0) {
            $duration .= $duration_secodes . " second" . ($duration_secodes > 1 ? "s" : "") . " ";
        } else {
            $duration = "0 seconds";
        }
    } else {
        // Fallback to calculating from start and end times
        $start_time = strtotime($consultation['timer_start_time']);
        $end_time = strtotime($consultation['timer_end_time']);
        
        if ($start_time && $end_time) {
            $duration_seconds = $end_time - $start_time;
            $duration_minutes = floor($duration_seconds / 60);
            $duration_hours = floor($duration_minutes / 60);
            $duration_minutes_remainder = $duration_minutes % 60;
            
            $duration = "";
            if ($duration_hours > 0) {
                $duration .= $duration_hours . " hour" . ($duration_hours > 1 ? "s" : "") . " ";
            }
            $duration .= $duration_minutes_remainder . " minute" . ($duration_minutes_remainder > 1 ? "s" : "");
        }
    }
} else if (!empty($consultation['consultation_minutes'])) {
    // If consultation is not completed, show the scheduled duration
    $duration_minutes = $consultation['consultation_minutes'];
    $duration = $duration_minutes . " minutes (scheduled)";
}

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
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> rejected</span>';
        case 'canceled':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Canceled</span>';

        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

// Function to get payment status badge
function getPaymentStatusBadge($status) {
    switch($status) {
        case 'completed':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Completed</span>';
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'processing':
            return '<span class="status-badge status-info"><i class="fas fa-spinner fa-spin"></i> Processing</span>';
        case 'failed':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Failed</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-ban"></i> rejected</span>';
        case 'canceled':
            return '<span class="status-badge status-rejected"><i class="fas fa-ban"></i> Canceled</span>';
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

// Function to format datetime
function formatDateTime($datetime, $format = 'd/m/Y H:i') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

// Get currency from settings
$currency_query = "SELECT setting_value FROM settings WHERE setting_key = 'currency'";
$currency_result = $conn->query($currency_query);
$currency = ($currency_result && $currency_result->num_rows > 0) ? $currency_result->fetch_assoc()['setting_value'] : 'DA';


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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Consultation - <?php echo htmlspecialchars($site_name); ?></title>
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
        .rejection{
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        .btn-outline:hover {
            background-color: var(--light-color);
            border-color: var(--primary-color);
            color: var(--primary-color);
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
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .info-item {
            background-color: var(--light-color);
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }
        
        .info-item:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-color-dark);
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        .info-value {
            color: var(--text-color);
        }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: 500px;
            overflow-y: auto;
            padding: 1rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }
        
        .chat-message {
            display: flex;
            gap: 1rem;
            max-width: 50%;
        }
        
        .chat-message.client {
            align-self: flex-start;
        }
        
        .chat-message.expert {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .chat-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .chat-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .chat-content {
            background-color: var(--card-bg);
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            position: relative;
        }
        
        .chat-message.client .chat-content {
            border-top-left-radius: 0;
        }
        
        .chat-message.expert .chat-content {
            border-top-right-radius: 0;
            background-color: var(--primary-bg);
        }
        
        .chat-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }
        
        .chat-name {
            font-weight: 600;
            color: var(--text-color-dark);
            font-size: 0.875rem;
        }
        
        .chat-time {
            font-size: 0.75rem;
            color: var(--text-color-light);
        }
        
        .chat-text {
            font-size: 0.875rem;
            color: var(--text-color);
            white-space: pre-wrap;
        }
        
        .chat-message.expert .chat-text {
            color: var(--primary-dark);
        }
        
        .chat-date-divider {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin: 1rem 0;
            color: var(--text-color-light);
            font-size: 0.75rem;
        }
        
        .chat-date-divider::before,
        .chat-date-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: var(--border-color);
        }
        
        .payment-info {
            background-color: var(--light-color-2);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }
        
        .payment-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .payment-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .payment-title i {
            color: var(--primary-color);
        }
        
        .payment-details {
            display: flex;
            justify-content: space-between;
        }
        
        .payment-item {
            display: flex;
            flex-direction: column;
        }
        
        .payment-label {
            font-size: 0.75rem;
            color: var(--text-color-light);
            margin-bottom: 0.25rem;
        }
        
        .payment-value {
            font-weight: 600;
            color: var(--text-color-dark);
        }
        
        .payment-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--success-color);
        }
        
        .consultation-details {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .consultation-participant {
            flex: 1;
            min-width: 250px;
            background-color: var(--card-bg);
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            gap: 1rem;
            align-items: center;
            transition: var(--transition);
        }
        
        .consultation-participant:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .participant-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .participant-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }
        
        .participant-info {
            flex: 1;
        }
        
        .participant-name {
            font-weight: 600;
            color: var(--text-color-dark);
            margin-bottom: 0.25rem;
            font-size: 1.1rem;
        }
        
        .participant-email {
            font-size: 0.875rem;
            color: var(--text-color-light);
            margin-bottom: 0.5rem;
        }
        
        .participant-role {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            background-color: var(--primary-bg);
            color: var(--primary-color);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .participant-role.client {
            background-color: var(--success-bg);
            color: var(--success-color);
        }
        
        .consultation-info {
            background-color: var(--light-color-2);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
        }
        
        .consultation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .consultation-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-color-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .consultation-title i {
            color: var(--primary-color);
        }
        
        .consultation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .consultation-item {
            display: flex;
            flex-direction: column;
        }
        
        .consultation-label {
            font-size: 0.75rem;
            color: var(--text-color-light);
            margin-bottom: 0.25rem;
        }
        
        .consultation-value {
            font-weight: 600;
            color: var(--text-color-dark);
        }
        
        .duration-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            background-color: var(--info-bg);
            color: var(--info-color);
            border-radius: var(--radius-full);
            font-weight: 600;
            gap: 0.5rem;
        }
        
        .duration-badge i {
            font-size: 1.1rem;
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
            .info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 992px) {
            .consultation-details {
                flex-direction: column;
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
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .chat-message {
                max-width: 90%;
            }
            
            .consultation-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-details {
                grid-template-columns: 1fr;
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
                <h1><i class="fas fa-comments"></i> Consultation Details</h1>
                <div class="action-buttons">
                    <a href="#" onclick="history.back();" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Consultations
                    </a>
                   
                </div>
            </div>
            
            <?php if (isset($_SESSION["admin_message"])): ?>
                <div class="alert alert-<?php echo $_SESSION["admin_message_type"]; ?>">
                    <i class="fas fa-<?php echo $_SESSION["admin_message_type"] === "success" ? "check-circle" : "exclamation-circle"; ?>"></i>
                    <div><?php echo $_SESSION["admin_message"]; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
                <?php unset($_SESSION["admin_message"]); unset($_SESSION["admin_message_type"]); ?>
            <?php endif; ?>
            
            <!-- Consultation Status -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-info-circle"></i> Consultation Status</h2>
                    <div>
                        <?php echo getConsultationStatusBadge($consultation['status']); ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="consultation-details">
                        <!-- Client Information -->
                        <div class="consultation-participant">
                            <div class="participant-avatar">
                                <?php if (!empty($consultation['client_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="Client">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($consultation['client_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="participant-info">
                                <div class="participant-name"><?php echo htmlspecialchars($consultation['client_name']); ?></div>
                                <div class="participant-email"><?php echo htmlspecialchars($consultation['client_email']); ?></div>
                                <div class="participant-role client">Client</div>
                            </div>
                        </div>
                        
                        <!-- Expert Information -->
                        <div class="consultation-participant">
                            <div class="participant-avatar">
                                <?php if (!empty($consultation['expert_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($consultation['expert_image']); ?>" alt="Expert">
                                <?php else: ?>
                                    <?php echo strtoupper(substr($consultation['expert_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="participant-info">
                                <div class="participant-name"><?php echo htmlspecialchars($consultation['expert_name']); ?></div>
                                <div class="participant-email"><?php echo htmlspecialchars($consultation['expert_email']); ?></div>
                                <div class="participant-role">Expert</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Consultation Information -->
                    <div class="consultation-info">
                        <div class="consultation-header">
                            <div class="consultation-title">
                                <i class="fas fa-calendar-alt"></i> Consultation Details
                            </div>
                            <div class="duration-badge">
                                <i class="fas fa-clock"></i> <?php echo $duration; ?>
                            </div>
                        </div>
                        <div class="consultation-grid">
                            <div class="consultation-item">
                                <div class="consultation-label">Category</div>
                                <div class="consultation-value"><?php echo htmlspecialchars($consultation['category_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="consultation-item">
                                <div class="consultation-label">Subcategory</div>
                                <div class="consultation-value"><?php echo htmlspecialchars($consultation['subcategory_name'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="consultation-item">
                                <div class="consultation-label">Date</div>
                                <div class="consultation-value"><?php echo formatDate($consultation['consultation_date']); ?></div>
                            </div>
                            <div class="consultation-item">
                                <div class="consultation-label">Created</div>
                                <div class="consultation-value"><?php echo formatDateTime($consultation['created_at']); ?></div>
                            </div>
                            <?php if ($consultation['status'] === 'completed'): ?>
                                <div class="consultation-item">
                                    <div class="consultation-label">Start Time</div>
                                    <div class="consultation-value"><?php echo formatDateTime($consultation['timer_start_time'] ?? 'N/A'); ?></div>
                                </div>
                                <div class="consultation-item">
                                    <div class="consultation-label">End Time</div>
                                    <div class="consultation-value"><?php echo formatDateTime($consultation['timer_end_time'] ?? 'N/A'); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($consultation['status'] === 'rejected'): ?>
                                <div class="consultation-item rejection" style="grid-column: 1 / -1 ; background-color:var(--danger-bg); color:var(--danger-color); border-left-color:var(--danger-color);"> 
                                    <div class="consultation-label" style="color: red;font-size: 15px;">Rejection Reason</div>
                                    <div class="consultation-value"><?php echo htmlspecialchars($consultation['rejection_reason'] ?? 'No reason provided'); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Payment Information -->
                    <?php if ($payment): ?>
                    <div class="payment-info">
                        <div class="payment-header">
                            <div class="payment-title">
                                <i class="fas fa-money-bill-wave"></i> Payment Information
                            </div>
                            <div>
                                <?php echo getPaymentStatusBadge($payment['status']); ?>
                            </div>
                        </div>
                        <div class="payment-details">
                            <div class="payment-item">
                                <div class="payment-label">Amount</div>
                                <div class="payment-amount"><?php echo number_format($payment['amount'], 2) . ' ' . $currency; ?></div>
                            </div>
                            <div class="payment-item">
                                <div class="payment-label">Payment ID</div>
                                <div class="payment-value"><?php echo htmlspecialchars($payment['id'] ?? 'N/A'); ?></div>
                            </div>
                            <div class="payment-item">
                                <div class="payment-label">Payment Date</div>
                                <div class="payment-value"><?php echo formatDateTime($payment['payment_date']); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>No payment information found for this consultation.</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Consultation Messages -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-comments"></i> Conversation</h2>
                </div>
                <div class="card-body">
                    <?php if ($messages_result && $messages_result->num_rows > 0): ?>
                        <div class="chat-container">
                            <?php 
                            $current_date = '';
                            while ($message = $messages_result->fetch_assoc()): 
                                $message_date = date('Y-m-d', strtotime($message['created_at']));
                                if ($message_date != $current_date) {
                                    $current_date = $message_date;
                                    echo '<div class="chat-date-divider">' . formatDate($current_date, 'd F Y') . '</div>';
                                }
                                
                                $message_class = $message['role'] === 'expert' ? 'expert' : 'client';
                            ?>
                                <div class="chat-message <?php echo $message_class; ?>">
                                    <div class="chat-avatar">
                                        <?php if (!empty($message['profile_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($message['profile_image']); ?>" alt="<?php echo htmlspecialchars($message['full_name']); ?>">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($message['full_name'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chat-content">
                                        <div class="chat-header">
                                            <div class="chat-name"><?php echo htmlspecialchars($message['full_name']); ?></div>
                                            <div class="chat-time"><?php echo formatTime($message['created_at'], 'H:i'); ?></div>
                                        </div>
                                        <div class="chat-text"><?php echo nl2br(htmlspecialchars($message['message'])); ?></div>
                                        <img src="<?php echo htmlspecialchars($message['file_path']); ?>" alt="" style="max-width: 50%; margin-top: 0.5rem; border-radius: var(--radius);">
                                        

                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem;">
                            <i class="fas fa-comments" style="color: var(--text-color-light); font-size: 3rem; margin-bottom: 1rem;"></i>
                            <p>No messages found for this consultation.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    

    
   
 
    <script>
       
        
        
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
            
            // Scroll to bottom of chat container
            const chatContainer = document.querySelector('.chat-container');
            if (chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
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
