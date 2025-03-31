<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Set default date range (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Handle custom date range
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// Handle report type
$report_type = isset($_GET['type']) ? $_GET['type'] : 'contributions';

// Generate report data based on type
$report_data = [];
$chart_data = [];
$chart_labels = [];

switch ($report_type) {
    case 'contributions':
        // Get contributions summary
        $stmt = $pdo->prepare("
            SELECT 
                SUM(amount) as total_amount,
                COUNT(*) as total_count,
                AVG(amount) as average_amount,
                MAX(amount) as max_amount,
                MIN(amount) as min_amount
            FROM contributions
            WHERE contribution_date BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get contributions by date
        $stmt = $pdo->prepare("
            SELECT 
                contribution_date,
                SUM(amount) as daily_total,
                COUNT(*) as daily_count
            FROM contributions
            WHERE contribution_date BETWEEN ? AND ?
            GROUP BY contribution_date
            ORDER BY contribution_date
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare chart data
        foreach ($report_data as $row) {
            $chart_labels[] = date('M d', strtotime($row['contribution_date']));
            $chart_data[] = $row['daily_total'];
        }
        
        // Get top contributors
        $stmt = $pdo->prepare("
            SELECT 
                u.name,
                SUM(c.amount) as total_contributed,
                COUNT(c.id) as contribution_count
            FROM contributions c
            JOIN users u ON c.user_id = u.id
            WHERE c.contribution_date BETWEEN ? AND ?
            GROUP BY u.id, u.name
            ORDER BY total_contributed DESC
            LIMIT 10
        ");
        $stmt->execute([$start_date, $end_date]);
        $top_contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'loans':
        // Get loans summary
        $stmt = $pdo->prepare("
            SELECT 
                SUM(amount) as total_amount,
                COUNT(*) as total_count,
                AVG(amount) as average_amount,
                MAX(amount) as max_amount,
                MIN(amount) as min_amount
            FROM loans
            WHERE loan_date BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get loans by status
        $stmt = $pdo->prepare("
            SELECT 
                status,
                COUNT(*) as count,
                SUM(amount) as total
            FROM loans
            WHERE loan_date BETWEEN ? AND ?
            GROUP BY status
        ");
        $stmt->execute([$start_date, $end_date]);
        $status_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get loans by date
        $stmt = $pdo->prepare("
            SELECT 
                loan_date,
                SUM(amount) as daily_total,
                COUNT(*) as daily_count
            FROM loans
            WHERE loan_date BETWEEN ? AND ?
            GROUP BY loan_date
            ORDER BY loan_date
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare chart data
        foreach ($report_data as $row) {
            $chart_labels[] = date('M d', strtotime($row['loan_date']));
            $chart_data[] = $row['daily_total'];
        }
        
        // Get loan repayments
        $stmt = $pdo->prepare("
            SELECT 
                SUM(lr.amount) as total_repaid,
                COUNT(lr.id) as repayment_count
            FROM loan_repayments lr
            JOIN loans l ON lr.loan_id = l.id
            WHERE lr.repayment_date BETWEEN ? AND ?
        ");
        $stmt->execute([$start_date, $end_date]);
        $repayments = $stmt->fetch(PDO::FETCH_ASSOC);
        break;
        
    case 'members':
        // Get members summary
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_members,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_members,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive_members,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_members,
                SUM(CASE WHEN created_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as new_members
            FROM users
            WHERE user_role = 'member'
        ");
        $stmt->execute([$start_date, $end_date]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get new members by date
        $stmt = $pdo->prepare("
            SELECT 
                DATE(created_at) as join_date,
                COUNT(*) as daily_count
            FROM users
            WHERE user_role = 'member' AND created_at BETWEEN ? AND ?
            GROUP BY DATE(created_at)
            ORDER BY join_date
        ");
        $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare chart data
        foreach ($report_data as $row) {
            $chart_labels[] = date('M d', strtotime($row['join_date']));
            $chart_data[] = $row['daily_count'];
        }
        
        // Get member activity
        $stmt = $pdo->prepare("
            SELECT 
                u.name,
                COUNT(DISTINCT c.id) as contribution_count,
                COUNT(DISTINCT l.id) as loan_count,
                COUNT(DISTINCT ma.id) as meeting_attendance
            FROM users u
            LEFT JOIN contributions c ON u.id = c.user_id AND c.contribution_date BETWEEN ? AND ?
            LEFT JOIN loans l ON u.id = l.user_id AND l.loan_date BETWEEN ? AND ?
            LEFT JOIN meeting_attendees ma ON u.id = ma.user_id AND ma.attended = true
            LEFT JOIN meetings m ON ma.meeting_id = m.id AND m.date BETWEEN ? AND ?
            WHERE u.user_role = 'member'
            GROUP BY u.id, u.name
            ORDER BY contribution_count DESC, loan_count DESC
            LIMIT 10
        ");
        $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
        $member_activity = $stmt->fetchAll(PDO::FETCH_ASSOC);
        break;
        
    case 'projects':
        // Get projects summary
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as total_projects,
                SUM(target_amount) as total_target,
                SUM(current_amount) as total_raised,
                AVG(current_amount / target_amount * 100) as average_progress
            FROM projects
            WHERE created_at <= ?
        ");
        $stmt->execute([$end_date . ' 23:59:59']);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get project contributions
        $stmt = $pdo->prepare("
            SELECT 
                p.title as project_name,
                p.target_amount,
                p.current_amount,
                (p.current_amount / p.target_amount * 100) as progress,
                COUNT(DISTINCT pc.user_id) as contributor_count
            FROM projects p
            LEFT JOIN project_contributions pc ON p.id = pc.project_id AND pc.contribution_date BETWEEN ? AND ?
            WHERE p.created_at <= ?
            GROUP BY p.id, p.title, p.target_amount, p.current_amount
            ORDER BY progress DESC
        ");
        $stmt->execute([$start_date, $end_date, $end_date . ' 23:59:59']);
        $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare chart data for project progress
        foreach ($report_data as $row) {
            $chart_labels[] = $row['project_name'];
            $chart_data[] = $row['progress'];
        }
        break;
        
    case 'financial':
        // Get overall financial summary
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT SUM(amount) FROM contributions WHERE contribution_date BETWEEN ? AND ?) as total_contributions,
                (SELECT SUM(amount) FROM loans WHERE status = 'approved' AND loan_date BETWEEN ? AND ?) as total_loans_given,
                (SELECT SUM(amount) FROM loan_repayments WHERE repayment_date BETWEEN ? AND ?) as total_repayments,
                (SELECT SUM(total_savings) FROM user_accounts) as total_savings,
                (SELECT SUM(amount) FROM project_contributions WHERE contribution_date BETWEEN ? AND ?) as total_project_contributions
            FROM dual
        ");
        $stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Calculate net cash flow
        $cash_inflow = ($summary['total_contributions'] ?? 0) + ($summary['total_repayments'] ?? 0);
        $cash_outflow = ($summary['total_loans_given'] ?? 0) + ($summary['total_project_contributions'] ?? 0);
        $net_cash_flow = $cash_inflow - $cash_outflow;
        
        // Get monthly financial data
        $stmt = $pdo->prepare("
            SELECT 
                DATE_TRUNC('month', contribution_date) as month,
                SUM(amount) as monthly_contributions
            FROM contributions
            WHERE contribution_date BETWEEN ? AND ?
            GROUP BY month
            ORDER BY month
        ");
        $stmt->execute([$start_date, $end_date]);
        $monthly_contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("
            SELECT 
                DATE_TRUNC('month', repayment_date) as month,
                SUM(amount) as monthly_repayments
            FROM loan_repayments
            WHERE repayment_date BETWEEN ? AND ?
            GROUP BY month
            ORDER BY month
        ");
        $stmt->execute([$start_date, $end_date]);
        $monthly_repayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare chart data
        $months = [];
        $contributions_data = [];
        $repayments_data = [];
        
        // Initialize with all months in range
        $current_month = new DateTime($start_date);
        $end_month = new DateTime($end_date);
        $end_month->modify('first day of next month');
        
        while ($current_month < $end_month) {
            $month_key = $current_month->format('Y-m');
            $months[$month_key] = $current_month->format('M Y');
            $contributions_data[$month_key] = 0;
            $repayments_data[$month_key] = 0;
            $current_month->modify('+1 month');
        }
        
        // Fill in actual data
        foreach ($monthly_contributions as $row) {
            $month_key = date('Y-m', strtotime($row['month']));
            $contributions_data[$month_key] = $row['monthly_contributions'];
        }
        
        foreach ($monthly_repayments as $row) {
            $month_key = date('Y-m', strtotime($row['month']));
            $repayments_data[$month_key] = $row['monthly_repayments'];
        }
        
        $chart_labels = array_values($months);
        $chart_contributions = array_values($contributions_data);
        $chart_repayments = array_values($repayments_data);
        break;
        
    default:
        // Default to contributions
        header("Location: reports.php?type=contributions");
        exit();
}

// Handle export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Add report title and date range
    fputcsv($output, [ucfirst($report_type) . ' Report']);
    fputcsv($output, ['Date Range:', date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date))]);
    fputcsv($output, []); // Empty line
    
    // Add summary data
    if (isset($summary)) {
        fputcsv($output, ['Summary']);
        foreach ($summary as $key => $value) {
            $label = ucwords(str_replace('_', ' ', $key));
            if (strpos($key, 'amount') !== false || strpos($key, 'total') !== false) {
                $value = 'KES ' . number_format($value, 2);
            } elseif (strpos($key, 'average') !== false && strpos($key, 'progress') !== false) {
                $value = number_format($value, 2) . '%';
            }
            fputcsv($output, [$label, $value]);
        }
        fputcsv($output, []); // Empty line
    }
    
    // Add detailed data
    if (!empty($report_data)) {
        // Get column headers from first row
        $headers = array_keys($report_data[0]);
        $headers = array_map(function($header) {
            return ucwords(str_replace('_', ' ', $header));
        }, $headers);
        
        fputcsv($output, $headers);
        
          foreach ($report_data as $row) {
            $formatted_row = [];
            foreach ($row as $key => $value) {
                if (strpos($key, 'amount') !== false || strpos($key, 'total') !== false) {
                    $formatted_row[] = number_format($value, 2);
                } elseif (strpos($key, 'date') !== false) {
                    $formatted_row[] = date('Y-m-d', strtotime($value));
                } elseif (strpos($key, 'progress') !== false) {
                    $formatted_row[] = number_format($value, 2) . '%';
                } else {
                    $formatted_row[] = $value;
                }
            }
            fputcsv($output, $formatted_row);
        }
    }
    
    exit();
}
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">         </h1>
        <a href="reports.php?type=<?= $report_type ?>&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>&export=csv" 
           class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
            <i class="fas fa-download fa-sm text-white-50"></i> Export Report
        </a>
    </div>

    <!-- Report Filters -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Report Filters</h6>
        </div>
        <div class="card-body">
            <form method="get" action="reports.php" class="form-inline">
                <div class="form-group mb-2 mr-3">
                    <label for="type" class="mr-2">Report Type:</label>
                    <select name="type" id="type" class="form-control">
                        <option value="contributions" <?= $report_type == 'contributions' ? 'selected' : '' ?>>Contributions</option>
                        <option value="loans" <?= $report_type == 'loans' ? 'selected' : '' ?>>Loans</option>
                        <option value="members" <?= $report_type == 'members' ? 'selected' : '' ?>>Members</option>
                        <option value="projects" <?= $report_type == 'projects' ? 'selected' : '' ?>>Projects</option>
                        <option value="financial" <?= $report_type == 'financial' ? 'selected' : '' ?>>Financial Overview</option>
                    </select>
                </div>
                
                <div class="form-group mb-2 mr-3">
                    <label for="start_date" class="mr-2">Start Date:</label>
                    <input type="date" name="start_date" id="start_date" class="form-control" value="<?= $start_date ?>">
                </div>
                
                <div class="form-group mb-2 mr-3">
                    <label for="end_date" class="mr-2">End Date:</label>
                    <input type="date" name="end_date" id="end_date" class="form-control" value="<?= $end_date ?>">
                </div>
                
                <button type="submit" class="btn btn-primary mb-2">Generate Report</button>
            </form>
        </div>
    </div>

    <!-- Report Content -->
    <div class="row">
        <!-- Summary Card -->
        <div class="col-lg-12 mb-4">
            <div class="card shadow">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <?= ucfirst($report_type) ?> Summary 
                        <small class="text-muted">(<?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?>)</small>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($report_type == 'contributions' && isset($summary)): ?>
                        <div class="row">
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Contributions</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['total_amount'], 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Number of Contributions</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['total_count']) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-list fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    Average Contribution</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['average_amount'], 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-calculator fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Highest Contribution</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['max_amount'], 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-trophy fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($report_type == 'loans' && isset($summary)): ?>
                        <div class="row">
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Loans</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['total_amount'], 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Number of Loans</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['total_count']) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-list fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    Average Loan</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['average_amount'], 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-calculator fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Total Repayments</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($repayments['total_repaid'] ?? 0, 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loan Status Breakdown -->
                        <?php if (isset($status_data) && count($status_data) > 0): ?>
                            <div class="row mt-3">
                                <div class="col-lg-12">
                                    <h5 class="mb-3">Loan Status Breakdown</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Status</th>
                                                    <th>Count</th>
                                                    <th>Total Amount</th>
                                                    <th>Percentage</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php 
                                                $total_loans = array_sum(array_column($status_data, 'count'));
                                                foreach ($status_data as $status): 
                                                    $percentage = ($status['count'] / $total_loans) * 100;
                                                ?>
                                                    <tr>
                                                        <td><?= ucfirst($status['status']) ?></td>
                                                        <td><?= number_format($status['count']) ?></td>
                                                        <td>KES <?= number_format($status['total'], 2) ?></td>
                                                        <td><?= number_format($percentage, 1) ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($report_type == 'members' && isset($summary)): ?>
                        <div class="row">
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Members</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['total_members']) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-users fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Active Members</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['active_members']) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-user-check fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    New Members</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['new_members']) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-user-plus fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Inactive Members</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['inactive_members']) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-user-times fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Member Activity -->
                        <?php if (isset($member_activity) && count($member_activity) > 0): ?>
                            <div class="row mt-3">
                                <div class="col-lg-12">
                                    <h5 class="mb-3">Top Member Activity</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Member Name</th>
                                                    <th>Contributions</th>
                                                    <th>Loans</th>
                                                    <th>Meeting Attendance</th>
                                                    <th>Total Activity</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($member_activity as $member): 
                                                    $total_activity = $member['contribution_count'] + $member['loan_count'] + $member['meeting_attendance'];
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($member['name']) ?></td>
                                                        <td><?= number_format($member['contribution_count']) ?></td>
                                                        <td><?= number_format($member['loan_count']) ?></td>
                                                        <td><?= number_format($member['meeting_attendance']) ?></td>
                                                        <td><?= number_format($total_activity) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($report_type == 'projects' && isset($summary)): ?>
                        <div class="row">
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Projects</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['total_projects']) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Target Amount</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['total_target'], 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-bullseye fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    Total Raised</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['total_raised'], 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 mb-4">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Average Progress</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($summary['average_progress'], 1) ?>%</div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-chart-line fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Project Progress -->
                        <?php if (isset($report_data) && count($report_data) > 0): ?>
                            <div class="row mt-3">
                                <div class="col-lg-12">
                                    <h5 class="mb-3">Project Progress</h5>
                                    <div class="table-responsive">
                                        <table class="table table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>Project Name</th>
                                                    <th>Target Amount</th>
                                                    <th>Current Amount</th>
                                                    <th>Progress</th>
                                                    <th>Contributors</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($report_data as $project): ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($project['project_name']) ?></td>
                                                        <td>KES <?= number_format($project['target_amount'], 2) ?></td>
                                                        <td>KES <?= number_format($project['current_amount'], 2) ?></td>
                                                        <td>
                                                            <div class="progress" style="height: 20px;">
                                                                <div class="progress-bar bg-success" role="progressbar" 
                                                                     style="width: <?= min(100, $project['progress']) ?>%;" 
                                                                     aria-valuenow="<?= $project['progress'] ?>" aria-valuemin="0" aria-valuemax="100">
                                                                    <?= number_format($project['progress'], 1) ?>%
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td><?= number_format($project['contributor_count']) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php elseif ($report_type == 'financial' && isset($summary)): ?>
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card border-left-primary shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                    Total Contributions</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['total_contributions'] ?? 0, 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <div class="card border-left-success shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                                    Total Savings</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['total_savings'] ?? 0, 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-4">
                                <div class="card border-left-info shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                                    Net Cash Flow</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($net_cash_flow, 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-exchange-alt fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card border-left-warning shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                                    Total Loans Given</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['total_loans_given'] ?? 0, 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-hand-holding-usd fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="card border-left-danger shadow h-100 py-2">
                                    <div class="card-body">
                                        <div class="row no-gutters align-items-center">
                                            <div class="col mr-2">
                                                <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                                    Total Repayments</div>
                                                <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($summary['total_repayments'] ?? 0, 2) ?></div>
                                            </div>
                                            <div class="col-auto">
                                                <i class="fas fa-money-check-alt fa-2x text-gray-300"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Monthly Financial Data -->
                        <div class="row mt-4">
                            <div class="col-lg-12">
                                <h5 class="mb-3">Monthly Financial Overview</h5>
                                <div class="chart-container" style="position: relative; height:300px;">
                                    <canvas id="financialChart"></canvas>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Chart Section -->
        <?php if (!empty($chart_labels) && !empty($chart_data)): ?>
            <div class="col-lg-12 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <?= ucfirst($report_type) ?> Chart
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="chart-container" style="position: relative; height:400px;">
                            <?php if ($report_type == 'financial'): ?>
                                <!-- Financial chart is handled separately -->
                            <?php elseif ($report_type == 'projects'): ?>
                                <canvas id="projectsChart"></canvas>
                            <?php else: ?>
                                <canvas id="reportChart"></canvas>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Detailed Data Section -->
        <?php if (!empty($report_data)): ?>
            <div class="col-lg-12 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Detailed <?= ucfirst($report_type) ?> Data
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <?php foreach (array_keys($report_data[0]) as $header): ?>
                                            <th><?= ucwords(str_replace('_', ' ', $header)) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($report_data as $row): ?>
                                        <tr>
                                            <?php foreach ($row as $key => $value): ?>
                                                <td>
                                                    <?php 
                                                    if (strpos($key, 'amount') !== false || strpos($key, 'total') !== false) {
                                                        echo 'KES ' . number_format($value, 2);
                                                    } elseif (strpos($key, 'date') !== false) {
                                                        echo date('M d, Y', strtotime($value));
                                                    } elseif (strpos($key, 'progress') !== false) {
                                                        echo number_format($value, 1) . '%';
                                                    } else {
                                                        echo htmlspecialchars($value);
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Additional Data Sections -->
        <?php if ($report_type == 'contributions' && isset($top_contributors) && count($top_contributors) > 0): ?>
            <div class="col-lg-12 mb-4">
                <div class="card shadow">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            Top Contributors
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Member Name</th>
                                        <th>Total Contributed</th>
                                        <th>Number of Contributions</th>
                                        <th>Average Contribution</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_contributors as $contributor): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($contributor['name']) ?></td>
                                            <td>KES <?= number_format($contributor['total_contributed'], 2) ?></td>
                                            <td><?= number_format($contributor['contribution_count']) ?></td>
                                            <td>KES <?= number_format($contributor['total_contributed'] / $contributor['contribution_count'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Page level plugins -->
<script src="vendor/chart.js/Chart.min.js"></script>

<!-- Page level custom scripts -->
<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#dataTable').DataTable();
    
    <?php if (!empty($chart_labels) && !empty($chart_data) && $report_type != 'financial' && $report_type != 'projects'): ?>
    // Standard Chart
    var ctx = document.getElementById("reportChart");
    var myChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: "<?= ucfirst($report_type) ?>",
                lineTension: 0.3,
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                borderColor: "rgba(78, 115, 223, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(78, 115, 223, 1)",
                pointBorderColor: "rgba(78, 115, 223, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: <?= json_encode($chart_data) ?>,
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                xAxes: [{
                    time: {
                        unit: 'date'
                    },
                    gridLines: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        maxTicksLimit: 7
                    }
                }],
                yAxes: [{
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value, index, values) {
                            return 'KES ' + number_format(value);
                        }
                    },
                    gridLines: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                }],
            },
            legend: {
                display: false
            },
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                titleMarginBottom: 10,
                titleFontColor: '#6e707e',
                titleFontSize: 14,
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                intersect: false,
                mode: 'index',
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem, chart) {
                        var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ': KES ' + number_format(tooltipItem.yLabel);
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if ($report_type == 'projects' && !empty($chart_labels) && !empty($chart_data)): ?>
    // Projects Chart (Bar chart for progress)
    var ctx = document.getElementById("projectsChart");
    var myBarChart = new Chart(ctx, {
        type: 'horizontalBar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [{
                label: "Progress (%)",
                backgroundColor: "rgba(28, 200, 138, 0.8)",
                borderColor: "rgba(28, 200, 138, 1)",
                data: <?= json_encode($chart_data) ?>,
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                xAxes: [{
                    ticks: {
                        min: 0,
                        max: 100,
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value, index, values) {
                            return value + '%';
                        }
                    },
                    gridLines: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                }],
                yAxes: [{
                    gridLines: {
                        display: false,
                        drawBorder: false
                    }
                }],
            },
            legend: {
                display: false
            },
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                titleMarginBottom: 10,
                titleFontColor: '#6e707e',
                titleFontSize: 14,
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: false,
                intersect: false,
                mode: 'index',
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem, chart) {
                        var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ': ' + number_format(tooltipItem.xLabel, 1) + '%';
                    }
                }
            }
        }
    });
    <?php endif; ?>
    
    <?php if ($report_type == 'financial' && isset($chart_labels) && isset($chart_contributions) && isset($chart_repayments)): ?>
    // Financial Chart (Bar chart with multiple datasets)
    var ctx = document.getElementById("financialChart");
    var myBarChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels) ?>,
            datasets: [
                {
                    label: "Contributions",
                    backgroundColor: "rgba(78, 115, 223, 0.8)",
                    borderColor: "rgba(78, 115, 223, 1)",
                    data: <?= json_encode($chart_contributions) ?>,
                },
                {
                    label: "Repayments",
                    backgroundColor: "rgba(28, 200, 138, 0.8)",
                    borderColor: "rgba(28, 200, 138, 1)",
                    data: <?= json_encode($chart_repayments) ?>,
                }
            ],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                xAxes: [{
                    gridLines: {
                        display: false,
                        drawBorder: false
                    }
                }],
                yAxes: [{
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value, index, values) {
                            return 'KES ' + number_format(value);
                        }
                    },
                    gridLines: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                }],
            },
            legend: {
                display: true,
                position: 'top'
            },
            tooltips: {
                backgroundColor: "rgb(255,255,255)",
                bodyFontColor: "#858796",
                titleMarginBottom: 10,
                titleFontColor: '#6e707e',
                titleFontSize: 14,
                borderColor: '#dddfeb',
                borderWidth: 1,
                xPadding: 15,
                yPadding: 15,
                displayColors: true,
                intersect: false,
                mode: 'index',
                caretPadding: 10,
                callbacks: {
                    label: function(tooltipItem, chart) {
                        var datasetLabel = chart.datasets[tooltipItem.datasetIndex].label || '';
                        return datasetLabel + ': KES ' + number_format(tooltipItem.yLabel);
                    }
                }
            }
        }
    });
    <?php endif; ?>
});

