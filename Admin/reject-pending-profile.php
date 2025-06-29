<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';
require_once 'utils/mailer.php'; // This will contain the sendVerificationEmail function

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in, redirect to login page
    header("Location: ../pages/login.php");
    exit();
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];
$success_message = "";
$error_message = "";

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
    
    return $data;
}

// Get user data
$sql = "SELECT u.*, up.phone, up.address, up.dob, up.gender, up.profile_image,
        ep.*, c.name AS category_name, sc.name AS subcategory_name
        FROM users u 
        LEFT JOIN user_profiles up ON u.id = up.user_id 
        LEFT JOIN expert_profiledetails ep ON u.id = ep.user_id 
        LEFT JOIN categories c ON ep.category = c.id
        LEFT JOIN subcategories sc ON ep.subcategory = sc.id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$full_name = $user["full_name"];
// Fetch skills
$skills_query = "SELECT sk.*,ep.* FROM skills sk
                JOIN expert_profiledetails ep ON sk.profile_id = ep.id
                WHERE ep.user_id = ?";
$stmt = $conn->prepare($skills_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$skills_result = $stmt->get_result();
$skills = [];
while ($row = $skills_result->fetch_assoc()) {
    $skills[] = $row['skill_name'];
}
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
        // Ensure description key exists
        if (!isset($row['description'])) {
            $row['description'] = '';
        }
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
        // Ensure description key exists
        if (!isset($row['description'])) {
            $row['description'] = '';
        }
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
        // Ensure description key exists
        if (!isset($row['description'])) {
            $row['description'] = '';
        }
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

// Handle certificate addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_certificate"])) {
    $institution = sanitize_input($_POST["institution"]);
    $start_date = sanitize_input($_POST["start_date"]);
    $end_date = sanitize_input($_POST["end_date"]);
    $description = sanitize_input($_POST["cert_description"]);
    
    // Validate that end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "End date cannot be before start date.";
    } else {
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
            // Insert certificate
            $insert_cert_sql = "INSERT INTO certificates (profile_id, institution, start_date, end_date, description, file_path, status) 
                               VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $insert_cert_stmt = $conn->prepare($insert_cert_sql);
            $insert_cert_stmt->bind_param("isssss", $profile_id, $institution, $start_date, $end_date, $description, $file_path);
            
            if ($insert_cert_stmt->execute()) {
                $success_message = "Certificate added successfully! It will be reviewed by an administrator.";
                
                // Create notification for admin
                $notification_type = "certificate_added";
                $message = "New certificate added by $full_name and needs review";
                
                $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
                $insert_notif_stmt = $conn->prepare($insert_notif_sql);
                $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
                $insert_notif_stmt->execute();
                
                // Update profile status
                $update_status_sql = "UPDATE expert_profiledetails SET certificates_status = 'pending_review' WHERE id = ?";
                $update_status_stmt = $conn->prepare($update_status_sql);
                $update_status_stmt->bind_param("i", $profile_id);
                $update_status_stmt->execute();
                
                // Refresh certificates data
                $cert_stmt->execute();
                $cert_result = $cert_stmt->get_result();
                $certificates = [];
                while ($row = $cert_result->fetch_assoc()) {
                    if (!isset($row['description'])) {
                        $row['description'] = '';
                    }
                    $certificates[] = $row;
                }
            } else {
                $error_message = "Error adding certificate.";
            }
        }
    }
}

// Handle certificate update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_certificate"])) {
    $cert_id = sanitize_input($_POST["cert_id"]);
    $institution = sanitize_input($_POST["institution"]);
    $start_date = sanitize_input($_POST["start_date"]);
    $end_date = sanitize_input($_POST["end_date"]);
    $description = sanitize_input($_POST["cert_description"]);

    // Validate that end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "End date cannot be before start date.";
    } else {
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
            // Update certificate
            $update_cert_sql = "UPDATE certificates SET institution = ?, start_date = ?, end_date = ?, description = ?, status = 'pending'";
            
            // Add file path to update if a new file was uploaded
            $params = [$institution, $start_date, $end_date, $description];
            $types = "ssss";
            
            if (!empty($file_path)) {
                $update_cert_sql .= ", file_path = ?";
                $params[] = $file_path;
                $types .= "s";
            }
            
            $update_cert_sql .= " WHERE id = ? AND profile_id = ?";
            $params[] = $cert_id;
            $params[] = $profile_id;
            $types .= "ii";
            
            $update_cert_stmt = $conn->prepare($update_cert_sql);
            $update_cert_stmt->bind_param($types, ...$params);
            
            if ($update_cert_stmt->execute()) {
                $success_message = "Certificate updated successfully! It will be reviewed by an administrator.";
                
                // Create notification for admin
                $notification_type = "certificate_updated";
                $message = "Certificate updated by $full_name and needs review";
                
                $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
                $insert_notif_stmt = $conn->prepare($insert_notif_sql);
                $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
                $insert_notif_stmt->execute();
                
                // Update profile status
                $update_status_sql = "UPDATE expert_profiledetails SET certificates_status = 'pending_review' WHERE id = ?";
                $update_status_stmt = $conn->prepare($update_status_sql);
                $update_status_stmt->bind_param("i", $profile_id);
                $update_status_stmt->execute();
                
                // Refresh certificates data
                $cert_stmt->execute();
                $cert_result = $cert_stmt->get_result();
                $certificates = [];
                while ($row = $cert_result->fetch_assoc()) {
                    if (!isset($row['description'])) {
                        $row['description'] = '';
                    }
                    $certificates[] = $row;
                }
            } else {
                $error_message = "Error updating certificate.";
            }
        }
    }
}

