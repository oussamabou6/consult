<?php
// Import PHPMailer classes into the global namespace
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require __DIR__ . '/../vendor/autoload.php';

function sendVerificationEmail($email, $name, $verificationCode, $siteName = 'Consult Pro') {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        // $mail->SMTPDebug = SMTP::DEBUG_SERVER;                 // Enable verbose debug output (set to 0 for production)
        $mail->isSMTP();                                          // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                     // Set the SMTP server to send through (change to your SMTP server)
        $mail->SMTPAuth   = true;                                 // Enable SMTP authentication
        $mail->Username   = 'consultpro29000@gmail.com';          // SMTP username (change to your email)
        $mail->Password   = 'ssqshptitkcsoeas';                   // SMTP password (change to your app password)
        $mail->SMTPSecure = 'tls';                                // Enable TLS encryption
        $mail->Port       = 587;                                  // TCP port to connect to; use 587 for TLS, 465 for SSL

        // Recipients
        $mail->setFrom('consultpro29000@gmail.com', $siteName);   // Sender email (change to your email)
        $mail->addAddress($email, $name);                         // Add a recipient

        // Content
        $mail->isHTML(true);                                      // Set email format to HTML
        $mail->Subject = 'Email Verification Code';
        
        // Email body with verification code
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Verification</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .container {
                    background-color: #f9f9f9;
                    border-radius: 10px;
                    padding: 30px;
                    border: 1px solid #e0e0e0;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2563eb;
                }
                .verification-code {
                    background-color: #2563eb;
                    color: white;
                    font-size: 24px;
                    font-weight: bold;
                    text-align: center;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                    letter-spacing: 5px;
                }
                .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    color: #666;
                    text-align: center;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">' . $siteName . '</div>
                </div>
                
                <p>Hello ' . $name . ',</p>
                
                <p>We received a request to verify your email address. Please use the verification code below to complete the process:</p>
                
                <div class="verification-code">' . $verificationCode . '</div>
                
                <p>This code will expire in 5 minutes. If you did not request this verification, please ignore this email.</p>
                
                <p>Thank you,<br>The ' . $siteName . ' Team</p>
                
                <div class="footer">
                    <p>This is an automated email, please do not reply.</p>
                    <p>&copy; ' . date('Y') . ' ' . $siteName . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        // Plain text version for non-HTML mail clients
        $mail->AltBody = "Hello $name,\n\nYour verification code is: $verificationCode\n\nThis code will expire in 5 minutes.\n\nThank you,\nThe $siteName Team";

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error (you can implement proper logging)
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send notification email when user updates their profile or settings
 * 
 * @param string $email Recipient email address
 * @param string $name Recipient name
 * @param string $updateType Type of update (personal, banking, password, etc.)
 * @param array $updateDetails Optional details about the update
 * @param string $siteName Site name for branding
 * @return bool True if email was sent successfully, false otherwise
 */
function sendUpdateNotificationEmail($email, $name, $updateType, $updateDetails = [], $siteName = 'Consult Pro') {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();                                          // Send using SMTP
        $mail->Host       = 'smtp.gmail.com';                     // Set the SMTP server to send through
        $mail->SMTPAuth   = true;                                 // Enable SMTP authentication
        $mail->Username   = 'consultpro29000@gmail.com';          // SMTP username
        $mail->Password   = 'ssqshptitkcsoeas';                   // SMTP password
        $mail->SMTPSecure = 'tls';                                // Enable TLS encryption
        $mail->Port       = 587;                                  // TCP port to connect to

        // Recipients
        $mail->setFrom('consultpro29000@gmail.com', $siteName);   // Sender email
        $mail->addAddress($email, $name);                         // Add a recipient

        // Set subject based on update type
        $subjects = [
            'personal' => 'Your Personal Information Has Been Updated',
            'banking' => 'Your Banking Information Has Been Updated',
            'password' => 'Your Password Has Been Changed',
            'certificate' => 'New Certificate Added to Your Profile',
            'experience' => 'New Work Experience Added to Your Profile',
            'formation' => 'New Formation/Course Added to Your Profile',
            'email' => 'Your Email Address Has Been Changed'
        ];
        
        $mail->Subject = isset($subjects[$updateType]) ? $subjects[$updateType] : 'Your Profile Has Been Updated';
        
        // Create update message based on type
        $updateMessage = '';
        $updateIcon = '';
        
        switch ($updateType) {
            case 'personal':
                $updateMessage = 'Your personal information has been successfully updated.';
                $updateIcon = '👤';
                break;
            case 'banking':
                $updateMessage = 'Your banking information has been successfully updated and is pending review.';
                $updateIcon = '🏦';
                break;
            case 'password':
                $updateMessage = 'Your password has been successfully changed.';
                $updateIcon = '🔒';
                break;
            case 'certificate':
                $updateMessage = 'A new certificate has been added to your profile and is pending review.';
                $updateIcon = '🎓';
                break;
            case 'experience':
                $updateMessage = 'A new work experience has been added to your profile and is pending review.';
                $updateIcon = '💼';
                break;
            case 'formation':
                $updateMessage = 'A new formation/course has been added to your profile and is pending review.';
                $updateIcon = '📚';
                break;
            case 'email':
                $updateMessage = 'Your email address has been successfully changed to ' . ($updateDetails['new_email'] ?? 'your new email') . '.';
                $updateIcon = '✉️';
                break;
            default:
                $updateMessage = 'Your profile has been successfully updated.';
                $updateIcon = '✅';
        }
        
        // Generate details section if provided
        $detailsSection = '';
        if (!empty($updateDetails) && $updateType != 'password') {
            $detailsSection = '<div class="details-section">';
            $detailsSection .= '<h3>Update Details:</h3>';
            $detailsSection .= '<ul>';
            
            foreach ($updateDetails as $key => $value) {
                // Skip sensitive information
                if ($key == 'password' || $key == 'current_password' || $key == 'new_password' || $key == 'confirm_password') {
                    continue;
                }
                
                // Format the key for display
                $displayKey = ucwords(str_replace('_', ' ', $key));
                
                // Format the value for display
                if (is_array($value)) {
                    $displayValue = implode(', ', $value);
                } else {
                    $displayValue = $value;
                }
                
                $detailsSection .= '<li><strong>' . $displayKey . ':</strong> ' . htmlspecialchars($displayValue) . '</li>';
            }
            
            $detailsSection .= '</ul>';
            $detailsSection .= '</div>';
        }
        
        // Email body with update notification
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Profile Update Notification</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .container {
                    background-color: #f9f9f9;
                    border-radius: 10px;
                    padding: 30px;
                    border: 1px solid #e0e0e0;
                }
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    color: #2563eb;
                }
                .update-icon {
                    font-size: 48px;
                    text-align: center;
                    margin: 20px 0;
                }
                .update-message {
                    background-color: #e8f4fd;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                    border-left: 4px solid #2563eb;
                }
                .details-section {
                    background-color: #f0f0f0;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                }
                .details-section h3 {
                    margin-top: 0;
                    color: #2563eb;
                }
                .details-section ul {
                    padding-left: 20px;
                }
                .security-note {
                    background-color: #fff8e6;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                    border-left: 4px solid #f59e0b;
                }
                .footer {
                    margin-top: 30px;
                    font-size: 12px;
                    color: #666;
                    text-align: center;
                }
                .button {
                    display: inline-block;
                    background-color: #2563eb;
                    color: white;
                    text-decoration: none;
                    padding: 10px 20px;
                    border-radius: 5px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">' . $siteName . '</div>
                </div>
                
                <p>Hello ' . $name . ',</p>
                
                <div class="update-icon">' . $updateIcon . '</div>
                
                <div class="update-message">
                    <p>' . $updateMessage . '</p>
                </div>
                
                ' . $detailsSection . '
                
                ' . ($updateType == 'password' ? '
                <div class="security-note">
                    <p><strong>Security Note:</strong> If you did not make this change, please contact our support team immediately or reset your password.</p>
                </div>
                ' : '') . '
                
                <p>You can review your profile settings by logging into your account.</p>
                
                <p>Thank you,<br>The ' . $siteName . ' Team</p>
                
                <div class="footer">
                    <p>This is an automated email, please do not reply.</p>
                    <p>&copy; ' . date('Y') . ' ' . $siteName . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        // Plain text version for non-HTML mail clients
        $mail->AltBody = "Hello $name,\n\n$updateMessage\n\n" . 
                        ($updateType == 'password' ? "Security Note: If you did not make this change, please contact our support team immediately or reset your password.\n\n" : "") .
                        "You can review your profile settings by logging into your account.\n\n" .
                        "Thank you,\nThe $siteName Team";

        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Update notification email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
