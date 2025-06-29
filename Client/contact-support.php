<?php

// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Update user status to Online if logged in
if ($isLoggedIn) {
    if ($_SESSION['user_role'] == 'client') {
        
    $updateStatusQuery = "UPDATE users SET status = 'Online' WHERE id = $userId";
    $conn->query($updateStatusQuery);
    
    // Get user balance if logged in
    $balanceQuery = "SELECT balance FROM users WHERE id = $userId";
    $balanceResult = $conn->query($balanceQuery);
    if ($balanceResult && $balanceResult->num_rows > 0) {
        $userBalance = $balanceResult->fetch_assoc()['balance'];
        } else {
        $userBalance = 0;} 

    } else {
        header("Location: ../config/logout.php");
        exit;
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
    
    // Get user email
    $userEmailQuery = "SELECT email FROM users WHERE id = $userId";
    $userEmailResult = $conn->query($userEmailQuery);
    if ($userEmailResult && $userEmailResult->num_rows > 0) {
        $userEmail = $userEmailResult->fetch_assoc()['email'];
    }
}

// Fetch notifications for logged-in user
$notifications = [];
$notificationCount = 0;
if ($isLoggedIn) {
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

// Fetch navigation menu items
$menuQuery = "SELECT * FROM categories ORDER BY name ASC LIMIT 6";
$menuResult = $conn->query($menuQuery);
$menuItems = [];

if ($menuResult && $menuResult->num_rows > 0) {
    while ($row = $menuResult->fetch_assoc()) {
        $menuItems[] = $row;
    }
}

// Process form submission
$successMessage = '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_support'])) {
    // Check if user is logged in
    if (!$isLoggedIn) {
        $errorMessage = "You must be logged in to submit a support request.";
    } else {
        // Get form data
        $subject = trim($_POST['subject']);
        $message = trim($_POST['message']);
        $contactType = trim($_POST['contact_type']);
        $email = trim($_POST['email']);
        
        // Validate form data
        if (empty($subject) || empty($message) || empty($contactType) || empty($email)) {
            $errorMessage = "All fields are required.";
        } else {
            // Process file uploads
            $attachments = [];
            $uploadError = false;
            
            // Check if files were uploaded
            if (!empty($_FILES['attachments']['name'][0])) {
                // Create uploads directory if it doesn't exist
                $uploadDir = '../uploads/support_attachments/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                // Maximum file size (5MB)
                $maxFileSize = 5 * 1024 * 1024;
                
                // Allowed file types
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                
                // Maximum number of files
                $maxFiles = 3;
                
                // Count valid files
                $validFileCount = 0;
                foreach ($_FILES['attachments']['name'] as $key => $name) {
                    if (!empty($name)) {
                        $validFileCount++;
                    }
                }
                
                // Check if too many files
                if ($validFileCount > $maxFiles) {
                    $errorMessage = "You can upload a maximum of $maxFiles files.";
                    $uploadError = true;
                } else {
                    // Process each file
                    foreach ($_FILES['attachments']['name'] as $key => $name) {
                        if (empty($name)) continue;
                        
                        $tmpName = $_FILES['attachments']['tmp_name'][$key];
                        $fileSize = $_FILES['attachments']['size'][$key];
                        $fileType = $_FILES['attachments']['type'][$key];
                        
                        // Validate file size
                        if ($fileSize > $maxFileSize) {
                            $errorMessage = "File '$name' exceeds the maximum size limit of 5MB.";
                            $uploadError = true;
                            break;
                        }
                        
                        // Validate file type
                        if (!in_array($fileType, $allowedTypes)) {
                            $errorMessage = "File '$name' has an invalid file type. Allowed types: JPG, PNG, PDF, DOC, DOCX.";
                            $uploadError = true;
                            break;
                        }
                        
                        // Generate unique filename
                        $fileName = time() . '_' . $userId . '_' . preg_replace('/[^a-zA-Z0-9\.]/', '_', $name);
                        $filePath = $uploadDir . $fileName;
                        
                        // Move uploaded file
                        if (move_uploaded_file($tmpName, $filePath)) {
                            $attachments[] = $fileName;
                        } else {
                            $errorMessage = "Error uploading file '$name'. Please try again.";
                            $uploadError = true;
                            break;
                        }
                    }
                }
            }
            
            // If no upload errors, proceed with form submission
            if (!$uploadError) {
                // Insert into support_messages table
                $insertQuery = "INSERT INTO support_messages (user_id, contact_type, subject, message, status, contact_email) 
                               VALUES (?, ?, ?, ?, 'pending', ?)";
                
                $stmt = $conn->prepare($insertQuery);
                $stmt->bind_param("issss", $userId, $contactType, $subject, $message, $email);
                
                if ($stmt->execute()) {
                    $messageId = $stmt->insert_id;
                    
                    // Save attachments if any
                    if (!empty($attachments)) {
                        $attachmentQuery = "INSERT INTO support_attachments (message_id, file_name) VALUES (?, ?)";
                        $attachmentStmt = $conn->prepare($attachmentQuery);
                        
                        foreach ($attachments as $attachment) {
                            $attachmentStmt->bind_param("is", $messageId, $attachment);
                            $attachmentStmt->execute();
                        }
                        
                        $attachmentStmt->close();
                    }
                    
                    $successMessage = "Your support request has been submitted successfully. We will get back to you soon.";
                    
                    // Create notification for admin
                    $adminNotificationQuery = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) 
                                              VALUES (?, 0, 'support_request', ?)";
                    $adminNotificationStmt = $conn->prepare($adminNotificationQuery);
                    $notificationMessage = "New support request from user #$userId regarding: $subject";
                    $adminNotificationStmt->bind_param("is", $userId, $notificationMessage);
                    $adminNotificationStmt->execute();
                } else {
                    $errorMessage = "Error submitting your request. Please try again later.";
                }
                
                $stmt->close();
            }
        }
    }
}

