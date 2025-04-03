<?php
require_once '../includes/db.php';

// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch editor details
if (isset($_GET['editor_id'])) {
    $editor_id = intval($_GET['editor_id']);

    // Fetch editor's general details
    $sql = "SELECT u.id, u.username, u.email, p.bio, p.skills, p.portfolio_url, p.availability, p.hourly_rate
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE u.id = $editor_id";

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $editor = $result->fetch_assoc();
    } else {
        die("Editor not found.");
    }

    // Fetch editor's portfolios
    $portfolio_sql = "SELECT title, description, file_path FROM portfolios WHERE user_id = $editor_id";
    $portfolio_result = $conn->query($portfolio_sql);

    // Fetch projects assigned to the editor
    $project_sql = "SELECT title, description, status FROM projects WHERE editor_id = $editor_id";
    $project_result = $conn->query($project_sql);

} else {
    die("Invalid request.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editor Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .profile-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .portfolio-item, .project-item {
            background: white;
            border-radius: 8px;
            padding: 15px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="container mt-4">
    <a href="admin.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>

    <div class="row">
        <!-- Profile Section -->
        <div class="col-md-4">
            <div class="profile-card">
                <h3><i class="fas fa-user"></i> <?= htmlspecialchars($editor['username']) ?>'s Profile</h3>
                <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?= htmlspecialchars($editor['email']) ?></p>
                
                <?php if (!empty($editor['bio'])): ?>
                    <p><i class="fas fa-info-circle"></i> <strong>Bio:</strong> <?= htmlspecialchars($editor['bio']) ?></p>
                <?php endif; ?>

                <?php if (!empty($editor['skills'])): ?>
                    <p><i class="fas fa-tools"></i> <strong>Skills:</strong> <?= htmlspecialchars($editor['skills']) ?></p>
                <?php endif; ?>

                <?php if (!empty($editor['portfolio_url'])): ?>
                    <p><i class="fas fa-globe"></i> <strong>Portfolio URL:</strong> 
                        <a href="<?= htmlspecialchars($editor['portfolio_url']) ?>" target="_blank">View Portfolio</a>
                    </p>
                <?php endif; ?>

                <?php if (!empty($editor['availability'])): ?>
                    <p><i class="fas fa-clock"></i> <strong>Availability:</strong> <?= ucfirst($editor['availability']) ?></p>
                <?php endif; ?>

                <?php if (!empty($editor['hourly_rate']) && $editor['hourly_rate'] > 0): ?>
                    <p><i class="fas fa-dollar-sign"></i> <strong>Hourly Rate:</strong> $<?= htmlspecialchars($editor['hourly_rate']) ?>/hr</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Portfolio Section -->
        <div class="col-md-8">
            <h3><i class="fas fa-briefcase"></i> Portfolios</h3>
            <?php if ($portfolio_result->num_rows > 0): ?>
                <div class="row">
                    <?php while ($portfolio = $portfolio_result->fetch_assoc()): ?>
                        <div class="col-md-6 mb-3">
                            <div class="portfolio-item p-3">
                                <h5><i class="fas fa-folder-open"></i> <?= htmlspecialchars($portfolio['title']) ?></h5>
                                <p><?= htmlspecialchars($portfolio['description']) ?></p>
                                <a href="<?= htmlspecialchars($portfolio['file_path']) ?>" target="_blank" class="btn btn-primary btn-sm">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">No portfolios available.</p>
            <?php endif; ?>

            <!-- Projects Section -->
            <h3 class="mt-4"><i class="fas fa-tasks"></i> Projects</h3>
            <?php if ($project_result->num_rows > 0): ?>
                <table class="table table-bordered">
                    <thead class="table-dark">
                        <tr>
                            <th><i class="fas fa-file-alt"></i> Title</th>
                            <th><i class="fas fa-align-left"></i> Description</th>
                            <th><i class="fas fa-flag"></i> Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($project = $project_result->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($project['title']) ?></td>
                                <td><?= htmlspecialchars($project['description']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $project['status'] === 'completed' ? 'success' : ($project['status'] === 'in_progress' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($project['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="text-muted">No projects assigned.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php $conn->close(); ?>
