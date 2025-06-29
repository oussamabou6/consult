<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");
// Include mailer utility
require "utils/mailer.php";

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email']) || $_SESSION['user_role'] != 'client') {
    header("Location: ../config/logout.php");
    exit;
}
$userId = $_SESSION['user_id'];

// Get user information
$userQuery = "SELECT u.*,
                (SELECT COUNT(*) FROM reports WHERE reported_id = u.id AND status IN ('remborser','accepted')) AS reports_count,
                (SELECT COUNT(*) FROM user_suspensions WHERE user_id = u.id) AS suspension_count 
                FROM users u WHERE u.id = $userId";
$userResult = $conn->query($userQuery);
$user = $userResult->fetch_assoc();

// Get user profile information
$profileQuery = "SELECT * FROM user_profiles WHERE user_id = $userId";
$profileResult = $conn->query($profileQuery);
$profile = $profileResult->fetch_assoc();

// Get user role
$userRole = $user['role'];

// Get site settings
$settingsQuery = "SELECT * FROM settings";
$settingsResult = $conn->query($settingsQuery);
$settings = [];

if ($settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Handle form submissions
$successMessage = '';
$errorMessage = '';

// Handle basic profile update
if (isset($_POST['update_basic_profile'])) {
    $fullName = $conn->real_escape_string($_POST['full_name']);
    $phone = $conn->real_escape_string($_POST['phone']);
    $address = $conn->real_escape_string($_POST['address']);
    $bio = $conn->real_escape_string($_POST['bio']);
    
    // Check if phone number already exists for another user
    if (!empty($phone)) {
        $checkPhoneQuery = "SELECT user_id FROM user_profiles WHERE phone = '$phone' AND user_id != $userId";
        $checkPhoneResult = $conn->query($checkPhoneQuery);
        
        if ($checkPhoneResult->num_rows > 0) {
            $errorMessage = "This phone number is already registered with another account.";
        } else {
            // Update users table
            $updateUserQuery = "UPDATE users SET full_name = '$fullName' WHERE id = $userId";
            $conn->query($updateUserQuery);
            
            // Update user_profiles table
            $updateProfileQuery = "UPDATE user_profiles SET 
                                phone = '$phone', 
                                address = '$address', 
                                bio = '$bio' 
                                WHERE user_id = $userId";
            
            if ($conn->query($updateProfileQuery)) {
                $successMessage = "Profile information updated successfully!";
                
                // Refresh profile data
                $profileResult = $conn->query($profileQuery);
                $profile = $profileResult->fetch_assoc();
                
                $userResult = $conn->query($userQuery);
                $user = $userResult->fetch_assoc();
            } else {
                $errorMessage = "Error updating profile: " . $conn->error;
            }
        }
    } else {
        // Update users table
        $updateUserQuery = "UPDATE users SET full_name = '$fullName' WHERE id = $userId";
        $conn->query($updateUserQuery);
        
        // Update user_profiles table
        $updateProfileQuery = "UPDATE user_profiles SET 
                            address = '$address', 
                            bio = '$bio' 
                            WHERE user_id = $userId";
        
        if ($conn->query($updateProfileQuery)) {
            $successMessage = "Profile information updated successfully!";
            
            // Refresh profile data
            $profileResult = $conn->query($profileQuery);
            $profile = $profileResult->fetch_assoc();
            
            $userResult = $conn->query($userQuery);
            $user = $userResult->fetch_assoc();
        } else {
            $errorMessage = "Error updating profile: " . $conn->error;
        }
    }
}

// Handle email update
if (isset($_POST['update_email'])) {
    $newEmail = $conn->real_escape_string($_POST['new_email']);
    $currentPasswordEmail = $_POST['current_password_email'];
    
    // Verify current password
    if (password_verify($currentPasswordEmail, $user['password'])) {
        // Check if email already exists
        $checkEmailQuery = "SELECT id FROM users WHERE email = '$newEmail' AND id != $userId";
        $checkEmailResult = $conn->query($checkEmailQuery);
        
        if ($checkEmailResult->num_rows > 0) {
            $errorMessage = "This email address is already registered with another account.";
        } else {
            // Generate verification code
            $verificationCode = sprintf("%06d", mt_rand(100000, 999999));
            $codeExpiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store verification code in database
            $updateCodeQuery = "UPDATE users SET verification_code = '$verificationCode', code_expires_at = '$codeExpiresAt' WHERE id = $userId";
            
            if ($conn->query($updateCodeQuery)) {
                // Store new email in session for verification
                $_SESSION['new_email'] = $newEmail;
                
                // Get site name for email branding
                $siteName = isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro';
                
                // Send verification email
                $emailSent = sendVerificationEmail($newEmail, $user['full_name'], $verificationCode, $siteName);
                
                if ($emailSent) {
                    // Redirect to verification page
                    header("Location: verify-email.php");
                    exit;
                } else {
                    $errorMessage = "Failed to send verification email. Please try again.";
                }
            } else {
                $errorMessage = "Error generating verification code: " . $conn->error;
            }
        }
    } else {
        $errorMessage = "Current password is incorrect.";
    }
}

// Handle password update
if (isset($_POST['update_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($currentPassword, $user['password'])) {
        // Check if new passwords match
        if ($newPassword === $confirmPassword) {
            // Hash new password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            // Update password
            $updatePasswordQuery = "UPDATE users SET password = '$hashedPassword' WHERE id = $userId";
            
            if ($conn->query($updatePasswordQuery)) {
                $successMessage = "Password updated successfully!";
            } else {
                $errorMessage = "Error updating password: " . $conn->error;
            }
        } else {
            $errorMessage = "New passwords do not match!";
        }
    } else {
        $errorMessage = "Current password is incorrect!";
    }
}

// Handle profile image upload
if (isset($_POST['update_profile_image'])) {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $fileType = $_FILES['profile_image']['type'];
        
        if (in_array($fileType, $allowedTypes)) {
            $fileName = 'profile_' . $userId . '_' . time() . '.' . pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $uploadDir = '../uploads/profiles/';
            
            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath)) {
                // Update profile image path in database
                $imagePath = $uploadPath;
                $updateImageQuery = "UPDATE user_profiles SET profile_image = '$imagePath' WHERE user_id = $userId";
                
                if ($conn->query($updateImageQuery)) {
                    $successMessage = "Profile image updated successfully!";
                    
                    // Refresh profile data
                    $profileResult = $conn->query($profileQuery);
                    $profile = $profileResult->fetch_assoc();
                } else {
                    $errorMessage = "Error updating profile image in database: " . $conn->error;
                }
            } else {
                $errorMessage = "Error uploading image file!";
            }
        } else {
            $errorMessage = "Invalid file type. Please upload a JPEG, PNG, or GIF image.";
        }
    } else {
        $errorMessage = "Error uploading file: " . $_FILES['profile_image']['error'];
    }
}

