<?php
require_once '../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$client_id = $_SESSION['user_id'];
$error = '';
$success = '';

try {
    // Handle Profile Update
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
        $bio = $conn->real_escape_string($_POST['bio']);

        // UPSERT profile data
        $stmt = $conn->prepare("
        UPDATE profiles SET bio = ? WHERE user_id = ?;
        ");
        $stmt->bind_param("si", $bio, $client_id);

        if (!$stmt->execute()) {
            throw new Exception("Error updating profile: " . $stmt->error);
        }

        $_SESSION['success'] = "Profile updated successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "#profile");
        exit();
    }

    // Handle Review Submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_review'])) {
        $project_id = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
        $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 5]
        ]);
        $comment = $conn->real_escape_string($_POST['comment']);

        // Verify project belongs to client
        $check_stmt = $conn->prepare("SELECT id FROM projects 
                                        WHERE id = ? AND client_id = ? AND status = 'completed'");
        $check_stmt->bind_param("ii", $project_id, $client_id);
        $check_stmt->execute();

        if (!$check_stmt->get_result()->num_rows) {
            throw new Exception("Invalid project or project not completed");
        }

        $stmt = $conn->prepare("INSERT INTO reviews (project_id, rating, comment)
                                    VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $project_id, $rating, $comment);

        if (!$stmt->execute()) {
            throw new Exception("Error submitting review: " . $stmt->error);
        }

        $_SESSION['success'] = "Review submitted successfully!";
        header("Location: " . $_SERVER['PHP_SELF'] . "#projects");
        exit();
    }


    // Fetch Data
    $profile = [];
    $projects = [];

    // Profile Data
    $stmt = $conn->prepare("
        SELECT users.email, users.username, profiles.bio 
        FROM users 
        LEFT JOIN profiles ON users.id = profiles.user_id 
        WHERE users.id = ?
    ");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    // Projects Data
    $stmt = $conn->prepare("
    SELECT p.*, s.name AS service_name, u.username AS editor_name,
           r.rating, r.comment, r.created_at AS review_date
    FROM projects p
    LEFT JOIN services s ON p.service_id = s.id
    LEFT JOIN users u ON p.editor_id = u.id
    LEFT JOIN reviews r ON p.id = r.project_id
    WHERE p.client_id = ?
    ORDER BY p.created_at DESC
");
    $stmt->bind_param("i", $client_id);
    $stmt->execute();
    $projects_result = $stmt->get_result();
    $projects = [];
    while ($row = $projects_result->fetch_assoc()) {
        $projects[] = $row;
    }
    $stmt->close();

} catch (Exception $e) {
    $error = $e->getMessage();
    error_log("Dashboard Error: " . $e->getMessage());
}

// Handle session messages
if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #60a5fa;
        }

        .dashboard-sidebar {
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            width: 280px;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
        }

        .dashboard-main {
            margin-left: 280px;
            min-height: 100vh;
            padding: 30px;
            background-color: #f8fafc;
        }

        .nav-link {
            color: #e2e8f0 !important;
            padding: 15px 25px !important;
            transition: all 0.3s;
            border-radius: 8px;
            margin: 4px 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-link:hover,
        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }

        .avatar-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-color);
            font-size: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .project-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            border-radius: 12px;
        }

        .project-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .dashboard-sidebar {
                position: fixed;
                width: 100%;
                height: 100vh;
                z-index: 1000;
                display: none;
            }

            .dashboard-sidebar.active {
                display: block;
            }

            .dashboard-main {
                margin-left: 0;
                padding: 20px;
            }

            .avatar-circle {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <div class="container-fluid">
        <!-- Mobile Navigation -->
        <nav class="navbar navbar-dark bg-primary d-md-none">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#sidebarCollapse">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <span class="navbar-brand">Client Dashboard</span>
            </div>
        </nav>

        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 dashboard-sidebar collapse d-md-block" id="sidebarCollapse">
                <div class="p-3 h-100 d-flex flex-column">
                    <h3 class="my-4 text-center d-none d-md-block">
                        <i class="fas fa-user-tie me-2"></i>Dashboard
                    </h3>
                    <nav class="nav flex-column flex-grow-1">
                        <a class="nav-link" href="../index.php">
                            <i class="fas fa-home me-2"></i>Home
                        </a>
                        <a class="nav-link" href="#profile">
                            <i class="fas fa-user"></i>Profile
                        </a>
                        <a class="nav-link" href="#projects">
                            <i class="fas fa-project-diagram"></i>Projects
                        </a>
                        <div class="mt-auto">
                            <a class="nav-link" href="../logout.php">
                                <i class="fas fa-sign-out-alt"></i>Logout
                            </a>
                        </div>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 dashboard-main">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?= $success ?></div>
                <?php endif; ?>

                <!-- View Profile Section -->
                <section id="profile">
                    <div class="card border-0 shadow-lg mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-start mb-4">
                                <div class="text-center text-md-start mb-4 mb-md-0">
                                    <div class="avatar-circle mb-3">
                                        <?= strtoupper(substr(htmlspecialchars($profile['username'] ?? ''), 0, 1)) ?>
                                    </div>
                                    <h4 class="mt-3 fw-bold"><?= htmlspecialchars($profile['username'] ?? '') ?></h4>
                                    <p class="text-muted mb-0"><?= htmlspecialchars($profile['email'] ?? '') ?></p>
                                </div>
                                <a href="#edit-profile" class="btn btn-primary btn-lg align-self-end">
                                    <i class="fas fa-edit me-2"></i>Edit Profile
                                </a>
                            </div>

                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="card bg-light border-0">
                                        <div class="card-body">
                                            <h5 class="card-title text-primary mb-3">
                                                <i class="fas fa-info-circle me-2"></i>Bio
                                            </h5>
                                            <p class="card-text lead">
                                                <?= nl2br(htmlspecialchars($profile['bio'] ?? 'No bio added yet')) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Edit Profile Section -->
                <section id="edit-profile">
                    <div class="card border-0 shadow-lg mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h2 class="fw-bold text-primary">
                                    <i class="fas fa-user-edit me-2"></i>Edit Profile
                                </h2>
                                <a href="#profile" class="btn btn-outline-secondary btn-lg">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>

                            <form method="POST" action="client.php#profile">
                                <div class="row g-4">
                                    <div class="col-12">
                                        <div class="form-floating">
                                            <textarea name="bio" class="form-control" style="height: 150px"
                                                placeholder="Enter your bio" maxlength="1000"
                                                required><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                                            <label>Bio</label>
                                        </div>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="submit" name="update_profile"
                                            class="btn btn-primary btn-lg px-5 py-3">
                                            <i class="fas fa-save me-2"></i>Save Changes
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>

                 <!-- Projects Section -->
                 <section id="projects">
                    <div class="card border-0 shadow-lg">
                        <div class="card-body p-4">
                            <h2 class="mb-4 fw-bold text-primary">
                                <i class="fas fa-folder-open me-2"></i>Your Projects
                            </h2>
                            
                            <?php if (!empty($projects)): ?>
                                <div class="row g-4">
                                    <?php foreach ($projects as $project): ?>
                                        <div class="col-12 col-md-6 col-lg-4">
                                            <div class="project-card card h-100 border-0">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                                        <h5 class="card-title fw-bold">
                                                            <?= htmlspecialchars($project['title']) ?>
                                                        </h5>
                                                        <span class="status-badge bg-<?= match ($project['status']) {
                                                            'pending' => 'warning',
                                                            'in_progress' => 'primary',
                                                            'completed' => 'success',
                                                            default => 'secondary'
                                                        } ?> text-white">
                                                            <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                                                        </span>
                                                    </div>

                                                    <p class="card-text text-muted mb-3">
                                                        <?= htmlspecialchars($project['description']) ?>
                                                    </p>

                                                    <div class="project-meta mb-3">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <i class="fas fa-cog me-2 text-primary"></i>
                                                            <span class="small">
                                                                <?= htmlspecialchars($project['service_name']) ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <?php if ($project['editor_name']): ?>
                                                            <div class="d-flex align-items-center mb-2">
                                                                <i class="fas fa-user-edit me-2 text-primary"></i>
                                                                <span class="small">
                                                                    <?= htmlspecialchars($project['editor_name']) ?>
                                                                </span>
                                                            </div>
                                                        <?php endif; ?>

                                                       
                                                    </div>
                                                </div>

                                                <?php if ($project['status'] === 'completed'): ?>
                                                    <div class="card-footer bg-transparent border-top">
                                                        <?php if (empty($project['rating'])): ?>
                                                            <!-- Review Form -->
                                                            <form method="POST" class="review-form">
                                                                <input type="hidden" name="project_id" 
                                                                       value="<?= $project['id'] ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Rating</label>
                                                                    <div class="star-rating">
                                                                        <?php for ($i = 5; $i >= 1; $i--): ?>
                                                                            <input type="radio" id="rating-<?= $project['id'] ?>-<?= $i ?>" 
                                                                                   name="rating" value="<?= $i ?>" required>
                                                                            <label for="rating-<?= $project['id'] ?>-<?= $i ?>"><i class="fas fa-star"></i></label>
                                                                        <?php endfor; ?>
                                                                    </div>
                                                                </div>
                                                                
                                                                <div class="mb-3">
                                                                    <label class="form-label">Review</label>
                                                                    <textarea name="comment" class="form-control" 
                                                                              rows="3" required
                                                                              placeholder="Share your experience..."></textarea>
                                                                </div>
                                                                
                                                                <button type="submit" name="submit_review" 
                                                                        class="btn btn-success w-100">
                                                                    <i class="fas fa-star me-2"></i>Submit Review
                                                                </button>
                                                            </form>
                                                        <?php else: ?>
                                                            <!-- Display Existing Review -->
                                                            <div class="review-card">
                                                                <div class="d-flex justify-content-between align-items-center mb-2">
                                                                    <div class="rating-stars">
                                                                        <?php for ($i = 0; $i < 5; $i++): ?>
                                                                            <i class="fas fa-star <?= $i < $project['rating'] ? 'text-warning' : 'text-secondary' ?>"></i>
                                                                        <?php endfor; ?>
                                                                    </div>
                                                                    <small class="text-muted">
                                                                        <?= date('M j, Y', strtotime($project['created_at'])) ?>
                                                                    </small>
                                                                </div>
                                                                <p class="mb-0"><?= htmlspecialchars($project['comment']) ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    No projects found. Start by creating a new project request.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Manage section visibility based on hash
            function manageSections() {
                const hash = window.location.hash || '#profile';
                document.querySelectorAll('section').forEach(section => {
                    section.style.display = section.id === hash.substring(1) ? 'block' : 'none';
                });

                // Update active nav links
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.toggle('active', link.getAttribute('href') === hash);
                });
            }

            // Smooth scroll and hash update
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const href = this.getAttribute('href');
                    const target = document.querySelector(href);
                    if (target) {
                        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        history.pushState(null, null, href);
                        manageSections();
                    }
                });
            });

            // Initial section setup
            manageSections();

            // Handle browser navigation
            window.addEventListener('popstate', manageSections);

            // Mobile menu toggle
            const sidebar = document.getElementById('sidebarCollapse');
            const sidebarInstance = new bootstrap.Collapse(sidebar, { toggle: false });
            document.querySelector('.navbar-toggler').addEventListener('click', () => {
                sidebarInstance.toggle();
            });
        });

        document.querySelectorAll('.star-rating').forEach(ratingContainer => {
            const stars = ratingContainer.querySelectorAll('input[type="radio"]');
            stars.forEach((star, index) => {
                star.addEventListener('change', () => {
                    stars.forEach((s, i) => {
                        s.nextElementSibling.style.color = i <= index ? '#ffc107' : '#e4e5e9';
                    });
                });
            });
        });
    </script>
</body>

</html>