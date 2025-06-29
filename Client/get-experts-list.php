<?php
// Start session
session_start();

// Include database configuration
require_once("../config/config.php");

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userId = $isLoggedIn ? $_SESSION['user_id'] : null;

// Get user balance if logged in
$userBalance = 0;
if ($isLoggedIn) {
    $balanceQuery = "SELECT balance FROM users WHERE id = $userId";
    $balanceResult = $conn->query($balanceQuery);
    if ($balanceResult && $balanceResult->num_rows > 0) {
        $userBalance = $balanceResult->fetch_assoc()['balance'];
    }
}

// Get search parameters
$searchCategory = isset($_GET['category']) ? $_GET['category'] : '';
$searchSubcategory = isset($_GET['subcategory']) ? $_GET['subcategory'] : '';
$searchName = isset($_GET['name']) ? $_GET['name'] : '';
$searchSkills = isset($_GET['skills']) ? $_GET['skills'] : '';

// Build the experts query with search filters
$expertsQuery = "SELECT u.id, u.full_name, u.status, up.profile_image, up.bio, 
                ep.id as profile_id, ep.category, ep.subcategory, 
                c.name as category_name, sc.name as subcategory_name,
                ct.name as city_name, ep.workplace_map_url,
                COUNT(con.id) as consultation_count,
                COALESCE(AVG(er.rating), 0) as avg_rating, 
                COUNT(DISTINCT er.id) as review_count,
                bi.consultation_price, bi.consultation_minutes
                FROM users u
                JOIN user_profiles up ON u.id = up.user_id
                JOIN expert_profiledetails ep ON u.id = ep.user_id
                LEFT JOIN categories c ON ep.category = c.id
                LEFT JOIN subcategories sc ON ep.subcategory = sc.id
                LEFT JOIN cities ct ON ep.city = ct.id
                LEFT JOIN consultations con ON u.id = con.expert_id AND con.status IN ('completed', 'confirmed')
                LEFT JOIN expert_ratings er ON ep.id = er.expert_id
                LEFT JOIN banking_information bi ON ep.id = bi.profile_id
                WHERE u.role = 'expert' AND ep.status = 'approved'";

// Add search filters if provided
if (!empty($searchCategory)) {
    $expertsQuery .= " AND ep.category = '$searchCategory'";
}

if (!empty($searchSubcategory)) {
    $expertsQuery .= " AND ep.subcategory = '$searchSubcategory'";
}

if (!empty($searchName)) {
    $expertsQuery .= " AND u.full_name LIKE '%$searchName%'";
}

if (!empty($searchSkills)) {
    $expertsQuery .= " AND ep.id IN (SELECT profile_id FROM skills WHERE skill_name LIKE '%$searchSkills%')";
}

$expertsQuery .= " GROUP BY u.id, u.full_name, up.profile_image, up.bio, ep.id, ep.category, ep.subcategory, 
                c.name, sc.name, ct.name, ep.workplace_map_url, bi.consultation_price, bi.consultation_minutes
                ORDER BY u.status = 'Online' DESC, avg_rating DESC, review_count DESC";

$expertsResult = $conn->query($expertsQuery);
$experts = [];

