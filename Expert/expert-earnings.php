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

// Check for success message in URL
if (isset($_GET['success']) && $_GET['success'] == 'withdrawal_submitted') {
    $next_date = isset($_GET['next_date']) ? $_GET['next_date'] : '';
    if (!empty($next_date)) {
        $success_message = "Your withdrawal request has been submitted successfully. Your payment will be processed on " . $next_date . ".";
    } else {
        $success_message = "Your withdrawal request has been submitted successfully. Your payment will be processed soon.";
    }
}

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

// Calculate commission rate
$commission_rate = isset($settings['commission_rate']) ? (float)$settings['commission_rate'] : 10;

// Get expert profile data
$profile_sql = "SELECT * FROM expert_profiledetails WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$profile_id = $profile ? $profile['id'] : 0;

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

// Get earnings data
$earnings = [
    'total' => 0,
    'pending' => 0,
    'available' => 0,
    'monthly' => [],
    'recent' => [],
    'yearly' => 0,
    'weekly' => 0,
    'daily' => 0,
    'avg_per_consultation' => 0,
    'highest_earning' => 0,
    'total_consultations' => 0,
    'completed_consultations' => 0
];

// Get total successful withdrawals
$successful_withdrawals = 0;
$successful_withdrawals_commission = 0;
$successful_withdrawals_sql = "SELECT SUM(amount) as total, SUM(amount_avec_commission) as total_avec_commission FROM withdrawal_requests WHERE user_id = ? AND status = 'completed'";
$successful_withdrawals_stmt = $conn->prepare($successful_withdrawals_sql);
$successful_withdrawals_stmt->bind_param("i", $user_id);
$successful_withdrawals_stmt->execute();
$successful_withdrawals_result = $successful_withdrawals_stmt->get_result();
$successful_withdrawals_data = $successful_withdrawals_result->fetch_assoc();
if ($successful_withdrawals_data && $successful_withdrawals_data['total']) {
    $successful_withdrawals = $successful_withdrawals_data['total'];
    $successful_withdrawals_net = $successful_withdrawals_data['total_avec_commission'];
} else {
    $successful_withdrawals_net = 0;
}

// Remove the commission calculation since we're now getting it directly from the database
// Delete or comment out these lines:
// $successful_withdrawals_commission = ($successful_withdrawals * $commission_rate) / 100;
// $successful_withdrawals_net = $successful_withdrawals - $successful_withdrawals_commission;

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
$pending_sql = "SELECT SUM(amount) as pending FROM payments WHERE expert_id = ? AND status = 'processing'";
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

// Ajouter cette requête pour obtenir le montant total déposé sur le compte
$total_deposits_sql = "SELECT SUM(amount) as total FROM payments WHERE expert_id = ? AND status = 'completed'";
$total_deposits_stmt = $conn->prepare($total_deposits_sql);
$total_deposits_stmt->bind_param("i", $user_id);
$total_deposits_stmt->execute();
$total_deposits_result = $total_deposits_stmt->get_result();
$total_deposits_data = $total_deposits_result->fetch_assoc();
$total_deposits = $total_deposits_data && $total_deposits_data['total'] ? $total_deposits_data['total'] : 0;

// Get yearly earnings (current year)
$yearly_sql = "SELECT SUM(amount) as yearly FROM payments 
               WHERE expert_id = ? AND status = 'completed' 
               AND YEAR(created_at) = YEAR(CURRENT_DATE())";
$yearly_stmt = $conn->prepare($yearly_sql);
$yearly_stmt->bind_param("i", $user_id);
$yearly_stmt->execute();
$yearly_result = $yearly_stmt->get_result();
$yearly_data = $yearly_result->fetch_assoc();
if ($yearly_data && $yearly_data['yearly']) {
    $earnings['yearly'] = $yearly_data['yearly'];
}

// Get weekly earnings (current week)
$weekly_sql = "SELECT SUM(amount) as weekly FROM payments 
               WHERE expert_id = ? AND status = 'completed' 
               AND YEARWEEK(created_at) = YEARWEEK(CURRENT_DATE())";
$weekly_stmt = $conn->prepare($weekly_sql);
$weekly_stmt->bind_param("i", $user_id);
$weekly_stmt->execute();
$weekly_result = $weekly_stmt->get_result();
$weekly_data = $weekly_result->fetch_assoc();
if ($weekly_data && $weekly_data['weekly']) {
    $earnings['weekly'] = $weekly_data['weekly'];
}