// For experts only - Handle social links update
if ($userRole === 'expert' && isset($_POST['update_social_links'])) {
    // Get expert profile ID
    $expertProfileQuery = "SELECT id FROM expert_profiledetails WHERE user_id = $userId";
    $expertProfileResult = $conn->query($expertProfileQuery);
    
    if ($expertProfileResult && $expertProfileResult->num_rows > 0) {
        $expertProfile = $expertProfileResult->fetch_assoc();
        $profileId = $expertProfile['id'];
        
        $facebookUrl = $conn->real_escape_string($_POST['facebook_url']);
        $instagramUrl = $conn->real_escape_string($_POST['instagram_url']);
        $linkedinUrl = $conn->real_escape_string($_POST['linkedin_url']);
        $githubUrl = $conn->real_escape_string($_POST['github_url']);
        $twitterUrl = $conn->real_escape_string($_POST['twitter_url']);
        $websiteUrl = $conn->real_escape_string($_POST['website_url']);
        
        // Check if social links already exist
        $checkSocialQuery = "SELECT id FROM expert_social_links WHERE user_id = $userId";
        $checkSocialResult = $conn->query($checkSocialQuery);
        
        if ($checkSocialResult && $checkSocialResult->num_rows > 0) {
            // Update existing social links
            $updateSocialQuery = "UPDATE expert_social_links SET 
                                facebook_url = '$facebookUrl',
                                instagram_url = '$instagramUrl',
                                linkedin_url = '$linkedinUrl',
                                github_url = '$githubUrl',
                                twitter_url = '$twitterUrl',
                                website_url = '$websiteUrl'
                                WHERE user_id = $userId";
        } else {
            // Insert new social links
            $updateSocialQuery = "INSERT INTO expert_social_links 
                                (user_id, profile_id, facebook_url, instagram_url, linkedin_url, github_url, twitter_url, website_url)
                                VALUES 
                                ($userId, $profileId, '$facebookUrl', '$instagramUrl', '$linkedinUrl', '$githubUrl', '$twitterUrl', '$websiteUrl')";
        }
        
        if ($conn->query($updateSocialQuery)) {
            $successMessage = "Social links updated successfully!";
        } else {
            $errorMessage = "Error updating social links: " . $conn->error;
        }
    }
}

// Get expert-specific information if user is an expert
$expertProfile = null;
$expertSocialLinks = null;
$expertSkills = [];
$certificates = [];
$experiences = [];
$formations = [];
$bankingInfo = null;

