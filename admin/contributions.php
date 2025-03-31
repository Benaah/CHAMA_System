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

// Handle contribution status updates
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $contribution_id = $_GET['id'];
    $new_status = $_GET['status'];
    
    // Validate status
    $valid_statuses = ['pending', 'approved', 'rejected'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE contributions SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $contribution_id])) {
            $_SESSION['admin_success'] = "Contribution status updated successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to update contribution status.";
        }
    } else {
        $_SESSION['admin_error'] = "Invalid status value.";
    }
    
    // Redirect to remove GET parameters
    header("Location: contributions.php" . ($status_filter != 'all' ? "?status=$status_filter" : ""));
    exit();
}

// Build query based on filters
$query = "
    SELECT c.*, u.name as member_name 
    FROM contributions c
    JOIN users u ON c.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($status_filter != 'all') {
    $query .= " AND c.status = ?";
    $params[] = $status_filter;
}

if ($member_filter) {
    $query .= " AND c.user_id = ?";
    $params[] = $member_filter;
}

$query .= " ORDER BY c.contribution_date DESC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get contribution statistics
$stats = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0
];

$stmt = $pdo->query("SELECT status, COUNT(*) as count, SUM(amount) as total FROM contributions GROUP BY status");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($results as $result) {
    $stats[$result['status']] = [
        'count' => $result['count'],
        'amount' => $result['total']
    ];
    $stats['total'] += $result['total'];
}