// Get daily earnings (today)
$daily_sql = "SELECT SUM(amount) as daily FROM payments 
              WHERE expert_id = ? AND status = 'completed' 
              AND DATE(created_at) = CURRENT_DATE()";
$daily_stmt = $conn->prepare($daily_sql);
$daily_stmt->bind_param("i", $user_id);
$daily_stmt->execute();
$daily_result = $daily_stmt->get_result();
$daily_data = $daily_result->fetch_assoc();
if ($daily_data && $daily_data['daily']) {
    $earnings['daily'] = $daily_data['daily'];
}

// Get highest earning
$highest_sql = "SELECT MAX(amount) as highest FROM payments 
                WHERE expert_id = ? AND status = 'completed'";
$highest_stmt = $conn->prepare($highest_sql);
$highest_stmt->bind_param("i", $user_id);
$highest_stmt->execute();
$highest_result = $highest_stmt->get_result();
$highest_data = $highest_result->fetch_assoc();
if ($highest_data && $highest_data['highest']) {
    $earnings['highest_earning'] = $highest_data['highest'];
}

// Get total consultations count
$total_consult_sql = "SELECT COUNT(*) as total FROM consultations WHERE expert_id = ?";
$total_consult_stmt = $conn->prepare($total_consult_sql);
$total_consult_stmt->bind_param("i", $user_id);
$total_consult_stmt->execute();
$total_consult_result = $total_consult_stmt->get_result();
$total_consult_data = $total_consult_result->fetch_assoc();
if ($total_consult_data) {
    $earnings['total_consultations'] = $total_consult_data['total'];
}

// Get completed consultations count
$completed_consult_sql = "SELECT COUNT(*) as completed FROM consultations WHERE expert_id = ? AND status = 'completed'";
$completed_consult_stmt = $conn->prepare($completed_consult_sql);
$completed_consult_stmt->bind_param("i", $user_id);
$completed_consult_stmt->execute();
$completed_consult_result = $completed_consult_stmt->get_result();
$completed_consult_data = $completed_consult_result->fetch_assoc();
if ($completed_consult_data) {
    $earnings['completed_consultations'] = $completed_consult_data['completed'];
}

// Calculate average earnings per consultation
if ($earnings['completed_consultations'] > 0) {
    $earnings['avg_per_consultation'] = $earnings['total'] / $earnings['completed_consultations'];
}

// Get monthly earnings for the last 12 months
$monthly_sql = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    SUM(amount) as total
                FROM payments 
                WHERE expert_id = ? AND status = 'completed' 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
$monthly_stmt = $conn->prepare($monthly_sql);
$monthly_stmt->bind_param("i", $user_id);
$monthly_stmt->execute();
$monthly_result = $monthly_stmt->get_result();

// Initialize all months with zero values
$current_month = new DateTime();
for ($i = 11; $i >= 0; $i--) {
    $month = clone $current_month;
    $month->modify("-$i months");
    $month_key = $month->format('Y-m');
    $month_name = $month->format('M Y');
    $earnings['monthly'][$month_key] = [
        'month' => $month_name,
        'total' => 0
    ];
}

// Fill in actual values
while ($row = $monthly_result->fetch_assoc()) {
    $month_date = new DateTime($row['month'] . '-01');
    $month_name = $month_date->format('M Y');
    $earnings['monthly'][$row['month']] = [
        'month' => $month_name,
        'total' => $row['total']
    ];
}

// Convert to indexed array for easier use in JavaScript
$earnings['monthly'] = array_values($earnings['monthly']);

// Get earnings by day of week
$day_of_week_sql = "SELECT 
                      DAYOFWEEK(created_at) as day_num,
                      SUM(amount) as total
                    FROM payments 
                    WHERE expert_id = ? AND status = 'completed' 
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH)
                    GROUP BY DAYOFWEEK(created_at)
                    ORDER BY day_num ASC";
$day_of_week_stmt = $conn->prepare($day_of_week_sql);
$day_of_week_stmt->bind_param("i", $user_id);
$day_of_week_stmt->execute();
$day_of_week_result = $day_of_week_stmt->get_result();

// Initialize days of week with zero values
$days_of_week = [
    'Sunday' => 0,
    'Monday' => 0,
    'Tuesday' => 0,
    'Wednesday' => 0,
    'Thursday' => 0,
    'Friday' => 0,
    'Saturday' => 0
];