if ($userRole === 'expert') {
    // Get expert profile details
    $expertProfileQuery = "SELECT * FROM expert_profiledetails WHERE user_id = $userId";
    $expertProfileResult = $conn->query($expertProfileQuery);
    
    if ($expertProfileResult && $expertProfileResult->num_rows > 0) {
        $expertProfile = $expertProfileResult->fetch_assoc();
        $profileId = $expertProfile['id'];
        
        // Get expert social links
        $socialLinksQuery = "SELECT * FROM expert_social_links WHERE user_id = $userId";
        $socialLinksResult = $conn->query($socialLinksQuery);
        
        if ($socialLinksResult && $socialLinksResult->num_rows > 0) {
            $expertSocialLinks = $socialLinksResult->fetch_assoc();
        }
        
        // Get expert skills
        $skillsQuery = "SELECT * FROM skills WHERE profile_id = $profileId";
        $skillsResult = $conn->query($skillsQuery);
        
        if ($skillsResult && $skillsResult->num_rows > 0) {
            while ($skill = $skillsResult->fetch_assoc()) {
                $expertSkills[] = $skill;
            }
        }
        
        // Get certificates
        $certificatesQuery = "SELECT * FROM certificates WHERE profile_id = $profileId";
        $certificatesResult = $conn->query($certificatesQuery);
        
        if ($certificatesResult && $certificatesResult->num_rows > 0) {
            while ($certificate = $certificatesResult->fetch_assoc()) {
                $certificates[] = $certificate;
            }
        }
        
        // Get experiences
        $experiencesQuery = "SELECT * FROM experiences WHERE profile_id = $profileId";
        $experiencesResult = $conn->query($experiencesQuery);
        
        if ($experiencesResult && $experiencesResult->num_rows > 0) {
            while ($experience = $experiencesResult->fetch_assoc()) {
                $experiences[] = $experience;
            }
        }
        
        // Get formations
        $formationsQuery = "SELECT * FROM formations WHERE profile_id = $profileId";
        $formationsResult = $conn->query($formationsQuery);
        
        if ($formationsResult && $formationsResult->num_rows > 0) {
            while ($formation = $formationsResult->fetch_assoc()) {
                $formations[] = $formation;
            }
        }
        
        // Get banking information
        $bankingQuery = "SELECT * FROM banking_information WHERE user_id = $userId";
        $bankingResult = $conn->query($bankingQuery);
        
        if ($bankingResult && $bankingResult->num_rows > 0) {
            $bankingInfo = $bankingResult->fetch_assoc();
        }
    }
    
    // Get categories and subcategories for dropdowns
    $categoriesQuery = "SELECT * FROM categories ORDER BY name ASC";
    $categoriesResult = $conn->query($categoriesQuery);
    $categories = [];
    
    if ($categoriesResult && $categoriesResult->num_rows > 0) {
        while ($category = $categoriesResult->fetch_assoc()) {
            $categories[] = $category;
        }
    }
    
    $subcategoriesQuery = "SELECT * FROM subcategories ORDER BY name ASC";
    $subcategoriesResult = $conn->query($subcategoriesQuery);
    $subcategories = [];
    
    if ($subcategoriesResult && $subcategoriesResult->num_rows > 0) {
        while ($subcategory = $subcategoriesResult->fetch_assoc()) {
            $subcategories[] = $subcategory;
        }
    }
    
    // Get cities for dropdown
    $citiesQuery = "SELECT * FROM cities ORDER BY name ASC";
    $citiesResult = $conn->query($citiesQuery);
    $cities = [];
    
    if ($citiesResult && $citiesResult->num_rows > 0) {
        while ($city = $citiesResult->fetch_assoc()) {
            $cities[] = $city;
        }
    }
}

// Get user balance
$balanceQuery = "SELECT balance FROM users WHERE id = $userId";
$balanceResult = $conn->query($balanceQuery);
$userBalance = 0;

if ($balanceResult && $balanceResult->num_rows > 0) {
    $userBalance = $balanceResult->fetch_assoc()['balance'];
}

// Get consultation statistics
$totalConsultations = 0;
$completedConsultations = 0;
$pendingConsultations = 0;

if ($userRole === 'client') {
    $consultationsQuery = "SELECT COUNT(*) as total FROM consultations WHERE client_id = $userId";
    $completedQuery = "SELECT COUNT(*) as completed FROM consultations WHERE client_id = $userId AND status = 'completed'";
    $pendingQuery = "SELECT COUNT(*) as pending FROM consultations WHERE client_id = $userId AND (status = 'pending' OR status = 'confirmed')";
} else {
    $consultationsQuery = "SELECT COUNT(*) as total FROM consultations WHERE expert_id = $userId";
    $completedQuery = "SELECT COUNT(*) as completed FROM consultations WHERE expert_id = $userId AND status = 'completed'";
    $pendingQuery = "SELECT COUNT(*) as pending FROM consultations WHERE expert_id = $userId AND (status = 'pending' OR status = 'confirmed')";
}

