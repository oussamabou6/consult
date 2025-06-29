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

// Get site settings
$settingsQuery = "SELECT * FROM settings";
$settingsResult = $conn->query($settingsQuery);
$settings = [];

if ($settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Handle consultation actions
$successMessage = '';
$errorMessage = '';
// Get user balance
$balanceQuery = "SELECT balance FROM users WHERE id = $userId";
$balanceResult = $conn->query($balanceQuery);
if ($balanceResult && $balanceResult->num_rows > 0) {
    $userBalance = $balanceResult->fetch_assoc()['balance'];
} else {
    $userBalance = 0;
}
// Handle consultation cancellation
if (isset($_POST['cancel_consultation'])) {
    $consultationId = $_POST['consultation_id'];
    
    // Check if the consultation belongs to the user
    if ($userRole === 'client') {
        $checkQuery = "SELECT * FROM consultations WHERE id = $consultationId AND client_id = $userId";
    } else {
        $checkQuery = "SELECT * FROM consultations WHERE id = $consultationId AND expert_id = $userId";
    }
    
    $checkResult = $conn->query($checkQuery);
    
    if ($checkResult && $checkResult->num_rows > 0) {
        $consultation = $checkResult->fetch_assoc();
        
        // Only allow cancellation if status is pending or confirmed
        if ($consultation['status'] === 'pending' || $consultation['status'] === 'confirmed') {
            // Update consultation status
            $updateQuery = "UPDATE consultations SET status = 'Rejected', updated_at = NOW() WHERE id = $consultationId";
            
            if ($conn->query($updateQuery)) {
                // If client cancels, create notification for expert
                if ($userRole === 'client') {
                    $expertId = $consultation['expert_id'];
                    $notificationMessage = "A client has Rejected a consultation.";
                    
                    $insertNotificationQuery = "INSERT INTO expert_notifications (user_id, profile_id, notification_type, message, related_id) 
                                              VALUES ($expertId, NULL, 'consultation_Rejected', '$notificationMessage', $consultationId)";
                    $conn->query($insertNotificationQuery);
                } 
                // If expert cancels, create notification for client
                else {
                    $clientId = $consultation['client_id'];
                    $notificationMessage = "An expert has Rejected your consultation.";
                    
                    $insertNotificationQuery = "INSERT INTO client_notifications (user_id, message) 
                                              VALUES ($clientId, '$notificationMessage')";
                    $conn->query($insertNotificationQuery);
                }
                
                $successMessage = "Consultation Rejected successfully.";
            } else {
                $errorMessage = "Error cancelling consultation: " . $conn->error;
            }
        } else {
            $errorMessage = "This consultation cannot be Rejected.";
        }
    } else {
        $errorMessage = "Invalid consultation.";
    }
}

// Get consultations based on user role
if ($userRole === 'client') {
    $consultationsQuery = "SELECT c.*, u.full_name as expert_name, u.email as expert_email, 
                          up.profile_image as expert_image, ep.category, ep.subcategory,
                          bi.consultation_price, bi.consultation_minutes, 
                          cat.name as category_name, subcat.name as subcategory_name,
                          ct.started_at, ct.ended_at, ct.duration,cs.id as chat_session_id,
                          p.amount as payment_amount, p.status as payment_status
                          FROM consultations c
                          JOIN users u ON c.expert_id = u.id
                          LEFT JOIN user_profiles up ON u.id = up.user_id
                          LEFT JOIN expert_profiledetails ep ON u.id = ep.user_id
                          LEFT JOIN banking_information bi ON u.id = bi.user_id
                          LEFT JOIN categories cat ON ep.category = cat.id
                          LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                          LEFT JOIN chat_sessions cs ON c.id = cs.consultation_id
                          LEFT JOIN chat_timers ct ON cs.id = ct.chat_session_id
                          LEFT JOIN payments p ON c.id = p.consultation_id
                          WHERE c.client_id = $userId
                          ORDER BY c.consultation_date DESC, c.consultation_time DESC";
} else {
    $consultationsQuery = "SELECT c.*, u.full_name as client_name, u.email as client_email, 
                          up.profile_image as client_image, 
                          ct.started_at, ct.ended_at, ct.duration,
                          p.amount as payment_amount, p.status as payment_status,cs.id as chat_session_id,
                          bi.consultation_price, bi.consultation_minutes
                          FROM consultations c
                          JOIN users u ON c.client_id = u.id
                          LEFT JOIN user_profiles up ON u.id = up.user_id
                          LEFT JOIN chat_sessions cs ON c.id = cs.consultation_id
                          LEFT JOIN chat_timers ct ON cs.id = ct.chat_session_id
                          LEFT JOIN payments p ON c.id = p.consultation_id
                          LEFT JOIN banking_information bi ON c.expert_id = bi.user_id
                          WHERE c.expert_id = $userId
                          ORDER BY c.consultation_date DESC, c.consultation_time DESC";
}

$consultationsResult = $conn->query($consultationsQuery);
$consultations = [];

if ($consultationsResult && $consultationsResult->num_rows > 0) {
    while ($consultation = $consultationsResult->fetch_assoc()) {
        // Get category and subcategory names if available
        if (isset($consultation['category']) && isset($consultation['subcategory'])) {
            $categoryQuery = "SELECT name FROM categories WHERE id = " . $consultation['category'];
            $categoryResult = $conn->query($categoryQuery);
            if ($categoryResult && $categoryResult->num_rows > 0) {
                $consultation['category_name'] = $categoryResult->fetch_assoc()['name'];
            } else {
                $consultation['category_name'] = 'Unknown';
            }
            
            $subcategoryQuery = "SELECT name FROM subcategories WHERE id = " . $consultation['subcategory'];
            $subcategoryResult = $conn->query($subcategoryQuery);
            if ($subcategoryResult && $subcategoryResult->num_rows > 0) {
                $consultation['subcategory_name'] = $subcategoryResult->fetch_assoc()['name'];
            } else {
                $consultation['subcategory_name'] = 'Unknown';
            }
        }
        
        $consultations[] = $consultation;
    }
}

// Group consultations by status
$CurrentConsultations = [];
$completedConsultations = [];
$RejectedConsultations = [];
$canceledConsultations = [];

foreach ($consultations as $consultation) {
    $consultationDateTime = $consultation['consultation_date'] . ' ' . $consultation['consultation_time'];
    $now = date('Y-m-d H:i:s');
    
    if ($consultation['status'] === 'completed') {
        $completedConsultations[] = $consultation;
    } else if ($consultation['status'] === 'rejected') {
        $RejectedConsultations[] = $consultation;
    } else if ($consultation['status'] === 'canceled') {
        $canceledConsultations[] = $consultation;
    } else if ($consultationDateTime > $now || $consultation['status'] === 'pending' || $consultation['status'] === 'confirmed') {
        $CurrentConsultations[] = $consultation;
    }
}

// Group consultations by category and subcategory
function groupConsultationsByCategory($consultations) {
    $grouped = [];
    
    foreach ($consultations as $consultation) {
        $categoryId = isset($consultation['category']) ? $consultation['category'] : 0;
        $subcategoryId = isset($consultation['subcategory']) ? $consultation['subcategory'] : 0;
        $categoryName = isset($consultation['category_name']) ? $consultation['category_name'] : 'Uncategorized';
        $subcategoryName = isset($consultation['subcategory_name']) ? $consultation['subcategory_name'] : 'General';
        
        if (!isset($grouped[$categoryId])) {
            $grouped[$categoryId] = [
                'name' => $categoryName,
                'subcategories' => []
            ];
        }
        
        if (!isset($grouped[$categoryId]['subcategories'][$subcategoryId])) {
            $grouped[$categoryId]['subcategories'][$subcategoryId] = [
                'name' => $subcategoryName,
                'consultations' => []
            ];
        }
        
        $grouped[$categoryId]['subcategories'][$subcategoryId]['consultations'][] = $consultation;
    }
    
    return $grouped;
}

// Group each type of consultation by category
$CurrentByCategory = groupConsultationsByCategory($CurrentConsultations);
$completedByCategory = groupConsultationsByCategory($completedConsultations);
$rejectedByCategory = groupConsultationsByCategory($RejectedConsultations);
$canceledByCategory = groupConsultationsByCategory($canceledConsultations);
$user_fullname = $user['full_name'];
// Fetch notifications for logged-in user
$notifications = [];
$notificationCount = 0;
if ($userRole === 'client') {
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
// Function to format consultation time
function formatConsultationTime($date, $time) {
    $dateTime = new DateTime($date . ' ' . $time);
    return $dateTime->format('l, F j, Y \a\t g:i A');
}

// Function to get status badge class
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'pending':
            return 'bg-warning';
        case 'confirmed':
            return 'bg-primary';
        case 'completed':
            return 'bg-success';
        case 'Rejected':
            return 'bg-danger';
        case 'rejected':
            return 'bg-secondary';
        case 'canceled':
            return 'bg-secondary';
        default:
            return 'bg-info';
    }
}


