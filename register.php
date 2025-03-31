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
    
    // Combine first and last name for the name field
    $name = $first_name . ' ' . $last_name;
    
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
        
        try {
            // Let's check the structure of the users table first
            $tableInfo = $pdo->query("SELECT column_name FROM information_schema.columns WHERE table_name = 'users'");
            $columns = $tableInfo->fetchAll(PDO::FETCH_COLUMN);
            
            // Build the SQL query dynamically based on the actual columns
            $fields = [];
            $placeholders = [];
            $values = [];
            
            // Always include these essential fields
            if (in_array('name', $columns)) {
                $fields[] = 'name';
                $placeholders[] = '?';
                $values[] = $name;
            }
            
            if (in_array('username', $columns)) {
                $fields[] = 'username';
                $placeholders[] = '?';
                $values[] = $username;
            }
            
            if (in_array('email', $columns)) {
                $fields[] = 'email';
                $placeholders[] = '?';
                $values[] = $email;
            }
            
            if (in_array('phone', $columns)) {
                $fields[] = 'phone';
                $placeholders[] = '?';
                $values[] = $phone;
            } else if (in_array('phone_number', $columns)) {
                $fields[] = 'phone_number';
                $placeholders[] = '?';
                $values[] = $phone;
            }
            
            if (in_array('password', $columns)) {
                $fields[] = 'password';
                $placeholders[] = '?';
                $values[] = $hashedPassword;
            }
            
            if (in_array('user_role', $columns)) {
                $fields[] = 'user_role';
                $placeholders[] = '?';
                $values[] = 'member';
            }
            
            if (in_array('status', $columns)) {
                $fields[] = 'status';
                $placeholders[] = '?';
                $values[] = 'active';
            }
            
            if (in_array('registration_date', $columns)) {
                $fields[] = 'registration_date';
                $placeholders[] = 'CURRENT_TIMESTAMP';
            }
            
            // Construct the SQL query
            $sql = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            // Prepare and execute the statement
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute($values)) {
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
        } catch (PDOException $e) {
            // Fallback to a simpler query with minimal fields
            try {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
                
                if ($stmt->execute([$username, $email, $hashedPassword])) {
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
            } catch (PDOException $e2) {
                $_SESSION['error'] = 'Registration failed: ' . $e2->getMessage();
            }
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
    
    .btn-primary, .btn-success {
        background-color: #94c270;
        border: none;
    }
    
    .btn-primary:hover, .btn-success:hover {
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
        <div class="col-md-6">
        <div class="card form-card">
            <div class="card-header text-center bg-transparent">
                <img src="assets/images/default-avatar.png" alt="Avatar" class="rounded-circle" width="100">
                <h4 class="mt-2">Create a New Account</h4>
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
                    <div class="form-group">
                        <label for="first_name" id="first_name_label">First Name</label>
                        <input type="text" class="form-control" id="first_name" name="first_name" 
                                aria-labelledby="first_name_label" aria-required="true"
                                placeholder="Enter your first name" 
                                value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name" id="last_name_label">Last Name</label>
                        <input type="text" class="form-control" id="last_name" name="last_name" 
                                aria-labelledby="last_name_label" aria-required="true"
                                placeholder="Enter your last name" 
                                value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="username" id="username_label">Username</label>
                        <input type="text" class="form-control" id="username" name="username" 
                                aria-labelledby="username_label" aria-required="true"
                                placeholder="Choose a username" 
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" id="email_label">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" 
                                aria-labelledby="email_label" aria-required="true"
                                placeholder="Enter your email address" 
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                    </div>
            
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
                    
                    <div class="form-group">
                        <label for="password" id="password_label">Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                                aria-labelledby="password_label" aria-required="true"
                                placeholder="Choose a password" required>
                        <small class="form-text text-muted">Password must be at least 8 characters long.</small>
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
                        Create Account
                    </button>
                </form>
                
                <div class="text-center mt-4">
                    <p class="mb-0">Already have an account? 
                        <a href="login.php" class="text-decoration-none text-primary fw-bold">Sign in here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add animation for the form card -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const formCard = document.querySelector('.form-card');
        
        // Add initial animation
        formCard.style.opacity = '0';
        formCard.style.transform = 'translateY(20px)';
        
        setTimeout(function() {
            formCard.style.transition = 'opacity 0.8s ease, transform 0.8s ease';
            formCard.style.opacity = '1';
            formCard.style.transform = 'translateY(0)';
        }, 200);
        
        // Add form validation visual feedback
        const inputs = document.querySelectorAll('.form-control');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transition = 'transform 0.3s ease';
                this.parentElement.style.transform = 'translateX(5px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateX(0)';
            });
        });
    });
</script>

<?php include 'includes/footer.php'; ?>