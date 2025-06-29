<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email']) || $_SESSION['user_role'] != 'client') {
    header("Location: ../config/logout.php");
    exit;
}
$userId = $_SESSION['user_id'];

// Update user status to Online
$updateStatusQuery = "UPDATE users SET status = 'Online' WHERE id = $userId";
$conn->query($updateStatusQuery);

// Get user balance
$balanceQuery = "SELECT balance FROM users WHERE id = $userId";
$balanceResult = $conn->query($balanceQuery);
if ($balanceResult && $balanceResult->num_rows > 0) {
    $userBalance = $balanceResult->fetch_assoc()['balance'];
} else {
    $userBalance = 0;
}

// Fetch site settings
$settingsQuery = "SELECT * FROM settings";
$settingsResult = $conn->query($settingsQuery);
$settings = [];

if ($settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Fetch user profile
$userProfileQuery = "SELECT * FROM user_profiles WHERE user_id = $userId";
$userProfileResult = $conn->query($userProfileQuery);
$userProfile = null;

if ($userProfileResult && $userProfileResult->num_rows > 0) {
    $userProfile = $userProfileResult->fetch_assoc();
}

// Fetch user's support messages
$supportMessagesQuery = "SELECT sm.*, 
                        (SELECT COUNT(*) FROM support_message_replies WHERE message_id = sm.id) as reply_count,
                        (SELECT COUNT(*) FROM support_responses WHERE message_id = sm.id) as admin_reply_count
                        FROM support_messages sm 
                        WHERE sm.user_id = $userId 
                        ORDER BY sm.created_at DESC";
$supportMessagesResult = $conn->query($supportMessagesQuery);
$supportMessages = [];

if ($supportMessagesResult && $supportMessagesResult->num_rows > 0) {
    while ($row = $supportMessagesResult->fetch_assoc()) {
        $supportMessages[] = $row;
    }
}

// Fetch user's reports
$reportsQuery = "SELECT r.*, 
                c.consultation_date, c.consultation_time,
                u.full_name as reported_name,
                (CASE WHEN u.role = 'expert' THEN 'Expert' ELSE 'Client' END) as reported_role
                FROM reports r
                JOIN consultations c ON r.consultation_id = c.id
                JOIN users u ON r.reported_id = u.id
                WHERE r.reporter_id = $userId
                ORDER BY r.created_at DESC";
$reportsResult = $conn->query($reportsQuery);
$reports = [];

if ($reportsResult && $reportsResult->num_rows > 0) {
    while ($row = $reportsResult->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Fetch notifications for logged-in user
$notificationsQuery = "SELECT * FROM client_notifications 
                      WHERE user_id = $userId AND is_read = 0
                      ORDER BY created_at DESC
                      LIMIT 5";
$notificationsResult = $conn->query($notificationsQuery);
$notifications = [];
$notificationCount = 0;

if ($notificationsResult && $notificationsResult->num_rows > 0) {
    $notificationCount = $notificationsResult->num_rows;
    while ($row = $notificationsResult->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Get user's name
$userNameQuery = "SELECT full_name FROM users WHERE id = $userId";
$userNameResult = $conn->query($userNameQuery);
$userName = "";
if ($userNameResult && $userNameResult->num_rows > 0) {
    $userName = $userNameResult->fetch_assoc()['full_name'];
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-warning';
       
        case 'accepted':
            return 'bg-success';
        case 'rejected':
            return 'bg-refund';
        case 'remborser':
        case 'handled':
            return 'bg-info';
        default:
            return 'bg-secondary';
    }
}

// Function to format date
function formatDate($dateString) {
    $date = new DateTime($dateString);
    return $date->format('M d, Y - H:i');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reports & Support Messages - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
    <!-- Favicon -->
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- Custom CSS -->
    <style>
        :root {
            /* Primary Colors */
            --primary-50: #eff6ff;
            --primary-100: #dbeafe;
            --primary-200: #bfdbfe;
            --primary-300: #93c5fd;
            --primary-400: #60a5fa;
            --primary-500: #3b82f6;
            --primary-600: #2563eb;
            --primary-700: #1d4ed8;
            --primary-800: #1e40af;
            --primary-900: #1e3a8a;
            --primary-950: #172554;
            
            /* Secondary Colors */
            --secondary-50: #f5f3ff;
            --secondary-100: #ede9fe;
            --secondary-200: #ddd6fe;
            --secondary-300: #c4b5fd;
            --secondary-400: #a78bfa;
            --secondary-500: #8b5cf6;
            --secondary-600: #7c3aed;
            --secondary-700: #6d28d9;
            --secondary-800: #5b21b6;
            --secondary-900: #4c1d95;
            --secondary-950: #2e1065;
            
            /* Accent Colors */
            --accent-50: #fff7ed;
            --accent-100: #ffedd5;
            --accent-200: #fed7aa;
            --accent-300: #fdba74;
            --accent-400: #fb923c;
            --accent-500: #f97316;
            --accent-600: #ea580c;
            --accent-700: #c2410c;
            --accent-800: #9a3412;
            --accent-900: #7c2d12;
            --accent-950: #431407;
            
            /* Success Colors */
            --success-50: #ecfdf5;
            --success-100: #d1fae5;
            --success-200: #a7f3d0;
            --success-300: #6ee7b7;
            --success-400: #34d399;
            --success-500: #10b981;
            --success-600: #059669;
            --success-700: #047857;
            --success-800: #065f46;
            --success-900: #064e3b;
            --success-950: #022c22;
            
            /* Warning Colors */
            --warning-50: #fffbeb;
            --warning-100: #fef3c7;
            --warning-200: #fde68a;
            --warning-300: #fcd34d;
            --warning-400: #fbbf24;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            --warning-700: #b45309;
            --warning-800: #92400e;
            --warning-900: #78350f;
            --warning-950: #451a03;
            
            /* Danger Colors */
            --danger-50: #fef2f2;
            --danger-100: #fee2e2;
            --danger-200: #fecaca;
            --danger-300: #fca5a5;
            --danger-400: #f87171;
            --danger-500: #ef4444;
            --danger-600: #dc2626;
            --danger-700: #b91c1c;
            --danger-800: #991b1b;
            --danger-900: #7f1d1d;
            --danger-950: #450a0a;
            
            /* Gray Colors */
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --gray-950: #030712;
            
            --refund: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            /* UI Variables */
            --border-radius-sm: 0.375rem;
            --border-radius: 0.5rem;
            --border-radius-md: 0.75rem;
            --border-radius-lg: 1rem;
            --border-radius-xl: 1.5rem;
            --border-radius-2xl: 2rem;
            --border-radius-3xl: 3rem;
            --border-radius-full: 9999px;
            
            --box-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --box-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --box-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --box-shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --box-shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Base Styles */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            line-height: 1.3;
            color: var(--gray-900);
        }
        
        a {
            text-decoration: none;
            color: var(--primary-600);
            transition: var(--transition);
        }
        
        a:hover {
            color: var(--primary-700);
        }
        
        /* Preloader */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-in-out, visibility 0.5s ease-in-out;
        }
        
        .preloader.fade-out {
            opacity: 0;
            visibility: hidden;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            position: relative;
        }
        
        .spinner-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 3px solid transparent;
            border-top-color: var(--primary-600);
            border-radius: 50%;
            animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        }
        
        .spinner-ring:nth-child(1) {
            animation-delay: -0.45s;
        }
        
        .spinner-ring:nth-child(2) {
            animation-delay: -0.3s;
            width: 80%;
            height: 80%;
            top: 10%;
            left: 10%;
            border-top-color: var(--secondary-600);
        }
        
        .spinner-ring:nth-child(3) {
            animation-delay: -0.15s;
            width: 60%;
            height: 60%;
            top: 20%;
            left: 20%;
            border-top-color: var(--accent-600);
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
       /* Navbar */
        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--box-shadow);
            transition: all 0.4s ease;
            padding: 20px 0;
            z-index: 1000;
        }
        
        .navbar.scrolled {
            padding: 12px 0;
            box-shadow: var(--box-shadow-md);
            background-color: rgba(255, 255, 255, 0.98);
        }
        
        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--primary-600);
            transition: transform 0.3s ease;
            font-family: 'Manrope', sans-serif;
        }
        
        .navbar-brand:hover {
            transform: scale(1.05);
            color: var(--primary-700);
        }
        
        .navbar-brand img {
            height: 40px;
            transition: var(--transition);
        }
        
        .navbar.scrolled .navbar-brand img {
            height: 35px;
        }
        
        .nav-link {
            position: relative;
            margin: 0 12px;
            padding: 8px 0;
            font-weight: 600;
            color: var(--gray-700);
            transition: color 0.3s ease;
            font-size: 0.95rem;
        }
        
        .nav-link:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: 0;
            left: 0;
            background-color: var(--primary-600);
            transition: width 0.3s ease;
            border-radius: 2px;
        }
        
        .nav-link:hover {
            color: var(--primary-600);
        }
        
        .nav-link:hover:after, .nav-link.active:after {
            width: 100%;
        }
        
        .nav-link.active {
            color: var(--primary-600);
            font-weight: 700;
        }
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(124, 58, 237, 0.9));
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0 70px;
            border-radius: 0 0 var(--border-radius-3xl) var(--border-radius-3xl);
            margin-bottom: 60px;
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 0;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            z-index: 0;
        }
        
        .page-header-content {
            position: relative;
            z-index: 1;
        }
        
        .page-title {
            font-weight: 800;
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .page-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            max-width: 700px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
       
        /* Card Styles */
        .card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .card-header {
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            padding: 15px 20px;
        }
        
        .card-body {
            padding: 20px;
        }
        
        /* Tab Navigation */
        .nav-tabs {
            border-bottom: 1px solid var(--gray-200);
            margin-bottom: 30px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 3px solid transparent;
            border-radius: 0;
            font-weight: 600;
            color: var(--gray-600);
            padding: 10px 20px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-600);
            border-bottom-color: var(--primary-200);
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-600);
            background-color: transparent;
            border-bottom-color: var(--primary-600);
        }
        
        /* Message/Report Cards */
        .message-card {
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            background-color: white;
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .message-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .message-header {
            padding: 15px 20px;
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message-title {
            font-weight: 700;
            margin-bottom: 0;
            color: var(--gray-900);
        }
        
        .message-body {
            padding: 20px;
        }
        
        .message-footer {
            padding: 15px 20px;
            background-color: var(--gray-50);
            border-top: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .message-meta {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .message-actions {
            display: flex;
            gap: 10px;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: var(--border-radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }
        
        /* Modal Styles */
        .modal-content {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-xl);
        }
        
        .modal-header {
            border-bottom: 1px solid var(--gray-200);
            padding: 20px;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .modal-footer {
            border-top: 1px solid var(--gray-200);
            padding: 20px;
        }
        
        /* Reply Section */
        .reply-section {
            background-color: var(--gray-50);
            border-radius: var(--border-radius-lg);
            padding: 20px;
            margin-top: 20px;
        }
        
        .reply-item {
            background-color: white;
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: var(--box-shadow-sm);
        }
        
        .reply-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .reply-author {
            font-weight: 600;
            color: var(--gray-900);
        }
        
        .reply-date {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .reply-content {
            color: var(--gray-800);
        }
        
        .admin-reply {
            background-color: var(--primary-50);
            border-left: 4px solid var(--primary-500);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: var(--gray-400);
            margin-bottom: 20px;
        }
        
        .empty-state-text {
            color: var(--gray-600);
            font-size: 1.1rem;
            margin-bottom: 20px;
        }
        
        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--accent-600);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--box-shadow);
        }
         /* Buttons */
         .btn {
            padding: 12px 25px;
            font-weight: 600;
            border-radius: var(--border-radius-full);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
            font-size: 1rem;
            letter-spacing: 0.5px;
            box-shadow: var(--box-shadow);
        }
        
        .btn-lg {
            padding: 15px 35px;
            font-size: 1.125rem;
        }
        
        .btn-sm {
            padding: 8px 20px;
            font-size: 0.875rem;
        }
        
        .btn-primary {
            background-color: var(--primary-600);
            border-color: var(--primary-600);
            color: white;
        }
        
        .btn-primary:before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
            z-index: -1;
        }
        
        .btn-primary:hover:before {
            left: 100%;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-700);
            border-color: var(--primary-700);
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        /* Footer */
        .footer {
            background-color: var(--gray-900);
            color: white;
            padding: 100px 0 20px;
            position: relative;
            margin-top: 100px;
        }
        
        .footer-title {
            font-weight: 700;
            margin-bottom: 30px;
            position: relative;
            display: inline-block;
            font-size: 1.25rem;
            color: white;
        }
        
        .footer-title::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: -10px;
            width: 40px;
            height: 3px;
            background-color: var(--primary-600);
            border-radius: 2px;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            display: block;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            position: relative;
            padding-left: 20px;
            font-size: 1rem;
        }
        
        .footer-links a::before {
            content: '\f105';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 2px;
            color: var(--primary-600);
            transition: all 0.3s ease;
        }
        
        .footer-links a:hover {
            color: white;
            transform: translateX(5px);
        }
        
        .footer-links a:hover::before {
            color: var(--accent-600);
        }
        
        .social-links a {
            color: white;
            font-size: 1.25rem;
            margin-right: 20px;
            transition: all 0.3s ease;
            display: inline-block;
        }
        
        .social-links a:hover {
            color: var(--accent-600);
            transform: translateY(-5px);
        }
        
        .copyright {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Back to Top Button */
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background-color: var(--primary-600);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.125rem;
            box-shadow: var(--box-shadow);
            cursor: pointer;
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .back-to-top.active {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background-color: var(--primary-700);
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .page-title {
                font-size: 2rem;
            }
            
            .page-subtitle {
                font-size: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 30px;
                border-radius: 0;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .nav-tabs .nav-link {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
            
            .footer {
                padding: 60px 0 20px;
            }
            
            .footer-title {
                margin-top: 30px;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.5rem;
            }
            
            .message-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .message-header .status-badge {
                margin-top: 10px;
            }
            
            .message-footer {
                flex-direction: column;
                gap: 10px;
            }
            
            .back-to-top {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader">
        <div class="spinner">
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
        </div>
    </div>

    <!-- Back to Top Button -->
    <a href="#" class="back-to-top">
        <i class="fas fa-arrow-up"></i>
    </a>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <?php if(isset($settings['site_image']) && !empty($settings['site_image'])): ?>
                    <img src="../uploads/<?php echo $settings['site_image']; ?>" alt="<?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>" height="40">
                <?php else: ?>
                    <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="find-experts.php">Find Experts</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact-support.php">Contact Support</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my-reports.php">My reports</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" onclick="markNotificationsAsRead()">
                            <i class="fas fa-bell fs-5 text-gray-700"></i>
                            <?php if($notificationCount > 0): ?>
                                <span class="notification-badge" id="notification-badge"><?php echo $notificationCount; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" style="border-radius: 12px;">
                            <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
                            <div id="notifications-container" style="font-size:12px;">
                                <?php if(count($notifications) > 0): ?>
                                    <?php foreach($notifications as $notification): ?>
                                        <li>
                                            <a class="dropdown-item py-3 border-bottom" href="#">
                                                <small class="text-muted d-block"><?php echo date('M d, H:i', strtotime($notification['created_at'])); ?></small>
                                                <p class="mb-0 mt-1"><?php echo $notification['message']; ?></p>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>
                                <?php endif; ?>
                            </div>
                            <li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="notifications.php">View All</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a class="btn btn-outline-primary dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if($userProfile && !empty($userProfile['profile_image'])): ?>
                                <img src="<?php echo $userProfile['profile_image']; ?>" alt="Profile" class="rounded-circle" width="30" height="30">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <span class="d-none d-md-inline"><?php echo $userName; ?>
                                                            <span class="ms-2 badge bg-success"><?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>

                        </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="userDropdown" style="border-radius: 12px;">
                                <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
                                <li><a class="dropdown-item py-2" href="add-fund.php"><i class="fas fa-wallet me-2 text-primary"></i> Add  Fund: <?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></a></li>
                                <li><a class="dropdown-item py-2" href="my-consultations.php"><i class="fas fa-calendar-check me-2 text-primary"></i> My Consultations</a></li>
                                <li><a class="dropdown-item py-2" href="messages.php"><i class="fas fa-envelope me-2 text-primary"></i> Messages</a></li>
                                <li><a class="dropdown-item py-2" href="my-reports.php"><i class="fas fa-flag me-2 text-primary"></i> My Reports</a></li>
                            <li><a class="dropdown-item py-2" href="history-ratings.php"><i class="fas fa-solid fa-star text-primary"></i> Ratings</a></li>

                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2" href="../Config/logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
                            </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="page-header-content">
                <h1 class="page-title">My Reports & Support Messages</h1>
                <p class="page-subtitle">View and manage your support requests and reports</p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="container">
        <!-- Tab Navigation -->
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="support-tab" data-bs-toggle="tab" data-bs-target="#support-tab-pane" type="button" role="tab" aria-controls="support-tab-pane" aria-selected="true">
                    <i class="fas fa-headset me-2"></i> Support Messages <span class="badge bg-primary rounded-pill ms-2"><?php echo count($supportMessages); ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports-tab-pane" type="button" role="tab" aria-controls="reports-tab-pane" aria-selected="false">
                    <i class="fas fa-flag me-2"></i> Reports <span class="badge bg-primary rounded-pill ms-2"><?php echo count($reports); ?></span>
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="myTabContent">
            <!-- Support Messages Tab -->
            <div class="tab-pane fade show active" id="support-tab-pane" role="tabpanel" aria-labelledby="support-tab" tabindex="0">
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <h2 class="h4 mb-0">Support Messages</h2>
                            <a href="contact-support.php" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> New Support Request
                            </a>
                        </div>
                    </div>
                    
                    <?php if(count($supportMessages) > 0): ?>
                        <?php foreach($supportMessages as $message): ?>
                            <div class="col-md-6">
                                <div class="message-card">
                                    <div class="message-header">
                                        <h5 class="message-title"><?php echo htmlspecialchars($message['subject']); ?></h5>
                                        <span class="status-badge <?php echo getStatusBadgeClass($message['status']); ?>">
                                            <?php echo ucfirst($message['status']); ?>
                                        </span>
                                    </div>
                                    <div class="message-body">
                                        <p class="mb-3">
                                            <?php 
                                            $messageText = htmlspecialchars($message['message']);
                                            echo (strlen($messageText) > 150) ? substr($messageText, 0, 150) . '...' : $messageText; 
                                            ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-light text-dark me-2">
                                                    <i class="fas fa-tag me-1"></i> <?php echo ucfirst($message['contact_type']); ?>
                                                </span>
                                                <?php if($message['consultation_id']): ?>
                                                    <span class="badge bg-light text-dark">
                                                        <i class="fas fa-calendar-check me-1"></i> Consultation #<?php echo $message['consultation_id']; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <?php if($message['reply_count'] > 0 || $message['admin_reply_count'] > 0): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-comments me-1"></i> 
                                                        <?php echo ($message['reply_count'] + $message['admin_reply_count']); ?> 
                                                        <?php echo ($message['reply_count'] + $message['admin_reply_count']) > 1 ? 'Replies' : 'Reply'; ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="message-footer">
                                        <div class="message-meta">
                                            <i class="far fa-clock me-1"></i> <?php echo formatDate($message['created_at']); ?>
                                        </div>
                                        <div class="message-actions">
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#supportModal<?php echo $message['id']; ?>">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Support Message Modal -->
                            <div class="modal fade" id="supportModal<?php echo $message['id']; ?>" tabindex="-1" aria-labelledby="supportModalLabel<?php echo $message['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="supportModalLabel<?php echo $message['id']; ?>">
                                                Support Request: <?php echo htmlspecialchars($message['subject']); ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-4">
                                                <div class="d-flex justify-content-between mb-3">
                                                    <div>
                                                        <span class="badge bg-light text-dark me-2">
                                                            <i class="fas fa-tag me-1"></i> <?php echo ucfirst($message['contact_type']); ?>
                                                        </span>
                                                        <?php if($message['consultation_id']): ?>
                                                            <span class="badge bg-light text-dark">
                                                                <i class="fas fa-calendar-check me-1"></i> Consultation #<?php echo $message['consultation_id']; ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <span class="status-badge <?php echo getStatusBadgeClass($message['status']); ?>">
                                                        <?php echo ucfirst($message['status']); ?>
                                                    </span>
                                                </div>
                                                <p class="text-muted small mb-3">
                                                    <i class="far fa-clock me-1"></i> Submitted on <?php echo formatDate($message['created_at']); ?>
                                                </p>
                                                <div class="card">
                                                    <div class="card-body">
                                                        <p><?php echo nl2br(htmlspecialchars($message['message'])); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                           
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <h3>No Support Messages</h3>
                                <p class="empty-state-text">You haven't submitted any support requests yet.</p>
                                <a href="contact-support.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i> Create Support Request
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reports Tab -->
            <div class="tab-pane fade" id="reports-tab-pane" role="tabpanel" aria-labelledby="reports-tab" tabindex="0">
                <div class="row">
                    <div class="col-12 mb-4">
                        <h2 class="h4 mb-0">My Reports</h2>
                    </div>
                    
                    <?php if(count($reports) > 0): ?>
                        <?php foreach($reports as $report): ?>
                            <div class="col-md-6">
                                <div class="message-card">
                                    <div class="message-header">
                                        <h5 class="message-title">Report #<?php echo $report['id']; ?></h5>
                                        <span class="status-badge <?php echo getStatusBadgeClass($report['status']); ?>">
                                            <?php echo ucfirst($report['status']); ?>
                                        </span>
                                    </div>
                                    <div class="message-body">
                                        <p class="mb-3">
                                            <?php 
                                            $reportText = htmlspecialchars($report['message']);
                                            echo (strlen($reportText) > 150) ? substr($reportText, 0, 150) . '...' : $reportText; 
                                            ?>
                                        </p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-light text-dark me-2">
                                                    <i class="fas fa-tag me-1"></i> <?php echo ucfirst($report['report_type']); ?>
                                                </span>
                                                <span class="badge bg-light text-dark">
                                                    <i class="fas fa-calendar-check me-1"></i> Consultation #<?php echo $report['consultation_id']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="message-footer">
                                        <div class="message-meta">
                                            <i class="far fa-clock me-1"></i> <?php echo formatDate($report['created_at']); ?>
                                        </div>
                                        <div class="message-actions">
                                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#reportModal<?php echo $report['id']; ?>">
                                                <i class="fas fa-eye me-1"></i> View Details
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Report Modal -->
                            <div class="modal fade" id="reportModal<?php echo $report['id']; ?>" tabindex="-1" aria-labelledby="reportModalLabel<?php echo $report['id']; ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="reportModalLabel<?php echo $report['id']; ?>">
                                                Report #<?php echo $report['id']; ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="mb-4">
                                                <div class="d-flex justify-content-between mb-3">
                                                    <div>
                                                        <span class="badge bg-light text-dark me-2">
                                                            <i class="fas fa-tag me-1"></i> <?php echo ucfirst($report['report_type']); ?>
                                                        </span>
                                                        <span class="badge bg-light text-dark">
                                                            <i class="fas fa-calendar-check me-1"></i> Consultation #<?php echo $report['consultation_id']; ?>
                                                        </span>
                                                    </div>
                                                    <span class="status-badge <?php echo getStatusBadgeClass($report['status']); ?>">
                                                        <?php echo ucfirst($report['status']); ?>
                                                    </span>
                                                </div>
                                                <p class="text-muted small mb-3">
                                                    <i class="far fa-clock me-1"></i> Submitted on <?php echo formatDate($report['created_at']); ?>
                                                </p>
                                                
                                                <div class="card mb-4">
                                                    <div class="card-header bg-light">
                                                        <h6 class="mb-0">Report Details</h6>
                                                    </div>
                                                    <div class="card-body">
                                                        <div class="row mb-3">
                                                            <div class="col-md-6">
                                                                <p class="mb-1 fw-bold">Reported User:</p>
                                                                <p><?php echo htmlspecialchars($report['reported_name']); ?> (<?php echo $report['reported_role']; ?>)</p>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <p class="mb-1 fw-bold">Consultation Date:</p>
                                                                <p><?php echo date('M d, Y', strtotime($report['consultation_date'])); ?> at <?php echo date('H:i', strtotime($report['consultation_time'])); ?></p>
                                                            </div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <p class="mb-1 fw-bold">Report Message:</p>
                                                            <p><?php echo nl2br(htmlspecialchars($report['message'])); ?></p>
                                                        </div>
                                                        <?php if(!empty($report['admin_notes']) && ($report['status'] == 'reviewed' || $report['status'] == 'accepted')): ?>
                                                        <div class="alert alert-info">
                                                            <p class="mb-1 fw-bold">Admin Response:</p>
                                                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($report['admin_notes'])); ?></p>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-flag"></i>
                                </div>
                                <h3>No Reports</h3>
                                <p class="empty-state-text">You haven't submitted any reports yet.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-5">
                    <h5 class="footer-title">
                        <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>
                    </h5>
                    <p class="mb-4"><?php echo isset($settings['site_description']) ? $settings['site_description'] : 'Connect with top professionals across various fields and get the guidance you need, when you need it. Our platform brings expertise directly to you.'; ?></p>
                    <div class="social-links mt-3">
                        <?php if(isset($settings['facebook_url']) && !empty($settings['facebook_url'])): ?>
                            <a href="<?php echo $settings['facebook_url']; ?>" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if(isset($settings['instagram_url']) && !empty($settings['instagram_url'])): ?>
                            <a href="<?php echo $settings['instagram_url']; ?>" target="_blank"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-2 mb-5">
                    <h5 class="footer-title">Quick Links</h5>
                    <div class="footer-links">
                        <a href="../index.php">Home</a>
                        <a href="find-experts.php">Find Experts</a>
                        <a href="how-it-works.php">How It Works</a>
                        <a href="contact-support.php">Contact Support</a>
                    </div>
                </div>
                <div class="col-md-2 mb-5">
                    <h5 class="footer-title">Popular Categories</h5>
                    <div class="footer-links">
                        <?php 
                        // Get top 4 categories
                        $topCategoriesQuery = "SELECT c.id, c.name, COUNT(ep.id) as expert_count 
                                              FROM categories c
                                              LEFT JOIN expert_profiledetails ep ON c.id = ep.category AND ep.status = 'approved'
                                              GROUP BY c.id, c.name
                                              ORDER BY expert_count DESC, c.name ASC
                                              LIMIT 4";
                        $topCategoriesResult = $conn->query($topCategoriesQuery);
                        
                        if ($topCategoriesResult && $topCategoriesResult->num_rows > 0) {
                            while ($row = $topCategoriesResult->fetch_assoc()) {
                                echo '<a href="find-experts.php?category=' . $row['id'] . '">' . ucfirst($row['name']) . '</a>';
                            }
                        }
                        ?>
                    </div>
                </div>
                <div class="col-md-4 mb-5">
                    <h5 class="footer-title">Contact Us</h5>
                    <div class="footer-links">
                        <?php if(isset($settings['phone_number1']) && !empty($settings['phone_number1'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($settings['phone_number1']); ?>"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($settings['phone_number1']); ?></a>
                        <?php endif; ?>
                        <?php if(isset($settings['phone_number2']) && !empty($settings['phone_number2'])): ?>
                            <a href="tel:<?php echo htmlspecialchars($settings['phone_number2']); ?>"><i class="fas fa-phone-alt me-2"></i> <?php echo htmlspecialchars($settings['phone_number2']); ?></a>
                        <?php endif; ?>
                        <?php if(isset($settings['site_email']) && !empty($settings['site_email'])): ?>
                            <a href="mailto:<?php echo htmlspecialchars($settings['site_email']); ?>"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($settings['site_email']); ?></a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>. All Rights Reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <!-- Custom JS -->
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            mirror: false,
            offset: 50
        });

        // Preloader
        window.addEventListener('load', function() {
            const preloader = document.querySelector('.preloader');
            preloader.classList.add('fade-out');
            setTimeout(function() {
                preloader.style.display = 'none';
            }, 500);
        });

        // Navbar Scroll Effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            
            // Back to Top Button
            const backToTop = document.querySelector('.back-to-top');
            if (window.scrollY > 300) {
                backToTop.classList.add('active');
            } else {
                backToTop.classList.remove('active');
            }
        });

        // Back to Top Button Click
        document.querySelector('.back-to-top').addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
// Add this JavaScript code at the end of the file, just before the closing </body> tag

// Function to mark notifications as read
function markNotificationsAsRead() {
    // Hide the notification badge immediately for better UX
    const badge = document.getElementById('notification-badge');
    if (badge) {
        badge.style.display = 'none';
    }
    
    // Send AJAX request to mark notifications as read
    fetch('mark-notifications-read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'mark_read' }),
    })
    .then(response => response.json())
    .then(data => {
        console.log('Notifications marked as read:', data);
    })
    .catch(error => {
        console.error('Error marking notifications as read:', error);
    });
}

// Function to fetch notifications
function fetchNotifications() {
    fetch('get-notifications.php')
        .then(response => response.json())
        .then(data => {
            // Update notification badge
            const badge = document.getElementById('notification-badge');
            if (data.count > 0) {
                if (badge) {
                    badge.textContent = data.count;
                    badge.style.display = 'flex';
                } else {
                    const newBadge = document.createElement('span');
                    newBadge.id = 'notification-badge';
                    newBadge.className = 'notification-badge';
                    newBadge.textContent = data.count;
                    document.querySelector('#notificationDropdown').appendChild(newBadge);
                }
            } else {
                if (badge) {
                    badge.style.display = 'none';
                }
            }
            
            // Update notification list
            const container = document.getElementById('notifications-container');
            if (container) {
                let html = '';
                if (data.notifications.length > 0) {
                    data.notifications.forEach(notification => {
                        html += `
                            <li>
                                <a class="dropdown-item py-3 border-bottom" href="#">
                                    <small class="text-muted d-block">${notification.date}</small>
                                    <p class="mb-0 mt-1">${notification.message}</p>
                                </a>
                            </li>
                        `;
                    });
                } else {
                    html = '<li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>';
                }
                container.innerHTML = html;
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

// Fetch notifications every second
setInterval(fetchNotifications, 1000);


        // Handle tab persistence with URL hash
        document.addEventListener('DOMContentLoaded', function() {
            // Check for hash in URL
            let hash = window.location.hash;
            if (hash) {
                // Find the tab with this hash and activate it
                const tab = document.querySelector(`a[href="${hash}"]`);
                if (tab) {
                    tab.click();
                }
            }

            // Add hash to URL when tab is clicked
            const tabs = document.querySelectorAll('button[data-bs-toggle="tab"]');
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const id = e.target.getAttribute('id');
                    if (id === 'support-tab') {
                        history.pushState(null, null, '#support');
                    } else if (id === 'reports-tab') {
                        history.pushState(null, null, '#reports');
                    }
                });
            });
        });

        // Submit reply form via AJAX
        const replyForms = document.querySelectorAll('form[action="submit-reply.php"]');
        replyForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const messageId = form.querySelector('input[name="message_id"]').value;
                const replyText = form.querySelector('textarea[name="reply_text"]').value;
                const submitButton = form.querySelector('button[type="submit"]');
                
                // Disable button and show loading state
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Sending...';
                
                // Send AJAX request
                fetch('submit-reply.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `message_id=${messageId}&reply_text=${encodeURIComponent(replyText)}`,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to show the new reply
                        window.location.reload();
                    } else {
                        alert('Error: ' + data.message);
                        submitButton.disabled = false;
                        submitButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Send Reply';
                    }
                })
                .catch(error => {
                    console.error('Error submitting reply:', error);
                    alert('An error occurred. Please try again.');
                    submitButton.disabled = false;
                    submitButton.innerHTML = '<i class="fas fa-paper-plane me-2"></i> Send Reply';
                });
            });

        });
    </script>
</body>
</html>
