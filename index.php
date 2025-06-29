<?php
// Start session
session_start();

// Include database configuration
require_once("config/config.php");

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
        header("Location: config/logout.php");
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

// Fetch top experts (with most consultations and highest ratings)
$topExpertsQuery = "SELECT u.id, u.full_name, up.profile_image, up.bio, 
                    ep.id as profile_id, ep.category, ep.subcategory, 
                    c.name as category_name, sc.name as subcategory_name,
                    ct.name as city_name,ep.user_id as id_profile,
                    COUNT(con.id) as consultation_count,
                    COALESCE(AVG(er.rating), 0) as avg_rating, 
                    COUNT(DISTINCT er.id) as review_count,
                    bi.consultation_price, bi.consultation_minutes
                    FROM users u
                    JOIN user_profiles up ON u.id = up.user_id
                    JOIN expert_profiledetails ep ON u.id = ep.user_id
                    LEFT JOIN categories c ON ep.category = c.id
                    LEFT JOIN subcategories sc ON ep.subcategory = sc.id
                    LEFT JOIN cities ct ON ep.city = ct.id
                    LEFT JOIN consultations con ON u.id = con.expert_id AND con.status IN ('completed', 'confirmed')
                    LEFT JOIN expert_ratings er ON ep.user_id = er.expert_id
                    LEFT JOIN banking_information bi ON ep.id = bi.profile_id
                    WHERE u.role = 'expert' AND ep.status = 'approved'
                    GROUP BY u.id, u.full_name, up.profile_image, up.bio, ep.id, ep.category, ep.subcategory, 
                    c.name, sc.name, ct.name, bi.consultation_price, bi.consultation_minutes
                    ORDER BY consultation_count DESC, avg_rating DESC";

$topExpertsResult = $conn->query($topExpertsQuery);
$topExperts = [];

if ($topExpertsResult && $topExpertsResult->num_rows > 0) {
    while ($row = $topExpertsResult->fetch_assoc()) {
        // Get expert skills
        $skillsQuery = "SELECT skill_name FROM skills WHERE profile_id = " . $row['profile_id'] . " LIMIT 5";
        $skillsResult = $conn->query($skillsQuery);
        $skills = [];
        
        if ($skillsResult && $skillsResult->num_rows > 0) {
            while ($skillRow = $skillsResult->fetch_assoc()) {
                $skills[] = $skillRow['skill_name'];
            }
        }
        
        // Get expert certificates
        $certificatesQuery = "SELECT * FROM certificates WHERE profile_id = " . $row['profile_id'] . " AND status = 'approved' ORDER BY id";
        $certificatesResult = $conn->query($certificatesQuery);
        $certificates = [];
        
        if ($certificatesResult && $certificatesResult->num_rows > 0) {
            while ($certRow = $certificatesResult->fetch_assoc()) {
                $certificates[] = $certRow;
            }
        }
        
        // Get expert experiences
        $experiencesQuery = "SELECT * FROM experiences WHERE profile_id = " . $row['profile_id'] . " AND status = 'approved' ORDER BY id";
        $experiencesResult = $conn->query($experiencesQuery);
        $experiences = [];
        
        if ($experiencesResult && $experiencesResult->num_rows > 0) {
            while ($expRow = $experiencesResult->fetch_assoc()) {
                $experiences[] = $expRow;
            }
        }
        
        // Get expert formations (courses)
        $formationsQuery = "SELECT * FROM formations WHERE profile_id = " . $row['profile_id'] . " AND status = 'approved' ORDER BY id";
        $formationsResult = $conn->query($formationsQuery);
        $formations = [];
        
        if ($formationsResult && $formationsResult->num_rows > 0) {
            while ($formRow = $formationsResult->fetch_assoc()) {
                $formations[] = $formRow;
            }
        }
        
        // Get expert ratings and reviews
        $ratingsQuery = "SELECT er.*, er.id as rating_id ,u.full_name as client_name, up.profile_image as client_image
                        FROM expert_ratings er
                        JOIN users u ON er.client_id = u.id
                        JOIN user_profiles up ON u.id = up.user_id
                        WHERE er.expert_id = " . $row['id_profile'] . "
                        ORDER BY er.created_at DESC
                        LIMIT 5";
        $ratingsResult = $conn->query($ratingsQuery);
        $ratings = [];
        
        if ($ratingsResult && $ratingsResult->num_rows > 0) {
            while ($ratingRow = $ratingsResult->fetch_assoc()) {
                $ratings[] = $ratingRow;
            }
        }
        
        // Get expert social links
        $socialLinksQuery = "SELECT * FROM expert_social_links WHERE profile_id = " . $row['profile_id'];
        $socialLinksResult = $conn->query($socialLinksQuery);
        $socialLinks = null;
        
        if ($socialLinksResult && $socialLinksResult->num_rows > 0) {
            $socialLinks = $socialLinksResult->fetch_assoc();
        }
        
        $row['skills'] = $skills;
        $row['certificates'] = $certificates;
        $row['experiences'] = $experiences;
        $row['formations'] = $formations;
        $row['ratings'] = $ratings;
        $row['social_links'] = $socialLinks;
        $topExperts[] = $row;
    }
}

// Fetch all testimonials/reviews from the database
$testimonialsQuery = "SELECT er.*, u.full_name as client_name, up.profile_image as client_image,
                     eu.full_name as expert_name, eup.profile_image as expert_image,
                     c.name as category_name, sc.name as subcategory_name
                     FROM expert_ratings er
                     JOIN users u ON er.client_id = u.id
                     JOIN user_profiles up ON u.id = up.user_id
                     JOIN expert_profiledetails ep ON er.expert_id = ep.id
                     JOIN users eu ON ep.user_id = eu.id
                     JOIN user_profiles eup ON eu.id = eup.user_id
                     LEFT JOIN categories c ON ep.category = c.id
                     LEFT JOIN subcategories sc ON ep.subcategory = sc.id
                     WHERE er.comment IS NOT NULL AND LENGTH(er.comment) > 10
                     ORDER BY er.rating DESC, er.created_at DESC";

$testimonialsResult = $conn->query($testimonialsQuery);
$testimonials = [];

if ($testimonialsResult && $testimonialsResult->num_rows > 0) {
    while ($row = $testimonialsResult->fetch_assoc()) {
        $testimonials[] = $row;
    }
}

// Define default client and expert images
$defaultClientImages = [
    '../assets/images/client1.jpg',
    '../assets/images/client2.jpg',
    '../assets/images/client3.jpg'
];

