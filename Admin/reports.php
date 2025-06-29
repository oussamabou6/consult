<?php
// Start the session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Redirect to login page if not logged in as admin
    header("Location: ../config/logout");
    exit;
}

// Add this code to update user status to "Online" when they log in
$user_id = $_SESSION["user_id"];
$update_status_sql = "UPDATE users SET status = 'Online' WHERE id = ?";
$update_status_stmt = $conn->prepare($update_status_sql);
$update_status_stmt->bind_param("i", $user_id);
$update_status_stmt->execute();

// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Get currency from settings
$currency_query = "SELECT setting_value FROM settings WHERE setting_key = 'currency'";
$currency_result = $conn->query($currency_query);
$currency = ($currency_result && $currency_result->num_rows > 0) ? $currency_result->fetch_assoc()['setting_value'] : 'DA';

// Function to suspend user and send notifications
function suspendUser($conn, $user_id, $site_name) {
    // Calculate suspension end date (30 days from now)
    $suspension_end_date = date('Y-m-d H:i:s', strtotime('+30 days'));
    
    // Update user status
    $update_user_status_sql = "UPDATE users SET status = 'suspended', suspension_end_date = ? WHERE id = ?";
    $update_user_status_stmt = $conn->prepare($update_user_status_sql);
    $update_user_status_stmt->bind_param("si", $suspension_end_date, $user_id);
    $update_user_status_stmt->execute();
    
    // Add suspension record
    $suspension_reason = "Excessive reports";
    $insert_suspension_sql = "INSERT INTO user_suspensions (user_id, start_date, end_date, reason, active) 
                             VALUES (?, NOW(), ?, ?, 1)";
    $insert_suspension_stmt = $conn->prepare($insert_suspension_sql);
    $insert_suspension_stmt->bind_param("iss", $user_id, $suspension_end_date, $suspension_reason);
    $insert_suspension_stmt->execute();
    
   
    
    // Send email notification
    // Get user email
    $user_query = "SELECT email, full_name FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    
    if ($user) {
        // Include mailer
        require_once 'utils/mailer.php';
        
        // Send suspension email
        $email = $user['email'];
        $name = $user['full_name'];
        $subject = "Account Suspension Notice";
        $message = "Dear $name,\n\nYour account has been suspended for 30 days due to multiple reports against you. The suspension will end on " . date('Y-m-d', strtotime($suspension_end_date)) . ".\n\nYour account will be automatically reactivated after this period.";
        
        // Use the sendVerificationEmail function (assuming it can be used for general emails)
        sendVerificationEmail($email, $name, $message, $site_name);
    }
    
    return $suspension_end_date;
}

// Function to check if user should be suspended
function checkUserForSuspension($conn, $user_id, $site_name) {
    // Check if reported user has reached report threshold
    $reported_reports_query = "SELECT COUNT(*) as report_count FROM reports 
                              WHERE reported_id = ? 
                              AND status IN ('accepted', 'remborser') 
                              AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $reported_reports_stmt = $conn->prepare($reported_reports_query);
    $reported_reports_stmt->bind_param("i", $user_id);
    $reported_reports_stmt->execute();
    $reported_reports_result = $reported_reports_stmt->get_result();
    $reported_reports = $reported_reports_result->fetch_assoc();
    
    if ($reported_reports['report_count'] >= 15) {
        // Suspend user
        $suspension_end_date = suspendUser($conn, $user_id, $site_name);
        return true;
    }
    
    return false;
}

