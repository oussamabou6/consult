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
$current_page = 1;
$requests_per_page = 10;
$status_filter = "all";
$search = "";
$date_from = "";
$date_to = "";

// Get search and filter parameters
if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}

if (isset($_GET["status"])) {
    $status_filter = $_GET["status"];
}

if (isset($_GET["date_from"])) {
    $date_from = $_GET["date_from"];
}

if (isset($_GET["date_to"])) {
    $date_to = $_GET["date_to"];
}

if (isset($_GET["page"]) && is_numeric($_GET["page"])) {
    $current_page = (int)$_GET["page"];
    if ($current_page < 1) {
        $current_page = 1;
    }
}

// Process fund request status updates if submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && isset($_POST["request_id"])) {
    $request_id = (int)$_POST["request_id"];
    $action = $_POST["action"];
    $admin_notes = isset($_POST["admin_notes"]) ? $_POST["admin_notes"] : "";
    
    if ($action == "approve") {
        // Get fund request details
        $get_request_sql = "SELECT fr.*, u.balance, u.id as user_id FROM fund_requests fr 
                           JOIN users u ON fr.user_id = u.id 
                           WHERE fr.id = ?";
        $stmt = $conn->prepare($get_request_sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request_result = $stmt->get_result();
        
        if ($request_result && $request_result->num_rows > 0) {
            $request = $request_result->fetch_assoc();
        
            // Update fund request status to Approved
            $update_sql = "UPDATE fund_requests SET status = 'approved', admin_notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $admin_notes, $request_id);
        
            if ($stmt->execute()) {
                // Add the amount to user's balance
                $user_id = $request["user_id"];
                $amount = $request["amount"];
                $current_balance = $request["balance"];
                $new_balance = $current_balance + $amount;
            
                // Update user's balance
                $update_balance_sql = "UPDATE users SET balance = ? WHERE id = ?";
                $stmt = $conn->prepare($update_balance_sql);
                $stmt->bind_param("di", $new_balance, $user_id);
                $stmt->execute();
            
                // Create notification for the user
                $notification_message = "Your fund request of " . number_format($amount, 2) . " has been approved and added to your balance.";
            
                $insert_notification_sql = "INSERT INTO expert_notifications (user_id, message, created_at) 
                                      VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($insert_notification_sql);
                $stmt->bind_param("is", $user_id, $notification_message);
                $stmt->execute();
            
                $success_message = "Fund request #" . $request_id . " has been approved successfully.";
            } else {
                $error_message = "Error updating fund request: " . $conn->error;
            }
        } else {
            $error_message = "Fund request not found.";
        }
    } elseif ($action == "reject") {
        // Get fund request details
        $get_request_sql = "SELECT fr.*, u.id as user_id FROM fund_requests fr 
                           JOIN users u ON fr.user_id = u.id 
                           WHERE fr.id = ?";
        $stmt = $conn->prepare($get_request_sql);
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request_result = $stmt->get_result();
        
        if ($request_result && $request_result->num_rows > 0) {
            $request = $request_result->fetch_assoc();
            
            // Update fund request status to rejected
            $update_sql = "UPDATE fund_requests SET status = 'rejected', admin_notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("si", $admin_notes, $request_id);
            
            if ($stmt->execute()) {
                // Create notification for the user
                $user_id = $request["user_id"];
                $amount = $request["amount"];
                $notification_message = "Your fund request of " . number_format($amount, 2) . " has been rejected.";
                
                $insert_notification_sql = "INSERT INTO expert_notifications (user_id, message, created_at) 
                                          VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($insert_notification_sql);
                $stmt->bind_param("is", $user_id, $notification_message);
                $stmt->execute();
                
                $success_message = "Fund request #" . $request_id . " has been rejected.";
            } else {
                $error_message = "Error updating fund request: " . $conn->error;
            }
        } else {
            $error_message = "Fund request not found.";
        }
    }
}

// Build the query to get fund requests
$sql_count = "SELECT COUNT(*) as total FROM fund_requests fr 
              JOIN users u ON fr.user_id = u.id";

$sql = "SELECT fr.*, u.full_name, u.email, u.balance 
        FROM fund_requests fr 
        JOIN users u ON fr.user_id = u.id";

