<?php
// Start session
session_start();

// Enable error reporting for development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"])) {
    header("location: ../config/logout.php");
    exit;
}

// Include database connection
require_once "../config/config.php";
// Include mailer
require_once "utils/mailer.php";

// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];
$role = isset($_SESSION["user_role"]) ? $_SESSION["user_role"] : "";
$full_name = isset($_SESSION["full_name"]) ? $_SESSION["full_name"] : "";

// Debug information
error_log("User role in profile.php: " . $role);
error_log("User ID: " . $user_id);
error_log("User email: " . $email);

$phone = "";
$address = "";
$gender = "";
$profile_image = "";
$date_of_birth = "";
$phone_err = "";
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate phone number
    if (empty(trim($_POST["phone"]))) {
        $phone_err = "Please enter a phone number.";
    } else {
        $phone = trim($_POST["phone"]);
        // Check if the number is valid (allows country codes like +213)
        if (!preg_match('/^\+?[0-9]{12}$/', $phone)) {
            $phone_err = "The phone number format is not valid.";
        } else {
            // Check if phone already exists for another user
            $check_phone_sql = "SELECT id FROM user_profiles WHERE phone = ? AND user_id != ?";
            $check_phone_stmt = $conn->prepare($check_phone_sql);
            if ($check_phone_stmt === false) {
                // Log the SQL error
                error_log("Prepare statement error: " . $conn->error);
                $phone_err = "An error occurred. Please try again later.";
            } else {
                $check_phone_stmt->bind_param("si", $phone, $user_id);
                $check_phone_stmt->execute();
                $check_phone_result = $check_phone_stmt->get_result();
                
                if ($check_phone_result->num_rows > 0) {
                    $phone_err = "This phone number is already used by another user.";
                }
                $check_phone_stmt->close();
            }
        }
    }

    // If no errors, proceed with saving data
    if (empty($phone_err)) {
        $address = trim($_POST["address"]);
        $gender = trim($_POST["gender"]);
        $date_of_birth = trim($_POST["dob"]);
        
        // Handle profile image upload
        $profile_image_path = "";
        
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
                    $profile_image_path = $target_file;
                }
            }
        }
        
        // Check if user profile already exists
        $check_sql = "SELECT id FROM user_profiles WHERE user_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        if ($check_stmt === false) {
            // Log the SQL error
            error_log("Prepare statement error: " . $conn->error);
            echo "Error: " . $conn->error;
            exit;
        }
        
        $check_stmt->bind_param("i", $user_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_stmt->close();
        
        if ($check_result->num_rows > 0) {
            // Update existing profile
            if (!empty($profile_image_path)) {
                $sql = "UPDATE user_profiles SET phone = ?, address = ?, gender = ?, dob = ?, profile_image = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    // Log the SQL error
                    error_log("Prepare statement error: " . $conn->error);
                    echo "Error: " . $conn->error;
                    exit;
                }
                $stmt->bind_param("sssssi", $phone, $address, $gender, $date_of_birth, $profile_image_path, $user_id);
            } else {
                $sql = "UPDATE user_profiles SET phone = ?, address = ?, gender = ?, dob = ? WHERE user_id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt === false) {
                    // Log the SQL error
                    error_log("Prepare statement error: " . $conn->error);
                    echo "Error: " . $conn->error;
                    exit;
                }
                $stmt->bind_param("ssssi", $phone, $address, $gender, $date_of_birth, $user_id);
            }
        } else {
            // Insert new profile
            $sql = "INSERT INTO user_profiles (user_id, phone, address, gender, dob, profile_image) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                // Log the SQL error
                error_log("Prepare statement error: " . $conn->error);
                echo "Error: " . $conn->error;
                exit;
            }
            $stmt->bind_param("isssss", $user_id, $phone, $address, $gender, $date_of_birth, $profile_image_path);
        }
        
        if ($stmt->execute()) {
            $success = true;
            
            // Store profile data in session
            $_SESSION["phone"] = $phone;
            $_SESSION["address"] = $address;
            $_SESSION["gender"] = $gender;
            $_SESSION["dob"] = $date_of_birth;
            if (!empty($profile_image_path)) {
                $_SESSION["profile_image"] = $profile_image_path;
            }
            
            // Generate a 6-digit verification code
            $verification_code = sprintf("%06d", mt_rand(100000, 999999));
            
            // Store the verification code in the session
            $_SESSION["verification_code"] = $verification_code;
            $_SESSION["code_expires_at"] = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            
            // Update the verification code in the database
            $update_code_sql = "UPDATE users SET verification_code = ?, code_expires_at = ? WHERE id = ?";
            $update_code_stmt = $conn->prepare($update_code_sql);
            if ($update_code_stmt) {
                $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                $update_code_stmt->bind_param("ssi", $verification_code, $expires_at, $user_id);
                $update_code_stmt->execute();
                $update_code_stmt->close();
            }
            
            // Send verification email
            $email_sent = sendVerificationEmail($email, $full_name, $verification_code);
            
            if ($email_sent) {
                // Redirect to verification page
                header("location: verify-email.php");
                exit;
            } else {
                // Email sending failed
                echo "Error: Failed to send verification email. Please try again.";
            }
        } else {
            echo "Error: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// Close connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile | ConsultPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            animation: gradientShift 15s infinite alternate;
        }

        @keyframes gradientShift {
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
            animation: patternMove 20s linear infinite;
        }

        @keyframes patternMove {
            0% {
                background-position: 0 0;
            }
            100% {
                background-position: 100px 100px;
            }
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
            animation: gridMove 15s linear infinite;
        }

        @keyframes gridMove {
            0% {
                background-position: 0 0;
            }
            100% {
                background-position: 50px 50px;
            }
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

        @keyframes float {
            0%, 100% {
                transform: translateY(0) scale(1) rotate(0deg);
            }
            25% {
                transform: translateY(-30px) scale(1.05) rotate(5deg);
            }
            50% {
                transform: translateY(0) scale(1.1) rotate(0deg);
            }
            75% {
                transform: translateY(30px) scale(1.05) rotate(-5deg);
            }
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
            max-width: 550px;
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

        /* Profile Card with 3D and Glassmorphism Effects */
        .profile-card-wrapper {
            position: relative;
            width: 100%;
            max-width: 550px;
            perspective: 1000px;
        }

        .profile-card {
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-xl), 0 0 0 1px var(--primary-100);
            width: 100%;
            padding: 40px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
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
            animation: morph 10s infinite alternate ease-in-out;
        }

        .decoration-2 {
            bottom: -50px;
            left: -50px;
            width: 140px;
            height: 140px;
            background: linear-gradient(135deg, var(--secondary-100), var(--secondary-500));
            border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
            opacity: 0.15;
            animation: morph 12s infinite alternate-reverse ease-in-out;
        }

        @keyframes morph {
            0% {
                border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            }
            25% {
                border-radius: 50% 50% 20% 80% / 25% 80% 20% 75%;
            }
            50% {
                border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
            }
            75% {
                border-radius: 20% 80% 50% 50% / 40% 40% 60% 60%;
            }
            100% {
                border-radius: 80% 20% 40% 60% / 50% 60% 40% 50%;
            }
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

        /* Profile Header */
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .profile-header h1 {
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

        @keyframes text-shimmer {
            0% {
                background-position: 0% 50%;
            }
            100% {
                background-position: 100% 50%;
            }
        }

        .profile-header h1::after {
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

        .profile-header p {
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

        /* Avatar Container */
        .avatar-container {
            display: flex;
            justify-content: center;
            margin-bottom: 10px; /* Reduced from 30px to make room for the required message */
            position: relative;
        }

        .avatar {
            position: relative;
            width: 120px;
            height: 120px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            border: 3px solid var(--primary-light);
            box-shadow: 0 10px 20px var(--primary-glow);
            transition: all 0.3s ease;
        }

        .avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 15px 30px var(--primary-glow);
        }

        .avatar-icon {
            width: 50px;
            height: 50px;
            color: var(--primary-500);
        }

        .avatar-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            position: absolute;
            display: none;
        }

        .add-icon {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 36px;
            height: 36px;
            background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 20px;
            cursor: pointer;
            box-shadow: 0 4px 10px var(--primary-glow);
            transition: all 0.3s ease;
            z-index: 2;
        }

        .add-icon:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 15px var(--primary-glow);
        }

        #file-input {
            display: none;
        }

        /* Photo required message */
        .photo-required {
            color: var(--primary-600);
            font-size: 0.9rem;
            margin-top: 5px;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-required i {
            margin-right: 5px;
        }

        .photo-required.error {
            color: var(--danger-600);
        }

        /* Form Groups */
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

        .input-group input, .input-group select {
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
            box-shadow: var(--box-shadow-sm);
            transform: translateZ(0);
        }

        .input-group input:focus, .input-group select:focus {
            outline: none;
            border-color: var(--primary-600);
            box-shadow: 0 0 0 4px var(--primary-100), 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px) translateZ(10px);
        }

        .input-group select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            background-size: 16px;
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 18px;
            transition: var(--transition);
            display: inline-block !important;
            font-style: normal;
            font-variant: normal;
            text-rendering: auto;
            line-height: 1;
            z-index: 10;
        }

        .input-group:hover .input-icon {
            color: var(--primary-600);
        }

        .input-group input:focus ~ .input-icon, .input-group select:focus ~ .input-icon {
            color: var(--primary-600);
            transform: translateY(-50%) scale(1.1);
        }

        /* Validation Icons */
        .validation-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            transition: var(--transition);
            opacity: 0;
            z-index: 10;
        }

        .validation-icon.valid {
            color: var(--success-600);
        }

        .validation-icon.invalid {
            color: var(--danger-600);
        }

        .input-group.is-valid .validation-icon.valid,
        .input-group.is-invalid .validation-icon.invalid {
            opacity: 1;
            animation: pop-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes pop-in {
            0% {
                transform: translateY(-50%) scale(0);
            }
            50% {
                transform: translateY(-50%) scale(1.2);
            }
            100% {
                transform: translateY(-50%) scale(1);
            }
        }

        .input-group.is-valid input,
        .input-group.is-valid select {
            border-color: var(--success-600);
            padding-right: 50px;
        }

        .input-group.is-invalid input,
        .input-group.is-invalid select {
            border-color: var(--danger-600);
            padding-right: 50px;
        }

        .input-error {
            border-color: var(--danger-600) !important;
        }

        .error-message {
            color: var(--danger-600);
            font-size: 0.85rem;
            margin-top: 5px;
            display: flex;
            align-items: center;
        }

        /* Submit Button */
        .submit-btn-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 10px;
            overflow: hidden;
            border-radius: var(--border-radius);
            z-index: 2;
        }

        .submit-btn {
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

        .submit-btn i {
            margin-right: 8px;
            position: relative;
            z-index: 2;
            transition: var(--transition);
            display: inline-block !important;
        }

        .submit-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }

        .submit-btn:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 15px 30px var(--primary-glow), 0 0 15px var(--primary-glow);
            letter-spacing: 1px;
        }

        .submit-btn:hover::before {
            left: 100%;
        }

        .submit-btn:active {
            transform: translateY(-3px) scale(0.98);
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

        /* Responsive Styles */
        @media (max-width: 768px) {
            .profile-card {
                padding: 30px;
            }

            .profile-header h1 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 480px) {
            .profile-card {
                padding: 25px;
            }

            .profile-header h1 {
                font-size: 1.6rem;
            }

            .input-group input, .input-group select {
                padding: 12px 14px;
                padding-right: 40px;
                padding-left: 40px;
            }

            .submit-btn {
                padding: 12px 18px;
            }
            
            .avatar {
                width: 100px;
                height: 100px;
            }
            
            .add-icon {
                width: 30px;
                height: 30px;
                font-size: 16px;
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
        </div>
        <div class="particles">
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
                <img src="../imgs/logo.png" alt="Consult Pro Logo" />
            </div>
        </a>
        
        <div class="profile-card-wrapper">
            <div class="profile-card">
                <div class="card-glass"></div>
                <div class="card-decoration decoration-1"></div>
                <div class="card-decoration decoration-2"></div>
                <div class="card-corner corner-top-left"></div>
                <div class="card-corner corner-top-right"></div>
                <div class="card-corner corner-bottom-left"></div>
                <div class="card-corner corner-bottom-right"></div>
                
                <div class="profile-header">
                    <h1>Complete Your Profile</h1>
                    <p>Let's set up your professional profile</p>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" id="profileForm">
                    <div class="avatar-container">
                        <div class="avatar">
                            <svg class="avatar-icon" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                            </svg>
                            <img class="avatar-image" id="avatar-preview" src="<?php echo !empty($profile_image) ? $profile_image : ''; ?>" alt="Profile photo">
                            <div class="add-icon" id="add-photo">
                                <i class="fas fa-camera"></i>
                            </div>
                            <input type="file" id="file-input" accept="image/*" name="profile_image" required>
                        </div>
                    </div>
                    <!-- Added required photo message here -->
                    <div class="photo-required" id="photo-required-message">
                        <i class="fas fa-info-circle"></i> Profile photo is required
                    </div>

                    <div class="form-group">
                        <label for="fullName">Full Name</label>
                        <div class="input-group">
                            <input type="text" id="fullName" name="fullName" placeholder="Full name" value="<?php echo $full_name; ?>" readonly>
                            <i class="fas fa-user input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-group">
                            <input type="email" id="email" name="email" placeholder="Email" value="<?php echo $email; ?>" readonly>
                            <i class="fas fa-envelope input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <div class="input-group">
                            <input type="tel" id="phone" name="phone" placeholder="ex: +213612345678" value="<?php echo $phone; ?>" required class="<?php echo !empty($phone_err) ? 'input-error' : ''; ?>">
                            <i class="fas fa-phone input-icon"></i>
                            <i class="fas fa-check-circle validation-icon valid"></i>
                            <i class="fas fa-times-circle validation-icon invalid"></i>
                        </div>
                        <?php if (!empty($phone_err)): ?>
                            <div class="error-message"><?php echo $phone_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="address">Professional Address</label>
                        <div class="input-group">
                            <input type="text" id="address" name="address" placeholder="Enter your professional address" value="<?php echo $address; ?>" required>
                            <i class="fas fa-map-marker-alt input-icon"></i>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <div class="input-group">
                            <select id="gender" name="gender" required>
                                <option value="" disabled <?php echo empty($gender) ? 'selected' : ''; ?>>Select your gender</option>
                                <option value="male" <?php echo $gender === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $gender === 'female' ? 'selected' : ''; ?>>Female</option>
                            </select>
                            <i class="fas fa-venus-mars input-icon"></i>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="dob">Date of birth</label>
                        <div class="input-group">
                            <input type="date" id="dob" name="dob" value="<?php echo $date_of_birth; ?>" required>
                            <i class="fas fa-calendar-alt input-icon"></i>
                        </div>
                    </div>

                    <div class="submit-btn-wrapper">
                        <button type="submit" class="submit-btn">
                            <i class="fas fa-check-circle"></i> Continue
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
    // Prevent going back to previous page
    history.pushState(null, null, document.URL);
    window.addEventListener('popstate', function () {
        history.pushState(null, null, document.URL);
    });
    
    // Hide preloader after page loads
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            document.querySelector(".preloader").classList.add("fade-out");
            setTimeout(function() {
                document.querySelector(".preloader").style.display = "none";
            }, 500);
        }, 1000);
        
        // Add animated background elements
        document.addEventListener('mousemove', function(e) {
            const shapes = document.querySelectorAll('.shape');
            const particles = document.querySelectorAll('.particle');
            
            const mouseX = e.clientX / window.innerWidth;
            const mouseY = e.clientY / window.innerHeight;
            
            const moveX = (mouseX - 0.5) * 40;
            const moveY = (mouseY - 0.5) * 40;
            
        
            // Move shapes
            shapes.forEach(shape => {
                const speed = Math.random() * 0.4 + 0.2;
                shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
            });
            
            // Move particles
            particles.forEach(particle => {
                const speed = Math.random() * 0.6 + 0.3;
                particle.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
            });
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
    
    // Handle file upload preview
    document.getElementById('add-photo').addEventListener('click', function() {
        document.getElementById('file-input').click();
    });

    document.getElementById('file-input').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const avatarPreview = document.getElementById('avatar-preview');
                avatarPreview.src = event.target.result;
                avatarPreview.style.display = 'block';
                document.getElementById('photo-required-message').innerHTML = '<i class="fas fa-check-circle"></i> Photo selected';
                document.getElementById('photo-required-message').style.color = 'var(--success-600)';
            };
            reader.readAsDataURL(file);
        }
    });

    // Function to show validation icons
    function showValidationIcon(inputElement, isValid) {
        const inputGroup = inputElement.parentElement;
        inputGroup.classList.remove('is-valid', 'is-invalid');
        
        if (isValid === true) {
            inputGroup.classList.add('is-valid');
        } else if (isValid === false) {
            inputGroup.classList.add('is-invalid');
        }
    }

    // Validate phone number
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function() {
        const phoneRegex = /^\+?[0-9]{12}$/;
        const isValid = phoneRegex.test(this.value);
        showValidationIcon(this, isValid);
    });
    // Validation du numéro de téléphone
