<?php
// Initialize the session
session_start();

// Include config file
require_once "../config/config.php";

// Define variables and initialize with empty values
$email = $password = "";
$email_err = $password_err = $login_err = "";

// Processing form data when form is submitted
if($_SERVER["REQUEST_METHOD"] == "POST"){

// Check if email is empty
if(empty(trim($_POST["email"]))){
    $email_err = "Please enter email.";
} else{
    $email = trim($_POST["email"]);
}

// Check if password is empty
if(empty(trim($_POST["password"]))){
    $password_err = "Please enter your password.";
} else{
    $password = trim($_POST["password"]);
}

// Validate credentials
if(empty($email_err) && empty($password_err)){
    // Prepare a select statement
    $sql = "SELECT id, email, password, role, status,email_verified, full_name, suspension_end_date FROM users WHERE email = ?";
    
    if($stmt = $conn->prepare($sql)){
        // Bind variables to the prepared statement as parameters
        $stmt->bind_param("s", $param_email);
        
        // Set parameters
        $param_email = $email;
        
        // Attempt to execute the prepared statement
        if($stmt->execute()){
            // Store result
            $stmt->store_result();
            
            // Check if email exists, if yes then verify password
            if($stmt->num_rows == 1){                    
                // Bind result variables
                $stmt->bind_result($id, $email, $hashed_password, $role, $status,$email_verified,$full_name, $suspension_end_date);
                if($stmt->fetch()){
                    if(password_verify($password, $hashed_password)){
                        // Check account status
                        if($status == "suspended"){
                            // Calculate days remaining in suspension
                            $today = new DateTime();
                            $end_date = new DateTime($suspension_end_date);
                            $interval = $today->diff($end_date);
                            $days_remaining = $interval->days;
                            
                            if($days_remaining <= 0){
                                // Update user status to active
                                $update_sql = "UPDATE users SET status = 'Offline' WHERE id = ?";
                                if($update_stmt = $conn->prepare($update_sql)){
                                    $update_stmt->bind_param("i", $id);
                                    $update_stmt->execute();
                                    $update_stmt->close();
                                    
                                    // Continue with login process
                                    $_SESSION["loggedin"] = true;
                                    $_SESSION["user_id"] = $id;
                                    $_SESSION["email"] = $email;
                                    $_SESSION["full_name"] = $full_name;
                                    $_SESSION["user_role"] = $role;
                                    
                                    // Redirect based on role
                                    handleRoleBasedRedirect($conn, $id, $role);
                                }
                            } else {
                                if($days_remaining == 1){
                                    $login_err = "Your account has been suspended. You have 1 day remaining.";
                                } else {
                                    $login_err = "Your account has been suspended. You have " . $days_remaining;
                                }
                            }
                        } else if(strtolower($status) == "deleted") {
                            $login_err = "This account has been deleted. You cannot register with this email again.";
                        } else if($email_verified != 1) {
                            // Email is not verified, generate a verification code
                            $verification_code = sprintf("%06d", mt_rand(100000, 999999));
                            
                            // Set code expiration time (5 minutes from now)
                            $expiry_time = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                            
                            // Update user record with verification code and expiry time
                            $update_sql = "UPDATE users SET verification_code = ?, code_expires_at = ? WHERE id = ?";
                            if($update_stmt = $conn->prepare($update_sql)) {
                                $update_stmt->bind_param("ssi", $verification_code, $expiry_time, $id);
                                
                                if($update_stmt->execute()) {
                                    // Store data in session variables
                                    $_SESSION["user_id"] = $id;
                                    $_SESSION["email"] = $email;
                                    $_SESSION["full_name"] = $full_name;
                                    $_SESSION["user_role"] = $role;
                                    $_SESSION["verification_code"] = $verification_code;
                                    $_SESSION["code_expires_at"] = $expiry_time;
                                    
                                    // Include mailer
                                    require_once "utils/mailer.php";
                                    
                                    // Send verification email
                                    $email_sent = sendVerificationEmail($email, $full_name, $verification_code);
                                    
                                    if($email_sent) {
                                        // Redirect to verify email page
                                        header("location: verify-email.php");
                                        exit;
                                    } else {
                                        $login_err = "Failed to send verification email. Please try again.";
                                    }
                                } else {
                                    $login_err = "Oops! Something went wrong. Please try again later.";
                                }
                                
                                $update_stmt->close();
                            }
                        } else {
                            // Password is correct, store data in session variables
                            // No need to call session_start() again as it's already started at the top
                            $_SESSION["loggedin"] = true;
                            $_SESSION["user_id"] = $id;
                            $_SESSION["email"] = $email;
                            $_SESSION["full_name"] = $full_name;
                            $_SESSION["user_role"] = $role;
                            
                            // Redirect based on role
                            handleRoleBasedRedirect($conn, $id, $role);
                        }
                    } else{
                        // Password is not valid
                        $login_err = "Invalid email or password.";
                    }
                }
            } else{
                // Email doesn't exist
                $login_err = "Invalid email or password.";
            }
        } else{
            $login_err = "Oops! Something went wrong. Please try again later.";
        }

        // Close statement
        $stmt->close();
    }
}
}

