<?php
// Start session
session_start();

// Check if user has initiated password reset
if (!isset($_SESSION["reset_email"]) || !isset($_SESSION["verification_code"]) || !isset($_SESSION["code_expires_at"])) {
    header("location: forget-password.php");
    exit;
}

// Include database connection
require_once "../config/config.php";
// Include mailer
require_once "utils/mailer.php";

// Initialize variables
$email = $_SESSION["reset_email"];
$user_id = $_SESSION["reset_user_id"];
$full_name = $_SESSION["reset_full_name"];
$verification_code = $_SESSION["verification_code"];
$code_expires_at = $_SESSION["code_expires_at"];

$error_message = "";
$success_message = "";
$resend_disabled = true;
$time_remaining = 0;

// Check if code has expired
$now = new DateTime();
$expires = new DateTime($code_expires_at);
if ($now > $expires) {
    $resend_disabled = false;
} else {
    $interval = $now->diff($expires);
    $time_remaining = $interval->format('%i:%s'); // minutes:seconds
    $resend_disabled = true;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if it's a resend request
    if (isset($_POST["resend"])) {
        // Generate a new 6-digit verification code
        $new_verification_code = sprintf("%06d", mt_rand(100000, 999999));
        
        // Update the verification code in the session
        $_SESSION["verification_code"] = $new_verification_code;
        $_SESSION["code_expires_at"] = date('Y-m-d H:i:s', strtotime('+5 minutes'));
        
        // Update the verification code in the database
        $update_code_sql = "UPDATE users SET verification_code = ?, code_expires_at = ? WHERE id = ?";
        $update_code_stmt = $conn->prepare($update_code_sql);
        if ($update_code_stmt) {
            $expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $update_code_stmt->bind_param("ssi", $new_verification_code, $expires_at, $user_id);
            $update_code_stmt->execute();
            $update_code_stmt->close();
        }
        
        // Send verification email
        $email_sent = sendVerificationEmail($email, $full_name, $new_verification_code);
        
        if ($email_sent) {
            $success_message = "A new verification code has been sent to your email.";
            $verification_code = $new_verification_code;
            $resend_disabled = true;
            $code_expires_at = date('Y-m-d H:i:s', strtotime('+5 minutes'));
            $expires = new DateTime($code_expires_at);
            $interval = $now->diff($expires);
            $time_remaining = $interval->format('%i:%s'); // minutes:seconds
        } else {
            $error_message = "Failed to send verification email. Please try again.";
        }
    } else {
        // Verify the code
        $entered_code = trim($_POST["verification_code"]);
        
        if (empty($entered_code)) {
            $error_message = "Please enter the verification code.";
        } else if ($entered_code !== $verification_code) {
            $error_message = "Invalid verification code. Please try again.";
        } else {
            // Check if code has expired
            $now = new DateTime();
            $expires = new DateTime($code_expires_at);
            if ($now > $expires) {
                $error_message = "Verification code has expired. Please request a new one.";
                $resend_disabled = false;
            } else {
                // Code is valid, proceed to reset password page
                $_SESSION["code_verified"] = true;
                header("location: reset-password.php");
                exit;
            }
        }
    }
}

// Close connection
$conn->close();

