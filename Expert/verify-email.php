<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    header("Location: ../config/logout.php");
    exit();
}

// Check if there's a pending email change
if (!isset($_SESSION['email_verification']) || !isset($_SESSION['email_change_data'])) {
    $_SESSION['error_message'] = "No pending email verification found.";
    header("Location: expert-settings.php");
    exit();
}

// Initialize variables
$error_message = "";
$success_message = "";

// Check for any error message passed from other pages
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST["verify_code"])) {
        // Verify code logic
        if (!isset($_POST["verification_code"]) || empty(trim($_POST["verification_code"]))) {
            $error_message = "Please enter the verification code.";
        } else {
            $entered_code = trim($_POST["verification_code"]);
            
            // Check if verification session data exists
            if (!isset($_SESSION['email_verification']['code']) || 
                !isset($_SESSION['email_verification']['expires']) ||
                !isset($_SESSION['email_verification']['new_email']) ||
                !isset($_SESSION['email_change_data']['full_name']) ||
                !isset($_SESSION['email_change_data']['phone']) ||
                !isset($_SESSION['email_change_data']['address'])) {
                
                $error_message = "Verification session expired. Please try again.";
                unset($_SESSION['email_verification']);
                unset($_SESSION['email_change_data']);
                unset($_SESSION['email_change_pending']);
            } else {
                // Check if code matches and is not expired
                if ($entered_code == $_SESSION['email_verification']['code']) {
                    if (time() <= $_SESSION['email_verification']['expires']) {
                        // Code is valid, update the email
                        require_once '../config/config.php';
                        
                        // Get the data from session
                        $full_name = $_SESSION['email_change_data']['full_name'];
                        $new_email = $_SESSION['email_verification']['new_email'];
                        $phone = $_SESSION['email_change_data']['phone'];
                        $address = $_SESSION['email_change_data']['address'];
                        $user_id = $_SESSION["user_id"];
                        $email = $_SESSION["email"];
                        
                        // Update users table
                        $update_user_sql = "UPDATE users SET full_name = ?, email = ? WHERE id = ?";
                        $update_user_stmt = $conn->prepare($update_user_sql);
                        $update_user_stmt->bind_param("ssi", $full_name, $new_email, $user_id);
                        $update_user_result = $update_user_stmt->execute();
                        
                        // Update user profile
                        $check_profile_sql = "SELECT id FROM user_profiles WHERE user_id = ?";
                        $check_profile_stmt = $conn->prepare($check_profile_sql);
                        $check_profile_stmt->bind_param("i", $user_id);
                        $check_profile_stmt->execute();
                        $check_profile_result = $check_profile_stmt->get_result();
                        
                        if ($check_profile_result->num_rows > 0) {
                            // Update existing profile
                            $update_profile_sql = "UPDATE user_profiles SET phone = ?, address = ? WHERE user_id = ?";
                            $update_profile_stmt = $conn->prepare($update_profile_sql);
                            $update_profile_stmt->bind_param("ssi", $phone, $address, $user_id);
                            $update_profile_result = $update_profile_stmt->execute();
                        } else {
                            // Create new profile
                            $insert_profile_sql = "INSERT INTO user_profiles (user_id, phone, address) VALUES (?, ?, ?)";
                            $insert_profile_stmt = $conn->prepare($insert_profile_sql);
                            $insert_profile_stmt->bind_param("iss", $user_id, $phone, $address);
                            $update_profile_result = $insert_profile_stmt->execute();
                        }
                        
                        if ($update_user_result && isset($update_profile_result) && $update_profile_result) {
                            // Update session data
                            $_SESSION["full_name"] = $full_name;
                            $_SESSION["email"] = $new_email;
                            
                            // Clear verification session
                            unset($_SESSION['email_verification']);
                            unset($_SESSION['email_change_pending']);
                            unset($_SESSION['email_change_data']);
                            
                            $_SESSION['success_message'] = "Email verification successful! Your email has been changed to " . $new_email;

                            // Add this code to send notification email
                            require_once 'utils/mail.php';
                            $updateDetails = [
                                'new_email' => $new_email,
                                'previous_email' => $email
                            ];
                            sendUpdateNotificationEmail($new_email, $full_name, 'email', $updateDetails, $settings['site_name'] ?? 'Consult Pro');
                            
                            header("Location: expert-settings.php");
                            exit();
                        } else {
                            $error_message = "Error updating information after verification.";
                        }
                    } else {
                        $error_message = "Verification code has expired. Please request a new one.";
                        unset($_SESSION['email_verification']);
                        unset($_SESSION['email_change_pending']);
                        unset($_SESSION['email_change_data']);
                    }
                } else {
                    $error_message = "Invalid verification code. Please try again.";
                }
            }
        }
    } elseif (isset($_POST["resend_code"])) {
        // Improved resend code functionality
        require_once '../config/config.php';
        require_once 'utils/mail.php';
        
        // Check if we have the necessary session data
        if (!isset($_SESSION['email_verification']['new_email']) || 
            !isset($_SESSION['email_change_data']['full_name'])) {
            $error_message = "Session data is missing. Please try again from the settings page.";
        } else {
            // Generate new verification code
            $verification_code = rand(100000, 999999);
            $_SESSION['email_verification']['code'] = $verification_code;
            $_SESSION['email_verification']['expires'] = time() + 300; // 5 minutes expiration
            
            // Get site settings for site name
            $settings_sql = "SELECT setting_key, setting_value FROM settings";
            $settings_result = $conn->query($settings_sql);
            $settings = [];
            if ($settings_result && $settings_result->num_rows > 0) {
                while ($row = $settings_result->fetch_assoc()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
            
            // Send verification email
            $email_sent = sendVerificationEmail(
                $_SESSION['email_verification']['new_email'], 
                $_SESSION['email_change_data']['full_name'], 
                $verification_code, 
                $settings['site_name'] ?? 'Consult Pro'
            );
            
            if ($email_sent) {
                $success_message = "A new verification code has been sent to " . htmlspecialchars($_SESSION['email_verification']['new_email']);
                
                // Reset the timer in JavaScript
                echo "<script>timeLeft = 300; updateTimer();</script>";
            } else {
                $error_message = "Failed to send verification email. Please try again.";
            }
        }
    }
}

// If we get here, show the verification form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></title>
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
            display: flex;
            align-items: center;
            justify-content: center;
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
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) scale(1);
            }
            50% {
                transform: translateY(-40px) scale(1.05);
            }
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
        
        /* Verification Card */
        .verification-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            width: 100%;
            max-width: 500px;
            margin: 2rem auto;
            animation: fadeIn 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
        }
        
        .verification-card-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            text-align: center;
            background-color: rgba(248, 250, 252, 0.8);
        }
        
        .verification-card-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .verification-card-subtitle {
            color: var(--text-muted);
            font-size: 0.95rem;
        }
        
        .verification-card-body {
            padding: 2rem;
        }
        
        .verification-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
            display: block;
            text-align: center;
        }
        
        /* Form Styles */
        .form-control {
            height: 50px;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 0.75rem 1rem;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.9);
            letter-spacing: 0.5rem;
            text-align: center;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
        }
        
        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }
        
        .form-text {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }
        
        /* Button Styles */
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
        
        .btn-link {
            color: var(--primary-color);
            text-decoration: none;
            padding: 0;
            background: transparent;
            border: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-link:hover {
            color: var(--primary-dark);
            text-decoration: underline;
            transform: translateY(-2px);
        }
        
        /* Alert Styles */
        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        /* Timer */
        .timer {
            font-family: var(--code-font);
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--danger-color);
            text-align: center;
            margin-top: 1rem;
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
        
        /* Responsive Styles */
        @media (max-width: 767.98px) {
            .verification-card {
                margin: 1rem;
                max-width: calc(100% - 2rem);
            }
            
            .verification-card-header {
                padding: 1.25rem;
            }
            
            .verification-card-body {
                padding: 1.5rem;
            }
            
            .form-control {
                height: 45px;
                font-size: 1rem;
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
        </div>
    </div>

    <div class="container">
        <div class="verification-card">
            <div class="verification-card-header">
                <i class="fas fa-envelope-open-text verification-icon"></i>
                <h2 class="verification-card-title">Email Verification</h2>
                <?php if (isset($_SESSION['email_verification']['new_email'])): ?>
                    <p class="verification-card-subtitle">We've sent a verification code to <strong><?php echo htmlspecialchars($_SESSION['email_verification']['new_email']); ?></strong></p>
                <?php endif; ?>
            </div>
            
            <div class="verification-card-body">
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="verify-email.php">
                    <div class="mb-4">
                        <label for="verification_code" class="form-label">Verification Code</label>
                        <input type="text" class="form-control" id="verification_code" name="verification_code" 
                               maxlength="6" pattern="\d{6}"  autofocus>
                        <div class="form-text text-center">Enter the 6-digit code sent to your email</div>
                    </div>
                    
                    <div class="d-grid gap-2 mb-4">
                        <button type="submit" name="verify_code" class="btn btn-primary">
                            <i class="fas fa-check-circle me-2"></i> Verify Email
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p>Didn't receive the code? <button type="submit" name="resend_code" class="btn btn-link">Resend Code</button></p>
                        <?php if (isset($_SESSION['email_verification']['expires'])): ?>
                            <div class="timer" id="timer">05:00</div>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Timer functionality
        <?php if (isset($_SESSION['email_verification']['expires'])): ?>
            let timeLeft = <?php echo $_SESSION['email_verification']['expires'] - time(); ?>;
            
            function updateTimer() {
                const minutes = Math.floor(timeLeft / 60);
                let seconds = timeLeft % 60;
                
                // Add leading zero if seconds < 10
                seconds = seconds < 10 ? '0' + seconds : seconds;
                
                document.getElementById('timer').textContent = `${minutes}:${seconds}`;
                
                if (timeLeft > 0) {
                    timeLeft--;
                    setTimeout(updateTimer, 1000);
                } else {
                    document.getElementById('timer').textContent = "Expired";
                }
            }
            
            // Start the timer when page loads
            updateTimer();
        <?php endif; ?>
        
        // Auto-focus next input when a digit is entered
        const codeInput = document.getElementById('verification_code');
        codeInput.addEventListener('input', function() {
            if (this.value.length === 6) {
                this.blur();
            }
        });
        
        // Add subtle parallax effect to background elements
        if (window.innerWidth > 768) {
            document.addEventListener('mousemove', (e) => {
                const moveX = (e.clientX - window.innerWidth / 2) / 30;
                const moveY = (e.clientY - window.innerHeight / 2) / 30;
                
                document.querySelectorAll('.shape').forEach((shape) => {
                    const speed = parseFloat(shape.getAttribute('data-speed') || 1);
                    shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
                });
            });
        }
    </script>
</body>
</html>