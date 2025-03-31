<?php
include '../config.php';
include 'header.php';

// Get status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Handle welfare case status updates
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $case_id = $_GET['id'];
    $new_status = $_GET['status'];
    
    // Validate status
    $valid_statuses = ['pending', 'approved', 'rejected', 'completed'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE welfare_cases SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $case_id])) {
            $_SESSION['admin_success'] = "Welfare case status updated successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to update welfare case status.";
        }
    } else {
        $_SESSION['admin_error'] = "Invalid status value.";
    }
    
    // Redirect to remove GET parameters
    header("Location: welfare.php" . ($status_filter != 'all' ? "?status=$status_filter" : ""));
    exit();
}

// Fetch welfare cases with filter
$query = "
    SELECT w.*, u.name as requester_name, u.email as requester_email, u.phone as requester_phone,
    (SELECT SUM(amount) FROM welfare_contributions WHERE case_id = w.id) as total_contributions
    FROM welfare_cases w
    JOIN users u ON w.user_id = u.id
";

if ($status_filter != 'all') {
    $query .= " WHERE w.status = :status";
}

$query .= " ORDER BY w.created_at DESC";

$stmt = $pdo->prepare($query);

if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}

$stmt->execute();
$welfare_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total welfare fund
$stmt = $pdo->query("SELECT SUM(amount) as total FROM welfare_contributions");
$total_welfare_fund = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Get statistics
$stats = [
    'total_cases' => 0,
    'pending' => 0,
    'approved' => 0,
    'completed' => 0,
    'rejected' => 0
];

