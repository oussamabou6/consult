<?php
// Start the session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Redirect to login page if not logged in
    header("Location: ../config/logout.php");
    exit;
}

// Process user deletion if requested
if (isset($_POST['delete_user']) && isset($_POST['user_id'])) {
    $user_id = (int)$_POST['user_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete user
        $delete_query = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION["admin_message"] = "User deleted successfully.";
        $_SESSION["admin_message_type"] = "success";
        
        // Redirect to refresh the page
        header("Location: users.php");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION["admin_message"] = "Error deleting user: " . $e->getMessage();
        $_SESSION["admin_message_type"] = "error";
    }
}

// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Get all users with their profile information
// Get all users with their profile information and suspension count
$users_query = "SELECT 
                u.id, 
                u.full_name, 
                u.email, 
                u.role, 
                u.status, 
                u.created_at, 
                up.phone, 
                u.last_login, 
                u.deleted_at, 
                u.suspension_end_date, 
                u.status_updated_at,
                (SELECT COUNT(*) FROM reports WHERE reported_id = u.id AND status IN ('remborser','accepted')) AS reports_count,
                (SELECT COUNT(*) FROM user_suspensions WHERE user_id = u.id) AS suspension_count
                FROM users u
                LEFT JOIN user_profiles up ON u.id = up.user_id
                ORDER BY u.last_login DESC";
