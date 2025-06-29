<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require __DIR__ . '/../vendor/autoload.php';

/**
 * Send verification email to user
 * 
 * @param string $email Recipient email address
 * @param string $name Recipient name
 * @param string $message Email message content
 * @param string $siteName Site name to use in the email
 * @param string $subject Email subject (optional)
 * @return bool True if email was sent successfully, false otherwise
 */
function sendVerificationEmail($email, $name, $message, $siteName, $subject = null) {
    // Create a new PHPMailer instance
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();                                        
        $mail->Host       = 'smtp.gmail.com';                     
        $mail->SMTPAuth   = true;                                 
        $mail->Username   = 'consultpro29000@gmail.com';          
        $mail->Password   = 'ssqshptitkcsoeas';                   
        $mail->SMTPSecure = 'tls';                                
        $mail->Port       = 587;                                  

        // Recipients
        $mail->setFrom('consultpro29000@gmail.com', $siteName);   // Sender email (change to your email)
        $mail->addAddress($email, $name);                         // Add a recipient

        // Content
        $mail->isHTML(true);                                      // Set email format to HTML
        
        // Set subject if provided, otherwise use default
        if ($subject) {
            $mail->Subject = $subject;
        } else {
            $mail->Subject = 'Message from ' . $siteName;
        }
        
        // Create HTML version of the message
        $htmlMessage = nl2br(htmlspecialchars($message));
        
        // Email body
        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . $mail->Subject . '</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    line-height: 1.6;
                    color: #333;
                    margin: 0;
                    padding: 0;
                }
                .container {
                    max-width: 600px;
                    margin: 0 auto;
                    padding: 20px;
                    border: 1px solid #ddd;
                    border-radius: 5px;
                }
                .header {
                    background-color: #7C3AED;
                    color: white;
                    padding: 15px;
                    text-align: center;
                    border-radius: 5px 5px 0 0;
                }
                .content {
                    padding: 20px;
                    background-color: #f9f9f9;
                }
                .footer {
                    text-align: center;
                    padding: 15px;
                    font-size: 12px;
                    color: #777;
                    border-top: 1px solid #ddd;
                }
                .button {
                    display: inline-block;
                    padding: 10px 20px;
                    background-color: #7C3AED;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    margin-top: 15px;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h2>' . $siteName . '</h2>
                </div>
                <div class="content">
                    <p>Dear ' . $name . ',</p>
                    <p>' . $htmlMessage . '</p>
                </div>
                <div class="footer">
                    <p>&copy; ' . date('Y') . ' ' . $siteName . '. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        // Plain text version for non-HTML mail clients
        $mail->AltBody = strip_tags($message);
        
        // Send the email
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log the error
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Send a general email
 * 
 * @param string $email Recipient email address
 * @param string $name Recipient name
 * @param string $subject Email subject
 * @param string $message Email message content
 * @param string $siteName Site name to use in the email
 * @return bool True if email was sent successfully, false otherwise
 */
function sendEmail($email, $name, $subject, $message, $siteName) {
    return sendVerificationEmail($email, $name, $message, $siteName, $subject);
}

/**
 * Send a notification email to multiple recipients
 * 
 * @param array $recipients Array of recipient email addresses and names [['email' => 'email@example.com', 'name' => 'John Doe'], ...]
 * @param string $subject Email subject
 * @param string $message Email message content
 * @param string $siteName Site name to use in the email
 * @return bool True if all emails were sent successfully, false otherwise
 */
function sendBulkEmail($recipients, $subject, $message, $siteName) {
    $success = true;
    
    foreach ($recipients as $recipient) {
        $result = sendEmail($recipient['email'], $recipient['name'], $subject, $message, $siteName);
        if (!$result) {
            $success = false;
        }
    }
    
    return $success;
}

/**
 * Send a suspension notification email
 * 
 * @param string $email Recipient email address
 * @param string $name Recipient name
 * @param string $suspensionEndDate Date when the suspension ends
 * @param string $siteName Site name to use in the email
 * @return bool True if email was sent successfully, false otherwise
 */
function sendSuspensionEmail($email, $name, $suspensionEndDate, $siteName) {
    $subject = "Account Suspension Notice";
    $message = "Dear $name,\n\nYour account has been suspended for 30 days due to multiple reports against you. The suspension will end on " . date('Y-m-d', strtotime($suspensionEndDate)) . ".\n\nYour account will be automatically reactivated after this period.";
    
    return sendEmail($email, $name, $subject, $message, $siteName);
}
?>