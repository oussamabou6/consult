<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';

// Check if user is logged in
if (!isset($_SESSION["user_id"]) || !isset($_SESSION["email"]) || $_SESSION["user_role"] != "expert") {
    // User is not logged in, redirect to login page
    header("Location: ../config/logout.php");
    exit();
}

// Check if user is an expert
if ($_SESSION["user_role"] !== "expert") {
    header("location: ../pages/profile.php");
    exit;
}

// Initialize variables
$user_id = $_SESSION["user_id"];
$error_message = "";
$success_message = "";


// Get categories for dropdown
$categories_sql = "SELECT id, name FROM categories ORDER BY name";
$categories_result = $conn->query($categories_sql);

// Get cities from database
$cities_sql = "SELECT * FROM cities ORDER BY id";
$cities_result = $conn->query($cities_sql);

// Check if expert profile already exists
$check_sql = "SELECT * FROM expert_profiledetails WHERE user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$profile_data = $check_result->fetch_assoc();

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // 1. Save basic profile details
        $category = $_POST["category"];
        $subcategory = isset($_POST["subcategory"]) && !empty($_POST["subcategory"]) ? $_POST["subcategory"] : "0";
        $city = $_POST["city"];
        $skills = $_POST["skills"];
        
        if ($check_result->num_rows > 0) {
            // Update existing profile
            $update_sql = "UPDATE expert_profiledetails SET 
                category = ?, 
                subcategory = ?, 
                city = ?, 
                status = 'pending_review', 
                submitted_at = NOW() 
                WHERE user_id = ?";
                
            $update_stmt = $conn->prepare($update_sql);
            
            if ($update_stmt === false) {
                throw new Exception("Error preparing update query: " . $conn->error);
            }
            
            $update_stmt->bind_param("sssi", $category, $subcategory, $city, $user_id);
            $update_stmt->execute();
            
            // Get the profile ID
            $profile_id = $profile_data['id'];
        } else {
            // Insert new profile
            $insert_sql = "INSERT INTO expert_profiledetails (
                user_id, 
                category, 
                subcategory, 
                city, 
                status, 
                submitted_at
            ) VALUES (?, ?, ?, ?, 'pending_review', NOW())";
            
            $insert_stmt = $conn->prepare($insert_sql);
            
            if ($insert_stmt === false) {
                throw new Exception("Error preparing insert query: " . $conn->error);
            }
            
            $insert_stmt->bind_param("isss", $user_id, $category, $subcategory, $city);
            $insert_stmt->execute();
            
            // Get the new profile ID
            $profile_id = $conn->insert_id;
        }
        
        // 2. Save skills
        if (!empty($skills)) {
            // First delete existing skills
            $delete_skills_sql = "DELETE FROM skills WHERE profile_id = ?";
            $delete_skills_stmt = $conn->prepare($delete_skills_sql);
            $delete_skills_stmt->bind_param("i", $profile_id);
            $delete_skills_stmt->execute();
            
            // Insert new skills
            $skills_array = explode(',', $skills);
            foreach ($skills_array as $skill) {
                $skill = trim($skill);
                if (!empty($skill)) {
                    $insert_skill_sql = "INSERT INTO skills (profile_id, skill_name) VALUES (?, ?)";
                    $insert_skill_stmt = $conn->prepare($insert_skill_sql);
                    $insert_skill_stmt->bind_param("is", $profile_id, $skill);
                    $insert_skill_stmt->execute();
                }
            }
        }
        
        // 3. Process certificates
        // First, delete existing certificates
        $delete_cert_sql = "DELETE FROM certificates WHERE profile_id = ?";
        $delete_cert_stmt = $conn->prepare($delete_cert_sql);
        $delete_cert_stmt->bind_param("i", $profile_id);
        $delete_cert_stmt->execute();
        
        // Get the number of certificate sections
        $cert_section_count = isset($_POST["cert_section_count"]) ? intval($_POST["cert_section_count"]) : 0;
        
        // Traiter chaque section de certificat
        for ($i = 1; $i <= $cert_section_count; $i++) {
            // Vérifier si cette section existe
            if (isset($_POST["certificate_institution_{$i}"]) && !empty($_POST["certificate_institution_{$i}"])) {
                $cert_start_date = $_POST["certificate_start_date_{$i}"];
                $cert_end_date = $_POST["certificate_end_date_{$i}"];
                $cert_institution = $_POST["certificate_institution_{$i}"];
                $cert_description = $_POST["certificate_description_{$i}"];
                
                // Handle file upload
                $cert_file_path = "";
                if (isset($_FILES["certificate_file_{$i}"]) && $_FILES["certificate_file_{$i}"]["error"] == 0) {
                    $target_dir = "../uploads/certificates/";
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    
                    $file_extension = pathinfo($_FILES["certificate_file_{$i}"]["name"], PATHINFO_EXTENSION);
                    $new_filename = "cert_" . $user_id . "_" . $i . "_" . time() . "." . $file_extension;
                    $target_file = $target_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES["certificate_file_{$i}"]["tmp_name"], $target_file)) {
                        $cert_file_path = $target_file;
                    }
                }
                
                // Insert certificate
                $insert_cert_sql = "INSERT INTO certificates (
                    profile_id, 
                    section_id,
                    certificate_id,
                    institution, 
                    start_date, 
                    end_date, 
                    description, 
                    file_path, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                
                $insert_cert_stmt = $conn->prepare($insert_cert_sql);
                
                if ($insert_cert_stmt === false) {
                    throw new Exception("Error preparing certificate insert query: " . $conn->error);
                }
                
                // Utiliser 1 comme valeur par défaut pour certificate_id
                $cert_id = 1;
                
                $insert_cert_stmt->bind_param("iiisssss", $profile_id, $i, $cert_id, $cert_institution, $cert_start_date, $cert_end_date, $cert_description, $cert_file_path);
                $insert_cert_stmt->execute();
            }
        }
        
        // 4. Process experiences
        // First, delete existing experiences
        $delete_exp_sql = "DELETE FROM experiences WHERE profile_id = ?";
        $delete_exp_stmt = $conn->prepare($delete_exp_sql);
        $delete_exp_stmt->bind_param("i", $profile_id);
        $delete_exp_stmt->execute();
        
        // Check if experiences were submitted
        if (isset($_POST["workplace"]) && is_array($_POST["workplace"])) {
            $workplaces = $_POST["workplace"];
            $start_dates = $_POST["start_date"];
            $end_dates = $_POST["end_date"];
            $duration_years = $_POST["duration_years"];
            $duration_months = $_POST["duration_months"];
            $experience_descriptions = $_POST["experience_description"];
            
            for ($i = 0; $i < count($workplaces); $i++) {
                if (!empty($workplaces[$i])) {
                    // Handle file upload
                    $exp_file_path = "";
                    if (isset($_FILES["experience_file"]["name"][$i]) && !empty($_FILES["experience_file"]["name"][$i])) {
                        $target_dir = "../uploads/experiences/";
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        
                        $file_extension = pathinfo($_FILES["experience_file"]["name"][$i], PATHINFO_EXTENSION);
                        $new_filename = "exp_" . $user_id . "_" . $i . "_" . time() . "." . $file_extension;
                        $target_file = $target_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES["experience_file"]["tmp_name"][$i], $target_file)) {
                            $exp_file_path = $target_file;
                        }
                    }
                    
                    // Insert experience
                    $insert_exp_sql = "INSERT INTO experiences (
                        profile_id, 
                        workplace, 
                        start_date, 
                        end_date, 
                        duration_years, 
                        duration_months, 
                        description, 
                        file_path, 
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
                    
                    $insert_exp_stmt = $conn->prepare($insert_exp_sql);
                    
                    if ($insert_exp_stmt === false) {
                        throw new Exception("Error preparing experience insert query: " . $conn->error);
                    }
                    
                    $insert_exp_stmt->bind_param("isssssss", $profile_id, $workplaces[$i], $start_dates[$i], $end_dates[$i], $duration_years[$i], $duration_months[$i], $experience_descriptions[$i], $exp_file_path);
                    $insert_exp_stmt->execute();
                }
            }
        }
        
        // 5. Process formations
        // First, delete existing formations
        $delete_form_sql = "DELETE FROM formations WHERE profile_id = ?";
        $delete_form_stmt = $conn->prepare($delete_form_sql);
        $delete_form_stmt->bind_param("i", $profile_id);
        $delete_form_stmt->execute();
        
        // Check if formations were submitted
        if (isset($_POST["formation_name"]) && is_array($_POST["formation_name"])) {
            $formation_names = $_POST["formation_name"];
            $formation_types = $_POST["formation_type"];
            $formation_years = $_POST["formation_year"];
            $formation_descriptions = $_POST["formation_description"];
            
            for ($i = 0; $i < count($formation_names); $i++) {
                if (!empty($formation_names[$i])) {
                    // Handle file upload
                    $form_file_path = "";
                    if (isset($_FILES["formation_file"]["name"][$i]) && !empty($_FILES["formation_file"]["name"][$i])) {
                        $target_dir = "../uploads/formations/";
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($target_dir)) {
                            mkdir($target_dir, 0777, true);
                        }
                        
                        $file_extension = pathinfo($_FILES["formation_file"]["name"][$i], PATHINFO_EXTENSION);
                        $new_filename = "form_" . $user_id . "_" . $i . "_" . time() . "." . $file_extension;
                        $target_file = $target_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES["formation_file"]["tmp_name"][$i], $target_file)) {
                            $form_file_path = $target_file;
                        }
                    }
                    
                    // Insert formation
                    $insert_form_sql = "INSERT INTO formations (
                        profile_id, 
                        formation_name, 
                        formation_type, 
                        formation_year, 
                        description, 
                        file_path, 
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                    
                    $insert_form_stmt = $conn->prepare($insert_form_sql);
                    
                    if ($insert_form_stmt === false) {
                        throw new Exception("Error preparing formation insert query: " . $conn->error);
                    }
                    
                    $insert_form_stmt->bind_param("isssss", $profile_id, $formation_names[$i], $formation_types[$i], $formation_years[$i], $formation_descriptions[$i], $form_file_path);
                    $insert_form_stmt->execute();
                }
            }
        }
        
        // Create notification for admin
        $notification_message = "New expert profile submitted for review";
        if ($check_result->num_rows > 0) {
            $notification_message = "Expert profile updated and waiting for review";
        }
        
        $insert_notification_sql = "INSERT INTO admin_notifications (
            user_id, 
            profile_id, 
            notification_type, 
            message, 
            created_at
        ) VALUES (?, ?, ?, ?, NOW())";
        
        $notification_type = $check_result->num_rows > 0 ? "profile_updated" : "new_profile";
        
        $insert_notification_stmt = $conn->prepare($insert_notification_sql);
        $insert_notification_stmt->bind_param("iiss", $user_id, $profile_id, $notification_type, $notification_message);
        $insert_notification_stmt->execute();
        
        // Commit transaction
        $conn->commit();

        // Set success message
        $success_message = "Profile details saved successfully!";
        
        // Redirect to banking information page
        header("location: bankinformation.php");
        exit;
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get existing data for pre-filling the form
$category_id = "";
$subcategory_id = "";
$city_id = "";
$skills = [];

