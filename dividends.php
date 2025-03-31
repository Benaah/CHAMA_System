<?php
include 'includes/header.php';
include 'config.php'; // Database connection

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to access the dividends page.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if dividends table exists, if not create it
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.dividends')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Create dividends table
        $sql = "CREATE TABLE dividends (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            description TEXT,
            source_type VARCHAR(50),
            source_id INTEGER,
            distribution_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $pdo->exec($sql);
        
        // Create index for faster queries
        $pdo->exec("CREATE INDEX idx_dividends_user_id ON dividends(user_id)");
        $pdo->exec("CREATE INDEX idx_dividends_date ON dividends(distribution_date)");
    }
} catch (PDOException $e) {
    // Log the error but continue with default values
    error_log("Error checking/creating dividends table: " . $e->getMessage());
}

// Initialize variables
$dividends = [];
$total_dividends = 0;
$year_filter = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Get available years for filtering - check if distribution_date column exists
try {
    // First check if the distribution_date column exists
    $stmt = $pdo->prepare("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'dividends' AND column_name = 'distribution_date'
    ");
    $stmt->execute();
    $hasDistributionDate = $stmt->fetchColumn();
    
    if ($hasDistributionDate) {
        // Use distribution_date column
        $stmt = $pdo->prepare("
            SELECT DISTINCT EXTRACT(YEAR FROM distribution_date) as year 
            FROM dividends 
            WHERE user_id = ? 
            ORDER BY year DESC
        ");
        $stmt->execute([$user_id]);
    } else {
        // Fall back to created_at column
        $stmt = $pdo->prepare("
            SELECT DISTINCT EXTRACT(YEAR FROM created_at) as year 
            FROM dividends 
            WHERE user_id = ? 
            ORDER BY year DESC
        ");
        $stmt->execute([$user_id]);
    }
    
    $available_years = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // If there's an error, just use current year
    $available_years = [];
    error_log("Error fetching dividend years: " . $e->getMessage());
}

// If no years found, add current year to the list
if (empty($available_years)) {
    $available_years[] = date('Y');
}

// Fetch user's dividends with year filter - adapt to available columns
try {
    // Check which date column to use
    if ($hasDistributionDate) {
        $dateColumn = 'distribution_date';
    } else {
        $dateColumn = 'created_at';
    }
    
    $stmt = $pdo->prepare("
        SELECT d.*, 
               COALESCE(p.name, 'General Distribution') as source_name
        FROM dividends d
        LEFT JOIN projects p ON d.source_id = p.id AND d.source_type = 'project'
        WHERE d.user_id = ? AND EXTRACT(YEAR FROM d.$dateColumn) = ?
        ORDER BY d.$dateColumn DESC
    ");
    $stmt->execute([$user_id, $year_filter]);
    $dividends = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate total dividends for the selected year
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total 
        FROM dividends 
        WHERE user_id = ? AND EXTRACT(YEAR FROM $dateColumn) = ?
    ");
    $stmt->execute([$user_id, $year_filter]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_dividends = $result['total'] ?? 0;

    // Get all-time total dividends
    $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM dividends WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $all_time_total = $result['total'] ?? 0;
} catch (PDOException $e) {
    // If there's an error, set empty values
    $dividends = [];
    $total_dividends = 0;
    $all_time_total = 0;
    error_log("Error fetching dividend data: " . $e->getMessage());
}
?>

<div class="container py-5 mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-chart-pie mr-2 text-primary"></i> My Dividends</h2>
            <p class="lead text-muted">View your dividend distributions and earnings</p>
        </div>
        <div class="col-md-4 text-md-right">
            <div class="btn-group">
                <button type="button" class="btn btn-outline-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <i class="fas fa-calendar-alt mr-2"></i> <?= $year_filter ?>
                </button>
                <div class="dropdown-menu dropdown-menu-right">
                    <?php foreach ($available_years as $year): ?>
                        <a class="dropdown-item <?= ($year == $year_filter) ? 'active' : '' ?>" href="?year=<?= $year ?>">
                            <?= $year ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-4 mb-4">
            <!-- Dividends Summary Card -->
            <div class="card shadow-sm h-100">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-money-bill-wave mr-2"></i> Dividends Summary</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <h2 class="text-primary mb-0">KES <?= number_format($total_dividends, 2) ?></h2>
                        <p class="text-muted">Total Dividends for <?= $year_filter ?></p>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span>All-time Total:</span>
                        <span class="font-weight-bold">KES <?= number_format($all_time_total, 2) ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span>Number of Distributions:</span>
                        <span class="font-weight-bold"><?= count($dividends) ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <span>Average Distribution:</span>
                        <span class="font-weight-bold">
                            KES <?= count($dividends) > 0 ? number_format($total_dividends / count($dividends), 2) : '0.00' ?>
                        </span>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> Dividends are distributed based on your contributions and group profits.
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-8">
            <!-- Dividends List Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list mr-2"></i> Dividend Distributions</h5>
                </div>
                <div class="card-body">
                    <?php if (count($dividends) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover datatable">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Source</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dividends as $dividend): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($dividend[$dateColumn])) ?></td>
                                            <td><?= htmlspecialchars($dividend['source_name']) ?></td>
                                            <td><?= htmlspecialchars($dividend['description']) ?></td>
                                            <td class="text-success font-weight-bold">
                                                KES <?= number_format($dividend['amount'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-pie fa-4x text-muted mb-3"></i>
                            <p>No dividend distributions found for <?= $year_filter ?>.</p>
                            <?php if (count($available_years) > 1): ?>
                                <p>Try selecting a different year from the dropdown above.</p>
                            <?php else: ?>
                                <p>Dividends are distributed based on group profits and your contributions.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Dividends Chart Card -->
            <div class="card shadow-sm mt-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i> Dividend Trends</h5>
                </div>
                <div class="card-body">
                    <?php if (count($dividends) > 0): ?>
                        <canvas id="dividendsChart" width="100%" height="300"></canvas>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p>No data available to display chart.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dividend Information Card -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i> About Dividends</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="font-weight-bold">How Dividends Are Calculated</h6>
                    <p>Dividends are calculated based on:</p>
                    <ul>
                        <li>Your total contributions to the group</li>
                        <li>Length of membership</li>
                        <li>Group's overall profit from investments and projects</li>
                        <li>Dividend distribution policy as approved by members</li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6 class="font-weight-bold">Dividend Distribution Schedule</h6>
                    <p>Dividends are typically distributed:</p>
                    <ul>
                        <li>Quarterly for project-specific profits</li>
                        <li>Annually for general group profits</li>
                        <li>Special distributions may occur after successful project completions</li>
                    </ul>
                    <p>The next scheduled dividend distribution is expected in <strong>December <?= date('Y') ?></strong>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Dividends Chart -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if (count($dividends) > 0): ?>
    // Prepare data for chart
    const dates = [];
    const amounts = [];
    const cumulativeAmounts = [];
    let runningTotal = 0;
    
    // Process in reverse to get chronological order
    <?php 
    $chart_data = array_reverse($dividends);
    foreach ($chart_data as $dividend): 
    ?>
        dates.push('<?= date('M d', strtotime($dividend[$dateColumn])) ?>');
        amounts.push(<?= $dividend['amount'] ?>);
        runningTotal += <?= $dividend['amount'] ?>;
        cumulativeAmounts.push(runningTotal);
    <?php endforeach; ?>
    
    // Create chart
    const ctx = document.getElementById('dividendsChart').getContext('2d');
    const dividendsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [
                {
                    label: 'Dividend Amount',
                    backgroundColor: 'rgba(78, 115, 223, 0.2)',
                    borderColor: 'rgba(78, 115, 223, 1)',
                    pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    data: amounts,
                    yAxisID: 'y-axis-1',
                },
                {
                    label: 'Cumulative Dividends',
                    backgroundColor: 'rgba(28, 200, 138, 0.2)',
                    borderColor: 'rgba(28, 200, 138, 1)',
                    pointBackgroundColor: 'rgba(28, 200, 138, 1)',
                    pointBorderColor: '#fff',
                    pointRadius: 4,
                    data: cumulativeAmounts,
                    yAxisID: 'y-axis-2',
                    borderDash: [5, 5],
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            responsive: true,
                tooltips: {
                mode: 'index',
                intersect: false,
                callbacks: {
                    label: function(tooltipItem, data) {
                        let label = data.datasets[tooltipItem.datasetIndex].label || '';
                        if (label) {
                            label += ': ';
                        }
                        label += 'KES ' + parseFloat(tooltipItem.value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        return label;
                    }
                }
            },
            scales: {
                xAxes: [{
                    gridLines: {
                        display: false,
                        drawBorder: true
                    },
                    ticks: {
                        maxTicksLimit: 7
                    }
                }],
                yAxes: [
                    {
                        id: 'y-axis-1',
                        type: 'linear',
                        position: 'left',
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        },
                        gridLines: {
                            color: "rgb(234, 236, 244)",
                            zeroLineColor: "rgb(234, 236, 244)",
                            drawBorder: false,
                            borderDash: [2],
                            zeroLineBorderDash: [2]
                        }
                    },
                    {
                        id: 'y-axis-2',
                        type: 'linear',
                        position: 'right',
                        ticks: {
                            beginAtZero: true,
                            callback: function(value) {
                                return 'KES ' + value.toLocaleString();
                            }
                        },
                        gridLines: {
                            display: false,
                        }
                    }
                ]
            },
            legend: {
                display: true,
                position: 'top'
            }
        }
    });
    <?php endif; ?>
});
</script>

<?php include 'includes/footer.php'; ?>