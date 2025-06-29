<?php
// get-expert-profile.php
session_start();

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit();
}

// Include database connection
require_once '../config/config.php';

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if expert ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Expert ID is required']);
    exit();
}

$expert_id = sanitize_input($_GET['id']);

// Get expert data
$expert_sql = "SELECT u.id, u.full_name, u.email, up.phone, up.address, up.dob, up.gender, up.bio, up.profile_image, 
              ep.id as profile_id, ep.category, ep.subcategory, ep.city, ep.workplace_map_url, ep.status, 
              c.name as category_name, sc.name as subcategory_name, 
              ct.name as city_name, 
              (SELECT AVG(rating) FROM expert_ratings WHERE expert_id = ep.id) as average_rating,
              (SELECT COUNT(*) FROM expert_ratings WHERE expert_id = ep.id) as rating_count
              FROM expert_profiledetails ep
              JOIN users u ON ep.user_id = u.id
              JOIN user_profiles up ON u.id = up.user_id
              LEFT JOIN categories c ON ep.category = c.id
              LEFT JOIN subcategories sc ON ep.subcategory = sc.id
              LEFT JOIN cities ct ON ep.city = ct.id
              WHERE ep.id = ? AND ep.status = 'approved'";

$expert_stmt = $conn->prepare($expert_sql);
$expert_stmt->bind_param("i", $expert_id);
$expert_stmt->execute();
$expert_result = $expert_stmt->get_result();

if ($expert_result->num_rows === 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Expert not found or not approved']);
    exit();
}

$expert = $expert_result->fetch_assoc();

// Get expert skills
$skills_sql = "SELECT skill_name FROM skills WHERE profile_id = ?";
$skills_stmt = $conn->prepare($skills_sql);
$skills_stmt->bind_param("i", $expert_id);
$skills_stmt->execute();
$skills_result = $skills_stmt->get_result();
$skills = [];
while ($skill_row = $skills_result->fetch_assoc()) {
    $skills[] = $skill_row['skill_name'];
}
$expert['skills'] = $skills;

// Get social links
$social_sql = "SELECT * FROM expert_social_links WHERE profile_id = ?";
$social_stmt = $conn->prepare($social_sql);
$social_stmt->bind_param("i", $expert_id);
$social_stmt->execute();
$social_result = $social_stmt->get_result();
if ($social_result->num_rows > 0) {
    $expert['social_links'] = $social_result->fetch_assoc();
} else {
    $expert['social_links'] = null;
}

// Get certificates
$cert_sql = "SELECT * FROM certificates WHERE profile_id = ? AND status = 'approved'";
$cert_stmt = $conn->prepare($cert_sql);
$cert_stmt->bind_param("i", $expert_id);
$cert_stmt->execute();
$cert_result = $cert_stmt->get_result();
$certificates = [];
while ($cert_row = $cert_result->fetch_assoc()) {
    $certificates[] = $cert_row;
}
$expert['certificates'] = $certificates;

// Get experiences
$exp_sql = "SELECT * FROM experiences WHERE profile_id = ? AND status = 'approved'";
$exp_stmt = $conn->prepare($exp_sql);
$exp_stmt->bind_param("i", $expert_id);
$exp_stmt->execute();
$exp_result = $exp_stmt->get_result();
$experiences = [];
while ($exp_row = $exp_result->fetch_assoc()) {
    $experiences[] = $exp_row;
}
$expert['experiences'] = $experiences;

// Get formations
$form_sql = "SELECT * FROM formations WHERE profile_id = ? AND status = 'approved'";
$form_stmt = $conn->prepare($form_sql);
$form_stmt->bind_param("i", $expert_id);
$form_stmt->execute();
$form_result = $form_stmt->get_result();
$formations = [];
while ($form_row = $form_result->fetch_assoc()) {
    $formations[] = $form_row;
}
$expert['formations'] = $formations;

// Return expert data as JSON
header('Content-Type: application/json');
echo json_encode(['success' => true, 'expert' => $expert]);

// Close database connection
$conn->close();
?>