// Define image sources for the website
$imageSources = [
    'hero' => '../photo/hero.jpg',
    'about' => 'https://img.freepik.com/free-photo/group-diverse-people-having-business-meeting_53876-25060.jpg',
    'cta' => 'https://img.freepik.com/free-photo/business-people-shaking-hands-together_53876-30568.jpg'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Support - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
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
            position: relative;
            padding: 120px 0 80px;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.9), rgba(124, 58, 237, 0.9)), url('<?php echo $imageSources['hero']; ?>');
            background-size: cover;
            background-position: center;
            color: white;
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
            text-align: center;
        }
        
        .page-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .page-subtitle {
            font-size: 1.2rem;
            margin-bottom: 0;
            opacity: 0.9;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Contact Form Section */
        .contact-section {
            padding: 80px 0;
            position: relative;
            z-index: 1;
        }
        
        .contact-card {
            background-color: white;
            border-radius: var(--border-radius-xl);
            overflow: hidden;
            box-shadow: var(--box-shadow-lg);
            border: 1px solid var(--gray-100);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-xl);
        }
        
        .contact-card-header {
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            padding: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .contact-card-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 0;
        }
        
        .contact-card-header h3 {
            position: relative;
            z-index: 1;
            color: white;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }
        
        .contact-card-header p {
            position: relative;
            z-index: 1;
            margin-bottom: 0;
            opacity: 0.9;
        }
        
        .contact-card-body {
            padding: 40px;
        }
        
        .contact-info {
            margin-bottom: 40px;
        }
        
        .contact-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 25px;
        }
        
        .contact-info-item:last-child {
            margin-bottom: 0;
        }
        
        .contact-info-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-100), var(--primary-200));
            color: var(--primary-600);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 1.2rem;
            flex-shrink: 0;
        }
        
        .contact-info-content h5 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .contact-info-content p {
            color: var(--gray-600);
            margin-bottom: 0;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-700);
            display: block;
        }
        
        .form-control {
            height: 50px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-300);
            padding: 10px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-400);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        textarea.form-control {
            height: auto;
            min-height: 150px;
            resize: vertical;
        }
        
        .form-select {
            height: 50px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-300);
            padding: 10px 15px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-position: right 15px center;
        }
        
        .form-select:focus {
            border-color: var(--primary-400);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        /* Alert Messages */
        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
        }
        
        .alert-success {
            background-color: var(--success-50);
            color: var(--success-700);
            border-left: 4px solid var(--success-500);
        }
        
        .alert-danger {
            background-color: var(--danger-50);
            color: var(--danger-700);
            border-left: 4px solid var(--danger-500);
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
        
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .page-title {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 992px) {
            .page-title {
                font-size: 2rem;
            }
            
            .page-subtitle {
                font-size: 1.1rem;
            }
            
            .contact-card-body {
                padding: 30px;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 100px 0 60px;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .contact-info-item {
                flex-direction: column;
                text-align: center;
            }
            
            .contact-info-icon {
                margin-right: 0;
                margin-bottom: 15px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .footer {
                padding: 60px 0 20px;
            }
            
            .footer-title {
                margin-top: 30px;
            }
        }
        
        @media (max-width: 576px) {
            .contact-card-body {
                padding: 20px;
            }
            
            .btn-lg {
                padding: 12px 25px;
                font-size: 1rem;
            }
        }

        /* File Upload Styles */
        .file-upload-wrapper {
            position: relative;
        }

        .file-upload-wrapper input[type="file"] {
            padding: 0.75rem;
            background-color: var(--light-color);
            border: 1px dashed var(--primary-color);
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .file-upload-wrapper input[type="file"]:hover {
            background-color: var(--primary-bg);
        }

        .file-upload-wrapper .text-muted {
            font-size: 0.85rem;
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
                        <a class="nav-link active" href="contact-support.php">Contact Support</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if($isLoggedIn): ?>
                        <div class="dropdown me-3">
                            <a class="position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" onclick="markNotificationsAsRead()">
                                <i class="fas fa-bell fs-5 text-gray-700"></i>
                                <?php if($notificationCount > 0): ?>
                                    <span class="notification-badge" id="notification-badge"><?php echo $notificationCount; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" style=" border-radius: 12px;">
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
                                <span class="d-none d-md-inline">
                                    <?php 
                                    // Get user's full name from database
                                    $userNameQuery = "SELECT full_name FROM users WHERE id = $userId";
                                    $userNameResult = $conn->query($userNameQuery);
                                    if ($userNameResult && $userNameResult->num_rows > 0) {
                                        $userData = $userNameResult->fetch_assoc();
                                        echo htmlspecialchars($userData['full_name']);
                                    } else {
                                        echo "My Profile";
                                    }
                                    ?>
                                </span>
                                <?php if(isset($userBalance)): ?>
                                    <span class="ms-2 badge bg-success"><?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>
                                <?php endif; ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="userDropdown" style="border-radius: 12px;">
                                <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
                                <li><a class="dropdown-item py-2" href="Add-fund.php"><i class="fas fa-wallet me-2 text-primary"></i> Add Fund: <?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></a></li>
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
            <div class="page-header-content">
                <h1 class="page-title" data-aos="fade-up">Contact Support</h1>
                <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">Need help? Our support team is here to assist you with any questions or issues you may have.</p>
            </div>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section class="contact-section">
        <div class="container">
            <div class="row">
                <div class="col-lg-5 mb-4 mb-lg-0" data-aos="fade-up">
                    <div class="contact-card h-100">
                        <div class="contact-card-header">
                            <h3>Get in Touch</h3>
                            <p>We're here to help and answer any question you might have</p>
                        </div>
                        <div class="contact-card-body">
                            <div class="contact-info">
                                <?php if(isset($settings['phone_number1']) && !empty($settings['phone_number1'])): ?>
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-phone"></i>
                                    </div>
                                    <div class="contact-info-content">
                                        <h5>Call Us</h5>
                                        <p><?php echo htmlspecialchars($settings['phone_number1']); ?></p>
                                        <?php if(isset($settings['phone_number2']) && !empty($settings['phone_number2'])): ?>
                                        <p><?php echo htmlspecialchars($settings['phone_number2']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <?php if(isset($settings['site_email']) && !empty($settings['site_email'])): ?>
                                <div class="contact-info-item">
                                    <div class="contact-info-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="contact-info-content">
                                        <h5>Email Us</h5>
                                        <p><?php echo htmlspecialchars($settings['site_email']); ?></p>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                               
                            </div>
                            
                            
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-7" data-aos="fade-up" data-aos-delay="100">
                    <div class="contact-card">
                        <div class="contact-card-header">
                            <h3>Send a Message</h3>
                            <p>Fill out the form below and we'll get back to you as soon as possible</p>
                        </div>
                        <div class="contact-card-body">
                            <?php if(!empty($successMessage)): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i> <?php echo $successMessage; ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($errorMessage)): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $errorMessage; ?>
                                </div>
                            <?php endif; ?>
                            
                            <form action="contact-support.php" method="POST" enctype="multipart/form-data">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name" class="form-label">Your Name</label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?php 
                                                if ($isLoggedIn) {
                                                    $userNameQuery = "SELECT full_name FROM users WHERE id = $userId";
                                                    $userNameResult = $conn->query($userNameQuery);
                                                    if ($userNameResult && $userNameResult->num_rows > 0) {
                                                        $userData = $userNameResult->fetch_assoc();
                                                        echo htmlspecialchars($userData['full_name']);
                                                    } else {
                                                        echo '';
                                                    }
                                                } else {
                                                    echo '';
                                                }
                                            ?>" readonly>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email" class="form-label">Your Email</label>
                                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter your email" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="contact_type" class="form-label">Type of Inquiry</label>
                                    <select class="form-select" id="contact_type" name="contact_type" required>
                                        <option value="" selected disabled>Select an option</option>
                                        <option value="general">General Inquiry</option>
                                        <option value="technical">Technical Support</option>
                                        <option value="billing">Billing Issue</option>
                                        <option value="consultation">Consultation Problem</option>
                                        <option value="feedback">Feedback</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="subject" class="form-label">Subject</label>
                                    <input type="text" class="form-control" id="subject" name="subject" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="message" class="form-label">Message</label>
                                    <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                                </div>

                                <div class="form-group">
                                    <label for="attachments" class="form-label">Attachments (Optional)</label>
                                    <div class="file-upload-wrapper">
                                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx">
                                        <div class="mt-2 text-muted small">
                                            <i class="fas fa-info-circle me-1"></i> You can upload
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" name="submit_support" class="btn btn-primary btn-lg">
                                        <i class="fas fa-paper-plane me-2"></i> Send Message
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
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
                        <a href="contact-support.php">Contact Supports</a>
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

        // Function to refresh notifications every second
        
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
        
        // Set interval to refresh notifications every second (1000ms)
        setInterval(fetchNotifications, 1000);
    </script>
</body>
</html>
