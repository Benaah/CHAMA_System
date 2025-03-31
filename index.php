<?php 
include 'config.php';
include 'includes/header.php';

// Fetch announcements from database
$stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$announcements = $stmt->fetchAll();

// Fetch upcoming meetings for homepage
$stmt = $pdo->prepare("SELECT m.* FROM meetings m WHERE m.date >= CURRENT_DATE ORDER BY m.date ASC LIMIT 2");
$stmt->execute();
$meetings = $stmt->fetchAll();

// Fetch some key statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as member_count FROM users WHERE user_role = 'member'");
$stmt->execute();
$memberCount = $stmt->fetch()['member_count'];

// Fetch total savings
$stmt = $pdo->prepare("SELECT SUM(total_savings) as total_savings FROM user_accounts");
$stmt->execute();
$totalSavings = $stmt->fetch()['total_savings'] ?? 0;

// Fetch projects count
$stmt = $pdo->prepare("SELECT COUNT(*) as projects_count FROM projects");
$stmt->execute();
$projectsCount = $stmt->fetch()['projects_count'] ?? 0;

// Fetch latest news (documents)
$stmt = $pdo->prepare("SELECT * FROM documents WHERE file_type IN ('pdf', 'doc', 'docx') ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$news = $stmt->fetchAll();

// Fetch latest loan information
$stmt = $pdo->prepare("SELECT * FROM loans ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$loans = $stmt->fetchAll();

// Fetch latest project information
$stmt = $pdo->prepare("SELECT * FROM projects ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$projects = $stmt->fetchAll();

// Fetch latest investment information
$stmt = $pdo->prepare("SELECT * FROM investments ORDER BY created_at DESC LIMIT 3");
$stmt->execute();
$investments = $stmt->fetchAll();
?>

<!-- Hero Section -->
<div class="hero-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6">
                <h1 class="display-4 text-black">Welcome to Agape Youth Group!</h1>
                <p class="lead text-blue">Empowering young people through financial growth, unity, and sustainable development.</p>
                <div class="mt-4">
                    <?php if(!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn btn-primary btn-lg mr-3">Login</a>
                        <a href="register.php" class="btn btn-outline-light btn-lg">Join Us</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-primary btn-lg mr-3">My Dashboard</a>
                        <a href="contribute.php" class="btn btn-success btn-lg">Make Contribution</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <img src="assets/images/hero-image.jpg" alt="Agape Youth" class="img-fluid rounded shadow">
            </div>
        </div>
    </div>
</div>

<!-- Floating Stats Section -->
<div class="container stats-container">
    <div class="row">
        <div class="col-md-4">
            <div class="stat-card" data-aos="fade-up">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <h3 class="stat-number"><?php echo number_format($memberCount); ?></h3>
                <p class="stat-title">Active Members</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-icon"><i class="fas fa-money-bill-wave"></i></div>
                <h3 class="stat-number">KSh <?php echo number_format($totalSavings); ?></h3>
                <p class="stat-title">Total Savings</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-icon"><i class="fas fa-project-diagram"></i></div>
                <h3 class="stat-number"><?php echo number_format($projectsCount); ?></h3>
                <p class="stat-title">Funded Projects</p>
            </div>
        </div>
    </div>
</div>
<!-- Announcements Section -->
<section class="announcements-section py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="display-5 fw-bold text-primary">Latest Announcements</h2>
            <div class="divider mx-auto"></div>
        </div>
        
        <div class="row">
            <?php if(count($announcements) > 0): ?>
                <?php foreach($announcements as $index => $announcement): ?>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                        <div class="card h-100 announcement-card shadow-sm border-0">
                            <div class="card-body">
                                <div class="announcement-date mb-2">
                                    <span class="badge bg-primary"><?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></span>
                                </div>
                                <h4 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h4>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 150))); ?>...</p>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="announcement.php?id=<?php echo $announcement['id']; ?>" class="btn btn-sm btn-outline-primary">Read More</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p>No announcements available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="announcements.php" class="btn btn-outline-primary">View All Announcements</a>
        </div>
    </div>
</section>

<!-- Upcoming Meetings Section -->
<section class="meetings-section py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="display-5 fw-bold text-primary">Upcoming Meetings</h2>
            <div class="divider mx-auto"></div>
        </div>
        
        <div class="row">
            <?php if(count($meetings) > 0): ?>
                <?php foreach($meetings as $index => $meeting): ?>
                    <div class="col-lg-6 mb-4" data-aos="zoom-in" data-aos-delay="<?php echo $index * 150; ?>">
                        <div class="meeting-card shadow-sm rounded p-4 position-relative overflow-hidden">
                            <div class="meeting-date-badge">
                                <span class="date"><?php echo date('d', strtotime($meeting['date'])); ?></span>
                                <span class="month"><?php echo date('M', strtotime($meeting['date'])); ?></span>
                            </div>
                            <h4><?php echo htmlspecialchars($meeting['title']); ?></h4>
                            <p class="text-muted mb-2">
                                <i class="fas fa-clock mr-2"></i> <?php echo date('h:i A', strtotime($meeting['time'])); ?>
                            </p>
                            <p class="text-muted mb-3">
                                <i class="fas fa-map-marker-alt mr-2"></i> <?php echo htmlspecialchars($meeting['location']); ?>
                            </p>
                            <p><?php echo nl2br(htmlspecialchars(substr($meeting['description'], 0, 120))); ?>...</p>
                            <a href="meeting.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p>No upcoming meetings scheduled at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="meetings.php" class="btn btn-outline-primary">View All Meetings</a>
        </div>
    </div>
</section>

<!-- Services Section -->
<section class="services-section py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="display-5 fw-bold text-primary">Our Services</h2>
            <div class="divider mx-auto"></div>
            <p class="lead">Explore the financial opportunities available to our members</p>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-4" data-aos="fade-up">
                <div class="service-card text-center p-4 h-100 shadow-sm rounded bg-white">
                    <div class="service-icon mb-3">
                        <i class="fas fa-hand-holding-usd fa-3x text-primary"></i>
                    </div>
                    <h4>Loans</h4>
                    <p>Access affordable loans with competitive interest rates for your personal and business needs.</p>
                    <a href="loans.php" class="btn btn-outline-primary mt-3">Learn More</a>
                </div>
            </div>
            
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                <div class="service-card text-center p-4 h-100 shadow-sm rounded bg-white">
                    <div class="service-icon mb-3">
                        <i class="fas fa-chart-line fa-3x text-primary"></i>
                    </div>
                    <h4>Investments</h4>
                    <p>Grow your wealth through our diverse investment opportunities with attractive returns.</p>
                    <a href="investments.php" class="btn btn-outline-primary mt-3">Learn More</a>
                </div>
            </div>
            
            <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                <div class="service-card text-center p-4 h-100 shadow-sm rounded bg-white">
                    <div class="service-icon mb-3">
                        <i class="fas fa-piggy-bank fa-3x text-primary"></i>
                    </div>
                    <h4>Savings</h4>
                    <p>Build your financial future with our structured savings programs designed for growth.</p>
                    <a href="savings.php" class="btn btn-outline-primary mt-3">Learn More</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Latest Projects Section -->
<section class="projects-section py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="display-5 fw-bold text-primary">Our Latest Projects</h2>
            <div class="divider mx-auto"></div>
        </div>
        
        <div class="row">
            <?php if(count($projects) > 0): ?>
                <?php foreach($projects as $index => $project): ?>
                    <div class="col-lg-4 mb-4" data-aos="flip-up" data-aos-delay="<?php echo $index * 100; ?>">
                        <div class="card project-card h-100 shadow-sm border-0">
                            <div class="project-image">
                                <img src="<?php echo !empty($project['image']) ? 'uploads/projects/'.$project['image'] : 'assets/images/project-placeholder.jpg'; ?>" 
                                     class="card-img-top" alt="<?php echo htmlspecialchars($project['title']); ?>">
                                <div class="project-overlay">
                                    <a href="project.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-light">View Details</a>
                                </div>
                            </div>
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($project['title']); ?></h5>
                                <div class="progress mb-3">
                                    <?php 
                                    $percentage = 0;
                                    if($project['target_amount'] > 0) {
                                        $percentage = min(100, round(($project['current_amount'] / $project['target_amount']) * 100));
                                    }
                                    ?>
                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                         aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                        <?php echo $percentage; ?>%
                                    </div>
                                </div>
                                <p class="card-text">
                                    <span class="text-muted">Raised: </span>KSh <?php echo number_format($project['current_amount']); ?> 
                                    <span class="text-muted">of KSh <?php echo number_format($project['target_amount']); ?></span>
                                </p>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($project['description'], 0, 100))); ?>...</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p>No projects available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="projects.php" class="btn btn-outline-primary">View All Projects</a>
        </div>
    </div>
