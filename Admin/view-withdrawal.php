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

// Initialize variables
$error_message = "";
$success_message = "";
$withdrawal_id = 0;
$withdrawal = null;
$expert = null;
$banking_info = null;

// Get withdrawal ID from URL
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $withdrawal_id = (int)$_GET['id'];
} else {
    // Redirect to withdrawal requests page if no ID provided
    header("Location: withdrawal-requests.php");
    exit;
}

// Get commission rate from settings
$commission_query = "SELECT setting_value FROM settings WHERE setting_key = 'commission_rate'";
$commission_result = $conn->query($commission_query);
$commission_rate = ($commission_result && $commission_result->num_rows > 0) ? (float)$commission_result->fetch_assoc()['setting_value'] : 10;

// Get currency from settings
$currency_query = "SELECT setting_value FROM settings WHERE setting_key = 'currency'";
$currency_result = $conn->query($currency_query);
$currency = ($currency_result && $currency_result->num_rows > 0) ? $currency_result->fetch_assoc()['setting_value'] : 'DA';

// Process withdrawal status updates if submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    $action = $_POST["action"];
    $admin_notes = isset($_POST["admin_notes"]) ? $_POST["admin_notes"] : "";
    
    // Modify the approval process to deduct the amount from expert's balance
    if ($action == "approve") {
        // Get withdrawal request details
        $get_withdrawal_sql = "SELECT w.*, u.balance, u.id as user_id FROM withdrawal_requests w 
                              JOIN users u ON w.user_id = u.id 
                              WHERE w.id = ?";
        $stmt = $conn->prepare($get_withdrawal_sql);
        $stmt->bind_param("i", $withdrawal_id);
        $stmt->execute();
        $withdrawal_result = $stmt->get_result();
        
        if ($withdrawal_result && $withdrawal_result->num_rows > 0) {
            $withdrawal = $withdrawal_result->fetch_assoc();
            
            // Update withdrawal status to completed
            $update_sql = "UPDATE withdrawal_requests SET status = 'completed', admin_notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $admin_notes, $withdrawal_id);
            
            if ($stmt->execute()) {
                // Deduct the amount from expert's balance
                $expert_id = $withdrawal["user_id"];
                $amount = $withdrawal["amount"];
                $current_balance = $withdrawal["balance"];
                $new_balance = $current_balance - $amount;
                
                // Update expert's balance
                $update_balance_sql = "UPDATE users SET balance = ? WHERE id = ?";
                $stmt = $conn->prepare($update_balance_sql);
                $stmt->bind_param("di", $new_balance, $expert_id);
                $stmt->execute();
                
                // Create notification for the expert
                $notification_message = "Your withdrawal request of " . number_format($amount, 2) . " has been approved.";
                
                $insert_notification_sql = "INSERT INTO expert_notifications (user_id, message, created_at) 
                                          VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($insert_notification_sql);
                $stmt->bind_param("is", $expert_id, $notification_message);
                $stmt->execute();
                
                $success_message = "Withdrawal request #" . $withdrawal_id . " has been approved successfully.";
            } else {
                $error_message = "Error updating withdrawal request: " . $conn->error;
            }
        } else {
            $error_message = "Withdrawal request not found.";
        }
    } elseif ($action == "reject") {
        // Get withdrawal request details
        $get_withdrawal_sql = "SELECT w.*, u.balance, u.id as user_id FROM withdrawal_requests w 
                              JOIN users u ON w.user_id = u.id 
                              WHERE w.id = ?";
        $stmt = $conn->prepare($get_withdrawal_sql);
        $stmt->bind_param("i", $withdrawal_id);
        $stmt->execute();
        $withdrawal_result = $stmt->get_result();
        
        if ($withdrawal_result && $withdrawal_result->num_rows > 0) {
            $withdrawal = $withdrawal_result->fetch_assoc();
            
            // Update withdrawal status to rejected
            $update_sql = "UPDATE withdrawal_requests SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $admin_notes, $withdrawal_id);
            
            if ($stmt->execute()) {
                // Return the amount to the expert's balance
                $expert_id = $withdrawal["user_id"];
                $amount = $withdrawal["amount"];
                $current_balance = $withdrawal["balance"];
                $new_balance = $current_balance + $amount;
                
                $update_balance_sql = "UPDATE users SET balance = ? WHERE id = ?";
                $stmt = $conn->prepare($update_balance_sql);
                $stmt->bind_param("di", $new_balance, $expert_id);
                $stmt->execute();
                
                // Create notification for the expert
                $notification_message = "Your withdrawal request of " . number_format($amount, 2) . " has been rejected. The amount has been returned to your balance.";
                
                $insert_notification_sql = "INSERT INTO expert_notifications (user_id, message, created_at) 
                                          VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($insert_notification_sql);
                $stmt->bind_param("is", $expert_id, $notification_message);
                $stmt->execute();
                
                $success_message = "Withdrawal request #" . $withdrawal_id . " has been rejected and the amount has been returned to the expert's balance.";
            } else {
                $error_message = "Error updating withdrawal request: " . $conn->error;
            }
        } else {
            $error_message = "Withdrawal request not found.";
        }
    }
}

