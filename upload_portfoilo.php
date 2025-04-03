<?php
session_start();
include 'includes/db.php';

// Check if the user is logged in and is an editor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'editor') {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $conn->real_escape_string($_POST['title']);
    $description = $conn->real_escape_string($_POST['description']);
    $category_id = intval($_POST['category_id']);

    // Handle file upload
    if (isset($_FILES['portfolio_file']) && $_FILES['portfolio_file']['error'] == 0) {
        $upload_dir = 'uploads/portfolios/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = time() . '_' . basename($_FILES['portfolio_file']['name']);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['portfolio_file']['tmp_name'], $file_path)) {
            // Insert portfolio item into the database
            $stmt = $conn->prepare("
                INSERT INTO portfolios (user_id, title, description, file_path, category_id)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("isssi", $user_id, $title, $description, $file_path, $category_id);

            if ($stmt->execute()) {
                $success = "Portfolio item uploaded successfully!";
            } else {
                $error = "Error uploading portfolio item: " . $stmt->error;
            }
        } else {
            $error = "Error uploading file.";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Fetch categories for the dropdown
$categories = $conn->query("SELECT * FROM services");
?>

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <h1 class="mb-4">Upload Portfolio Item</h1>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="mb-3">
            <label for="title" class="form-label">Title</label>
            <input type="text" name="title" class="form-control" required>
        </div>

        <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="4" required></textarea>
        </div>

        <div class="mb-3">
            <label for="category_id" class="form-label">Category</label>
            <select name="category_id" class="form-select" required>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                    <option value="<?= $cat['id'] ?>"><?= $cat['name'] ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="mb-3">
            <label for="portfolio_file" class="form-label">Upload File</label>
            <input type="file" name="portfolio_file" class="form-control" accept=".jpg,.jpeg,.png,.mp4,.pdf" required>
        </div>

        <button type="submit" class="btn btn-primary">Upload</button>
    </form>
</div>

<?php include 'includes/footer.php'; ?>