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
$ccp = "";
$ccp_key = "";
$minutes = "";
$price = "";
$check_file = "";
$error_message = "";
$ccp_err = "";
$ccp_key_err = "";
$minutes_err = "";
$price_err = "";
$check_err = "";
$upload_success = false;

// Check if banking information already exists
$check_sql = "SELECT * FROM banking_information WHERE user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows > 0) {
    $bank_info = $check_result->fetch_assoc();
    $ccp = $bank_info["ccp"];
    $ccp_key = $bank_info["ccp_key"];
    $minutes = $bank_info["consultation_minutes"];
    $price = $bank_info["consultation_price"];
    $check_file = $bank_info["check_file_path"];
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CCP (no negative numbers)
    if (empty($_POST["ccp"])) {
        $ccp_err = "CCP is required";
    } else {
        $ccp = trim($_POST["ccp"]);
        if (!is_numeric($ccp) || $ccp < 0) {
            $ccp_err = "CCP must be a positive number";
        }
    }
    
    // Validate CCP Key (no negative numbers)
    if (empty($_POST["ccp_key"])) {
        $ccp_key_err = "CCP Key is required";
    } else {
        $ccp_key = trim($_POST["ccp_key"]);
        if (!is_numeric($ccp_key) || $ccp_key < 0) {
            $ccp_key_err = "CCP Key must be a positive number";
        }
    }
    
    // Validate Minutes (no negative numbers)
    if (empty($_POST["minutes"])) {
        $minutes_err = "Minutes is required";
    } else {
        $minutes = trim($_POST["minutes"]);
        if (!is_numeric($minutes) || $minutes < 0 || $minutes > 1440) {
            $minutes_err = "Minutes must be a positive number between 0 and 1440";
        }
    }
    
    // Validate Price (no negative numbers)
    if (empty($_POST["price"])) {
        $price_err = "Price is required";
    } else {
        $price = trim($_POST["price"]);
        if (!is_numeric($price) || $price < 0) {
            $price_err = "Price must be a positive number";
        }
    }
    
    // Handle file upload for crossed check
    $check_file_path = "";
    if ($check_result->num_rows > 0 && !empty($check_file)) {
        // Keep existing file if no new file is uploaded
        $check_file_path = $check_file;
    }
    
    if (isset($_FILES["check"]) && $_FILES["check"]["error"] == 0) {
        $allowed_types = ["application/pdf", "image/jpeg", "image/jpg", "image/png"];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($_FILES["check"]["type"], $allowed_types) && $_FILES["check"]["size"] <= $max_size) {
            $upload_dir = "../uploads/checks/";
            
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES["check"]["name"], PATHINFO_EXTENSION);
            $new_filename = "check_" . $user_id . "_" . time() . "." . $file_extension;
            $target_file = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES["check"]["tmp_name"], $target_file)) {
                $check_file_path = $target_file;
                $upload_success = true;
            } else {
                $check_err = "Failed to upload file";
            }
        } else {
            $check_err = "Invalid file. Please upload a PDF or image file (max 5MB)";
        }
    } elseif ($_FILES["check"]["error"] != 4 && empty($check_file)) { // Error 4 means no file was uploaded
        $check_err = "Error uploading file";
    } elseif (empty($check_file_path)) {
        $check_err = "Crossed check file is required";
    }
    
    // If no errors, proceed with saving data
    if (empty($ccp_err) && empty($ccp_key_err) && empty($minutes_err) && empty($price_err) && empty($check_err)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            if ($check_result->num_rows > 0) {
                // Update existing banking information
                $update_sql = "UPDATE banking_information SET 
                    ccp = ?, 
                    ccp_key = ?, 
                    consultation_minutes = ?, 
                    consultation_price = ?, 
                    check_file_path = ?
                    WHERE user_id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssiisi", $ccp, $ccp_key, $minutes, $price, $check_file_path, $user_id);
                $update_stmt->execute();
            } else {
                // Get profile_id from expert_profiledetails table
                $profile_id_sql = "SELECT id FROM expert_profiledetails WHERE user_id = ?";
                $profile_id_stmt = $conn->prepare($profile_id_sql);
                $profile_id_stmt->bind_param("i", $user_id);
                $profile_id_stmt->execute();
                $profile_id_result = $profile_id_stmt->get_result();
                
                if ($profile_id_result->num_rows > 0) {
                    $profile_data = $profile_id_result->fetch_assoc();
                    $profile_id = $profile_data["id"];
                } else {
                    throw new Exception("Profile ID not found for user: " . $user_id);
                }

                // Insert new banking information
                $insert_sql = "INSERT INTO banking_information (
                    user_id, 
                    profile_id,
                    ccp, 
                    ccp_key, 
                    consultation_minutes, 
                    consultation_price, 
                    check_file_path
                ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("iisssis", $user_id, $profile_id, $ccp, $ccp_key, $minutes, $price, $check_file_path);
                $insert_stmt->execute();
            }
            
            // Update expert profile status
            $update_status_sql = "UPDATE expert_profiledetails SET status = 'pending_review' WHERE user_id = ?";
            $update_status_stmt = $conn->prepare($update_status_sql);
            $update_status_stmt->bind_param("i", $user_id);
            $update_status_stmt->execute();
            
            // Commit transaction
            $conn->commit();
            
            // Redirect to success page
            header("location: succesprofile.php");
            exit;
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Banking Information | ConsultPro</title>
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
            --shadow-neon-secondary: rgba(124, 58, 237, 0.5);
            
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
/* Next Button Styling */
.next-btn-wrapper {
            position: relative;
            width: 100%;
            margin-top: 20px;
            overflow: hidden;
            border-radius: var(--border-radius);
            z-index: 2;
        }

        .next-button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            z-index: 1;
            overflow: hidden;
            box-shadow: 0 8px 20px var(--primary-glow);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition-spring);
        }

        .next-button i {
            position: relative;
            z-index: 2;
            transition: var(--transition);
        }

        .next-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }

        .next-button:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 15px 30px var(--primary-glow), 0 0 15px var(--primary-glow);
            letter-spacing: 1px;
        }

        .next-button:hover::before {
            left: 100%;
        }

        .next-button:hover i {
            transform: translateX(5px);
        }

        .next-button:active {
            transform: translateY(-3px) scale(0.98);
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

        /* Advanced Banking Card with 3D and Glassmorphism Effects */
        .banking-card-wrapper {
            position: relative;
            width: 100%;
            max-width: 550px;
            perspective: 1000px;
        }

        .banking-card {
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl), 0 0 0 1px var(--primary-100);
            width: 100%;
            padding: 40px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.8s ease;
            z-index: 2;
            transform-style: preserve-3d;
            transform: translateZ(0);
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

        /* Advanced Banking Header with Animated Elements */
        .banking-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .banking-header h1 {
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

        .banking-header h1::after {
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

        .banking-header p {
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
        }


        /* Form Elements */
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

        .input-group:hover .input-icon {
            color: var(--primary-600);
        }

        .input-group input:focus ~ .input-icon {
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

        .input-group.is-valid input {
            border-color: var(--success-600);
            padding-right: 50px;
        }

        .input-group.is-invalid input {
            border-color: var(--danger-600);
            padding-right: 50px;
        }

        .input-error {
            border-color: var(--danger-600) !important;
        }

        .error-message {
            color: var(--danger-600);
            font-size: 0.85rem;
            margin-top: 6px;
            display: flex;
            align-items: center;
        }

        /* File Upload Section */
        .upload-section {
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: var(--border-radius);
            padding: 20px;
            border: 2px dashed var(--primary-300);
            margin-bottom: 20px;
            transition: var(--transition);
            position: relative;
        }

        .upload-section:hover {
            border-color: var(--primary-600);
            background-color: rgba(255, 255, 255, 0.9);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .upload-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            border: none;
            cursor: pointer;
            margin: 0 auto 15px;
            transition: var(--transition-bounce);
            box-shadow: 0 5px 15px var(--primary-glow);
        }

        .upload-button:hover {
            transform: scale(1.1) translateY(-5px);
            box-shadow: 0 8px 20px var(--primary-glow);
        }

        .upload-button i {
            font-size: 24px;
        }

        .upload-message {
            text-align: center;
            color: var(--gray-600);
            font-size: 0.9rem;
            margin-top: 10px;
        }

        .file-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: white;
            padding: 12px;
            border-radius: var(--border-radius);
            margin-top: 15px;
            box-shadow: var(--shadow-sm);
            animation: fade-in 0.3s ease-in-out;
        }

        @keyframes fade-in {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .file-preview i {
            color: var(--primary-600);
            font-size: 20px;
        }

        .file-name {
            font-size: 0.9rem;
            color: var(--gray-700);
            flex: 1;
            word-break: break-all;
        }

        .file-remove {
            color: var(--danger-600);
            cursor: pointer;
            transition: var(--transition);
        }

        .file-remove:hover {
            transform: scale(1.2);
        }

        .required-message {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            padding: 8px 12px;
            background-color: var(--danger-50);
            border: 1px solid var(--danger-200);
            border-radius: var(--border-radius);
            color: var(--danger-600);
            font-size: 0.85rem;
            animation: pulse-error 2s infinite;
        }

        @keyframes pulse-error {
            0%, 100% {
                background-color: var(--danger-50);
            }
            50% {
                background-color: var(--danger-100);
            }
        }

        .required-message i {
            margin-right: 6px;
            font-size: 1rem;
        }

        /* Success Message */
        .success-message {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 10px;
            padding: 8px 12px;
            border-radius: var(--border-radius);
            color: var(--success-600);
            font-size: 0.85rem;
            animation: fade-in 0.5s ease-in-out;
        }

        .success-message i {
            margin-right: 6px;
            font-size: 1rem;
        }

        /* Submit Button */
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
            overflow: hidden;
            box-shadow: 0 8px 20px var(--primary-glow);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            margin-top: 10px;
            z-index: 1;
        }

        .submit-btn i {
            margin-right: 8px;
            position: relative;
            z-index: 2;
            transition: var(--transition);
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

        /* Input Row */
        .input-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .input-field {
            flex: 1;
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
            .banking-card {
                padding: 30px;
            }

            .banking-header h1 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 480px) {
            .banking-card {
                padding: 25px;
            }

            .banking-header h1 {
                font-size: 1.6rem;
            }

            .input-group input {
                padding: 12px 14px;
                padding-left: 40px;
            }

            .submit-btn {
                padding: 14px;
            }
            
            .input-row {
                flex-direction: column;
                gap: 10px;
            }
        }

        .hidden {
            display: none;
        }

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
                <img src="../imgs/logo.png" alt="ConsultPro Logo" />
            </div>
        </a>
        
        <div class="banking-card-wrapper">
            <div class="banking-card">
                <div class="card-glass"></div>
                <div class="card-corner corner-top-left"></div>
                <div class="card-corner corner-top-right"></div>
                <div class="card-corner corner-bottom-left"></div>
                <div class="card-corner corner-bottom-right"></div>
                
                <div class="banking-header">
                    <div class="header-badge">Secure Information</div>
                    <h1>Banking Information</h1>
                    <p>Complete your expert profile</p>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" id="bankingForm">
                    <?php if (!empty($error_message)): ?>
                        <div class="error-message"><?php echo $error_message; ?></div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <div class="input-row">
                            <div class="input-field">
                                <label for="ccp">CCP</label>
                                <div class="input-group">
                                    <input type="number" id="ccp" name="ccp" placeholder="Enter CCP" value="<?php echo htmlspecialchars($ccp); ?>" class="<?php echo (!empty($ccp_err)) ? 'input-error' : ''; ?>" required>
                                    <i class="fas fa-credit-card input-icon"></i>
                                    <i class="fas fa-check-circle validation-icon valid"></i>
                                    <i class="fas fa-times-circle validation-icon invalid"></i>
                                </div>
                                <?php if (!empty($ccp_err)): ?>
                                    <div class="error-message"><?php echo $ccp_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="input-field">
                                <label for="ccp_key">CCP Key</label>
                                <div class="input-group">
                                    <input type="number" id="ccp_key" name="ccp_key" placeholder="Enter CCP Key" value="<?php echo htmlspecialchars($ccp_key); ?>" class="<?php echo (!empty($ccp_key_err)) ? 'input-error' : ''; ?>" required>
                                    <i class="fas fa-key input-icon"></i>
                                    <i class="fas fa-check-circle validation-icon valid"></i>
                                    <i class="fas fa-times-circle validation-icon invalid"></i>
                                </div>
                                <?php if (!empty($ccp_key_err)): ?>
                                    <div class="error-message"><?php echo $ccp_key_err; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
        <label for="check">Crossed Check</label>
        <div class="upload-section">
            <span class="upload-label">Crossed Check Document</span>
            <input type="file" id="check" name="check" class="hidden" accept=".pdf,.jpg,.jpeg,.png" <?php echo empty($check_file) ? 'required' : ''; ?>>
            <button type="button" class="upload-button" onclick="document.getElementById('check').click()">
                <span class="plus-icon"><i class="fas fa-plus"></i></span>
            </button>
            <div id="file-preview" class="<?php echo empty($check_file) ? 'hidden' : ''; ?> file-preview">
                <?php if (!empty($check_file)): ?>
                    <i class="fas fa-file-upload"></i>
                    <span id="file-name" class="file-name"><?php echo basename($check_file); ?></span>
                    <i class="fas fa-times file-remove" onclick="removeFile()"></i>
                <?php else: ?>
                    <i class="fas fa-file-upload"></i>
                    <span id="file-name" class="file-name"></span>
                    <i class="fas fa-times file-remove" onclick="removeFile()"></i>
                <?php endif; ?>
            </div>
            <div id="upload-message" class="upload-message <?php echo !empty($check_file) ? 'hidden' : ''; ?>">
                Upload your crossed check document (PDF, JPG, PNG)
            </div>
            <?php if (empty($check_file)): ?>
                <div id="check-required-message" style="display:none;" class="required-message">
                    <i class="fas fa-exclamation-circle"></i>
                    Crossed check upload is required to complete your profile
                </div>
            <?php endif; ?>
            <?php if (!empty($check_err)): ?>
                <div class="error-message"><?php echo $check_err; ?></div>
            <?php endif; ?>
            <?php if ($upload_success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    File uploaded successfully!
                </div>
            <?php endif; ?>
        </div>
    </div>

                    <div class="form-group">
                        <div class="input-row">
                            <div class="input-field">
                                <label for="minutes">Minutes</label>
                                <div class="input-group">
                                    <input type="number" id="minutes" name="minutes" placeholder="Enter minutes" value="<?php echo htmlspecialchars($minutes); ?>" class="<?php echo (!empty($minutes_err)) ? 'input-error' : ''; ?>" required>
                                    <i class="fas fa-clock input-icon"></i>
                                    <i class="fas fa-check-circle validation-icon valid"></i>
                                    <i class="fas fa-times-circle validation-icon invalid"></i>
                                </div>
                                <?php if (!empty($minutes_err)): ?>
                                    <div class="error-message"><?php echo $minutes_err; ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="input-field">
                                <label for="price">Price</label>
                                <div class="input-group">
                                    <input type="number" id="price" name="price" placeholder="Enter price" value="<?php echo htmlspecialchars($price); ?>" class="<?php echo (!empty($price_err)) ? 'input-error' : ''; ?>" required>
                                    <i class="fas fa-dollar-sign input-icon"></i>
                                    <i class="fas fa-check-circle validation-icon valid"></i>
                                    <i class="fas fa-times-circle validation-icon invalid"></i>
                                </div>
                                <?php if (!empty($price_err)): ?>
                                    <div class="error-message"><?php echo $price_err; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="next-btn-wrapper">
                    <button type="submit" class="submit-btn" id="submitBtn">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
                    
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Hide preloader after page loads
            setTimeout(function() {
                document.querySelector(".preloader").classList.add("fade-out");
                setTimeout(function() {
                    document.querySelector(".preloader").style.display = "none";
                }, 500);
            }, 1000);
            
            const ccpInput = document.getElementById('ccp');
            const keyInput = document.getElementById('ccp_key');
            const minutesInput = document.getElementById('minutes');
            const priceInput = document.getElementById('price');
            const checkInput = document.getElementById('check');
            const form = document.getElementById('bankingForm');
            
            // Function to add validation icons
            function showValidationIcon(inputElement, isValid) {
                const inputGroup = inputElement.parentElement;
                inputGroup.classList.remove('is-valid', 'is-invalid');
                
                if (isValid === true) {
                    inputGroup.classList.add('is-valid');
                } else if (isValid === false) {
                    inputGroup.classList.add('is-invalid');
                }
            }
            
            // Validate CCP (no negative numbers)
            ccpInput.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
                
                if (this.value === '' || this.value === '0') {
                    this.classList.add('input-error');
                    showValidationIcon(this, false);
                    
                    let errorMessage = this.parentElement.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        this.parentElement.after(errorMessage);
                    }
                    
                    errorMessage.textContent = 'CCP is required and must be a positive number';
                } else {
                    this.classList.remove('input-error');
                    showValidationIcon(this, true);
                    
                    const errorMessage = this.parentElement.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('error-message')) {
                        errorMessage.textContent = '';
                    }
                }
            });
            
            // Validate CCP Key (no negative numbers)
            keyInput.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
                
                if (this.value === '' || this.value === '0') {
                    this.classList.add('input-error');
                    showValidationIcon(this, false);
                    
                    let errorMessage = this.parentElement.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        this.parentElement.after(errorMessage);
                    }
                    
                    errorMessage.textContent = 'CCP Key is required and must be a positive number';
                } else {
                    this.classList.remove('input-error');
                    showValidationIcon(this, true);
                    
                    const errorMessage = this.parentElement.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('error-message')) {
                        errorMessage.textContent = '';
                    }
                }
            });
            
            // Validate Minutes (no negative numbers)
            minutesInput.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
                
                if (this.value > 1440) {
                    this.value = 1440;
                }
                
                if (this.value === '' || this.value === '0') {
                    this.classList.add('input-error');
                    showValidationIcon(this, false);
                    
                    let errorMessage = this.parentElement.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        this.parentElement.after(errorMessage);
                    }
                    
                    errorMessage.textContent = 'Minutes is required and must be a positive number';
                } else {
                    this.classList.remove('input-error');
                    showValidationIcon(this, true);
                    
                    const errorMessage = this.parentElement.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('error-message')) {
                        errorMessage.textContent = '';
                    }
                }
            });
            
            // Validate Price (no negative numbers)
            priceInput.addEventListener('input', function() {
                if (this.value < 0) {
                    this.value = 0;
                }
                
                if (this.value === '' || this.value === '0') {
                    this.classList.add('input-error');
                    showValidationIcon(this, false);
                    
                    let errorMessage = this.parentElement.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        this.parentElement.after(errorMessage);
                    }
                    
                    errorMessage.textContent = 'Price is required and must be a positive number';
                } else {
                    this.classList.remove('input-error');
                    showValidationIcon(this, true);
                    
                    const errorMessage = this.parentElement.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('error-message')) {
                        errorMessage.textContent = '';
                    }
                }
            });
            
            // Handle file upload
            checkInput.addEventListener('change', function(e) {
        const fileName = e.target.files[0]?.name;
        const filePreview = document.getElementById('file-preview');
        const uploadMessage = document.getElementById('upload-message');
        const requiredMessage = document.getElementById('check-required-message');
        
        if (fileName) {
            document.getElementById('file-name').textContent = fileName;
            filePreview.classList.remove('hidden');
            uploadMessage.classList.add('hidden');
            requiredMessage?.classList.add('hidden');
            
            // Show validation icon
            showValidationIcon(this, true);
            
            // Show success message
            const successMessage = document.createElement('div');
            successMessage.className = 'success-message';
            successMessage.innerHTML = '<i class="fas fa-check-circle"></i> File uploaded successfully!';
            
            // Remove any existing success message
            const existingSuccess = document.querySelector('.success-message');
            if (existingSuccess) {
                existingSuccess.remove();
            }
            
            // Add the success message after the file preview
            filePreview.parentNode.insertBefore(successMessage, filePreview.nextSibling);
            
            // Remove the success message after 3 seconds
            setTimeout(() => {
                successMessage.remove();
            }, 3000);
        } else {
            filePreview.classList.add('hidden');
            uploadMessage.classList.remove('hidden');
            requiredMessage?.classList.remove('hidden');
            
            // Show validation icon
            showValidationIcon(this, false);
        }
    });
    
    // Remove file function
    window.removeFile = function() {
        document.getElementById('check').value = '';
        document.getElementById('file-preview').classList.add('hidden');
        document.getElementById('upload-message').classList.remove('hidden');
        document.getElementById('check-required-message')?.classList.remove('hidden');
        
        // Remove any existing success message
        const existingSuccess = document.querySelector('.success-message');
        if (existingSuccess) {
            existingSuccess.remove();
        }
        
        // Show validation icon
        showValidationIcon(document.getElementById('check'), false);
    };
            
            // Validation before form submission
            form.addEventListener('submit', function(event) {
                let hasError = false;
                
                // Validate CCP
                if (ccpInput.value === '' || ccpInput.value === '0') {
                    ccpInput.classList.add('input-error');
                    showValidationIcon(ccpInput, false);
                    hasError = true;
                }
                
                // Validate CCP Key
                if (keyInput.value === '' || keyInput.value === '0') {
                    keyInput.classList.add('input-error');
                    showValidationIcon(keyInput, false);
                    hasError = true;
                }
                
                // Validate Minutes
                if (minutesInput.value === '' || minutesInput.value === '0') {
                    minutesInput.classList.add('input-error');
                    showValidationIcon(minutesInput, false);
                    hasError = true;
                }
                
                // Validate Price
                if (priceInput.value === '' || priceInput.value === '0') {
                    priceInput.classList.add('input-error');
                    showValidationIcon(priceInput, false);
                    hasError = true;
                }
                
                // Validate File Upload
                if (checkInput.value === '' && !document.getElementById('file-name').textContent) {
                    document.getElementById('check-required-message')?.classList.remove('hidden');
                    hasError = true;
                }
                
                if (hasError) {
                    event.preventDefault();
                }
            });
            
            // Initialize validation icons for inputs with values
            if (ccpInput.value && ccpInput.value !== '0') {
                showValidationIcon(ccpInput, true);
            }
            
            if (keyInput.value && keyInput.value !== '0') {
                showValidationIcon(keyInput, true);
            }
            
            if (minutesInput.value && minutesInput.value !== '0') {
                showValidationIcon(minutesInput, true);
            }
            
            if (priceInput.value && priceInput.value !== '0') {
                showValidationIcon(priceInput, true);
            }
            
            // Add animated background elements
            document.addEventListener('mousemove', function(e) {
                const shapes = document.querySelectorAll('.shape');
                const particles = document.querySelectorAll('.particle');
                const bankingCard = document.querySelector('.banking-card');
                
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
    </script>
</body>
</html>
