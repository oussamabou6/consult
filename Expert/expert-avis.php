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

// Add this code to update user status to "Online" when they log in
$user_id = $_SESSION["user_id"];

$update_reviews_sql= "UPDATE expert_ratings SET is_read = 1 WHERE expert_id = ? AND is_read = 0";
$update_reviews_stmt = $conn->prepare($update_reviews_sql);
$update_reviews_stmt->bind_param("i", $user_id);
$update_reviews_stmt->execute();
$update_reviews_stmt->close();


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

// Process reply to a comment
if (isset($_POST['submit_reply'])) {
    $rating_id = $_POST['rating_id'];
    $response_text = $_POST['response_text'];
    
    // Check if a response already exists
    $check_sql = "SELECT id FROM expert_rating_responses WHERE rating_id = ? AND expert_id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("ii", $rating_id, $profile_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing response
        $update_sql = "UPDATE expert_rating_responses SET response_text = ? WHERE rating_id = ? AND expert_id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sii", $response_text, $rating_id, $profile_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Your response has been updated successfully.";
        } else {
            $error_message = "An error occurred while updating your response.";
        }
    } else {
        // Insert new response
        $insert_sql = "INSERT INTO expert_rating_responses (rating_id, expert_id, response_text) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iis", $rating_id, $profile_id, $response_text);
        
        if ($insert_stmt->execute()) {
            $success_message = "Your response has been saved successfully.";
        } else {
            $error_message = "An error occurred while saving your response.";
        }
    }
}

// Search filters
$search_rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$search_client = isset($_GET['client_name']) ? $_GET['client_name'] : '';

// Build SQL query with filters
$sql = "SELECT er.*, u.full_name as client_name, c.consultation_date, err.response_text, err.id as response_id
        FROM expert_ratings er
        JOIN users u ON er.client_id = u.id
        LEFT JOIN consultations c ON er.consultation_id = c.id
        LEFT JOIN expert_rating_responses err ON er.id = err.rating_id AND err.expert_id = ?
        WHERE er.expert_id = ?";

$params = [$profile_id, $profile_id];
$types = "ii";

if ($search_rating > 0) {
    $sql .= " AND er.rating = ?";
    $params[] = $search_rating;
    $types .= "i";
}

if (!empty($search_client)) {
    $search_client = "%$search_client%";
    $sql .= " AND u.full_name LIKE ?";
    $params[] = $search_client;
    $types .= "s";
}

$sql .= " ORDER BY er.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$ratings = $stmt->get_result();

