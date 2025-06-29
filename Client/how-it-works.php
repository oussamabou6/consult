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

// Fetch navigation menu items
$menuQuery = "SELECT * FROM categories ORDER BY name ASC LIMIT 6";
$menuResult = $conn->query($menuQuery);
$menuItems = [];

if ($menuResult && $menuResult->num_rows > 0) {
    while ($row = $menuResult->fetch_assoc()) {
        $menuItems[] = $row;
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

// Define image sources for the website with attribution
$imageSources = [
    'step1' => [
        'url' => 'https://images.unsplash.com/photo-1555421689-491a97ff2040',
    ],
    'step3' => [
        'url' => 'https://images.unsplash.com/photo-1600880292203-757bb62b4baf',
    ],
    'step4' => [
        'url' => 'https://images.unsplash.com/photo-1573497620053-ea5300f94f21',
    ],
    'header_bg' => [
        'url' => 'https://images.unsplash.com/photo-1522202176988-66273c2fd55f',
    ],
    'faq_bg' => [
        'url' => 'https://images.unsplash.com/photo-1557804506-669a67965ba0',
    ]
];
// FAQ items  
$faqItems = [  
    [  
        'question' => 'How do I get started with Consult Pro?',  
        'answer' => 'Getting started is easy! Simply create an account, browse available expert profiles, and book a consultation with your chosen expert. You can filter experts by specialty, field, and availability to find the best match for your needs.'  
    ],  
    [  
        'question' => 'How much does a consultation cost?',  
        'answer' => 'Consultation fees vary depending on the professional\'s experience, specialty, and session duration. Each expert sets their own rates which are clearly displayed on their profile. You can filter experts according to your budget to find suitable options.'  
    ],  
    [  
        'question' => 'How can I pay for consultations?',  
        'answer' => 'We currently offer one payment method through Algeria Post. You can add credit to your account through any Algerian post office. When booking a consultation, the amount is reserved from your account and only transferred to the expert after successful completion of the consultation.'  
    ],  
    [  
        'question' => 'Can I cancel a consultation?',  
        'answer' => 'Yes, you can cancel a consultation at any time before it begins for a full refund. Consultations are also automatically canceled and refunded if you or the expert logs out of the platform during the session.'  
    ],  
    [  
        'question' => 'How long do consultation sessions last?',  
        'answer' => 'Consultation sessions start from 5 minutes depending on your needs, and you can choose the duration that suits you when booking. After the session ends, you can immediately book another consultation if you need more time or have additional questions.'  
    ],  
    [  
        'question' => 'What if I\'m not satisfied with the consultation?',  
        'answer' => 'We guarantee complete satisfaction with our service. If you\'re unsatisfied with the consultation for any reason, the full amount will be refunded to your account within 24 hours of submitting a refund request.'  
    ],  
    [  
        'question' => 'How do I become an expert on Consult Pro?',  
        'answer' => 'To become an expert, create an account, complete your professional profile, and submit your qualification documents for verification. Our team will review your application, and upon approval, you can start offering consultations on our platform.'  
    ],  
    [  
        'question' => 'Is my personal information secure?',  
        'answer' => 'Yes, we take data security very seriously. All personal information and consultation details are encrypted and stored securely. We do not share your information with third parties without your consent. You can review our privacy policy for more details.'  
    ]  
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>How It Works - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
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
            
            /* Font Sizes */
            --text-xs: 0.75rem;
            --text-sm: 0.875rem;
            --text-base: 1rem;
            --text-lg: 1.125rem;
            --text-xl: 1.25rem;
            --text-2xl: 1.5rem;
            --text-3xl: 1.875rem;
            --text-4xl: 2.25rem;
            --text-5xl: 3rem;
            --text-6xl: 3.75rem;
            --text-7xl: 4.5rem;
            --text-8xl: 6rem;
            --text-9xl: 8rem;
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
        
        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(124, 58, 237, 0.9)), url('<?php echo $imageSources['header_bg']['url']; ?>');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0 80px;
            border-radius: 0 0 var(--border-radius-3xl) var(--border-radius-3xl);
            margin-bottom: 80px;
            position: relative;
            overflow: hidden;
        }
        
        .hero-section::before {
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
        
        .hero-section::after {
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
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-title {
            font-weight: 800;
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .hero-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            max-width: 700px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        /* Steps Section */
        .steps-section {
            padding: 80px 0;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 60px;
            position: relative;
            padding-bottom: 20px;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
            border-radius: var(--border-radius-full);
        }
        
        .step-card {
            background-color: white;
            border-radius: var(--border-radius-xl);
            overflow: hidden;
            box-shadow: var(--box-shadow-lg);
            transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
            margin-bottom: 30px;
            height: 100%;
            position: relative;
            border: 1px solid var(--gray-100);
        }
        
        .step-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-xl);
        }
        
        .step-img-container {
            position: relative;
            overflow: hidden;
            height: 200px;
        }
        
        .step-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .step-card:hover .step-img {
            transform: scale(1.1);
        }
        
        .step-number {
            position: absolute;
            top: 15px;
            left: 15px;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.25rem;
            z-index: 2;
            box-shadow: var(--box-shadow);
        }
        
        .step-content {
            padding: 25px;
            position: relative;
        }
        
        .step-title {
            font-weight: 700;
            margin-bottom: 15px;
            color: var(--gray-900);
            font-size: var(--text-xl);
        }
        
        .step-description {
            color: var(--gray-600);
            margin-bottom: 20px;
            font-size: var(--text-base);
            line-height: 1.6;
        }
        
        /* Benefits Section */
        .benefits-section {
            padding: 80px 0;
            background-color: var(--gray-100);
            border-radius: var(--border-radius-2xl);
            margin: 40px 0;
        }
        
        .benefit-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        
        .benefit-icon {
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-right: 20px;
            flex-shrink: 0;
            box-shadow: var(--box-shadow);
        }
        
        .benefit-content h4 {
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--gray-900);
            font-size: var(--text-lg);
        }
        
        .benefit-content p {
            color: var(--gray-600);
            font-size: var(--text-base);
            line-height: 1.6;
        }
        
        /* Testimonials Section */
        .testimonials-section {
            padding: 80px 0;
        }
        
        .testimonial-card {
            background-color: white;
            border-radius: var(--border-radius-xl);
            overflow: hidden;
            box-shadow: var(--box-shadow-lg);
            transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
            margin-bottom: 30px;
            height: 100%;
            position: relative;
            border: 1px solid var(--gray-100);
            padding: 30px;
        }
        
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-xl);
        }
        
        .testimonial-content {
            position: relative;
            padding-top: 30px;
        }
        
        .testimonial-content::before {
            content: '\201C';
            position: absolute;
            top: -20px;
            left: -10px;
            font-size: 5rem;
            color: var(--primary-200);
            font-family: Georgia, serif;
            line-height: 1;
        }
        
        .testimonial-text {
            color: var(--gray-700);
            font-size: var(--text-base);
            line-height: 1.7;
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
        }
        
        .testimonial-author-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
            border: 3px solid var(--primary-100);
        }
        
        .testimonial-author-info h5 {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--gray-900);
            font-size: var(--text-base);
        }
        
        .testimonial-author-info p {
            color: var(--gray-600);
            font-size: var(--text-sm);
        }
        
        /* FAQ Section */
        .faq-section {
            padding: 80px 0;
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.05), rgba(124, 58, 237, 0.05)), url('<?php echo $imageSources['faq_bg']['url']; ?>');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            border-radius: var(--border-radius-2xl);
            margin: 40px 0;
            position: relative;
        }
        
        .faq-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            border-radius: var(--border-radius-2xl);
        }
        
        .faq-content {
            position: relative;
            z-index: 1;
        }
        
        .accordion-item {
            margin-bottom: 15px;
            border: none;
            background-color: transparent;
        }
        
        .accordion-button {
            background-color: white;
            border-radius: var(--border-radius) !important;
            box-shadow: var(--box-shadow);
            font-weight: 600;
            color: var(--gray-800);
            padding: 20px 25px;
            transition: all 0.3s ease;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: var(--primary-50);
            color: var(--primary-700);
            box-shadow: var(--box-shadow);
        }
        
        .accordion-button:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
            border-color: var(--primary-300);
        }
        
        .accordion-button::after {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%234b5563' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
            transition: all 0.3s ease;
        }
        
        .accordion-button:not(.collapsed)::after {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%232563eb' viewBox='0 0 16 16'%3E%3Cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3E%3C/svg%3E");
        }
        
        .accordion-body {
            padding: 20px 25px;
            background-color: white;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
            color: var(--gray-600);
            font-size: var(--text-base);
            line-height: 1.7;
        }
        
        /* CTA Section */
        .cta-section {
            padding: 80px 0;
            text-align: center;
        }
        
        .cta-card {
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            border-radius: var(--border-radius-2xl);
            padding: 60px 40px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: var(--box-shadow-xl);
        }
        
        .cta-card::before {
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
        
        .cta-card::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            z-index: 0;
        }
        
        .cta-content {
            position: relative;
            z-index: 1;
        }
        
        .cta-title {
            font-weight: 800;
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: white;
        }
        
        .cta-subtitle {
            font-size: 1.25rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .btn-light {
            background-color: white;
            color: var(--primary-700);
            font-weight: 600;
            padding: 15px 35px;
            border-radius: var(--border-radius-full);
            transition: all 0.3s ease;
            box-shadow: var(--box-shadow);
        }
        
        .btn-light:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
            background-color: var(--gray-100);
        }
        
        /* Footer */
        .footer {
            background-color: var(--gray-900);
            color: white;
            padding: 100px 0 20px;
            margin-top: 100px;
            position: relative;
        }
        
        .footer::before {
            content: '';
            position: absolute;
            top: -30px;
            left: 0;
            width: 100%;
            height: 60px;
            background-color: var(--gray-900);
            border-radius: 30px 30px 0 0;
        }
        
        .footer-title {
            font-weight: 700;
            margin-bottom: 30px;
            position: relative;
            display: inline-block;
            font-size: var(--text-xl);
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
            font-size: var(--text-base);
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
            font-size: var(--text-xl);
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
        .btn-outline-light{
            background-color: transparent;
            color: white;
            border: 2px solid white;
            padding: 14px 20px;
            border-radius: var(--border-radius-full);
            transition: all 0.3s ease;

        }
        .btn-outline-light:hover{
            background-color: white;
            color: var(--gray-900);
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
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
            font-size: var(--text-lg);
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
            font-size: var(--text-xs);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--box-shadow);
        }
        
        /* Image Attribution */
        .image-attribution {
            font-size: var(--text-xs);
            color: var(--gray-500);
            text-align: center;
            margin-top: 5px;
        }
        
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: var(--text-4xl);
            }
            
            .cta-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 992px) {
            .hero-title {
                font-size: var(--text-3xl);
            }
            
            .hero-subtitle {
                font-size: var(--text-base);
            }
            
            .cta-title {
                font-size: 1.75rem;
            }
            
            .cta-subtitle {
                font-size: var(--text-base);
            }
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0 50px;
            }
            
            .hero-title {
                font-size: var(--text-2xl);
            }
            
            .section-title {
                margin-bottom: 40px;
            }
            
            .step-img-container {
                height: 180px;
            }
            
            .step-content {
                padding: 20px;
            }
            
            .step-title {
                font-size: var(--text-lg);
            }
            
            .cta-card {
                padding: 40px 20px;
            }
            
            .cta-title {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .hero-section {
                padding: 60px 0 40px;
            }
            
            .hero-title {
                font-size: var(--text-xl);
            }
            
            .step-img-container {
                height: 150px;
            }
            
            .step-content {
                padding: 15px;
            }
            
            .step-title {
                font-size: var(--text-base);
            }
            
            .back-to-top {
                width: 40px;
                height: 40px;
                font-size: var(--text-base);
            }
        }
        #btnclick{
            padding: 12px 24px;
            font-weight: 600;
            border-radius: var(--border-radius-full);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            z-index: 1;
            font-size: var(--text-base);
            letter-spacing: 0.5px;
            box-shadow: var(--box-shadow);
         }.btn-outline-primary {
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
        
        .btn-success {
            background: linear-gradient(135deg, var(--success-500), var(--success-600));
            border: none;
            color: white;
        }
        
        .btn-success:hover {
            background: linear-gradient(135deg, var(--success-600), var(--success-700));
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-500), var(--danger-600));
            border: none;
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, var(--danger-600), var(--danger-700));
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
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
                    <?php echo isset($settings['site_name']) ? $settings['site_name'] : ''; ?>
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
                        <a class="nav-link active" href="how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact-support.php">Contact Support</a>
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
                        <div class="dropdown" >
                            <a  id="btnclick" class= "btn btn-outline-primary dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false" >
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
    <?php if(isset($userBalance)): ?>
        <span class="ms-2 badge bg-success"><?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></span>
    <?php endif; ?>
