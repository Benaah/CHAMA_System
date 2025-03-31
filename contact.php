<?php
require_once 'config.php';

// Process contact form submission
$formSubmitted = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');
    
    // Validate form data
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (!empty($phone) && !preg_match('/^(\+\d{1,3}[- ]?)?\d{9,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }
    
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    // If no errors, process the form
    if (empty($errors)) {
        try {
            // Insert into database
            $stmt = $pdo->prepare("INSERT INTO contact_messages (name, email, phone, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            
            if (executeQuery($stmt, [$name, $email, $phone, $subject, $message], 'contact form submission')) {
                // Set success message
                setFlashMessage('success', 'Your message has been sent successfully. We will get back to you soon!');
                $formSubmitted = true;
                
                // Optional: Send email notification
                // mail('info@agapeyouthchama.org', 'New Contact Form Submission: ' . $subject, $message, 'From: ' . $email);
            } else {
                setFlashMessage('danger', 'There was a problem sending your message. Please try again later.');
            }
        } catch (Exception $e) {
            error_log("Error in contact form: " . $e->getMessage());
            setFlashMessage('danger', 'An error occurred. Please try again later or contact us directly.');
        }
    } else {
        // Set error message
        $errorMessage = '<ul class="mb-0">';
        foreach ($errors as $error) {
            $errorMessage .= '<li>' . $error . '</li>';
        }
        $errorMessage .= '</ul>';
        
        setFlashMessage('danger', $errorMessage);
    }
}

include 'includes/header.php';
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-12 text-center mb-5">
            <h2 class="display-4">Contact Us</h2>
            <p class="lead">We'd love to hear from you. Reach out with any questions or feedback.</p>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="mb-4">Get in Touch</h4>
                    <?php displayFlashMessage(); ?>
                    
                    <?php if ($formSubmitted): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-check-circle text-success fa-5x mb-4"></i>
                            <h4>Thank You for Contacting Us!</h4>
                            <p class="lead">We've received your message and will respond shortly.</p>
                                <a href="index.php" class="btn btn-primary mt-3">Return to Home</a>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="">
                            <div class="form-group">
                                <label for="name">Your Name</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number (optional)</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" value="<?php echo isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : ''; ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Your Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="fas fa-paper-plane mr-2"></i> Send Message
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="mb-4">Contact Information</h4>
                    
                    <div class="d-flex mb-4">
                        <div class="contact-icon mr-3">
                            <i class="fas fa-map-marker-alt fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5>Our Location</h5>
                            <p class="mb-0">Agape Youth Center<br>
                            Luanda Town, Vihiga County<br>
                            Kenya</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="contact-icon mr-3">
                            <i class="fas fa-phone fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5>Phone Numbers</h5>
                            <p class="mb-0">Main: +254 712 345 678<br>
                            Support: +254 723 456 789<br>
                            WhatsApp: +254 734 567 890</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="contact-icon mr-3">
                            <i class="fas fa-envelope fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5>Email Addresses</h5>
                            <p class="mb-0">General Inquiries: info@agapeyouthchama.org<br>
                            Support: support@agapeyouthchama.org<br>
                            Membership: members@agapeyouthchama.org</p>
                        </div>
                    </div>
                    
                    <div class="d-flex mb-4">
                        <div class="contact-icon mr-3">
                            <i class="fas fa-clock fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h5>Office Hours</h5>
                            <p class="mb-0">Monday - Friday: 8:00 AM - 5:00 PM<br>
                            Saturday: 9:00 AM - 1:00 PM<br>
                            Sunday: Closed</p>
                        </div>
                    </div>
                    
                    <div class="social-media mt-4">
                        <h5>Connect With Us</h5>
                        <div class="social-icons">
                            <a href="#" class="btn btn-outline-primary mr-2"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="btn btn-outline-info mr-2"><i class="fab fa-twitter"></i></a>
                            <a href="#" class="btn btn-outline-danger mr-2"><i class="fab fa-instagram"></i></a>
                            <a href="#" class="btn btn-outline-success mr-2"><i class="fab fa-whatsapp"></i></a>
                            <a href="#" class="btn btn-outline-primary"><i class="fab fa-linkedin-in"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h4 class="mb-3">Our Location</h4>
                    <div class="embed-responsive embed-responsive-16by9">
                        <iframe class="embed-responsive-item" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3989.7575146271384!2d34.0500663!3d0.3153889!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMMKwMTgnNTUuNCJOIDM0wrAwMycwMC4yIkU!5e0!3m2!1sen!2ske!4v1620000000000!5m2!1sen!2ske" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mb-5">
        <div class="col-md-12">
            <div class="card bg-light">
                <div class="card-body">
                    <h4 class="text-center mb-4">Frequently Asked Questions</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5><i class="fas fa-question-circle text-primary mr-2"></i> How quickly will I receive a response?</h5>
                                <p>We aim to respond to all inquiries within 24 hours during business days. For urgent matters, please call our support line.</p>
                            </div>
                            <div class="mb-4">
                                <h5><i class="fas fa-question-circle text-primary mr-2"></i> Can I visit your office without an appointment?</h5>
                                <p>While we welcome visitors, we recommend scheduling an appointment to ensure that the appropriate staff member is available to assist you.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-4">
                                <h5><i class="fas fa-question-circle text-primary mr-2"></i> How do I join Agape Youth Group?</h5>
                                <p>You can start the registration process online through our <a href="register.php">registration page</a> or visit our office with your ID and passport photos.</p>
                            </div>
                            <div class="mb-4">
                                <h5><i class="fas fa-question-circle text-primary mr-2"></i> Do you offer virtual consultations?</h5>
                                <p>Yes, we offer virtual consultations via Zoom or Google Meet. Please indicate your preference for a virtual meeting when contacting us.</p>
                            </div>
                        </div>
                    </div>
                    <div class="text-center mt-3">
                        <a href="faq.php" class="btn btn-outline-primary">View All FAQs</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>