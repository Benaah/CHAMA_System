<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if member ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['admin_error'] = "Invalid member ID.";
    header("Location: members.php");
    exit();
}

$member_id = $_GET['id'];

// Fetch member details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'member'");
$stmt->execute([$member_id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    $_SESSION['admin_error'] = "Member not found.";
    header("Location: members.php");
    exit();
}

// Fetch member statistics
$stats = [];

// Total contributions
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM contributions WHERE user_id = ?");
$stmt->execute([$member_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['contributions'] = $result['total'] ?? 0;

// Active loans
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM loans WHERE user_id = ? AND status = 'active'");
$stmt->execute([$member_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['active_loans'] = $result['count'] ?? 0;

// Total loan amount
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM loans WHERE user_id = ? AND status = 'active'");
$stmt->execute([$member_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['loan_amount'] = $result['total'] ?? 0;

// Meetings attended
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM meeting_attendances WHERE user_id = ? AND status = 'present'");
$stmt->execute([$member_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['meetings_attended'] = $result['count'] ?? 0;

// Total savings
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM savings WHERE user_id = ?");
$stmt->execute([$member_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$stats['savings'] = $result['total'] ?? 0;

// Recent contributions
$stmt = $pdo->prepare("
    SELECT * FROM contributions 
    WHERE user_id = ? 
    ORDER BY contribution_date DESC 
    LIMIT 5
");
$stmt->execute([$member_id]);
$recent_contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent loans
$stmt = $pdo->prepare("
    SELECT * FROM loans 
    WHERE user_id = ? 
    ORDER BY application_date DESC 
    LIMIT 5
");
$stmt->execute([$member_id]);
$recent_loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent meeting attendance
$stmt = $pdo->prepare("
    SELECT ma.*, m.title, m.meeting_date
    FROM meeting_attendances ma
    JOIN meetings m ON ma.meeting_id = m.id
    WHERE ma.user_id = ?
    ORDER BY m.meeting_date DESC
    LIMIT 5
");
$stmt->execute([$member_id]);
$recent_attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Member Details</h1>
        <div>
            <a href="members.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left fa-sm text-white-50"></i> Back to Members
            </a>
            <a href="#" class="btn btn-primary btn-sm ml-2" data-toggle="modal" data-target="#editMemberModal">
                <i class="fas fa-edit fa-sm text-white-50"></i> Edit Member
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['admin_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= $_SESSION['admin_success']; unset($_SESSION['admin_success']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['admin_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= $_SESSION['admin_error']; unset($_SESSION['admin_error']); ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Member Profile Card -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profile Information</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <?php if (!empty($member['profile_picture']) && file_exists('../' . $member['profile_picture'])): ?>
                            <img src="../<?= htmlspecialchars($member['profile_picture']) ?>" alt="Profile" class="img-profile rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <img src="../assets/images/default-avatar.png" alt="Default Profile" class="img-profile rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">
                        <?php endif; ?>
                        <h4 class="mt-3"><?= htmlspecialchars($member['name']) ?></h4>
                        <p class="badge badge-<?= $member['status'] == 'active' ? 'success' : ($member['status'] == 'inactive' ? 'warning' : 'danger') ?>">
                            <?= ucfirst($member['status']) ?>
                        </p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Contact Information</h6>
                        <p>
                            <i class="fas fa-envelope mr-2 text-primary"></i> <?= htmlspecialchars($member['email']) ?><br>
                            <i class="fas fa-phone mr-2 text-primary"></i> <?= htmlspecialchars($member['phone']) ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($member['bio'])): ?>
                        <div class="mb-3">
                            <h6 class="font-weight-bold">Bio</h6>
                            <p><?= nl2br(htmlspecialchars($member['bio'])) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($member['occupation'])): ?>
                        <div class="mb-3">
                            <h6 class="font-weight-bold">Occupation</h6>
                            <p><?= htmlspecialchars($member['occupation']) ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Account Information</h6>
                        <p>
                            <strong>Member Since:</strong> <?= date('M d, Y', strtotime($member['created_at'])) ?><br>
                            <strong>Last Updated:</strong> <?= date('M d, Y', strtotime($member['updated_at'])) ?>
                        </p>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="#" class="btn btn-warning btn-sm" data-toggle="modal" data-target="#resetPasswordModal">
                            <i class="fas fa-key mr-2"></i> Reset Password
                        </a>
                        <a href="#" class="btn btn-danger btn-sm ml-2" data-toggle="modal" data-target="#deleteMemberModal">
                            <i class="fas fa-trash mr-2"></i> Delete Account
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Member Statistics Card -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Member Statistics</h6>
                </div>
                <div class="card-body">
                    <div class="row no-gutters align-items-center mb-3">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Contributions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['contributions'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    
                    <div class="row no-gutters align-items-center mb-3">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Savings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['savings'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    
                    <div class="row no-gutters align-items-center mb-3">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Active Loans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active_loans'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    
                    <div class="row no-gutters align-items-center mb-3">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Outstanding Loan Amount</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['loan_amount'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                    
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Meetings Attended</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['meetings_attended'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8 col-lg-7">
            <!-- Recent Contributions -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Contributions</h6>
                    <a href="contributions.php?member_id=<?= $member_id ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_contributions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                        <th>Payment Method</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_contributions as $contribution): ?>
                                        <tr>
                                            <td><?= $contribution['id'] ?></td>
                                            <td>KES <?= number_format($contribution['amount'], 2) ?></td>
                                            <td><?= date('M d, Y', strtotime($contribution['contribution_date'])) ?></td>
                                            <td><?= ucfirst($contribution['payment_method']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $contribution['status'] == 'approved' ? 'success' : ($contribution['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($contribution['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No contributions found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Loans -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Loans</h6>
                    <a href="loans.php?member_id=<?= $member_id ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_loans) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Amount</th>
                                        <th>Purpose</th>
                                        <th>Application Date</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_loans as $loan): ?>
                                        <tr>
                                            <td><?= $loan['id'] ?></td>
                                            <td>KES <?= number_format($loan['amount'], 2) ?></td>
                                            <td><?= htmlspecialchars($loan['purpose']) ?></td>
                                            <td><?= date('M d, Y', strtotime($loan['application_date'])) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $loan['status'] == 'approved' ? 'success' : ($loan['status'] == 'pending' ? 'warning' : ($loan['status'] == 'active' ? 'info' : 'danger')) ?>">
                                                    <?= ucfirst($loan['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No loans found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Recent Meeting Attendance -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Meeting Attendance</h6>
                    <a href="meetings.php?member_id=<?= $member_id ?>" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_attendance) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Meeting</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_attendance as $attendance): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($attendance['title']) ?></td>
                                            <td><?= date('M d, Y', strtotime($attendance['meeting_date'])) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $attendance['status'] == 'present' ? 'success' : ($attendance['status'] == 'absent' ? 'danger' : 'warning') ?>">
                                                    <?= ucfirst($attendance['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($attendance['comments'] ?? 'N/A') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No meeting attendance records found.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Activity Timeline -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Activity Timeline</h6>
                </div>
                <div class="card-body">
                    <?php
                    // Fetch recent activity for this member
                    $stmt = $pdo->prepare("
                        (SELECT 'contribution' as type, id, amount, contribution_date as date, status, NULL as purpose
                        FROM contributions
                        WHERE user_id = ?)
                        UNION
                        (SELECT 'loan' as type, id, amount, application_date as date, status, purpose
                        FROM loans
                        WHERE user_id = ?)
                        UNION
                        (SELECT 'saving' as type, id, amount, transaction_date as date, 'completed' as status, NULL as purpose
                        FROM savings
                        WHERE user_id = ?)
                        ORDER BY date DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$member_id, $member_id, $member_id]);
                    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (count($activities) > 0):
                    ?>
                        <div class="timeline">
                            <?php foreach ($activities as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker 
                                        <?php 
                                        if ($activity['type'] == 'contribution') echo 'bg-primary';
                                        elseif ($activity['type'] == 'loan') echo 'bg-warning';
                                        else echo 'bg-success';
                                        ?>">
                                    </div>
                                    <div class="timeline-content">
                                        <h6 class="mb-0">
                                            <?php 
                                            if ($activity['type'] == 'contribution') echo 'Made a contribution';
                                            elseif ($activity['type'] == 'loan') echo 'Applied for a loan';
                                            else echo 'Made a savings deposit';
                                            ?>
                                        </h6>
                                        <p class="mb-0">
                                            <strong>Amount:</strong> KES <?= number_format($activity['amount'], 2) ?><br>
                                            <strong>Date:</strong> <?= date('M d, Y', strtotime($activity['date'])) ?><br>
                                            <strong>Status:</strong> 
                                            <span class="badge badge-<?= $activity['status'] == 'approved' || $activity['status'] == 'completed' ? 'success' : ($activity['status'] == 'pending' ? 'warning' : ($activity['status'] == 'active' ? 'info' : 'danger')) ?>">
                                                <?= ucfirst($activity['status']) ?>
                                            </span>
                                            <?php if (!empty($activity['purpose'])): ?>
                                                <br><strong>Purpose:</strong> <?= htmlspecialchars($activity['purpose']) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No activity records found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Member Modal -->
<div class="modal fade" id="editMemberModal" tabindex="-1" role="dialog" aria-labelledby="editMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editMemberModalLabel">Edit Member</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="member_actions.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_member">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">
                    
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" value="<?= htmlspecialchars($member['name']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($member['email']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($member['phone']) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" <?= $member['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $member['status'] == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $member['status'] == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="resetPasswordModalLabel">Reset Password</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="member_actions.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                        <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i> This will reset the member's password. They will need to use this new password to log in.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Member Modal -->
<div class="modal fade" id="deleteMemberModal" tabindex="-1" role="dialog" aria-labelledby="deleteMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMemberModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong><?= htmlspecialchars($member['name']) ?></strong>?</p>
                <p class="text-danger">This action cannot be undone. All associated data (contributions, loans, etc.) will also be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <form action="member_actions.php" method="post">
                    <input type="hidden" name="action" value="delete_member">
                    <input type="hidden" name="member_id" value="<?= $member_id ?>">
                    <button type="submit" class="btn btn-danger">Delete Member</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Custom styles for timeline -->
<style>
.timeline {
    position: relative;
    padding-left: 30px;
}
.timeline-item {
    position: relative;
    padding-bottom: 20px;
}
.timeline-marker {
    position: absolute;
    left: -30px;
    width: 15px;
    height: 15px;
    border-radius: 50%;
}
.timeline-content {
    position: relative;
    padding-bottom: 10px;
    border-bottom: 1px solid #e3e6f0;
}
</style>

<?php include 'footer.php'; ?>