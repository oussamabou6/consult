<?php
// Start session
session_start();


// Include database connection
require_once '../config/config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION["user_id"]) || $_SESSION["user_role"] != "admin") {
    // Redirect to login page if not logged in as admin
    header("Location: ../config/logout.php");
    exit;
}
// Get site name from settings table
$site_name_query = "SELECT setting_value FROM settings WHERE setting_key = 'site_name'";
$site_name_result = $conn->query($site_name_query);
$site_name = "Consult Pro"; // Default value
if ($site_name_result && $site_name_result->num_rows > 0) {
    $site_name_row = $site_name_result->fetch_assoc();
    $site_name = $site_name_row["setting_value"];
}

// Initialize variables
$error_message = "";
$success_message = "";
$current_page = 1;
$items_per_page = 10;
$search = "";

// Get search parameter
if (isset($_GET["search"])) {
    $search = trim($_GET["search"]);
}

// Get current page
if (isset($_GET["page"]) && is_numeric($_GET["page"])) {
    $current_page = (int)$_GET["page"];
    if ($current_page < 1) {
        $current_page = 1;
    }
}

// Check if success message is passed in URL
if (isset($_GET["success"])) {
    $success_message = $_GET["success"];
}

// Handle category actions (add, edit, delete)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Add new category
    if (isset($_POST["action"]) && $_POST["action"] == "add_category") {
        $category_name = trim($_POST["category_name"]);
        
        if (empty($category_name)) {
            $error_message = "Category name is required.";
        } else {
            // Check if category already exists
            $check_sql = "SELECT id FROM categories WHERE name = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $category_name);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Category already exists.";
            } else {
                // Insert new category
                $insert_sql = "INSERT INTO categories (name) VALUES (?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("s", $category_name);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Category added successfully.";
                } else {
                    $error_message = "Error adding category: " . $conn->error;
                }
            }
        }
    }
    
    // Edit category
    if (isset($_POST["action"]) && $_POST["action"] == "edit_category") {
        $category_id = $_POST["category_id"];
        $category_name = trim($_POST["category_name"]);
        
        if (empty($category_name)) {
            $error_message = "Category name is required.";
        } else {
            // Check if category already exists with this name (excluding current category)
            $check_sql = "SELECT id FROM categories WHERE name = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $category_name, $category_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Category with this name already exists.";
            } else {
                // Update category
                $update_sql = "UPDATE categories SET name = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $category_name, $category_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Category updated successfully.";
                } else {
                    $error_message = "Error updating category: " . $conn->error;
                }
            }
        }
    }
    
    // Delete category
    if (isset($_POST["action"]) && $_POST["action"] == "delete_category") {
        $category_id = $_POST["category_id"];
        
        // Check if category has subcategories
        $check_sql = "SELECT id FROM subcategories WHERE category_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $category_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Cannot delete category with subcategories. Please delete subcategories first.";
        } else {
            // Delete category
            $delete_sql = "DELETE FROM categories WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $category_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Category deleted successfully.";
            } else {
                $error_message = "Error deleting category: " . $conn->error;
            }
        }
    }
    
    // Add new subcategory
    if (isset($_POST["action"]) && $_POST["action"] == "add_subcategory") {
        $category_id = $_POST["category_id"];
        $subcategory_name = trim($_POST["subcategory_name"]);
        
        if (empty($subcategory_name)) {
            $error_message = "Subcategory name is required.";
        } else {
            // Check if subcategory already exists in this category
            $check_sql = "SELECT id FROM subcategories WHERE name = ? AND category_id = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("si", $subcategory_name, $category_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Subcategory already exists in this category.";
            } else {
                // Insert new subcategory
                $insert_sql = "INSERT INTO subcategories (name, category_id) VALUES (?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("si", $subcategory_name, $category_id);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Subcategory added successfully.";
                } else {
                    $error_message = "Error adding subcategory: " . $conn->error;
                }
            }
        }
    }
    
    // Edit subcategory
    if (isset($_POST["action"]) && $_POST["action"] == "edit_subcategory") {
        $subcategory_id = $_POST["subcategory_id"];
        $category_id = $_POST["category_id"];
        $subcategory_name = trim($_POST["subcategory_name"]);
        
        if (empty($subcategory_name)) {
            $error_message = "Subcategory name is required.";
        } else {
            // Check if subcategory already exists with this name in the same category (excluding current subcategory)
            $check_sql = "SELECT id FROM subcategories WHERE name = ? AND category_id = ? AND id != ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("sii", $subcategory_name, $category_id, $subcategory_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Subcategory with this name already exists in this category.";
            } else {
                // Update subcategory
                $update_sql = "UPDATE subcategories SET name = ?, category_id = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sii", $subcategory_name, $category_id, $subcategory_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Subcategory updated successfully.";
                } else {
                    $error_message = "Error updating subcategory: " . $conn->error;
                }
            }
        }
    }
    
    // Delete subcategory
    if (isset($_POST["action"]) && $_POST["action"] == "delete_subcategory") {
        $subcategory_id = $_POST["subcategory_id"];
        
        // Check if subcategory is being used by experts
        $check_sql = "SELECT id FROM expert_profiledetails WHERE subcategory = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("i", $subcategory_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Cannot delete subcategory that is being used by experts.";
        } else {
            // Delete subcategory
            $delete_sql = "DELETE FROM subcategories WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $subcategory_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Subcategory deleted successfully.";
            } else {
                $error_message = "Error deleting subcategory: " . $conn->error;
            }
        }
    }
}

