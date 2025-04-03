<?php
include '../includes/db.php';

// Redirect if not logged in or not an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Fetch counts
$total_clients = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role = 'client'")->fetch_assoc()['count'];
$total_editors = $conn->query("SELECT COUNT(*) AS count FROM users WHERE role = 'editor'")->fetch_assoc()['count'];

// Fetch data
$clients = $conn->query("SELECT * FROM users WHERE role = 'client'");
$editors = $conn->query("SELECT * FROM users WHERE role = 'editor'");
$projects = $conn->query("
    SELECT p.*, s.name AS service_name, u1.username AS client_name, u2.username AS editor_name
    FROM projects p
    JOIN services s ON p.service_id = s.id
    JOIN users u1 ON p.client_id = u1.id
    JOIN users u2 ON p.editor_id = u2.id
");
$messages = $conn->query("
    SELECT m.*, u1.username AS sender_name, u2.username AS receiver_name
    FROM messages m
    JOIN users u1 ON m.sender_id = u1.id
    JOIN users u2 ON m.receiver_id = u2.id
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body { display: flex; }
        #sidebar { width: 250px; height: 100vh; background: #343a40; color: white; padding: 15px; }
        #sidebar a { color: white; text-decoration: none; display: block; padding: 10px; }
        #sidebar a:hover { background: #495057; border-radius: 5px; }
        .content { flex-grow: 1; padding: 20px; }
        @media (max-width: 768px) { #sidebar { display: none; } }
    </style>
</head>
<body>

<!-- Sidebar -->
<div id="sidebar">
    <h4 class="text-center">Admin Panel</h4>
    <hr>
    <a href="#" onclick="showTab('dashboard')"><i class="bi bi-house-door"></i> Dashboard</a>
    <a href="#" onclick="showTab('clients')"><i class="bi bi-people"></i> Clients (<?= $total_clients ?>)</a>
    <a href="#" onclick="showTab('editors')"><i class="bi bi-pencil-square"></i> Editors (<?= $total_editors ?>)</a>
    <a href="#" onclick="showTab('projects')"><i class="bi bi-list-task"></i> Projects</a>
    <hr>
    <a href="../logout.php" class="btn btn-danger w-100">Logout</a>
</div>

<!-- Main Content -->
<div class="content">
    <button class="btn btn-dark d-md-none mb-3" id="toggleSidebar">
        <i class="bi bi-list"></i> Menu
    </button>

    <!-- Dashboard -->
    <div id="dashboard">
        <h1>Admin Dashboard</h1>
        <div class="row">
            <div class="col-md-4">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Clients</h5>
                        <p class="card-text fs-3"><?= $total_clients ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Total Editors</h5>
                        <p class="card-text fs-3"><?= $total_editors ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Clients -->
    <div id="clients" style="display: none;">
    <h2>Clients</h2>
    <table class="table">
        <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Action</th></tr></thead>
        <tbody>
            <?php while ($client = $clients->fetch_assoc()): ?>
                <tr>
                    <td><?= $client['id'] ?></td>
                    <td><?= $client['username'] ?></td>
                    <td><?= $client['email'] ?></td>
                    <td>
                    <button class="btn btn-info btn-sm" onclick="viewClient(<?= $client['id'] ?>)">
    View
</button>                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

    <!-- Editors -->
    <div id="editors" style="display: none;">
    <h2>Editors</h2>
    <table class="table">
        <thead><tr><th>ID</th><th>Username</th><th>Email</th><th>Action</th></tr></thead>
        <tbody>
            <?php while ($editor = $editors->fetch_assoc()): ?>
                <tr>
                    <td><?= $editor['id'] ?></td>
                    <td><?= $editor['username'] ?></td>
                    <td><?= $editor['email'] ?></td>
                    <td>
                    <button class="btn btn-info btn-sm" onclick="viewEditor(<?= $editor['id'] ?>)">
    View
</button>                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

    <!-- Projects -->
    <div id="projects" style="display: none;">
        <h2>Projects</h2>
        <div class="row">
            <?php while ($project = $projects->fetch_assoc()): ?>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <h5><?= $project['title'] ?></h5>
                            <p><?= $project['description'] ?></p>
                            <p class="text-muted">Service: <?= $project['service_name'] ?></p>
                            <p class="text-muted">Client: <?= $project['client_name'] ?></p>
                            <p class="text-muted">Editor: <?= $project['editor_name'] ?></p>
                            <p class="text-muted">Status: <span class="badge bg-info"><?= ucfirst($project['status']) ?></span></p>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- Messages -->

</div>

<script>
function showTab(tab) {
    document.querySelectorAll('.content > div').forEach(div => div.style.display = 'none');
    document.getElementById(tab).style.display = 'block';
}
document.getElementById('toggleSidebar').addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('d-none');
});

function viewClient(clientId) {
    window.location.href = "client_details.php?client_id=" + clientId;
}
function viewEditor(editorId) {
    window.location.href = "editor_details.php?editor_id=" + editorId;
}
</script>

</body>
</html>