// Map MySQL DAYOFWEEK() to actual day names (MySQL: 1=Sunday, 2=Monday, etc.)
$day_map = [
    1 => 'Sunday',
    2 => 'Monday',
    3 => 'Tuesday',
    4 => 'Wednesday',
    5 => 'Thursday',
    6 => 'Friday',
    7 => 'Saturday'
];

// Fill in actual values
while ($row = $day_of_week_result->fetch_assoc()) {
    $day_name = $day_map[$row['day_num']];
    $days_of_week[$day_name] = $row['total'];
}

// Get recent earnings
$recent_earnings_sql = "SELECT p.*, c.consultation_date, u.full_name as client_name 
                        FROM payments p 
                        JOIN consultations c ON p.consultation_id = c.id 
                        JOIN users u ON p.client_id = u.id 
                        WHERE p.expert_id = ? AND p.status = 'completed' 
                        ORDER BY p.created_at DESC LIMIT 10";
$recent_earnings_stmt = $conn->prepare($recent_earnings_sql);
$recent_earnings_stmt->bind_param("i", $user_id);
$recent_earnings_stmt->execute();
$recent_earnings_result = $recent_earnings_stmt->get_result();
while ($row = $recent_earnings_result->fetch_assoc()) {
    $earnings['recent'][] = $row;
}

// Get earnings by client
$client_earnings_sql = "SELECT 
                          u.full_name as client_name,
                          COUNT(p.id) as transaction_count,
                          SUM(p.amount) as total_amount
                        FROM payments p
                        JOIN users u ON p.client_id = u.id
                        WHERE p.expert_id = ? AND p.status = 'completed'
                        GROUP BY p.client_id
                        ORDER BY total_amount DESC
                        LIMIT 5";
$client_earnings_stmt = $conn->prepare($client_earnings_sql);
$client_earnings_stmt->bind_param("i", $user_id);
$client_earnings_stmt->execute();
$client_earnings_result = $client_earnings_stmt->get_result();
$client_earnings = [];
while ($row = $client_earnings_result->fetch_assoc()) {
    $client_earnings[] = $row;
}

// Get withdrawal requests
$withdrawal_requests = [];
$withdrawal_sql = "SELECT * FROM withdrawal_requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$withdrawal_stmt = $conn->prepare($withdrawal_sql);
$withdrawal_stmt->bind_param("i", $user_id);
$withdrawal_stmt->execute();
$withdrawal_result = $withdrawal_stmt->get_result();
while ($row = $withdrawal_result->fetch_assoc()) {
    $withdrawal_requests[] = $row;
}

// Get withdrawal dates from settings
$withdrawal_dates = [];
if (!empty($settings['withdrawal_days'])) {
    $withdrawal_dates = explode(',', $settings['withdrawal_days']);
}

// Handle withdrawal request submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_withdrawal'])) {
    $amount = sanitize_input($_POST['amount']);
    $notes = sanitize_input($_POST['notes']);
    
    // Validate amount
    if (!is_numeric($amount) || $amount <= 0) {
        $error_message = "Please enter a valid amount.";
    } elseif ($amount > $user['balance']) {
        $error_message = "Withdrawal amount cannot exceed your available balance.";
    } else {
        // Get the next withdrawal date
        $next_withdrawal_date = "";
        if (!empty($withdrawal_dates)) {
            $today_day = (int)date('j');
            $current_month = (int)date('m');
            $current_year = (int)date('Y');
            
            // Sort withdrawal dates
            sort($withdrawal_dates, SORT_NUMERIC);
            
            // Find the next withdrawal date
            $found_next_date = false;
            foreach ($withdrawal_dates as $day) {
                if ((int)$day > $today_day) {
                    $next_withdrawal_date = $day . " " . date('F Y');
                    $found_next_date = true;
                    break;
                }
            }
            
            // If no date found in current month, use the first date of next month
            if (!$found_next_date && !empty($withdrawal_dates)) {
                $next_month = $current_month == 12 ? 1 : $current_month + 1;
                $next_year = $current_month == 12 ? $current_year + 1 : $current_year;
                $next_month_name = date('F', mktime(0, 0, 0, $next_month, 1, $next_year));
                $next_withdrawal_date = $withdrawal_dates[0] . " " . $next_month_name . " " . $next_year;
            }
        }

        // Calculate amount with commission
        $amount_avec_commission = $amount - (($amount * $commission_rate) / 100);

        // Insert withdrawal request
        $insert_sql = "INSERT INTO withdrawal_requests (user_id, amount, amount_avec_commission, notes, created_at) VALUES (?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("idds", $user_id, $amount, $amount_avec_commission, $notes);

        if ($insert_stmt->execute()) {
            if (!empty($next_withdrawal_date)) {
                $success_message = "Your withdrawal request has been submitted successfully. Your payment will be processed on " . $next_withdrawal_date . ".";
            } else {
                $success_message = "Your withdrawal request has been submitted successfully. Your payment will be processed soon.";
            }

            // Notify admin
            $admin_notification_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message, created_at) 
                                      VALUES (?, ?, ?, ?, NOW())";
            $notification_message = "New withdrawal request of " . $amount . " " . ($settings['currency'] ?? 'DA') . " from expert #" . $user_id;
            $admin_notification_stmt = $conn->prepare($admin_notification_sql);
            $notification_type = "withdrawal_request";
            $admin_notification_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $notification_message);
            $admin_notification_stmt->execute();

            // Refresh page to update data
            header("Location: expert-earnings.php?success=withdrawal_submitted&next_date=" . urlencode($next_withdrawal_date));
            exit();
        } else {
            $error_message = "Error submitting withdrawal request. Please try again.";
        }
    }
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

