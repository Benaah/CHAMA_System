<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // This will redirect to login if not authenticated

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's total contributions
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM contributions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalContributions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get user's outstanding loans
$stmt = $pdo->prepare("SELECT COALESCE(SUM(l.amount), 0) - COALESCE(SUM(lr.amount), 0) as total 
                      FROM loans l 
                      LEFT JOIN loan_repayments lr ON l.id = lr.loan_id 
                      WHERE l.user_id = ? AND l.status IN ('approved', 'disbursed')");
$stmt->execute([$_SESSION['user_id']]);
$outstandingLoans = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get user's loan eligibility (typically 3x of total contributions)
$loanEligibility = $totalContributions * 3;

// Get recent contributions
$stmt = $pdo->prepare("SELECT * FROM contributions WHERE user_id = ? ORDER BY contribution_date DESC LIMIT 5");
$stmt->execute([$_SESSION['user_id']]);
$recentContributions = $stmt->fetchAll();

// Get active loans
$stmt = $pdo->prepare("SELECT l.*, COALESCE(SUM(lr.amount), 0) as amount_repaid 
                      FROM loans l 
                      LEFT JOIN loan_repayments lr ON l.id = lr.loan_id 
                      WHERE l.user_id = ? AND l.status IN ('approved', 'disbursed') 
                      GROUP BY l.id 
                      ORDER BY l.loan_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$activeLoans = $stmt->fetchAll();

// Get user's shares
$stmt = $pdo->prepare("SELECT * FROM project_shares WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userShares = $stmt->fetchAll();

// Get user's investments
$stmt = $pdo->prepare("SELECT * FROM investments WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$userInvestments = $stmt->fetchAll();

// Get user's project contributions
$stmt = $pdo->prepare("SELECT * FROM project_contributions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$projectContributions = $stmt->fetchAll();

// Get user's meeting attendance
$stmt = $pdo->prepare("SELECT * FROM meeting_attendees WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$meetingAttendance = $stmt->fetchAll();

// Get upcoming meetings
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE date >= CURRENT_DATE ORDER BY date ASC LIMIT 3");
$stmt->execute();
$upcomingMeetings = $stmt->fetchAll();

// Get recent announcements
$stmt = $pdo->prepare("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$recentAnnouncements = $stmt->fetchAll();

// Include header
include 'includes/header.php';
?>

<!-- Add required CSS for animations and floating video window -->
<style>
    /* Animation keyframes */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(50px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideInLeft {
        from { opacity: 0; transform: translateX(-50px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    /* Apply animations to elements */
    .welcome-banner {
        animation: fadeIn 0.8s ease-out;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #4b421b 0%, #24252a 51%, #070716 100%);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .welcome-banner:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
    }
    
    .summary-card {
        animation: fadeIn 0.8s ease-out;
        animation-fill-mode: both;
        transition: all 0.3s ease;
        overflow: hidden;
        border-radius: 10px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
    }
    
    .summary-card:hover {
        transform: translateY(-7px);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    }
    
    .summary-card:nth-child(1) { animation-delay: 0.1s; }
    .summary-card:nth-child(2) { animation-delay: 0.2s; }
    .summary-card:nth-child(3) { animation-delay: 0.3s; }
    .summary-card:nth-child(4) { animation-delay: 0.4s; }
    
    .card-left {
        animation: slideInLeft 0.8s ease-out;
        animation-fill-mode: both;
        animation-delay: 0.5s;
    }
    
    .card-right {
        animation: slideInRight 0.8s ease-out;
        animation-fill-mode: both;
        animation-delay: 0.5s;
    }
    
    .display-4 {
        animation: pulse 2s infinite;
    }
    
    /* Floating video window */
    #floating-video-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 320px;
        background-color: #fff;
        border-radius: 10px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
        z-index: 1000;
        overflow: hidden;
        transition: all 0.3s ease;
        opacity: 0.9;
    }
    
    #floating-video-container:hover {
        opacity: 1;
    }
    
    #video-header {
        background: linear-gradient(135deg, #4b421b 0%, #24252a 51%, #070716 100%);
        color: white;
        padding: 10px 15px;
        cursor: move;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    #video-content {
        padding: 10px;
    }
    
    #video-minimize {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 16px;
    }
    
    #video-frame {
        width: 100%;
        height: 180px;
        border: none;
    }
    
    .minimized {
        width: 200px !important;
        height: 40px !important;
        overflow: hidden;
    }
    
    /* Card hover effects */
    .card {
        transition: all 0.3s ease;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    /* Table row hover animation */
    .table-hover tbody tr {
        transition: all 0.2s ease;
    }
    
    .table-hover tbody tr:hover {
        transform: scale(1.01);
        background-color: rgba(78, 115, 223, 0.05);
    }
    
    /* Meeting date styling */
    .meeting-date {
        background-color: #4e73df;
        color: white;
        border-radius: 5px;
        padding: 5px 10px;
        min-width: 60px;
    }
    
    .meeting-date .month {
        font-size: 14px;
        font-weight: bold;
    }
    
    .meeting-date .day {
        font-size: 20px;
        font-weight: bold;
    }
    
    /* Announcement hover effect */
    .announcement-item {
        transition: all 0.3s ease;
        padding: 10px;
        border-radius: 5px;
    }
    
    .announcement-item:hover {
        background-color: rgba(78, 115, 223, 0.05);
    }
</style>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="welcome-banner p-4 mb-4 text-white rounded shadow">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2>Welcome, <?php echo htmlspecialchars($user['name']); ?>!</h2>
                        <p class="lead mb-0">Here's an overview of your account and recent activities.</p>
                    </div>
                    <div class="col-md-4 text-md-right">
                        <a href="contribute.php" class="btn btn-light btn-lg">
                            <i class="fas fa-hand-holding-usd"></i> Make Contribution
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Financial Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-3 summary-card">
            <div class="card h-100 border-primary">
                <div class="card-body text-center">
                    <h5 class="card-title text-primary">Total Contributions</h5>
                    <div class="display-4 font-weight-bold text-primary">
                        <?php echo number_format($totalContributions, 2); ?>
                    </div>
                    <p class="card-text text-muted">Your lifetime contributions</p>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="contributions.php" class="btn btn-sm btn-outline-primary">View Details</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 summary-card">
            <div class="card h-100 border-danger">
                <div class="card-body text-center">
                    <h5 class="card-title text-danger">Outstanding Loans</h5>
                    <div class="display-4 font-weight-bold text-danger">
                        <?php echo number_format($outstandingLoans, 2); ?>
                    </div>
                    <p class="card-text text-muted">Current loan balance</p>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="loans.php" class="btn btn-sm btn-outline-danger">View Details</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 summary-card">
            <div class="card h-100 border-success">
                <div class="card-body text-center">
                    <h5 class="card-title text-success">Loan Eligibility</h5>
                    <div class="display-4 font-weight-bold text-success">
                        <?php echo number_format($loanEligibility, 2); ?>
                    </div>
                    <p class="card-text text-muted">Maximum amount you can borrow</p>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="apply_loan.php" class="btn btn-sm btn-outline-success">Apply for Loan</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 summary-card">
            <div class="card h-100 border-info">
                <div class="card-body text-center">
                    <h5 class="card-title text-info">Next Meeting</h5>
                    <?php if (count($upcomingMeetings) > 0): ?>
                        <div class="h5 font-weight-bold text-info">
                            <?php echo date('M d, Y', strtotime($upcomingMeetings[0]['date'])); ?>
                        </div>
                        <p class="card-text">
                            <?php echo date('h:i A', strtotime($upcomingMeetings[0]['time'])); ?><br>
                            <?php echo htmlspecialchars($upcomingMeetings[0]['location'] ?? 'TBD'); ?>
                        </p>
                    <?php else: ?>
                        <div class="h5 font-weight-bold text-info">No upcoming meetings</div>
                        <p class="card-text text-muted">Check back later for updates</p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent border-0 text-center">
                    <a href="meetings.php" class="btn btn-sm btn-outline-info">View All Meetings</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Recent Contributions -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 card-left">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Recent Contributions</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recentContributions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentContributions as $contribution): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($contribution['contribution_date'])); ?></td>
                                            <td><?php echo number_format($contribution['amount'], 2); ?></td>
                                            <td><?php echo ucfirst($contribution['payment_method'] ?? 'Cash'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo ($contribution['status'] ?? 'approved') == 'approved' ? 'success' : 
                                                        (($contribution['status'] ?? 'pending') == 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($contribution['status'] ?? 'approved'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            You haven't made any contributions yet. 
                            <a href="contribute.php" class="alert-link">Make your first contribution</a>.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="contributions.php" class="btn btn-sm btn-outline-primary">View All Contributions</a>
                </div>
            </div>
        </div>
        
        <!-- Active Loans -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 card-right">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Active Loans</h5>
                </div>
                <div class="card-body">
                    <?php if (count($activeLoans) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activeLoans as $loan): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($loan['loan_date'])); ?></td>
                                            <td><?php echo number_format($loan['amount'], 2); ?></td>
                                            <td><?php echo number_format($loan['amount'] - $loan['amount_repaid'], 2); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $loan['status'] == 'approved' ? 'success' : 
                                                        ($loan['status'] == 'pending' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo ucfirst($loan['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            You don't have any active loans. 
                            <a href="apply_loan.php" class="alert-link">Apply for a loan</a> if you need financial assistance.
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="loans.php" class="btn btn-sm btn-outline-primary">View All Loans</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Announcements -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 card-left">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Recent Announcements</h5>
                </div>
                <div class="card-body">
                    <?php if (count($recentAnnouncements) > 0): ?>
                        <?php foreach ($recentAnnouncements as $announcement): ?>
                            <div class="announcement-item mb-3">
                                <h5><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                <p><?php echo nl2br(htmlspecialchars(substr($announcement['content'], 0, 150))); ?>
                                   <?php if (strlen($announcement['content']) > 150): ?>...<?php endif; ?></p>
                                <small class="text-muted">Posted on <?php echo date('M d, Y', strtotime($announcement['created_at'])); ?></small>
                                <hr>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No recent announcements.</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="announcements.php" class="btn btn-sm btn-outline-primary">View All Announcements</a>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Meetings -->
        <div class="col-md-6 mb-4">
            <div class="card h-100 card-right">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Upcoming Meetings</h5>
                </div>
                <div class="card-body">
                    <?php if (count($upcomingMeetings) > 0): ?>
                        <?php foreach ($upcomingMeetings as $meeting): ?>
                            <div class="meeting-item mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="meeting-date mr-3 text-center">
                                        <div class="month"><?php echo date('M', strtotime($meeting['date'])); ?></div>
                                        <div class="day"><?php echo date('d', strtotime($meeting['date'])); ?></div>
                                    </div>
                                    <div>
                                        <h5><?php echo htmlspecialchars($meeting['title']); ?></h5>
                                        <p class="mb-1">
                                            <i class="fas fa-clock mr-1"></i> 
                                            <?php echo date('h:i A', strtotime($meeting['time'])); ?> - 
                                            <?php echo date('h:i A', strtotime($meeting['time'] . ' +2 hours')); ?>
                                        </p>
                                        <p class="mb-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i> 
                                            <?php echo htmlspecialchars($meeting['location'] ?? 'TBD'); ?>
                                        </p>
                                    </div>
                                </div>
                                <hr>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="alert alert-info">No upcoming meetings scheduled.</div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="meetings.php" class="btn btn-sm btn-outline-primary">View All Meetings</a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Video Window -->
<div id="floating-video-container">
    <div id="video-header">
        <span>Video Updates</span>
        <button id="video-minimize"><i class="fas fa-minus"></i></button>
        <button id="video-close"><i class="fas fa-times"></i></button>
    </div>
    <div id="video-content">
        <iframe id="video-frame" src="https://www.youtube.com/embed/dQw4w9WgXcQ?autoplay=0" allowfullscreen></iframe>
        <p class="mt-2 mb-0 small">Latest updates and tips for AGAPE CHAMA members</p>
    </div>
</div>

<!-- JavaScript for the floating video window -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Make the floating video window draggable
    const videoContainer = document.getElementById('floating-video-container');
    const videoHeader = document.getElementById('video-header');
    const videoMinimize = document.getElementById('video-minimize');
    
    let isDragging = false;
    let offsetX, offsetY;
    
    // Close button functionality
    document.getElementById('video-close').addEventListener('click', function() {
        videoContainer.style.display = 'none';
    });

    // Toggle minimize/maximize
    videoMinimize.addEventListener('click', function() {
        videoContainer.classList.toggle('minimized');
        
        // Change the icon based on state
        if (videoContainer.classList.contains('minimized')) {
            videoMinimize.innerHTML = '<i class="fas fa-plus"></i>';
        } else {
            videoMinimize.innerHTML = '<i class="fas fa-minus"></i>';
        }
    });
    
    // Mouse down event to start dragging
    videoHeader.addEventListener('mousedown', function(e) {
        isDragging = true;
        
        // Get the initial mouse position relative to the container
        const rect = videoContainer.getBoundingClientRect();
        offsetX = e.clientX - rect.left;
        offsetY = e.clientY - rect.top;
        
        // Prevent text selection during drag
        e.preventDefault();
    });
    
    // Mouse move event to handle dragging
    document.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        
        // Calculate new position
        const x = e.clientX - offsetX;
        const y = e.clientY - offsetY;
        
        // Apply new position
        videoContainer.style.left = x + 'px';
        videoContainer.style.top = y + 'px';
        videoContainer.style.right = 'auto';
        videoContainer.style.bottom = 'auto';
    });
    
    // Mouse up event to stop dragging
    document.addEventListener('mouseup', function() {
        isDragging = false;
    });
    
    // Mouse leave event to stop dragging if mouse leaves the window
    document.addEventListener('mouseleave', function() {
        isDragging = false;
    });
});
</script>

<?php include 'includes/footer.php'; ?>