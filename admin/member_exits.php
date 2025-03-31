<?php
require_once '../config.php';
require_once '../includes/functions.php';
require_once '../auth.php'; // This will redirect to login if not authenticated as admin

// Process exit request actions
$message = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_exit'])) {
        $exit_id = filter_input(INPUT_POST, 'exit_id', FILTER_VALIDATE_INT);
        $admin_notes = filter_input(INPUT_POST, 'admin_notes', FILTER_SANITIZE_STRING);
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update exit request status
            $stmt = $pdo->prepare("UPDATE member_exits SET status = 'approved', admin_notes = ?, processed_date = NOW(), processed_by = ? WHERE id = ?");
            $stmt->execute([$admin_notes, $_SESSION['user_id'], $exit_id]);
            
            // Get user ID from exit request
            $stmt = $pdo->prepare("SELECT user_id FROM member_exits WHERE id = ?");
            $stmt->execute([$exit_id]);
            $user_id = $stmt->fetchColumn();
            
            // Log activity
            logActivity('Member exit request approved', $user_id, $_SESSION['user_id']);
            
            // Commit transaction
            $pdo->commit();
            
            $message = 'Exit request has been approved successfully.';
            $alertType = 'success';
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $message = 'An error occurred while processing the request: ' . $e->getMessage();
            $alertType = 'danger';
        }
    } elseif (isset($_POST['reject_exit'])) {
        $exit_id = filter_input(INPUT_POST, 'exit_id', FILTER_VALIDATE_INT);
        $admin_notes = filter_input(INPUT_POST, 'admin_notes', FILTER_SANITIZE_STRING);
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update exit request status
            $stmt = $pdo->prepare("UPDATE member_exits SET status = 'rejected', admin_notes = ?, processed_date = NOW(), processed_by = ? WHERE id = ?");
            $stmt->execute([$admin_notes, $_SESSION['user_id'], $exit_id]);
            
            // Get user ID from exit request
            $stmt = $pdo->prepare("SELECT user_id FROM member_exits WHERE id = ?");
            $stmt->execute([$exit_id]);
            $user_id = $stmt->fetchColumn();
            
            // Log activity
            logActivity('Member exit request rejected', $user_id, $_SESSION['user_id']);
            
            // Commit transaction
            $pdo->commit();
            
            $message = 'Exit request has been rejected successfully.';
            $alertType = 'success';
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $message = 'An error occurred while processing the request: ' . $e->getMessage();
            $alertType = 'danger';
        }
    } elseif (isset($_POST['complete_exit'])) {
        $exit_id = filter_input(INPUT_POST, 'exit_id', FILTER_VALIDATE_INT);
        $admin_notes = filter_input(INPUT_POST, 'admin_notes', FILTER_SANITIZE_STRING);
        
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update exit request status
            $stmt = $pdo->prepare("UPDATE member_exits SET status = 'completed', admin_notes = CONCAT(admin_notes, '\n\nCompletion Notes: ', ?), processed_date = NOW(), processed_by = ? WHERE id = ?");
            $stmt->execute([$admin_notes, $_SESSION['user_id'], $exit_id]);
            
            // Get user ID from exit request
            $stmt = $pdo->prepare("SELECT user_id FROM member_exits WHERE id = ?");
            $stmt->execute([$exit_id]);
            $user_id = $stmt->fetchColumn();
            
            // Update user status to inactive
            $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$user_id]);
            
            // Log activity
            logActivity('Member exit process completed', $user_id, $_SESSION['user_id']);
            
            // Commit transaction
            $pdo->commit();
            
            $message = 'Exit process has been completed successfully. The member\'s account has been deactivated.';
            $alertType = 'success';
        } catch (PDOException $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $message = 'An error occurred while processing the request: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Get exit requests with user information
$stmt = $pdo->prepare("
    SELECT e.*, u.first_name, u.last_name, u.username, u.email, u.phone_number, u.registration_date,
           a.first_name as admin_first_name, a.last_name as admin_last_name
    FROM member_exits e
    JOIN users u ON e.user_id = u.id
    LEFT JOIN users a ON e.processed_by = a.id
    ORDER BY 
        CASE 
            WHEN e.status = 'pending' THEN 1
            WHEN e.status = 'approved' THEN 2
            WHEN e.status = 'rejected' THEN 3
            WHEN e.status = 'completed' THEN 4
            WHEN e.status = 'cancelled' THEN 5
        END,
        e.request_date DESC
");
$stmt->execute();
$exitRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Include header
include 'header.php';
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Member Exit Requests</h1>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">Exit Requests</h6>
            <div class="dropdown no-arrow">
                <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                    <div class="dropdown-header">Actions:</div>
                    <a class="dropdown-item" href="#" id="exportCSV">Export to CSV</a>
                    <a class="dropdown-item" href="#" id="printReport">Print Report</a>
                </div>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="exitRequestsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member</th>
                            <th>Request Date</th>
                            <th>Net Balance</th>
                            <th>Status</th>
                            <th>Processed Date</th>
                            <th>Processed By</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exitRequests as $request): ?>
                            <tr>
                                <td><?php echo $request['id']; ?></td>
                                <td>
                                    <a href="user_details.php?id=<?php echo $request['user_id']; ?>">
                                        <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($request['email']); ?></small>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($request['request_date'])); ?></td>
                                <td class="<?php echo $request['net_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    KES <?php echo number_format($request['net_balance'], 2); ?>
                                </td>
                                <td>
                                    <span class="badge badge-<?php 
                                        echo $request['status'] == 'approved' ? 'success' : 
                                            ($request['status'] == 'pending' ? 'warning' : 
                                                ($request['status'] == 'rejected' ? 'danger' : 
                                                    ($request['status'] == 'completed' ? 'info' : 'secondary'))); 
                                    ?>">
                                        <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $request['processed_date'] ? date('M d, Y', strtotime($request['processed_date'])) : 'N/A'; ?>
                                </td>
                                <td>
                                    <?php echo $request['processed_by'] ? htmlspecialchars($request['admin_first_name'] . ' ' . $request['admin_last_name']) : 'N/A'; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-info view-details" data-id="<?php echo $request['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <button type="button" class="btn btn-sm btn-success approve-exit" data-id="<?php echo $request['id']; ?>">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger reject-exit" data-id="<?php echo $request['id']; ?>">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    <?php elseif ($request['status'] === 'approved'): ?>
                                        <button type="button" class="btn btn-sm btn-primary complete-exit" data-id="<?php echo $request['id']; ?>">
                                            <i class="fas fa-check-double"></i> Complete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- View Details Modal -->
<div class="modal fade" id="viewDetailsModal" tabindex="-1" role="dialog" aria-labelledby="viewDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewDetailsModalLabel">Exit Request Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="exitDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Approve Exit Modal -->
<div class="modal fade" id="approveExitModal" tabindex="-1" role="dialog" aria-labelledby="approveExitModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="approveExitModalLabel">Approve Exit Request</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="exit_id" id="approveExitId">
                    <p>Are you sure you want to approve this exit request?</p>
                    <p>The member will be notified and the settlement process will begin.</p>
                    
                    <div class="form-group">
                        <label for="approveNotes">Notes (Optional)</label>
                        <textarea class="form-control" id="approveNotes" name="admin_notes" rows="3" placeholder="Add any notes or instructions for the member..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="approve_exit" class="btn btn-success">Approve Exit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Exit Modal -->
<div class="modal fade" id="rejectExitModal" tabindex="-1" role="dialog" aria-labelledby="rejectExitModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rejectExitModalLabel">Reject Exit Request</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="exit_id" id="rejectExitId">
                    <p>Are you sure you want to reject this exit request?</p>
                    
                    <div class="form-group">
                        <label for="rejectNotes">Reason for Rejection</label>
                        <textarea class="form-control" id="rejectNotes" name="admin_notes" rows="3" placeholder="Provide a reason for rejecting this exit request..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="reject_exit" class="btn btn-danger">Reject Exit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Exit Modal -->
<div class="modal fade" id="completeExitModal" tabindex="-1" role="dialog" aria-labelledby="completeExitModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="completeExitModalLabel">Complete Exit Process</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="exit_id" id="completeExitId">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i> This action will deactivate the member's account and complete the exit process.
                    </div>
                    <p>Please confirm that the final settlement has been processed and the member has received their funds.</p>
                    
                    <div class="form-group">
                        <label for="completeNotes">Completion Notes</label>
                        <textarea class="form-control" id="completeNotes" name="admin_notes" rows="3" placeholder="Add any notes about the settlement process..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="complete_exit" class="btn btn-primary">Complete Exit</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#exitRequestsTable').DataTable({
        order: [[2, 'desc']], // Sort by request date (newest first)
        pageLength: 10,
        responsive: true
    });
    
    // View Details
    $('.view-details').on('click', function() {
        const exitId = $(this).data('id');
        
        // Find the corresponding exit request
        <?php
        echo "const exitRequests = " . json_encode($exitRequests) . ";";
        ?>
        
        const request = exitRequests.find(req => req.id == exitId);
        
        if (request) {
            let statusClass = '';
            switch (request.status) {
                case 'approved': statusClass = 'success'; break;
                case 'pending': statusClass = 'warning'; break;
                case 'rejected': statusClass = 'danger'; break;
                case 'completed': statusClass = 'info'; break;
                default: statusClass = 'secondary';
            }
            
            let detailsHtml = `
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="font-weight-bold">Member Information</h6>
                        <p><strong>Name:</strong> ${request.first_name} ${request.last_name}</p>
                        <p><strong>Email:</strong> ${request.email}</p>
                        <p><strong>Phone:</strong> ${request.phone_number}</p>
                        <p><strong>Member Since:</strong> ${new Date(request.registration_date).toLocaleDateString()}</p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="font-weight-bold">Exit Request Information</h6>
                        <p><strong>Request Date:</strong> ${new Date(request.request_date).toLocaleDateString()}</p>
                        <p><strong>Status:</strong> <span class="badge badge-${statusClass}">${request.status.charAt(0).toUpperCase() + request.status.slice(1)}</span></p>
                        <p><strong>Payment Method:</strong> ${request.payment_method.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>
                        ${request.processed_date ? `<p><strong>Processed Date:</strong> ${new Date(request.processed_date).toLocaleDateString()}</p>` : ''}
                        ${request.processed_by ? `<p><strong>Processed By:</strong> ${request.admin_first_name} ${request.admin_last_name}</p>` : ''}
                    </div>
                </div>
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="font-weight-bold">Financial Summary</h6>
                        <table class="table table-sm">
                            <tr>
                                <th>Contribution Balance:</th>
                                <td class="text-right">KES ${parseFloat(request.contribution_balance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <th>Dividend Balance:</th>
                                <td class="text-right">KES ${parseFloat(request.dividend_balance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <th>Project Shares:</th>
                                <td class="text-right">KES ${parseFloat(request.project_shares).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr>
                                <th>Outstanding Loans:</th>
                                <td class="text-right text-danger">KES ${parseFloat(request.outstanding_loans).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                            </tr>
                            <tr class="font-weight-bold">
                                <th>Net Balance:</th>
                                <td class="text-right ${parseFloat(request.net_balance) >= 0 ? 'text-success' : 'text-danger'}">
                                    KES ${parseFloat(request.net_balance).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6 class="font-weight-bold">Reason for Leaving</h6>
                        <p>${request.reason}</p>
                        
                        <h6 class="font-weight-bold mt-3">Account Details</h6>
                        <p>${request.account_details.replace(/\n/g, '<br>')}</p>
                        
                        ${request.admin_notes ? `
                            <h6 class="font-weight-bold mt-3">Administrator Notes</h6>
                            <p>${request.admin_notes.replace(/\n/g, '<br>')}</p>
                        ` : ''}
                    </div>
                </div>
            `;
            
            $('#exitDetails').html(detailsHtml);
            $('#viewDetailsModal').modal('show');
        }
    });
    
    // Approve Exit
    $('.approve-exit').on('click', function() {
        const exitId = $(this).data('id');
        $('#approveExitId').val(exitId);
        $('#approveExitModal').modal('show');
    });
    
    // Reject Exit
    $('.reject-exit').on('click', function() {
        const exitId = $(this).data('id');
        $('#rejectExitId').val(exitId);
        $('#rejectExitModal').modal('show');
    });
    
    // Complete Exit
    $('.complete-exit').on('click', function() {
        const exitId = $(this).data('id');
        $('#completeExitId').val(exitId);
        $('#completeExitModal').modal('show');
    });
    
    // Export to CSV
    $('#exportCSV').on('click', function(e) {
        e.preventDefault();
        
        // Get table data
        const table = $('#exitRequestsTable').DataTable();
        const data = table.data().toArray();
        
        // Create CSV content
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID,Member,Email,Request Date,Net Balance,Status,Processed Date,Processed By\n";
        
        data.forEach(function(row) {
            const rowData = [
                row[0],
                $(row[1]).text().trim().split('\n')[0],
                $(row[1]).find('small').text(),
                row[2],
                $(row[3]).text().trim(),
                $(row[4]).text().trim(),
                row[5],
                row[6]
            ];
            csvContent += rowData.join(',') + "\n";
        });
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "exit_requests_report.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Print Report
    $('#printReport').on('click', function(e) {
        e.preventDefault();
        window.print();
    });
});
</script>

<?php include 'footer.php'; ?>