// Calculate commission amount and net earnings
$commission_amount = ($earnings['total'] * $commission_rate) / 100;
$net_earnings = $earnings['total'] - $commission_amount;
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
    <title>Expert Earnings - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=JetBrains+Mono:wght@400;500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Stats Cards */
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            height: 100%;
            width: 240px;
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.05));
            z-index: -1;
        }
        
        .stat-card::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0));
            transform: rotate(30deg);
            z-index: -1;
            transition: all 0.5s ease;
            opacity: 0;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:hover::after {
            animation: shine 1.5s ease;
            opacity: 1;
        }
        
        @keyframes shine {
            0% {
                left: -50%;
                opacity: 0;
            }
            50% {
                opacity: 1;
            }
            100% {
                left: 150%;
                opacity: 0;
            }
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            margin-bottom: 1.25rem;
            color: white;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .stat-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0));
            z-index: 1;
        }
        
        .stat-icon.total {
            background: linear-gradient(135deg, var(--success-color), #34d399);
        }
        
        .stat-icon.pending {
            background: linear-gradient(135deg, var(--warning-color), #fbbf24);
        }
        
        .stat-icon.available {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
        }
        
        .stat-icon.weekly {
            background: linear-gradient(135deg, var(--accent-color), #c084fc);
        }
        
        .stat-icon.daily {
            background: linear-gradient(135deg, var(--secondary-color), #22d3ee);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0.5rem;
            font-family: var(--code-font);
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        
        .stat-label {
            color: var(--text-muted);
            font-size: 0.95rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .stat-change {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: auto;
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
        
        /* Earnings Chart */
        .earnings-chart-container {
            height: 350px;
            position: relative;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin-bottom: 1.5rem;
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
            position: relative;
            overflow: hidden;
        }
        
        .earnings-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
            opacity: 0;
            transition: all 0.3s ease;
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
            display: flex;
            align-items: center;
        }
        
        .earnings-date i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .earnings-amount {
            font-weight: 700;
            color: var(--success-color);
            font-family: var(--code-font);
            font-size: 1.1rem;
            background: linear-gradient(to right, var(--success-color), #34d399);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        
        /* Client Earnings */
        .client-earnings-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
        }
        
        .client-earnings-item:last-child {
            border-bottom: none;
        }
        
        .client-earnings-item:hover {
            background-color: rgba(99, 102, 241, 0.05);
            transform: translateX(5px);
        }
        
        .client-name {
            font-weight: 600;
            color: var(--dark-color);
        }
        
        .client-transactions {
            color: var(--text-muted);
            font-size: 0.9rem;
        }
        
        .client-amount {
            font-weight: 700;
            color: var(--success-color);
            font-family: var(--code-font);
        }
        
        /* Withdrawal Requests */
        .withdrawal-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .withdrawal-item {
            padding: 1.25rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            position: relative;
        }
        
        .withdrawal-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .withdrawal-item.pending::before {
            background: linear-gradient(to bottom, var(--warning-color), #fbbf24);
        }
        
        .withdrawal-item.processing::before {
            background: linear-gradient(to bottom, var(--info-color), #60a5fa);
        }
        
        .withdrawal-item.completed::before {
            background: linear-gradient(to bottom, var(--success-color), #34d399);
        }
        
        .withdrawal-item.rejected::before {
            background: linear-gradient(to bottom, var(--danger-color), #f87171);
        }
        
        .withdrawal-item:last-child {
            border-bottom: none;
        }
        
        .withdrawal-item:hover {
            background-color: rgba(99, 102, 241, 0.05);
        }
        
        .withdrawal-item:hover::before {
            opacity: 1;
        }
        
        .withdrawal-amount {
            font-weight: 700;
            font-size: 1.1rem;
            font-family: var(--code-font);
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        
        .withdrawal-date {
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
        }
        
        .withdrawal-date i {
            margin-right: 0.5rem;
            color: var(--primary-color);
        }
        
        .withdrawal-status {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .withdrawal-status.pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .withdrawal-status.processing {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }
        
        .withdrawal-status.completed {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .withdrawal-status.rejected {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        /* Withdrawal Form */
        .withdrawal-form {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .withdrawal-form::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        }
        
        .withdrawal-form:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
            background-color: white;
        }
        
        .form-text {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        /* Banking Info */
        .banking-info {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.7);
            position: relative;
            overflow: hidden;
        }
        
        .banking-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
        }
        
        .banking-info-item {
            margin-bottom: 1rem;
            padding-left: 1rem;
        }
        
        .banking-info-label {
            font-weight: 600;
            color: var(--text-muted);
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .banking-info-value {
            font-weight: 500;
            color: var(--dark-color);
            font-family: var(--code-font);
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: inline-block;
        }
        
        /* Financial Summary */
        .financial-summary {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px dashed rgba(226, 232, 240, 0.7);
        }
        
        .summary-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .summary-label {
            font-weight: 500;
            color: var(--text-muted);
        }
        
        .summary-value {
            font-weight: 700;
            font-family: var(--code-font);
        }
        
        .summary-value.total {
            color: var(--success-color);
        }
        
        .summary-value.commission {
            color: var(--warning-color);
        }
        
        .summary-value.net {
            color: var(--primary-color);
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
        
        .btn-success {
            background: linear-gradient(to right, var(--success-color), #34d399);
            border: none;
            color: white;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.2);
        }
        
        .btn-success:hover {
            background: linear-gradient(to right, #059669, var(--success-color));
            border: none;
            color: white;
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.3);
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
        
        /* Progress Bar */
        .progress {
            height: 8px;
            border-radius: 4px;
            background-color: rgba(226, 232, 240, 0.7);
            margin-bottom: 0.5rem;
            overflow: hidden;
        }
        
        .progress-bar {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border-radius: 4px;
            transition: width 1s ease;
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
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .page-header {
                padding: 1.5rem;
            }
            
            .page-title {
                font-size: 1.8rem;
            }
            
            .stat-value {
                font-size: 1.5rem;
            }
            
            .earnings-chart-container {
                height: 300px;
            }
            
            .chart-container {
                height: 250px;
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
            
            .stat-card {
                padding: 1.25rem;
            }
            
            .shape {
                opacity: 0.05;
            }
            
            .earnings-chart-container {
                height: 250px;
            }
            
            .chart-container {
                height: 200px;
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
        
        /* Tooltip Styles */
        .custom-tooltip {
            position: absolute;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 10px;
            padding: 10px 15px;
            box-shadow: 0 5px 15px rgba(0, 0,255,255,0.95);
            border-radius: 10px;
            padding: 10px 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            font-size: 0.9rem;
            color: var(--text-color);
            z-index: 1000;
            pointer-events: none;
            transition: opacity 0.3s ease, transform 0.3s ease;
            transform: translateY(10px);
            opacity: 0;
        }
        
        .custom-tooltip.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .custom-tooltip-label {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--primary-color);
        }
        
        .custom-tooltip-value {
            font-family: var(--code-font);
            font-weight: 500;
        }
        
        /* Badges */
        .badge-pill {
            padding: 0.35em 0.65em;
            border-radius: 50rem;
            font-size: 0.75em;
            font-weight: 600;
        }
        
        .badge-primary {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            color: white;
        }
        
        .badge-success {
            background: linear-gradient(to right, var(--success-color), #34d399);
            color: white;
        }
        
        .badge-warning {
            background: linear-gradient(to right, var(--warning-color), #fbbf24);
            color: white;
        }
        
        .badge-info {
            background: linear-gradient(to right, var(--info-color), #60a5fa);
            color: white;
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
                    <a class="nav-link d-flex flex-column align-items-center active" href="expert-earnings.php">
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
    
    <!-- Page Header -->
    <div class="page-header fade-in">
        <div class="page-header-content">
            <h1 class="page-title">Financial Dashboard</h1>
            <p class="page-subtitle">Track your earnings, manage withdrawals, and analyze your financial performance</p>
        </div>
    </div>
    
    <!-- Stats Row -->
    
<div class="row g-4 mt-4" style="flex-wrap: nowrap;width: 82%;">
    <div class="col-md-4 col-lg-3 fade-in delay-1">
        <div class="stat-card">
            <div class="stat-icon total">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-value"><?php echo number_format($successful_withdrawals); ?></div>
            <div class="stat-label">Successful Withdrawals <span class="badge badge-pill badge-primary"><?php echo htmlspecialchars($currency); ?></span></div>
            <div class="stat-change positive">
                <i class="fas fa-money-bill-wave"></i> <?php echo number_format($successful_withdrawals_net); ?> <?php echo htmlspecialchars($currency); ?> after commission
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-3 fade-in delay-1">
        <div class="stat-card">
            <div class="stat-icon available">
                <i class="fas fa-coins"></i>
            </div>
            <div class="stat-value"><?php echo number_format($earnings['available']); ?></div>
            <div class="stat-label">Available Balance <span class="badge badge-pill badge-primary"><?php echo htmlspecialchars($currency); ?></span></div>
            <div class="stat-change positive">
                <i class="fas fa-check-circle"></i> Withdrawable
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-3 fade-in delay-3">
        <div class="stat-card">
            <div class="stat-icon weekly">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-value"><?php echo number_format($earnings['weekly']); ?></div>
            <div class="stat-label">This Week <span class="badge badge-pill badge-primary"><?php echo htmlspecialchars($currency); ?></span></div>
            <div class="stat-change positive">
                <i class="fas fa-calendar-day"></i> Current
            </div>
        </div>
    </div>
    <div class="col-md-4 col-lg-3 fade-in delay-3">
        <div class="stat-card">
            <div class="stat-icon daily">
                <i class="fas fa-sun"></i>
            </div>
            <div class="stat-value"><?php echo number_format($earnings['daily']); ?></div>
            <div class="stat-label">Today <span class="badge badge-pill badge-primary"><?php echo htmlspecialchars($currency); ?></span></div>
            <div class="stat-change positive">
                <i class="fas fa-clock"></i> <?php echo date('d M'); ?>
            </div>
        </div>
    </div>
<div class="col-md-4 col-lg-3 fade-in delay-4">
    <div class="stat-card">
        <div class="stat-icon total">
            <i class="fas fa-money-bill-alt"></i>
        </div>
        <div class="stat-value"><?php echo number_format($total_deposits); ?></div>
        <div class="stat-label">Total Earnings <span class="badge badge-pill badge-primary"><?php echo htmlspecialchars($currency); ?></span></div>
        <div class="stat-change positive">
            <i class="fas fa-chart-line"></i> Lifetime earnings
        </div>
    </div>
</div>
</div>
    
    <!-- Main Dashboard Content -->
    <div class="row g-4 mt-4">
        <!-- Earnings Chart -->
        <div class="col-lg-8 fade-in delay-1">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Monthly Earnings Trend</h2>
                    <div>
                        <span class="badge badge-pill badge-primary">Last 12 Months</span>
                    </div>
                </div>
                <div class="dashboard-card-body">
                    <div class="earnings-chart-container">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Financial Summary -->
        <div class="col-lg-4 fade-in delay-2">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Financial Summary</h2>
                </div>
                <div class="dashboard-card-body">
                    <div class="financial-summary">
                        <div class="summary-item">
                            <div class="summary-label">Total Earnings</div>
                            <div class="summary-value total"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($earnings['total']); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Platform Commission (<?php echo $commission_rate; ?>%)</div>
                            <div class="summary-value commission"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($commission_amount); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Net Earnings</div>
                            <div class="summary-value net"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($net_earnings); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Total Consultations</div>
                            <div class="summary-value"><?php echo number_format($earnings['total_consultations']); ?></div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-label">Completed Consultations</div>
                            <div class="summary-value"><?php echo number_format($earnings['completed_consultations']); ?></div>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Second Row -->
    <div class="row g-4 mt-4">
        <!-- Top Clients -->
        <div class="col-lg-6 fade-in delay-3">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Top Clients</h2>
                </div>
                <div class="dashboard-card-body">
                    <?php if (empty($client_earnings)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-users fa-3x text-muted mb-3"></i>
                            <p class="mb-0">No client data available yet.</p>
                            <p class="text-muted">Complete more consultations to see your top clients.</p>
                        </div>
                    <?php else: ?>
                        <div class="client-earnings-list">
                            <?php foreach ($client_earnings as $client): ?>
                                <div class="client-earnings-item">
                                    <div>
                                        <div class="client-name"><?php echo htmlspecialchars($client['client_name']); ?></div>
                                        <div class="client-transactions"><?php echo htmlspecialchars($client['transaction_count']); ?> consultations</div>
                                    </div>
                                    <div class="client-amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($client['total_amount']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Banking Information -->
        <div class="col-lg-6 fade-in delay-4">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Banking Information</h2>
                </div>
                <div class="dashboard-card-body">
                    <?php if ($banking): ?>
                        <div class="banking-info">
                            <div class="banking-info-item">
                                <div class="banking-info-label">CCP Number</div>
                                <div class="banking-info-value"><?php echo htmlspecialchars($banking['ccp']); ?></div>
                            </div>
                            <div class="banking-info-item">
                                <div class="banking-info-label">CCP Key</div>
                                <div class="banking-info-value"><?php echo htmlspecialchars($banking['ccp_key']); ?></div>
                            </div>
                            <div class="banking-info-item">
                                <div class="banking-info-label">Consultation Price</div>
                                <div class="banking-info-value"><?php echo htmlspecialchars($currency); ?> <?php echo htmlspecialchars($banking['consultation_price']); ?> / <?php echo htmlspecialchars($banking['consultation_minutes']); ?> min</div>
                            </div>
                         
                        </div>
                        <div class="text-center mt-3">
                            <a href="expert-settings.php#banking" class="btn btn-outline-primary">
                                <i class="fas fa-edit me-2"></i> Update Banking Info
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-university fa-3x text-muted mb-3"></i>
                            <p class="mb-3">You haven't added your banking information yet.</p>
                            <a href="expert-settings.php#banking" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Add Banking Info
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Withdrawal Section -->
    <div class="row g-4 mt-4">
        <div class="col-lg-6 fade-in delay-3">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Request Withdrawal</h2>
                </div>
                <div class="dashboard-card-body">
                    <?php if ($banking): ?>
                        <div class="withdrawal-form">
                            <form method="post" action="">
                                <div class="mb-3">
                                    <label for="amount" class="form-label">Withdrawal Amount (<?php echo htmlspecialchars($currency); ?>)</label>
                                    <input type="number" class="form-control" id="amount" name="amount" min="1" max="<?php echo $earnings['available']; ?>" required>
                                    <div class="form-text">
                                        Available balance: <?php echo htmlspecialchars($currency); ?> <?php echo number_format($earnings['available']); ?>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes (Optional)</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="Add any additional information for the admin..."></textarea>
                                </div>
                                
                                <?php if (!empty($withdrawal_dates)): ?>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        You can request a withdrawal at any time. Payments are processed on the following days of the month: <?php echo implode(', ', $withdrawal_dates); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <button type="submit" name="submit_withdrawal" class="btn btn-primary">
                                    <i class="fas fa-money-bill-wave me-2"></i> Request Withdrawal
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i>
                            <p class="mb-3">You need to add your banking information before requesting withdrawals.</p>
                            <a href="expert-settings.php#banking" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Add Banking Info
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6 fade-in delay-4">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Withdrawal History</h2>
                </div>
                <div class="dashboard-card-body">
                    <?php if (empty($withdrawal_requests)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-history fa-3x text-muted mb-3"></i>
                            <p class="mb-0">You don't have any withdrawal requests yet.</p>
                            <p class="text-muted">Your withdrawal history will appear here.</p>
                        </div>
                    <?php else: ?>
                        <ul class="withdrawal-list">
                            <?php foreach ($withdrawal_requests as $request): ?>
                                
<li class="withdrawal-item <?php echo strtolower($request['status']); ?>">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="withdrawal-amount"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($request['amount'], 2); ?></div>
        <span class="withdrawal-status <?php echo strtolower($request['status']); ?>"><?php echo ucfirst($request['status']); ?></span>
    </div>
    <div class="withdrawal-date">
        <i class="far fa-calendar-alt me-1"></i> <?php echo formatDate($request['created_at']); ?>
    </div>
    <div class="withdrawal-commission mt-2">
        <small>
            <i class="fas fa-percentage me-1"></i> Commission (<?php echo $commission_rate; ?>%): 
            <span class="text-danger"><?php echo htmlspecialchars($currency); ?> <?php echo number_format(($request['amount'] * $commission_rate) / 100, 2); ?></span>
        </small>
    </div>
    <div class="withdrawal-net mt-1">
        <small>
            <i class="fas fa-coins me-1"></i> After commission: 
            <span class="text-success"><?php echo htmlspecialchars($currency); ?> <?php echo number_format($request['amount_avec_commission'], 2); ?></span>
        </small>
    </div>
    <?php if (!empty($request['notes'])): ?>
        <div class="withdrawal-notes mt-2">
            <small class="text-muted"><i class="fas fa-sticky-note me-1"></i> <?php echo htmlspecialchars($request['notes']); ?></small>
        </div>
    <?php endif; ?>
    <?php if (!empty($request['admin_notes']) && ($request['status'] == 'rejected' || $request['status'] == 'completed')): ?>
        <div class="withdrawal-admin-notes mt-2">
            <small class="<?php echo $request['status'] == 'rejected' ? 'text-danger' : 'text-success'; ?>">
                <i class="fas fa-info-circle me-1"></i> <?php echo htmlspecialchars($request['admin_notes']); ?>
            </small>
        </div>
    <?php endif; ?>
</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Earnings -->
    <div class="row g-4 mt-4">
        <div class="col-12 fade-in delay-5">
            <div class="dashboard-card">
                <div class="dashboard-card-header">
                    <h2 class="dashboard-card-title">Recent Transactions</h2>
                </div>
                <div class="dashboard-card-body">
                    <?php if (empty($earnings['recent'])): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-coins fa-3x text-muted mb-3"></i>
                            <p class="mb-0">You don't have any earnings yet.</p>
                            <p class="text-muted">Complete consultations to start earning.</p>
                        </div>
                    <?php else: ?>
                        <ul class="earnings-list">
                            <?php foreach ($earnings['recent'] as $earning): ?>
                                <li class="earnings-item">
                                    <div class="earnings-info">
                                        <div class="earnings-client"><?php echo htmlspecialchars($earning['client_name']); ?></div>
                                        <div class="earnings-date">
                                            <i class="far fa-calendar-alt"></i> <?php echo formatDate($earning['created_at']); ?>
                                        </div>
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
        fetch('expert-earnings.php?fetch_notifications=true')
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
    
        // Initialize earnings chart
        const ctx = document.getElementById('earningsChart').getContext('2d');
        
        // Get monthly earnings data from PHP
        const monthlyData = <?php echo json_encode($earnings['monthly']); ?>;
        
        // Extract labels and data
        const labels = monthlyData.map(item => item.month);
        const data = monthlyData.map(item => item.total);
        
        // Create gradient for chart
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(99, 102, 241, 0.8)');
        gradient.addColorStop(1, 'rgba(139, 92, 246, 0.2)');
        
        const earningsChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Monthly Earnings (<?php echo htmlspecialchars($currency); ?>)',
                    data: data,
                    backgroundColor: gradient,
                    borderColor: 'rgba(99, 102, 241, 1)',
                    borderWidth: 1,
                    borderRadius: 8,
                    hoverBackgroundColor: 'rgba(139, 92, 246, 0.8)',
                    barPercentage: 0.7,
                    categoryPercentage: 0.8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.9)',
                        titleColor: '#1e293b',
                        bodyColor: '#334155',
                        borderColor: 'rgba(226, 232, 240, 0.7)',
                        borderWidth: 1,
                        padding: 12,
                        boxPadding: 6,
                        usePointStyle: true,
                        callbacks: {
                            label: function(context) {
                                return `${context.dataset.label}: ${context.parsed.y} <?php echo htmlspecialchars($currency); ?>`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(226, 232, 240, 0.5)',
                        },
                        ticks: {
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 11
                            },
                            callback: function(value) {
                                return value + ' <?php echo htmlspecialchars($currency); ?>';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                family: "'Poppins', sans-serif",
                                size: 11
                            }
                        }
                    }
                },
                animation: {
                    duration: 2000,
                    easing: 'easeOutQuart'
                }
            }
        });
        
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
        
        // Custom tooltip for financial summary
        const summaryItems = document.querySelectorAll('.summary-item');
        summaryItems.forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(99, 102, 241, 0.05)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.backgroundColor = 'transparent';
            });
        });
    });
</script>
</body>
</html>