phoneInput.addEventListener('input', function(e) {
    const value = e.target.value;
    
    if (!phoneRegex.test(value) && value !== '') {
        phoneInput.classList.add('input-error');
        showValidationIcon(phoneInput, false);
        
        // Créer un message d'erreur s'il n'existe pas
        let errorMessage = phoneInput.parentElement.nextElementSibling;
        if (!errorMessage || !errorMessage.classList.contains('error-message')) {
            errorMessage = document.createElement('div');
            errorMessage.className = 'error-message';
            phoneInput.parentElement.after(errorMessage);
        }
        
        errorMessage.textContent = 'Invalid phone number format. Use international format (ex: +213612345678)';
    } 
    else {
        phoneInput.classList.remove('input-error');
        showValidationIcon(phoneInput, true);
        
        // Supprimer le message d'erreur s'il existe
        const errorMessage = phoneInput.parentElement.nextElementSibling;
        if (errorMessage && errorMessage.classList.contains('error-message')) {
            errorMessage.textContent = '';
        }
    }
});

// Validation de la date de naissance
const dobInput = document.getElementById('dob');
dobInput.addEventListener('change', function() {
    const today = new Date();
    const birthDate = new Date(dobInput.value);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }
    
    if (age < 18) {
        dobInput.classList.add('input-error');
        
        // Créer un message d'erreur s'il n'existe pas
        let errorMessage = dobInput.parentElement.nextElementSibling;
        if (!errorMessage || !errorMessage.classList.contains('error-message')) {
            errorMessage = document.createElement('div');
            errorMessage.className = 'error-message';
            dobInput.parentElement.after(errorMessage);
        }
        
        errorMessage.textContent = 'You must be at least 18 years old to register';
    } 
    else {
        dobInput.classList.remove('input-error');
        
        // Supprimer le message d'erreur s'il existe
        const errorMessage = dobInput.parentElement.nextElementSibling;
        if (errorMessage && errorMessage.classList.contains('error-message')) {
            errorMessage.textContent = '';
        }
    }
});

