<?php
// Include authentication check
include_once('../auth.php');

// Check for admin privileges
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header('Location: ../index.php');
    exit;
}

// Database connection
require_once '../config.php';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 20;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE action LIKE '%$search%' OR ip_address LIKE '%$search%'";
}

// Date filter
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : '';

if (!empty($date_from) && !empty($date_to)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE timestamp BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    } else {
        $search_condition .= " AND timestamp BETWEEN '$date_from 00:00:00' AND '$date_to 23:59:59'";
    }
} else if (!empty($date_from)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE timestamp >= '$date_from 00:00:00'";
    } else {
        $search_condition .= " AND timestamp >= '$date_from 00:00:00'";
    }
} else if (!empty($date_to)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE timestamp <= '$date_to 23:59:59'";
    } else {
        $search_condition .= " AND timestamp <= '$date_to 23:59:59'";
    }
}

// User filter
$user_filter = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($user_filter > 0) {
    if (empty($search_condition)) {
        $search_condition = "WHERE user_id = $user_filter";
    } else {
        $search_condition .= " AND user_id = $user_filter";
    }
}

// Count total records for pagination
$count_query = "SELECT COUNT(*) as total FROM logs $search_condition";
$count_result = mysqli_query($conn, $count_query);
$total_records = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_records / $records_per_page);

// Fetch logs for current page
$query = "SELECT l.*, u.username 
          FROM logs l 
          LEFT JOIN users u ON l.user_id = u.id 
          $search_condition 
          ORDER BY l.timestamp DESC 
          LIMIT $offset, $records_per_page";
$result = mysqli_query($conn, $query);

// Get all users for filter dropdown
$users_query = "SELECT id, username FROM users ORDER BY username";
$users_result = mysqli_query($conn, $users_query);
$users = [];
while ($user = mysqli_fetch_assoc($users_result)) {
    $users[$user['id']] = $user['username'];
}

