<?php
// Start session
session_start();


// Include database connection
require_once '../config/config.php';
// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Redirect to login page if not logged in as admin
    header("Location: ../config/logout.php");
    exit;
}

$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Get site image
$site_image = "";
$site_image_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_image'";
$site_image_result = $conn->query($site_image_query);
if ($site_image_result && $site_image_result->num_rows > 0) {
    $site_image = $site_image_result->fetch_assoc()['setting_value'];
}

// Initialize variables
$error_message = "";
$success_message = "";

// Get current settings
$settings = [];
$settings_sql = "SELECT * FROM settings";

// Check if settings table exists
$table_exists = false;
$check_table_sql = "SHOW TABLES LIKE 'settings'";
$table_result = $conn->query($check_table_sql);
if ($table_result && $table_result->num_rows > 0) {
    $table_exists = true;
    $settings_result = $conn->query($settings_sql);
    if ($settings_result && $settings_result->num_rows > 0) {
        while ($row = $settings_result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} else {
    // Table doesn't exist, show message
    $error_message = "Settings table doesn't exist. Please run the <code>create_settings_table.sql</code> script to create it.";
}

// Get admin bank accounts
$admin_bank_accounts = [];
$bank_accounts_sql = "SELECT * FROM admin_bank_accounts ORDER BY account_type";
$bank_accounts_result = $conn->query($bank_accounts_sql);
if ($bank_accounts_result && $bank_accounts_result->num_rows > 0) {
    while ($row = $bank_accounts_result->fetch_assoc()) {
        $admin_bank_accounts[] = $row;
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // General settings
    if (isset($_POST["action"]) && $_POST["action"] == "update_general") {
        $site_name = trim($_POST["site_name"]);
        $site_email = trim($_POST["site_email"]);
        $site_description = trim($_POST["site_description"]);
        
        $phone_number1 = trim($_POST["phone_number1"]);
        $phone_number2 = trim($_POST["phone_number2"]);
        $facebook_url = trim($_POST["facebook_url"]);
        $instagram_url = trim($_POST["instagram_url"]);

        // Handle image upload
        if (isset($_FILES['site_image']) && $_FILES['site_image']['error'] == 0) {
            $allowed = array('jpg', 'jpeg', 'png', 'gif');
            $filename = $_FILES['site_image']['name'];
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            
            if (in_array(strtolower($ext), $allowed)) {
                $new_filename = 'site_logo_' . time() . '.' . $ext;
                $upload_path = '../uploads/';
                
                // Create directory if it doesn't exist
                if (!file_exists($upload_path)) {
                    mkdir($upload_path, 0777, true);
                }
                
                if (move_uploaded_file($_FILES['site_image']['tmp_name'], $upload_path . $new_filename)) {
                    // Delete old image if exists
                    if (!empty($site_image) && file_exists('../uploads/' . $site_image)) {
                        unlink('../uploads/' . $site_image);
                    }
                    
                    // Update database with new image
                    $site_image = $new_filename;
                    updateSetting($conn, "site_image", $site_image);
                }
            }
        }

        // Update settings in database
        updateSetting($conn, "site_name", $site_name);
        updateSetting($conn, "site_email", $site_email);
        updateSetting($conn, "site_description", $site_description);
        
        updateSetting($conn, "phone_number1", $phone_number1);
        updateSetting($conn, "phone_number2", $phone_number2);
        updateSetting($conn, "facebook_url", $facebook_url);
        updateSetting($conn, "instagram_url", $instagram_url);
        
        $success_message = "General settings updated successfully.";
    }
    
    // Handle image deletion
    if (isset($_POST["action"]) && $_POST["action"] == "delete_site_image") {
        if (!empty($site_image) && file_exists('../uploads/' . $site_image)) {
            unlink('../uploads/' . $site_image);
        }
        
        // Clear image from database
        updateSetting($conn, "site_image", "");
        $site_image = "";
        $success_message = "Site image deleted successfully.";
    }
    
    // Payment settings
    if (isset($_POST["action"]) && $_POST["action"] == "update_payment") {
        $commission_rate = trim($_POST["commission_rate"]);
        
        // Update settings in database
        updateSetting($conn, "commission_rate", $commission_rate);
        
        $success_message = "Payment settings updated successfully.";
    }
    
    // Bank Account settings
    if (isset($_POST["action"]) && $_POST["action"] == "add_bank_account") {
        $account_type = trim($_POST["account_type"]);
        $account_number = trim($_POST["account_number"]);
        $bank_name = trim($_POST["bank_name"]);
        $key_number = isset($_POST["key_number"]) ? trim($_POST["key_number"]) : '';
        $rip_number = isset($_POST["rip_number"]) ? trim($_POST["rip_number"]) : '';
        
        // Insert new bank account
        $insert_sql = "INSERT INTO admin_bank_accounts (account_type, account_number, bank_name, key_number, rip_number, created_at) 
                      VALUES (?, ?, ?, ?, ?, NOW())";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("sssss", $account_type, $account_number, $bank_name, $key_number, $rip_number);
        
        if ($insert_stmt->execute()) {
            $success_message = "Bank account added successfully.";
        } else {
            $error_message = "Failed to add bank account: " . $conn->error;
        }
    }
    
    // Update Bank Account settings
    if (isset($_POST["action"]) && $_POST["action"] == "update_bank_account") {
        $account_id = (int)$_POST["account_id"];
        $account_type = trim($_POST["edit_account_type"]);
        $account_number = trim($_POST["edit_account_number"]);
        $bank_name = trim($_POST["edit_bank_name"]);
        $key_number = isset($_POST["edit_key_number"]) ? trim($_POST["edit_key_number"]) : '';
        $rip_number = isset($_POST["edit_rip_number"]) ? trim($_POST["edit_rip_number"]) : '';
        
        // Update bank account
        $update_sql = "UPDATE admin_bank_accounts SET account_type = ?, account_number = ?, bank_name = ?, 
                      key_number = ?, rip_number = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("sssssi", $account_type, $account_number, $bank_name, $key_number, $rip_number, $account_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Bank account updated successfully.";
        } else {
            $error_message = "Failed to update bank account: " . $conn->error;
        }
    }
    
    // Delete Bank Account
    if (isset($_POST["action"]) && $_POST["action"] == "delete_bank_account") {
        $account_id = (int)$_POST["account_id"];
        
        // Delete bank account
        $delete_sql = "DELETE FROM admin_bank_accounts WHERE id = ?";
        $delete_stmt = $conn->prepare($delete_sql);
        $delete_stmt->bind_param("i", $account_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Bank account deleted successfully.";
        } else {
            $error_message = "Failed to delete bank account: " . $conn->error;
        }
    }
}

// Function to update setting
function updateSetting($conn, $key, $value) {
    // Check if setting exists
    $check_sql = "SELECT id FROM settings WHERE setting_key = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->bind_param("s", $key);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        // Update existing setting
        $update_sql = "UPDATE settings SET setting_value = ? WHERE setting_key = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("ss", $value, $key);
        $update_stmt->execute();
    } else {
        // Insert new setting
        $insert_sql = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("ss", $key, $value);
        $insert_stmt->execute();
    }
}

// Helper function to get setting value
function getSetting($settings, $key, $default = '') {
    return isset($settings[$key]) ? $settings[$key] : $default;
}


// Get unread notifications count
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get pending withdrawals count
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending fund requests count
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending expert profile count
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
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
            width: 100%;
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

        .container {
            display: flex;
            min-height: 100vh;
            width: 100%;
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
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
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

        .main-content {
            flex: 1;
            width: 100%;
            padding: 1rem;
            transition: var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            flex-wrap: wrap;
            gap: 1rem;
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
            font-size: 1.5rem;
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
            box-shadow: var(--shadow);
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
        
        .user-avatar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 50%);
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

        .alert-danger {
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

        .btn::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10, 10);
            opacity: 0;
            transition: transform 0.5s, opacity 1s;
        }

        .btn:active::after {
            transform: scale(0, 0);
            opacity: 0.3;
            transition: 0s;
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

        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4185,129,0.3);
        }

        .btn-success:hover {
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4);
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

        .btn-warning {
            background: var(--warning-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.4);
            transform: translateY(-2px);
        }

        .tab-navigation {
            display: flex;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .tab-navigation::-webkit-scrollbar {
            display: none;
        }

        .tab {
            padding: 0.75rem 1.25rem;
            cursor: pointer;
            border: 1px solid transparent;
            border-bottom: none;
            border-radius: var(--radius) var(--radius) 0 0;
            margin-right: 0.25rem;
            background-color: var(--light-color-2);
            color: var(--text-color);
            font-weight: 500;
            transition: var(--transition);
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab i {
            font-size: 1rem;
            color: var(--primary-color);
        }

        .tab.active {
            background-color: white;
            border-color: var(--border-color);
            color: var(--primary-color);
            position: relative;
            font-weight: 600;
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-gradient);
        }

        .tab:hover:not(.active) {
            background-color: var(--light-color-3);
            color: var(--primary-dark);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease;
        }

        .tab-content.active {
            display: block;
        }

        .settings-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid var(--border-color);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .settings-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .settings-card::before {
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

        .settings-card:hover::before {
            opacity: 1;
        }

        .settings-card h2 {
            color: var(--dark-color);
            font-size: 1.25rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .settings-card h2 i {
            color: var(--primary-color);
            background: var(--primary-bg);
            padding: 0.5rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-color-dark);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--text-color);
            background-color: white;
            transition: var(--transition-fast);
            box-shadow: var(--shadow-sm);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }

        .form-text {
            font-size: 0.8rem;
            color: var(--text-color-light);
            margin-top: 0.5rem;
        }

        .checkbox-group {
            margin-bottom: 1rem;
        }

        .checkbox-group label {
            display: inline-flex;
            align-items: center;
            margin-right: 1rem;
            cursor: pointer;
            font-weight: normal;
            margin-bottom: 0.5rem;
        }

        .checkbox-group input[type="checkbox"] {
            width: 1.125rem;
            height: 1.125rem;
            border-radius: var(--radius-sm);
            border: 2px solid var(--border-color-dark);
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
            position: relative;
            margin-right: 0.5rem;
            transition: var(--transition-fast);
        }

        .checkbox-group input[type="checkbox"]:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .checkbox-group input[type="checkbox"]:checked::after {
            content: '';
            position: absolute;
            top: 2px;
            left: 6px;
            width: 4px;
            height: 8px;
            border: solid white;
            border-width: 0 2px 2px 0;
            transform: rotate(45deg);
        }

        .form-actions {
            margin-top: 1.5rem;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .duration-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            padding: 0.75rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .duration-item:hover {
            background-color: var(--light-color-3);
            transform: translateX(5px);
        }

        .duration-item input {
            flex: 1;
            margin-right: 0.75rem;
        }

        .duration-item button {
            background: var(--danger-gradient);
            color: white;
            border: none;
            border-radius: var(--radius);
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }

        .duration-item button:hover {
            transform: rotate(90deg);
            box-shadow: var(--shadow);
        }

        .add-duration {
            display: flex;
            margin-top: 1.25rem;
            gap: 0.75rem;
        }

        .add-duration input {
            flex: 1;
        }

        .menu-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: var(--z-fixed);
            box-shadow: var(--shadow);
        }

        .bank-account-card {
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .bank-account-card:hover {
            background-color: var(--light-color-3);
            transform: translateY(-2px);
        }

        .bank-account-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border-color);
        }

        .bank-account-header h3 {
            font-size: 1rem;
            color: var(--text-color-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .bank-account-header h3 i {
            color: var(--primary-color);
        }

        .bank-account-actions {
            display: flex;
            gap: 0.5rem;
        }

        .bank-account-actions button {
            background: none;
            border: none;
            color: var(--text-color-light);
            cursor: pointer;
            font-size: 1rem;
            transition: var(--transition-fast);
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
        }

        .bank-account-actions button:hover {
            background-color: var(--light-color-3);
            color: var(--primary-color);
        }

        .bank-account-actions button.delete:hover {
            color: var(--danger-color);
        }

        .bank-account-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }

        .bank-account-info-item {
            margin-bottom: 0.5rem;
        }

        .bank-account-info-label {
            font-size: 0.75rem;
            color: var(--text-color-light);
            margin-bottom: 0.25rem;
        }

        .bank-account-info-value {
            font-weight: 500;
            color: var(--text-color-dark);
        }

        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: var(--z-modal);
            opacity: 0;
            visibility: hidden;
            transition: var(--transition);
        }

        .modal.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            transform: translateY(-20px);
            transition: var(--transition);
        }

        .modal.active .modal-content {
            transform: translateY(0);
        }

        .modal-header {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h3 {
            font-size: 1.25rem;
            color: var(--text-color-dark);
            font-weight: 600;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-color-light);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .modal-close:hover {
            color: var(--danger-color);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 1.25rem;
        }

        .modal-footer {
            padding: 1.25rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .site-image-container {
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .site-image-preview {
            width: 150px;
            height: 150px;
            border: 2px dashed var(--border-color);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
            background-color: var(--light-color-2);
        }
        
        .site-image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .site-image-preview .placeholder {
            color: var(--text-color-light);
            font-size: 0.875rem;
            text-align: center;
            padding: 1rem;
        }
        
        .site-image-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .image-upload-label {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            padding: 0.625rem 1.25rem;
            background: var(--primary-gradient);
            color: white;
            border-radius: var(--radius);
            font-weight: 500;
            font-size: 0.875rem;
            transition: var(--transition);
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.3);
        }
        
        .image-upload-label:hover {
            box-shadow: 0 6px 15px rgba(124, 58, 237, 0.4);
            transform: translateY(-2px);
        }
        
        .image-upload-input {
            display: none;
        }

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
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 2rem;
                margin-left: 280px;
            }
            
            .sidebar {
                transform: translateX(0);
            }
            
            .menu-toggle {
                display: none;
            }
            
            .tab-navigation {
                flex-wrap: nowrap;
            }
        }

        @media (max-width: 767px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar {
                width: 250px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .form-actions .btn {
                width: 100%;
            }
            
            .tab {
                padding: 0.625rem 1rem;
            }
        }

        @media (max-width: 480px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .add-duration {
                flex-direction: column;
            }
        }

        .calendar-container {
            margin-top: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .calendar-wrapper {
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            padding: 1rem;
            box-shadow: var(--shadow);
        }

        #calendar {
            width: 100%;
        }

        #calendar table {
            width: 100%;
            border-collapse: collapse;
        }

        #calendar th {
            padding: 0.75rem;
            text-align: center;
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 1px solid var(--border-color);
        }

        #calendar td {
            padding: 0.75rem;
            text-align: center;
            cursor: pointer;
            border-radius: var(--radius-sm);
            transition: var(--transition-fast);
        }

        #calendar td:hover:not(.disabled) {
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }

        #calendar td.selected {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            box-shadow: var(--shadow);
        }

        #calendar td.today {
            border: 2px solid var(--primary-color);
            font-weight: 600;
        }

        #calendar td.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .calendar-header h3 {
            font-size: 1.25rem;
            color: var(--text-color-dark);
            margin: 0;
        }

        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .calendar-nav button {
            background: var(--light-color-3);
            border: none;
            border-radius: var(--radius);
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition-fast);
            color: var(--text-color);
        }

        .calendar-nav button:hover {
            background-color: var(--primary-bg);
            color: var(--primary-color);
        }

        .selected-day-info {
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            padding: 1rem;
            box-shadow: var(--shadow);
        }

        #selected-day-message {
            padding: 1rem;
            margin-top: 1rem;
            border-radius: var(--radius);
            background-color: var(--primary-bg);
            color: var(--primary-color);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        #selected-day-message i {
            font-size: 1.25rem;
        }

        .d-none {
            display: none !important;
        }

        .alert-info {
            background-color: var(--info-bg);
            color: var(--info-color);
            border-left-color: var(--info-color);
        }

        .alert-warning {
            background-color: var(--warning-bg);
            color: var(--warning-color);
            border-left-color: var(--warning-color);
        }

        .day-selector {
            margin-top: 1.5rem;
        }

        .day-selector label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .day-selector select {
            width: 100%;
            padding: 0.75rem;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            background-color: white;
            font-size: 1rem;
            color: var(--text-color-dark);
            box-shadow: var(--shadow-sm);
            transition: var(--transition-fast);
        }

        .day-selector select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }

        .current-day-info {
            text-align: center;
            margin-top: 1rem;
            padding: 1rem;
            background-color: var(--light-color-2);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .current-day-info p {
            font-size: 1.1rem;
            color: var(--text-color-dark);
            margin-bottom: 0.5rem;
        }

        .current-day-info strong {
            color: var(--primary-color);
            font-weight: 700;
        }

        /* Additional styles for the calendar */
        .selected-days-display {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .day-badge {
            background-color: var(--primary-color);
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }

/* Add these CSS styles to the existing styles section */
.selected-days-display {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin: 1rem 0;
}

.day-badge {
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: var(--primary-bg);
    color: var(--primary-color);
    font-size: 1rem;
    font-weight: 600;
    height: 40px;
    width: 40px;
    border-radius: 50%;
    box-shadow: var(--shadow);
    border: 2px solid var(--primary-light);
}

.calendar-container {
    margin-top: 1.5rem;
    margin-bottom: 1.5rem;
}

.calendar-wrapper {
    background-color: var(--light-color-2);
    border-radius: var(--radius);
    padding: 1rem;
    box-shadow: var(--shadow);
}

#calendar table {
    width: 100%;
    border-collapse: collapse;
}

#calendar th {
    padding: 0.75rem;
    text-align: center;
    font-weight: 600;
    color: var(--primary-color);
    border-bottom: 1px solid var(--border-color);
}

