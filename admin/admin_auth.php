<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Set error message
    $_SESSION['error'] = "You must be logged in as an administrator to access this page.";
    
    // Redirect to login page
    header("Location: ../login.php");
    exit;
}
?>