$defaultExpertImages = [
    '../assets/images/expert1.jpg',
    '../assets/images/expert2.jpg',
    '../assets/images/expert3.jpg'
];

$clientIndex = 0; // Initialize client index for default images

// Fetch categories for the menu and category section
$categoriesQuery = "SELECT c.*, COUNT(ep.id) as expert_count 
                   FROM categories c
                   LEFT JOIN expert_profiledetails ep ON c.id = ep.category AND ep.status = 'approved'
                   GROUP BY c.id, c.name
                   ORDER BY expert_count DESC, c.name ASC
                   LIMIT 8";
$categoriesResult = $conn->query($categoriesQuery);
$categories = [];

if ($categoriesResult && $categoriesResult->num_rows > 0) {
    while ($row = $categoriesResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch statistics
$statsQuery = "SELECT 
              (SELECT COUNT(*) FROM users WHERE role = 'expert' AND id IN (SELECT user_id FROM expert_profiledetails WHERE status = 'approved')) as expert_count,
              (SELECT COUNT(*) FROM users WHERE role = 'client') as client_count,
              (SELECT COUNT(*) FROM consultations WHERE status IN ('completed', 'confirmed')) as consultation_count,
              (SELECT COUNT(*) FROM categories) as category_count";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Fetch user's active consultations if logged in
$activeConsultations = [];
if ($isLoggedIn) {
    $activeConsultationsQuery = "SELECT c.*, u.full_name as expert_name, up.profile_image as expert_image,
                                bi.consultation_price, bi.consultation_minutes
                                FROM consultations c
                                JOIN users u ON c.expert_id = u.id
                                JOIN user_profiles up ON u.id = up.user_id
                                JOIN expert_profiledetails ep ON u.id = ep.user_id
                                JOIN banking_information bi ON ep.id = bi.profile_id
                                WHERE c.client_id = $userId AND c.status IN ('confirmed', 'in_progress')
                                ORDER BY c.status = 'in_progress' DESC, c.consultation_date ASC, c.consultation_time ASC
                                LIMIT 3";
    $activeConsultationsResult = $conn->query($activeConsultationsQuery);
    
    if ($activeConsultationsResult && $activeConsultationsResult->num_rows > 0) {
        while ($row = $activeConsultationsResult->fetch_assoc()) {
            $activeConsultations[] = $row;
        }
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

// Get user profile if logged in
$userProfile = null;
if ($isLoggedIn) {
    $userProfileQuery = "SELECT * FROM user_profiles WHERE user_id = $userId";
    $userProfileResult = $conn->query($userProfileQuery);
    
    if ($userProfileResult && $userProfileResult->num_rows > 0) {
        $userProfile = $userProfileResult->fetch_assoc();
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

        if ($ratingsResult && $ratingsResult->num_rows > 0) {
            while ($ratingRow = $ratingsResult->fetch_assoc()) {
                // Get expert responses to this rating
                $responseQuery = "SELECT * FROM expert_rating_responses WHERE rating_id = " . $ratingRow['rating_id'];
                $responseResult = $conn->query($responseQuery);
                $responses = [];
                
                if ($responseResult && $responseResult->num_rows > 0) {
                    while ($responseRow = $responseResult->fetch_assoc()) {
                        $responses[] = $responseRow;
                    }
                }
                
                $ratingRow['response_text'] = $responses;
                $ratings[] = $ratingRow;
            }
        }
// Define image sources for the website
$imageSources = [
    'hero' => 'photo/hero.jpg',
    'about' => 'https://img.freepik.com/free-photo/group-diverse-people-having-business-meeting_53876-25060.jpg',
    'cta' => 'https://img.freepik.com/free-photo/business-people-shaking-hands-together_53876-30568.jpg'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?> - Expert Consultation Platform</title>
    <!-- Favicon -->
    <link rel="icon" href="#" type="image/icon">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- AOS Animation Library -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css">
    <!-- Swiper Slider CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.css" />
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
        
        /* Hero Section */
        .hero-section {
            position: relative;
            padding: 150px 0 120px;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.9), rgba(124, 58, 237, 0.9)), url('<?php echo $imageSources['hero']; ?>');
            background-size: cover;
            background-position: center;
            color: white;
            overflow: hidden;
            border-radius: 0 0 0 100px;
        }
        
        .hero-section::before {
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
        
        .hero-section::after {
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
        
        .hero-content {
            position: relative;
            z-index: 1;
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            line-height: 1.2;
        }
        
        .hero-subtitle {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0.9;
        }
        
        .hero-image {
            position: relative;
            z-index: 1;
        }
        
        .hero-image img {
            max-width: 100%;
            border-radius: var(--border-radius-xl);
            box-shadow: var(--box-shadow-xl);
            transform: perspective(1000px) rotateY(-15deg);
            transition: transform 0.5s ease;
        }
        
        .hero-image:hover img {
            transform: perspective(1000px) rotateY(0deg);
        }
        
        .hero-stats {
            position: relative;
            margin-top: -80px;
            z-index: 10;
        }
        
        .stat-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            padding: 30px;
            box-shadow: var(--box-shadow-lg);
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            border: 1px solid var(--gray-100);
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-xl);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-100), var(--primary-200));
            color: var(--primary-600);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 1.8rem;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--gray-900);
            font-family: 'Montserrat', sans-serif;
            background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-title {
            font-size: 1rem;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        
        /* Featured Experts Section */
        .section-title {
            position: relative;
            margin-bottom: 50px;
            text-align: center;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            color: var(--gray-900);
            position: relative;
            display: inline-block;
        }
        
        .section-title p {
            font-size: 1.1rem;
            color: var(--gray-600);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
            border-radius: 2px;
        }
        
        .expert-card {
            background-color: white;
            border-radius: var(--border-radius-xl);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: all 0.5s cubic-bezier(0.25, 0.8, 0.25, 1);
            margin-bottom: 30px;
            height: 100%;
            position: relative;
            border: 1px solid var(--gray-100);
        }
        
        .expert-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .expert-img-container {
            position: relative;
            overflow: hidden;
            height: 240px;
        }
        
        .expert-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .expert-card:hover .expert-img {
            transform: scale(1.1);
        }
        
        .expert-info {
            padding: 25px;
            position: relative;
            border-top: 1px solid var(--gray-100);
        }
        
        .expert-name {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--gray-900);
            font-size: 1.25rem;
        }
        
        .expert-category {
            color: var(--primary-600);
            font-weight: 600;
            margin-bottom: 15px;
            font-size: 0.875rem;
        }
        
        .expert-rating {
            color: var(--warning-500);
            margin-bottom: 15px;
            font-size: 0.875rem;
        }
        
        .expert-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            padding: 5px 15px;
            border-radius: var(--border-radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
            box-shadow: var(--box-shadow);
        }
        
        .expert-price {
            font-weight: 700;
            color: var(--gray-900);
            font-size: 1.125rem;
            margin-bottom: 15px;
        }
        
        .expert-price span {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 400;
        }
        
        .expert-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .expert-skill {
            background-color: var(--gray-100);
            color: var(--gray-700);
            padding: 5px 12px;
            border-radius: var(--border-radius-full);
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .expert-bio {
            color: var(--gray-600);
            margin-bottom: 20px;
            font-size: 0.875rem;
            line-height: 1.6;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Categories Section */
        .categories-section {
            padding: 100px 0;
            background-color: var(--gray-50);
            position: relative;
            overflow: hidden;
        }
        
        .categories-section::before {
            content: '';
            position: absolute;
            top: -200px;
            right: -200px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-50), var(--primary-100));
            z-index: 0;
            opacity: 0.5;
        }
        
        .categories-section::after {
            content: '';
            position: absolute;
            bottom: -200px;
            left: -200px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-50), var(--secondary-100));
            z-index: 0;
            opacity: 0.5;
        }
        
        .category-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            padding: 30px;
            text-align: center;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            height: 100%;
            border: 1px solid var(--gray-100);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
        }
        
        .category-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-50), var(--primary-100));
            color: var(--primary-600);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            transition: all 0.3s ease;
        }
        
        .category-card:hover .category-icon {
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            transform: scale(1.1);
        }
        
        .category-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--gray-900);
        }
        
        .category-count {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-bottom: 20px;
        }
        
        /* Testimonials Section */
        .testimonials-section {
            padding: 100px 0;
            background-color: white;
            position: relative;
            overflow: hidden;
        }
        
        .testimonials-section::before {
            content: '';
            position: absolute;
            top: -200px;
            right: -200px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-50), var(--primary-100));
            z-index: 0;
            opacity: 0.5;
        }
        
        .testimonials-section::after {
            content: '';
            position: absolute;
            bottom: -200px;
            left: -200px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-50), var(--secondary-100));
            z-index: 0;
            opacity: 0.5;
        }
        
        .testimonial-card {
            background-color: white;
            border-radius: var(--border-radius-xl);
            padding: 30px;
            box-shadow: var(--box-shadow-lg);
            margin: 20px 10px;
            position: relative;
            z-index: 1;
            border: 1px solid var(--gray-100);
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .testimonial-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-xl);
        }
        
        .testimonial-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .testimonial-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 15px;
            border: 3px solid var(--primary-100);
        }
        
        .testimonial-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .testimonial-meta {
            flex: 1;
        }
        
        .testimonial-name {
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--gray-900);
        }
        
        .testimonial-position {
            font-size: 0.9rem;
            color: var(--primary-600);
        }
        
        .testimonial-rating {
            color: var(--warning-500);
            margin-bottom: 15px;
            font-size: 0.875rem;
        }
        
        .testimonial-text {
            font-size: 1rem;
            color: var(--gray-700);
            line-height: 1.7;
            margin-bottom: 20px;
            position: relative;
            padding-left: 25px;
        }
        
        .testimonial-text::before {
            content: '"';
            position: absolute;
            left: 0;
            top: -10px;
            font-size: 3rem;
            color: var(--primary-200);
            font-family: Georgia, serif;
            line-height: 1;
        }
        
        .testimonial-expert {
            display: flex;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid var(--gray-200);
        }
        
        .testimonial-expert-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 10px;
        }
        
        .testimonial-expert-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .testimonial-expert-info {
            flex: 1;
        }
        
        .testimonial-expert-name {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 2px;
            color: var(--gray-900);
        }
        
        .testimonial-expert-category {
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        
        /* CTA Section */
        .cta-section {
            padding: 100px 0;
            background: linear-gradient(135deg, rgba(2, 132, 199, 0.9), rgba(124, 58, 237, 0.9)), url('<?php echo $imageSources['cta']; ?>');
            background-size: cover;
            background-position: center;
            color: white;
            position: relative;
            overflow: hidden;
            border-radius: var(--border-radius-3xl) var(--border-radius-3xl) 0 0;
            margin-top: 100px;
        }
        
        .cta-section::before {
            content: '';
            position: absolute;
            top: -100px;
            right: -100px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            z-index: 0;
        }
        
        .cta-section::after {
            content: '';
            position: absolute;
            bottom: -100px;
            left: -100px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            z-index: 0;
        }
        
        .cta-content {
            position: relative;
            z-index: 1;
            text-align: center;
        }
        
        .cta-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 20px;
        }
        
        .cta-subtitle {
            font-size: 1.2rem;
            margin-bottom: 40px;
            opacity: 0.9;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
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
        
        .btn-secondary {
            background-color: var(--secondary-600);
            border-color: var(--secondary-600);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: var(--secondary-700);
            border-color: var(--secondary-700);
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
        
        .btn-white {
            background-color: white;
            color: var(--primary-600);
            border-color: white;
        }
        
        .btn-white:hover {
            background-color: var(--gray-100);
            color: var(--primary-700);
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .btn-outline-white {
            border: 2px solid white;
            color: white;
            background-color: transparent;
        }
        
        .btn-outline-white:hover {
            background-color: white;
            color: var(--primary-600);
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
            animation: pulse 2s infinite;
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
        
        /* Swiper Slider Customization */
        .swiper-pagination-bullet {
            width: 12px;
            height: 12px;
            background-color: var(--primary-600);
            opacity: 0.5;
        }
        
        .swiper-pagination-bullet-active {
            opacity: 1;
            background-color: var(--primary-600);
        }
        
        .swiper-button-next, .swiper-button-prev {
            color: var(--primary-600);
            background-color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            box-shadow: var(--box-shadow);
        }
        
        .swiper-button-next:after, .swiper-button-prev:after {
            font-size: 1.2rem;
        }
        
        /* Active Consultations */
        .active-consultations {
            margin-bottom: 40px;
        }
        
        .consultation-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--gray-100);
            transition: var(--transition);
        }
        
        .consultation-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
        }
        
        /* Responsive Styles */
        @media (max-width: 1200px) {
            .hero-title {
                font-size: 3rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
            
            .cta-title {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 992px) {
            .hero-title {
                font-size: 2.5rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .hero-image img {
                transform: none;
                margin-top: 30px;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .cta-title {
                font-size: 2rem;
            }
            
            .cta-subtitle {
                font-size: 1.1rem;
            }
        }
        
        @media (max-width: 768px) {
            .hero-section {
                padding: 80px 0 60px;
                text-align: center;
            }
            
            .hero-title {
                font-size: 2rem;
            }
            
            .hero-subtitle {
                font-size: 1rem;
            }
            
            .hero-stats {
                margin-top: 40px;
            }
            
            .stat-card {
                margin-bottom: 20px;
            }
            
            .section-title h2 {
                font-size: 1.8rem;
            }
            
            .section-title p {
                font-size: 1rem;
            }
            
            .cta-section {
                padding: 60px 0;
            }
            
            .cta-title {
                font-size: 1.8rem;
            }
            
            .cta-subtitle {
                font-size: 1rem;
            }
            
            .footer {
                padding: 60px 0 20px;
            }
            
            .footer-title {
                margin-top: 30px;
            }
        }
        
        @media (max-width: 576px) {
            .hero-title {
                font-size: 1.8rem;
            }
            
            .btn-lg {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .stat-number {
                font-size: 1.8rem;
            }
            
            .stat-title {
                font-size: 0.9rem;
            }
            
            .section-title h2 {
                font-size: 1.5rem;
            }
            
            .cta-title {
                font-size: 1.5rem;
            }
            
            .back-to-top {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }

/* Expert Profile Modal Styles */
.profile-modal-header {
    position: relative;
    height: 250px;
    background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
}

.profile-modal-header::before {
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

.profile-modal-header::after {
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

.profile-avatar-container {
    position: relative;
    bottom: -50px;
    left: 50px;
    z-index: 10;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    border: 5px solid white;
    box-shadow: var(--box-shadow-lg);
    object-fit: cover;
}

.profile-modal-body {
    padding-top: 80px;
}

.profile-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--gray-200);
}

.profile-section:last-child {
    border-bottom: none;
}

.profile-section-title {
    font-weight: 700;
    font-size: 1.125rem;
    margin-bottom: 15px;
    color: var(--gray-900);
    position: relative;
    display: inline-block;
    padding-bottom: 8px;
}

.profile-section-title::after {
    content: '';
    position: absolute;
    left: 0;
    bottom: 0;
    width: 40px;
    height: 3px;
    background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
    border-radius: 3px;
}

.social-links-container {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-top: 15px;
}

.social-link {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 15px;
    border-radius: var(--border-radius);
    background-color: var(--gray-100);
    color: var(--gray-700);
    transition: var(--transition);
    font-size: 0.875rem;
}

.social-link:hover {
    background-color: var(--primary-100);
    color: var(--primary-700);
    transform: translateY(-3px);
}

.social-link i {
    font-size: 1.125rem;
}

.credential-card {
    background-color: var(--gray-50);
    border-radius: var(--border-radius);
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid var(--gray-200);
    transition: var(--transition);
}

.credential-card:hover {
    background-color: var(--gray-100);
    transform: translateY(-3px);
    box-shadow: var(--box-shadow);
}

.credential-title {
    font-weight: 600;
    margin-bottom: 5px;
    color: var(--gray-900);
}

.credential-subtitle {
    font-size: 0.875rem;
    color: var(--gray-600);
    margin-bottom: 10px;
}

.credential-description {
    font-size: 0.875rem;
    color: var(--gray-700);
}

.review-card {
    background-color: var(--gray-50);
    border-radius: var(--border-radius);
    padding: 15px;
    margin-bottom: 15px;
    border: 1px solid var(--gray-200);
}

.review-header {
    display: flex;
    align-items: center;
    margin-bottom: 10px;
}

.review-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    overflow: hidden;
    margin-right: 10px;
}

.review-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.review-meta {
    flex: 1;
}

.review-name {
    font-weight: 600;
    margin-bottom: 2px;
    color: var(--gray-900);
    font-size: 0.9rem;
}

.review-date {
    font-size: 0.8rem;
    color: var(--gray-600);
}

.review-rating {
    color: var(--warning-500);
    margin-bottom: 10px;
    font-size: 0.875rem;
}

.review-text {
    font-size: 0.9rem;
    color: var(--gray-700);
    line-height: 1.6;
}

/* Responsive adjustments for the profile modal */
@media (max-width: 992px) {
    .profile-avatar-container {
        left: 30px;
    }
    
    .profile-avatar {
        width: 100px;
        height: 100px;
    }
    
    .profile-modal-body {
        padding-top: 60px;
    }
}

@media (max-width: 768px) {
    .profile-avatar-container {
        left: 50%;
        transform: translateX(-50%);
        bottom: -60px;
    }
    
    .profile-modal-body {
        padding-top: 70px;
        text-align: center;
    }
    
    .profile-section-title::after {
        left: 50%;
        transform: translateX(-50%);
    }
}

@media (max-width: 576px) {
    .profile-avatar {
        width: 80px;
        height: 80px;
    }
    
    .profile-modal-body {
        padding-top: 50px;
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
            <a class="navbar-brand" href="index.php">
                <?php if(isset($settings['site_image']) && !empty($settings['site_image'])): ?>
                    <img src="uploads/<?php echo $settings['site_image']; ?>" alt="<?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>" height="40">
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
                        <a class="nav-link active" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="client/find-experts.php">Find Experts</a>
                    </li>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="client/how-it-works.php">How It Works</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="client/contact-support.php">Contact Support</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <?php if($isLoggedIn): ?>
                        <div class="dropdown me-3">
                            <a class="position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" onclick="markNotificationsAsRead()">
                                <i class="fas fa-bell fs-5 text-gray-700"></i>
                                <span class="notification-badge" id="notification-badge"><?php echo $notificationCount; ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="notificationDropdown" style="border-radius: 12px;">
                                <li><h6 class="dropdown-header fw-bold">Notifications</h6></li>
                                <div id="notifications-container">
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
                                <li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="client/notifications.php">View All</a></li>
                            </ul>
                        </div>
                        <div class="dropdown">
                            <a class="btn btn-outline-primary dropdown-toggle d-flex align-items-center gap-2" href="#" role="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <?php if($userProfile && !empty($userProfile['profile_image'])): ?>
                                    <img src="uploads/<?php echo $userProfile['profile_image']; ?>" alt="Profile" class="rounded-circle" width="30" height="30">
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
                                <li><a class="dropdown-item py-2" href="client/profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
                                <?php if(isset($userBalance)): ?>
                                <li><a class="dropdown-item py-2" href="client/add-fund.php"><i class="fas fa-wallet me-2 text-primary"></i> Add Fund: <?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></a></li>
                                <?php endif; ?>
                                <li><a class="dropdown-item py-2" href="client/my-consultations.php"><i class="fas fa-calendar-check me-2 text-primary"></i> My Consultations</a></li>
                                <li><a class="dropdown-item py-2" href="client/messages.php"><i class="fas fa-envelope me-2 text-primary"></i> Messages</a></li>
                                <li><a class="dropdown-item py-2" href="client/my-reports.php"><i class="fas fa-flag me-2 text-primary"></i> My Reports</a></li>
                                <li><a class="dropdown-item py-2" href="client/history-ratings.php"><i class="fas fa-solid fa-star text-primary"></i> Ratings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2" href="Config/logout.php"><i class="fas fa-sign-out-alt me-2 text-danger"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="pages/login.php" class="btn btn-outline-primary me-2">Login</a>
                        <a href="pages/signup.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content">
                    <h1 class="hero-title" data-aos="fade-up">Connect with Expert Consultants in Real-Time</h1>
                    <p class="hero-subtitle" data-aos="fade-up" data-aos-delay="100">Get personalized guidance from top professionals across various fields. Our platform brings expertise directly to you, whenever you need it.</p>
                    <div class="d-flex flex-wrap gap-3" data-aos="fade-up" data-aos-delay="200">
                        <a href="client/find-experts.php" class="btn btn-white btn-lg">Find an Expert</a>
                        <a href="client/how-it-works.php" class="btn btn-outline-white btn-lg">How It Works</a>
                    </div>
                </div>
                <div class="col-lg-6 hero-image" data-aos="fade-left" data-aos-delay="300">
                    <img src="<?php echo $imageSources['hero']; ?>" alt="Expert Consultation" class="img-fluid">
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="hero-stats">
        <div class="container">
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['expert_count']); ?>+</div>
                        <div class="stat-title">Expert Consultants</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['client_count']); ?>+</div>
                        <div class="stat-title">Happy Clients</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-comments"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['consultation_count']); ?>+</div>
                        <div class="stat-title">Consultations</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-th-large"></i>
                        </div>
                        <div class="stat-number"><?php echo number_format($stats['category_count']); ?>+</div>
                        <div class="stat-title">Categories</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Active Consultations Section (if any) -->
    <?php if($isLoggedIn && count($activeConsultations) > 0): ?>
        <section class="container mt-5">
            <div class="active-consultations" data-aos="fade-up">
                <h3 class="mb-4"><i class="fas fa-comments me-2 text-success"></i> Your Active Consultations</h3>
                <div class="row">
                    <?php foreach($activeConsultations as $consultation): ?>
                        <div class="col-md-4 mb-3">
                            <div class="consultation-card">
                                <div class="d-flex align-items-center mb-3">
                                    <?php if(!empty($consultation['expert_image'])): ?>
                                        <img src="uploads/<?php echo $consultation['expert_image']; ?>" alt="<?php echo $consultation['expert_name']; ?>" class="rounded-circle me-3" width="50" height="50" style="object-fit: cover;">
                                   <?php endif; ?>
                                    <div>
                                        <h6 class="mb-0 fw-bold"><?php echo $consultation['expert_name']; ?></h6>
                                        <p class="text-muted mb-0 small">
                                            <?php if($consultation['status'] == 'confirmed'): ?>
                                                <span class="badge bg-success">Accepted</span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">In Progress</span>
                                            <?php endif; ?>
                                            <span class="ms-2">
                                                <i class="far fa-calendar-alt me-1"></i> 
                                                <?php echo date('M d, Y', strtotime($consultation['consultation_date'])); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end">
                                    <a href="client/consultation-chat.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-comments me-1"></i> Go to Chat
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Featured Experts Section -->
    <section class="container mt-5 pt-5">
        <div class="section-title" data-aos="fade-up">
            <h2>Our Expert Consultants</h2>
            <p>Connect with our experienced and highly-rated consultants who have helped countless clients achieve their goals.</p>
        </div>
        
        <div class="row">
            <?php foreach($topExperts as $expert): 
                // Get expert image
                $expertImage = !empty($expert['profile_image']) ? $expert['profile_image'] : '';
            ?>
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo 100 * array_search($expert, $topExperts); ?>">
                    <div class="expert-card">
                        <?php if($expert['avg_rating'] >= 4.5): ?>
                            <div class="expert-badge">Top Rated</div>
                        <?php endif; ?>
                        
                        <div class="expert-img-container">
                            <?php if(!empty($expert['profile_image'])): ?>
                                <img src="uploads/<?php echo $expert['profile_image']; ?>" alt="<?php echo $expert['full_name']; ?>" class="expert-img">
                            <?php endif; ?>
                        </div>
                        <div class="expert-info">
                            <h5 class="expert-name"><?php echo $expert['full_name']; ?></h5>
                            <p class="expert-category"><?php echo $expert['category_name'] . ' - ' . $expert['subcategory_name']; ?></p>
                            <div class="expert-rating">
                                <?php 
                                $rating = round($expert['avg_rating']);
                                for($i = 1; $i <= 5; $i++): 
                                    if($i <= $rating): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif;
                                endfor; ?>
                                <span class="ms-1">(<?php echo $expert['review_count']; ?> reviews)</span>
                            </div>
                            
                            <?php if(isset($expert['consultation_price']) && isset($expert['consultation_minutes'])): ?>
                            <div class="expert-price">
                                <?php echo number_format($expert['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>
                                <span>/ <?php echo $expert['consultation_minutes']; ?> min</span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if(!empty($expert['bio'])): ?>
                            <p class="expert-bio"><?php echo $expert['bio']; ?></p>
                            <?php endif; ?>
                            
                            <?php if(!empty($expert['skills'])): ?>
                            <div class="expert-skills">
                                <?php foreach(array_slice($expert['skills'], 0, 3) as $skill): ?>
                                    <span class="expert-skill"><?php echo $skill; ?></span>
                                <?php endforeach; ?>
                                <?php if(count($expert['skills']) > 3): ?>
                                    <span class="expert-skill">+<?php echo count($expert['skills']) - 3; ?></span>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#expertProfileModal<?php echo $expert['id']; ?>">
                                    View Profile
                                </button>
                                <a href="client/find-experts.php" class="btn btn-primary flex-grow-1">Book Consultation</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Expert Profile Modal -->
<div class="modal fade" id="expertProfileModal<?php echo $expert['id']; ?>" tabindex="-1" aria-labelledby="expertProfileModalLabel<?php echo $expert['id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="profile-modal-header">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3 bg-white" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="profile-avatar-container">
                    <img src="uploads/<?php echo $expertImage; ?>" alt="<?php echo $expert['full_name']; ?>" class="profile-avatar">
                </div>
            </div>
            <div class="modal-body profile-modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <h2 class="mb-1"><?php echo $expert['full_name']; ?></h2>
                        <p class="text-primary fw-semibold mb-3"><?php echo $expert['category_name'] . ' - ' . $expert['subcategory_name']; ?></p>
                        
                        <div class="d-flex align-items-center mb-4">
                            <div class="expert-rating me-3">
                                <?php 
                                $rating = round($expert['avg_rating']);
                                for($i = 1; $i <= 5; $i++): 
                                    if($i <= $rating): ?>
                                        <i class="fas fa-star"></i>
                                    <?php else: ?>
                                        <i class="far fa-star"></i>
                                    <?php endif;
                                endfor; ?>
                                <span class="ms-1">(<?php echo $expert['review_count']; ?> reviews)</span>
                            </div>
                            <span class="badge <?php echo ($expert['status'] == 'Online') ? 'bg-success' : 'bg-secondary'; ?> ms-2">
                                <?php echo $expert['status']; ?>
                            </span>
                        </div>
                        
                        <!-- Bio Section -->
                        <div class="profile-section">
                            <h3 class="profile-section-title">About</h3>
                            <p><?php echo !empty($expert['bio']) ? $expert['bio'] : 'No bio available.'; ?></p>
                        </div>
                        
                        <!-- Location Section -->
                        <div class="profile-section">
                            <h3 class="profile-section-title">Location</h3>
                            <p>
                                <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                <?php echo !empty($expert['city_name']) ? $expert['city_name'] : 'City not specified'; ?>
                            </p>
                            
                            <?php if(!empty($expert['workplace_map_url'])): ?>
                            <div class="mt-3">
                                <a href="<?php echo $expert['workplace_map_url']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-map me-2"></i> View on Google Maps
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Skills Section -->
                        <?php if(!empty($expert['skills'])): ?>
                        <div class="profile-section">
                            <h3 class="profile-section-title">Skills</h3>
                            <div class="expert-skills">
                                <?php foreach($expert['skills'] as $skill): ?>
                                    <span class="expert-skill"><?php echo $skill; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Social Media Links -->
                        <?php if(!empty($expert['social_links'])): ?>
                        <div class="profile-section">
                            <h3 class="profile-section-title">Connect</h3>
                            <div class="social-links-container">
                                <?php if(!empty($expert['social_links']['facebook_url'])): ?>
                                <a href="<?php echo $expert['social_links']['facebook_url']; ?>" target="_blank" class="social-link">
                                    <i class="fab fa-facebook-f"></i> Facebook
                                </a>
                                <?php endif; ?>
                                
                                <?php if(!empty($expert['social_links']['instagram_url'])): ?>
                                <a href="<?php echo $expert['social_links']['instagram_url']; ?>" target="_blank" class="social-link">
                                    <i class="fab fa-instagram"></i> Instagram
                                </a>
                                <?php endif; ?>
                                
                                <?php if(!empty($expert['social_links']['linkedin_url'])): ?>
                                <a href="<?php echo $expert['social_links']['linkedin_url']; ?>" target="_blank" class="social-link">
                                    <i class="fab fa-linkedin-in"></i> LinkedIn
                                </a>
                                <?php endif; ?>
                                
                                <?php if(!empty($expert['social_links']['twitter_url'])): ?>
                                <a href="<?php echo $expert['social_links']['twitter_url']; ?>" target="_blank" class="social-link">
                                    <i class="fab fa-twitter"></i> Twitter
                                </a>
                                <?php endif; ?>
                                
                                <?php if(!empty($expert['social_links']['github_url'])): ?>
                                <a href="<?php echo $expert['social_links']['github_url']; ?>" target="_blank" class="social-link">
                                    <i class="fab fa-github"></i> GitHub
                                </a>
                                <?php endif; ?>
                                
                                <?php if(!empty($expert['social_links']['website_url'])): ?>
                                <a href="<?php echo $expert['social_links']['website_url']; ?>" target="_blank" class="social-link">
                                    <i class="fas fa-globe"></i> Website
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Reviews Section -->
                        <div class="profile-section">
                            <h3 class="profile-section-title">Client Reviews</h3>
                            <?php if(!empty($expert['ratings'])): ?>
                                <?php foreach($expert['ratings'] as $rating): ?>
                                <div class="review-card">
                                    <div class="review-header">
                                        <div class="review-avatar">
                                            <?php if(!empty($rating['client_image'])): ?>
                                                <img src="uploads/<?php echo $rating['client_image']; ?>" alt="<?php echo $rating['client_name']; ?>">
                                            <?php endif; ?>
                                        </div>
                                        <div class="review-meta">
                                            <div class="review-name"><?php echo $rating['client_name']; ?></div>
                                            <div class="review-date"><?php echo date('M d, Y', strtotime($rating['created_at'])); ?></div>
                                        </div>
                                    </div>
                                    <div class="review-rating">
                                        <?php 
                                        for($i = 1; $i <= 5; $i++): 
                                            if($i <= $rating['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif;
                                        endfor; 
                                        ?>
                                    </div>
                                    <div class="review-text"><?php echo $rating['comment']; ?></div>
                                    
                                    <?php if(!empty($rating['responses'])): ?>
                                    <div class="mt-3 p-3 bg-light rounded">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="me-2">
                                                <img src="uploads/<?php echo $expertImage; ?>" alt="<?php echo $expert['full_name']; ?>" class="rounded-circle" width="30" height="30">
                                            </div>
                                            <div>
                                                <strong><?php echo $expert['full_name']; ?></strong>
                                                <small class="text-muted d-block">Expert Response</small>
                                            </div>
                                        </div>
                                        <p class="mb-0 small"><?php echo $rating['responses'][0]['response_text']; ?></p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted">No reviews yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <!-- Consultation Price -->
                        <?php if(isset($expert['consultation_price']) && isset($expert['consultation_minutes'])): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title">Consultation Fee</h5>
                                <div class="d-flex align-items-center">
                                    <h3 class="mb-0"><?php echo number_format($expert['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></h3>
                                    <span class="ms-2 text-muted">/ <?php echo $expert['consultation_minutes']; ?> min</span>
                                </div>
                                
                                <a href="client/find-experts.php" class="btn btn-primary w-100 mt-3">
                                    Book Consultation
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Credentials Section -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <h5 class="card-title mb-3">Professional Credentials</h5>
                                
                                <!-- Certificates -->
                                <?php if(!empty($expert['certificates'])): ?>
                                <h6 class="fw-bold mb-2">Certificates</h6>
                                <div class="mb-3">
                                    <?php foreach($expert['certificates'] as $cert): ?>
                                    <div class="credential-card">
                                        <div class="credential-title"><?php echo htmlspecialchars($cert['institution']); ?></div>
                                        <div class="credential-subtitle">
                                            <?php 
                                                $start_date = new DateTime($cert['start_date']);
                                                $end_date = new DateTime($cert['end_date']);
                                                echo $start_date->format('M Y') . ' - ' . $end_date->format('M Y');
                                            ?>
                                        </div>
                                        <div class="credential-description"><?php echo htmlspecialchars($cert['description']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Experiences -->
                                <?php if(!empty($expert['experiences'])): ?>
                                <h6 class="fw-bold mb-2">Work Experience</h6>
                                <div class="mb-3">
                                    <?php foreach($expert['experiences'] as $exp): ?>
                                    <div class="credential-card">
                                        <div class="credential-title"><?php echo htmlspecialchars($exp['workplace']); ?></div>
                                        <div class="credential-subtitle">
                                            <?php 
                                                $start_date = new DateTime($exp['start_date']);
                                                $end_date = !empty($exp['end_date']) ? new DateTime($exp['end_date']) : null;
                                                echo $start_date->format('M Y') . ' - ' . ($end_date ? $end_date->format('M Y') : 'Present');
                                            ?>
                                            <span class="ms-2">
                                                (<?php echo $exp['duration_years']; ?> years, <?php echo $exp['duration_months']; ?> months)
                                            </span>
                                        </div>
                                        <div class="credential-description"><?php echo htmlspecialchars($exp['description']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Formations (Courses) -->
                                <?php if(!empty($expert['formations'])): ?>
                                <h6 class="fw-bold mb-2">Courses & Training</h6>
                                <div>
                                    <?php foreach($expert['formations'] as $form): ?>
                                    <div class="credential-card">
                                        <div class="credential-title"><?php echo htmlspecialchars($form['formation_name']); ?></div>
                                        <div class="credential-subtitle">
                                            <?php echo htmlspecialchars(ucfirst($form['formation_type'])); ?> - 
                                            <?php echo htmlspecialchars($form['formation_year']); ?>
                                        </div>
                                        <div class="credential-description"><?php echo htmlspecialchars($form['description']); ?></div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                                
                                <?php if(empty($expert['certificates']) && empty($expert['experiences']) && empty($expert['formations'])): ?>
                                <p class="text-muted">No professional credentials available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <a href="client/find-experts.php" class="btn btn-primary">Book Consultation</a>
            </div>
        </div>
    </div>
</div>
            <?php endforeach; ?>
        </div>
        
        <?php if(count($topExperts) > 6): ?>
<div class="text-center mt-4" data-aos="fade-up">
    <nav aria-label="Expert pagination">
        <ul class="pagination justify-content-center">
            <?php 
            $totalExperts = count($topExperts);
            $totalPages = ceil($totalExperts / 6);
            for($i = 1; $i <= $totalPages; $i++): 
            ?>
            <li class="page-item <?php echo $i == 1 ? 'active' : ''; ?>">
                <a class="page-link" href="#" data-page="<?php echo $i; ?>"><?php echo $i; ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const expertsPerPage = 6;
    const expertCards = document.querySelectorAll('.expert-card').length;
    const totalPages = Math.ceil(expertCards / expertsPerPage);
    
    // Hide all experts except first page
    const allExperts = document.querySelectorAll('.expert-card');
    for(let i = expertsPerPage; i < allExperts.length; i++) {
        allExperts[i].closest('.col-lg-4').style.display = 'none';
    }
    
    // Add pagination functionality
    const pageLinks = document.querySelectorAll('.pagination .page-link');
    pageLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const page = parseInt(this.dataset.page);
            
            // Update active class
            document.querySelector('.pagination .active').classList.remove('active');
            this.parentElement.classList.add('active');
            
            // Show/hide experts based on page
            for(let i = 0; i < allExperts.length; i++) {
                if(i >= (page-1) * expertsPerPage && i < page * expertsPerPage) {
                    allExperts[i].closest('.col-lg-4').style.display = 'block';
                } else {
                    allExperts[i].closest('.col-lg-4').style.display = 'none';
                }
            }
            
            // Scroll to experts section
            document.querySelector('.section-title').scrollIntoView({behavior: 'smooth'});
        });
    });
});
</script>
<?php endif; ?>
    </section>

   <!-- Categories Section -->
<section class="categories-section">
    <div class="container">
        <div class="section-title" data-aos="fade-up">
            <h2>Browse by Category</h2>
            <p>Explore our diverse range of categories and find the perfect expert for your specific needs.</p>
        </div>
        
        <div class="row">
            <?php 
            // Define icons for categories
            $categoryIcons = [
                'technology' => 'fas fa-laptop-code',
                'healthcare' => 'fas fa-heartbeat',
                'education' => 'fas fa-graduation-cap',
                'finance' => 'fas fa-chart-line',
                'legal' => 'fas fa-balance-scale',
                'business' => 'fas fa-briefcase',
                'marketing' => 'fas fa-bullhorn',
                'design' => 'fas fa-paint-brush',
                'default' => 'fas fa-th'
            ];

            // Get top 8 categories
            $topCategoriesQuery = "SELECT c.id, c.name, COUNT(ep.id) as expert_count 
                                  FROM categories c
                                  LEFT JOIN expert_profiledetails ep ON c.id = ep.category AND ep.status = 'approved'
                                  GROUP BY c.id, c.name
                                  ORDER BY expert_count DESC, c.name ASC
                                  LIMIT 8";
            $topCategoriesResult = $conn->query($topCategoriesQuery);

            if ($topCategoriesResult && $topCategoriesResult->num_rows > 0) {
                $delay = 0;
                while ($row = $topCategoriesResult->fetch_assoc()) {
                    // Get icon based on category name
                    $iconClass = $categoryIcons['default'];
                    foreach ($categoryIcons as $key => $icon) {
                        if (stripos($row['name'], $key) !== false) {
                            $iconClass = $icon;
                            break;
                        }
                    }

                    echo '
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4" data-aos="fade-up" data-aos-delay="' . $delay . '">
                        <a href="client/find-experts.php?category=' . $row['id'] . '" class="text-decoration-none">
                            <div class="category-card">
                                <div class="category-icon">
                                    <i class="' . $iconClass . '"></i>
                                </div>
                                <h3 class="category-title">' . ucfirst($row['name']) . '</h3>
                                <p class="category-count">' . $row['expert_count'] . ' Experts Available</p>
                                <span class="btn btn-sm btn-outline-primary">Explore</span>
                            </div>
                        </a>
                    </div>';
                    $delay += 100;
                }
            }
            ?>
        </div>
    </div>
</section>


    <!-- How It Works Section -->
    <section class="container py-5 my-5">
        <div class="section-title" data-aos="fade-up">
            <h2>How It Works</h2>
            <p>Our platform makes it easy to connect with experts in just a few simple steps.</p>
        </div>
        
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="text-center">
                    <div class="mb-4">
                        <div class="bg-primary-100 text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-search fa-2x"></i>
                        </div>
                    </div>
                    <h4 class="mb-3">1. Find an Expert</h4>
                    <p class="text-muted">Browse through our diverse categories and find the perfect expert for your specific needs.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="text-center">
                    <div class="mb-4">
                        <div class="bg-primary-100 text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-calendar-check fa-2x"></i>
                        </div>
                    </div>
                    <h4 class="mb-3">2. Book a Consultation</h4>
                    <p class="text-muted">Schedule a consultation at a time that works for you and the expert.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="300">
                <div class="text-center">
                    <div class="mb-4">
                        <div class="bg-primary-100 text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-comments fa-2x"></i>
                        </div>
                    </div>
                    <h4 class="mb-3">3. Connect & Consult</h4>
                    <p class="text-muted">Connect with your expert through our secure platform and get the guidance you need.</p>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="400">
                <div class="text-center">
                    <div class="mb-4">
                        <div class="bg-primary-100 text-primary-600 rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="fas fa-star fa-2x"></i>
                        </div>
                    </div>
                    <h4 class="mb-3">4. Rate & Review</h4>
                    <p class="text-muted">Share your experience and help others find the right expert for their needs.</p>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4" data-aos="fade-up">
            <a href="client/how-it-works.php" class="btn btn-outline-primary btn-lg">Learn More</a>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content" data-aos="fade-up">
                <h2 class="cta-title">Ready to Connect with an Expert?</h2>
                <p class="cta-subtitle">Join thousands of satisfied clients who have found the perfect expert for their needs. Start your journey today!</p>
                <div class="d-flex flex-wrap justify-content-center gap-3">
                    <a href="client/find-experts.php" class="btn btn-white btn-lg">Find an Expert</a>
                    <?php if(!$isLoggedIn): ?>
                        <a href="page/signup.php" class="btn btn-outline-white btn-lg">Create an Account</a>
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
                        <a href="index.php">Home</a>
                        <a href="client/find-experts.php">Find Experts</a>
                        <a href="client/how-it-works.php">How It Works</a>
                        <a href="client/contact-support.php">Contact Support</a>
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
                            echo '<a href="client/find-experts.php?category=' . $row['id'] . '">' . ucfirst($row['name']) . '</a>';
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
    <!-- Swiper Slider JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@8/swiper-bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        // Initialize Swiper
        const testimonialsSwiper = new Swiper('.testimonials-swiper', {
            slidesPerView: 1,
            spaceBetween: 30,
            loop: true,
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            breakpoints: {
                640: {
                    slidesPerView: 1,
                    spaceBetween: 20,
                },
                768: {
                    slidesPerView: 2,
                    spaceBetween: 30,
                },
                1024: {
                    slidesPerView: 3,
                    spaceBetween: 30,
                },
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
            fetch('client/mark-notifications-read.php', {
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
        let previousNotificationCount = <?php echo $notificationCount; ?>; // Track previous notification count

        function fetchNotifications() {
            // Only run if user is logged in
            if (<?php echo $isLoggedIn ? 'true' : 'false'; ?>) {
                fetch('client/get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update notifications container
                        const notificationsContainer = document.getElementById('notifications-container');
                        let notificationsHTML = '';
                        
                        if (data.notifications.length > 0) {
                            data.notifications.forEach(notification => {
                                notificationsHTML += `
                                    <li>
                                        <a class="dropdown-item py-3 border-bottom" href="#">
                                            <small class="text-muted d-block">${notification.created_at}</small>
                                            <p class="mb-0 mt-1">${notification.message}</p>
                                        </a>
                                    </li>
                                `;
                            });
                            
                            // Update notification badge
                            const badge = document.getElementById('notification-badge');
                            if (badge) {
                                badge.textContent = data.count;
                                badge.style.display = data.count > 0 ? 'flex' : 'none';
                            }
                            
                            // Check if notification count has changed
                            if (data.count !== previousNotificationCount) {
                                // Refresh the booked consultations section if it exists
                                refreshBookedConsultations();
                               
                                // Update previous count
                                previousNotificationCount = data.count;
                            }
                        } else {
                            notificationsHTML = '<li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>';
                            
                            // Hide notification badge
                            const badge = document.getElementById('notification-badge');
                            if (badge) {
                                badge.style.display = 'none';
                            }
                            
                            // Reset previous count
                            previousNotificationCount = 0;
                        }
                        
                        if (notificationsContainer) {
                            notificationsContainer.innerHTML = notificationsHTML;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error refreshing notifications:', error);
                });
            }
        }

        // Function to refresh the booked consultations section
        function refreshBookedConsultations() {
            const consultationsSection = document.querySelector('.active-consultations');
            if (consultationsSection) {
                fetch('client/get-booked-consultations.php')
                .then(response => response.text())
                .then(html => {
                    consultationsSection.innerHTML = html;
                    // Re-initialize AOS for new elements
                    AOS.refresh();
                })
                .catch(error => {
                    console.error('Error refreshing consultations:', error);
                });
            }
        }
        
        // Set interval to refresh notifications every second (1000ms)
        setInterval(fetchNotifications, 1000);
    </script>
</body>
</html>
