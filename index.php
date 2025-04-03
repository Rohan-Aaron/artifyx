<?php
include 'includes/db.php';
include 'includes/header.php';

?>

<main class="landing-page">
    <!-- Hero Section -->
    <section class="hero bg-primary text-white py-5">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-4">Transform Your Creative Vision into Reality</h1>
            <p class="lead mb-4">Connect with skilled editors and designers to bring your projects to life</p>
            <div class="cta-buttons">
                <a href="register.php" class="btn btn-light btn-lg px-5 me-3">Get Started</a>
                <a href="#how-it-works" class="btn btn-outline-light btn-lg px-5">Learn More</a>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-5">
        <div class="container">
            <h2 class="text-center mb-5">How Artifyx Works</h2>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card h-100 border-0">
                        <div class="card-body text-center">
                            <div class="step-number">1</div>
                            <h3>Browse Categories</h3>
                            <p>Explore our wide range of creative service categories</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0">
                        <div class="card-body text-center">
                            <div class="step-number">2</div>
                            <h3>Choose Experts</h3>
                            <p>View portfolios and select the perfect editor for your project</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 border-0">
                        <div class="card-body text-center">
                            <div class="step-number">3</div>
                            <h3>Collaborate</h3>
                            <p>Work directly with your chosen professional to complete your vision</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="categories py-5 bg-light">
    <div class="container">
        <div class="text-center mb-5">
            <h2 class="display-5 fw-bold mb-3">Popular Service Categories</h2>
            <p class="lead text-muted">Explore our most sought-after creative services</p>
        </div>
        
        <div class="row g-4">
            <?php
            // Modified query to get editor counts
            $categories = $conn->query("
                SELECT s.*, 
                (SELECT COUNT(DISTINCT ps.professional_id) 
                 FROM professional_services ps 
                 INNER JOIN users u ON ps.professional_id = u.id 
                 WHERE ps.service_id = s.id AND u.role = 'editor') AS editor_count 
                FROM services s 
                LIMIT 6
            ");
            
            while($cat = $categories->fetch_assoc()):
            ?>
            <div class="col-md-4 col-lg-2">
                <a href="categories.php?category=<?= $cat['id'] ?>" class="category-card text-decoration-none">
                    <div class="card h-100 border-0 shadow-sm hover-effect">
                        <div class="card-body text-center p-4">
                            <!-- Category Icon -->
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
                            
                            <!-- Category Name -->
                            <h5 class="mb-2"><?= $cat['name'] ?></h5>
                            
                            <!-- Category Description -->
                            <p class="text-muted small mb-0"><?= substr($cat['description'], 0, 60) ?>...</p>
                            
                            <!-- Actual Editor Count -->
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
        
        <!-- View All Button -->
        <div class="text-center mt-5">
            <a href="categories.php" class="btn btn-outline-primary btn-lg">
                View All Categories <i class="fas fa-arrow-right ms-2"></i>
            </a>
        </div>
    </div>
</section>
</main>

<?php include 'includes/footer.php'; ?>