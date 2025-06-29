<?php
// Include database connection
require_once '../config/config.php';

// Check if category ID is provided
if (!isset($_GET['category_id']) || empty($_GET['category_id'])) {
  echo json_encode([]);
  exit;
}

$category_id = intval($_GET['category_id']);

// Get subcategories for the selected category
$sql = "SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

$subcategories = [];
while ($row = $result->fetch_assoc()) {
  $subcategories[] = [
      'id' => $row['id'],
      'name' => $row['name']
  ];
}

// Debug output
if (empty($subcategories)) {
    error_log("No subcategories found for category_id: $category_id");
}

// Return subcategories as JSON
header('Content-Type: application/json');
echo json_encode($subcategories);
?>

