<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';
// Check if user is logged in
// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in, redirect to login page
    header("Location: ../config/logout.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];
$success_message = "";
$error_message = "";

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Get user data
$sql = "SELECT u.*, up.phone, up.address, up.dob, up.gender, up.profile_image, up.bio 
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get expert profile data
$profile_sql = "SELECT * FROM expert_profiledetails WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$profile_id = $profile ? $profile['id'] : 0;

// Get social media links
$social_sql = "SELECT * FROM expert_social_links WHERE user_id = ?";
$social_stmt = $conn->prepare($social_sql);
$social_stmt->bind_param("i", $user_id);
$social_stmt->execute();
$social_result = $social_stmt->get_result();
$social_links = $social_result->fetch_assoc();

// Get skills
$skills = [];
if ($profile_id) {
    $skills_sql = "SELECT * FROM skills WHERE profile_id = ?";
    $skills_stmt = $conn->prepare($skills_sql);
    $skills_stmt->bind_param("i", $profile_id);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    while ($row = $skills_result->fetch_assoc()) {
        $skills[] = $row['skill_name'];
    }
}

// Get approved certificates
$certificates = [];
if ($profile_id) {
    $cert_sql = "SELECT * FROM certificates WHERE profile_id = ? AND status = 'approved' ORDER BY id";
    $cert_stmt = $conn->prepare($cert_sql);
    $cert_stmt->bind_param("i", $profile_id);
    $cert_stmt->execute();
    $cert_result = $cert_stmt->get_result();
    while ($row = $cert_result->fetch_assoc()) {
        $certificates[] = $row;
    }
}

// Get approved experiences
$experiences = [];
if ($profile_id) {
    $exp_sql = "SELECT * FROM experiences WHERE profile_id = ? AND status = 'approved' ORDER BY id";
    $exp_stmt = $conn->prepare($exp_sql);
    $exp_stmt->bind_param("i", $profile_id);
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();
    while ($row = $exp_result->fetch_assoc()) {
        $experiences[] = $row;
    }
}

// Get approved formations (courses)
$formations = [];
if ($profile_id) {
    $form_sql = "SELECT * FROM formations WHERE profile_id = ? AND status = 'approved' ORDER BY id";
    $form_stmt = $conn->prepare($form_sql);
    $form_stmt->bind_param("i", $profile_id);
    $form_stmt->execute();
    $form_result = $form_stmt->get_result();
    while ($row = $form_result->fetch_assoc()) {
        $formations[] = $row;
    }
}

// Get all categories
$categories = [];
$cat_sql = "SELECT * FROM categories ORDER BY name";
$cat_result = $conn->query($cat_sql);
while ($row = $cat_result->fetch_assoc()) {
    $categories[$row['id']] = $row['name'];
}

// Get all subcategories
$subcategories = [];
$subcat_sql = "SELECT * FROM subcategories ORDER BY category_id, name";
$subcat_result = $conn->query($subcat_sql);
while ($row = $subcat_result->fetch_assoc()) {
    $subcategories[$row['category_id']][$row['id']] = $row['name'];
}

// Get all cities
$cities = [];
$city_sql = "SELECT * FROM cities ORDER BY name";
$city_result = $conn->query($city_sql);
while ($row = $city_result->fetch_assoc()) {
    $cities[$row['id']] = $row['name'];
}


// Get settings from database
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile"])) {
    // Only allow editing of bio, Google Maps link, and social media links
    $bio = sanitize_input($_POST["bio"]);
    
    // Social Media Links
    $facebook = sanitize_input($_POST["facebook"]);
    $instagram = sanitize_input($_POST["instagram"]);
    $linkedin = sanitize_input($_POST["linkedin"]);
    $github = sanitize_input($_POST["github"]);
    $website = sanitize_input($_POST["website"]);
    $twitter = sanitize_input($_POST["twitter"]);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update bio in user_profiles table
        $check_profile_sql = "SELECT id FROM user_profiles WHERE user_id = ?";
        $check_profile_stmt = $conn->prepare($check_profile_sql);
        $check_profile_stmt->bind_param("i", $user_id);
        $check_profile_stmt->execute();
        $check_profile_result = $check_profile_stmt->get_result();
        
        if ($check_profile_result->num_rows > 0) {
            // Update user_profiles table (bio only)
            $update_profile_sql = "UPDATE user_profiles SET bio = ? WHERE user_id = ?";
            $update_profile_stmt = $conn->prepare($update_profile_sql);
            $update_profile_stmt->bind_param("si", $bio, $user_id);
            $update_profile_stmt->execute();
        } else {
            // Insert into user_profiles table (bio only)
            $insert_profile_sql = "INSERT INTO user_profiles (user_id, bio) VALUES (?, ?)";
            $insert_profile_stmt = $conn->prepare($insert_profile_sql);
            $insert_profile_stmt->bind_param("is", $user_id, $bio);
            $insert_profile_stmt->execute();
        }
        
        // Update workplace_map_url in expert_profiledetails table
        if ($profile_id) {
            $update_map_sql = "UPDATE expert_profiledetails SET workplace_map_url = ? WHERE id = ?";
            $update_map_stmt = $conn->prepare($update_map_sql);
            $update_map_stmt->bind_param("si", $google_maps_link, $profile_id);
            $update_map_stmt->execute();
        }
        
        // Check if expert_social_links table exists, if not create it
        $check_table_sql = "SHOW TABLES LIKE 'expert_social_links'";
        $check_table_result = $conn->query($check_table_sql);
        
       
        
        // Check if social links exist
        $check_social_sql = "SELECT id FROM expert_social_links WHERE user_id = ?";
        $check_social_stmt = $conn->prepare($check_social_sql);
        $check_social_stmt->bind_param("i", $user_id);
        $check_social_stmt->execute();
        $check_social_result = $check_social_stmt->get_result();
        
        if ($check_social_result->num_rows > 0) {
            // Update social links
            $update_social_sql = "UPDATE expert_social_links SET facebook_url = ?, instagram_url = ?, linkedin_url = ?, github_url = ?, website_url = ?, twitter_url = ? WHERE user_id = ?";
            $update_social_stmt = $conn->prepare($update_social_sql);
            $update_social_stmt->bind_param("ssssssi", $facebook, $instagram, $linkedin, $github, $website, $twitter, $user_id);
            $update_social_stmt->execute();
        } else {
            // Insert social links
            $insert_social_sql = "INSERT INTO expert_social_links (user_id, profile_id, facebook_url, instagram_url, linkedin_url, github_url, website_url, twitter_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $insert_social_stmt = $conn->prepare($insert_social_sql);
            $insert_social_stmt->bind_param("iissssss", $user_id, $profile_id, $facebook, $instagram, $linkedin, $github, $website, $twitter);
            $insert_social_stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $success_message = "Profile updated successfully!";
        
        // Refresh user data
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        // Refresh social links data
        $social_stmt->execute();
        $social_result = $social_stmt->get_result();
        $social_links = $social_result->fetch_assoc();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error updating profile: " . $e->getMessage();
    }
}
// Get pending consultation requests
$pending_consultations = [];
$pending_sql = "SELECT c.*, u.full_name as client_name, u.status as client_status, up.profile_image as client_image,
                cat.name as category_name, subcat.name as subcategory_name
                FROM consultations c 
                JOIN users u ON c.client_id = u.id 
                LEFT JOIN user_profiles up ON u.id = up.user_id 
                LEFT JOIN expert_profiledetails ep ON c.expert_id = ep.user_id
                LEFT JOIN categories cat ON ep.category = cat.id
                LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                WHERE c.expert_id = ? AND c.status = 'pending' 
                ORDER BY c.created_at DESC";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();

