<?php
include 'includes/header.php';
include 'config.php'; // Database connection

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to access your profile.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get meetings attended
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM meeting_attendances WHERE user_id = ? AND status = 'present'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['meetings_attended'] = $result['count'] ?? 0;
} catch (PDOException $e) {
    // If table doesn't exist, set count to 0
    $stats['meetings_attended'] = 0;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $bio = trim($_POST['bio']);
    $occupation = trim($_POST['occupation']);
    $dob = trim($_POST['dob']);

    if (!empty($name) && !empty($email) && !empty($phone)) {
        // Handle profile picture upload
        $profile_picture = $user['profile_picture']; // Default to current picture
        
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['profile_picture']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            
            if (in_array(strtolower($filetype), $allowed)) {
                $new_filename = 'profile_' . $user_id . '_' . uniqid() . '.' . $filetype;
                $upload_path = 'uploads/profiles/' . $new_filename;
                
                // Create directory if it doesn't exist
                if (!file_exists('uploads/profiles/')) {
                    mkdir('uploads/profiles/', 0777, true);
                }
                
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                    // Delete old profile picture if it exists and is not the default
                    if (!empty($profile_picture) && file_exists($profile_picture) && strpos($profile_picture, 'default') === false) {
                        unlink($profile_picture);
                    }
                    $profile_picture = $upload_path;
                }
            }
        }

        // Update user profile
        $update_stmt = $pdo->prepare("UPDATE users SET name=?, email=?, phone=?, profile_picture=?, bio=?, occupation=?, date_of_birth=? WHERE id=?");
        
        if ($update_stmt->execute([$name, $email, $phone, $profile_picture, $bio, $occupation, $dob, $user_id])) {
            $_SESSION['success'] = "Profile updated successfully!";
            // Refresh user data
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $_SESSION['error'] = "Failed to update profile.";
        }
    } else {
        $_SESSION['error'] = "Name, email, and phone are required fields.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: profile.php");
    exit();
}

// Handle password change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $user['password'])) {
        // Check if new passwords match
        if ($new_password === $confirm_password) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
            
            if ($update_stmt->execute([$hashed_password, $user_id])) {
                $_SESSION['success'] = "Password updated successfully!";
            } else {
                $_SESSION['error'] = "Failed to update password.";
            }
        } else {
            $_SESSION['error'] = "New passwords do not match.";
        }
    } else {
        $_SESSION['error'] = "Current password is incorrect.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: profile.php");
    exit();
}

// Initialize stats with default values
$stats = [
    'contributions' => 0,
    'loans' => 0,
    'meetings_attended' => 0,
    'savings' => 0
];

// Get total contributions
try {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM contributions WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['contributions'] = $result['total'] ?? 0;
} catch (PDOException $e) {
    // If table doesn't exist, keep the default value
}

// Get active loans
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'active'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['loans'] = $result['count'] ?? 0;
} catch (PDOException $e) {
    // If table doesn't exist, keep the default value
}

// Get meetings attended
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM meeting_attendances WHERE user_id = ? AND status = 'present'");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['meetings_attended'] = $result['count'] ?? 0;
} catch (PDOException $e) {
    // If table doesn't exist, keep the default value
}

// Get total savings
try {
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM savings WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['savings'] = $result['total'] ?? 0;
} catch (PDOException $e) {
    // If table doesn't exist, keep the default value
}
?>