if ($profile_data) {
    $category_id = $profile_data["category"];
    $subcategory_id = $profile_data["subcategory"];
    $city_id = $profile_data["city"];
    
    // Get skills
    $skills_sql = "SELECT skill_name FROM skills WHERE profile_id = ?";
    $skills_stmt = $conn->prepare($skills_sql);
    $skills_stmt->bind_param("i", $profile_data['id']);
    $skills_stmt->execute();
    $skills_result = $skills_stmt->get_result();
    
    while ($skill = $skills_result->fetch_assoc()) {
        $skills[] = $skill['skill_name'];
    }
}

// Get existing certificates
$certificates = [];

if ($profile_data) {
    $cert_sql = "SELECT * FROM certificates WHERE profile_id = ? ORDER BY section_id, certificate_id";
    $cert_stmt = $conn->prepare($cert_sql);
    $cert_stmt->bind_param("i", $profile_data['id']);
    $cert_stmt->execute();
    $cert_result = $cert_stmt->get_result();
    
    while ($row = $cert_result->fetch_assoc()) {
        $certificates[] = $row;
    }
}

// Get existing experiences
$experiences = [];

if ($profile_data) {
    $exp_sql = "SELECT * FROM experiences WHERE profile_id = ? ORDER BY id";
    $exp_stmt = $conn->prepare($exp_sql);
    $exp_stmt->bind_param("i", $profile_data['id']);
    $exp_stmt->execute();
    $exp_result = $exp_stmt->get_result();
    
    while ($row = $exp_result->fetch_assoc()) {
        $experiences[] = $row;
    }
}

// Get existing formations
$formations = [];

if ($profile_data) {
    $form_sql = "SELECT * FROM formations WHERE profile_id = ? ORDER BY id";
    $form_stmt = $conn->prepare($form_sql);
    $form_stmt->bind_param("i", $profile_data['id']);
    $form_stmt->execute();
    $form_result = $form_stmt->get_result();
    
    while ($row = $form_result->fetch_assoc()) {
        $formations[] = $row;
    }
}

