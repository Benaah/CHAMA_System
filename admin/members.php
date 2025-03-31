<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle member status updates
if (isset($_GET['action']) && $_GET['action'] == 'update_status' && isset($_GET['id']) && isset($_GET['status'])) {
    $member_id = $_GET['id'];
    $new_status = $_GET['status'];
    
    // Validate status
    $valid_statuses = ['active', 'inactive', 'suspended'];
    if (in_array($new_status, $valid_statuses)) {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        if ($stmt->execute([$new_status, $member_id])) {
            $_SESSION['admin_success'] = "Member status updated successfully.";
            
            // Log the activity
            $admin_id = $_SESSION['user_id'];
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $admin_id, 
                'update', 
                'user', 
                $member_id, 
                "Updated member status to $new_status"
            ]);
        } else {
            $_SESSION['admin_error'] = "Failed to update member status.";
        }
    } else {
        $_SESSION['admin_error'] = "Invalid status value.";
    }
    
    // Redirect to remove GET parameters
    header("Location: members.php");
    exit();
}

// Get status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Fetch members with filter
$query = "SELECT * FROM users WHERE role = 'member'";

if ($status_filter != 'all') {
    $query .= " AND status = :status";
}

$query .= " ORDER BY created_at DESC";

$stmt = $pdo->prepare($query);

if ($status_filter != 'all') {
    $stmt->bindParam(':status', $status_filter);
}

$stmt->execute();
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get member statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'inactive' => 0,
    'suspended' => 0
];