if ($expertsResult && $expertsResult->num_rows > 0) {
    while ($row = $expertsResult->fetch_assoc()) {
        // Get expert skills
        $skillsQuery = "SELECT skill_name FROM skills WHERE profile_id = " . $row['profile_id'];
        $skillsResult = $conn->query($skillsQuery);
        $skills = [];
        
        if ($skillsResult && $skillsResult->num_rows > 0) {
            while ($skillRow = $skillsResult->fetch_assoc()) {
                $skills[] = $skillRow['skill_name'];
            }
        }
        
        // Get expert certificates
        $certificatesQuery = "SELECT * FROM certificates WHERE profile_id = " . $row['profile_id'] . " AND status = 'approved' ORDER BY id";
        $certificatesResult = $conn->query($certificatesQuery);
        $certificates = [];
        
        if ($certificatesResult && $certificatesResult->num_rows > 0) {
            while ($certRow = $certificatesResult->fetch_assoc()) {
                $certificates[] = $certRow;
            }
        }
        
        // Get expert experiences
        $experiencesQuery = "SELECT * FROM experiences WHERE profile_id = " . $row['profile_id'] . " AND status = 'approved' ORDER BY id";
        $experiencesResult = $conn->query($experiencesQuery);
        $experiences = [];
        
        if ($experiencesResult && $experiencesResult->num_rows > 0) {
            while ($expRow = $experiencesResult->fetch_assoc()) {
                $experiences[] = $expRow;
            }
        }
        
        // Get expert formations (courses)
        $formationsQuery = "SELECT * FROM formations WHERE profile_id = " . $row['profile_id'] . " AND status = 'approved' ORDER BY id";
        $formationsResult = $conn->query($formationsQuery);
        $formations = [];
        
        if ($formationsResult && $formationsResult->num_rows > 0) {
            while ($formRow = $formationsResult->fetch_assoc()) {
                $formations[] = $formRow;
            }
        }
        
        // Get expert ratings and reviews
        $ratingsQuery = "SELECT er.*, u.full_name as client_name, up.profile_image as client_image
                        FROM expert_ratings er
                        JOIN users u ON er.client_id = u.id
                        JOIN user_profiles up ON u.id = up.user_id
                        WHERE er.expert_id = " . $row['profile_id'] . "
                        ORDER BY er.created_at DESC
                        LIMIT 5";
        $ratingsResult = $conn->query($ratingsQuery);
        $ratings = [];
        
        if ($ratingsResult && $ratingsResult->num_rows > 0) {
            while ($ratingRow = $ratingsResult->fetch_assoc()) {
                // Get expert responses to this rating
                $responseQuery = "SELECT * FROM expert_rating_responses WHERE rating_id = " . $ratingRow['id'];
                $responseResult = $conn->query($responseQuery);
                $responses = [];
                
                if ($responseResult && $responseResult->num_rows > 0) {
                    while ($responseRow = $responseResult->fetch_assoc()) {
                        $responses[] = $responseRow;
                    }
                }
                
                $ratingRow['responses'] = $responses;
                $ratings[] = $ratingRow;
            }
        }
        
        // Get expert social links
        $socialLinksQuery = "SELECT * FROM expert_social_links WHERE profile_id = " . $row['profile_id'];
        $socialLinksResult = $conn->query($socialLinksQuery);
        $socialLinks = null;
        
        if ($socialLinksResult && $socialLinksResult->num_rows > 0) {
            $socialLinks = $socialLinksResult->fetch_assoc();
        }
        
        $row['skills'] = $skills;
        $row['certificates'] = $certificates;
        $row['experiences'] = $experiences;
        $row['formations'] = $formations;
        $row['ratings'] = $ratings;
        $row['social_links'] = $socialLinks;
        $experts[] = $row;
    }
}

// Output the experts list HTML
if(count($experts) > 0): 
    foreach($experts as $expert): 
        // Get expert image
        $expertImage = !empty($expert['profile_image']) ? $expert['profile_image'] : '../assets/images/default-expert.jpg';
