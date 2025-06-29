<?php
// Start the session
session_start();

// Include database connection
require_once '../config/config.php';

// Get notification counts for the sidebar
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_withdrawals_count = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_fund_requests_count = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Redirect to login page if not logged in
    header("Location: ../config/logout.php");
    exit;
}

// Check if profile_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION["admin_message"] = "Invalid profile ID.";
    $_SESSION["admin_message_type"] = "error";
    header("Location: expert-profiles.php");
    exit;
}

$profile_id = (int)$_GET['id'];

// Process certificate approval/rejection
if (isset($_POST['approve_certificate'])) {
    $certificate_id = (int)$_POST['certificate_id'];
    
    $update_query = "UPDATE certificates SET status = 'approved' WHERE id = ? AND profile_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $certificate_id, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Certificate approved successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error approving certificate.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['reject_certificate'])) {
    $certificate_id = (int)$_POST['certificate_id'];
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    $update_query = "UPDATE certificates SET status = 'rejected', rejection_reason = ? WHERE id = ? AND profile_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $rejection_reason, $certificate_id, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Certificate rejected successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error rejecting certificate.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

// Process experience approval/rejection
if (isset($_POST['approve_experience'])) {
    $experience_id = (int)$_POST['experience_id'];
    
    $update_query = "UPDATE experiences SET status = 'approved' WHERE id = ? AND profile_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $experience_id, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Experience approved successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error approving experience.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['reject_experience'])) {
    $experience_id = (int)$_POST['experience_id'];
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    $update_query = "UPDATE experiences SET status = 'rejected', rejection_reason = ? WHERE id = ? AND profile_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $rejection_reason, $experience_id, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Experience rejected successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error rejecting experience.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

// Process formation approval/rejection
if (isset($_POST['approve_formation'])) {
    $formation_id = (int)$_POST['formation_id'];
    
    $update_query = "UPDATE formations SET status = 'approved' WHERE id = ? AND profile_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ii", $formation_id, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Formation approved successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error approving formation.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['reject_formation'])) {
    $formation_id = (int)$_POST['formation_id'];
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    $update_query = "UPDATE formations SET status = 'rejected', rejection_reason = ? WHERE id = ? AND profile_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("sii", $rejection_reason, $formation_id, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Formation rejected successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error rejecting formation.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

// Process section approval
if (isset($_POST['approve_certificates_section'])) {
    $update_query = "UPDATE expert_profiledetails SET certificates_status = 'approved' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $profile_id);
    
    if ($stmt->execute()) {
        // Also approve all pending certificates
        $update_certs = "UPDATE certificates SET status = 'approved' WHERE profile_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($update_certs);
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        
        $_SESSION["admin_message"] = "Certificates section approved successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error approving certificates section.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['reject_certificates_section'])) {
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    $update_query = "UPDATE expert_profiledetails SET certificates_status = 'rejected', certificates_feedback = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $rejection_reason, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Certificates section rejected successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error rejecting certificates section.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['approve_experiences_section'])) {
    $update_query = "UPDATE expert_profiledetails SET experiences_status = 'approved' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $profile_id);
    
    if ($stmt->execute()) {
        // Also approve all pending experiences
        $update_exps = "UPDATE experiences SET status = 'approved' WHERE profile_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($update_exps);
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        
        $_SESSION["admin_message"] = "Experiences section approved successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error approving experiences section.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['reject_experiences_section'])) {
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    $update_query = "UPDATE expert_profiledetails SET experiences_status = 'rejected', experiences_feedback = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $rejection_reason, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Experiences section rejected successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error rejecting experiences section.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['approve_formations_section'])) {
    $update_query = "UPDATE expert_profiledetails SET formations_status = 'approved' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $profile_id);
    
    if ($stmt->execute()) {
        // Also approve all pending formations
        $update_forms = "UPDATE formations SET status = 'approved' WHERE profile_id = ? AND status = 'pending'";
        $stmt = $conn->prepare($update_forms);
        $stmt->bind_param("i", $profile_id);
        $stmt->execute();
        
        $_SESSION["admin_message"] = "Formations section approved successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error approving formations section.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['reject_formations_section'])) {
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    $update_query = "UPDATE expert_profiledetails SET formations_status = 'rejected', formations_feedback = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $rejection_reason, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Formations section rejected successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error rejecting formations section.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['approve_banking_section'])) {
    $update_query = "UPDATE expert_profiledetails SET banking_status = 'approved' WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("i", $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Banking section approved successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error approving banking section.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['reject_banking_section'])) {
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    
    $update_query = "UPDATE expert_profiledetails SET banking_status = 'rejected', banking_feedback = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $rejection_reason, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Banking section rejected successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error rejecting banking section.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

// Process category and subcategory update
if (isset($_POST['update_category_subcategory'])) {
    $new_category = (int)$_POST['new_category'];
    $new_subcategory = (int)$_POST['new_subcategory'];
    
    $update_query = "UPDATE expert_profiledetails SET category = ?, subcategory = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("iii", $new_category, $new_subcategory, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Category and subcategory updated successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error updating category and subcategory.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}


// Process profile approval/rejection
if (isset($_POST['approve_profile'])) {
    $now = date('Y-m-d H:i:s');
    
    $update_query = "UPDATE expert_profiledetails SET status = 'approved', profile_status = 'approved', approved_at = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("si", $now, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Expert profile approved successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error approving expert profile.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

if (isset($_POST['reject_profile'])) {
    $rejection_reason = $conn->real_escape_string($_POST['rejection_reason']);
    $now = date('Y-m-d H:i:s');
    
    $update_query = "UPDATE expert_profiledetails SET status = 'rejected', profile_status = 'rejected', rejection_reason = ?, rejected_at = ? WHERE id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("ssi", $rejection_reason, $now, $profile_id);
    
    if ($stmt->execute()) {
        $_SESSION["admin_message"] = "Expert profile rejected successfully.";
        $_SESSION["admin_message_type"] = "success";
    } else {
        $_SESSION["admin_message"] = "Error rejecting expert profile.";
        $_SESSION["admin_message_type"] = "error";
    }
    
    header("Location: view-profile.php?id=$profile_id");
    exit;
}

// Fetch expert profile details
$profile_query = "SELECT ep.*, u.full_name, u.email, up.phone, c.name as category_name, sc.name as subcategory_name, ct.name as city_name
                 FROM expert_profiledetails ep
                 JOIN users u ON ep.user_id = u.id
                 LEFT JOIN user_profiles up ON u.id = up.user_id
                 LEFT JOIN categories c ON ep.category = c.id
                 LEFT JOIN subcategories sc ON ep.subcategory = sc.id
                 LEFT JOIN cities ct ON ep.city = ct.id
                 WHERE ep.id = ?";
$stmt = $conn->prepare($profile_query);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$profile_result = $stmt->get_result();

if ($profile_result->num_rows === 0) {
    $_SESSION["admin_message"] = "Expert profile not found.";
    $_SESSION["admin_message_type"] = "error";
    header("Location: expert-profiles.php");
    exit;
}

$profile = $profile_result->fetch_assoc();

// Fetch all categories
$categories_query = "SELECT * FROM categories ORDER BY name";
$categories_result = $conn->query($categories_query);
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Fetch all subcategories
$subcategories_query = "SELECT * FROM subcategories ORDER BY name";
$subcategories_result = $conn->query($subcategories_query);
$subcategories = [];
while ($row = $subcategories_result->fetch_assoc()) {
    $subcategories[] = $row;
}

// Fetch certificates
$certificates_query = "SELECT * FROM certificates WHERE profile_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($certificates_query);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$certificates_result = $stmt->get_result();
$certificates = [];
while ($row = $certificates_result->fetch_assoc()) {
    $certificates[] = $row;
}

// Fetch experiences
$experiences_query = "SELECT * FROM experiences WHERE profile_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($experiences_query);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$experiences_result = $stmt->get_result();
$experiences = [];
while ($row = $experiences_result->fetch_assoc()) {
    $experiences[] = $row;
}

// Fetch formations
$formations_query = "SELECT * FROM formations WHERE profile_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($formations_query);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$formations_result = $stmt->get_result();
$formations = [];
while ($row = $formations_result->fetch_assoc()) {
    $formations[] = $row;
}

// Fetch banking information
$banking_query = "SELECT * FROM banking_information WHERE profile_id = ?";
$stmt = $conn->prepare($banking_query);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$banking_result = $stmt->get_result();
$banking = $banking_result->fetch_assoc();


// Fetch skills
$skills_query = "SELECT * FROM skills WHERE profile_id = ?";
$stmt = $conn->prepare($skills_query);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$skills_result = $stmt->get_result();
$skills = [];
while ($row = $skills_result->fetch_assoc()) {
    $skills[] = $row['skill_name'];
}

// Fetch social links
$social_query = "SELECT * FROM expert_social_links WHERE profile_id = ?";
$stmt = $conn->prepare($social_query);
$stmt->bind_param("i", $profile_id);
$stmt->execute();
$social_result = $stmt->get_result();
$social_links = $social_result->fetch_assoc();

// Get site name from settings table
$site_name = "Consult Pro"; // Default value
$site_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_result = $conn->query($site_query);
if ($site_result && $site_result->num_rows > 0) {
    $site_name = $site_result->fetch_assoc()['setting_value'];
}

// Function to format date
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

// Function to get status badge
function getStatusBadge($status) {
    switch($status) {
        case 'approved':
            return '<span class="status-badge status-approved"><i class="fas fa-check-circle"></i> Approved</span>';
        case 'pending':
        case 'pending_review':
            return '<span class="status-badge status-pending"><i class="fas fa-clock"></i> Pending</span>';
        case 'pending_revision':
            return '<span class="status-badge status-info"><i class="fas fa-edit"></i> Revision</span>';
        case 'rejected':
            return '<span class="status-badge status-rejected"><i class="fas fa-times-circle"></i> Rejected</span>';
        default:
            return '<span class="status-badge">' . ucfirst($status) . '</span>';
    }
}

// Function to get view certificate button
function getViewButton($file_path, $type = 'certificate') {
    if (empty($file_path) || $file_path == '') {
        return '<button class="btn btn-sm btn-secondary" disabled><i class="fas fa-eye"></i> No File</button>';
    }
    return '<a href="' . $file_path . '" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-eye"></i> View ' . ucfirst($type) . '</a>';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert Profile Review - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS Variables */
        :root {
            /* Main Colors */
            --primary-color: #6366f1;
            --primary-light: #818cf8;
            --primary-dark: #4f46e5;
            --primary-bg: rgba(99, 102, 241, 0.1);
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            
            --secondary-color: #64748b;
            --secondary-light: #94a3b8;
            --secondary-dark: #475569;
            --secondary-bg: rgba(100, 116, 139, 0.1);
            
            --success-color: #10b981;
            --success-light: #34d399;
            --success-dark: #059669;
            --success-bg: rgba(16, 185, 129, 0.1);
            --success-gradient: linear-gradient(135deg, #10b981 0%, #059669 100%);
            
            --warning-color: #f59e0b;
            --warning-light: #fbbf24;
            --warning-dark: #d97706;
            --warning-bg: rgba(245, 158, 11, 0.1);
            --warning-gradient: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            
            --danger-color: #ef4444;
            --danger-light: #f87171;
            --danger-dark: #dc2626;
            --danger-bg: rgba(239, 68, 44, 0.1);
            --danger-gradient: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            
            --info-color: #06b6d4;
            --info-light: #22d3ee;
            --info-dark: #0891b2;
            --info-bg: rgba(6, 182, 212, 0.1);
            --info-gradient: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            
            /* Neutral Colors */
            --light-color: #f8fafc;
            --light-color-2: #f1f5f9;
            --light-color-3: #e2e8f0;
            
            --dark-color: #0f172a;
            --dark-color-2: #1e293b;
            --dark-color-3: #334155;
            
            --border-color: #e2e8f0;
            --border-color-dark: #cbd5e1;
            
            /* Background Colors */
            --card-bg: #ffffff;
            --body-bg: #f8fafc;
            --body-bg-gradient: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            
            /* Text Colors */
            --text-color: #334155;
            --text-color-light: #64748b;
            --text-color-lighter: #94a3b8;
            --text-color-dark: #1e293b;
            --text-color-darker: #0f172a;
            
            /* Shadow Variables */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-md: 0 6px 10px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-inner: inset 0 2px 4px 0 rgba(0, 0, 0, 0.06);
            --shadow-outline: 0 0 0 3px rgba(99, 102, 241, 0.2);
            
            /* Border Radius */
            --radius-sm: 0.25rem;
            --radius: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
            --radius-full: 9999px;
            
            /* Transitions */
            --transition: all 0.3s ease;
            --transition-slow: all 0.5s ease;
            --transition-fast: all 0.15s ease;
            --transition-bounce: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            
            /* Z-index */
            --z-negative: -1;
            --z-normal: 1;
            --z-tooltip: 10;
            --z-fixed: 100;
            --z-modal: 1000;
        }

        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }
        
        body {
            background: var(--body-bg-gradient);
            color: var(--text-color);
            line-height: 1.6;
            font-size: 0.95rem;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%236366f1' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4H-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            z-index: var(--z-negative);
            pointer-events: none;
        }
        
        a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition-fast);
        }
        
        a:hover {
            color: var(--primary-dark);
        }

        /* Layout */
        .container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: var(--dark-color-2);
            color: white;
            padding: 1.5rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: var(--z-fixed);
            box-shadow: var(--shadow-lg);
            transition: var(--transition);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: var(--dark-color-3);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: var(--radius-full);
        }
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: var(--transition);
        }

        /* Sidebar Styles */
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .sidebar-header h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
        }
        
        .sidebar-header p {
            font-size: 0.875rem;
            opacity: 0.7;
        }
        
        .sidebar-menu {
            padding: 1.5rem 0;
        }
        
        .menu-item {
            padding: 0.875rem 1.5rem;
            display: flex;
            align-items: center;
            transition: var(--transition);
            text-decoration: none;
            color: var(--light-color-3);
            position: relative;
            overflow: hidden;
        }
        
        .menu-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: var(--primary-gradient);
            transform: scaleY(0);
            transition: var(--transition);
        }
        
        .menu-item:hover, .menu-item.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        .menu-item:hover::before, .menu-item.active::before {
            transform: scaleY(1);
        }
        
        .menu-item.active {
            background-color: rgba(99, 102, 241, 0.1);
            font-weight: 500;
        }
        
        .menu-item i {
            margin-right: 0.75rem;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
            color: var(--primary-light);
            transition: var(--transition);
        }
        
        .menu-item:hover i, .menu-item.active i {
            color: var(--primary-color);
        }
        
        .notification-badge {
            position: absolute;
            top: 0.5rem;
            right: 1.5rem;
            background: var(--danger-gradient);
            color: white;
            border-radius: var(--radius-full);
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.1rem 0.4rem;
            min-width: 1.2rem;
            text-align: center;
            box-shadow: var(--shadow);
            animation: pulse 2s infinite;
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }
        
        .header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: var(--radius-full);
        }
        
        .header h1 {
            color: var(--dark-color);
            font-size: 1.75rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .header h1 i {
            color: var(--primary-color);
            background: var(--primary-bg);
            padding: 0.5rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.5rem 1rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-full);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .user-info:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--dark-color);
            font-size: 0.95rem;
        }
        
        .user-role {
            color: var(--text-color-light);
            font-size: 0.75rem;
        }

        /* Card Styles */
        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--border-color);
        }
        
        .card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
        }
        
        .card:hover::before {
            opacity: 1;
        }
        
        .card-header {
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(255, 255, 255, 0.8));
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .card-header h2 i {
            color: var(--primary-color);
            background: var(--primary-bg);
            padding: 0.5rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-footer {
            background-color: var(--light-color);
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1rem;
        }

        /* Expert Profile Styles */
        .expert-profile {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .expert-avatar {
            width: 120px;
            height: 120px;
            border-radius: var(--radius);
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 600;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .expert-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .expert-details {
            flex: 1;
        }
        
        .expert-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .expert-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .expert-info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
        }
        
        .expert-info-item i {
            color: var(--primary-color);
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }
        
        .expert-bio {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .expert-bio h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .expert-bio p {
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .expert-skills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .skill-tag {
            background: var(--primary-bg);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .skill-tag:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        /* Tab Styles */
        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 1.5rem;
            overflow-x: auto;
            scrollbar-width: none;
        }
        
        .tabs::-webkit-scrollbar {
            display: none;
        }
        
        .tab {
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: var(--text-color-light);
            cursor: pointer;
            transition: var(--transition);
            position: relative;
            white-space: nowrap;
        }
        
        .tab::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: var(--transition);
        }
        
        .tab:hover {
            color: var(--primary-color);
        }
        
        .tab.active {
            color: var(--primary-color);
        }
        
        .tab.active::after {
            transform: scaleX(1);
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        /* Item Card Styles */
        .item-card {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }
        
        .item-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .item-card-header {
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(255, 255, 255, 0.8));
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .item-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .item-card-subtitle {
            font-size: 0.875rem;
            color: var(--text-color-light);
            margin-top: 0.25rem;
        }
        
        .item-card-body {
            padding: 1rem 1.5rem;
        }
        
        .item-card-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .item-card-info-item {
            display: flex;
            flex-direction: column;
        }
        
        .item-card-info-label {
            font-size: 0.75rem;
            color: var(--text-color-light);
            margin-bottom: 0.25rem;
        }
        
        .item-card-info-value {
            font-size: 0.875rem;
            color: var(--text-color-dark);
            font-weight: 500;
        }
        
        .item-card-description {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-color);
        }
        
        .item-card-description h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }
        
        .item-card-description p {
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .item-card-footer {
            background-color: var(--light-color);
            padding: 0.75rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .item-card-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .item-card-actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Banking Info Styles */
        .banking-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        
        .banking-info-item {
            background-color: var(--card-bg);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            padding: 1.25rem;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }
        
        .banking-info-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .banking-info-label {
            font-size: 0.875rem;
            color: var(--text-color-light);
            margin-bottom: 0.5rem;
        }
        
        .banking-info-value {
            font-size: 1rem;
            color: var(--text-color-dark);
            font-weight: 600;
        }
        
        .banking-check {
            margin-top: 1.5rem;
        }
        
        .banking-check-label {
            font-size: 1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.75rem;
        }
        
        .banking-check-image {
            width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .banking-check-image:hover {
            transform: scale(1.02);
            box-shadow: var(--shadow-md);
        }

        /* Button Styles */
        .btn {
            padding: 0.625rem 1.25rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
            z-index: var(--z-normal);
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%);
            transition: var(--transition);
            z-index: -1;
        }
        
        .btn:hover::before {
            transform: translateX(0);
        }
        
        .btn i {
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }
        
        .btn-primary:hover {
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: var(--danger-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }
        
        .btn-danger:hover {
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-info {
            background: var(--info-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(6, 182, 212, 0.3);
        }
        
        .btn-info:hover {
            box-shadow: 0 6px 15px rgba(6, 182, 212, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: var(--secondary-color);
            color: white;
            box-shadow: 0 4px 10px rgba(100, 116, 139, 0.3);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 6px 15px rgba(100, 116, 139, 0.4);
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }
        
        .btn-lg {
            padding: 0.75rem 1.5rem;
            font-size: 1rem;
        }

        /* Status Badge Styles */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            gap: 0.375rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .status-badge::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%);
            transition: var(--transition);
            z-index: 1;
        }
        
        .status-badge:hover::before {
            transform: translateX(0);
        }
        
        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .status-badge i {
            z-index: 2;
        }
        
        .status-badge span {
            z-index: 2;
        }
        
        .status-approved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: 1px solid rgba(217, 119, 6, 0.2);
        }
        
        .status-rejected {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: 1px solid rgba(220, 38, 38, 0.2);
        }
        
        .status-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
            border: 1px solid rgba(8, 145, 178, 0.2);
        }

        /* Alert Styles */
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideInDown 0.5s ease;
            position: relative;
            box-shadow: var(--shadow);
            border-left: 4px solid transparent;
        }
        
        .alert i {
            font-size: 1.25rem;
        }
        
        .alert-success {
            background-color: var(--success-bg);
            color: var(--success-color);
            border-left-color: var(--success-color);
        }
        
        .alert-error {
            background-color: var(--danger-bg);
            color: var(--danger-color);
            border-left-color: var(--danger-color);
        }
        
        .alert-close {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: inherit;
            opacity: 0.7;
            transition: var(--transition-fast);
        }
        
        .alert-close:hover {
            opacity: 1;
            transform: translateY(-50%) rotate(90deg);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: var(--z-modal);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
            backdrop-filter: blur(4px);
        }
        
        .modal.show {
            display: flex;
        }
        
        .modal-content {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-xl);
            width: 100%;
            max-width: 500px;
            animation: slideInUp 0.3s ease;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(255, 255, 255, 0.8));
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }
        
        .modal-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: var(--text-color-light);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition-fast);
        }
        
        .modal-close:hover {
            color: var(--danger-color);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            background-color: var(--light-color);
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 1rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-color-dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            background-color: var(--card-bg);
            color: var(--text-color);
            font-size: 0.875rem;
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }
        
        .form-control::placeholder {
            color: var(--text-color-lighter);
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* Section Actions */
        .section-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        /* Profile Actions */
        .profile-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border-color);
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-color);
            font-weight: 500;
            transition: var(--transition);
            margin-bottom: 1.5rem;
        }
        
        .back-button:hover {
            color: var(--primary-dark);
            transform: translateX(-5px);
        }
        
        .back-button i {
            font-size: 1rem;
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes slideInDown {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes slideInUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.1);
                opacity: 0.7;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        /* Responsive Styles */
        @media (max-width: 1200px) {
            .expert-info {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .banking-info {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                overflow-y: hidden;
                max-height: 300px;
                transition: max-height 0.3s ease;
            }
            
            .sidebar.expanded {
                max-height: 100vh;
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .user-info {
                width: 100%;
            }
            
            .expert-profile {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            
            .expert-info {
                grid-template-columns: 1fr;
            }
            
            .expert-info-item {
                justify-content: center;
            }
            
            .expert-skills {
                justify-content: center;
            }
            
            .item-card-info {
                grid-template-columns: 1fr;
            }
            
            .item-card-footer {
                flex-direction: column;
                gap: 1rem;
            }
            
            .item-card-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .section-actions {
                flex-direction: column;
            }
            
            .profile-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        /* Toggle Sidebar Button */
        .toggle-sidebar {
            display: none;
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: var(--shadow-lg);
            z-index: var(--z-fixed);
            transition: var(--transition);
        }
        
        .toggle-sidebar:hover {
            transform: scale(1.1);
        }
        
        .toggle-sidebar i {
            font-size: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .toggle-sidebar {
                display: flex;
            }
            
            .sidebar-toggle-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1rem 1.5rem;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }
            
            .sidebar-toggle-btn {
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
            }
        }

        /* Status Badge Styles - Enhanced */
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.375rem 0.75rem;
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            gap: 0.375rem;
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }

        .status-badge:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .status-approved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: 1px solid rgba(5, 150, 105, 0.2);
        }

        .status-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
            border: 1px solid rgba(217, 119, 6, 0.2);
        }

        .status-rejected {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            border: 1px solid rgba(220, 38, 38, 0.2);
        }

        .status-info {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
            border: 1px solid rgba(8, 145, 178, 0.2);
        }

        /* Section Heading Styles */
        .section-heading {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
        }

        .section-heading::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--primary-gradient);
            border-radius: var(--radius-full);
        }

        .section-heading h3 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        /* Item Card Styles - Enhanced */
        .item-card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 1.25rem;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
        }

        .item-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .item-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
        }

        .item-card:hover::before {
            opacity: 1;
        }

        .item-card-header {
            background: linear-gradient(to right, rgba(248, 250, 252, 0.8), rgba(255, 255, 255, 0.8));
            padding: 1.25rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--border-color);
        }

        .item-card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark-color);
            margin: 0;
        }

        .item-card-subtitle {
            font-size: 0.875rem;
            color: var(--text-color-light);
            margin-top: 0.25rem;
        }

        .item-card-body {
            padding: 1.25rem 1.5rem;
            background-color: white;
        }

        .item-card-info {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.25rem;
        }

        .item-card-info-item {
            display: flex;
            flex-direction: column;
            background-color: var(--light-color-2);
            padding: 1rem;
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .item-card-info-item:hover {
            background-color: var(--primary-bg);
            transform: translateY(-2px);
        }

        .item-card-info-label {
            font-size: 0.75rem;
            color: var(--text-color-light);
            margin-bottom: 0.375rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .item-card-info-value {
            font-size: 0.95rem;
            color: var(--text-color-dark);
            font-weight: 600;
        }

        .item-card-description {
            margin-top: 1.25rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--border-color);
        }

        .item-card-description h4 {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .item-card-description h4::before {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            background: var(--primary-gradient);
            border-radius: 50%;
        }

        .item-card-description p {
            color: var(--text-color);
            font-size: 0.95rem;
            line-height: 1.7;
            padding-left: 1.25rem;
        }

        .item-card-footer {
            background-color: var(--light-color-2);
            padding: 1rem 1.5rem;
            border-top: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .item-card-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .item-card-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* Certificate-specific styles */
        .certificate-card {
            border-left: 4px solid var(--primary-color);
        }

        .certificate-card .item-card-header {
            background: linear-gradient(to right, var(--primary-bg), rgba(255, 255, 255, 0.8));
        }

        /* Experience-specific styles */
        .experience-card {
            border-left: 4px solid var(--success-color);
        }

        .experience-card .item-card-header {
            background: linear-gradient(to right, var(--success-bg), rgba(255, 255, 255, 0.8));
        }

        /* Formation-specific styles */
        .formation-card {
            border-left: 4px solid var(--info-color);
        }

        .formation-card .item-card-header {
            background: linear-gradient(to right, var(--info-bg), rgba(255, 255, 255, 0.8));
        }

        /* Banking-specific styles */
        .banking-info-item {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .banking-info-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--primary-gradient);
            opacity: 0;
            transition: var(--transition);
        }

        .banking-info-item:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .banking-info-item:hover::before {
            opacity: 1;
        }

        .banking-info-label {
            font-size: 0.875rem;
            color: var(--text-color-light);
            margin-bottom: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 500;
        }

        .banking-info-value {
            font-size: 1.125rem;
            color: var(--text-color-dark);
            font-weight: 600;
        }

        .banking-check {
            margin-top: 2rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            padding: 1.5rem;
            border: 1px solid var(--border-color);
            transition: var(--transition);
        }

        .banking-check:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-3px);
        }

        .banking-check-label {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark-color);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .banking-check-label::before {
            content: '';
            display: inline-block;
            width: 16px;
            height: 16px;
            background: var(--primary-gradient);
            border-radius: 50%;
        }

        .banking-check-image {
            width: 100%;
            max-height: 350px;
            object-fit: contain;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .banking-check-image:hover {
            transform: scale(1.03);
            box-shadow: var(--shadow-lg);
        }

        /* Section Actions - Enhanced */
        .section-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2rem;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            background-color: var(--light-color-2);
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow);
        }

        /* Profile Actions - Enhanced */
        .profile-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            margin-top: 2.5rem;
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            background: linear-gradient(to right, var(--light-color-2), var(--light-color));
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-md);
        }

        /* Button Styles - Enhanced */
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            position: relative;
            overflow: hidden;
            z-index: var(--z-normal);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(-100%);
            transition: var(--transition);
            z-index: -1;
        }

        .btn:hover::before {
            transform: translateX(0);
        }

        .btn i {
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(99, 102, 241, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 15px rgba(99, 102, 241, 0.4);
            transform: translateY(-2px);
        }

        .btn-success {
            background: var(--success-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(16, 185, 129, 0.3);
        }

        .btn-success:hover {
            box-shadow: 0 6px 15px rgba(16, 185, 129, 0.4);
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--danger-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3);
        }

        .btn-danger:hover {
            box-shadow: 0 6px 15px rgba(239, 68, 68, 0.4);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.75rem;
        }

        .btn-lg {
            padding: 0.875rem 1.75rem;
            font-size: 1rem;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2><?php echo htmlspecialchars($site_name); ?></h2>
                <p>Admin Dashboard</p>
            </div>
            <div class="sidebar-menu">
                <a href="dashboard.php" class="menu-item">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="users.php" class="menu-item">
                    <i class="fas fa-users"></i> Users
                </a>
                <a href="expert-profiles.php" class="menu-item active">
                    <i class="fas fa-user-tie"></i> Expert Profiles
                    <?php if ($pending_review_profiles > 0): ?>
                        <span class="notification-badge"><?php echo $pending_review_profiles; ?></span>
                    <?php endif; ?>
                </a>
                <a href="expert-messages.php" class="menu-item">
                    <i class="fas fa-comments"></i> Expert Messages
                </a>
                <a href="client-messages.php" class="menu-item">
                    <i class="fas fa-comments"></i> Client Messages
                    <?php if ($pending_messages > 0): ?>
                        <span class="notification-badge"><?php echo $pending_messages; ?></span>
                    <?php endif; ?>
                </a>
                <a href="reports.php" class="menu-item">
                    <i class="fas fa-flag"></i> Reports
                    <?php if ($pending_reports > 0): ?>
                        <span class="notification-badge"><?php echo $pending_reports; ?></span>
                    <?php endif; ?>
                </a>
                <a href="withdrawal-requests.php" class="menu-item">
                    <i class="fas fa-money-bill-wave"></i> Withdrawal Requests
                    <?php if ($pending_withdrawals_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_withdrawals_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="fund-requests.php" class="menu-item">
                    <i class="fas fa-wallet"></i> Fund Requests
                    <?php if ($pending_fund_requests_count > 0): ?>
                        <span class="notification-badge"><?php echo $pending_fund_requests_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="consultations.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i> Consultations
                </a>
                <a href="transactions.php" class="menu-item">
                    <i class="fas fa-exchange-alt"></i> Transactions
                </a>
                <a href="categories.php" class="menu-item">
                    <i class="fas fa-tags"></i> Categories
                </a>
                <a href="notifications.php" class="menu-item">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if ($unread_notifications_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_notifications_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="menu-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <a href="../config/logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-user-tie"></i> Expert Profile Review</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php if (isset($_SESSION["full_name"])): ?>
                            <?php echo strtoupper(substr($_SESSION["full_name"], 0, 1)); ?>
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($_SESSION["full_name"] ?? "Admin"); ?></div>
                        <div class="user-role">Administrator</div>
                    </div>
                </div>
            </div>
            
            <!-- Back Button -->
            <a href="expert-profiles.php" class="back-button">
                <i class="fas fa-arrow-left"></i> Back to Expert Profiles
            </a>
            
            <!-- Alerts -->
            <?php if (isset($_SESSION["admin_message"])): ?>
                <div class="alert alert-<?php echo $_SESSION["admin_message_type"]; ?>">
                    <i class="fas fa-<?php echo $_SESSION["admin_message_type"] === "success" ? "check-circle" : "exclamation-circle"; ?>"></i>
                    <div><?php echo $_SESSION["admin_message"]; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
                <?php unset($_SESSION["admin_message"]); unset($_SESSION["admin_message_type"]); ?>
            <?php endif; ?>
            
            <!-- Expert Profile -->
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-user-tie"></i> Expert Information</h2>
                </div>
                <div class="card-body">
                    <div class="expert-profile">
                        <div class="expert-avatar">
                            <?php
                            $profile_image = '';
                            $profile_query = "SELECT profile_image FROM user_profiles WHERE user_id = ?";
                            $stmt = $conn->prepare($profile_query);
                            $stmt->bind_param("i", $profile['user_id']);
                            $stmt->execute();
                            $result = $stmt->get_result();
                            if ($result->num_rows > 0) {
                                $profile_image = $result->fetch_assoc()['profile_image'];
                            }
                            
                            if (!empty($profile_image)): ?>
                                <img src="<?php echo $profile_image; ?>" alt="<?php echo htmlspecialchars($profile['full_name']); ?>">
                            <?php else: ?>
                                <?php echo strtoupper(substr($profile['full_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <div class="expert-details">
                            <h3 class="expert-name"><?php echo htmlspecialchars($profile['full_name']); ?></h3>
                            <div class="expert-info">
                                <div class="expert-info-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($profile['email']); ?></span>
                                </div>
                                <div class="expert-info-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($profile['phone'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="expert-info-item">
                                    <i class="fas fa-tag"></i>
                                    <span><?php echo htmlspecialchars($profile['category_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="expert-info-item">
                                    <i class="fas fa-tags"></i>
                                    <span><?php echo htmlspecialchars($profile['subcategory_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="expert-info-item">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($profile['city_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="expert-info-item">
                                    <i class="fas fa-calendar-alt"></i>
                                    <span>Submitted: <?php echo formatDate($profile['submitted_at'], 'd/m/Y H:i'); ?></span>
                                </div>
                                <div class="expert-info-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Status: <?php echo getStatusBadge($profile['status']); ?></span>
                                </div>
                            </div>
                            <?php if (!empty($skills)): ?>
                            <div class="expert-skills">
                                <?php foreach ($skills as $skill): ?>
                                    <span class="skill-tag"><?php echo htmlspecialchars($skill); ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

<div class="card">
<div class="card-header">
    <h2><i class="fas fa-edit"></i> Update Category/Subcategory</h2>
</div>
<div class="card-body">
    <form method="post" action="">
        <div class="form-group">
            <label for="new_category" class="form-label">Category</label>
            <select name="new_category" id="new_category" class="form-control" onchange="loadSubcategories(this.value)">
                <option value="">Select Category</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?php echo $category['id']; ?>" <?php echo ($profile['category'] == $category['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label for="new_subcategory" class="form-label">Subcategory</label>
            <select name="new_subcategory" id="new_subcategory" class="form-control">
                <option value="">Select Subcategory</option>
                <?php foreach ($subcategories as $subcategory): ?>
                    <?php if ($profile['category'] == $subcategory['category_id']): ?>
                        <option value="<?php echo $subcategory['id']; ?>" <?php echo ($profile['subcategory'] == $subcategory['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($subcategory['name']); ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="form-group">
            <button type="submit" name="update_category_subcategory" class="btn btn-primary">
                <i class="fas fa-save"></i> Update Category/Subcategory
            </button>
        </div>
    </form>
</div>
</div>

            <?php if ($profile['status'] === 'rejected' && !empty($profile['rejection_reason'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <div><strong>Profile Rejection Reason:</strong> <span style="color: var(--danger-color);"><?php echo htmlspecialchars($profile['rejection_reason']); ?></span></div>
            </div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <div class="tab active" data-tab="certificates">Certificates</div>
                <div class="tab" data-tab="experiences">Experiences</div>
                <div class="tab" data-tab="formations">Formations</div>
                <div class="tab" data-tab="banking">Banking Information</div>
            </div>
            
            <!-- Certificates Tab -->
            <div class="tab-content active" id="certificates-tab">
                <div class="section-heading">
                    <h3>Certificates</h3>
                    <?php echo getStatusBadge($profile['certificates_status']); ?>
                </div>

                <?php if ($profile['certificates_status'] === 'rejected' && !empty($profile['certificates_feedback'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><strong>Section Rejection Reason:</strong> <span style="color: var(--danger-color);"><?php echo htmlspecialchars($profile['certificates_feedback']); ?></span></div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($certificates)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>No certificates found for this expert.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($certificates as $certificate): ?>
                        <div class="item-card certificate-card">
                            <div class="item-card-header">
                                <div>
                                    <div class="item-card-subtitle">
                                        <?php echo formatDate($certificate['start_date']); ?> - <?php echo formatDate($certificate['end_date']); ?>
                                    </div>
                                </div>
                                <div class="item-card-status">
                                    <?php echo getStatusBadge($certificate['status']); ?>
                                </div>
                            </div>
                            <div class="item-card-body">
                                <div class="item-card-info">
                                    <div class="item-card-info-item">
                                        <div class="item-card-info-label">Institution</div>
                                        <div class="item-card-info-value"><?php echo htmlspecialchars($certificate['institution']); ?></div>
                                    </div>
                                </div>
                                <?php if (!empty($certificate['description'])): ?>
                                <div class="item-card-description">
                                    <h4>Description</h4>
                                    <p><?php echo htmlspecialchars($certificate['description']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($certificate['status'] === 'rejected' && !empty($certificate['rejection_reason'])): ?>
                                <div class="item-card-description">
                                    <h4>Rejection Reason</h4>
                                    <p style="color: var(--danger-color);"><?php echo htmlspecialchars($certificate['rejection_reason']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-card-footer">
                                <div>
                                    <?php echo getViewButton($certificate['file_path'], 'certificate'); ?>
                                </div>
                                <div class="item-card-actions">
                                    <?php if ($certificate['status'] === 'pending'): ?>
                                        <form method="post">
                                            <input type="hidden" name="certificate_id" value="<?php echo $certificate['id']; ?>">
                                            <button type="submit" name="approve_certificate" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openRejectModal('certificate', <?php echo $certificate['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="section-actions">
                        <?php if ($profile['certificates_status'] === 'pending_review'): ?>
                        <form method="post">
                            <button type="submit" name="approve_certificates_section" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Approve All Certificates
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger" onclick="openRejectModal('certificates_section')">
                            <i class="fas fa-times-circle"></i> Reject Certificates
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Experiences Tab -->
            <div class="tab-content" id="experiences-tab">
                <div class="section-heading">
                    <h3>Experiences</h3>
                    <?php if (!empty($experiences)): ?>
                    <?php echo getStatusBadge($profile['experiences_status']); ?>
                    <?php endif; ?>
                </div>

                <?php if ($profile['experiences_status'] === 'rejected' && !empty($profile['experiences_feedback'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><strong>Section Rejection Reason:</strong> <span style="color: var(--danger-color);"><?php echo htmlspecialchars($profile['experiences_feedback']); ?></span></div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($experiences)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>No experiences have been added for this expert.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($experiences as $experience): ?>
                        <div class="item-card experience-card">
                            <div class="item-card-header">
                                <div>
                                    <h4 class="item-card-title"><?php echo htmlspecialchars($experience['workplace']); ?></h4>
                                    <div class="item-card-subtitle">
                                        <?php echo formatDate($experience['start_date']); ?> - <?php echo formatDate($experience['end_date']); ?>
                                    </div>
                                </div>
                                <div class="item-card-status">
                                    <?php echo getStatusBadge($experience['status']); ?>
                                </div>
                            </div>
                            <div class="item-card-body">
                                <div class="item-card-info">
                                    <div class="item-card-info-item">
                                        <div class="item-card-info-label">Duration</div>
                                        <div class="item-card-info-value">
                                            <?php 
                                            if ($experience['duration_years'] > 0) {
                                                echo $experience['duration_years'] . ' year(s)';
                                                if ($experience['duration_months'] > 0) {
                                                    echo ' and ' . $experience['duration_months'] . ' month(s)';
                                                }
                                            } else {
                                                echo $experience['duration_months'] . ' month(s)';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                                <?php if (!empty($experience['description'])): ?>
                                <div class="item-card-description">
                                    <h4>Description</h4>
                                    <p><?php echo htmlspecialchars($experience['description']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($experience['status'] === 'rejected' && !empty($experience['rejection_reason'])): ?>
                                <div class="item-card-description">
                                    <h4>Rejection Reason</h4>
                                    <p style="color: var(--danger-color);"><?php echo htmlspecialchars($experience['rejection_reason']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-card-footer">
                                <div>
                                    <?php echo getViewButton($experience['file_path'], 'document'); ?>
                                </div>
                                <div class="item-card-actions">
                                    <?php if ($experience['status'] === 'pending'): ?>
                                        <form method="post">
                                            <input type="hidden" name="experience_id" value="<?php echo $experience['id']; ?>">
                                            <button type="submit" name="approve_experience" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openRejectModal('experience', <?php echo $experience['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="section-actions">
                        <?php if ($profile['experiences_status'] === 'pending_review'): ?>
                        <form method="post">
                            <button type="submit" name="approve_experiences_section" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Approve All Experiences
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger" onclick="openRejectModal('experiences_section')">
                            <i class="fas fa-times-circle"></i> Reject Experiences
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Formations Tab -->
            <div class="tab-content" id="formations-tab">
                <div class="section-heading">
                    <h3>Formations</h3>
                    <?php if (!empty($formations)): ?>
                        <?php echo getStatusBadge($profile['formations_status']); ?>
                    <?php endif; ?>        
                </div>

                <?php if ($profile['formations_status'] === 'rejected' && !empty($profile['formations_feedback'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><strong>Section Rejection Reason:</strong> <span style="color: var(--danger-color);"><?php echo htmlspecialchars($profile['formations_feedback']); ?></span></div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($formations)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>No formations have been added for this expert.</div>
                    </div>
                <?php else: ?>
                    <?php foreach ($formations as $formation): ?>
                        <div class="item-card formation-card">
                            <div class="item-card-header">
                                <div>
                                    <h4 class="item-card-title"><?php echo htmlspecialchars($formation['formation_name']); ?></h4>
                                    <div class="item-card-subtitle">
                                        <?php echo htmlspecialchars($formation['formation_type']); ?> (<?php echo $formation['formation_year']; ?>)
                                    </div>
                                </div>
                                <div class="item-card-status">
                                    <?php echo getStatusBadge($formation['status']); ?>
                                </div>
                            </div>
                            <div class="item-card-body">
                                <?php if (!empty($formation['description'])): ?>
                                <div class="item-card-description">
                                    <h4>Description</h4>
                                    <p><?php echo htmlspecialchars($formation['description']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if ($formation['status'] === 'rejected' && !empty($formation['rejection_reason'])): ?>
                                <div class="item-card-description">
                                    <h4>Rejection Reason</h4>
                                    <p style="color: var(--danger-color);"><?php echo htmlspecialchars($formation['rejection_reason']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="item-card-footer">
                                <div>
                                    <?php echo getViewButton($formation['file_path'], 'document'); ?>
                                </div>
                                <div class="item-card-actions">
                                    <?php if ($formation['status'] === 'pending'): ?>
                                        <form method="post">
                                            <input type="hidden" name="formation_id" value="<?php echo $formation['id']; ?>">
                                            <button type="submit" name="approve_formation" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                        </form>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="openRejectModal('formation', <?php echo $formation['id']; ?>)">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="section-actions">
                        <?php if ($profile['formations_status'] === 'pending_review'): ?>
                        <form method="post">
                            <button type="submit" name="approve_formations_section" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Approve All Formations
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger" onclick="openRejectModal('formations_section')">
                            <i class="fas fa-times-circle"></i> Reject Formations
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Banking Information Tab -->
            <div class="tab-content" id="banking-tab">
                <div class="section-heading">
                    <h3>Banking Information</h3>
                    <?php echo getStatusBadge($profile['banking_status']); ?>
                </div>

                <?php if ($profile['banking_status'] === 'rejected' && !empty($profile['banking_feedback'])): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><strong>Section Rejection Reason:</strong> <span style="color: var(--danger-color);"><?php echo htmlspecialchars($profile['banking_feedback']); ?></span></div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($banking)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>No banking information found for this expert.</div>
                    </div>
                <?php else: ?>
                    <div class="banking-info">
                        <div class="banking-info-item">
                            <div class="banking-info-label">CCP Number</div>
                            <div class="banking-info-value"><?php echo htmlspecialchars($banking['ccp']); ?></div>
                        </div>
                        <div class="banking-info-item">
                            <div class="banking-info-label">CCP Key</div>
                            <div class="banking-info-value"><?php echo htmlspecialchars($banking['ccp_key']); ?></div>
                        </div>
                        <div class="banking-info-item">
                            <div class="banking-info-label">Consultation Minutes</div>
                            <div class="banking-info-value"><?php echo $banking['consultation_minutes']; ?> minutes</div>
                        </div>
                        <div class="banking-info-item">
                            <div class="banking-info-label">Consultation Price</div>
                            <div class="banking-info-value"><?php echo number_format($banking['consultation_price'], 2); ?> DA</div>
                        </div>
                    </div>
                    
                    <?php if (!empty($banking['check_file_path'])): ?>
                    <div class="banking-check">
                        <div class="banking-check-label">Check Image</div>
                        <a href="<?php echo $banking['check_file_path']; ?>" target="_blank">
                            <img src="<?php echo $banking['check_file_path']; ?>" alt="Check Image" class="banking-check-image">
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="section-actions">
                        <?php if ($profile['banking_status'] === 'pending_review'): ?>
                        <form method="post">
                            <button type="submit" name="approve_banking_section" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Approve Banking Information
                            </button>
                        </form>
                        <button type="button" class="btn btn-danger" onclick="openRejectModal('banking_section')">
                            <i class="fas fa-times-circle"></i> Reject Banking Information
                        </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            
            <!-- Profile Actions -->
            <div class="profile-actions">
                <form method="post">
                    <button type="submit" name="approve_profile" class="btn btn-lg btn-success">
                        <i class="fas fa-check-circle"></i> Approve Profile
                    </button>
                </form>
                <button type="button" class="btn btn-lg btn-danger" onclick="openRejectModal('profile')">
                    <i class="fas fa-times-circle"></i> Reject Profile
                </button>
            </div>
        </div>
    </div>
    
    <!-- Mobile Toggle Sidebar Button -->
    <div class="toggle-sidebar">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- Rejection Modals -->
    <!-- Certificate Rejection Modal -->
    <div class="modal" id="reject-certificate-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Certificate</h3>
                <button type="button" class="modal-close" onclick="closeModal('reject-certificate-modal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="certificate_id" id="certificate_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="certificate-rejection-reason" class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" id="certificate-rejection-reason" class="form-control" placeholder="Enter reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-certificate-modal')">Cancel</button>
                    <button type="submit" name="reject_certificate" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Experience Rejection Modal -->
    <div class="modal" id="reject-experience-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Experience</h3>
                <button type="button" class="modal-close" onclick="closeModal('reject-experience-modal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="experience_id" id="experience_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="experience-rejection-reason" class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" id="experience-rejection-reason" class="form-control" placeholder="Enter reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-experience-modal')">Cancel</button>
                    <button type="submit" name="reject_experience" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Formation Rejection Modal -->
    <div class="modal" id="reject-formation-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Formation</h3>
                <button type="button" class="modal-close" onclick="closeModal('reject-formation-modal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="formation_id" id="formation_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="formation-rejection-reason" class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" id="formation-rejection-reason" class="form-control" placeholder="Enter reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-formation-modal')">Cancel</button>
                    <button type="submit" name="reject_formation" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
  
    
    <!-- Certificates Section Rejection Modal -->
    <div class="modal" id="reject-certificates_section-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Certificates Section</h3>
                <button type="button" class="modal-close" onclick="closeModal('reject-certificates_section-modal')">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="certificates-section-rejection-reason" class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" id="certificates-section-rejection-reason" class="form-control" placeholder="Enter reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-certificates_section-modal')">Cancel</button>
                    <button type="submit" name="reject_certificates_section" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Experiences Section Rejection Modal -->
    <div class="modal" id="reject-experiences_section-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Experiences Section</h3>
                <button type="button" class="modal-close" onclick="closeModal('reject-experiences_section-modal')">&times;</button>
            </div>
            <form method="post">
                <input type="hidden" name="experience_id" id="experience_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="experiences-section-rejection-reason" class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" id="experiences-section-rejection-reason" class="form-control" placeholder="Enter reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-experiences_section-modal')">Cancel</button>
                    <button type="submit" name="reject_experiences_section" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Formations Section Rejection Modal -->
    <div class="modal" id="reject-formations_section-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Formations Section</h3>
                <button type="button" class="modal-close" onclick="closeModal('reject-formations_section-modal')">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="formations-section-rejection-reason" class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" id="formations-section-rejection-reason" class="form-control" placeholder="Enter reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-formations_section-modal')">Cancel</button>
                    <button type="submit" name="reject_formations_section" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Banking Section Rejection Modal -->
    <div class="modal" id="reject-banking_section-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Banking Information</h3>
                <button type="button" class="modal-close" onclick="closeModal('reject-banking_section-modal')">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="banking-section-rejection-reason" class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" id="banking-section-rejection-reason" class="form-control" placeholder="Enter reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-banking_section-modal')">Cancel</button>
                    <button type="submit" name="reject_banking_section" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Profile Rejection Modal -->
    <div class="modal" id="reject-profile-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Reject Expert Profile</h3>
                <button type="button" class="modal-close" onclick="closeModal('reject-profile-modal')">&times;</button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="profile-rejection-reason" class="form-label">Rejection Reason</label>
                        <textarea name="rejection_reason" id="profile-rejection-reason" class="form-control" placeholder="Enter reason for rejection" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject-profile-modal')">Cancel</button>
                    <button type="submit" name="reject_profile" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Tab functionality
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const tabId = tab.getAttribute('data-tab');
                
                // Remove active class from all tabs and tab contents
                tabs.forEach(t => t.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding tab content
                tab.classList.add('active');
                document.getElementById(`${tabId}-tab`).classList.add('active');
            });
        });
        
        // Modal functionality
        function openRejectModal(type, id = null) {
            const modal = document.getElementById(`reject-${type}-modal`);
            modal.classList.add('show');
            
            if (id !== null) {
                if (type === 'profile_attachment') {
                    document.getElementById('profile_attachment_id').value = id;
                } else {
                    document.getElementById(`${type}_id`).value = id;
                }
            }
        }
        
        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            modal.classList.remove('show');
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });
        
        // Mobile sidebar toggle
        const toggleSidebar = document.querySelector('.toggle-sidebar');
        const sidebar = document.querySelector('.sidebar');
        
        if (toggleSidebar) {
            toggleSidebar.addEventListener('click', () => {
                sidebar.classList.toggle('expanded');
            });
        }
        
        // Auto-hide alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                alert.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            }, 5000000000);
        });

        // Function to load subcategories based on selected category
        function loadSubcategories(categoryId) {
            // Clear current options
            const subcategorySelect = document.getElementById('new_subcategory');
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
            
            if (!categoryId) return;
            
            // Add subcategories for the selected category
            <?php foreach ($subcategories as $subcategory): ?>
            if (categoryId == <?php echo $subcategory['category_id']; ?>) {
                const option = document.createElement('option');
                option.value = <?php echo $subcategory['id']; ?>;
                option.textContent = '<?php echo htmlspecialchars($subcategory['name']); ?>';
                subcategorySelect.appendChild(option);
            }
            <?php endforeach; ?>
        }

        // Initialize subcategories on page load if category is selected
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('new_category');
            if (categorySelect && categorySelect.value) {
                loadSubcategories(categorySelect.value);
                
                // Set the current subcategory as selected
                const subcategorySelect = document.getElementById('new_subcategory');
                const currentSubcategory = '<?php echo $profile['subcategory']; ?>';
                if (currentSubcategory) {
                    for (let i = 0; i < subcategorySelect.options.length; i++) {
                        if (subcategorySelect.options[i].value === currentSubcategory) {
                            subcategorySelect.options[i].selected = true;
                            break;
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>