// Calculate total seconds for JavaScript timer
$total_seconds = 0;
if ($resend_disabled && !empty($time_remaining)) {
    list($minutes, $seconds) = explode(':', $time_remaining);
    $total_seconds = (intval($minutes) * 60) + intval($seconds);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Code | Consult Pro</title>
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
            
            --box-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --box-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --box-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --box-shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Glow Colors */
            --primary-glow: rgba(37, 99, 235, 0.5);
            --secondary-glow: rgba(124, 58, 237, 0.5);
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
        }

        /* Background Elements */
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

        /* Logo */
        .logo-container {
            position: relative;
            margin-bottom: 10px;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 2;
        }

        .logo:hover {
            transform: scale(1.1) rotate(5deg);
        }

        .logo img {
            max-height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
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

        /* Card */
        .verify-code-card {
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-xl), 0 0 0 1px var(--primary-100);
            width: 100%;
            padding: 40px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.8s ease;
        }

        .verify-code-card:hover {
            transform: translateY(-10px);
            box-shadow: var(--box-shadow-xl), 0 30px 60px var(--primary-glow), 0 0 0 2px var(--primary-200);
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

        /* Card Decorative Elements */
        .card-decoration {
            position: absolute;
            z-index: 1;
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

        /* Card Corner Accents */
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
            border-bottom: 3px solid var(--primary-500);
            border-left: 3px solid var(--primary-500);
            border-bottom-left-radius: 10px;
            box-shadow: -3px 3px 10px var(--primary-glow);
        }

        .corner-bottom-right {
            bottom: 10px;
            right: 10px;
            border-bottom: 3px solid var(--secondary-500);
            border-right: 3px solid var(--secondary-500);
            border-bottom-right-radius: 10px;
            box-shadow: 3px 3px 10px var(--secondary-glow);
        }

        /* Header */
        .verify-code-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .verify-code-header h1 {
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
        }

        .verify-code-header h1::after {
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

        .verify-code-header p {
            color: var(--gray-500);
            font-size: 1rem;
        }

        /* Email Icon */
        .email-icon-container {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }

        .email-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-400), var(--secondary-400));
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: white;
            font-size: 36px;
            box-shadow: 0 10px 20px var(--primary-glow);
        }

        /* Verification Code Input */
        .verification-code-container {
            margin-bottom: 30px;
        }

        .verification-code-input {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }

        .code-input {
            width: 100%;
            padding: 16px;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            letter-spacing: 8px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            background-color: rgba(255, 255, 255, 0.9);
            transition: var(--transition);
            box-shadow: var(--box-shadow-sm);
        }

        .code-input:focus {
            outline: none;
            border-color: var(--primary-600);
            box-shadow: 0 0 0 4px var(--primary-100), 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px);
        }

        /* Countdown Timer */
        .countdown-container {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .countdown-title {
            font-size: 1rem;
            color: var(--gray-600);
            margin-bottom: 10px;
        }

        .countdown-wrapper {
            position: relative;
            width: 100%;
            height: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .countdown-display {
            position: relative;
            z-index: 2;
            font-family: 'Orbitron', sans-serif;
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-700);
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .countdown-unit {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 0 5px;
        }

        .countdown-value {
            font-size: 2rem;
            line-height: 1;
            min-width: 60px;
            text-align: center;
        }

        .countdown-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--gray-500);
            margin-top: 5px;
        }

        .countdown-separator {
            font-size: 2rem;
            color: var(--primary-500);
            margin: 0 5px;
        }

        /* Verify Button */
        .verify-btn-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 20px;
            overflow: hidden;
            border-radius: var(--border-radius);
            z-index: 2;
        }

        .verify-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s ease;
            position: relative;
            z-index: 1;
            overflow: hidden;
            box-shadow: 0 8px 20px var(--primary-glow);
        }

        .verify-btn i {
            margin-right: 8px;
            position: relative;
            z-index: 2;
            transition: var(--transition);
            display: inline-block !important;
        }

        .verify-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }

        .verify-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px var(--primary-glow);
        }

        .verify-btn:hover::before {
            left: 100%;
        }

        .verify-btn:active {
            transform: translateY(-2px);
        }

        /* Resend Button */
        .resend-btn-wrapper {
            text-align: center;
        }

        .resend-btn {
            background: none;
            border: none;
            color: var(--primary-600);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            padding: 8px 16px;
            border-radius: var(--border-radius);
            display: inline-flex;
            align-items: center;
        }

        .resend-btn i {
            margin-right: 8px;
            transition: var(--transition);
            display: inline-block !important;
        }

        .resend-btn:hover {
            background-color: var(--primary-50);
            transform: translateY(-2px);
        }

        .resend-btn:hover i {
            transform: rotate(360deg);
        }

        .resend-btn:disabled {
            color: var(--gray-400);
            cursor: not-allowed;
        }

        .resend-btn:disabled:hover {
            background: none;
            transform: none;
        }

        /* Alert Messages */
        .alert {
            padding: 12px 16px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            animation: fadeIn 0.5s ease-in-out;
        }

        .alert i {
            margin-right: 10px;
            font-size: 18px;
            display: inline-block !important;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            border-left: 4px solid #dc2626;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #047857;
            border-left: 4px solid #10b981;
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
            border-top-color: var(--primary-400);
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .verify-code-card {
                padding: 30px;
            }

            .verify-code-header h1 {
                font-size: 1.8rem;
            }
            
            .countdown-value {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 480px) {
            .verify-code-card {
                padding: 25px;
            }

            .verify-code-header h1 {
                font-size: 1.6rem;
            }

            .code-input {
                font-size: 1.2rem;
                padding: 12px;
            }

            .verify-btn {
                padding: 12px;
            }
            
            .email-icon {
                width: 60px;
                height: 60px;
                font-size: 28px;
            }
            
            .countdown-value {
                font-size: 2rem;
                min-width: 40px;
            }
            
            .countdown-wrapper {
                height: 100px;
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
        <div class="bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
        </div>
    </div>

    <div class="container">
        <a href="../index.php" class="logo-container">
            <div class="logo-glow"></div>
            <div class="logo">
                <img src="../imgs/logo.png" alt="Consult Pro Logo" />
            </div>
        </a>
        
        <div class="verify-code-card">
            <div class="card-glass"></div>
            <div class="card-decoration decoration-1"></div>
            <div class="card-decoration decoration-2"></div>
            <div class="card-corner corner-top-left"></div>
            <div class="card-corner corner-top-right"></div>
            <div class="card-corner corner-bottom-left"></div>
            <div class="card-corner corner-bottom-right"></div>
            
            <div class="verify-code-header">
                <h1>Verify Code</h1>
                <p>Enter the 6-digit code sent to your email</p>
            </div>
            
            <div class="email-icon-container">
                <div class="email-icon">
                    <i class="fas fa-envelope"></i>
                </div>
            </div>
            
            <?php if(!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if(!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                <div class="verification-code-container">
                    <div class="verification-code-input">
                        <input type="text" class="code-input" name="verification_code" maxlength="6" placeholder="------" autocomplete="off" required>
                    </div>
                </div>
                
                <?php if ($resend_disabled && !empty($time_remaining)): ?>
                <div class="countdown-container">
                    <div class="countdown-title">Code expires in:</div>
                    <div class="countdown-wrapper">
                        <div class="countdown-display" id="countdown-display">
                            <div class="countdown-unit">
                                <div class="countdown-value" id="minutes">00</div>
                                <div class="countdown-label">minutes</div>
                            </div>
                            <div class="countdown-separator">:</div>
                            <div class="countdown-unit">
                                <div class="countdown-value" id="seconds">00</div>
                                <div class="countdown-label">seconds</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="verify-btn-wrapper">
                    <button type="submit" class="verify-btn">
                        <i class="fas fa-check-circle"></i> Verify Code
                    </button>
                </div>
            </form>
            
            <div class="resend-btn-wrapper">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <input type="hidden" name="resend" value="1">
                    <button type="submit" class="resend-btn" <?php echo $resend_disabled ? 'disabled' : ''; ?>>
                        <i class="fas fa-sync-alt"></i> Resend Code
                    </button>
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
                
                const mouseX = e.clientX / window.innerWidth;
                const mouseY = e.clientY / window.innerHeight;
                
                const moveX = (mouseX - 0.5) * 40;
                const moveY = (mouseY - 0.5) * 40;
                
                // Move shapes
                shapes.forEach(shape => {
                    const speed = Math.random() * 0.4 + 0.2;
                    shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
                });
            });
            
            // Enhanced Countdown Timer
            const minutesElement = document.getElementById('minutes');
            const secondsElement = document.getElementById('seconds');
            const countdownDisplay = document.getElementById('countdown-display');
            
            if (minutesElement && secondsElement) {
                // Get initial time from PHP
                let totalSeconds = <?php echo $total_seconds; ?>;
                
                // Update countdown every second
                const countdown = setInterval(() => {
                    if (totalSeconds <= 0) {
                        clearInterval(countdown);
                        document.querySelector('.resend-btn').disabled = false;
                        
                        // Show expired message
                        minutesElement.textContent = "00";
                        secondsElement.textContent = "00";
                        
                        // Reload page to update UI
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                        
                        return;
                    }
                    
                    totalSeconds--;
                    
                    // Calculate minutes and seconds
                    const minutes = Math.floor(totalSeconds / 60);
                    const seconds = totalSeconds % 60;
                    
                    // Update display with leading zeros
                    minutesElement.textContent = minutes < 10 ? `0${minutes}` : minutes;
                    secondsElement.textContent = seconds < 10 ? `0${seconds}` : seconds;
                    
                    // Add pulse animation
                    countdownDisplay.classList.add('countdown-pulse');
                    setTimeout(() => {
                        countdownDisplay.classList.remove('countdown-pulse');
                    }, 300);
                    
                }, 1000);
            }
            
            // Auto-focus on code input
            const codeInput = document.querySelector('.code-input');
            if (codeInput) {
                codeInput.focus();
            }
            
            // Format code input to only allow numbers
            if (codeInput) {
                codeInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/[^0-9]/g, '');
                });
            }
        });
    </script>
</body>
</html>