// Function to check if consultation is Current
function isCurrent($date, $time) {
    $consultationDateTime = new DateTime($date . ' ' . $time);
    $now = new DateTime();
    return $consultationDateTime > $now;
}

// Function to check if consultation is happening now
function isHappeningNow($date, $time, $duration = 60) {
    $consultationDateTime = new DateTime($date . ' ' . $time);
    $endDateTime = clone $consultationDateTime;
    $endDateTime->add(new DateInterval('PT' . $duration . 'M')); // Add duration in minutes
    $now = new DateTime();
    return ($now >= $consultationDateTime && $now <= $endDateTime);
}

// Function to get time until consultation
function getTimeUntil($date, $time) {
    $consultationDateTime = new DateTime($date . ' ' . $time);
    $now = new DateTime();
    $interval = $now->diff($consultationDateTime);
    
    if ($interval->days > 0) {
        return $interval->format('%a days, %h hours');
    } else if ($interval->h > 0) {
        return $interval->format('%h hours, %i minutes');
    } else {
        return $interval->format('%i minutes');
    }
}

// Function to format duration in minutes and seconds
function formatDuration($seconds) {
    if (!$seconds) return "0 minutes";
    
    $minutes = floor($seconds / 60);
    $remainingSeconds = $seconds % 60;
    
    if ($minutes > 0) {
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . 
               ($remainingSeconds > 0 ? ", " . $remainingSeconds . " second" . ($remainingSeconds > 1 ? "s" : "") : "");
    } else {
        return $remainingSeconds . " second" . ($remainingSeconds > 1 ? "s" : "");
    }
}

