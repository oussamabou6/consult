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

// Get search parameters
$searchCategory = isset($_GET['category']) ? $_GET['category'] : '';
$searchSubcategory = isset($_GET['subcategory']) ? $_GET['subcategory'] : '';
$searchName = isset($_GET['name']) ? $_GET['name'] : '';
$searchSkills = isset($_GET['skills']) ? $_GET['skills'] : '';

// Fetch all categories for filter dropdown
$categoriesQuery = "SELECT * FROM categories ORDER BY name ASC";
$categoriesResult = $conn->query($categoriesQuery);
$categories = [];

if ($categoriesResult && $categoriesResult->num_rows > 0) {
    while ($row = $categoriesResult->fetch_assoc()) {
        $categories[] = $row;
    }
}

// Fetch subcategories based on selected category
$subcategories = [];
if (!empty($searchCategory)) {
    $subcategoriesQuery = "SELECT * FROM subcategories WHERE category_id = '$searchCategory' ORDER BY name ASC";
    $subcategoriesResult = $conn->query($subcategoriesQuery);
    
    if ($subcategoriesResult && $subcategoriesResult->num_rows > 0) {
        while ($row = $subcategoriesResult->fetch_assoc()) {
            $subcategories[] = $row;
        }
    }
}

// Build the experts query with search filters
$expertsQuery = "SELECT u.id, u.full_name, u.status, up.profile_image, up.bio, 
                ep.id as profile_id, ep.category, ep.subcategory,ep.user_id as id_profile, 
                c.name as category_name, sc.name as subcategory_name,
                ct.name as city_name, ep.workplace_map_url,
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
                WHERE u.role = 'expert' AND ep.status = 'approved'";

// Add search filters if provided
if (!empty($searchCategory)) {
    $expertsQuery .= " AND ep.category = '$searchCategory'";
}

if (!empty($searchSubcategory)) {
    $expertsQuery .= " AND ep.subcategory = '$searchSubcategory'";
}

if (!empty($searchName)) {
    $expertsQuery .= " AND u.full_name LIKE '%$searchName%'";
}

if (!empty($searchSkills)) {
    $expertsQuery .= " AND ep.id IN (SELECT profile_id FROM skills WHERE skill_name LIKE '%$searchSkills%')";
}

$expertsQuery .= " GROUP BY u.id, u.full_name, up.profile_image, up.bio, ep.id, ep.category, ep.subcategory, 
                c.name, sc.name, ct.name, ep.workplace_map_url, bi.consultation_price, bi.consultation_minutes
                ORDER BY u.status = 'Online' DESC, avg_rating DESC, review_count DESC";

$expertsResult = $conn->query($expertsQuery);
$experts = [];

