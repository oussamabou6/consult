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

// Add this code to update user status to "Online" when they log in
$user_id = $_SESSION["user_id"];
$update_status_sql = "UPDATE users SET status = 'Online' WHERE id = ?";
$update_status_stmt = $conn->prepare($update_status_sql);
$update_status_stmt->bind_param("i", $user_id);
$update_status_stmt->execute();

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
$sql = "SELECT u.*, up.phone, up.address, up.profile_image, up.bio 
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
$notif_sql = "SELECT * FROM expert_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$notif_stmt = $conn->prepare($notif_sql);
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$notif_result = $notif_stmt->get_result();
while ($row = $notif_result->fetch_assoc()) {
    $notifications[] = $row;
}

// Get upcoming consultations
$upcoming_consultations = [];
$consult_sql = "SELECT c.*, u.full_name as client_name, up.profile_image as client_image 
                FROM consultations c 
                JOIN users u ON c.client_id = u.id 
                LEFT JOIN user_profiles up ON u.id = up.user_id 
                WHERE c.expert_id = ? AND c.status = 'confirmed' AND c.consultation_date >= CURDATE() 
                ORDER BY c.consultation_date, c.consultation_time 
                LIMIT 5";
$consult_stmt = $conn->prepare($consult_sql);
$consult_stmt->bind_param("i", $user_id);
$consult_stmt->execute();
$consult_result = $consult_stmt->get_result();
while ($row = $consult_result->fetch_assoc()) {
    $upcoming_consultations[] = $row;
}

//count consultations
$unique_consultations_count = 0;
$sql = "SELECT COUNT(*) AS consultations_count
        FROM consultations c
        WHERE c.expert_id = ? AND c.status = 'completed'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $unique_consultations_count = $row['consultations_count'];
}

// count client
$unique_clients_count = 0;
$sql = "SELECT COUNT(DISTINCT c.client_id) AS client_count
        FROM consultations c
        WHERE c.expert_id = ? AND c.status = 'completed'";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $unique_clients_count = $row['client_count'];
}

// Get earnings data
$earnings = [
    'total' => 0,
    'pending' => 0,
    'available' => 0,
    'recent' => []
];

// Get total earnings
$earnings_sql = "SELECT SUM(amount) as total FROM payments WHERE expert_id = ? AND status = 'completed'";
$earnings_stmt = $conn->prepare($earnings_sql);
$earnings_stmt->bind_param("i", $user_id);
$earnings_stmt->execute();
$earnings_result = $earnings_stmt->get_result();
$earnings_data = $earnings_result->fetch_assoc();
if ($earnings_data && $earnings_data['total']) {
    $earnings['total'] = $earnings_data['total'];
}

// Get pending earnings
$pending_sql = "SELECT SUM(amount) as pending FROM payments WHERE expert_id = ? AND status = 'pending'";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();
$pending_data = $pending_result->fetch_assoc();
if ($pending_data && $pending_data['pending']) {
    $earnings['pending'] = $pending_data['pending'];
}

// Get available balance from users table
$earnings['available'] = $user['balance'] ?? 0;

// Get recent earnings
$recent_earnings_sql = "SELECT p.*, c.consultation_date, u.full_name as client_name 
                        FROM payments p 
                        JOIN consultations c ON p.consultation_id = c.id 
                        JOIN users u ON p.client_id = u.id 
                        WHERE p.expert_id = ? AND p.status = 'completed' 
                        ORDER BY p.created_at DESC LIMIT 5";
$recent_earnings_stmt = $conn->prepare($recent_earnings_sql);
$recent_earnings_stmt->bind_param("i", $user_id);
$recent_earnings_stmt->execute();
$recent_earnings_result = $recent_earnings_stmt->get_result();
while ($row = $recent_earnings_result->fetch_assoc()) {
    $earnings['recent'][] = $row;
}