// Get subcategories for the selected category
$subcategories = [];
if (!empty($category_id)) {
    $subcat_sql = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name";
    $subcat_stmt = $conn->prepare($subcat_sql);
    $subcat_stmt->bind_param("i", $category_id);
    $subcat_stmt->execute();
    $subcat_result = $subcat_stmt->get_result();
    
    while ($row = $subcat_result->fetch_assoc()) {
        $subcategories[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert Profile | ConsultPro</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" integrity="sha512-iecdLmaskl7CVkqkXNQ/ZH/XLlvWZOJyj7Yy7tcenmpD1ypASozpmT/E0iPtmFIB46ZmdtAc9eNBvH0H/ZpiBw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&family=Montserrat:wght@400;500;600;700;800&family=Orbitron:wght@400;500;600;700&display=swap" rel="stylesheet">
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
            
            /* Secondary Colors */
            --secondary-50: #f5f3ff;
            --secondary-100: #ede9fe;
            --secondary-200: #ddd6fe;
            --secondary-300: #c4b5fd;
            --secondary-400: #a78bfa;
            --secondary-500: #8b5cf6;
            --secondary-600: #7c3aed;
            --secondary-700: #6d28d9;
            --secondary-800: #5b21b6;
            --secondary-900: #4c1d95;
            --secondary-950: #2e1065;
            
            /* Accent Colors */
            --accent-50: #fff7ed;
            --accent-100: #ffedd5;
            --accent-200: #fed7aa;
            --accent-300: #fdba74;
            --accent-400: #fb923c;
            --accent-500: #f97316;
            --accent-600: #ea580c;
            --accent-700: #c2410c;
            --accent-800: #9a3412;
            --accent-900: #7c2d12;
            --accent-950: #431407;
            
            /* Success Colors */
            --success-50: #ecfdf5;
            --success-100: #d1fae5;
            --success-200: #a7f3d0;
            --success-300: #6ee7b7;
            --success-400: #34d399;
            --success-500: #10b981;
            --success-600: #059669;
            --success-700: #047857;
            --success-800: #065f46;
            --success-900: #064e3b;
            --success-950: #022c22;
            
            /* Warning Colors */
            --warning-50: #fffbeb;
            --warning-100: #fef3c7;
            --warning-200: #fde68a;
            --warning-300: #fcd34d;
            --warning-400: #fbbf24;
            --warning-500: #f59e0b;
            --warning-600: #d97706;
            --warning-700: #b45309;
            --warning-800: #92400e;
            --warning-900: #78350f;
            --warning-950: #451a03;
            
            /* Danger Colors */
            --danger-50: #fef2f2;
            --danger-100: #fee2e2;
            --danger-200: #fecaca;
            --danger-300: #fca5a5;
            --danger-400: #f87171;
            --danger-500: #ef4444;
            --danger-600: #dc2626;
            --danger-700: #b91c1c;
            --danger-800: #991b1b;
            --danger-900: #7f1d1d;
            --danger-950: #450a0a;
            
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
            --border-radius-2xl: 2rem;
            --border-radius-3xl: 3rem;
            --border-radius-full: 9999px;
            
            --box-shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --box-shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --box-shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --box-shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --box-shadow-2xl: 0 25px 50px -12px rgb(0 0 0 / 0.25);
            
            --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition: 200ms cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: 300ms cubic-bezier(0.4, 0, 0.2, 1);
            
            /* Glow Colors (derived from primary colors) */
            --primary-glow: rgba(37, 99, 235, 0.5);
            --secondary-glow: rgba(124, 58, 237, 0.5);
            --accent-glow: rgba(234, 88, 12, 0.5);
            --success-glow: rgba(5, 150, 105, 0.5);
            --warning-glow: rgba(217, 119, 6, 0.5);
            --danger-glow: rgba(220, 38, 38, 0.5);
            --info-glow: rgba(6, 182, 212, 0.5);
            
            /* Shadow Effects */
            --shadow-neon: 0 0 10px var(--primary-glow), 0 0 20px var(--primary-glow), 0 0 30px var(--primary-glow);
            --shadow-neon-accent: 0 0 10px var(--accent-glow), 0 0 20px var(--accent-glow), 0 0 30px var(--accent-glow);
            --shadow-neon-secondary: 0 0 10px var(--secondary-glow), 0 0 20px var(--secondary-glow), 0 0 30px var(--secondary-glow);
            
            /* Transitions */
            --transition-bounce: all 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
            --transition-spring: all 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            --transition-elastic: all 1s cubic-bezier(0.68, -0.6, 0.32, 1.6);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--primary-100) 50%, var(--secondary-100) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            color: var(--gray-700);
            line-height: 1.6;
            position: relative;
            overflow-x: hidden;
            perspective: 1000px;
        }

        /* Advanced Animated Background */
        .background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }

        .bg-gradient {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary-50) 0%, var(--primary-100) 50%, var(--secondary-100) 100%);
            opacity: 0.8;
            animation: gradientShift 15s infinite alternate;
        }

        @keyframes gradientShift {
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

        .bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 25px 25px, var(--primary-200) 2px, transparent 0),
                radial-gradient(circle at 75px 75px, var(--secondary-200) 2px, transparent 0);
            background-size: 100px 100px;
            opacity: 0.6;
            animation: patternMove 20s linear infinite;
        }

        @keyframes patternMove {
            0% {
                background-position: 0 0;
            }
            100% {
                background-position: 100px 100px;
            }
        }

        .bg-grid {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(var(--primary-200) 1px, transparent 1px),
                            linear-gradient(90deg, var(--primary-200) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: gridMove 15s linear infinite;
        }

        @keyframes gridMove {
            0% {
                background-position: 0 0;
            }
            100% {
                background-position: 50px 50px;
            }
        }

        /* Advanced Animated Shapes */
        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        .shape {
            position: absolute;
            filter: blur(70px);
            opacity: 0.2;
            animation: float 20s infinite ease-in-out;
        }

        .shape-1 {
            width: 500px;
            height: 500px;
            background: var(--primary-300);
            top: -200px;
            right: -100px;
            animation-delay: 0s;
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
        }

        .shape-2 {
            width: 400px;
            height: 400px;
            background: var(--secondary-300);
            bottom: -150px;
            left: -150px;
            animation-delay: -5s;
            border-radius: 60% 40% 30% 70% / 60% 30% 70% 40%;
        }

        .shape-3 {
            width: 300px;
            height: 300px;
            background: var(--accent-300);
            top: 40%;
            left: 30%;
            animation-delay: -10s;
            border-radius: 50% 50% 20% 80% / 25% 80% 20% 75%;
        }

        .shape-4 {
            width: 250px;
            height: 250px;
            background: var(--success-300);
            top: 60%;
            right: 20%;
            animation-delay: -15s;
            border-radius: 80% 20% 40% 60% / 50% 60% 40% 50%;
        }

        .shape-5 {
            width: 200px;
            height: 200px;
            background: var(--warning-300);
            top: 20%;
            right: 30%;
            animation-delay: -7s;
            border-radius: 40% 60% 60% 40% / 70% 30% 70% 30%;
        }

        .shape-6 {
            width: 180px;
            height: 180px;
            background: var(--danger-300);
            bottom: 30%;
            left: 20%;
            animation-delay: -12s;
            border-radius: 70% 30% 50% 50% / 30% 70% 30% 70%;
        }

        .shape-7 {
            width: 220px;
            height: 220px;
            background: var(--primary-400);
            top: 10%;
            left: 10%;
            animation-delay: -9s;
            border-radius: 20% 80% 40% 60% / 60% 40% 60% 40%;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0) scale(1) rotate(0deg);
            }
            25% {
                transform: translateY(-30px) scale(1.05) rotate(5deg);
            }
            50% {
                transform: translateY(0) scale(1.1) rotate(0deg);
            }
            75% {
                transform: translateY(30px) scale(1.05) rotate(-5deg);
            }
        }

        /* Advanced Animated Particles */
        .particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: 0;
        }

        .particle {
            position: absolute;
            display: block;
            pointer-events: none;
            border-radius: 50%;
            animation: particles 15s linear infinite;
        }

        .particle:nth-child(1) {
            top: 20%;
            left: 20%;
            width: 8px;
            height: 8px;
            background-color: var(--primary-glow);
            box-shadow: 0 0 10px var(--primary-glow);
            animation-delay: 0s;
        }

        .particle:nth-child(2) {
            top: 80%;
            left: 80%;
            width: 10px;
            height: 10px;
            background-color: var(--secondary-glow);
            box-shadow: 0 0 10px var(--secondary-glow);
            animation-delay: -2s;
        }

        .particle:nth-child(3) {
            top: 40%;
            left: 60%;
            width: 12px;
            height: 12px;
            background-color: var(--accent-glow);
            box-shadow: 0 0 10px var(--accent-glow);
            animation-delay: -4s;
        }

        .particle:nth-child(4) {
            top: 60%;
            left: 40%;
            width: 9px;
            height: 9px;
            background-color: var(--success-glow);
            box-shadow: 0 0 10px var(--success-glow);
            animation-delay: -6s;
        }

        .particle:nth-child(5) {
            top: 30%;
            left: 70%;
            width: 11px;
            height: 11px;
            background-color: var(--warning-glow);
            box-shadow: 0 0 10px var(--warning-glow);
            animation-delay: -8s;
        }

        .particle:nth-child(6) {
            top: 70%;
            left: 30%;
            width: 7px;
            height: 7px;
            background-color: var(--danger-glow);
            box-shadow: 0 0 10px var(--danger-glow);
            animation-delay: -10s;
        }

        .particle:nth-child(7) {
            top: 50%;
            left: 50%;
            width: 10px;
            height: 10px;
            background-color: var(--primary-glow);
            box-shadow: 0 0 10px var(--primary-glow);
            animation-delay: -12s;
        }

        .particle:nth-child(8) {
            top: 10%;
            left: 90%;
            width: 8px;
            height: 8px;
            background-color: var(--secondary-glow);
            box-shadow: 0 0 10px var(--secondary-glow);
            animation-delay: -14s;
        }

        .particle:nth-child(9) {
            top: 90%;
            left: 10%;
            width: 9px;
            height: 9px;
            background-color: var(--accent-glow);
            box-shadow: 0 0 10px var(--accent-glow);
            animation-delay: -16s;
        }

        .particle:nth-child(10) {
            top: 25%;
            left: 75%;
            width: 11px;
            height: 11px;
            background-color: var(--info-glow);
            box-shadow: 0 0 10px var(--info-glow);
            animation-delay: -18s;
        }

        .particle:nth-child(11) {
            top: 75%;
            left: 25%;
            width: 10px;
            height: 10px;
            background-color: var(--success-glow);
            box-shadow: 0 0 10px var(--success-glow);
            animation-delay: -20s;
        }

        .particle:nth-child(12) {
            top: 35%;
            left: 85%;
            width: 7px;
            height: 7px;
            background-color: var(--warning-glow);
            box-shadow: 0 0 10px var(--warning-glow);
            animation-delay: -22s;
        }

        .particle:nth-child(13) {
            top: 85%;
            left: 35%;
            width: 9px;
            height: 9px;
            background-color: var(--danger-glow);
            box-shadow: 0 0 10px var(--danger-glow);
            animation-delay: -24s;
        }

        .particle:nth-child(14) {
            top: 15%;
            left: 45%;
            width: 8px;
            height: 8px;
            background-color: var(--info-glow);
            box-shadow: 0 0 10px var(--info-glow);
            animation-delay: -26s;
        }

        .particle:nth-child(15) {
            top: 45%;
            left: 15%;
            width: 10px;
            height: 10px;
            background-color: var(--primary-glow);
            box-shadow: 0 0 10px var(--primary-glow);
            animation-delay: -28s;
        }

        @keyframes particles {
            0% {
                transform: translate(0, 0) rotate(0deg) scale(1);
                opacity: 0.2;
            }
            25% {
                transform: translate(100px, 50px) rotate(90deg) scale(1.2);
                opacity: 0.7;
            }
            50% {
                transform: translate(50px, 100px) rotate(180deg) scale(1);
                opacity: 0.2;
            }
            75% {
                transform: translate(-50px, 50px) rotate(270deg) scale(0.8);
                opacity: 0.7;
            }
            100% {
                transform: translate(0, 0) rotate(360deg) scale(1);
                opacity: 0.2;
            }
        }

        /* Binary Rain Effect */
        .binary-rain {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 0;
            overflow: hidden;
        }

        .binary {
            position: absolute;
            color: var(--primary-300);
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1;
            animation: binary-fall linear infinite;
        }

        @keyframes binary-fall {
            0% {
                transform: translateY(-100%);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(1000%);
                opacity: 0;
            }
        }

        .binary:nth-child(1) { left: 10%; animation-duration: 15s; animation-delay: 0s; }
        .binary:nth-child(2) { left: 20%; animation-duration: 18s; animation-delay: -5s; }
        .binary:nth-child(3) { left: 30%; animation-duration: 16s; animation-delay: -10s; }
        .binary:nth-child(4) { left: 40%; animation-duration: 14s; animation-delay: -7s; }
        .binary:nth-child(5) { left: 50%; animation-duration: 17s; animation-delay: -3s; }
        .binary:nth-child(6) { left: 60%; animation-duration: 19s; animation-delay: -8s; }
        .binary:nth-child(7) { left: 70%; animation-duration: 15s; animation-delay: -12s; }
        .binary:nth-child(8) { left: 80%; animation-duration: 16s; animation-delay: -6s; }
        .binary:nth-child(9) { left: 90%; animation-duration: 18s; animation-delay: -9s; }

        /* Main Container */
        .container {
            width: 100%;
            max-width: 800px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 30px;
            position: relative;
            z-index: 1;
            margin: 40px 0;
        }

        /* Advanced Animated Logo */
        .logo-container {
            position: relative;
            margin-bottom: 10px;
            perspective: 1000px;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 2;
            transform-style: preserve-3d;
        }

        .logo:hover {
            transform: scale(1.1) rotate(5deg) translateZ(20px);
        }

        .logo img {
            max-height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
            animation: logo-pulse 3s infinite ease-in-out;
        }

        @keyframes logo-pulse {
            0%, 100% {
                filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
            }
            50% {
                filter: drop-shadow(0 8px 15px var(--primary-glow));
            }
        }

        .logo-glow {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 140px;
            height: 50px;
            background: var(--primary-300);
            border-radius: 50%;
            filter: blur(20px);
            z-index: 1;
            animation: pulse 3s infinite ease-in-out;
        }

        @keyframes pulse {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 0.3;
            }
            50% {
                transform: translate(-50%, -50%) scale(1.3);
                opacity: 0.5;
            }
        }

        /* Profile Form Card with 3D and Glassmorphism Effects */
        .profile-form-wrapper {
            position: relative;
            width: 100%;
            max-width: 800px;
            perspective: 1000px;
        }

        .profile-form {
            background-color: rgba(255, 255, 255, 0.85);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-xl), 0 0 0 1px var(--primary-100);
            width: 100%;
            padding: 40px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            transition: transform 0.8s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.8s ease;
            z-index: 2;
            transform-style: preserve-3d;
            transform: translateZ(0);
        }

        /* Card Glassmorphism Effect */
        .card-glass {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, 
                                    rgba(255, 255, 255, 0.4) 0%, 
                                    rgba(255, 255, 255, 0.1) 100%);
            border-radius: var(--border-radius-lg);
            z-index: -1;
        }

        /* Card Corner Accents with Glow */
        .card-corner {
            position: absolute;
            width: 40px;
            height: 40px;
            z-index: 3;
        }

        .corner-top-left {
            top: 10px;
            left: 10px;
            border-top: 3px solid var(--primary-500);
            border-left: 3px solid var(--primary-500);
            border-top-left-radius: 10px;
            box-shadow: -3px -3px 10px var(--primary-glow);
        }

        .corner-top-right {
            top: 10px;
            right: 10px;
            border-top: 3px solid var(--secondary-500);
            border-right: 3px solid var(--secondary-500);
            border-top-right-radius: 10px;
            box-shadow: 3px -3px 10px var(--secondary-glow);
        }

        .corner-bottom-left {
            bottom: 10px;
            left: 10px;
            border-bottom: 3px solid var(--accent-500);
            border-left: 3px solid var(--accent-500);
            border-bottom-left-radius: 10px;
            box-shadow: -3px 3px 10px var(--accent-glow);
        }

        .corner-bottom-right {
            bottom: 10px;
            right: 10px;
            border-bottom: 3px solid var(--success-500);
            border-right: 3px solid var(--success-500);
            border-bottom-right-radius: 10px;
            box-shadow: 3px 3px 10px var(--success-glow);
        }

        /* Profile Header with Animated Elements */
        .profile-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        .profile-header h1 {
            font-family: 'Montserrat', sans-serif;
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 8px;
            background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            position: relative;
            display: inline-block;
            text-shadow: 0 5px 15px var(--primary-glow);
            animation: text-shimmer 5s infinite linear;
        }

        @keyframes text-shimmer {
            0% {
                background-position: 0% 50%;
            }
            100% {
                background-position: 100% 50%;
            }
        }

        .profile-header h1::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 3px;
            background: linear-gradient(to right, var(--primary-600), var(--secondary-600));
            border-radius: 3px;
            animation: width-pulse 3s infinite ease-in-out;
        }

        @keyframes width-pulse {
            0%, 100% {
                width: 60px;
                opacity: 1;
            }
            50% {
                width: 100px;
                opacity: 0.8;
            }
        }

        .profile-header p {
            color: var(--gray-500);
            font-size: 1rem;
            position: relative;
            display: inline-block;
            animation: float-subtle 3s infinite ease-in-out;
        }

        @keyframes float-subtle {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-5px);
            }
        }

        /* Header Badge */
        .header-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            font-size: 0.8rem;
            font-weight: 600;
            padding: 5px 15px;
            border-radius: 20px;
            box-shadow: 0 5px 15px var(--primary-glow);
        }

        /* Form Group Styling */
        .form-group {
            margin-bottom: 24px;
            position: relative;
            z-index: 2;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            color: var(--gray-700);
            transition: var(--transition);
            position: relative;
            padding-left: 5px;
        }

        .form-group label::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            width: 3px;
            height: 0;
            background: linear-gradient(to bottom, var(--primary-600), var(--secondary-600));
            border-radius: 3px;
            transition: var(--transition);
        }

        .form-group:hover label::before {
            height: 80%;
        }

        .form-group label::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 1px;
            background: var(--primary-600);
            transition: var(--transition);
        }

        .form-group:hover label {
            color: var(--primary-600);
            transform: translateX(3px);
        }

        .form-group:hover label::after {
            width: 30px;
        }

        /* Input Styling */
        .input-group {
            position: relative;
            z-index: 2;
        }

        .input-group input, 
        .input-group select, 
        .input-group textarea {
            width: 100%;
            padding: 16px 18px;
            padding-left: 50px;
            border: 1px solid var(--gray-300);
            border-radius: var(--border-radius);
            font-size: 1rem;
            color: var(--gray-800);
            background-color: rgba(255, 255, 255, 0.9);
            transition: var(--transition-bounce);
            box-shadow: var(--shadow-sm);
            transform: translateZ(0);
        }

        .input-group input:focus, 
        .input-group select:focus, 
        .input-group textarea:focus {
            outline: none;
            border-color: var(--primary-600);
            box-shadow: 0 0 0 4px var(--primary-100), 0 5px 15px rgba(0, 0, 0, 0.1);
            transform: translateY(-3px) translateZ(10px);
        }

        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray-400);
            font-size: 18px;
            transition: var(--transition);
        }

        .input-group:hover .input-icon {
            color: var(--primary-600);
        }

        .input-group input:focus ~ .input-icon, 
        .input-group select:focus ~ .input-icon, 
        .input-group textarea:focus ~ .input-icon {
            color: var(--primary-600);
            transform: translateY(-50%) scale(1.1);
        }

        /* Certificate Section Styling */
        .certificate-section {
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 20px;
            position: relative;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: var(--transition-bounce);
        }

        .certificate-section:hover {
            background-color: rgba(255, 255, 255, 0.9);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-100);
        }

        .certificate-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }

        .certificate-title {
            font-weight: 700;
            color: var(--primary-800);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .certificate-title i {
            color: var(--primary-600);
        }

        .remove-experience, 
        .remove-formation,
        .remove-certificate {
            background: none;
            border: none;
            color: var(--danger-600);
            cursor: pointer;
            font-size: 1.2rem;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .remove-experience:hover, 
        .remove-formation:hover,
        .remove-certificate:hover {
            background-color: var(--danger-100);
            color: var(--danger-700);
            transform: rotate(90deg);
        }

        /* Date Range Styling */
        .date-range {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }

        .date-range input[type="date"] {
            flex: 1;
            padding-left: 15px;
        }

        .date-range span {
            color: var(--gray-500);
            font-weight: 500;
        }

        /* Upload Section Styling */
        .upload-section {
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: var(--border-radius);
            padding: 20px;
            margin-top: 15px;
            margin-bottom: 15px;
            border: 2px dashed var(--primary-100);
            position: relative;
            overflow: hidden;
            transition: var(--transition-bounce);
        }

        .upload-section:hover {
            border-color: var(--primary-600);
            background-color: rgba(255, 255, 255, 0.9);
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .upload-button {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            border: none;
            cursor: pointer;
            margin-bottom: 10px;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
            transition: var(--transition-bounce);
        }

        .upload-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }

        .upload-button:hover {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 0 15px var(--primary-glow);
        }

        .upload-button:hover::before {
            left: 100%;
        }

        .plus-icon {
            font-size: 24px;
            color: white;
        }

        .upload-label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .file-preview {
            display: flex;
            align-items: center;
            gap: 10px;
            background-color: white;
            padding: 12px;
            border-radius: var(--border-radius);
            margin-top: 10px;
            box-shadow: var(--shadow-sm);
            transition: var(--transition-bounce);
        }

        .file-preview:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .file-preview i {
            color: var(--primary-600);
            font-size: 20px;
        }

        .file-name {
            font-size: 0.9rem;
            color: var(--gray-700);
            word-break: break-all;
        }

        .upload-message {
            text-align: center;
            color: var(--gray-600);
            font-size: 0.95rem;
            margin-top: 10px;
        }

        /* Experience Section Styling */
        .experience-section {
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 20px;
            position: relative;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: var(--transition-bounce);
        }

        .experience-section:hover {
            background-color: rgba(255, 255, 255, 0.9);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-100);
        }

        .experience-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }

        .experience-title {
            font-weight: 700;
            color: var(--primary-800);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .experience-title i {
            color: var(--primary-600);
        }

        /* Duration Inputs Styling */
        .duration-inputs {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-top: 15px;
            margin-bottom: 15px;
            background-color: rgba(255, 255, 255, 0.7);
            padding: 15px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }

        .duration-inputs:hover {
            background-color: rgba(255, 255, 255, 0.9);
            border-color: var(--primary-100);
        }

        .duration-inputs input {
            width: 70px;
            text-align: center;
            padding: 10px;
            border-radius: var(--border-radius);
            border: 1px solid var(--gray-300);
            transition: var(--transition);
        }

        .duration-inputs input:focus {
            outline: none;
            border-color: var(--primary-600);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
        }

        .duration-inputs span {
            color: var(--gray-600);
            font-weight: 500;
        }

        /* Skills Styling */
        .skills-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }

        .skill-tag {
            background: linear-gradient(135deg, var(--primary-50), var(--secondary-50));
            padding: 8px 15px;
            border-radius: 30px;
            font-size: 0.9rem;
            color: var(--primary-800);
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(79, 70, 229, 0.1);
            transition: var(--transition-bounce);
        }

        .skill-tag:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: var(--shadow-md), 0 0 10px var(--primary-glow);
        }

        .remove-skill {
            background: none;
            border: none;
            color: var(--primary-800);
            cursor: pointer;
            font-size: 1rem;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .remove-skill:hover {
            background-color: rgba(79, 70, 229, 0.2);
            color: var(--primary-600);
            transform: rotate(90deg);
        }

        /* Formation Section Styling */
        .formation-section {
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 20px;
            position: relative;
            border: 1px solid var(--gray-200);
            box-shadow: var(--shadow-sm);
            transition: var(--transition-bounce);
        }

        .formation-section:hover {
            background-color: rgba(255, 255, 255, 0.9);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
            border-color: var(--primary-100);
        }

        .formation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--gray-200);
        }

        .formation-title {
            font-weight: 700;
            color: var(--primary-800);
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .formation-title i {
            color: var(--primary-600);
        }

        .formation-meta {
            display: flex;
            gap: 15px;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .formation-meta select {
            flex: 1;
        }

        /* Add Button Styling */
        .add-experience {
            background-color: rgba(255, 255, 255, 0.7);
            border: 2px dashed var(--primary-100);
            border-radius: var(--border-radius);
            padding: 15px;
            width: 100%;
            color: var(--primary-800);
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition-bounce);
        }

        .add-experience:hover {
            background-color: var(--primary-50);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .add-experience i {
            font-size: 1.2rem;
            color: var(--primary-600);
            transition: var(--transition);
        }

        .add-experience:hover i {
            transform: rotate(90deg);
        }

        /* Next Button Styling */
        .next-btn-wrapper {
            position: relative;
            width: 100%;
            margin-top: 20px;
            overflow: hidden;
            border-radius: var(--border-radius);
            z-index: 2;
        }

        .next-button {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary-600), var(--secondary-600));
            color: white;
            border: none;
            border-radius: var(--border-radius);
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            position: relative;
            z-index: 1;
            overflow: hidden;
            box-shadow: 0 8px 20px var(--primary-glow);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: var(--transition-spring);
        }

        .next-button i {
            position: relative;
            z-index: 2;
            transition: var(--transition);
        }

        .next-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s ease;
        }

        .next-button:hover {
            transform: translateY(-8px) scale(1.03);
            box-shadow: 0 15px 30px var(--primary-glow), 0 0 15px var(--primary-glow);
            letter-spacing: 1px;
        }

        .next-button:hover::before {
            left: 100%;
        }

        .next-button:hover i {
            transform: translateX(5px);
        }

        .next-button:active {
            transform: translateY(-3px) scale(0.98);
        }

        /* Error and Success Messages */
        .error-message {
            color: var(--danger-600);
            font-size: 0.9rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .error-message i {
            color: var(--danger-600);
        }

        .success-message {
            color: var(--success-600);
            font-size: 0.9rem;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .success-message i {
            color: var(--success-600);
        }

        /* Alert Messages */
        .alert {
            padding: 15px;
            border-radius: var(--border-radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background-color: var(--danger-50);
            color: var(--danger-600);
            border-left: 4px solid var(--danger-600);
        }

        .alert-success {
            background-color: var(--success-50);
            color: var(--success-600);
            border-left: 4px solid var(--success-600);
        }

        .alert i {
            font-size: 1.2rem;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            transition: opacity 0.5s ease;
        }

        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(79, 70, 229, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary-600);
            animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Preloader */
        .preloader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease-in-out, visibility 0.5s ease-in-out;
        }
        
        .preloader.fade-out {
            opacity: 0;
            visibility: hidden;
        }
        
        .spinner-ring {
            position: absolute;
            width: 100%;
            height: 100%;
            border: 3px solid transparent;
            border-top-color: var(--primary-600);
            border-radius: 50%;
            animation: spin 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
        }
        
        .spinner-ring:nth-child(1) {
            animation-delay: -0.45s;
        }
        
        .spinner-ring:nth-child(2) {
            animation-delay: -0.3s;
            width: 80%;
            height: 80%;
            top: 10%;
            left: 10%;
            border-top-color: var(--secondary-600);
        }
        
        .spinner-ring:nth-child(3) {
            animation-delay: -0.15s;
            width: 60%;
            height: 60%;
            top: 20%;
            left: 20%;
            border-top-color: var(--accent-600);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .profile-form {
                padding: 30px;
            }

            .profile-header h1 {
                font-size: 1.8rem;
            }

            .formation-meta {
                flex-direction: column;
                gap: 10px;
            }
        }

        @media (max-width: 600px) {
            .profile-form {
                padding: 25px;
            }

            .profile-header h1 {
                font-size: 1.6rem;
            }

            .date-range {
                flex-direction: column;
                gap: 10px;
            }

            .date-range input[type="date"] {
                width: 100%;
            }

            .duration-inputs {
                flex-wrap: wrap;
            }
        }

        .hidden {
            display: none;
        }

.validation-icon {
    position: absolute;
    right: 18px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    transition: var(--transition);
    opacity: 0;
    z-index: 10;
}

.validation-icon.valid {
    color: var(--success-600);
}

.validation-icon.invalid {
    color: var(--danger-600);
}

.input-group.is-valid .validation-icon.valid,
.input-group.is-invalid .validation-icon.invalid {
    opacity: 1;
    animation: pop-in 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes pop-in {
    0% {
        transform: translateY(-50%) scale(0);
    }
    50% {
        transform: translateY(-50%) scale(1.2);
    }
    100% {
        transform: translateY(-50%) scale(1);
    }
}

.input-group.is-valid input,
.input-group.is-valid select {
    border-color: var(--success-600);
    padding-right: 50px;
}

.input-group.is-invalid input,
.input-group.is-invalid select {
    border-color: var(--danger-600);
    padding-right: 50px;
}
    </style>
</head>
<body>
    <!-- Preloader -->
    <div class="preloader">
        <div class="spinner">
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
            <div class="spinner-ring"></div>
        </div>
    </div>

    <!-- Background Elements -->
    <div class="background">
        <div class="bg-gradient"></div>
        <div class="bg-pattern"></div>
        <div class="bg-grid"></div>
        <div class="bg-shapes">
            <div class="shape shape-1"></div>
            <div class="shape shape-2"></div>
            <div class="shape shape-3"></div>
            <div class="shape shape-4"></div>
            <div class="shape shape-5"></div>
            <div class="shape shape-6"></div>
            <div class="shape shape-7"></div>
        </div>
        <div class="particles">
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
            <div class="particle"></div>
        </div>
        <div class="binary-rain">
            <div class="binary">01001100</div>
            <div class="binary">10101010</div>
            <div class="binary">11001100</div>
            <div class="binary">00110011</div>
            <div class="binary">10011001</div>
            <div class="binary">01010101</div>
            <div class="binary">11110000</div>
            <div class="binary">00001111</div>
            <div class="binary">10101010</div>
        </div>
    </div>

    <div class="container">
        <a href="../Client/acceuil.html" class="logo-container">
            <div class="logo-glow"></div>
            <div class="logo">
                <img src="../imgs/logo.png" alt="ConsultPro Logo" />
            </div>
        </a>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="profile-form-wrapper">
            <form class="profile-form" id="profileDetailsForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                <div class="card-glass"></div>
                <div class="card-corner corner-top-left"></div>
                <div class="card-corner corner-top-right"></div>
                <div class="card-corner corner-bottom-left"></div>
                <div class="card-corner corner-bottom-right"></div>
                
                <div class="profile-header">
                    <div class="header-badge">Expert Profile</div>
                    <h1>Complete Your Profile</h1>
                    <p>Showcase your expertise and experience</p>
                </div>
                
                <div class="form-group">
                    <label for="category">Category</label>
                    <div class="input-group">
                        <select id="category" name="category" required onchange="validateSelect(this)">
                            <option value="">Select a category</option>
                            <?php while ($cat = $categories_result->fetch_assoc()): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <i class="fas fa-th-list input-icon"></i>
                        <i class="fas fa-check-circle validation-icon valid"></i>
                        <i class="fas fa-times-circle validation-icon invalid"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="subcategory">Subcategory</label>
                    <div class="input-group">
                        <select id="subcategory" name="subcategory" required onchange="validateSelect(this)">
                            <option value="">Select a subcategory</option>
                            <?php foreach ($subcategories as $subcat): ?>
                                <option value="<?php echo $subcat['id']; ?>" <?php echo $subcategory_id == $subcat['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subcat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-tag input-icon"></i>
                        <i class="fas fa-check-circle validation-icon valid"></i>
                        <i class="fas fa-times-circle validation-icon invalid"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="city">City</label>
                    <div class="input-group">
                        <select id="city" name="city" required onchange="validateSelect(this)">
                            <option value="">Select a city</option>
                            <?php while ($city = $cities_result->fetch_assoc()): ?>
                                <option value="<?php echo $city['id']; ?>" <?php echo $city_id == $city['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($city['id'] . ' - ' . $city['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                        <i class="fas fa-map-marker-alt input-icon"></i>
                        <i class="fas fa-check-circle validation-icon valid"></i>
                        <i class="fas fa-times-circle validation-icon invalid"></i>
                    </div>
                </div>

                <!-- Certificate Section -->
                <div class="form-group">
                    <label>Certificates</label>
                    <div id="certificates-container">
                        <?php if (count($certificates) > 0): ?>
                            <?php foreach ($certificates as $index => $cert): ?>
                                <div class="certificate-section" id="certificate-section-<?php echo $index + 1; ?>">
                                    <div class="certificate-header">
                                        <span class="certificate-title"><i class="fas fa-certificate"></i> Certificate Details</span>
                                        <?php if ($index > 0): ?>
                                        <button type="button" class="remove-certificate" onclick="removeCertificateSection('certificate-section-<?php echo $index + 1; ?>')"><i class="fas fa-times"></i></button>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="date-range">
                                        <div class="input-group">
                                            <input type="date" name="certificate_start_date_<?php echo $index + 1; ?>" id="certificate_start_date_<?php echo $index + 1; ?>" required onchange="validateCertificateDates(<?php echo $index + 1; ?>)" value="<?php echo $cert['start_date']; ?>" max="<?php echo date('Y-m-d'); ?>">
                                             
                                        </div>
                                        <span>To</span>
                                        <div class="input-group">
                                            <input type="date" name="certificate_end_date_<?php echo $index + 1; ?>" id="certificate_end_date_<?php echo $index + 1; ?>" required onchange="validateCertificateDates(<?php echo $index + 1; ?>)" value="<?php echo $cert['end_date']; ?>" max="<?php echo date('Y-m-d'); ?>">
                                             
                                        </div>
                                    </div>
                                    <div class="error-message" id="certificate-date-error-<?php echo $index + 1; ?>"></div>

                                    <div class="form-group" style="margin-top: 15px; margin-bottom: 15px;">
                                        <div class="input-group">
                                            <input type="text" name="certificate_institution_<?php echo $index + 1; ?>" id="certificate_institution_<?php echo $index + 1; ?>" placeholder="Institution name" required value="<?php echo htmlspecialchars($cert['institution']); ?>">
                                            <i class="fas fa-university input-icon"></i>
                                        </div>
                                    </div>

                                    <div class="upload-section">
                                        <span class="upload-label">Certificate Upload</span>
                                        <input type="file" id="certificate_file_<?php echo $index + 1; ?>" name="certificate_file_<?php echo $index + 1; ?>" class="hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <button type="button" class="upload-button" onclick="document.getElementById('certificate_file_<?php echo $index + 1; ?>').click()">
                                            <span class="plus-icon"><i class="fas fa-plus"></i></span>
                                        </button>
                                        <div id="certificate-file-preview-<?php echo $index + 1; ?>" class="<?php echo empty($cert['file_path']) ? 'hidden' : ''; ?> file-preview">
                                            <?php if (!empty($cert['file_path'])): ?>
                                                <i class="fas fa-file-upload"></i>
                                                <span class="file-name"><?php echo basename($cert['file_path']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div id="certificate-file-message-<?php echo $index + 1; ?>" class="upload-message <?php echo !empty($cert['file_path']) ? 'hidden' : ''; ?>">Upload your certificate document</div>
                                        <div id="certificate-file-success-<?php echo $index + 1; ?>" class="success-message hidden"><i class="fas fa-check-circle"></i> File uploaded successfully</div>
                                    </div>

                                    <div class="form-group" style="margin-top: 15px;">
                                        <div class="input-group">
                                            <textarea name="certificate_description_<?php echo $index + 1; ?>" id="certificate_description_<?php echo $index + 1; ?>" placeholder="Description of your certificate" rows="3" required><?php echo htmlspecialchars($cert['description']); ?></textarea>
                                            <i class="fas fa-align-left input-icon" style="top: 20px;"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Default certificate section if none exists -->
                            <div class="certificate-section" id="certificate-section-1">
                                <div class="certificate-header">
                                    <span class="certificate-title"><i class="fas fa-certificate"></i> Certificate Details</span>
                                </div>
                                
                                <div class="date-range">
                                    <div class="input-group">
                                        <input type="date" name="certificate_start_date_1" id="certificate_start_date_1" required onchange="validateCertificateDates(1)" max="<?php echo date('Y-m-d'); ?>">
                                         
                                    </div>
                                    <span>To</span>
                                    <div class="input-group">
                                        <input type="date" name="certificate_end_date_1" id="certificate_end_date_1" required onchange="validateCertificateDates(1)" max="<?php echo date('Y-m-d'); ?>">
                                         
                                    </div>
                                </div>
                                <div class="error-message" id="certificate-date-error-1"></div>

                                <div class="form-group" style="margin-top: 15px; margin-bottom: 15px;">
                                    <div class="input-group">
                                        <input type="text" name="certificate_institution_1" id="certificate_institution_1" placeholder="Institution name" required>
                                        <i class="fas fa-university input-icon"></i>
                                    </div>
                                </div>

                                <div class="upload-section">
                                    <span class="upload-label">Certificate Upload</span>
                                    <input type="file" id="certificate_file_1" name="certificate_file_1" class="hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                                    <button type="button" class="upload-button" onclick="document.getElementById('certificate_file_1').click()">
                                        <span class="plus-icon"><i class="fas fa-plus"></i></span>
                                    </button>
                                    <div id="certificate-file-preview-1" class="hidden file-preview"></div>
                                    <div id="certificate-file-message-1" class="upload-message">Upload your certificate document</div>
                                    <div id="certificate-file-success-1" class="success-message hidden"><i class="fas fa-check-circle"></i> File uploaded successfully</div>
                                </div>

                                <div class="form-group" style="margin-top: 15px;">
                                    <div class="input-group">
                                        <textarea name="certificate_description_1" id="certificate_description_1" placeholder="Description of your certificate" rows="3" required></textarea>
                                        <i class="fas fa-align-left input-icon" style="top: 20px;"></i>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Button to add another certificate -->
                    <button type="button" class="add-experience" id="add-certificate-btn" onclick="addCertificateSection()">
                        <i class="fas fa-plus-circle"></i> Add Another Certificate
                    </button>
                    
                    <!-- Hidden field to track certificate section count -->
                    <input type="hidden" name="cert_section_count" id="cert_section_count" value="<?php echo count($certificates) > 0 ? count($certificates) : 1; ?>">
                </div>

                <!-- Experience Section -->
                <div class="form-group">
                    <label>Work Experience</label>
                    <div id="experiences-container">
                        <?php if (count($experiences) > 0): ?>
                            <?php foreach ($experiences as $index => $exp): ?>
                                <div class="experience-section" id="experience-<?php echo $index; ?>">
                                    <div class="experience-header">
                                        <span class="experience-title"><i class="fas fa-briefcase"></i> Work Experience</span>
                                        <button type="button" class="remove-experience" onclick="removeExperience('experience-<?php echo $index; ?>')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div class="date-range">
                                        <div class="input-group">
                                            <input type="date" name="start_date[]" required onchange="validateDates('experience-<?php echo $index; ?>'); calculateDuration('experience-<?php echo $index; ?>');" value="<?php echo $exp['start_date']; ?>" max="<?php echo date('Y-m-d'); ?>">
                                             
                                        </div>
                                        <span>To</span>
                                        <div class="input-group">
                                            <input type="date" name="end_date[]" required onchange="validateDates('experience-<?php echo $index; ?>'); calculateDuration('experience-<?php echo $index; ?>');" value="<?php echo $exp['end_date']; ?>" max="<?php echo date('Y-m-d'); ?>">
                                             
                                        </div>
                                    </div>
                                    <div class="error-message" id="experience-<?php echo $index; ?>-date-error"></div>
                                    
                                    <div class="form-group" style="margin-top: 15px;">
                                        <div class="input-group">
                                            <input type="text" name="workplace[]" placeholder="Place of employment" required value="<?php echo htmlspecialchars($exp['workplace']); ?>">
                                            <i class="fas fa-building input-icon"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="duration-inputs">
                                        <input type="number" name="duration_years[]" placeholder="Years" min="0" required onchange="calculateDuration('experience-<?php echo $index; ?>')" value="<?php echo $exp['duration_years']; ?>">
                                        <span>Years</span>
                                        <input type="number" name="duration_months[]" placeholder="Months" min="0" max="11" required onchange="calculateDuration('experience-<?php echo $index; ?>')" value="<?php echo $exp['duration_months']; ?>">
                                        <span>Months</span>
                                    </div>
                                    <div class="error-message" id="experience-<?php echo $index; ?>-duration-error"></div>
                                    
                                    <div class="form-group" style="margin-top: 15px;">
                                        <div class="input-group">
                                            <textarea name="experience_description[]" placeholder="Description of your experience" rows="3"><?php echo htmlspecialchars($exp['description']); ?></textarea>
                                            <i class="fas fa-align-left input-icon" style="top: 20px;"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="upload-section">
                                        <span class="upload-label">Experience Document</span>
                                        <input type="file" id="experience-file-<?php echo $index; ?>" name="experience_file[]" class="hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                                        <button type="button" class="upload-button" onclick="document.getElementById('experience-file-<?php echo $index; ?>').click()">
                                            <span class="plus-icon"><i class="fas fa-plus"></i></span>
                                        </button>
                                        <div id="experience-preview-<?php echo $index; ?>" class="file-preview <?php echo empty($exp['file_path']) ? 'hidden' : ''; ?>">
                                            <?php if (!empty($exp['file_path'])): ?>
                                                <i class="fas fa-file-upload"></i>
                                                <span class="file-name"><?php echo basename($exp['file_path']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="upload-message <?php echo !empty($exp['file_path']) ? 'hidden' : ''; ?>">Upload supporting document</div>
                                    
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="add-experience" onclick="addExperience()">
                        <i class="fas fa-plus-circle"></i> Add Work Experience
                    </button>
                </div>

                <!-- Skills Section -->
                <div class="form-group">
                    <label>Skills</label>
                    <div class="input-group">
                        <input type="text" id="skill-input" placeholder="Type a skill and press Enter" onkeypress="handleSkillInput(event)">
                        <i class="fas fa-lightbulb input-icon"></i>
                    </div>
                    <div class="skills-container" id="skills-container">
                        <!-- Skills will be populated by JavaScript -->
                    </div>
                    <input type="hidden" name="skills" id="skills-hidden" value="<?php echo htmlspecialchars(implode(',', $skills)); ?>">
                </div>

                <!-- Formation Section -->
                <div class="form-group">
                    <label>Education & Training</label>
                    <div id="formations-container">
                        <?php if (count($formations) > 0): ?>
                            <?php foreach ($formations as $index => $form): ?>
                                <div class="formation-section" id="formation-<?php echo $index; ?>">
                                    <div class="formation-header">
                                        <span class="formation-title"><i class="fas fa-graduation-cap"></i> Education</span>
                                        <button type="button" class="remove-formation" onclick="removeFormation('formation-<?php echo $index; ?>')"><i class="fas fa-times  onclick="removeFormation('formation-<?php echo $index; ?>')"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div class="form-group">
                                        <div class="input-group">
                                            <input type="text" name="formation_name[]" placeholder="Education/Training name" required value="<?php echo htmlspecialchars($form['formation_name']); ?>">
                                            <i class="fas fa-book input-icon"></i>
                                        </div>
                                    </div>
                                    <div class="formation-meta">
                                        <div class="input-group">
                                            <select name="formation_type[]">
                                                <option value="">Select type</option>
                                                <option value="certificate" <?php echo $form['formation_type'] == 'certificate' ? 'selected' : ''; ?>>Certificate</option>
                                                <option value="diploma" <?php echo $form['formation_type'] == 'diploma' ? 'selected' : ''; ?>>Diploma</option>
                                                <option value="degree" <?php echo $form['formation_type'] == 'degree' ? 'selected' : ''; ?>>Degree</option>
                                                <option value="course" <?php echo $form['formation_type'] == 'course' ? 'selected' : ''; ?>>Course</option>
                                                <option value="workshop" <?php echo $form['formation_type'] == 'workshop' ? 'selected' : ''; ?>>Workshop</option>
                                                <option value="other" <?php echo $form['formation_type'] == 'other' ? 'selected' : ''; ?>>Other</option>
                                            </select>
                                            <i class="fas fa-list-alt input-icon"></i>
                                        </div>
                                        <div class="input-group">
                                            <select name="formation_year[]">
                                                <option value="">Year completed</option>
                                                <?php 
                                                $currentYear = date('Y');
                                                for ($year = $currentYear; $year >= $currentYear - 50; $year--): 
                                                ?>
                                                    <option value="<?php echo $year; ?>" <?php echo $form['formation_year'] == $year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                                                <?php endfor; ?>
                                            </select>
                                            <i class="fas fa-calendar-day input-icon"></i>
                                        </div>
                                    </div>
                                    
                                    <div class="upload-section">
                                        <span class="upload-label">Certificate/Diploma (Optional)</span>
                                        <input type="file" id="formation-file-<?php echo $index; ?>" name="formation_file[]" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
                                        <button type="button" class="upload-button" onclick="document.getElementById('formation-file-<?php echo $index; ?>').click()">
                                            <span class="plus-icon"><i class="fas fa-plus"></i></span>
                                        </button>
                                        <div id="formation-preview-<?php echo $index; ?>" class="file-preview <?php echo empty($form['file_path']) ? 'hidden' : ''; ?>">
                                            <?php if (!empty($form['file_path'])): ?>
                                                <i class="fas fa-file-upload"></i>
                                                <span class="file-name"><?php echo basename($form['file_path']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="upload-message <?php echo !empty($form['file_path']) ? 'hidden' : ''; ?>">Upload certificate/diploma (optional)</div>
                                    </div>
                                    
                                    <div class="form-group" style="margin-top: 15px;">
                                        <div class="input-group">
                                            <textarea name="formation_description[]" placeholder="Brief description of what you learned" rows="3"><?php echo htmlspecialchars($form['description']); ?></textarea>
                                            <i class="fas fa-align-left input-icon" style="top: 20px;"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="add-experience" onclick="addFormation()">
                        <i class="fas fa-plus-circle"></i> Add Education/Training
                    </button>
                </div>

                <div class="next-btn-wrapper">
                    <button type="submit" class="next-button">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variables globales
        let certificateSectionCount = <?php echo count($certificates) > 0 ? count($certificates) : 1; ?>;
        let experienceCount = <?php echo count($experiences); ?>;
        let formationCount = <?php echo count($formations); ?>;
        
        // Initialize skills from database
        const skills = new Set(<?php echo json_encode($skills); ?>);
        
        // Function to add a new certificate section
        function addCertificateSection() {
            certificateSectionCount++;
            document.getElementById('cert_section_count').value = certificateSectionCount;
            
            const container = document.getElementById('certificates-container');
            const sectionId = `certificate-section-${certificateSectionCount}`;
            
            const newSection = document.createElement('div');
            newSection.className = 'certificate-section';
            newSection.id = sectionId;
            newSection.innerHTML = `
                <div class="certificate-header">
                    <span class="certificate-title"><i class="fas fa-certificate"></i> Certificate Details</span>
                    <button type="button" class="remove-certificate" onclick="removeCertificateSection('${sectionId}')"><i class="fas fa-times"></i></button>
                </div>
                
                <div class="date-range">
                    <div class="input-group">
                        <input type="date" name="certificate_start_date_${certificateSectionCount}" id="certificate_start_date_${certificateSectionCount}" required onchange="validateCertificateDates(${certificateSectionCount})" max="<?php echo date('Y-m-d'); ?>">
                         
                    </div>
                    <span>To</span>
                    <div class="input-group">
                        <input type="date" name="certificate_end_date_${certificateSectionCount}" id="certificate_end_date_${certificateSectionCount}" required onchange="validateCertificateDates(${certificateSectionCount})" max="<?php echo date('Y-m-d'); ?>">
                         
                    </div>
                </div>
                <div class="error-message" id="certificate-date-error-${certificateSectionCount}"></div>

                <div class="form-group" style="margin-top: 15px; margin-bottom: 15px;">
                    <div class="input-group">
                        <input type="text" name="certificate_institution_${certificateSectionCount}" id="certificate_institution_${certificateSectionCount}" placeholder="Institution name" required>
                        <i class="fas fa-university input-icon"></i>
                    </div>
                </div>

                <div class="upload-section">
                    <span class="upload-label">Certificate Upload</span>
                    <input type="file" id="certificate_file_${certificateSectionCount}" name="certificate_file_${certificateSectionCount}" class="hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                    <button type="button" class="upload-button" onclick="document.getElementById('certificate_file_${certificateSectionCount}').click()">
                        <span class="plus-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div id="certificate-file-preview-${certificateSectionCount}" class="hidden file-preview"></div>
                    <div id="certificate-file-message-${certificateSectionCount}" class="upload-message">Upload your certificate document</div>
                    <div id="certificate-file-success-${certificateSectionCount}" class="success-message hidden"><i class="fas fa-check-circle"></i> File uploaded successfully</div>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <div class="input-group">
                        <textarea name="certificate_description_${certificateSectionCount}" id="certificate_description_${certificateSectionCount}" placeholder="Description of your certificate" rows="3" required></textarea>
                        <i class="fas fa-align-left input-icon" style="top: 20px;"></i>
                    </div>
                </div>
            `;
            
            container.appendChild(newSection);
            
            // Add event listeners for file uploads
            document.getElementById(`certificate_file_${certificateSectionCount}`).addEventListener('change', function(e) {
                handleCertificateFileUpload(certificateSectionCount, e);
            });
        }
        
        // Function to remove a certificate section
        function removeCertificateSection(sectionId) {
            document.getElementById(sectionId).remove();
            // We don't decrement the count to avoid ID conflicts
            // Just update the hidden field with the current visible count
            const visibleSections = document.querySelectorAll('[id^="certificate-section-"]').length;
            document.getElementById('cert_section_count').value = visibleSections;
        }
        
        // Function to handle certificate file upload
        function handleCertificateFileUpload(section, event) {
            const fileInput = event.target;
            const filePreview = document.getElementById(`certificate-file-preview-${section}`);
            const fileMessage = document.getElementById(`certificate-file-message-${section}`);
            const fileSuccess = document.getElementById(`certificate-file-success-${section}`);
            
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Show file icon and name
                    filePreview.innerHTML = `<i class="fas fa-file-upload"></i> <span class="file-name">${fileInput.files[0].name}</span>`;
                    filePreview.classList.remove('hidden');
                    fileMessage.classList.add('hidden');
                    
                    // Show success message
                    fileSuccess.classList.remove('hidden');
                    setTimeout(() => {
                        fileSuccess.classList.add('hidden');
                    }, 3000);
                }
                
                reader.readAsDataURL(fileInput.files[0]);
            } else {
                // Reset preview if no file is selected
                filePreview.classList.add('hidden');
                filePreview.innerHTML = '';
                fileMessage.classList.remove('hidden');
                fileSuccess.classList.add('hidden');
            }
        }
        
        // Add event listeners for initial certificate file uploads
        <?php if (count($certificates) > 0): ?>
            <?php foreach ($certificates as $index => $cert): ?>
                document.getElementById('certificate_file_<?php echo $index + 1; ?>').addEventListener('change', function(e) {
                    handleCertificateFileUpload(<?php echo $index + 1; ?>, e);
                });
            <?php endforeach; ?>
        <?php else: ?>
            document.getElementById('certificate_file_1').addEventListener('change', function(e) {
                handleCertificateFileUpload(1, e);
            });
        <?php endif; ?>
        
        // Function to add a new experience
        function addExperience() {
            experienceCount++;
            const container = document.getElementById('experiences-container');
            const experienceId = `experience-${experienceCount}`;
            
            const newExperience = document.createElement('div');
            newExperience.className = 'experience-section';
            newExperience.id = experienceId;
            newExperience.innerHTML = `
                <div class="experience-header">
                    <span class="experience-title"><i class="fas fa-briefcase"></i> Work Experience</span>
                    <button type="button" class="remove-experience" onclick="removeExperience('${experienceId}')"><i class="fas fa-times"></i></button>
                </div>
                <div class="date-range">
                    <div class="input-group">
                        <input type="date" name="start_date[]" required onchange="validateDates('${experienceId}'); calculateDuration('${experienceId}');" max="<?php echo date('Y-m-d'); ?>">
                         
                    </div>
                    <span>To</span>
                    <div class="input-group">
                        <input type="date" name="end_date[]" required onchange="validateDates('${experienceId}'); calculateDuration('${experienceId}');" max="<?php echo date('Y-m-d'); ?>">
                         
                    </div>
                </div>
                <div class="error-message" id="${experienceId}-date-error"></div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <div class="input-group">
                        <input type="text" name="workplace[]" placeholder="Place of employment" required>
                        <i class="fas fa-building input-icon"></i>
                    </div>
                </div>
                
                <div class="duration-inputs">
                    <input type="number" name="duration_years[]" placeholder="Years" min="0" required onchange="calculateDuration('${experienceId}')">
                    <span>Years</span>
                    <input type="number" name="duration_months[]" placeholder="Months" min="0" max="11" required onchange="calculateDuration('${experienceId}')">
                    <span>Months</span>
                </div>
                <div class="error-message" id="${experienceId}-duration-error"></div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <div class="input-group">
                        <textarea name="experience_description[]" placeholder="Description of your experience" rows="3"></textarea>
                        <i class="fas fa-align-left input-icon" style="top: 20px;"></i>
                    </div>
                </div>
                
                <div class="upload-section">
                    <span class="upload-label">Experience Document</span>
                    <input type="file" id="experience-file-${experienceCount}" name="experience_file[]" class="hidden" accept=".pdf,.jpg,.jpeg,.png" required>
                    <button type="button" class="upload-button" onclick="document.getElementById('experience-file-${experienceCount}').click()">
                        <span class="plus-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div id="experience-preview-${experienceCount}" class="file-preview hidden"></div>
                    <div class="upload-message">Upload supporting document</div>
                </div>
            `;
            container.appendChild(newExperience);
            
            // Add event listener for file upload
            document.getElementById(`experience-file-${experienceCount}`).addEventListener('change', function(e) {
                const preview = document.getElementById(`experience-preview-${experienceCount}`);
                const file = e.target.files[0];
                
                if (file) {
                    preview.innerHTML = `
                        <i class="fas fa-file-upload"></i>
                        <span class="file-name">${file.name}</span>
                    `;
                    preview.classList.remove('hidden');
                    this.nextElementSibling.nextElementSibling.nextElementSibling.classList.add('hidden');
                } else {
                    preview.innerHTML = '';
                    preview.classList.add('hidden');
                    this.nextElementSibling.nextElementSibling.nextElementSibling.classList.remove('hidden');
                }
            });
        }
        
        // Function to remove an experience
        function removeExperience(experienceId) {
            document.getElementById(experienceId).remove();
        }
        
        // Function to validate dates
        function validateDates(experienceId) {
            const startDateInput = document.querySelector(`#${experienceId} input[name="start_date[]"]`);
            const endDateInput = document.querySelector(`#${experienceId} input[name="end_date[]"]`);
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            const errorDiv = document.getElementById(`${experienceId}-date-error`);
            
            if (startDate > endDate) {
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> End date must be after start date';
                endDateInput.value = ''; // Clear the end date
            } else {
                errorDiv.innerHTML = "";
            }
        }
        
        // Function to validate certificate dates
        function validateCertificateDates(section) {
            const startDateInput = document.getElementById(`certificate_start_date_${section}`);
            const endDateInput = document.getElementById(`certificate_end_date_${section}`);
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            const errorDiv = document.getElementById(`certificate-date-error-${section}`);
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
        
        // Function to calculate duration
        function calculateDuration(experienceId) {
            const startDateInput = document.querySelector(`#${experienceId} input[name="start_date[]"]`);
            const endDateInput = document.querySelector(`#${experienceId} input[name="end_date[]"]`);
            const durationYearsInput = document.querySelector(`#${experienceId} input[name="duration_years[]"]`);
            const durationMonthsInput = document.querySelector(`#${experienceId} input[name="duration_months[]"]`);
            const errorDiv = document.getElementById(`${experienceId}-duration-error`);
            
            const startDate = new Date(startDateInput.value);
            const endDate = new Date(endDateInput.value);
            
            if (startDateInput.value === "" || endDateInput.value === "") {
                return; // Don't calculate if dates are empty
            }
            
            if (startDate > endDate) {
                errorDiv.innerHTML = '<i class="fas fa-exclamation-circle"></i> End date must be after start date';
                durationYearsInput.value = '';
                durationMonthsInput.value = '';
                return;
            } else {
                errorDiv.innerHTML = "";
            }
            
            let years = endDate.getFullYear() - startDate.getFullYear();
            let months = endDate.getMonth() - startDate.getMonth();
            
            if (months < 0) {
                years--;
                months += 12;
            }
            
            durationYearsInput.value = years;
            durationMonthsInput.value = months;
        }
        
        // Function to handle skill input
        function handleSkillInput(event) {
            if (event.key === "Enter") {
                event.preventDefault();
                const skillInput = document.getElementById("skill-input");
                const skill = skillInput.value.trim();
                if (skill !== "") {
                    addSkill(skill);
                    skillInput.value = "";
                }
            }
        }
        
        // Function to add a skill
        function addSkill(skill) {
            if (!skills.has(skill)) {
                skills.add(skill);
                updateSkillsContainer();
                updateSkillsHiddenInput();
            }
        }
        
        // Function to remove a skill
        function removeSkill(skill) {
            skills.delete(skill);
            updateSkillsContainer();
            updateSkillsHiddenInput();
        }
        
        // Function to update the skills container
        function updateSkillsContainer() {
            const skillsContainer = document.getElementById("skills-container");
            skillsContainer.innerHTML = "";
            skills.forEach(skill => {
                const skillTag = document.createElement("div");
                skillTag.className = "skill-tag";
                skillTag.innerHTML = `
                    <span>${skill}</span>
                    <button type="button" class="remove-skill" onclick="removeSkill('${skill}')"><i class="fas fa-times"></i></button>
                `;
                skillsContainer.appendChild(skillTag);
            });
        }
        
        // Function to update the hidden input field with skills
        function updateSkillsHiddenInput() {
            const skillsHiddenInput = document.getElementById("skills-hidden");
            skillsHiddenInput.value = Array.from(skills).join(",");
        }
        
        // Function to add a new formation
        function addFormation() {
            formationCount++;
            const container = document.getElementById('formations-container');
            const formationId = `formation-${formationCount}`;
            
            const newFormation = document.createElement('div');
            newFormation.className = 'formation-section';
            newFormation.id = formationId;
            newFormation.innerHTML = `
                <div class="formation-header">
                    <span class="formation-title"><i class="fas fa-graduation-cap"></i> Education</span>
                    <button type="button" class="remove-formation" onclick="removeFormation('${formationId}')"><i class="fas fa-times"></i></button>
                </div>
                <div class="form-group">
                    <div class="input-group">
                        <input type="text" name="formation_name[]" placeholder="Education/Training name" required>
                        <i class="fas fa-book input-icon"></i>
                    </div>
                </div>
                <div class="formation-meta">
                    <div class="input-group">
                        <select name="formation_type[]">
                            <option value="">Select type</option>
                            <option value="certificate">Certificate</option>
                            <option value="diploma">Diploma</option>
                            <option value="degree">Degree</option>
                            <option value="course">Course</option>
                            <option value="workshop">Workshop</option>
                            <option value="other">Other</option>
                        </select>
                        <i class="fas fa-list-alt input-icon"></i>
                    </div>
                    <div class="input-group">
                        <select name="formation_year[]">
                            <option value="">Year completed</option>
                            <?php 
                            $currentYear = date('Y');
                            for ($year = $currentYear; $year >= $currentYear - 50; $year--): 
                            ?>
                                <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endfor; ?>
                        </select>
                        <i class="fas fa-calendar-day input-icon"></i>
                    </div>
                </div>
                
                <div class="upload-section">
                    <span class="upload-label">Certificate/Diploma (Optional)</span>
                    <input type="file" id="formation-file-${formationCount}" name="formation_file[]" class="hidden" accept=".pdf,.jpg,.jpeg,.png">
                    <button type="button" class="upload-button" onclick="document.getElementById('formation-file-${formationCount}').click()">
                        <span class="plus-icon"><i class="fas fa-plus"></i></span>
                    </button>
                    <div id="formation-preview-${formationCount}" class="file-preview hidden"></div>
                    <div class="upload-message">Upload certificate/diploma (optional)</div>
                </div>
                
                <div class="form-group" style="margin-top: 15px;">
                    <div class="input-group">
                        <textarea name="formation_description[]" placeholder="Brief description of what you learned" rows="3"></textarea>
                        <i class="fas fa-align-left input-icon" style="top: 20px;"></i>
                    </div>
                </div>
            `;
            container.appendChild(newFormation);
            
            // Add event listener for file upload
            document.getElementById(`formation-file-${formationCount}`).addEventListener('change', function(e) {
                const preview = document.getElementById(`formation-preview-${formationCount}`);
                const file = e.target.files[0];
                
                if (file) {
                    preview.innerHTML = `
                        <i class="fas fa-file-upload"></i>
                        <span class="file-name">${file.name}</span>
                    `;
                    preview.classList.remove('hidden');
                    this.nextElementSibling.nextElementSibling.nextElementSibling.classList.add('hidden');
                } else {
                    preview.innerHTML = '';
                    preview.classList.add('hidden');
                    this.nextElementSibling.nextElementSibling.nextElementSibling.classList.remove('hidden');
                }
            });
        }
        
        // Function to remove a formation
        function removeFormation(formationId) {
            document.getElementById(formationId).remove();
        }
        
        // Function to load subcategories based on selected category
        document.getElementById('category').addEventListener('change', function() {
            var categoryId = this.value;
            var subcategorySelect = document.getElementById('subcategory');
            subcategorySelect.innerHTML = "<option value=''>Select a subcategory</option>"; // Clear existing options
            
            if (categoryId) {
                // Make an AJAX request to fetch subcategories for the selected category
                var xhr = new XMLHttpRequest();
                xhr.open("GET", "get_subcategories.php?category_id=" + categoryId, true);
                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        var subcategories = JSON.parse(xhr.responseText);
                        subcategories.forEach(function(subcategory) {
                            var option = document.createElement("option");
                            option.value = subcategory.id;
                            option.text = subcategory.name;
                            subcategorySelect.add(option);
                        });
                    } else {
                        console.error("Request failed with status:", xhr.status);
                    }
                };
                xhr.onerror = function() {
                    console.error("Request failed");
                };
                xhr.send();
            }
        });
        
        // Add this function to validate selects
        function validateSelect(selectElement) {
            const inputGroup = selectElement.parentElement;
            if (selectElement.value) {
                inputGroup.classList.remove('is-invalid');
                inputGroup.classList.add('is-valid');
            } else {
                inputGroup.classList.remove('is-valid');
                inputGroup.classList.add('is-invalid');
            }
        }

        // Modify the handleCertificateFileUpload function to show success message
        function handleCertificateFileUpload(section, event) {
            const fileInput = event.target;
            const filePreview = document.getElementById(`certificate-file-preview-${section}`);
            const fileMessage = document.getElementById(`certificate-file-message-${section}`);
            const fileSuccess = document.getElementById(`certificate-file-success-${section}`);
            
            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    // Show file icon and name
                    filePreview.innerHTML = `<i class="fas fa-file-upload"></i> <span class="file-name">${fileInput.files[0].name}</span>`;
                    filePreview.classList.remove('hidden');
                    fileMessage.classList.add('hidden');
                    
                    // Show success message
                    fileSuccess.classList.remove('hidden');
                    setTimeout(() => {
                        fileSuccess.classList.add('hidden');
                    }, 3000);
                }
                
                reader.readAsDataURL(fileInput.files[0]);
            } else {
                // Reset preview if no file is selected
                filePreview.classList.add('hidden');
                filePreview.innerHTML = '';
                fileMessage.classList.remove('hidden');
                fileSuccess.classList.add('hidden');
            }
        }
        
        // Initialize on page load
        window.addEventListener('DOMContentLoaded', function() {
            // Initialize skills
            updateSkillsContainer();
            
            // Add a default experience if none exists
            if (document.querySelectorAll('.experience-section').length === 0) {
                addExperience();
            }
            
            // Add a default formation if none exists
            if (document.querySelectorAll('.formation-section').length === 0) {
                addFormation();
            }
            
            // Hide preloader
            setTimeout(function() {
                document.querySelector(".preloader").classList.add("fade-out");
                setTimeout(function() {
                    document.querySelector(".preloader").style.display = "none";
                }, 500);
            }, 1000);
            
            // Generate binary rain
            function generateBinaryRain() {
                const binaryRain = document.querySelector('.binary-rain');
                if (!binaryRain) return;
                
                setInterval(() => {
                    const binary = document.createElement('div');
                    binary.classList.add('binary');
                    binary.style.left = Math.random() * 100 + '%';
                    binary.style.animationDuration = Math.random() * 10 + 10 + 's';
                    binary.textContent = Math.random() > 0.5 ? '10101010' : '01010101';
                    binaryRain.appendChild(binary);
                    
                    setTimeout(() => {
                        binary.remove();
                    }, 20000);
                }, 1000);
            }
            
            // Initialize validation for selects
            if (document.getElementById('category').value) {
                document.getElementById('category').parentElement.classList.add('is-valid');
            }
            if (document.getElementById('subcategory').value) {
                document.getElementById('subcategory').parentElement.classList.add('is-valid');
            }
            if (document.getElementById('city').value) {
                document.getElementById('city').parentElement.classList.add('is-valid');
            }
            
            generateBinaryRain();
            
            // Add animated background elements
            document.addEventListener('mousemove', function(e) {
                const shapes = document.querySelectorAll('.shape');
                const particles = document.querySelectorAll('.particle');
                const profileForm = document.querySelector('.profile-form');
                
                const mouseX = e.clientX / window.innerWidth;
                const mouseY = e.clientY / window.innerHeight;
                
                const moveX = (mouseX - 0.5) * 40;
                const moveY = (mouseY - 0.5) * 40;
                
                // Move shapes
                shapes.forEach(shape => {
                    const speed = Math.random() * 0.4 + 0.2;
                    shape.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
                });
                
                // Move particles
                particles.forEach(particle => {
                    const speed = Math.random() * 0.6 + 0.3;
                    particle.style.transform = `translate(${moveX * speed}px, ${moveY * speed}px)`;
                });
            });
        });
    </script>
</body>
</html>