#calendar td {
    padding: 0.75rem;
    text-align: center;
    cursor: pointer;
    border-radius: var(--radius-sm);
    transition: var(--transition-fast);
}

#calendar td:hover:not(.disabled) {
    background-color: var(--primary-bg);
    color: var(--primary-color);
}

#calendar td.selected {
    background-color: var(--primary-color);
    color: white;
    font-weight: 600;
    box-shadow: var(--shadow);
}

#calendar td.today {
    border: 2px solid var(--primary-color);
    font-weight: 600;
}

#calendar td.disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.calendar-header h3 {
    font-size: 1.25rem;
    color: var(--text-color-dark);
    margin: 0;
}

.calendar-nav {
    display: flex;
    gap: 0.5rem;
}

.calendar-nav button {
    background: var(--light-color-3);
    border: none;
    border-radius: var(--radius);
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition-fast);
    color: var(--text-color);
}

.calendar-nav button:hover {
    background-color: var(--primary-bg);
    color: var(--primary-color);
}

.selected-days-info {
    margin-top: 1rem;
    padding: 1rem;
    background-color: var(--light-color-2);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
}

.selected-days-info p {
    margin: 0;
    font-weight: 500;
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
    </style>
</head>
<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="container">
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo htmlspecialchars($site_name); ?></h2>
                <p>Admin Dashboard</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="menu-item">
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

                <a href="settings.php" class="menu-item active">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="../config/logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-cog"></i> Settings</h1>
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
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success_message; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error_message; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            
            <div class="tab-navigation">
                <div class="tab active" onclick="openTab('general')">
                    <i class="fas fa-globe"></i> General
                </div>
                <div class="tab" onclick="openTab('payment')">
                    <i class="fas fa-credit-card"></i> Payment
                </div>
                <div class="tab" onclick="openTab('bankAccounts')">
                    <i class="fas fa-university"></i> Bank Accounts
                </div>
            </div>
            
            <!-- General Settings -->
            <div id="general" class="tab-content active">
                <div class="settings-card">
                    <h2><i class="fas fa-cogs"></i> General Settings</h2>
                    <form method="POST" enctype="multipart/form-data">
                        <div class="form-group">
                            <label for="site_image">Site Logo</label>
                            <div class="site-image-container">
                                <div class="site-image-preview">
                                    <?php if (!empty($site_image) && file_exists('../uploads/' . $site_image)): ?>
                                        <img src="../uploads/<?php echo $site_image; ?>" alt="Site Logo">
                                    <?php else: ?>
                                        <div class="placeholder">
                                            <i class="fas fa-image fa-2x"></i>
                                            <p>No image uploaded</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="site-image-actions">
                                    <label class="image-upload-label">
                                        <i class="fas fa-upload"></i> Update Image
                                        <input type="file" name="site_image" id="site_image" class="image-upload-input" accept="image/*">
                                    </label>
                                    <?php if (!empty($site_image)): ?>
                                        <button type="submit" name="action" value="delete_site_image" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> Delete Image
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="form-text">Recommended size: 200x200 pixels. Supported formats: JPG, PNG, GIF.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_name">Site Name</label>
                            <input type="text" id="site_name" name="site_name" class="form-control" value="<?php echo getSetting($settings, 'site_name', 'Consult Pro'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="site_email">Site Email</label>
                            <input type="email" id="site_email" name="site_email" class="form-control" value="<?php echo getSetting($settings, 'site_email', 'admin@consultpro.com'); ?>">
                            <div class="form-text">This email will be used for system notifications and as the sender for automated emails.</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="site_description">Site Description</label>
                            <textarea id="site_description" name="site_description" class="form-control" rows="3"><?php echo getSetting($settings, 'site_description', 'Consult Pro - Expert Consultation Platform'); ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone_number1">Phone Number 1</label>
                            <input type="text" id="phone_number1" name="phone_number1" class="form-control" value="<?php echo getSetting($settings, 'phone_number1', ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="phone_number2">Phone Number 2</label>
                            <input type="text" id="phone_number2" name="phone_number2" class="form-control" value="<?php echo getSetting($settings, 'phone_number2', ''); ?>">
                        </div>

                        <div class="form-group">
                            <label for="facebook_url">Facebook URL</label>
                            <input type="url" id="facebook_url" name="facebook_url" class="form-control" value="<?php echo getSetting($settings, 'facebook_url', ''); ?>">
                            <div class="form-text">Enter the full URL to your Facebook page (e.g., https://facebook.com/yourpage)</div>
                        </div>

                        <div class="form-group">
                            <label for="instagram_url">Instagram URL</label>
                            <input type="url" id="instagram_url" name="instagram_url" class="form-control" value="<?php echo getSetting($settings, 'instagram_url', ''); ?>">
                            <div class="form-text">Enter the full URL to your Instagram profile (e.g., https://instagram.com/yourprofile)</div>
                        </div>
                        
                        <div class="form-actions">
                            <input type="hidden" name="action" value="update_general">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Payment Settings -->
            <div id="payment" class="tab-content">
                <div class="settings-card">
                    <h2><i class="fas fa-money-check-alt"></i> Payment Settings</h2>
                    <form method="POST">
                        
                        
                       
                        
                        <div class="form-group">
                            <label for="commission_rate">Commission Rate (%)</label>
                            <input type="number" id="commission_rate" name="commission_rate" class="form-control" min="0" max="100" step="0.1" value="<?php echo getSetting($settings, 'commission_rate', '10'); ?>">
                            <div class="form-text">Percentage of each transaction that will be charged as commission.</div>
                        </div>
                        
                        <div class="form-actions">
                            <input type="hidden" name="action" value="update_payment">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Bank Accounts -->
            <div id="bankAccounts" class="tab-content">
                <div class="settings-card">
                    <h2><i class="fas fa-university"></i> Bank Accounts</h2>
                    <p>Configure bank accounts that will be used for withdrawal operations.</p>
                    
                    <div class="bank-accounts-list">
                        <?php if (!empty($admin_bank_accounts)): ?>
                            <?php foreach ($admin_bank_accounts as $account): ?>
                                <div class="bank-account-card">
                                    <div class="bank-account-header">
                                        <h3>
                                            <i class="fas fa-<?php echo $account['account_type'] == 'ccp' ? 'money-check' : 'landmark'; ?>"></i>
                                            <?php echo $account['account_type'] == 'ccp' ? 'CCP Account' : 'RIP Account'; ?>
                                        </h3>
                                        <div class="bank-account-actions">
                                            <button type="button" onclick="openEditModal(<?php echo $account['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="delete" onclick="confirmDelete(<?php echo $account['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="bank-account-info">
                                        <div class="bank-account-info-item">
                                            <div class="bank-account-info-label">Bank Name</div>
                                            <div class="bank-account-info-value"><?php echo $account['bank_name']; ?></div>
                                        </div>
                                        <div class="bank-account-info-item">
                                            <div class="bank-account-info-label">Account Number</div>
                                            <div class="bank-account-info-value"><?php echo $account['account_number']; ?></div>
                                        </div>
                                        <?php if ($account['account_type'] == 'ccp' && !empty($account['key_number'])): ?>
                                            <div class="bank-account-info-item">
                                                <div class="bank-account-info-label">Key Number</div>
                                                <div class="bank-account-info-value"><?php echo $account['key_number']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($account['account_type'] == 'rip' && !empty($account['rip_number'])): ?>
                                            <div class="bank-account-info-item">
                                                <div class="bank-account-info-label">RIP Number</div>
                                                <div class="bank-account-info-value"><?php echo $account['rip_number']; ?></div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
    <p>No bank accounts configured. Add one below.</p>
<?php endif; ?>
</div>
                    
                    <div style="margin-top: 1.5rem;">
                        <button type="button" class="btn btn-primary" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Bank Account
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Bank Account Modal -->
    <div class="modal" id="addBankAccountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Bank Account</h3>
                <button class="modal-close" onclick="closeModal('addBankAccountModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="addBankAccountForm">
                    <input type="hidden" name="action" value="add_bank_account">
                    
                    <div class="form-group">
                        <label for="account_type">Account Type</label>
                        <select id="account_type" name="account_type" class="form-control" onchange="toggleFields()">
                            <option value="">-- Select Account Type --</option>
                            <option value="ccp">CCP Account</option>
                            <option value="rip">RIP Account</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="bank_name">Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" class="form-control" placeholder="Enter bank name">
                    </div>
                    
                    <div class="form-group">
                        <label for="account_number">Account Number</label>
                        <input type="text" id="account_number" name="account_number" class="form-control" placeholder="Enter account number">
                    </div>
                    
                    <div class="form-group" id="key_number_group" style="display: none;">
                        <label for="key_number">Key Number</label>
                        <input type="text" id="key_number" name="key_number" class="form-control" placeholder="Enter key number">
                    </div>
                    
                    <div class="form-group" id="rip_number_group" style="display: none;">
                        <label for="rip_number">RIP Number (رقم بريدي موب)</label>
                        <input type="text" id="rip_number" name="rip_number" class="form-control" placeholder="Enter RIP number">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addBankAccountModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitAddForm()">Add Account</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Bank Account Modal -->
    <div class="modal" id="editBankAccountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Bank Account</h3>
                <button class="modal-close" onclick="closeModal('editBankAccountModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form method="POST" id="editBankAccountForm">
                    <input type="hidden" name="action" value="update_bank_account">
                    <input type="hidden" name="account_id" id="edit_account_id">
                    
                    <div class="form-group">
                        <label for="edit_account_type">Account Type</label>
                        <select id="edit_account_type" name="edit_account_type" class="form-control" onchange="toggleEditFields()">
                            <option value="">-- Select Account Type --</option>
                            <option value="ccp">CCP Account</option>
                            <option value="rip">RIP Account</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_bank_name">Bank Name</label>
                        <input type="text" id="edit_bank_name" name="edit_bank_name" class="form-control" placeholder="Enter bank name">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_account_number">Account Number</label>
                        <input type="text" id="edit_account_number" name="edit_account_number" class="form-control" placeholder="Enter account number">
                    </div>
                    
                    <div class="form-group" id="edit_key_number_group" style="display: none;">
                        <label for="edit_key_number">Key Number</label>
                        <input type="text" id="edit_key_number" name="edit_key_number" class="form-control" placeholder="Enter key number">
                    </div>
                    
                    <div class="form-group" id="edit_rip_number_group" style="display: none;">
                        <label for="edit_rip_number">RIP Number (رقم بريدي موب)</label>
                        <input type="text" id="edit_rip_number" name="edit_rip_number" class="form-control" placeholder="Enter RIP number">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('editBankAccountModal')">Cancel</button>
                <button class="btn btn-primary" onclick="submitEditForm()">Update Account</button>
            </div>
        </div>
    </div>
    
    <!-- Delete Bank Account Modal -->
    <div class="modal" id="deleteBankAccountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Bank Account</h3>
                <button class="modal-close" onclick="closeModal('deleteBankAccountModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this bank account? This action cannot be undone.</p>
                <form method="POST" id="deleteBankAccountForm">
                    <input type="hidden" name="action" value="delete_bank_account">
                    <input type="hidden" name="account_id" id="delete_account_id">
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('deleteBankAccountModal')">Cancel</button>
                <button class="btn btn-danger" onclick="submitDeleteForm()">Delete Account</button>
            </div>
        </div>
    </div>
    
    <script>
        function openTab(tabName) {
            // Hide all tab contents
            const tabContents = document.getElementsByClassName('tab-content');
            for (let i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all tabs
            const tabs = document.getElementsByClassName('tab');
            for (let i = 0; i < tabs.length; i++) {
                tabs[i].classList.remove('active');
            }
            
            // Show the selected tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to the clicked tab
            event.currentTarget.classList.add('active');
        }
        
        function addDuration() {
            const newDuration = document.getElementById('new_duration').value;
            if (newDuration && parseInt(newDuration) > 0) {
                const durationList = document.querySelector('.duration-list');
                
                const durationItem = document.createElement('div');
                durationItem.className = 'duration-item';
                
                const input = document.createElement('input');
                input.type = 'number';
                input.name = 'durations[]';
                input.className = 'form-control';
                input.value = newDuration;
                input.min = '1';
                input.required = true;
                
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'btn-danger';
                button.onclick = function() { removeDuration(this); };
                
                const icon = document.createElement('i');
                icon.className = 'fas fa-trash';
                
                button.appendChild(icon);
                durationItem.appendChild(input);
                durationItem.appendChild(button);
                durationList.appendChild(durationItem);
                
                document.getElementById('new_duration').value = '';
            }
        }
        
        function removeDuration(button) {
            const durationItem = button.parentNode;
            durationItem.parentNode.removeChild(durationItem);
        }

        function openBankTab(tabName) {
            // Hide all bank tab contents
            const bankTabContents = document.getElementsByClassName('bank-tab-content');
            for (let i = 0; i < bankTabContents.length; i++) {
                bankTabContents[i].classList.remove('active');
            }
            
            // Remove active class from all bank tabs
            const bankTabs = document.querySelectorAll('.tab-navigation .tab');
            for (let i = 0; i < bankTabs.length; i++) {
                bankTabs[i].classList.remove('active');
            }
            
            // Show the selected bank tab content
            document.getElementById(tabName).classList.add('active');
            
            // Add active class to the clicked tab
            event.currentTarget.classList.add('active');
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
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
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.sidebar');
        
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 768 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target) && 
                    sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
        });

        // Bank Account Functions
        function openEditModal(accountId) {
            // Fetch account data via AJAX
            fetch(`get_bank_account.php?id=${accountId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const account = data.account;
                        document.getElementById('edit_account_id').value = account.id;
                        document.getElementById('edit_account_type').value = account.account_type;
                        document.getElementById('edit_bank_name').value = account.bank_name;
                        document.getElementById('edit_account_number').value = account.account_number;
                        
                        // Show/hide appropriate fields based on account type
                        if (account.account_type === 'ccp') {
                            document.getElementById('edit_key_number_group').style.display = 'block';
                            document.getElementById('edit_rip_number_group').style.display = 'none';
                            document.getElementById('edit_key_number').value = account.key_number || '';
                        } else {
                            document.getElementById('edit_key_number_group').style.display = 'none';
                            document.getElementById('edit_rip_number_group').style.display = 'block';
                            document.getElementById('edit_rip_number').value = account.rip_number || '';
                        }
                        
                        // Open the modal
                        openModal('editBankAccountModal');
                    } else {
                        alert('Error fetching account data');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error fetching account data');
                });
        }

        function confirmDelete(accountId) {
            document.getElementById('delete_account_id').value = accountId;
            openModal('deleteBankAccountModal');
        }

        function openAddModal() {
            // Reset form
            document.getElementById('addBankAccountForm').reset();
            document.getElementById('key_number_group').style.display = 'none';
            document.getElementById('rip_number_group').style.display = 'none';
            
            // Open modal
            openModal('addBankAccountModal');
        }

        function toggleFields() {
            const accountType = document.getElementById('account_type').value;
            if (accountType === 'ccp') {
                document.getElementById('key_number_group').style.display = 'block';
                document.getElementById('rip_number_group').style.display = 'none';
            } else if (accountType === 'rip') {
                document.getElementById('key_number_group').style.display = 'none';
                document.getElementById('rip_number_group').style.display = 'block';
            } else {
                document.getElementById('key_number_group').style.display = 'none';
                document.getElementById('rip_number_group').style.display = 'none';
            }
        }

        function toggleEditFields() {
            const accountType = document.getElementById('edit_account_type').value;
            if (accountType === 'ccp') {
                document.getElementById('edit_key_number_group').style.display = 'block';
                document.getElementById('edit_rip_number_group').style.display = 'none';
            } else if (accountType === 'rip') {
                document.getElementById('edit_key_number_group').style.display = 'none';
                document.getElementById('edit_rip_number_group').style.display = 'block';
            } else {
                document.getElementById('edit_key_number_group').style.display = 'none';
                document.getElementById('edit_rip_number_group').style.display = 'none';
            }
        }

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function submitAddForm() {
            document.getElementById('addBankAccountForm').submit();
        }

        function submitEditForm() {
            document.getElementById('editBankAccountForm').submit();
        }

        function submitDeleteForm() {
            document.getElementById('deleteBankAccountForm').submit();
        }

        // Image preview functionality
        const imageInput = document.getElementById('site_image');
        if (imageInput) {
            imageInput.addEventListener('change', function() {
                const preview = document.querySelector('.site-image-preview');
                
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        // Clear preview
                        preview.innerHTML = '';
                        
                        // Create image element
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Site Logo Preview';
                        
                        // Add to preview
                        preview.appendChild(img);
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }
    // Function to refresh notification badges
    function refreshNotificationBadges() {
                fetch('get-notification-counts.php')
                    .then(response => response.json())
                    .then(data => {
                        // Update notification badges
                        updateBadge('unread_notifications', data.unread_notifications);
                        updateBadge('pending_withdrawals', data.pending_withdrawals);
                        updateBadge('pending_fund_requests', data.pending_fund_requests);
                        updateBadge('pending_review_profiles', data.pending_review_profiles);
                        updateBadge('pending_messages', data.pending_messages);
                        updateBadge('pending_reports', data.pending_reports);
                    })
                    .catch(error => console.error('Error fetching notification counts:', error));
            }

            // Function to update a specific badge
            function updateBadge(type, count) {
                const badges = document.querySelectorAll(`.menu-item:has(i.fas.fa-${getBadgeIcon(type)}) .notification-badge`);
                
                badges.forEach(badge => {
                    if (count > 0) {
                        badge.textContent = count;
                        badge.style.display = 'block';
                    } else {
                        badge.style.display = 'none';
                    }
                });
            }

            // Helper function to get icon name based on notification type
            function getBadgeIcon(type) {
                switch(type) {
                    case 'unread_notifications': return 'bell';
                    case 'pending_withdrawals': return 'money-bill-wave';
                    case 'pending_fund_requests': return 'wallet';
                    case 'pending_review_profiles': return 'clock';
                    case 'pending_messages': return 'comments';
                    case 'pending_reports': return 'flag';
                    default: return '';
                }
            }

            // Refresh notification badges every 1 second
            setInterval(refreshNotificationBadges, 1000);
        
       
    </script>
</body>
</html>