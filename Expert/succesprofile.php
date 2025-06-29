<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in, redirect to login page
    header("Location: ../config/logout.php");
    exit();
}


$error_message = "";
$success_message = "";
$user_id = $_SESSION["user_id"];


// Include database connection
require_once '../config/config.php';

// Process form submission when OK button is clicked
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        // Update the profile status to "pending_review" in the database
        $sql = "UPDATE expert_profiledetails SET status = 'pending_review', submitted_at = NOW() WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        
        // Check if prepare statement was successful
        if ($stmt === false) {
            throw new Exception("Error preparing statement: " . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            // Check if admin_notifications table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'admin_notifications'");
            
            if ($table_check && $table_check->num_rows > 0) {
                // Get profile ID
                $profile_query = "SELECT id FROM expert_profiledetails WHERE user_id = ?";
                $profile_stmt = $conn->prepare($profile_query);
                
                if ($profile_stmt === false) {
                    throw new Exception("Error preparing profile query: " . $conn->error);
                }
                
                $profile_stmt->bind_param("i", $user_id);
                $profile_stmt->execute();
                $profile_result = $profile_stmt->get_result();
                
                if ($profile_result && $profile_result->num_rows > 0) {
                    $profile_id = $profile_result->fetch_assoc()['id'];
                    
                    // Insert notification for admin
                    $notification_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message, created_at) 
                                        VALUES (?, ?, 'new_profile', 'A new expert profile has been submitted for review', NOW())";
                    $notification_stmt = $conn->prepare($notification_sql);
                    
                    if ($notification_stmt === false) {
                        throw new Exception("Error preparing notification statement: " . $conn->error);
                    }
                    
                    $notification_stmt->bind_param("ii", $user_id, $profile_id);
                    $notification_stmt->execute();
                }
            }
            
            // Destroy the session
            session_destroy();
            
            // Redirect to login page
            header("location: ../config/logout.php");
            exit;
        } else {
            $error_message = "Error updating profile status: " . $stmt->error;
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Submitted - ConsultPro</title>
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

        /* Success Card with 3D and Glassmorphism Effects */
        .success-card-wrapper {
            position: relative;
            width: 100%;
            max-width: 550px;
            perspective: 1000px;
        }

        .success-card {
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

        /* Success Content Styling */
        .success-content {
            text-align: center;
            position: relative;
            z-index: 2;
        }

        .success-icon-wrapper {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 30px;
        }

        .success-icon-bg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--success-100), var(--success-500));
            border-radius: 50%;
            opacity: 0.2;
            animation: pulse-success 3s infinite ease-in-out;
        }

        @keyframes pulse-success {
            0%, 100% {
                transform: scale(1);
                opacity: 0.2;
            }
            50% {
                transform: scale(1.3);
                opacity: 0.4;
            }
        }

        .success-icon {
            position: relative;
            z-index: 2;
            font-size: 60px;
            color: var(--success-500);
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            animation: bounce-success 2s infinite ease-in-out;
        }

        @keyframes bounce-success {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .success-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 10px;
            background: linear-gradient(to right, var(--primary-600), var(--success-600));
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

        .success-subtitle {
            color: var(--gray-600);
            font-size: 1.2rem;
            margin-bottom: 30px;
            position: relative;
            display: inline-block;
        }

        /* Animated Loader */
        .loader-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 30px auto;
        }

        .loader-circle {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 4px solid transparent;
            border-top-color: var(--primary-500);
            animation: spin 2s linear infinite;
        }

        .loader-circle:nth-child(2) {
            border-top-color: transparent;
            border-right-color: var(--secondary-500);
            animation-duration: 1.8s;
        }

        .loader-circle:nth-child(3) {
            border-top-color: transparent;
            border-right-color: transparent;
            border-bottom-color: var(--accent-500);
            animation-duration: 1.6s;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        .loader-dots {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .loader-dot {
            width: 10px;
            height: 10px;
            margin: 0 5px;
            border-radius: 50%;
            background-color: var(--primary-500);
            animation: pulse-dot 1.5s infinite ease-in-out;
        }

        .loader-dot:nth-child(2) {
            animation-delay: 0.2s;
            background-color: var(--secondary-500);
        }

        .loader-dot:nth-child(3) {
            animation-delay: 0.4s;
            background-color: var(--accent-500);
        }

        @keyframes pulse-dot {
            0%, 100% {
                transform: scale(0.8);
                opacity: 0.6;
            }
            50% {
                transform: scale(1.2);
                opacity: 1;
            }
        }

        .wait-title {
            font-family: 'Montserrat', sans-serif;
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--primary-700);
            margin-bottom: 15px;
            position: relative;
            display: inline-block;
        }

        .wait-message {
            color: var(--gray-600);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 30px;
            padding: 0 20px;
        }

        /* OK Button Styling */
        .ok-btn-wrapper {
            position: relative;
            width: 100%;
            margin-top: 20px;
            overflow: hidden;
            border-radius: var(--border-radius);
            z-index: 2;
        }

        .ok-button {
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

        .ok-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }

        .ok-button:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 15px 30px var(--primary-glow), 0 0 15px var(--primary-glow);
            letter-spacing: 1px;
        }

        .ok-button:hover::before {
            left: 100%;
        }

        .ok-button:active {
            transform: translateY(-3px) scale(0.98);
        }

        /* Error Message Styling */
        .error-message {
            color: var(--danger-600);
            background-color: var(--danger-50);
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: var(--box-shadow);
        }

        .error-message i {
            font-size: 1.2rem;
        }

        /* Confetti Animation */
        .confetti-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 100;
            overflow: hidden;
        }

        .confetti {
            position: absolute;
            top: -20px;
            width: 10px;
            height: 10px;
            background-color: var(--primary-500);
            opacity: 0.8;
            animation: confetti-fall 5s linear forwards;
        }

        @keyframes confetti-fall {
            0% {
                transform: translateY(0) rotate(0deg) scale(1);
            }
            25% {
                transform: translateY(25vh) rotate(90deg) scale(0.9);
            }
            50% {
                transform: translateY(50vh) rotate(180deg) scale(1.1);
            }
            75% {
                transform: translateY(75vh) rotate(270deg) scale(0.9);
            }
            100% {
                transform: translateY(100vh) rotate(360deg) scale(1);
                opacity: 0;
            }
        }

        /* Page Transition */
        .page-transition {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: var(--primary-500);
            z-index: 1000;
            transform: translateY(100%);
            transition: transform 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            pointer-events: none;
        }

        .page-transition.active {
            transform: translateY(0);
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
            .success-card {
                padding: 30px;
            }

            .success-title {
                font-size: 1.8rem;
            }

            .wait-title {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .success-card {
                padding: 25px;
            }

            .success-title {
                font-size: 1.6rem;
            }

            .wait-title {
                font-size: 1.3rem;
            }

            .success-icon {
                font-size: 50px;
            }

            .loader-container {
                width: 100px;
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
        
        <div class="success-card-wrapper">
            <div class="success-card">
                <div class="card-glass"></div>
                <div class="card-decoration decoration-1"></div>
                <div class="card-decoration decoration-2"></div>
                <div class="card-corner corner-top-left"></div>
                <div class="card-corner corner-top-right"></div>
                <div class="card-corner corner-bottom-left"></div>
                <div class="card-corner corner-bottom-right"></div>
                
                <div class="success-content">
                    <?php if (!empty($error_message)): ?>
                        <div class="error-message">
                            <i class="fas fa-exclamation-circle"></i>
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="success-icon-wrapper">
                        <div class="success-icon-bg"></div>
                        <div class="success-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    
                    <h1 class="success-title">Congratulations!</h1>
                    <p class="success-subtitle">Your account is created</p>
                    
                    <div class="loader-container">
                        <div class="loader-circle"></div>
                        <div class="loader-circle"></div>
                        <div class="loader-circle"></div>
                        <div class="loader-dots">
                            <div class="loader-dot"></div>
                            <div class="loader-dot"></div>
                            <div class="loader-dot"></div>
                        </div>
                    </div>
                    
                    <h2 class="wait-title">Please wait !!!!!</h2>
                    <p class="wait-message">
                        I will wait for the review of the students and contact you as soon as possible.
                    </p>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                        <div class="ok-btn-wrapper">
                            <button type="submit" class="ok-button">Send</button>
                        </div>
                    </form>
                </div>
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