// Process report actions
if (isset($_POST['action'])) {
    $report_id = $_POST['report_id'];
    $action = $_POST['action'];
    $admin_notes = isset($_POST['admin_notes']) ? $_POST['admin_notes'] : '';
    
    if ($action === 'accept') {
        // Get report details
        $report_query = "SELECT r.*, c.expert_id, c.client_id, p.amount 
                         FROM reports r 
                         JOIN consultations c ON r.consultation_id = c.id 
                         LEFT JOIN payments p ON c.id = p.consultation_id 
                         WHERE r.id = ?";
        $report_stmt = $conn->prepare($report_query);
        $report_stmt->bind_param("i", $report_id);
        $report_stmt->execute();
        $report_result = $report_stmt->get_result();
        $report = $report_result->fetch_assoc();
        
        // Update report status
        $update_sql = "UPDATE reports SET status = 'accepted', admin_notes = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $admin_notes, $report_id);
        $update_stmt->execute();
        
        // Send notifications to both parties
        $reporter_id = $report['reporter_id'];
        $reported_id = $report['reported_id'];
        
        $reporter_notification = "Your report has been updated to: Accepted.";
        $insert_reporter_notification = "INSERT INTO client_notifications (user_id, message, is_read) VALUES (?, ?, 0)";
        $reporter_stmt = $conn->prepare($insert_reporter_notification);
        $reporter_stmt->bind_param("is", $reporter_id, $reporter_notification);
        $reporter_stmt->execute();
        
        // Check if user should be suspended
        checkUserForSuspension($conn, $reported_id, $site_name);
        
        // Set success message
        $_SESSION["admin_message"] = "Report has been Accepted.";
        $_SESSION["admin_message_type"] = "success";
    } 
    else if ($action === 'decline') {
        // Update report status
        $update_sql = "UPDATE reports SET status = 'rejected', admin_notes = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("si", $admin_notes, $report_id);
        $update_stmt->execute();
        
        // Get report details
        $report_query = "SELECT reporter_id, reported_id FROM reports WHERE id = ?";
        $report_stmt = $conn->prepare($report_query);
        $report_stmt->bind_param("i", $report_id);
        $report_stmt->execute();
        $report_result = $report_stmt->get_result();
        $report = $report_result->fetch_assoc();
        
        // Send notifications to reporter
        $reporter_id = $report['reporter_id'];
        $reported_id = $report['reported_id'];
        
        $reporter_notification = "Your report has been updated to: Rejected.";
        $insert_reporter_notification = "INSERT INTO client_notifications (user_id, message, is_read) VALUES (?, ?, 0)";
        $reporter_stmt = $conn->prepare($insert_reporter_notification);
        $reporter_stmt->bind_param("is", $reporter_id, $reporter_notification);
        $reporter_stmt->execute();
        
        
        
        // Set success message
        $_SESSION["admin_message"] = "Report has been rejected.";
        $_SESSION["admin_message_type"] = "success";
    }
    else if ($action === 'remborser') {
        // Get report details
        $report_query = "SELECT r.*, c.expert_id, c.client_id, p.amount, p.id as payment_id 
                         FROM reports r 
                         JOIN consultations c ON r.consultation_id = c.id 
                         LEFT JOIN payments p ON c.id = p.consultation_id 
                         WHERE r.id = ? AND p.status = 'completed'";
        $report_stmt = $conn->prepare($report_query);
        $report_stmt->bind_param("i", $report_id);
        $report_stmt->execute();
        $report_result = $report_stmt->get_result();
        
        if ($report_result->num_rows > 0) {
            $report = $report_result->fetch_assoc();
            $payment_amount = $report['amount'];
            $expert_id = $report['expert_id'];
            $client_id = $report['client_id'];
            $reported_id = $report['reported_id'];
            
            // Begin transaction
            $conn->begin_transaction();
            
            try {
                // Update report status
                $update_sql = "UPDATE reports SET status = 'remborser', admin_notes = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $admin_notes, $report_id);
                $update_stmt->execute();
                
                // Deduct amount from expert's balance
                $update_expert_sql = "UPDATE users SET balance = balance - ? WHERE id = ?";
                $update_expert_stmt = $conn->prepare($update_expert_sql);
                $update_expert_stmt->bind_param("di", $payment_amount, $expert_id);
                $update_expert_stmt->execute();
                
                // Add amount to client's balance
                $update_client_sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
                $update_client_stmt = $conn->prepare($update_client_sql);
                $update_client_stmt->bind_param("di", $payment_amount, $client_id);
                $update_client_stmt->execute();
                
                // Send notifications to both parties
                $expert_notification = "A report against you has been accepted and a refund of " . number_format($payment_amount, 2) . " " . $currency . " has been processed. Payment has been reimbursed. This is a warning. Please contact support if you have any questions.";
                $insert_expert_notification = "INSERT INTO expert_notifications (user_id, message, is_read) VALUES (?, ?, 0)";
                $expert_stmt = $conn->prepare($insert_expert_notification);
                $expert_stmt->bind_param("is", $expert_id, $expert_notification);
                $expert_stmt->execute();
                
                $client_notification = "Your report has been accepted and a refund of " . number_format($payment_amount, 2) . " " . $currency . " has been processed. Payment has been reimbursed.";
                $insert_client_notification = "INSERT INTO client_notifications (user_id, message, is_read) VALUES (?, ?, 0)";
                $client_stmt = $conn->prepare($insert_client_notification);
                $client_stmt->bind_param("is", $client_id, $client_notification);
                $client_stmt->execute();
                
                // Check if user should be suspended
                checkUserForSuspension($conn, $reported_id, $site_name);
                
                // Commit transaction
                $conn->commit();
                
                // Set success message
                $_SESSION["admin_message"] = "Report has been accepted and payment has been reimbursed.";
                $_SESSION["admin_message_type"] = "success";
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                
                // Set error message
                $_SESSION["admin_message"] = "Error processing refund: " . $e->getMessage();
                $_SESSION["admin_message_type"] = "error";
            }
        } else {
            // No valid payment found
            $_SESSION["admin_message"] = "No valid payment found for this consultation.";
            $_SESSION["admin_message_type"] = "error";
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: reports.php");
    exit;
}

// Rest of the code remains unchanged...

// Get reports from database
$reports_query = "SELECT r.*, 
                 c.expert_id, c.client_id, c.consultation_date, c.consultation_time, 
                 ue.full_name as expert_name, ue.email as expert_email, 
                 uc.full_name as client_name, uc.email as client_email,
                 p.amount as payment_amount,
                 (SELECT profile_image FROM user_profiles WHERE user_id = r.reporter_id) as reporter_image,
                 (SELECT profile_image FROM user_profiles WHERE user_id = r.reported_id) as reported_image
                 FROM reports r
                 JOIN consultations c ON r.consultation_id = c.id
                 JOIN users ue ON c.expert_id = ue.id
                 JOIN users uc ON c.client_id = uc.id
                 LEFT JOIN payments p ON c.id = p.consultation_id AND p.status = 'completed'
                 ORDER BY r.created_at DESC";
$reports_result = $conn->query($reports_query);

// Count reports by status
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];
$accepted_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'accepted'")->fetch_assoc()['count'];
$rejected_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'rejected'")->fetch_assoc()['count'];
$remborser_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'remborser'")->fetch_assoc()['count'];

// Function to get report status badge
function getReportStatusBadge($status) {
    switch($status) {
        case 'handled':
            return '<span class="status-badge status-handled"><i class="fas fa-check-circle"></i> Handled</span>';
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'accepted':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Accepted</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
        case 'remborser':
            return '<span class="status-badge status-info"><i class="fas fa-money-bill-wave"></i> Refunded</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

// Function to get report count badge
function getReportCountBadge($count) {
    if ($count == 0) {
        return '<span class="report-count report-count-green"><span class="count-number">' . $count . '</span> reports</span>';
    } else if ($count >= 1 && $count <= 7) {
        return '<span class="report-count report-count-orange"><span class="count-number">' . $count . '</span> reports</span>';
    } else {
        // Calculate color intensity based on count (8-14 range)
        $intensity = min(100, 50 + ($count - 8) * 5); // 50% at 8 reports, increasing to 100% at 14+
        return '<span class="report-count" style="background-color: rgba(239, 68, 68, ' . $intensity/100 . '); color: white;"><span class="count-number">' . $count . '</span> reports</span>';
    }
}

// Function to format date
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Function to get time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'A few seconds ago';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports Management - <?php echo htmlspecialchars($site_name); ?></title>
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
        
.dashboard-overview {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

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

.stat-card.pending::before {
    background: var(--warning-gradient);
}

.stat-card.approved::before {
    background: var(--success-gradient);
}

.stat-card.rejected::before {
    background: var(--danger-gradient);
}

.stat-card.refunded::before {
    background: var(--info-gradient);
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

.stat-card.pending .stat-card-title i {
    color: var(--warning-color);
}

.stat-card.approved .stat-card-title i {
    color: var(--success-color);
}

.stat-card.rejected .stat-card-title i {
    color: var(--danger-color);
}

.stat-card.refunded .stat-card-title i {
    color: var(--info-color);
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-card.pending .stat-card-value {
    color: var(--warning-color);
}

.stat-card.approved .stat-card-value {
    color: var(--success-color);
}

.stat-card.rejected .stat-card-value {
    color: var(--danger-color);
}

.stat-card.refunded .stat-card-value {
    color: var(--info-color);
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
        
        .card-header a {
            color: var(--primary-color);
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: var(--transition);
        }
        
        .card-header a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-footer {
            background-color: var(--light-color);
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1rem;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 1.5rem;
        }
        
        .report-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }
        
        .report-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
            border-color: var(--primary-color);
        }
        
        .report-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(255, 255, 255, 0.8));
        }
        
        .report-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .report-type {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }
        
        .report-type.payment {
            background-color: var(--warning-bg);
            color: var(--warning-color);
        }
        
        .report-type.inappropriate {
            background-color: var(--danger-bg);
            color: var(--danger-color);
        }
        
        .report-type.technical {
            background-color: var(--info-bg);
            color: var(--info-color);
        }
        
        .report-type.quality {
            background-color: var(--secondary-bg);
            color: var(--secondary-color);
        }
        
        .report-type.other {
            background-color: var(--light-color-3);
            color: var(--text-color);
        }
        
        .report-content {
            padding: 1.25rem;
            flex: 1;
        }
        
        .report-users {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .report-user {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 1rem;
            border-radius: var(--radius);
            background-color: var(--light-color-2);
            position: relative;
        }
        
        .report-user.reporter {
            background-color: var(--primary-bg);
        }
        
        .report-user.reported {
            background-color: var(--danger-bg);
        }
        
        .report-user-avatar {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            overflow: hidden;
            margin-bottom: 0.75rem;
            border: 2px solid white;
            box-shadow: var(--shadow);
        }
        
        .report-user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .report-user-name {
            font-weight: 600;
            font-size: 1rem;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        
        .report-user-email {
            font-size: 0.8rem;
            color: var(--text-color-light);
            margin-bottom: 0.75rem;
        }
        
        .report-user-role {
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.2rem 0.5rem;
            border-radius: var(--radius-full);
            background-color: var(--light-color-3);
            color: var(--text-color);
            margin-bottom: 0.75rem;
        }
        
        .report-user-role.expert {
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }
        
        .report-user-role.client {
            background-color: var(--success-bg);
            color: var(--success-color);
        }
        
        .report-payment {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: var(--radius-full);
            background-color: var(--warning-bg);
            color: var(--warning-color);
        }
        
        .report-message {
            margin-bottom: 1.25rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .report-message h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .report-message p {
            font-size: 0.9rem;
            color: var(--text-color);
            padding: 0.75rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            border-left: 3px solid var(--primary-color);
        }
        
        .report-admin-notes {
            margin-bottom: 1.25rem;
        }
        
        .report-admin-notes h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .report-admin-notes p {
            font-size: 0.9rem;
            color: var(--text-color);
            padding: 0.75rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            border-left: 3px solid var(--secondary-color);
        }
        
        .report-admin-notes textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            resize: vertical;
            min-height: 80px;
            font-family: inherit;
            font-size: 0.9rem;
            color: var(--text-color);
            margin-top: 0.5rem;
        }
        
        .report-admin-notes textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }
        
        .report-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8rem;
            color: var(--text-color-light);
            margin-bottom: 0.75rem;
        }
        
        .report-date {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-date i {
            color: var(--primary-color);
        }
        
        .report-consultation {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-consultation i {
            color: var(--info-color);
        }
        
        .report-footer {
            padding: 1.25rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: var(--light-color-2);
        }
        
        .report-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .report-actions {
            display: flex;
            gap: 0.5rem;
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
        .status-handled{
                        background: var(--info-gradient);

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

        /* Report count badges */
        .report-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
            animation: pulse 2s infinite;
        }

        .report-count-green {
            background-color: var(--success-color);
            color: white;
        }

        .report-count-orange {
            background-color: var(--warning-color);
            color: white;
        }

        .report-count-red {
            background-color: var(--danger-color);
            color: white;
        }

        .count-number {
            display: inline-block;
            animation: countBounce 1s ease infinite;
            margin-right: 0.25rem;
        }

        @keyframes countBounce {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.3);
            }
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
        
        /* Modal styles */
        .modal-overlay {
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
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        
        .modal-overlay.active .modal {
            transform: translateY(0);
            opacity: 1;
        }
        
        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-color-light);
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition-fast);
        }
        
        .modal-close:hover {
            color: var(--danger-color);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 1.25rem;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 1.25rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }
        
        .chat-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            max-height: 400px;
            overflow-y: auto;
            padding: 0.5rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
        }
        
        .chat-message {
            display: flex;
            gap: 0.75rem;
            max-width: 80%;
        }
        
        .chat-message.client {
            align-self: flex-start;
        }
        
        .chat-message.expert {
            align-self: flex-end;
            flex-direction: row-reverse;
        }
        
        .chat-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .chat-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .chat-bubble {
            padding: 0.75rem 1rem;
            border-radius: var(--radius-lg);
            position: relative;
            box-shadow: var(--shadow-sm);
        }
        
        .chat-message.client .chat-bubble {
            background-color: white;
            border-bottom-left-radius: 0;
        }
        
        .chat-message.expert .chat-bubble {
            background-color: var(--primary-bg);
            border-bottom-right-radius: 0;
        }
        
        .chat-content {
            font-size: 0.9rem;
            color: var(--text-color);
        }
        
        .chat-time {
            font-size: 0.7rem;
            color: var(--text-color-light);
            margin-top: 0.25rem;
            text-align: right;
        }
        
        .chat-image {
            max-width: 200px;
            border-radius: var(--radius);
            margin-top: 0.5rem;
        }
        
        .chat-file {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background-color: var(--light-color-3);
            border-radius: var(--radius);
            margin-top: 0.5rem;
        }
        
        .chat-file i {
            color: var(--primary-color);
        }
        
        .chat-file-name {
            font-size: 0.8rem;
            color: var(--text-color);
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chat-file-download {
            font-size: 0.8rem;
            color: var(--primary-color);
            text-decoration: none;
        }
        
        .chat-file-download:hover {
            text-decoration: underline;
        }
        
        .consultation-info {
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .consultation-info-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .consultation-info-label {
            font-weight: 500;
            color: var(--text-color-dark);
        }
        
        .consultation-info-value {
            color: var(--text-color);
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: var(--text-color-light);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }
        
        .tab.active {
            color: var(--primary-color);
        }
        
        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
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
        
        .pulse {
            animation: pulse 2s infinite;
        }
        
        @media (max-width: 1200px) {
            .report-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .dashboard-overview {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .report-grid {
                grid-template-columns: 1fr;
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
            
            .dashboard-overview {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .user-info {
                width: 100%;
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
                <a href="reports.php" class="menu-item active">
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
                <h1><i class="fas fa-flag"></i> Reports Management</h1>
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
            
            <?php if (isset($_SESSION["admin_message"])): ?>
                <div class="alert alert-<?php echo $_SESSION["admin_message_type"]; ?>">
                    <i class="fas fa-<?php echo $_SESSION["admin_message_type"] === "success" ? "check-circle" : "exclamation-circle"; ?>"></i>
                    <div><?php echo $_SESSION["admin_message"]; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
                <?php unset($_SESSION["admin_message"]); unset($_SESSION["admin_message_type"]); ?>
            <?php endif; ?>
            
            <!-- Reports Stats -->
            <h2 style="margin-bottom: 1rem; color: var(--dark-color); font-size: 1.25rem;">Reports Statistics</h2>
            <div class="dashboard-grid">
                <div class="stat-card pending">
                    <div class="stat-card-title">
                        <i class="fas fa-clock"></i> Pending Reports
                    </div>
                    <div class="stat-card-value"><?php echo $pending_reports; ?></div>
                    <div class="stat-card-desc">Reports awaiting review</div>
                </div>
                
                <div class="stat-card approved">
                    <div class="stat-card-title">
                        <i class="fas fa-check-circle"></i> Accepted Reports
                    </div>
                    <div class="stat-card-value"><?php echo $accepted_reports; ?></div>
                    <div class="stat-card-desc">Reports that were accepted</div>
                </div>
                
                <div class="stat-card rejected">
                    <div class="stat-card-title">
                        <i class="fas fa-times-circle"></i> Rejected Reports
                    </div>
                    <div class="stat-card-value"><?php echo $rejected_reports; ?></div>
                    <div class="stat-card-desc">Reports that were rejected</div>
                </div>
                
                <div class="stat-card refunded">
                    <div class="stat-card-title">
                        <i class="fas fa-money-bill-wave"></i> Refunded Reports
                    </div>
                    <div class="stat-card-value"><?php echo $remborser_reports; ?></div>
                    <div class="stat-card-desc">Reports with refunded payments</div>
                </div>
            </div>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" data-tab="all">All Reports</div>
                <div class="tab" data-tab="expert">Expert Reports</div>
                <div class="tab" data-tab="client">Client Reports</div>
                <div class="tab" data-tab="pending">Pending Reports</div>
            </div>
            
            <!-- Reports Grid -->
            <div class="tab-content active" id="all-tab">
                <div class="report-grid">
                    <?php if ($reports_result && $reports_result->num_rows > 0): ?>
                        <?php while ($report = $reports_result->fetch_assoc()): ?>
                            <?php
                            // Get report counts for each user
                            $reporter_id = $report['reporter_id'];
                            $reported_id = $report['reported_id'];
                            
                            $reporter_reports_query = "SELECT COUNT(*) as count FROM reports WHERE reporter_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
                            $reporter_reports_stmt = $conn->prepare($reporter_reports_query);
                            $reporter_reports_stmt->bind_param("i", $reporter_id);
                            $reporter_reports_stmt->execute();
                            $reporter_reports_result = $reporter_reports_stmt->get_result();
                            $reporter_reports_count = $reporter_reports_result->fetch_assoc()['count'];
                            
                            $reported_reports_query = "SELECT COUNT(*) as count FROM reports r 
                                                      JOIN consultations c ON r.consultation_id = c.id 
                                                      WHERE (c.expert_id = ? OR c.client_id = ?) 
                                                      AND r.status IN ('accepted', 'remborser') 
                                                      AND r.created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
                            $reported_reports_stmt = $conn->prepare($reported_reports_query);
                            $reported_reports_stmt->bind_param("ii", $reported_id, $reported_id);
                            $reported_reports_stmt->execute();
                            $reported_reports_result = $reported_reports_stmt->get_result();
                            $reported_reports_count = $reported_reports_result->fetch_assoc()['count'];

                            // Get total accepted/refunded reports for expert (regardless of consultation)
                            $expert_reports_query = "SELECT COUNT(*) as count FROM reports r 
                                                    JOIN consultations c ON r.consultation_id = c.id 
                                                    WHERE reported_id = ? 
                                                    AND r.status IN ('accepted', 'remborser')";
                            $expert_reports_stmt = $conn->prepare($expert_reports_query);
                            $expert_reports_stmt->bind_param("i", $reported_id);
                            $expert_reports_stmt->execute();
                            $expert_reports_result = $expert_reports_stmt->get_result();
                            $expert_reports_count = $expert_reports_result->fetch_assoc()['count'];

                            // Get total accepted reports for client (regardless of consultation)
                            $client_reports_query = "SELECT COUNT(*) as count FROM reports r 
                                                    JOIN consultations c ON r.consultation_id = c.id 
                                                    WHERE reported_id = ? 
                                                    AND r.status = 'accepted'";
                            $client_reports_stmt = $conn->prepare($client_reports_query);
                            $client_reports_stmt->bind_param("i", $reported_id);
                            $client_reports_stmt->execute();
                            $client_reports_result = $client_reports_stmt->get_result();
                            $client_reports_count = $client_reports_result->fetch_assoc()['count'];
                            
                            // Determine if reporter is expert or client
                            $is_reporter_expert = ($report['expert_id'] == $report['reporter_id']);
                            $is_reported_expert = ($report['expert_id'] == $report['reported_id']);
                            ?>
                            <div class="report-card" data-type="<?php echo $is_reporter_expert ? 'expert' : 'client'; ?>" data-status="<?php echo $report['status']; ?>">
                                <div class="report-header">
                                    <h3>Report #<?php echo $report['id']; ?></h3>
                                    <span class="report-type <?php echo $report['report_type']; ?>"><?php echo ucfirst($report['report_type']); ?></span>
                                </div>
                                <div class="report-content">
                                    <div class="report-meta">
                                        <div class="report-date">
                                            <i class="fas fa-calendar-alt"></i>
                                            <span><?php echo timeAgo($report['created_at']); ?></span>
                                        </div>
                                        <div class="report-consultation">
                                            <i class="fas fa-handshake"></i>
                                            <span>Consultation #<?php echo $report['consultation_id']; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="report-users">
                                        <div class="report-user reporter">
                                            <div class="report-user-avatar">
                                                <?php if ($report['reporter_image']): ?>
                                                    <img src="<?php echo $report['reporter_image']; ?>" alt="Reporter">
                                                <?php else: ?>
                                                    <img src="/placeholder.svg?height=100&width=100" alt="Reporter">
                                                <?php endif; ?>
                                            </div>
                                            <div class="report-user-name">
                                                <?php echo $is_reporter_expert ? $report['expert_name'] : $report['client_name']; ?>
                                            </div>
                                            <div class="report-user-email">
                                                <?php echo $is_reporter_expert ? $report['expert_email'] : $report['client_email']; ?>
                                            </div>
                                            <div class="report-user-role <?php echo $is_reporter_expert ? 'expert' : 'client'; ?>">
                                                <?php echo $is_reporter_expert ? 'Expert' : 'Client'; ?>
                                            </div>
                                            
                                            <a href="view-user.php?id=<?php echo $report['reporter_id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-user"></i> View Profile
                                            </a>
                                        </div>
                                        
                                        <div class="report-user reported">
                                            <div class="report-user-avatar">
                                                <?php if ($report['reported_image']): ?>
                                                    <img src="<?php echo $report['reported_image']; ?>" alt="Reported">
                                                <?php else: ?>
                                                    <img src="/placeholder.svg?height=100&width=100" alt="Reported">
                                                <?php endif; ?>
                                            </div>
                                            <div class="report-user-name">
                                                <?php echo $is_reported_expert ? $report['expert_name'] : $report['client_name']; ?>
                                            </div>
                                            <div class="report-user-email">
                                                <?php echo $is_reported_expert ? $report['expert_email'] : $report['client_email']; ?>
                                            </div>
                                            <div class="report-user-role <?php echo $is_reported_expert ? 'expert' : 'client'; ?>">
                                                <?php echo $is_reported_expert ? 'Expert' : 'Client'; ?>
                                            </div>
                                            <?php if ($is_reported_expert): ?>
                                                <?php echo getReportCountBadge($expert_reports_count); ?> 
                                                </span>
                                            <?php else: ?>
                                                 <?php echo getReportCountBadge($client_reports_count); ?>
                                                </span>
                                            <?php endif; ?>
                                            <a href="view-user.php?id=<?php echo $report['reported_id']; ?>" class="btn btn-sm btn-outline">
                                                <i class="fas fa-user"></i> View Profile
                                            </a>
                                            <?php if ($report['payment_amount']): ?>
                                                <div class="report-payment">
                                                    <?php echo number_format($report['payment_amount'], 2) . ' ' . $currency; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="report-message">
                                        <h4>Report Message:</h4>
                                        <p><?php echo htmlspecialchars($report['message']); ?></p>
                                    </div>
                                    
                                    <?php if ($report['admin_notes'] && $report['status'] !== 'pending'): ?>
                                        <div class="report-admin-notes">
                                            <h4>Admin Notes:</h4>
                                            <p><?php echo htmlspecialchars($report['admin_notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($report['status'] === 'pending'): ?>
                                        <div class="report-admin-notes">
                                            <h4>Admin Notes:</h4>
                                            <form id="admin-notes-form-<?php echo $report['id']; ?>" method="post">
                                                <input type="hidden" name="report_id" value="<?php echo $report['id']; ?>">
                                                <textarea name="admin_notes" placeholder="Add your notes here..."></textarea>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="report-footer">
                                    <div class="report-status">
                                        <?php echo getReportStatusBadge($report['status']); ?>
                                    </div>
                                    
                                    <?php if ($report['status'] === 'pending'): ?>
                                        <div class="report-actions">
                                            <button class="btn btn-sm btn-info view-chat" data-consultation="<?php echo $report['consultation_id']; ?>">
                                                <i class="fas fa-comments"></i> View Chat
                                            </button>
                                            
                                            <button class="btn btn-sm btn-success accept-report" data-form="admin-notes-form-<?php echo $report['id']; ?>" data-action="accept">
                                                <i class="fas fa-check"></i> Accept
                                            </button>
                                            
                                            <button class="btn btn-sm btn-danger decline-report" data-form="admin-notes-form-<?php echo $report['id']; ?>" data-action="decline">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                            
                                            <?php if ($report['payment_amount']): ?>
                                                <button class="btn btn-sm btn-warning remborser-report" data-form="admin-notes-form-<?php echo $report['id']; ?>" data-action="remborser" data-amount="<?php echo $report['payment_amount']; ?>">
                                                    <i class="fas fa-money-bill-wave"></i> Remborser
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="report-actions">
                                            <button class="btn btn-sm btn-info view-chat" data-consultation="<?php echo $report['consultation_id']; ?>">
                                                <i class="fas fa-comments"></i> View Chat
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body" style="text-align: center; padding: 3rem 1rem;">
                                <i class="fas fa-flag" style="font-size: 3rem; color: var(--text-color-light); margin-bottom: 1rem;"></i>
                                <h3>No Reports Found</h3>
                                <p>There are no reports in the system at this time.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="tab-content" id="expert-tab">
                <!-- Expert reports will be filtered via JavaScript -->
                <div class="report-grid" id="expert-reports-grid"></div>
            </div>
            
            <div class="tab-content" id="client-tab">
                <!-- Client reports will be filtered via JavaScript -->
                <div class="report-grid" id="client-reports-grid"></div>
            </div>
            
            <div class="tab-content" id="pending-tab">
                <!-- Pending reports will be filtered via JavaScript -->
                <div class="report-grid" id="pending-reports-grid"></div>
            </div>
        </div>
    </div>
    
    <!-- Chat Modal -->
    <div class="modal-overlay" id="chat-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Consultation Chat</h3>
                <button class="modal-close" id="close-chat-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="consultation-info" id="consultation-info">
                    <!-- Consultation info will be loaded here -->
                </div>
                <div class="chat-container" id="chat-messages">
                    <!-- Chat messages will be loaded here -->
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="close-chat-btn">Close</button>
            </div>
        </div>
    </div>
    
    <!-- Remborser Confirmation Modal -->
    <div class="modal-overlay" id="remborser-modal">
        <div class="modal">
            <div class="modal-header">
                <h3>Confirm Refund</h3>
                <button class="modal-close" id="close-remborser-modal">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to refund the payment of <span id="refund-amount"></span> <?php echo $currency; ?>?</p>
                <p>This action will:</p>
                <ul>
                    <li>Deduct the amount from the expert's balance</li>
                    <li>Add the amount to the client's balance</li>
                    <li>Mark the report as refunded</li>
                    <li>Send notifications to both parties</li>
                </ul>
                <p><strong>Note:</strong> If the expert has 15 or more reports in the last 30 days, their account will be automatically suspended for 30 days.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" id="cancel-remborser-btn">Cancel</button>
                <button class="btn btn-warning" id="confirm-remborser-btn">Confirm Refund</button>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    tabs.forEach(t => t.classList.remove('active'));
                    tabContents.forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                    
                    // Filter reports based on tab
                    filterReports(tabId);
                });
            });
            
            // Filter reports based on tab
            function filterReports(tabType) {
                const allReports = document.querySelectorAll('.report-card');
                const expertReportsGrid = document.getElementById('expert-reports-grid');
                const clientReportsGrid = document.getElementById('client-reports-grid');
                const pendingReportsGrid = document.getElementById('pending-reports-grid');
                
                // Clear existing reports
                expertReportsGrid.innerHTML = '';
                clientReportsGrid.innerHTML = '';
                pendingReportsGrid.innerHTML = '';
                
                // Clone and filter reports
                allReports.forEach(report => {
                    const reportType = report.getAttribute('data-type');
                    const reportStatus = report.getAttribute('data-status');
                    const reportClone = report.cloneNode(true);
                    
                    // Add event listeners to cloned report
                    addEventListenersToReport(reportClone);
                    
                    if (tabType === 'expert' && reportType === 'expert') {
                        expertReportsGrid.appendChild(reportClone);
                    } else if (tabType === 'client' && reportType === 'client') {
                        clientReportsGrid.appendChild(reportClone);
                    } else if (tabType === 'pending' && reportStatus === 'pending') {
                        pendingReportsGrid.appendChild(reportClone);
                    }
                });
                
                // Show message if no reports found
                if (tabType === 'expert' && expertReportsGrid.children.length === 0) {
                    expertReportsGrid.innerHTML = getNoReportsMessage('expert');
                }
                
                if (tabType === 'client' && clientReportsGrid.children.length === 0) {
                    clientReportsGrid.innerHTML = getNoReportsMessage('client');
                }
                
                if (tabType === 'pending' && pendingReportsGrid.children.length === 0) {
                    pendingReportsGrid.innerHTML = getNoReportsMessage('pending');
                }
            }
            
            // Get no reports message
            function getNoReportsMessage(type) {
                let message = '';
                
                switch(type) {
                    case 'expert':
                        message = 'No expert reports found.';
                        break;
                    case 'client':
                        message = 'No client reports found.';
                        break;
                    case 'pending':
                        message = 'No pending reports found.';
                        break;
                    default:
                        message = 'No reports found.';
                }
                
                return `
                    <div class="card">
                        <div class="card-body" style="text-align: center; padding: 3rem 1rem;">
                            <i class="fas fa-flag" style="font-size: 3rem; color: var(--text-color-light); margin-bottom: 1rem;"></i>
                            <h3>No Reports Found</h3>
                            <p>${message}</p>
                        </div>
                    </div>
                `;
            }
            
            // Add event listeners to report card
            function addEventListenersToReport(reportCard) {
                const viewChatBtn = reportCard.querySelector('.view-chat');
                const acceptBtn = reportCard.querySelector('.accept-report');
                const declineBtn = reportCard.querySelector('.decline-report');
                const remborserBtn = reportCard.querySelector('.remborser-report');
                
                if (viewChatBtn) {
                    viewChatBtn.addEventListener('click', function() {
                        const consultationId = this.getAttribute('data-consultation');
                        loadChatMessages(consultationId);
                    });
                }
                
                if (acceptBtn) {
                    acceptBtn.addEventListener('click', function() {
                        const formId = this.getAttribute('data-form');
                        const form = document.getElementById(formId);
                        
                        if (form) {
                            const actionInput = document.createElement('input');
                            actionInput.type = 'hidden';
                            actionInput.name = 'action';
                            actionInput.value = 'accept';
                            
                            form.appendChild(actionInput);
                            form.submit();
                        }
                    });
                }
                
                if (declineBtn) {
                    declineBtn.addEventListener('click', function() {
                        const formId = this.getAttribute('data-form');
                        const form = document.getElementById(formId);
                        
                        if (form) {
                            const actionInput = document.createElement('input');
                            actionInput.type = 'hidden';
                            actionInput.name = 'action';
                            actionInput.value = 'decline';
                            
                            form.appendChild(actionInput);
                            form.submit();
                        }
                    });
                }
                
                if (remborserBtn) {
                    remborserBtn.addEventListener('click', function() {
                        const formId = this.getAttribute('data-form');
                        const amount = this.getAttribute('data-amount');
                        
                        document.getElementById('refund-amount').textContent = amount;
                        document.getElementById('remborser-modal').classList.add('active');
                        
                        // Store form ID for later use
                        document.getElementById('confirm-remborser-btn').setAttribute('data-form', formId);
                    });
                }
            }
            
            // Add event listeners to original reports
            document.querySelectorAll('.report-card').forEach(report => {
                addEventListenersToReport(report);
            });
            
            // Chat modal
            const chatModal = document.getElementById('chat-modal');
            const closeChatModal = document.getElementById('close-chat-modal');
            const closeChatBtn = document.getElementById('close-chat-btn');
            
            closeChatModal.addEventListener('click', function() {
                chatModal.classList.remove('active');
            });
            
            closeChatBtn.addEventListener('click', function() {
                chatModal.classList.remove('active');
            });
            
            // Load chat messages
            function loadChatMessages(consultationId) {
                fetch(`get-message-details.php?id=${consultationId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Load consultation info
                            const consultationInfo = document.getElementById('consultation-info');
                            const consultation = data.consultation;
                            consultationInfo.innerHTML = `
                                <div class="consultation-info-item">
                                    <span class="consultation-info-label">Consultation ID:</span>
                                    <span class="consultation-info-value">#${consultation.id}</span>
                                </div>
                                <div class="consultation-info-item">
                                    <span class="consultation-info-label">Date:</span>
                                    <span class="consultation-info-value">${consultation.consultation_date} ${consultation.consultation_time}</span>
                                </div>
                                <div class="consultation-info-item">
                                    <span class="consultation-info-label">Status:</span>
                                    <span class="consultation-info-value">${consultation.status}</span>
                                </div>
                                <div class="consultation-info-item">
                                    <span class="consultation-info-label">Expert:</span>
                                    <span class="consultation-info-value">${consultation.expert_name}</span>
                                </div>
                                <div class="consultation-info-item">
                                    <span class="consultation-info-label">Client:</span>
                                    <span class="consultation-info-value">${consultation.client_name}</span>
                                </div>
                            `;
                            
                            // Load chat messages
                            const chatMessages = document.getElementById('chat-messages');
                            chatMessages.innerHTML = '';
                            
                            if (data.messages.length === 0) {
                                chatMessages.innerHTML = '<p style="text-align: center; padding: 2rem;">No messages found for this consultation.</p>';
                            } else {
                                data.messages.forEach(message => {
                                    const isExpert = message.sender_type === 'expert';
                                    const messageType = isExpert ? 'expert' : 'client';
                                    
                                    let messageContent = '';
                                    
                                    if (message.message_type === 'text') {
                                        messageContent = `<div class="chat-content">${message.message}</div>`;
                                    } else if (message.message_type === 'image') {
                                        messageContent = `
                                            <div class="chat-content">${message.message}</div>
                                            <img src="${message.file_path}" alt="Chat Image" class="chat-image">
                                        `;
                                    } else if (message.message_type === 'file') {
                                        const fileName = message.file_path.split('/').pop();
                                        messageContent = `
                                            <div class="chat-content">${message.message}</div>
                                            <div class="chat-file">
                                                <i class="fas fa-file"></i>
                                                <span class="chat-file-name">${fileName}</span>
                                                <a href="${message.file_path}" class="chat-file-download" target="_blank">Download</a>
                                            </div>
                                        `;
                                    }
                                    
                                    const messageHtml = `
                                        <div class="chat-message ${messageType}">
                                            <div class="chat-avatar">
                                                <img src="/placeholder.svg?height=50&width=50" alt="${messageType}">
                                            </div>
                                            <div class="chat-bubble">
                                                ${messageContent}
                                                <div class="chat-time">${formatTime(message.created_at)}</div>
                                            </div>
                                        </div>
                                    `;
                                    
                                    chatMessages.innerHTML += messageHtml;
                                });
                                
                                // Scroll to bottom
                                chatMessages.scrollTop = chatMessages.scrollHeight;
                            }
                            
                            // Show modal
                            chatModal.classList.add('active');
                        } else {
                            alert('Error loading chat messages: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error loading chat messages:', error);
                        alert('Error loading chat messages. Please try again.');
                    });
            }
            
            // Format time
            function formatTime(timestamp) {
                const date = new Date(timestamp);
                return date.toLocaleString();
            }
            
            // Remborser modal
            const remborserModal = document.getElementById('remborser-modal');
            const closeRemborserModal = document.getElementById('close-remborser-modal');
            const cancelRemborserBtn = document.getElementById('cancel-remborser-btn');
            const confirmRemborserBtn = document.getElementById('confirm-remborser-btn');
            
            closeRemborserModal.addEventListener('click', function() {
                remborserModal.classList.remove('active');
            });
            
            cancelRemborserBtn.addEventListener('click', function() {
                remborserModal.classList.remove('active');
            });
            
            confirmRemborserBtn.addEventListener('click', function() {
                const formId = this.getAttribute('data-form');
                const form = document.getElementById(formId);
                
                if (form) {
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'action';
                    actionInput.value = 'remborser';
                    
                    form.appendChild(actionInput);
                    form.submit();
                }
                
                remborserModal.classList.remove('active');
            });
            
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

            // Refresh notification badges every 30 seconds
            setInterval(refreshNotificationBadges, 1000);
        });
    </script>
</body>
</html>