<div class="container py-5 mt-4">
    <div class="row">
        <div class="col-lg-4">
            <!-- Profile Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if (!empty($user['profile_picture']) && file_exists($user['profile_picture'])): ?>
                            <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <img src="assets/images/default-avatar.png" alt="Default Profile Picture" class="rounded-circle img-thumbnail" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php endif; ?>
                    </div>
                    <h4 class="font-weight-bold"><?= htmlspecialchars($user['name']) ?></h4>
                    <p class="text-muted">
                        <?= !empty($user['occupation']) ? htmlspecialchars($user['occupation']) : 'Member' ?>
                    </p>
                    <p>
                        <i class="fas fa-envelope mr-2 text-primary"></i> <?= htmlspecialchars($user['email']) ?><br>
                        <i class="fas fa-phone mr-2 text-primary"></i> <?= htmlspecialchars($user['phone']) ?>
                    </p>
                    <div class="border-top pt-3">
                        <p class="text-muted mb-0">Member since: <?= date('M d, Y', strtotime($user['created_at'])) ?></p>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i> Your Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <span>Total Contributions:</span>
                        <span class="font-weight-bold">KES <?= number_format($stats['contributions'], 2) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Active Loans:</span>
                        <span class="font-weight-bold"><?= $stats['loans'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Meetings Attended:</span>
                        <span class="font-weight-bold"><?= $stats['meetings_attended'] ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Total Savings:</span>
                        <span class="font-weight-bold">KES <?= number_format($stats['savings'], 2) ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Quick Links Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-link mr-2"></i> Quick Links</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <a href="contributions.php" class="text-decoration-none">
                                <i class="fas fa-hand-holding-usd mr-2 text-primary"></i> My Contributions
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="loans.php" class="text-decoration-none">
                                <i class="fas fa-money-bill-wave mr-2 text-primary"></i> My Loans
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="meetings.php" class="text-decoration-none">
                                <i class="fas fa-calendar-alt mr-2 text-primary"></i> Upcoming Meetings
                            </a>
                        </li>
                        <li class="list-group-item">
                            <a href="tools/budget_planner.php" class="text-decoration-none">
                                <i class="fas fa-calculator mr-2 text-primary"></i> Budget Planner
                            </a>
                        </li>
                        <li class="list-group-item">
                        <a href="member_exit.php" class="btn btn-danger">
                            <i class="fas fa-sign-out-alt mr-2"></i> Request to Leave CHAMA
                        </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Profile Tabs -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-white">
                    <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="edit-tab" data-toggle="tab" href="#edit" role="tab">
                                <i class="fas fa-user-edit mr-2"></i> Edit Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="security-tab" data-toggle="tab" href="#security" role="tab">
                                <i class="fas fa-lock mr-2"></i> Security
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="preferences-tab" data-toggle="tab" href="#preferences" role="tab">
                                <i class="fas fa-cog mr-2"></i> Preferences
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="profileTabsContent">
                        <!-- Edit Profile Tab -->
                        <div class="tab-pane fade show active" id="edit" role="tabpanel">
                            <form method="post" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="profile_picture">Profile Picture</label>
                                    <input type="file" class="form-control-file" id="profile_picture" name="profile_picture" accept="image/*">
                                    <small class="form-text text-muted">Upload a square image for best results. Maximum size: 2MB.</small>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="name">Full Name</label>
                                            <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="email">Email Address</label>
                                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="phone">Phone Number</label>
                                            <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($user['phone']) ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="dob">Date of Birth</label>
                                            <input type="date" class="form-control" id="dob" name="dob" value="<?= htmlspecialchars($user['date_of_birth'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="occupation">Occupation</label>
                                    <input type="text" class="form-control" id="occupation" name="occupation" value="<?= htmlspecialchars($user['occupation'] ?? '') ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="bio">Bio</label>
                                    <textarea class="form-control" id="bio" name="bio" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                                    <small class="form-text text-muted">Tell us a little about yourself.</small>
                                </div>
                                
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save mr-2"></i> Save Changes
                                </button>
                            </form>
                        </div>
                        
                        <!-- Security Tab -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <h5 class="mb-4">Change Password</h5>
                            <form method="post">
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                <div class="form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                                    <small class="form-text text-muted">Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, and one number.</small>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-primary">
                                    <i class="fas fa-key mr-2"></i> Update Password
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-4">Two-Factor Authentication</h5>
                            <p class="text-muted">Enhance your account security by enabling two-factor authentication.</p>
                            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#twoFactorModal">
                                <i class="fas fa-shield-alt mr-2"></i> Setup Two-Factor Authentication
                            </button>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-4">Login History</h5>
                            <p class="text-muted">Review your recent login activity.</p>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date & Time</th>
                                            <th>IP Address</th>
                                            <th>Device</th>
                                            <th>Location</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><?= date('M d, Y H:i:s') ?></td>
                                            <td>127.0.0.1</td>
                                            <td>Chrome on Windows</td>
                                            <td>Nairobi, Kenya</td>
                                            <td><span class="badge badge-success">Success</span></td>
                                        </tr>
                                        <!-- More login history would be populated from database -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Preferences Tab -->
                        <div class="tab-pane fade" id="preferences" role="tabpanel">
                            <h5 class="mb-4">Notification Settings</h5>
                            <form>
                                <div class="custom-control custom-switch mb-3">
                                    <input type="checkbox" class="custom-control-input" id="emailNotifications" checked>
                                    <label class="custom-control-label" for="emailNotifications">Email Notifications</label>
                                    <small class="form-text text-muted">Receive email notifications about contributions, loans, and meetings.</small>
                                </div>
                                
                                <div class="custom-control custom-switch mb-3">
                                    <input type="checkbox" class="custom-control-input" id="smsNotifications">
                                    <label class="custom-control-label" for="smsNotifications">SMS Notifications</label>
                                    <small class="form-text text-muted">Receive SMS alerts for important updates.</small>
                                </div>
                                
                                <div class="custom-control custom-switch mb-3">
                                    <input type="checkbox" class="custom-control-input" id="meetingReminders" checked>
                                    <label class="custom-control-label" for="meetingReminders">Meeting Reminders</label>
                                    <small class="form-text text-muted">Get reminders about upcoming meetings.</small>
                                </div>
                                
                                <div class="custom-control custom-switch mb-3">
                                    <input type="checkbox" class="custom-control-input" id="paymentReminders" checked>
                                    <label class="custom-control-label" for="paymentReminders">Payment Reminders</label>
                                    <small class="form-text text-muted">Receive reminders about contribution and loan payment deadlines.</small>
                                </div>
                                
                                <button type="button" class="btn btn-primary">
                                    <i class="fas fa-save mr-2"></i> Save Preferences
                                </button>
                            </form>
                            
                            <hr class="my-4">
                            
                            <h5 class="mb-4">Language & Regional Settings</h5>
                            <form>
                                <div class="form-group">
                                    <label for="language">Language</label>
                                    <select class="form-control" id="language">
                                        <option value="en" selected>English</option>
                                        <option value="sw">Swahili</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="timezone">Timezone</label>
                                    <select class="form-control" id="timezone">
                                        <option value="Africa/Nairobi" selected>East Africa Time (EAT)</option>
                                        <option value="UTC">Coordinated Universal Time (UTC)</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dateFormat">Date Format</label>
                                    <select class="form-control" id="dateFormat">
                                        <option value="dd/mm/yyyy" selected>DD/MM/YYYY</option>
                                        <option value="mm/dd/yyyy">MM/DD/YYYY</option>
                                        <option value="yyyy-mm-dd">YYYY-MM-DD</option>
                                    </select>
                                </div>
                                
                                <button type="button" class="btn btn-primary">
                                    <i class="fas fa-save mr-2"></i> Save Settings
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-history mr-2"></i> Recent Activity</h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-hand-holding-usd text-success mr-2"></i>
                                    <span>Contribution of <strong>KES 2,000</strong> recorded</span>
                                </div>
                                <small class="text-muted">2 days ago</small>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-calendar-check text-primary mr-2"></i>
                                    <span>Attended <strong>Monthly Meeting</strong></span>
                                </div>
                                <small class="text-muted">1 week ago</small>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-money-bill-wave text-warning mr-2"></i>
                                    <span>Loan payment of <strong>KES 5,000</strong> made</span>
                                </div>
                                <small class="text-muted">2 weeks ago</small>
                            </div>
                        </li>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="fas fa-piggy-bank text-info mr-2"></i>
                                    <span>Savings deposit of <strong>KES 3,000</strong> recorded</span>
                                </div>
                                <small class="text-muted">3 weeks ago</small>
                            </div>
                        </li>
                        <li class="list-group-item text-center">
                            <a href="#" class="text-primary">View All Activity</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Two-Factor Authentication Modal -->
<div class="modal fade" id="twoFactorModal" tabindex="-1" role="dialog" aria-labelledby="twoFactorModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="twoFactorModalLabel">Setup Two-Factor Authentication</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Scan the QR code below with an authenticator app like Google Authenticator or Authy:</p>
                <div class="text-center my-4">
                    <img src="assets/images/sample-qr-code.png" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                </div>
                <p>Or enter this code manually in your app:</p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" value="ABCD EFGH IJKL MNOP" readonly>
                    <div class="input-group-append">
                        <button class="btn btn-outline-secondary" type="button">Copy</button>
                    </div>
                </div>
                <div class="form-group mt-4">
                    <label for="verificationCode">Enter the 6-digit verification code from your app:</label>
                    <input type="text" class="form-control" id="verificationCode" placeholder="000000">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary">Verify & Enable</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>