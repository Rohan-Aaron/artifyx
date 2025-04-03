<?php
require_once 'includes/db.php';
require_once 'includes/auth_functions.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $conn->real_escape_string($_POST['username']);
    $email = $conn->real_escape_string($_POST['email']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = $conn->real_escape_string($_POST['role']); // Get selected role

    // Validate role
    $allowed_roles = ['client', 'editor'];
    if (!in_array($role, $allowed_roles)) {
        $error = "Invalid role selected!";
    } else {
        // Check if email exists
        if ($conn->query("SELECT id FROM users WHERE email='$email'")->num_rows > 0) {
            $error = "Email already registered!";
        } else {
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $password, $role);
            
            if ($stmt->execute()) {
                header("Location: login.php?registered=1");
                exit();
            } else {
                $error = "Registration failed!";
            }
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <svg class="auth-illustration" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
            </svg>
            <h2>Join Artifyx</h2>
            <p>Start your creative journey today</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="username" class="form-control" placeholder="Enter your name" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@example.com" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="position-relative">
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <i class="fas fa-eye-slash password-toggle"></i>
                </div>
                <small class="text-muted">Minimum 8 characters</small>
            </div>

            <div class="form-group">
                <label class="form-label">Register as</label>
                <div class="role-selection">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="role" id="clientRole" value="client" checked>
                        <label class="form-check-label" for="clientRole">
                            <i class="fas fa-user"></i> Client
                            <small class="text-muted">I want to hire creative professionals</small>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="role" id="editorRole" value="editor">
                        <label class="form-check-label" for="editorRole">
                            <i class="fas fa-pen-nib"></i> Editor
                            <small class="text-muted">I want to offer my creative services</small>
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn-primary">Create Account</button>
        </form>

        <div class="auth-footer">
            <p class="mt-3">Already have an account? <a href="login.php">Sign in here</a></p>
            <div class="social-login">
                <p class="text-muted">Or sign up with</p>
                <div class="social-buttons">
                    <button type="button" class="social-btn">
                        <i class="fab fa-google"></i>
                        Google
                    </button>
                    <button type="button" class="social-btn">
                        <i class="fab fa-github"></i>
                        GitHub
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>