</section>

<!-- Testimonials Section -->
<section class="testimonials-section py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="display-5 fw-bold text-primary">Member Testimonials</h2>
            <div class="divider mx-auto"></div>
        </div>
        
        <div class="testimonial-slider">
            <div class="row">
                <div class="col-md-4 mb-4" data-aos="fade-up">
                    <div class="testimonial-card p-4 bg-white shadow-sm rounded h-100">
                        <div class="testimonial-content">
                            <p class="mb-4"><i class="fas fa-quote-left text-primary mr-2"></i> Joining Agape Youth Group was one of the best decisions I've made. The financial literacy I've gained and the support from fellow members has been invaluable.</p>
                        </div>
                        <div class="testimonial-author d-flex align-items-center">
                            <div class="author-avatar mr-3">
                                <img src="assets/images/testimonial-1.jpg" alt="Testimonial" class="rounded-circle" width="60">
                            </div>
                            <div class="author-info">
                                <h5 class="mb-0">Jane Doe</h5>
                                <small class="text-muted">Member since 2020</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-card p-4 bg-white shadow-sm rounded h-100">
                        <div class="testimonial-content">
                            <p class="mb-4"><i class="fas fa-quote-left text-primary mr-2"></i> The loan I received helped me start my small business. The process was straightforward and the interest rates are fair. I'm grateful for this opportunity.</p>
                        </div>
                        <div class="testimonial-author d-flex align-items-center">
                            <div class="author-avatar mr-3">
                                <img src="assets/images/testimonial-2.jpg" alt="Testimonial" class="rounded-circle" width="60">
                            </div>
                            <div class="author-info">
                                <h5 class="mb-0">John Smith</h5>
                                <small class="text-muted">Member since 2019</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-card p-4 bg-white shadow-sm rounded h-100">
                        <div class="testimonial-content">
                            <p class="mb-4"><i class="fas fa-quote-left text-primary mr-2"></i> The investment opportunities provided by Agape have helped me grow my savings significantly. The transparency and accountability in management is commendable.</p>
                        </div>
                        <div class="testimonial-author d-flex align-items-center">
                            <div class="author-avatar mr-3">
                                <img src="assets/images/testimonial-3.jpg" alt="Testimonial" class="rounded-circle" width="60">
                            </div>
                            <div class="author-info">
                                <h5 class="mb-0">Mary Johnson</h5>
                                <small class="text-muted">Member since 2021</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Call to Action Section -->
