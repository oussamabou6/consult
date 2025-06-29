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

// Get user information
$userQuery = "SELECT * FROM users WHERE id = $userId";
$userResult = $conn->query($userQuery);
$user = $userResult->fetch_assoc();

// Get user role
$userRole = $user['role'];

// Get user profile information
$profileQuery = "SELECT * FROM user_profiles WHERE user_id = $userId";
$profileResult = $conn->query($profileQuery);
$profile = $profileResult->fetch_assoc();
// Get user balance
$balanceQuery = "SELECT balance FROM users WHERE id = $userId";
$balanceResult = $conn->query($balanceQuery);
if ($balanceResult && $balanceResult->num_rows > 0) {
    $userBalance = $balanceResult->fetch_assoc()['balance'];
} else {
    $userBalance = 0;
}
// Get site settings
$settingsQuery = "SELECT * FROM settings";
$settingsResult = $conn->query($settingsQuery);
$settings = [];

if ($settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Get admin bank accounts
$bankAccountsQuery = "SELECT * FROM admin_bank_accounts";
$bankAccountsResult = $conn->query($bankAccountsQuery);
$bankAccounts = [];

if ($bankAccountsResult->num_rows > 0) {
    while ($row = $bankAccountsResult->fetch_assoc()) {
        $bankAccounts[] = $row;
    }
}

// Check if fund_requests table exists, if not create it
$checkTableQuery = "SHOW TABLES LIKE 'fund_requests'";
$tableExists = $conn->query($checkTableQuery);

if ($tableExists->num_rows == 0) {
    // Create fund_requests table
    $createTableQuery = "CREATE TABLE `fund_requests` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `user_id` int(11) NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `previous_balance` decimal(10,2) NOT NULL,
        `new_balance` decimal(10,2) NOT NULL,
        `proof_image` varchar(255) NOT NULL,
        `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        `admin_notes` text DEFAULT NULL,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`),
        CONSTRAINT `fund_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
    
    $conn->query($createTableQuery);
}

// Handle form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_fund_request'])) {
    $amount = floatval($_POST['amount']);
    $previousBalance = floatval($user['balance']);
    $newBalance = $previousBalance + $amount;
    
    // Check if amount is valid
    if ($amount <= 0) {
        $errorMessage = "The amount must be greater than zero.";
    } else {
        // Handle file upload
        $targetDir = "../uploads/fund_proofs/";
        
        // Create directory if it doesn't exist
        if (!file_exists($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $fileName = "proof_" . $userId . "_" . time() . "_" . basename($_FILES["proof_image"]["name"]);
        $targetFilePath = $targetDir . $fileName;
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
        
        // Allow certain file formats
        $allowTypes = array('jpg', 'png', 'jpeg', 'gif', 'pdf');
        
        if (in_array(strtolower($fileType), $allowTypes)) {
            // Upload file to server
            if (move_uploaded_file($_FILES["proof_image"]["tmp_name"], $targetFilePath)) {
                // Insert fund request into database
                $insertQuery = "INSERT INTO fund_requests (user_id, amount, previous_balance, new_balance, proof_image, status) 
                               VALUES ($userId, $amount, $previousBalance, $newBalance, '$targetFilePath', 'pending')";
                
                if ($conn->query($insertQuery)) {
                    // Create notification for admin
                    $notificationQuery = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message, related_id, is_read, created_at) 
                                         VALUES ($userId, 0, 'fund_request', 'New fund request of $amount " . $settings['currency'] . "', LAST_INSERT_ID(), 0, NOW())";
                    $conn->query($notificationQuery);
                    
                    $successMessage = "Your fund request has been successfully submitted. It is pending approval by the administrator.";
                } else {
                    $errorMessage = "An error occurred while submitting your request. Please try again.";
                }
            } else {
                $errorMessage = "Sorry, there was an error uploading your file.";
            }
        } else {
            $errorMessage = "Only JPG, JPEG, PNG, GIF, and PDF files are allowed.";
        }
    }
}

// Get user's fund requests
$fundRequestsQuery = "SELECT * FROM fund_requests WHERE user_id = $userId ORDER BY created_at DESC";
$fundRequestsResult = $conn->query($fundRequestsQuery);
$fundRequests = [];

if ($fundRequestsResult && $fundRequestsResult->num_rows > 0) {
    while ($row = $fundRequestsResult->fetch_assoc()) {
        $fundRequests[] = $row;
    }
}


// Fetch notifications for logged-in user
$notifications = [];
$notificationCount = 0;
if ($userRole == 'client') {
    $notificationsQuery = "SELECT * FROM client_notifications 
                          WHERE user_id = $userId AND is_read = 0
                          ORDER BY created_at DESC
                          LIMIT 5";
    $notificationsResult = $conn->query($notificationsQuery);
    
    if ($notificationsResult && $notificationsResult->num_rows > 0) {
        $notificationCount = $notificationsResult->num_rows;
        while ($row = $notificationsResult->fetch_assoc()) {
            $notifications[] = $row;
        }
    }
}
// Function to format date
function formatDate($date) {
    return date('m/d/Y at H:i', strtotime($date));
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-warning';
        case 'approved':
            return 'bg-success';
        case 'rejected':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

// Function to get status text
function getStatusText($status) {
    switch ($status) {
        case 'pending':
            return 'Pending';
        case 'approved':
            return 'Approved';
        case 'rejected':
            return 'Rejected';
        default:
            return 'Unknown';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Funds - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
    <!-- Favicon -->
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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
            --accent-50: #fdf2f8;
            --accent-100: #fce7f3;
            --accent-200: #fbcfe8;
            --accent-300: #f9a8d4;
            --accent-400: #f472b6;
            --accent-500: #ec4899;
            --accent-600: #db2777;
            --accent-700: #be185d;
            --accent-800: #9d174d;
            --accent-900: #831843;
            --accent-950: #500724;
            
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
            font-family: 'Inter', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            overflow-x: hidden;
            line-height: 1.6;
        }
        
        h1, h2, h3, h4, h5, h6 {
            font-family: 'Manrope', sans-serif;
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
        
        /* Main Content */
        .main-container {
            margin-top: -50px;
            margin-bottom: 50px;
            position: relative;
            z-index: 10;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            overflow: hidden;
            height: 100%;
        }
        
        .card:hover {
            box-shadow: var(--box-shadow-lg);
            transform: translateY(-5px);
        }
        
        .card-header {
            background-color: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            padding: 20px;
            font-weight: 700;
            color: var(--gray-800);
        }
        
        .card-body {
            padding: 30px;
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 12px 15px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-400);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }
        
        .form-text {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        /* File Upload */
        .file-upload-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        
        .file-upload-input {
            position: relative;
            z-index: 1;
            width: 100%;
            height: 60px;
            margin: 0;
            padding: 0;
            display: block;
            cursor: pointer;
            opacity: 0;
        }
        
        .file-upload-text {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 60px;
            border: 2px dashed var(--primary-300);
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-600);
            font-weight: 500;
            transition: var(--transition);
            background-color: var(--primary-50);
        }
        
        .file-upload-text i {
            margin-right: 10px;
            font-size: 1.5rem;
            color: var(--primary-500);
        }
        
        .file-upload-input:hover + .file-upload-text,
        .file-upload-input:focus + .file-upload-text {
            border-color: var(--primary-500);
            background-color: var(--primary-100);
        }
        
        .file-upload-preview {
            margin-top: 15px;
            display: none;
        }
        
        .file-upload-preview img {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .file-upload-name {
            margin-top: 10px;
            font-size: 0.9rem;
            color: var(--gray-700);
            font-weight: 500;
        }
        
        /* Bank Account Card */
        .bank-account-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            background-color: var(--gray-50);
            transition: var(--transition);
        }
        
        .bank-account-card:hover {
            background-color: var(--primary-50);
            border-color: var(--primary-200);
        }
        
        .bank-account-title {
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .bank-account-title i {
            margin-right: 10px;
            color: var(--primary-600);
        }
        
        .bank-account-details {
            margin-bottom: 0;
        }
        
        .bank-account-details li {
            margin-bottom: 10px;
            display: flex;
            align-items: flex-start;
        }
        
        .bank-account-details li i {
            margin-right: 10px;
            color: var(--primary-500);
            margin-top: 4px;
        }
        
        .bank-account-details li strong {
            margin-right: 5px;
            color: var(--gray-700);
        }
        
        /* Fund Request History */
        .fund-request-card {
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            transition: var(--transition);
        }
        
        .fund-request-card:hover {
            box-shadow: var(--box-shadow-md);
        }
        
        .fund-request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .fund-request-date {
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .fund-request-status {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 5px 12px;
            border-radius: var(--border-radius-full);
        }
        
        .fund-request-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 10px;
        }
        
        .fund-request-balance {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-top: 1px solid var(--gray-200);
            margin-top: 10px;
        }
        
        .fund-request-balance-item {
            text-align: center;
            flex: 1;
        }
        
        .fund-request-balance-label {
            font-size: 0.8rem;
            color: var(--gray-600);
            margin-bottom: 5px;
        }
        
        .fund-request-balance-value {
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .fund-request-balance-arrow {
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            font-size: 1.5rem;
            padding: 0 10px;
        }
        
        .fund-request-proof {
            margin-top: 15px;
            text-align: center;
        }
        
        .fund-request-proof img {
            max-width: 100%;
            max-height: 200px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .fund-request-notes {
            margin-top: 15px;
            padding: 15px;
            background-color: var(--gray-100);
            border-radius: var(--border-radius);
            font-style: italic;
            color: var(--gray-700);
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
        
        /* Alerts */
        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
        }
        
        .alert-success {
            background-color: var(--success-100);
            color: var(--success-800);
        }
        
        .alert-danger {
            background-color: var(--danger-100);
            color: var(--danger-800);
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
            font-size: 1.2rem;
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
        @media (max-width: 1200px) {
            .page-title {
                font-size: 3rem;
            }
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
        @media (max-width: 992px) {
            .page-title {
                font-size: 2.5rem;
            }
            
            .page-subtitle {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 40px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .main-container {
                margin-top: -30px;
            }
            
            .card-body {
                padding: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.8rem;
            }
            
            .fund-request-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .fund-request-status {
                margin-top: 10px;
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
                        <a class="nav-link active" href="add-fund.php">Add Fund</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
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
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" id="notifications-container" aria-labelledby="notificationDropdown" style="border-radius: 12px; font-size: 12px;">
                            <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
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
                            <li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="notifications.php">View All</a></li>
                        </ul>
                    </div>
                    <div class="dropdown">
                        <a class="btn btn-outline-primary dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if($profile && !empty($profile['profile_image'])): ?>
                                <img src="<?php echo $profile['profile_image']; ?>" alt="Profile" class="rounded-circle" width="30" height="30">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <span class="d-none d-md-inline">
                                <?php echo $user['full_name']; ?>
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
    <div class="page-header">
        <div class="container">
            <div class="page-header-content">
                <h1 class="page-title" data-aos="fade-up">Add Funds</h1>
                <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">Add funds to your account to book consultations with our experts</p>
                <div class="d-flex align-items-center" data-aos="fade-up" data-aos-delay="200">
                    <div class="bg-white py-2 px-4 rounded-pill shadow-sm d-inline-flex align-items-center">
                        <i class="fas fa-wallet text-primary me-2"></i>
                        <span class="fw-bold">Current Balance:</span>
                        <span class="ms-2 fs-5 fw-bold text-primary"><?php echo number_format($user['balance'], 2); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container main-container">
        <?php if(!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert" data-aos="fade-up">
                <i class="fas fa-check-circle me-2"></i> <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($errorMessage)): ?>
            <div class="alert alert-danger" role="alert" data-aos="fade-up">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Add Fund Form -->
            <div class="col-lg-7 mb-4" data-aos="fade-up">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle me-2 text-primary"></i> Fund Request
                    </div>
                    <div class="card-body">
                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="mb-4">
                                <label for="amount" class="form-label">Amount to Add</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" id="amount" name="amount" placeholder="Enter amount" min="1" step="0.01" required>
                                    <span class="input-group-text"><?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>
                                </div>
                                <div class="form-text">Enter the amount you want to add to your account.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Payment Proof</label>
                                <div class="file-upload-wrapper">
                                    <input type="file" class="file-upload-input" name="proof_image" id="proof_image" accept="image/*,.pdf" required>
                                    <div class="file-upload-text">
                                        <i class="fas fa-cloud-upload-alt"></i> Click or drag-drop your payment proof here
                                    </div>
                                </div>
                                <div class="file-upload-preview">
                                    <img id="preview-image" src="#" alt="Preview">
                                    <div class="file-upload-name" id="file-name"></div>
                                </div>
                                <div class="form-text">Upload a screenshot or photo of your payment proof.</div>
                            </div>

                            <div class="alert alert-info" role="alert">
                                <i class="fas fa-info-circle me-2"></i> Your request will be reviewed by our team. Once approved, the amount will be added to your balance.
                            </div>

                            <button type="submit" name="submit_fund_request" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i> Submit Request
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Bank Account Information -->
            <div class="col-lg-5 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="card h-100">
                    <div class="card-header">
                        <i class="fas fa-university me-2 text-primary"></i> Bank Information
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning mb-4" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i> Please make your payment to one of the accounts below and upload proof of payment.
                        </div>

                        <?php foreach($bankAccounts as $account): ?>
                            <div class="bank-account-card">
                                <h5 class="bank-account-title">
                                    <?php if($account['account_type'] === 'ccp'): ?>
                                        <i class="fas fa-envelope"></i> CCP Account
                                    <?php else: ?>
                                        <i class="fas fa-university"></i> Bank Account (RIP)
                                    <?php endif; ?>
                                </h5>
                                <ul class="bank-account-details list-unstyled">
                                    <li>
                                        <i class="fas fa-building"></i>
                                        <div>
                                            <strong>Bank:</strong> <?php echo $account['bank_name']; ?>
                                        </div>
                                    </li>
                                    <li>
                                        <i class="fas fa-credit-card"></i>
                                        <div>
                                            <strong>Account Number:</strong> <?php echo $account['account_number']; ?>
                                        </div>
                                    </li>
                                    <?php if($account['account_type'] === 'ccp' && !empty($account['key_number'])): ?>
                                        <li>
                                            <i class="fas fa-key"></i>
                                            <div>
                                                <strong>Key:</strong> <?php echo $account['key_number']; ?>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                    <?php if($account['account_type'] === 'rip' && !empty($account['rip_number'])): ?>
                                        <li>
                                            <i class="fas fa-hashtag"></i>
                                            <div>
                                                <strong>RIP:</strong> <?php echo $account['rip_number']; ?>
                                            </div>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endforeach; ?>

                        <div class="mt-4">
                            <h5 class="mb-3">Payment Instructions:</h5>
                            <ol class="mb-0">
                                <li class="mb-2">Make a transfer or deposit to one of the accounts above.</li>
                                <li class="mb-2">Take a screenshot or photo of your receipt/proof of payment.</li>
                                <li class="mb-2">Fill out the form and upload your proof of payment.</li>
                                <li>Once approved, the amount will be added to your balance.</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fund Request History -->
        <div class="mt-5" data-aos="fade-up">
            <h2 class="mb-4">Request History</h2>
            
            <?php if(empty($fundRequests)): ?>
                <div class="text-center py-5 bg-white rounded shadow-sm">
                    <i class="fas fa-history text-gray-300 fa-4x mb-3"></i>
                    <h4 class="text-gray-500">No fund requests</h4>
                    <p class="text-gray-400 mb-0">Your fund requests will appear here.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach($fundRequests as $request): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="fund-request-card">
                                <div class="fund-request-header">
                                    <div class="fund-request-date">
                                        <i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($request['created_at']); ?>
                                    </div>
                                    <span class="fund-request-status badge <?php echo getStatusBadgeClass($request['status']); ?>">
                                        <?php echo getStatusText($request['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="fund-request-amount">
                                    <?php echo number_format($request['amount'], 2); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>
                                </div>
                                
                                <div class="fund-request-balance">
                                    <div class="fund-request-balance-item">
                                        <div class="fund-request-balance-label">Previous Balance</div>
                                        <div class="fund-request-balance-value"><?php echo number_format($request['previous_balance'], 2); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></div>
                                    </div>
                                    
                                    <div class="fund-request-balance-arrow">
                                        <i class="fas fa-arrow-right"></i>
                                    </div>
                                    
                                    <div class="fund-request-balance-item">
                                        <div class="fund-request-balance-label">New Balance</div>
                                        <div class="fund-request-balance-value"><?php echo number_format($request['new_balance'], 2); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></div>
                                    </div>
                                </div>
                                
                                <?php if(!empty($request['admin_notes'])): ?>
                                    <div class="fund-request-notes">
                                        <i class="fas fa-comment-alt me-2"></i> <?php echo $request['admin_notes']; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="fund-request-proof mt-3 text-center">
                                    <a href="<?php echo $request['proof_image']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i> View Payment Proof
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

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
        const notificationsContainer = document.getElementById('notifications-container');
        
        if (data.count > 0) {
            // Update badge
            if (badge) {
                badge.textContent = data.count;
                badge.style.display = 'flex';
            } else {
                // Create badge if it doesn't exist
                const bellIcon = document.querySelector('#notificationDropdown');
                const newBadge = document.createElement('span');
                newBadge.id = 'notification-badge';
                newBadge.className = 'notification-badge';
                newBadge.textContent = data.count;
                bellIcon.appendChild(newBadge);
            }
            
            // Update notifications list
            if (notificationsContainer) {
                let notificationsHTML = '<li><h6 class="dropdown-header fw-bold">Notifications</h6></li>';
                
                data.notifications.forEach(notification => {
                    const date = new Date(notification.created_at);
                    const formattedDate = `${date.toLocaleString('default', { month: 'short' })} ${date.getDate()}, ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`;
                    
                    notificationsHTML += `
                        <li>
                            <a class="dropdown-item py-3 border-bottom" href="#">
                                <small class="text-muted d-block">${formattedDate}</small>
                                <p class="mb-0 mt-1">${notification.message}</p>
                            </a>
                        </li>
                    `;
                });
                
                notificationsHTML += '<li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="notifications.php">View All</a></li>';
                notificationsContainer.innerHTML = notificationsHTML;
            }
        } else {
            // No notifications
            if (badge) {
                badge.style.display = 'none';
            }
            
            if (notificationsContainer) {
                notificationsContainer.innerHTML = `
                    <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
                    <li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>
                    <li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="notifications.php">View All</a></li>
                `;
            }
        }
    })
    .catch(error => {
        console.error('Error fetching notifications:', error);
    });
}

// Fetch notifications every second
setInterval(fetchNotifications, 1000);
    
        // Back to Top Button
        const backToTopButton = document.querySelector('.back-to-top');
        
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('active');
            } else {
                backToTopButton.classList.remove('active');
            }
        });
        
        backToTopButton.addEventListener('click', function(e) {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });

        // Navbar Scroll
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // File Upload Preview
        document.getElementById('proof_image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewContainer = document.querySelector('.file-upload-preview');
            const previewImage = document.getElementById('preview-image');
            const fileName = document.getElementById('file-name');
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    if (file.type.includes('image')) {
                        previewImage.src = e.target.result;
                        previewImage.style.display = 'block';
                    } else {
                        previewImage.style.display = 'none';
                    }
                    
                    fileName.textContent = file.name;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            } else {
                previewContainer.style.display = 'none';
            }
        });
    </script>
</body>
</html>