// Handle experience addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_experience"])) {
    $workplace = sanitize_input($_POST["workplace"]);
    $start_date = sanitize_input($_POST["start_date"]);
    $end_date = sanitize_input($_POST["end_date"]);
    $duration_years = intval(sanitize_input($_POST["duration_years"]));
    $duration_months = intval(sanitize_input($_POST["duration_months"]));
    $description = sanitize_input($_POST["exp_description"]);
    
    // Validate that end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "End date cannot be before start date.";
    } else {
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
            // Insert experience
            $insert_exp_sql = "INSERT INTO experiences (profile_id, workplace, start_date, end_date, duration_years, duration_months, description, file_path, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $insert_exp_stmt = $conn->prepare($insert_exp_sql);
            $insert_exp_stmt->bind_param("isssiiis", $profile_id, $workplace, $start_date, $end_date, $duration_years, $duration_months, $description, $file_path);
            
            if ($insert_exp_stmt->execute()) {
                $success_message = "Experience added successfully! It will be reviewed by an administrator.";
                
                // Create notification for admin
                $notification_type = "experience_added";
                $message = "New experience added by $full_name and needs review";
                
                $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
                $insert_notif_stmt = $conn->prepare($insert_notif_sql);
                $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
                $insert_notif_stmt->execute();
                
                // Update profile status
                $update_status_sql = "UPDATE expert_profiledetails SET experiences_status = 'pending_review' WHERE id = ?";
                $update_status_stmt = $conn->prepare($update_status_sql);
                $update_status_stmt->bind_param("i", $profile_id);
                $update_status_stmt->execute();
                
                // Refresh experiences data
                $exp_stmt->execute();
                $exp_result = $exp_stmt->get_result();
                $experiences = [];
                while ($row = $exp_result->fetch_assoc()) {
                    if (!isset($row['description'])) {
                        $row['description'] = '';
                    }
                    $experiences[] = $row;
                }
            } else {
                $error_message = "Error adding experience.";
            }
        }
    }
}

// Handle experience update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_experience"])) {
    $exp_id = sanitize_input($_POST["exp_id"]);
    $workplace = sanitize_input($_POST["workplace"]);
    $start_date = sanitize_input($_POST["start_date"]);
    $end_date = sanitize_input($_POST["end_date"]);
    $duration_years = intval(sanitize_input($_POST["duration_years"]));
    $duration_months = intval(sanitize_input($_POST["duration_months"]));
    $description = sanitize_input($_POST["exp_description"]);
    
    // Validate that end date is not before start date
    if (strtotime($end_date) < strtotime($start_date)) {
        $error_message = "End date cannot be before start date.";
    } else {
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
            // Update experience
            $update_exp_sql = "UPDATE experiences SET workplace = ?, start_date = ?, end_date = ?, duration_years = ?, duration_months = ?, description = ?, status = 'pending'";
            
            // Add file path to update if a new file was uploaded
            $params = [$workplace, $start_date, $end_date, $duration_years, $duration_months, $description];
            $types = "sssiss";
            
            if (!empty($file_path)) {
                $update_exp_sql .= ", file_path = ?";
                $params[] = $file_path;
                $types .= "s";
            }
            
            $update_exp_sql .= " WHERE id = ? AND profile_id = ?";
            $params[] = $exp_id;
            $params[] = $profile_id;
            $types .= "ii";
            
            $update_exp_stmt = $conn->prepare($update_exp_sql);
            $update_exp_stmt->bind_param($types, ...$params);
            
            if ($update_exp_stmt->execute()) {
                $success_message = "Experience updated successfully! It will be reviewed by an administrator.";
                
                // Create notification for admin
                $notification_type = "experience_updated";
                $message = "Experience updated by $full_name and needs review";
                
                $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
                $insert_notif_stmt = $conn->prepare($insert_notif_sql);
                $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
                $insert_notif_stmt->execute();
                
                // Update profile status
                $update_status_sql = "UPDATE expert_profiledetails SET experiences_status = 'pending_review' WHERE id = ?";
                $update_status_stmt = $conn->prepare($update_status_sql);
                $update_status_stmt->bind_param("i", $profile_id);
                $update_status_stmt->execute();
                
                // Refresh experiences data
                $exp_stmt->execute();
                $exp_result = $exp_stmt->get_result();
                $experiences = [];
                while ($row = $exp_result->fetch_assoc()) {
                    if (!isset($row['description'])) {
                        $row['description'] = '';
                    }
                    $experiences[] = $row;
                }
            } else {
                $error_message = "Error updating experience.";
            }
        }
    }
}

// Handle formation addition
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["add_formation"])) {
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
        // Insert formation
        $insert_form_sql = "INSERT INTO formations (profile_id, formation_name, formation_type, formation_year, description, file_path, status) 
                           VALUES (?, ?, ?, ?, ?, ?, 'pending')";
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
            
            // Update profile status
            $update_status_sql = "UPDATE expert_profiledetails SET formations_status = 'pending_review' WHERE id = ?";
            $update_status_stmt = $conn->prepare($update_status_sql);
            $update_status_stmt->bind_param("i", $profile_id);
            $update_status_stmt->execute();
            
            // Refresh formations data
            $form_stmt->execute();
            $form_result = $form_stmt->get_result();
            $formations = [];
            while ($row = $form_result->fetch_assoc()) {
                if (!isset($row['description'])) {
                    $row['description'] = '';
                }
                $formations[] = $row;
            }
        } else {
            $error_message = "Error adding formation.";
        }
    }
}

// Handle formation update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["update_formation"])) {
    $form_id = sanitize_input($_POST["form_id"]);
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
        // Update formation
        $update_form_sql = "UPDATE formations SET formation_name = ?, formation_type = ?, formation_year = ?, description = ?, status = 'pending'";
        
        // Add file path to update if a new file was uploaded
        $params = [$formation_name, $formation_type, $formation_year, $description];
        $types = "ssis";
        
        if (!empty($file_path)) {
            $update_form_sql .= ", file_path = ?";
            $params[] = $file_path;
            $types .= "s";
        }
        
        $update_form_sql .= " WHERE id = ? AND profile_id = ?";
        $params[] = $form_id;
        $params[] = $profile_id;
        $types .= "ii";
        
        $update_form_stmt = $conn->prepare($update_form_sql);
        $update_form_stmt->bind_param($types, ...$params);
        
        if ($update_form_stmt->execute()) {
            $success_message = "Formation updated successfully! It will be reviewed by an administrator.";
            
            // Create notification for admin
            $notification_type = "formation_updated";
            $message = "Formation updated by $full_name and needs review";
            
            $insert_notif_sql = "INSERT INTO admin_notifications (user_id, profile_id, notification_type, message) VALUES (?, ?, ?, ?)";
            $insert_notif_stmt = $conn->prepare($insert_notif_sql);
            $insert_notif_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $message);
            $insert_notif_stmt->execute();
            
            // Update profile status
            $update_status_sql = "UPDATE expert_profiledetails SET formations_status = 'pending_review' WHERE id = ?";
            $update_status_stmt = $conn->prepare($update_status_sql);
            $update_status_stmt->bind_param("i", $profile_id);
            $update_status_stmt->execute();
            
            // Refresh formations data
            $form_stmt->execute();
            $form_result = $form_stmt->get_result();
            $formations = [];
            while ($row = $form_result->fetch_assoc()) {
                if (!isset($row['description'])) {
                    $row['description'] = '';
                }
                $formations[] = $row;
            }
        } else {
            $error_message = "Error updating formation.";
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
            $update_banking_stmt->bind_param("sssiii", $ccp, $ccp_key, $check_file_path, $consultation_minutes, $consultation_price, $banking['id']);
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
        } else {
            // Insert new banking information
            $insert_banking_sql = "INSERT INTO banking_information (user_id, profile_id, ccp, ccp_key, check_file_path, consultation_minutes, consultation_price) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_banking_stmt = $conn->prepare($insert_banking_sql);
            $insert_banking_stmt->bind_param("iisssii", $user_id, $profile_id, $ccp, $ccp_key, $check_file_path, $consultation_minutes, $consultation_price);
            $update_banking_result = $insert_banking_stmt->execute();
            
            // Create notification for admin
            $notification_type = "banking_added";
            $message = "$full_name : This Expert added banking information and needs review";
            
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
        } else {
            $error_message = "Error updating banking information.";
        }
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
$pending_consultations = $conn->prepare("SELECT COUNT(*) as count FROM consultations WHERE expert_id = ? AND status = 'pending'");
$pending_consultations->bind_param("i", $user_id);
$pending_consultations->execute();
$pending_consultations_result = $pending_consultations->get_result();
$pending_consultations_count = $pending_consultations_result->fetch_assoc()['count'];
$pending_consultations->close();