<section class="cta-section py-5 bg-primary text-white">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-8 mb-4 mb-lg-0">
                <h2 class="mb-2">Ready to join our community?</h2>
                <p class="lead mb-0">Become a member today and start your journey towards financial growth and empowerment.</p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <?php if(!isset($_SESSION['user_id'])): ?>
                    <a href="register.php" class="btn btn-light btn-lg">Join Now</a>
                <?php else: ?>
                    <a href="dashboard.php" class="btn btn-light btn-lg">Go to Dashboard</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Latest News Section -->
<section class="news-section py-5">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="display-5 fw-bold text-primary">Latest News & Resources</h2>
            <div class="divider mx-auto"></div>
        </div>
        
        <div class="row">
            <?php if(count($news) > 0): ?>
                <?php foreach($news as $index => $document): ?>
                    <div class="col-lg-4 mb-4" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                        <div class="card news-card h-100 shadow-sm border-0">
                            <div class="card-body">
                                <div class="document-type mb-2">
                                    <span class="badge bg-<?php echo ($document['file_type'] == 'pdf') ? 'danger' : (($document['file_type'] == 'doc' || $document['file_type'] == 'docx') ? 'primary' : 'secondary'); ?>">
                                        <?php echo strtoupper($document['file_type']); ?>
                                    </span>
                                </div>
                                <h5 class="card-title"><?php echo htmlspecialchars($document['title']); ?></h5>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($document['description'], 0, 120))); ?>...</p>
                                <p class="text-muted small">
                                    <i class="fas fa-calendar-alt mr-1"></i> 
                                    <?php echo date('M d, Y', strtotime($document['created_at'])); ?>
                                </p>
                            </div>
                            <div class="card-footer bg-transparent border-0">
                                <a href="download.php?id=<?php echo $document['id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download mr-1"></i> Download
                                </a>
                                <a href="document.php?id=<?php echo $document['id']; ?>" class="btn btn-sm btn-link">View Details</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <p>No documents available at the moment.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="text-center mt-4">
            <a href="resources.php" class="btn btn-outline-primary">View All Resources</a>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="newsletter-section py-5">
    <div class="container">
        <div class="newsletter-container bg-primary text-white p-5 rounded shadow">
            <div class="row align-items-center">
                <div class="col-lg-7 mb-4 mb-lg-0">
                    <h2 class="mb-3">Subscribe to Our Newsletter</h2>
                    <p class="lead mb-0">Stay updated with the latest news, events, and opportunities from Agape Youth Group.</p>
                </div>
                <div class="col-lg-5">
                    <form action="subscribe.php" method="post" class="newsletter-form">
                        <div class="input-group">
                            <input type="email" name="email" class="form-control form-control-lg" placeholder="Your email address" required>
                            <button type="submit" class="btn btn-light">Subscribe</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Partners Section -->