// Get categories with count of subcategories
$sql_count = "SELECT COUNT(*) as total FROM categories";
$sql = "SELECT c.id, c.name, COUNT(s.id) as subcategory_count 
        FROM categories c 
        LEFT JOIN subcategories s ON c.id = s.category_id";

// Add search condition if search is provided
if (!empty($search)) {
    $sql_count .= " WHERE name LIKE ?";
    $sql .= " WHERE c.name LIKE ?";
    $search_param = "%" . $search . "%";
}

// Add GROUP BY and ORDER BY
$sql .= " GROUP BY c.id ORDER BY c.name ASC";

// Execute count query
if (!empty($search)) {
    $stmt_count = $conn->prepare($sql_count);
    $stmt_count->bind_param("s", $search_param);
    $stmt_count->execute();
    $count_result = $stmt_count->get_result();
} else {
    $count_result = $conn->query($sql_count);
}

$count_row = $count_result->fetch_assoc();
$total_categories = $count_row["total"];

// Calculate pagination
$total_pages = ceil($total_categories / $items_per_page);
if ($current_page > $total_pages && $total_pages > 0) {
    $current_page = $total_pages;
}
$offset = ($current_page - 1) * $items_per_page;

// Add LIMIT clause for pagination
$sql .= " LIMIT ?, ?";

// Execute main query
$stmt = $conn->prepare($sql);
if (!empty($search)) {
    $stmt->bind_param("sii", $search_param, $offset, $items_per_page);
} else {
    $stmt->bind_param("ii", $offset, $items_per_page);
}
$stmt->execute();
$result = $stmt->get_result();

// Get all categories for dropdown
$categories_sql = "SELECT id, name FROM categories ORDER BY name ASC";
$categories_result = $conn->query($categories_sql);
$categories = [];
while ($category = $categories_result->fetch_assoc()) {
    $categories[] = $category;
}

// Get statistics
$stats = [
    'total_categories' => $total_categories,
    'total_subcategories' => 0
];

$stats_sql = "SELECT COUNT(*) as total FROM subcategories";
$stats_result = $conn->query($stats_sql);
if ($stats_result && $stats_result->num_rows > 0) {
    $stats['total_subcategories'] = $stats_result->fetch_assoc()['total'];
}

