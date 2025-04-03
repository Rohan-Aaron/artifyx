<?php 
include 'includes/db.php';
include 'includes/header.php';


// Get selected category
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$category = [];
$editors = [];

if($category_id > 0) {
    // Get category details
    $category = $conn->query("SELECT * FROM services WHERE id = $category_id")->fetch_assoc();
    
    // Get editors in this category
    $editors = $conn->query("
        SELECT DISTINCT u.id, u.username, p.bio, p.skills, p.hourly_rate, p.availability 
        FROM users u
        INNER JOIN professional_services ps 
            ON u.id = ps.professional_id
        INNER JOIN profiles p 
            ON u.id = p.user_id
        WHERE ps.service_id = $category_id
        AND u.role = 'editor'
    ");
}
?>

<!-- Header Section -->
<section class="category-header bg-light py-5">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <!-- Breadcrumb Navigation -->
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                        <?php if($category_id > 0): ?>
                            <li class="breadcrumb-item"><a href="categories.php">Categories</a></li>
                            <li class="breadcrumb-item active"><?= htmlspecialchars($category['name']) ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item active">All Categories</li>
                        <?php endif; ?>
                    </ol>
                </nav>
                
                <!-- Page Title -->
                <h1 class="display-5 fw-bold mb-3"><?= $category_id > 0 ? htmlspecialchars($category['name']) : 'All Categories' ?></h1>
                
                <!-- Category Description -->
                <?php if($category_id > 0): ?>
                    <p class="lead text-muted"><?= htmlspecialchars($category['description']) ?></p>
                <?php endif; ?>
            </div>
            
            <!-- Category Stats -->
            <?php if($category_id > 0): ?>
                <div class="category-stats bg-white p-4 rounded-3 shadow-sm">
                    <div class="d-flex gap-4">
                        <div class="text-center">
                            <div class="h2 mb-0"><?= $editors->num_rows ?></div>
                            <small class="text-muted">Available Editors</small>
                        </div>
                        <div class="text-center">
    <?php
    // Initialize average rating
//     $avg_rating = null;
    
//     try {
//         // Prepare statement securely
//         $stmt = $conn->prepare("
//             SELECT AVG(r.rating) as avg_rating 
//             FROM reviews r
//             INNER JOIN projects p ON r.project_id = p.id
//             WHERE p.service_id = ?
//         ");
        
//         // Bind parameter and execute
//         $stmt->bind_param("i", $category_id);
//         $stmt->execute();
        
//         // Get result
//         $result = $stmt->get_result();
//         if ($result && $result->num_rows > 0) {
//             $row = $result->fetch_assoc();
//             $avg_rating = $row['avg_rating'];
//         }
//     } catch (Exception $e) {
//         error_log("Error calculating average rating: " . $e->getMessage());
//     }
//     ?>
    
<!-- //     <div class="h2 mb-0">
//         <?= $avg_rating ? number_format($avg_rating, 1) : 'N/A' ?>
//     </div>
//     <small class="text-muted">Avg. Rating</small>
// </div> -->
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Main Content Section -->
<div class="container py-5">
    <?php if($category_id > 0): ?>
        <!-- Editors Grid -->
        <div class="row g-4">
            <?php while($editor = $editors->fetch_assoc()): ?>
            <div class="col-md-4">
                <div class="editor-card shadow-sm hover-effect">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <div class="editor-avatar">
                                <?= strtoupper(substr(htmlspecialchars($editor['username']), 0, 1)) ?>
                                <div class="availability-dot <?= htmlspecialchars($editor['availability']) ?>"></div>
                            </div>
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($editor['username']) ?></h5>
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <?php
                                    // Get actual rating
                                    $rating = $conn->query("
                                        SELECT AVG(rating) as rating 
                                        FROM reviews 
                                        WHERE project_id IN (
                                            SELECT id FROM projects WHERE editor_id = {$editor['id']}
                                        )
                                    ")->fetch_assoc()['rating'];
                                    $rating = $rating ? round($rating) : 0;
                                    ?>
                                    <div class="rating">
                                        <?php for($i=0; $i<5; $i++): ?>
                                            <i class="fas fa-star <?= $i < $rating ? 'text-warning' : 'text-secondary' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                    <?php
                                    // Get actual project count
                                    $projects = $conn->query("
                                        SELECT COUNT(*) as count 
                                        FROM projects 
                                        WHERE editor_id = {$editor['id']}
                                    ")->fetch_assoc()['count'];
                                    ?>
                                    <small class="text-muted">(<?= $projects ?> projects)</small>
                                </div>
                                <div class="skills-list">
                                    <?php 
                                    $skills = explode(',', $editor['skills']);
                                    foreach(array_slice($skills, 0, 3) as $skill): 
                                    ?>
                                        <span class="skill-badge"><?= htmlspecialchars(trim($skill)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="editor-info mt-4">
                            <div class="row g-2">
                                <div class="col-6">
                                    <div class="d-flex align-items-center gap-2 text-muted">
                                        <i class="fas fa-clock"></i>
                                        <small>Response: 2h</small>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <!-- <div class="d-flex align-items-center gap-2 text-muted">
                                        <i class="fas fa-coins"></i>
                                        <small>$<?= number_format($editor['hourly_rate'], 2) ?>/hour</small>
                                    </div> -->
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-top">
                        <a href="profile.php?id=<?= $editor['id'] ?>" class="btn btn-primary w-100">
                            View Portfolio <i class="fas fa-arrow-right ms-2"></i>
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

    <?php else: ?>
        <!-- All Categories Grid -->
        <div class="row g-4">
            <?php
            $categories = $conn->query("
                SELECT s.*, 
                (SELECT COUNT(DISTINCT ps.professional_id) 
                 FROM professional_services ps 
                 INNER JOIN users u ON ps.professional_id = u.id 
                 WHERE ps.service_id = s.id AND u.role = 'editor') AS editor_count 
                FROM services s
            ");
            
            while($cat = $categories->fetch_assoc()):
            ?>
            <div class="col-md-4 col-lg-3">
                <a href="categories.php?category=<?= $cat['id'] ?>" class="category-card text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm hover-effect">
                        <div class="card-body text-center p-4">
                            <div class="icon-wrapper mb-3">
                                <?php if($cat['name'] == 'Video Editing'): ?>
                                    <i class="fas fa-video fa-2x text-primary"></i>
                                <?php elseif($cat['name'] == 'Photo Editing'): ?>
                                    <i class="fas fa-camera-retro fa-2x text-primary"></i>
                                <?php elseif($cat['name'] == 'Logo Design'): ?>
                                    <i class="fas fa-pen-nib fa-2x text-primary"></i>
                                <?php elseif($cat['name'] == 'Animation'): ?>
                                    <i class="fas fa-film fa-2x text-primary"></i>
                                <?php elseif($cat['name'] == 'Flyer Design'): ?>
                                    <i class="fas fa-palette fa-2x text-primary"></i>
                                <?php else: ?>
                                    <i class="fas fa-lightbulb fa-2x text-primary"></i>
                                <?php endif; ?>
                            </div>
                            <h5 class="mb-2"><?= htmlspecialchars($cat['name']) ?></h5>
                            <p class="text-muted small mb-0"><?= substr(htmlspecialchars($cat['description']), 0, 60) ?>...</p>
                            <div class="mt-3">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-users me-1"></i>
                                    <?= $cat['editor_count'] ?> Editor<?= $cat['editor_count'] != 1 ? 's' : '' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>