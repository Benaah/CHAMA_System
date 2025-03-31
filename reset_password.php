<?php
require_once 'config.php';
require_once 'includes/functions.php';

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// Validate token
$token = isset($_GET['token']) ? $_GET['token'] : '';
$validToken = false;
$userId = null;

if (!empty($token)) {
    // Check if token exists and is not expired
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > CURRENT_TIMESTAMP");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if ($user) {
        $validToken = true;
        $userId = $user['id'];
    }
}

// If token is invalid or not provided, redirect to forgot password page
if (!$validToken) {
    $_SESSION['error'] = 'Invalid or expired password reset link. Please request a new one.';
    header('Location: forgot_password.php');
    exit;
}

// Process reset password form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    if (empty($password)) {
        $_SESSION['error'] = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $_SESSION['error'] = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirmPassword) {
        $_SESSION['error'] = 'Passwords do not match.';
    } else {
        // Hash the new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user's password and clear reset token
        $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
        
        if ($stmt->execute([$hashedPassword, $userId])) {
            // Log activity
            logActivity('Password reset completed', $userId);
            
            $_SESSION['success'] = 'Your password has been reset successfully. You can now login with your new password.';
            header('Location: login.php');
            exit;
        } else {
            $_SESSION['error'] = 'Failed to reset password. Please try again later.';
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
           <div class="card form-card">
                <div class="card-header bg-primary text-white" role="heading" aria-level="1">
                    <h4 class="mb-0">Reset Password</h4>
                </div>
                <div class="card-body">
                    <?php
                    // Display flash messages
                    if(isset($_SESSION['error'])) {
                        echo '<div class="alert alert-danger alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                ' . $_SESSION['error'] . '
                              </div>';
                        unset($_SESSION['error']);
                    }
                    ?>
                    
                    <p class="mb-4">Please enter your new password below.</p>
        
                    <form method="POST" action="" aria-label="Reset Password Form">
                        <div class="form-group">
                            <label for="password" id="password_label">New Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                </div>
                                <input type="password" class="form-control" id="password" name="password" 
                                    aria-labelledby="password_label" aria-required="true"
                                    placeholder="Enter your new password" required>
                            </div>
                            <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" id="confirm_password_label">Confirm New Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                </div>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                    aria-labelledby="confirm_password_label" aria-required="true"
                                    placeholder="Confirm your new password" required>
                            </div>
                        </div>
            
                        <button type="submit" class="btn btn-primary btn-block" aria-label="Reset Password">
                            <i class="fas fa-key mr-2" aria-hidden="true"></i> Reset Password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>