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

// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Get statistics for dashboard
// Total users
$total_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status != 'Deleted'")->fetch_assoc()['count'];
$total_experts = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'expert' AND status != 'Deleted'")->fetch_assoc()['count'];
$total_clients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'client' AND status != 'Deleted'")->fetch_assoc()['count'];

// Online users
$online_users = $conn->query("SELECT COUNT(*) as count FROM users WHERE status = 'Online'")->fetch_assoc()['count'];

// Consultations
$total_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations")->fetch_assoc()['count'];
$completed_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'completed'")->fetch_assoc()['count'];
$pending_consultations = $conn->query("SELECT COUNT(*) as count FROM consultations WHERE status = 'pending'")->fetch_assoc()['count'];

// Payments
$total_payments = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed'")->fetch_assoc()['total'] ?? 0;
$recent_payments = $conn->query("SELECT SUM(amount) as total FROM payments WHERE status = 'completed' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['total'] ?? 0;

// Pending items
$pending_experts = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review' OR status = 'pending_revision'")->fetch_assoc()['count'];
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];

// Unread notifications
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Recent activities (last 10)
$recent_activities_query = "
    (SELECT 'New User' as type, u.full_name as name, u.created_at as date, u.role as details
     FROM users u
     WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    UNION
    (SELECT 'New Consultation' as type, CONCAT(c.client_id, ' with ', c.expert_id) as name, c.created_at as date, c.status as details
     FROM consultations c
     WHERE c.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    UNION
    (SELECT 'New Payment' as type, CONCAT(p.client_id, ' to ', p.expert_id) as name, p.created_at as date, p.status as details
     FROM payments p
     WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    UNION
    (SELECT 'New Report' as type, CONCAT(r.reporter_id, ' reported ', r.reported_id) as name, r.created_at as date, r.report_type as details
     FROM reports r
     WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
    ORDER BY date DESC
    LIMIT 10";
$recent_activities_result = $conn->query($recent_activities_query);

// NEW QUERY: Experts with pending items (certificates, experiences, training, banking information)
$pending_expert_items_query = "
    SELECT 
        e.*,
        e.id as expert_id,
        u.full_name as expert_name,
        u.email as expert_email,
        c.name as category,
        s.name as subcategory,
        (SELECT COUNT(*) FROM certificates WHERE profile_id = e.id AND status = 'pending') as pending_certificates,
        (SELECT COUNT(*) FROM experiences WHERE profile_id = e.id AND status = 'pending') as pending_experiences,
        (SELECT COUNT(*) FROM formations WHERE profile_id = e.id AND status = 'pending') as pending_formations,
        (SELECT COUNT(*) FROM expert_profiledetails WHERE id = e.id AND banking_status = 'pending_review') as pending_banking
    FROM 
        expert_profiledetails e
    JOIN 
        users u ON e.user_id = u.id
    JOIN 
        categories c ON e.category = c.id
    JOIN 
        subcategories s ON e.subcategory = s.id
    WHERE 
        e.status = 'approved' AND
        (
            (SELECT COUNT(*) FROM certificates WHERE profile_id = e.id AND status = 'pending') > 0 OR
            (SELECT COUNT(*) FROM experiences WHERE profile_id = e.id AND status = 'pending') > 0 OR
            (SELECT COUNT(*) FROM formations WHERE profile_id = e.id AND status = 'pending') > 0 OR
            (SELECT COUNT(*) FROM expert_profiledetails WHERE id = e.id AND banking_status = 'pending_review') > 0
        )
    ORDER BY 
        u.full_name ASC
    LIMIT 10";
$pending_expert_items_result = $conn->query($pending_expert_items_query);

// Count the total number of experts with pending items
$total_pending_expert_items_query = "
    SELECT COUNT(*) as count
    FROM expert_profiledetails e
    WHERE 
        e.status = 'approved' AND
        (
            (SELECT COUNT(*) FROM certificates WHERE profile_id = e.id AND status = 'pending') > 0 OR
            (SELECT COUNT(*) FROM experiences WHERE profile_id = e.id AND status = 'pending') > 0 OR
            (SELECT COUNT(*) FROM formations WHERE profile_id = e.id AND status = 'pending') > 0 OR
            (SELECT COUNT(*) FROM expert_profiledetails WHERE id = e.id AND banking_status = 'pending_review') > 0
        )";
$total_pending_expert_items = $conn->query($total_pending_expert_items_query)->fetch_assoc()['count'];

// Add notifications for pending items if they don't already exist
if ($pending_expert_items_result && $pending_expert_items_result->num_rows > 0) {
    // Save the result for later use in display
    $pending_expert_items_data = [];
    while ($row = $pending_expert_items_result->fetch_assoc()) {
        $pending_expert_items_data[] = $row;
        
        // Check if a notification already exists for this expert
        $expert_id = $row['expert_id'];
        $expert_name = $row['expert_name'];
        
        // Create a notification for each type of pending item
        $notification_types = [
            'certificates' => $row['pending_certificates'],
            'experiences' => $row['pending_experiences'],
            'formations' => $row['pending_formations'],
            'banking' => $row['pending_banking']
        ];
        
        foreach ($notification_types as $type => $count) {
            if ($count > 0) {
                $notification_message = "Expert {$expert_name} has {$count} " . ($count > 1 ? "new " : "new ") . 
                                       ($type === 'certificates' ? "certificate(s)" : 
                                        ($type === 'experiences' ? "experience(s)" : 
                                         ($type === 'formations' ? "training(s)" : "banking information"))) . 
                                       " pending approval.";
                
                // Check if this notification already exists
                $check_notification_query = "
                    SELECT id FROM admin_notifications 
                    WHERE related_id = ? AND notification_type = ? AND is_read = 0";
                $stmt = $conn->prepare($check_notification_query);
                $notification_type = "expert_{$type}";
                $stmt->bind_param("is", $expert_id, $notification_type);
                $stmt->execute();
                $result = $stmt->get_result();
                
                // If the notification doesn't exist, add it
                if ($result->num_rows === 0) {
                    $insert_notification_query = "
                        INSERT INTO admin_notifications (notification_type, message, related_id, created_at) 
                        VALUES (?, ?, ?, NOW())";
                    $stmt = $conn->prepare($insert_notification_query);
                    $stmt->bind_param("ssi", $notification_type, $notification_message, $expert_id);
                    $stmt->execute();
                }
            }
        }
    }
    
    // Reset the result pointer for use in display
    $pending_expert_items_result = $conn->query($pending_expert_items_query);
}

// Function to format date
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Function to format currency
function formatCurrency($amount) {
    return number_format($amount, 2) . ' DA';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($site_name); ?></title>
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

        /* Dashboard Specific Styles */
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border-color);
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
        
        .stat-card.primary::before {
            background: var(--primary-gradient);
        }
        
        .stat-card.success::before {
            background: var(--success-gradient);
        }
        
        .stat-card.warning::before {
            background: var(--warning-gradient);
        }
        
        .stat-card.danger::before {
            background: var(--danger-gradient);
        }
        
        .stat-card.info::before {
            background: var(--info-gradient);
        }
        
        .stat-card-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 2.5rem;
            opacity: 0.1;
            color: var(--primary-color);
        }
        
        .stat-card.primary .stat-card-icon {
            color: var(--primary-color);
        }
        
        .stat-card.success .stat-card-icon {
            color: var(--success-color);
        }
        
        .stat-card.warning .stat-card-icon {
            color: var(--warning-color);
        }
        
        .stat-card.danger .stat-card-icon {
            color: var(--danger-color);
        }
        
        .stat-card.info .stat-card-icon {
            color: var(--info-color);
        }
        
        .stat-card-title {
            font-size: 0.875rem;
            color: var(--text-color-light);
            margin-bottom: 0.5rem;
        }
        
        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-card.primary .stat-card-value {
            color: var(--primary-color);
        }
        
        .stat-card.success .stat-card-value {
            color: var(--success-color);
        }
        
        .stat-card.warning .stat-card-value {
            color: var(--warning-color);
        }
        
        .stat-card.danger .stat-card-value {
            color: var(--danger-color);
        }
        
        .stat-card.info .stat-card-value {
            color: var(--info-color);
        }
        
        .stat-card-desc {
            font-size: 0.75rem;
            color: var(--text-color-light);
        }
        
        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
        }
        
        @media (max-width: 1200px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .activity-icon.user {
            background: var(--primary-bg);
            color: var(--primary-color);
        }
        
        .activity-icon.consultation {
            background: var(--success-bg);
            color: var(--success-color);
        }
        
        .activity-icon.payment {
            background: var(--info-bg);
            color: var(--info-color);
        }
        
        .activity-icon.report {
            background: var(--danger-bg);
            color: var(--danger-color);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        
        .activity-details {
            font-size: 0.875rem;
            color: var(--text-color-light);
        }
        
        .activity-time {
            font-size: 0.75rem;
            color: var(--text-color-lighter);
        }
        
        .pending-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-color);
        }
        
        .pending-item:last-child {
            border-bottom: none;
        }
        
        .pending-item-title {
            font-weight: 500;
            color: var(--dark-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .pending-item-title i {
            color: var(--primary-color);
        }
        
        .pending-item-count {
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--radius-full);
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .pending-item-count.warning {
            background: var(--warning-gradient);
        }
        
        .pending-item-count.danger {
            background: var(--danger-gradient);
        }
        
        .chart-container {
            width: 100%;
            height: 300px;
            position: relative;
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
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4);
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

        /* Table Styles */
        .table-container {
            overflow-x: auto;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background-color: var(--light-color-2);
        }
        
        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: var(--dark-color);
            border-bottom: 1px solid var(--border-color);
        }
        
        td {
            padding: 1rem;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        tr:hover {
            background-color: var(--light-color-2);
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
        }
        
        .badge-primary {
            background: var(--primary-gradient);
        }
        
        .badge-success {
            background: var(--success-gradient);
        }
        
        .badge-warning {
            background: var(--warning-gradient);
        }
        
        .badge-danger {
            background: var(--danger-gradient);
        }
        
        .badge-info {
            background: var(--info-gradient);
        }
        
        .badge i {
            margin-right: 0.25rem;
            font-size: 0.7rem;
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
            .dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .dashboard-stats {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
            
            .dashboard-stats {
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
                <a href="dashboard.php" class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="menu-item">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="expert-profiles.php" class="menu-item">
                    <i class="fas fa-user-tie"></i> Expert Profiles
                    <?php if ($pending_experts > 0): ?>
                        <span class="notification-badge"><?php echo $pending_experts; ?></span>
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
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
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
            
            <!-- Dashboard Stats -->
            <div class="dashboard-stats">
                <div class="stat-card primary">
                    <i class="fas fa-users stat-card-icon"></i>
                    <div class="stat-card-title">Total Users</div>
                    <div class="stat-card-value"><?php echo $total_users; ?></div>
                    <div class="stat-card-desc">
                        <?php echo $total_experts; ?> Experts, <?php echo $total_clients; ?> Clients
                    </div>
                </div>
                
                <div class="stat-card success">
                    <i class="fas fa-calendar-check stat-card-icon"></i>
                    <div class="stat-card-title">Consultations</div>
                    <div class="stat-card-value"><?php echo $total_consultations; ?></div>
                    <div class="stat-card-desc">
                        <?php echo $completed_consultations; ?> Completed, <?php echo $pending_consultations; ?> Pending
                    </div>
                </div>
                
                <div class="stat-card info">
                    <i class="fas fa-money-bill-wave stat-card-icon"></i>
                    <div class="stat-card-title">Total Revenue</div>
                    <div class="stat-card-value"><?php echo formatCurrency($total_payments); ?></div>
                    <div class="stat-card-desc">
                        <?php echo formatCurrency($recent_payments); ?> in last 7 days
                    </div>
                </div>
                
                <div class="stat-card warning">
                    <i class="fas fa-user-clock stat-card-icon"></i>
                    <div class="stat-card-title">Online Users</div>
                    <div class="stat-card-value"><?php echo $online_users; ?></div>
                    <div class="stat-card-desc">
                        Currently active on the platform
                    </div>
                </div>
            </div>
            
            <!-- NEW SECTION: Experts with pending items -->
            <?php if ($total_pending_expert_items > 0): ?>
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-graduate"></i> Experts with Pending Items</h2>
                    <span class="badge badge-warning">
                        <i class="fas fa-clock"></i> <?php echo $total_pending_expert_items; ?> expert(s)
                    </span>
                </div>
                <div class="card-body">
                    <div class="table-container">
                        <table>
                            <thead>
                                <tr>
                                    <th>Expert</th>
                                    <th>Email</th>
                                    <th>Category</th>
                                    <th>Certificates</th>
                                    <th>Experiences</th>
                                    <th>Training</th>
                                    <th>Banking Info</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($pending_expert_items_result && $pending_expert_items_result->num_rows > 0): ?>
                                    <?php while ($expert = $pending_expert_items_result->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($expert['expert_name']); ?></td>
                                            <td><?php echo htmlspecialchars($expert['expert_email']); ?></td>
                                            <td><?php echo htmlspecialchars($expert['category'] . ' / ' . $expert['subcategory']); ?></td>
                                            <td>
                                                <?php if ($expert['pending_certificates'] > 0): ?>
                                                    <span class="badge badge-warning">
                                                        <i class="fas fa-certificate"></i> <?php echo $expert['pending_certificates']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #cbd5e1;">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($expert['pending_experiences'] > 0): ?>
                                                    <span class="badge badge-warning">
                                                        <i class="fas fa-briefcase"></i> <?php echo $expert['pending_experiences']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #cbd5e1;">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($expert['pending_formations'] > 0): ?>
                                                    <span class="badge badge-warning">
                                                        <i class="fas fa-graduation-cap"></i> <?php echo $expert['pending_formations']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #cbd5e1;">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($expert['pending_banking'] > 0): ?>
                                                    <span class="badge badge-warning">
                                                        <i class="fas fa-university"></i> <?php echo $expert['pending_banking']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge" style="background-color: #cbd5e1;">0</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="view-profile.php?id=<?php echo $expert['expert_id']; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fas fa-eye"></i> View Profile
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No experts with pending items.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="expert-profiles.php" class="btn btn-primary">
                        <i class="fas fa-list"></i> View all experts with pending items
                    </a>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-history"></i> Recent Activities</h2>
                        <a href="notifications" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if ($recent_activities_result && $recent_activities_result->num_rows > 0): ?>
                            <?php while ($activity = $recent_activities_result->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <?php
                                    $icon_class = '';
                                    switch ($activity['type']) {
                                        case 'New User':
                                            $icon_class = 'user';
                                            $icon = 'fa-user-plus';
                                            break;
                                        case 'New Consultation':
                                            $icon_class = 'consultation';
                                            $icon = 'fa-calendar-check';
                                            break;
                                        case 'New Payment':
                                            $icon_class = 'payment';
                                            $icon = 'fa-money-bill-wave';
                                            break;
                                        case 'New Report':
                                            $icon_class = 'report';
                                            $icon = 'fa-flag';
                                            break;
                                        default:
                                            $icon_class = 'user';
                                            $icon = 'fa-bell';
                                    }
                                    ?>
                                    <div class="activity-icon <?php echo $icon_class; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title"><?php echo htmlspecialchars($activity['type']); ?></div>
                                        <div class="activity-details">
                                            <?php echo htmlspecialchars($activity['name']); ?> - 
                                            <span class="activity-status"><?php echo htmlspecialchars($activity['details']); ?></span>
                                        </div>
                                        <div class="activity-time"><?php echo formatDate($activity['date'], 'd/m/Y H:i'); ?></div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-center p-4">No recent activities found.</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="#" class="btn btn-primary">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </a>
                    </div>
                </div>
                
                <!-- Pending Items -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="fas fa-tasks"></i> Pending Items</h2>
                    </div>
                    <div class="card-body">
                        <div class="pending-item">
                            <div class="pending-item-title">
                                <i class="fas fa-user-tie"></i> Expert Profiles
                            </div>
                            <a href="expert-profiles.php?status=pending" class="pending-item-count <?php echo $pending_experts > 0 ? 'warning' : ''; ?>">
                                <?php echo $pending_experts; ?>
                            </a>
                        </div>
                        
                        <div class="pending-item">
                            <div class="pending-item-title">
                                <i class="fas fa-certificate"></i> Expert Items
                            </div>
                            <a href="expert-profiles.php?status=pending" class="pending-item-count <?php echo $total_pending_expert_items > 0 ? 'warning' : ''; ?>">
                                <?php echo $total_pending_expert_items; ?>
                            </a>
                        </div>
                        
                        <div class="pending-item">
                            <div class="pending-item-title">
                                <i class="fas fa-money-bill-wave"></i> Withdrawal Requests
                            </div>
                            <a href="withdrawal-requests.php" class="pending-item-count <?php echo $pending_withdrawals > 0 ? 'warning' : ''; ?>">
                                <?php echo $pending_withdrawals; ?>
                            </a>
                        </div>
                        
                        <div class="pending-item">
                            <div class="pending-item-title">
                                <i class="fas fa-wallet"></i> Fund Requests
                            </div>
                            <a href="fund-requests.php" class="pending-item-count <?php echo $pending_fund_requests > 0 ? 'warning' : ''; ?>">
                                <?php echo $pending_fund_requests; ?>
                            </a>
                        </div>
                        
                        <div class="pending-item">
                            <div class="pending-item-title">
                                <i class="fas fa-flag"></i> Reports
                            </div>
                            <a href="reports.php" class="pending-item-count <?php echo $pending_reports > 0 ? 'danger' : ''; ?>">
                                <?php echo $pending_reports; ?>
                            </a>
                        </div>
                        
                        <div class="pending-item">
                            <div class="pending-item-title">
                                <i class="fas fa-comments"></i> Support Messages
                            </div>
                            <a href="client-messages.php" class="pending-item-count <?php echo $pending_messages > 0 ? 'warning' : ''; ?>">
                                <?php echo $pending_messages; ?>
                            </a>
                        </div>
                        
                        <div class="pending-item">
                            <div class="pending-item-title">
                                <i class="fas fa-bell"></i> Unread Notifications
                            </div>
                            <a href="notifications.php" class="pending-item-count <?php echo $unread_notifications_count > 0 ? 'warning' : ''; ?>">
                                <?php echo $unread_notifications_count; ?>
                            </a>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="#" class="btn btn-primary" onclick="window.location.reload()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                <div class="card-body" style="display: flex; flex-wrap: wrap; gap: 1rem;">
                    <a href="expert-profiles.php" class="btn btn-primary">
                        <i class="fas fa-user-tie"></i> Manage Experts
                    </a>
                    <a href="users.php" class="btn btn-info">
                        <i class="fas fa-users"></i> Manage Users
                    </a>
                    <a href="consultations.php" class="btn btn-success">
                        <i class="fas fa-calendar-check"></i> View Consultations
                    </a>
                    <a href="reports.php" class="btn btn-warning">
                        <i class="fas fa-flag"></i> Handle Reports
                    </a>
                    <a href="settings.php" class="btn btn-danger">
                        <i class="fas fa-cog"></i> System Settings
                    </a>
                    <a href="expert-profiles.php?status=pending" class="btn btn-primary">
                        <i class="fas fa-certificate"></i> Pending Expert Items
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
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