while ($row = $pending_result->fetch_assoc()) {
    $pending_consultations[] = $row;
}
$pending_stmt->close();
// Get notification counts

$admin_messages = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND receiver_id = ? AND sender_type = 'admin'");
$admin_messages->bind_param("i", $user_id);
$admin_messages->execute();
$admin_messages_result = $admin_messages->get_result();
$admin_messages_count = $admin_messages_result->fetch_assoc()['count'];
$admin_messages->close();

// Get pending withdrawals count
$pending_withdrawals = $conn->prepare("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending' AND user_id = ?");
$pending_withdrawals->bind_param("i", $user_id);
$pending_withdrawals->execute();
$pending_withdrawals_result = $pending_withdrawals->get_result();
$pending_withdrawals_count = $pending_withdrawals_result->fetch_assoc()['count'];
$pending_withdrawals->close();

// Get pending consultation count
$pending_consultations_count = count($pending_consultations);

$reviews_not_read = $conn->prepare("SELECT COUNT(*) as count FROM expert_ratings WHERE is_read = 0 AND expert_id = ?");
$reviews_not_read->bind_param("i", $user_id);
$reviews_not_read->execute();
$reviews_not_read_result = $reviews_not_read->get_result();
$reviews_not_read_count = $reviews_not_read_result->fetch_assoc()['count'];
$reviews_not_read->close();

$notifictaions_not_read = $conn->prepare("SELECT COUNT(*) as count FROM expert_notifications WHERE is_read = 0 AND user_id = ? ");
$notifictaions_not_read->bind_param("i", $user_id);
$notifictaions_not_read->execute();
$notifictaions_not_read_result = $notifictaions_not_read->get_result();
$notifictaions_not_read_count = $notifictaions_not_read_result->fetch_assoc()['count'];
$notifictaions_not_read->close();