$reviews_not_read = $conn->prepare("SELECT COUNT(*) as count FROM expert_ratings WHERE is_read = 0 AND expert_id = ?");
$reviews_not_read->bind_param("i", $user_id);
$reviews_not_read->execute();
$reviews_not_read_result = $reviews_not_read->get_result();
$reviews_not_read_count = $reviews_not_read_result->fetch_assoc()['count'];
$reviews_not_read->close();

$notifications_not_read = $conn->prepare("SELECT COUNT(*) as count FROM expert_notifications WHERE is_read = 0 AND user_id = ? ");
$notifications_not_read->bind_param("i", $user_id);
$notifications_not_read->execute();
$notifications_not_read_result = $notifications_not_read->get_result();
$notifications_not_read_count = $notifications_not_read_result->fetch_assoc()['count'];
$notifications_not_read->close();

// Handle AJAX request for notifications
if (isset($_GET['fetch_notifications'])) {
    $response = [
        'pending_consultations' => $pending_consultations_count,
        'pending_withdrawals' => $pending_withdrawals_count,
        'admin_messages' => $admin_messages_count,
        'reviews' => $reviews_not_read_count,
        'notifications_not_read' => $notifications_not_read_count,
        'total' => $pending_consultations_count + $pending_withdrawals_count + $admin_messages_count + $reviews_not_read_count + $notifications_not_read_count,
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Close database connection
$conn->close();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Status - Consult Pro</title>
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
        
        /* Status Message Styles */
        .status-message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .status-message i {
            font-size: 24px;
            margin-right: 15px;
        }
        
        .status-pending {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning-color);
            border-left: 4px solid var(--warning-color);
        }
        
        .status-rejected {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
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
        
        /* User Info Card Styling */
        .user-info-card {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .user-info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .user-info-header {
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
            color: white;
            padding: 1.25rem;
            position: relative;
        }
        
        .user-info-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0;
        }
        
        .user-info-body {
            padding: 1.5rem;
        }
        
        .user-info-item {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
        }
        
        .user-info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .user-info-label {
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        .user-info-value {
            font-weight: 500;
            color: var(--text-color);
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

        /* Modal Fixes */
        .modal {
            padding-right: 0 !important;
            position: relative;
        }

        .modal-open {
            overflow: auto !important;
            padding-right: 0 !important;
        }

        .modal-backdrop {
            opacity: 0.5;
            position: relative;
        }

        .modal-dialog {
            margin: 1.75rem auto;
            animation: modalFadeIn 0.3s ease-out forwards;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-content {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            border-bottom: 1px solid rgba(226, 232, 240, 0.7);
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(241, 245, 249, 0.8));
            border-radius: 15px 15px 0 0;
            padding: 1.25rem 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(226, 232, 240, 0.7);
            border-radius: 0 0 15px 15px;
            padding: 1.25rem 1.5rem;
        }
        .Logout{
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 10px;
            background-color: rgb(211 64 64);
            color: white;
            transition: all 0.4s ease-in-out;
            cursor: pointer;
            text-decoration: none;
        }
        .Logout:hover{
            background-color:rgb(150, 36, 36);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .error-message {
            color: var(--danger-color);
            margin-top: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .error-message i {
            margin-right: 0.375rem;
        }
    </style>
</head>
<body>
<!-- Background Elements -->
<div class="background-container">
    <div class="background-gradient"></div>
    <div class="background-pattern"></div>
    <div class="background-grid"></div>
</div>



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
    
    <div class="settings-container fade-in">
        <div class="settings-header">
            <h1 class="settings-title">Profile Status
                
            </h1>
            <p class="settings-subtitle">View and manage your profile status, pending items, and rejected items</p>
            <br>
            <a class="Logout" href="../config/logout.php" >
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
        
        <div class="settings-content">
            <!-- User Information Card -->
            <div class="user-info-card mb-4 fade-in">
                <div class="user-info-header">
                    <h5 class="user-info-title"><i class="fas fa-user-circle me-2"></i> Personal Information</h5>
                </div>
                <div class="user-info-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="user-info-item">
                                <div class="user-info-label">Full Name</div>
                                <div class="user-info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            </div>
                            <div class="user-info-item">
                                <div class="user-info-label">Email</div>
                                <div class="user-info-value"><?php echo htmlspecialchars($user['email']); ?></div>
                            </div>
                            <div class="user-info-item">
                                <div class="user-info-label">Phone</div>
                                <div class="user-info-value"><?php echo !empty($user['phone']) ? htmlspecialchars($user['phone']) : 'Not provided'; ?></div>
                            </div>
                            <div class="user-info-item">
                                <div class="user-info-label">Category</div>
                                <div class="user-info-value"><?php echo !empty($user['category_name']) ? htmlspecialchars($user['category_name']) : 'Not provided'; ?></div>
                            </div>
                            <?php if (!empty($skills)): ?>
                            <div class="user-info-item">
                                <div class="user-info-label">Skill</div>
                                <?php foreach ($skills as $skill): ?>
                                    <div class="user-info-value"><?php echo htmlspecialchars($skill); ?></div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <div class="user-info-item">
                                <div class="user-info-label">Address</div>
                                <div class="user-info-value"><?php echo !empty($user['address']) ? htmlspecialchars($user['address']) : 'Not provided'; ?></div>
                            </div>
                            <div class="user-info-item">
                                <div class="user-info-label">Gender</div>
                                <div class="user-info-value"><?php echo !empty($user['gender']) ? htmlspecialchars($user['gender']) : 'Not provided'; ?></div>
                            </div>
                            <div class="user-info-item">
                                <div class="user-info-label">Date of Birth</div>
                                <div class="user-info-value">
                                    <?php 
                                    if (!empty($user['dob'])) {
                                        $dob = new DateTime($user['dob']);
                                        echo $dob->format('F j, Y');
                                    } else {
                                        echo 'Not provided';
                                    }
                                    ?>
                                </div>
                            </div>
                             <div class="user-info-item">
                                <div class="user-info-label">SubCategory</div>
                                <div class="user-info-value"><?php echo !empty($user['subcategory_name']) ? htmlspecialchars($user['subcategory_name']) : 'Not provided'; ?></div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
            
            <?php if ($profile): ?>
                <?php if ($profile['status'] == 'pending_review' || $profile['profile_status'] == 'pending_review'): ?>
                    <div class="status-message status-pending fade-in">
                        <i class="fas fa-clock"></i>
                        <div>
                            <h5 class="mb-0">Account Under Review</h5>
                            <p class="mb-0">Your profile is currently under review. We'll notify you once the review is complete.</p>
                        </div>
                    </div>
                <?php elseif ($profile['status'] == 'rejected' || $profile['profile_status'] == 'rejected'): ?>
                    <div class="status-message status-rejected fade-in">
                        <i class="fas fa-times-circle"></i>
                        <div>
                            <h5 class="mb-0">This account has been rejected</h5>
                            <p class="mb-0">
                                <?php echo !empty($profile['rejection_reason']) ? htmlspecialchars($profile['rejection_reason']) : 'Your profile has been rejected. Please update your information and resubmit.'; ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            
                <ul class="nav nav-tabs" id="statusTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="certificates-tab" data-bs-toggle="tab" data-bs-target="#certificates" type="button" role="tab" aria-controls="certificates" aria-selected="true">
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
                </ul>
                
                <div class="tab-content" id="statusTabsContent">
                    <!-- Certificates Tab -->
                    <div class="tab-pane fade show active" id="certificates" role="tabpanel" aria-labelledby="certificates-tab">
                        <div class="section-title">Certificates Status</div>
                        
                        <?php if ($profile['certificates_status'] == 'pending_review'): ?>
                            <div class="status-message status-pending mb-4">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <h5 class="mb-0">Certificates Under Review</h5>
                                    <p class="mb-0">Your certificates are currently being reviewed by our team.</p>
                                </div>
                            </div>
                        <?php elseif ($profile['certificates_status'] == 'rejected'): ?>
                            <div class="status-message status-rejected mb-4">
                                <i class="fas fa-times-circle"></i>
                                <div>
                                    <h5 class="mb-0">Certificates Rejected</h5>
                                    <p class="mb-0">
                                        <?php echo !empty($profile['certificates_feedback']) ? htmlspecialchars($profile['certificates_feedback']) : 'Your certificates have been rejected. Please update and resubmit.'; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card fade-in delay-1">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-certificate"></i> Your Certificates</h5>
                                    </div>
                                    <div class="card-body">
                                        <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addCertificateModal">
                                            <i class="fas fa-plus"></i> Add New Certificate
                                        </button>
                                        
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
                                                            
                                                            <?php if (isset($cert['status']) && ($cert['status'] == 'rejected' || $cert['status'] == 'pending')): ?>
                                                                <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#updateCertificateModal<?php echo $cert['id']; ?>">
                                                                    <i class="fas fa-edit"></i> Update
                                                                </button>
                                                                
                                                                <!-- Update Certificate Modal -->
                                                                <div class="modal fade" id="updateCertificateModal<?php echo $cert['id']; ?>" tabindex="-1" aria-labelledby="updateCertificateModalLabel<?php echo $cert['id']; ?>" aria-hidden="true">
                                                                    <div class="modal-dialog modal-lg">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title" id="updateCertificateModalLabel<?php echo $cert['id']; ?>">Update Certificate</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                                                                    <input type="hidden" name="cert_id" value="<?php echo $cert['id']; ?>">
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="institution<?php echo $cert['id']; ?>" class="form-label">Institution</label>
                                                                                        <input type="text" class="form-control" id="institution<?php echo $cert['id']; ?>" name="institution" value="<?php echo htmlspecialchars($cert['institution']); ?>" required>
                                                                                    </div>
                                                                                    
                                                                                    <div class="row mb-3">
                                                                                        <div class="col-md-6">
                                                                                            <label for="start_date<?php echo $cert['id']; ?>" class="form-label">Start Date</label>
                                                                                            <input type="date" class="form-control" id="start_date<?php echo $cert['id']; ?>" name="start_date" value="<?php echo $cert['start_date']; ?>" required>
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label for="end_date<?php echo $cert['id']; ?>" class="form-label">End Date</label>
                                                                                            <input type="date" class="form-control" id="end_date<?php echo $cert['id']; ?>" name="end_date" value="<?php echo $cert['end_date']; ?>" required>
                                                                                        </div>
                                                                                    </div>
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="cert_description<?php echo $cert['id']; ?>" class="form-label">Description</label>
                                                                                        <textarea class="form-control" id="cert_description<?php echo $cert['id']; ?>" name="cert_description" rows="3" required><?php echo htmlspecialchars($cert['description']); ?></textarea>
                                                                                    </div>
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="cert_file<?php echo $cert['id']; ?>" class="form-label">Certificate File</label>
                                                                                        <input type="file" class="form-control" id="cert_file<?php echo $cert['id']; ?>" name="cert_file" accept=".jpg,.jpeg,.png,.pdf">
                                                                                        <div class="form-text">Upload a scanned copy of your certificate. Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                                                                        <?php if (!empty($cert['file_path'])): ?>
                                                                                            <div class="mt-2">
                                                                                                <a href="<?php echo htmlspecialchars($cert['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                                                    <i class="fas fa-file-alt"></i> View Current Certificate
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                    
                                                                                    <div class="d-grid">
                                                                                        <button type="submit" name="update_certificate" class="btn btn-primary">
                                                                                            <i class="fas fa-save"></i> Update Certificate
                                                                                        </button>
                                                                                    </div>
                                                                                </form>
                                                                            </div>
                                                                        </div>
                                                                    </div>
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
                        
                        <!-- Add Certificate Modal -->
                        <div class="modal fade" id="addCertificateModal" tabindex="-1" aria-labelledby="addCertificateModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addCertificateModalLabel">Add New Certificate</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="institution" class="form-label">Institution</label>
                                                <input type="text" class="form-control" id="institution" name="institution" required>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="start_date" class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date" required 
                                                           max="<?php echo date('Y-m-d'); ?>" 
                                                           onchange="validateCertificateDates('new')">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="end_date" class="form-label">End Date</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date" required 
                                                           max="<?php echo date('Y-m-d'); ?>"
                                                           onchange="validateCertificateDates('new')">
                                                </div>
                                            </div>
                                            <div class="error-message" id="certificate-date-error-new"></div>
                                            
                                            
                                            <div class="mb-3">
                                                <label for="cert_description" class="form-label">Description</label>
                                                <textarea class="form-control" id="cert_description" name="cert_description" rows="3" required></textarea>
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
                        </div>
                    </div>
                    
                    <!-- Experiences Tab -->
                    <div class="tab-pane fade" id="experiences" role="tabpanel" aria-labelledby="experiences-tab">
                        <div class="section-title">Experiences Status</div>
                        
                        <?php if ($profile['experiences_status'] == 'pending_review'): ?>
                            <div class="status-message status-pending mb-4">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <h5 class="mb-0">Experiences Under Review</h5>
                                    <p class="mb-0">Your work experiences are currently being reviewed by our team.</p>
                                </div>
                            </div>
                        <?php elseif ($profile['experiences_status'] == 'rejected'): ?>
                            <div class="status-message status-rejected mb-4">
                                <i class="fas fa-times-circle"></i>
                                <div>
                                    <h5 class="mb-0">Experiences Rejected</h5>
                                    <p class="mb-0">
                                        <?php echo !empty($profile['experiences_feedback']) ? htmlspecialchars($profile['experiences_feedback']) : 'Your work experiences have been rejected. Please update and resubmit.'; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card fade-in delay-1">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-briefcase"></i> Your Work Experiences</h5>
                                    </div>
                                    <div class="card-body">
                                        <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addExperienceModal">
                                            <i class="fas fa-plus"></i> Add New Experience
                                        </button>
                                        
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
                                                            
                                                            <?php if (isset($exp['status']) && ($exp['status'] == 'rejected' || $exp['status'] == 'pending')): ?>
                                                                <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#updateExperienceModal<?php echo $exp['id']; ?>">
                                                                    <i class="fas fa-edit"></i> Update
                                                                </button>
                                                                
                                                                <!-- Update Experience Modal -->
                                                                <div class="modal fade" id="updateExperienceModal<?php echo $exp['id']; ?>" tabindex="-1" aria-labelledby="updateExperienceModalLabel<?php echo $exp['id']; ?>" aria-hidden="true">
                                                                    <div class="modal-dialog modal-lg">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title" id="updateExperienceModalLabel<?php echo $exp['id']; ?>">Update Experience</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                                                                    <input type="hidden" name="exp_id" value="<?php echo $exp['id']; ?>">
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="workplace<?php echo $exp['id']; ?>" class="form-label">Workplace</label>
                                                                                        <input type="text" class="form-control" id="workplace<?php echo $exp['id']; ?>" name="workplace" value="<?php echo htmlspecialchars($exp['workplace']); ?>" required>
                                                                                    </div>
                                                                                    
                                                                                    <div class="row mb-3">
                                                                                        <div class="col-md-6">
                                                                                            <label for="start_date<?php echo $exp['id']; ?>" class="form-label">Start Date</label>
                                                                                            <input type="date" class="form-control" id="start_date<?php echo $exp['id']; ?>" name="start_date" value="<?php echo $exp['start_date']; ?>" required>
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label for="end_date<?php echo $exp['id']; ?>" class="form-label">End Date</label>
                                                                                            <input type="date" class="form-control" id="end_date<?php echo $exp['id']; ?>" name="end_date" value="<?php echo $exp['end_date']; ?>" required>
                                                                                        </div>
                                                                                    </div>
                                                                                    
                                                                                    <div class="row mb-3">
                                                                                        <div class="col-md-6">
                                                                                            <label for="duration_years<?php echo $exp['id']; ?>" class="form-label">Duration (Years)</label>
                                                                                            <input type="number" class="form-control" id="duration_years<?php echo $exp['id']; ?>" name="duration_years" value="<?php echo $exp['duration_years']; ?>" min="0" required>
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label for="duration_months<?php echo $exp['id']; ?>" class="form-label">Duration (Months)</label>
                                                                                            <input type="number" class="form-control" id="duration_months<?php echo $exp['id']; ?>" name="duration_months" value="<?php echo $exp['duration_months']; ?>" min="0" max="11" required>
                                                                                        </div>
                                                                                    </div>
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="exp_description<?php echo $exp['id']; ?>" class="form-label">Description</label>
                                                                                        <textarea class="form-control" id="exp_description<?php echo $exp['id']; ?>" name="exp_description" rows="3" required><?php echo htmlspecialchars($exp['description']); ?></textarea>
                                                                                    </div>
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="exp_file<?php echo $exp['id']; ?>" class="form-label">Experience Document</label>
                                                                                        <input type="file" class="form-control" id="exp_file<?php echo $exp['id']; ?>" name="exp_file" accept=".jpg,.jpeg,.png,.pdf">
                                                                                        <div class="form-text">Upload a document that verifies your work experience. Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                                                                        <?php if (!empty($exp['file_path'])): ?>
                                                                                            <div class="mt-2">
                                                                                                <a href="<?php echo htmlspecialchars($exp['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                                                    <i class="fas fa-file-alt"></i> View Current Document
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                    
                                                                                    <div class="d-grid">
                                                                                        <button type="submit" name="update_experience" class="btn btn-primary">
                                                                                            <i class="fas fa-save"></i> Update Experience
                                                                                        </button>
                                                                                    </div>
                                                                                </form>
                                                                            </div>
                                                                        </div>
                                                                    </div>
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
                        
                        <!-- Add Experience Modal -->
                        <div class="modal fade" id="addExperienceModal" tabindex="-1" aria-labelledby="addExperienceModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addExperienceModalLabel">Add New Experience</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="workplace" class="form-label">Workplace</label>
                                                <input type="text" class="form-control" id="workplace" name="workplace" required>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="start_date" class="form-label">Start Date</label>
                                                    <input type="date" class="form-control" id="start_date" name="start_date" required 
                                                           max="<?php echo date('Y-m-d'); ?>"
                                                           onchange="validateExperienceDates('new')">
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="end_date" class="form-label">End Date</label>
                                                    <input type="date" class="form-control" id="end_date" name="end_date" required
                                                           max="<?php echo date('Y-m-d'); ?>"
                                                           onchange="validateExperienceDates('new')">
                                                </div>
                                            </div>
                                            <div class="error-message" id="experience-date-error-new"></div>
                                            
                                            
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
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="exp_file" class="form-label">Experience Document</label>
                                                <input type="file" class="form-control" id="exp_file" name="exp_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                                <div class="form-text">Upload a document that verifies your work experience. Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
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
                        </div>
                    </div>
                    
                    <!-- Formations Tab -->
                    <div class="tab-pane fade" id="formations" role="tabpanel" aria-labelledby="formations-tab">
                        <div class="section-title">Formations Status</div>
                        
                        <?php if ($profile['formations_status'] == 'pending_review'): ?>
                            <div class="status-message status-pending mb-4">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <h5 class="mb-0">Formations Under Review</h5>
                                    <p class="mb-0">Your formations are currently being reviewed by our team.</p>
                                </div>
                            </div>
                        <?php elseif ($profile['formations_status'] == 'rejected'): ?>
                            <div class="status-message status-rejected mb-4">
                                <i class="fas fa-times-circle"></i>
                                <div>
                                    <h5 class="mb-0">Formations Rejected</h5>
                                    <p class="mb-0">
                                        <?php echo !empty($profile['formations_feedback']) ? htmlspecialchars($profile['formations_feedback']) : 'Your formations have been rejected. Please update and resubmit.'; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card fade-in delay-1">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-graduation-cap"></i> Your Formations</h5>
                                    </div>
                                    <div class="card-body">
                                        <button type="button" class="btn btn-primary mb-4" data-bs-toggle="modal" data-bs-target="#addFormationModal">
                                            <i class="fas fa-plus"></i> Add New Formation
                                        </button>
                                        
                                        <div class="item-list">
                                            <?php if (empty($formations)): ?>
                                                <p class="text-muted">You haven't added any formations yet.</p>
                                            <?php else: ?>
                                                <?php foreach ($formations as $form): ?>
                                                    <div class="card item-card <?php echo isset($form['status']) ? $form['status'] : 'pending'; ?> mb-3">
                                                        <div class="card-body">
                                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                                <h6 class="card-subtitle"><?php echo htmlspecialchars($form['formation_name']); ?></h6>
                                                                <?php echo getStatusBadge(isset($form['status']) ? $form['status'] : 'pending'); ?>
                                                            </div>
                                                            <p class="card-text small">
                                                                <span class="badge bg-info"><?php echo htmlspecialchars($form['formation_type']); ?></span>
                                                                <span class="ms-2"><?php echo htmlspecialchars($form['formation_year']); ?></span>
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
                                                            
                                                            <?php if (isset($form['status']) && ($form['status'] == 'rejected' || $form['status'] == 'pending')): ?>
                                                                <button type="button" class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#updateFormationModal<?php echo $form['id']; ?>">
                                                                    <i class="fas fa-edit"></i> Update
                                                                </button>
                                                                
                                                                <!-- Update Formation Modal -->
                                                                <div class="modal fade" id="updateFormationModal<?php echo $form['id']; ?>" tabindex="-1" aria-labelledby="updateFormationModalLabel<?php echo $form['id']; ?>" aria-hidden="true">
                                                                    <div class="modal-dialog modal-lg">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <h5 class="modal-title" id="updateFormationModalLabel<?php echo $form['id']; ?>">Update Formation</h5>
                                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                                                                    <input type="hidden" name="form_id" value="<?php echo $form['id']; ?>">
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="formation_name<?php echo $form['id']; ?>" class="form-label">Formation Name</label>
                                                                                        <input type="text" class="form-control" id="formation_name<?php echo $form['id']; ?>" name="formation_name" value="<?php echo htmlspecialchars($form['formation_name']); ?>" required>
                                                                                    </div>
                                                                                    
                                                                                    <div class="row mb-3">
                                                                                        <div class="col-md-6">
                                                                                            <label for="formation_type<?php echo $form['id']; ?>" class="form-label">Formation Type</label>
                                                                                            <select class="form-select" id="formation_type<?php echo $form['id']; ?>" name="formation_type" required>
                                                                                                <option value="Course" <?php echo $form['formation_type'] == 'Course' ? 'selected' : ''; ?>>Course</option>
                                                                                                <option value="Workshop" <?php echo $form['formation_type'] == 'Workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                                                                <option value="Seminar" <?php echo $form['formation_type'] == 'Seminar' ? 'selected' : ''; ?>>Seminar</option>
                                                                                                <option value="Training" <?php echo $form['formation_type'] == 'Training' ? 'selected' : ''; ?>>Training</option>
                                                                                                <option value="Other" <?php echo $form['formation_type'] == 'Other' ? 'selected' : ''; ?>>Other</option>
                                                                                            </select>
                                                                                        </div>
                                                                                        <div class="col-md-6">
                                                                                            <label for="formation_year<?php echo $form['id']; ?>" class="form-label">Year</label>
                                                                                            <input type="number" class="form-control" id="formation_year<?php echo $form['id']; ?>" name="formation_year" value="<?php echo $form['formation_year']; ?>" min="1950" max="<?php echo date('Y'); ?>" required>
                                                                                        </div>
                                                                                    </div>
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="formation_description<?php echo $form['id']; ?>" class="form-label">Description</label>
                                                                                        <textarea class="form-control" id="formation_description<?php echo $form['id']; ?>" name="formation_description" rows="3" required><?php echo htmlspecialchars($form['description']); ?></textarea>
                                                                                    </div>
                                                                                    
                                                                                    <div class="mb-3">
                                                                                        <label for="formation_file<?php echo $form['id']; ?>" class="form-label">Formation Document</label>
                                                                                        <input type="file" class="form-control" id="formation_file<?php echo $form['id']; ?>" name="formation_file" accept=".jpg,.jpeg,.png,.pdf">
                                                                                        <div class="form-text">Upload a document that verifies your formation. Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                                                                        <?php if (!empty($form['file_path'])): ?>
                                                                                            <div class="mt-2">
                                                                                                <a href="<?php echo htmlspecialchars($form['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                                                                    <i class="fas fa-file-alt"></i> View Current Document
                                                                                                </a>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                    
                                                                                    <div class="d-grid">
                                                                                        <button type="submit" name="update_formation" class="btn btn-primary">
                                                                                            <i class="fas fa-save"></i> Update Formation
                                                                                        </button>
                                                                                    </div>
                                                                                </form>
                                                                            </div>
                                                                        </div>
                                                                    </div>
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
                        
                        <!-- Add Formation Modal -->
                        <div class="modal fade" id="addFormationModal" tabindex="-1" aria-labelledby="addFormationModalLabel" aria-hidden="true">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="addFormationModalLabel">Add New Formation</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                            <div class="mb-3">
                                                <label for="formation_name" class="form-label">Education/Training Name</label>
                                                <input type="text" class="form-control" id="formation_name" name="formation_name" required>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="formation_type" class="form-label">Education/Training Type</label>
                                                    <select class="form-select" id="formation_type" name="formation_type" required>
                                                        <option value="" selected disabled>Select Type</option>
                                                        <option value="Certificate">Certificate</option>
                                                        <option value="Diploma">Diploma</option>
                                                        <option value="Degree">Degree</option>
                                                        <option value="Course">Course</option>
                                                        <option value="Workshop">Workshop</option>
                                                        <option value="Other">Other</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="formation_year" class="form-label">Year Completed</label>
                                                    <input type="number" class="form-control" id="formation_year" name="formation_year" 
                                                           min="<?php echo date('Y') - 50; ?>" max="<?php echo date('Y'); ?>" 
                                                           value="<?php echo date('Y'); ?>" required
                                                           onchange="validateFormationYear(this)">
                                                    <div class="error-message" id="formation-year-error"></div>
                                            </div>
                                        </div>
                                        
                                        
                                        <div class="mb-3">
                                            <label for="formation_description" class="form-label">Description</label>
                                            <textarea class="form-control" id="formation_description" name="formation_description" rows="3" required></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="formation_file" class="form-label">Education/Training Document</label>
                                            <input type="file" class="form-control" id="formation_file" name="formation_file" accept=".jpg,.jpeg,.png,.pdf" required>
                                            <div class="form-text">Upload a document that verifies your education/training. Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                        </div>
                                        
                                        <div class="d-grid">
                                            <button type="submit" name="add_formation" class="btn btn-primary">
                                                <i class="fas fa-plus"></i> Add Education/Training
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Banking Tab -->
                    <div class="tab-pane fade" id="banking" role="tabpanel" aria-labelledby="banking-tab">
                        <div class="section-title">Banking Information Status</div>
                        
                        <?php if ($profile['banking_status'] == 'pending_review'): ?>
                            <div class="status-message status-pending mb-4">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <h5 class="mb-0">Banking Information Under Review</h5>
                                    <p class="mb-0">Your banking information is currently being reviewed by our team.</p>
                                </div>
                            </div>
                        <?php elseif ($profile['banking_status'] == 'rejected'): ?>
                            <div class="status-message status-rejected mb-4">
                                <i class="fas fa-times-circle"></i>
                                <div>
                                    <h5 class="mb-0">Banking Information Rejected</h5>
                                    <p class="mb-0">
                                        <?php echo !empty($profile['banking_feedback']) ? htmlspecialchars($profile['banking_feedback']) : 'Your banking information has been rejected. Please update and resubmit.'; ?>
                                    </p>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-12">
                                <div class="card fade-in delay-1">
                                    <div class="card-header">
                                        <h5 class="card-title"><i class="fas fa-university"></i> Your Banking Information</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="ccp" class="form-label">CCP Number</label>
                                                    <input type="text" class="form-control" id="ccp" name="ccp" value="<?php echo $banking ? htmlspecialchars($banking['ccp']) : ''; ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="ccp_key" class="form-label">CCP Key</label>
                                                    <input type="text" class="form-control" id="ccp_key" name="ccp_key" value="<?php echo $banking ? htmlspecialchars($banking['ccp_key']) : ''; ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="check_file" class="form-label">Check Image</label>
                                                <input type="file" class="form-control" id="check_file" name="check_file" accept=".jpg,.jpeg,.png,.pdf" <?php echo $banking ? '' : 'required'; ?>>
                                                <div class="form-text">Upload a scanned copy of your check. Max file size: 5MB. Supported formats: JPG, PNG, PDF.</div>
                                                <?php if ($banking && !empty($banking['check_file_path'])): ?>
                                                    <div class="mt-2">
                                                        <a href="<?php echo htmlspecialchars($banking['check_file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-file-alt"></i> View Current Check
                                                        </a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <label for="consultation_minutes" class="form-label">Consultation Duration (Minutes)</label>
                                                    <input type="number" class="form-control" id="consultation_minutes" name="consultation_minutes" min="0"  value="<?php echo $banking ? intval($banking['consultation_minutes']) : '60'; ?>" required>
                                                </div>
                                                <div class="col-md-6">
                                                    <label for="consultation_price" class="form-label">Consultation Price (DZD)</label>
                                                    <input type="number" class="form-control" id="consultation_price" name="consultation_price" min="0"  value="<?php echo $banking ? intval($banking['consultation_price']) : '1000'; ?>" required>
                                                </div>
                                            </div>
                                            
                                            <div class="d-grid">
                                                <button type="submit" name="update_banking" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> <?php echo $banking ? 'Update' : 'Save'; ?> Banking Information
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning fade-in">
                    <i class="fas fa-exclamation-triangle me-2"></i> You need to create your expert profile first. Please go to <a href="expert-profile.php" class="alert-link">Expert Profile</a> to set up your profile.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>



<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Déplacer tous les modals au niveau du body pour éviter les problèmes d'imbrication
    const modals = document.querySelectorAll('.modal');
    modals.forEach(function(modal) {
        document.body.appendChild(modal);
    });
    
    // Gérer les boutons d'ouverture de modal
    const modalButtons = document.querySelectorAll('[data-bs-toggle="modal"]');
    modalButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const targetModal = this.getAttribute('data-bs-target');
            const modalElement = document.querySelector(targetModal);
            
            // Fermer tous les autres modals avant d'ouvrir celui-ci
            modals.forEach(function(m) {
                const bsModal = bootstrap.Modal.getInstance(m);
                if (bsModal) {
                    bsModal.hide();
                }
            });
            
            // Ouvrir le modal ciblé
            setTimeout(function() {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }, 100);
        });
    });
    
    // Fix modal issues
    const fixModalIssues = function() {
        // Remove padding-right added by Bootstrap
        document.body.style.paddingRight = '0';
        
        // Ensure body remains scrollable when modal is open
        const modals = document.querySelectorAll('.modal');
        modals.forEach(function(modal) {
            modal.addEventListener('show.bs.modal', function() {
                setTimeout(function() {
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = 'auto';
                    document.body.style.paddingRight = '0';
                }, 0);
            });
            
            modal.addEventListener('hidden.bs.modal', function() {
                document.body.style.overflow = '';
                document.body.style.paddingRight = '0';
            });
        });
    };

    fixModalIssues();

    // Re-initialize modal fix after AJAX content is loaded
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                fixModalIssues();
                
                // Déplacer les nouveaux modals au niveau du body
                const newModals = document.querySelectorAll('.modal:not([data-moved])');
                newModals.forEach(function(modal) {
                    document.body.appendChild(modal);
                    modal.setAttribute('data-moved', 'true');
                });
            }
        });
    

    observer.observe(document.body, { childList: true, subtree: true });
    
    // Calculer la durée automatiquement lorsque les dates de début et de fin changent
    const startDateInputs = document.querySelectorAll('input[name="start_date"]');
    const endDateInputs = document.querySelectorAll('input[name="end_date"]');
    
    startDateInputs.forEach(function(startDateInput) {
        const form = startDateInput.closest('form');
        const endDateInput = form.querySelector('input[name="end_date"]');
        const durationYearsInput = form.querySelector('input[name="duration_years"]');
        const durationMonthsInput = form.querySelector('input[name="duration_months"]');
        
        if (endDateInput && durationYearsInput && durationMonthsInput) {
            const calculateDuration = function() {
                if (startDateInput.value && endDateInput.value) {
                    const startDate = new Date(startDateInput.value);
                    const endDate = new Date(endDateInput.value);
                    
                    if (endDate >= startDate) {
                        let years = endDate.getFullYear() - startDate.getFullYear();
                        let months = endDate.getMonth() - startDate.getMonth();
                        
                        if (months < 0) {
                            years--;
                            months += 12;
                        }
                        
                        durationYearsInput.value = years;
                        durationMonthsInput.value = months;
                    }
                }
            };
            
            startDateInput.addEventListener('change', calculateDuration);
            endDateInput.addEventListener('change', calculateDuration);
        }
    });
    
    // Fetch notification counts
    function fetchNotifications() {
        fetch('<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?fetch_notifications=1')
            .then(response => response.json())
            .then(data => {
                // Update notification badges
                updateBadge('.pending-consultations-badge', data.pending_consultations);
                updateBadge('.pending-withdrawals-badge', data.pending_withdrawals);
                updateBadge('.admin-messages-badge', data.admin_messages);
                updateBadge('.reviews-badge', data.reviews);
                updateBadge('.notifications-not_read-badge', data.notifications_not_read);
                updateBadge('.total-notifications-badge', data.total);
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }
    
    function updateBadge(selector, count) {
        const badge = document.querySelector(selector);
        if (badge) {
            if (count > 0) {
                badge.textContent = count;
                badge.classList.add('show');
            } else {
                badge.textContent = '';
                badge.classList.remove('show');
            }
        }
    }
    
    // Initial fetch
    fetchNotifications();
    
    // Fetch every 30 seconds
    setInterval(fetchNotifications, 3000);
});