// Build WHERE clause for filtering
$where_clauses = [];
$params = [];
$types = "";

if ($status_filter != "all") {
    $where_clauses[] = "fr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if (!empty($search)) {
    $search_term = "%" . $search . "%";
    $where_clauses[] = "(u.full_name LIKE ? OR u.email LIKE ? OR fr.reference_id LIKE ?)";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "sss";
}

if (!empty($date_from)) {
    $where_clauses[] = "DATE(fr.created_at) >= ?";
    $params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    $where_clauses[] = "DATE(fr.created_at) <= ?";
    $params[] = $date_to;
    $types .= "s";
}

// Combine WHERE clauses
if (!empty($where_clauses)) {
    $sql_count .= " WHERE " . implode(" AND ", $where_clauses);
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

// Add ORDER BY
$sql .= " ORDER BY fr.created_at DESC";

// Prepare and execute count query
$stmt_count = $conn->prepare($sql_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$count_result = $stmt_count->get_result();
$count_row = $count_result->fetch_assoc();
$total_requests = $count_row["total"];

// Calculate pagination
$total_pages = ceil($total_requests / $requests_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $requests_per_page;

// Add LIMIT clause for pagination
$sql .= " LIMIT ?, ?";
$params[] = $offset;
$params[] = $requests_per_page;
$types .= "ii";

// Prepare and execute main query
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Check if success message is passed in URL
if (isset($_GET["success"])) {
    $success_message = $_GET["success"];
}

// Get summary statistics
$stats_sql = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as Approved_requests,
    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests,
    SUM(amount) as total_amount,
    SUM(CASE WHEN status = 'Approved' THEN amount ELSE 0 END) as Approved_amount
FROM fund_requests";

$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get total users balance
$total_users_balance_sql = "SELECT SUM(balance) as total_balance FROM users";
$total_users_balance_result = $conn->query($total_users_balance_sql);
$total_users_balance = ($total_users_balance_result && $total_users_balance_result->num_rows > 0) ? $total_users_balance_result->fetch_assoc()['total_balance'] : 0;

// Get currency from settings
$currency_query = "SELECT setting_value FROM settings WHERE setting_key = 'currency'";
$currency_result = $conn->query($currency_query);
$currency = ($currency_result && $currency_result->num_rows > 0) ? $currency_result->fetch_assoc()['setting_value'] : 'DA';

// Function to get status badge
function getStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'approved':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Approved</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
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
    <title>Fund Requests - <?php echo htmlspecialchars($site_name); ?></title>
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
        
        /* Replace the existing .stats-container and .stat-card styles with these */
        .stats-container {
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
            cursor: pointer;
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

        .stat-card.total::before {
            background: var(--primary-gradient);
        }

        .stat-card.pending::before {
            background: var(--warning-gradient);
        }

        .stat-card.approved::before, .stat-card.Approved::before {
            background: var(--success-gradient);
        }

        .stat-card.rejected::before {
            background: var(--danger-gradient);
        }

        .stat-card.deleted::before {
            background: linear-gradient(135deg, var(--dark-color) 0%, var(--dark-color-2) 100%);
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

        .stat-card.approved .stat-card-title i, .stat-card.Approved .stat-card-title i {
            color: var(--success-color);
        }

        .stat-card.rejected .stat-card-title i {
            color: var(--danger-color);
        }

        .stat-card.deleted .stat-card-title i {
            color: var(--dark-color);
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

        .stat-card.approved .stat-card-value, .stat-card.Approved .stat-card-value {
            color: var(--success-color);
        }

        .stat-card.rejected .stat-card-value {
            color: var(--danger-color);
        }

        .stat-card.deleted .stat-card-value {
            color: var(--dark-color);
        }

        .stat-card-desc {
            font-size: 0.75rem;
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
        
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }
        
        .fund-requests-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            min-width: 800px;
        }
        
        .fund-requests-table th, .fund-requests-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .fund-requests-table th {
            background-color: var(--dark-color-2);
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }
        
        .fund-requests-table tr:last-child td {
            border-bottom: none;
        }
        
        .fund-requests-table tr:hover {
            background-color: var(--light-color-2);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
       
        
        .status-rejected {
            background: var(--danger-gradient);
            color: white;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text-color);
            background-color: white;
            transition: var(--transition-fast);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .pagination a:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }
        
        .pagination .active {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.3);
        }
        
        .pagination .disabled {
            color: var(--text-color-lighter);
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .no-requests {
            text-align: center;
            padding: 3rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }
        
        .no-requests i {
            font-size: 3rem;
            color: var(--text-color-lighter);
            margin-bottom: 1rem;
        }
        
        .no-requests h3 {
            font-size: 1.25rem;
            color: var(--text-color-dark);
            margin-bottom: 0.5rem;
        }
        
        .no-requests p {
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
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-buttons .btn {
                padding: 0.5rem;
            }
            
            .action-buttons .btn i {
                margin-right: 0;
            }
            
            .action-buttons .btn span {
                display: none;
            }
        }
        
        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .pagination a, .pagination span {
                padding: 0.5rem 0.75rem;
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
                <a href="fund-requests.php" class="menu-item active">
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
                <h1><i class="fas fa-wallet"></i> Fund Requests</h1>
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
            
            <!-- Statistics -->
            <div class="stats-container">
    <div class="stat-card total" title="Click to view all requests">
        <div class="stat-card-title">
            <i class="fas fa-wallet"></i> Total Requests
        </div>
        <div class="stat-card-value"><?php echo $stats['total_requests'] ?? 0; ?></div>
        <div class="stat-card-desc">All fund requests</div>
    </div>
    <div class="stat-card pending" title="Click to filter by pending status">
        <div class="stat-card-title">
            <i class="fas fa-clock"></i> Pending
        </div>
        <div class="stat-card-value"><?php echo $stats['pending_requests'] ?? 0; ?></div>
        <div class="stat-card-desc">Awaiting approval</div>
    </div>
    <div class="stat-card Approved" title="Click to filter by Approved status">
        <div class="stat-card-title">
            <i class="fas fa-check-circle"></i> Approved
        </div>
        <div class="stat-card-value"><?php echo $stats['Approved_requests'] ?? 0; ?></div>
        <div class="stat-card-desc">Approved funds</div>
    </div>
    <div class="stat-card rejected" title="Click to filter by rejected status">
        <div class="stat-card-title">
            <i class="fas fa-times-circle"></i> Rejected
        </div>
        <div class="stat-card-value"><?php echo $stats['rejected_requests'] ?? 0; ?></div>
        <div class="stat-card-desc">Declined funds</div>
    </div>
    <div class="stat-card total">
        <div class="stat-card-title">
            <i class="fas fa-coins"></i> Total Amount
        </div>
        <div class="stat-card-value"><?php echo number_format($stats['total_amount'] ?? 0, 2); ?></div>
        <div class="stat-card-desc"><?php echo $currency; ?> requested</div>
    </div>
    <div class="stat-card total">
        <div class="stat-card-title">
            <i class="fas fa-users"></i> Users Balance
        </div>
        <div class="stat-card-value"><?php echo number_format($total_users_balance ?? 0, 2); ?></div>
        <div class="stat-card-desc"><?php echo $currency; ?> available</div>
    </div>
    <div class="stat-card Approved">
        <div class="stat-card-title">
            <i class="fas fa-check-double"></i> Approved Amount
        </div>
        <div class="stat-card-value"><?php echo number_format($stats['Approved_amount'] ?? 0, 2); ?></div>
        <div class="stat-card-desc"><?php echo $currency; ?> funded</div>
    </div>
</div>
            
            <!-- Filters -->
            <div class="filters">
                <form method="GET" class="search-form">
                    <div class="filter-group">
                        <label class="filter-label" for="status">Status:</label>
                        <select name="status" id="status" class="filter-select">
                            <option value="all" <?php echo $status_filter == "all" ? "selected" : ""; ?>>All Statuses</option>
                            <option value="pending" <?php echo $status_filter == "pending" ? "selected" : ""; ?>>Pending</option>
                            <option value="Approved" <?php echo $status_filter == "rejected" ? "selected" : ""; ?>>Approved</option>
                            <option value="rejected" <?php echo $status_filter == "rejected" ? "selected" : ""; ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label" for="date_from">From:</label>
                        <input type="text" id="date_from" name="date_from" class="date-input" placeholder="From date" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label" for="date_to">To:</label>
                        <input type="text" id="date_to" name="date_to" class="date-input" placeholder="To date" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <input type="text" name="search" class="search-input" placeholder="Search by name, email or reference" value="<?php echo htmlspecialchars($search); ?>">
                    
                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if (!empty($search) || $status_filter != "all" || !empty($date_from) || !empty($date_to)): ?>
                            <a href="fund-requests.php" class="btn btn-warning">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-container">
                    <table class="fund-requests-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></div>
                                        <div class="contact-info">
                                            <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($row['email']); ?>
                                        </div>
                                        <div>
                                            <small>Balance: <?php echo number_format($row['balance'], 2); ?> <?php echo $currency; ?></small>
                                        </div>
                                    </td>
                                    <td><strong><?php echo number_format($row['amount'], 2); ?> <?php echo $currency; ?></strong></td>
                                    <td><?php echo getStatusBadge($row['status']); ?></td>
                                    <td><?php echo formatDate($row['created_at']); ?></td>
                                    <td class="action-buttons">
                                        <a href="view-fund-request.php?id=<?php echo $row['id']; ?>" class="btn btn-primary btn-sm" style="margin-top: 10px;">
                                            <i class="fas fa-eye"></i> <span>View</span>
                                        </a>
                                        <?php if ($row['status'] == 'pending'): ?>
                                            <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to approve this fund request?');">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> <span>Approve</span>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline-block;" onsubmit="return confirm('Are you sure you want to reject this fund request?');">
                                                <input type="hidden" name="request_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-danger btn-sm">
                                                    <i class="fas fa-times"></i> <span>Reject</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                            <span class="disabled"><i class="fas fa-angle-left"></i></span>
                        <?php endif; ?>
                        
                        <?php
                        // Calculate range of page numbers to display
                        $range = 2; // Display 2 pages before and after current page
                        $start_page = max(1, $current_page - $range);
                        $end_page = min($total_pages, $current_page + $range);
                        
                        // Always show first page
                        if ($start_page > 1) {
                            echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . ($status_filter != 'all' ? '&status=' . urlencode($status_filter) : '') . (!empty($date_from) ? '&date_from=' . urlencode($date_from) : '') . (!empty($date_to) ? '&date_to=' . urlencode($date_to) : '') . '">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="disabled">...</span>';
                            }
                        }
                        
                        // Display page numbers
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . ($status_filter != 'all' ? '&status=' . urlencode($status_filter) : '') . (!empty($date_from) ? '&date_from=' . urlencode($date_from) : '') . (!empty($date_to) ? '&date_to=' . urlencode($date_to) : '') . '">' . $i . '</a>';
                            }
                        }
                        
                        // Always show last page
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="disabled">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . ($status_filter != 'all' ? '&status=' . urlencode($status_filter) : '') . (!empty($date_from) ? '&date_from=' . urlencode($date_from) : '') . (!empty($date_to) ? '&date_to=' . urlencode($date_to) : '') . '">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo $status_filter != 'all' ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-right"></i></span>
                            <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-requests">
                    <i class="fas fa-wallet"></i>
                    <h3>No fund requests found</h3>
                    <p>There are no fund requests matching your criteria.</p>
                    <a href="fund-requests.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Initialize date pickers
        flatpickr("#date_from", {
            dateFormat: "Y-m-d",
            allowInput: true
        });
        
        flatpickr("#date_to", {
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

            // Replace the existing stat card click event listeners with these
            // Make stat cards clickable to filter by status
            document.querySelector('.stat-card.total').addEventListener('click', function() {
                window.location.href = 'fund-requests.php';
            });
            document.querySelector('.stat-card.pending').addEventListener('click', function() {
                window.location.href = 'fund-requests.php?status=pending';
            });
            document.querySelector('.stat-card.Approved').addEventListener('click', function() {
                window.location.href = 'fund-requests.php?status=Approved';
            });
            document.querySelector('.stat-card.rejected').addEventListener('click', function() {
                window.location.href = 'fund-requests.php?status=rejected';
            });
        
        });
    </script>
</body>
</html>
