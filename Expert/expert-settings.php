<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';
require_once 'utils/mail.php'; // This will contain the sendVerificationEmail function

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in, redirect to login page
    header("Location: ../pages/login.php");
    exit();
}
/*Take style and design et les parametres de page "expert-profiles.php" et develop page "dashboard.php" dans this page ajouter les experts en status approved qui add new ' certificate , experience , formation , bankinformation ' en status panding  pour afficher en section et ajouter button "voir profile" qui aller a certificate qui add 
and add notifications 
add other informations
database dans consult_pro*/
// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];
$success_message = "";
$error_message = "";
$password_error = "";
$current_password_error = "";
$new_password_error = "";
$confirm_password_error = "";

// Also, let's modify the sanitize_input function to ensure it doesn't strip important content:

// Function to sanitize input data
function sanitize_input($data) {
    if (is_null($data)) {
        return '';
    }
    
    // Convert to string if it's not already
    if (!is_string($data)) {
        $data = (string)$data;
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    // Debug log
    error_log("Sanitized input: " . substr($data, 0, 100) . (strlen($data) > 100 ? '...' : ''));
    
    return $data;
}

// Get user data
$sql = "SELECT u.*, up.phone, up.address, up.dob, up.gender, up.profile_image 
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$full_name = $user["full_name"];
// Get expert profile data
$profile_sql = "SELECT * FROM expert_profiledetails WHERE user_id = ?";
$profile_stmt = $conn->prepare($profile_sql);
$profile_stmt->bind_param("i", $user_id);
$profile_stmt->execute();
$profile_result = $profile_stmt->get_result();
$profile = $profile_result->fetch_assoc();
$profile_id = $profile ? $profile['id'] : 0;

// Get certificates
$certificates = [];
if ($profile_id) {
    $cert_sql = "SELECT * FROM certificates WHERE profile_id = ? ORDER BY id";
    $cert_stmt = $conn->prepare($cert_sql);
    $cert_stmt->bind_param("i", $profile_id);
    $cert_stmt->execute();
    $cert_result = $cert_stmt->get_result();
    while ($row = $cert_result->fetch_assoc()) {
        $certificates[] = $row;
    }
}

// Get experiences
$experiences = [];
if ($profile_id) {
    $exp_sql = "SELECT * FROM experiences WHERE profile_id = ? ORDER BY id";
    $exp_stmt = $conn->prepare($exp_sql);
    $exp_stmt->bind_param("i", $profile_id);
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();
    while ($row = $exp_result->fetch_assoc()) {
        $experiences[] = $row;
    }
}

// Get formations (courses)
$formations = [];
if ($profile_id) {
    $form_sql = "SELECT * FROM formations WHERE profile_id = ? ORDER BY id";
    $form_stmt = $conn->prepare($form_sql);
    $form_stmt->bind_param("i", $profile_id);
    $form_stmt->execute();
    $form_result = $form_stmt->get_result();
    while ($row = $form_result->fetch_assoc()) {
        $formations[] = $row;
    }
}

// Get banking information
$banking = null;
if ($profile_id) {
    $banking_sql = "SELECT * FROM banking_information WHERE profile_id = ? AND user_id = ?";
    $banking_stmt = $conn->prepare($banking_sql);
    $banking_stmt->bind_param("ii", $profile_id, $user_id);
    $banking_stmt->execute();
    $banking_result = $banking_stmt->get_result();
    $banking = $banking_result->fetch_assoc();
}



// Add this code after the "Check for any error message passed from other pages" section (around line 40-45)
// This will handle the success message when returning from email verification

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Handle personal information update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_personal"])) {
    
    $new_email = sanitize_input($_POST["email"]);
    $phone = sanitize_input($_POST["phone"]);
    $address = sanitize_input($_POST["address"]);
    
    // Validate email and phone uniqueness
    $errors = [];
    $email_changed = false;
    $phone_changed = false;
    
    // Check if email is being changed
    if ($new_email != $email) {
        $email_changed = true;
        // Check if new email exists in database
        $check_email_sql = "SELECT id FROM users WHERE email = ? AND id != ?";
        $check_email_stmt = $conn->prepare($check_email_sql);
        $check_email_stmt->bind_param("si", $new_email, $user_id);
        $check_email_stmt->execute();
        $check_email_result = $check_email_stmt->get_result();
        
        if ($check_email_result->num_rows > 0) {
            $error_message = "This email is already registered in our database. Please use a different email address.";
            $errors['email'] = true;
        } else {
            // Generate verification code
            $verification_code = rand(100000, 999999);
            $_SESSION['email_verification'] = [
                'new_email' => $new_email,
                'code' => $verification_code,
                'expires' => time() + 300 // 5 minutes expiration
            ];
            
            // Send verification email
            if (sendVerificationEmail($new_email, $user['full_name'], $verification_code, $settings['site_name'] ?? 'Consult Pro')) {
                $_SESSION['email_change_pending'] = true;
                $_SESSION['email_change_data'] = [
                    'phone' => $phone,
                    'address' => $address
                ];
                
                header("Location: verify-email.php");
                exit();
            } else {
                $error_message = "Failed to send verification email. Please try again.";
                $errors['email'] = true;
            }
        }
    }
    
    // Check if phone is being changed
    if ($phone != $user['phone']) {
        $phone_changed = true;
        // Check if phone exists in database
        $check_phone_sql = "SELECT user_id FROM user_profiles WHERE phone = ? AND user_id != ?";
        $check_phone_stmt = $conn->prepare($check_phone_sql);
        $check_phone_stmt->bind_param("si", $phone, $user_id);
        $check_phone_stmt->execute();
        $check_phone_result = $check_phone_stmt->get_result();
        
        if ($check_phone_result->num_rows > 0) {
            $error_message = "This phone number is already registered in our database. Please use a different phone number.";
            $errors['phone'] = true;
        }
    }
    
    // If there are errors, show them
    if (!empty($errors)) {
        // Error message is already set above
    } else {
        // If email is not being changed, update immediately
        if (!$email_changed) {
            // Update users table
            $update_user_sql = "UPDATE users SET id = ? WHERE id = ?";
            $update_user_stmt = $conn->prepare($update_user_sql);
            $update_user_stmt->bind_param("ii", $user_id, $user_id);
            $update_user_result = $update_user_stmt->execute();
            
            // Check if user profile exists
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
                if ($phone_changed) {
                    $success_message = "Your information has been updated successfully! Phone number has been changed.";
                } else {
                    $success_message = "Your information has been updated successfully!";
                }
                
                // Update session data
                $_SESSION["full_name"] = $user['full_name'];
                
                // Refresh user data
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();

                // Add this code to send notification email
                require_once 'utils/mail.php';
                $updateDetails = [
                    'full_name' => $user['full_name'],
                    'phone' => $phone,
                    'address' => $address
                ];
                sendUpdateNotificationEmail($email, $user['full_name'], 'personal', $updateDetails, $settings['site_name'] ?? 'Consult Pro');
            } else {
                $error_message = "Error updating personal information.";
            }
        } else {
            $success_message = "Personal information will be updated after email verification.";
        }
    }
}

// Handle banking information update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_banking"])) {
    $ccp = sanitize_input($_POST["ccp"]);
    $ccp_key = sanitize_input($_POST["ccp_key"]);
    $consultation_minutes = sanitize_input($_POST["consultation_minutes"]);
    $consultation_price = sanitize_input($_POST["consultation_price"]);
    
    // Check if check file is uploaded
    $check_file_path = "";
    if (isset($banking['check_file_path'])) {
        $check_file_path = $banking['check_file_path'];
    }
    
    if (isset($_FILES["check_file"]) && $_FILES["check_file"]["error"] == 0) {
        $allowed_types = ["image/jpeg", "image/jpg", "image/png", "application/pdf"];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (in_array($_FILES["check_file"]["type"], $allowed_types) && $_FILES["check_file"]["size"] <= $max_size) {
            $upload_dir = "../uploads/checks/";
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = "check_" . $user_id . "_" . time() . "." . pathinfo($_FILES["check_file"]["name"], PATHINFO_EXTENSION);
            $target_file = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES["check_file"]["tmp_name"], $target_file)) {
                $check_file_path = $target_file;
            } else {
                $error_message = "Error uploading check file.";
            }
        } else {
            $error_message = "Invalid file type or size. Please upload a JPG, PNG, or PDF file under 5MB.";
        }
    }
    
    if (empty($error_message)) {
        if ($banking) {
            // Update existing banking information
            $update_banking_sql = "UPDATE banking_information SET ccp = ?, ccp_key = ?, check_file_path = ?, consultation_minutes = ?, consultation_price = ? WHERE id = ?";
            $update_banking_stmt = $conn->prepare($update_banking_sql);
            $update_banking_stmt->bind_param("sssiii", $ccp, $ccp_key, $check_file_path,  $consultation_minutes, $consultation_price, $banking['id']);
            $update_banking_result = $update_banking_stmt->execute();
            
            // Create notification for admin
            $notification_type = "banking_updated";
            $message = "$full_name : This Expert banking information updated and needs review";
            
            $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
            $insert_notif_stmt = $conn->prepare($insert_notif_sql);
            $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
            $insert_notif_stmt->execute();
            
            // Update banking status to pending review
            $update_status_sql = "UPDATE expert_profiledetails SET banking_status = 'pending_review' WHERE id = ?";
            $update_status_stmt = $conn->prepare($update_status_sql);
            $update_status_stmt->bind_param("i", $profile_id);
            $update_status_stmt->execute();
        } 
        
        if (isset($update_banking_result) && $update_banking_result) {
            $success_message = "Banking information updated successfully! It will be reviewed by an administrator.";
            
            // Refresh banking data
            if ($profile_id) {
                $banking_stmt->execute();
                $banking_result = $banking_stmt->get_result();
                $banking = $banking_result->fetch_assoc();
            }

            // Add this code to send notification email
            require_once 'utils/mail.php';
            $updateDetails = [
                'ccp' => $ccp,
                'ccp_key' => $ccp_key,
                'consultation_minutes' => $consultation_minutes,
                'consultation_price' => $consultation_price
            ];
            sendUpdateNotificationEmail($email, $user['full_name'], 'banking', $updateDetails, $settings['site_name'] ?? 'Consult Pro');
        } else {
            $error_message = "Error updating banking information.";
        }
    }
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["change_password"])) {
    $current_password = $_POST["current_password"];
    $new_password = $_POST["new_password"];
    $confirm_password = $_POST["confirm_password"];
    
    // Validate inputs
    $valid = true;
    
    if (empty($current_password)) {
        $current_password_error = "Current password is required";
        $valid = false;
    }
    
    if (empty($new_password)) {
        $new_password_error = "New password is required";
        $valid = false;
    } elseif (strlen($new_password) < 8) {
        $new_password_error = "Password must be at least 8 characters long";
        $valid = false;
    }
    
    if ($new_password !== $confirm_password) {
        $confirm_password_error = "Passwords do not match";
        $valid = false;
    }
    
    if ($valid) {
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $update_password_sql = "UPDATE users SET password = ? WHERE id = ?";
            $update_password_stmt = $conn->prepare($update_password_sql);
            $update_password_stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($update_password_stmt->execute()) {
                $success_message = "Password changed successfully!";

                // Add this code to send notification email
                require_once 'utils/mail.php';
                sendUpdateNotificationEmail($email, $user['full_name'], 'password', [], $settings['site_name'] ?? 'Consult Pro');
            } else {
                $error_message = "Error changing password.";
            }
        } else {
            $current_password_error = "Current password is incorrect";
        }
    } else {
        $password_error = "Please fix the errors below.";
    }
}