// Clear logs functionality
if (isset($_POST['clear_logs']) && isset($_POST['confirm_clear'])) {
    // Get the date threshold
    $days = isset($_POST['days']) ? (int)$_POST['days'] : 30;
    $threshold_date = date('Y-m-d H:i:s', strtotime("-$days days"));
    
    // Delete logs older than the threshold
    $delete_query = "DELETE FROM logs WHERE timestamp < '$threshold_date'";
    if (mysqli_query($conn, $delete_query)) {
        $affected_rows = mysqli_affected_rows($conn);
        
        // Log this action
        $user_id = $_SESSION['user_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        mysqli_query($conn, "INSERT INTO logs (user_id, action, ip_address) VALUES ('$user_id', 'Cleared $affected_rows logs older than $days days', '$ip')");
        
        $_SESSION['success'] = "Successfully cleared $affected_rows logs older than $days days.";
    } else {
        $_SESSION['error'] = "Error clearing logs: " . mysqli_error($conn);
    }
    
    header('Location: logs.php');
    exit;
}

// Export logs functionality
if (isset($_GET['export'])) {
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add CSV header row
    fputcsv($output, ['Timestamp', 'Username', 'User ID', 'Action', 'IP Address']);
    
    // Fetch all logs based on current filters but without pagination
    $export_query = "SELECT l.*, u.username 
                    FROM logs l 
                    LEFT JOIN users u ON l.user_id = u.id 
                    $search_condition 
                    ORDER BY l.timestamp DESC";
    $export_result = mysqli_query($conn, $export_query);
    
    // Add data rows
    while ($row = mysqli_fetch_assoc($export_result)) {
        fputcsv($output, [
            $row['timestamp'],
            $row['username'] ?? 'Unknown',
            $row['user_id'],
            $row['action'],
            $row['ip_address']
        ]);
    }
    
    // Close the output stream
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs - AGAPE CHAMA</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <?php include_once('../includes/admin_header.php'); ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h4>System Activity Logs</h4>
                        <div>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#clearLogsModal">
                                <i class="fas fa-trash"></i> Clear Old Logs
                            </button>
                            <a href="logs.php?export=1<?php 
                                echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                echo ($user_filter > 0) ? '&user_id=' . $user_filter : '';
                            ?>" class="btn btn-success">
                                <i class="fas fa-file-export"></i> Export to CSV
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        
                        <!-- Search and Filter Form -->
                        <form method="GET" class="mb-4">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control" placeholder="Search logs..." value="<?php echo htmlspecialchars($search); ?>">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="col-md-2">
                                    <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo $date_from; ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo $date_to; ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <select name="user_id" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Users</option>
                                        <?php foreach ($users as $id => $username): ?>
                                            <option value="<?php echo $id; ?>" <?php echo ($user_filter == $id) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($username); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || $user_filter > 0): ?>
                                        <a href="logs.php" class="btn btn-secondary w-100">Clear Filters</a>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                        
                        <!-- Log Summary -->
                        <div class="row mb-4">
                            <?php
                            // Get log statistics
                            $today = date('Y-m-d');
                            $yesterday = date('Y-m-d', strtotime('-1 day'));
                            $last_week = date('Y-m-d', strtotime('-7 days'));
                            
                            $stats_query = "SELECT 
                                COUNT(*) as total_logs,
                                SUM(CASE WHEN DATE(timestamp) = '$today' THEN 1 ELSE 0 END) as today_logs,
                                SUM(CASE WHEN DATE(timestamp) = '$yesterday' THEN 1 ELSE 0 END) as yesterday_logs,
                                SUM(CASE WHEN DATE(timestamp) BETWEEN '$last_week' AND '$today' THEN 1 ELSE 0 END) as week_logs
                            FROM logs";
                            $stats_result = mysqli_query($conn, $stats_query);
                            $stats = mysqli_fetch_assoc($stats_result);
                            ?>
                            
                            <div class="col-md-3">
                                <div class="card bg-primary text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Total Logs</h5>
                                        <h3><?php echo number_format($stats['total_logs']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Today</h5>
                                        <h3><?php echo number_format($stats['today_logs']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Yesterday</h5>
                                        <h3><?php echo number_format($stats['yesterday_logs']); ?></h3>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="card bg-warning text-dark">
                                    <div class="card-body text-center">
                                        <h5 class="card-title">Last 7 Days</h5>
                                        <h3><?php echo number_format($stats['week_logs']); ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Logs Table -->
                        <?php if (mysqli_num_rows($result) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($log = mysqli_fetch_assoc($result)): ?>
                                            <tr>
                                                <td><?php echo date('Y-m-d H:i:s', strtotime($log['timestamp'])); ?></td>
                                                <td>
                                                    <?php if (!empty($log['username'])): ?>
                                                        <a href="user_edit.php?id=<?php echo $log['user_id']; ?>">
                                                            <?php echo htmlspecialchars($log['username']); ?>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">Unknown (ID: <?php echo $log['user_id']; ?>)</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="logs.php?page=1<?php 
                                                    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                    echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                    echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                    echo ($user_filter > 0) ? '&user_id=' . $user_filter : '';
                                                ?>">
                                                    First
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="logs.php?page=<?php echo $page - 1; ?><?php 
                                                    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                    echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                    echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                    echo ($user_filter > 0) ? '&user_id=' . $user_filter : '';
                                                ?>">
                                                    Previous
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $page + 2);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++):
                                        ?>
                                            <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                                <a class="page-link" href="logs.php?page=<?php echo $i; ?><?php 
                                                    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                    echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                    echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                    echo ($user_filter > 0) ? '&user_id=' . $user_filter : '';
                                                ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="logs.php?page=<?php echo $page + 1; ?><?php 
                                                    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                    echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                    echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                    echo ($user_filter > 0) ? '&user_id=' . $user_filter : '';
                                                ?>">
                                                    Next
                                                </a>
                                            </li>
                                            <li class="page-item">
                                                <a class="page-link" href="logs.php?page=<?php echo $total_pages; ?><?php 
                                                    echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                    echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                    echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                    echo ($user_filter > 0) ? '&user_id=' . $user_filter : '';
                                                ?>">
                                                    Last
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">No logs found matching your criteria.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Clear Logs Modal -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1" aria-labelledby="clearLogsModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="clearLogsModalLabel">Clear Old Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="alert alert-danger">
                            <strong>Warning:</strong> This action will permanently delete old logs from the system. This cannot be undone.
                        </div>
                        
                        <div class="mb-3">
                            <label for="days" class="form-label">Delete logs older than:</label>
                            <select name="days" id="days" class="form-select" required>
                                <option value="30">30 days</option>
                                <option value="60">60 days</option>
                                <option value="90">90 days</option>
                                <option value="180">6 months</option>
                                <option value="365">1 year</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="confirm_clear" name="confirm_clear" required>
                            <label class="form-check-label" for="confirm_clear">
                                I understand that this action cannot be undone.
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="clear_logs" class="btn btn-danger">Clear Old Logs</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include_once('../includes/admin_footer.php'); ?>
</body>
</html>