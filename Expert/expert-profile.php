<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';
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
// Get all users with their profile information
// Get all users with their profile information and suspension count

// Get user data
$sql = "SELECT u.*, up.phone, up.address, up.profile_image, up.bio ,
        (SELECT COUNT(*) FROM reports WHERE reported_id = u.id AND status IN ('remborser','accepted')) AS reports_count,
        (SELECT COUNT(*) FROM user_suspensions WHERE user_id = u.id) AS suspension_count
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Get site settings
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

// Get expert profile data
$profile_sql = "SELECT * FROM expert_profiledetails WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$profile_id = $profile ? $profile['user_id'] : 0;

$ratings = [
    'average' => 0,
    'total_count' => 0,
    'distribution' => [
        5 => 0,
        4 => 0,
        3 => 0,
        2 => 0,
        1 => 0
    ]
];

if ($profile_id) {
    // Get average rating
    $rating_sql = "SELECT AVG(rating) as average_rating, COUNT(*) as total_ratings FROM expert_ratings WHERE expert_id = ?";
    $rating_stmt = $conn->prepare($rating_sql);
    $rating_stmt->bind_param("i", $profile_id);
    $rating_stmt->execute();
    $rating_result = $rating_stmt->get_result();
    $rating_data = $rating_result->fetch_assoc();
    
    if ($rating_data && $rating_data['total_ratings'] > 0) {
        $ratings['average'] = round($rating_data['average_rating'], 1);
        $ratings['total_count'] = $rating_data['total_ratings'];
        
        // Get rating distribution
        $dist_sql = "SELECT rating, COUNT(*) as count FROM expert_ratings WHERE expert_id = ? GROUP BY rating ORDER BY rating DESC";
        $dist_stmt = $conn->prepare($dist_sql);
        $dist_stmt->bind_param("i", $profile_id);
        $dist_stmt->execute();
        $dist_result = $dist_stmt->get_result();
        
        while ($row = $dist_result->fetch_assoc()) {
            $ratings['distribution'][$row['rating']] = $row['count'];
        }
    }
}

// Get certificates
$certificates = [];
if ($profile_id) {
    $cert_sql = "SELECT * FROM certificates WHERE profile_id = ? ORDER BY id";
    $cert_stmt = $conn->prepare($cert_sql);
    $cert_stmt->bind_param("i", $profile_id);
    $cert_stmt->execute();
    $cert_result = $cert_stmt->get_result();
    while ($row = $cert_result->fetch_assoc()) {
        // Make sure status is set
        if (!isset($row['status'])) {
            $row['status'] = 'pending';
        }
        // Make sure rejection_reason is set
        if (!isset($row['rejection_reason'])) {
            $row['rejection_reason'] = '';
        }
        $certificates[] = $row;
    }
}

// Get experiences
$experiences = [];
if ($profile_id) {
    $exp_sql = "SELECT * FROM experiences WHERE profile_id = ? ORDER BY id";
    $exp_stmt = $conn->prepare($exp_sql);
    $exp_stmt->bind_param("i", $profile_id);
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();
    while ($row = $exp_result->fetch_assoc()) {
        // Make sure status is set
        if (!isset($row['status'])) {
            $row['status'] = 'pending';
        }
        // Add rejection_reason from session if available
        $row['rejection_reason'] = isset($_SESSION["experience_reasons"][$row['id']]) ? 
                                  $_SESSION["experience_reasons"][$row['id']] : '';
        $experiences[] = $row;
    }
}

// Get formations (courses)
$formations = [];
if ($profile_id) {
    $form_sql = "SELECT * FROM formations WHERE profile_id = ? ORDER BY id";
    $form_stmt = $conn->prepare($form_sql);
    $form_stmt->bind_param("i", $profile_id);
    $form_stmt->execute();
    $form_result = $form_stmt->get_result();
    while ($row = $form_result->fetch_assoc()) {
        // Make sure status is set
        if (!isset($row['status'])) {
            $row['status'] = 'pending';
        }
        // Add rejection_reason from session if available
        $row['rejection_reason'] = isset($_SESSION["formation_reasons"][$row['id']]) ? 
                                  $_SESSION["formation_reasons"][$row['id']] : '';
        $formations[] = $row;
    }
}

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

// Get social media links
$social_links = null;
if ($profile_id) {
    $social_sql = "SELECT * FROM expert_social_links WHERE profile_id = ? AND user_id = ?";
    $social_stmt = $conn->prepare($social_sql);
    $social_stmt->bind_param("ii", $profile_id, $user_id);
    $social_stmt->execute();
    $social_result = $social_stmt->get_result();
    $social_links = $social_result->fetch_assoc();
}

// Get banking information
$banking = null;
if ($profile_id) {
    $banking_sql = "SELECT * FROM banking_information WHERE profile_id = ? AND user_id = ?";
    $banking_stmt = $conn->prepare($banking_sql);
    $banking_stmt->bind_param("ii", $profile_id, $user_id);
    $banking_stmt->execute();
    $banking_result = $banking_stmt->get_result();
    $banking = $banking_result->fetch_assoc();
}

// Get notifications
$notifications = [];
$notif_sql = "SELECT * FROM expert_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get category and city names
$category_name = "";
$subcategory_name = "";
$city_name = "";

if ($profile && !empty($profile['category'])) {
    $cat_sql = "SELECT name FROM categories WHERE id = ?";
    $cat_stmt = $conn->prepare($cat_sql);
    $cat_stmt->bind_param("i", $profile['category']);
    $cat_stmt->execute();
    $cat_result = $cat_stmt->get_result();
    if ($cat_row = $cat_result->fetch_assoc()) {
        $category_name = $cat_row['name'];
    }
}