// Check if action is specified in URL (for direct approve/reject links)
if (isset($_GET['action']) && ($_GET['action'] == 'approve' || $_GET['action'] == 'reject')) {
    $action = $_GET['action'];
}

// Get withdrawal request details
$sql = "SELECT w.*, u.full_name, u.email, u.balance, u.id as user_id 
        FROM withdrawal_requests w 
        JOIN users u ON w.user_id = u.id 
        WHERE w.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $withdrawal_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $withdrawal = $result->fetch_assoc();
    
    // Get expert profile details
    $expert_id = $withdrawal['user_id'];
    $expert_sql = "SELECT ep.*, u.full_name, u.email, u.balance, up.phone, up.profile_image 
                  FROM expert_profiledetails ep 
                  JOIN users u ON ep.user_id = u.id 
                  LEFT JOIN user_profiles up ON u.id = up.user_id 
                  WHERE ep.user_id = ?";
    $stmt = $conn->prepare($expert_sql);
    $stmt->bind_param("i", $expert_id);
    $stmt->execute();
    $expert_result = $stmt->get_result();
    
    if ($expert_result && $expert_result->num_rows > 0) {
        $expert = $expert_result->fetch_assoc();
        
        // Get banking information
        $banking_sql = "SELECT * FROM banking_information WHERE user_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($banking_sql);
        $stmt->bind_param("i", $expert_id);
        $stmt->execute();
        $banking_result = $stmt->get_result();
        
        if ($banking_result && $banking_result->num_rows > 0) {
            $banking_info = $banking_result->fetch_assoc();
        }
    }
} else {
    // Withdrawal request not found
    $error_message = "Withdrawal request not found.";
}

// Function to get status badge
function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'processing':
            return '<span class="status-badge status-processing"><i class="fas fa-spinner fa-spin"></i> Processing</span>';
        case 'completed':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Completed</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
        case 'cancelled':
            return '<span class="status-badge status-rejected"><i class="fas fa-ban"></i> Cancelled</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

// Function to format date
function formatDate($date, $format = 'd/m/Y H:i') {
    return date($format, strtotime($date));
}