?>
        <div class="col-lg-4 col-md-6 mb-4" data-aos="fade-up" data-aos-delay="<?php echo 100 * array_search($expert, $experts) % 5; ?>">
            <div class="expert-card">
                <?php if($expert['avg_rating'] >= 4.5): ?>
                    <div class="expert-badge">Top Rated</div>
                <?php endif; ?>
                
                <?php if($expert['status'] == 'Online'): ?>
                    <div class="expert-status-badge online">
                        <i class="fas fa-circle me-1"></i> Online
                    </div>
                <?php else: ?>
                    <div class="expert-status-badge offline">
                        <i class="fas fa-circle me-1"></i> Offline
                    </div>
                <?php endif; ?>
                
                <div class="expert-img-container">
                    <?php if(!empty($expert['profile_image'])): ?>
                        <img src="<?php echo $expert['profile_image']; ?>" alt="<?php echo $expert['full_name']; ?>" class="expert-img">
                    <?php else: ?>
                        <img src="../assets/images/default-expert.jpg" alt="<?php echo $expert['full_name']; ?>" class="expert-img">
                    <?php endif; ?>
                </div>
                <div class="expert-info">
                    <h5 class="expert-name" data-expert-id="<?php echo $expert['id']; ?>"><?php echo $expert['full_name']; ?></h5>
                    <p class="expert-category"><?php echo $expert['category_name'] . ' - ' . $expert['subcategory_name']; ?></p>
                    <div class="expert-rating">
                        <?php 
                        $rating = round($expert['avg_rating']);
                        for($i = 1; $i <= 5; $i++): 
                            if($i <= $rating): ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif;
                        endfor; ?>
                        <span class="ms-1">(<?php echo $expert['review_count']; ?> reviews)</span>
                    </div>
                    
                    <?php if(isset($expert['consultation_price']) && isset($expert['consultation_minutes'])): ?>
                    <div class="expert-price">
                        <?php echo number_format($expert['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>
                        <span>/ <?php echo $expert['consultation_minutes']; ?> min</span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($expert['skills'])): ?>
                    <div class="expert-skills">
                        <?php foreach(array_slice($expert['skills'], 0, 3) as $skill): ?>
                            <span class="expert-skill"><?php echo $skill; ?></span>
                        <?php endforeach; ?>
                        <?php if(count($expert['skills']) > 3): ?>
                            <span class="expert-skill">+<?php echo count($expert['skills']) - 3; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if(!empty($expert['bio'])): ?>
                    <div class="expert-bio" id="bio-<?php echo $expert['id']; ?>">
                        <?php echo $expert['bio']; ?>
                    </div>
                    <?php if(strlen($expert['bio']) > 150): ?>
                    <span class="read-more" onclick="toggleBio(<?php echo $expert['id']; ?>)" id="read-more-<?php echo $expert['id']; ?>">Read more</span>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-outline-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#expertProfileModal<?php echo $expert['id']; ?>">
                            View Profile
                        </button>
                        <button type="button" class="btn btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $expert['id']; ?>" <?php echo ($expert['status'] != 'Online') ? 'disabled' : ''; ?>>
                            Book Consultation
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Expert Profile Modal -->
        <div class="modal fade" id="expertProfileModal<?php echo $expert['id']; ?>" tabindex="-1" aria-labelledby="expertProfileModalLabel<?php echo $expert['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="profile-modal-header">
                        <button type="button" class="btn-close position-absolute top-0 end-0 m-3 bg-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        <div class="profile-avatar-container">
                            <img src="<?php echo $expertImage; ?>" alt="<?php echo $expert['full_name']; ?>" class="profile-avatar">
                        </div>
                    </div>
                    <div class="modal-body profile-modal-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h2 class="mb-1"><?php echo $expert['full_name']; ?></h2>
                                <p class="text-primary fw-semibold mb-3"><?php echo $expert['category_name'] . ' - ' . $expert['subcategory_name']; ?></p>
                                
                                <div class="d-flex align-items-center mb-4">
                                    <div class="expert-rating me-3">
                                        <?php 
                                        $rating = round($expert['avg_rating']);
                                        for($i = 1; $i <= 5; $i++): 
                                            if($i <= $rating): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif;
                                        endfor; ?>
                                        <span class="ms-1">(<?php echo $expert['review_count']; ?> reviews)</span>
                                    </div>
                                    <span class="badge <?php echo ($expert['status'] == 'Online') ? 'bg-success' : 'bg-secondary'; ?> ms-2">
                                        <?php echo $expert['status']; ?>
                                    </span>
                                </div>
                                
                                <!-- Bio Section -->
                                <div class="profile-section">
                                    <h3 class="profile-section-title">About</h3>
                                    <p><?php echo !empty($expert['bio']) ? $expert['bio'] : 'No bio available.'; ?></p>
                                </div>
                                
                                <!-- Location Section -->
                                <div class="profile-section">
                                    <h3 class="profile-section-title">Location</h3>
                                    <p>
                                        <i class="fas fa-map-marker-alt me-2 text-primary"></i>
                                        <?php echo !empty($expert['city_name']) ? $expert['city_name'] : 'City not specified'; ?>
                                    </p>
                                    
                                    <?php if(!empty($expert['workplace_map_url'])): ?>
                                    <div class="mt-3">
                                        <a href="<?php echo $expert['workplace_map_url']; ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-map me-2"></i> View on Google Maps
                                        </a>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Skills Section -->
                                <?php if(!empty($expert['skills'])): ?>
                                <div class="profile-section">
                                    <h3 class="profile-section-title">Skills</h3>
                                    <div class="expert-skills">
                                        <?php foreach($expert['skills'] as $skill): ?>
                                            <span class="expert-skill"><?php echo $skill; ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Social Media Links -->
                                <?php if(!empty($expert['social_links'])): ?>
                                <div class="profile-section">
                                    <h3 class="profile-section-title">Connect</h3>
                                    <div class="social-links-container">
                                        <?php if(!empty($expert['social_links']['facebook_url'])): ?>
                                        <a href="<?php echo $expert['social_links']['facebook_url']; ?>" target="_blank" class="social-link">
                                            <i class="fab fa-facebook-f"></i> Facebook
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($expert['social_links']['instagram_url'])): ?>
                                        <a href="<?php echo $expert['social_links']['instagram_url']; ?>" target="_blank" class="social-link">
                                            <i class="fab fa-instagram"></i> Instagram
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($expert['social_links']['linkedin_url'])): ?>
                                        <a href="<?php echo $expert['social_links']['linkedin_url']; ?>" target="_blank" class="social-link">
                                            <i class="fab fa-linkedin-in"></i> LinkedIn
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($expert['social_links']['twitter_url'])): ?>
                                        <a href="<?php echo $expert['social_links']['twitter_url']; ?>" target="_blank" class="social-link">
                                            <i class="fab fa-twitter"></i> Twitter
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($expert['social_links']['github_url'])): ?>
                                        <a href="<?php echo $expert['social_links']['github_url']; ?>" target="_blank" class="social-link">
                                            <i class="fab fa-github"></i> GitHub
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if(!empty($expert['social_links']['website_url'])): ?>
                                        <a href="<?php echo $expert['social_links']['website_url']; ?>" target="_blank" class="social-link">
                                            <i class="fas fa-globe"></i> Website
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Reviews Section -->
                                <div class="profile-section">
                                    <h3 class="profile-section-title">Client Reviews</h3>
                                    <?php if(!empty($expert['ratings'])): ?>
                                        <?php foreach($expert['ratings'] as $rating): ?>
                                        <div class="review-card">
                                            <div class="review-header">
                                                <div class="review-avatar">
                                                    <?php if(!empty($rating['client_image'])): ?>
                                                        <img src="<?php echo $rating['client_image']; ?>" alt="<?php echo $rating['client_name']; ?>">
                                                    <?php else: ?>
                                                        <img src="../assets/images/default-client.jpg" alt="<?php echo $rating['client_name']; ?>">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="review-meta">
                                                    <div class="review-name"><?php echo $rating['client_name']; ?></div>
                                                    <div class="review-date"><?php echo date('M d, Y', strtotime($rating['created_at'])); ?></div>
                                                </div>
                                            </div>
                                            <div class="review-rating">
                                                <?php 
                                                for($i = 1; $i <= 5; $i++): 
                                                    if($i <= $rating['rating']): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star"></i>
                                                    <?php endif;
                                                endfor; 
                                                ?>
                                            </div>
                                            <div class="review-text"><?php echo $rating['comment']; ?></div>
                                            
                                            <?php if(!empty($rating['responses'])): ?>
                                            <div class="mt-3 p-3 bg-light rounded">
                                                <div class="d-flex align-items-center mb-2">
                                                    <div class="me-2">
                                                        <img src="<?php echo $expertImage; ?>" alt="<?php echo $expert['full_name']; ?>" class="rounded-circle" width="30" height="30">
                                                    </div>
                                                    <div>
                                                        <strong><?php echo $expert['full_name']; ?></strong>
                                                        <small class="text-muted d-block">Expert Response</small>
                                                    </div>
                                                </div>
                                                <p class="mb-0 small"><?php echo $rating['responses'][0]['response_text']; ?></p>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted">No reviews yet.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <!-- Consultation Price -->
                                <?php if(isset($expert['consultation_price']) && isset($expert['consultation_minutes'])): ?>
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title">Consultation Fee</h5>
                                        <div class="d-flex align-items-center">
                                            <h3 class="mb-0"><?php echo number_format($expert['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></h3>
                                            <span class="ms-2 text-muted">/ <?php echo $expert['consultation_minutes']; ?> min</span>
                                        </div>
                                        
                                        <button type="button" class="btn btn-primary w-100 mt-3" data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $expert['id']; ?>" <?php echo ($expert['status'] != 'Online') ? 'disabled' : ''; ?>>
                                            <?php if($expert['status'] == 'Online'): ?>
                                                Book Consultation
                                            <?php else: ?>
                                                Expert Offline
                                            <?php endif; ?>
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Credentials Section -->
                                <div class="card mb-4">
                                    <div class="card-body">
                                        <h5 class="card-title mb-3">Professional Credentials</h5>
                                        
                                        <!-- Certificates -->
                                        <?php if(!empty($expert['certificates'])): ?>
                                        <h6 class="fw-bold mb-2">Certificates</h6>
                                        <div class="mb-3">
                                            <?php foreach($expert['certificates'] as $cert): ?>
                                            <div class="credential-card">
                                                <div class="credential-title"><?php echo htmlspecialchars($cert['institution']); ?></div>
                                                <div class="credential-subtitle">
                                                    <?php 
                                                        $start_date = new DateTime($cert['start_date']);
                                                        $end_date = new DateTime($cert['end_date']);
                                                        echo $start_date->format('M Y') . ' - ' . $end_date->format('M Y');
                                                    ?>
                                                </div>
                                                <div class="credential-description"><?php echo htmlspecialchars($cert['description']); ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Experiences -->
                                        <?php if(!empty($expert['experiences'])): ?>
                                        <h6 class="fw-bold mb-2">Work Experience</h6>
                                        <div class="mb-3">
                                            <?php foreach($expert['experiences'] as $exp): ?>
                                            <div class="credential-card">
                                                <div class="credential-title"><?php echo htmlspecialchars($exp['workplace']); ?></div>
                                                <div class="credential-subtitle">
                                                    <?php 
                                                        $start_date = new DateTime($exp['start_date']);
                                                        $end_date = !empty($exp['end_date']) ? new DateTime($exp['end_date']) : null;
                                                        echo $start_date->format('M Y') . ' - ' . ($end_date ? $end_date->format('M Y') : 'Present');
                                                    ?>
                                                    <span class="ms-2">
                                                        (<?php echo $exp['duration_years']; ?> years, <?php echo $exp['duration_months']; ?> months)
                                                    </span>
                                                </div>
                                                <div class="credential-description"><?php echo htmlspecialchars($exp['description']); ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <!-- Formations (Courses) -->
                                        <?php if(!empty($expert['formations'])): ?>
                                        <h6 class="fw-bold mb-2">Courses & Training</h6>
                                        <div>
                                            <?php foreach($expert['formations'] as $form): ?>
                                            <div class="credential-card">
                                                <div class="credential-title"><?php echo htmlspecialchars($form['formation_name']); ?></div>
                                                <div class="credential-subtitle">
                                                    <?php echo htmlspecialchars(ucfirst($form['formation_type'])); ?> - 
                                                    <?php echo htmlspecialchars($form['formation_year']); ?>
                                                </div>
                                                <div class="credential-description"><?php echo htmlspecialchars($form['description']); ?></div>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if(empty($expert['certificates']) && empty($expert['experiences']) && empty($expert['formations'])): ?>
                                        <p class="text-muted">No professional credentials available.</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookingModal<?php echo $expert['id']; ?>" <?php echo ($expert['status'] != 'Online') ? 'disabled' : ''; ?>>
                            <?php if($expert['status'] == 'Online'): ?>
                                Book Consultation
                            <?php else: ?>
                                Expert Offline
                            <?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Booking Modal -->
        <div class="modal fade" id="bookingModal<?php echo $expert['id']; ?>" tabindex="-1" aria-labelledby="bookingModalLabel<?php echo $expert['id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="bookingModalLabel<?php echo $expert['id']; ?>">Book Consultation with <?php echo $expert['full_name']; ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body booking-modal-body">
                        <?php if($isLoggedIn): ?>
                            <div class="booking-price-info">
                                <div class="booking-price">
                                    <?php echo number_format($expert['consultation_price']); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>
                                    <span class="booking-duration">/ <?php echo $expert['consultation_minutes']; ?> min</span>
                                </div>
                                <p class="text-muted small mb-0">Base consultation fee</p>
                            </div>
                            
                            
                            <form id="bookingForm<?php echo $expert['id']; ?>" onsubmit="submitBookingForm(event, <?php echo $expert['id']; ?>)">
                                <input type="hidden" name="expert_id" value="<?php echo $expert['id']; ?>">
                                <input type="hidden" name="profile_id" value="<?php echo $expert['profile_id']; ?>">
                                <input type="hidden" name="base_price" value="<?php echo $expert['consultation_price']; ?>">
                                <input type="hidden" name="base_duration" value="<?php echo $expert['consultation_minutes']; ?>">
                                
                                <div class="mb-3">
                                    <label for="message<?php echo $expert['id']; ?>" class="form-label">Message to Expert <span class="text-danger">*</span></label>
                                    <textarea class="form-control" id="message<?php echo $expert['id']; ?>" name="message" rows="4" placeholder="Describe what you need help with..." required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="duration<?php echo $expert['id']; ?>" class="form-label">Consultation Duration (minutes) <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="duration<?php echo $expert['id']; ?>" name="duration" min="<?php echo $expert['consultation_minutes']; ?>" value="<?php echo $expert['consultation_minutes']; ?>" data-base-price="<?php echo $expert['consultation_price']; ?>" data-base-duration="<?php echo $expert['consultation_minutes']; ?>" data-user-balance="<?php echo $userBalance; ?>" onchange="calculateTotal(<?php echo $expert['id']; ?>, <?php echo $expert['consultation_price']; ?>, <?php echo $expert['consultation_minutes']; ?>, <?php echo $userBalance; ?>)" required>
                                        <span class="input-group-text">minutes</span>
                                    </div>
                                    <div class="form-text">Minimum duration: <?php echo $expert['consultation_minutes']; ?> minutes</div>
                                </div>
                                
                                <div class="booking-price-info mt-4">
                                    <div class="booking-total">
                                        Total: <span id="totalPrice<?php echo $expert['id']; ?>"><?php echo number_format($expert['consultation_price']); ?></span> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?>
                                    </div>
                                    <p class="text-muted small mb-0">Your current balance: <?php echo number_format($userBalance); ?> <?php echo isset($settings['currency']) ? $settings['currency'] : 'DA'; ?></p>
                                    <div id="balanceWarning<?php echo $expert['id']; ?>" class="text-danger small mt-2" style="display: none;">
                                        Your balance is insufficient for this consultation duration.
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-user-lock fa-3x text-muted mb-3"></i>
                                <h5>Please login to book a consultation</h5>
                                <p class="text-muted">You need to be logged in to book a consultation with our experts.</p>
                                <a href="../pages/login.php" class="btn btn-primary mt-2">Login Now</a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <?php if($isLoggedIn): ?>
                            <button type="submit" form="bookingForm<?php echo $expert['id']; ?>" class="btn btn-primary" id="submitBooking<?php echo $expert['id']; ?>">
                                Book Now
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="col-12 no-results" data-aos="fade-up">
        <div class="no-results-icon">
            <i class="fas fa-search"></i>
        </div>
        <h3 class="no-results-text">No experts found matching your criteria</h3>
        <p class="text-muted mb-4">Try adjusting your search filters or browse all experts</p>
        <a href="find-experts.php" class="btn btn-primary">View All Experts</a>
    </div>
<?php endif; ?>