// Format numbers with commas
function number_format(number, decimals = 0, dec_point = '.', thousands_sep = ',') {
    number = (number + '').replace(',', '').replace(' ', '');
    var n = !isFinite(+number) ? 0 : +number,
        prec = !isFinite(+decimals) ? 0 : Math.abs(decimals),
        sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep,
        dec = (typeof dec_point === 'undefined') ? '.' : dec_point,
        s = '',
        toFixedFix = function(n, prec) {
            var k = Math.pow(10, prec);
            return '' + Math.round(n * k) / k;
        };
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    return s.join(dec);
}
</script>

<!-- Add a print button -->
<script>
$(document).ready(function() {
    // Add print button to the page
    $('.d-sm-flex').append(
        '<a href="javascript:void(0)" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm ml-2" id="printReport">' +
        '<i class="fas fa-print fa-sm text-white-50"></i> Print Report</a>'
    );
    
    // Print functionality
    $('#printReport').on('click', function() {
        window.print();
    });
    
    // Add date range shortcuts
    var dateRangeForm = $('form[action="reports.php"]');
    var dateRangeButtons = 
        '<div class="form-group mb-2 ml-2">' +
        '<button type="button" class="btn btn-outline-secondary btn-sm mr-1" data-range="7">Last 7 Days</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-sm mr-1" data-range="30">Last 30 Days</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-sm mr-1" data-range="90">Last 90 Days</button>' +
        '<button type="button" class="btn btn-outline-secondary btn-sm" data-range="365">Last Year</button>' +
        '</div>';
    
    dateRangeForm.append(dateRangeButtons);
    
    // Date range shortcut functionality
    $('button[data-range]').on('click', function() {
        var days = $(this).data('range');
        var endDate = new Date();
        var startDate = new Date();
        startDate.setDate(startDate.getDate() - days);
        
        $('#end_date').val(endDate.toISOString().split('T')[0]);
        $('#start_date').val(startDate.toISOString().split('T')[0]);
        
        dateRangeForm.submit();
    });
});
</script>

<!-- Add print styles -->
<style>
@media print {
    .navbar, .sidebar, .card-header, form, .footer, #printReport, .btn, .dataTables_filter, .dataTables_length, .dataTables_paginate, .dataTables_info {
        display: none !important;
    }
    
    .container-fluid {
        width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .card-body {
        padding: 0 !important;
    }
    
    body {
        padding: 0 !important;
        margin: 0 !important;
    }
    
    /* Add report title for print */
    .container-fluid:before {
        content: "<?= ucfirst($report_type) ?> Report (<?= date('M d, Y', strtotime($start_date)) ?> to <?= date('M d, Y', strtotime($end_date)) ?>)";
        font-size: 18pt;
        font-weight: bold;
        display: block;
        text-align: center;
        margin-bottom: 20px;
    }
    
    /* Add page breaks where appropriate */
    .row {
        page-break-inside: avoid;
    }
    
    .col-lg-12 + .col-lg-12 {
        page-break-before: always;
    }
}
</style>

<?php include 'footer.php'; ?>