// Add these validation functions
function validateCertificateDates(id) {
    const startDateInput = id === 'new' ? 
        document.getElementById('start_date') : 
        document.getElementById(`start_date${id}`);
    const endDateInput = id === 'new' ? 
        document.getElementById(`end_date`) : 
        document.getElementById(`end_date${id}`);
    const errorDiv = document.getElementById(`certificate-date-error-${id}`);
    
    if (!startDateInput || !endDateInput) return true;
    
    const startDate = new Date(startDateInput.value);
    const endDate = new Date(endDateInput.value);
    const today = new Date();
    
    // Reset error message
    errorDiv.innerHTML = "";
    
    // Check if start date is after end date
    if (startDate > endDate) {
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> End date must be after start date';
        endDateInput.value = ''; // Clear the end date
        return false;
    }
    
    // Check if end date is in the future
    if (endDate > today) {
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Certificate dates must be in the past';
        endDateInput.value = ''; // Clear the end date
        return false;
    }
    
    return true;
}

function validateExperienceDates(id) {
    const startDateInput = id === 'new' ? 
        document.getElementById('start_date') : 
        document.getElementById(`start_date${id}`);
    const endDateInput = id === 'new' ? 
        document.getElementById(`end_date`) : 
        document.getElementById(`end_date${id}`);
    const errorDiv = document.getElementById(`experience-date-error-${id}`);
    
    if (!startDateInput || !endDateInput) return true;
    
    const startDate = new Date(startDateInput.value);
    const endDate = new Date(endDateInput.value);
    const today = new Date();
    
    // Reset error message
    errorDiv.innerHTML = "";
    
    // Check if start date is after end date
    if (startDate > endDate) {
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> End date must be after start date';
        endDateInput.value = ''; // Clear the end date
        return false;
    }
    
    // Check if end date is in the future
    if (endDate > today) {
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Experience end date cannot be in the future';
        endDateInput.value = ''; // Clear the end date
        return false;
    }
    
    // Update duration fields if they exist
    const durationYearsInput = document.getElementById('duration_years');
    const durationMonthsInput = document.getElementById('duration_months');
    
    if (durationYearsInput && durationMonthsInput) {
        let years = endDate.getFullYear() - startDate.getFullYear();
        let months = endDate.getMonth() - startDate.getMonth();
        
        if (months < 0) {
            years--;
            months += 12;
        }
        
        durationYearsInput.value = years;
        durationMonthsInput.value = months;
    }
    
    return true;
}