$stmt = $pdo->query("SELECT status, COUNT(*) as count FROM users WHERE role = 'member' GROUP BY status");
$status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($status_counts as $count) {
    $stats['total'] += $count['count'];
    if (isset($stats[$count['status']])) {
        $stats[$count['status']] = $count['count'];
    }
}
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Member Management</h1>
        <div>
            <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addMemberModal">
                <i class="fas fa-user-plus fa-sm text-white-50"></i> Add New Member
            </a>
            <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm ml-2" data-toggle="modal" data-target="#exportModal">
                <i class="fas fa-download fa-sm text-white-50"></i> Export Members
            </a>
        </div>
    </div>

    <!-- Status Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Members</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                Active Members</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-check fa-2x text-gray-300"></i>
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
                                Inactive Members</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['inactive'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-clock fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Suspended Members</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['suspended'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user-slash fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Options -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filter Members</h6>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="statusFilter">Status</label>
                        <select class="form-control" id="statusFilter" onchange="window.location.href='members.php?status='+this.value">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Members</option>
                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status_filter == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= $status_filter == 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for="searchMember">Search</label>
                        <input type="text" class="form-control" id="searchMember" placeholder="Search by name, email, or phone...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Members Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Member List</h6>
        </div>
        <div class="card-body">
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
            
            <div class="table-responsive">
                <table class="table table-bordered" id="membersTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member): ?>
                            <tr>
                                <td><?= $member['id'] ?></td>
                                <td>
                                    <?php if (!empty($member['profile_picture']) && file_exists('../' . $member['profile_picture'])): ?>
                                        <img src="../<?= htmlspecialchars($member['profile_picture']) ?>" alt="Profile" class="img-profile rounded-circle mr-2" style="width: 30px; height: 30px;">
                                    <?php else: ?>
                                        <img src="../assets/images/default-avatar.png" alt="Default Profile" class="img-profile rounded-circle mr-2" style="width: 30px; height: 30px;">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($member['name']) ?>
                                </td>
                                <td><?= htmlspecialchars($member['email']) ?></td>
                                <td><?= htmlspecialchars($member['phone']) ?></td>
                                <td>
                                    <?php if ($member['status'] == 'active'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php elseif ($member['status'] == 'inactive'): ?>
                                        <span class="badge badge-warning">Inactive</span>
                                    <?php elseif ($member['status'] == 'suspended'): ?>
                                        <span class="badge badge-danger">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($member['created_at'])) ?></td>
                                <td>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="dropdownMenuButton" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                            Actions
                                        </button>
                                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton">
                                            <a class="dropdown-item" href="member_details.php?id=<?= $member['id'] ?>">
                                                <i class="fas fa-eye fa-sm fa-fw mr-2 text-gray-400"></i> View Details
                                            </a>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#editMemberModal<?= $member['id'] ?>">
                                                <i class="fas fa-edit fa-sm fa-fw mr-2 text-gray-400"></i> Edit
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="members.php?action=update_status&id=<?= $member['id'] ?>&status=active">
                                                <i class="fas fa-user-check fa-sm fa-fw mr-2 text-success"></i> Set Active
                                            </a>
                                            <a class="dropdown-item" href="members.php?action=update_status&id=<?= $member['id'] ?>&status=inactive">
                                                <i class="fas fa-user-clock fa-sm fa-fw mr-2 text-warning"></i> Set Inactive
                                            </a>
                                            <a class="dropdown-item" href="members.php?action=update_status&id=<?= $member['id'] ?>&status=suspended">
                                                <i class="fas fa-user-slash fa-sm fa-fw mr-2 text-danger"></i> Suspend
                                            </a>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item" href="#" data-toggle="modal" data-target="#deleteMemberModal<?= $member['id'] ?>">
                                                <i class="fas fa-trash fa-sm fa-fw mr-2 text-danger"></i> Delete
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Edit Member Modal -->
                            <div class="modal fade" id="editMemberModal<?= $member['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="editMemberModalLabel" aria-hidden="true">
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
                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                
                                                <div class="form-group">
                                                    <label for="name<?= $member['id'] ?>">Full Name</label>
                                                    <input type="text" class="form-control" id="name<?= $member['id'] ?>" name="name" value="<?= htmlspecialchars($member['name']) ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="email<?= $member['id'] ?>">Email Address</label>
                                                    <input type="email" class="form-control" id="email<?= $member['id'] ?>" name="email" value="<?= htmlspecialchars($member['email']) ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="phone<?= $member['id'] ?>">Phone Number</label>
                                                    <input type="text" class="form-control" id="phone<?= $member['id'] ?>" name="phone" value="<?= htmlspecialchars($member['phone']) ?>" required>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="status<?= $member['id'] ?>">Status</label>
                                                    <select class="form-control" id="status<?= $member['id'] ?>" name="status">
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
                            
                            <!-- Delete Member Modal -->
                            <div class="modal fade" id="deleteMemberModal<?= $member['id'] ?>" tabindex="-1" role="dialog" aria-labelledby="deleteMemberModalLabel" aria-hidden="true">
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
                                                <input type="hidden" name="member_id" value="<?= $member['id'] ?>">
                                                <button type="submit" class="btn btn-danger">Delete Member</button>
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

<!-- Add Member Modal -->
<div class="modal fade" id="addMemberModal" tabindex="-1" role="dialog" aria-labelledby="addMemberModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMemberModalLabel">Add New Member</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="member_actions.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_member">
                    
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" class="form-control" id="phone" name="phone" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Member</button>
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
                <h5 class="modal-title" id="exportModalLabel">Export Members</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form action="export.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="export_type" value="members">
                    
                    <div class="form-group">
                        <label>Select Export Format</label>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="formatCSV" name="format" value="csv" class="custom-control-input" checked>
                            <label class="custom-control-label" for="formatCSV">CSV</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="formatExcel" name="format" value="excel" class="custom-control-input">
                            <label class="custom-control-label" for="formatExcel">Excel</label>
                        </div>
                        <div class="custom-control custom-radio">
                            <input type="radio" id="formatPDF" name="format" value="pdf" class="custom-control-input">
                            <label class="custom-control-label" for="formatPDF">PDF</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Member Status</label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="statusAll" name="export_status[]" value="all" class="custom-control-input" checked>
                            <label class="custom-control-label" for="statusAll">All Members</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="statusActive" name="export_status[]" value="active" class="custom-control-input">
                            <label class="custom-control-label" for="statusActive">Active Members</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="statusInactive" name="export_status[]" value="inactive" class="custom-control-input">
                            <label class="custom-control-label" for="statusInactive">Inactive Members</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="statusSuspended" name="export_status[]" value="suspended" class="custom-control-input">
                            <label class="custom-control-label" for="statusSuspended">Suspended Members</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Select Fields to Export</label>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="fieldAll" name="export_fields[]" value="all" class="custom-control-input" checked>
                            <label class="custom-control-label" for="fieldAll">All Fields</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="fieldName" name="export_fields[]" value="name" class="custom-control-input">
                            <label class="custom-control-label" for="fieldName">Name</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="fieldEmail" name="export_fields[]" value="email" class="custom-control-input">
                            <label class="custom-control-label" for="fieldEmail">Email</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="fieldPhone" name="export_fields[]" value="phone" class="custom-control-input">
                            <label class="custom-control-label" for="fieldPhone">Phone</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="fieldStatus" name="export_fields[]" value="status" class="custom-control-input">
                            <label class="custom-control-label" for="fieldStatus">Status</label>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" id="fieldJoined" name="export_fields[]" value="created_at" class="custom-control-input">
                            <label class="custom-control-label" for="fieldJoined">Join Date</label>
                        </div>
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

<!-- Page level plugins -->
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.min.js"></script>

<!-- Page level custom scripts -->
<script>
$(document).ready(function() {
    // Initialize DataTable
    var table = $('#membersTable').DataTable({
        responsive: true,
        order: [[5, 'desc']], // Sort by join date by default
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search members...",
        }
    });
    
    // Apply search filter
    $('#searchMember').on('keyup', function() {
        table.search(this.value).draw();
    });
    
    // Handle checkbox logic for export modal
    $('#statusAll').change(function() {
        if(this.checked) {
            $('#statusActive, #statusInactive, #statusSuspended').prop('checked', false);
        }
    });
    
    $('#statusActive, #statusInactive, #statusSuspended').change(function() {
        if(this.checked) {
            $('#statusAll').prop('checked', false);
        }
    });
    
    $('#fieldAll').change(function() {
        if(this.checked) {
            $('#fieldName, #fieldEmail, #fieldPhone, #fieldStatus, #fieldJoined').prop('checked', false);
        }
    });
    
    $('#fieldName, #fieldEmail, #fieldPhone, #fieldStatus, #fieldJoined').change(function() {
        if(this.checked) {
            $('#fieldAll').prop('checked', false);
        }
    });
});
</script>

<?php include 'footer.php'; ?>