$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM welfare_cases GROUP BY status");
$status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($status_counts as $count) {
    $stats['total_cases'] += $count['count'];
    if (isset($stats[$count['status']])) {
        $stats[$count['status']] = $count['count'];
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Welfare Management</h1>
        <div>
            <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addWelfareModal">
                <i class="fas fa-plus fa-sm text-white-50"></i> Add New Case
            </a>
            <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm ml-2" data-toggle="modal" data-target="#exportModal">
                <i class="fas fa-download fa-sm text-white-50"></i> Export Report
            </a>
        </div>
    </div>

    <!-- Overview Cards -->
    <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Welfare Cases</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_cases'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hands-helping fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Cases</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['pending'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Approved/Completed Cases</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['approved'] + $stats['completed'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Total Welfare Fund</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($total_welfare_fund, 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-donate fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Welfare Cases Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Welfare Cases</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="statusFilterDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-filter fa-sm fa-fw text-gray-400"></i> Filter by Status
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="statusFilterDropdown">
                    <div class="dropdown-header">Status Options:</div>
                    <a class="dropdown-item <?= $status_filter == 'all' ? 'active' : '' ?>" href="welfare.php">All Cases</a>
                    <a class="dropdown-item <?= $status_filter == 'pending' ? 'active' : '' ?>" href="welfare.php?status=pending">Pending</a>
                    <a class="dropdown-item <?= $status_filter == 'approved' ? 'active' : '' ?>" href="welfare.php?status=approved">Approved</a>
                    <a class="dropdown-item <?= $status_filter == 'completed' ? 'active' : '' ?>" href="welfare.php?status=completed">Completed</a>
                    <a class="dropdown-item <?= $status_filter == 'rejected' ? 'active' : '' ?>" href="welfare.php?status=rejected">Rejected</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Requester</th>
                            <th>Title</th>
                            <th>Amount Needed</th>
                            <th>Contributions</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($welfare_cases as $case): ?>
                            <tr>
                                <td><?= $case['id'] ?></td>
                                <td>
                                    <div><?= htmlspecialchars($case['requester_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($case['requester_phone']) ?></small>
                                </td>
                                <td>
                                    <a href="#" data-toggle="modal" data-target="#caseDetailsModal<?= $case['id'] ?>"><?= htmlspecialchars($case['title']) ?></a>
                                </td>
                                <td>KES <?= number_format($case['amount_needed'], 2) ?></td>
                                <td>
                                    KES <?= number_format($case['total_contributions'] ?? 0, 2) ?>
                                    <div class="progress mt-1" style="height: 5px;">
                                        <?php 
                                            $percentage = ($case['amount_needed'] > 0) ? min(100, (($case['total_contributions'] ?? 0) / $case['amount_needed']) * 100) : 0;
                                        ?>
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percentage ?>%" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                    <small><?= number_format($percentage, 1) ?>% of target</small>
                                </td>
                                <td>
                                    <?php if ($case['status'] == 'pending'): ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php elseif ($case['status'] == 'approved'): ?>
                                        <span class="badge badge-success">Approved</span>
                                    <?php elseif ($case['status'] == 'completed'): ?>
                                        <span class="badge badge-primary">Completed</span>
                                    <?php elseif ($case['status'] == 'rejected'): ?>
                                        <span class="badge badge-danger">Rejected</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($case['created_at'])) ?></td>
                                <td>
                                    <div class="dropdown no-arrow">
                                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink<?= $case['id'] ?>" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                                        </a>
                                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink<?= $case['id'] ?>">
                                            <div class="dropdown-header">Actions:</div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#caseDetailsModal<?= $case['id'] ?>">
                                                <i class="fas fa-eye fa-sm fa-fw mr-2 text-gray-400"></i> View Details
                                            </a>
                                            <?php if ($case['status'] == 'pending'): ?>
                                                <a class="dropdown-item" href="welfare.php?action=update_status&id=<?= $case['id'] ?>&status=approved<?= $status_filter != 'all' ? '&status=' . $status_filter : '' ?>">
                                                    <i class="fas fa-check fa-sm fa-fw mr-2 text-success"></i> Approve
                                                </a>
                                                <a class="dropdown-item" href="welfare.php?action=update_status&id=<?= $case['id'] ?>&status=rejected<?= $status_filter != 'all' ? '&status=' . $status_filter : '' ?>">
                                                    <i class="fas fa-times fa-sm fa-fw mr-2 text-danger"></i> Reject
                                                </a>
                                            <?php elseif ($case['status'] == 'approved'): ?>
                                                <a class="dropdown-item" href="welfare.php?action=update_status&id=<?= $case['id'] ?>&status=completed<?= $status_filter != 'all' ? '&status=' . $status_filter : '' ?>">
                                                    <i class="fas fa-check-double fa-sm fa-fw mr-2 text-primary"></i> Mark as Completed
                                                </a>
                                            <?php endif; ?>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#addContributionModal<?= $case['id'] ?>">
                                                <i class="fas fa-donate fa-sm fa-fw mr-2 text-gray-400"></i> Add Contribution
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" onclick="return confirmDelete('welfare.php?action=delete&id=<?= $case['id'] ?>', 'welfare case')">
                                                <i class="fas fa-trash fa-sm fa-fw mr-2 text-danger"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Case Details Modal -->
                            <div class="modal fade" id="caseDetailsModal<?= $case['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="caseDetailsModalLabel<?= $case['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog modal-lg" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="caseDetailsModalLabel<?= $case['id'] ?>">Welfare Case Details</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="font-weight-bold">Case Information</h6>
                                                    <p><strong>Title:</strong> <?= htmlspecialchars($case['title']) ?></p>
                                                    <p><strong>Description:</strong> <?= nl2br(htmlspecialchars($case['description'])) ?></p>
                                                    <p><strong>Amount Needed:</strong> KES <?= number_format($case['amount_needed'], 2) ?></p>
                                                    <p><strong>Status:</strong> 
                                                        <?php if ($case['status'] == 'pending'): ?>
                                                            <span class="badge badge-warning">Pending</span>
                                                        <?php elseif ($case['status'] == 'approved'): ?>
                                                            <span class="badge badge-success">Approved</span>
                                                        <?php elseif ($case['status'] == 'completed'): ?>
                                                            <span class="badge badge-primary">Completed</span>
                                                        <?php elseif ($case['status'] == 'rejected'): ?>
                                                            <span class="badge badge-danger">Rejected</span>
                                                        <?php endif; ?>
                                                    </p>
                                                    <p><strong>Date Created:</strong> <?= date('M d, Y', strtotime($case['created_at'])) ?></p>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="font-weight-bold">Requester Information</h6>
                                                    <p><strong>Name:</strong> <?= htmlspecialchars($case['requester_name']) ?></p>
                                                    <p><strong>Email:</strong> <?= htmlspecialchars($case['requester_email']) ?></p>
                                                    <p><strong>Phone:</strong> <?= htmlspecialchars($case['requester_phone']) ?></p>
                                                    
                                                    <h6 class="font-weight-bold mt-4">Contribution Progress</h6>
                                                    <div class="progress mb-2">
                                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percentage ?>%" aria-valuenow="<?= $percentage ?>" aria-valuemin="0" aria-valuemax="100"><?= number_format($percentage, 1) ?>%</div>
                                                    </div>
                                                    <p><strong>Total Contributions:</strong> KES <?= number_format($case['total_contributions'] ?? 0, 2) ?></p>
                                                    <p><strong>Remaining:</strong> KES <?= number_format(max(0, $case['amount_needed'] - ($case['total_contributions'] ?? 0)), 2) ?></p>
                                                </div>
                                            </div>
                                            
                                            <hr>
                                            
                                            <h6 class="font-weight-bold">Contributions</h6>
                                            <?php
                                                // Fetch contributions for this case
                                                $contrib_stmt = $pdo->prepare("
                                                    SELECT wc.*, u.name as contributor_name 
                                                    FROM welfare_contributions wc
                                                    JOIN users u ON wc.user_id = u.id
                                                    WHERE wc.case_id = ?
                                                    ORDER BY wc.contribution_date DESC
                                                ");
                                                $contrib_stmt->execute([$case['id']]);
                                                $contributions = $contrib_stmt->fetchAll(PDO::FETCH_ASSOC);
                                            ?>
                                            
                                            <?php if (count($contributions) > 0): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered">
                                                        <thead>
                                                            <tr>
                                                                <th>Contributor</th>
                                                                <th>Amount</th>
                                                                <th>Date</th>
                                                                <th>Notes</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($contributions as $contribution): ?>
                                                                <tr>
                                                                    <td><?= htmlspecialchars($contribution['contributor_name']) ?></td>
                                                                    <td>KES <?= number_format($contribution['amount'], 2) ?></td>
                                                                    <td><?= date('M d, Y', strtotime($contribution['contribution_date'])) ?></td>
                                                                    <td><?= htmlspecialchars($contribution['notes'] ?? 'N/A') ?></td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <p class="text-muted">No contributions have been made to this case yet.</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                            <?php if ($case['status'] == 'pending'): ?>
                                                <a href="welfare.php?action=update_status&id=<?= $case['id'] ?>&status=approved<?= $status_filter != 'all' ? '&status=' . $status_filter : '' ?>" class="btn btn-success">
                                                    <i class="fas fa-check mr-1"></i> Approve
                                                </a>
                                                <a href="welfare.php?action=update_status&id=<?= $case['id'] ?>&status=rejected<?= $status_filter != 'all' ? '&status=' . $status_filter : '' ?>" class="btn btn-danger">
                                                    <i class="fas fa-times mr-1"></i> Reject
                                                </a>
                                            <?php elseif ($case['status'] == 'approved'): ?>
                                                <a href="welfare.php?action=update_status&id=<?= $case['id'] ?>&status=completed<?= $status_filter != 'all' ? '&status=' . $status_filter : '' ?>" class="btn btn-primary">
                                                    <i class="fas fa-check-double mr-1"></i> Mark as Completed
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-primary" data-dismiss="modal" data-toggle="modal" data-target="#addContributionModal<?= $case['id'] ?>">
                                                <i class="fas fa-donate mr-1"></i> Add Contribution
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add Contribution Modal -->
                            <div class="modal fade" id="addContributionModal<?= $case['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="addContributionModalLabel<?= $case['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="addContributionModalLabel<?= $case['id'] ?>">Add Contribution</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form action="welfare_actions.php" method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="add_contribution">
                                                <input type="hidden" name="case_id" value="<?= $case['id'] ?>">
                                                <input type="hidden" name="redirect_url" value="welfare.php<?= $status_filter != 'all' ? '?status=' . $status_filter : '' ?>">
                                                
                                                <div class="form-group">
                                                    <label for="contributor<?= $case['id'] ?>">Contributor</label>
                                                    <select class="form-control" id="contributor<?= $case['id'] ?>" name="user_id" required>
                                                        <option value="">Select Contributor</option>
                                                        <?php
                                                            $user_stmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
                                                            $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
                                                            foreach ($users as $user) {
                                                                echo '<option value="' . $user['id'] . '">' . htmlspecialchars($user['name']) . '</option>';
                                                            }
                                                        ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="amount<?= $case['id'] ?>">Amount (KES)</label>
                                                    <input type="number" class="form-control" id="amount<?= $case['id'] ?>" name="amount" min="1" step="0.01" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="contribution_date<?= $case['id'] ?>">Contribution Date</label>
                                                    <input type="date" class="form-control" id="contribution_date<?= $case['id'] ?>" name="contribution_date" value="<?= date('Y-m-d') ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="notes<?= $case['id'] ?>">Notes</label>
                                                    <textarea class="form-control" id="notes<?= $case['id'] ?>" name="notes" rows="3"></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">Save Contribution</button>
                                            </div>
                                        </form>
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

<!-- Add New Welfare Case Modal -->
<div class="modal fade" id="addWelfareModal" tabindex="-1" role="dialog" aria-labelledby="addWelfareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addWelfareModalLabel">Add New Welfare Case</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="welfare_actions.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_case">
                    <input type="hidden" name="redirect_url" value="welfare.php<?= $status_filter != 'all' ? '?status=' . $status_filter : '' ?>">
                    
                    <div class="form-group">
                        <label for="requester">Requester</label>
                        <select class="form-control" id="requester" name="user_id" required>
                            <option value="">Select Requester</option>
                            <?php
                                $user_stmt = $pdo->query("SELECT id, name FROM users ORDER BY name");
                                $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($users as $user) {
                                    echo '<option value="' . $user['id'] . '">' . htmlspecialchars($user['name']) . '</option>';
                                }
                            ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="amount_needed">Amount Needed (KES)</label>
                                <input type="number" class="form-control" id="amount_needed" name="amount_needed" min="1" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select class="form-control" id="status" name="status" required>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Case</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Export Report Modal -->
<div class="modal fade" id="exportModal" tabindex="-1" role="dialog" aria-labelledby="exportModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="exportModalLabel">Export Welfare Report</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="export_reports.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="report_type" value="welfare">
                    
                    <div class="form-group">
                        <label for="date_from">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?= date('Y-m-01') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="export_status">Status Filter</label>
                        <select class="form-control" id="export_status" name="status">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="completed">Completed</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="export_format">Export Format</label>
                        <select class="form-control" id="export_format" name="format" required>
                            <option value="csv">CSV</option>
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Export Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>