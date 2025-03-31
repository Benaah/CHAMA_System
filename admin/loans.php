<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$member_filter = isset($_GET['member_id']) ? $_GET['member_id'] : null;

// Handle loan status updates
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $loan_id = $_GET['id'];
    $new_status = $_GET['status'];
    
    // Validate status
    $valid_statuses = ['pending', 'approved', 'active', 'rejected', 'paid', 'defaulted'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE loans SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $loan_id])) {
            $_SESSION['admin_success'] = "Loan status updated successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to update loan status.";
        }
    } else {
        $_SESSION['admin_error'] = "Invalid status value.";
    }
    
    // Redirect to remove GET parameters
    header("Location: loans.php" . ($status_filter != 'all' ? "?status=$status_filter" : ""));
    exit();
}

// Build query based on filters
$query = "
    SELECT l.*, u.name as member_name 
    FROM loans l
    JOIN users u ON l.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($status_filter != 'all') {
    $query .= " AND l.status = ?";
    $params[] = $status_filter;
}

if ($member_filter) {
    $query .= " AND l.user_id = ?";
    $params[] = $member_filter;
}

$query .= " ORDER BY l.loan_date DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$loans = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get loan statistics
$stats = [
    'total_amount' => 0,
    'pending_count' => 0,
    'approved_count' => 0,
    'active_count' => 0,
    'paid_count' => 0,
    'defaulted_count' => 0
];

$stmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN status = 'active' OR status = 'approved' THEN amount ELSE 0 END) as active_amount,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
        SUM(CASE WHEN status = 'defaulted' THEN 1 ELSE 0 END) as defaulted_count
    FROM loans
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$stats['total_amount'] = $result['active_amount'] ?? 0;
$stats['pending_count'] = $result['pending_count'] ?? 0;
$stats['approved_count'] = $result['approved_count'] ?? 0;
$stats['active_count'] = $result['active_count'] ?? 0;
$stats['paid_count'] = $result['paid_count'] ?? 0;
$stats['defaulted_count'] = $result['defaulted_count'] ?? 0;

