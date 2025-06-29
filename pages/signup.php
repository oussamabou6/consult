<?php
// Start session
session_start();

// Include database connection
require_once "../config/config.php";

// Define variables and initialize with empty values
$full_name = $email = $password = $confirm_password = $role = "";
$full_name_err = $email_err = $password_err = $confirm_password_err = $role_err = "";

// Processing form data when form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate full name
    if (empty(trim($_POST["full_name"]))) {
        $full_name_err = "Please enter your full name.";
    } elseif (strlen(trim($_POST["full_name"])) < 3) {
        $full_name_err = "Full name must have more than 3 characters.";
    } else {
        $full_name = trim($_POST["full_name"]);
    }
    
    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } else {
        // Prepare a select statement
        $sql = "SELECT id FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("s", $param_email);
            
            // Set parameters
            $param_email = trim($_POST["email"]);
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Store result
                $stmt->store_result();
                
                if ($stmt->num_rows > 0) {
                    $email_err = "This email is already taken.";
                } else {
                    $email = trim($_POST["email"]);
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Veuillez entrer un mot de passe.";     
    } elseif (strlen(trim($_POST["password"])) < 8) {
        $password_err = "Le mot de passe doit contenir au moins 8 caractères.";
    } elseif (!preg_match('/[A-Z]/', $_POST["password"])) {
        $password_err = "Le mot de passe doit contenir au moins une lettre majuscule.";
    } elseif (!preg_match('/[a-z]/', $_POST["password"])) {
        $password_err = "Le mot de passe doit contenir au moins une lettre minuscule.";
    } elseif (!preg_match('/[0-9]/', $_POST["password"])) {
        $password_err = "Le mot de passe doit contenir au moins un chiffre.";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $_POST["password"])) {
        $password_err = "Le mot de passe doit contenir au moins un caractère spécial.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm password.";     
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Passwords do not match.";
        }
    }
    
    // Validate role selection
    if (empty(trim($_POST["role"]))) {
        $role_err = "Please select a role.";
    } else {
        $role = trim($_POST["role"]);
        // Ensure role is either 'client' or 'expert'
        if ($role != "client" && $role != "expert") {
            $role_err = "Invalid role selection.";
        }
    }
    
    // Check input errors before inserting in database
    if (empty($full_name_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($role_err)) {
        
        // Prepare an insert statement for users table
        $sql = "INSERT INTO users (full_name, email, password, role, status, balance) VALUES (?, ?, ?, ?, 'Offline', 0)";
         
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssss", $param_full_name, $param_email, $param_password, $param_role);
            
            // Set parameters
            $param_full_name = $full_name;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Creates a password hash
            $param_role = $role;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Get the newly created user ID
                $user_id = $conn->insert_id;
                
                // Store data in session variables
                $_SESSION["user_id"] = $user_id;
                $_SESSION["email"] = $email;
                $_SESSION["full_name"] = $full_name;
                $_SESSION["user_role"] = $role;
                
                // Redirect based on role
                if ($role == "client") {
                    header("location: profile.php");
                } else {
                    header("location: profile.php"); // Expert profile
                }
                exit;
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }
    
    // Close connection
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | ConsultPro</title>
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

        /* Advanced Signup Card with 3D and Glassmorphism Effects */
        .signup-card-wrapper {
            position: relative;
            width: 100%;
            max-width: 550px;
            perspective: 1000px;
        }

        .signup-card {
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

        /* Advanced Signup Header with Animated Elements */
        .signup-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .signup-header h1 {
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

        .signup-header h1::after {
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

        .signup-header p {
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


        /* Role Selection */
        .role-selection {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .role-option {
            flex: 1;
            position: relative;
        }

        .role-option input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }

        .role-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.7);
            border: 2px solid var(--gray-300);
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition-bounce);
            height: 100%;
            text-align: center;
            box-shadow: var(--shadow-sm);
            transform: translateZ(0);
        }

        .role-option input[type="radio"]:checked + label {
            border-color: var(--primary-600);
            background-color: var(--primary-50);
            box-shadow: 0 0 0 4px var(--primary-100), 0 10px 20px rgba(0, 0, 0, 0.1);
            transform: translateY(-5px) translateZ(10px);
        }

        .role-option input[type="radio"]:focus + label {
            box-shadow: 0 0 0 4px var(--primary-100);
        }

        .role-icon {
            font-size: 24px;
            margin-bottom: 10px;
            color: var(--gray-600);
            transition: var(--transition);
        }

        .role-option input[type="radio"]:checked + label .role-icon {
            color: var(--primary-600);
            transform: scale(1.2);
        }

        .role-title {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 5px;
        }

        .role-description {
            font-size: 0.8rem;
            color: var(--gray-500);
        }

        /* Password Strength */
        .password-strength {
            margin-top: 8px;
        }

        .strength-meter {
            height: 4px;
            background-color: var(--gray-200);
            border-radius: 2px;
            position: relative;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .strength-meter-fill {
            height: 100%;
            border-radius: 2px;
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .strength-text {
            font-size: 0.75rem;
            font-weight: 500;
        }

        /* Password Requirements */
        .password-requirements {
            margin-top: 10px;
            font-size: 0.85rem;
            color: var(--gray-600);
        }

        .password-requirements ul {
            list-style-type: none;
            padding-left: 0;
            margin-top: 5px;
        }

        .password-requirements li {
            margin-bottom: 3px;
            display: flex;
            align-items: center;
            color: var(--gray-600);
            font-size: 0.85rem;
        }
        
        .password-requirements li i {
            margin-right: 8px;
            font-size: 0.7rem;
            color: var(--gray-400);
        }
        
        .password-requirements li.valid {
            color: var(--success-600);
        }
        
        .password-requirements li.invalid {
            color: var(--gray-600);
        }
        
        .password-requirements li.valid i {
            color: var(--success-600);
        }
        
        .password-requirements li.invalid i {
            color: var(--gray-400);
        }

    /* Terms Checkbox */
    .terms-checkbox {
        display: flex;
        align-items: flex-start;
        margin-bottom: 10px;
        position: relative;
    }
    
    .terms-checkbox input[type="checkbox"] {
        appearance: none;
        -webkit-appearance: none;
        width: 20px;
        height: 20px;
        border: 2px solid var(--gray-300);
        border-radius: var(--border-radius-sm);
        margin-right: 10px;
        position: relative;
        cursor: pointer;
        transition: var(--transition);
        flex-shrink: 0;
        margin-top: 2px;
    }
    
    .terms-checkbox input[type="checkbox"]:checked {
        background-color: var(--primary-600);
        border-color: var(--primary-600);
    }
    
    .terms-checkbox input[type="checkbox"]:checked::after {
        content: '\f00c';
        font-family: 'Font Awesome 5 Free';
        font-weight: 900;
        color: white;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 12px;
    }
    
    .terms-checkbox input[type="checkbox"]:focus {
        outline: none;
        box-shadow: 0 0 0 3px var(--primary-100);
    }
    
    .terms-checkbox label {
        font-size: 0.9rem;
        color: var(--gray-600);
        cursor: pointer;
        user-select: none;
    }
    
    .terms-checkbox label a {
        color: var(--primary-600);
        text-decoration: none;
        font-weight: 600;
        transition: var(--transition);
    }
    
    .terms-checkbox label a:hover {
        color: var(--secondary-600);
        text-decoration: underline;
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

        /* Login Link */
        .login-link {
            text-align: center;
            margin-top: 20px;
            color: var(--gray-600);
            font-size: 0.95rem;
        }

        .login-link a {
            color: var(--primary-600);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition-bounce);
            position: relative;
            padding: 3px 0;
            display: inline-block;
        }

        .login-link a i {
            margin-right: 5px;
            font-size: 0.9em;
            transition: var(--transition);
        }

        .login-link a:hover {
            color: var(--secondary-600);
            transform: translateY(-3px);
            text-shadow: 0 3px 10px var(--primary-glow);
        }

        .login-link a:hover i {
            transform: translateX(-3px);
        }

        .login-link a::before {
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

        .login-link a:hover::before {
            transform: scaleX(1);
            transform-origin: left;
        }

        /* Toggle Password Visibility */
        .password-toggle {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray-400);
            font-size: 18px;
            transition: var(--transition);
        }

        .input-group:hover .password-toggle {
            color: var(--primary-600);
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
            .signup-card {
                padding: 30px;
            }

            .signup-header h1 {
                font-size: 1.8rem;
            }
        }

        @media (max-width: 480px) {
            .signup-card {
                padding: 25px;
            }

            .signup-header h1 {
                font-size: 1.6rem;
            }

            .input-group input {
                padding: 12px 14px;
                padding-left: 40px;
            }

            .submit-btn {
                padding: 14px;
            }
            
            .role-selection {
                flex-direction: column;
                gap: 10px;
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
                <img src="../imgs/logo.png" alt="ConsultPro Logo" />
            </div>
        </a>
        
        <div class="signup-card-wrapper">
            <div class="signup-card">
                <div class="card-glass"></div>
                <div class="card-decoration decoration-1"></div>
                <div class="card-decoration decoration-2"></div>
                <div class="card-corner corner-top-left"></div>
                <div class="card-corner corner-top-right"></div>
                <div class="card-corner corner-bottom-left"></div>
                <div class="card-corner corner-bottom-right"></div>
                
                <div class="signup-header">
                    <div class="header-badge">Secure Registration</div>
                    <h1>Create Your Account</h1>
                    <p>Join our community today</p>
                </div>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" id="signupForm">
                    <!-- Role Selection -->
                    <div class="form-group">
                        <label>I want to join as:</label>
                        <div class="role-selection">
                            <div class="role-option">
                                <input type="radio" id="role-client" name="role" value="client" <?php echo (isset($_POST['role']) && $_POST['role'] == 'client') ? 'checked' : ''; ?>>
                                <label for="role-client">
                                    <i class="fas fa-user role-icon"></i>
                                    <div class="role-title">Client</div>
                                    <div class="role-description">I need expert advice</div>
                                </label>
                            </div>
                            <div class="role-option">
                                <input type="radio" id="role-expert" name="role" value="expert" <?php echo (isset($_POST['role']) && $_POST['role'] == 'expert') ? 'checked' : ''; ?>>
                                <label for="role-expert">
                                    <i class="fas fa-user-tie role-icon"></i>
                                    <div class="role-title">Expert</div>
                                    <div class="role-description">I provide consultations</div>
                                </label>
                            </div>
                        </div>
                        <?php if (!empty($role_err)): ?>
                            <div class="error-message"><?php echo $role_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="full_name">Full Name</label>
                        <div class="input-group">
                            <input type="text" id="full_name" name="full_name" placeholder="Enter your full name" value="<?php echo $full_name; ?>" class="<?php echo (!empty($full_name_err)) ? 'input-error' : ''; ?>" required>
                            <i class="fas fa-user input-icon"></i>
                            <i class="fas fa-check-circle validation-icon valid"></i>
                            <i class="fas fa-times-circle validation-icon invalid"></i>
                        </div>
                        <?php if (!empty($full_name_err)): ?>
                            <div class="error-message"><?php echo $full_name_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <div class="input-group">
                            <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo $email; ?>" class="<?php echo (!empty($email_err)) ? 'input-error' : ''; ?>" required>
                            <i class="fas fa-envelope input-icon"></i>
                            <i class="fas fa-check-circle validation-icon valid"></i>
                            <i class="fas fa-times-circle validation-icon invalid"></i>
                        </div>
                        <?php if (!empty($email_err)): ?>
                            <div class="error-message"><?php echo $email_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="input-group">
                            <input type="password" id="password" name="password" placeholder="Enter your password" class="<?php echo (!empty($password_err)) ? 'input-error' : ''; ?>" required>
                            <i class="fas fa-lock input-icon"></i>
                            <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                            <i class="fas fa-check-circle validation-icon valid"></i>
                            <i class="fas fa-times-circle validation-icon invalid"></i>
                        </div>
                        <div class="password-strength">
                            <div class="strength-meter">
                                <div class="strength-meter-fill" id="strength-meter-fill"></div>
                            </div>
                            <div class="strength-text" id="strength-text">Entrez un mot de passe</div>
                            <div class="password-requirements">
                                <ul>
                                    <li id="length"><i class="fas fa-circle"></i> At least 8 characters</li>
                                    <li id="uppercase"><i class="fas fa-circle"></i> At least one uppercase letter</li>
                                    <li id="lowercase"><i class="fas fa-circle"></i> At least one lowercase letter</li>
                                    <li id="number"><i class="fas fa-circle"></i> At least one number</li>
                                    <li id="special"><i class="fas fa-circle"></i> At least one special character</li>
                                </ul>
                            </div>
                        </div>
                        <?php if (!empty($password_err)): ?>
                            <div class="error-message"><?php echo $password_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" class="<?php echo (!empty($confirm_password_err)) ? 'input-error' : ''; ?>" required>
                            <i class="fas fa-lock input-icon"></i>
                            <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                            <i class="fas fa-check-circle validation-icon valid"></i>
                            <i class="fas fa-times-circle validation-icon invalid"></i>
                        </div>
                        <?php if (!empty($confirm_password_err)): ?>
                            <div class="error-message"><?php echo $confirm_password_err; ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <div class="terms-checkbox">
                            <input type="checkbox" id="terms" name="terms" required>
                            <label for="terms">I agree to the <a href="../terms.php">Terms of Service</a> </label>
                        </div>
                        <div class="error-message" id="terms-error"></div>
                    </div>

                    <button type="submit" class="submit-btn" id="submitBtn">
                        <i class="fas fa-user-plus"></i> Create Account
                    </button>
                    
                    <div class="login-link">
                        Already have an account? <a href="login.php"><i class="fas fa-sign-in-alt"></i> Sign In</a>
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
            
            const fullNameInput = document.getElementById('full_name');
            const passwordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const strengthMeterFill = document.getElementById('strength-meter-fill');
            const strengthText = document.getElementById('strength-text');
            const form = document.getElementById('signupForm');
            const submitBtn = document.getElementById('submitBtn');
            const roleClient = document.getElementById('role-client');
            const roleExpert = document.getElementById('role-expert');
            const emailInput = document.getElementById('email');
            
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
        
            // Toggle password visibility
            document.getElementById('togglePassword').addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        
            document.getElementById('toggleConfirmPassword').addEventListener('click', function() {
                const type = confirmPasswordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                confirmPasswordInput.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        
            // Password requirements elements
            const lengthCheck = document.getElementById('length');
            const uppercaseCheck = document.getElementById('uppercase');
            const lowercaseCheck = document.getElementById('lowercase');
            const numberCheck = document.getElementById('number');
            const specialCheck = document.getElementById('special');
        
            // Validation du nom
            fullNameInput.addEventListener('input', function() {
                if (fullNameInput.value.length < 3) {
                    fullNameInput.classList.add('input-error');
                    showValidationIcon(fullNameInput, false);
                
                    let errorMessage = fullNameInput.parentElement.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        fullNameInput.parentElement.after(errorMessage);
                    }
                    
                    errorMessage.textContent = 'Full name must have more than 3 characters';
                } else {
                    fullNameInput.classList.remove('input-error');
                    showValidationIcon(fullNameInput, true);
                    
                    const errorMessage = fullNameInput.parentElement.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('error-message')) {
                        errorMessage.textContent = '';
                    }
                }
            });
            
            // Email validation in real-time
            emailInput.addEventListener('input', function() {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value)) {
                    emailInput.classList.add('input-error');
                    showValidationIcon(emailInput, false);
                    
                    let errorMessage = emailInput.parentElement.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        emailInput.parentElement.after(errorMessage);
                    }
                    
                    errorMessage.textContent = 'Please enter a valid email address';
                } else {
                    emailInput.classList.remove('input-error');
                    showValidationIcon(emailInput, true);
                    
                    const errorMessage = emailInput.parentElement.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('error-message')) {
                        errorMessage.textContent = '';
                    }
                }
            });
        
            // Validation du mot de passe et affichage de la force
            passwordInput.addEventListener('input', function() {
                const password = passwordInput.value;
                let strength = 0;
                let feedback = '';
                
                // 1. Vérifier la longueur
                const hasLength = password.length >= 8;
                if (hasLength) strength += 25;
                updateRequirement(lengthCheck, hasLength);
                
                // 2. Vérifier les majuscules
                const hasUppercase = /[A-Z]/.test(password);
                if (hasUppercase) strength += 25;
                updateRequirement(uppercaseCheck, hasUppercase);
                
                // 3. Vérifier les minuscules
                const hasLowercase = /[a-z]/.test(password);
                if (hasLowercase) strength += 25;
                updateRequirement(lowercaseCheck, hasLowercase);
                
                // 4. Vérifier les chiffres
                const hasNumber = /[0-9]/.test(password);
                if (hasNumber) strength += 12.5;
                updateRequirement(numberCheck, hasNumber);
                
                // 5. Vérifier les caractères spéciaux
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                if (hasSpecial) strength += 12.5;
                updateRequirement(specialCheck, hasSpecial);
                
               // Mettre à jour l'indicateur de force
                strengthMeterFill.style.width = strength + '%';
                
                // Définir la couleur et le texte en fonction de la force
                if (strength < 25) {
                    strengthMeterFill.style.backgroundColor = '#ef4444';
                    feedback = 'Très faible';
                    showValidationIcon(passwordInput, false);
                } else if (strength < 50) {
                    strengthMeterFill.style.backgroundColor = '#f59e0b';
                    feedback = 'Faible';
                    showValidationIcon(passwordInput, false);
                } else if (strength < 75) {
                    strengthMeterFill.style.backgroundColor = '#f59e0b';
                    feedback = 'Moyen';
                    showValidationIcon(passwordInput, false);
                } else if (strength < 100) {
                    strengthMeterFill.style.backgroundColor = '#10b981';
                    feedback = 'Fort';
                    showValidationIcon(passwordInput, true);
                } else {
                    strengthMeterFill.style.backgroundColor = '#10b981';
                    feedback = 'Très fort';
                    showValidationIcon(passwordInput, true);
                }
                
                strengthText.textContent = feedback;
                
                // Validation des erreurs
                let errorMessage = passwordInput.parentElement.nextElementSibling.nextElementSibling;
                if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                    errorMessage = document.createElement('div');
                    errorMessage.className = 'error-message';
                    passwordInput.parentElement.nextElementSibling.after(errorMessage);
                }
                
                if (!hasLength) {
                    passwordInput.classList.add('input-error');
                    errorMessage.textContent = 'Le mot de passe doit contenir au moins 8 caractères';
                } else if (!hasUppercase) {
                    passwordInput.classList.add('input-error');
                    errorMessage.textContent = 'Le mot de passe doit contenir au moins une lettre majuscule';
                } else if (!hasLowercase) {
                    passwordInput.classList.add('input-error');
                    errorMessage.textContent = 'Le mot de passe doit contenir au moins une lettre minuscule';
                } else if (!hasNumber) {
                    passwordInput.classList.add('input-error');
                    errorMessage.textContent = 'Le mot de passe doit contenir au moins un chiffre';
                } else if (!hasSpecial) {
                    passwordInput.classList.add('input-error');
                    errorMessage.textContent = 'Le mot de passe doit contenir au moins un caractère spécial';
                } else {
                    passwordInput.classList.remove('input-error');
                    errorMessage.textContent = '';
                }
                
                // Vérifier la confirmation du mot de passe
                if (confirmPasswordInput.value && confirmPasswordInput.value !== password) {
                    confirmPasswordInput.classList.add('input-error');
                    showValidationIcon(confirmPasswordInput, false);
                    
                    let confirmErrorMessage = confirmPasswordInput.parentElement.nextElementSibling;
                    if (!confirmErrorMessage || !confirmErrorMessage.classList.contains('error-message')) {
                        confirmErrorMessage = document.createElement('div');
                        confirmErrorMessage.className = 'error-message';
                        confirmPasswordInput.parentElement.after(confirmErrorMessage);
                    }
                    
                    confirmErrorMessage.textContent = 'Passwords do not match';
                } else if (confirmPasswordInput.value) {
                    confirmPasswordInput.classList.remove('input-error');
                    showValidationIcon(confirmPasswordInput, true);
                    
                    const confirmErrorMessage = confirmPasswordInput.parentElement.nextElementSibling;
                    if (confirmErrorMessage && confirmErrorMessage.classList.contains('error-message')) {
                        confirmErrorMessage.textContent = '';
                    }
                }
            });
        
            // Vérifier la confirmation du mot de passe
            confirmPasswordInput.addEventListener('input', function() {
                if (confirmPasswordInput.value !== passwordInput.value) {
                    confirmPasswordInput.classList.add('input-error');
                    showValidationIcon(confirmPasswordInput, false);
                    
                    let errorMessage = confirmPasswordInput.parentElement.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        confirmPasswordInput.parentElement.after(errorMessage);
                    }
                    
                    errorMessage.textContent = 'Passwords do not match';
                } else {
                    confirmPasswordInput.classList.remove('input-error');
                    showValidationIcon(confirmPasswordInput, true);
                    
                    const errorMessage = confirmPasswordInput.parentElement.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('error-message')) {
                        errorMessage.textContent = '';
                    }
                }
            });

            // Fonction pour mettre à jour les indicateurs de validation
            function updateRequirement(element, isValid) {
                if (isValid) {
                    element.classList.add('valid');
                    element.classList.remove('invalid');
                    element.querySelector('i').classList.remove('fa-circle');
                    element.querySelector('i').classList.add('fa-check-circle');
                } else {
                    element.classList.add('invalid');
                    element.classList.remove('valid');
                    element.querySelector('i').classList.remove('fa-check-circle');
                    element.querySelector('i').classList.add('fa-circle');
                }
            }

            // Validation du formulaire avant soumission
            form.addEventListener('submit', function(event) {
                let hasError = false;

                // Vérifier le nom
                if (fullNameInput.value.length <= 3) {
                    hasError = true;
                    showValidationIcon(fullNameInput, false);
                } else {
                    showValidationIcon(fullNameInput, true);
                }
                
                // Vérifier l'email
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value)) {
                    hasError = true;
                    showValidationIcon(emailInput, false);
                } else {
                    showValidationIcon(emailInput, true);
                }

                // Vérifier le mot de passe
                const password = passwordInput.value;
                const hasLength = password.length >= 8;
                const hasUppercase = /[A-Z]/.test(password);
                const hasLowercase = /[a-z]/.test(password);
                const hasNumber = /[0-9]/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

                if (!hasLength || !hasUppercase || !hasLowercase || !hasNumber || !hasSpecial) {
                    hasError = true;
                    showValidationIcon(passwordInput, false);
                } else {
                    showValidationIcon(passwordInput, true);
                }

                // Vérifier la confirmation du mot de passe
                if (passwordInput.value !== confirmPasswordInput.value) {
                    hasError = true;
                    showValidationIcon(confirmPasswordInput, false);
                } else if (confirmPasswordInput.value) {
                    showValidationIcon(confirmPasswordInput, true);
                }
                
                // Vérifier si un rôle a été sélectionné
                if (!roleClient.checked && !roleExpert.checked) {
                    hasError = true;
                    
                    // Afficher un message d'erreur pour le rôle
                    let roleErrorContainer = document.querySelector('.role-selection').nextElementSibling;
                    if (!roleErrorContainer || !roleErrorContainer.classList.contains('error-message')) {
                        roleErrorContainer = document.createElement('div');
                        roleErrorContainer.className = 'error-message';
                        document.querySelector('.role-selection').after(roleErrorContainer);
                    }
                    
                    roleErrorContainer.textContent = 'Please select a role';
                }

                // Verify terms checkbox
                const termsCheckbox = document.getElementById('terms');
                if (!termsCheckbox.checked) {
                    hasError = true;
                    
                    // Show error message for terms
                    let termsErrorContainer = document.getElementById('terms-error');
                    if (termsErrorContainer) {
                        termsErrorContainer.textContent = 'You must agree to the Terms of Service and Privacy Policy';
                    }
                } else {
                    let termsErrorContainer = document.getElementById('terms-error');
                    if (termsErrorContainer) {
                        termsErrorContainer.textContent = '';
                    }
                }

                if (hasError) {
                    event.preventDefault();
                }
            });
        
            // Mettre à jour le texte du bouton en fonction du rôle sélectionné
            function updateSubmitButton() {
                if (roleClient.checked) {
                    submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Join as Client';
                } else if (roleExpert.checked) {
                    submitBtn.innerHTML = '<i class="fas fa-user-tie"></i> Join as Expert';
                } else {
                    submitBtn.innerHTML = '<i class="fas fa-user-plus"></i> Create Account';
                }
            }
        
            // Écouter les changements de rôle
            roleClient.addEventListener('change', updateSubmitButton);
            roleExpert.addEventListener('change', updateSubmitButton);
        
            // Initialiser le bouton au chargement
            updateSubmitButton();
            
            // Add animated background elements
            document.addEventListener('mousemove', function(e) {
                const shapes = document.querySelectorAll('.shape');
                const particles = document.querySelectorAll('.particle');
                const signupCard = document.querySelector('.signup-card');
                
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
            
            // Initialize validation icons for inputs with values
            if (fullNameInput.value.length > 3) {
                showValidationIcon(fullNameInput, true);
            } else if (fullNameInput.value.length > 0) {
                showValidationIcon(fullNameInput, false);
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (emailRegex.test(emailInput.value)) {
                showValidationIcon(emailInput, true);
            } else if (emailInput.value.length > 0) {
                showValidationIcon(emailInput, false);
            }
        });
    </script>
</body>
</html>