// Validation à la soumission du formulaire
document.getElementById('profileForm').addEventListener('submit', function(e) {
    let hasError = false;
    
    // Valider la photo de profil
    const fileInput = document.getElementById('file-input');
    const photoMessage = document.getElementById('photo-required-message');
    
    if (fileInput.files.length === 0 && !document.getElementById('avatar-preview').src) {
        e.preventDefault();
        photoMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please upload a profile photo';
        photoMessage.style.color = 'var(--danger-600)';
        hasError = true;
    }
    
    // Valider le téléphone (format international)
    if (!phoneRegex.test(phoneInput.value)) {
        e.preventDefault();
        phoneInput.classList.add('input-error');
        showValidationIcon(phoneInput, false);
        
        let errorMessage = phoneInput.parentElement.nextElementSibling;
        if (!errorMessage || !errorMessage.classList.contains('error-message')) {
            errorMessage = document.createElement('div');
            errorMessage.className = 'error-message';
            phoneInput.parentElement.after(errorMessage);
        }
        
        errorMessage.textContent = 'Invalid phone number format. Use international format (ex: +213612345678)';
        hasError = true;
    } 
    else {
        showValidationIcon(phoneInput, true);
    }
    
    // Valider la date de naissance
    if (dobInput.value) {
        const today = new Date();
        const birthDate = new Date(dobInput.value);
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        if (age < 18) {
            e.preventDefault();
            dobInput.classList.add('input-error');
            
            let errorMessage = dobInput.parentElement.nextElementSibling;
            if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                errorMessage = document.createElement('div');
                errorMessage.className = 'error-message';
                dobInput.parentElement.after(errorMessage);
            }
            
            errorMessage.textContent = 'You must be at least 18 years old to register';
            hasError = true;
        }
    } 
    else {
        e.preventDefault();
        dobInput.classList.add('input-error');
        
        let errorMessage = dobInput.parentElement.nextElementSibling;
        if (!errorMessage || !errorMessage.classList.contains('error-message')) {
            errorMessage = document.createElement('div');
            errorMessage.className = 'error-message';
            dobInput.parentElement.after(errorMessage);
        }
        
        errorMessage.textContent = 'Please enter your date of birth';
        hasError = true;
    }
    
    if (hasError) {
        e.preventDefault();
    }
});

// Initialiser la validation du téléphone s'il a une valeur
if (phoneInput.value) {
    if (phoneRegex.test(phoneInput.value)) {
        showValidationIcon(phoneInput, true);
    } 
    else {
        showValidationIcon(phoneInput, false);
    }
}
</script>
</body>
</html>