// Get members for dropdown
$stmt = $pdo->query("SELECT id, name FROM users WHERE user_role = 'member' ORDER BY name");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">            </h1>
        <div>
            <a href="#" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addLoanModal">
                <i class="fas fa-plus fa-sm text-white-50"></i> Add Loan
            </a>
            <a href="#" class="btn btn-primary btn-sm ml-2" data-toggle="modal" data-target="#exportModal">
                <i class="fas fa-download fa-sm text-white-50"></i> Export
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

    <!-- Content Row -->
    <div class="row">
        <!-- Total Active Loans Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Active Loans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['total_amount'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Loans Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Loans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['pending_count'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Loans Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Active Loans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active_count'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-sync fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Defaulted Loans Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Defaulted Loans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['defaulted_count'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filters</h6>
        </div>
        <div class="card-body">
            <form method="get" class="form-inline">
                <div class="form-group mb-2 mr-3">
                    <label for="status" class="mr-2">Status:</label>
                    <select class="form-control" id="status" name="status" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        <option value="defaulted" <?= $status_filter == 'defaulted' ? 'selected' : '' ?>>Defaulted</option>
                    </select>
                </div>
                
                <div class="form-group mb-2 mr-3">
                    <label for="member_id" class="mr-2">Member:</label>
                    <select class="form-control" id="member_id" name="member_id" onchange="this.form.submit()">
                        <option value="">All Members</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?= $member['id'] ?>" <?= $member_filter == $member['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($member['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($status_filter != 'all' || $member_filter): ?>
                    <a href="loans.php" class="btn btn-secondary mb-2">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Loans Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Loan List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="loansTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Interest</th>
                            <th>Duration</th>
                            <th>Application Date</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loans as $loan): ?>
                            <tr>
                                <td><?= $loan['id'] ?></td>
                                <td>
                                    <a href="member_details.php?id=<?= $loan['user_id'] ?>">
                                        <?= htmlspecialchars($loan['member_name']) ?>
                                    </a>
                                </td>
                                <td>KES <?= number_format($loan['amount'], 2) ?></td>
                                <td><?= $loan['interest_rate'] ?>%</td>
                                <td><?= $loan['duration'] ?> months</td>
                                <td><?= date('M d, Y', strtotime($loan['application_date'])) ?></td>
                                <td><?= htmlspecialchars($loan['purpose']) ?></td>
                                <td>
                                    <span class="badge badge-<?= 
                                        $loan['status'] == 'approved' ? 'success' : 
                                        ($loan['status'] == 'pending' ? 'warning' : 
                                        ($loan['status'] == 'active' ? 'info' : 
                                        ($loan['status'] == 'paid' ? 'primary' : 
                                        ($loan['status'] == 'defaulted' ? 'danger' : 'secondary')))) 
                                    ?>">
                                        <?= ucfirst($loan['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Actions
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#viewLoanModal<?= $loan['id'] ?>">
                                                <i class="fas fa-eye fa-sm fa-fw mr-2 text-gray-400"></i> View Details
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editLoanModal<?= $loan['id'] ?>">
                                                <i class="fas fa-edit fa-sm fa-fw mr-2 text-gray-400"></i> Edit
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#repaymentModal<?= $loan['id'] ?>">
                                                <i class="fas fa-money-bill-wave fa-sm fa-fw mr-2 text-gray-400"></i> Record Repayment
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            
                                            <?php if ($loan['status'] == 'pending'): ?>
                                                <a class="dropdown-item" href="loans.php?action=update_status&id=<?= $loan['id'] ?>&status=approved">
                                                    <i class="fas fa-check fa-sm fa-fw mr-2 text-success"></i> Approve
                                                </a>
                                                <a class="dropdown-item" href="loans.php?action=update_status&id=<?= $loan['id'] ?>&status=rejected">
                                                    <i class="fas fa-times fa-sm fa-fw mr-2 text-danger"></i> Reject
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($loan['status'] == 'approved'): ?>
                                                <a class="dropdown-item" href="loans.php?action=update_status&id=<?= $loan['id'] ?>&status=active">
                                                    <i class="fas fa-play fa-sm fa-fw mr-2 text-info"></i> Mark as Active
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($loan['status'] == 'active'): ?>
                                                <a class="dropdown-item" href="loans.php?action=update_status&id=<?= $loan['id'] ?>&status=paid">
                                                    <i class="fas fa-check-circle fa-sm fa-fw mr-2 text-success"></i> Mark as Paid
                                                </a>
                                                <a class="dropdown-item" href="loans.php?action=update_status&id=<?= $loan['id'] ?>&status=defaulted">
                                                    <i class="fas fa-exclamation-circle fa-sm fa-fw mr-2 text-danger"></i> Mark as Defaulted
                                                </a>
                                            <?php endif; ?>
                                            
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#deleteLoanModal<?= $loan['id'] ?>">
                                                <i class="fas fa-trash fa-sm fa-fw mr-2 text-danger"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- View Loan Modal -->
                            <div class="modal fade" id="viewLoanModal<?= $loan['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="viewLoanModalLabel<?= $loan['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="viewLoanModalLabel<?= $loan['id'] ?>">Loan Details</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="font-weight-bold">Loan Information</h6>
                                                    <p><strong>ID:</strong> <?= $loan['id'] ?></p>
                                                    <p><strong>Member:</strong> <?= htmlspecialchars($loan['member_name']) ?></p>
                                                    <p><strong>Amount:</strong> KES <?= number_format($loan['amount'], 2) ?></p>
                                                    <p><strong>Interest Rate:</strong> <?= $loan['interest_rate'] ?>%</p>
                                                    <p><strong>Duration:</strong> <?= $loan['duration'] ?> months</p>
                                                    <p><strong>Total Repayable:</strong> KES <?= number_format($loan['amount'] * (1 + $loan['interest_rate']/100), 2) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="font-weight-bold">Status Information</h6>
                                                    <p><strong>Status:</strong> 
                                                        <span class="badge badge-<?= 
                                                            $loan['status'] == 'approved' ? 'success' : 
                                                            ($loan['status'] == 'pending' ? 'warning' : 
                                                            ($loan['status'] == 'active' ? 'info' : 
                                                            ($loan['status'] == 'paid' ? 'primary' : 
                                                            ($loan['status'] == 'defaulted' ? 'danger' : 'secondary')))) 
                                                        ?>">
                                                            <?= ucfirst($loan['status']) ?>
                                                        </span>
                                                    </p>
                                                    <p><strong>Application Date:</strong> <?= date('M d, Y', strtotime($loan['application_date'])) ?></p>
                                                    <p><strong>Approval Date:</strong> <?= $loan['approval_date'] ? date('M d, Y', strtotime($loan['approval_date'])) : 'N/A' ?></p>
                                                    <p><strong>Disbursement Date:</strong> <?= $loan['disbursement_date'] ? date('M d, Y', strtotime($loan['disbursement_date'])) : 'N/A' ?></p>
                                                    <p><strong>Due Date:</strong> <?= $loan['due_date'] ? date('M d, Y', strtotime($loan['due_date'])) : 'N/A' ?></p>
                                                </div>
                                            </div>
                                            
                                            <hr>
                                            
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <h6 class="font-weight-bold">Purpose</h6>
                                                    <p><?= nl2br(htmlspecialchars($loan['purpose'])) ?></p>
                                                </div>
                                            </div>
                                            
                                            <?php
                                            // Fetch repayments for this loan
                                            $repayment_stmt = $pdo->prepare("SELECT * FROM loan_repayments WHERE loan_id = ? ORDER BY payment_date DESC");
                                            $repayment_stmt->execute([$loan['id']]);
                                            $repayments = $repayment_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            
                                            // Calculate total repaid
                                            $total_repaid = 0;
                                            foreach ($repayments as $repayment) {
                                                $total_repaid += $repayment['amount'];
                                            }
                                            
                                            // Calculate remaining balance
                                            $total_due = $loan['amount'] * (1 + $loan['interest_rate']/100);
                                            $remaining_balance = $total_due - $total_repaid;
                                            ?>
                                            
                                            <hr>
                                            
                                            <div class="row">
                                                <div class="col-md-12">
                                                    <h6 class="font-weight-bold">Repayment Summary</h6>
                                                    <div class="row">
                                                        <div class="col-md-4">
                                                            <p><strong>Total Due:</strong> KES <?= number_format($total_due, 2) ?></p>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <p><strong>Total Repaid:</strong> KES <?= number_format($total_repaid, 2) ?></p>
                                                        </div>
                                                        <div class="col-md-4">
                                                            <p><strong>Remaining Balance:</strong> KES <?= number_format($remaining_balance, 2) ?></p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <?php if (count($repayments) > 0): ?>
                                                <div class="table-responsive mt-3">
                                                    <table class="table table-bordered table-sm">
                                                        <thead>
                                                            <tr>
                                                                <th>Date</th>
                                                                <th>Amount</th>
                                                                <th>Method</th>
                                                                <th>Reference</th>
                                                                <th>Notes</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($repayments as $repayment): ?>
                                                                <tr>
                                                                    <td><?= date('M d, Y', strtotime($repayment['payment_date'])) ?></td>
                                                                    <td>KES <?= number_format($repayment['amount'], 2) ?></td>
                                                                    <td><?= ucfirst($repayment['payment_method']) ?></td>
                                                                    <td><?= htmlspecialchars($repayment['reference_number'] ?? 'N/A') ?></td>
                                                                    <td><?= htmlspecialchars($repayment['notes'] ?? '') ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted mt-3">No repayments recorded yet.</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#repaymentModal<?= $loan['id'] ?>" data-dismiss="modal">
                                                Record Repayment
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Loan Modal -->
                            <div class="modal fade" id="editLoanModal<?= $loan['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="editLoanModalLabel<?= $loan['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editLoanModalLabel<?= $loan['id'] ?>">Edit Loan</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form action="loan_actions.php" method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit_loan">
                                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                
                                                <div class="form-group">
                                                    <label for="member_id<?= $loan['id'] ?>">Member</label>
                                                    <select class="form-control" id="member_id<?= $loan['id'] ?>" name="member_id" required>
                                                        <?php foreach ($members as $member): ?>
                                                            <option value="<?= $member['id'] ?>" <?= $loan['user_id'] == $member['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($member['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="amount<?= $loan['id'] ?>">Loan Amount (KES)</label>
                                                    <input type="number" class="form-control" id="amount<?= $loan['id'] ?>" name="amount" value="<?= $loan['amount'] ?>" min="0" step="0.01" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="interest_rate<?= $loan['id'] ?>">Interest Rate (%)</label>
                                                    <input type="number" class="form-control" id="interest_rate<?= $loan['id'] ?>" name="interest_rate" value="<?= $loan['interest_rate'] ?>" min="0" step="0.01" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="duration<?= $loan['id'] ?>">Duration (months)</label>
                                                    <input type="number" class="form-control" id="duration<?= $loan['id'] ?>" name="duration" value="<?= $loan['duration'] ?>" min="1" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="application_date<?= $loan['id'] ?>">Application Date</label>
                                                    <input type="date" class="form-control" id="application_date<?= $loan['id'] ?>" name="application_date" value="<?= date('Y-m-d', strtotime($loan['application_date'])) ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="purpose<?= $loan['id'] ?>">Purpose</label>
                                                    <textarea class="form-control" id="purpose<?= $loan['id'] ?>" name="purpose" rows="3" required><?= htmlspecialchars($loan['purpose']) ?></textarea>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="status<?= $loan['id'] ?>">Status</label>
                                                    <select class="form-control" id="status<?= $loan['id'] ?>" name="status" required>
                                                        <option value="approved" <?= $loan['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                                        <option value="active" <?= $loan['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="paid" <?= $loan['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                                                        <option value="rejected" <?= $loan['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                        <option value="defaulted" <?= $loan['status'] == 'defaulted' ? 'selected' : '' ?>>Defaulted</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="approval_date<?= $loan['id'] ?>">Approval Date</label>
                                                    <input type="date" class="form-control" id="approval_date<?= $loan['id'] ?>" name="approval_date" value="<?= $loan['approval_date'] ? date('Y-m-d', strtotime($loan['approval_date'])) : '' ?>">
                                                    <small class="form-text text-muted">Leave blank if not approved yet</small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="disbursement_date<?= $loan['id'] ?>">Disbursement Date</label>
                                                    <input type="date" class="form-control" id="disbursement_date<?= $loan['id'] ?>" name="disbursement_date" value="<?= $loan['disbursement_date'] ? date('Y-m-d', strtotime($loan['disbursement_date'])) : '' ?>">
                                                    <small class="form-text text-muted">Leave blank if not disbursed yet</small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="due_date<?= $loan['id'] ?>">Due Date</label>
                                                    <input type="date" class="form-control" id="due_date<?= $loan['id'] ?>" name="due_date" value="<?= $loan['due_date'] ? date('Y-m-d', strtotime($loan['due_date'])) : '' ?>">
                                                    <small class="form-text text-muted">Final repayment date</small>
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
                            
                            <!-- Record Repayment Modal -->
                            <div class="modal fade" id="repaymentModal<?= $loan['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="repaymentModalLabel<?= $loan['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="repaymentModalLabel<?= $loan['id'] ?>">Record Loan Repayment</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form action="loan_actions.php" method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="add_repayment">
                                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                
                                                <div class="alert alert-info">
                                                    <p class="mb-0"><strong>Loan Amount:</strong> KES <?= number_format($loan['amount'], 2) ?></p>
                                                    <p class="mb-0"><strong>Total Due:</strong> KES <?= number_format($total_due, 2) ?></p>
                                                    <p class="mb-0"><strong>Already Paid:</strong> KES <?= number_format($total_repaid, 2) ?></p>
                                                    <p class="mb-0"><strong>Remaining Balance:</strong> KES <?= number_format($remaining_balance, 2) ?></p>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="amount<?= $loan['id'] ?>_repayment">Payment Amount (KES)</label>
                                                    <input type="number" class="form-control" id="amount<?= $loan['id'] ?>_repayment" name="amount" min="0" step="0.01" max="<?= $remaining_balance ?>" required>
                                                    <small class="form-text text-muted">Maximum amount: KES <?= number_format($remaining_balance, 2) ?></small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="payment_date<?= $loan['id'] ?>">Payment Date</label>
                                                    <input type="date" class="form-control" id="payment_date<?= $loan['id'] ?>" name="payment_date" value="<?= date('Y-m-d') ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="payment_method<?= $loan['id'] ?>">Payment Method</label>
                                                    <select class="form-control" id="payment_method<?= $loan['id'] ?>" name="payment_method" required>
                                                        <option value="cash">Cash</option>
                                                        <option value="mpesa">M-Pesa</option>
                                                        <option value="bank">Bank Transfer</option>
                                                        <option value="cheque">Cheque</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="reference_number<?= $loan['id'] ?>_repayment">Reference Number</label>
                                                    <input type="text" class="form-control" id="reference_number<?= $loan['id'] ?>_repayment" name="reference_number">
                                                    <small class="form-text text-muted">Transaction ID, receipt number, or other reference</small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="notes<?= $loan['id'] ?>_repayment">Notes</label>
                                                    <textarea class="form-control" id="notes<?= $loan['id'] ?>_repayment" name="notes" rows="3"></textarea>
                                                </div>
                                                
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="mark_paid<?= $loan['id'] ?>" name="mark_paid" value="1">
                                                    <label class="form-check-label" for="mark_paid<?= $loan['id'] ?>">
                                                        Mark loan as fully paid if this is the final payment
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Record Payment</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Delete Loan Modal -->
                            <div class="modal fade" id="deleteLoanModal<?= $loan['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="deleteLoanModalLabel<?= $loan['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteLoanModalLabel<?= $loan['id'] ?>">Confirm Delete</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete this loan?</p>
                                            <p><strong>Member:</strong> <?= htmlspecialchars($loan['member_name']) ?></p>
                                            <p><strong>Amount:</strong> KES <?= number_format($loan['amount'], 2) ?></p>
                                            <p><strong>Status:</strong> <?= ucfirst($loan['status']) ?></p>
                                            <p class="text-danger">This action cannot be undone and will also delete all associated repayment records.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <form action="loan_actions.php" method="post">
                                                <input type="hidden" name="action" value="delete_loan">
                                                <input type="hidden" name="loan_id" value="<?= $loan['id'] ?>">
                                                <button type="submit" class="btn btn-danger">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Loan Modal -->
<div class="modal fade" id="addLoanModal" tabindex="-1" role="dialog" aria-labelledby="addLoanModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addLoanModalLabel">Add New Loan</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="loan_actions.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_loan">
                    
                    <div class="form-group">
                        <label for="member_id">Member</label>
                        <select class="form-control" id="member_id" name="member_id" required>
                            <option value="">Select Member</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Loan Amount (KES)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="interest_rate">Interest Rate (%)</label>
                        <input type="number" class="form-control" id="interest_rate" name="interest_rate" min="0" step="0.01" value="10" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="duration">Duration (months)</label>
                        <input type="number" class="form-control" id="duration" name="duration" min="1" value="12" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="application_date">Application Date</label>
                        <input type="date" class="form-control" id="application_date" name="application_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="purpose">Purpose</label>
                        <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="active">Active</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="approval_date">Approval Date</label>
                        <input type="date" class="form-control" id="approval_date" name="approval_date">
                        <small class="form-text text-muted">Leave blank if not approved yet</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="disbursement_date">Disbursement Date</label>
                        <input type="date" class="form-control" id="disbursement_date" name="disbursement_date">
                        <small class="form-text text-muted">Leave blank if not disbursed yet</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date">Due Date</label>
                        <input type="date" class="form-control" id="due_date" name="due_date">
                        <small class="form-text text-muted">Final repayment date</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Loan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Loans</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="export.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="export_type" value="loans">
                    
                    <div class="form-group">
                        <label for="export_status">Status</label>
                        <select class="form-control" id="export_status" name="status">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="active">Active</option>
                            <option value="paid">Paid</option>
                            <option value="rejected">Rejected</option>
                            <option value="defaulted">Defaulted</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="export_member">Member</label>
                        <select class="form-control" id="export_member" name="member_id">
                            <option value="">All Members</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="export_date_from">Date From</label>
                        <input type="date" class="form-control" id="export_date_from" name="date_from">
                    </div>
                    
                    <div class="form-group">
                        <label for="export_date_to">Date To</label>
                        <input type="date" class="form-control" id="export_date_to" name="date_to" value="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="export_format">Format</label>
                        <select class="form-control" id="export_format" name="format">
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Export</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#loansTable').DataTable({
        order: [[5, 'desc']], // Sort by application date column in descending order
        pageLength: 25,
        responsive: true
    });
    
    // Show/hide date fields based on status selection in the add/edit modals
    $('#status').change(function() {
        if ($(this).val() == 'approved') {
            $('#approval_date').parent().show();
        } else {
            $('#approval_date').parent().hide();
        }
        
        if ($(this).val() == 'active') {
            $('#disbursement_date').parent().show();
            $('#due_date').parent().show();
        } else {
            $('#disbursement_date').parent().hide();
            $('#due_date').parent().hide();
        }
    });
    
    // Trigger the change event on page load
    $('#status').trigger('change');
});
</script>

<?php include 'footer.php'; ?>