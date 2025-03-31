<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get quick statistics
$stats = [];

// Total members
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE user_role = 'member'");
$stats['total_members'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total contributions
$stmt = $pdo->query("SELECT SUM(amount) as total FROM contributions");
$stats['total_contributions'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Active loans
$stmt = $pdo->query("SELECT COUNT(*) as count FROM loans WHERE status = 'active'");
$stats['active_loans'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total loan amount
$stmt = $pdo->query("SELECT SUM(amount) as total FROM loans WHERE status = 'active'");
$stats['total_loan_amount'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Total savings
$stmt = $pdo->query("SELECT SUM(total_savings) as total FROM user_accounts");
$stats['total_savings'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Upcoming meetings
$stmt = $pdo->query("SELECT COUNT(*) as count FROM meetings WHERE date >= CURRENT_DATE");
$stats['upcoming_meetings'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent activities
$stmt = $pdo->query("
    SELECT al.*, u.name as user_name 
    FROM audit_logs al
    JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent members
$stmt = $pdo->query("
    SELECT * FROM users 
    WHERE user_role = 'member'
    ORDER BY created_at DESC
    LIMIT 5
");
$recent_members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent contributions
$stmt = $pdo->query("
    SELECT c.*, u.name as member_name
    FROM contributions c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.contribution_date DESC
    LIMIT 5
");
$recent_contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly contribution trends for current year
$stmt = $pdo->query("
    SELECT EXTRACT(MONTH FROM contribution_date) as month, SUM(amount) as total
    FROM contributions
    WHERE EXTRACT(YEAR FROM contribution_date) = EXTRACT(YEAR FROM CURRENT_DATE)
    GROUP BY EXTRACT(MONTH FROM contribution_date)
    ORDER BY month
");
$monthly_contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare data for chart
$months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$contribution_data = array_fill(0, 12, 0);

foreach ($monthly_contributions as $item) {
    $month_index = $item['month'] - 1; // Convert to 0-based index
    $contribution_data[$month_index] = $item['total'];
}
?>

<di class="container-fluid">

    <!-- Content Row -->
    <div class="row">
        <!-- Members Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Members</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total_members'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contributions Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Total Contributions</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['total_contributions'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-dollar-sign fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loans Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Active Loans</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active_loans'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Savings Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Total Savings</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">KES <?= number_format($stats['total_savings'], 2) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-piggy-bank fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Projects Card - NEW -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-danger shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                            Total Projects</div>
                        <?php
                        // Get projects count
                        $stmt = $pdo->query("SELECT COUNT(*) as count FROM projects");
                        $projects_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                        ?>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $projects_count ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Area Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <!-- Card Header - Dropdown -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Monthly Contributions Overview</h6>
                </div>
                <!-- Card Body -->
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="contributionsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pie Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <!-- Card Header - Dropdown -->
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Financial Distribution</h6>
                </div>
                <!-- Card Body -->
                <div class="card-body">
                    <div class="chart-pie pt-4 pb-2">
                        <canvas id="financialDistributionChart"></canvas>
                    </div>
                    <div class="mt-4 text-center small">
                        <span class="mr-2">
                            <i class="fas fa-circle text-primary"></i> Contributions
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-success"></i> Loans
                        </span>
                        <span class="mr-2">
                            <i class="fas fa-circle text-info"></i> Savings
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- Recent Members -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Members</h6>
                    <a href="members.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_members) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Joined</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_members as $member): ?>
                                        <tr>
                                            <td><a href="member_details.php?id=<?= $member['id'] ?>"><?= htmlspecialchars($member['name']) ?></a></td>
                                            <td><?= htmlspecialchars($member['email']) ?></td>
                                            <td><?= htmlspecialchars($member['phone']) ?></td>
                                            <td><?= date('M d, Y', strtotime($member['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No members found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Recent Contributions -->
        <div class="col-lg-6 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Contributions</h6>
                    <a href="contributions.php" class="btn btn-sm btn-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($recent_contributions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_contributions as $contribution): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($contribution['member_name']) ?></td>
                                            <td>KES <?= number_format($contribution['amount'], 2) ?></td>
                                            <td><?= date('M d, Y', strtotime($contribution['contribution_date'])) ?></td>
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
        </div>
    </div>

    
    <!-- Projects Section - NEW -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Recent Projects</h6>
                <a href="projects.php" class="btn btn-sm btn-primary">Manage Projects</a>
            </div>
            <div class="card-body">
                <?php
                // Fetch recent projects
                $stmt = $pdo->query("
                    SELECT * FROM projects 
                    ORDER BY created_at DESC
                    LIMIT 5
                ");
                $recent_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($recent_projects) > 0): 
                ?>
                    <div class="table-responsive">
                        <table class="table table-bordered" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_projects as $project): ?>
                                    <tr>
                                        <td><a href="project_view.php?id=<?= $project['id'] ?>"><?= htmlspecialchars($project['name'] ?? $project['title']) ?></a></td>
                                        <td><?= htmlspecialchars(substr($project['description'], 0, 50)) ?>...</td>
                                        <td>
                                            <?php if (isset($project['status'])): ?>
                                                <?php if ($project['status'] == 'completed'): ?>
                                                    <span class="badge badge-success">Completed</span>
                                                <?php elseif ($project['status'] == 'in_progress' || $project['status'] == 'active'): ?>
                                                    <span class="badge badge-primary">In Progress</span>
                                                <?php elseif ($project['status'] == 'planned' || $project['status'] == 'planning'): ?>
                                                    <span class="badge badge-info">Planned</span>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary"><?= ucfirst($project['status']) ?></span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Unknown</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($project['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center">No projects found. <a href="project_add.php">Add a new project</a>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Activity Timeline -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Activities</h6>
                </div>
                <div class="card-body">
                    <?php if (count($recent_activities) > 0): ?>
                        <div class="timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="timeline-item">
                                    <div class="timeline-marker"></div>
                                    <div class="timeline-content">
                                        <h6 class="timeline-title"><?= htmlspecialchars($activity['user_name']) ?></h6>
                                        <p><?= htmlspecialchars($activity['details']) ?></p>
                                        <p class="text-muted small"><?= date('M d, Y H:i', strtotime($activity['created_at'])) ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-center">No recent activities found.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Page level plugins -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>

<!-- Page level custom scripts -->
<script>
// Set new default font family and font color to mimic Bootstrap's default styling
Chart.defaults.global.defaultFontFamily = 'Nunito', '-apple-system,system-ui,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif';
Chart.defaults.global.defaultFontColor = '#858796';

// Area Chart - Monthly Contributions
function number_format(number, decimals, dec_point, thousands_sep) {
    // Format numbers with commas and decimal points
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

// Area Chart Example
var ctx = document.getElementById("contributionsChart");
var myLineChart = new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: "Contributions",
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
            data: <?= json_encode($contribution_data) ?>,
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
                    unit: 'month'
                },
                gridLines: {
                    display: false,
                    drawBorder: false
                },
                ticks: {
                    maxTicksLimit: 12
                }
            }],
            yAxes: [{
                ticks: {
                    maxTicksLimit: 5,
                    padding: 10,
                    // Include a currency sign in the ticks
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

// Pie Chart Example
var ctx2 = document.getElementById("financialDistributionChart");
var myPieChart = new Chart(ctx2, {
    type: 'doughnut',
    data: {
        labels: ["Contributions", "Loans", "Savings"],
        datasets: [{
            data: [
                <?= $stats['total_contributions'] ?>, 
                <?= $stats['total_loan_amount'] ?>, 
                <?= $stats['total_savings'] ?>
            ],
            backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc'],
            hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf'],
            hoverBorderColor: "rgba(234, 236, 244, 1)",
        }],
    },
    options: {
        maintainAspectRatio: false,
        tooltips: {
            backgroundColor: "rgb(255,255,255)",
            bodyFontColor: "#858796",
            borderColor: '#dddfeb',
            borderWidth: 1,
            xPadding: 15,
            yPadding: 15,
            displayColors: false,
            caretPadding: 10,
            callbacks: {
                label: function(tooltipItem, data) {
                    var label = data.labels[tooltipItem.index];
                    var value = data.datasets[0].data[tooltipItem.index];
                    return label + ': KES ' + number_format(value);
                }
            }
        },
        legend: {
            display: false
        },
        cutoutPercentage: 70,
    },
});
</script>

<?php include 'footer.php'; ?>