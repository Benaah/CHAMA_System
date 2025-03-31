<?php
// Include authentication check
include_once('../auth.php');

// Database connection
$host = 'localhost';
$username = 'root'; // Replace with your database username
$password = ''; // Replace with your database password
$database = 'agape_chama';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Handle withdrawal deletion if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $delete_query = "DELETE FROM withdrawals WHERE id = $id";
    
    if (mysqli_query($conn, $delete_query)) {
        $success_message = "Withdrawal record deleted successfully.";
    } else {
        $error_message = "Error deleting record: " . mysqli_error($conn);
    }
}

// Fetch all withdrawal records
$query = "SELECT w.*, m.name as member_name 
          FROM withdrawals w 
          JOIN members m ON w.member_id = m.id 
          ORDER BY w.request_date DESC";
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Withdrawals - AGAPE CHAMA</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <?php include_once('../includes/admin_header.php'); ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Manage Withdrawals</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success_message)): ?>
                            <div class="alert alert-success"><?php echo $success_message; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($error_message)): ?>
                            <div class="alert alert-danger"><?php echo $error_message; ?></div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <a href="withdrawal_add.php" class="btn btn-success">Add New Withdrawal</a>
                        </div>
                        
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Member</th>
                                            <th>Amount</th>
                                            <th>Request Date</th>
                                            <th>Processing Date</th>
                                            <th>Status</th>
                                            <th>Payment Method</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><?php echo $row['id']; ?></td>
                                                <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                                                <td><?php echo number_format($row['amount'], 2); ?></td>
                                                <td><?php echo date('Y-m-d', strtotime($row['request_date'])); ?></td>
                                                <td>
                                                    <?php 
                                                    echo !empty($row['processing_date']) 
                                                        ? date('Y-m-d', strtotime($row['processing_date'])) 
                                                        : 'N/A'; 
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php if ($row['status'] == 'approved'): ?>
                                                        <span class="badge bg-success">Approved</span>
                                                    <?php elseif ($row['status'] == 'pending'): ?>
                                                        <span class="badge bg-warning">Pending</span>
                                                    <?php elseif ($row['status'] == 'processing'): ?>
                                                        <span class="badge bg-info">Processing</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Rejected</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($row['payment_method']); ?></td>
                                                <td>
                                                    <a href="withdrawal_edit.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                                    <a href="withdrawals_manage.php?delete=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this withdrawal record?')">Delete</a>
                                                    <?php if ($row['status'] == 'pending'): ?>
                                                        <a href="withdrawal_approve.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success">Approve</a>
                                                        <a href="withdrawal_reject.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger">Reject</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No withdrawal records found.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once('../includes/admin_footer.php'); ?>
    
    <script src="../js/jquery.min.js"></script>
    <script src="../js/bootstrap.bundle.min.js"></script>
</body>
</html>