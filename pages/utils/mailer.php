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
        $mail->Username   = 'consultpro29000@gmail.com';               // SMTP username (change to your email)
        $mail->Password   = 'ssqshptitkcsoeas';                  // SMTP password (change to your app password)
        $mail->SMTPSecure = 'tls';       // Enable TLS encryption
        $mail->Port       = 587;                                  // TCP port to connect to; use 587 for TLS, 465 for SSL

        // Recipients
        $mail->setFrom('consultpro29000@gmail.com', $siteName);        // Sender email (change to your email)
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
