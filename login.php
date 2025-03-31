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
            try {
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $updateStmt->execute([$user['id']]);
            } catch (PDOException $e) {
                // If last_login column doesn't exist, just continue without updating it
                // You might want to log this error for future reference
            }
            
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

<style>
    /* Video background styles */
    #video-background {
        position: fixed;
        right: 0;
        bottom: 0;
        min-width: 100%;
        min-height: 100%;
        width: auto;
        height: auto;
        z-index: -1000;
        background-size: cover;
        overflow: hidden;
    }
    
    /* Overlay to darken the video slightly */
    .video-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: -999;
    }
    
    /* Transparent form card */
    .form-card {
        background: rgba(255, 255, 255, 0.8);
        backdrop-filter: blur(10px);
        border-radius: 10px;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        transition: all 0.3s ease;
    }
    
    .form-card:hover {
        box-shadow: 0 12px 48px rgba(0, 0, 0, 0.4);
        transform: translateY(-5px);
    }
    
    .card-header {
        background: rgba(0, 0, 0, 0.1);
        border-bottom: 1px solid rgba(255, 255, 255, 0.2);
    }
    
    .btn-primary {
        background-color: #94c270;
        border: none;
    }
    
    .btn-primary:hover {
        background-color: rgb(119, 196, 119);
    }
    
    .input-group-text {
        background-color: transparent;
        border: none;
    }
    
    /* Make form inputs semi-transparent */
    .form-control {
        background-color: rgba(255, 255, 255, 0.7);
        border: 1px solid rgba(0, 0, 0, 0.1);
    }
    
    .form-control:focus {
        background-color: rgba(255, 255, 255, 0.9);
        box-shadow: 0 0 0 0.2rem rgba(148, 194, 112, 0.25);
    }
</style>

<!-- Video Background -->
<video autoplay muted loop id="video-background">
    <source src="assets/background.mp4" type="video/mp4">
    <!-- Fallback background image if video doesn't load -->
    <style>
        body {
            background-image: url('assets/images/login-bg.jpg');
            background-size: cover;
            background-position: center;
        }
    </style>
</video>
<div class="video-overlay"></div>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-4">
           <div class="card form-card">
                <div class="card-header text-center bg-transparent">
                    <img src="assets/images/default-avatar.png" alt="Avatar" class="rounded-circle" width="100">
                    <h4 class="mt-2">Welcome</h4>
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
                            <label for="username" id="username_label">Email</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-user"></i></span>
                                </div>
                                <input type="text" class="form-control" id="username" name="username" 
                                    aria-labelledby="username_label" aria-required="true"
                                    placeholder="Enter Email" required>
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
                                    placeholder="Enter Password" required>
                        </div>
                    </div>
            
                    <button type="submit" class="btn btn-primary btn-block" aria-label="Login">
                        Sign In
                    </button>
                </form>
        
                <div class="text-center mt-4">
                    <a href="forgot_password.php" class="text-decoration-none text-primary">Forgot Password?</a>
                    <hr class="my-3 w-75 mx-auto">
                    <p class="mb-0">Don't have an account? 
                        <a href="register.php" class="text-decoration-none text-primary fw-bold">Sign up here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>