// Function to get default profile image

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Consultations - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
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
        
        /* Consultations Container */
        .consultations-container {
            margin-top: -50px;
            margin-bottom: 50px;
            position: relative;
            z-index: 10;
        }
        
        /* Tabs */
        .consultations-tabs {
            margin-bottom: 30px;
        }
        
        .nav-tabs {
            border-bottom: 2px solid var(--gray-200);
            margin-bottom: 30px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            padding: 12px 20px;
            font-weight: 600;
            color: var(--gray-600);
            transition: var(--transition);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-600);
            border-color: transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-600);
            border-color: var(--primary-600);
            background-color: transparent;
        }
        
        .nav-tabs .nav-link .badge {
            margin-left: 5px;
            font-size: 0.7rem;
            padding: 0.35em 0.65em;
        }
        
        /* Consultation Cards */
        .consultation-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--gray-100);
            position: relative;
        }
        
        .consultation-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .consultation-card.happening-now {
            border: 2px solid var(--success-500);
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.3);
        }
        
        .consultation-card.happening-now .happening-now-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--success-500);
            color: white;
            padding: 5px 15px;
            border-radius: var(--border-radius-full);
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 10;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
        
        .consultation-header {
            position: relative;
            height: 150px;
            overflow: hidden;
            background-color: var(--primary-600);
        }
        
        .consultation-header-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-size: cover;
            background-position: center;
            filter: brightness(0.7);
            transition: var(--transition);
        }
        
        .consultation-card:hover .consultation-header-bg {
            transform: scale(1.05);
            filter: brightness(0.8);
        }
        
        .consultation-header-content {
            position: relative;
            z-index: 1;
            padding: 20px;
            color: white;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background-size: cover;
            background-position: center;
            
        }
        
        .consultation-type {
            display: inline-flex;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(5px);
            padding: 5px 15px;
            border-radius: var(--border-radius-full);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        .consultation-type i {
            margin-right: 8px;
        }
        
        .consultation-status {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 5px 15px;
            border-radius: var(--border-radius-full);
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .consultation-date {
            font-size: 1.1rem;
            font-weight: 700;
            margin-bottom: 5px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
        }
        
        .consultation-body {
            padding: 20px;
        }
        
        .consultation-person {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .consultation-person-image {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 2px solid var(--primary-100);
        }
        
        .consultation-person-info {
            flex: 1;
        }
        
        .consultation-person-name {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 2px;
            color: var(--gray-900);
        }
        
        .consultation-person-email {
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .consultation-details {
            margin-bottom: 20px;
        }
        
        .consultation-detail-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        
        .consultation-detail-icon {
            width: 20px;
            margin-right: 10px;
            color: var(--primary-600);
        }
        
        .consultation-detail-text {
            flex: 1;
            font-size: 0.95rem;
        }
        
        .consultation-notes {
            background-color: var(--gray-50);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 20px;
            border-left: 3px solid var(--primary-400);
        }
        
        .consultation-notes-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: var(--gray-700);
        }
        
        .consultation-notes-content {
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .consultation-footer {
            padding: 15px 20px;
            border-top: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: var(--gray-50);
        }
        
        .consultation-price {
            font-weight: 700;
            color: var(--gray-900);
        }
        
        .consultation-price-currency {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-left: 5px;
        }
        
        .consultation-actions {
            display: flex;
            gap: 10px;
        }
        
        .consultation-countdown {
            background-color: var(--primary-50);
            border-radius: var(--border-radius);
            padding: 10px 15px;
            margin-bottom: 20px;
            border: 1px solid var(--primary-100);
        }
        
        .consultation-countdown-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 5px;
            color: var(--primary-700);
        }
        
        .consultation-countdown-time {
            font-size: 0.9rem;
            color: var(--primary-600);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .empty-state-icon {
            font-size: 3rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .empty-state-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--gray-800);
        }
        
        .empty-state-description {
            font-size: 1rem;
            color: var(--gray-600);
            max-width: 500px;
            margin: 0 auto 20px;
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
        
        /* Category and Subcategory Sections */
        .category-section {
            margin-bottom: 40px;
        }

        .category-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--gray-900);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--gray-200);
            margin-bottom: 20px;
            position: relative;
        }

        .category-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 80px;
            height: 2px;
            background-color: var(--primary-600);
        }

        .subcategory-section {
            margin-bottom: 30px;
        }

        .subcategory-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 15px;
        }

        .no-consultations-message {
            text-align: center;
            padding: 20px;
            background-color: var(--gray-50);
            border-radius: var(--border-radius);
            color: var(--gray-600);
            font-style: italic;
        }
        
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .page-title {
                font-size: 3rem;
            }
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
            
            .consultations-container {
                margin-top: -30px;
            }
            
            .nav-tabs .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .category-title {
                font-size: 1.5rem;
            }
            
            .subcategory-title {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.8rem;
            }
            
            .consultation-header {
                height: 120px;
            }
            
            .consultation-type {
                font-size: 0.8rem;
                padding: 4px 12px;
            }
            
            .consultation-status {
                font-size: 0.7rem;
                padding: 4px 10px;
            }
            
            .consultation-date {
                font-size: 1rem;
            }
            
            .consultation-person-image {
                width: 40px;
                height: 40px;
            }
            
            .consultation-person-name {
                font-size: 1rem;
            }
            
            .consultation-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .consultation-actions {
                width: 100%;
                justify-content: space-between;
            }
        }

        .rating-stars {
            color: #ccc;
            cursor: pointer;
        }

        .rating-stars .rating-star.active {
            color: #ffc107;
        }
        
        .duration-payment-info {
            display: flex;
            align-items: center;
            background-color: var(--primary-50);
            border-radius: var(--border-radius);
            padding: 10px 15px;
            margin-bottom: 15px;
            border: 1px solid var(--primary-100);
        }
        
        .duration-payment-info-icon {
            margin-right: 10px;
            color: var(--primary-600);
        }
        
        .duration-payment-info-content {
            flex: 1;
        }
        
        .duration-payment-info-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 2px;
            color: var(--primary-700);
        }
        
        .duration-payment-info-text {
            font-size: 0.9rem;
            color: var(--primary-600);
        }
        .modal-backdrop{
            position: relative;
        }/* Notification Badge */
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
                    <?php if($userRole === 'client'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="find-experts.php">Find Experts</a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact-support.php">Contact Support</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="my-consultations.php">My Consultations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">Messages</a>
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
                            <?php if($profile && !empty($profile['profile_image'])): ?>
                                <img src="<?php echo $profile['profile_image']; ?>" alt="Profile" class="rounded-circle" width="30" height="30">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <span class="d-none d-md-inline"><?php echo $user['full_name']; ?>
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
                <h1 class="page-title" data-aos="fade-up">My Consultations</h1>
                <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">View and manage all your consultations in one place</p>
                <?php if($userRole === 'client'): ?>
                    <a href="find-experts.php" class="btn btn-light btn-lg" data-aos="fade-up" data-aos-delay="200">
                        <i class="fas fa-plus-circle me-2"></i> Book New Consultation
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container consultations-container">
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

        <!-- Consultations Tabs -->
        <div class="consultations-tabs" data-aos="fade-up">
            <ul class="nav nav-tabs" id="consultationsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="Current-tab" data-bs-toggle="tab" data-bs-target="#Current-tab-pane" type="button" role="tab" aria-controls="Current-tab-pane" aria-selected="true">
                        <i class="fas fa-calendar-alt me-2"></i> Current
                        <?php if(count($CurrentConsultations) > 0): ?>
                            <span class="badge bg-primary"><?php echo count($CurrentConsultations); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed-tab-pane" type="button" role="tab" aria-controls="completed-tab-pane" aria-selected="false">
                        <i class="fas fa-check-circle me-2"></i> Completed
                        <?php if(count($completedConsultations) > 0): ?>
                            <span class="badge bg-success"><?php echo count($completedConsultations); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="Rejected-tab" data-bs-toggle="tab" data-bs-target="#Rejected-tab-pane" type="button" role="tab" aria-controls="Rejected-tab-pane" aria-selected="false">
                        <i class="fas fa-times-circle me-2"></i> Rejected
                        <?php if(count($RejectedConsultations) > 0): ?>
                            <span class="badge bg-danger"><?php echo count($RejectedConsultations); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="canceled-tab" data-bs-toggle="tab" data-bs-target="#canceled-tab-pane" type="button" role="tab" aria-controls="canceled-tab-pane" aria-selected="false">
                        <i class="fas fa-ban me-2"></i> Canceled
                        <?php if(count($canceledConsultations) > 0): ?>
                            <span class="badge bg-secondary"><?php echo count($canceledConsultations); ?></span>
                        <?php endif; ?>
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="consultationsTabsContent">
                <!-- Current Consultations Tab -->
                <div class="tab-pane fade show active" id="Current-tab-pane" role="tabpanel" aria-labelledby="Current-tab" tabindex="0">
                    <?php if(count($CurrentConsultations) > 0): ?>
                        <?php foreach($CurrentByCategory as $categoryId => $category): ?>
                            <div class="category-section mb-4" data-aos="fade-up">
                                <h3 class="category-title mb-3"><?php echo ucfirst($category['name']); ?></h3>
                                
                                <?php foreach($category['subcategories'] as $subcategoryId => $subcategory): ?>
                                    <div class="subcategory-section mb-4">
                                        <h5 class="subcategory-title mb-3 ps-3 border-start border-3 border-primary"><?php echo ucfirst($subcategory['name']); ?></h5>
                                        
                                        <div class="row">
                                            <?php foreach($subcategory['consultations'] as $consultation): ?>
                                                <?php 
                                                    $isNow = isHappeningNow($consultation['consultation_date'], $consultation['consultation_time']);
                                                    $isCurrent = isCurrent($consultation['consultation_date'], $consultation['consultation_time']);
                                                    $timeUntil = $isCurrent ? getTimeUntil($consultation['consultation_date'], $consultation['consultation_time']) : '';
                                                ?>
                                                <div class="col-lg-6 mb-4">
                                                    <div class="consultation-card <?php echo $isNow ? 'happening-now' : ''; ?>">
                                                        <?php if($isNow): ?>
                                                            <div class="happening-now-badge">
                                                                <i class="fas fa-circle me-1"></i> Happening Now
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="consultation-header">
                                                            <div class="consultation-header-content">
                                                                <div class="consultation-date">
                                                                    <?php echo formatConsultationTime($consultation['consultation_date'], $consultation['consultation_time']); ?>
                                                                </div>
                                                                <div class="consultation-status badge <?php echo getStatusBadgeClass($consultation['status']); ?>">
                                                                    <?php echo ucfirst($consultation['status']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="consultation-body">
                                                            <div class="consultation-person">
                                                                <?php if($userRole === 'client'): ?>
                                                                    <img src="<?php echo !empty($consultation['expert_image']) ? $consultation['expert_image'] : ''; ?>" alt="Expert" class="consultation-person-image">
                                                                    <div class="consultation-person-info">
                                                                        <div class="consultation-person-name"><?php echo $consultation['expert_name']; ?></div>
                                                                        <div class="consultation-person-email"><?php echo $consultation['expert_email']; ?></div>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <img src="<?php echo !empty($consultation['client_image']) ? $consultation['client_image'] : ''; ?>" alt="Client" class="consultation-person-image">
                                                                    <div class="consultation-person-info">
                                                                        <div class="consultation-person-name"><?php echo $consultation['client_name']; ?></div>
                                                                        <div class="consultation-person-email"><?php echo $consultation['client_email']; ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <?php if($isCurrent && !$isNow): ?>
                                                                <div class="consultation-countdown">
                                                                    <div class="consultation-countdown-title">Time until consultation:</div>
                                                                    <div class="consultation-countdown-time"><?php echo $timeUntil; ?></div>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="consultation-details">
                                                                <?php if(isset($consultation['consultation_minutes'])): ?>
                                                                    <div class="consultation-detail-item">
                                                                        <div class="consultation-detail-icon">
                                                                            <i class="fas fa-clock"></i>
                                                                        </div>
                                                                        <div class="consultation-detail-text">
                                                                            <?php echo $consultation['consultation_minutes']; ?> minutes
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if(!empty($consultation['notes'])): ?>
                                                                    <div class="consultation-notes">
                                                                        <div class="consultation-notes-title">Notes:</div>
                                                                        <div class="consultation-notes-content"><?php echo $consultation['notes']; ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="consultation-footer">
                                                            <?php if(isset($consultation['consultation_price'])): ?>
                                                                <div class="consultation-price">
                                                                    <?php echo number_format($consultation['consultation_price']); ?>
                                                                    <span class="consultation-price-currency"><?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>
                                                                </div>
                                                            <?php else: ?>
                                                                <div> </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <div class="empty-state" data-aos="fade-up">
                            <div class="empty-state-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <h3 class="empty-state-title">No Current Consultations</h3>
                            <p class="empty-state-description">
                                <?php if($userRole === 'client'): ?>
                                    You don't have any Current consultations. Book a consultation with an expert to get started.
                                <?php else: ?>
                                    You don't have any Current consultations. Clients will appear here when they book a consultation with you.
                                <?php endif; ?>
                            </p>
                            <?php if($userRole === 'client'): ?>
                                <a href="find-experts.php" class="btn btn-primary">
                                    <i class="fas fa-search me-2"></i> Find Experts
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Completed Consultations Tab -->
                <div class="tab-pane fade" id="completed-tab-pane" role="tabpanel" aria-labelledby="completed-tab" tabindex="0">
                    <?php if(count($completedConsultations) > 0): ?>
                        <?php foreach($completedByCategory as $categoryId => $category): ?>
                            <div class="category-section mb-4" data-aos="fade-up">
                                <h3 class="category-title mb-3"><?php echo ucfirst($category['name']); ?></h3>
                                
                                <?php foreach($category['subcategories'] as $subcategoryId => $subcategory): ?>
                                    <div class="subcategory-section mb-4">
                                        <h5 class="subcategory-title mb-3 ps-3 border-start border-3 border-primary"><?php echo ucfirst($subcategory['name']); ?></h5>
                                        
                                        <div class="row">
                                            <?php foreach($subcategory['consultations'] as $consultation): ?>
                                                <div class="col-lg-6 mb-4">
                                                    <div class="consultation-card">
                                                        <div class="consultation-header">
                                                            <div class="consultation-header-content">
                                                                <div class="consultation-date">
                                                                    <?php echo formatConsultationTime($consultation['consultation_date'], $consultation['consultation_time']); ?>
                                                                </div>
                                                                <div class="consultation-status badge <?php echo getStatusBadgeClass($consultation['status']); ?>">
                                                                    <?php echo ucfirst($consultation['status']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="consultation-body">
                                                            <div class="consultation-person">
                                                                <?php if($userRole === 'client'): ?>
                                                                    <img src="<?php echo !empty($consultation['expert_image']) ? $consultation['expert_image'] : ''; ?>" alt="Expert" class="consultation-person-image">
                                                                    <div class="consultation-person-info">
                                                                        <div class="consultation-person-name"><?php echo $consultation['expert_name']; ?></div>
                                                                        <div class="consultation-person-email"><?php echo $consultation['expert_email']; ?></div>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <img src="<?php echo !empty($consultation['client_image']) ? $consultation['client_image'] : ''; ?>" alt="Client" class="consultation-person-image">
                                                                    <div class="consultation-person-info">
                                                                        <div class="consultation-person-name"><?php echo $consultation['client_name']; ?></div>
                                                                        <div class="consultation-person-email"><?php echo $consultation['client_email']; ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <div class="consultation-details">
                                                                <?php if(isset($consultation['duration'])): ?>
                                                                    <div class="duration-payment-info">
                                                                        <div class="duration-payment-info-icon">
                                                                            <i class="fas fa-hourglass-half"></i>
                                                                        </div>
                                                                        <div class="duration-payment-info-content">
                                                                            <div class="duration-payment-info-title">Consultation Duration:</div>
                                                                            <div class="duration-payment-info-text">
                                                                                <?php echo formatDuration($consultation['duration']); ?>
                                                                                <?php if(isset($consultation['payment_amount'])): ?>
                                                                                    <span class="ms-2 fw-bold">
                                                                                        (<?php echo number_format($consultation['payment_amount']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>)
                                                                                    </span>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if(isset($consultation['consultation_minutes'])): ?>
                                                                    <div class="consultation-detail-item">
                                                                        <div class="consultation-detail-icon">
                                                                            <i class="fas fa-clock"></i>
                                                                        </div>
                                                                        <div class="consultation-detail-text">
                                                                            <strong>Estimated time:</strong> <?php echo $consultation['consultation_minutes']; ?> minutes
                                                                        </div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if(!empty($consultation['notes'])): ?>
                                                                    <div class="consultation-notes">
                                                                        <div class="consultation-notes-title">Notes:</div>
                                                                        <div class="consultation-notes-content"><?php echo $consultation['notes']; ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="consultation-footer">
                                                            <?php if(isset($consultation['consultation_price'])): ?>
                                                                <div class="consultation-price">
                                                                    <?php echo number_format($consultation['consultation_price']); ?>
                                                                    <span class="consultation-price-currency"><?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>
                                                                    <?php if(isset($consultation['consultation_minutes'])): ?>
                                                                        <div class="small text-muted">
                                                                            Expert rate: <?php echo number_format($consultation['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?> / <?php echo $consultation['consultation_minutes']; ?> min
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php else: ?>
                                                                <div></div>
                                                            <?php endif; ?>
                                                            
                                                            <div class="consultation-actions">
                                                                
                                                                
                                                                <a href="messages.php?chat_session_id=<?php echo $consultation['chat_session_id']; ?>" class="btn btn-secondary btn-sm">
                                                                    <i class="fas fa-history me-1"></i> View History
                                                                </a>
                                                                
                                                              
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Review Modal -->
                                                    <div class="modal fade" id="reviewModal<?php echo $consultation['id']; ?>" tabindex="-1" aria-labelledby="reviewModalLabel<?php echo $consultation['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="reviewModalLabel<?php echo $consultation['id']; ?>">Leave a Review</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <form id="reviewForm<?php echo $consultation['id']; ?>">
                                                                        <div class="mb-3">
                                                                            <label class="form-label">Rating</label>
                                                                            <div class="rating-stars mb-3">
                                                                                <i class="fas fa-star fs-3 me-2 rating-star" data-rating="1"></i>
                                                                                <i class="fas fa-star fs-3 me-2 rating-star" data-rating="2"></i>
                                                                                <i class="fas fa-star fs-3 me-2 rating-star" data-rating="3"></i>
                                                                                <i class="fas fa-star fs-3 me-2 rating-star" data-rating="4"></i>
                                                                                <i class="fas fa-star fs-3 me-2 rating-star" data-rating="5"></i>
                                                                                <input type="hidden" name="rating" id="rating<?php echo $consultation['id']; ?>" value="0">
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="reviewComment<?php echo $consultation['id']; ?>" class="form-label">Your Review</label>
                                                                            <textarea class="form-control" id="reviewComment<?php echo $consultation['id']; ?>" rows="4" placeholder="Share your experience with this consultation..."></textarea>
                                                                        </div>
                                                                    </form>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="button" class="btn btn-primary" onclick="submitReview(<?php echo $consultation['id']; ?>)">Submit Review</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Details Modal -->
                                                    <div class="modal fade" id="detailsModal<?php echo $consultation['id']; ?>" tabindex="-1" aria-labelledby="detailsModalLabel<?php echo $consultation['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog modal-dialog-centered modal-lg">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="detailsModalLabel<?php echo $consultation['id']; ?>">Consultation Details</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <div class="row mb-4">
                                                                        <div class="col-md-6">
                                                                            <h6 class="fw-bold">Consultation Information</h6>
                                                                            <p><strong>Date & Time:</strong> <?php echo formatConsultationTime($consultation['consultation_date'], $consultation['consultation_time']); ?></p>
                                                                            <p><strong>Status:</strong> <span class="badge <?php echo getStatusBadgeClass($consultation['status']); ?>"><?php echo ucfirst($consultation['status']); ?></span></p>
                                                                            
                                                                            <?php if(isset($consultation['duration'])): ?>
                                                                                <p><strong>Actual Duration:</strong> <?php echo formatDuration($consultation['duration']); ?></p>
                                                                            <?php endif; ?>
                                                                            
                                                                            <?php if(isset($consultation['consultation_minutes'])): ?>
                                                                                <p><strong>Estimated Duration:</strong> <?php echo $consultation['consultation_minutes']; ?> minutes</p>
                                                                            <?php endif; ?>
                                                                            
                                                                            <?php if(isset($consultation['payment_amount'])): ?>
                                                                                <p><strong>Payment Amount:</strong> <?php echo number_format($consultation['payment_amount']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></p>
                                                                            <?php endif; ?>
                                                                            
                                                                            <?php if(isset($consultation['consultation_price'])): ?>
                                                                                <p><strong>Rate:</strong> <?php echo number_format($consultation['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?> / <?php echo $consultation['consultation_minutes']; ?> min</p>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <div class="col-md-6">
                                                                            <?php if($userRole === 'client'): ?>
                                                                                <h6 class="fw-bold">Expert Information</h6>
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <img src="<?php echo !empty($consultation['expert_image']) ? $consultation['expert_image'] : ''; ?>" alt="Expert" class="rounded-circle me-3" width="60" height="60">
                                                                                    <div>
                                                                                        <h6 class="mb-1"><?php echo $consultation['expert_name']; ?></h6>
                                                                                        <p class="text-muted mb-0"><?php echo $consultation['expert_email']; ?></p>
                                                                                    </div>
                                                                                </div>
                                                                                <p><strong>Expertise:</strong> <?php echo ucfirst($category['name']); ?> / <?php echo ucfirst($subcategory['name']); ?></p>
                                                                            <?php else: ?>
                                                                                <h6 class="fw-bold">Client Information</h6>
                                                                                <div class="d-flex align-items-center mb-3">
                                                                                    <img src="<?php echo !empty($consultation['client_image']) ? $consultation['client_image'] : ''; ?>" alt="Client" class="rounded-circle me-3" width="60" height="60">
                                                                                    <div>
                                                                                        <h6 class="mb-1"><?php echo $consultation['client_name']; ?></h6>
                                                                                        <p class="text-muted mb-0"><?php echo $consultation['client_email']; ?></p>
                                                                                    </div>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    </div>
                                                                    
                                                                    <?php if(!empty($consultation['notes'])): ?>
                                                                        <div class="mb-4">
                                                                            <h6 class="fw-bold">Consultation Notes</h6>
                                                                            <div class="p-3 bg-light rounded">
                                                                                <?php echo nl2br($consultation['notes']); ?>
                                                                            </div>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    
                                                                    <div class="d-flex justify-content-between">
                                                                        <a href="messages.php?chat_session_id=<?php echo $consultation['chat_session_id']; ?>" class="btn btn-primary">
                                                                            <i class="fas fa-comments me-2"></i> View Chat History
                                                                        </a>
                                                                        <?php if($userRole === 'client'): ?>
                                                                            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#reviewModal<?php echo $consultation['id']; ?>" data-bs-dismiss="modal">
                                                                                <i class="fas fa-star me-2"></i> Leave a Review
                                                                            </button>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" data-aos="fade-up">
                            <div class="empty-state-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <h3 class="empty-state-title">No Completed Consultations</h3>
                            <p class="empty-state-description">
                                You haven't completed any consultations yet. Completed consultations will appear here.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Rejected Consultations Tab -->
                <div class="tab-pane fade" id="Rejected-tab-pane" role="tabpanel" aria-labelledby="Rejected-tab" tabindex="0">
                    <?php if(count($RejectedConsultations) > 0): ?>
                        <?php foreach($rejectedByCategory as $categoryId => $category): ?>
                            <div class="category-section mb-4" data-aos="fade-up">
                                <h3 class="category-title mb-3"><?php echo ucfirst($category['name']); ?></h3>
                                
                                <?php foreach($category['subcategories'] as $subcategoryId => $subcategory): ?>
                                    <div class="subcategory-section mb-4">
                                        <h5 class="subcategory-title mb-3 ps-3 border-start border-3 border-primary"><?php echo ucfirst($subcategory['name']); ?></h5>
                                        
                                        <div class="row">
                                            <?php foreach($subcategory['consultations'] as $consultation): ?>
                                                <div class="col-lg-6 mb-4">
                                                    <div class="consultation-card">
                                                        <div class="consultation-header">
                                                            <div class="consultation-header-content">
                                                                <div class="consultation-date">
                                                                    <?php echo formatConsultationTime($consultation['consultation_date'], $consultation['consultation_time']); ?>
                                                                </div>
                                                                <div class="consultation-status badge <?php echo getStatusBadgeClass($consultation['status']); ?>">
                                                                    <?php echo ucfirst($consultation['status']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        <div class="consultation-body">
                                                            <div class="consultation-person">
                                                                <?php if($userRole === 'client'): ?>
                                                                    <img src="<?php echo !empty($consultation['expert_image']) ? $consultation['expert_image'] : ''; ?>" alt="Expert" class="consultation-person-image">
                                                                    <div class="consultation-person-info">
                                                                        <div class="consultation-person-name"><?php echo $consultation['expert_name']; ?></div>
                                                                        <div class="consultation-person-email"><?php echo $consultation['expert_email']; ?></div>
                                                                    </div>
                                                                <?php else: ?>
                                                                    <img src="<?php echo !empty($consultation['client_image']) ? $consultation['client_image'] : ''; ?>" alt="Client" class="consultation-person-image">
                                                                    <div class="consultation-person-info">
                                                                        <div class="consultation-person-name"><?php echo $consultation['client_name']; ?></div>
                                                                        <div class="consultation-person-email"><?php echo $consultation['client_email']; ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <div class="consultation-details">
                                                                <?php if(isset($consultation['rejection_reason'])): ?>
                                                                    <div class="consultation-notes" style="background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 20px;">
                                                                        <div class="consultation-notes-icon">
                                                                            <i class="fas fa-exclamation-triangle"></i>
                                                                        </div>
                                                                        <div class="consultation-notes-title">Rejection Reason:</div>
                                                                        <div class="consultation-notes-content"><?php echo $consultation['rejection_reason']; ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if(!empty($consultation['notes'])): ?>
                                                                    <div class="consultation-notes">
                                                                        <div class="consultation-notes-title">Notes:</div>
                                                                        <div class="consultation-notes-content"><?php echo $consultation['notes']; ?></div>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        <div class="consultation-footer">
                                                            <div class="consultation-actions w-100 d-flex justify-content-end">
                                                                <?php if($userRole === 'client'): ?>
                                                                    <a href="find-experts.php" class="btn btn-outline-primary btn-sm">
                                                                        <i class="fas fa-calendar-plus me-1"></i> Book Again
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state" data-aos="fade-up">
                            <div class="empty-state-icon">
                                <i class="fas fa-times-circle"></i>
                            </div>
                            <h3 class="empty-state-title">No Rejected Consultations</h3>
                            <p class="empty-state-description">
                                You don't have any Rejected consultations. Rejected consultations will appear here.
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                <!-- Canceled Consultations Tab -->
<div class="tab-pane fade" id="canceled-tab-pane" role="tabpanel" aria-labelledby="canceled-tab" tabindex="0">
    <?php if(count($canceledConsultations) > 0): ?>
        <?php foreach($canceledByCategory as $categoryId => $category): ?>
            <div class="category-section mb-4" data-aos="fade-up">
                <h3 class="category-title mb-3"><?php echo ucfirst($category['name']); ?></h3>
                
                <?php foreach($category['subcategories'] as $subcategoryId => $subcategory): ?>
                    <div class="subcategory-section mb-4">
                        <h5 class="subcategory-title mb-3 ps-3 border-start border-3 border-primary"><?php echo ucfirst($subcategory['name']); ?></h5>
                        
                        <div class="row">
                            <?php foreach($subcategory['consultations'] as $consultation): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="consultation-card">
                                        <div class="consultation-header">
                                            <div class="consultation-header-content">
                                                <div class="consultation-date">
                                                    <?php echo formatConsultationTime($consultation['consultation_date'], $consultation['consultation_time']); ?>
                                                </div>
                                                <div class="consultation-status badge <?php echo getStatusBadgeClass($consultation['status']); ?>">
                                                    <?php echo ucfirst($consultation['status']); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="consultation-body">
                                            <div class="consultation-person">
                                                <?php if($userRole === 'client'): ?>
                                                    <img src="<?php echo !empty($consultation['expert_image']) ? $consultation['expert_image'] : ''; ?>" alt="Expert" class="consultation-person-image">
                                                    <div class="consultation-person-info">
                                                        <div class="consultation-person-name"><?php echo $consultation['expert_name']; ?></div>
                                                        <div class="consultation-person-email"><?php echo $consultation['expert_email']; ?></div>
                                                    </div>
                                               
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="consultation-details">
                                               
                                                
                                                <?php if(!empty($consultation['notes'])): ?>
                                                    <div class="consultation-notes">
                                                        <div class="consultation-notes-title">Notes:</div>
                                                        <div class="consultation-notes-content"><?php echo $consultation['notes']; ?></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="consultation-footer">
                                            <div class="consultation-actions w-100 d-flex justify-content-end">
                                                <?php if(isset($consultation['chat_session_id']) && !empty($consultation['chat_session_id'])): ?>
                                                    <a href="messages.php?chat_session_id=<?php echo $consultation['chat_session_id']; ?>" class="btn btn-secondary btn-sm">
                                                        <i class="fas fa-history me-1"></i> View Chat History
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if($userRole === 'client'): ?>
                                                    <a href="find-experts.php" class="btn btn-outline-primary btn-sm ms-2">
                                                        <i class="fas fa-calendar-plus me-1"></i> Book Again
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
        <?php else: ?>
            <div class="empty-state" data-aos="fade-up">
                <div class="empty-state-icon">
                    <i class="fas fa-ban"></i>
                </div>
                <h3 class="empty-state-title">No Canceled Consultations</h3>
                <p class="empty-state-description">
                    You don't have any canceled consultations. Canceled consultations will appear here.
                </p>
            </div>
        <?php endif; ?>
</div>
            </div>
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

        // Rating stars functionality
        document.addEventListener('DOMContentLoaded', function() {
            const ratingStars = document.querySelectorAll('.rating-star');
            
            ratingStars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = this.getAttribute('data-rating');
                    const consultationId = this.closest('form').id.replace('reviewForm', '');
                    document.getElementById('rating' + consultationId).value = rating;
                    
                    // Reset all stars
                    const stars = this.parentElement.querySelectorAll('.rating-star');
                    stars.forEach(s => s.classList.remove('active'));
                    
                    // Activate stars up to the clicked one
                    for (let i = 0; i < rating; i++) {
                        stars[i].classList.add('active');
                    }
                });
                
                star.addEventListener('mouseover', function() {
                    const rating = this.getAttribute('data-rating');
                    const stars = this.parentElement.querySelectorAll('.rating-star');
                    
                    // Highlight stars up to the hovered one
                    for (let i = 0; i < stars.length; i++) {
                        if (i < rating) {
                            stars[i].style.color = '#ffc107';
                        } else {
                            stars[i].style.color = '#ccc';
                        }
                    }
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
                star.addEventListener('mouseout', function() {
                    const consultationId = this.closest('form').id.replace('reviewForm', '');
                    const rating = document.getElementById('rating' + consultationId).value;
                    const stars = this.parentElement.querySelectorAll('.rating-star');
                    
                    // Reset to the actual rating
                    for (let i = 0; i < stars.length; i++) {
                        if (i < rating) {
                            stars[i].style.color = '#ffc107';
                        } else {
                            stars[i].style.color = '#ccc';
                        }
                    }
                });
            });
        });
        
        // Submit review function
        function submitReview(consultationId) {
            const rating = document.getElementById('rating' + consultationId).value;
            const comment = document.getElementById('reviewComment' + consultationId).value;
            
            if (rating === '0') {
                alert('Please select a rating');
                return;
            }
            
            // Here you would normally send the data to the server via AJAX
            // For demonstration, we'll just show an alert
            alert('Thank you for your review! Rating: ' + rating + '/5');
            
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('reviewModal' + consultationId));
            modal.hide();
        }
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

    </script>
</body>
</html>
