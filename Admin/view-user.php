<?php
// ======================================================================
// INITIALISATION ET CONFIGURATION
// ======================================================================
// Start the session
session_start();

// Include database connection
require_once '../config/config.php';

// ======================================================================
// FONCTIONS UTILITAIRES
// ======================================================================
/**
 * Format date to specified format
 */
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Get time ago from datetime
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'A few seconds ago';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 2592000) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

/**
 * Get status badge HTML
 */
function getStatusBadge($status) {
    switch($status) {
        case 'Online':
            return '<span class="status-badge status-active"><i class="fas fa-circle"></i> Online</span>';
        case 'Offline':
            return '<span class="status-badge status-inactive"><i class="far fa-circle"></i> Offline</span>';
        case 'suspended':
            return '<span class="status-badge status-rejected"><i class="fas fa-ban"></i> Suspended</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

/**
 * Get role badge HTML
 */
function getRoleBadge($role) {
    switch($role) {
        case 'admin':
            return '<span class="status-badge status-admin"><i class="fas fa-shield-alt"></i> Admin</span>';
        case 'expert':
            return '<span class="status-badge status-expert"><i class="fas fa-user-tie"></i> Expert</span>';
        case 'client':
            return '<span class="status-badge status-client"><i class="fas fa-user"></i> Client</span>';
        default:
            return '<span class="status-badge">' . ucfirst($role) . '</span>';
    }
}

/**
 * Get payment status badge HTML
 */
function getPaymentStatusBadge($status) {
    switch($status) {
        case 'completed':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Completed</span>';
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'failed':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Failed</span>';
        case 'processing':
            return '<span class="status-badge status-info"><i class="fas fa-spinner fa-spin"></i> Processing</span>';
        case 'canceled':
            return '<span class="status-badge status-rejected"><i class="fas fa-ban"></i> Canceled</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

/**
 * Get report status badge HTML
 */
function getReportStatusBadge($status) {
    switch($status) {
        case 'pending':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'resolved':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Accepted</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
        case 'remborser':
            return '<span class="status-badge status-info"><i class="fas fa-money-bill-wave"></i> Refunded</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

/**
 * Render star rating HTML
 */
function renderStarRating($rating) {
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

// ======================================================================
// VÉRIFICATION D'AUTHENTIFICATION
// ======================================================================
// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Redirect to login page if not logged in
    header("Location: ../config/logout.php");
    exit;
}

// ======================================================================
// VÉRIFICATION DES PARAMÈTRES
// ======================================================================
// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to users page if no ID provided
    header("Location: users.php");
    exit;
}

$user_id = (int)$_GET['id'];

// ======================================================================
// RÉCUPÉRATION DES PARAMÈTRES SYSTÈME
// ======================================================================
// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Get currency from settings
$currency_query = "SELECT setting_value FROM settings WHERE setting_key = 'currency'";
$currency_result = $conn->query($currency_query);
$currency = ($currency_result && $currency_result->num_rows > 0) ? $currency_result->fetch_assoc()['setting_value'] : 'DA';

// ======================================================================
// RÉCUPÉRATION DES DONNÉES UTILISATEUR
// ======================================================================
// Get user details
$user_query = "SELECT u.*, up.phone, up.address, up.bio, up.profile_image, up.gender, up.dob 
               FROM users u 
               LEFT JOIN user_profiles up ON u.id = up.user_id 
               WHERE u.id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();

if ($user_result->num_rows === 0) {
    // User not found
    $_SESSION["admin_message"] = "User not found.";
    $_SESSION["admin_message_type"] = "error";
    header("Location: users.php");
    exit;
}

$user = $user_result->fetch_assoc();
$is_expert = ($user['role'] === 'expert');

// ======================================================================
// RÉCUPÉRATION DES DONNÉES EXPERT (SI APPLICABLE)
// ======================================================================
// Get expert profile details if user is an expert
$expert_profile = null;
$certificates = [];
$experiences = [];
$educations = [];

if ($is_expert) {
    $expert_query = "SELECT ep.*, c.name as category_name, s.name as subcategory_name
                    FROM expert_profiledetails ep
                    LEFT JOIN categories c ON ep.category = c.id
                    LEFT JOIN subcategories s ON ep.subcategory = s.id
                    WHERE ep.user_id = ?";
    $stmt = $conn->prepare($expert_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $expert_result = $stmt->get_result();
    
    if ($expert_result->num_rows > 0) {
        $expert_profile = $expert_result->fetch_assoc();
        
        // Get banking information
        $banking_query = "SELECT * FROM banking_information WHERE user_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($banking_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $banking_result = $stmt->get_result();
        
        if ($banking_result->num_rows > 0) {
            $expert_profile['banking'] = $banking_result->fetch_assoc();
        }
        
        // Get skills
        $skills_query = "SELECT skill_name FROM skills WHERE profile_id = ?";
        $stmt = $conn->prepare($skills_query);
        $stmt->bind_param("i", $expert_profile['id']);
        $stmt->execute();
        $skills_result = $stmt->get_result();
        
        $skills = [];
        while ($skill = $skills_result->fetch_assoc()) {
            $skills[] = $skill['skill_name'];
        }
        $expert_profile['skills'] = $skills;
        
        // Get social links
        $social_query = "SELECT * FROM expert_social_links WHERE user_id = ? ORDER BY id DESC LIMIT 1";
        $stmt = $conn->prepare($social_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $social_result = $stmt->get_result();
        
        if ($social_result->num_rows > 0) {
            $expert_profile['social_links'] = $social_result->fetch_assoc();
        }

        // Get certificates with approved status
        $certificates_query = "SELECT * FROM certificates WHERE profile_id = ? AND status = 'approved' ORDER BY id DESC";
        $stmt = $conn->prepare($certificates_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $certificates_result = $stmt->get_result();
        
        while ($certificate = $certificates_result->fetch_assoc()) {
            $certificates[] = $certificate;
        }

        // Get experiences with approved status
        $experiences_query = "SELECT * FROM experiences WHERE profile_id = ? AND status = 'approved' ORDER BY created_at DESC";
        $stmt = $conn->prepare($experiences_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $experiences_result = $stmt->get_result();
        
        while ($experience = $experiences_result->fetch_assoc()) {
            $experiences[] = $experience;
        }

        // Get education with approved status
        $educations_query = "SELECT * FROM formations WHERE profile_id = ? AND status = 'approved' ORDER BY created_at DESC";
        $stmt = $conn->prepare($educations_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $educations_result = $stmt->get_result();
        
        while ($education = $educations_result->fetch_assoc()) {
            $educations[] = $education;
        }
    }
}// Get expert profile data
$profile_sql = "SELECT * FROM expert_profiledetails WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc()['user_id'] ?? null;
   
   

    // Get number of ratings for expert
    $ratings_count = 0;
    if ($is_expert) {
        $ratings_count_query = "SELECT COUNT(*) as count FROM expert_ratings WHERE expert_id = ?";
        $stmt = $conn->prepare($ratings_count_query);
        $stmt->bind_param("i", $profile);
        $stmt->execute();
        $ratings_count_result = $stmt->get_result();
        $ratings_count = $ratings_count_result->fetch_assoc()['count'] ?? 0;
    }

// ======================================================================
// RÉCUPÉRATION DES ÉVALUATIONS (SI EXPERT)
// ======================================================================
// Get user ratings if expert
$ratings = [];
if ($is_expert) {
    $ratings_query = "SELECT er.*, u.full_name as client_name, u.email as client_email, up.profile_image as client_image,
                     err.response_text, err.created_at as response_date
                     FROM expert_ratings er
                     JOIN users u ON er.client_id = u.id
                     LEFT JOIN user_profiles up ON u.id = up.user_id
                     LEFT JOIN expert_rating_responses err ON er.id = err.rating_id
                     WHERE er.expert_id = ?
                     ORDER BY er.created_at DESC";
    $stmt = $conn->prepare($ratings_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $ratings_result = $stmt->get_result();
    
    while ($rating = $ratings_result->fetch_assoc()) {
        $ratings[] = $rating;
    }
    
    // Calculate average rating
    $avg_rating_query = "SELECT AVG(rating) as avg_rating FROM expert_ratings WHERE expert_id = ?";
    $stmt = $conn->prepare($avg_rating_query);
    $stmt->bind_param("i", $profile);
    $stmt->execute();
    $avg_rating_result = $stmt->get_result();
    $avg_rating = $avg_rating_result->fetch_assoc()['avg_rating'] ?? 0;
}

// ======================================================================
// RÉCUPÉRATION DES TRANSACTIONS
// ======================================================================
// Get transactions (payments for experts, consultations for clients)
$transactions = [];
if ($is_expert) {
    $transactions_query = "SELECT p.*, c.consultation_date, c.consultation_time, c.status as consultation_status,
                          u.full_name as client_name
                          FROM payments p
                          JOIN consultations c ON p.consultation_id = c.id
                          JOIN users u ON p.client_id = u.id
                          WHERE p.expert_id = ?
                          ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($transactions_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $transactions_result = $stmt->get_result();
    
    while ($transaction = $transactions_result->fetch_assoc()) {
        $transactions[] = $transaction;
    }
} else {
    // For clients
    $transactions_query = "SELECT p.*, c.consultation_date, c.consultation_time, c.status as consultation_status,
                          u.full_name as expert_name
                          FROM payments p
                          JOIN consultations c ON p.consultation_id = c.id
                          JOIN users u ON p.expert_id = u.id
                          WHERE p.client_id = ?
                          ORDER BY p.created_at DESC";
    $stmt = $conn->prepare($transactions_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $transactions_result = $stmt->get_result();
    
    while ($transaction = $transactions_result->fetch_assoc()) {
        $transactions[] = $transaction;
    }
}

    // For clients, get ratings they've given to experts and any responses
    $client_ratings = [];
    if (!$is_expert) {
        $client_ratings_query = "SELECT er.*, u.full_name as expert_name, 
                                err.response_text, err.created_at as response_date
                                FROM expert_ratings er
                                JOIN users u ON er.expert_id = u.id
                                LEFT JOIN expert_rating_responses err ON er.id = err.rating_id
                                WHERE er.client_id = ?
                                ORDER BY er.created_at DESC";
        $stmt = $conn->prepare($client_ratings_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $client_ratings_result = $stmt->get_result();
        
        while ($rating = $client_ratings_result->fetch_assoc()) {
            $client_ratings[] = $rating;
        }
    }

// ======================================================================
// RÉCUPÉRATION DES DEMANDES DE RETRAIT/FINANCEMENT
// ======================================================================
// Get withdrawal requests for experts
$withdrawals = [];
if ($is_expert) {
    $withdrawals_query = "SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($withdrawals_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $withdrawals_result = $stmt->get_result();
    
    while ($withdrawal = $withdrawals_result->fetch_assoc()) {
        $withdrawals[] = $withdrawal;
    }
}

// Get fund requests for clients
$fund_requests = [];
if (!$is_expert) {
    $fund_requests_query = "SELECT * FROM fund_requests WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($fund_requests_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $fund_requests_result = $stmt->get_result();
    
    while ($fund_request = $fund_requests_result->fetch_assoc()) {
        $fund_requests[] = $fund_request;
    }
}

// ======================================================================
// RÉCUPÉRATION DES SIGNALEMENTS
// ======================================================================
// Get reports involving this user
$reports_query = "SELECT r.*, c.consultation_date, c.consultation_time,
                 u1.full_name as reporter_name, u1.email as reporter_email,
                 u2.full_name as reported_name, u2.email as reported_email
                 FROM reports r
                 JOIN consultations c ON r.consultation_id = c.id
                 JOIN users u1 ON r.reporter_id = u1.id
                 JOIN users u2 ON r.reported_id = u2.id
                 WHERE r.reporter_id = ? OR r.reported_id = ?
                 ORDER BY r.created_at DESC";
$stmt = $conn->prepare($reports_query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$reports_result = $stmt->get_result();

$reports_as_reporter = [];
$reports_as_reported = [];
while ($report = $reports_result->fetch_assoc()) {
    if ($report['reporter_id'] == $user_id) {
        $reports_as_reporter[] = $report;
    } else {
        $reports_as_reported[] = $report;
    }
}

// ======================================================================
// RÉCUPÉRATION DES NOTIFICATIONS
// ======================================================================
// Get unread notifications count
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get pending withdrawals count
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending fund requests count
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending expert profile count
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];

$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];



$report_suspend_query = "SELECT 
                u.id, 
                (SELECT COUNT(*) FROM reports WHERE reported_id = u.id AND status IN ('remborser','accepted')) AS reports_count,
                (SELECT COUNT(*) FROM user_suspensions WHERE user_id = u.id) AS suspension_count
                FROM users u WHERE u.id = $user_id";
$report_suspend_result = $conn->query($report_suspend_query);
$report_suspend = $report_suspend_result->fetch_assoc();

// ======================================================================
// TRAITEMENT DES ACTIONS
// ======================================================================
// Process user deletion
if (isset($_POST['delete_user'])) {
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Delete user
        $delete_query = "DELETE FROM users WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        $_SESSION["admin_message"] = "User deleted successfully.";
        $_SESSION["admin_message_type"] = "success";
        header("Location: users.php");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        
        $_SESSION["admin_message"] = "Error deleting user: " . $e->getMessage();
        $_SESSION["admin_message_type"] = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ======================================================================
        * VARIABLES CSS
        * ====================================================================== */
        :root {
            /* Main Colors */
            --primary-color: #7C3AED;
            --primary-light: #A78BFA;
            --primary-dark: #6D28D9;
            --primary-bg: rgba(124, 58, 237, 0.1);
            --primary-gradient: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
            
            --secondary-color: #64748b;
            --secondary-light: #94a3b8;
            --secondary-dark: #475569;
            --secondary-bg: rgba(100, 116, 139, 0.1);
            
            --success-color: #10b981;
            --success-light: #34d399;
            --success-dark: #059669;
            --success-bg: rgba(16, 185, 129, 0.1);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            
            --warning-color: #f59e0b;
            --warning-light: #fbbf24;
            --warning-dark: #d97706;
            --warning-bg: rgba(245, 158, 11, 0.1);
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            
            --danger-color: #ef4444;
            --danger-light: #f87171;
            --danger-dark: #dc2626;
            --danger-bg: rgba(239, 68, 44, 0.1);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            
            --info-color: #06b6d4;
            --info-light: #22d3ee;
            --info-dark: #0891b2;
            --info-bg: rgba(6, 182, 212, 0.1);
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            
            /* Neutral Colors */
            --light-color: #f8fafc;
            --light-color-2: #f1f5f9;
            --light-color-3: #e2e8f0;
            
            --dark-color: #0f172a;
            --dark-color-2: #1e293b;
            --dark-color-3: #334155;
            
            --border-color: #e2e8f0;
            --border-color-dark: #cbd5e1;
            
            /* Background Colors */
            --card-bg: #ffffff;
            --body-bg: #f8fafc;
            --body-bg-gradient: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            
            /* Text Colors */
            --text-color: #334155;
            --text-color-light: #64748b;
            --text-color-lighter: #94a3b8;
            --text-color-dark: #1e293b;
            --text-color-darker: #0f172a;
            
            /* Shadow Variables */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 6px 10px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
            --shadow-outline: 0 0 0 3px rgba(124, 58, 237, 0.2);
            
            /* Border Radius */
            --radius-sm: 0.25rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-full: 9999px;
            
            /* Transitions */
            --transition: all 0.3s ease;
            --transition-slow: all 0.5s ease;
            --transition-fast: all 0.15s ease;
            --transition-bounce: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            
            /* Z-index */
            --z-negative: -1;
            --z-normal: 1;
            --z-tooltip: 10;
            --z-fixed: 100;
            --z-modal: 1000;
        }

        /* ======================================================================
        * STYLES DE BASE
        * ====================================================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        
        body {
            background: var(--body-bg-gradient);
            color: var(--text-color);
            line-height: 1.6;
            font-size: 0.95rem;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%237C3AED' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            z-index: var(--z-negative);
            pointer-events: none;
        }
        
        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition-fast);
        }
        
        a:hover {
            color: var(--primary-dark);
        }

        /* ======================================================================
        * LAYOUT PRINCIPAL
        * ====================================================================== */
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: var(--dark-color-2);
            color: white;
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: var(--z-fixed);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: var(--dark-color-3);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: var(--radius-full);
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: var(--transition);
        }

        /* ======================================================================
        * SIDEBAR STYLES
        * ====================================================================== */
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .sidebar-header p {
            font-size: 0.875rem;
            opacity: 0.7;
        }
        
        .sidebar-menu {
            padding: 1.5rem 0;
        }
        
        .menu-item {
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            transition: var(--transition);
            text-decoration: none;
            color: var(--light-color-3);
            position: relative;
            overflow: hidden;
        }
        
        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--primary-gradient);
            transform: scaleY(0);
            transition: var(--transition);
        }
        
        .menu-item:hover, .menu-item.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .menu-item:hover::before, .menu-item.active::before {
            transform: scaleY(1);
        }
        
        .menu-item.active {
            background-color: rgba(124, 58, 237, 0.1);
            font-weight: 500;
        }
        
        .menu-item i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
            color: var(--primary-light);
            transition: var(--transition);
        }
        
        .menu-item:hover i, .menu-item.active i {
            color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 0.5rem;
            right: 1.5rem;
            background: var(--danger-gradient);
            color: white;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.1rem 0.4rem;
            min-width: 1.2rem;
            text-align: center;
            box-shadow: var(--shadow);
            animation: pulse 2s infinite;
        }

        /* ======================================================================
        * HEADER STYLES
        * ====================================================================== */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: var(--radius-full);
        }
        
        .header h1 {
            color: var(--dark-color);
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .header h1 i {
            color: var(--primary-color);
            background: var(--primary-bg);
            padding: 0.5rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 1rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-full);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .user-info:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
        }
        
        .user-role {
            color: var(--text-color-light);
            font-size: 0.75rem;
        }

        /* ======================================================================
        * CARD STYLES
        * ====================================================================== */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--border-color);
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
        }
        
        .card:hover::before {
            opacity: 1;
        }
        
        .card-header {
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(255, 255, 255, 0.8));
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-header h2 i {
            color: var(--primary-color);
            background: var(--primary-bg);
            padding: 0.5rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-footer {
            background-color: var(--light-color);
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
        }

        /* ======================================================================
        * USER PROFILE STYLES
        * ====================================================================== */
        .user-profile {
            display: flex;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .user-profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            flex-shrink: 0;
        }
        
        .user-profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-profile-avatar span {
            position: relative;
            z-index: 1;
        }
        
        .user-profile-avatar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 50%);
        }
        
        .user-profile-info {
            flex: 1;
        }
        
        .user-profile-info h3 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--dark-color);
        }
        
        .user-profile-info p {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .expert-profile-details {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .expert-profile-details h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--dark-color);
        }
        
        .expert-profile-details h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 1rem 0 0.5rem;
            color: var(--dark-color);
        }
        
        .expert-profile-details ul {
            list-style: none;
            padding-left: 1rem;
        }
        
        .expert-profile-details li {
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .expert-profile-details li i {
            color: var(--primary-color);
        }

        /* ======================================================================
        * TABLE STYLES
        * ====================================================================== */
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 1rem;
        }
        
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table th, .table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .table th {
            font-weight: 600;
            color: var(--dark-color);
            background-color: var(--light-color-2);
        }
        
        .table tr:hover {
            background-color: var(--light-color-2);
        }
        
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: var(--light-color);
        }
        
        .table-striped tbody tr:nth-of-type(odd):hover {
            background-color: var(--light-color-2);
        }

        .text-warning{
            color: var(--warning-color);
        }
        /* ======================================================================
        * BUTTON STYLES
        * ====================================================================== */
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
            z-index: var(--z-normal);
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%);
            transition: var(--transition);
            z-index: -1;
        }
        
        .btn:hover::before {
            transform: translateX(0);
        }
        
        .btn i {
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.3);
        }
        
        .btn-primary:hover {
            box-shadow: 0 6px 15px rgba(124, 58, 237, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: var(--info-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(6, 182, 212, 0.3);
        }
        
        .btn-info:hover {
            box-shadow: 0 6px 15px rgba(6, 182, 212, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        /* ======================================================================
        * STATUS BADGE STYLES
        * ====================================================================== */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            gap: 0.375rem;
            box-shadow: var(--shadow-sm);
        }
        
        .status-admin {
            background: linear-gradient(135deg, var(--dark-color) 0%, var(--dark-color-2) 100%);
            color: white;
        }
        
        .status-expert {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
        }
        
        .status-client {
            background: linear-gradient(135deg, var(--success-color) 0%, var(--success-dark) 100%);
            color: white;
        }
        
        .status-active {
            background: var(--success-gradient);
            color: white;
        }
        
        .status-inactive {
            background: var(--danger-gradient);
            color: white;
        }
        
        .status-pending {
            background: var(--warning-gradient);
            color: white;
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

        /* ======================================================================
        * ALERT STYLES
        * ====================================================================== */
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInDown 0.5s ease;
            position: relative;
            box-shadow: var(--shadow);
            border-left: 4px solid transparent;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }
        
        .alert-error {
            background-color: var(--danger-bg);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
        }
        
        .alert-close {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: var(--transition-fast);
        }
        
        .alert-close:hover {
            opacity: 1;
            transform: translateY(-50%) rotate(90deg);
        }

        /* ======================================================================
        * ANIMATIONS
        * ====================================================================== */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* ======================================================================
        * ACCORDION STYLES
        * ====================================================================== */
        .accordion-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 1.5rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            margin-bottom: 1rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .accordion-header:hover {
            background-color: var(--light-color-3);
        }

        .accordion-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .accordion-header h3 i {
            color: var(--primary-color);
        }

        .accordion-header .toggle-icon {
            transition: var(--transition);
            color: var(--primary-color);
        }

        .accordion-header.active .toggle-icon {
            transform: rotate(180deg);
        }

        .accordion-content {
            display: none;
            padding: 1rem;
            background-color: var(--card-bg);
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            animation: fadeIn 0.3s ease;
        }

        .accordion-content.active {
            display: block;
        }

        /* ======================================================================
        * CERTIFICATE, EXPERIENCE, EDUCATION STYLES
        * ====================================================================== */
        .certificate-card, .experience-card, .education-card {
            background-color: var(--light-color);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border-left: 3px solid var(--primary-color);
        }

        .certificate-card h4, .experience-card h4, .education-card h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .certificate-card p, .experience-card p, .education-card p {
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .certificate-card .certificate-date, 
        .experience-card .experience-date, 
        .education-card .education-date {
            color: var(--text-color-light);
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .certificate-card .certificate-issuer, 
        .experience-card .experience-company, 
        .education-card .education-institution {
            font-weight: 500;
            color: var(--primary-color);
        }

        /* ======================================================================
        * RATING STYLES
        * ====================================================================== */
        .rating-card {
            background-color: var(--light-color);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border-left: 3px solid var(--warning-color);
        }

        .rating-card .rating-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .rating-card .rating-client {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .rating-card .rating-client-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            overflow: hidden;
            flex-shrink: 0;
        }

        .rating-card .rating-client-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .rating-card .rating-client-info {
            display: flex;
            flex-direction: column;
        }

        .rating-card .rating-client-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .rating-card .rating-client-email {
            font-size: 0.8rem;
            color: var(--text-color-light);
        }

        .rating-card .rating-stars {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .rating-card .rating-stars i {
            color: var(--warning-color);
        }

        .rating-card .rating-date {
            font-size: 0.8rem;
            color: var(--text-color-light);
            margin-bottom: 0.5rem;
        }

        .rating-card .rating-comment {
            padding: 0.75rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
        }

        .rating-card .rating-response {
            padding: 0.75rem;
            background-color: var(--primary-bg);
            border-radius: var(--radius);
            margin-top: 0.5rem;
            margin-left: 1.5rem;
            position: relative;
        }

        .rating-card .rating-response::before {
            content: '';
            position: absolute;
            top: -10px;
            left: 20px;
            border-left: 10px solid transparent;
            border-right: 10px solid transparent;
            border-bottom: 10px solid var(--primary-bg);
        }

        .rating-card .rating-response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.8rem;
            color: var(--primary-color);
            font-weight: 500;
        }
       .status-reports-low {
            background: var(--warning-gradient);
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

        /* ======================================================================
        * REPORT STYLES
        * ====================================================================== */
        .report-section {
            margin-bottom: 2rem;
        }

        .report-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .report-card {
            background-color: var(--light-color);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-sm);
            border-left: 3px solid var(--danger-color);
        }

        .report-card .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .report-card .report-id {
            font-weight: 600;
            color: var(--dark-color);
        }

        .report-card .report-date {
            font-size: 0.8rem;
            color: var(--text-color-light);
        }

        .report-card .report-users {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.75rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .report-card .report-user {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .report-card .report-user-role {
            font-size: 0.8rem;
            font-weight: 500;
            color: var(--text-color-light);
            margin-bottom: 0.25rem;
        }

        .report-card .report-user-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .report-card .report-user-email {
            font-size: 0.8rem;
            color: var(--text-color-light);
        }

        .report-card .report-details {
            margin-bottom: 0.75rem;
        }

        .report-card .report-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
            background-color: var(--danger-bg);
            color: var(--danger-color);
        }

        .report-card .report-message {
            padding: 0.75rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            font-size: 0.9rem;
        }

        .report-card .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
        }

        .report-card .report-consultation {
            font-size: 0.8rem;
            color: var(--text-color-light);
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
        }

        .tab {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: var(--text-color-light);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
        }

        .tab.active {
            color: var(--primary-color);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* ======================================================================
        * RESPONSIVE STYLES
        * ====================================================================== */
        @media (max-width: 1200px) {
            .user-profile {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .user-profile-info p {
                justify-content: center;
            }
        }
        
        @media (max-width: 992px) {
            .user-profile-avatar {
                width: 120px;
                height: 120px;
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                overflow-y: hidden;
                max-height: 300px;
                transition: max-height 0.3s ease;
            }
            
            .sidebar.expanded {
                max-height: 100vh;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .user-info {
                width: 100%;
            }
        }

        /* ======================================================================
        * ACCORDION STYLES
        * ====================================================================== */
        .accordion-header {
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .accordion-header i.toggle-icon {
            transition: transform 0.3s ease;
        }

        .accordion-header.active i.toggle-icon {
            transform: rotate(180deg);
        }

        .accordion-content {
            display: none;
            padding: 1rem 0;
            animation: fadeIn 0.5s ease;
        }

        .accordion-content.active {
            display: block;
        }

        .rating-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: var(--light-color);
            transition: var(--transition);
        }

        .rating-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .rating-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .rating-client {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rating-client-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            overflow: hidden;
        }

        .rating-client-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .rating-client-info {
            display: flex;
            flex-direction: column;
        }

        .rating-client-name {
            font-weight: 600;
            color: var(--dark-color);
        }

        .rating-client-email {
            font-size: 0.75rem;
            color: var(--text-color-light);
        }

        .rating-stars {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .rating-date {
            font-size: 0.75rem;
            color: var(--text-color-light);
        }

        .rating-comment {
            margin: 0.5rem 0;
            padding: 0.5rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            border-left: 3px solid var(--primary-color);
        }

        .rating-response {
            margin-top: 0.5rem;
            padding: 0.5rem;
            background-color: var(--primary-bg);
            border-radius: var(--radius);
            border-left: 3px solid var(--primary-dark);
        }

        .rating-response-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.25rem;
            font-size: 0.75rem;
            color: var(--text-color-light);
        }

        .certificate-item, .experience-item, .education-item {
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
            background-color: var(--light-color);
            transition: var(--transition);
        }

        .certificate-item:hover, .experience-item:hover, .education-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .certificate-header, .experience-header, .education-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .certificate-title, .experience-title, .education-title {
            font-weight: 600;
            color: var(--dark-color);
        }

        .certificate-date, .experience-date, .education-date {
            font-size: 0.75rem;
            color: var(--text-color-light);
        }

        .certificate-issuer, .experience-company, .education-institution {
            font-size: 0.875rem;
            color: var(--text-color);
            margin-bottom: 0.25rem;
        }

        .certificate-description, .experience-description, .education-description {
            font-size: 0.875rem;
            color: var(--text-color-light);
        }

        .reports-section {
            margin-bottom: 1.5rem;
        }

        .reports-section h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .no-items-message {
            text-align: center;
            padding: 2rem;
            color: var(--text-color-light);
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            margin: 1rem 0;
        }

        .no-items-message i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: var(--text-color-lighter);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo htmlspecialchars($site_name); ?></h2>
                <p>Admin Dashboard</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="menu-item active">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="expert-profiles.php" class="menu-item">
                    <i class="fas fa-user-tie"></i> Expert Profiles
                    <?php if ($pending_review_profiles > 0): ?>
                        <span class="notification-badge"><?php echo $pending_review_profiles; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="expert-messages.php" class="menu-item">
                    <i class="fas fa-comments"></i> Expert Messages
                </a>
                <a href="client-messages.php" class="menu-item">
                    <i class="fas fa-comments"></i> Client Messages
                    <?php if ($pending_messages > 0): ?>
                        <span class="notification-badge"><?php echo $pending_messages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-flag"></i> Reports
                    <?php if ($pending_reports > 0): ?>
                        <span class="notification-badge"><?php echo $pending_reports; ?></span>
                    <?php endif; ?>
                </a>
                <a href="withdrawal-requests.php" class="menu-item">
                    <i class="fas fa-money-bill-wave"></i> Withdrawal Requests
                    <?php if ($pending_withdrawals > 0): ?>
                        <span class="notification-badge"><?php echo $pending_withdrawals; ?></span>
                    <?php endif; ?>
                </a>
                <a href="fund-requests.php" class="menu-item">
                    <i class="fas fa-wallet"></i> Fund Requests
                    <?php if ($pending_fund_requests > 0): ?>
                        <span class="notification-badge"><?php echo $pending_fund_requests; ?></span>
                    <?php endif; ?>
                </a>
                <a href="consultations.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i> Consultations
                </a>
                <a href="transactions.php" class="menu-item">
                    <i class="fas fa-exchange-alt"></i> Transactions
                </a>
                <a href="categories.php" class="menu-item">
                    <i class="fas fa-tags"></i> Categories
                </a>
                <a href="notifications.php" class="menu-item">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="../config/logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-user"></i> User Profile</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if (isset($_SESSION["full_name"])): ?>
                            <?php echo strtoupper(substr($_SESSION["full_name"], 0, 1)); ?>
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION["full_name"] ?? "Admin"); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if (isset($_SESSION["admin_message"])): ?>
                <div class="alert alert-<?php echo $_SESSION["admin_message_type"]; ?>">
                    <i class="fas fa-<?php echo $_SESSION["admin_message_type"] === "success" ? "check-circle" : "exclamation-circle"; ?>"></i>
                    <div><?php echo $_SESSION["admin_message"]; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
                <?php unset($_SESSION["admin_message"]); unset($_SESSION["admin_message_type"]); ?>
            <?php endif; ?>
            
            <!-- User Information Card -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user"></i> User Information</h2>
                    <a href="#" class="btn btn-sm btn-outline" onclick="history.back();">
                        <i class="fas fa-arrow-left"></i> Back to Users
                    </a>
                </div>
                <div class="card-body">
                    <div class="user-profile">
                        <div class="user-profile-avatar">
                            <?php if ($user['profile_image']): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                            <?php else: ?>
                                <?php
                                $name_parts = explode(" ", $user['full_name']);
                                $initials = strtoupper(substr($name_parts[0], 0, 1) . (count($name_parts) > 1 ? substr(end($name_parts), 0, 1) : ""));
                                ?>
                                <span><?php echo htmlspecialchars($initials); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="user-profile-info">
                            <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                            <p><i class="fas fa-user-tag"></i> <?php echo getRoleBadge(htmlspecialchars($user['role'])); ?></p>
                            <p><i class="fas fa-circle"></i> <?php echo getStatusBadge(htmlspecialchars($user['status'])); ?></p>
                            <?php if ($is_expert && isset($avg_rating)): ?>
                                <p><i class="fas fa-star"></i> <?php echo renderStarRating($avg_rating); ?> (<?php echo number_format($avg_rating, 2); ?>)</p>
                            <?php endif; ?>
                            <?php if ($is_expert && isset($ratings_count)): ?>
                                <p><i class="fas fa-users"></i> <?php echo $ratings_count; ?> ratings received</p>
                            <?php endif; ?>
                            <?php if ($user['phone']): ?>
                                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></p>
                            <?php endif; ?>
                            <?php if ($user['address']): ?>
                                <p><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($user['address']); ?></p>
                            <?php endif; ?>
                            <?php if ($user['bio']): ?>
                                <p><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($user['bio']); ?></p>
                            <?php endif; ?>
                            <?php if ($is_expert): ?>
                                <a href="expert-messages.php?expert_id=<?php echo $user_id; ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-comments"></i> Chat with Expert
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($is_expert && $expert_profile): ?>
                        <div class="expert-profile-details">
                            <h4>Expert Profile Details</h4>
                            <p><i class="fas fa-folder"></i> Category: <?php echo htmlspecialchars($expert_profile['category_name']); ?></p>
                            <p><i class="fas fa-folder-open"></i> Subcategory: <?php echo htmlspecialchars($expert_profile['subcategory_name']); ?></p>
                            <?php if (isset($expert_profile['skills']) && is_array($expert_profile['skills'])): ?>
                                <p><i class="fas fa-tools"></i> Skills: <?php echo htmlspecialchars(implode(", ", $expert_profile['skills'])); ?></p>
                            <?php endif; ?>
                            <?php if (isset($expert_profile['social_links'])): ?>
                                <h5><i class="fas fa-share-alt"></i> Social Links:</h5>
                                <ul>
                                    <?php if ($expert_profile['social_links']['facebook_url']): ?>
                                        <li><i class="fab fa-facebook"></i> <a href="<?php echo htmlspecialchars($expert_profile['social_links']['facebook_url']); ?>" target="_blank">Facebook</a></li>
                                    <?php endif; ?>
                                    <?php if ($expert_profile['social_links']['instagram_url']): ?>
                                        <li><i class="fab fa-instagram"></i> <a href="<?php echo htmlspecialchars($expert_profile['social_links']['instagram_url']); ?>" target="_blank">Instagram</a></li>
                                    <?php endif; ?>
                                    <?php if ($expert_profile['social_links']['linkedin_url']): ?>
                                        <li><i class="fab fa-linkedin"></i> <a href="<?php echo htmlspecialchars($expert_profile['social_links']['linkedin_url']); ?>" target="_blank">LinkedIn</a></li>
                                    <?php endif; ?>
                                </ul>
                            <?php endif; ?>
                            
                            <!-- Certificates Section -->
                            <?php if (!empty($certificates)): ?>
                                <div class="accordion">
                                    <div class="accordion-header" data-target="certificates-content">
                                        <h3><i class="fas fa-certificate"></i> Certificates</h3>
                                        <i class="fas fa-chevron-down toggle-icon"></i>
                                    </div>
                                    <div class="accordion-content" id="certificates-content">
                                        <?php foreach ($certificates as $certificate): ?>
                                            <div class="certificate-item">
                                                <div class="certificate-header">
                                                    <div class="certificate-title"><?php echo htmlspecialchars($certificate['certificate_name']); ?></div>
                                                    <div class="certificate-date"><?php echo formatDate($certificate['issue_date']); ?></div>
                                                </div>
                                                <div class="certificate-issuer"><?php echo htmlspecialchars($certificate['issuing_organization']); ?></div>
                                                <?php if ($certificate['description']): ?>
                                                    <div class="certificate-description"><?php echo htmlspecialchars($certificate['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Experiences Section -->
                            <?php if (!empty($experiences)): ?>
                                <div class="accordion">
                                    <div class="accordion-header" data-target="experiences-content">
                                        <h3><i class="fas fa-briefcase"></i> Work Experience</h3>
                                        <i class="fas fa-chevron-down toggle-icon"></i>
                                    </div>
                                    <div class="accordion-content" id="experiences-content">
                                        <?php foreach ($experiences as $experience): ?>
                                            <div class="experience-item">
                                                <div class="experience-header">
                                                    <div class="experience-title"><?php echo htmlspecialchars($experience['job_title']); ?></div>
                                                    <div class="experience-date">
                                                        <?php echo formatDate($experience['created_at']); ?> - 
                                                        <?php echo $experience['end_date'] ? formatDate($experience['end_date']) : 'Present'; ?>
                                                    </div>
                                                </div>
                                                <div class="experience-company"><?php echo htmlspecialchars($experience['company_name']); ?></div>
                                                <?php if ($experience['description']): ?>
                                                    <div class="experience-description"><?php echo htmlspecialchars($experience['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Education Section -->
                            <?php if (!empty($educations)): ?>
                                <div class="accordion">
                                    <div class="accordion-header" data-target="educations-content">
                                        <h3><i class="fas fa-graduation-cap"></i> Education</h3>
                                        <i class="fas fa-chevron-down toggle-icon"></i>
                                    </div>
                                    <div class="accordion-content" id="educations-content">
                                        <?php foreach ($educations as $education): ?>
                                            <div class="education-item">
                                                <div class="education-header">
                                                    <div class="education-title"><?php echo htmlspecialchars($education['degree']); ?></div>
                                                    <div class="education-date">
                                                        <?php echo formatDate($education['created_at']); ?> - 
                                                        <?php echo $education['end_date'] ? formatDate($education['end_date']) : 'Present'; ?>
                                                    </div>
                                                </div>
                                                <div class="education-institution"><?php echo htmlspecialchars($education['institution']); ?></div>
                                                <?php if ($education['description']): ?>
                                                    <div class="education-description"><?php echo htmlspecialchars($education['description']); ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Ratings Section -->
                            <?php if (!empty($ratings)): ?>
                                <div class="accordion">
                                    <div class="accordion-header" data-target="ratings-content">
                                        <h3><i class="fas fa-star"></i> Ratings & Reviews</h3>
                                        <i class="fas fa-chevron-down toggle-icon"></i>
                                    </div>
                                    <div class="accordion-content" id="ratings-content">
                                        <?php foreach ($ratings as $rating): ?>
                                            <div class="rating-item">
                                                <div class="rating-header">
                                                    <div class="rating-client">
                                                        <div class="rating-client-avatar">
                                                            <?php if ($rating['client_image']): ?>
                                                                <img src="<?php echo htmlspecialchars($rating['client_image']); ?>" alt="Client">
                                                            <?php else: ?>
                                                                <img src="/placeholder.svg?height=50&width=50" alt="Client">
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="rating-client-info">
                                                            <div class="rating-client-name"><?php echo htmlspecialchars($rating['client_name']); ?></div>
                                                            <div class="rating-client-email"><?php echo htmlspecialchars($rating['client_email']); ?></div>
                                                        </div>
                                                    </div>
                                                    <div class="rating-stars">
                                                        <?php echo renderStarRating($rating['rating']); ?>
                                                    </div>
                                                </div>
                                                <div class="rating-date"><?php echo formatDate($rating['created_at'], 'd/m/Y H:i'); ?></div>
                                                <div class="rating-comment"><?php echo htmlspecialchars($rating['comment']); ?></div>
                                                <?php if ($rating['response_text']): ?>
                                                    <div class="rating-response">
                                                        <div class="rating-response-header">
                                                            <span>Expert Response</span>
                                                            <span><?php echo formatDate($rating['response_date'], 'd/m/Y H:i'); ?></span>
                                                        </div>
                                                        <?php echo htmlspecialchars($rating['response_text']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <div class="reports_suspend">
                         <?php if ($report_suspend['reports_count'] == 0): ?>
                                                    <span class="status-badge status-approved">
                                                        <i class="fas fa-check"></i> 0
                                                    </span>
                                                <?php elseif ($report_suspend['reports_count'] >= 1 && $report_suspend['reports_count'] <= 7): ?>
                                                    <span class="status-badge status-reports-low">
                                                        <i class="fas fa-flag"></i> <?php echo $report_suspend['reports_count']; ?>
                                                    </span>
                                                <?php elseif ($report_suspend['reports_count'] >= 8 && $report_suspend['reports_count'] <= 14): ?>
                                                    <span class="status-badge status-reports-medium">
                                                        <i class="fas fa-flag"></i> <?php echo $report_suspend['reports_count']; ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge status-reports-high">
                                                        <i class="fas fa-exclamation-triangle"></i> <?php echo $report_suspend['reports_count']; ?>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($report_suspend['suspension_count'] > 0): ?>
                                                    <span class="status-badge status-reports-low">
                                                        <i class="fas fa-flag"></i> <?php echo $report_suspend['suspension_count']; ?>
                                                    </span>
                                                <?php endif; ?>

                    </div>
                    <form method="post" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                        <button type="submit" name="delete_user" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash"></i> Delete User
                        </button>
                    </form>
                </div>
            </div>

            <!-- Accordion Sections -->
            <div class="accordion-container">
                <!-- Transactions Accordion -->
                <div class="accordion">
                    <div class="accordion-header" data-target="transactions-content">
                        <h3><i class="fas fa-exchange-alt"></i> Transactions</h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="accordion-content" id="transactions-content">
                        <?php if (!empty($transactions)): ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Date</th>
                                            <th><?php echo $is_expert ? 'Client' : 'Expert'; ?></th>
                                            <th>Amount</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($transactions as $transaction): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                                                <td><?php echo formatDate(htmlspecialchars($transaction['created_at'])); ?></td>
                                                <td><?php echo htmlspecialchars($is_expert ? $transaction['client_name'] : $transaction['expert_name']); ?></td>
                                                <td><?php echo htmlspecialchars(number_format($transaction['amount'], 2) . ' ' . $currency); ?></td>
                                                <td><?php echo getPaymentStatusBadge(htmlspecialchars($transaction['status'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center"><i class="fas fa-info-circle"></i> No transactions found for this user.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Withdrawal/Fund Requests Accordion -->
                <?php if ($is_expert): ?>
                    <div class="accordion">
                        <div class="accordion-header" data-target="withdrawals-content">
                            <h3><i class="fas fa-money-bill-wave"></i> Withdrawal Requests</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="accordion-content" id="withdrawals-content">
                            <?php if (!empty($withdrawals)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($withdrawals as $withdrawal): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($withdrawal['id']); ?></td>
                                                    <td><?php echo formatDate(htmlspecialchars($withdrawal['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars(number_format($withdrawal['amount'], 2) . ' ' . $currency); ?></td>
                                                    <td><?php echo getPaymentStatusBadge(htmlspecialchars($withdrawal['status'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center"><i class="fas fa-info-circle"></i> No withdrawal requests found for this expert.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="accordion">
                        <div class="accordion-header" data-target="fund-requests-content">
                            <h3><i class="fas fa-wallet"></i> Fund Requests</h3>
                            <i class="fas fa-chevron-down toggle-icon"></i>
                        </div>
                        <div class="accordion-content" id="fund-requests-content">
                            <?php if (!empty($fund_requests)): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Date</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($fund_requests as $fund_request): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($fund_request['id']); ?></td>
                                                    <td><?php echo formatDate(htmlspecialchars($fund_request['created_at'])); ?></td>
                                                    <td><?php echo htmlspecialchars(number_format($fund_request['amount'], 2) . ' ' . $currency); ?></td>
                                                    <td><?php echo getPaymentStatusBadge(htmlspecialchars($fund_request['status'])); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-center"><i class="fas fa-info-circle"></i> No fund requests found for this client.</p>
                        <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Reports Accordion -->
                <div class="accordion">
                    <div class="accordion-header" data-target="reports-content">
                        <h3><i class="fas fa-flag"></i> Reports</h3>
                        <i class="fas fa-chevron-down toggle-icon"></i>
                    </div>
                    <div class="accordion-content" id="reports-content">
                        <?php if (!empty($reports_as_reported) || !empty($reports_as_reporter)): ?>
                            <!-- Tabs for Reports -->
                            <div class="tabs">
                                <div class="tab active" data-tab="reported">Reports Against User</div>
                                <div class="tab" data-tab="reporter">Reports By User</div>
                            </div>
                            
                            <!-- Reports Against User -->
                            <div class="tab-content active" id="reported-tab">
                                <?php if (!empty($reports_as_reported)): ?>
                                    <div class="report-section">
                                        <?php foreach ($reports_as_reported as $report): ?>
                                            <div class="report-card">
                                                <div class="report-header">
                                                    <div class="report-id">Report #<?php echo htmlspecialchars($report['id']); ?></div>
                                                    <div class="report-date"><?php echo formatDate($report['created_at'], 'd/m/Y H:i'); ?></div>
                                                </div>
                                                <div class="report-users">
                                                    <div class="report-user">
                                                        <div class="report-user-role">Reporter</div>
                                                        <div class="report-user-name"><?php echo htmlspecialchars($report['reporter_name']); ?></div>
                                                        <div class="report-user-email"><?php echo htmlspecialchars($report['reporter_email']); ?></div>
                                                    </div>
                                                    <div class="report-user">
                                                        <div class="report-user-role">Reported</div>
                                                        <div class="report-user-name"><?php echo htmlspecialchars($report['reported_name']); ?></div>
                                                        <div class="report-user-email"><?php echo htmlspecialchars($report['reported_email']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="report-details">
                                                    <div class="report-type"><?php echo htmlspecialchars($report['report_type']); ?></div>
                                                    <div class="report-message"><?php echo htmlspecialchars($report['message']); ?></div>
                                                </div>
                                                <div class="report-footer">
                                                    <div class="report-consultation">Consultation: <?php echo formatDate($report['consultation_date']) . ' ' . $report['consultation_time']; ?></div>
                                                    <div><?php echo getReportStatusBadge($report['status']); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-items-message">
                                        <i class="fas fa-info-circle"></i>
                                        <p>No reports against this user.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Reports By User -->
                            <div class="tab-content" id="reporter-tab">
                                <?php if (!empty($reports_as_reporter)): ?>
                                    <div class="report-section">
                                        <?php foreach ($reports_as_reporter as $report): ?>
                                            <div class="report-card">
                                                <div class="report-header">
                                                    <div class="report-id">Report #<?php echo htmlspecialchars($report['id']); ?></div>
                                                    <div class="report-date"><?php echo formatDate($report['created_at'], 'd/m/Y H:i'); ?></div>
                                                </div>
                                                <div class="report-users">
                                                    <div class="report-user">
                                                        <div class="report-user-role">Reporter</div>
                                                        <div class="report-user-name"><?php echo htmlspecialchars($report['reporter_name']); ?></div>
                                                        <div class="report-user-email"><?php echo htmlspecialchars($report['reporter_email']); ?></div>
                                                    </div>
                                                    <div class="report-user">
                                                        <div class="report-user-role">Reported</div>
                                                        <div class="report-user-name"><?php echo htmlspecialchars($report['reported_name']); ?></div>
                                                        <div class="report-user-email"><?php echo htmlspecialchars($report['reported_email']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="report-details">
                                                    <div class="report-type"><?php echo htmlspecialchars($report['report_type']); ?></div>
                                                    <div class="report-message"><?php echo htmlspecialchars($report['message']); ?></div>
                                                </div>
                                                <div class="report-footer">
                                                    <div class="report-consultation">Consultation: <?php echo formatDate($report['consultation_date']) . ' ' . $report['consultation_time']; ?></div>
                                                    <div><?php echo getReportStatusBadge($report['status']); ?></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="no-items-message">
                                        <i class="fas fa-info-circle"></i>
                                        <p>No reports made by this user.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-items-message">
                                <i class="fas fa-info-circle"></i>
                                <p>No reports associated with this user.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php if (!$is_expert): ?>
    <!-- Client Ratings Accordion -->
    <div class="accordion">
        <div class="accordion-header" data-target="client-ratings-content">
            <h3><i class="fas fa-star"></i> Ratings Given</h3>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </div>
        <div class="accordion-content" id="client-ratings-content">
            <?php if (!empty($client_ratings)): ?>
                <?php foreach ($client_ratings as $rating): ?>
                    <div class="rating-item">
                        <div class="rating-header">
                            <div class="rating-expert">
                                <div class="rating-expert-info">
                                    <div class="rating-expert-name">Expert: <?php echo htmlspecialchars($rating['expert_name']); ?></div>
                                </div>
                            </div>
                            <div class="rating-stars">
                                <?php echo renderStarRating($rating['rating']); ?>
                            </div>
                        </div>
                        <div class="rating-date"><?php echo formatDate($rating['created_at'], 'd/m/Y H:i'); ?></div>
                        <div class="rating-comment"><?php echo htmlspecialchars($rating['comment']); ?></div>
                        <?php if ($rating['response_text']): ?>
                            <div class="rating-response">
                                <div class="rating-response-header">
                                    <span>Expert Response</span>
                                    <span><?php echo formatDate($rating['response_date'], 'd/m/Y H:i'); ?></span>
                                </div>
                                <?php echo htmlspecialchars($rating['response_text']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-items-message">
                    <i class="fas fa-info-circle"></i>
                    <p>No ratings given by this client.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Accordion functionality
            const accordionHeaders = document.querySelectorAll('.accordion-header');
            
            accordionHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const content = document.getElementById(targetId);
                    
                    // Close all accordion contents
                    document.querySelectorAll('.accordion-content').forEach(item => {
                        if (item.id !== targetId) {
                            item.classList.remove('active');
                        }
                    });
                    
                    // Close all accordion headers
                    document.querySelectorAll('.accordion-header').forEach(item => {
                        if (item !== this) {
                            item.classList.remove('active');
                        }
                    });
                    
                    // Toggle current accordion
                    this.classList.toggle('active');
                    content.classList.toggle('active');
                });
            });
            
            // Tab functionality
            const tabs = document.querySelectorAll('.tab');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabId = this.getAttribute('data-tab');
                    
                    // Remove active class from all tabs and contents
                    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                    
                    // Add active class to clicked tab and corresponding content
                    this.classList.add('active');
                    document.getElementById(tabId + '-tab').classList.add('active');
                });
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
            
            // Mobile sidebar toggle
            if (window.innerWidth <= 768) {
                const sidebar = document.querySelector('.sidebar');
                const toggleBtn = document.createElement('button');
                toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
                toggleBtn.classList.add('sidebar-toggle');
                toggleBtn.style.position = 'absolute';
                toggleBtn.style.top = '1rem';
                toggleBtn.style.right = '1rem';
                toggleBtn.style.background = 'var(--primary-gradient)';
                toggleBtn.style.color = 'white';
                toggleBtn.style.border = 'none';
                toggleBtn.style.borderRadius = 'var(--radius)';
                toggleBtn.style.padding = '0.5rem';
                toggleBtn.style.cursor = 'pointer';
                toggleBtn.style.zIndex = '1000';
                toggleBtn.style.boxShadow = 'var(--shadow)';
                
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('expanded');
                });
                
                sidebar.appendChild(toggleBtn);
            }
        });
    </script>
</body>
</html>