$users_result = $conn->query($users_query);


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
    <title>Users - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Variables */
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

        /* Base Styles */
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

        /* Layout */
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
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: var(--transition);
        }

        /* Sidebar Styles */
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

        /* Header Styles */
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
            font-size: 0.875rem;
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

        /* Card Styles */
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
            justify-content: flex-end;
            align-items: center;
            gap: 1rem;
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 1rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            font-weight: 600;
            color: var(--dark-color);
            background-color: var(--light-color-2);
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .table tr:hover {
            background-color: var(--light-color-2);
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: var(--light-color);
        }
        
        .table-striped tbody tr:nth-of-type(odd):hover {
            background-color: var(--light-color-2);
        }

        /* Button Styles */
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
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
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

        /* Status Badge Styles */
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

        .status-reports-low {
            background: var(--warning-gradient);
            color: white;
        }
        .status-reports-suspended {
            background: var(--danger-gradient);
            color: white;
        }

        .status-reports-medium {
            background: linear-gradient(135deg, var(--warning-color) 0%, var(--danger-color) 100%);
            color: white;
        }

        .status-reports-high {
            background: var(--danger-gradient);
            color: white;
            animation: pulse 1.5s infinite;
            border: 1px solid var(--danger-light);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }

        /* Alert Styles */
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

        /* Search and Filter Styles */
        .search-filter-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--card-bg);
            color: var(--text-color);
            font-size: 0.875rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-color-light);
            font-size: 0.875rem;
        }
        
        .filter-box {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        
        .filter-box select {
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--card-bg);
            color: var(--text-color);
            font-size: 0.875rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }
        
        .filter-box select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }

        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1.5rem;
        }
        
        .pagination-item {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: var(--radius);
            background-color: var(--card-bg);
            color: var(--text-color);
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            cursor: pointer;
        }
        
        .pagination-item:hover {
            background-color: var(--primary-bg);
            color: var(--primary-color);
            box-shadow: var(--shadow);
        }
        
        .pagination-item.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: var(--shadow);
        }
        
        .pagination-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-item.disabled:hover {
            background-color: var(--card-bg);
            color: var(--text-color);
            box-shadow: var(--shadow-sm);
        }

        /* Animations */
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

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .search-filter-container {
                flex-direction: column;
                align-items: stretch;
            }
        }
        
        @media (max-width: 992px) {
            .table th, .table td {
                padding: 0.5rem 0.75rem;
                font-size: 0.875rem;
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
            
            .user-info {
                width: 100%;
            }
            
            .btn {
                padding: 0.5rem 1rem;
                font-size: 0.75rem;
            }
        }

        /* Dashboard Specific Styles */
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

.stat-card.experts .stat-card-title i {
    color: var(--info-color);
}

.stat-card.clients .stat-card-title i {
    color: var(--success-color);
}

.stat-card.admins .stat-card-title i {
    color: var(--dark-color);
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

.stat-card.earnings .stat-card-title i {
    color: #10b981;
}

.stat-card-value {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.stat-card.users .stat-card-value {
    color: var(--primary-color);
}

.stat-card.experts .stat-card-value {
    color: var(--info-color);
}

.stat-card.clients .stat-card-value {
    color: var(--success-color);
}

.stat-card.admins .stat-card-value {
    color: var(--dark-color);
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

.stat-card.earnings .stat-card-value {
    color: #10b981;
}

.stat-card-desc {
    font-size: 0.75rem;
    color: var(--text-color-light);
}

/* Responsive Styles for Dashboard Grid */
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
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo htmlspecialchars($site_name); ?></h2>
                <p>Admin Dashboard</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="menu-item active">
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
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-users"></i> Users</h1>
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
            
            <!-- Alerts -->
            <?php if (isset($_SESSION["admin_message"])): ?>
                <div class="alert alert-<?php echo $_SESSION["admin_message_type"]; ?>">
                    <i class="fas fa-<?php echo $_SESSION["admin_message_type"] === "success" ? "check-circle" : "exclamation-circle"; ?>"></i>
                    <div><?php echo $_SESSION["admin_message"]; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
                <?php unset($_SESSION["admin_message"]); unset($_SESSION["admin_message_type"]); ?>
            <?php endif; ?>
            
            <!-- User Statistics -->
<h2 style="margin-bottom: 1rem; color: var(--dark-color); font-size: 1.25rem;">User Statistics</h2>
<div class="dashboard-grid">
    <?php
    // Get users counts
    $total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status != 'Deleted'")->fetch_assoc()['count'];
    $total_experts = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'expert' AND status != 'Deleted'")->fetch_assoc()['count'];
    $total_clients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'client' AND status != 'Deleted'")->fetch_assoc()['count'];
    $total_admins = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin' AND status != 'Deleted'")->fetch_assoc()['count'];
    
    // Get active/inactive counts
    $online_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Online'")->fetch_assoc()['count'];
    $suspended_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'suspended'")->fetch_assoc()['count'];
    ?>
    <div class="stat-card users">
        <div class="stat-card-title">
            <i class="fas fa-users"></i> Total Users
        </div>
        <div class="stat-card-value"><?php echo $total_users; ?></div>
        <div class="stat-card-desc">All registered users</div>
    </div>
    <div class="stat-card experts">
        <div class="stat-card-title">
            <i class="fas fa-user-tie"></i> Experts
        </div>
        <div class="stat-card-value"><?php echo $total_experts; ?></div>
        <div class="stat-card-desc">Registered experts</div>
    </div>
    <div class="stat-card clients">
        <div class="stat-card-title">
            <i class="fas fa-user"></i> Clients
        </div>
        <div class="stat-card-value"><?php echo $total_clients; ?></div>
        <div class="stat-card-desc">Registered clients</div>
    </div>
    <div class="stat-card admins">
        <div class="stat-card-title">
            <i class="fas fa-shield-alt"></i> Admins
        </div>
        <div class="stat-card-value"><?php echo $total_admins; ?></div>
        <div class="stat-card-desc">System administrators</div>
    </div>
</div>

<h2 style="margin: 1.5rem 0 1rem; color: var(--dark-color); font-size: 1.25rem;">User Status</h2>
<div class="dashboard-grid">
    <div class="stat-card approved">
        <div class="stat-card-title">
            <i class="fas fa-circle"></i> Online Users
        </div>
        <div class="stat-card-value"><?php echo $online_users; ?></div>
        <div class="stat-card-desc">Currently active users</div>
    </div>
    <div class="stat-card rejected">
        <div class="stat-card-title">
            <i class="fas fa-ban"></i> Suspended Users
        </div>
        <div class="stat-card-value"><?php echo $suspended_users; ?></div>
        <div class="stat-card-desc">Temporarily banned users</div>
    </div>
   
    <div class="stat-card earnings">
        <div class="stat-card-title">
            <i class="fas fa-calendar-alt"></i> New This Month
        </div>
        <div class="stat-card-value"><?php echo $conn->query("SELECT COUNT(*) as count FROM users WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())")->fetch_assoc()['count']; ?></div>
        <div class="stat-card-desc">Users registered this month</div>
    </div>
</div>
            
            <!-- Users Card -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> All Users</h2>
                    <a href="add-user.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Add New User
                    </a>
                </div>
                <div class="card-body">
                    <!-- Search and Filter -->
                    <div class="search-filter-container">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search users..." onkeyup="searchTable()">
                        </div>
                        <div class="filter-box">
                            <select id="roleFilter" onchange="filterTable()">
                                <option value="">All Roles</option>
                                <option value="admin">Admin</option>
                                <option value="expert">Expert</option>
                                <option value="client">Client</option>
                            </select>
                            <select id="statusFilter" onchange="filterTable()">
                                <option value="">All Statuses</option>
                                <option value="Online">Online</option>
                                <option value="Offline">Offline</option>
                                <option value="suspended">Suspended</option>
                                <option value="Deleted">Deleted</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Users Table -->
                    <div class="table-responsive">
                        <table class="table table-striped" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Reports</th>
                                    <th>Last Login</th>
                                    
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($users_result && $users_result->num_rows > 0): ?>
                                    <?php while ($user = $users_result->fetch_assoc()): ?>
                                        <tr>
                                            <td>
                                                <?php echo htmlspecialchars($user['full_name']); ?>
                                                <?php if ($user['suspension_count'] > 0): ?>
                                                    <span class="status-badge status-reports-suspended">
                                                        <i class="fas fa-flag"></i> <?php echo $user['suspension_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['email']); ?>
                                            <br><small><?php echo htmlspecialchars($user['phone']); ?></small>
                                        </td>
                                            <td>
                                                <?php
                                                switch($user['role']) {
                                                    case 'admin':
                                                        echo '<span class="status-badge status-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
                                                        break;
                                                    case 'expert':
                                                        echo '<span class="status-badge status-expert"><i class="fas fa-user-tie"></i> Expert</span>';
                                                        break;
                                                    case 'client':
                                                        echo '<span class="status-badge status-client"><i class="fas fa-user"></i> Client</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="status-badge">' . ucfirst($user['role']) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php
                                                switch($user['status']) {
                                                    case 'Online':
                                                        echo '<span class="status-badge status-active"><i class="fas fa-circle"></i> Online</span>';
                                                        break;
                                                    case 'Offline':
                                                        echo '<span class="status-badge status-inactive"><i class="far fa-circle"></i> Offline</span>';
                                                        break;
                                                    case 'suspended':
                                                        echo '<span class="status-badge status-rejected"><i class="fas fa-ban"></i> Suspended</span>';
                                                        break;
                                                    case 'Deleted':
                                                        echo '<span class="status-badge status-rejected"><i class="fas fa-trash"></i> Deleted</span>';
                                                        break;
                                                    default:
                                                        echo '<span class="status-badge">' . ucfirst($user['status']) . '</span>';
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?php if ($user['reports_count'] == 0): ?>
                                                    <span class="status-badge status-approved">
                                                        <i class="fas fa-check"></i> 0
                                                    </span>
                                                <?php elseif ($user['reports_count'] >= 1 && $user['reports_count'] <= 7): ?>
                                                    <span class="status-badge status-reports-low">
                                                        <i class="fas fa-flag"></i> <?php echo $user['reports_count']; ?>
                                                    </span>
                                                <?php elseif ($user['reports_count'] >= 8 && $user['reports_count'] <= 14): ?>
                                                    <span class="status-badge status-reports-medium">
                                                        <i class="fas fa-flag"></i> <?php echo $user['reports_count']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge status-reports-high">
                                                        <i class="fas fa-exclamation-triangle"></i> <?php echo $user['reports_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                            </td>
                                                

                                            <td>
    <?php if ($user['status'] === 'suspended'): ?>
        <span class="status-badge status-rejected">
            <i class="fas fa-ban"></i>
            <small><?php echo htmlspecialchars($user['last_login']); ?></small>
        </span>
    
    <?php elseif ($user['status'] === 'Deleted'): ?>
        <span class="status-badge status-rejected">
            <i class="fas fa-trash"></i>
            <small><?php echo htmlspecialchars($user['deleted_at']); ?></small>
        </span>

    <?php elseif (is_null($user['last_login'])): ?>
        <span class="status-badge status-inactive">
            <i class="fas fa-clock"></i> Never
        </span>

    <?php else: ?>
        <span class="status-badge status-active">
            <i class="fas fa-clock"></i> 
            <small><?php echo htmlspecialchars($user['last_login']); ?></small>
        </span>
    <?php endif; ?>
</td>


                                            <td>
                                                <div class="btn-group">
                                                    <a href="view-user.php?id=<?php echo $user['id']; ?>" class="btn btn-sm btn-info" style="margin-bottom: 5px;">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                                                            <i class="fas fa-trash"></i> Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center">No users found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Search functionality
        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.getElementById('usersTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                let found = false;
                const td = tr[i].getElementsByTagName('td');
                
                for (let j = 0; j < 2; j++) { // Search only in name and email columns
                    if (td[j]) {
                        const txtValue = td[j].textContent || td[j].innerText;
                        if (txtValue.toUpperCase().indexOf(filter) > -1) {
                            found = true;
                            break;
                        }
                    }
                }
                
                if (found) {
                    tr[i].style.display = '';
                } else {
                    tr[i].style.display = 'none';
                }
            }
        }
        
        // Filter functionality
        function filterTable() {
            const roleFilter = document.getElementById('roleFilter').value.toUpperCase();
            const statusFilter = document.getElementById('statusFilter').value.toUpperCase();
            const table = document.getElementById('usersTable');
            const tr = table.getElementsByTagName('tr');
            
            for (let i = 1; i < tr.length; i++) {
                const tdRole = tr[i].getElementsByTagName('td')[2];
                const tdStatus = tr[i].getElementsByTagName('td')[3];
                
                if (tdRole && tdStatus) {
                    const roleValue = tdRole.textContent || tdRole.innerText;
                    const statusValue = tdStatus.textContent || tdStatus.innerText;
                    
                    const roleMatch = roleFilter === '' || roleValue.toUpperCase().indexOf(roleFilter) > -1;
                    const statusMatch = statusFilter === '' || statusValue.toUpperCase().indexOf(statusFilter) > -1;
                    
                    if (roleMatch && statusMatch) {
                        tr[i].style.display = '';
                    } else {
                        tr[i].style.display = 'none';
                    }
                }
            }
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
        
        // Mobile sidebar toggle
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
            
            toggleBtn.addEventListener('click', function() {
                sidebar.classList.toggle('expanded');
            });
            
            sidebar.appendChild(toggleBtn);
        }
    </script>
</body>
</html>
