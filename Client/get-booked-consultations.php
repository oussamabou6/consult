<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-warning">Please log in to view your consultations.</div>';
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user's active consultations
$consultationsQuery = "SELECT c.*, 
              u.full_name as expert_name, 
              u.status as expert_status,
              up.profile_image as expert_image,
              c.created_at as booking_date,
              ep.category, ep.subcategory,
              c.expert_message as expert_message,
              p.amount as price
              FROM consultations c
              JOIN payments p ON c.id = p.consultation_id
              JOIN users u ON c.expert_id = u.id
              JOIN user_profiles up ON u.id = up.user_id
              LEFT JOIN expert_profiledetails ep ON u.id = ep.user_id
              WHERE c.client_id = $userId 
              AND c.status IN ('pending', 'confirmed')
              ORDER BY c.created_at DESC
              LIMIT 5";
$consultationsResult = $conn->query($consultationsQuery);


// Fetch site settings
$settingsQuery = "SELECT * FROM settings";
$settingsResult = $conn->query($settingsQuery);
$settings = [];

if ($settingsResult->num_rows > 0) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
// Output HTML for the consultations section
?>
<div class="card shadow-sm border-0" data-aos="fade-up">
    <div class="card-header bg-white py-3">
        <h3 class="card-title mb-0 fw-bold">
            <i class="fas fa-calendar-check text-primary me-2"></i> Your Booked Consultations
        </h3>
    </div>
    <div class="card-body">
        <?php if ($consultationsResult && $consultationsResult->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Expert</th>
                            <th>Category</th>
                            <th>Subcategory</th>
                            <th>Date</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($consultation = $consultationsResult->fetch_assoc()): 
                            // Format date
                            $bookingDate = new DateTime($consultation['booking_date']);
                            $formattedDate = $bookingDate->format('M d, Y - H:i');
                            
                            // Get expert image
                            $expertImage = !empty($consultation['expert_image']) ? $consultation['expert_image'] : '../assets/images/default-expert.jpg';
                        ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="<?php echo $expertImage; ?>" alt="<?php echo $consultation['expert_name']; ?>" class="rounded-circle me-2" width="40" height="40">
                                        <div>
                                            <div class="fw-semibold"><?php echo $consultation['expert_name']; ?></div>
                                            <span class="badge <?php echo ($consultation['expert_status'] == 'Online') ? 'bg-success' : 'bg-secondary'; ?> rounded-pill">
                                                <?php echo $consultation['expert_status']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    // Get category name
                                    $catQuery = "SELECT c.name FROM categories c 
                                                JOIN expert_profiledetails ep ON c.id = ep.category 
                                                WHERE ep.user_id = " . $consultation['expert_id'];
                                    $catResult = $conn->query($catQuery);
                                    $categoryName = ($catResult && $catResult->num_rows > 0) ? $catResult->fetch_assoc()['name'] : 'N/A';
                                    echo htmlspecialchars(ucfirst($categoryName)); 
                                    ?>
                                </td>
                                <td>
                                    <?php 
                                    // Get subcategory name
                                    $subcatQuery = "SELECT sc.name FROM subcategories sc 
                                                   JOIN expert_profiledetails ep ON sc.id = ep.subcategory 
                                                   WHERE ep.user_id = " . $consultation['expert_id'];
                                    $subcatResult = $conn->query($subcatQuery);
                                    $subcategoryName = ($subcatResult && $subcatResult->num_rows > 0) ? $subcatResult->fetch_assoc()['name'] : 'N/A';
                                    echo htmlspecialchars(ucfirst($subcategoryName)); 
                                    ?>
                                </td>
                                <td><?php echo $formattedDate; ?></td>
                                <td>
                                    <?php echo $consultation['duration']; ?> min
                                    <br>
                                    <strong><?php echo number_format($consultation['price'], 2); ?> <?php echo $settings['currency']; ?></strong>
                                </td>
                                <td>
                                    <?php if($consultation['status'] == 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php elseif($consultation['status'] == 'confirmed'): ?>
                                        <span class="badge bg-success">Confirmed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($consultation['status'] == 'pending'): ?>
                                        <a href="cancel-consultation.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to cancel this consultation?');">
                                            <i class="fas fa-times me-1"></i> Cancel
                                        </a>
                                    <?php elseif($consultation['status'] == 'confirmed'): ?>
                                        <a href="consultation-chat.php?id=<?php echo $consultation['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-comments me-1"></i> Go to Chat
                                        </a>
                                    
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if(!empty($consultation['expert_message'])): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="expert-message-card">
                                        <h5 class="expert-message-title">Message from Expert</h5>
                                        <div class="expert-message-content">
                                            <?php echo htmlspecialchars($consultation['expert_message']); ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if($consultationsResult->num_rows > 4): ?>
                <div class="text-center mt-3">
                    <a href="my-consultations.php" class="btn btn-link text-primary">
                        View All Consultations <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-4">
                <h5>No Active Consultations</h5>
                <p class="text-muted">You don't have any active consultations at the moment.</p>
                <p class="mb-0">Browse experts below and book a consultation.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