function validateFormationYear(yearInput) {
    const year = parseInt(yearInput.value);
    const currentYear = new Date().getFullYear();
    const errorDiv = document.getElementById('formation-year-error');
    
    if (!errorDiv) return true;
    
    // Reset error message
    errorDiv.innerHTML = "";
    
    // Check if year is valid
    if (isNaN(year)) {
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Please enter a valid year';
        return false;
    }
    
    // Check if year is in the future
    if (year > currentYear) {
        errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> Formation year cannot be in the future';
        return false;
    }
    
    return true;
}

// Add event listeners when the document is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add validation for certificate dates
    const certificateStartDateInputs = document.querySelectorAll('input[id^="start_date"]');
    const certificateEndDateInputs = document.querySelectorAll('input[id^="end_date"]');
    
    certificateStartDateInputs.forEach(function(input) {
        const id = input.id.replace('start_date', '');
        if (id) {
            input.addEventListener('change', function() {
                validateCertificateDates(id);
            });
        }
    });
    
    certificateEndDateInputs.forEach(function(input) {
        const id = input.id.replace('end_date', '');
        if (id) {
            input.addEventListener('change', function() {
                validateCertificateDates(id);
            });
        }
    });
    
    // Add validation for formation year inputs
    const formationYearInputs = document.querySelectorAll('input[id^="formation_year"]');
    formationYearInputs.forEach(function(input) {
        input.addEventListener('change', function() {
            validateFormationYear(this);
        });
    });
});
</script>
</body>
</html>
