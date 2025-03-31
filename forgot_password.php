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

// Process forgot password form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    // Validate input
    if (empty($email)) {
        $_SESSION['error'] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = 'Please enter a valid email address.';
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            // Generate a unique token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?");
            $stmt->execute([$token, $expiry, $user['id']]);
            
            // Create reset link
            $resetLink = BASE_URL . '/reset_password.php?token=' . $token;
            
            // Prepare email
            $to = $user['email'];
            $subject = 'Password Reset Request - Agape Youth Group';
            $message = '
            <html>
            <head>
                <title>Password Reset Request</title>
            </head>
            <body>
                <h2>Password Reset Request</h2>
                <p>Hello ' . htmlspecialchars($user['first_name']) . ',</p>
                <p>We received a request to reset your password. If you did not make this request, please ignore this email.</p>
                <p>To reset your password, click on the link below:</p>
                <p><a href="' . $resetLink . '">' . $resetLink . '</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>Regards,<br>Agape Youth Group Team</p>
            </body>
            </html>
            ';
            
            // Send email
            if (sendEmail($to, $subject, $message)) {
                // Log activity
                logActivity('Password reset requested', $user['id']);
                
                $_SESSION['success'] = 'Password reset instructions have been sent to your email address.';
                header('Location: login.php');
                exit;
            } else {
                $_SESSION['error'] = 'Failed to send password reset email. Please try again later.';
            }
        } else {
            // Don't reveal that the email doesn't exist for security reasons
            $_SESSION['success'] = 'If your email address exists in our database, you will receive a password recovery link at your email address.';
            header('Location: login.php');
            exit;
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
                    <h4 class="mb-0">Forgot Password</h4>
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
                    
                    <p class="mb-4">Enter your email address below and we'll send you instructions to reset your password.</p>
        
                    <form method="POST" action="" aria-label="Forgot Password Form">
                        <div class="form-group">
                            <label for="email" id="email_label">Email Address</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-envelope"></i></span>
                                </div>
                                <input type="email" class="form-control" id="email" name="email" 
                                    aria-labelledby="email_label" aria-required="true"
                                    placeholder="Enter your email address" required>
                            </div>
                        </div>
            
                        <button type="submit" class="btn btn-primary btn-block" aria-label="Reset Password">
                            <i class="fas fa-paper-plane mr-2" aria-hidden="true"></i> Send Reset Instructions
                        </button>
                    </form>
        
                    <div class="text-center mt-3">
                        <a href="login.php">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>