// Handle certificate addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_certificate"])) {
    if ($profile_id) {
        $institution = sanitize_input($_POST["institution"]);
        $start_date = sanitize_input($_POST["cert_start_date"]);
        $end_date = sanitize_input($_POST["cert_end_date"]);
        $description = sanitize_input($_POST["cert_description"]);
        
        // Handle file upload
        $file_path = "";
        if (isset($_FILES["cert_file"]) && $_FILES["cert_file"]["error"] == 0) {
            $allowed_types = ["image/jpeg", "image/jpg", "image/png", "application/pdf"];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (in_array($_FILES["cert_file"]["type"], $allowed_types) && $_FILES["cert_file"]["size"] <= $max_size) {
                $upload_dir = "../uploads/certificates/";
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = "cert_" . $user_id . "_" . time() . "." . pathinfo($_FILES["cert_file"]["name"], PATHINFO_EXTENSION);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES["cert_file"]["tmp_name"], $target_file)) {
                    $file_path = $target_file;
                } else {
                    $error_message = "Error uploading certificate file.";
                }
            } else {
                $error_message = "Invalid file type or size. Please upload a JPG, PNG, or PDF file under 5MB.";
            }
        }
        
        if (empty($error_message)) {
            // Insert certificate with pending status
            $insert_cert_sql = "INSERT INTO certificates (profile_id, section_id, certificate_id, start_date, end_date, institution, file_path, description, status) VALUES (?, 0, 0, ?, ?, ?, ?, ?, 'pending')";
            $insert_cert_stmt = $conn->prepare($insert_cert_sql);
            $insert_cert_stmt->bind_param("isssss", $profile_id, $start_date, $end_date, $institution, $file_path, $description);
            
            if ($insert_cert_stmt->execute()) {
                $success_message = "Certificate added successfully! It will be reviewed by an administrator.";
                
                // Create notification for admin
                $notification_type = "certificate_added";
                $message = "New certificate added by $full_name and needs review";
                
                $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
                $insert_notif_stmt = $conn->prepare($insert_notif_sql);
                $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
                $insert_notif_stmt->execute();
              
                
                // Refresh certificates data
                $cert_stmt->execute();
                $cert_result = $cert_stmt->get_result();
                $certificates = [];
                while ($row = $cert_result->fetch_assoc()) {
                    $certificates[] = $row;
                }

                // Add this code to send notification email
                require_once 'utils/mail.php';
                $updateDetails = [
                    'institution' => $institution,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'description' => $description
                ];
                sendUpdateNotificationEmail($email, $user['full_name'], 'certificate', $updateDetails, $settings['site_name'] ?? 'Consult Pro');
            } else {
                $error_message = "Error adding certificate.";
            }
        }
    } else {
        $error_message = "You need to complete your expert profile first.";
    }
}

// Handle experience addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_experience"])) {
    if ($profile_id) {
        $workplace = sanitize_input($_POST["workplace"]);
        $start_date = sanitize_input($_POST["exp_start_date"]);
        $end_date = sanitize_input($_POST["exp_end_date"]);
        $duration_years = intval(sanitize_input($_POST["duration_years"]));
        $duration_months = intval(sanitize_input($_POST["duration_months"]));
        $description = sanitize_input($_POST["exp_description"]);
        
        // Debug output to check values
        error_log("Experience Description: " . $description);
        
        // Handle file upload
        $file_path = "";
        if (isset($_FILES["exp_file"]) && $_FILES["exp_file"]["error"] == 0) {
            $allowed_types = ["image/jpeg", "image/jpg", "image/png", "application/pdf"];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (in_array($_FILES["exp_file"]["type"], $allowed_types) && $_FILES["exp_file"]["size"] <= $max_size) {
                $upload_dir = "../uploads/experiences/";
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = "exp_" . $user_id . "_" . time() . "." . pathinfo($_FILES["exp_file"]["name"], PATHINFO_EXTENSION);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES["exp_file"]["tmp_name"], $target_file)) {
                    $file_path = $target_file;
                } else {
                    $error_message = "Error uploading experience file.";
                }
            } else {
                $error_message = "Invalid file type or size. Please upload a JPG, PNG, or PDF file under 5MB.";
            }
        }
        
        if (empty($error_message)) {
            try {
                // Insert experience with pending status
                $insert_exp_sql = "INSERT INTO experiences (profile_id, start_date, end_date, workplace, duration_years, duration_months, description, file_path, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
                $insert_exp_stmt = $conn->prepare($insert_exp_sql);
                
                // Explicitly check if prepare was successful
                if ($insert_exp_stmt === false) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                // Bind parameters with explicit types
                $insert_exp_stmt->bind_param("isssiiss", 
                    $profile_id,      // i - integer
                    $start_date,      // s - string
                    $end_date,        // s - string
                    $workplace,       // s - string
                    $duration_years,  // i - integer
                    $duration_months, // i - integer
                    $description,     // s - string
                    $file_path        // s - string
                );
                
                // Log the SQL and parameters for debugging
                error_log("SQL: " . $insert_exp_sql);
                error_log("Parameters: profile_id=$profile_id, start_date=$start_date, end_date=$end_date, workplace=$workplace, duration_years=$duration_years, duration_months=$duration_months, description=$description, file_path=$file_path");
                
                // Execute the statement
                $result = $insert_exp_stmt->execute();
                
                if ($result === false) {
                    throw new Exception("Execute failed: " . $insert_exp_stmt->error);
                }
                
                $success_message = "Experience added successfully! It will be reviewed by an administrator.";
                
                // Create notification for admin
                $notification_type = "experience_added";
                $message = "New work experience added by $full_name and needs review";
                
                $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
                $insert_notif_stmt = $conn->prepare($insert_notif_sql);
                $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
                $insert_notif_stmt->execute();
                
              
                // Refresh experiences data
                $exp_stmt->execute();
                $exp_result = $exp_stmt->get_result();
                $experiences = [];
                while ($row = $exp_result->fetch_assoc()) {
                    $experiences[] = $row;
                }

                // Add this code to send notification email
                require_once 'utils/mail.php';
                $updateDetails = [
                    'workplace' => $workplace,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'duration' => $duration_years . ' years, ' . $duration_months . ' months',
                    'description' => $description
                ];
                sendUpdateNotificationEmail($email, $user['full_name'], 'experience', $updateDetails, $settings['site_name'] ?? 'Consult Pro');
            } catch (Exception $e) {
                $error_message = "Error adding experience: " . $e->getMessage();
                error_log($error_message);
            }
        }
    } else {
        $error_message = "You need to complete your expert profile first.";
    }
}

// Let's also modify the experience form to ensure the textarea is properly handled:

// Find the textarea in the experience form and replace it with:

