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

// Process login form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    // Validate input
    if (empty($username) || empty($password)) {
        $_SESSION['error'] = 'Please enter both username and password.';
    } else {
        // Check if user exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['user_role'];
            
            // Update last login time
            $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Log activity
            logActivity('User logged in', $user['id']);
            
            // Redirect to dashboard or requested page
            if (isset($_SESSION['redirect_url'])) {
                $redirect = $_SESSION['redirect_url'];
                unset($_SESSION['redirect_url']);
                header("Location: $redirect");
            } else {
                header('Location: dashboard.php');
            }
            exit;
        } else {
            $_SESSION['error'] = 'Invalid username or password.';
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
                    <h4 class="mb-0">Welcome</h4>
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
                    
                    if(isset($_SESSION['success'])) {
                        echo '<div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                ' . $_SESSION['success'] . '
                              </div>';
                        unset($_SESSION['success']);
                    }
                    ?>
        
                    <form method="POST" action="" aria-label="Login Form">
                        <div class="form-group">
                            <label for="username" id="username_label">Username or Email</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" class="form-control" id="username" name="username" 
                                    aria-labelledby="username_label" aria-required="true"
                                    placeholder="Enter your username or email" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" id="password_label">Password</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-lock"></i></span>
                                </div>
                            <input type="password" class="form-control" id="password" name="password" 
                                    aria-labelledby="password_label" aria-required="true"
                                    placeholder="Enter your password" required>
                        </div>
                    </div>
                                
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="remember" name="remember">
                        <label class="form-check-label" for="remember">Remember me</label>
                    </div>
            
                    <button type="submit" class="btn btn-primary btn-block" aria-label="Login">
                        <i class="fas fa-sign-in-alt mr-2" aria-hidden="true"></i> Login
                    </button>
                </form>
        
                <div class="text-center mt-3">
                    <a href="forgot_password.php">Forgot your password?</a>
                </div>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Don't have an account? <a href="register.php">Register</a></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>