</a>
    <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="userDropdown" style="border-radius: 12px;">
    <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
    <li><a class="dropdown-item py-2" href="add-fund.php"><i class="fas fa-wallet me-2 text-primary"></i> Add Fund: <?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></a></li>
    <li><a class="dropdown-item py-2" href="my-consultations.php"><i class="fas fa-calendar-check me-2 text-primary"></i> My Consultations</a></li>
    <li><a class="dropdown-item py-2" href="messages.php"><i class="fas fa-envelope me-2 text-primary"></i> Messages</a></li>
    <li><a class="dropdown-item py-2" href="my-reports.php"><i class="fas fa-flag me-2 text-primary"></i> My Reports</a></li>
                                <li><a class="dropdown-item py-2" href="history-ratings.php"><i class="fas fa-solid fa-star text-primary"></i> Ratings</a></li>

    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item py-2" href="../Config/logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
</ul>
                        </div>
                    <?php else: ?>
                        <a href="../pages/login.php" class="btn btn-outline-primary me-2" id="btnclick">Login</a>
                        <a href="../pages/profile.php" class="btn btn-primary" id="btnclick">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="hero-content">
                <h1 class="hero-title" data-aos="fade-up">How Consult Pro Works</h1>
                <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="100">
                    Discover how our platform connects you with top experts across various fields to help you solve problems, make informed decisions, and achieve your goals.
                </p>
                <div data-aos="fade-up" data-aos-delay="200">
                    <a href="#steps-section" class="btn btn-light btn-lg">Learn More <i class="fas fa-arrow-down ms-2"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Steps Section -->
    <section id="steps-section" class="steps-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Simple Steps to Get Expert Advice</h2>
            <div class="row" style="justify-content:center;">
                <!-- Step 1 -->
                <div class="col-md-6 col-lg-3" data-aos="fade-up">
                    <div class="step-card">
                        <div class="step-img-container">
                            <img src="<?php echo $imageSources['step1']['url']; ?>" alt="Create an Account" class="step-img">
                            <div class="step-number">1</div>
                        </div>
                        <div class="step-content">
                            <h3 class="step-title">Create an Account</h3>
                            <p class="step-description">Sign up for free and complete your profile to get started. This helps us personalize your experience.</p>
                            <?php if($isLoggedIn): ?>
                                <a href="profile.php" class="btn btn-sm btn-outline-primary">Go to Profile</a>
                            <?php else: ?>  
                                <a href="../pages/signup.php" class="btn btn-sm btn-outline-primary">Create Account</a>
                            <?php endif; ?>

                        </div>
                    </div>
                    
                </div>
                
                <!-- Step 2 -->
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="100">
                    <div class="step-card">
                        <div class="step-img-container">
                            <img src="<?php echo $imageSources['step3']['url']; ?>" alt="Find an Expert" class="step-img">
                            <div class="step-number">2</div>
                        </div>
                        <div class="step-content">
                            <h3 class="step-title">Find an Expert</h3>
                            <p class="step-description">Browse through our expert profiles and filter by category, specialty, and availability.</p>
                            <a href="find-experts.php" class="btn btn-sm btn-outline-primary">Browse Experts</a>
                        </div>
                    </div>
                    
                </div>
                

                
                <!-- Step 3 -->
                <div class="col-md-6 col-lg-3" data-aos="fade-up" data-aos-delay="300">
                    <div class="step-card">
                        <div class="step-img-container">
                            <img src="<?php echo $imageSources['step4']['url']; ?>" alt="Get Expert Advice" class="step-img">
                            <div class="step-number">3</div>
                        </div>
                        <div class="step-content">
                            <h3 class="step-title">Get Expert Advice</h3>
                            <p class="step-description">Connect with your expert at the scheduled time and get the guidance you need.</p>
                            <?php if($isLoggedIn): ?>
                                <a href="my-consultations.php" class="btn btn-sm btn-outline-primary">My Consultations</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    </div>
            </div>
        </div>
    </section>

    <!-- Benefits Section -->
    <section class="benefits-section">
        <div class="container">
            <h2 class="section-title" data-aos="fade-up">Why Choose Consult Pro</h2>
            <div class="row">
                <div class="col-lg-6" data-aos="fade-up">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="benefit-content">
                            <h4>Verified Experts</h4>
                            <p>All our experts go through a rigorous verification process to ensure they have the qualifications and experience they claim.</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div class="benefit-content">
                            <h4>Secure Payments</h4>
                            <p>Your payment information is protected with bank-level security, and funds are only released to experts after your consultation is completed.</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="benefit-content">
                            <h4>Quality Assurance</h4>
                            <p>We maintain high standards through our rating system and regular quality checks to ensure you receive the best advice.</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-globe"></i>
                        </div>
                        <div class="benefit-content">
                            <h4>Global Expertise</h4>
                            <p>Access the best experts in Algeria, with specialized advice and perspectives tailored to your local challenges.</p>
                        </div>
                    </div>
                    
                    <div class="benefit-item">
                        <div class="benefit-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div class="benefit-content">
                            <h4>Dedicated Support</h4>
                            <p>Our customer support team is available to assist you with any questions or issues you may encounter during your consultation journey.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    

    <!-- FAQ Section -->
    <section class="faq-section">
        <div class="container">
            <div class="faq-content">
                <h2 class="section-title" data-aos="fade-up">Frequently Asked Questions</h2>
                <div class="accordion" id="faqAccordion" data-aos="fade-up">
                    <?php foreach($faqItems as $index => $faq): ?>
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                            <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index === 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                                <?php echo $faq['question']; ?>
                            </button>
                        </h2>
                        <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                <?php echo $faq['answer']; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-card" data-aos="fade-up">
                <div class="cta-content">
                    <h2 class="cta-title">Ready to Get Expert Advice?</h2>
                    <p class="cta-subtitle">Join thousands of satisfied clients who have transformed their lives and businesses with expert guidance from our platform.</p>
                    <div class="d-flex justify-content-center gap-3 flex-wrap">
                        <a href="find-experts.php" class="btn btn-light">Find an Expert</a>
                        <?php if($isLoggedIn): ?>
                                <a href="profile.php" class="btn btn-outline-light">Go to Profile</a>
                            <?php else: ?>  
                                <a href="../pages/signup.php" class="btn btn-outline-light">Create an Account</a>
                            <?php endif; ?>
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
        
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                const targetElement = document.querySelector(targetId);
                
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 100,
                        behavior: 'smooth'
                    });
                }
            });
        });

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