// Handle formation addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_formation"])) {
    if ($profile_id) {
        $formation_name = sanitize_input($_POST["formation_name"]);
        $formation_type = sanitize_input($_POST["formation_type"]);
        $formation_year = sanitize_input($_POST["formation_year"]);
        $description = sanitize_input($_POST["formation_description"]);
        
        // Handle file upload
        $file_path = "";
        if (isset($_FILES["formation_file"]) && $_FILES["formation_file"]["error"] == 0) {
            $allowed_types = ["image/jpeg", "image/jpg", "image/png", "application/pdf"];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (in_array($_FILES["formation_file"]["type"], $allowed_types) && $_FILES["formation_file"]["size"] <= $max_size) {
                $upload_dir = "../uploads/formations/";
                
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_name = "form_" . $user_id . "_" . time() . "." . pathinfo($_FILES["formation_file"]["name"], PATHINFO_EXTENSION);
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES["formation_file"]["tmp_name"], $target_file)) {
                    $file_path = $target_file;
                } else {
                    $error_message = "Error uploading formation file.";
                }
            } else {
                $error_message = "Invalid file type or size. Please upload a JPG, PNG, or PDF file under 5MB.";
            }
        }
        
        if (empty($error_message)) {
            // Insert formation with pending status
            $insert_form_sql = "INSERT INTO formations (profile_id, formation_name, formation_type, formation_year, description, file_path, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $insert_form_stmt = $conn->prepare($insert_form_sql);
            $insert_form_stmt->bind_param("ississ", $profile_id, $formation_name, $formation_type, $formation_year, $description, $file_path);
            
            if ($insert_form_stmt->execute()) {
                $success_message = "Formation added successfully! It will be reviewed by an administrator.";
                
                // Create notification for admin
                $notification_type = "formation_added";
                $message = "New formation added by $full_name and needs review";
                
                $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
                $insert_notif_stmt = $conn->prepare($insert_notif_sql);
                $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
                $insert_notif_stmt->execute();
                
                
                // Refresh formations data
                $form_stmt->execute();
                $form_result = $form_stmt->get_result();
                $formations = [];
                while ($row = $form_result->fetch_assoc()) {
                    $formations[] = $row;
                }

                // Add this code to send notification email
                require_once 'utils/mail.php';
                $updateDetails = [
                    'formation_name' => $formation_name,
                    'formation_type' => $formation_type,
                    'formation_year' => $formation_year,
                    'description' => $description
                ];
                sendUpdateNotificationEmail($email, $user['full_name'], 'formation', $updateDetails, $settings['site_name'] ?? 'Consult Pro');
            } else {
                $error_message = "Error adding formation.";
            }
        }
    } else {
        $error_message = "You need to complete your expert profile first.";
    }
}