// Get most used categories
$most_used_sql = "SELECT c.id, c.name, COUNT(ep.id) as usage_count 
                 FROM categories c 
                 LEFT JOIN expert_profiledetails ep ON c.id = ep.category 
                 GROUP BY c.id 
                 ORDER BY usage_count DESC 
                 LIMIT 1";
$most_used_result = $conn->query($most_used_sql);
if ($most_used_result && $most_used_result->num_rows > 0) {
    $stats['most_used'] = $most_used_result->fetch_assoc();
} else {
    $stats['most_used'] = ['name' => 'None', 'usage_count' => 0];
}


// Get unread notifications count
$unread_notifications_count = $conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0")->fetch_assoc()['count'];

// Get pending withdrawals count
$pending_withdrawals = $conn->query("SELECT COUNT(*) as count FROM withdrawal_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending fund requests count
$pending_fund_requests = $conn->query("SELECT COUNT(*) as count FROM fund_requests WHERE status = 'pending'")->fetch_assoc()['count'];

// Get pending expert profile count
$pending_review_profiles = $conn->query("SELECT COUNT(*) as count FROM expert_profiledetails WHERE status = 'pending_review'")->fetch_assoc()['count'];
 
$pending_messages = $conn->query("SELECT COUNT(*) as count FROM support_messages WHERE status = 'pending'")->fetch_assoc()['count'];
$pending_reports = $conn->query("SELECT COUNT(*) as count FROM reports WHERE status = 'pending'")->fetch_assoc()['count'];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - <?php echo htmlspecialchars($site_name); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Main Colors */
            --primary-color: #7C3AED;
            --primary-light: #A78BFA;
            --primary-dark: #6D28D9;
            --primary-bg: rgba(124, 58, 237, 0.1);
            --primary-gradient: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%);
            
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
            --shadow-outline: 0 0 0 3px rgba(124, 58, 237, 0.2);
            
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

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, 'Open Sans', 'Helvetica Neue', sans-serif;
        }

        body {
            background: var(--body-bg-gradient);
            color: var(--text-color);
            line-height: 1.6;
            font-size: 0.95rem;
            overflow-x: hidden;
            min-height: 100vh;
            position: relative;
            width: 100%;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%237C3AED' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
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

        .container {
            display: flex;
            min-height: 100vh;
            width: 100%;
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
            transform: translateX(-100%);
        }

        .sidebar.active {
            transform: translateX(0);
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
            background-color: rgba(124, 58, 237, 0.1);
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

        .main-content {
            flex: 1;
            width: 100%;
            padding: 1rem;
            transition: var(--transition);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
            position: relative;
            flex-wrap: wrap;
            gap: 1rem;
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
        
        .user-avatar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 50%);
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

        .header h1 {
            color: var(--dark-color);
            font-size: 1.5rem;
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
            box-shadow: var(--shadow);
        }

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

        .alert-danger {
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

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
            width: 100%;
        }

        .stat-card {
            display: flex;
            flex-direction: column;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-5px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary-gradient);
        }

        .stat-card.purple::before {
            background: var(--primary-gradient);
        }

        .stat-card.blue::before {
            background: var(--info-gradient);
        }

        .stat-card.green::before {
            background: var(--success-gradient);
        }

        .stat-card-content {
            padding: 1.25rem 1.25rem 1.25rem 1.5rem;
        }

        .stat-card-header {
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
        }

        .stat-card-header i {
            margin-right: 0.5rem;
            font-size: 1.25rem;
        }

        .stat-card.purple .stat-card-header i {
            color: var(--primary-color);
        }

        .stat-card.blue .stat-card-header i {
            color: var(--info-color);
        }

        .stat-card.green .stat-card-header i {
            color: var(--success-color);
        }

        .stat-card-title {
            font-weight: 600;
            color: var(--text-color-dark);
        }

        .stat-card-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-card.purple .stat-card-value {
            color: var(--primary-color);
        }

        .stat-card.blue .stat-card-value {
            color: var(--info-color);
        }

        .stat-card.green .stat-card-value {
            color: var(--success-color);
        }

        .stat-card-description {
            font-size: 0.875rem;
            color: var(--text-color-light);
        }

        .card {
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: var(--transition);
            position: relative;
            border: 1px solid var(--border-color);
            width: 100%;
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

        .search-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 1.5rem;
            width: 100%;
        }

        .search-input {
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--text-color);
            background-color: white;
            transition: var(--transition-fast);
            box-shadow: var(--shadow-sm);
            width: 100%;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }

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

        .btn::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10, 10);
            opacity: 0;
            transition: transform 0.5s, opacity 1s;
        }

        .btn:active::after {
            transform: scale(0, 0);
            opacity: 0.3;
            transition: 0s;
        }

        .btn i {
            font-size: 1rem;
        }

        .btn-primary {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.3);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 15px rgba(124, 58, 237, 0.4);
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

        .btn-warning {
            background: var(--warning-gradient);
            color: white;
            box-shadow: 0 4px 10px rgba(245, 158, 11, 0.3);
        }

        .btn-warning:hover {
            box-shadow: 0 6px 15px rgba(245, 158, 11, 0.4);
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
        }

        .table-container {
            width: 100%;
            overflow-x: auto;
            margin-bottom: 1.5rem;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
        }

        .categories-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            min-width: 800px;
        }

        .categories-table th, .categories-table td {
            padding: 0.75rem 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .categories-table th {
            background-color: var(--dark-color-2);
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .categories-table tr:last-child td {
            border-bottom: none;
        }

        .categories-table tr:hover {
            background-color: var(--light-color-2);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .badge {
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
            white-space: nowrap;
        }

        .badge-primary {
            background: var(--primary-gradient);
            color: white;
        }

        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 1.5rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .pagination a, .pagination span {
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            text-decoration: none;
            color: var(--text-color);
            background-color: white;
            transition: var(--transition-fast);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pagination a:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .pagination .active {
            background: var(--primary-gradient);
            color: white;
            border-color: var(--primary-color);
            box-shadow: 0 4px 10px rgba(124, 58, 237, 0.3);
        }

        .pagination .disabled {
            color: var(--text-color-lighter);
            cursor: not-allowed;
            opacity: 0.6;
        }

        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: var(--z-modal);
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }

        .modal-content {
            background-color: var(--card-bg);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            width: 67%;
            max-width: 90%;
            box-shadow: var(--shadow-xl);
            animation: slideInDown 0.3s ease;
            border: 1px solid var(--border-color);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border-color);
        }

        .modal-header h3 {
            margin: 0;
            color: var(--dark-color);
            font-size: 1.25rem;
            font-weight: 600;
        }

        .close {
            color: var(--text-color-light);
            font-size: 1.5rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-fast);
            width: 2rem;
            height: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius);
        }

        .close:hover {
            color: var(--danger-color);
            background-color: var(--danger-bg);
        }

        .modal-body {
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-color-dark);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.625rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: var(--radius);
            font-size: 0.875rem;
            color: var(--text-color);
            background-color: white;
            transition: var(--transition-fast);
            box-shadow: var(--shadow-sm);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: var(--shadow-outline);
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border-color);
        }

        .no-categories {
            text-align: center;
            padding: 3rem;
            background-color: var(--card-bg);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
        }

        .no-categories i {
            font-size: 3rem;
            color: var(--text-color-lighter);
            margin-bottom: 1rem;
        }

        .no-categories p {
            font-size: 1.125rem;
            color: var(--text-color-light);
            margin-bottom: 1.5rem;
        }

        .menu-toggle {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: var(--radius);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: var(--z-fixed);
            box-shadow: var(--shadow);
        }

        .text-danger {
            color: var(--danger-color);
            font-weight: 500;
        }

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

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @media (min-width: 768px) {
            .main-content {
                padding: 2rem;
                margin-left: 280px;
            }
            
            .sidebar {
                transform: translateX(0);
            }
            
            .menu-toggle {
                display: none;
            }
            
            .search-form {
                flex-direction: row;
                flex-wrap: wrap;
            }
            
            .header h1 {
                font-size: 1.75rem;
            }

            .action-bar {
                flex-direction: row;
            }
        }

        @media (max-width: 767px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar {
                width: 250px;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .action-bar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .search-form {
                width: 100%;
            }
            
            .action-buttons .btn {
                padding: 0.5rem;
            }
            
            .action-buttons .btn i {
                margin-right: 0;
            }
            
            .action-buttons .btn span {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .pagination a, .pagination span {
                padding: 0.5rem 0.75rem;
            }
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
    </style>
</head>
<body>
    <button class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </button>
    <div class="container">
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
                <a href="expert-profiles.php" class="menu-item">
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
                    <?php if ($pending_withdrawals > 0): ?>
                        <span class="notification-badge"><?php echo $pending_withdrawals; ?></span>
                    <?php endif; ?>
                </a>

                <a href="fund-requests.php" class="menu-item">
                    <i class="fas fa-wallet"></i> Fund Requests
                    <?php if ($pending_fund_requests > 0): ?>
                        <span class="notification-badge"><?php echo $pending_fund_requests; ?></span>
                    <?php endif; ?>
                </a>


                <a href="consultations.php" class="menu-item">
                    <i class="fas fa-calendar-check"></i> Consultations
                </a>
                <a href="transactions.php" class="menu-item">
                    <i class="fas fa-exchange-alt"></i> Transactions
                </a>
                <a href="categories.php" class="menu-item active">
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
        
        <div class="main-content">
            <div class="header">
                <h1><i class="fas fa-tags"></i> Category Management</h1>
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
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?php echo $success_message; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php echo $error_message; ?></div>
                    <span class="alert-close" onclick="this.parentElement.style.display='none';">&times;</span>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-container">
                <div class="stat-card purple">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-tags"></i>
                            <span class="stat-card-title">Total Categories</span>
                        </div>
                        <div class="stat-card-value"><?php echo $stats['total_categories']; ?></div>
                        <div class="stat-card-description">All categories in the system</div>
                    </div>
                </div>
                <div class="stat-card blue">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-layer-group"></i>
                            <span class="stat-card-title">Subcategories</span>
                        </div>
                        <div class="stat-card-value"><?php echo $stats['total_subcategories']; ?></div>
                        <div class="stat-card-description">Total subcategories</div>
                    </div>
                </div>
                <div class="stat-card green">
                    <div class="stat-card-content">
                        <div class="stat-card-header">
                            <i class="fas fa-chart-line"></i>
                            <span class="stat-card-title">Most Used</span>
                        </div>
                        <div class="stat-card-value"><?php echo $stats['most_used']['name']; ?></div>
                        <div class="stat-card-description">Most popular category</div>
                    </div>
                </div>
            </div>
            
            <div class="action-bar">
                <form method="GET" class="search-form">
                    <input type="text" name="search" class="search-input" placeholder="Search categories" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="categories.php" class="btn btn-warning">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
                
                <button type="button" class="btn btn-success" onclick="openAddCategoryModal()">
                    <i class="fas fa-plus"></i> Add Category
                </button>
            </div>
            
            <?php if ($result && $result->num_rows > 0): ?>
                <div class="table-container">
                    <table class="categories-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Category Name</th>
                                <th>Subcategories</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row["id"]; ?></td>
                                    <td><?php echo htmlspecialchars($row["name"]); ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?php echo $row["subcategory_count"]; ?></span>
                                        <button type="button" class="btn btn-primary btn-sm" onclick="viewSubcategories(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button type="button" class="btn btn-success btn-sm" onclick="openAddSubcategoryModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                            <i class="fas fa-plus"></i> Add
                                        </button>
                                    </td>
                                    <td class="action-buttons">
                                        <button type="button" class="btn btn-primary" onclick="openEditCategoryModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                            <i class="fas fa-edit"></i> <span>Edit</span>
                                        </button>
                                        <button type="button" class="btn btn-danger" onclick="openDeleteCategoryModal(<?php echo $row['id']; ?>, '<?php echo htmlspecialchars($row['name']); ?>')">
                                            <i class="fas fa-trash-alt"></i> <span>Delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($current_page > 1): ?>
                            <a href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                            <a href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-angle-left"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-double-left"></i></span>
                            <span class="disabled"><i class="fas fa-angle-left"></i></span>
                        <?php endif; ?>
                        
                        <?php
                        // Calculate range of page numbers to display
                        $range = 2; // Display 2 pages before and after current page
                        $start_page = max(1, $current_page - $range);
                        $end_page = min($total_pages, $current_page + $range);
                        
                        // Always show first page
                        if ($start_page > 1) {
                            echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '">1</a>';
                            if ($start_page > 2) {
                                echo '<span class="disabled">...</span>';
                            }
                        }
                        
                        // Display page numbers
                        for ($i = $start_page; $i <= $end_page; $i++) {
                            if ($i == $current_page) {
                                echo '<span class="active">' . $i . '</span>';
                            } else {
                                echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $i . '</a>';
                            }
                        }
                        
                        // Always show last page
                        if ($end_page < $total_pages) {
                            if ($end_page < $total_pages - 1) {
                                echo '<span class="disabled">...</span>';
                            }
                            echo '<a href="?page=' . $total_pages . (!empty($search) ? '&search=' . urlencode($search) : '') . '">' . $total_pages . '</a>';
                        }
                        ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                            <a href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-angle-right"></i>
                            </a>
                            <a href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="disabled"><i class="fas fa-angle-right"></i></span>
                            <span class="disabled"><i class="fas fa-angle-double-right"></i></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="no-categories">
                    <i class="fas fa-tag"></i>
                    <p>No categories found matching your criteria.</p>
                    <a href="categories.php" class="btn btn-primary">
                        <i class="fas fa-redo"></i> Reset Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add Category Modal -->
    <div id="addCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Category</h3>
                <span class="close" onclick="closeAddCategoryModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="category_name">Category Name</label>
                        <input type="text" id="category_name" name="category_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeAddCategoryModal()">Cancel</button>
                    <input type="hidden" name="action" value="add_category">
                    <button type="submit" class="btn btn-success">Add Category</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Edit Category Modal -->
    <div id="editCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Category</h3>
                <span class="close" onclick="closeEditCategoryModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_category_name">Category Name</label>
                        <input type="text" id="edit_category_name" name="category_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeEditCategoryModal()">Cancel</button>
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Category Modal -->
    <div id="deleteCategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Category</h3>
                <span class="close" onclick="closeDeleteCategoryModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to delete the category "<span id="delete_category_name"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeDeleteCategoryModal()">Cancel</button>
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" id="delete_category_id">
                    <button type="submit" class="btn btn-danger">Delete Category</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Add Subcategory Modal -->
    <div id="addSubcategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Subcategory</h3>
                <span class="close" onclick="closeAddSubcategoryModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="subcategory_category">Category</label>
                        <input type="text" id="subcategory_category_name" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label for="subcategory_name">Subcategory Name</label>
                        <input type="text" id="subcategory_name" name="subcategory_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeAddSubcategoryModal()">Cancel</button>
                    <input type="hidden" name="action" value="add_subcategory">
                    <input type="hidden" name="category_id" id="subcategory_category_id">
                    <button type="submit" class="btn btn-success">Add Subcategory</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Subcategories Modal -->
    <div id="viewSubcategoriesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Subcategories for <span id="view_category_name"></span></h3>
                <span class="close" onclick="closeViewSubcategoriesModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="subcategories_list">
                    Loading subcategories...
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn" onclick="closeViewSubcategoriesModal()">Close</button>
                <button type="button" class="btn btn-success" id="add_subcategory_btn">Add Subcategory</button>
            </div>
        </div>
    </div>
    
    <!-- Edit Subcategory Modal -->
    <div id="editSubcategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Subcategory</h3>
                <span class="close" onclick="closeEditSubcategoryModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit_subcategory_category">Category</label>
                        <select name="category_id" id="edit_subcategory_category" class="form-control" required>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit_subcategory_name">Subcategory Name</label>
                        <input type="text" id="edit_subcategory_name" name="subcategory_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeEditSubcategoryModal()">Cancel</button>
                    <input type="hidden" name="action" value="edit_subcategory">
                    <input type="hidden" name="subcategory_id" id="edit_subcategory_id">
                    <button type="submit" class="btn btn-primary">Update Subcategory</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Subcategory Modal -->
    <div id="deleteSubcategoryModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Delete Subcategory</h3>
                <span class="close" onclick="closeDeleteSubcategoryModal()">&times;</span>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <p>Are you sure you want to delete the subcategory "<span id="delete_subcategory_name"></span>"?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" onclick="closeDeleteSubcategoryModal()">Cancel</button>
                    <input type="hidden" name="action" value="delete_subcategory">
                    <input type="hidden" name="subcategory_id" id="delete_subcategory_id">
                    <button type="submit" class="btn btn-danger">Delete Subcategory</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Category modals
        function openAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'flex';
        }
        
        function closeAddCategoryModal() {
            document.getElementById('addCategoryModal').style.display = 'none';
        }
        
        function openEditCategoryModal(id, name) {
            document.getElementById('edit_category_id').value = id;
            document.getElementById('edit_category_name').value = name;
            document.getElementById('editCategoryModal').style.display = 'flex';
        }
        
        function closeEditCategoryModal() {
            document.getElementById('editCategoryModal').style.display = 'none';
        }
        
        function openDeleteCategoryModal(id, name) {
            document.getElementById('delete_category_id').value = id;
            document.getElementById('delete_category_name').textContent = name;
            document.getElementById('deleteCategoryModal').style.display = 'flex';
        }
        
        function closeDeleteCategoryModal() {
            document.getElementById('deleteCategoryModal').style.display = 'none';
        }
        
        // Subcategory modals
        function openAddSubcategoryModal(categoryId, categoryName) {
            document.getElementById('subcategory_category_id').value = categoryId;
            document.getElementById('subcategory_category_name').value = categoryName;
            document.getElementById('addSubcategoryModal').style.display = 'flex';
        }
        
        function closeAddSubcategoryModal() {
            document.getElementById('addSubcategoryModal').style.display = 'none';
        }
        
        function viewSubcategories(categoryId, categoryName) {
            document.getElementById('view_category_name').textContent = categoryName;
            document.getElementById('subcategories_list').innerHTML = 'Loading subcategories...';
            document.getElementById('viewSubcategoriesModal').style.display = 'flex';
            
            // Add event listener to the "Add Subcategory" button
            document.getElementById('add_subcategory_btn').onclick = function() {
                closeViewSubcategoriesModal();
                openAddSubcategoryModal(categoryId, categoryName);
            };
            
            // Fetch subcategories via AJAX
            fetch('get_subcategories.php?category_id=' + categoryId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('subcategories_list').innerHTML = data;
                    
                    // Add event listeners to edit and delete buttons
                    const editButtons = document.querySelectorAll('.edit-subcategory-btn');
                    const deleteButtons = document.querySelectorAll('.delete-subcategory-btn');
                    
                    editButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            const subcategoryId = this.getAttribute('data-id');
                            const subcategoryName = this.getAttribute('data-name');
                            const subcategoryCategoryId = this.getAttribute('data-category-id');
                            
                            closeViewSubcategoriesModal();
                            openEditSubcategoryModal(subcategoryId, subcategoryName, subcategoryCategoryId);
                        });
                    });
                    
                    deleteButtons.forEach(button => {
                        button.addEventListener('click', function() {
                            const subcategoryId = this.getAttribute('data-id');
                            const subcategoryName = this.getAttribute('data-name');
                            
                            closeViewSubcategoriesModal();
                            openDeleteSubcategoryModal(subcategoryId, subcategoryName);
                        });
                    });
                })
                .catch(error => {
                    document.getElementById('subcategories_list').innerHTML = 'Error loading subcategories: ' + error;
                });
        }
        
        function closeViewSubcategoriesModal() {
            document.getElementById('viewSubcategoriesModal').style.display = 'none';
        }
        
        function openEditSubcategoryModal(id, name, categoryId) {
            document.getElementById('edit_subcategory_id').value = id;
            document.getElementById('edit_subcategory_name').value = name;
            document.getElementById('edit_subcategory_category').value = categoryId;
            document.getElementById('editSubcategoryModal').style.display = 'flex';
        }
        
        function closeEditSubcategoryModal() {
            document.getElementById('editSubcategoryModal').style.display = 'none';
        }
        
        function openDeleteSubcategoryModal(id, name) {
            document.getElementById('delete_subcategory_id').value = id;
            document.getElementById('delete_subcategory_name').textContent = name;
            document.getElementById('deleteSubcategoryModal').style.display = 'flex';
        }
        
        function closeDeleteSubcategoryModal() {
            document.getElementById('deleteSubcategoryModal').style.display = 'none';
        }
        
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
            
            // Add pulse animation to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.querySelector('.stat-card-header i').classList.add('pulse');
                });
                
                card.addEventListener('mouseleave', function() {
                    this.querySelector('.stat-card-header i').classList.remove('pulse');
                });
            });
        
            // Mobile sidebar toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.querySelector('.sidebar');
        
            if (menuToggle) {
                menuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('active');
                });
            }
        
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 768 && 
                    !sidebar.contains(event.target) && 
                    !menuToggle.contains(event.target) && 
                    sidebar.classList.contains('active')) {
                    sidebar.classList.remove('active');
                }
            });
        });
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let i = 0; i < modals.length; i++) {
                if (event.target == modals[i]) {
                    modals[i].style.display = 'none';
                }
            }
        }
        
        // Function to refresh notification badges
        function refreshNotificationBadges() {
            fetch('get-notification-counts.php')
                .then(response => response.json())
                .then(data => {
                    // Update notification badges
                    updateBadge('unread_notifications', data.unread_notifications);
                    updateBadge('pending_withdrawals', data.pending_withdrawals);
                    updateBadge('pending_fund_requests', data.pending_fund_requests);
                    updateBadge('pending_review_profiles', data.pending_review_profiles);
                    updateBadge('pending_messages', data.pending_messages);
                    updateBadge('pending_reports', data.pending_reports);
                })
                .catch(error => console.error('Error fetching notification counts:', error));
        }

        // Function to update a specific badge
        function updateBadge(type, count) {
            const badges = document.querySelectorAll(`.menu-item:has(i.fas.fa-${getBadgeIcon(type)}) .notification-badge`);
            
            badges.forEach(badge => {
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            });
        }

        // Helper function to get icon name based on notification type
        function getBadgeIcon(type) {
            switch(type) {
                case 'unread_notifications': return 'bell';
                case 'pending_withdrawals': return 'money-bill-wave';
                case 'pending_fund_requests': return 'wallet';
                case 'pending_review_profiles': return 'user-tie';
                case 'pending_messages': return 'comments';
                case 'pending_reports': return 'flag';
                default: return '';
            }
        }

        // Create get_subcategories.php file if it doesn't exist
        // This file should be created separately to handle AJAX requests for subcategories
    </script>
</body>
</html>
