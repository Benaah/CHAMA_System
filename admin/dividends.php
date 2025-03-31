<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get filter values
$year_filter = isset($_GET['year']) ? $_GET['year'] : date('Y');
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Handle dividend status updates
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $dividend_id = $_GET['id'];
    $new_status = $_GET['status'];
    
    // Validate status
    $valid_statuses = ['pending', 'approved', 'paid', 'cancelled'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE dividends SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt->execute([$new_status, $dividend_id])) {
            $_SESSION['admin_success'] = "Dividend status updated successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to update dividend status.";
        }
    } else {
        $_SESSION['admin_error'] = "Invalid status value.";
    }
    
    // Redirect to remove GET parameters
    header("Location: dividends.php");
    exit();
}

// Build query based on filters
$query = "
    SELECT d.*, u.name as member_name 
    FROM dividends d
    JOIN users u ON d.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($year_filter != 'all') {
    $query .= " AND YEAR(d.declaration_date) = ?";
    $params[] = $year_filter;
}

if ($status_filter != 'all') {
    $query .= " AND d.status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY d.declaration_date DESC, u.name ASC";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$dividends = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get dividend statistics
$stats = [
    'total_amount' => 0,
    'pending_amount' => 0,
    'paid_amount' => 0,
    'current_year_amount' => 0
];

$stmt = $pdo->query("
    SELECT 
        SUM(amount) as total_amount,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as pending_amount,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as paid_amount,
        SUM(CASE WHEN YEAR(declaration_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as current_year_amount
    FROM dividends
");
$result = $stmt->fetch(PDO::FETCH_ASSOC);

$stats['total_amount'] = $result['total_amount'] ?? 0;
$stats['pending_amount'] = $result['pending_amount'] ?? 0;
$stats['paid_amount'] = $result['paid_amount'] ?? 0;
$stats['current_year_amount'] = $result['current_year_amount'] ?? 0;

// Get available years for filter
$stmt = $pdo->query("SELECT DISTINCT YEAR(declaration_date) as year FROM dividends ORDER BY year DESC");
$years = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get members for dropdown
$stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'member' ORDER BY name");
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Manage Dividends</h1>
        <div>
            <a href="#" class="btn btn-success btn-sm" data-toggle="modal" data-target="#addDividendModal">
                <i class="fas fa-plus fa-sm text-white-50"></i> Add Dividend
            </a>
            <a href="#" class="btn btn-primary btn-sm ml-2" data-toggle="modal" data-target="#bulkDividendModal">
                <i class="fas fa-list fa-sm text-white-50"></i> Bulk Dividends
            </a>
            <a href="#" class="btn btn-info btn-sm ml-2" data-toggle="modal" data-target="#exportModal">
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
        <!-- Total Dividends Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Dividends</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['total_amount'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-chart-pie fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Current Year Dividends Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <?= date('Y') ?> Dividends</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['current_year_amount'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-calendar fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Dividends Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Pending Dividends</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['pending_amount'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Paid Dividends Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Paid Dividends</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['paid_amount'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
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
                    <label for="year" class="mr-2">Year:</label>
                    <select class="form-control" id="year" name="year" onchange="this.form.submit()">
                        <option value="all" <?= $year_filter == 'all' ? 'selected' : '' ?>>All Years</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?= $year ?>" <?= $year_filter == $year ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group mb-2 mr-3">
                    <label for="status" class="mr-2">Status:</label>
                    <select class="form-control" id="status" name="status" onchange="this.form.submit()">
                        <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="paid" <?= $status_filter == 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                
                <?php if ($year_filter != 'all' || $status_filter != 'all'): ?>
                    <a href="dividends.php" class="btn btn-secondary mb-2">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Dividends Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Dividend List</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dividendsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Amount</th>
                            <th>Declaration Date</th>
                            <th>Payment Date</th>
                            <th>Status</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dividends as $dividend): ?>
                            <tr>
                                <td><?= $dividend['id'] ?></td>
                                <td>
                                    <a href="member_details.php?id=<?= $dividend['user_id'] ?>">
                                        <?= htmlspecialchars($dividend['member_name']) ?>
                                    </a>
                                </td>
                                <td>KES <?= number_format($dividend['amount'], 2) ?></td>
                                <td><?= date('M d, Y', strtotime($dividend['declaration_date'])) ?></td>
                                <td><?= $dividend['payment_date'] ? date('M d, Y', strtotime($dividend['payment_date'])) : 'Not paid yet' ?></td>
                                <td>
                                    <span class="badge badge-<?= 
                                        $dividend['status'] == 'approved' ? 'success' : 
                                        ($dividend['status'] == 'pending' ? 'warning' : 
                                        ($dividend['status'] == 'paid' ? 'info' : 'secondary')) 
                                    ?>">
                                        <?= ucfirst($dividend['status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($dividend['description']) ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Actions
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editDividendModal<?= $dividend['id'] ?>">
                                                <i class="fas fa-edit fa-sm fa-fw mr-2 text-gray-400"></i> Edit
                                            </a>
                                            
                                            <?php if ($dividend['status'] == 'pending'): ?>
                                                <a class="dropdown-item" href="dividends.php?action=update_status&id=<?= $dividend['id'] ?>&status=approved">
                                                    <i class="fas fa-check fa-sm fa-fw mr-2 text-success"></i> Approve
                                                </a>
                                                <a class="dropdown-item" href="dividends.php?action=update_status&id=<?= $dividend['id'] ?>&status=cancelled">
                                                    <i class="fas fa-times fa-sm fa-fw mr-2 text-danger"></i> Cancel
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if ($dividend['status'] == 'approved'): ?>
                                                <a class="dropdown-item" href="dividends.php?action=update_status&id=<?= $dividend['id'] ?>&status=paid">
                                                    <i class="fas fa-money-bill-wave fa-sm fa-fw mr-2 text-info"></i> Mark as Paid
                                                </a>
                                            <?php endif; ?>
                                            
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#deleteDividendModal<?= $dividend['id'] ?>">
                                                <i class="fas fa-trash fa-sm fa-fw mr-2 text-danger"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Edit Dividend Modal -->
                            <div class="modal fade" id="editDividendModal<?= $dividend['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="editDividendModalLabel<?= $dividend['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editDividendModalLabel<?= $dividend['id'] ?>">Edit Dividend</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <form action="dividend_actions.php" method="post">
                                            <div class="modal-body">
                                                <input type="hidden" name="action" value="edit_dividend">
                                                <input type="hidden" name="dividend_id" value="<?= $dividend['id'] ?>">
                                                
                                                <div class="form-group">
                                                    <label for="member_id<?= $dividend['id'] ?>">Member</label>
                                                    <select class="form-control" id="member_id<?= $dividend['id'] ?>" name="member_id" required>
                                                        <?php foreach ($members as $member): ?>
                                                            <option value="<?= $member['id'] ?>" <?= $dividend['user_id'] == $member['id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($member['name']) ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="amount<?= $dividend['id'] ?>">Amount (KES)</label>
                                                    <input type="number" class="form-control" id="amount<?= $dividend['id'] ?>" name="amount" value="<?= $dividend['amount'] ?>" min="0" step="0.01" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="declaration_date<?= $dividend['id'] ?>">Declaration Date</label>
                                                    <input type="date" class="form-control" id="declaration_date<?= $dividend['id'] ?>" name="declaration_date" value="<?= date('Y-m-d', strtotime($dividend['declaration_date'])) ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="payment_date<?= $dividend['id'] ?>">Payment Date</label>
                                                    <input type="date" class="form-control" id="payment_date<?= $dividend['id'] ?>" name="payment_date" value="<?= $dividend['payment_date'] ? date('Y-m-d', strtotime($dividend['payment_date'])) : '' ?>">
                                                    <small class="form-text text-muted">Leave blank if not paid yet</small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="status<?= $dividend['id'] ?>">Status</label>
                                                    <select class="form-control" id="status<?= $dividend['id'] ?>" name="status" required>
                                                        <option value="pending" <?= $dividend['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                                        <option value="approved" <?= $dividend['status'] == 'approved' ? 'selected' : '' ?>>Approved</option>
                                                        <option value="paid" <?= $dividend['status'] == 'paid' ? 'selected' : '' ?>>Paid</option>
                                                        <option value="cancelled" <?= $dividend['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="description<?= $dividend['id'] ?>">Description</label>
                                                    <textarea class="form-control" id="description<?= $dividend['id'] ?>" name="description" rows="3"><?= htmlspecialchars($dividend['description']) ?></textarea>
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
                            
                            <!-- Delete Dividend Modal -->
                            <div class="modal fade" id="deleteDividendModal<?= $dividend['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="deleteDividendModalLabel<?= $dividend['id'] ?>" aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteDividendModalLabel<?= $dividend['id'] ?>">Confirm Delete</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Are you sure you want to delete this dividend?</p>
                                            <p><strong>Member:</strong> <?= htmlspecialchars($dividend['member_name']) ?></p>
                                            <p><strong>Amount:</strong> KES <?= number_format($dividend['amount'], 2) ?></p>
                                            <p><strong>Declaration Date:</strong> <?= date('M d, Y', strtotime($dividend['declaration_date'])) ?></p>
                                            <p class="text-danger">This action cannot be undone.</p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                            <form action="dividend_actions.php" method="post">
                                                <input type="hidden" name="action" value="delete_dividend">
                                                <input type="hidden" name="dividend_id" value="<?= $dividend['id'] ?>">
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

<!-- Add Dividend Modal -->
<div class="modal fade" id="addDividendModal" tabindex="-1" role="dialog" aria-labelledby="addDividendModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addDividendModalLabel">Add New Dividend</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="dividend_actions.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_dividend">
                    
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
                        <label for="declaration_date">Declaration Date</label>
                        <input type="date" class="form-control" id="declaration_date" name="declaration_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date">
                        <small class="form-text text-muted">Leave blank if not paid yet</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Dividend</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Dividend Modal -->
<div class="modal fade" id="bulkDividendModal" tabindex="-1" role="dialog" aria-labelledby="bulkDividendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulkDividendModalLabel">Bulk Dividend Distribution</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="dividend_actions.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="bulk_dividends">
                    
                    <div class="alert alert-info">
                        <p class="mb-0">Use this form to distribute dividends to multiple members at once based on their contributions or shares.</p>
                    </div>
                    
                    <div class="form-group">
                        <label for="distribution_method">Distribution Method</label>
                        <select class="form-control" id="distribution_method" name="distribution_method" required>
                            <option value="equal">Equal Distribution</option>
                            <option value="contribution">Based on Contribution</option>
                            <option value="shares">Based on Shares</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="total_amount">Total Amount to Distribute (KES)</label>
                        <input type="number" class="form-control" id="total_amount" name="total_amount" min="0" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="declaration_date_bulk">Declaration Date</label>
                        <input type="date" class="form-control" id="declaration_date_bulk" name="declaration_date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment_date_bulk">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date_bulk" name="payment_date">
                        <small class="form-text text-muted">Leave blank if not paid yet</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="status_bulk">Status</label>
                        <select class="form-control" id="status_bulk" name="status" required>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="description_bulk">Description</label>
                        <textarea class="form-control" id="description_bulk" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Members</label>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="select_all_members" checked>
                            <label class="form-check-label" for="select_all_members">
                                Select All Members
                            </label>
                        </div>
                        <div class="member-list" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                            <?php foreach ($members as $member): ?>
                                <div class="form-check">
                                        <input class="form-check-input member-checkbox" type="checkbox" name="selected_members[]" value="<?= $member['id'] ?>" checked>
                                    <label class="form-check-label">
                                        <?= htmlspecialchars($member['name']) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Distribute Dividends</button>
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
                <h5 class="modal-title" id="exportModalLabel">Export Dividends</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="export.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="export_type" value="dividends">
                    
                    <div class="form-group">
                        <label for="export_year">Year</label>
                        <select class="form-control" id="export_year" name="year">
                            <option value="all">All Years</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?= $year ?>"><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="export_status">Status</label>
                        <select class="form-control" id="export_status" name="status">
                            <option value="all">All Statuses</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
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
    $('#dividendsTable').DataTable({
        order: [[3, 'desc']], // Sort by declaration date column in descending order
        pageLength: 25,
        responsive: true
    });
    
    // Handle select all members checkbox
    $('#select_all_members').change(function() {
        $('.member-checkbox').prop('checked', $(this).prop('checked'));
    });
    
    // Update select all checkbox when individual checkboxes change
    $('.member-checkbox').change(function() {
        if ($('.member-checkbox:checked').length == $('.member-checkbox').length) {
            $('#select_all_members').prop('checked', true);
        } else {
            $('#select_all_members').prop('checked', false);
        }
    });
});
</script>

<?php include 'footer.php'; ?>