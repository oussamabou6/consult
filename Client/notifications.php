<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Redirect if not logged in
if ($isLoggedIn) {
    if ($_SESSION['user_role'] != 'client') {
        header("Location: ../config/logout.php");
        exit;
    }
}

// Update user status to Online if logged in
if ($isLoggedIn) {
    $updateStatusQuery = "UPDATE users SET status = 'Online' WHERE id = $userId";
    $conn->query($updateStatusQuery);
    
    // Get user balance if logged in
    $balanceQuery = "SELECT balance FROM users WHERE id = $userId";
    $balanceResult = $conn->query($balanceQuery);
    if ($balanceResult && $balanceResult->num_rows > 0) {
        $userBalance = $balanceResult->fetch_assoc()['balance'];
    } else {
        $userBalance = 0;
    }
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

// Get user profile if logged in
$userProfile = null;
if ($isLoggedIn) {
    $userProfileQuery = "SELECT * FROM user_profiles WHERE user_id = $userId";
    $userProfileResult = $conn->query($userProfileQuery);
    
    if ($userProfileResult && $userProfileResult->num_rows > 0) {
        $userProfile = $userProfileResult->fetch_assoc();
    }
}

// Mark all notifications as read when the page loads
if (isset($_GET['mark_all_read']) && $_GET['mark_all_read'] == 1) {
    $markReadQuery = "UPDATE client_notifications SET is_read = 1 WHERE user_id = $userId";
    $conn->query($markReadQuery);
    header("Location: notifications.php");
    exit();
}

// Delete notification if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notificationId = $_GET['delete'];
    $deleteQuery = "DELETE FROM client_notifications WHERE id = $notificationId AND user_id = $userId";
    $conn->query($deleteQuery);
    header("Location: notifications.php");
    exit();
}

// Pagination settings
$notificationsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $notificationsPerPage;

// Fetch all notifications for logged-in user with pagination
$notificationsQuery = "SELECT * FROM client_notifications 
                      WHERE user_id = $userId
                      ORDER BY created_at DESC
                      LIMIT $offset, $notificationsPerPage";
$notificationsResult = $conn->query($notificationsQuery);
$notifications = [];

if ($notificationsResult && $notificationsResult->num_rows > 0) {
    while ($row = $notificationsResult->fetch_assoc()) {
        $notifications[] = $row;
    }
}

// Count total notifications for pagination
$totalNotificationsQuery = "SELECT COUNT(*) as total FROM client_notifications WHERE user_id = $userId";
$totalNotificationsResult = $conn->query($totalNotificationsQuery);
$totalNotifications = $totalNotificationsResult->fetch_assoc()['total'];
$totalPages = ceil($totalNotifications / $notificationsPerPage);