// Get members for dropdown
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'member' ORDER BY name");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Contributions</h1>
        <div>
            <a href="#" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addContributionModal">
                <i class="fas fa-plus fa-sm text-white-50"></i> Add Contribution
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
        <!-- Total Contributions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Contributions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['total'] ?? 0, 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approved Contributions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Approved Contributions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['approved']['amount'] ?? 0, 2) ?></div>
                            <div class="text-xs text-gray-600"><?= $stats['approved']['count'] ?? 0 ?> contributions</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Contributions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Contributions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['pending']['amount'] ?? 0, 2) ?></div>
                            <div class="text-xs text-gray-600"><?= $stats['pending']['count'] ?? 0 ?> contributions</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rejected Contributions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Rejected Contributions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['rejected']['amount'] ?? 0, 2) ?></div>
                            <div class="text-xs text-gray-600"><?= $stats['rejected']['count'] ?? 0 ?> contributions</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-times-circle fa-2x text-gray-300"></i>
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
                        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>Rejected</option>
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
                    <a href="contributions.php" class="btn btn-secondary mb-2">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Contributions Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Contribution List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="contributionsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th>Payment Method</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($contributions as $contribution): ?>
                            <tr>
                                <td><?= $contribution['id'] ?></td>
                                <td>
                                    <a href="member_details.php?id=<?= $contribution['user_id'] ?>">
                                        <?= htmlspecialchars($contribution['member_name']) ?>
                                    </a>
                                </td>
                                <td>KES <?= number_format($contribution['amount'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($contribution['contribution_date'])) ?></td>
                                <td><?= ucfirst($contribution['payment_method']) ?></td>
                                <td><?= htmlspecialchars($contribution['reference_number'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="badge badge-<?= $contribution['status'] == 'approved' ? 'success' : ($contribution['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                        <?= ucfirst($contribution['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Actions
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#viewContributionModal<?= $contribution['id'] ?>">
                                                <i class="fas fa-eye fa-sm fa-fw mr-2 text-gray-400"></i> View Details
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editContributionModal<?= $contribution['id'] ?>">
                                                <i class="fas fa-edit fa-sm fa-fw mr-2 text-gray-400"></i> Edit
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <?php if ($contribution['status'] != 'approved'): ?>
                                                <a class="dropdown-item" href="contributions.php?action=update_status&id=<?= $contribution['id'] ?>&status=approved">
                                                    <i class="fas fa-check fa-sm fa-fw mr-2 text-success"></i> Approve
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($contribution['status'] != 'pending'): ?>
                                                <a class="dropdown-item" href="contributions.php?action=update_status&id=<?= $contribution['id'] ?>&status=pending">
                                                    <i class="fas fa-clock fa-sm fa-fw mr-2 text-warning"></i> Mark as Pending
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($contribution['status'] != 'rejected'): ?>
                                                <a class="dropdown-item" href="contributions.php?action=update_status&id=<?= $contribution['id'] ?>&status=rejected">
                                                    <i class="fas fa-times fa-sm fa-fw mr-2 text-danger"></i> Reject
                                                </a>
                                            <?php endif; ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#deleteContributionModal<?= $contribution['id'] ?>">
                                                <i class="fas fa-trash fa-sm fa-fw mr-2 text-danger"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- View Contribution Modal -->
                            <div class="modal fade" id="viewContributionModal<?= $contribution['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="viewContributionModalLabel<?= $contribution['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="viewContributionModalLabel<?= $contribution['id'] ?>">Contribution Details</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <p><strong>ID:</strong> <?= $contribution['id'] ?></p>
                                                    <p><strong>Member:</strong> <?= htmlspecialchars($contribution['member_name']) ?></p>
                                                    <p><strong>Amount:</strong> KES <?= number_format($contribution['amount'], 2) ?></p>
                                                    <p><strong>Date:</strong> <?= date('M d, Y', strtotime($contribution['contribution_date'])) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <p><strong>Payment Method:</strong> <?= ucfirst($contribution['payment_method']) ?></p>
                                                    <p><strong>Reference Number:</strong> <?= htmlspecialchars($contribution['reference_number'] ?? 'N/A') ?></p>
                                                    <p><strong>Status:</strong> 
                                                        <span class="badge badge-<?= $contribution['status'] == 'approved' ? 'success' : ($contribution['status'] == 'pending' ? 'warning' : 'danger') ?>">
                                                            <?= ucfirst($contribution['status']) ?>
                                                        </span>
                                                    </p>
                                                    <p><strong>Created:</strong> <?= date('M d, Y H:i', strtotime($contribution['created_at'])) ?></p>
                                                </div>
                                            </div>
                                            <?php if (!empty($contribution['notes'])): ?>
                                                <div class="mt-3">
                                                    <h6>Notes:</h6>
                                                    <p><?= nl2br(htmlspecialchars($contribution['notes'])) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Edit Contribution Modal -->
                            <div class="modal fade" id="editContributionModal<?= $contribution['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="editContributionModalLabel<?= $contribution['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editContributionModalLabel<?= $contribution['id'] ?>">Edit Contribution</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form action="contribution_actions.php" method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit_contribution">
                                                <input type="hidden" name="contribution_id" value="<?= $contribution['id'] ?>">
                                                
                                                <div class="form-group">
                                                    <label for="member_id<?= $contribution['id'] ?>">Member</label>
                                                    <select class="form-control" id="member_id<?= $contribution['id'] ?>" name="member_id" required>
                                                        <?php foreach ($members as $member): ?>
                                                            <option value="<?= $member['id'] ?>" <?= $contribution['user_id'] == $member['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($member['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="amount<?= $contribution['id'] ?>">Amount (KES)</label>
                                                    <input type="number" class="form-control" id="amount<?= $contribution['id'] ?>" name="amount" value="<?= $contribution['amount'] ?>" min="0" step="0.01" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="contribution_date<?= $contribution['id'] ?>">Contribution Date</label>
                                                    <input type="date" class="form-control" id="contribution_date<?= $contribution['id'] ?>" name="contribution_date" value="<?= date('Y-m-d', strtotime($contribution['contribution_date'])) ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="payment_method<?= $contribution['id'] ?>">Payment Method</label>
                                                    <select class="form-control" id="payment_method<?= $contribution['id'] ?>" name="payment_method" required>
                                                        <option value="cash" <?= $contribution['payment_method'] == 'cash' ? 'selected' : '' ?>>Cash</option>
                                                        <option value="mpesa" <?= $contribution['payment_method'] == 'mpesa' ? 'selected' : '' ?>>M-Pesa</option>
                                                        <option value="bank" <?= $contribution['payment_method'] == 'bank' ? 'selected' : '' ?>>Bank Transfer</option>
                                                        <option value="cheque" <?= $contribution['payment_method'] == 'cheque' ? 'selected' : '' ?>>Cheque</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="reference_number<?= $contribution['id'] ?>">Reference Number</label>
                                                    <input type="text" class="form-control" id="reference_number<?= $contribution['id'] ?>" name="reference_number" value="<?= htmlspecialchars($contribution['reference_number'] ?? '') ?>">
                                                    <small class="form-text text-muted">Transaction ID, receipt number, or other reference</small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="status<?= $contribution['id'] ?>">Status</label>
                                                    <select class="form-control" id="status<?= $contribution['id'] ?>" name="status" required>
                                                        <option value="pending" <?= $contribution['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="approved" <?= $contribution['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                                        <option value="rejected" <?= $contribution['status'] == 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="notes<?= $contribution['id'] ?>">Notes</label>
                                                    <textarea class="form-control" id="notes<?= $contribution['id'] ?>" name="notes" rows="3"><?= htmlspecialchars($contribution['notes'] ?? '') ?></textarea>
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
                            
                            <!-- Delete Contribution Modal -->
                            <div class="modal fade" id="deleteContributionModal<?= $contribution['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="deleteContributionModalLabel<?= $contribution['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteContributionModalLabel<?= $contribution['id'] ?>">Confirm Delete</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete this contribution?</p>
                                            <p><strong>Member:</strong> <?= htmlspecialchars($contribution['member_name']) ?></p>
                                            <p><strong>Amount:</strong> KES <?= number_format($contribution['amount'], 2) ?></p>
                                            <p><strong>Date:</strong> <?= date('M d, Y', strtotime($contribution['contribution_date'])) ?></p>
                                            <p class="text-danger">This action cannot be undone.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <form action="contribution_actions.php" method="post">
                                                <input type="hidden" name="action" value="delete_contribution">
                                                <input type="hidden" name="contribution_id" value="<?= $contribution['id'] ?>">
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

<!-- Add Contribution Modal -->
<div class="modal fade" id="addContributionModal" tabindex="-1" role="dialog" aria-labelledby="addContributionModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addContributionModalLabel">Add New Contribution</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="contribution_actions.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_contribution">
                    
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
                        <label for="amount">Amount (KES)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="contribution_date">Contribution Date</label>
                        <input type="date" class="form-control" id="contribution_date" name="contribution_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_method">Payment Method</label>
                        <select class="form-control" id="payment_method" name="payment_method" required>
                            <option value="cash">Cash</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank">Bank Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="reference_number">Reference Number</label>
                        <input type="text" class="form-control" id="reference_number" name="reference_number">
                        <small class="form-text text-muted">Transaction ID, receipt number, or other reference</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Contribution</button>
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
                <h5 class="modal-title" id="exportModalLabel">Export Contributions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="export.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="export_type" value="contributions">
                    
                    <div class="form-group">
                        <label for="export_status">Status</label>
                        <select class="form-control" id="export_status" name="status">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
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
    $('#contributionsTable').DataTable({
        order: [[3, 'desc']], // Sort by date column in descending order
        pageLength: 25,
        responsive: true
    });
});
</script>

<?php include 'footer.php'; ?>