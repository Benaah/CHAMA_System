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

// Process registration form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate input
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required.";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Username already exists. Please choose another one.";
        }
    }
    
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already exists. Please use another one.";
        }
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    } elseif (!preg_match('/^(\+\d{1,3}[- ]?)?\d{9,15}$/', $phone)) {
        $errors[] = "Invalid phone number format. Please use a valid format (e.g., +254712345678 or 0712345678).";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, phone_number, password, user_role, status, registration_date) VALUES (?, ?, ?, ?, ?, ?, 'member', 'active', CURRENT_TIMESTAMP)");
        
        if ($stmt->execute([$first_name, $last_name, $username, $email, $phone, $hashedPassword])) {
            // Get the new user ID
            $userId = $pdo->lastInsertId();

            // Log activity
            logActivity('New user registration', $userId);

            // Set success message
            $_SESSION['success'] = 'Registration successful! You can now login with your credentials.';
            
            // Redirect to login page
            header('Location: login.php');
            exit;
        } else {
            $_SESSION['error'] = 'Registration failed. Please try again later.';
        }
    } else {
        // Set error message
        $errorMessage = '<ul class="mb-0">';
        foreach ($errors as $error) {
            $errorMessage .= '<li>' . $error . '</li>';
        }
        $errorMessage .= '</ul>';
        
        $_SESSION['error'] = $errorMessage;
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
        <div class="card form-card">
            <div class="card-header bg-success text-white" role="heading" aria-level="1">
                <h4 class="mb-0">Create a New Account</h4>
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
        
                <form method="POST" action="" aria-label="Registration Form">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="first_name" id="first_name_label">First Name</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                        aria-labelledby="first_name_label" aria-required="true"
                                        placeholder="Enter your first name" 
                                        value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="last_name" id="last_name_label">Last Name</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                        aria-labelledby="last_name_label" aria-required="true"
                                        placeholder="Enter your last name" 
                                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="username" id="username_label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                        aria-labelledby="username_label" aria-required="true"
                                        placeholder="Choose a username" 
                                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="email" id="email_label">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                        aria-labelledby="email_label" aria-required="true"
                                        placeholder="Enter your email address" 
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>
            
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="phone" id="phone_label">Phone Number</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                        aria-labelledby="phone_label" aria-required="true"
                                        placeholder="Enter your phone number (e.g., +254712345678)" 
                                        pattern="(\+\d{1,3}[- ]?)?\d{9,15}" 
                                        title="Please enter a valid phone number (e.g., +254712345678 or 0712345678)"
                                        value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" required>
                                <small class="form-text text-muted">Enter your phone number with country code (e.g., +254712345678)</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="password" id="password_label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                        aria-labelledby="password_label" aria-required="true"
                                        placeholder="Choose a password" required>
                                <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                            </div>
                        </div>
                    </div>
            
                        <div class="form-group">
                        <label for="confirm_password" id="confirm_password_label">Confirm Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                aria-labelledby="confirm_password_label" aria-required="true"
                                placeholder="Confirm your password" required>
                    </div>
                    
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                        <label class="form-check-label" for="terms">
                            I agree to the <a href="terms.php" target="_blank">Terms and Conditions</a> and <a href="privacy.php" target="_blank">Privacy Policy</a>
                        </label>
                    </div>
            
                    <button type="submit" class="btn btn-success btn-block" aria-label="Register">
                        <i class="fas fa-user-plus mr-2" aria-hidden="true"></i> Create Account
                    </button>
                </form>
            </div>
            <div class="card-footer text-center">
                <p class="mb-0">Already have an account? <a href="login.php">Login</a></p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>