// Get unread notifications count
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get pending withdrawals count
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending fund requests count
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending expert profile count
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];
 ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Withdrawal Request - <?php echo htmlspecialchars($site_name); ?></title>
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
        
        .card-footer {
            background-color: var(--light-color-2);
            padding: 1.25rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
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
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        .btn-outline:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background-color: var(--primary-bg);
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
        
        .detail-row {
            display: flex;
            margin-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1rem;
        }
        
        .detail-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .detail-label {
            width: 200px;
            font-weight: 600;
            color: var(--text-color-dark);
        }
        
        .detail-value {
            flex: 1;
            color: var(--text-color);
        }
        
        .expert-profile {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }
        
        .expert-avatar {
            width: 120px;
            height: 120px;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 3px solid white;
            flex-shrink: 0;
        }
        
        .expert-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .expert-info {
            flex: 1;
            min-width: 250px;
        }
        
        .expert-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .expert-contact {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .expert-contact-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
        }
        
        .expert-contact-item i {
            color: var(--primary-color);
            width: 20px;
            text-align: center;
        }
        
        .banking-info {
            background-color: var(--light-color-2);
            padding: 1.25rem;
            border-radius: var(--radius);
            margin-top: 1rem;
        }
        
        .banking-info h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .banking-info h3 i {
            color: var(--primary-color);
        }
        
        .amount-card {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        
        .amount-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='rgba(255,255,255,0.1)' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.3;
            z-index: 0;
        }
        
        .amount-label {
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .amount-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .amount-info {
            font-size: 0.875rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .amount-row:last-child {
            margin-bottom: 0;
            padding-top: 0.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .notes-form {
            margin-top: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-color-dark);
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.95rem;
            color: var(--text-color);
            background-color: white;
            transition: var(--transition-fast);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
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
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            font-weight: 500;
            transition: var(--transition);
        }
        
        .back-button:hover {
            transform: translateX(-5px);
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
            
            .expert-profile {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .expert-info {
                width: 100%;
            }
            
            .expert-contact {
                align-items: center;
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
 <a href="withdrawal-requests.php" class="menu-item active">
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
            <a href="withdrawal-requests.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Withdrawal Requests
            </a>
            
            <div class="header">
                <h1><i class="fas fa-money-bill-wave"></i> Withdrawal Request Details</h1>
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
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success_message; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error_message; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if ($withdrawal): ?>
                <?php 
                    $commission_amount = $withdrawal['amount'] * ($commission_rate / 100);
                    $final_amount = $withdrawal['amount'] - $commission_amount;
                ?>
                
                <!-- Amount Card -->
                <div class="amount-card">
                    <div class="amount-label">Withdrawal Amount</div>
                    <div class="amount-value"><?php echo number_format($withdrawal['amount'], 2); ?> <?php echo $currency; ?></div>
                    
                    <div class="amount-row">
                        <div>Commission (<?php echo $commission_rate; ?>%)</div>
                        <div>-<?php echo number_format($commission_amount, 2); ?> <?php echo $currency; ?></div>
                    </div>
                    
                    <div class="amount-row">
                        <div><strong>Final Amount</strong></div>
                        <div><strong><?php echo number_format($final_amount, 2); ?> <?php echo $currency; ?></strong></div>
                    </div>
                </div>
                
                <!-- Withdrawal Details Card -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-info-circle"></i> Withdrawal Request #<?php echo $withdrawal['id']; ?></h2>
                        <div><?php echo getStatusBadge($withdrawal['status']); ?></div>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <div class="detail-label">Request ID</div>
                            <div class="detail-value">#<?php echo $withdrawal['id']; ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Status</div>
                            <div class="detail-value"><?php echo getStatusBadge($withdrawal['status']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Request Date</div>
                            <div class="detail-value"><?php echo formatDate($withdrawal['created_at']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Last Updated</div>
                            <div class="detail-value"><?php echo formatDate($withdrawal['updated_at']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Expert Notes</div>
                            <div class="detail-value"><?php echo !empty($withdrawal['notes']) ? htmlspecialchars($withdrawal['notes']) : '<em>No notes provided</em>'; ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Admin Notes</div>
                            <div class="detail-value"><?php echo !empty($withdrawal['admin_notes']) ? htmlspecialchars($withdrawal['admin_notes']) : '<em>No admin notes</em>'; ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Expert Details Card -->
                <?php if ($expert): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-user-tie"></i> Expert Details</h2>
                    </div>
                    <div class="card-body">
                        <div class="expert-profile">
                            <div class="expert-avatar">
                                <?php if (!empty($expert['profile_image']) && file_exists($expert['profile_image'])): ?>
                                    <img src="<?php echo $expert['profile_image']; ?>" alt="<?php echo htmlspecialchars($expert['full_name']); ?>">
                                <?php else: ?>
                                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--primary-gradient); color: white; font-size: 3rem;">
                                        <?php echo strtoupper(substr($expert['full_name'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="expert-info">
                                <div class="expert-name"><?php echo htmlspecialchars($expert['full_name']); ?></div>
                                <div class="expert-contact">
                                    <div class="expert-contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($expert['email']); ?></span>
                                    </div>
                                    <?php if (!empty($expert['phone'])): ?>
                                    <div class="expert-contact-item">
                                        <i class="fas fa-  ?>
                                    <div class="expert-contact-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($expert['phone']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="expert-contact-item">
                                    <i class="fas fa-wallet"></i>
                                    <span>Current Balance: <strong><?php echo number_format($expert['balance'], 2); ?> <?php echo $currency; ?></strong></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Banking Information -->
                        <?php if ($banking_info): ?>
                        <div class="banking-info">
                            <h3><i class="fas fa-university"></i> Banking Information</h3>
                            <div class="detail-row">
                                <div class="detail-label">CCP Number</div>
                                <div class="detail-value"><?php echo htmlspecialchars($banking_info['ccp']); ?></div>
                            </div>
                            <div class="detail-row">
                                <div class="detail-label">CCP Key</div>
                                <div class="detail-value"><?php echo htmlspecialchars($banking_info['ccp_key']); ?></div>
                            </div>
                            <?php if (!empty($banking_info['check_file_path']) && file_exists($banking_info['check_file_path'])): ?>
                            <div class="detail-row">
                                <div class="detail-label">Check Image</div>
                                <div class="detail-value">
                                    <a href="<?php echo $banking_info['check_file_path']; ?>" target="_blank" class="btn btn-outline">
                                        <i class="fas fa-file-image"></i> View Check Image
                                    </a>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <div class="banking-info">
                            <h3><i class="fas fa-exclamation-triangle"></i> No Banking Information</h3>
                            <p>This expert has not submitted their banking information yet.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Action Form -->
                <?php if ($withdrawal['status'] == 'pending'): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-tasks"></i> Process Withdrawal Request</h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="admin_notes" class="form-label">Admin Notes</label>
                                <textarea id="admin_notes" name="admin_notes" class="form-control" placeholder="Add notes about this withdrawal request (optional)"><?php echo isset($_POST['admin_notes']) ? htmlspecialchars($_POST['admin_notes']) : ''; ?></textarea>
                            </div>
                            
                            <div class="card-footer">
                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                    <i class="fas fa-check"></i> Approve Withdrawal
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger">
                                    <i class="fas fa-times"></i> Reject Withdrawal
                                </button>
                                <a href="withdrawal-requests.php" class="btn btn-outline">
                                    <i class="fas fa-arrow-left"></i> Back to List
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card-footer">
                    <a href="withdrawal-requests.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Back to Withdrawal Requests
                    </a>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="card">
                    <div class="card-body">
                        <div style="text-align: center; padding: 2rem;">
                            <i class="fas fa-exclamation-circle" style="font-size: 3rem; color: var(--danger-color); margin-bottom: 1rem;"></i>
                            <h3>Withdrawal Request Not Found</h3>
                            <p>The withdrawal request you are looking for does not exist or has been deleted.</p>
                            <a href="withdrawal-requests.php" class="btn btn-primary" style="margin-top: 1rem;">
                                <i class="fas fa-arrow-left"></i> Back to Withdrawal Requests
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
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
            
            // Confirm before approving or rejecting
            const approveBtn = document.querySelector('button[value="approve"]');
            const rejectBtn = document.querySelector('button[value="reject"]');
            
            if (approveBtn) {
                approveBtn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to approve this withdrawal request?')) {
                        e.preventDefault();
                    }
                });
            }
            
            if (rejectBtn) {
                rejectBtn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to reject this withdrawal request? The amount will be returned to the expert\'s balance.')) {
                        e.preventDefault();
                    }
                });
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