// Count unread notifications
$unreadNotificationsQuery = "SELECT COUNT(*) as count FROM client_notifications WHERE user_id = $userId AND is_read = 0";
$unreadNotificationsResult = $conn->query($unreadNotificationsQuery);
$unreadNotificationsCount = $unreadNotificationsResult->fetch_assoc()['count'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
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
            color: var(--gray-800);
            overflow-x: hidden;
            line-height: 1.6;
            background-color: var(--gray-50);
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
            font-family: 'Montserrat', sans-serif;
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
            background: 
            linear-gradient(135deg, rgba(2, 132, 199, 0.9), rgba(124, 58, 237, 0.9)),
            url('../photo/photo-1557804506-669a67965ba0.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 80px 0 50px;
            position: relative;
            overflow: hidden;
            border-radius: 0 0 0 100px;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 0;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -150px;
            left: -150px;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            z-index: 0;
        }
        
        .page-header-content {
            position: relative;
            z-index: 1;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto 30px;
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
       
        /* Notification Badge */
        .notification-badge {
            display: none;
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background-color: #ef4444;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            animation: pulse 1.5s infinite;
            z-index: 10;
        }
        
        .notification-badge.show {
            display: block;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.8;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        /* Notifications Page Styles */
        .notifications-container {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            margin-top: -50px;
            position: relative;
            z-index: 10;
            margin-bottom: 40px;
            overflow: hidden;
        }
        
        .notifications-header {
            padding: 20px 30px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notifications-title {
            font-size: 1.5rem;
            margin-bottom: 0;
        }
        
        .notifications-actions {
            display: flex;
            gap: 10px;
        }
        
        .notification-item {
            padding: 20px 30px;
            border-bottom: 1px solid var(--gray-100);
            transition: var(--transition);
            position: relative;
        }
        
        .notification-item:hover {
            background-color: var(--gray-50);
        }
        
        .notification-item.unread {
            background-color: var(--primary-50);
        }
        
        .notification-item.unread:hover {
            background-color: var(--primary-100);
        }
        
        .notification-content {
            margin-bottom: 10px;
            font-size: 1rem;
            color: var(--gray-800);
        }
        
        .notification-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: var(--gray-500);
        }
        
        .notification-actions {
            display: flex;
            gap: 15px;
        }
        
        .notification-action {
            color: var(--gray-500);
            font-size: 0.9rem;
            transition: var(--transition);
        }
        
        .notification-action:hover {
            color: var(--primary-600);
        }
        
        .notification-action.delete:hover {
            color: var(--danger-600);
        }
        
        .notification-indicator {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: var(--primary-600);
        }
        
        .notification-empty {
            padding: 60px 30px;
            text-align: center;
        }
        
        .notification-empty-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .notification-empty-text {
            font-size: 1.2rem;
            color: var(--gray-600);
            margin-bottom: 10px;
        }
        
        .notification-empty-subtext {
            color: var(--gray-500);
            max-width: 400px;
            margin: 0 auto;
        }
        
        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin-top: 30px;
            margin-bottom: 30px;
        }
        
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 5px;
        }
        
        .pagination-item {
            margin: 0 5px;
        }
        
        .pagination-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: var(--border-radius);
            background-color: white;
            color: var(--gray-700);
            font-weight: 500;
            transition: var(--transition);
            box-shadow: var(--box-shadow-sm);
        }
        
        .pagination-link:hover {
            background-color: var(--gray-100);
            color: var(--gray-900);
        }
        
        .pagination-link.active {
            background-color: var(--primary-600);
            color: white;
        }
        
        .pagination-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
        
        
        
        .copyright {
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .page-title {
                font-size: 2.2rem;
            }
        }
        
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
                padding: 60px 0 80px;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .notifications-container {
                margin-top: -60px;
            }
            
            .notifications-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
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
            
            .page-subtitle {
                font-size: 0.9rem;
            }
            
            .notification-item {
                padding: 15px 20px;
            }
            
            .notification-meta {
                flex-direction: column;
                align-items: flex-start;
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
            <span class="navbar-  aria-expanded="false" aria-label="Toggle navigation">
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
            </ul>
            <div class="d-flex align-items-center">
                <?php if($isLoggedIn): ?>
                    <!-- Notification dropdown -->
                    <div class="dropdown me-3">
                        <a class="position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" onclick="markNotificationsAsRead()">
                            <i class="fas fa-bell fs-5 text-gray-700"></i>
                            <span class="notification-badge"></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" style="border-radius: 12px; min-width: 300px;">
                            <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
                            <div id="notifications-container" style="font-size:12px;">
                                <?php if(count($notifications) > 0): ?>
                                    <?php foreach(array_slice($notifications, 0, 5) as $notification): ?>
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
                            <span class="d-none d-md-inline">
                                <?php 
                                // Get user's full name from database
                                $userNameQuery = "SELECT full_name FROM users WHERE id = $userId";
                                $userNameResult = $conn->query($userNameQuery);
                                if ($userNameResult && $userNameResult->num_rows > 0) {
                                    echo $userNameResult->fetch_assoc()['full_name'];
                                } else {
                                    echo "My Profile";
                                }
                                ?>
                                
                            </span>
                            <span class="ms-2 badge bg-success"><?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>
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
                <?php else: ?>
                    <a href="../pages/login.php" class="btn btn-outline-primary me-2">Login</a>
                    <a href="../pages/profile.php" class="btn btn-primary">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<!-- Page Header -->
<section class="page-header">
    <div class="container">
        <div class="page-header-content text-center">
            <h1 class="page-title" data-aos="fade-up">Notifications</h1>
            <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">
                Stay updated with all your consultation activities and important updates.
            </p>
        </div>
    </div>
</section>

<!-- Notifications Content -->
<section class="container">
    <div class="notifications-container" data-aos="fade-up">
        <div class="notifications-header">
            <h3 class="notifications-title">
                Your Notifications
                <?php if($unreadNotificationsCount > 0): ?>
                    <span class="badge bg-primary ms-2"><?php echo $unreadNotificationsCount; ?> unread</span>
                <?php endif; ?>
            </h3>
            <div class="notifications-actions">
                <?php if(count($notifications) > 0): ?>
                    <a href="notifications.php?mark_all_read=1" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-check-double me-1"></i> Mark All as Read
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div id="notifications-list">
            <?php if(count($notifications) > 0): ?>
                <?php foreach($notifications as $notification): ?>
                    <div class="notification-item <?php echo $notification['is_read'] == 0 ? 'unread' : ''; ?>" id="notification-<?php echo $notification['id']; ?>">
                        <?php if($notification['is_read'] == 0): ?>
                            <div class="notification-indicator"></div>
                        <?php endif; ?>
                        <div class="notification-content">
                            <?php echo $notification['message']; ?>
                        </div>
                        <div class="notification-meta">
                            <div class="notification-time">
                                <i class="far fa-clock me-1"></i> <?php echo date('M d, Y - h:i A', strtotime($notification['created_at'])); ?>
                            </div>
                            <div class="notification-actions">
                                <?php if($notification['is_read'] == 0): ?>
                                    <a href="javascript:void(0)" class="notification-action mark-read" onclick="markAsRead(<?php echo $notification['id']; ?>)">
                                        <i class="fas fa-check me-1"></i> Mark as Read
                                    </a>
                                <?php endif; ?>
                                <a href="notifications.php?delete=<?php echo $notification['id']; ?>" class="notification-action delete" onclick="return confirm('Are you sure you want to delete this notification?');">
                                    <i class="fas fa-trash-alt me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if($totalPages > 1): ?>
                    <div class="pagination-container">
                        <ul class="pagination">
                            <?php if($page > 1): ?>
                                <li class="pagination-item">
                                    <a href="notifications.php?page=<?php echo $page - 1; ?>" class="pagination-link">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="pagination-item">
                                    <span class="pagination-link disabled">
                                        <i class="fas fa-chevron-left"></i>
                                    </span>
                                </li>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $startPage + 4);
                            
                            if($startPage > 1): ?>
                                <li class="pagination-item">
                                    <a href="notifications.php?page=1" class="pagination-link">1</a>
                                </li>
                                <?php if($startPage > 2): ?>
                                    <li class="pagination-item">
                                        <span class="pagination-link disabled">...</span>
                                    </li>
                                <?php endif; ?>
                            <?php endif; ?>
                            
                            <?php for($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="pagination-item">
                                    <a href="notifications.php?page=<?php echo $i; ?>" class="pagination-link <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if($endPage < $totalPages): ?>
                                <?php if($endPage < $totalPages - 1): ?>
                                    <li class="pagination-item">
                                        <span class="pagination-link disabled">...</span>
                                    </li>
                                <?php endif; ?>
                                <li class="pagination-item">
                                    <a href="notifications.php?page=<?php echo $totalPages; ?>" class="pagination-link">
                                        <?php echo $totalPages; ?>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php if($page < $totalPages): ?>
                                <li class="pagination-item">
                                    <a href="notifications.php?page=<?php echo $page + 1; ?>" class="pagination-link">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="pagination-item">
                                    <span class="pagination-link disabled">
                                        <i class="fas fa-chevron-right"></i>
                                    </span>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="notification-empty">
                    <div class="notification-empty-icon">
                        <i class="far fa-bell"></i>
                    </div>
                    <h4 class="notification-empty-text">No notifications yet</h4>
                    <p class="notification-empty-subtext">
                        You don't have any notifications at the moment. We'll notify you when there are updates on your consultations or other important activities.
                    </p>
                    <a href="find-experts.php" class="btn btn-primary mt-4">
                        <i class="fas fa-search me-2"></i> Find Experts
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>


<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- AOS Animation Library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Custom JS -->
<script>
    // Initialize AOS
    AOS.init({
        duration: 800,
        easing: 'ease-in-out',
        once: true
    });
    
    // Preloader
    window.addEventListener('load', function() {
        const preloader = document.querySelector('.preloader');
        preloader.classList.add('fade-out');
        setTimeout(function() {
            preloader.style.display = 'none';
        }, 500);
    });
    
    // Navbar scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar');
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
    
    // Back to top button
    const backToTopButton = document.querySelector('.back-to-top');
    
    window.addEventListener('scroll', function() {
        if (window.scrollY > 300) {
            backToTopButton.classList.add('active');
        } else {
            backToTopButton.classList.remove('active');
        }
    });
    
    backToTopButton.addEventListener('click', function(e) {
        e.preventDefault();
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    });
    
    // Mark notification as read
    function markAsRead(notificationId) {
        fetch('mark-notification-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update UI to show notification as read
                const notificationItem = document.getElementById('notification-' + notificationId);
                notificationItem.classList.remove('unread');
                
                // Remove the indicator
                const indicator = notificationItem.querySelector('.notification-indicator');
                if (indicator) {
                    indicator.remove();
                }
                
                // Remove the mark as read button
                const markReadButton = notificationItem.querySelector('.mark-read');
                if (markReadButton) {
                    markReadButton.remove();
                }
                
                // Update unread count
                updateUnreadCount();
            }
        })
        .catch(error => {
            console.error('Error marking notification as read:', error);
        });
    }
    
    // Mark all notifications as read
    function markNotificationsAsRead() {
        fetch('mark-notifications-read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ user_id: <?php echo $userId; ?> })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the notification badge to show zero notifications
                updateNotificationBadge('.notification-badge', 0);
            }
        })
        .catch(error => {
            console.error('Error marking notifications as read:', error);
        });
    }
    
    // Function to refresh notifications every second
    let previousNotificationCount = 0; // Track previous notification count
    let highestNotificationId = <?php echo count($notifications) > 0 ? $notifications[0]['id'] : 0; ?>;

    function fetchNotifications() {
        fetch('get-notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update notification badge
                updateNotificationBadge('.notification-badge', data.count);
                
                // Check for new notifications
                if (data.count > previousNotificationCount) {
                    // Update the notifications list with new notifications
                    updateNotificationsList(data.notifications);
                    
                    // Update unread count in the title
                    updateUnreadCount(data.count);
                }
                
                previousNotificationCount = data.count;
            }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
    }
    
    // Update notifications list with new notifications
    function updateNotificationsList(notifications) {
        const notificationsList = document.getElementById('notifications-list');
        const emptyNotification = document.querySelector('.notification-empty');
        
        if (notifications.length > 0) {
            // If we had an empty state, remove it
            if (emptyNotification) {
                notificationsList.innerHTML = '';
            }
            
            // Check if we have new notifications to add
            const firstNotificationId = parseInt(document.querySelector('.notification-item')?.id.split('-')[1] || 0);
            
            // Add new notifications that aren't already in the list
            notifications.forEach(notification => {
                if (notification.id > firstNotificationId) {
                    const notificationHtml = `
                        <div class="notification-item unread" id="notification-${notification.id}">
                            <div class="notification-indicator"></div>
                            <div class="notification-content">
                                ${notification.message}
                            </div>
                            <div class="notification-meta">
                                <div class="notification-time">
                                    <i class="far fa-clock me-1"></i> ${notification.created_at}
                                </div>
                                <div class="notification-actions">
                                    <a href="javascript:void(0)" class="notification-action mark-read" onclick="markAsRead(${notification.id})">
                                        <i class="fas fa-check me-1"></i> Mark as Read
                                    </a>
                                    <a href="notifications.php?delete=${notification.id}" class="notification-action delete" onclick="return confirm('Are you sure you want to delete this notification?');">
                                        <i class="fas fa-trash-alt me-1"></i> Delete
                                    </a>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    // Insert at the beginning of the list
                    const firstNotification = notificationsList.querySelector('.notification-item');
                    if (firstNotification) {
                        firstNotification.insertAdjacentHTML('beforebegin', notificationHtml);
                    } else {
                        notificationsList.innerHTML = notificationHtml;
                    }
                }
            });
        }
    }
    
    // Update unread count in the title
    function updateUnreadCount(count) {
        const unreadCountBadge = document.querySelector('.notifications-title .badge');
        if (unreadCountBadge) {
            if (count > 0) {
                unreadCountBadge.textContent = count + ' unread';
                unreadCountBadge.style.display = 'inline-block';
            } else {
                unreadCountBadge.style.display = 'none';
            }
        }
    }
    
    // Update notification badge function
    function updateNotificationBadge(selector, count) {
        const badge = document.querySelector(selector);
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.add('show');
            } else {
                badge.textContent = '';
                badge.classList.remove('show');
            }
        }
    }
    
    // Initial fetch
    fetchNotifications();
    
    // Set interval to fetch notifications every second
    setInterval(fetchNotifications, 1000);
</script>
</body>
</html>
