<?php
// check_suspensions.php - Run this file daily via cron job

// Include database connection
require_once 'config/config.php';

// Include mailer
require_once 'utils/mailer.php';

// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Function to check and remove expired suspensions
function checkExpiredSuspensions($conn, $site_name) {
    // Get current date and time
    $current_datetime = date('Y-m-d H:i:s');
    
    // Find users with expired suspensions
    $query = "SELECT u.id, u.email, u.full_name, u.suspension_end_date 
              FROM users u 
              WHERE u.status = 'suspended' 
              AND u.suspension_end_date <= ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $current_datetime);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $count = 0;
    
    while ($user = $result->fetch_assoc()) {
        $user_id = $user['id'];
        $email = $user['email'];
        $name = $user['full_name'];
        $end_date = $user['suspension_end_date'];
        
        // Update user status to active
        $update_sql = "UPDATE users SET status = 'Offline', suspension_end_date = NULL WHERE id = ?";
        $update_stmt = $conn->prepare($update_sql);
        $update_stmt->bind_param("i", $user_id);
        $update_stmt->execute();
        
        // Update suspension record
        $update_suspension_sql = "UPDATE user_suspensions SET active = 0 WHERE user_id = ? AND active = 1";
        $update_suspension_stmt = $conn->prepare($update_suspension_sql);
        $update_suspension_stmt->bind_param("i", $user_id);
        $update_suspension_stmt->execute();
        
        
        
        // Send email notification
        $subject = "Account Suspension Lifted";
        $message = "Dear $name,\n\nYour account suspension has been lifted. Your account is now active again.\n\nThank you for your patience.\n\nRegards,\n$site_name Team";
        
        // Use the sendVerificationEmail function
        sendVerificationEmail($email, $name, $message, $site_name);
        
        $count++;
    }
    
    return $count;
}

// Run the function
$lifted_suspensions = checkExpiredSuspensions($conn, $site_name);

echo "Processed $lifted_suspensions expired suspensions.\n";