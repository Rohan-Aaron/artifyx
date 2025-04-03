<?php
require_once 'includes/db.php';
require_once 'includes/auth_functions.php';

if (is_logged_in()) {
    redirect_to_dashboard();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];
    
    $result = $conn->query("SELECT * FROM users WHERE email='$email' LIMIT 1");
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header("Location:dashboard/{$_SESSION['role']}.php");
        }
    }
    $error = "Invalid email or password";
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <svg class="auth-illustration" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                <path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 14l9-5-9-5-9 5 9 5zm0 0l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14zm-4 6v-7.5l4-2.222"/>
            </svg>
            <h2>Welcome to Artifyx</h2>
            <p>Bring your creative projects to life</p>
        </div>

        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="Enter your email" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Password</label>
                <div class="position-relative">
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    <i class="fas fa-eye-slash password-toggle"></i>
                </div>
            </div>
            
            <button type="submit" class="btn-primary">Sign In</button>
        </form>

        <div class="auth-footer">
            <p class="mt-3">Don't have an account? <a href="register.php">Create account</a></p>
            <div class="social-login">
                <p class="text-muted">Or continue with</p>
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