$consultationsResult = $conn->query($consultationsQuery);
$completedResult = $conn->query($completedQuery);
$pendingResult = $conn->query($pendingQuery);

if ($consultationsResult && $consultationsResult->num_rows > 0) {
    $totalConsultations = $consultationsResult->fetch_assoc()['total'];
}

if ($completedResult && $completedResult->num_rows > 0) {
    $completedConsultations = $completedResult->fetch_assoc()['completed'];
}

if ($pendingResult && $pendingResult->num_rows > 0) {
    $pendingConsultations = $pendingResult->fetch_assoc()['pending'];
}


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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
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
                   --primary-gradient: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            --radius-full: 9999px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);

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
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.9), rgba(124, 58, 237, 0.9)), url('https://images.unsplash.com/photo-1557804506-669a67965ba0');
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
        
        /* Profile Section */
        .profile-container {
            margin-top: -50px;
            margin-bottom: 50px;
            position: relative;
            z-index: 10;
        }
        
        .profile-header {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-xl);
            padding: 30px;
            margin-bottom: 30px;
            position: relative;
            border: 1px solid var(--gray-100);
            transform: translateY(0);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .profile-header:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-2xl);
        }
        
        .profile-avatar-container {
            position: relative;
            margin-bottom: 20px;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            box-shadow: var(--box-shadow-lg);
            background-color: var(--gray-200);
        }
        
        .profile-avatar-upload {
            position: absolute;
            bottom: 0;
            right: 0;
            background-color: var(--primary-600);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--box-shadow);
        }
        
        .profile-avatar-upload:hover {
            background-color: var(--primary-700);
            transform: scale(1.1);
        }
        
        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .profile-role {
            display: inline-block;
            padding: 5px 15px;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            border-radius: var(--border-radius-full);
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .profile-status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: var(--border-radius-full);
            font-size: 0.9rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .status-online {
            background-color: var(--success-100);
            color: var(--success-800);
        }
        
        .status-offline {
            background-color: var(--gray-100);
            color: var(--gray-800);
        }
        
        .profile-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-item {
            flex: 1;
            min-width: 120px;
            padding: 15px;
            background-color: var(--gray-50);
            border-radius: var(--border-radius);
            text-align: center;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }
        
        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow);
            background-color: white;
        }
        
        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-600);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        /* Tabs */
        .profile-tabs {
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
        
        /* Content Cards */
        .content-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow);
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--gray-100);
            transition: var(--transition);
        }
        
        .content-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .card-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-100);
            color: var(--gray-900);
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--gray-700);
        }
        
        .form-control {
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 12px 20px;
            font-size: 1rem;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--primary-600);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        
        .form-select {
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            padding: 12px 20px;
            font-size: 1rem;
            transition: var(--transition);
            background-position: right 20px center;
        }
        
        .form-select:focus {
            border-color: var(--primary-600);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }
        
        .form-text {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        /* Buttons */
        .btn {
            padding: 12px 24px;
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
        
        /* Credentials Section */
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
        
        /* Skills */
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .skill-badge {
            background-color: var(--primary-100);
            color: var(--primary-800);
            padding: 5px 15px;
            border-radius: var(--border-radius-full);
            font-size: 0.875rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
 .status-approved {
            background: var(--success-gradient);
            color: white;
        }
        
        .status-rejected {
            background: var(--danger-gradient);
            color: white;
        }
        
        .status-info {
            background: var(--info-gradient);
            color: white;
        }

        .status-reports-low {
            background: var(--warning-gradient);
            color: white;
        }
        .status-reports-suspended {
            background: var(--danger-gradient);
            color: white;
        }

        .status-reports-medium {
            background: linear-gradient(135deg, var(--warning-color) 0%, var(--danger-color) 100%);
            color: white;
        }

        .status-reports-high {
            background: var(--danger-gradient);
            color: white;
            animation: pulse 1.5s infinite;
            border: 1px solid var(--danger-light);
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.5);
        }
  /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            margin-bottom: 10px;
            align-items: center;
            padding: 0.375rem 1.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            gap: 0.375rem;
            box-shadow: var(--shadow-sm);
        }
        
        /* Social Links */
        .social-links {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 20px;
        }
        
        .social-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 15px;
            background-color: var(--gray-100);
            border-radius: var(--border-radius);
            color: var(--gray-700);
            transition: var(--transition);
            font-size: 0.9rem;
        }
        
        .social-link:hover {
            background-color: var(--primary-100);
            color: var(--primary-700);
            transform: translateY(-3px);
            box-shadow: var(--box-shadow);
        }
        
        .social-link i {
            font-size: 1.2rem;
        }
        
        /* Banking Info */
        .banking-info {
            background-color: var(--gray-50);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 20px;
            border: 1px solid var(--gray-200);
        }
        
        .banking-info-item {
            margin-bottom: 15px;
        }
        
        .banking-info-label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 5px;
        }
        
        .banking-info-value {
            color: var(--gray-900);
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
        
        @media (max-width: 992px) {
            .page-title {
                font-size: 2.5rem;
            }
            
            .page-subtitle {
                font-size: 1.1rem;
            }
            
            .profile-header {
                padding: 20px;
            }
            
            .profile-avatar {
                width: 120px;
                height: 120px;
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                padding: 60px 0 40px;
            }
            
            .page-title {
                font-size: 2rem;
            }
            
            .profile-container {
                margin-top: -30px;
            }
            
            .profile-header {
                padding: 15px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profile-name {
                font-size: 1.5rem;
            }
            
            .stat-item {
                min-width: 100px;
                padding: 10px;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .content-card {
                padding: 20px;
            }
        }
        
        @media (max-width: 576px) {
            .page-title {
                font-size: 1.8rem;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .profile-stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .nav-tabs .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
            
            .content-card {
                padding: 15px;
            }
            
            .card-title {
                font-size: 1.3rem;
            }
        }
        .add_fund{
            background-color: var(--primary-600);
            color: white;
            padding: 10px 20px;
            border-radius: 50%;
            font-size: 1rem;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
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
                        <a class="nav-link active" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="add-fund.php">Add Fund</a>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                <div class="dropdown me-3">
                        <a class="position-relative" href="#" role="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" onclick="markNotificationsAsRead()">
                            <i class="fas fa-bell fs-5 text-gray-700"></i>
                            <span class="notification-badge" id="notification-badge"><?php echo $notificationCount; ?></span>
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
                <h1 class="page-title" data-aos="fade-up">My Profile</h1>
                <p class="page-subtitle" data-aos="fade-up" data-aos-delay="100">View and manage your personal information and account settings</p>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container profile-container">
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

        <!-- Profile Header -->
        <div class="profile-header" data-aos="fade-up">
            <div class="row">
                <div class="col-md-3 text-center">
                    <div class="profile-avatar-container">
                        <?php if($profile && !empty($profile['profile_image'])): ?>
                            <img src="<?php echo $profile['profile_image']; ?>" alt="<?php echo $user['full_name']; ?>" class="profile-avatar">
                        <?php else: ?>
                            <div class="profile-avatar d-flex align-items-center justify-content-center">
                                <i class="fas fa-user-circle fa-5x text-gray-400"></i>
                            </div>
                        <?php endif; ?>
                        <label for="profile_image_upload" class="profile-avatar-upload">
                            <i class="fas fa-camera"></i>
                        </label>
                    </div>
                </div>
                <div class="col-md-9">
                    <h2 class="profile-name"><?php echo $user['full_name']; ?></h2>
                    <div>
                        <span class="profile-status <?php echo $user['status'] === 'Online' ? 'status-online' : 'status-offline'; ?>">
                            <i class="fas fa-circle me-1 small"></i> <?php echo $user['status']; ?>
                        </span>

                        <?php if ($user['reports_count'] == 0): ?>
                    <span class="status-badge status-approved">
                        <i class="fas fa-check"></i> 0
                    </span>
                    <?php elseif ($user['reports_count'] >= 1 && $user['reports_count'] <= 7): ?>
                    <span class="status-badge status-reports-low">
                        <i class="fas fa-flag"></i> <?php echo $user['reports_count']; ?>
                    </span>
                    <?php elseif ($user['reports_count'] >= 8 && $user['reports_count'] <= 14): ?>
                    <span class="status-badge status-reports-medium">
                        <i class="fas fa-flag"></i> <?php echo $user['reports_count']; ?>
                    </span>
                    <?php else: ?>
                    <span class="status-badge status-reports-high">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $user['reports_count']; ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($user['suspension_count'] > 0): ?>
                        <span class="status-badge status-reports-suspended">
                            <i class="fas fa-flag"></i> <?php echo $user['suspension_count']; ?>
                        </span>                                               
                    <?php endif; ?>
                    </div>
                    <p class="mt-3"><?php echo $profile && !empty($profile['bio']) ? $profile['bio'] : 'No bio available'; ?></p>
                    
                    <div class="profile-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $totalConsultations; ?></div>
                            <div class="stat-label">Total Consultations</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $completedConsultations; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $pendingConsultations; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo number_format($userBalance); ?></div>
                            <div class="stat-label">Balance (<?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>)</div>
                        </div>
                    </div>
                    <div class="text-end">
                        <button  class="btn btn-primary" style="margin-top: 15px;margin-right: 15px;">
                            <a href="add-fund.php" class="text-white text-decoration-none">
                                <i class="fas fa-plus me-2"></i> Add Fund
                            </a>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Tabs -->
        <div class="profile-tabs" data-aos="fade-up">
            <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="basic-tab" data-bs-toggle="tab" data-bs-target="#basic-tab-pane" type="button" role="tab" aria-controls="basic-tab-pane" aria-selected="true">
                        <i class="fas fa-user me-2"></i> Basic Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-tab-pane" type="button" role="tab" aria-controls="security-tab-pane" aria-selected="false">
                        <i class="fas fa-lock me-2"></i> Security
                    </button>
                </li>
                <?php if($userRole === 'expert'): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="professional-tab" data-bs-toggle="tab" data-bs-target="#professional-tab-pane" type="button" role="tab" aria-controls="professional-tab-pane" aria-selected="false">
                        <i class="fas fa-briefcase me-2"></i> Professional Info
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking-tab-pane" type="button" role="tab" aria-controls="banking-tab-pane" aria-selected="false">
                        <i class="fas fa-money-bill-wave me-2"></i> Banking Info
                    </button>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="tab-content" id="profileTabsContent">
                <!-- Basic Info Tab -->
                <div class="tab-pane fade show active" id="basic-tab-pane" role="tabpanel" aria-labelledby="basic-tab" tabindex="0">
                    <div class="content-card">
                        <h3 class="card-title">Basic Information</h3>
                        <form action="" method="POST">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo $user['full_name']; ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" value="<?php echo $user['email']; ?>" disabled>
                                    <div class="form-text">Email cannot be changed directly. Use the Email Address section below.</div>
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo $profile ? $profile['phone'] : ''; ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="gender" class="form-label">Gender</label>
                                    <input  type="text" class="form-control" id="gender" value="<?php echo $profile['gender']; ?>" disabled>
                                    <div class="form-text">Gender cannot be changed directly.</div>

                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo $profile ? $profile['address'] : ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="bio" class="form-label">Bio</label>
                                <textarea class="form-control" id="bio" name="bio" rows="4"><?php echo $profile ? $profile['bio'] : ''; ?></textarea>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="update_basic_profile" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- In the Basic Info Tab section, add a new form for email update -->
                    <div class="content-card">
                        <h3 class="card-title">Email Address</h3>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label for="current_email" class="form-label">Current Email</label>
                                <input type="email" class="form-control" id="current_email" value="<?php echo $user['email']; ?>" disabled>
                            </div>
                            <div class="mb-3">
                                <label for="new_email" class="form-label">New Email</label>
                                <input type="email" class="form-control" id="new_email" name="new_email" required>
                                <div class="form-text">You will need to verify your new email address.</div>
                            </div>
                            <div class="mb-3">
                                <label for="current_password_email" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password_email" name="current_password_email" required>
                                <div class="form-text">Enter your current password to confirm this change.</div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="update_email" class="btn btn-primary">
                                    <i class="fas fa-envelope me-2"></i> Update Email
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="content-card">
                        <h3 class="card-title">Profile Image</h3>
                        <form action="" method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="profile_image_upload" class="form-label">Upload Profile Image</label>
                                <input type="file" class="form-control" id="profile_image_upload" name="profile_image" accept="image/*">
                                <div class="form-text">You Can Upload</div>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="update_profile_image" class="btn btn-primary">
                                    <i class="fas fa-upload me-2"></i> Upload Image
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Security Tab -->
                <div class="tab-pane fade" id="security-tab-pane" role="tabpanel" aria-labelledby="security-tab" tabindex="0">
                    <div class="content-card">
                        <h3 class="card-title">Change Password</h3>
                        <form action="" method="POST">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, and one number.</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            <div class="text-end">
                                <button type="submit" name="update_password" class="btn btn-primary">
                                    <i class="fas fa-key me-2"></i> Change Password
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="content-card">
                        <h3 class="card-title">Account Security</h3>
                        <div class="mb-4">
                            <h5>Login Activity</h5>
                            <p>Last login: <?php echo date('F d, Y H:i:s', strtotime($user['updated_at'])); ?></p>
                        </div>
                        <div>
                            <h5>Account Status</h5>
                            <p>Your account is <span class="badge bg-success">Active</span></p>
                        </div>
                    </div>
                </div>
                
                <?php if($userRole === 'expert'): ?>
                <!-- Professional Info Tab -->
                <div class="tab-pane fade" id="professional-tab-pane" role="tabpanel" aria-labelledby="professional-tab" tabindex="0">
                    <div class="content-card">
                        <h3 class="card-title">Professional Information</h3>
                        <div class="mb-4">
                            <h5>Category & Subcategory</h5>
                            <?php if($expertProfile): ?>
                                <?php
                                    // Get category and subcategory names
                                    $categoryQuery = "SELECT name FROM categories WHERE id = " . $expertProfile['category'];
                                    $categoryResult = $conn->query($categoryQuery);
                                    $categoryName = $categoryResult->fetch_assoc()['name'];
                                    
                                    $subcategoryQuery = "SELECT name FROM subcategories WHERE id = " . $expertProfile['subcategory'];
                                    $subcategoryResult = $conn->query($subcategoryQuery);
                                    $subcategoryName = $subcategoryResult->fetch_assoc()['name'];
                                ?>
                                <p><strong>Category:</strong> <?php echo ucfirst($categoryName); ?></p>
                                <p><strong>Subcategory:</strong> <?php echo ucfirst($subcategoryName); ?></p>
                            <?php else: ?>
                                <p>No professional information available. Please complete your expert profile.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <h5>Skills</h5>
                            <?php if(count($expertSkills) > 0): ?>
                                <div class="skills-container">
                                    <?php foreach($expertSkills as $skill): ?>
                                        <span class="skill-badge"><?php echo $skill['skill_name']; ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p>No skills added yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <h5>Certificates</h5>
                            <?php if(count($certificates) > 0): ?>
                                <?php foreach($certificates as $certificate): ?>
                                    <div class="credential-card">
                                        <div class="credential-title"><?php echo htmlspecialchars($certificate['institution']); ?></div>
                                        <div class="credential-subtitle">
                                            <?php 
                                                $start_date = new DateTime($certificate['start_date']);
                                                $end_date = new DateTime($certificate['end_date']);
                                                echo $start_date->format('M Y') . ' - ' . $end_date->format('M Y');
                                            ?>
                                        </div>
                                        <div class="credential-description"><?php echo htmlspecialchars($certificate['description']); ?></div>
                                        <div class="mt-2">
                                            <span class="badge bg-<?php echo $certificate['status'] === 'approved' ? 'success' : ($certificate['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($certificate['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No certificates added yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <h5>Work Experience</h5>
                            <?php if(count($experiences) > 0): ?>
                                <?php foreach($experiences as $experience): ?>
                                    <div class="credential-card">
                                        <div class="credential-title"><?php echo htmlspecialchars($experience['workplace']); ?></div>
                                        <div class="credential-subtitle">
                                            <?php 
                                                $start_date = new DateTime($experience['start_date']);
                                                $end_date = new DateTime($experience['end_date']);
                                                echo $start_date->format('M Y') . ' - ' . $end_date->format('M Y');
                                            ?>
                                            <span class="ms-2">
                                                (<?php echo $experience['duration_years']; ?> years, <?php echo $experience['duration_months']; ?> months)
                                            </span>
                                        </div>
                                        <div class="credential-description"><?php echo htmlspecialchars($experience['description']); ?></div>
                                        <div class="mt-2">
                                            <span class="badge bg-<?php echo $experience['status'] === 'approved' ? 'success' : ($experience['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($experience['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No work experience added yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <h5>Education & Training</h5>
                            <?php if(count($formations) > 0): ?>
                                <?php foreach($formations as $formation): ?>
                                    <div class="credential-card">
                                        <div class="credential-title"><?php echo htmlspecialchars($formation['formation_name']); ?></div>
                                        <div class="credential-subtitle">
                                            <?php echo htmlspecialchars(ucfirst($formation['formation_type'])); ?> - 
                                            <?php echo htmlspecialchars($formation['formation_year']); ?>
                                        </div>
                                        <div class="credential-description"><?php echo htmlspecialchars($formation['description']); ?></div>
                                        <div class="mt-2">
                                            <span class="badge bg-<?php echo $formation['status'] === 'approved' ? 'success' : ($formation['status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($formation['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p>No education or training added yet.</p>
                            <?php endif; ?>
                        </div>
                        
                        <div>
                            <h5>Social Links</h5>
                            <form action="" method="POST">
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="facebook_url" class="form-label">Facebook</label>
                                        <input type="url" class="form-control" id="facebook_url" name="facebook_url" value="<?php echo $expertSocialLinks ? $expertSocialLinks['facebook_url'] : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="instagram_url" class="form-label">Instagram</label>
                                        <input type="url" class="form-control" id="instagram_url" name="instagram_url" value="<?php echo $expertSocialLinks ? $expertSocialLinks['instagram_url'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="linkedin_url" class="form-label">LinkedIn</label>
                                        <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" value="<?php echo $expertSocialLinks ? $expertSocialLinks['linkedin_url'] : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="github_url" class="form-label">GitHub</label>
                                        <input type="url" class="form-control" id="github_url" name="github_url" value="<?php echo $expertSocialLinks ? $expertSocialLinks['github_url'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="twitter_url" class="form-label">Twitter</label>
                                        <input type="url" class="form-control" id="twitter_url" name="twitter_url" value="<?php echo $expertSocialLinks ? $expertSocialLinks['twitter_url'] : ''; ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="website_url" class="form-label">Website</label>
                                        <input type="url" class="form-control" id="website_url" name="website_url" value="<?php echo $expertSocialLinks ? $expertSocialLinks['website_url'] : ''; ?>">
                                    </div>
                                </div>
                                <div class="text-end">
                                    <button type="submit" name="update_social_links" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> Save Social Links
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Banking Info Tab -->
                <div class="tab-pane fade" id="banking-tab-pane" role="tabpanel" aria-labelledby="banking-tab" tabindex="0">
                    <div class="content-card">
                        <h3 class="card-title">Banking Information</h3>
                        <?php if($bankingInfo): ?>
                            <div class="banking-info">
                                <div class="banking-info-item">
                                    <div class="banking-info-label">CCP Number</div>
                                    <div class="banking-info-value"><?php echo $bankingInfo['ccp']; ?></div>
                                </div>
                                <div class="banking-info-item">
                                    <div class="banking-info-label">CCP Key</div>
                                    <div class="banking-info-value"><?php echo $bankingInfo['ccp_key']; ?></div>
                                </div>
                                <div class="banking-info-item">
                                    <div class="banking-info-label">Consultation Rate</div>
                                    <div class="banking-info-value">
                                        <?php echo number_format($bankingInfo['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?> / <?php echo $bankingInfo['consultation_minutes']; ?> minutes
                                    </div>
                                </div>
                                <div class="banking-info-item">
                                    <div class="banking-info-label">Withdrawal Duration</div>
                                    <div class="banking-info-value"><?php echo $bankingInfo['withdrawal_duration']; ?> days</div>
                                </div>
                                <div class="banking-info-item">
                                    <div class="banking-info-label">Status</div>
                                    <div class="banking-info-value">
                                        <?php if($expertProfile): ?>
                                            <span class="badge bg-<?php echo $expertProfile['banking_status'] === 'approved' ? 'success' : ($expertProfile['banking_status'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                                <?php echo ucfirst($expertProfile['banking_status']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not Available</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <p class="text-muted">To update your banking information, please contact support.</p>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i> No banking information available. Please complete your expert profile.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
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

        // Variables to control notification fetching
        let lastFetchTime = 0;
        let notificationCache = null;
        let notificationCacheTime = 0;
        const CACHE_LIFETIME = 5000; // Cache lifetime in milliseconds (5 seconds)
        const FETCH_INTERVAL = 1000; // Fetch interval in milliseconds (1 second)

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

        // Function to fetch notifications with caching and throttling
        function fetchNotifications() {
            const now = Date.now();
            
            // Use cache if it's still valid
            if (notificationCache && now - notificationCacheTime < CACHE_LIFETIME) {
                updateNotificationUI(notificationCache);
                return;
            }
            
            // Throttle requests - only fetch if it's been at least FETCH_INTERVAL since last fetch
            if (now - lastFetchTime < FETCH_INTERVAL) {
                return;
            }
            
            lastFetchTime = now;
            
            fetch('get-notifications.php')
                .then(response => response.json())
                .then(data => {
                    // Cache the response
                    notificationCache = data;
                    notificationCacheTime = now;
                    
                    // Update the UI
                    updateNotificationUI(data);
                })
                .catch(error => {
                    console.error('Error fetching notifications:', error);
                });
        }

        // Function to update notification UI elements
        function updateNotificationUI(data) {
            // Update notification badge count
            const badge = document.getElementById('notification-badge');
            if (badge) {
                if (data.count > 0) {
                    badge.textContent = data.count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
            
            // Update notification dropdown content
            const notificationsList = document.querySelector('.dropdown-menu[aria-labelledby="notificationDropdown"]');
            if (notificationsList) {
                let notificationsHTML = '<li><h6 class="dropdown-header fw-bold">Notifications</h6></li>';
                
                if (data.notifications && data.notifications.length > 0) {
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
                } else {
                    notificationsHTML += '<li><p class="dropdown-item py-3 text-center mb-0">No new notifications</p></li>';
                }
                
                notificationsHTML += '<li><a class="dropdown-item text-center text-primary py-3 fw-semibold" href="notifications.php">View All</a></li>';
                
                notificationsList.innerHTML = notificationsHTML;
            }
        }

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

        // Set up the notification refresh interval
        const notificationInterval = setInterval(fetchNotifications, FETCH_INTERVAL);

        // Initial fetch
        fetchNotifications();

        // Clean up interval when page is unloaded
        window.addEventListener('beforeunload', () => {
            clearInterval(notificationInterval);
        });
    </script>
</body>
</html>