// Function to handle role-based redirects
function handleRoleBasedRedirect($conn, $user_id, $role) {
if($role == "expert") {

  
    // Check if profile is complete
    $profile_sql = "SELECT phone, address, dob, gender FROM user_profiles WHERE user_id = ?";
    $profile_complete = checkProfileCompleteness($conn, $profile_sql, $user_id);
    
    if(!$profile_complete) {
        // Redirect to complete profile
        header("location: profile.php");
        exit;
    }
    
    // Get the profile_id for this expert
    $profile_id_sql = "SELECT id FROM expert_profiledetails WHERE user_id = ?";
    $profile_id = 0;
    if($profile_id_stmt = $conn->prepare($profile_id_sql)) {
        $profile_id_stmt->bind_param("i", $user_id);
        if($profile_id_stmt->execute()) {
            $profile_id_stmt->store_result();
            if($profile_id_stmt->num_rows > 0) {
                $profile_id_stmt->bind_result($profile_id);
                $profile_id_stmt->fetch();
            }
        }
        $profile_id_stmt->close();
    }
    
    // Check expert profile details
    $expert_details_sql = "SELECT category, subcategory, city FROM expert_profiledetails WHERE user_id = ?";
    $expert_details_complete = checkProfileCompleteness($conn, $expert_details_sql, $user_id);
    
    $certificates_sql = "SELECT start_date, end_date, institution, file_path, description FROM certificates WHERE profile_id = ?";
    $certificates_complete = checkProfileCompleteness($conn, $certificates_sql, $profile_id);
    
    if(!$expert_details_complete || !$certificates_complete ) {
        // Redirect to complete expert profile details
        header("location: ../expert/profiledetails.php");
        exit;
    }
    
    // Check banking information
    $banking_sql = "SELECT ccp, ccp_key, check_file_path FROM banking_information WHERE user_id = ?";
    $banking_complete = checkProfileCompleteness($conn, $banking_sql, $user_id);
    
    if(!$banking_complete) {
        // Redirect to complete banking information
        header("location: ../expert/bankinformation.php");
        exit;
    }
    
    // Check expert profile status
    $status_sql = "SELECT status, rejection_reason FROM expert_profiledetails WHERE user_id = ?";
    if($status_stmt = $conn->prepare($status_sql)) {
        $status_stmt->bind_param("i", $user_id);
        if($status_stmt->execute()) {
            $status_stmt->store_result();
            if($status_stmt->num_rows > 0) {
                // Initialize variables with default values
                $expert_status = "";
                $rejection_reason = "";
                
                // Now bind the results
                $status_stmt->bind_result($expert_status, $rejection_reason);
                $status_stmt->fetch();
                
                if($expert_status == "pending_review") {
                    $_SESSION["status_message"] = "This account is pending and is being reviewed by support.";
                    $_SESSION["status_color"] = "orange";
                    header("location: login.php");
                    exit;
                } else if($expert_status == "approved") {
                    header("location: ../expert/home-profile.php");
                    exit;
                } else if($expert_status == "rejected") {
                    $_SESSION["status_message"] = "This account has been rejected: " . $rejection_reason;
                    $_SESSION["status_color"] = "red";
                    header("location: ../admin/reject-pending-profile.php");
                    exit;
                }
            }
        }
        $status_stmt->close();
    }
    
} else if($role == "client") {
    // Check if profile is complete
    $profile_sql = "SELECT phone, address, dob, gender FROM user_profiles WHERE user_id = ?";
    $profile_complete = checkProfileCompleteness($conn, $profile_sql, $user_id);
    
    if(!$profile_complete) {
        // Redirect to complete profile
        header("location: profile.php");
        exit;
    }
    
    // Redirect to client home
    header("location: ../index.php");
    exit;
    
} else if($role == "admin") {
    // Redirect to admin dashboard
    header("location: ../admin/dashboard.php");
    exit;
}
}