// Handle AJAX request for notifications
if (isset($_GET['fetch_notifications'])) {
    $response = [
        'pending_consultations' => $pending_consultations_count,
        'pending_withdrawals' => $pending_withdrawals_count,
        'admin_messages' => $admin_messages_count,
        'reviews' => $reviews_not_read_count,
        'notifications_not_read' => $notifictaions_not_read_count,
        'total' => $pending_consultations_count + $pending_withdrawals_count + $admin_messages_count + $reviews_not_read_count + $notifictaions_not_read_count,
       ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Profile - Consult Pro</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=JetBrains+Mono:wght@400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --primary-color: #6366f1;
        --primary-light: #818cf8;
        --primary-dark: #4f46e5;
        --secondary-color: #06b6d4;
        --secondary-light: #22d3ee;
        --secondary-dark: #0891b2;
        --accent-color: #8b5cf6;
        --accent-light: #a78bfa;
        --accent-dark: #7c3aed;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #3b82f6;
        --light-color: #f8fafc;
        --dark-color: #1e293b;
        --border-color: #e2e8f0;
        --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        --text-color: #334155;
        --text-muted: #64748b;
        --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        --code-font: 'JetBrains Mono', monospace;
        --body-font: 'Poppins', sans-serif;
    }
    
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }
    
    body {
        font-family: var(--body-font);
        color: var(--text-color);
        line-height: 1.6;
        min-height: 100vh;
        position: relative;
        background-color: #f1f5f9;
        overflow-x: hidden;
    }
    
    /* Enhanced Background Design */
    .background-container {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        overflow: hidden;
    }
    
    .background-gradient {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, #eef2ff 0%, #e0e7ff 50%, #dbeafe 100%);
        opacity: 0.8;
    }
    
    .background-pattern {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
            radial-gradient(circle at 25px 25px, rgba(99, 102, 241, 0.15) 2px, transparent 0),
            radial-gradient(circle at 75px 75px, rgba(139, 92, 246, 0.1) 2px, transparent 0);
        background-size: 100px 100px;
        opacity: 0.6;
        background-attachment: fixed;
    }
    
    .background-grid {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: linear-gradient(rgba(99, 102, 241, 0.1) 1px, transparent 1px),
                          linear-gradient(90deg, rgba(99, 102, 241, 0.1) 1px, transparent 1px);
        background-size: 50px 50px;
        background-attachment: fixed;
    }
    
    .background-shapes {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    
    .shape {
        position: absolute;
        border-radius: 50%;
        filter: blur(70px);
        opacity: 0.2;
        animation: float 20s infinite ease-in-out;
    }
    
    .shape-1 {
        width: 600px;
        height: 600px;
        background: rgba(99, 102, 241, 0.5);
        top: -200px;
        right: -100px;
        animation-delay: 0s;
    }
    
    .shape-2 {
        width: 500px;
        height: 500px;
        background: rgba(6, 182, 212, 0.4);
        bottom: -150px;
        left: -150px;
        animation-delay: -5s;
    }
    
    .shape-3 {
        width: 400px;
        height: 400px;
        background: rgba(139, 92, 246, 0.3);
        top: 30%;
        left: 30%;
        animation-delay: -10s;
    }
    
    .shape-4 {
        width: 350px;
        height: 350px;
        background: rgba(245, 158, 11, 0.2);
        bottom: 20%;
        right: 20%;
        animation-delay: -7s;
    }
    
    .shape-5 {
        width: 300px;
        height: 300px;
        background: rgba(16, 185, 129, 0.3);
        top: 10%;
        left: 20%;
        animation-delay: -3s;
    }
    
    @keyframes float {
        0%, 100% {
            transform: translateY(0) scale(1);
        }
        50% {
            transform: translateY(-40px) scale(1.05);
        }
    }
    
    /* Particles */
    .particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        z-index: -1;
    }
    
    .particle {
        position: absolute;
        width: 6px;
        height: 6px;
        background-color: rgba(99, 102, 241, 0.2);
        border-radius: 50%;
        animation: particle-animation 15s infinite linear;
    }
    
    @keyframes particle-animation {
        0% {
            transform: translate(0, 0);
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            transform: translate(var(--tx), var(--ty));
            opacity: 0;
        }
    }
    
    /* Animated Gradient Background */
    .animated-gradient {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(-45deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1), rgba(6, 182, 212, 0.1), rgba(16, 185, 129, 0.1));
        background-size: 400% 400%;
        animation: gradient 15s ease infinite;
        z-index: -1;
    }
    
    @keyframes gradient {
        0% {
            background-position: 0% 50%;
        }
        50% {
            background-position: 100% 50%;
        }
        100% {
            background-position: 0% 50%;
        }
    }
    
    /* 3D Floating Elements */
    .floating-elements {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        perspective: 1000px;
    }
    
    .floating-element {
        position: absolute;
        transform-style: preserve-3d;
        animation: float-3d 20s infinite ease-in-out;
        opacity: 0.15;
    }
    
    .floating-element-1 {
        top: 15%;
        left: 10%;
        animation-delay: 0s;
    }
    
    .floating-element-2 {
        top: 60%;
        right: 15%;
        animation-delay: -5s;
    }
    
    .floating-element-3 {
        bottom: 20%;
        left: 20%;
        animation-delay: -10s;
    }
    
    .floating-cube {
        width: 80px;
        height: 80px;
        position: relative;
        transform-style: preserve-3d;
        animation: rotate-cube 20s infinite linear;
    }
    
    .floating-cube-face {
        position: absolute;
        width: 80px;
        height: 80px;
        background: rgba(99, 102, 241, 0.2);
        border: 1px solid rgba(99, 102, 241, 0.3);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: var(--code-font);
        font-size: 12px;
        color: rgba(99, 102, 241, 0.8);
    }
    
    .floating-cube-face:nth-child(1) { transform: translateZ(40px); }
    .floating-cube-face:nth-child(2) { transform: rotateY(180deg) translateZ(40px); }
    .floating-cube-face:nth-child(3) { transform: rotateY(90deg) translateZ(40px); }
    .floating-cube-face:nth-child(4) { transform: rotateY(-90deg) translateZ(40px); }
    .floating-cube-face:nth-child(5) { transform: rotateX(90deg) translateZ(40px); }
    .floating-cube-face:nth-child(6) { transform: rotateX(-90deg) translateZ(40px); }
    
    @keyframes float-3d {
        0%, 100% {
            transform: translateY(0) rotateX(10deg) rotateY(10deg);
        }
        50% {
            transform: translateY(-30px) rotateX(-10deg) rotateY(-10deg);
        }
    }
    
    @keyframes rotate-cube {
        0% {
            transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg);
        }
        100% {
            transform: rotateX(360deg) rotateY(360deg) rotateZ(360deg);
        }
    }
    
    /* Code Elements Animation */
    .code-element {
        position: absolute;
        font-family: var(--code-font);
        color: rgba(99, 102, 241, 0.3);
        font-size: 14px;
        white-space: nowrap;
        pointer-events: none;
        z-index: -1;
        text-shadow: 0 0 10px rgba(99, 102, 241, 0.1);
    }
    
    .code-element-1 {
        top: 15%;
        left: 5%;
        transform: rotate(-15deg);
        animation: fadeInOut 8s infinite ease-in-out, float-code 20s infinite ease-in-out;
    }
    
    .code-element-2 {
        top: 40%;
        right: 10%;
        transform: rotate(10deg);
        animation: fadeInOut 12s infinite ease-in-out 2s, float-code 25s infinite ease-in-out 2s;
    }
    
    .code-element-3 {
        bottom: 20%;
        left: 15%;
        transform: rotate(5deg);
        animation: fadeInOut 10s infinite ease-in-out 4s, float-code 22s infinite ease-in-out 4s;
    }
    
    .code-element-4 {
        top: 25%;
        right: 25%;
        transform: rotate(-5deg);
        animation: fadeInOut 11s infinite ease-in-out 1s, float-code 24s infinite ease-in-out 1s;
    }
    
    .code-element-5 {
        bottom: 35%;
        right: 15%;
        transform: rotate(8deg);
        animation: fadeInOut 9s infinite ease-in-out 3s, float-code 21s infinite ease-in-out 3s;
    }
    
    @keyframes fadeInOut {
        0%, 100% {
            opacity: 0.1;
        }
        50% {
            opacity: 0.3;
        }
    }
    
    @keyframes float-code {
        0%, 100% {
            transform: translateY(0) rotate(var(--rotate));
        }
        50% {
            transform: translateY(-20px) rotate(var(--rotate));
        }
    }
    
    /* Navbar Styles */
    .navbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 0.8rem 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        .logo-text .fw-bold {
            font-size: 1.8rem;
            color: var(--primary-color);
        }

        .logo-subtitle {
            font-size: 0.7rem;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .navbar-nav {
            gap: 0.5rem;
        }

        .navbar-nav .nav-item {
            position: relative;
        }

        .navbar-light .navbar-nav .nav-link {
            color: var(--text-color);
            font-weight: 500;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.9rem;
        }

        .navbar-light .navbar-nav .nav-link i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .navbar-light .navbar-nav .nav-link:hover {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.08);
        }

        .navbar-light .navbar-nav .nav-link:hover i {
            transform: translateY(-3px);
        }

        .navbar-light .navbar-nav .active > .nav-link,
        .navbar-light .navbar-nav .nav-link.active {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
            font-weight: 600;
        }

        .navbar-light .navbar-nav .active > .nav-link i,
        .navbar-light .navbar-nav .nav-link.active i {
            color: var(--primary-color);
        }

        .nav-user-section {
            margin-left: 1rem;
            border-left: 1px solid rgba(226, 232, 240, 0.8);
            padding-left: 1rem;
        }

        
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 0;
            margin-top: 0.5rem;
            animation: dropdown-fade 0.3s ease;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.7);
            min-width: 200px;
        }

        @keyframes dropdown-fade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .dropdown-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            z-index: -1;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .dropdown-item:hover::before {
            transform: translateX(0);
        }

        .dropdown-item:hover {
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .dropdown-item:active {
            background-color: var(--primary-color);
            color: white;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .dropdown-item:hover i {
            transform: scale(1.2);
        }
    
    /* Main Content Styles */
    .main-container {
        padding: 2rem 0;
        position: relative;
        z-index: 1;
    }
    
    /* Profile Container */
    .profile-container {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        margin-bottom: 2.5rem;
        overflow: hidden;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        border: 1px solid rgba(226, 232, 240, 0.7);
    }
    
    .profile-container:hover {
        transform: translateY(-10px);
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        background: rgba(255, 255, 255, 0.9);
    }
    
    .profile-header {
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
        padding: 2.5rem;
        position: relative;
        overflow: hidden;
        border-bottom: 1px solid rgba(226, 232, 240, 0.7);
    }
    
    .profile-header::before {
        content: '';
        position: absolute;
        top: -50px;
        right: -50px;
        width: 200px;
        height: 200px;
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.2) 0%, rgba(99, 102, 241, 0) 60%);
        border-radius: 50%;
        z-index: 0;
    }
    
    .profile-header::after {
        content: '';
        position: absolute;
        bottom: -50px;
        left: -50px;
        width: 200px;
        height: 200px;
        background: linear-gradient(135deg, rgba(139, 92, 246, 0.2) 0%, rgba(139, 92, 246, 0) 60%);
        border-radius: 50%;
        z-index: 0;
    }
    
    .profile-image-container {
        position: relative;
        z-index: 1;
    }
    
    .profile-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        object-fit: cover;
        background-color: #fff;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    
    .profile-container:hover .profile-avatar {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 15px 35px rgba(99, 102, 241, 0.2);
    }
    
    .profile-avatar-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: var(--primary-color);
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    
    .profile-container:hover .profile-avatar-placeholder {
        transform: scale(1.1) rotate(5deg);
        box-shadow: 0 15px 35px rgba(99, 102, 241, 0.2);
    }
    
    .profile-info {
        margin-left: 1.5rem;
        position: relative;
        z-index: 1;
    }
    
    .profile-name {
        font-size: 2.2rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        color: var(--dark-color);
        position: relative;
        display: inline-block;
    }
    
    .profile-name::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50%;
        height: 3px;
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        border-radius: 3px;
        transition: width 0.4s ease;
    }
    
    .profile-container:hover .profile-name::after {
        width: 100%;
    }
    
    .profile-title {
        font-size: 1.2rem;
        color: var(--text-muted);
        margin-bottom: 0.75rem;
    }
    
    .profile-location {
        display: flex;
        align-items: center;
        font-size: 1rem;
        color: var(--text-muted);
    }
    
    .profile-location i {
        margin-right: 0.75rem;
        color: var(--primary-color);
        font-size: 1.1rem;
    }
    
    .profile-content {
        padding: 2.5rem;
    }
    
    /* Section Styles */
    .section-title {
        font-size: 1.4rem;
        font-weight: 600;
        color: var(--dark-color);
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        position: relative;
        display: inline-block;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        border-radius: 3px;
        transition: width 0.4s ease;
    }
    
    .section-title:hover::after {
        width: 100%;
    }
    
    /* Form Styles */
    .form-label {
        font-weight: 500;
        color: var(--dark-color);
        margin-bottom: 0.5rem;
    }
    
    .form-control {
        border-radius: 10px;
        padding: 0.75rem 1rem;
        border: 1px solid rgba(226, 232, 240, 0.7);
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        transition: all 0.3s ease;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .form-control:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        background: rgba(255, 255, 255, 0.95);
    }
    
    .form-control.read-only-field {
        background: rgba(241, 245, 249, 0.8);
        cursor: not-allowed;
        color: var(--text-muted);
    }
    
    textarea.form-control {
        min-height: 120px;
    }
    
    .form-text {
        color: var(--text-muted);
        font-size: 0.85rem;
        margin-top: 0.5rem;
    }
    
    /* Editable Section */
    .editable-section {
        border-left: 4px solid var(--primary-color);
        padding-left: 1.5rem;
        margin-left: -1.5rem;
        position: relative;
        transition: all 0.3s ease;
    }
    
    .editable-section:hover {
        border-left-color: var(--accent-color);
        background: rgba(99, 102, 241, 0.03);
    }
    
    /* Badge Styles */
    .badge {
        padding: 0.5rem 0.75rem;
        border-radius: 10px;
        font-weight: 500;
        font-size: 0.8rem;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    
    .bg-success {
        background: linear-gradient(to right, var(--success-color), #34d399) !important;
        color: white !important;
    }
    
    /* Skills Container */
    .skills-container {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }
    
    .skill-tag {
        background: linear-gradient(to right, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
        color: var(--primary-color);
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    
    .skill-tag:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 15px rgba(99, 102, 241, 0.15);
        background: linear-gradient(to right, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    }
    
    /* Card Styles */
    .card {
        border: none;
        border-radius: 15px;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(5px);
        -webkit-backdrop-filter: blur(5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        margin-bottom: 1.5rem;
        border: 1px solid rgba(226, 232, 240, 0.7);
    }
    
    .card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        background: rgba(255, 255, 255, 0.9);
    }
    
    .card-body {
        padding: 1.5rem;
    }
    
    .card-title {
        font-weight: 600;
        font-size: 1.1rem;
        color: var(--dark-color);
        margin-bottom: 0.75rem;
    }
    
    .card-text {
        color: var(--text-muted);
        font-size: 0.95rem;
        line-height: 1.6;
    }
    
    .card-text.text-muted {
        font-size: 0.85rem;
        margin-bottom: 0.75rem;
    }
    
    /* Button Styles */
    .btn {
        padding: 0.75rem 1.5rem;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        overflow: hidden;
        z-index: 1;
    }
    
    .btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(to right, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.3));
        transition: all 0.4s ease;
        z-index: -1;
    }
    
    .btn:hover::before {
        left: 0;
    }
    
    .btn-primary {
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        border: none;
        color: white;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-8px) scale(1.05);
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.4);
        background: linear-gradient(to right, var(--primary-dark), var(--accent-dark));
    }
    
    .btn-lg {
        padding: 1rem 2.5rem;
        font-size: 1.1rem;
    }
    
    /* Alert Styles */
    .alert {
        border-radius: 15px;
        padding: 1.25rem;
        margin-bottom: 2rem;
        border: none;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        animation: fadeIn 0.5s ease;
    }
    
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .alert-success {
        border-left: 4px solid var(--success-color);
        color: var(--success-color);
    }
    
    .alert-danger {
        border-left: 4px solid var(--danger-color);
        color: var(--danger-color);
    }
    /* Notification Badge Styles */
    .notification-badge {
            display: none;
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background-color: var(--danger-color) ;
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
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
    /* Footer Styles */
    footer {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        color: var(--text-color);
        padding: 3rem 0 0;
        margin-top: 3rem;
        position: relative;
        overflow: hidden;
        border-top: 1px solid rgba(226, 232, 240, 0.7);
    }
    
    .footer-content {
        position: relative;
        z-index: 1;
    }
    
    footer h5 {
        font-size: 1.25rem;
        font-weight: 600;
        margin-bottom: 1.5rem;
        position: relative;
        display: inline-block;
        color: var(--dark-color);
    }
    
    footer h5::after {
        content: '';
        position: absolute;
        bottom: -8px;
        left: 0;
        width: 40px;
        height: 3px;
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        border-radius: 3px;
        transition: width 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    
    footer h5:hover::after {
        width: 100%;
    }
    
    .footer-links {
        list-style: none;
        padding-left: 0;
    }
    
    .footer-links li {
        margin-bottom: 0.75rem;
        transition: all 0.3s ease;
    }
    
    .footer-links a {
        color: var(--text-muted);
        text-decoration: none;
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: inline-block;
        font-weight: 500;
        position: relative;
        padding-left: 20px;
    }
    
    .footer-links a i {
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        color: var(--primary-color);
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
    }
    
    .footer-links a:hover {
        color: var(--primary-color);
        transform: translateX(10px);
    }
    
    .footer-links a:hover i {
        color: var(--accent-color);
        transform: translateY(-50%) scale(1.2);
    }
    
    .social-icons {
        display: flex;
        gap: 1.25rem;
        margin-bottom: 1.5rem;
    }
    
    .social-icons a {
        color: var(--text-color);
        font-size: 1.5rem;
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 45px;
        height: 45px;
        background: rgba(248, 250, 252, 0.8);
        border-radius: 12px;
        position: relative;
        overflow: hidden;
        z-index: 1;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }
    
    .social-icons a::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        opacity: 0;
        transition: opacity 0.3s ease;
        z-index: -1;
    }
    
    .social-icons a:hover::before {
        opacity: 1;
    }
    
    .social-icons a:hover {
        color: white;
        transform: translateY(-8px) rotate(10deg);
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
    }
    
    .footer-bottom {
        background: rgba(248, 250, 252, 0.8);
        padding: 1.5rem 0;
        margin-top: 3rem;
        position: relative;
        z-index: 1;
        border-top: 1px solid rgba(226, 232, 240, 0.7);
    }
    
    .footer-bottom p {
        margin-bottom: 0;
        text-align: center;
        font-size: 0.95rem;
        color: var(--text-muted);
    }
    
    /* Responsive Styles */
    @media (max-width: 991.98px) {
        .profile-header {
            padding: 2rem;
        }
        
        .profile-name {
            font-size: 1.8rem;
        }
        
        .profile-content {
            padding: 2rem;
        }
        
        .code-element {
            display: none;
        }
        
        .floating-elements {
            display: none;
        }
    }
    
    @media (max-width: 767.98px) {
        .profile-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .profile-info {
            margin-left: 0;
            margin-top: 1.5rem;
            text-align: center;
        }
        
        .profile-name::after {
            left: 50%;
            transform: translateX(-50%);
        }
        
        .editable-section {
            margin-left: 0;
            padding-left: 1rem;
        }
        
        .section-title::after {
            left: 50%;
            transform: translateX(-50%);
        }
        
        .section-title {
            display: block;
            text-align: center;
        }
        
        .shape {
            opacity: 0.05;
        }
    }
    
    /* Animations */
    .fade-in {
        animation: fadeIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    }
    
    .delay-1 {
        animation-delay: 0.1s;
    }
    
    .delay-2 {
        animation-delay: 0.2s;
    }
    
    .delay-3 {
        animation-delay: 0.3s;
    }
    
    .delay-4 {
        animation-delay: 0.4s;
    }
    
    /* Custom Scrollbar */
    ::-webkit-scrollbar {
        width: 10px;
        height: 10px;
    }
    
    ::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }
    
    ::-webkit-scrollbar-thumb {
        background: linear-gradient(to bottom, var(--primary-light), var(--accent-light));
        border-radius: 10px;
        transition: all 0.3s ease;
    }
    
    ::-webkit-scrollbar-thumb:hover {
        background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
    }
    
    /* Glowing Effect */
    .glow {
        position: relative;
    }
    
    .glow::after {
        content: '';
        position: absolute;
        top: -10px;
        left: -10px;
        right: -10px;
        bottom: -10px;
        z-index: -1;
        background: linear-gradient(45deg, var(--primary-color), var(--accent-color), var(--secondary-color));
        filter: blur(20px);
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .glow:hover::after {
        opacity: 0.15;
    }
</style>
</head>
<body>
<!-- Background Elements -->
<div class="background-container">
    <div class="background-gradient"></div>
    <div class="background-pattern"></div>
    <div class="background-grid"></div>
    <div class="animated-gradient"></div>
    <div class="background-shapes">
        <div class="shape shape-1" data-speed="1.5"></div>
        <div class="shape shape-2" data-speed="1"></div>
        <div class="shape shape-3" data-speed="2"></div>
        <div class="shape shape-4" data-speed="1.2"></div>
        <div class="shape shape-5" data-speed="1.8"></div>
    </div>
    
    <!-- Particles -->
    <div class="particles" id="particles"></div>
    
    <!-- 3D Floating Elements -->
    <div class="floating-elements">
        <div class="floating-element floating-element-1">
            <div class="floating-cube">
                <div class="floating-cube-face">HTML</div>
                <div class="floating-cube-face">CSS</div>
                <div class="floating-cube-face">JS</div>
                <div class="floating-cube-face">React</div>
                <div class="floating-cube-face">Node</div>
                <div class="floating-cube-face">API</div>
            </div>
        </div>
        <div class="floating-element floating-element-2">
            <div class="floating-cube">
                <div class="floating-cube-face">Vue</div>
                <div class="floating-cube-face">Angular</div>
                <div class="floating-cube-face">Svelte</div>
                <div class="floating-cube-face">Next</div>
                <div class="floating-cube-face">Express</div>
                <div class="floating-cube-face">MongoDB</div>
            </div>
        </div>
        <div class="floating-element floating-element-3">
            <div class="floating-cube">
                <div class="floating-cube-face">TypeScript</div>
                <div class="floating-cube-face">GraphQL</div>
                <div class="floating-cube-face">Redux</div>
                <div class="floating-cube-face">Tailwind</div>
                <div class="floating-cube-face">Firebase</div>
                <div class="floating-cube-face">AWS</div>
            </div>
        </div>
    </div>
</div>

<!-- Code Elements -->
<div class="code-element code-element-1" data-rotate="-15deg">
    &lt;div class="profile"&gt;<br>
    &nbsp;&nbsp;&lt;header class="profile-header"&gt;<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&lt;h1&gt;Edit Profile&lt;/h1&gt;<br>
    &nbsp;&nbsp;&lt;/header&gt;
</div>

<div class="code-element code-element-2" data-rotate="10deg">
    function updateProfile() {<br>
    &nbsp;&nbsp;const data = getFormData();<br>
    &nbsp;&nbsp;saveProfile(data);<br>
    &nbsp;&nbsp;return data;<br>
    }
</div>

<div class="code-element code-element-3" data-rotate="5deg">
    .profile-form {<br>
    &nbsp;&nbsp;display: flex;<br>
    &nbsp;&nbsp;flex-direction: column;<br>
    &nbsp;&nbsp;gap: 1.5rem;<br>
    }
</div>

<div class="code-element code-element-4" data-rotate="-5deg">
    import React, { useState } from 'react';<br>
    &nbsp;&nbsp;const [profile, setProfile] = useState({});<br>
    &nbsp;&nbsp;// Update profile data
</div>

<div class="code-element code-element-5" data-rotate="8deg">
    @keyframes float {<br>
    &nbsp;&nbsp;0%, 100% { transform: translateY(0); }<br>
    &nbsp;&nbsp;50% { transform: translateY(-20px); }<br>
    }
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand" href="#">
            <?php if (!empty($settings['site_image'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($settings['site_image']); ?>" alt="<?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?> Logo" style="height: 40px;">
            <?php else: ?>
                <span class="fw-bold"><?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="home-profile.php">
                        <i class="fas fa-home mb-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center active" href="expert-profile.php">
                        <i class="fas fa-user mb-1"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-consultations.php">
                        <i class="fas fa-laptop-code mb-1"></i> Consultations
                        <span class="notification-badge pending-consultations-badge"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-earnings.php">
                        <i class="fas fa-chart-line mb-1"></i> Earnings
                        <span class="notification-badge pending-withdrawals-badge"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-avis.php">
                        <i class="fas fa-star mb-1"></i> Reviews

                        <span class="notification-badge reviews-badge"></span>
                    </a>
                </li>
               
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-contact.php">
                        <i class="fas fa-envelope mb-1"></i> Contact
                        <span class="notification-badge admin-messages-badge"></span>
                    </a>
                </li>
            </ul>
            <div class="nav-user-section">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="position-relative">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user-circle me-2"></i>
                            <?php endif; ?>
                            <span class="notification-badge total-notifications-badge"></span>
                        </div>
                        <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user['full_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">

                    <li><a class="dropdown-item" href="notifications.php"><i class="fa-solid fa-bell"></i> Notifications
                        <span class="notification-badge notifications-not-read-badge" style="margin-top: 10px;margin-right: 10px;"></span></a></li>
                <li><a class="dropdown-item" href="expert-settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../config/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>


<!-- Main Content -->
<div class="container main-container">
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success fade-in">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger fade-in">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="profile-container fade-in glow">
        <div class="profile-header d-flex align-items-center">
            <div class="profile-image-container">
                <?php if (!empty($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-info">
                <h1 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <p class="profile-title">Edit Your Profile</p>
                <p class="profile-location">
                    <i class="fas fa-edit"></i>
                    Update your bio, location link, and social media
                </p>
            </div>
        </div>
        
        <div class="profile-content">
            <form method="POST" action="">
                <div class="row">
                    <!-- Personal Information Section (Read-only) -->
                    <div class="col-md-6 mb-4">
                        <div class="section-title">Personal Information</div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control read-only-field" id="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control read-only-field" id="email" value="<?php echo htmlspecialchars($email); ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" class="form-control read-only-field" id="phone" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="dob" class="form-label">Birth Year</label>
                            <input type="date" class="form-control read-only-field" id="dob" value="<?php echo isset($user['dob']) ? htmlspecialchars($user['dob']) : ''; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" class="form-control read-only-field" id="address" value="<?php echo isset($user['address']) ? htmlspecialchars($user['address']) : ''; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="gender" class="form-label">Gender</label>
                            <input type="text" class="form-control read-only-field" id="gender" value="<?php echo isset($user['gender']) ? ucfirst(htmlspecialchars($user['gender'])) : ''; ?>" readonly>
                        </div>
                        
                        <!-- Bio - Editable -->
                        <div class="mb-3 editable-section">
                            <label for="bio" class="form-label">Bio <span class="badge bg-success">Editable</span></label>
                            <textarea class="form-control" id="bio" name="bio" rows="4" placeholder="Tell us about yourself..."><?php echo isset($user['bio']) ? htmlspecialchars($user['bio']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Professional Information Section (Read-only) -->
                    <div class="col-md-6 mb-4">
                        <div class="section-title">Professional Information</div>
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <input type="text" class="form-control read-only-field" id="category_display" value="<?php echo isset($profile['category']) && isset($categories[$profile['category']]) ? htmlspecialchars($categories[$profile['category']]) : ''; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="subcategory" class="form-label">Subcategory</label>
                            <input type="text" class="form-control read-only-field" id="subcategory_display" value="<?php echo isset($profile['subcategory']) && isset($profile['category']) && isset($subcategories[$profile['category']][$profile['subcategory']]) ? htmlspecialchars($subcategories[$profile['category']][$profile['subcategory']]) : ''; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="city" class="form-label">City</label>
                            <input type="text" class="form-control read-only-field" id="city_display" value="<?php echo isset($profile['city']) && isset($cities[$profile['city']]) ? htmlspecialchars($cities[$profile['city']]) : ''; ?>" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="skills_display" class="form-label">Skills</label>
                            <div class="skills-container">
                                <?php if (!empty($skills)): ?>
                                    <?php foreach ($skills as $skill): ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted">No skills added yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        
                    </div>
                    
                    <!-- Social Media Links Section - Editable -->
                    <div class="col-12 mb-4 editable-section">
                        <div class="section-title">Social Media Links <span class="badge bg-success">Editable</span></div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="facebook" class="form-label"><i class="fab fa-facebook me-2"></i>Facebook</label>
                                <input type="url" class="form-control" id="facebook" name="facebook" placeholder="https://facebook.com/username" value="<?php echo isset($social_links['facebook_url']) ? htmlspecialchars($social_links['facebook_url']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="instagram" class="form-label"><i class="fab fa-instagram me-2"></i>Instagram</label>
                                <input type="url" class="form-control" id="instagram" name="instagram" placeholder="https://instagram.com/username" value="<?php echo isset($social_links['instagram_url']) ? htmlspecialchars($social_links['instagram_url']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="linkedin" class="form-label"><i class="fab fa-linkedin me-2"></i>LinkedIn</label>
                                <input type="url" class="form-control" id="linkedin" name="linkedin" placeholder="https://linkedin.com/in/username" value="<?php echo isset($social_links['linkedin_url']) ? htmlspecialchars($social_links['linkedin_url']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="github" class="form-label"><i class="fab fa-github me-2"></i>GitHub</label>
                                <input type="url" class="form-control" id="github" name="github" placeholder="https://github.com/username" value="<?php echo isset($social_links['github_url']) ? htmlspecialchars($social_links['github_url']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="website" class="form-label"><i class="fas fa-globe me-2"></i>Personal Website</label>
                                <input type="url" class="form-control" id="website" name="website" placeholder="https://yourwebsite.com" value="<?php echo isset($social_links['website_url']) ? htmlspecialchars($social_links['website_url']) : ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="twitter" class="form-label"><i class="fab fa-twitter me-2"></i>Twitter</label>
                                <input type="url" class="form-control" id="twitter" name="twitter" placeholder="https://twitter.com/username" value="<?php echo isset($social_links['twitter_url']) ? htmlspecialchars($social_links['twitter_url']) : ''; ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Credentials Section (Read-only) -->
                    <div class="col-12 mb-4">
                        <div class="section-title">Professional Credentials</div>
                        
                        <!-- Certificates -->
                        <div class="mb-4">
                            <h5 class="mb-3">Certificates</h5>
                            <?php if (empty($certificates)): ?>
                                <p class="text-muted">No approved certificates found. You can add certificates in the profile details section.</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($certificates as $cert): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($cert['institution']); ?></h6>
                                                    <p class="card-text text-muted">
                                                        <?php 
                                                            $start_date = new DateTime($cert['start_date']);
                                                            $end_date = new DateTime($cert['end_date']);
                                                            echo $start_date->format('M Y') . ' - ' . $end_date->format('M Y');
                                                        ?>
                                                    </p>
                                                    <p class="card-text"><?php echo htmlspecialchars($cert['description']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Experiences -->
                        <div class="mb-4">
                            <h5 class="mb-3">Work Experience</h5>
                            <?php if (empty($experiences)): ?>
                                <p class="text-muted">No approved work experiences found. You can add experiences in the profile details section.</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($experiences as $exp): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($exp['workplace']); ?></h6>
                                                    <p class="card-text text-muted">
                                                        <?php 
                                                            $start_date = new DateTime($exp['start_date']);
                                                            $end_date = !empty($exp['end_date']) ? new DateTime($exp['end_date']) : null;
                                                            echo $start_date->format('M Y') . ' - ' . ($end_date ? $end_date->format('M Y') : 'Present');
                                                        ?>
                                                        <span class="ms-2">
                                                            (<?php echo $exp['duration_years']; ?> years, <?php echo $exp['duration_months']; ?> months)
                                                        </span>
                                                    </p>
                                                    <p class="card-text"><?php echo htmlspecialchars($exp['description']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Formations (Courses) -->
                        <div class="mb-4">
                            <h5 class="mb-3">Courses & Training</h5>
                            <?php if (empty($formations)): ?>
                                <p class="text-muted">No approved courses or trainings found. You can add courses in the profile details section.</p>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($formations as $form): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($form['formation_name']); ?></h6>
                                                    <p class="card-text text-muted">
                                                        <?php echo htmlspecialchars(ucfirst($form['formation_type'])); ?> - 
                                                        <?php echo htmlspecialchars($form['formation_year']); ?>
                                                    </p>
                                                    <p class="card-text"><?php echo htmlspecialchars($form['description']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <div class="col-12 text-center">
                        <button type="submit" name="update_profile" class="btn btn-primary btn-lg px-5">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="container footer-content">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5>About <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></h5>
                <p class="mb-4"><?php echo htmlspecialchars($settings['site_description'] ?? 'Expert Consultation Platform connecting experts with clients for professional consultations.'); ?></p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="home-profile.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="expert-profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="expert-consultations.php"><i class="fas fa-laptop-code"></i> Consultations</a></li>
                    <li><a href="expert-earnings.php"><i class="fas fa-chart-line"></i> Earnings</a></li>
                    <li><a href="expert-avis.php"><i class="fas fa-star"></i> Reviews</a></li>
                    <li><a href="expert-experts.php"><i class="fas fa-users"></i> Experts</a></li>
                    <li><a href="expert-contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Contact</h5>
                <ul class="footer-links">
                    <?php if (!empty($settings['site_name'])): ?>
                        <li><i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($settings['site_name']); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['site_email'])): ?>
                        <li><a href="mailto:<?php echo htmlspecialchars($settings['site_email']); ?>"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($settings['site_email']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['phone_number1'])): ?>
                        <li><a href="tel:<?php echo htmlspecialchars($settings['phone_number1']); ?>"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($settings['phone_number1']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['phone_number2'])): ?>
                        <li><a href="tel:<?php echo htmlspecialchars($settings['phone_number2']); ?>"><i class="fas fa-phone-alt me-2"></i> <?php echo htmlspecialchars($settings['phone_number2']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['facebook_url'])): ?>
                        <li><a href="<?php echo htmlspecialchars($settings['facebook_url']); ?>" target="_blank"><i class="fab fa-facebook me-2"></i> Facebook</a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['instagram_url'])): ?>
                        <li><a href="<?php echo htmlspecialchars($settings['instagram_url']); ?>" target="_blank"><i class="fab fa-instagram me-2"></i> Instagram</a></li>
                    <?php endif; ?>
                </ul>
                <p class="mt-3 mb-0">Need help? <a href="expert-contact.php" class="text-primary font-weight-bold">Contact Us</a></p>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['site_name'] ?? ' '); ?>. All rights reserved. </p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize animations and effects
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch notifications function
    function fetchNotifications() {
        fetch('edit-profile.php?fetch_notifications=true')
            .then(response => response.json())
            .then(data => {
                // Update notification badges
                updateNotificationBadge('.pending-consultations-badge', data.pending_consultations);
                updateNotificationBadge('.pending-withdrawals-badge', data.pending_withdrawals);
                updateNotificationBadge('.admin-messages-badge', data.admin_messages);
                updateNotificationBadge('.community-messages-badge', data.community_messages);
                updateNotificationBadge('.forums_messages-badge', data.forums_messages);
                updateNotificationBadge('.reviews-badge', data.reviews);
                updateNotificationBadge('.notifications-not-read-badge', data.notifications_not_read);
                updateNotificationBadge('.total-notifications-badge', data.total);
            })
            .catch(error => console.error('Error fetching notifications:', error));
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
    
        // Add animation classes on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, {
            threshold: 0.1
        });
        
        document.querySelectorAll('.card, .editable-section').forEach(el => {
            observer.observe(el);
        });
        
        // Create floating code elements
        function createRandomCodeElements() {
            const codeTexts = [
                'const profile = updateProfile();',
                'function saveUserData() { }',
                'import { useState } from "react";',
                '.profile-form { display: flex; }',
                'export default ProfileEditor;',
                'async function fetchUserData() { }',
                '<div className="profile-editor">',
                'npm install @vercel/earnings',
                'git commit -m "Update profile"',
                'const [profile, setProfile] = useState(null);'
            ];
            
            const container = document.querySelector('.background-container');
            
            for (let i = 0; i < 5; i++) {
                const element = document.createElement('div');
                element.className = 'code-element';
                element.style.top = Math.random() * 100 + '%';
                element.style.left = Math.random() * 100 + '%';
                element.style.transform = `rotate(${Math.random() * 20 - 10}deg)`;
                element.style.opacity = 0.1 + Math.random() * 0.1;
                element.style.animation = `fadeInOut ${8 + Math.random() * 8}s infinite ease-in-out ${Math.random() * 5}s`;
                element.textContent = codeTexts[Math.floor(Math.random() * codeTexts.length)];
                container.appendChild(element);
            }
        }
        
        // Create particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random position
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                
                // Random size
                const size = Math.random() * 4 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                
                // Random color
                const colors = [
                    'rgba(99, 102, 241, 0.3)',
                    'rgba(139, 92, 246, 0.3)',
                    'rgba(6, 182, 212, 0.3)',
                    'rgba(16, 185, 129, 0.3)'
                ];
                particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                
                // Random animation
                const tx = (Math.random() - 0.5) * 300;
                const ty = (Math.random() - 0.5) * 300;
                particle.style.setProperty('--tx', tx + 'px');
                particle.style.setProperty('--ty', ty + 'px');
                
                // Random animation duration
                const duration = Math.random() * 15 + 10;
                particle.style.animationDuration = duration + 's';
                
                // Random animation delay
                const delay = Math.random() * 5;
                particle.style.animationDelay = delay + 's';
                
                particlesContainer.appendChild(particle);
            }
        }
        
        // Only create these elements on desktop
        if (window.innerWidth > 768) {
            createRandomCodeElements();
            createParticles();
        }
        
        // Add subtle parallax effect to background elements
        if (window.innerWidth > 768) {
            document.addEventListener('mousemove', (e) => {
                const moveX = (e.clientX - window.innerWidth / 2) / 30;
                const moveY = (e.clientY - window.innerHeight / 2) / 30;
                
                document.querySelectorAll('.shape').forEach((shape) => {
                    const speed = parseFloat(shape.getAttribute('data-speed') || 1);
                    shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
                });
                
                document.querySelectorAll('.code-element').forEach((element) => {
                    const speed = 0.8;
                    const rotate = element.getAttribute('data-rotate') || '0deg';
                    element.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px) rotate(${rotate})`;
                });
                
                document.querySelectorAll('.floating-element').forEach((element) => {
                    const speed = 1.2;
                    element.style.transform = `translateX(${moveX * speed}px) translateY(${moveY * speed}px) rotateX(${moveY}deg) rotateY(${-moveX}deg)`;
                });
            });
        }
        
        // Add glowing effect on hover
        document.querySelectorAll('.glow').forEach(element => {
            element.addEventListener('mouseenter', () => {
                element.classList.add('glowing');
            });
            element.addEventListener('mouseleave', () => {
                element.classList.remove('glowing');
            });
        });
    });
</script>
</body>
</html>