// Calculate average ratings
$avg_sql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings FROM expert_ratings WHERE expert_id = ?";
$avg_stmt = $conn->prepare($avg_sql);
$avg_stmt->bind_param("i", $profile_id);
$avg_stmt->execute();
$avg_result = $avg_stmt->get_result();
$rating_stats = $avg_result->fetch_assoc();

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reviews & Ratings - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
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
        
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            border-radius: 20px;
            padding: 2rem;
            position: relative;
            overflow: hidden;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(99, 102, 241, 0.3);
        }
        
        .page-header::before {
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
        
        .page-header::after {
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
        
        .page-header-content {
            position: relative;
            z-index: 1;
        }
        
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        /* Dashboard Cards */
        .dashboard-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            height: 100%;
            margin-bottom: 1.5rem;
            position: relative;
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            z-index: 0;
        }
        
        .dashboard-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .dashboard-card-header {
            padding: 1.25rem 1.5rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #fafbfc;
            position: relative;
            z-index: 1;
        }
        
        .dashboard-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            padding-left: 15px;
        }
        
        .dashboard-card-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 5px;
            height: 20px;
            background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
            border-radius: 5px;
        }
        
        .dashboard-card-body {
            padding: 1.5rem;
            position: relative;
            z-index: 1;
        }
        
        /* Rating Styles */
        .rating-bars {
            margin-top: 1.5rem;
        }
        
        .rating-bar-container {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }
        
        .rating-bar-container span {
            width: 80px;
            font-size: 0.9rem;
        }
        
        .rating-bar-container .progress {
            flex: 1;
            height: 10px;
            border-radius: 5px;
            background-color: #e9ecef;
        }
        
        .rating-bar-container .progress-bar {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border-radius: 5px;
            transition: width 1s ease;
        }
        
        .rating {
            display: flex;
            align-items: center;
        }
        
        .rating .fas.fa-star {
            color: #e0e0e0;
            margin-right: 2px;
        }
        
        .rating .fas.fa-star.text-warning {
            color: #f59e0b;
        }
        
        .review-card {
            border-radius: 12px;
            border: 1px solid rgba(226, 232, 240, 0.7);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            overflow: hidden;
        }
        
        .review-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .review-header {
            background-color: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .review-body {
            padding: 1.5rem;
        }
        
        .review-footer {
            padding: 1rem 1.5rem;
            background-color: #f8f9fa;
            border-top: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .expert-response {
            background-color: rgba(99, 102, 241, 0.05);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            border-left: 4px solid var(--primary-color);
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
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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
            color: var(1.56,0.64,1);
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
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .page-header {
                padding: 1.5rem;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
        }
        
        @media (max-width: 767.98px) {
            .page-header {
                padding: 1.25rem;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .dashboard-card-header {
                padding: 1rem;
            }
            
            .dashboard-card-body {
                padding: 1rem;
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
        
        .delay-5 {
            animation-delay: 0.5s;
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

        html, body {
            overflow-x: hidden;
            scroll-behavior: smooth;
        }

        body {
            position: relative;
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
                    <a class="nav-link d-flex flex-column align-items-center" href="home-profile.php">
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
                    <a class="nav-link d-flex flex-column align-items-center active" href="expert-avis.php">
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
    
    <!-- Page Header -->
    <div class="page-header fade-in">
        <div class="page-header-content">
            <h1 class="page-title">Reviews & Ratings</h1>
            <p class="page-subtitle">View and manage client reviews</p>
        </div>
    </div>
    
    <div class="row g-4">
        <!-- Statistics Card -->
        <div class="col-lg-4 fade-in delay-1">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Statistics</h2>
                </div>
                <div class="dashboard-card-body">
                    <div class="text-center mb-4">
                        <h3 class="mb-0"><?php echo number_format($rating_stats['avg_rating'], 1); ?>/5</h3>
                        <div class="rating my-2">
                            <?php
                            $avg = round($rating_stats['avg_rating'] * 2) / 2; // Round to nearest 0.5
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $avg) {
                                    echo '<i class="fas fa-star text-warning"></i>';
                                } elseif ($i - 0.5 == $avg) {
                                    echo '<i class="fas fa-star-half-alt text-warning"></i>';
                                } else {
                                    echo '<i class="fas fa-star text-muted"></i>';
                                }
                            }
                            ?>
                        </div>
                        <p class="text-muted">Based on <?php echo $rating_stats['total_ratings']; ?> reviews</p>
                    </div>
                    
                    <div class="rating-bars">
                        <?php
                        for ($i = 5; $i >= 1; $i--) {
                            $count_stmt = $conn->prepare("SELECT COUNT(*) as count FROM expert_ratings WHERE expert_id = ? AND rating = ?");
                            $count_stmt->bind_param("ii", $profile_id, $i);
                            $count_stmt->execute();
                            $count_result = $count_stmt->get_result();
                            $count_data = $count_result->fetch_assoc();
                            $percentage = $rating_stats['total_ratings'] > 0 ? ($count_data['count'] / $rating_stats['total_ratings']) * 100 : 0;
                        ?>
                        <div class="rating-bar-container">
                            <span><?php echo $i; ?> stars</span>
                            <div class="progress">
                                <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $count_data['count']; ?></div>
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Card -->
        <div class="col-lg-8 fade-in delay-2">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Filter Reviews</h2>
                </div>
                <div class="dashboard-card-body">
                    <form method="GET" action="" class="row g-3" style="display: block;">
                        <div class="col-md-4">
                            <label for="rating" class="form-label">Filter by rating:</label>
                            <select name="rating" id="rating" class="form-select">
                                <option value="0" <?php echo $search_rating == 0 ? 'selected' : ''; ?>>All ratings</option>
                                <?php for ($i = 5; $i >= 1; $i--): ?>
                                <option value="<?php echo $i; ?>" <?php echo $search_rating == $i ? 'selected' : ''; ?>><?php echo $i; ?> stars</option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="client_name" class="form-label">Search by client name:</label>
                            <input type="text" name="client_name" id="client_name" class="form-control" value="<?php echo htmlspecialchars($search_client); ?>" placeholder="Enter client name...">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i> Filter
                            </button>
                        </div>
                        <div class="col-12 text-end">
                            <a href="expert-avis.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo me-2"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Reviews List -->
    <div class="row mt-4">
        <div class="col-12 fade-in delay-3">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Reviews List (<?php echo $ratings->num_rows; ?>)</h2>
                </div>
                <div class="dashboard-card-body">
                    <?php if ($ratings->num_rows == 0): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-comment-slash fa-4x text-muted mb-3"></i>
                            <h4>No reviews found</h4>
                            <p class="text-muted">No reviews match your search criteria.</p>
                        </div>
                    <?php else: ?>
                        <?php while ($rating = $ratings->fetch_assoc()): ?>
                            <div class="review-card mb-4">
                                <div class="review-header">
                                    <div>
                                        <h5 class="mb-0"><?php echo htmlspecialchars($rating['client_name']); ?></h5>
                                        <small class="text-muted">
                                            <?php echo date('m/d/Y at H:i', strtotime($rating['created_at'])); ?>
                                            <?php if ($rating['consultation_date']): ?>
                                                - Consultation on <?php echo date('m/d/Y', strtotime($rating['consultation_date'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                    <div class="rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?php echo $i <= $rating['rating'] ? 'text-warning' : 'text-muted'; ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                <div class="review-body">
                                    <p><?php echo nl2br(htmlspecialchars($rating['comment'])); ?></p>
                                    
                                    <?php if (!empty($rating['response_text'])): ?>
                                        <div class="expert-response mt-3">
                                            <h6><i class="fas fa-reply me-2"></i> Your response:</h6>
                                            <p class="mb-2"><?php echo nl2br(htmlspecialchars($rating['response_text'])); ?></p>
                                            <button class="btn btn-sm btn-outline-primary edit-response-btn" data-bs-toggle="modal" data-bs-target="#replyModal" data-rating-id="<?php echo $rating['id']; ?>" data-response="<?php echo htmlspecialchars($rating['response_text']); ?>">
                                                <i class="fas fa-edit me-1"></i> Edit response
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-primary reply-btn" data-bs-toggle="modal" data-bs-target="#replyModal" data-rating-id="<?php echo $rating['id']; ?>">
                                            <i class="fas fa-reply me-1"></i> Reply
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal for replying to reviews -->
<div class="modal fade" id="replyModal" tabindex="-1" aria-labelledby="replyModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="replyModalLabel">Reply to Review</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="rating_id" id="rating_id">
                    <div class="form-group">
                        <label for="response_text" class="form-label">Your response:</label>
                        <textarea name="response_text" id="response_text" class="form-control" rows="5" required></textarea>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i> Your response will be publicly visible to all users.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_reply" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-1"></i> Send
                    </button>
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
        fetch('expert-avis.php?fetch_notifications=true')
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
    
    // Handle click on reply button
    const replyButtons = document.querySelectorAll('.reply-btn, .edit-response-btn');
    replyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const ratingId = this.getAttribute('data-rating-id');
            const response = this.getAttribute('data-response') || '';
            
            document.getElementById('rating_id').value = ratingId;
            document.getElementById('response_text').value = response;
            
            // Update modal title
            const modalTitle = document.getElementById('replyModalLabel');
            if (response) {
                modalTitle.textContent = 'Edit Your Response';
            } else {
                modalTitle.textContent = 'Reply to Review';
            }
        });
    });
    
    // Animation for progress bars
    const progressBars = document.querySelectorAll('.progress-bar');
    setTimeout(() => {
        progressBars.forEach(bar => {
            const width = bar.getAttribute('aria-valuenow') + '%';
            bar.style.width = width;
        });
    }, 300);
    
    // Add animation on hover for review cards
    const reviewCards = document.querySelectorAll('.review-card');
    reviewCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
});
</script>
</body>
</html>