if ($expertsResult && $expertsResult->num_rows > 0) {
    while ($row = $expertsResult->fetch_assoc()) {
        // Get expert skills
        $skillsQuery = "SELECT skill_name FROM skills WHERE profile_id = " . $row['profile_id'];
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
        $ratingsQuery = "SELECT er.*, u.full_name as client_name, up.profile_image as client_image
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
                // Get expert responses to this rating
                $responseQuery = "SELECT * FROM expert_rating_responses WHERE rating_id = " . $ratingRow['id'];
                $responseResult = $conn->query($responseQuery);
                $responses = [];
                
                if ($responseResult && $responseResult->num_rows > 0) {
                    while ($responseRow = $responseResult->fetch_assoc()) {
                        $responses[] = $responseRow;
                    }
                }
                
                $ratingRow['responses'] = $responses;
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
        $experts[] = $row;
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
  

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Experts - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
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
            linear-gradient(135deg,  rgba(2, 132, 199, 0.9), rgba(124, 58, 237, 0.9)),
            url('../photo/photo-1557804506-669a67965ba0.jpg') ;
            background-size:cover;
            background-position:center;
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
        
        /* Search Filters */
        .search-filters {
            background-color: white;
            border-radius: var(--border-radius-lg);
            padding: 30px;
            box-shadow: var(--box-shadow-lg);
            margin-top: -50px;
            position: relative;
            z-index: 10;
            margin-bottom: 40px;
            border: 1px solid var(--gray-100);
        }
        
        .filter-title {
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--gray-900);
            font-size: 1.25rem;
        }
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
        
        /* Expert Cards */
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
        
        .expert-status-badge {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 5px 15px;
            border-radius: var(--border-radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 2;
            box-shadow: var(--box-shadow);
        }
        
        .expert-status-badge.online {
            background-color: var(--success-500);
            color: white;
        }
        
        .expert-status-badge.offline {
            background-color: var(--gray-500);
            color: white;
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
        
        .read-more {
            color: var(--primary-600);
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            display: inline-block;
            margin-top: -15px;
            margin-bottom: 15px;
        }
        
        .read-more:hover {
            color: var(--primary-700);
            text-decoration: underline;
        }
        
        /* Expert Profile Modal */
        .modal-xl {
            max-width: 1140px;
        }
        
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
        
        .profile-header-content {
            position: relative;
            z-index: 1;
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
        
        /* Booking Modal */
        .booking-modal-body {
            padding: 30px;
        }
        
        .booking-price-info {
            background-color: var(--gray-50);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid var(--gray-200);
        }
        
        .booking-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 5px;
        }
        
        .booking-duration {
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .booking-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--primary-600);
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--gray-200);
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
        
        /* No Results */
        .no-results {
            text-align: center;
            padding: 50px 0;
        }
        
        .no-results-icon {
            font-size: 4rem;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .no-results-text {
            font-size: 1.5rem;
            color: var(--gray-700);
            margin-bottom: 20px;
        }
        
        /* Expert Message Card */
        .expert-message-card {
            background-color: var(--primary-50);
            border-radius: var(--border-radius);
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid var(--primary-200);
        }
        
        .expert-message-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: var(--primary-700);
        }
        
        .expert-message-content {
            color: var(--gray-700);
            font-size: 0.95rem;
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
            .page-header {
                padding: 60px 0 80px;
                text-align: center;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .search-filters {
                margin-top: -60px;
                padding: 20px;
            }
            
            .filter-title {
                font-size: 1.1rem;
            }
            
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
            
            .search-filters {
                padding: 15px;
            }
            
            .filter-title {
                font-size: 1rem;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .profile-modal-body {
                padding-top: 50px;
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
                    <a class="nav-link active" href="find-experts.php">Find Experts</a>
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
                                <span class="ms-2 badge bg-success" id="user-balance">
                                    <?php echo number_format($userBalance); ?> 
                                    <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" aria-labelledby="userDropdown" style="border-radius: 12px;">
                            <li><a class="dropdown-item py-2" href="profile.php"><i class="fas fa-user me-2 text-primary"></i> Profile</a></li>
                            <li><a class="dropdown-item py-2" href="Add-Fund.php"><i class="fas fa-wallet me-2 text-primary"></i> Add Fund: <?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></a></li>
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
            <h1 class="page-title" data-aos="fade-up">Find Expert Consultants</h1>
            <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">
                Connect with top professionals across various fields and get the guidance you need, when you need it.
            </p>
        </div>
    </div>
</section>

<!-- Search Filters -->
<section class="container">
    <div class="search-filters" data-aos="fade-up">
        <h3 class="filter-title">Search Filters</h3>
        <form action="find-experts.php" method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo ($searchCategory == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="subcategory" class="form-label">Subcategory</label>
                <select class="form-select" id="subcategory" name="subcategory" <?php echo empty($subcategories) ? 'disabled' : ''; ?>>
                    <option value="">All Subcategories</option>
                    <?php foreach($subcategories as $subcategory): ?>
                        <option value="<?php echo $subcategory['id']; ?>" <?php echo ($searchSubcategory == $subcategory['id']) ? 'selected' : ''; ?>>
                            <?php echo ucfirst($subcategory['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="name" class="form-label">Expert Name</label>
                <input type="text" class="form-control" id="name" name="name" placeholder="Search by name" value="<?php echo htmlspecialchars($searchName); ?>">
            </div>
            <div class="col-md-3">
                <label for="skills" class="form-label">Skills</label>
                <input type="text" class="form-control" id="skills" name="skills" placeholder="Search by skills" value="<?php echo htmlspecialchars($searchSkills); ?>">
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search me-2"></i> Search
                </button>
                <a href="find-experts.php" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-redo me-2"></i> Reset
                </a>
            </div>
        </form>
    </div>
</section>

<!-- User's Booked Consultations -->
<?php if($isLoggedIn): ?>
<section class="container mb-5" id="booked-consultations-section">
    <div class="card shadow-sm border-0" data-aos="fade-up">
        <div class="card-header bg-white py-3">
            <h3 class="card-title mb-0 fw-bold">
                <i class="fas fa-calendar-check text-primary me-2"></i> Your Booked Consultations
            </h3>
        </div>
        <div class="card-body">
            <?php
            // Fetch user's active consultations
            $consultationsQuery = "SELECT c.*, 
                      u.full_name as expert_name, 
                      u.status as expert_status,
                      up.profile_image as expert_image,
                      c.created_at as booking_date,
                      ep.category, ep.subcategory,
                      c.expert_message as expert_message,
                      p.amount as price
                      FROM consultations c
                      JOIN payments p ON c.id = p.consultation_id
                      JOIN users u ON c.expert_id = u.id
                      JOIN user_profiles up ON u.id = up.user_id
                      LEFT JOIN expert_profiledetails ep ON u.id = ep.user_id
                      WHERE c.client_id = $userId 
                      AND c.status IN ('pending', 'confirmed')
                      ORDER BY c.created_at DESC
                      LIMIT 5";
            $consultationsResult = $conn->query($consultationsQuery);
            
            if ($consultationsResult && $consultationsResult->num_rows > 0):
            ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Expert</th>
                                <th>Category</th>
                                <th>Subcategory</th>
                                <th>Date</th>
                                <th>Duration&Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($consultation = $consultationsResult->fetch_assoc()): 
                                // Format date
                                $bookingDate = new DateTime($consultation['booking_date']);
                                $formattedDate = $bookingDate->format('M d, Y - H:i');
                                
                                // Get expert image
                                $expertImage = !empty($consultation['expert_image']) ? $consultation['expert_image'] : '';
                            ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?php echo $expertImage; ?>" alt="<?php echo $consultation['expert_name']; ?>" class="rounded-circle me-2" width="40" height="40">
                                            <div>
                                                <div class="fw-semibold"><?php echo $consultation['expert_name']; ?></div>
                                                <span class="badge <?php echo ($consultation['expert_status'] == 'Online') ? 'bg-success' : 'bg-secondary'; ?> rounded-pill">
                                                    <?php echo $consultation['expert_status']; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        // Get category name
                                        $catQuery = "SELECT c.name FROM categories c 
                                                    JOIN expert_profiledetails ep ON c.id = ep.category 
                                                    WHERE ep.user_id = " . $consultation['expert_id'];
                                        $catResult = $conn->query($catQuery);
                                        $categoryName = ($catResult && $catResult->num_rows > 0) ? $catResult->fetch_assoc()['name'] : 'N/A';
                                        echo htmlspecialchars(ucfirst($categoryName)); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        // Get subcategory name
                                        $subcatQuery = "SELECT sc.name FROM subcategories sc 
                                                       JOIN expert_profiledetails ep ON sc.id = ep.subcategory 
                                                       WHERE ep.user_id = " . $consultation['expert_id'];
                                        $subcatResult = $conn->query($subcatQuery);
                                        $subcategoryName = ($subcatResult && $subcatResult->num_rows > 0) ? $subcatResult->fetch_assoc()['name'] : 'N/A';
                                        echo htmlspecialchars(ucfirst($subcategoryName)); 
                                        ?>
                                    </td>
                                    <td><?php echo $formattedDate; ?></td>
                                    <td>
                                        <?php echo $consultation['duration']; ?> min
                                        <br>
                                        <strong><?php echo number_format($consultation['price'], 2); ?> <?php echo $settings['currency']; ?></strong>
                                    </td>
                                    <td>
                                        <?php if($consultation['status'] == 'pending'): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif($consultation['status'] == 'confirmed'): ?>
                                            <span class="badge bg-success">Confirmed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($consultation['status'] == 'pending'): ?>
                                            <a href="cancel-consultation.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this consultation?');">
                                                <i class="fas fa-times me-1"></i> Cancel
                                            </a>
                                        <?php elseif($consultation['status'] == 'confirmed'): ?>
                                            <a href="consultation-chat.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-comments me-1"></i> Go to Chat
                                            </a>
                                        <?php else: ?>
                                            <a href="my-consultations.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-eye me-1"></i> Details
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if(!empty($consultation['expert_message'])): ?>
                                <tr>
                                    <td colspan="7">
                                        <div class="expert-message-card">
                                            <h5 class="expert-message-title">Message from Expert</h5>
                                            <div class="expert-message-content">
                                                <?php echo htmlspecialchars($consultation['expert_message']); ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if($consultationsResult->num_rows > 4): ?>
                    <div class="text-center mt-3">
                        <a href="my-consultations.php" class="btn btn-link text-primary">
                            View All Consultations <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <h5>No Active Consultations</h5>
                    <p class="text-muted">You don't have any active consultations at the moment.</p>
                    <p class="mb-0">Browse experts below and book a consultation.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Experts List -->
<section class="container">
    <div class="row">
        <?php if(count($experts) > 0): ?>
            <?php foreach($experts as $expert): 
                // Get expert image
                $expertImage = !empty($expert['profile_image']) ? $expert['profile_image'] : '../assets/images/default-expert.jpg';
            ?>
                <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo 100 * array_search($expert, $experts) % 5; ?>">
                    <div class="expert-card" data-expert-id="<?php echo $expert['id']; ?>">
                        <?php if($expert['avg_rating'] >= 4.5): ?>
                            <div class="expert-badge">Top Rated</div>
                        <?php endif; ?>
                        
                        <?php if($expert['status'] == 'Online'): ?>
                            <div class="expert-status-badge online">
                                <i class="fas fa-circle me-1"></i> Online
                            </div>
                        <?php else: ?>
                            <div class="expert-status-badge offline">
                                <i class="fas fa-circle me-1"></i> Offline
                            </div>
                        <?php endif; ?>
                        
                        <div class="expert-img-container">
                            <?php if(!empty($expert['profile_image'])): ?>
                                <img src="<?php echo $expert['profile_image']; ?>" alt="<?php echo $expert['full_name']; ?>" class="expert-img">
                            <?php else: ?>
                                <img src="../assets/images/default-expert.jpg" alt="<?php echo $expert['full_name']; ?>" class="expert-img">
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
                            
                            <?php if(!empty($expert['bio'])): ?>
                            <div class="expert-bio" id="bio-<?php echo $expert['id']; ?>">
                                <?php echo $expert['bio']; ?>
                            </div>
                            <?php if(strlen($expert['bio']) > 150): ?>
                            <span class="read-more" onclick="toggleBio(<?php echo $expert['id']; ?>)" id="read-more-<?php echo $expert['id']; ?>">Read more</span>
                            <?php endif; ?>
                            <?php endif; ?>
                            
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#expertProfileModal<?php echo $expert['id']; ?>">
                                    View Profile
                                </button>
                                <button type="button" class="btn btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $expert['id']; ?>" <?php echo ($expert['status'] != 'Online') ? 'disabled' : ''; ?>>
                                    Book Consultation
                                </button>
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
                                    <img src="<?php echo $expertImage; ?>" alt="<?php echo $expert['full_name']; ?>" class="profile-avatar">
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
                                                                <img src="<?php echo $rating['client_image']; ?>" alt="<?php echo $rating['client_name']; ?>">
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
                                                                <img src="<?php echo $expertImage; ?>" alt="<?php echo $expert['full_name']; ?>" class="rounded-circle" width="30" height="30">
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
                                                
                                                <button type="button" class="btn btn-primary w-100 mt-3" data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $expert['id']; ?>" <?php echo ($expert['status'] != 'Online') ? 'disabled' : ''; ?>>
                                                    <?php if($expert['status'] == 'Online'): ?>
                                                        Book Consultation
                                                    <?php else: ?>
                                                        Expert Offline
                                                    <?php endif; ?>
                                                </button>
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
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $expert['id']; ?>" <?php echo ($expert['status'] != 'Online') ? 'disabled' : ''; ?>>
                                    <?php if($expert['status'] == 'Online'): ?>
                                        Book Consultation
                                    <?php else: ?>
                                        Expert Offline
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Booking Modal -->
                <div class="modal fade" id="bookingModal<?php echo $expert['id']; ?>" tabindex="-1" aria-labelledby="bookingModalLabel<?php echo $expert['id']; ?>" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="bookingModalLabel<?php echo $expert['id']; ?>">Book Consultation with <?php echo $expert['full_name']; ?></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body booking-modal-body">
                                <?php if($isLoggedIn): ?>
                                    <div class="booking-price-info">
                                        <div class="booking-price">
                                            <?php echo number_format($expert['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>
                                            <span class="booking-duration">/ <?php echo $expert['consultation_minutes']; ?> min</span>
                                        </div>
                                        <p class="text-muted small mb-0">Base consultation fee</p>
                                    </div>
                                    
                                    
                                    <form id="bookingForm<?php echo $expert['id']; ?>" onsubmit="submitBookingForm(event, <?php echo $expert['id']; ?>)">
                                        <input type="hidden" name="expert_id" value="<?php echo $expert['id']; ?>">
                                        <input type="hidden" name="profile_id" value="<?php echo $expert['profile_id']; ?>">
                                        <input type="hidden" name="base_price" value="<?php echo $expert['consultation_price']; ?>">
                                        <input type="hidden" name="base_duration" value="<?php echo $expert['consultation_minutes']; ?>">
                                        
                                        <div class="mb-3">
                                            <label for="message<?php echo $expert['id']; ?>" class="form-label">Message to Expert <span class="text-danger">*</span></label>
                                            <textarea class="form-control" id="message<?php echo $expert['id']; ?>" name="message" rows="4" placeholder="Describe what you need help with..." required></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="duration<?php echo $expert['id']; ?>" class="form-label">Consultation Duration (minutes) <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="number" class="form-control" id="duration<?php echo $expert['id']; ?>" name="duration" min="<?php echo $expert['consultation_minutes']; ?>" value="<?php echo $expert['consultation_minutes']; ?>" onchange="calculateTotal(<?php echo $expert['id']; ?>, <?php echo $expert['consultation_price']; ?>, <?php echo $expert['consultation_minutes']; ?>, <?php echo $userBalance; ?>)" required>
                                                <span class="input-group-text">minutes</span>
                                            </div>
                                            <div class="form-text">Minimum duration: <?php echo $expert['consultation_minutes']; ?> minutes</div>
                                        </div>
                                        
                                        <div class="booking-price-info mt-4">
                                            <div class="booking-total">
                                                Total: <span id="totalPrice<?php echo $expert['id']; ?>"><?php echo number_format($expert['consultation_price']); ?></span> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>
                                            </div>
                                            <p class="text-muted small mb-0">Your current balance: <?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></p>
                                            <div id="balanceWarning<?php echo $expert['id']; ?>" class="text-danger small mt-2" style="display: none;">
                                                Your balance is insufficient for this consultation duration.
                                            </div>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-user-lock fa-3x text-muted mb-3"></i>
                                        <h5>Please login to book a consultation</h5>
                                        <p class="text-muted">You need to be logged in to book a consultation with our experts.</p>
                                        <a href="../pages/login.php" class="btn btn-primary mt-2">Login Now</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <?php if($isLoggedIn): ?>
                                    <button type="submit" form="bookingForm<?php echo $expert['id']; ?>" class="btn btn-primary" id="submitBooking<?php echo $expert['id']; ?>">
                                        Book Now
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Consultation Status Modal -->
                <div class="modal fade" id="consultationStatusModal" tabindex="-1" aria-labelledby="consultationStatusModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="consultationStatusModalLabel">Consultation Request Status</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div id="consultationStatusContent">
                                    <div class="text-center py-3">
                                        <div class="spinner-border text-primary mb-3" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <p class="mb-0">Processing your consultation request...</p>
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
            <div class="col-12 no-results" data-aos="fade-up">
                <div class="no-results-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3 class="no-results-text">No experts found matching your criteria</h3>
                <p class="text-muted mb-4">Try adjusting your search filters or browse all experts</p>
                <a href="find-experts.php" class="btn btn-primary">View All Experts</a>
            </div>
        <?php endif; ?>
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
    
    // Toggle bio read more/less
    function toggleBio(expertId) {
        const bioElement = document.getElementById('bio-' + expertId);
        const readMoreButton = document.getElementById('read-more-' + expertId);
        
        if (bioElement.style.webkitLineClamp === 'unset') {
            bioElement.style.webkitLineClamp = '3';
            readMoreButton.textContent = 'Read more';
        } else {
            bioElement.style.webkitLineClamp = 'unset';
            readMoreButton.textContent = 'Read less';
        }
    }
    
    // Calculate total price based on duration
    function calculateTotal(expertId, basePrice, baseDuration, userBalance) {
        const durationInput = document.getElementById('duration' + expertId);
        const totalPriceElement = document.getElementById('totalPrice' + expertId);
        const balanceWarningElement = document.getElementById('balanceWarning' + expertId);
        const submitButton = document.getElementById('submitBooking' + expertId);
        
        const duration = parseInt(durationInput.value);
        const pricePerMinute = basePrice / baseDuration;
        const totalPrice = Math.round(pricePerMinute * duration);
        
        totalPriceElement.textContent = totalPrice.toLocaleString();
        
        // Check if user has enough balance
        if (totalPrice > userBalance) {
            balanceWarningElement.style.display = 'block';
            submitButton.disabled = true;
        } else {
            balanceWarningElement.style.display = 'none';
            submitButton.disabled = false;
        }
    }
    
    // Submit booking form
    function submitBookingForm(event, expertId) {
        event.preventDefault();
        
        const form = document.getElementById('bookingForm' + expertId);
        const formData = new FormData(form);
        
        // Show status modal
        const statusModal = new bootstrap.Modal(document.getElementById('consultationStatusModal'));
        statusModal.show();
        
        // Send AJAX request
        fetch('process-booking.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const statusContent = document.getElementById('consultationStatusContent');
            
            if (data.success) {
                statusContent.innerHTML = `
                    <div class="text-center py-3">
                        <div class="mb-3 text-success">
                            <i class="fas fa-check-circle fa-4x"></i>
                        </div>
                        <h4 class="mb-3">Consultation Request Sent!</h4>
                        <p>${data.message}</p>
                        <div class="mt-4">
                            <a href="find-experts.php" class="btn btn-primary">View My Request</a>
                        </div>
                    </div>
                `;
                
                // Close the booking modal
                const bookingModal = bootstrap.Modal.getInstance(document.getElementById('bookingModal' + expertId));
                bookingModal.hide();
                
                // Refresh the booked consultations section if it exists
                if (document.getElementById('booked-consultations-section')) {
                    setTimeout(function() {
                        location.reload();
                    }, 3000);
                }
            } else {
                statusContent.innerHTML = `
                    <div class="text-center py-3">
                        <div class="mb-3 text-danger">
                            <i class="fas fa-times-circle fa-4x"></i>
                        </div>
                        <h4 class="mb-3">Error</h4>
                        <p>${data.message}</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const statusContent = document.getElementById('consultationStatusContent');
            statusContent.innerHTML = `
                <div class="text-center py-3">
                    <div class="mb-3 text-danger">
                        <i class="fas fa-exclamation-triangle fa-4x"></i>
                    </div>
                    <h4 class="mb-3">Something went wrong</h4>
                    <p>There was an error processing your request. Please try again later.</p>
                </div>
            `;
        });
    }
    
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
   
    // Initialize subcategory dropdown based on selected category
    document.addEventListener('DOMContentLoaded', function() {
        const categorySelect = document.getElementById('category');
        const subcategorySelect = document.getElementById('subcategory');
        
        if (categorySelect && subcategorySelect) {
            categorySelect.addEventListener('change', function() {
                const categoryId = this.value;
                
                if (categoryId) {
                    // Enable subcategory dropdown
                    subcategorySelect.disabled = false;
                    
                    // Fetch subcategories for selected category
                    fetch(`get-subcategories.php?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        // Clear current options
                        subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
                        
                        // Add new options
                        if (data.success && data.subcategories.length > 0) {
                            data.subcategories.forEach(subcategory => {
                                const option = document.createElement('option');
                                option.value = subcategory.id;
                                option.textContent = subcategory.name;
                                subcategorySelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
                } else {
                    // Disable and clear subcategory dropdown
                    subcategorySelect.disabled = true;
                    subcategorySelect.innerHTML = '<option value="">All Subcategories</option>';
                }
            });
        }
    });

let previousNotificationCount = 0;

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

                    if (data.count !== previousNotificationCount) {
                        // Refresh the booked consultations section if it exists
                        refreshBookedConsultations();
                        // Update previous count
                        previousNotificationCount = data.count;
                    }
        })
        .catch(error => {
            console.error('Error fetching notifications:', error);
        });
}

// Function to refresh the booked consultations section
function refreshBookedConsultations() {
    const consultationsSection = document.getElementById('booked-consultations-section');

    if (consultationsSection) {
        fetch('get-booked-consultations.php')
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


// Fetch notifications every second
setInterval(fetchNotifications, 1000);

    // Function to refresh only the expert status (Online/Offline)
    function refreshExpertStatus() {
        fetch('get-expert-status.php')
        .then(response => response.json())
        .then(data => {
            if (data.experts) {
                // Update each expert's status badge
                data.experts.forEach(expert => {
                    const statusBadges = document.querySelectorAll(`.expert-card[data-expert-id="${expert.id}"] .expert-status-badge`);
                    const profileStatusBadges = document.querySelectorAll(`#expertProfileModal${expert.id} .badge`);
                    const bookButtons = document.querySelectorAll(`button[data-bs-target="#bookingModal${expert.id}"]`);
                    
                    if (statusBadges.length > 0) {
                        statusBadges.forEach(badge => {
                            if (expert.status === 'Online') {
                                badge.className = 'expert-status-badge online';
                                badge.innerHTML = '<i class="fas fa-circle me-1"></i> Online';
                            } else {
                                badge.className = 'expert-status-badge offline';
                                badge.innerHTML = '<i class="fas fa-circle me-1"></i> Offline';
                            }
                        });
                    }
                    
                    if (profileStatusBadges.length > 0) {
                        profileStatusBadges.forEach(badge => {
                            if (expert.status === 'Online') {
                                badge.className = 'badge bg-success ms-2';
                            } else {
                                badge.className = 'badge bg-secondary ms-2';
                            }
                            badge.textContent = expert.status;
                        });
                    }
                    
                    if (bookButtons.length > 0) {
                        bookButtons.forEach(button => {
                            if (expert.status === 'Online') {
                                button.disabled = false;
                                if (button.textContent.trim() === 'Expert Offline') {
                                    button.textContent = 'Book Consultation';
                                }
                            } else {
                                button.disabled = true;
                                if (button.textContent.trim() === 'Book Consultation') {
                                    button.textContent = 'Expert Offline';
                                }
                            }
                        });
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error refreshing expert status:', error);
        });
    }
    
    // Set interval to refresh expert status every 10 seconds
    setInterval(refreshExpertStatus, 5000);

    


function refreshUserBalance() {
    // Sélectionner l'élément contenant le solde
    const balanceElement = document.querySelector('.ms-2.badge.bg-success');
    
    if (balanceElement) {
        // Créer une requête AJAX pour récupérer le nouveau solde
        const xhr = new XMLHttpRequest();
        xhr.open('GET', window.location.href, true);
        xhr.onload = function() {
            if (this.status === 200) {
                // Créer un DOM temporaire pour parser la réponse
                const parser = new DOMParser();
                const htmlDoc = parser.parseFromString(this.responseText, 'text/html');
                // Trouver le nouvel élément de solde dans la réponse
                const newBalanceElement = htmlDoc.querySelector('.ms-2.badge.bg-success');
                
                if (newBalanceElement) {
                    // Mettre à jour seulement si le solde a changé
                    if (balanceElement.innerHTML !== newBalanceElement.innerHTML) {
                        balanceElement.innerHTML = newBalanceElement.innerHTML;
                    }
                }
            }
        };
        xhr.send();
    }
}

// Lancer la fonction toutes les secondes (1000ms)
setInterval(refreshUserBalance, 1000);
</script>
</body>
</html>