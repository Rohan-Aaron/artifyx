<?php
require_once '../includes/db.php';


// Authentication Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch client details
if (isset($_GET['client_id'])) {
    $client_id = intval($_GET['client_id']);
    $sql = "SELECT u.id, u.username, u.email, u.role, p.bio, p.skills, p.portfolio_url, p.availability, p.hourly_rate
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE u.id = $client_id";  // Use $client_id, not $user_id

    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        $client = $result->fetch_assoc();
    } else {
        die("Client not found.");
    }
} else {
    die("Invalid request.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="container mt-4">
    <a href="admin.php" class="btn btn-secondary mb-3">Back to Dashboard</a>

    <h2><?= htmlspecialchars($client['username']) ?>'s Profile</h2>

    <p><strong>Email:</strong> <?= htmlspecialchars($client['email']) ?></p>
    <p><strong>Role:</strong> <?= ucfirst($client['role']) ?></p>

    <?php if (!empty($client['bio'])): ?>
        <p><strong>Bio:</strong> <?= htmlspecialchars($client['bio']) ?></p>
    <?php endif; ?>

    <?php if (!empty($client['skills'])): ?>
        <p><strong>Skills:</strong> <?= htmlspecialchars($client['skills']) ?></p>
    <?php endif; ?>

    <?php if (!empty($client['portfolio_url'])): ?>
        <p><strong>Portfolio:</strong> <a href="<?= htmlspecialchars($client['portfolio_url']) ?>" target="_blank">View</a></p>
    <?php endif; ?>

    <?php if (!empty($client['availability'])): ?>
        <p><strong>Availability:</strong> <?= ucfirst($client['availability']) ?></p>
    <?php endif; ?>

    <?php if (!empty($client['hourly_rate']) && $client['hourly_rate'] > 0): ?>
        <p><strong>Hourly Rate:</strong> $<?= htmlspecialchars($client['hourly_rate']) ?></p>
    <?php endif; ?>
</body>
</html>

<?php $conn->close(); ?>