if ($profile && !empty($profile['subcategory'])) {
    $subcat_sql = "SELECT name FROM subcategories WHERE id = ?";
    $subcat_stmt = $conn->prepare($subcat_sql);
    $subcat_stmt->bind_param("i", $profile['subcategory']);
    $subcat_stmt->execute();
    $subcat_result = $subcat_stmt->get_result();
    if ($subcat_row = $subcat_result->fetch_assoc()) {
        $subcategory_name = $subcat_row['name'];
    }
}

if ($profile && !empty($profile['city'])) {
    $city_sql = "SELECT name FROM cities WHERE id = ?";
    $city_stmt = $conn->prepare($city_sql);
    $city_stmt->bind_param("i", $profile['city']);
    $city_stmt->execute();
    $city_result = $city_stmt->get_result();
    if ($city_row = $city_result->fetch_assoc()) {
        $city_name = $city_row['name'];
    }
}

// Handle profile image upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_profile_image"])) {
    if (isset($_FILES["profile_image"]) && $_FILES["profile_image"]["error"] == 0) {
        $allowed_types = ["image/jpeg", "image/jpg", "image/png"];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($_FILES["profile_image"]["type"], $allowed_types) && $_FILES["profile_image"]["size"] <= $max_size) {
            $upload_dir = "../uploads/profile_images/";
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = $user_id . "_" . time() . "_" . basename($_FILES["profile_image"]["name"]);
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES["profile_image"]["tmp_name"], $target_file)) {
                // Update profile image in database
                $update_sql = "UPDATE user_profiles SET profile_image = ? WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $target_file, $user_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Profile image updated successfully!";
                    // Update the user data
                    $user['profile_image'] = $target_file;
                } else {
                    $error_message = "Error updating profile image in database.";
                }
            } else {
                $error_message = "Error uploading profile image.";
            }
        } else {
            $error_message = "Invalid file type or size. Please upload a JPG or PNG image under 5MB.";
        }
    }
}

