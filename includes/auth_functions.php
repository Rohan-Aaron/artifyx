<?php
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

function redirect_to_dashboard() {
    if (isset($_SESSION['role'])) {
        header("Location:dashboard/{$_SESSION['role']}.php");
        exit();
    }
    header("Location: login.php");
    exit();
}
?>