// Function to get status badge HTML
function getStatusBadge($status) {
    if (!isset($status)) {
        $status = 'pending';
    }
    
    switch ($status) {
        case 'approved':
            return '<span class="badge bg-success">Approved</span>';
        case 'pending':
        case 'pending_review':
            return '<span class="badge bg-warning text-dark">Pending</span>';
        case 'rejected':
            return '<span class="badge bg-danger">Rejected</span>';
        default:
            return '<span class="badge bg-secondary">Unknown</span>';
    }
}
// Get site settings
$settings = [];
$settings_sql = "SELECT setting_key, setting_value FROM settings";
$settings_result = $conn->query($settings_sql);
if ($settings_result && $settings_result->num_rows > 0) {
    while ($row = $settings_result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
// Get pending consultation requests
$pending_consultations = [];
$pending_sql = "SELECT c.*, u.full_name as client_name, u.status as client_status, up.profile_image as client_image,
                cat.name as category_name, subcat.name as subcategory_name
                FROM consultations c 
                JOIN users u ON c.client_id = u.id 
                LEFT JOIN user_profiles up ON u.id = up.user_id 
                LEFT JOIN expert_profiledetails ep ON c.expert_id = ep.user_id
                LEFT JOIN categories cat ON ep.category = cat.id
                LEFT JOIN subcategories subcat ON ep.subcategory = subcat.id
                WHERE c.expert_id = ? AND c.status = 'pending' 
                ORDER BY c.created_at DESC";
$pending_stmt = $conn->prepare($pending_sql);
$pending_stmt->bind_param("i", $user_id);
$pending_stmt->execute();
$pending_result = $pending_stmt->get_result();

while ($row = $pending_result->fetch_assoc()) {
    $pending_consultations[] = $row;
}
$pending_stmt->close();
// Get notification counts

$admin_messages = $conn->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE is_read = 0 AND receiver_id = ? AND sender_type = 'admin'");
$admin_messages->bind_param("i", $user_id);
$admin_messages->execute();
$admin_messages_result = $admin_messages->get_result();
$admin_messages_count = $admin_messages_result->fetch_assoc()['count'];
$admin_messages->close();

// Get pending withdrawals count
$pending_withdrawals = $conn->prepare("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending' AND user_id = ?");
$pending_withdrawals->bind_param("i", $user_id);
$pending_withdrawals->execute();
$pending_withdrawals_result = $pending_withdrawals->get_result();
$pending_withdrawals_count = $pending_withdrawals_result->fetch_assoc()['count'];
$pending_withdrawals->close();

// Get pending consultation count
$pending_consultations_count = count($pending_consultations);

$reviews_not_read = $conn->prepare("SELECT COUNT(*) as count FROM expert_ratings WHERE is_read = 0 AND expert_id = ?");
$reviews_not_read->bind_param("i", $user_id);
$reviews_not_read->execute();
$reviews_not_read_result = $reviews_not_read->get_result();
$reviews_not_read_count = $reviews_not_read_result->fetch_assoc()['count'];
$reviews_not_read->close();


$notifictaions_not_read = $conn->prepare("SELECT COUNT(*) as count FROM expert_notifications WHERE is_read = 0 AND user_id = ? ");
$notifictaions_not_read->bind_param("i", $user_id);
$notifictaions_not_read->execute();
$notifictaions_not_read_result = $notifictaions_not_read->get_result();
$notifictaions_not_read_count = $notifictaions_not_read_result->fetch_assoc()['count'];
$notifictaions_not_read->close();

// Handle AJAX request for notifications
if (isset($_GET['fetch_notifications'])) {
    $response = [
        'pending_consultations' => $pending_consultations_count,
        'pending_withdrawals' => $pending_withdrawals_count,
        'admin_messages' => $admin_messages_count,
        'reviews' => $reviews_not_read_count,
        'notifications_not_read' => $notifictaions_not_read_count,
        'total' => $pending_consultations_count + $pending_withdrawals_count  + $admin_messages_count + $reviews_not_read_count + $notifictaions_not_read_count,
       ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}


// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert Settings - Consult Pro</title>
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
            opacity: 92,246,0.1) 2px, transparent 0);
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
        
        .shape-4 {
            width: 350px;
            height: 350px;
            background: rgba(245, 158, 11, 0.2);
            bottom: 20%;
            right: 20%;
            animation-delay: -7s;
        }
        
        .shape-5 {
            width: 300px;
            height: 300px;
            background: rgba(16, 185, 129, 0.3);
            top: 10%;
            left: 20%;
            animation-delay: -3s;
        }
        
        @keyframes float {
            0%, 100% {
                transform: translateY(0) scale(1);
            }
            50% {
                transform: translateY(-40px) scale(1.05);
            }
        }
        
        /* Particles */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            width: 6px;
            height: 6px;
            background-color: rgba(99, 102, 241, 0.2);
            border-radius: 50%;
            animation: particle-animation 15s infinite linear;
        }
        
        @keyframes particle-animation {
            0% {
                transform: translate(0, 0);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translate(var(--tx), var(--ty));
                opacity: 0;
            }
        }
        
        /* Animated Gradient Background */
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
        
        /* 3D Floating Elements */
        .floating-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            perspective: 1000px;
        }
        
        .floating-element {
            position: absolute;
            transform-style: preserve-3d;
            animation: float-3d 20s infinite ease-in-out;
            opacity: 0.15;
        }
        
        .floating-element-1 {
            top: 15%;
            left: 10%;
            animation-delay: 0s;
        }
        
        .floating-element-2 {
            top: 60%;
            right: 15%;
            animation-delay: -5s;
        }
        
        .floating-element-3 {
            bottom: 20%;
            left: 20%;
            animation-delay: -10s;
        }
        
        .floating-cube {
            width: 80px;
            height: 80px;
            position: relative;
            transform-style: preserve-3d;
            animation: rotate-cube 20s infinite linear;
        }
        
        .floating-cube-face {
            position: absolute;
            width: 80px;
            height: 80px;
            background: rgba(99, 102, 241, 0.2);
            border: 1px solid rgba(99, 102, 241, 0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--code-font);
            font-size: 12px;
            color: rgba(99, 102, 241, 0.8);
        }
        
        .floating-cube-face:nth-child(1) { transform: translateZ(40px); }
        .floating-cube-face:nth-child(2) { transform: rotateY(180deg) translateZ(40px); }
        .floating-cube-face:nth-child(3) { transform: rotateY(90deg) translateZ(40px); }
        .floating-cube-face:nth-child(4) { transform: rotateY(-90deg) translateZ(40px); }
        .floating-cube-face:nth-child(5) { transform: rotateX(90deg) translateZ(40px); }
        .floating-cube-face:nth-child(6) { transform: rotateX(-90deg) translateZ(40px); }
        
        @keyframes float-3d {
            0%, 100% {
                transform: translateY(0) rotateX(10deg) rotateY(10deg);
            }
            50% {
                transform: translateY(-30px) rotateX(-10deg) rotateY(-10deg);
            }
        }
        
        @keyframes rotate-cube {
            0% {
                transform: rotateX(0deg) rotateY(0deg) rotateZ(0deg);
            }
            100% {
                transform: rotateX(360deg) rotateY(360deg) rotateZ(360deg);
            }
        }
        
        /* Code Elements Animation */
        .code-element {
            position: absolute;
            font-family: var(--code-font);
            color: rgba(99, 102, 241, 0.3);
            font-size: 14px;
            white-space: nowrap;
            pointer-events: none;
            z-index: -1;
            text-shadow: 0 0 10px rgba(99, 102, 241, 0.1);
        }
        
        .code-element-1 {
            top: 15%;
            left: 5%;
            transform: rotate(-15deg);
            animation: fadeInOut 8s infinite ease-in-out, float-code 20s infinite ease-in-out;
        }
        
        .code-element-2 {
            top: 40%;
            right: 10%;
            transform: rotate(10deg);
            animation: fadeInOut 12s infinite ease-in-out 2s, float-code 25s infinite ease-in-out 2s;
        }
        
        .code-element-3 {
            bottom: 20%;
            left: 15%;
            transform: rotate(5deg);
            animation: fadeInOut 10s infinite ease-in-out 4s, float-code 22s infinite ease-in-out 4s;
        }
        
        .code-element-4 {
            top: 25%;
            right: 25%;
            transform: rotate(-5deg);
            animation: fadeInOut 11s infinite ease-in-out 1s, float-code 24s infinite ease-in-out 1s;
        }
        
        .code-element-5 {
            bottom: 35%;
            right: 15%;
            transform: rotate(8deg);
            animation: fadeInOut 9s infinite ease-in-out 3s, float-code 21s infinite ease-in-out 3s;
        }
        
        @keyframes fadeInOut {
            0%, 100% {
                opacity: 0.1;
            }
            50% {
                opacity: 0.3;
            }
        }
        
        @keyframes float-code {
            0%, 100% {
                transform: translateY(0) rotate(var(--rotate));
            }
            50% {
                transform: translateY(-20px) rotate(var(--rotate));
            }
        }
        /* Notification Badge Styles */
        .notification-badge {
            display: none;
            position: absolute;
            top: -5px;
            right: -5px;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 9px;
            background-color: var(--danger-color) ;
            color: white;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            animation: pulse 1.5s infinite;
            z-index: 10;
        }

        .notification-badge.show {
            display: block;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
        /* Navbar Styles */
        .navbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 0.8rem 1rem;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
            transition: all 0.3s ease;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-2px);
        }

        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1;
        }

        .logo-text .fw-bold {
            font-size: 1.8rem;
            color: var(--primary-color);
        }

        .logo-subtitle {
            font-size: 0.7rem;
            letter-spacing: 1px;
            color: var(--text-muted);
            font-weight: 600;
        }

        .navbar-nav {
            gap: 0.5rem;
        }

        .navbar-nav .nav-item {
            position: relative;
        }

        .navbar-light .navbar-nav .nav-link {
            color: var(--text-color);
            font-weight: 500;
            padding: 0.6rem 1rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.3rem;
            font-size: 0.9rem;
        }

        .navbar-light .navbar-nav .nav-link i {
            font-size: 1.2rem;
            transition: transform 0.3s ease;
        }

        .navbar-light .navbar-nav .nav-link:hover {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.08);
        }

        .navbar-light .navbar-nav .nav-link:hover i {
            transform: translateY(-3px);
        }

        .navbar-light .navbar-nav .active > .nav-link,
        .navbar-light .navbar-nav .nav-link.active {
            color: var(--primary-color);
            background: rgba(99, 102, 241, 0.1);
            font-weight: 600;
        }

        .navbar-light .navbar-nav .active > .nav-link i,
        .navbar-light .navbar-nav .nav-link.active i {
            color: var(--primary-color);
        }

        .nav-user-section {
            margin-left: 1rem;
            border-left: 1px solid rgba(226, 232, 240, 0.8);
            padding-left: 1rem;
        }

        
        .dropdown-menu {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            padding: 0.75rem 0;
            margin-top: 0.5rem;
            animation: dropdown-fade 0.3s ease;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.7);
            min-width: 200px;
        }

        @keyframes dropdown-fade {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .dropdown-item {
            padding: 0.6rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .dropdown-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.1));
            z-index: -1;
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }

        .dropdown-item::before:hover {
            transform: translateX(0);
        }

        .dropdown-item:hover {
            color: var(--primary-color);
            transform: translateX(5px);
        }

        .dropdown-item:active {
            background-color: var(--primary-color);
            color: white;
        }

        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
            font-size: 0.9rem;
            transition: transform 0.3s ease;
        }

        .dropdown-item:hover i {
            transform: scale(1.2);
        }

        /* Responsive navbar adjustments */
        @media (max-width: 991.98px) {
            .navbar-nav {
                margin-top: 1rem;
                gap: 0.2rem;
            }
            
            .navbar-light .navbar-nav .nav-link {
                flex-direction: row;
                justify-content: flex-start;
                padding: 0.8rem 1rem;
            }
            
            .navbar-light .navbar-nav .nav-link i {
                margin-right: 10px;
                width: 20px;
                text-align: center;
            }
            
            .nav-user-section {
                margin-left: 0;
                border-left: none;
                padding-left: 0;
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(226, 232, 240, 0.8);
            }
        }
        
        /* Main Content Styles */
        .main-container {
            padding: 2rem 0;
            position: relative;
            z-index: 1;
        }
        
        /* Settings Container */
        .settings-container {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .settings-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.12);
        }
        
        .settings-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
            color: white;
            padding: 2.5rem;
            position: relative;
            overflow: hidden;
        }
        
        .settings-header::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
        }
        
        .settings-header::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.15) 0%, rgba(255, 255, 255, 0) 70%);
            border-radius: 50%;
        }
        
        .settings-title {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .settings-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .settings-content {
            padding: 2rem;
        }
        
        /* Tabs Styling */
        .nav-tabs {
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            margin-bottom: 2rem;
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        
        .nav-tabs::-webkit-scrollbar {
            display: none;
        }
        
        .nav-tabs .nav-item {
            margin-bottom: -1px;
        }
        
        .nav-tabs .nav-link {
            border: none;
            color: var(--text-muted);
            font-weight: 500;
            padding: 1rem 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            white-space: nowrap;
        }
        
        .nav-tabs .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            transition: all 0.3s ease;
            transform: translateX(-50%);
            border-radius: 3px 3px 0 0;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
        }
        
        .nav-tabs .nav-link:hover::after {
            width: 50%;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: transparent;
            font-weight: 600;
        }
        
        .nav-tabs .nav-link.active::after {
            width: 80%;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 0.5rem;
            transition: transform 0.3s ease;
        }
        
        .nav-tabs .nav-link:hover i,
        .nav-tabs .nav-link.active i {
            transform: scale(1.2);
        }
        
        /* Section Titles */
        .section-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid;
            border-image: linear-gradient(to right, var(--primary-color), var(--accent-color)) 1;
            display: inline-block;
        }
        
        /* Form Styling */
        .form-label {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
            background-color: white;
        }
        
        .form-select {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(226, 232, 240, 0.7);
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.8);
        }
        
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(99, 102, 241, 0.25);
            background-color: white;
        }
        
        .form-text {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-top: 0.5rem;
        }
        
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.875rem;
            color: var(--danger-color);
        }
        
        /* Card Styling */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(241, 245, 249, 0.8));
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            padding: 1.25rem 1.5rem;
        }
        
        .card-title {
            margin-bottom: 0;
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .card-title i {
            margin-right: 0.75rem;
            color: var(--primary-color);
            transition: transform 0.3s ease;
        }
        
        .card:hover .card-title i {
            transform: scale(1.2) rotate(10deg);
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* Item List Styling */
        .item-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 0.5rem;
        }
        
        .item-list::-webkit-scrollbar {
            width: 6px;
        }
        
        .item-list::-webkit-scrollbar-track {
            background: rgba(226, 232, 240, 0.5);
            border-radius: 10px;
        }
        
        .item-list::-webkit-scrollbar-thumb {
            background: rgba(99, 102, 241, 0.3);
            border-radius: 10px;
        }
        
        .item-list::-webkit-scrollbar-thumb:hover {
            background: rgba(99, 102, 241, 0.5);
        }
        
        .item-card {
            margin-bottom: 1rem;
            border-left: 3px solid #dee2e6;
            transition: all 0.3s ease;
        }
        
        .item-card:hover {
            transform: translateX(5px);
        }
        
        .item-card.pending {
            border-left-color: var(--warning-color);
        }
        
        .item-card.approved {
            border-left-color: var(--success-color);
        }
        
        .item-card.rejected {
            border-left-color: var(--danger-color);
        }
        
        /* Button Styling */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
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
            background: linear-gradient(to right, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.2));
            transition: all 0.4s ease;
            z-index: -1;
        }
        
        .btn:hover::before {
            left: 0;
        }
        
        .btn-primary {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border: none;
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-5px) scale(1.03);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.4);
            background: linear-gradient(to right, var(--primary-dark), var(--accent-dark));
        }
        
        .btn-outline-primary {
            color: var(--primary-color);
            border: 2px solid var(--primary-color);
            background: transparent;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border-color: transparent;
            color: white;
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }
        
        .btn i {
            margin-right: 0.5rem;
            transition: transform 0.3s ease;
        }
        
        .btn:hover i {
            transform: scale(1.2);
        }
        
        /* Badge Styling */
        .badge {
            padding: 0.5rem 0.75rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.8rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .bg-success {
            background: linear-gradient(to right, var(--success-color), #34d399) !important;
            color: white !important;
        }
        
        .bg-warning {
            background: linear-gradient(to right, var(--warning-color), #fbbf24) !important;
        }
        
        .bg-danger {
            background: linear-gradient(to right, var(--danger-color), #f87171) !important;
            color: white !important;
        }
        
        /* Alert Styling */
        .alert {
            border-radius: 15px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            border: none;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            position: relative;
            overflow: hidden;
        }
        
        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
        }
        
        .alert-success::before {
            background: var(--success-color);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }
        
        .alert-danger::before {
            background: var(--danger-color);
        }
        
        .alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
        }
        
        .alert-warning::before {
            background: var(--warning-color);
        }
        
        .alert-info {
            background: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }
        
        .alert-info::before {
            background: var(--info-color);
        }
        
        /* Rejection Reason Styling */
        .rejection-reason {
            background-color: rgba(239, 68, 68, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            position: relative;
            border-left: 3px solid var(--danger-color);
        }
        
        /* Footer Styling */
        footer {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: var(--text-color);
            padding: 3rem 0 0;
            margin-top: 3rem;
            position: relative;
            overflow: hidden;
            border-top: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .footer-content {
            position: relative;
            z-index: 1;
        }
        
        footer h5 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            position: relative;
            display: inline-block;
            color: var(--dark-color);
        }
        
        footer h5::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 40px;
            height: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            border-radius: 3px;
            transition: width 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        footer h5:hover::after {
            width: 100%;
        }
        
        .footer-links {
            list-style: none;
            padding-left: 0;
        }
        
        .footer-links li {
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .footer-links a {
            color: var(--text-muted);
            text-decoration: none;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: inline-block;
            font-weight: 500;
            position: relative;
            padding-left: 20px;
        }
        
        .footer-links a i {
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        
        .footer-links a:hover {
            color: var(--primary-color);
            transform: translateX(10px);
        }
        
        .footer-links a:hover i {
            color: var(--accent-color);
            transform: translateY(-50%) scale(1.2);
        }
        
        .social-icons {
            display: flex;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        
        .social-icons a {
            color: var(--text-color);
            font-size: 1.5rem;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 45px;
            height: 45px;
            background: rgba(248, 250, 252, 0.8);
            border-radius: 12px;
            position: relative;
            overflow: hidden;
            z-index: 1;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .social-icons a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }
        
        .social-icons a:hover::before {
            opacity: 1;
        }
        
        .social-icons a:hover {
            color: white;
            transform: translateY(-8px) rotate(10deg);
            box-shadow: 0 10px 20px rgba(99, 102, 241, 0.2);
        }
        
        .footer-bottom {
            background: rgba(248, 250, 252, 0.8);
            padding: 1.5rem 0;
            margin-top: 3rem;
            position: relative;
            z-index: 1;
            border-top: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .footer-bottom p {
            margin-bottom: 0;
            text-align: center;
            font-size: 0.95rem;
            color: var(--text-muted);
        }
        
        /* Code elements */
        .code-tag {
            font-family: var(--code-font);
            color: var(--primary-color);
            background-color: rgba(99, 102, 241, 0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 6px;
            font-size: 0.9rem;
            box-shadow: 0 2px 5px rgba(99, 102, 241, 0.1);
        }
        
        /* Responsive Styles */
        @media (max-width: 991.98px) {
            .settings-title {
                font-size: 1.8rem;
            }
            
            .settings-subtitle {
                font-size: 1rem;
            }
            
            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
            }
            
            .code-element {
                display: none;
            }
            
            .floating-elements {
                display: none;
            }
        }
        
        @media (max-width: 767.98px) {
            .settings-header {
                padding: 1.75rem;
            }
            
            .settings-content {
                padding: 1.5rem;
            }
            
            .settings-title {
                font-size: 1.6rem;
            }
            
            .nav-tabs .nav-link {
                padding: 0.6rem 0.8rem;
                font-size: 0.9rem;
            }
            
            .section-title {
                font-size: 1.2rem;
            }
            
            .card-header {
                padding: 1rem;
            }
            
            .card-body {
                padding: 1.25rem;
            }
            
            .shape {
                opacity: 0.05;
            }
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
        
        .delay-1 {
            animation-delay: 0.1s;
        }
        
        .delay-2 {
            animation-delay: 0.2s;
        }
        
        .delay-3 {
            animation-delay: 0.3s;
        }
        
        .delay-4 {
            animation-delay: 0.4s;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, var(--primary-light), var(--accent-light));
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, var(--primary-color), var(--accent-color));
        }
        
        /* Glowing Effect */
        .glow {
            position: relative;
        }
        
        .glow::after {
            content: '';
            position: absolute;
            top: -10px;
            left: -10px;
            right: -10px;
            bottom: -10px;
            z-index: -1;
            background: linear-gradient(45deg, var(--primary-color), var(--accent-color), var(--secondary-color));
            filter: blur(20px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .glow:hover::after {
            opacity: 0.15;
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
        <div class="shape shape-4" data-speed="1.2"></div>
        <div class="shape shape-5" data-speed="1.8"></div>
    </div>
    
    <!-- Particles -->
    <div class="particles" id="particles"></div>
    
    <!-- 3D Floating Elements -->
    <div class="floating-elements">
        <div class="floating-element floating-element-1">
            <div class="floating-cube">
                <div class="floating-cube-face">HTML</div>
                <div class="floating-cube-face">CSS</div>
                <div class="floating-cube-face">JS</div>
                <div class="floating-cube-face">React</div>
                <div class="floating-cube-face">Node</div>
                <div class="floating-cube-face">API</div>
            </div>
        </div>
        <div class="floating-element floating-element-2">
            <div class="floating-cube">
                <div class="floating-cube-face">Vue</div>
                <div class="floating-cube-face">Angular</div>
                <div class="floating-cube-face">Svelte</div>
                <div class="floating-cube-face">Next</div>
                <div class="floating-cube-face">Express</div>
                <div class="floating-cube-face">MongoDB</div>
            </div>
        </div>
        <div class="floating-element floating-element-3">
            <div class="floating-cube">
                <div class="floating-cube-face">TypeScript</div>
                <div class="floating-cube-face">GraphQL</div>
                <div class="floating-cube-face">Redux</div>
                <div class="floating-cube-face">Tailwind</div>
                <div class="floating-cube-face">Firebase</div>
                <div class="floating-cube-face">AWS</div>
            </div>
        </div>
    </div>
</div>

<!-- Code Elements -->
<div class="code-element code-element-1" data-rotate="-15deg">
    &lt;div class="settings"&gt;<br>
    &nbsp;&nbsp;&lt;header class="header"&gt;<br>
    &nbsp;&nbsp;&nbsp;&nbsp;&lt;h1&gt;Account Settings&lt;/h1&gt;<br>
    &nbsp;&nbsp;&lt;/header&gt;
</div>

<div class="code-element code-element-2" data-rotate="10deg">
    function updateProfile() {<br>
    &nbsp;&nbsp;const data = getFormData();<br>
    &nbsp;&nbsp;saveUserSettings(data);<br>
    &nbsp;&nbsp;return data;<br>
    }
</div>

<div class="code-element code-element-3" data-rotate="5deg">
    .settings-card {<br>
    &nbsp;&nbsp;display: flex;<br>
    &nbsp;&nbsp;flex-direction: column;<br>
    &nbsp;&nbsp;gap: 1.5rem;<br>
    }
</div>

<div class="code-element code-element-4" data-rotate="-5deg">
    import React, { useState, useEffect } from 'react';<br>
    &nbsp;&nbsp;const [isLoading, setIsLoading] = useState(false);<br>
    &nbsp;&nbsp;// Fetch user settings
</div>

<div class="code-element code-element-5" data-rotate="8deg">
    @keyframes float {<br>
    &nbsp;&nbsp;0%, 100% { transform: translateY(0); }<br>
    &nbsp;&nbsp;50% { transform: translateY(-20px); }<br>
    }
</div>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light">
    <div class="container">
        <a class="navbar-brand" href="#">
            <?php if (!empty($settings['site_image'])): ?>
                <img src="../uploads/<?php echo htmlspecialchars($settings['site_image']); ?>" alt="<?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?> Logo" style="height: 40px;">
            <?php else: ?>
                <span class="fw-bold"><?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></span>
            <?php endif; ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav mx-auto">
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="home-profile.php">
                        <i class="fas fa-home mb-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center active" href="expert-profile.php">
                        <i class="fas fa-user mb-1"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-consultations.php">
                        <i class="fas fa-laptop-code mb-1"></i> Consultations
                        <span class="notification-badge pending-consultations-badge"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-earnings.php">
                        <i class="fas fa-chart-line mb-1"></i> Earnings
                        <span class="notification-badge pending-withdrawals-badge"></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-avis.php">
                        <i class="fas fa-star mb-1"></i> Reviews

                        <span class="notification-badge reviews-badge"></span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link d-flex flex-column align-items-center" href="expert-contact.php">
                        <i class="fas fa-envelope mb-1"></i> Contact
                        <span class="notification-badge admin-messages-badge"></span>
                    </a>
                </li>
            </ul>
            <div class="nav-user-section">
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="position-relative">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($user['profile_image']); ?>" alt="Profile" class="rounded-circle me-2" style="width: 32px; height: 32px; object-fit: cover;">
                            <?php else: ?>
                                <i class="fas fa-user-circle me-2"></i>
                            <?php endif; ?>
                            <span class="notification-badge total-notifications-badge"></span>
                        </div>
                        <span class="d-none d-sm-inline"><?php echo htmlspecialchars($user['full_name']); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li><a class="dropdown-item" href="notifications.php"><i class="fa-solid fa-bell"></i> Notifications
                        <span class="notification-badge notifications-not-read-badge" style="margin-top: 10px;margin-right: 10px;"></span></a></li>
                    <li><a class="dropdown-item" href="expert-settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../config/logout.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container main-container">
    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success fade-in">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger fade-in">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <div class="settings-container fade-in glow">
        <div class="settings-header">
            <h1 class="settings-title">Account Settings</h1>
            <p class="settings-subtitle">Manage your personal information, qualifications, banking details, and security settings</p>
        </div>
        
        <div class="settings-content">
            <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab" aria-controls="personal" aria-selected="true">
                        <i class="fas fa-user"></i> Personal Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="certificates-tab" data-bs-toggle="tab" data-bs-target="#certificates" type="button" role="tab" aria-controls="certificates" aria-selected="false">
                        <i class="fas fa-certificate"></i> Certificates
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="experiences-tab" data-bs-toggle="tab" data-bs-target="#experiences" type="button" role="tab" aria-controls="experiences" aria-selected="false">
                        <i class="fas fa-briefcase"></i> Experiences
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="formations-tab" data-bs-toggle="tab" data-bs-target="#formations" type="button" role="tab" aria-controls="formations" aria-selected="false">
                        <i class="fas fa-graduation-cap"></i> Formations
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="banking-tab" data-bs-toggle="tab" data-bs-target="#banking" type="button" role="tab" aria-controls="banking" aria-selected="false">
                        <i class="fas fa-university"></i> Banking Details
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab" aria-controls="security" aria-selected="false">
                        <i class="fas fa-shield-alt"></i> Security
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="settingsTabsContent">
                <!-- Personal Information Tab -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel" aria-labelledby="personal-tab">
                    <div class="section-title">Personal Information</div>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="fade-in delay-1">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" disabled>
                                <div class="form-text">Full Name cannot be changed.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" pattern="^\+?[0-9]{12}$" value="<?php echo isset($user['phone']) ? htmlspecialchars($user['phone']) : ''; ?>">
                                <div class="form-text">Enter a valid phone number in international format (e.g., +213612345678)</div>
                            </div>
                            <div class="col-md-6">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo isset($user['address']) ? htmlspecialchars($user['address']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob" value="<?php echo isset($user['dob']) ? htmlspecialchars($user['dob']) : ''; ?>" disabled>
                                <div class="form-text">Date of birth cannot be changed.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender</label>
                                 <input type="text" class="form-control" id="gender" name="gender" value="<?php echo $user['gender'];?>" disabled>
                                
                                <div class="form-text">Gender cannot be changed.</div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" name="update_personal" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Certificates Tab -->
                <div class="tab-pane fade" id="certificates" role="tabpanel" aria-labelledby="certificates-tab">
                    <div class="section-title">Certificates</div>
                    
                    <?php if (!$profile_id): ?>
                        <div class="alert alert-warning fade-in">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You need to complete your expert profile before adding certificates.
                            <a href="profiledetails.php" class="alert-link">Complete your profile here</a>.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card fade-in delay-1 glow">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-plus-circle"></i> Add New Certificate</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="institution" class="form-label">Institution</label>
                                                <input type="text" class="form-control" id="institution" name="institution" required>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="cert_start_date" class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="cert_start_date" name="cert_start_date" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="cert_end_date" class="form-label">End Date</label>
                                                    <input type="date" class="form-control" id="cert_end_date" name="cert_end_date" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="cert_description" class="form-label">Description</label>
                                                <textarea class="form-control" id="cert_description" name="cert_description" rows="3" raquired></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="cert_file" class="form-label">Certificate File</label>
                                                <input type="file" class="form-control" id="cert_file" name="cert_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                                <div class="form-text">Upload a scanned copy of your certificate. Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" name="add_certificate" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Add Certificate
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card fade-in delay-2 glow">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-certificate"></i> Your Certificates</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="item-list">
                                            <?php if (empty($certificates)): ?>
                                                <p class="text-muted">You haven't added any certificates yet.</p>
                                            <?php else: ?>
                                                <?php foreach ($certificates as $cert): ?>
                                                    <div class="card item-card <?php echo isset($cert['status']) ? $cert['status'] : 'pending'; ?> mb-3">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <h6 class="card-subtitle"><?php echo htmlspecialchars($cert['institution']); ?></h6>
                                                                <?php echo getStatusBadge(isset($cert['status']) ? $cert['status'] : 'pending'); ?>
                                                            </div>
                                                            <p class="card-text small">
                                                                <?php 
                                                                    $start_date = new DateTime($cert['start_date']);
                                                                    $end_date = new DateTime($cert['end_date']);
                                                                    echo $start_date->format('M Y') . ' - ' . $end_date->format('M Y');
                                                                ?>
                                                            </p>
                                                            <p class="card-text"><?php echo htmlspecialchars($cert['description']); ?></p>
                                                            
                                                            <?php if (!empty($cert['file_path'])): ?>
                                                                <a href="<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-file-alt"></i> View Certificate
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (isset($cert['status']) && $cert['status'] == 'rejected' && !empty($cert['rejection_reason'])): ?>
                                                                <div class="rejection-reason mt-2">
                                                                    <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($cert['rejection_reason']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Experiences Tab -->
                <div class="tab-pane fade" id="experiences" role="tabpanel" aria-labelledby="experiences-tab">
                    <div class="section-title">Work Experiences</div>
                    
                    <?php if (!$profile_id): ?>
                        <div class="alert alert-warning fade-in">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You need to complete your expert profile before adding work experiences.
                            <a href="profiledetails.php" class="alert-link">Complete your profile here</a>.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card fade-in delay-1 glow">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-plus-circle"></i> Add New Experience</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="workplace" class="form-label">Workplace / Company</label>
                                                <input type="text" class="form-control" id="workplace" name="workplace" required>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="exp_start_date" class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="exp_start_date" name="exp_start_date" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="exp_end_date" class="form-label">End Date</label>
                                                    <input type="date" class="form-control" id="exp_end_date" name="exp_end_date" required>
                                                </div>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="duration_years" class="form-label">Duration (Years)</label>
                                                    <input type="number" class="form-control" id="duration_years" name="duration_years" min="0" value="0" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="duration_months" class="form-label">Duration (Months)</label>
                                                    <input type="number" class="form-control" id="duration_months" name="duration_months" min="0" max="11" value="0" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="exp_description" class="form-label">Description</label>
                                                <textarea class="form-control" id="exp_description" name="exp_description" rows="3" required></textarea>
                                                <div class="form-text">Describe your responsibilities and achievements in this role.</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="exp_file" class="form-label">Supporting Document</label>
                                                <input type="file" class="form-control" id="exp_file" name="exp_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                                <div class="form-text">Upload a document supporting your experience (e.g., employment certificate). Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" name="add_experience" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Add Experience
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card fade-in delay-2 glow">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-briefcase"></i> Your Work Experiences</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="item-list">
                                            <?php if (empty($experiences)): ?>
                                                <p class="text-muted">You haven't added any work experiences yet.</p>
                                            <?php else: ?>
                                                <?php foreach ($experiences as $exp): ?>
                                                    <div class="card item-card <?php echo isset($exp['status']) ? $exp['status'] : 'pending'; ?> mb-3">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <h6 class="card-subtitle"><?php echo htmlspecialchars($exp['workplace']); ?></h6>
                                                                <?php echo getStatusBadge(isset($exp['status']) ? $exp['status'] : 'pending'); ?>
                                                            </div>
                                                            <p class="card-text small">
                                                                <?php 
                                                                    $start_date = new DateTime($exp['start_date']);
                                                                    $end_date = new DateTime($exp['end_date']);
                                                                    echo $start_date->format('M Y') . ' - ' . $end_date->format('M Y');
                                                                ?>
                                                                <span class="ms-2 text-muted">
                                                                    (<?php echo $exp['duration_years']; ?> years, <?php echo $exp['duration_months']; ?> months)
                                                                </span>
                                                            </p>
                                                            <p class="card-text"><?php echo htmlspecialchars($exp['description']); ?></p>
                                                            
                                                            <?php if (!empty($exp['file_path'])): ?>
                                                                <a href="<?php echo htmlspecialchars($exp['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-file-alt"></i> View Document
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (isset($exp['status']) && $exp['status'] == 'rejected' && !empty($exp['rejection_reason'])): ?>
                                                                <div class="rejection-reason mt-2">
                                                                    <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($exp['rejection_reason']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Formations Tab -->
                <div class="tab-pane fade" id="formations" role="tabpanel" aria-labelledby="formations-tab">
                    <div class="section-title">Formations & Courses</div>
                    
                    <?php if (!$profile_id): ?>
                        <div class="alert alert-warning fade-in">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You need to complete your expert profile before adding formations or courses.
                            <a href="profiledetails.php" class="alert-link">Complete your profile here</a>.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card fade-in delay-1 glow">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-plus-circle"></i> Add New Formation/Course</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="formation_name" class="form-label">Formation/Course Name</label>
                                                <input type="text" class="form-control" id="formation_name" name="formation_name" required>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="formation_type" class="form-label">Type</label>
                                                    <select class="form-select" id="formation_type" name="formation_type" required>
                                                        <option value="">Select Type</option>
                                                        <option value="certificate">Certificate</option>
                                                        <option value="course">Course</option>
                                                        <option value="workshop">Workshop</option>
                                                        <option value="seminar">Seminar</option>
                                                        <option value="training">Training</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="formation_year" class="form-label">Year</label>
                                                    <input type="number" class="form-control" id="formation_year" name="formation_year" min="1900" max="<?php echo date('Y'); ?>" value="<?php echo date('Y'); ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="formation_description" class="form-label">Description</label>
                                                <textarea class="form-control" id="formation_description" name="formation_description" rows="3" required></textarea>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="formation_file" class="form-label">Supporting Document</label>
                                                <input type="file" class="form-control" id="formation_file" name="formation_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                                <div class="form-text">Upload a document supporting your formation/course (e.g., certificate). Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" name="add_formation" class="btn btn-primary">
                                                    <i class="fas fa-plus"></i> Add Formation/Course
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card fade-in delay-2 glow">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-graduation-cap"></i> Your Formations & Courses</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="item-list">
                                            <?php if (empty($formations)): ?>
                                                <p class="text-muted">You haven't added any formations or courses yet.</p>
                                            <?php else: ?>
                                                <?php foreach ($formations as $form): ?>
                                                    <div class="card item-card <?php echo isset($form['status']) ? $form['status'] : 'pending'; ?> mb-3">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <h6 class="card-subtitle"><?php echo htmlspecialchars($form['formation_name']); ?></h6>
                                                                <?php echo getStatusBadge(isset($form['status']) ? $form['status'] : 'pending'); ?>
                                                            </div>
                                                            <p class="card-text small">
                                                                <?php echo htmlspecialchars(ucfirst($form['formation_type'])); ?> - 
                                                                <?php echo htmlspecialchars($form['formation_year']); ?>
                                                            </p>
                                                            <p class="card-text"><?php echo htmlspecialchars($form['description']); ?></p>
                                                            
                                                            <?php if (!empty($form['file_path'])): ?>
                                                                <a href="<?php echo htmlspecialchars($form['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                    <i class="fas fa-file-alt"></i> View Document
                                                                </a>
                                                            <?php endif; ?>
                                                            
                                                            <?php if (isset($form['status']) && $form['status'] == 'rejected' && !empty($form['rejection_reason'])): ?>
                                                                <div class="rejection-reason mt-2">
                                                                    <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($form['rejection_reason']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Banking Details Tab -->
                <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">
                    <div class="section-title">Banking Information</div>
                    
                    <?php if (!$profile_id): ?>
                        <div class="alert alert-warning fade-in">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            You need to complete your expert profile before adding banking information.
                            <a href="profiledetails.php" class="alert-link">Complete your profile here</a>.
                        </div>
                    <?php else: ?>
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" class="fade-in delay-1">
                            <div class="card glow mb-4">
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="ccp" class="form-label">CCP Number</label>
                                            <input type="text" class="form-control" id="ccp" name="ccp" value="<?php echo isset($banking['ccp']) ? htmlspecialchars($banking['ccp']) : ''; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="ccp_key" class="form-label">CCP Key</label>
                                            <input type="text" class="form-control" id="ccp_key" name="ccp_key" value="<?php echo isset($banking['ccp_key']) ? htmlspecialchars($banking['ccp_key']) : ''; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="check_file" class="form-label">Check File</label>
                                        <input type="file" class="form-control" id="check_file" name="check_file" accept=".jpg,.jpeg,.png,.pdf" <?php echo !isset($banking['check_file_path']) ? 'required' : ''; ?>>
                                        <div class="form-text">Upload a scanned copy of your check. Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                        <?php if (isset($banking['check_file_path']) && !empty($banking['check_file_path'])): ?>
                                            <div class="mt-2">
                                                <a href="<?php echo htmlspecialchars($banking['check_file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-file-alt"></i> View Current Check
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="row mb-3">
                                       
                                        <div class="col-md-4">
                                            <label for="consultation_minutes" class="form-label">Consultation Duration (Minutes)</label>
                                            <input type="number" class="form-control" id="consultation_minutes" name="consultation_minutes" min="5" step="5" value="<?php echo isset($banking['consultation_minutes']) ? htmlspecialchars($banking['consultation_minutes']) : '30'; ?>" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label for="consultation_price" class="form-label">Consultation Price (DZD)</label>
                                            <input type="number" class="form-control" id="consultation_price" name="consultation_price" min="100" step="100" value="<?php echo isset($banking['consultation_price']) ? htmlspecialchars($banking['consultation_price']) : '1000'; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <?php if (isset($banking) && isset($profile['banking_status'])): ?>
                                        <div class="mb-3">
                                            <div class="alert alert-info">
                                                <div class="d-flex align-items-center">
                                                    <div>Current Status: </div>
                                                    <div class="ms-2"><?php echo getStatusBadge($profile['banking_status']); ?></div>
                                                </div>
                                                
                                                <?php if ($profile['banking_status'] == 'rejected' && !empty($profile['banking_feedback'])): ?>
                                                    <div class="mt-2">
                                                        <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($profile['banking_feedback']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="submit" name="update_banking" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Banking Information
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                
                <!-- Security Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel" aria-labelledby="security-tab">
                    <div class="section-title">Change Password</div>
                    
                    <?php if (!empty($password_error)): ?>
                        <div class="alert alert-danger fade-in">
                            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $password_error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="fade-in delay-1">
                        <div class="card glow mb-4">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    <?php if (!empty($current_password_error)): ?>
                                        <div class="invalid-feedback"><?php echo $current_password_error; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <div class="form-text">Password must be at least 8 characters long.</div>
                                    <?php if (!empty($new_password_error)): ?>
                                        <div class="invalid-feedback"><?php echo $new_password_error; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    <?php if (!empty($confirm_password_error)): ?>
                                        <div class="invalid-feedback"><?php echo $confirm_password_error; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
                            <button type="submit" name="change_password" class="btn btn-primary">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </form>
                    
                    <div class="section-title">Account Security</div>
                    
                    <div class="card fade-in delay-2 glow">
                        <div class="card-header">
                            <h5 class="card-title"><i class="fas fa-shield-alt"></i> Security Tips</h5>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li>Use a strong, unique password for your account.</li>
                                <li>Never share your password with anyone.</li>
                                <li>Change your password regularly.</li>
                                <li>Make sure your email account is secure.</li>
                                <li>Log out when using shared computers.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="container footer-content">
        <div class="row">
            <div class="col-md-4 mb-4 mb-md-0">
                <h5>About <?php echo htmlspecialchars($settings['site_name'] ?? 'Consult Pro'); ?></h5>
                <p class="mb-4"><?php echo htmlspecialchars($settings['site_description'] ?? 'Expert Consultation Platform connecting experts with clients for professional consultations.'); ?></p>
            </div>
            <div class="col-md-4 mb-4 mb-md-0">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="home-profile.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="expert-profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                    <li><a href="expert-consultations.php"><i class="fas fa-laptop-code"></i> Consultations</a></li>
                    <li><a href="expert-earnings.php"><i class="fas fa-chart-line"></i> Earnings</a></li>
                    <li><a href="expert-avis.php"><i class="fas fa-star"></i> Reviews</a></li>
                    <li><a href="expert-contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                </ul>
            </div>
            <div class="col-md-4">
                <h5>Contact</h5>
                <ul class="footer-links">
                    <?php if (!empty($settings['site_name'])): ?>
                        <li><i class="fas fa-building me-2"></i> <?php echo htmlspecialchars($settings['site_name']); ?></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['site_email'])): ?>
                        <li><a href="mailto:<?php echo htmlspecialchars($settings['site_email']); ?>"><i class="fas fa-envelope me-2"></i> <?php echo htmlspecialchars($settings['site_email']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['phone_number1'])): ?>
                        <li><a href="tel:<?php echo htmlspecialchars($settings['phone_number1']); ?>"><i class="fas fa-phone me-2"></i> <?php echo htmlspecialchars($settings['phone_number1']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['phone_number2'])): ?>
                        <li><a href="tel:<?php echo htmlspecialchars($settings['phone_number2']); ?>"><i class="fas fa-phone-alt me-2"></i> <?php echo htmlspecialchars($settings['phone_number2']); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['facebook_url'])): ?>
                        <li><a href="<?php echo htmlspecialchars($settings['facebook_url']); ?>" target="_blank"><i class="fab fa-facebook me-2"></i> Facebook</a></li>
                    <?php endif; ?>
                    <?php if (!empty($settings['instagram_url'])): ?>
                        <li><a href="<?php echo htmlspecialchars($settings['instagram_url']); ?>" target="_blank"><i class="fab fa-instagram me-2"></i> Instagram</a></li>
                    <?php endif; ?>
                </ul>
                <p class="mt-3 mb-0">Need help? <a href="expert-contact.php" class="text-primary font-weight-bold">Contact Us</a></p>
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo isset($settings['site_name']) ? htmlspecialchars($settings['site_name']) : ' '; ?>. All rights reserved. </p>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Initialize animations and effects
    document.addEventListener('DOMContentLoaded', function() {
        // Fetch notifications function
    function fetchNotifications() {
        fetch('expert-settings.php?fetch_notifications=true')
            .then(response => response.json())
            .then(data => {
                // Update notification badges
                updateNotificationBadge('.pending-consultations-badge', data.pending_consultations);
                updateNotificationBadge('.pending-withdrawals-badge', data.pending_withdrawals);
                updateNotificationBadge('.admin-messages-badge', data.admin_messages);
                updateNotificationBadge('.community-messages-badge', data.community_messages);
                updateNotificationBadge('.forums_messages-badge', data.forums_messages);
                updateNotificationBadge('.reviews-badge', data.reviews);
                updateNotificationBadge('.notifications-not-read-badge', data.notifications_not_read);
                updateNotificationBadge('.total-notifications-badge', data.total);
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }
    
    // Update notification badge function
    function updateNotificationBadge(selector, count) {
        const badge = document.querySelector(selector);
        if (badge) {
            if (count > 0) {
                badge.textContent = count > 99 ? '99+' : count;
                badge.classList.add('show');
            } else {
                badge.textContent = '';
                badge.classList.remove('show');
            }
        }
    }
    
    // Initial fetch
    fetchNotifications();
    
    // Set interval to fetch notifications every second
    setInterval(fetchNotifications, 1000);
    
        // Add animation classes on scroll
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in');
                }
            });
        }, {
            threshold: 0.1
        });
        
        document.querySelectorAll('.card, .settings-container').forEach(el => {
            observer.observe(el);
        });
        
        // Create floating code elements
        function createRandomCodeElements() {
            const codeTexts = [
                'const app = createApp();',
                'function updateSettings() { }',
                'import { useState } from "react";',
                '.settings { display: flex; }',
                'export default Settings;',
                'async function saveData() { }',
                '<div className="profile">',
                'npm install @vercel/earnings',
                'git commit -m "Update settings"',
                'const [data, setData] = useState(null);'
            ];
            
            const container = document.querySelector('.background-container');
            
            for (let i = 0; i < 5; i++) {
                const element = document.createElement('div');
                element.className = 'code-element';
                element.style.top = Math.random() * 100 + '%';
                element.style.left = Math.random() * 100 + '%';
                element.style.transform = `rotate(${Math.random() * 20 - 10}deg)`;
                element.style.opacity = 0.1 + Math.random() * 0.1;
                element.style.animation = `fadeInOut ${8 + Math.random() * 8}s infinite ease-in-out ${Math.random() * 5}s`;
                element.textContent = codeTexts[Math.floor(Math.random() * codeTexts.length)];
                container.appendChild(element);
            }
        }
        
        // Create particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Random position
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                
                // Random size
                const size = Math.random() * 4 + 2;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                
                // Random color
                const colors = [
                    'rgba(99, 102, 241, 0.3)',
                    'rgba(139, 92, 246, 0.3)',
                    'rgba(6, 182, 212, 0.3)',
                    'rgba(16, 185, 129, 0.3)'
                ];
                particle.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                
                // Random animation
                const tx = (Math.random() - 0.5) * 300;
                const ty = (Math.random() - 0.5) * 300;
                particle.style.setProperty('--tx', tx + 'px');
                particle.style.setProperty('--ty', ty + 'px');
                
                // Random animation duration
                const duration = Math.random() * 15 + 10;
                particle.style.animationDuration = duration + 's';
                
                // Random animation delay
                const delay = Math.random() * 5;
                particle.style.animationDelay = delay + 's';
                
                particlesContainer.appendChild(particle);
            }
        }
        
        // Only create these elements on desktop
        if (window.innerWidth > 768) {
            createRandomCodeElements();
            createParticles();
        }
        
        // Add subtle parallax effect to background elements
        if (window.innerWidth > 768) {
            document.addEventListener('mousemove', (e) => {
                const moveX = (e.clientX - window.innerWidth / 2) / 30;
                const moveY = (e.clientY - window.innerHeight / 2) / 30;
                
                document.querySelectorAll('.shape').forEach((shape) => {
                    const speed = parseFloat(shape.getAttribute('data-speed') || 1);
                    shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
                });
                
                document.querySelectorAll('.code-element').forEach((element) => {
                    const speed = 0.8;
                    const rotate = element.getAttribute('data-rotate') || '0deg';
                    element.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px) rotate(${rotate})`;
                });
                
                document.querySelectorAll('.floating-element').forEach((element) => {
                    const speed = 1.2;
                    element.style.transform = `translateX(${moveX * speed}px) translateY(${moveY * speed}px) rotateX(${moveY}deg) rotateY(${-moveX}deg)`;
                });
            });
        }
        
        // Show active tab based on URL hash
        const hash = window.location.hash;
        if (hash) {
            const tab = document.querySelector(`[data-bs-target="${hash}"]`);
            if (tab) {
                const tabInstance = new bootstrap.Tab(tab);
                tabInstance.show();
            }
        }
        
        // Update URL hash when tab changes
        const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabs.forEach(tab => {
            tab.addEventListener('shown.bs.tab', function(event) {
                const target = event.target.getAttribute('data-bs-target');
                window.location.hash = target;
            });
        });
        
        // Calculate duration automatically when dates change
        const startDateInput = document.getElementById('exp_start_date');
        const endDateInput = document.getElementById('exp_end_date');
        const durationYearsInput = document.getElementById('duration_years');
        const durationMonthsInput = document.getElementById('duration_months');
        
        if (startDateInput && endDateInput && durationYearsInput && durationMonthsInput) {
            function calculateDuration() {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                if (startDate && endDate && !isNaN(startDate) && !isNaN(endDate)) {
                    let months = (endDate.getFullYear() - startDate.getFullYear()) * 12;
                    months -= startDate.getMonth();
                    months += endDate.getMonth();
                    
                    const years = Math.floor(months / 12);
                    const remainingMonths = months % 12;
                    
                    durationYearsInput.value = years;
                    durationMonthsInput.value = remainingMonths;
                }
            }
            
            startDateInput.addEventListener('change', calculateDuration);
            endDateInput.addEventListener('change', calculateDuration);
        }
    });

    // Phone number validation
    const phoneInput = document.getElementById('phone');
    phoneInput.addEventListener('input', function() {
        const phoneRegex = /^\+?[0-9]{12}$/;
        if (this.value && !phoneRegex.test(this.value)) {
            this.setCustomValidity('Please enter a valid phone number in international format (e.g., +213612345678)');
            this.classList.add('is-invalid');
        } else {
            this.setCustomValidity('');
            this.classList.remove('is-invalid');
        }
    });

    // Date validation to prevent future dates
    function validateDates() {
        const today = new Date();
        today.setHours(0, 0, 0, 0); // Set to beginning of today
        
        // Format today as YYYY-MM-DD for comparison with input values
        const todayFormatted = today.toISOString().split('T')[0];
        
        // Get all date inputs
        const dateInputs = document.querySelectorAll('input[type="date"]');
        
        dateInputs.forEach(input => {
            // Set max attribute to today
            input.setAttribute('max', todayFormatted);
            
            // Add validation on change
            input.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                if (selectedDate > today) {
                    this.setCustomValidity('Date cannot be in the future');
                    this.classList.add('is-invalid');
                    
                    // Add error message
                    let errorMessage = this.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('invalid-feedback')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'invalid-feedback';
                        this.parentNode.appendChild(errorMessage);
                    }
                    errorMessage.textContent = 'Date cannot be in the future';
                    errorMessage.style.display = 'block';
                } else {
                    this.setCustomValidity('');
                    this.classList.remove('is-invalid');
                    
                    // Remove error message if exists
                    const errorMessage = this.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('invalid-feedback')) {
                        errorMessage.style.display = 'none';
                    }
                }
            });
        });
        
        // Add special validation for end dates to ensure they're not before start dates
        const startEndPairs = [
            { start: 'cert_start_date', end: 'cert_end_date' },
            { start: 'exp_start_date', end: 'exp_end_date' }
        ];
        
        startEndPairs.forEach(pair => {
            const startInput = document.getElementById(pair.start);
            const endInput = document.getElementById(pair.end);
            
            if (startInput && endInput) {
                endInput.addEventListener('change', function() {
                    if (startInput.value && this.value && this.value < startInput.value) {
                        this.setCustomValidity('End date cannot be before start date');
                        this.classList.add('is-invalid');
                        
                        // Add error message
                        let errorMessage = this.nextElementSibling;
                        if (!errorMessage || !errorMessage.classList.contains('invalid-feedback')) {
                            errorMessage = document.createElement('div');
                            errorMessage.className = 'invalid-feedback';
                            this.parentNode.appendChild(errorMessage);
                        }
                        errorMessage.textContent = 'End date cannot be before start date';
                        errorMessage.style.display = 'block';
                    }
                });
                
                startInput.addEventListener('change', function() {
                    if (endInput.value && this.value && endInput.value < this.value) {
                        endInput.setCustomValidity('End date cannot be before start date');
                        endInput.classList.add('is-invalid');
                        
                        // Add error message
                        let errorMessage = endInput.nextElementSibling;
                        if (!errorMessage || !errorMessage.classList.contains('invalid-feedback')) {
                            errorMessage = document.createElement('div');
                            errorMessage.className = 'invalid-feedback';
                            endInput.parentNode.appendChild(errorMessage);
                        }
                        errorMessage.textContent = 'End date cannot be before start date';
                        errorMessage.style.display = 'block';
                    } else {
                        endInput.setCustomValidity('');
                        endInput.classList.remove('is-invalid');
                        
                        // Remove error message if exists
                        const errorMessage = endInput.nextElementSibling;
                        if (errorMessage && errorMessage.classList.contains('invalid-feedback')) {
                            errorMessage.style.display = 'none';
                        }
                    }
                });
            }
        });
    }

    // Call the validation function when the document is ready
    document.addEventListener('DOMContentLoaded', function() {
        validateDates();
    });
</script>
</body>
</html>