<section class="partners-section py-5 bg-light">
    <div class="container">
        <div class="section-header text-center mb-5">
            <h2 class="display-5 fw-bold text-primary">Our Partners</h2>
            <div class="divider mx-auto"></div>
        </div>
        
        <div class="row align-items-center justify-content-center">
            <div class="col-6 col-md-3 mb-4 mb-md-0" data-aos="zoom-in">
                <div class="partner-logo text-center">
                    <img src="assets/images/partner-1.png" alt="Partner 1" class="img-fluid">
                </div>
            </div>
            <div class="col-6 col-md-3 mb-4 mb-md-0" data-aos="zoom-in" data-aos-delay="100">
                <div class="partner-logo text-center">
                    <img src="assets/images/partner-2.png" alt="Partner 2" class="img-fluid">
                </div>
            </div>
            <div class="col-6 col-md-3 mb-4 mb-md-0" data-aos="zoom-in" data-aos-delay="200">
                <div class="partner-logo text-center">
                    <img src="assets/images/partner-3.png" alt="Partner 3" class="img-fluid">
                </div>
            </div>
            <div class="col-6 col-md-3" data-aos="zoom-in" data-aos-delay="300">
                <div class="partner-logo text-center">
                    <img src="assets/images/partner-4.png" alt="Partner 4" class="img-fluid">
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Map Section -->
<section class="map-section">
    <div class="container-fluid p-0">
        <div class="map-container">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3988.819917806043!2d36.81984761427565!3d-1.2833562359896379!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x182f10d22f42bf05%3A0xb8a0e3a919c36b10!2sNairobi%2C%20Kenya!5e0!3m2!1sen!2sus!4v1625124301611!5m2!1sen!2sus" 
                    width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>