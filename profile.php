<?php
include 'includes/db.php';

// Get editor ID from URL
$editor_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($editor_id == 0) {
    header("Location: categories.php");
    exit();
}

// Fetch editor details
$editor = $conn->query("
    SELECT u.username, p.bio, p.skills, p.hourly_rate, p.availability
    FROM users u
    JOIN profiles p ON u.id = p.user_id
    WHERE u.id = $editor_id AND u.role = 'editor'
")->fetch_assoc();

if (!$editor) {
    die("Editor not found.");
}

// Fetch portfolio items
$portfolio = $conn->query("
    SELECT * FROM portfolios
    WHERE user_id = $editor_id
    ORDER BY created_at DESC
");

// Handle project request form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['request_project'])) {
    $client_id = $_SESSION['user_id']; // Logged-in client's ID
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $service_id = intval($_POST['service_id']);

    // Insert project request into the database
    $stmt = $conn->prepare("
        INSERT INTO projects (client_id, editor_id, service_id, title, description, status)
        VALUES (?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param("iiiss", $client_id, $editor_id, $service_id, $title, $description);

    if ($stmt->execute()) {
        $success = "Project request sent successfully!";
    } else {
        $error = "Error sending project request: " . $stmt->error;
    }
}

// Fetch services for the dropdown
$services = $conn->query("SELECT * FROM services");
?>

<?php include 'includes/header.php'; ?>

<!-- Custom CSS for Profile Page -->
<style>
    .editor-card,
    .portfolio-item {
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .editor-card:hover,
    .portfolio-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }

    .editor-avatar {
        width: 80px;
        height: 80px;
        background: #6366f1;
        color: white;
        font-size: 2rem;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
    }

    .availability-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        position: absolute;
        bottom: 5px;
        right: 5px;
        border: 2px solid white;
    }

    .availability-dot.available {
        background: #10b981;
    }

    .availability-dot.busy {
        background: #ef4444;
    }

    .availability-dot.available_soon {
        background: #f59e0b;
    }

    .portfolio-item img,
    .portfolio-item video {
        border-radius: 8px;
    }
</style>

<div class="container py-5">
    <div class="row">
        <!-- Editor Info -->
        <div class="col-md-4">
            <div class="card mb-4 editor-card">
                <div class="card-body text-center">
                    <!-- Editor Avatar -->
                    <div class="editor-avatar position-relative mx-auto mb-3">
                        <?= strtoupper(substr($editor['username'], 0, 1)) ?>
                        <div class="availability-dot <?= $editor['availability'] ?>"></div>
                    </div>

                    <!-- Editor Name -->
                    <h2 class="mb-3"><?= $editor['username'] ?></h2>
                    <p class="text-muted"><?= $editor['bio'] ?></p>

                    <!-- Skills -->
                    <div class="mb-3">
                        <h5>Skills</h5>
                        <div class="skills-list">
                            <?php
                            $skills = explode(',', $editor['skills']);
                            foreach ($skills as $skill):
                                ?>
                                <span class="badge bg-primary me-1 mb-1"><?= trim($skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Hourly Rate -->
                    <!-- <div class="mb-3">
                        <h5>Hourly Rate</h5>
                        <p class="lead">$<?= $editor['hourly_rate'] ?>/hour</p>
                    </div> -->

                    <!-- Availability -->
                    <div class="mb-3">
                        <h5>Availability</h5>
                        <p class="lead text-<?= $editor['availability'] == 'available' ? 'success' : 'danger' ?>">
                            <?= ucfirst($editor['availability']) ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Portfolio Items -->
        <div class="col-md-8">
            <h2 class="mb-4">Portfolio</h2>

            <?php if ($portfolio->num_rows > 0): ?>
                <div class="row g-4">
                    <?php while ($item = $portfolio->fetch_assoc()): ?>
                        <div class="col-md-6">
                            <div class="card portfolio-item h-100">
                                <div class="card-body">
                                    <!-- Portfolio Title -->
                                    <h5 class="card-title"><?= $item['title'] ?></h5>
                                    <!-- Portfolio Description -->
                                    <p class="card-text"><?= $item['description'] ?></p>

                                    <!-- Portfolio Media -->
                                    <?php
                                    $file_ext = pathinfo($item['file_path'], PATHINFO_EXTENSION);
                                    if (in_array($file_ext, ['jpg', 'jpeg', 'png'])): ?>
                                        <img src="dashboard/<?= $item['file_path'] ?>" class="img-fluid mb-3" alt="<?= $item['title'] ?>">
                                    <?php elseif ($file_ext == 'mp4'): ?>
                                        <video controls class="w-100 mb-3">
                                            <source src="<?= $item['file_path'] ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php else: ?>
                                        <a href="<?= $item['file_path'] ?>" class="btn btn-primary" download>
                                            <i class="fas fa-download me-2"></i> Download File
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">No portfolio items found.</div>
            <?php endif; ?>
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Request Project Form -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h3 class="mb-4">Request Project</h3>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?= $error ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label">Project Title</label>
                                <input type="text" name="title" class="form-control" required>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Project Description</label>
                                <textarea name="description" class="form-control" rows="4" required></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="service_id" class="form-label">Service</label>
                                <select name="service_id" class="form-select" required>
                                    <?php while ($service = $services->fetch_assoc()): ?>
                                        <option value="<?= $service['id'] ?>"><?= $service['name'] ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <button type="submit" name="request_project" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i> Send Request
                            </button>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-warning">You must be logged in to request a project.</div>
                <a href="login.php" class="btn btn-success"><i class="fas fa-sign-in-alt me-2"></i> Login</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>