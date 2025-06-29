<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Include mailer utility
require_once("utils/mailer.php");

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['email']) || $_SESSION['user_role'] != 'client') {
    header("Location: ../config/logout.php");
    exit;
}
// Check if new email is set in session
if (!isset($_SESSION['new_email'])) {
    header("Location: profile.php");
    exit;
}

$userId = $_SESSION['user_id'];
$newEmail = $_SESSION['new_email'];

// Get user information
$userQuery = "SELECT * FROM users WHERE id = $userId";
$userResult = $conn->query($userQuery);
$user = $userResult->fetch_assoc();

// Get site settings
$settingsQuery = "SELECT * FROM settings";
$settingsResult = $conn->query($settingsQuery);
$settings = [];

if ($settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

$successMessage = '';
$errorMessage = '';

// Handle verification code submission
if (isset($_POST['verify_email'])) {
    $verificationCode = $_POST['verification_code'];
    
    // Check if verification code is correct and not expired
    if ($user['verification_code'] === $verificationCode && new DateTime() < new DateTime($user['code_expires_at'])) {
        // Check if email already exists for another user
        $checkEmailQuery = "SELECT id FROM users WHERE email = '$newEmail' AND id != $userId";
        $checkEmailResult = $conn->query($checkEmailQuery);
        
        if ($checkEmailResult->num_rows > 0) {
            $errorMessage = "This email address is already registered with another account.";
        } else {
            // Update email
            $updateEmailQuery = "UPDATE users SET email = '$newEmail', verification_code = NULL, code_expires_at = NULL WHERE id = $userId";
            
            if ($conn->query($updateEmailQuery)) {
                $successMessage = "Email address updated successfully!";
                
                // Remove new email from session
                unset($_SESSION['new_email']);
                
                // Redirect to profile page after 3 seconds
                header("refresh:3;url=profile.php");
            } else {
                $errorMessage = "Error updating email: " . $conn->error;
            }
        }
    } else {
        $errorMessage = "Invalid or expired verification code.";
    }
}

// Handle resend verification code
if (isset($_POST['resend_code'])) {
    // Generate new verification code
    $verificationCode = sprintf("%06d", mt_rand(100000, 999999));
    $codeExpiresAt = date('Y-m-d H:i:s', strtotime('+5 minutes'));
    
    // Update verification code in database
    $updateCodeQuery = "UPDATE users SET verification_code = '$verificationCode', code_expires_at = '$codeExpiresAt' WHERE id = $userId";
    
    if ($conn->query($updateCodeQuery)) {
        // Get site name for email branding
        $siteName = isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro';
        
        // Send verification email
        $emailSent = sendVerificationEmail($newEmail, $user['full_name'], $verificationCode, $siteName);
        
        if ($emailSent) {
            $successMessage = "Verification code has been resent to your new email address.";
        } else {
            $errorMessage = "Failed to send verification email. Please try again.";
        }
    } else {
        $errorMessage = "Error generating verification code: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - <?php echo isset($settings['site_name']) ? $settings['site_name'] : 'Consult Pro'; ?></title>
    <!-- Favicon -->
    <link rel="icon" href="../assets/images/favicon.ico" type="image/x-icon">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
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
            
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-800);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .verification-container {
            max-width: 500px;
            width: 100%;
            padding: 20px;
        }
        
        .verification-card {
            background-color: white;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--box-shadow-lg);
            padding: 40px;
            text-align: center;
        }
        
        .verification-icon {
            width: 80px;
            height: 80px;
            background-color: var(--primary-100);
            color: var(--primary-600);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
        }
        
        .verification-title {
            font-family: 'Manrope', sans-serif;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--gray-900);
        }
        
        .verification-subtitle {
            color: var(--gray-600);
            margin-bottom: 30px;
        }
        
        .verification-form {
            margin-bottom: 20px;
        }
        
        .verification-input {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .code-input {
            width: 100%;
            text-align: center;
            font-size: 1.5rem;
            font-weight: 600;
            padding: 10px;
            border: 2px solid var(--gray-200);
            border-radius: var(--border-radius);
            transition: var(--transition);
        }
        
        .code-input:focus {
            border-color: var(--primary-600);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
            outline: none;
        }
        
        .btn {
            padding: 12px 24px;
            font-weight: 600;
            border-radius: var(--border-radius-full);
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-600), var(--primary-700));
            border: none;
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-700), var(--primary-800));
            transform: translateY(-3px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .btn-link {
            color: var(--primary-600);
            text-decoration: none;
        }
        
        .btn-link:hover {
            color: var(--primary-700);
            text-decoration: underline;
        }
        
        .alert {
            border-radius: var(--border-radius);
            padding: 15px 20px;
            margin-bottom: 20px;
            border: none;
            text-align: left;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .timer {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <?php if(!empty($successMessage)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i> <?php echo $successMessage; ?>
            </div>
        <?php endif; ?>

        <?php if(!empty($errorMessage)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $errorMessage; ?>
            </div>
        <?php endif; ?>
        
        <div class="verification-card">
            <div class="verification-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <h2 class="verification-title">Verify Your Email</h2>
            <p class="verification-subtitle">We've sent a verification code to <strong><?php echo $newEmail; ?></strong></p>
            
            <form class="verification-form" method="POST" action="">
                <div class="verification-input">
                    <input type="text" class="code-input" name="verification_code" maxlength="6" placeholder="Enter 6-digit code" required>
                </div>
                <button type="submit" name="verify_email" class="btn btn-primary w-100">Verify Email</button>
            </form>
            
            <div class="mt-3">
                <p>Didn't receive the code?</p>
                <form method="POST" action="">
                    <button type="submit" name="resend_code" class="btn btn-link">Resend Code</button>
                </form>
            </div>
            
            <div class="mt-4">
                <a href="profile.php" class="btn btn-link">
                    <i class="fas fa-arrow-left me-2"></i> Back to Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-focus on code input
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('.code-input').focus();
        });
    </script>
</body>
</html>