// Function to get status badge HTML
function getStatusBadge($status) {
    if (!isset($status)) {
        $status = 'pending';
    }
    
    switch ($status) {
        case 'approved':
            return '<span class="badge bg-success">Approved</span>';
        case 'pending':
        case 'pending_review':
            return '<span class="badge bg-warning text-dark">Pending Review</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}

// Function to generate star rating HTML
function generateStarRating($rating) {
    $fullStars = floor($rating);
    $halfStar = ($rating - $fullStars) >= 0.5;
    $emptyStars = 5 - $fullStars - ($halfStar ? 1 : 0);
    
    $html = '';
    
    // Full stars
    for ($i = 0; $i < $fullStars; $i++) {
        $html .= '<i class="fas fa-star text-warning"></i>';
    }
    
    // Half star
    if ($halfStar) {
        $html .= '<i class="fas fa-star-half-alt text-warning"></i>';
    }
    
    // Empty stars
    for ($i = 0; $i < $emptyStars; $i++) {
        $html .= '<i class="far fa-star text-warning"></i>';
    }
    
    return $html;
}

// Function to calculate percentage for rating bars
function calculateRatingPercentage($count, $total) {
    if ($total == 0) return 0;
    return ($count / $total) * 100;
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
        'total' => $pending_consultations_count + $pending_withdrawals_count  + $admin_messages_count + $reviews_not_read_count + $notifictaions_not_read_count,
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
    <title>Web Developer Profile - Consult Pro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=JetBrains+Mono:wght@400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
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
                    --primary-gradient: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            --radius-full: 9999px;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);

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

        0%,
        100% {
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

    .floating-cube-face:nth-child(1) {
        transform: translateZ(40px);
    }

    .floating-cube-face:nth-child(2) {
        transform: rotateY(180deg) translateZ(40px);
    }

    .floating-cube-face:nth-child(3) {
        transform: rotateY(90deg) translateZ(40px);
    }

    .floating-cube-face:nth-child(4) {
        transform: rotateY(-90deg) translateZ(40px);
    }

    .floating-cube-face:nth-child(5) {
        transform: rotateX(90deg) translateZ(40px);
    }

    .floating-cube-face:nth-child(6) {
        transform: rotateX(-90deg) translateZ(40px);
    }

    @keyframes float-3d {

        0%,
        100% {
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

        0%,
        100% {
            opacity: 0.1;
        }

        50% {
            opacity: 0.3;
        }
    }

    @keyframes float-code {

        0%,
        100% {
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

    .navbar-light .navbar-nav .active>.nav-link,
    .navbar-light .navbar-nav .nav-link.active {
        color: var(--primary-color);
        background: rgba(99, 102, 241, 0.1);
        font-weight: 600;
    }

    .navbar-light .navbar-nav .active>.nav-link i,
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
        background-color: var(--danger-color);
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

    /* Profile Container */
    .profile-container {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: var(--card-shadow);
        margin-top: 2rem;
        margin-bottom: 2rem;
        overflow: hidden;
        border: 1px solid rgba(226, 232, 240, 0.7);
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .profile-container:hover {
        transform: translateY(-10px);
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
    }

    .profile-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
        color: white;
        padding: 2.5rem;
        position: relative;
        overflow: hidden;
    }

    .profile-header::before {
        content: '';
        position: absolute;
        top: -50px;
        right: -50px;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
        z-index: 0;
    }

    .profile-header::after {
        content: '';
        position: absolute;
        bottom: -80px;
        left: -80px;
        width: 250px;
        height: 250px;
        background: rgba(255, 255, 255, 0.05);
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
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        object-fit: cover;
        background-color: #fff;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .profile-container:hover .profile-avatar {
        transform: scale(1.05);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
    }

    .profile-avatar-placeholder {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        border: 4px solid white;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        background-color: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 3rem;
        color: white;
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .profile-container:hover .profile-avatar-placeholder {
        transform: scale(1.05);
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
    }

    .edit-profile-image {
        position: absolute;
        bottom: 0;
        right: 0;
        background: linear-gradient(135deg, var(--secondary-color), var(--secondary-light));
        color: white;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 0.9rem;
        border: 2px solid white;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
        z-index: 2;
    }

    .edit-profile-image:hover {
        transform: scale(1.1) rotate(15deg);
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
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .profile-title {
        font-size: 1.2rem;
        opacity: 0.9;
        margin-bottom: 0.75rem;
        font-weight: 500;
    }

    .profile-location {
        display: flex;
        align-items: center;
        font-size: 1rem;
        opacity: 0.8;
        margin-bottom: 1rem;
    }

    .profile-location i {
        margin-right: 0.5rem;
        font-size: 1.1rem;
    }

    .profile-actions {
        display: flex;
        gap: 0.75rem;
    }

    .profile-actions .btn {
        padding: 0.6rem 1.2rem;
        border-radius: 12px;
        font-weight: 500;
        transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

    .profile-actions .btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    }

    .profile-actions .btn i {
        margin-right: 0.5rem;
    }

    .profile-status {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        z-index: 1;
    }

    .profile-content {
        padding: 2rem;
    }

    /* Upload Form */
    .upload-form {
        display: none;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 15px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(226, 232, 240, 0.7);
        margin-top: 1rem;
        padding: 1.5rem;
        transition: all 0.3s ease;
    }

    /* Rating Overview */
    .rating-overview {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border-radius: 15px;
        padding: 2rem;
        margin: 0 2rem;
        margin-top: -1.5rem;
        position: relative;
        z-index: 2;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        border: 1px solid rgba(226, 232, 240, 0.7);
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .rating-overview:hover {
        transform: translateY(-5px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    }

    .rating-score {
        font-size: 4rem;
        font-weight: 700;
        line-height: 1;
        color: var(--dark-color);
        font-family: var(--code-font);
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        text-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }

    .rating-stars {
        font-size: 1.5rem;
        margin: 0.75rem 0;
    }

    .rating-count {
        color: var(--text-muted);
        font-size: 1rem;
        margin-bottom: 1rem;
        font-weight: 500;
    }

    .rating-bar-container {
        display: flex;
        align-items: center;
        margin-bottom: 0.75rem;
    }

    .rating-bar-label {
        width: 30px;
        text-align: right;
        margin-right: 10px;
        font-weight: 600;
        font-family: var(--code-font);
        color: var(--dark-color);
    }

    .rating-bar {
        flex-grow: 1;
        height: 10px;
        background-color: rgba(226, 232, 240, 0.7);
        border-radius: 5px;
        overflow: hidden;
        position: relative;
    }

    .rating-bar-fill {
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        border-radius: 5px;
        transition: width 1.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    /* Tabs */
    .nav-tabs {
        border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        margin-bottom: 1.5rem;
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }

    .nav-tabs::-webkit-scrollbar {
        display: none;
    }

    .nav-tabs .nav-link {
        border: none;
        color: var(--text-muted);
        font-weight: 500;
        padding: 1rem 1.5rem;
        position: relative;
        transition: all 0.3s ease;
        white-space: nowrap;
    }

    .nav-tabs .nav-link::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        transform: scaleX(0);
        transform-origin: left;
        transition: transform 0.3s ease;
        border-radius: 3px 3px 0 0;
    }

    .nav-tabs .nav-link:hover {
        color: var(--primary-color);
    }

    .nav-tabs .nav-link.active {
        color: var(--primary-color);
        background-color: transparent;
        font-weight: 600;
    }

    .nav-tabs .nav-link.active::after {
        transform: scaleX(1);
    }

    .tab-content {
        padding: 1.5rem 0;
    }

    /* Section Styles */
    .section-title {
        font-size: 1.4rem;
        font-weight: 600;
        color: var(--dark-color);
        margin-bottom: 1.5rem;
        padding-bottom: 0.75rem;
        position: relative;
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
        transition: width 0.3s ease;
    }

    .section-title:hover::after {
        width: 100px;
    }

    .info-item {
        margin-bottom: 1.5rem;
    }

    .info-label {
        font-weight: 600;
        color: var(--text-muted);
        margin-bottom: 0.5rem;
        font-size: 0.95rem;
    }

    .info-value {
        color: var(--text-color);
        font-size: 1.05rem;
    }

    /* Skills */
    .skills-container {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
        margin-top: 0.5rem;
    }

    .skill-tag {
        background: linear-gradient(45deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
        color: var(--primary-color);
        padding: 0.4rem 1rem;
        border-radius: 50px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: all 0.3s ease;
        border: 1px solid rgba(99, 102, 241, 0.2);
    }

    .skill-tag:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(99, 102, 241, 0.2);
        background: linear-gradient(45deg, rgba(99, 102, 241, 0.2), rgba(139, 92, 246, 0.2));
    }

    /* Cards */
    .card {
        border: none;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        margin-bottom: 1.5rem;
        overflow: hidden;
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.7);
        transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
    }

    .card:hover {
        transform: translateY(-10px);
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
    }

    .card-header {
        background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(241, 245, 249, 0.8));
        border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        padding: 1.25rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-title {
        margin-bottom: 0;
        font-size: 1.2rem;
        font-weight: 600;
        color: var(--dark-color);
    }

    .card-body {
        padding: 1.5rem;
    }

    /* Item Styles */
    .item-date {
        color: var(--text-muted);
        font-size: 0.9rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
    }

    .item-date i {
        margin-right: 0.5rem;
        color: var(--primary-color);
    }

    .item-subtitle {
        color: var(--text-muted);
        font-size: 0.95rem;
        margin-bottom: 0.75rem;
    }

    .item-description {
        color: var(--text-color);
        font-size: 1rem;
        line-height: 1.6;
        margin-bottom: 1rem;
    }

    /* Badges */
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

    .bg-warning {
        background: linear-gradient(to right, var(--warning-color), #fbbf24) !important;
    }

    .bg-danger {
        background: linear-gradient(to right, var(--danger-color), #f87171) !important;
        color: white !important;
    }

    .bg-secondary {
        background: linear-gradient(to right, #64748b, #94a3b8) !important;
        color: white !important;
    }

    /* Rejection Reason */
    .rejection-reason {
        background-color: rgba(239, 68, 68, 0.1);
        border: 1px solid rgba(239, 68, 68, 0.2);
        border-radius: 10px;
        color: var(--danger-color);
        padding: 1rem;
        margin-top: 1rem;
        font-size: 0.95rem;
    }

    /* Notifications */
    .notifications-container {
        max-height: 500px;
        overflow-y: auto;
        border-radius: 15px;
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(226, 232, 240, 0.7);
    }

    .notification-item {
        padding: 1.25rem;
        border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        transition: all 0.3s ease;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-item:hover {
        background-color: rgba(99, 102, 241, 0.05);
        transform: translateX(5px);
    }

    .notification-unread {
        background-color: rgba(99, 102, 241, 0.1);
        position: relative;
    }

    .notification-unread::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
        border-radius: 0 4px 4px 0;
    }

    .notification-content {
        font-size: 1rem;
        color: var(--text-color);
        line-height: 1.6;
    }

    .notification-time {
        font-size: 0.85rem;
        color: var(--text-muted);
        margin-top: 0.75rem;
        display: flex;
        align-items: center;
    }

    .notification-time i {
        margin-right: 0.5rem;
        color: var(--primary-color);
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
    /* Alerts */
    .alert {
        border-radius: 15px;
        padding: 1.25rem;
        margin-bottom: 1.5rem;
        border: none;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        position: relative;
        overflow: hidden;
    }

    .alert::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
    }

    .alert-success {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
    }

    .alert-success::before {
        background: linear-gradient(to bottom, var(--success-color), #34d399);
    }

    .alert-warning {
        background-color: rgba(245, 158, 11, 0.1);
        color: var(--warning-color);
    }

    .alert-warning::before {
        background: linear-gradient(to bottom, var(--warning-color), #fbbf24);
    }

    .alert-danger {
        background-color: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
    }

    .alert-danger::before {
        background: linear-gradient(to bottom, var(--danger-color), #f87171);
    }

    .alert-info {
        background-color: rgba(59, 130, 246, 0.1);
        color: var(--info-color);
    }

    .alert-info::before {
        background: linear-gradient(to bottom, var(--info-color), #60a5fa);
    }

    .alert i {
        margin-right: 0.75rem;
        font-size: 1.1rem;
    }

    /* Buttons */
    .btn {
        border-radius: 12px;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
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

    .btn:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .btn-primary {
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        border: none;
        color: white;
        box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
    }

    .btn-primary:hover {
        background: linear-gradient(to right, var(--primary-dark), var(--accent-dark));
        border: none;
        color: white;
        box-shadow: 0 15px 30px rgba(99, 102, 241, 0.3);
    }

    .btn-secondary {
        background: linear-gradient(to right, var(--secondary-color), var(--secondary-light));
        border: none;
        color: white;
        box-shadow: 0 10px 20px rgba(6, 182, 212, 0.2);
    }

    .btn-secondary:hover {
        background: linear-gradient(to right, var(--secondary-dark), var(--secondary-color));
        border: none;
        color: white;
        box-shadow: 0 15px 30px rgba(6, 182, 212, 0.3);
    }

    .btn-outline-light {
        color: white;
        border: 2px solid rgba(255, 255, 255, 0.7);
        background: transparent;
    }

    .btn-outline-light:hover {
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border-color: white;
    }

    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .btn-outline-primary {
        color: var(--primary-color);
        border: 2px solid var(--primary-color);
        background: transparent;
    }

    .btn-outline-primary:hover {
        background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        color: white;
        border-color: transparent;
    }

    /* Footer */
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
        font-size: 0.9rem;
        color: rgba(0, 0, 0, 0.6);
    }

    /* Code elements */
    .code-tag {
        font-family: var(--code-font);
        color: var(--primary-color);
        background-color: rgba(99, 102, 241, 0.1);
        padding: 0.2rem 0.4rem;
        border-radius: 6px;
        font-size: 0.9rem;
        box-shadow: 0 2px 5px rgba(99, 102, 241, 0.1);
    }

    /* Responsive Styles */
    @media (max-width: 991.98px) {
        .profile-header {
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding: 2rem 1.5rem;
        }

        .profile-info {
            margin-left: 0;
            margin-top: 1.5rem;
        }

        .profile-status {
            position: static;
            margin-top: 1.5rem;
        }

        .rating-score {
            font-size: 3rem;
        }

        .rating-stars {
            font-size: 1.2rem;
        }

        .code-element {
            display: none;
        }

        .floating-elements {
            display: none;
        }
    }

    @media (max-width: 767.98px) {
        .profile-container {
            margin-top: 1.5rem;
        }

        .profile-header {
            padding: 1.5rem 1rem;
        }

        .profile-name {
            font-size: 1.8rem;
        }

        .profile-title {
            font-size: 1.1rem;
        }

        .rating-overview {
            margin: 0 1rem;
            margin-top: -1rem;
            padding: 1.5rem;
        }

        .section-title {
            font-size: 1.2rem;
        }

        .card-header {
            padding: 1rem;
        }

        .card-body {
            padding: 1.25rem;
        }

        .shape {
            opacity: 0.05;
        }
    }

    /* Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

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
        &lt;div class="container"&gt;<br>
        &nbsp;&nbsp;&lt;header class="header"&gt;<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&lt;h1&gt;Welcome Developer&lt;/h1&gt;<br>
        &nbsp;&nbsp;&lt;/header&gt;
    </div>

    <div class="code-element code-element-2" data-rotate="10deg">
        function initDashboard() {<br>
        &nbsp;&nbsp;const data = fetchUserData();<br>
        &nbsp;&nbsp;renderStats(data);<br>
        &nbsp;&nbsp;return data;<br>
        }
    </div>

    <div class="code-element code-element-3" data-rotate="5deg">
        .dashboard {<br>
        &nbsp;&nbsp;display: flex;<br>
        &nbsp;&nbsp;flex-direction: column;<br>
        &nbsp;&nbsp;gap: 1.5rem;<br>
        }
    </div>

    <div class="code-element code-element-4" data-rotate="-5deg">
        import React, { useState, useEffect } from 'react';<br>
        &nbsp;&nbsp;const [isLoading, setIsLoading] = useState(false);<br>
        &nbsp;&nbsp;// Fetch data from API
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
                <img src="../uploads/<?php echo htmlspecialchars($settings['site_image']); ?>"
                    alt="<?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?> Logo"
                    style="height: 40px;">
                <?php else: ?>
                <span class="fw-bold"><?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></span>
                <?php endif; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown"
                            role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="position-relative">
                                <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile"
                                    class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                                <?php else: ?>
                                <i class="fas fa-user-circle me-2"></i>
                                <?php endif; ?>
                                <span class="notification-badge total-notifications-badge"></span>
                            </div>
                            <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                            <li><a class="dropdown-item" href="notifications.php"><i class="fa-solid fa-bell"></i>
                                    Notifications
                                    <span class="notification-badge notifications-not-read-badge"
                                        style="margin-top: 10px;margin-right: 10px;"></span></a></li>
                            <li><a class="dropdown-item" href="expert-settings.php"><i class="fas fa-cog me-2"></i>
                                    Settings</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="../config/logout.php"><i
                                        class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-container">
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success mt-3 fade-in">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger mt-3 fade-in">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <div class="profile-container fade-in glow">
            <div class="profile-header d-flex align-items-center">
                <div class="profile-image-container">
                    <?php if (!empty($user['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile"
                        class="profile-avatar">
                    <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <i class="fas fa-user"></i>
                    </div>
                    <?php endif; ?>
                    <div class="edit-profile-image" onclick="toggleUploadForm()">
                        <i class="fas fa-camera"></i>
                    </div>
                </div>

                <div class="profile-info">
                    <h1 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h1>
                    <p class="profile-title">
                        <?php echo !empty($category_name) ? htmlspecialchars($category_name) : 'Web Developer'; ?><?php echo !empty($subcategory_name) ? ' - ' . htmlspecialchars($subcategory_name) : ''; ?>
                    </p>
                    <p class="profile-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo !empty($city_name) ? htmlspecialchars($city_name) : ''; ?>
                    </p>
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
                    <div class="profile-actions">
                        <a href="edit-profile.php" class="btn btn-sm btn-outline-light">
                            <i class="fas fa-edit"></i> Edit Profile
                        </a>
                        <a href="expert-consultations.php" class="btn btn-sm btn-secondary">
                            <i class="fas fa-calendar-check"></i> View Consultations
                        </a>
                    </div>
                </div>

                <div class="profile-status">
                    <?php if ($profile): ?>
                    <?php echo getStatusBadge(isset($profile['status']) ? $profile['status'] : 'pending'); ?>
                    <?php else: ?>
                    <span class="badge bg-warning text-dark">Incomplete</span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upload Profile Image Form -->
            <form id="uploadForm" class="upload-form" method="POST" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="profile_image" class="form-label">Select new profile image</label>
                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/*"
                        required>
                    <div class="form-text">Max file size: 5MB. Supported formats: JPG, PNG.</div>
                </div>
                <button type="submit" name="update_profile_image" class="btn btn-primary">
                    <i class="fas fa-upload me-2"></i> Upload
                </button>
                <button type="button" class="btn btn-secondary ms-2" onclick="toggleUploadForm()">
                    <i class="fas fa-times me-2"></i> Cancel
                </button>
            </form>

            <!-- Rating Overview Section -->
            <div class="rating-overview fade-in delay-1">
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="rating-score"><?php echo number_format($ratings['average'], 1); ?></div>
                        <div class="rating-stars">
                            <?php echo generateStarRating($ratings['average']); ?>
                        </div>
                        <div class="rating-count"><?php echo number_format($ratings['total_count']); ?> ratings</div>
                    </div>
                    <div class="col-md-8">
                        <?php for ($i = 5; $i >= 1; $i--): ?>
                        <div class="rating-bar-container">
                            <div class="rating-bar-label"><?php echo $i; ?></div>
                            <div class="rating-bar">
                                <div class="rating-bar-fill"
                                    style="width: <?php echo calculateRatingPercentage($ratings['distribution'][$i], $ratings['total_count']); ?>%">
                                </div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <div class="profile-content">
                <ul class="nav nav-tabs" id="profileTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab"
                            data-bs-target="#overview" type="button" role="tab" aria-controls="overview"
                            aria-selected="true">
                            <i class="fas fa-user-circle me-2"></i> Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="certificates-tab" data-bs-toggle="tab"
                            data-bs-target="#certificates" type="button" role="tab" aria-controls="certificates"
                            aria-selected="false">
                            <i class="fas fa-certificate me-2"></i> Certificates
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="experiences-tab" data-bs-toggle="tab" data-bs-target="#experiences"
                            type="button" role="tab" aria-controls="experiences" aria-selected="false">
                            <i class="fas fa-briefcase me-2"></i> Experiences
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="courses-tab" data-bs-toggle="tab" data-bs-target="#courses"
                            type="button" role="tab" aria-controls="courses" aria-selected="false">
                            <i class="fas fa-graduation-cap me-2"></i> Courses
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="notifications-tab" data-bs-toggle="tab"
                            data-bs-target="#notifications" type="button" role="tab" aria-controls="notifications"
                            aria-selected="false">
                            <i class="fas fa-bell me-2"></i> Notifications
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="profileTabsContent">
                    <!-- Overview Tab -->
                    <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
                        <div class="row">
                            <div class="col-md-6 fade-in delay-1">
                                <div class="section-title">Personal Information</div>
                                <div class="info-item">
                                    <div class="info-label">Full Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Email</div>
                                    <div class="info-value"><?php echo htmlspecialchars($email); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Phone</div>
                                    <div class="info-value">
                                        <?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not provided'; ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Address</div>
                                    <div class="info-value">
                                        <?php echo !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not provided'; ?>
                                    </div>
                                </div>
                                <?php if (!empty($user['bio'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Bio</div>
                                    <div class="info-value"><?php echo htmlspecialchars($user['bio']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 fade-in delay-2">
                                <div class="section-title">Professional Information</div>
                                <div class="info-item">
                                    <div class="info-label">Category</div>
                                    <div class="info-value">
                                        <?php echo !empty($category_name) ? htmlspecialchars($category_name) : 'Not specified'; ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Subcategory</div>
                                    <div class="info-value">
                                        <?php echo !empty($subcategory_name) ? htmlspecialchars($subcategory_name) : 'Not specified'; ?>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Location</div>
                                    <div class="info-value">
                                        <?php echo !empty($city_name) ? htmlspecialchars($city_name) : 'Not specified'; ?>
                                    </div>
                                </div>
                                <?php if (!empty($profile['workplace_map_url'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Workplace Location</div>
                                    <div class="info-value">
                                        <a href="<?php echo htmlspecialchars($profile['workplace_map_url']); ?>"
                                            target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-map-marker-alt me-1"></i> View on Google Maps
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <div class="info-item">
                                    <div class="info-label">Skills</div>
                                    <div class="skills-container">
                                        <?php if (!empty($skills)): ?>
                                        <?php foreach ($skills as $skill): ?>
                                        <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                        <?php endforeach; ?>
                                        <?php else: ?>
                                        <span class="text-muted">No skills added</span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if ($social_links): ?>
                                <div class="info-item">
                                    <div class="info-label">Social Media</div>
                                    <div class="d-flex flex-wrap gap-3 mt-2">
                                        <?php if (!empty($social_links['facebook_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($social_links['facebook_url']); ?>"
                                            target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fab fa-facebook-f"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (!empty($social_links['instagram_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($social_links['instagram_url']); ?>"
                                            target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fab fa-instagram"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (!empty($social_links['linkedin_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($social_links['linkedin_url']); ?>"
                                            target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fab fa-linkedin-in"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (!empty($social_links['github_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($social_links['github_url']); ?>"
                                            target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fab fa-github"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (!empty($social_links['twitter_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($social_links['twitter_url']); ?>"
                                            target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fab fa-twitter"></i>
                                        </a>
                                        <?php endif; ?>

                                        <?php if (!empty($social_links['website_url'])): ?>
                                        <a href="<?php echo htmlspecialchars($social_links['website_url']); ?>"
                                            target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-globe"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if ($banking): ?>
                        <div class="row mt-4 fade-in delay-3">
                            <div class="col-12">
                                <div class="section-title">Banking Information</div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <div class="info-label">CCP</div>
                                            <div class="info-value"><?php echo htmlspecialchars($banking['ccp']); ?>
                                            </div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">CCP Key</div>
                                            <div class="info-value"><?php echo htmlspecialchars($banking['ccp_key']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-item">
                                            <div class="info-label">Consultation Duration</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars($banking['consultation_minutes']); ?>
                                                minutes</div>
                                        </div>
                                        <div class="info-item">
                                            <div class="info-label">Consultation Price</div>
                                            <div class="info-value">
                                                <?php echo htmlspecialchars($banking['consultation_price']); ?> DZD
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row mt-4 fade-in delay-4">
                            <div class="col-12">
                                <div class="section-title">Profile Status</div>
                                <div class="card glow">
                                    <div class="card-body">
                                        <?php if ($profile): ?>
                                        <p>Your profile is currently
                                            <strong><?php echo isset($profile['status']) ? ucfirst($profile['status']) : 'Pending'; ?></strong>.
                                        </p>

                                        <?php if (isset($profile['status']) && $profile['status'] == 'pending_review'): ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Your profile is under review by our team. You will be notified once it's
                                            approved.
                                        </div>
                                        <?php elseif (isset($profile['status']) && $profile['status'] == 'approved'): ?>
                                        <div class="alert alert-success">
                                            <i class="fas fa-check-circle me-2"></i>
                                            Your profile has been approved. You can now receive project requests.
                                        </div>
                                        <?php elseif (isset($profile['status']) && $profile['status'] == 'rejected'): ?>
                                        <div class="alert alert-danger">
                                            <i class="fas fa-exclamation-circle me-2"></i>
                                            Your profile has been rejected. Please check the notifications for more
                                            details.
                                        </div>
                                        <?php endif; ?>
                                        <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            Your profile is incomplete. Please complete your profile to start receiving
                                            project requests.
                                        </div>
                                        <a href="expert-settings.php#personal" class="btn btn-primary">
                                            <i class="fas fa-user-edit me-2"></i> Complete Profile
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Certificates Tab -->
                    <div class="tab-pane fade" id="certificates" role="tabpanel" aria-labelledby="certificates-tab">
                        <?php if (empty($certificates)): ?>
                        <div class="alert alert-info fade-in">
                            <i class="fas fa-info-circle me-2"></i>
                            You haven't added any certificates yet.
                        </div>
                        <a href="expert-settings.php#certificates" class="btn btn-primary fade-in">
                            <i class="fas fa-plus me-2"></i> Add Certificates
                        </a>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($certificates as $index => $cert): ?>
                            <div class="col-md-6 fade-in" style="animation-delay: <?php echo 0.1 * $index; ?>s">
                                <div class="card glow">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">
                                            <?php echo htmlspecialchars($cert['institution']); ?></h5>
                                        <?php echo getStatusBadge(isset($cert['status']) ? $cert['status'] : 'pending'); ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="item-date">
                                            <i class="far fa-calendar-alt"></i>
                                            <?php 
                                                    $start_date = new DateTime($cert['start_date']);
                                                    $end_date = new DateTime($cert['end_date']);
                                                    echo $start_date->format('M Y') . ' - ' . $end_date->format('M Y');
                                                ?>
                                        </div>
                                        <p class="item-description">
                                            <?php echo htmlspecialchars($cert['description']); ?></p>

                                        <?php if (!empty($cert['file_path'])): ?>
                                        <div class="mt-3">
                                            <a href="<?php echo htmlspecialchars($cert['file_path']); ?>"
                                                target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-file-alt me-1"></i> View Certificate
                                            </a>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (isset($cert['status']) && $cert['status'] == 'rejected' && !empty($cert['rejection_reason'])): ?>
                                        <div class="rejection-reason mt-3">
                                            <strong>Rejection Reason:</strong>
                                            <?php echo htmlspecialchars($cert['rejection_reason']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Experiences Tab -->
                    <div class="tab-pane fade" id="experiences" role="tabpanel" aria-labelledby="experiences-tab">
                        <?php if (empty($experiences)): ?>
                        <div class="alert alert-info fade-in">
                            <i class="fas fa-info-circle me-2"></i>
                            You haven't added any experiences yet.
                        </div>
                        <a href="expert-settings.php#experiences" class="btn btn-primary fade-in">
                            <i class="fas fa-plus me-2"></i> Add Experiences
                        </a>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($experiences as $index => $exp): ?>
                            <div class="col-md-6 fade-in" style="animation-delay: <?php echo 0.1 * $index; ?>s">
                                <div class="card glow">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($exp['workplace']); ?>
                                        </h5>
                                        <?php echo getStatusBadge(isset($exp['status']) ? $exp['status'] : 'pending'); ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="item-date">
                                            <i class="far fa-calendar-alt"></i>
                                            <?php 
                                                    $start_date = new DateTime($exp['start_date']);
                                                    $end_date = !empty($exp['end_date']) ? new DateTime($exp['end_date']) : null;
                                                    echo $start_date->format('M Y') . ' - ' . ($end_date ? $end_date->format('M Y') : 'Present');
                                                ?>
                                            <span class="ms-2 text-muted">
                                                (<?php echo $exp['duration_years']; ?> years,
                                                <?php echo $exp['duration_months']; ?> months)
                                            </span>
                                        </div>
                                        <p class="item-description"><?php echo htmlspecialchars($exp['description']); ?>
                                        </p>

                                        <?php if (!empty($exp['file_path'])): ?>
                                        <div class="mt-3">
                                            <a href="<?php echo htmlspecialchars($exp['file_path']); ?>" target="_blank"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-file-alt me-1"></i> View Document
                                            </a>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (isset($exp['status']) && $exp['status'] == 'rejected' && !empty($exp['rejection_reason'])): ?>
                                        <div class="rejection-reason mt-3">
                                            <strong>Rejection Reason:</strong>
                                            <?php echo htmlspecialchars($exp['rejection_reason']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Courses Tab -->
                    <div class="tab-pane fade" id="courses" role="tabpanel" aria-labelledby="courses-tab">
                        <?php if (empty($formations)): ?>
                        <div class="alert alert-info fade-in">
                            <i class="fas fa-info-circle me-2"></i>
                            You haven't added any courses or trainings yet.
                        </div>
                        <a href="expert-settings.php#formations" class="btn btn-primary fade-in">
                            <i class="fas fa-plus me-2"></i> Add Courses
                        </a>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($formations as $index => $form): ?>
                            <div class="col-md-6 fade-in" style="animation-delay: <?php echo 0.1 * $index; ?>s">
                                <div class="card glow">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">
                                            <?php echo htmlspecialchars($form['formation_name']); ?></h5>
                                        <?php echo getStatusBadge(isset($form['status']) ? $form['status'] : 'pending'); ?>
                                    </div>
                                    <div class="card-body">
                                        <div class="item-subtitle">
                                            <i class="fas fa-graduation-cap me-2"></i>
                                            <?php echo htmlspecialchars(ucfirst($form['formation_type'])); ?> -
                                            <?php echo htmlspecialchars($form['formation_year']); ?>
                                        </div>
                                        <p class="item-description">
                                            <?php echo htmlspecialchars($form['description']); ?></p>

                                        <?php if (!empty($form['file_path'])): ?>
                                        <div class="mt-3">
                                            <a href="<?php echo htmlspecialchars($form['file_path']); ?>"
                                                target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-file-alt me-1"></i> View Certificate
                                            </a>
                                        </div>
                                        <?php endif; ?>

                                        <?php if (isset($form['status']) && $form['status'] == 'rejected' && !empty($form['rejection_reason'])): ?>
                                        <div class="rejection-reason mt-3">
                                            <strong>Rejection Reason:</strong>
                                            <?php echo htmlspecialchars($form['rejection_reason']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Notifications Tab -->
                    <div class="tab-pane fade" id="notifications" role="tabpanel" aria-labelledby="notifications-tab">
                        <div class="notifications-container fade-in">
                            <?php if (empty($notifications)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                You don't have any notifications yet.
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications as $index => $notif): ?>
                            <div class="notification-item <?php echo isset($notif['is_read']) && $notif['is_read'] == 0 ? 'notification-unread' : ''; ?>"
                                style="animation-delay: <?php echo 0.05 * $index; ?>s">
                                <div class="notification-content">
                                    <?php echo htmlspecialchars($notif['message']); ?>

                                    <?php if (!empty($notif['feedback'])): ?>
                                    <div class="mt-2 ps-3 border-start border-3">
                                        <?php echo htmlspecialchars($notif['feedback']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-time">
                                    <i class="far fa-clock"></i>
                                    <?php 
                                            $created_at = new DateTime($notif['created_at']);
                                            echo $created_at->format('M d, Y - H:i');
                                        ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container footer-content">
            <div class="row">
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>About <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></h5>
                    <p class="mb-4">
                        <?php echo htmlspecialchars($settings['site_description'] ?? 'Expert Consultation Platform connecting experts with clients for professional consultations.'); ?>
                    </p>
                </div>
                <div class="col-md-4 mb-4 mb-md-0">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="home-profile.php"><i class="fas fa-home"></i> Home</a></li>
                        <li><a href="expert-profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                        <li><a href="expert-consultations.php"><i class="fas fa-laptop-code"></i> Consultations</a></li>
                        <li><a href="expert-earnings.php"><i class="fas fa-chart-line"></i> Earnings</a></li>
                        <li><a href="expert-avis.php"><i class="fas fa-star"></i> Reviews</a></li>
                        <li><a href="expert-contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h5>Contact</h5>
                    <ul class="footer-links">
                        <?php if (!empty($settings['site_name'])): ?>
                        <li><i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($settings['site_name']); ?>
                        </li>
                        <?php endif; ?>
                        <?php if (!empty($settings['site_email'])): ?>
                        <li><a href="mailto:<?php echo htmlspecialchars($settings['site_email']); ?>"><i
                                    class="fas fa-envelope me-2"></i>
                                <?php echo htmlspecialchars($settings['site_email']); ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($settings['phone_number1'])): ?>
                        <li><a href="tel:<?php echo htmlspecialchars($settings['phone_number1']); ?>"><i
                                    class="fas fa-phone me-2"></i>
                                <?php echo htmlspecialchars($settings['phone_number1']); ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($settings['phone_number2'])): ?>
                        <li><a href="tel:<?php echo htmlspecialchars($settings['phone_number2']); ?>"><i
                                    class="fas fa-phone-alt me-2"></i>
                                <?php echo htmlspecialchars($settings['phone_number2']); ?></a></li>
                        <?php endif; ?>
                        <?php if (!empty($settings['facebook_url'])): ?>
                        <li><a href="<?php echo htmlspecialchars($settings['facebook_url']); ?>" target="_blank"><i
                                    class="fab fa-facebook me-2"></i> Facebook</a></li>
                        <?php endif; ?>
                        <?php if (!empty($settings['instagram_url'])): ?>
                        <li><a href="<?php echo htmlspecialchars($settings['instagram_url']); ?>" target="_blank"><i
                                    class="fab fa-instagram me-2"></i> Instagram</a></li>
                        <?php endif; ?>
                    </ul>
                    <p class="mt-3 mb-0">Need help? <a href="expert-contact.php"
                            class="text-primary font-weight-bold">Contact Us</a></p>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>&copy; <?php echo date('Y'); ?>
                    <?php echo isset($settings['site_name']) ? htmlspecialchars($settings['site_name']) : ' '; ?>. All
                    rights reserved. </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // Toggle upload form
    function toggleUploadForm() {
        const uploadForm = document.getElementById('uploadForm');
        if (uploadForm.style.display === 'block') {
            uploadForm.style.display = 'none';
        } else {
            uploadForm.style.display = 'block';
        }
    }

    // Initialize animations and effects
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch notifications function
        function fetchNotifications() {
            fetch('expert-profile.php?fetch_notifications=true')
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

        document.querySelectorAll('.card, .section-title, .info-item').forEach(el => {
            observer.observe(el);
        });

        // Create floating code elements
        function createRandomCodeElements() {
            const codeTexts = [
                'const app = createApp();',
                'function renderComponent() { }',
                'import { useState } from "react";',
                '.container { display: flex; }',
                'export default Dashboard;',
                'async function fetchData() { }',
                '<div className="wrapper">',
                'npm install @vercel/earnings',
                'git commit -m "Update profile"',
                'const [data, setData] = useState(null);'
            ];

            const container = document.querySelector('.background-container');

            for (let i = 0; i < 5; i++) {
                const element = document.createElement('div');
                element.className = 'code-element';
                element.style.top = Math.random() * 100 + '%';
                element.style.left = Math.random() * 100 + '%';
                element.style.transform = `rotate(${Math.random() * 20 - 10}deg)`;
                element.style.opacity = 0.1 + Math.random() * 0.1;
                element.style.animation =
                    `fadeInOut ${8 + Math.random() * 8}s infinite ease-in-out ${Math.random() * 5}s`;
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
                    element.style.transform =
                        `translate(${moveX * speed}px, ${moveY * speed}px) rotate(${rotate})`;
                });

                document.querySelectorAll('.floating-element').forEach((element) => {
                    const speed = 1.2;
                    element.style.transform =
                        `translateX(${moveX * speed}px) translateY(${moveY * speed}px) rotateX(${moveY}deg) rotateY(${-moveX}deg)`;
                });
            });
        }

        // Mark notifications as read when viewed
        document.getElementById('notifications-tab').addEventListener('click', function() {
            // In a real application, you would make an AJAX request to mark notifications as read
            const unreadNotifications = document.querySelectorAll('.notification-unread');
            unreadNotifications.forEach(function(notification) {
                notification.classList.remove('notification-unread');
            });
        });
    });
    </script>
</body>

</html>