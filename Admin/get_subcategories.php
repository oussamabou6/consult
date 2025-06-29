<?php
// Start session
session_start();

// Include database connection
require_once '../config/config.php';



// Check if category ID is provided
if (!isset($_GET["category_id"]) || empty($_GET["category_id"])) {
    echo "Category ID is required";
    exit;
}

$category_id = $_GET["category_id"];

// Get subcategories for the category
$sql = "SELECT s.id, s.name, s.category_id, COUNT(e.id) as expert_count 
        FROM subcategories s 
        LEFT JOIN expert_profiledetails e ON s.id = e.subcategory 
        WHERE s.category_id = ? 
        GROUP BY s.id 
        ORDER BY s.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo '<table class="categories-table" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Subcategory Name</th>
                    <th>Experts</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>';
    
    while ($row = $result->fetch_assoc()) {
        echo '<tr>
                <td>' . $row["id"] . '</td>
                <td>' . htmlspecialchars($row["name"]) . '</td>
                <td>' . $row["expert_count"] . '</td>
                <td class="action-buttons">
                    <button type="button" class="btn btn-primary edit-subcategory-btn" 
                        data-id="' . $row["id"] . '" 
                        data-name="' . htmlspecialchars($row["name"]) . '" 
                        data-category-id="' . $row["category_id"] . '">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-danger delete-subcategory-btn" 
                        data-id="' . $row["id"] . '" 
                        data-name="' . htmlspecialchars($row["name"]) . '">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </td>
            </tr>';
    }
    
    echo '</tbody></table>';
} else {
    echo '<p>No subcategories found for this category.</p>';
}
?>

