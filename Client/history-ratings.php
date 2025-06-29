<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "client") {
    // User is not logged in or not a client, redirect to login page
    header("Location: ../pages/login.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];

// Get user data
$sql = "SELECT u.*, up.phone, up.address, up.profile_image, up.bio, u.balance
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Fetch site settings
$settingsQuery = "SELECT * FROM settings";
$settingsResult = $conn->query($settingsQuery);
$settings = [];

if ($settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Get notification counts
$notifications_query = "SELECT COUNT(*) as count FROM client_notifications WHERE user_id = ? AND is_read = 0";
$notifications_stmt = $conn->prepare($notifications_query);
$notifications_stmt->bind_param("i", $user_id);
$notifications_stmt->execute();
$notifications_result = $notifications_stmt->get_result();
$notification_count = $notifications_result->fetch_assoc()['count'];

// Get currency from settings
$currency = $settings['currency'] ?? 'DA';

// Fetch all ratings submitted by the client
$ratings_query = "SELECT er.*, 
                 u.full_name as expert_name, 
                 up.profile_image as expert_image,
                 c.id as consultation_id,
                 c.duration,
                 c.created_at as consultation_date,
                 c.status as consultation_status,
                 cat.name as category_name,
                 sc.name as subcategory_name,
                 p.amount as payment_amount
                 FROM expert_ratings er
                 JOIN users u ON er.expert_id = u.id
                 LEFT JOIN user_profiles up ON u.id = up.user_id
                 LEFT JOIN consultations c ON er.consultation_id = c.id
                 LEFT JOIN expert_profiledetails ep ON u.id = ep.user_id
                 LEFT JOIN categories cat ON ep.category = cat.id
                 LEFT JOIN subcategories sc ON ep.subcategory = sc.id
                 LEFT JOIN payments p ON c.id = p.consultation_id
                 WHERE er.client_id = ?
                 ORDER BY er.created_at DESC";

$ratings_stmt = $conn->prepare($ratings_query);
$ratings_stmt->bind_param("i", $user_id);
$ratings_stmt->execute();
$ratings_result = $ratings_stmt->get_result();
$ratings = [];

if ($ratings_result && $ratings_result->num_rows > 0) {
    while ($row = $ratings_result->fetch_assoc()) {
        // Get expert responses to this rating
        $response_query = "SELECT * FROM expert_rating_responses WHERE rating_id = ?";
        $response_stmt = $conn->prepare($response_query);
        $response_stmt->bind_param("i", $row['id']);
        $response_stmt->execute();
        $response_result = $response_stmt->get_result();
        $responses = [];
        
        if ($response_result && $response_result->num_rows > 0) {
            while ($response_row = $response_result->fetch_assoc()) {
                $responses[] = $response_row;
            }
        }
        
        $row['responses'] = $responses;
        $ratings[] = $row;
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Ratings History - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
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
        
        /* Navbar */
        .navbar {
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: var(--box-shadow);
            transition: all 0.4s ease;
            padding: 20px 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
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
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(124, 58, 237, 0.9)), url('../photo/photo-1557804506-669a67965ba0.jpg');
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
        
        /* Main Content */
        .main-content {
            padding: 50px 0;
        }
        
        /* Rating Card */
        .rating-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: 1px solid var(--gray-200);
        }
        
        .rating-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .rating-card-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .expert-info {
            display: flex;
            align-items: center;
        }
        
        .expert-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            border: 3px solid var(--primary-100);
        }
        
        .expert-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .expert-details h4 {
            margin: 0 0 5px;
            font-size: 1.1rem;
        }
        
        .expert-category {
            color: var(--primary-600);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .consultation-info {
            text-align: right;
        }
        
        .consultation-date {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-bottom: 5px;
        }
        
        .consultation-price {
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .rating-card-body {
            padding: 20px;
        }
        
        .rating-stars {
            margin-bottom: 15px;
            color: var(--warning-500);
            font-size: 1.2rem;
        }
        
        .rating-comment {
            color: var(--gray-700);
            margin-bottom: 20px;
            line-height: 1.6;
        }
        
        .rating-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        
        .rating-date {
            display: flex;
            align-items: center;
        }
        
        .rating-date i {
            margin-right: 5px;
        }
        
        .rating-likes {
            display: flex;
            align-items: center;
        }
        
        .rating-likes i {
            margin-right: 5px;
            color: var(--primary-600);
        }
        
        /* Expert Response */
        .expert-response {
            background-color: var(--gray-50);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-top: 20px;
            border-left: 3px solid var(--primary-600);
        }
        
        .expert-response-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .expert-response-avatar {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
        }
        
        .expert-response-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .expert-response-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .expert-response-content {
            color: var(--gray-700);
            font-size: 0.95rem;
        }
        
        /* Notification Badge */
        .notification-badge {
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

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
        /* No Ratings */
        .no-ratings {
            text-align: center;
            padding: 50px 0;
        }
        
        .no-ratings-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .no-ratings-text {
            font-size: 1.5rem;
            color: var(--gray-700);
            margin-bottom: 20px;
        }
        /* Buttons */
        .btn {
            padding: 10px 20px;
            font-weight: 600;
            border-radius: var(--border-radius-full);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
            border: none;
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
            background: linear-gradient(135deg, var(--primary-700), var(--primary-800));
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-600), var(--secondary-700));
            border: none;
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, var(--secondary-700), var(--secondary-800));
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .btn-outline-primary {
            border: 2px solid var(--primary-600);
            color: var(--primary-600);
            background-color: transparent;
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-600);
            color: white;
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-600), var(--danger-700));
            border: none;
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, var(--danger-700), var(--danger-800));
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .btn-sm {
            padding: 6px 15px;
            font-size: 0.85rem;
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
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .page-title {
                font-size: 2rem;
            }
            
            .page-subtitle {
                font-size: 1rem;
            }
            
            .rating-card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .consultation-info {
                text-align: left;
                margin-top: 15px;
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
            
            .expert-avatar {
                width: 50px;
                height: 50px;
            }
            
            .expert-details h4 {
                font-size: 1rem;
            }
            
            .rating-stars {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
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
                        <a class="nav-link" href="my-consultations.php">Consultations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact-support.php">Contact Support</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-3">
                        <a class="position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-bell fs-5 text-gray-700"></i>
                            <?php if($notification_count > 0): ?>
                                <span class="notification-badge" id="notification-badge"><?php echo $notification_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" style="border-radius: 12px;">
                            <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
                            <div id="notifications-container" style="font-size:12px;">
                                <li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>
                            </div>
                            <li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="notifications.php">View All</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a class="btn btn-outline-primary dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="rounded-circle" width="30" height="30">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <span class="d-none d-md-inline">
                                <?php echo htmlspecialchars($user['full_name']); ?>
                                <span class="badge bg-success ms-2"><?php echo number_format($user['balance'], 2) . ' ' . $currency; ?></span>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="userDropdown" style="border-radius: 12px;">
                            <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
                            <li><a class="dropdown-item py-2" href="add-fund.php"><i class="fas fa-wallet me-2 text-primary"></i> Add Fund: <span class="badge bg-success float-end"><?php echo number_format($user['balance'], 2) . ' ' . $currency; ?></span></a></li>
                            <li><a class="dropdown-item py-2" href="my-consultations.php"><i class="fas fa-calendar-check me-2 text-primary"></i> My Consultations</a></li>
                            <li><a class="dropdown-item py-2" href="messages.php"><i class="fas fa-envelope me-2 text-primary"></i> Messages</a></li>
                            <li><a class="dropdown-item py-2" href="my-reports.php"><i class="fas fa-flag me-2 text-primary"></i> My Reports</a></li>
                            <li><a class="dropdown-item py-2" href="history-ratings.php"><i class="fas fa-solid fa-star text-primary"></i> Ratings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item py-2" href="../config/logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <section class="page-header">
        <div class="container">
            <div class="page-header-content text-center">
                <h1 class="page-title" data-aos="fade-up">My Ratings History</h1>
                <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">
                    View all your past ratings and reviews for consultations with experts.
                </p>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="main-content">
        <div class="container">
            <div class="row">
                <div class="col-lg-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">Your Ratings</h2>
                        <a href="#" class="btn btn-outline-primary" onclick="history.back();">
                            <i class="fas fa-arrow-left me-2"></i> Back 
                        </a>
                    </div>
                    
                    <?php if(count($ratings) > 0): ?>
                        <?php foreach($ratings as $rating): ?>
                            <div class="rating-card" data-aos="fade-up">
                                <div class="rating-card-header">
                                    <div class="expert-info">
                                        <div class="expert-avatar">
                                            <?php if(!empty($rating['expert_image'])): ?>
                                                <img src="<?php echo $rating['expert_image']; ?>" alt="<?php echo $rating['expert_name']; ?>">
                                            <?php else: ?>
                                                <div class="w-100 h-100 bg-primary d-flex align-items-center justify-content-center text-white">
                                                    <i class="fas fa-user-tie fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="expert-details">
                                            <h4><?php echo htmlspecialchars($rating['expert_name']); ?></h4>
                                            <div class="expert-category">
                                                <?php echo htmlspecialchars($rating['category_name'] ?? 'N/A'); ?> - 
                                                <?php echo htmlspecialchars($rating['subcategory_name'] ?? 'N/A'); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="consultation-info">
                                        <div class="consultation-date">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            <?php echo date('F j, Y', strtotime($rating['consultation_date'] ?? $rating['created_at'])); ?>
                                        </div>
                                        <div class="consultation-price">
                                            <?php if(isset($rating['payment_amount'])): ?>
                                                <?php echo number_format($rating['payment_amount'], 2); ?> <?php echo $currency; ?>
                                            <?php endif; ?>
                                            <?php if(isset($rating['duration'])): ?>
                                                <span class="text-muted">/ <?php echo $rating['duration']; ?> min</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if(isset($rating['consultation_status'])): ?>
                                            <div class="mt-2">
                                                <span class="badge bg-success">Completed</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="rating-card-body">
                                    <div class="rating-stars">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <?php if($i <= $rating['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                    </div>
                                    
                                    <div class="rating-comment">
                                        <?php echo htmlspecialchars($rating['comment']); ?>
                                    </div>
                                    
                                    <div class="rating-meta">
                                        <div class="rating-date">
                                            <i class="far fa-clock"></i>
                                            <?php echo date('M d, Y - h:i A', strtotime($rating['created_at'])); ?>
                                        </div>
                                        <?php if(isset($rating['likes']) && $rating['likes'] > 0): ?>
                                            <div class="rating-likes">
                                                <i class="fas fa-thumbs-up"></i>
                                                <?php echo $rating['likes']; ?> likes
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if(!empty($rating['responses'])): ?>
                                        <div class="expert-response">
                                            <div class="expert-response-header">
                                                <div class="expert-response-avatar">
                                                    <?php if(!empty($rating['expert_image'])): ?>
                                                        <img src="<?php echo $rating['expert_image']; ?>" alt="<?php echo $rating['expert_name']; ?>">
                                                    <?php else: ?>
                                                        <div class="w-100 h-100 bg-primary d-flex align-items-center justify-content-center text-white">
                                                            <i class="fas fa-user-tie"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="expert-response-name">
                                                    <?php echo htmlspecialchars($rating['expert_name']); ?> 
                                                </div>
                                            </div>
                                            <div class="expert-response-content">
                                                <?php echo htmlspecialchars($rating['responses'][0]['response_text']); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-ratings" data-aos="fade-up">
                            <div class="no-ratings-icon">
                                <i class="far fa-star"></i>
                            </div>
                            <h3 class="no-ratings-text">You haven't submitted any ratings yet</h3>
                            <p class="text-muted mb-4">After completing consultations, you can rate your experience with experts</p>
                            <a href="find-experts.php" class="btn btn-primary">Find Experts</a>
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
                    <h5 class="footer-title">My Account</h5>
                    <div class="footer-links">
                        <a href="profile.php">My Profile</a>
                        <a href="my-consultations.php">My Consultations</a>
                        <a href="history-ratings.php">My Ratings</a>
                        <a href="add-fund.php">Add Funds</a>
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
            once: true
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
        
        // Mark notifications as read
        function markNotificationsAsRead() {
            if (document.getElementById('notification-badge')) {
                fetch('mark-notifications-read.php', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const badge = document.getElementById('notification-badge');
                        if (badge) {
                            badge.style.display = 'none';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            }
        }
        
        // Fetch notifications
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
        
        // Fetch notifications every 10 seconds
        setInterval(fetchNotifications, 10000);
        
        // Initial fetch
        document.addEventListener('DOMContentLoaded', function() {
            fetchNotifications();
        });
    </script>
</body>
</html>