// Function to check if profile fields are complete
function checkProfileCompleteness($conn, $sql, $id) {
if($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id);
    if($stmt->execute()) {
        $result = $stmt->get_result();
        if($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            foreach($row as $field => $value) {
                if(empty($value)) {
                    $stmt->close();
                    return false;
                }
            }
            $stmt->close();
            return true;
        }
    }
    $stmt->close();
}
return false;
}

// Get site settings
$settings = [];
$settings_sql = "SELECT * FROM settings LIMIT 1";
$settings_result = $conn->query($settings_sql);
if($settings_result && $settings_result->num_rows > 0) {
$settings = $settings_result->fetch_assoc();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        
        /* Glow Colors (derived from primary colors) */
        --primary-glow: rgba(37, 99, 235, 0.5);
        --secondary-glow: rgba(124, 58, 237, 0.5);
        --accent-glow: rgba(234, 88, 12, 0.5);
        --success-glow: rgba(5, 150, 105, 0.5);
        --warning-glow: rgba(217, 119, 6, 0.5);
        --danger-glow: rgba(220, 38, 38, 0.5);
        --info-glow: rgba(6, 182, 212, 0.5);
        
        /* Shadow Effects */
        --shadow-neon: 0 0 10px var(--primary-glow), 0 0 20px var(--primary-glow), 0 0 30px var(--primary-glow);
        --shadow-neon-accent: 0 0 10px var(--accent-glow), 0 0 20px var(--accent-glow), 0 0 30px var(--accent-glow);
        --shadow-neon-secondary: 0 0 10px var(--secondary-glow), 0 0 20px var(--secondary-glow), 0 0 30px var(--secondary-glow);
        
        /* Transitions */
        --transition-bounce: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        --transition-spring: all 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        --transition-elastic: all 1s cubic-bezier(0.68, -0.6, 0.32, 1.6);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, var(--primary-50) 0%, var(--primary-100) 50%, var(--secondary-100) 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        color: var(--gray-700);
        line-height: 1.6;
        position: relative;
        overflow-x: hidden;
        perspective: 1000px;
    }

    /* Advanced Animated Background */
    .background {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
        overflow: hidden;
    }

    .bg-gradient {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, var(--primary-50) 0%, var(--primary-100) 50%, var(--secondary-100) 100%);
        opacity: 0.8;
    }

   

    .bg-pattern {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: 
            radial-gradient(circle at 25px 25px, var(--primary-200) 2px, transparent 0),
            radial-gradient(circle at 75px 75px, var(--secondary-200) 2px, transparent 0);
        background-size: 100px 100px;
        opacity: 0.6;
    }

   

    .bg-grid {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: linear-gradient(var(--primary-200) 1px, transparent 1px),
                        linear-gradient(90deg, var(--primary-200) 1px, transparent 1px);
        background-size: 50px 50px;
    }


    /* Advanced Animated Shapes */
    .bg-shapes {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }

    .shape {
        position: absolute;
        filter: blur(70px);
        opacity: 0.2;
        animation: float 20s infinite ease-in-out;
    }

    .shape-1 {
        width: 500px;
        height: 500px;
        background: var(--primary-300);
        top: -200px;
        right: -100px;
        animation-delay: 0s;
        border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
    }

    .shape-2 {
        width: 400px;
        height: 400px;
        background: var(--secondary-300);
        bottom: -150px;
        left: -150px;
        animation-delay: -5s;
        border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
    }

    .shape-3 {
        width: 300px;
        height: 300px;
        background: var(--accent-300);
        top: 40%;
        left: 30%;
        animation-delay: -10s;
        border-radius: 50% 50% 20% 80% / 25% 80% 20% 75%;
    }

    .shape-4 {
        width: 250px;
        height: 250px;
        background: var(--success-300);
        top: 60%;
        right: 20%;
        animation-delay: -15s;
        border-radius: 80% 20% 40% 60% / 50% 60% 40% 50%;
    }

    .shape-5 {
        width: 200px;
        height: 200px;
        background: var(--warning-300);
        top: 20%;
        right: 30%;
        animation-delay: -7s;
        border-radius: 40% 60% 60% 40% / 70% 30% 70% 30%;
    }

    .shape-6 {
        width: 180px;
        height: 180px;
        background: var(--danger-300);
        bottom: 30%;
        left: 20%;
        animation-delay: -12s;
        border-radius: 70% 30% 50% 50% / 30% 70% 30% 70%;
    }

    .shape-7 {
        width: 220px;
        height: 220px;
        background: var(--primary-400);
        top: 10%;
        left: 10%;
        animation-delay: -9s;
        border-radius: 20% 80% 40% 60% / 60% 40% 60% 40%;
    }

    

    /* Advanced Animated Particles */
    .particles {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        z-index: 0;
    }

    .particle {
        position: absolute;
        display: block;
        pointer-events: none;
        border-radius: 50%;
        animation: particles 15s linear infinite;
    }

    .particle:nth-child(1) {
        top: 20%;
        left: 20%;
        width: 8px;
        height: 8px;
        background-color: var(--primary-glow);
        box-shadow: 0 0 10px var(--primary-glow);
        animation-delay: 0s;
    }

    .particle:nth-child(2) {
        top: 80%;
        left: 80%;
        width: 10px;
        height: 10px;
        background-color: var(--secondary-glow);
        box-shadow: 0 0 10px var(--secondary-glow);
        animation-delay: -2s;
    }

    .particle:nth-child(3) {
        top: 40%;
        left: 60%;
        width: 12px;
        height: 12px;
        background-color: var(--accent-glow);
        box-shadow: 0 0 10px var(--accent-glow);
        animation-delay: -4s;
    }

    .particle:nth-child(4) {
        top: 60%;
        left: 40%;
        width: 9px;
        height: 9px;
        background-color: var(--success-glow);
        box-shadow: 0 0 10px var(--success-glow);
        animation-delay: -6s;
    }

    .particle:nth-child(5) {
        top: 30%;
        left: 70%;
        width: 11px;
        height: 11px;
        background-color: var(--warning-glow);
        box-shadow: 0 0 10px var(--warning-glow);
        animation-delay: -8s;
    }

    .particle:nth-child(6) {
        top: 70%;
        left: 30%;
        width: 7px;
        height: 7px;
        background-color: var(--danger-glow);
        box-shadow: 0 0 10px var(--danger-glow);
        animation-delay: -10s;
    }

    .particle:nth-child(7) {
        top: 50%;
        left: 50%;
        width: 10px;
        height: 10px;
        background-color: var(--primary-glow);
        box-shadow: 0 0 10px var(--primary-glow);
        animation-delay: -12s;
    }

    .particle:nth-child(8) {
        top: 10%;
        left: 90%;
        width: 8px;
        height: 8px;
        background-color: var(--secondary-glow);
        box-shadow: 0 0 10px var(--secondary-glow);
        animation-delay: -14s;
    }

    .particle:nth-child(9) {
        top: 90%;
        left: 10%;
        width: 9px;
        height: 9px;
        background-color: var(--accent-glow);
        box-shadow: 0 0 10px var(--accent-glow);
        animation-delay: -16s;
    }

    .particle:nth-child(10) {
        top: 25%;
        left: 75%;
        width: 11px;
        height: 11px;
        background-color: var(--info-glow);
        box-shadow: 0 0 10px var(--info-glow);
        animation-delay: -18s;
    }

    .particle:nth-child(11) {
        top: 75%;
        left: 25%;
        width: 10px;
        height: 10px;
        background-color: var(--success-glow);
        box-shadow: 0 0 10px var(--success-glow);
        animation-delay: -20s;
    }

    .particle:nth-child(12) {
        top: 35%;
        left: 85%;
        width: 7px;
        height: 7px;
        background-color: var(--warning-glow);
        box-shadow: 0 0 10px var(--warning-glow);
        animation-delay: -22s;
    }

    .particle:nth-child(13) {
        top: 85%;
        left: 35%;
        width: 9px;
        height: 9px;
        background-color: var(--danger-glow);
        box-shadow: 0 0 10px var(--danger-glow);
        animation-delay: -24s;
    }

    .particle:nth-child(14) {
        top: 15%;
        left: 45%;
        width: 8px;
        height: 8px;
        background-color: var(--info-glow);
        box-shadow: 0 0 10px var(--info-glow);
        animation-delay: -26s;
    }

    .particle:nth-child(15) {
        top: 45%;
        left: 15%;
        width: 10px;
        height: 10px;
        background-color: var(--primary-glow);
        box-shadow: 0 0 10px var(--primary-glow);
        animation-delay: -28s;
    }

    @keyframes particles {
        0% {
            transform: translate(0, 0) rotate(0deg) scale(1);
            opacity: 0.2;
        }
        25% {
            transform: translate(100px, 50px) rotate(90deg) scale(1.2);
            opacity: 0.7;
        }
        50% {
            transform: translate(50px, 100px) rotate(180deg) scale(1);
            opacity: 0.2;
        }
        75% {
            transform: translate(-50px, 50px) rotate(270deg) scale(0.8);
            opacity: 0.7;
        }
        100% {
            transform: translate(0, 0) rotate(360deg) scale(1);
            opacity: 0.2;
        }
    }

    /* Binary Rain Effect */
    .binary-rain {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 0;
        overflow: hidden;
    }

    .binary {
        position: absolute;
        color: var(--primary-300);
        font-family: 'Courier New', monospace;
        font-size: 14px;
        line-height: 1;
        animation: binary-fall linear infinite;
    }

    @keyframes binary-fall {
        0% {
            transform: translateY(-100%);
            opacity: 0;
        }
        10% {
            opacity: 1;
        }
        90% {
            opacity: 1;
        }
        100% {
            transform: translateY(1000%);
            opacity: 0;
        }
    }

    .binary:nth-child(1) { left: 10%; animation-duration: 15s; animation-delay: 0s; }
    .binary:nth-child(2) { left: 20%; animation-duration: 18s; animation-delay: -5s; }
    .binary:nth-child(3) { left: 30%; animation-duration: 16s; animation-delay: -10s; }
    .binary:nth-child(4) { left: 40%; animation-duration: 14s; animation-delay: -7s; }
    .binary:nth-child(5) { left: 50%; animation-duration: 17s; animation-delay: -3s; }
    .binary:nth-child(6) { left: 60%; animation-duration: 19s; animation-delay: -8s; }
    .binary:nth-child(7) { left: 70%; animation-duration: 15s; animation-delay: -12s; }
    .binary:nth-child(8) { left: 80%; animation-duration: 16s; animation-delay: -6s; }
    .binary:nth-child(9) { left: 90%; animation-duration: 18s; animation-delay: -9s; }

    /* Main Container */
    .container {
        width: 100%;
        max-width: 450px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 30px;
        position: relative;
        z-index: 1;
    }

    /* Advanced Animated Logo */
    .logo-container {
        position: relative;
        margin-bottom: 10px;
        perspective: 1000px;
    }

    .logo {
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        position: relative;
        z-index: 2;
        transform-style: preserve-3d;
    }

    .logo:hover {
        transform: scale(1.1) rotate(5deg) translateZ(20px);
    }

    .logo img {
        max-height: 80px;
        object-fit: contain;
        filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        animation: logo-pulse 3s infinite ease-in-out;
    }

    @keyframes logo-pulse {
        0%, 100% {
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
        }
        50% {
            filter: drop-shadow(0 8px 15px var(--primary-glow));
        }
    }

    .logo-glow {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 140px;
        height: 50px;
        background: var(--primary-300);
        border-radius: 50%;
        filter: blur(20px);
        z-index: 1;
        animation: pulse 3s infinite ease-in-out;
    }

    @keyframes pulse {
        0%, 100% {
            transform: translate(-50%, -50%) scale(1);
            opacity: 0.3;
        }
        50% {
            transform: translate(-50%, -50%) scale(1.3);
            opacity: 0.5;
        }
    }

    /* Advanced Login Card with 3D and Glassmorphism Effects */
    .login-card-wrapper {
        position: relative;
        width: 100%;
        max-width: 450px;
        perspective: 1000px;
    }

    .login-card {
        background-color: rgba(255, 255, 255, 0.85);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-xl), 0 0 0 1px var(--primary-100);
        width: 100%;
        padding: 40px;
        position: relative;
        overflow: hidden;
        z-index: 2;
    }


    /* Card Glassmorphism Effect */
    .card-glass {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, 
                                rgba(255, 255, 255, 0.4) 0%, 
                                rgba(255, 255, 255, 0.1) 100%);
        border-radius: var(--border-radius-lg);
        z-index: -1;
    }

    /* Card Advanced Decorative Elements */
    .card-decoration {
        position: absolute;
        z-index: 1;
        transition: var(--transition-spring);
    }

    .decoration-1 {
        top: -40px;
        right: -40px;
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, var(--primary-100), var(--primary-500));
        border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
        opacity: 0.15;
    }

    .decoration-2 {
        bottom: -50px;
        left: -50px;
        width: 140px;
        height: 140px;
        background: linear-gradient(135deg, var(--secondary-100), var(--secondary-500));
        border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
        opacity: 0.15;
    }

    /* Card Corner Accents with Glow */
    .card-corner {
        position: absolute;
        width: 40px;
        height: 40px;
        z-index: 3;
    }

    .corner-top-left {
        top: 10px;
        left: 10px;
        border-top: 3px solid var(--primary-500);
        border-left: 3px solid var(--primary-500);
        border-top-left-radius: 10px;
        box-shadow: -3px -3px 10px var(--primary-glow);
    }

    .corner-top-right {
        top: 10px;
        right: 10px;
        border-top: 3px solid var(--secondary-500);
        border-right: 3px solid var(--secondary-500);
        border-top-right-radius: 10px;
        box-shadow: 3px -3px 10px var(--secondary-glow);
    }

    .corner-bottom-left {
        bottom: 10px;
        left: 10px;
        border-bottom: 3px solid var(--accent-500);
        border-left: 3px solid var(--accent-500);
        border-bottom-left-radius: 10px;
        box-shadow: -3px 3px 10px var(--accent-glow);
    }

    .corner-bottom-right {
        bottom: 10px;
        right: 10px;
        border-bottom: 3px solid var(--success-500);
        border-right: 3px solid var(--success-500);
        border-bottom-right-radius: 10px;
        box-shadow: 3px 3px 10px var(--success-glow);
    }

    /* Advanced Login Header with Animated Elements */
    .login-header {
        text-align: center;
        margin-bottom: 30px;
        position: relative;
    }

    .login-header h1 {
        font-family: 'Montserrat', sans-serif;
        font-size: 2.2rem;
        font-weight: 700;
        color: var(--gray-900);
        margin-bottom: 8px;
        background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        position: relative;
        display: inline-block;
        text-shadow: 0 5px 15px var(--primary-glow);
        animation: text-shimmer 5s infinite linear;
    }

   

    .login-header h1::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
        border-radius: 3px;
        animation: width-pulse 3s infinite ease-in-out;
    }

    @keyframes width-pulse {
        0%, 100% {
            width: 60px;
            opacity: 1;
        }
        50% {
            width: 100px;
            opacity: 0.8;
        }
    }

    .login-header p {
        color: var(--gray-500);
        font-size: 1rem;
        position: relative;
        display: inline-block;
        animation: float-subtle 3s infinite ease-in-out;
    }

    @keyframes float-subtle {
        0%, 100% {
            transform: translateY(0);
        }
        50% {
            transform: translateY(-5px);
        }
    }

    /* Header Badge */
    .header-badge {
        position: absolute;
        top: -15px;
        left: 50%;
        transform: translateX(-50%);
        background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
        color: white;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 5px 15px;
        border-radius: 20px;
        box-shadow: 0 5px 15px var(--primary-glow);
        animation: badge-pulse 3s infinite ease-in-out;
    }


    /* Advanced Form Elements with Enhanced Styling */
    .form-group {
        margin-bottom: 24px;
        position: relative;
        z-index: 2;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 0.9rem;
        color: var(--gray-700);
        transition: var(--transition);
        position: relative;
        padding-left: 5px;
    }

    .form-group label::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 0;
        transform: translateY(-50%);
        width: 3px;
        height: 0;
        background: linear-gradient(to bottom, var(--primary-600), var(--secondary-600));
        border-radius: 3px;
        transition: var(--transition);
    }

    .form-group:hover label::before {
        height: 80%;
    }

    .form-group label::after {
        content: '';
        position: absolute;
        bottom: -2px;
        left: 0;
        width: 0;
        height: 1px;
        background: var(--primary-600);
        transition: var(--transition);
    }

    .form-group:hover label {
        color: var(--primary-600);
        transform: translateX(3px);
    }

    .form-group:hover label::after {
        width: 30px;
    }

    .input-group {
        position: relative;
        z-index: 2;
    }

    .input-group input {
        width: 100%;
        padding: 16px 18px;
        padding-right: 50px;
        padding-left: 50px;
        border: 1px solid var(--gray-300);
        border-radius: var(--border-radius);
        font-size: 1rem;
        color: var(--gray-800);
        background-color: rgba(255, 255, 255, 0.9);
        transition: var(--transition-bounce);
        box-shadow: var(--shadow-sm);
        transform: translateZ(0);
    }

    .input-group input:focus {
        outline: none;
        border-color: var(--primary-600);
        box-shadow: 0 0 0 4px var(--primary-100), 0 5px 15px rgba(0, 0, 0, 0.1);
        transform: translateY(-3px) translateZ(10px);
    }

    .input-icon {
        position: absolute;
        left: 18px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        font-size: 18px;
        transition: var(--transition);
    }

    .input-icon.password-toggle {
        position: absolute;
        right: 18px;
        left: auto;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        font-size: 18px;
        cursor: pointer;
        transition: var(--transition);
    }

    .input-group:hover .input-icon {
        color: var(--primary-600);
    }

    .input-group input:focus ~ .input-icon {
        color: var(--primary-600);
        transform: translateY(-50%) scale(1.1);
    }

    /* Login Button */
    .login-btn-wrapper {
        position: relative;
        width: 100%;
        margin-bottom: 10px;
        overflow: hidden;
        border-radius: var(--border-radius);
        z-index: 2;
    }

    .login-btn {
        width: 100%;
        padding: 18px;
        background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
        color: white;
        border: none;
        border-radius: var(--border-radius);
        font-size: 1.1rem;
        font-weight: 600;
        cursor: pointer;
        transition: var(--transition-spring);
        position: relative;
        z-index: 1;
        overflow: hidden;
        box-shadow: 0 8px 20px var(--primary-glow);
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }

    .login-btn i {
        margin-right: 8px;
        position: relative;
        z-index: 2;
        transition: var(--transition);
    }

    .login-btn::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: all 0.6s ease;
    }

    .login-btn:hover {
        transform: translateY(-8px) scale(1.03);
        box-shadow: 0 15px 30px var(--primary-glow), 0 0 15px var(--primary-glow);
        letter-spacing: 1px;
    }

    .login-btn:hover::before {
        left: 100%;
    }

    .login-btn:active {
        transform: translateY(-3px) scale(0.98);
    }

    /* Login Footer */
    .login-footer {
        display: flex;
        justify-content: space-between;
        margin-top: 20px;
        font-size: 0.95rem;
        position: relative;
        z-index: 2;
    }

    .login-footer a {
        color: var(--primary-600);
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition-bounce);
        position: relative;
        padding: 3px 0;
        display: flex;
        align-items: center;
    }

    .login-footer a i {
        margin-right: 5px;
        font-size: 0.9em;
        transition: var(--transition);
    }

    .login-footer a:hover {
        color: var(--secondary-600);
        transform: translateY(-3px);
        text-shadow: 0 3px 10px var(--primary-glow);
    }

    .login-footer a:hover i {
        transform: translateX(-3px);
    }

    .login-footer a::before {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 100%;
        height: 2px;
        background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
        transform: scaleX(0);
        transform-origin: right;
        transition: transform 0.4s ease;
        border-radius: 2px;
    }

    .login-footer a:hover::before {
        transform: scaleX(1);
        transform-origin: left;
    }

    /* Error Message */
    .error-message {
        color: var(--danger-600);
        font-size: 0.85rem;
        margin-top: 6px;
        display: flex;
        align-items: center;
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
  

    /* Responsive Styles */
    @media (max-width: 768px) {
        .login-card {
            padding: 30px;
        }

        .login-header h1 {
            font-size: 1.8rem;
        }
    }

    @media (max-width: 480px) {
        .login-card {
            padding: 25px;
        }

        .login-header h1 {
            font-size: 1.6rem;
        }

        .input-group input {
            padding: 12px 14px;
        }

        .login-btn {
            padding: 12px 18px;
        }
    }

    .input-icon, .fa, .fas, .fab {
        display: inline-block !important;
        font-style: normal;
        font-variant: normal;
        text-rendering: auto;
        line-height: 1;
    }

    .input-group .input-icon {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-400);
        font-size: 18px;
        transition: var(--transition);
        z-index: 10;
    }

    .input-group .input-icon:not(.password-toggle) {
        left: 18px;
    }

    .input-group .input-icon.password-toggle {
        right: 18px;
        cursor: pointer;
    }

    .alert {
        padding: 12px;
        border-radius: var(--border-radius);
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        animation: fadeIn 0.5s ease-in-out;
    }

    .alert-danger {
        background-color: var(--danger-100);
        color: var(--danger-700);
        border-left: 4px solid var(--danger-600);
    }

    .alert-warning {
        background-color: var(--warning-100);
        color: var(--warning-700);
        border-left: 4px solid var(--warning-600);
    }

    .alert-success {
        background-color: var(--success-100);
        color: var(--success-700);
        border-left: 4px solid var(--success-600);
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
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

<!-- Background Elements -->
<div class="background">
    <div class="bg-gradient"></div>
    <div class="bg-pattern"></div>
    <div class="bg-grid"></div>
    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
        <div class="shape shape-3"></div>
        <div class="shape shape-4"></div>
        <div class="shape shape-5"></div>
        <div class="shape shape-6"></div>
        <div class="shape shape-7"></div>
    </div>
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>
    <div class="binary-rain">
        <div class="binary">01001100</div>
        <div class="binary">10101010</div>
        <div class="binary">11001100</div>
        <div class="binary">00110011</div>
        <div class="binary">10011001</div>
        <div class="binary">01010101</div>
        <div class="binary">11110000</div>
        <div class="binary">00001111</div>
        <div class="binary">10101010</div>
    </div>
</div>

<div class="container">
    <a href="../index.php" class="logo-container">
        <div class="logo-glow"></div>
        <div class="logo">
            <?php if(isset($settings['site_image']) && !empty($settings['site_image'])): ?>
                <img src="../uploads/<?php echo $settings['site_image']; ?>" alt="<?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>" />
            <?php else: ?>
                <img src="../imgs/logo.png" alt="<?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?>" />
            <?php endif; ?>
        </div>
    </a>
    
    <div class="login-card-wrapper">
        <div class="login-card">
            <div class="card-glass"></div>
            <div class="card-decoration decoration-1"></div>
            <div class="card-decoration decoration-2"></div>
            <div class="card-corner corner-top-left"></div>
            <div class="card-corner corner-top-right"></div>
            <div class="card-corner corner-bottom-left"></div>
            <div class="card-corner corner-bottom-right"></div>
            
            <div class="login-header">
                <div class="header-badge">Secure Login</div>
                <h1>Welcome Back</h1>
                <p>Sign in your account</p>
            </div>

            <?php if(!empty($login_err)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $login_err; ?>
                </div>
            <?php endif; ?>
            
            <?php if(isset($_SESSION["status_message"])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $_SESSION["status_message"]; ?>
                </div>
                <?php unset($_SESSION["status_message"]); ?>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <div class="input-group">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo $email; ?>" required>
                        <div class="error-message"><?php echo $email_err; ?></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <i class="fas fa-eye input-icon password-toggle"></i>
                        <div class="error-message"><?php echo $password_err; ?></div>
                    </div>
                </div>
                
                <div class="login-btn-wrapper">
                    <button type="submit" class="login-btn">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </div>
            </form>

            <div class="login-footer">
                <a href="../verifier/forget-password.php"><i class="fas fa-key"></i> Forgot Password?</a>
                <a href="signup.php"><i class="fas fa-user-plus"></i> Create Account</a>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        // Hide preloader after page loads
        setTimeout(function() {
            document.querySelector(".preloader").classList.add("fade-out");
            setTimeout(function() {
                document.querySelector(".preloader").style.display = "none";
            }, 500);
        }, 1000);
        
        // Toggle password visibility
        document.querySelector(".password-toggle").addEventListener("click", function() {
            const passwordInput = document.getElementById("password");
            const type = passwordInput.getAttribute("type") === "password" ? "text" : "password";
            passwordInput.setAttribute("type", type);
            this.classList.toggle("fa-eye");
            this.classList.toggle("fa-eye-slash");
        });
        
        // Add animated background elements
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const particles = document.querySelectorAll('.particle');
            const loginCard = document.querySelector('.login-card');
            
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            const moveX = (mouseX - 0.5) * 40;
            const moveY = (mouseY - 0.5) * 40;
          
            
            
            
        });
        
        // Generate binary rain
        function generateBinaryRain() {
            const binaryRain = document.querySelector('.binary-rain');
            if (!binaryRain) return;
            
            setInterval(() => {
                const binary = document.createElement('div');
                binary.classList.add('binary');
                binary.style.left = Math.random() * 100 + '%';
                binary.style.animationDuration = Math.random() * 10 + 10 + 's';
                binary.textContent = Math.random() > 0.5 ? '10101010' : '01010101';
                binaryRain.appendChild(binary);
                
                setTimeout(() => {
                    binary.remove();
                }, 20000);
            }, 1000);
        }
        
        generateBinaryRain();
    });
</script>
</body>
</html>
<?php
// Close connection
if (isset($conn) && $conn) {
$conn->close();
}
?>