// Get ratings data
$ratings = [
    'average' => 0,
    'total_count' => 0,
    'recent' => []
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
        
        // Get recent ratings
        $recent_ratings_sql = "SELECT er.*, u.full_name as client_name, c.consultation_date 
                              FROM expert_ratings er 
                              JOIN users u ON er.client_id = u.id 
                              LEFT JOIN consultations c ON er.consultation_id = c.id 
                              WHERE er.expert_id = ? 
                              ORDER BY er.created_at DESC LIMIT 5";
        $recent_ratings_stmt = $conn->prepare($recent_ratings_sql);
        $recent_ratings_stmt->bind_param("i", $profile_id);
        $recent_ratings_stmt->execute();
        $recent_ratings_result = $recent_ratings_stmt->get_result();
        while ($row = $recent_ratings_result->fetch_assoc()) {
            $ratings['recent'][] = $row;
        }
    }
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

// Function to format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Function to format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}

// Get currency from settings
$currency = $settings['currency'] ?? 'DA';
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
    <title>Expert Dashboard - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
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
        
        /* Dashboard Cards */
        .dashboard-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            height: 100%;
        }
        
        .dashboard-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.15);
        }
        
        .dashboard-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .dashboard-card-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0;
        }
        
        .dashboard-card-body {
            padding: 1.5rem;
        }
        
        /* Stats Cards */
        .stat-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: white;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon.earnings {
            background: linear-gradient(135deg, var(--success-color), #34d399);
        }
        
        .stat-icon.consultations {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        }
        
        .stat-icon.ratings {
            background: linear-gradient(135deg, var(--warning-color), #fbbf24);
        }
        
        .stat-icon.clients {
            background: linear-gradient(135deg, var(--info-color), #60a5fa);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.95rem;
            font-weight: 500;
        }
        
        .stat-change {
            margin-top: auto;
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .stat-change.positive {
            color: var(--success-color);
        }
        
        .stat-change.negative {
            color: var(--danger-color);
        }
        
        .stat-change i {
            margin-right: 0.25rem;
        }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            border-radius: 20px;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.3);
        }
        
        .welcome-section::before {
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
        
        .welcome-section::after {
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
        
        .welcome-content {
            position: relative;
            z-index: 1;
        }
        
        .welcome-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 1.5rem;
        }
        
        .welcome-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .welcome-actions .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .welcome-actions .btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        /* Consultation List */
        .consultation-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .consultation-item {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .consultation-item:last-child {
            border-bottom: none;
        }
        
        .consultation-item:hover {
            background-color: rgba(99, 102, 241, 0.05);
            transform: translateX(5px);
        }
        
        .client-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            background-color: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            color: var(--text-muted);
            border: 2px solid white;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .consultation-info {
            flex-grow: 1;
        }
        
        .client-name {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        
        .consultation-date {
            color: var(--text-muted);
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .consultation-date i {
            color: var(--primary-color);
        }
        
        .consultation-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .consultation-actions .btn {
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 8px;
        }
        
        /* Notification List */
        .notification-list {
            list-style: none;
            padding: 0;
            margin: 0;
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
        
        .notification-content {
            font-size: 0.95rem;
            color: var(--text-color);
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .notification-time {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
        }
        
        .notification-time i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        /* Earnings Chart */
        .earnings-chart-container {
            height: 300px;
            position: relative;
        }
        
        /* Recent Earnings */
        .earnings-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .earnings-item {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .earnings-item:last-child {
            border-bottom: none;
        }
        
        .earnings-item:hover {
            background-color: rgba(99, 102, 241, 0.05);
            transform: translateX(5px);
        }
        
        .earnings-info {
            display: flex;
            flex-direction: column;
        }
        
        .earnings-client {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.25rem;
        }
        
        .earnings-date {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .earnings-amount {
            font-weight: 700;
            color: var(--success-color);
            font-family: var(--code-font);
            font-size: 1.1rem;
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
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .welcome-section {
                padding: 2rem 1.5rem;
            }
            
            .welcome-title {
                font-size: 1.8rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 767.98px) {
            .welcome-section {
                padding: 1.5rem;
            }
            
            .welcome-title {
                font-size: 1.5rem;
            }
            
            .welcome-actions {
                flex-direction: column;
            }
            
            .welcome-actions .btn {
                width: 100%;
            }
            
            .dashboard-card-header {
                padding: 1.25rem;
            }
            
            .dashboard-card-body {
                padding: 1.25rem;
            }
            
            .stat-card {
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

        /* Updated Card Styles */
        .dashboard-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            height: 100%;
            margin-bottom: 1.5rem;
        }
        
        .dashboard-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fafbfc;
        }
        
        .dashboard-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .dashboard-card-body {
            padding: 1.5rem;
        }
        
        /* Reviews List */
        .reviews-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .review-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
        }
        
        .review-item:last-child {
            border-bottom: none;
        }
        
        .review-item:hover {
            background-color: rgba(99, 102, 241, 0.03);
        }
        
        .rating-stars {
            color: #f59e0b;
        }
        
        .review-content {
            font-size: 0.95rem;
            color: var(--text-color);
            line-height: 1.6;
            margin-bottom: 0.5rem;
        }
        
        .review-date {
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        
        /* Earnings Overview */
        .fs-3 {
            font-size: 1.75rem !important;
            font-weight: 700;
            color: #333;
        }
        
        .text-muted {
            color: #6c757d !important;
        }
        
        /* Buttons */
        .btn-outline-primary {
            color: #6366f1;
            border-color: #6366f1;
            background: transparent;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0.375rem 0.75rem;
        }
        
        .btn-outline-primary:hover {
            background-color: #6366f1;
            color: white;
        }

html, body {
    overflow-x: hidden;
    scroll-behavior: smooth;
}

body {
    position: relative;
    min-height: 100vh;
    width: 100%;
}

.main-container {
    position: relative;
    z-index: 2;
}

/* Fix for mobile scrolling */
@media (max-width: 767.98px) {
    .container {
        max-width: 100%;
        padding-left: 15px;
        padding-right: 15px;
    }
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
                    <a class="nav-link d-flex flex-column align-items-center active" href="home-profile.php">
                        <i class="fas fa-home mb-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-profile.php">
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
        <div class="alert alert-success mt-3 fade-in">
            <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger mt-3 fade-in">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Welcome Section -->
    <div class="welcome-section fade-in">
        <div class="welcome-content">
            <h1 class="welcome-title">Welcome back, <?php echo htmlspecialchars($user['full_name']); ?>!</h1>
            <p class="welcome-subtitle">Here's an overview of your expert profile and activities.</p>
            <div class="welcome-actions">
                <a href="expert-profile.php" class="btn btn-secondary">
                    <i class="fas fa-user-edit me-2"></i> View Profile
                </a>
            </div>
        </div>
    </div>
    
    <!-- Stats Row -->
    <div class="row g-4 mt-4">
        <div class="col-md-6 col-lg-3 fade-in delay-1">
            <div class="stat-card">
                <div class="stat-icon earnings">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-value"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($user['balance'] ?? 0); ?></div>
                <div class="stat-label">Available Balance</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 fade-in delay-2">
            <div class="stat-card">
                <div class="stat-icon consultations">
                    <i class="fas fa-comments"></i>
                </div>
                <div class="stat-value"><?php echo $unique_consultations_count; ?></div>
                <div class="stat-label">ِConsultation Completed</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 fade-in delay-3">
            <div class="stat-card">
                <div class="stat-icon ratings">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?php echo number_format($ratings['average'], 1); ?></div>
                <div class="stat-label">Average Rating</div>
            </div>
        </div>
        <div class="col-md-6 col-lg-3 fade-in delay-4">
            <div class="stat-card">
                <div class="stat-icon clients">
                    <i class="fas fa-user-friends"></i>
                </div>
                <div class="stat-value"><?php echo $unique_clients_count ; ?></div>
                <div class="stat-label">Recent Clients</div>
            </div>
        </div>
    </div>
    
    <!-- Main Dashboard Content -->
    <div class="row g-4 mt-4">
        <!-- Upcoming Consultations -->
        <div class="col-lg-8 fade-in delay-1">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Current Consultations</h2>
                    <a href="expert-consultations.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="dashboard-card-body">
                    <?php if (empty($upcoming_consultations)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                            <p class="mb-0">You don't have any current consultations.</p>
                            <p class="text-muted">Consultations will appear here once clients book with you.</p>
                        </div>
                    <?php else: ?>
                        <ul class="consultation-list">
                            <?php foreach ($upcoming_consultations as $consultation): ?>
                                <li class="consultation-item">
                                    <?php if (!empty($consultation['client_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($consultation['client_image']); ?>" alt="Client" class="client-avatar">
                                    <?php else: ?>
                                        <div class="client-avatar">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="consultation-info">
                                        <div class="client-name"><?php echo htmlspecialchars($consultation['client_name']); ?></div>
                                        <div class="consultation-date">
                                            <i class="far fa-calendar-alt"></i> <?php echo formatDate($consultation['consultation_date']); ?> at <?php echo formatTime($consultation['consultation_time']); ?>
                                        </div>
                                    </div>
                                    <div class="consultation-actions">
                                        <a href="expert-chat.php?client_id=<?php echo $consultation['client_id']; ?>&consultation_id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-comments me-1"></i> Chat
                                        </a>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Notifications -->
        <div class="col-lg-4 fade-in delay-2">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Notifications</h2>
                    <a href="#" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="dashboard-card-body">
                    <?php if (empty($notifications)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-bell fa-3x text-muted mb-3"></i>
                            <p class="mb-0">You don't have any notifications.</p>
                            <p class="text-muted">New notifications will appear here.</p>
                        </div>
                    <?php else: ?>
                        <ul class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <li class="notification-item <?php echo $notification['is_read'] ? '' : 'notification-unread'; ?>">
                                    <div class="notification-content">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                    <div class="notification-time">
                                        <i class="far fa-clock"></i>
                                        <?php 
                                            $created_at = new DateTime($notification['created_at']);
                                            echo $created_at->format('M d, Y - H:i');
                                        ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Earnings Section -->
    <div class="row g-4 mt-4">
        <div class="col-12 fade-in delay-3">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Earnings Overview</h2>
                    <a href="expert-earnings.php" class="btn btn-sm btn-outline-primary">View Details</a>
                </div>
                <div class="dashboard-card-body">
                    <div class="row mb-4">
                        <div class="col-md-4 text-center">
                            <h3 class="fs-5 text-muted mb-2">Total Earnings</h3>
                            <div class="fs-3 fw-bold"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($earnings['total']); ?></div>
                        </div>
                        <div class="col-md-4 text-center">
                            <h3 class="fs-5 text-muted mb-2">Pending</h3>
                            <div class="fs-3 fw-bold"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($earnings['pending']); ?></div>
                        </div>
                        <div class="col-md-4 text-center">
                            <h3 class="fs-5 text-muted mb-2">Available</h3>
                            <div class="fs-3 fw-bold"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($user['balance'] ?? 0); ?></div>
                        </div>
                    </div>
                    
                    <h3 class="fs-5 mb-3">Recent Earnings</h3>
                    <?php if (empty($earnings['recent'])): ?>
                        <div class="text-center py-4">
                            <div class="mb-3">
                                <img src="../assets/images/coins-stack.svg" alt="Earnings" width="64" height="64" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI2NCIgaGVpZ2h0PSI2NCIgdmlld0JveD0iMCAwIDI0IDI0IiBmaWxsPSJub25lIiBzdHJva2U9IiM2MzY2ZjEiIHN0cm9rZS13aWR0aD0iMiIgc3Ryb2tlLWxpbmVjYXA9InJvdW5kIiBzdHJva2UtbGluZWpvaW49InJvdW5kIj48Y2lyY2xlIGN4PSIxMiIgY3k9IjgiIHI9IjciPjwvY2lyY2xlPjxjaXJjbGUgY3g9IjEyIiBjeT0iMTYiIHI9IjciPjwvY2lyY2xlPjxsaW5lIHgxPSIxMiIgeTE9IjUiIHgyPSIxMiIgeTI9IjExIj48L2xpbmU+PGxpbmUgeDE9IjEyIiB5MT0iMTMiIHgyPSIxMiIgeTI9IjE5Ij48L2xpbmU+PC9zdmc+'" style="opacity: 0.7;">
                            </div>
                            <p class="mb-0">YOU DON'T HAVE ANY RECENT EARNINGS.</p>
                            <p class="text-muted">EARNINGS WILL APPEAR HERE ONCE YOU COMPLETE CONSULTATIONS.</p>
                        </div>
                    <?php else: ?>
                        <ul class="earnings-list">
                            <?php foreach ($earnings['recent'] as $earning): ?>
                                <li class="earnings-item">
                                    <div class="earnings-info">
                                        <div class="earnings-client"><?php echo htmlspecialchars($earning['client_name']); ?></div>
                                        <div class="earnings-date"><?php echo formatDate($earning['consultation_date']); ?></div>
                                    </div>
                                    <div class="earnings-amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($earning['amount']); ?></div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Reviews Section -->
    <div class="row g-4 mt-4">
        <div class="col-12 fade-in delay-4">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">RECENT REVIEWS</h2>
                    <a href="expert-avis.php" class="btn btn-sm btn-outline-primary">VIEW ALL</a>
                </div>
                <div class="dashboard-card-body">
                    <?php if (empty($ratings['recent'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-star fa-3x text-muted mb-3"></i>
                            <p class="mb-0">You don't have any reviews yet.</p>
                            <p class="text-muted">Reviews will appear here once clients rate your consultations.</p>
                        </div>
                    <?php else: ?>
                        <ul class="reviews-list">
                            <?php foreach ($ratings['recent'] as $rating): ?>
                                <li class="review-item">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <div class="fw-bold"><?php echo htmlspecialchars($rating['client_name']); ?></div>
                                        <div class="rating-stars">
                                            <?php echo generateStarRating($rating['rating']); ?>
                                        </div>
                                    </div>
                                    <div class="review-content">
                                        <?php echo htmlspecialchars($rating['comment'] ?? 'No comment provided.'); ?>
                                    </div>
                                    <div class="review-date">
                                        <i class="far fa-calendar-alt me-1"></i>
                                        <?php echo formatDate($rating['consultation_date'] ?? $rating['created_at']); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
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
            <p>&copy; <?php echo date('Y'); ?> <?php echo isset($settings['site_name']) ? htmlspecialchars($settings['site_name']) : ' '; ?>. All rights reserved. </p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch notifications function
    function fetchNotifications() {
        fetch('home-profile.php?fetch_notifications=true')
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
        
        document.querySelectorAll('.stat-card, .dashboard-card').forEach(el => {
            observer.observe(el);
        });
        
        // Only create these elements on desktop
        if (window.innerWidth > 768) {
            // Add subtle parallax effect to background elements
            document.addEventListener('mousemove', (e) => {
                const moveX = (e.clientX - window.innerWidth / 2) / 30;
                const moveY = (e.clientY - window.innerHeight / 2) / 30;
                
                document.querySelectorAll('.shape').forEach((shape) => {
                    const speed = parseFloat(shape.getAttribute('data-speed') || 1);
                    shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
                });
            });
        }
        
        // Mark notifications as read when viewed
        const notificationList = document.querySelector('.notification-list');
        if (notificationList) {
            notificationList.addEventListener('click', function(e) {
                const notificationItem = e.target.closest('.notification-item');
                if (notificationItem && notificationItem.classList.contains('notification-unread')) {
                    notificationItem.classList.remove('notification-unread');
                    // In a real application, you would make an AJAX request to mark the notification as read
                }
            });
        }
        
        // Make "View All" buttons functional
        document.querySelectorAll('.btn-outline-primary').forEach(button => {
            button.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            });
        });
        
        // Make consultation chat buttons functional
        document.querySelectorAll('.consultation-actions .btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href && href !== '#') {
                    window.location.href = href;
                }
            });
        });
        
        // Smooth scroll for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
        
        // Fix for mobile navigation
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        if (navbarToggler && navbarCollapse) {
            navbarToggler.addEventListener('click', function() {
                navbarCollapse.classList.toggle('show');
            });
            
            // Close mobile menu when clicking outside
            document.addEventListener('click', function(e) {
                if (navbarCollapse.classList.contains('show') && 
                    !navbarCollapse.contains(e.target) && 
                    !navbarToggler.contains(e.target)) {
                    navbarCollapse.classList.remove('show');
                }
            });
        }
    });
</script>
</body>
</html>
