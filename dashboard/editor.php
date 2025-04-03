<?php
require_once '../includes/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'editor') {
    header("Location: ../login.php");
    exit();
}

$editor_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Database fetch functions
function fetchServices($conn)
{
    $stmt = $conn->prepare("SELECT id, name FROM services");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function fetchProfile($conn, $editor_id)
{
    $stmt = $conn->prepare("
        SELECT users.email, profiles.bio, profiles.skills, 
               profiles.hourly_rate, profiles.availability,
               (SELECT GROUP_CONCAT(service_id) 
                FROM professional_services 
                WHERE professional_id = ?) AS selected_services
        FROM users 
        JOIN profiles ON users.id = profiles.user_id 
        WHERE users.id = ?
    ");
    $stmt->bind_param("ii", $editor_id, $editor_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    try {
        // Validate and sanitize inputs
        $bio = $conn->real_escape_string($_POST['bio'] ?? '');
        $skills = $conn->real_escape_string($_POST['skills'] ?? '');
        $hourly_rate = (float)($_POST['hourly_rate'] ?? 0);
        $availability = in_array($_POST['availability'] ?? '', ['available', 'busy', 'available_soon']) 
                      ? $_POST['availability'] 
                      : 'available';

        $conn->begin_transaction();

        // Use UPSERT (Update or Insert) operation
        $stmt = $conn->prepare("
            INSERT INTO profiles (user_id, bio, skills, hourly_rate, availability)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                bio = VALUES(bio),
                skills = VALUES(skills),
                hourly_rate = VALUES(hourly_rate),
                availability = VALUES(availability)
        ");
        $stmt->bind_param("issss", $editor_id, $bio, $skills, $hourly_rate, $availability);
        $stmt->execute();

        // Handle service categories
        $selected_services = array_map('intval', $_POST['services'] ?? []);
        $valid_services = array_column(fetchServices($conn), 'id');

        $stmt = $conn->prepare("DELETE FROM professional_services WHERE professional_id = ?");
        $stmt->bind_param("i", $editor_id);
        $stmt->execute();

        if (!empty($selected_services)) {
            $valid_selected = array_intersect($selected_services, $valid_services);
            $stmt = $conn->prepare("INSERT INTO professional_services (professional_id, service_id) VALUES (?, ?)");
            foreach ($valid_selected as $service_id) {
                $stmt->bind_param("ii", $editor_id, $service_id);
                $stmt->execute();
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Profile updated successfully!";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}
// Handle Project Status Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_project_status'])) {
    try {
        $project_id = intval($_POST['project_id']);
        $status = $conn->real_escape_string($_POST['status']);

        $allowed_statuses = ['in_progress', 'completed', 'rejected'];
        if (!in_array($status, $allowed_statuses)) {
            throw new Exception("Invalid status value!");
        }

        $stmt = $conn->prepare("UPDATE projects SET status=? WHERE id=? AND editor_id=?");
        $stmt->bind_param("sii", $status, $project_id, $editor_id);

        if ($stmt->execute()) {
            $_SESSION['success'] = "Project status updated successfully!";
        } else {
            throw new Exception("Error updating project status: " . $stmt->error);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}
// Handle Portfolio Upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['upload_portfolio'])) {
    try {
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description']);

        $upload_dir = "uploads/portfolio/";
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = uniqid() . '_' . basename($_FILES['file']['name']);
        $target_file = $upload_dir . $file_name;
        $file_type = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Added video formats: mp4, mov, avi, wmv, flv
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'mp4', 'mov', 'avi', 'wmv', 'flv'];
        if (!in_array($file_type, $allowed_types)) {
            throw new Exception("Only JPG, PNG, PDF, DOC, MP4, MOV, AVI, WMV, FLV files are allowed.");
        }

        // Increased file size limit to 50MB (50,000,000 bytes)
        if ($_FILES['file']['size'] > 50000000) {
            throw new Exception("File too large (max 50MB)");
        }

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $target_file)) {
            throw new Exception("Error uploading file");
        }

        $stmt = $conn->prepare("INSERT INTO portfolios (user_id, title, description, file_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $editor_id, $title, $description, $target_file);

        if (!$stmt->execute()) {
            throw new Exception("Error saving portfolio: " . $stmt->error);
        }

        $_SESSION['success'] = "Portfolio item uploaded!";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}
// Handle Portfolio Delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_portfolio'])) {
    try {
        $portfolio_id = intval($_POST['portfolio_id']);
        $stmt = $conn->prepare("SELECT file_path FROM portfolios WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $portfolio_id, $editor_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        if ($result && file_exists($result['file_path'])) {
            unlink($result['file_path']);
        }

        $stmt = $conn->prepare("DELETE FROM portfolios WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $portfolio_id, $editor_id);

        if (!$stmt->execute()) {
            throw new Exception("Error deleting portfolio item: " . $stmt->error);
        }

        $_SESSION['success'] = "Portfolio item deleted!";
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}

// Fetch Data
$profile = [];
$projects = [];
$portfolio = [];
$messages = [];

try {
    $services = fetchServices($conn);
    $profile = fetchProfile($conn, $editor_id);
    // In the data fetching section:
    $selected_services = isset($profile['selected_services']) && $profile['selected_services'] !== ''
        ? array_map('intval', explode(',', $profile['selected_services']))
        : [];
   
        
        $stmt = $conn->prepare("
SELECT 
    users.email,
    COALESCE(profiles.bio, 'No bio added yet') AS bio,
    COALESCE(profiles.skills, 'No skills listed') AS skills,
    COALESCE(profiles.hourly_rate, 0) AS hourly_rate,
    COALESCE(profiles.availability, 'available') AS availability,
    (
        SELECT GROUP_CONCAT(service_id) 
        FROM professional_services 
        WHERE professional_id = users.id
    ) AS selected_services
FROM users 
LEFT JOIN profiles ON users.id = profiles.user_id 
WHERE users.id = ?
");
$stmt->bind_param("i", $editor_id);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc() ?? [];
$selected_services = $profile['selected_services'] ? explode(',', $profile['selected_services']) : [];

    // Fetch projects
    $stmt = $conn->prepare("
        SELECT p.*, s.name AS service_name, u.username AS client_name
        FROM projects p
        JOIN services s ON p.service_id = s.id
        JOIN users u ON p.client_id = u.id
        WHERE p.editor_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->bind_param("i", $editor_id);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Portfolio
    $stmt = $conn->prepare("
        SELECT * FROM portfolios 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->bind_param("i", $editor_id);
    $stmt->execute();
    $portfolio = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Messages
    $stmt = $conn->prepare("
        SELECT m.*, u.username AS sender_name 
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.project_id IN (
            SELECT id FROM projects WHERE editor_id = ?
        )
        ORDER BY m.sent_at DESC
    ");
    $stmt->bind_param("i", $editor_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

} catch (Exception $e) {
    $error = $e->getMessage();
}

// Handle session messages
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
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
    <title>Editor Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #34495e;
            --accent-color: #3498db;
            --light-bg: #f8f9fa;
        }

        body {
            background-color: var(--light-bg);
        }

        .dashboard-sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            background: linear-gradient(180deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            transition: transform 0.3s;
            z-index: 1000;
            box-shadow: 2px 0 15px rgba(0, 0, 0, 0.1);
        }

        .dashboard-main {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s;
        }

        .nav-link {
            color: #ecf0f1 !important;
            padding: 15px 20px !important;
            border-radius: 8px;
            margin: 5px 0;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-link.active,
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
            text-decoration: none;
        }

        .dashboard-section {
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .dashboard-section.active {
            display: block;
            opacity: 1;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .card-header {
            background: var(--primary-color) !important;
            border-radius: 10px 10px 0 0 !important;
            padding: 1.5rem;
        }

        .portfolio-item {
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
        }

        .portfolio-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            padding: 0;
        }

        .file-preview {
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }

        @media (max-width: 768px) {
            .dashboard-sidebar {
                transform: translateX(-100%);
                width: 80%;
            }

            .dashboard-main {
                margin-left: 0;
                padding: 20px;
            }

            .mobile-menu-active .dashboard-sidebar {
                transform: translateX(0);
            }

            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <!-- Mobile Menu Toggle -->
    <button class="btn btn-dark mobile-menu-toggle" id="mobileMenuToggle">
        <i class="fas fa-bars"></i>
    </button>

    <div class="dashboard-sidebar">
        <h3 class="mb-4 text-center"><i class="fas fa-user-edit me-2"></i>Editor Dashboard</h3>
        <nav class="nav flex-column">
            <a class="nav-link" href="../index.php">
                <i class="fas fa-home"></i> Home
            </a>
            <a class="nav-link active" href="#profile">
                <i class="fas fa-user"></i> Profile
            </a>
            <a class="nav-link" href="#projects">
                <i class="fas fa-tasks"></i> Projects
            </a>
            <a class="nav-link" href="#portfolio">
                <i class="fas fa-briefcase"></i> Portfolio
            </a>
            <div class="mt-auto">
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </div>

    <div class="dashboard-main">
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Profile Section -->
<section id="profile" class="dashboard-section active">
    <!-- Profile View -->
    <!-- Profile View -->
<div class="profile-view card shadow-lg mb-4" id="profileView">
    <div class="card-header text-white">
        <h2 class="mb-0"><i class="fas fa-user me-2"></i>Your Profile</h2>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-primary mb-3"><i class="fas fa-info-circle me-2"></i>Bio</h5>
                <p class="lead"><?= htmlspecialchars($profile['bio']) ?></p>
            </div>
            <div class="col-md-6">
                <h5 class="text-primary mb-3"><i class="fas fa-tools me-2"></i>Details</h5>
                <div class="mb-4">
                    <p class="mb-1"><strong>Skills:</strong></p>
                    <p><?= htmlspecialchars($profile['skills']) ?></p>
                </div>
                <div class="mb-4">
                    <p class="mb-1"><strong>Hourly Rate:</strong></p>
                    <p>$<?= number_format($profile['hourly_rate'], 2) ?></p>
                </div>
                <div class="mb-4">
                    <p class="mb-1"><strong>Availability:</strong></p>
                    <span class="badge bg-<?= match(strtolower($profile['availability'])) {
                        'available' => 'success',
                        'busy' => 'danger',
                        'available_soon' => 'warning',
                        default => 'secondary'
                    } ?>">
                        <?= ucfirst(str_replace('_', ' ', $profile['availability'])) ?>
                    </span>
                </div>
            </div>
            <div class="col-12 mt-4">
                <h5 class="text-primary mb-3"><i class="fas fa-cogs me-2"></i>Service Categories</h5>
                <div class="row g-2">
                    <?php if (!empty($selected_services)): ?>
                        <?php foreach ($services as $service): 
                            if (in_array($service['id'], $selected_services)): ?>
                                <div class="col-auto">
                                    <span class="badge bg-primary rounded-pill">
                                        <?= htmlspecialchars($service['name']) ?>
                                    </span>
                                </div>
                            <?php endif;
                        endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <p class="text-muted">No services selected yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-12 mt-4 text-center">
                <button class="btn btn-primary btn-lg" onclick="toggleProfileEdit(true)">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </button>
            </div>
        </div>
    </div>
</div>

    <!-- Profile Edit Form -->
    <!-- Profile Edit Form -->
<div class="profile-edit card shadow-lg mb-4" id="profileEdit" style="<?= !empty($error) ? 'display:block' : 'display:none' ?>">
    <div class="card-header text-white">
        <h2 class="mb-0"><i class="fas fa-user-edit me-2"></i>Edit Profile</h2>
    </div>
    <div class="card-body">
        <form method="POST">
            
            
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Bio</label>
                        <textarea name="bio" class="form-control" rows="4" required><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Skills (comma separated)</label>
                        <input type="text" name="skills" class="form-control" 
                            value="<?= htmlspecialchars($profile['skills'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Hourly Rate ($)</label>
                        <input type="number" name="hourly_rate" class="form-control" 
                            step="0.01" min="0" value="<?= htmlspecialchars($profile['hourly_rate'] ?? 0) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Availability</label>
                        <select name="availability" class="form-select" required>
                            <option value="available" <?= ($profile['availability'] ?? 'available') == 'available' ? 'selected' : '' ?>>Available</option>
                            <option value="busy" <?= ($profile['availability'] ?? 'available') == 'busy' ? 'selected' : '' ?>>Busy</option>
                            <option value="available_soon" <?= ($profile['availability'] ?? 'available') == 'available_soon' ? 'selected' : '' ?>>Available Soon</option>
                        </select>
                    </div>
                </div>

                <!-- Service Categories Section -->
                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">Service Categories</label>
                        <div class="row g-2">
                            <?php foreach ($services as $service): 
                                $is_checked = in_array($service['id'], $selected_services);
                                $service_id = (int) $service['id'];
                            ?>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="services[]"
                                        value="<?= $service_id ?>" id="service-<?= $service_id ?>"
                                        <?= $is_checked ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="service-<?= $service_id ?>">
                                        <?= htmlspecialchars($service['name']) ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-outline-secondary btn-lg" 
                                onclick="toggleProfileEdit(false)">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="update_profile" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
</section>

        <!-- Projects Section -->
        <section id="projects" class="dashboard-section">
            <div class="card shadow-lg mb-4">
                <div class="card-header text-white">
                    <h2 class="mb-0"><i class="fas fa-tasks me-2"></i>Project Requests</h2>
                </div>
                <div class="card-body">
                    <?php if (!empty($projects)): ?>
                        <div class="row g-4">
                            <?php foreach ($projects as $project): ?>
                                <div class="col-12 col-lg-6">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-body">
                                            <h5 class="card-title"><?= htmlspecialchars($project['title']) ?></h5>
                                            <p class="card-text text-muted"><?= htmlspecialchars($project['description']) ?></p>

                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <span class="badge bg-<?= match ($project['status']) {
                                                    'pending' => 'warning',
                                                    'in_progress' => 'primary',
                                                    'completed' => 'success',
                                                    'rejected' => 'danger',
                                                    default => 'secondary'
                                                } ?> status-badge">
                                                    <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                                                </span>
                                                <small class="text-muted">
                                                    <?= date('M d, Y', strtotime($project['created_at'])) ?>
                                                </small>
                                            </div>

                                            <?php if ($project['status'] !== 'completed'): ?>
                                                <form method="POST" class="d-flex gap-2 align-items-center">
                                                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                                    <select name="status" class="form-select w-auto">
                                                        <option value="in_progress" <?= $project['status'] == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                                        <option value="completed" <?= $project['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                                        <option value="rejected" <?= $project['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                    </select>
                                                    <button type="submit" name="update_project_status" class="btn btn-primary">
                                                        <i class="fas fa-sync-alt me-2"></i>Update Status
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <p class="text-success"><i class="fas fa-check-circle"></i> Project Completed</p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">No project requests found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Portfolio Section -->
        <section id="portfolio" class="dashboard-section">
            <div class="card shadow-lg mb-4">
                <div class="card-header text-white">
                    <h2 class="mb-0"><i class="fas fa-briefcase me-2"></i>Portfolio Management</h2>
                </div>
                <div class="card-body">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-upload me-2"></i>Upload New Work</h5>
                            <form method="POST" enctype="multipart/form-data">
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <div class="mb-3">
                                            <label class="form-label">Service Categories</label>
                                            <div class="row g-2">
                                                <?php
                                                // Get all available services
                                                $services = $conn->query("SELECT id, name FROM services")->fetch_all(MYSQLI_ASSOC);
                                                foreach ($services as $service):
                                                    ?>
                                                    <div class="col-md-4">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="checkbox"
                                                                name="services[]" value="<?= $service['id'] ?>"
                                                                <?= in_array($service['id'], $selected_services) ? 'checked' : '' ?>>
                                                            <label class="form-check-label">
                                                                <?= htmlspecialchars($service['name']) ?>
                                                            </label>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="title" class="form-control" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">File</label>
                                        <input type="file" name="file" class="form-control" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label">Description</label>
                                        <textarea name="description" class="form-control" rows="3"></textarea>
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" name="upload_portfolio" class="btn btn-primary">
                                            <i class="fas fa-upload me-2"></i>Upload Item
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <h4 class="mb-4"><i class="fas fa-folder-open me-2"></i>Your Portfolio Items</h4>
                    <div class="row g-4">
                        <?php foreach ($portfolio as $item): ?>
                            <div class="col-12 col-md-6 col-lg-4">
                                <div class="card portfolio-item h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <h6><?= htmlspecialchars($item['title']) ?></h6>
                                            <form method="POST" class="ms-2">
                                                <input type="hidden" name="portfolio_id" value="<?= $item['id'] ?>">
                                                <button type="submit" name="delete_portfolio" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Are you sure you want to delete this item?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                        <p class="text-muted small"><?= htmlspecialchars($item['description']) ?></p>
                                        <?php if (in_array(pathinfo($item['file_path'], PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png'])): ?>
                                            <img src="<?= htmlspecialchars($item['file_path']) ?>"
                                                class="file-preview rounded mb-2">
                                        <?php else: ?>
                                            <div class="alert alert-info mb-2">
                                                <i class="fas fa-file me-2"></i>
                                                <a href="<?= htmlspecialchars($item['file_path']) ?>" target="_blank"
                                                    class="text-decoration-none">
                                                    Download File
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                        <small class="text-muted">
                                            Uploaded: <?= date('M d, Y', strtotime($item['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Mobile menu toggle
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            mobileMenuToggle.addEventListener('click', () => {
                document.body.classList.toggle('mobile-menu-active');
            });

            // Navigation handling
            function handleNavigation() {
                const hash = window.location.hash || '#profile';
                const sections = document.querySelectorAll('.dashboard-section');
                const links = document.querySelectorAll('.nav-link');

                sections.forEach(section => section.classList.remove('active'));
                links.forEach(link => link.classList.remove('active'));

                const activeSection = document.querySelector(hash);
                const activeLink = document.querySelector(`[href="${hash}"]`);

                if (activeSection) activeSection.classList.add('active');
                if (activeLink) activeLink.classList.add('active');

                if (window.innerWidth < 768) {
                    document.body.classList.remove('mobile-menu-active');
                }
            }

            // Initial setup
            handleNavigation();

            // Handle hash changes
            window.addEventListener('hashchange', handleNavigation);

            // Handle nav link clicks
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', function (e) {
                    if (this.hash) {
                        e.preventDefault();
                        window.location.hash = this.hash;
                        handleNavigation();
                    }
                });
            });

            // Close menu when clicking outside on mobile
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 768 &&
                    !e.target.closest('.dashboard-sidebar') &&
                    !e.target.closest('#mobileMenuToggle')) {
                    document.body.classList.remove('mobile-menu-active');
                }
            });
        });
        function toggleProfileEdit(showEdit) {
    const view = document.getElementById('profileView');
    const edit = document.getElementById('profileEdit');
    
    if (showEdit) {
        view.style.display = 'none';
        edit.style.display = 'block';
        window.scrollTo({ top: edit.offsetTop - 20, behavior: 'smooth' });
    } else {
        view.style.display = 'block';
        edit.style.display = 'none';
        window.scrollTo({ top: view.offsetTop - 20, behavior: 'smooth' });
    }
}
    </script>
</body>

</html>