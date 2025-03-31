<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // Require authentication

// Check if loans table exists, if not create it
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.loans')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Create loans table
        $sql = "CREATE TABLE loans (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            interest_rate DECIMAL(5, 2) NOT NULL DEFAULT 10.00,
            duration INTEGER NOT NULL DEFAULT 12,
            purpose TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            amount_repaid DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            application_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            approval_date TIMESTAMP,
            loan_date TIMESTAMP,
            due_date TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $pdo->exec($sql);
        
        // Create loan_repayments table
        $sql = "CREATE TABLE loan_repayments (
            id SERIAL PRIMARY KEY,
            loan_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            payment_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            payment_method VARCHAR(50),
            reference_number VARCHAR(100),
            notes TEXT,
            FOREIGN KEY (loan_id) REFERENCES loans(id),
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $pdo->exec($sql);
        
        // Create indexes for faster queries
        $pdo->exec("CREATE INDEX idx_loans_user_id ON loans(user_id)");
        $pdo->exec("CREATE INDEX idx_loans_status ON loans(status)");
        $pdo->exec("CREATE INDEX idx_loan_repayments_loan_id ON loan_repayments(loan_id)");
    }
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Error checking/creating loans tables: " . $e->getMessage());
}

// Check if application_date column exists, if not use created_at or add it
try {
    $stmt = $pdo->prepare("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'loans' AND column_name = 'application_date'
    ");
    $stmt->execute();
    $hasApplicationDate = $stmt->fetchColumn();
    
    if (!$hasApplicationDate) {
        // Check if created_at exists
        $stmt = $pdo->prepare("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'loans' AND column_name = 'created_at'
        ");
        $stmt->execute();
        $hasCreatedAt = $stmt->fetchColumn();
        
        if ($hasCreatedAt) {
            // Use created_at instead of application_date
            $applicationDateColumn = 'created_at';
        } else {
            // Add application_date column
            $pdo->exec("ALTER TABLE loans ADD COLUMN application_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP");
            $applicationDateColumn = 'application_date';
        }
    } else {
        $applicationDateColumn = 'application_date';
    }
} catch (PDOException $e) {
    // Default to application_date and handle errors in queries
    $applicationDateColumn = 'application_date';
    error_log("Error checking loan table columns: " . $e->getMessage());
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get user's loans with pagination
try {
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY $applicationDateColumn DESC LIMIT ? OFFSET ?");
    $stmt->execute([$_SESSION['user_id'], $records_per_page, $offset]);
    $loans = $stmt->fetchAll();
} catch (PDOException $e) {
    // If there's an error, set empty array
    $loans = [];
    error_log("Error fetching loans: " . $e->getMessage());
}

// Get total number of loans for pagination
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    // If there's an error, set default values
    $total_records = 0;
    $total_pages = 1;
    error_log("Error counting loans: " . $e->getMessage());
}

// Get user's total contributions for loan eligibility
$totalContributions = getUserTotalContributions($_SESSION['user_id']);
$loanEligibility = calculateLoanEligibility($totalContributions);

// Get user's outstanding loans
$outstandingLoans = getUserOutstandingLoans($_SESSION['user_id']);

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">My Loans</h4>
                    <a href="apply_loan.php" class="btn btn-light">
                        <i class="fas fa-plus"></i> Apply for Loan
                    </a>
                </div>
                <div class="card-body">
                    <?php
                    // Display flash messages
                    if(isset($_SESSION['error'])) {
                        echo '<div class="alert alert-danger alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                ' . $_SESSION['error'] . '
                              </div>';
                        unset($_SESSION['error']);
                    }
                    
                    if(isset($_SESSION['success'])) {
                        echo '<div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                ' . $_SESSION['success'] . '
                              </div>';
                        unset($_SESSION['success']);
                    }
                    ?>
                    
                    <!-- Loan Summary -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Total Contributions</h5>
                                    <h2 class="text-primary"><?php echo formatCurrency($totalContributions); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Outstanding Loans</h5>
                                    <h2 class="text-danger"><?php echo formatCurrency($outstandingLoans); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Loan Eligibility</h5>
                                    <h2 class="text-success"><?php echo formatCurrency($loanEligibility); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Loan Information -->
                    <div class="alert alert-info mb-4">
                        <h5><i class="fas fa-info-circle"></i> Loan Guidelines</h5>
                        <ul>
                            <li>Loan eligibility is <?php echo defined('MAX_LOAN_MULTIPLIER') ? MAX_LOAN_MULTIPLIER : 3; ?>x your total contributions</li>
                            <li>Current interest rate is <?php echo defined('LOAN_INTEREST_RATE') ? LOAN_INTEREST_RATE : 10; ?>% per annum</li>
                            <li>Maximum loan repayment period is 12 months</li>
                            <li>Early repayment is allowed without penalties</li>
                        </ul>
                    </div>
                    
                    <!-- Loans Table -->
                    <?php if (count($loans) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Application Date</th>
                                        <th>Amount</th>
                                        <th>Interest Rate</th>
                                        <th>Duration</th>
                                        <th>Repaid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loans as $loan): ?>
                                        <tr>
                                            <td><?php echo formatDate($loan[$applicationDateColumn] ?? date('Y-m-d H:i:s')); ?></td>
                                            <td><?php echo formatCurrency($loan['amount']); ?></td>
                                            <td><?php echo $loan['interest_rate']; ?>%</td>
                                            <td><?php echo $loan['duration']; ?> months</td>
                                            <td><?php echo formatCurrency($loan['amount_repaid'] ?? 0); ?></td>
                                            <td><?php echo formatCurrency($loan['amount'] - ($loan['amount_repaid'] ?? 0)); ?></td>
                                            <td>
                                                <?php 
                                                    $statusInfo = getLoanStatusLabel($loan['status']);
                                                ?>
                                                <span class="badge badge-<?php echo $statusInfo['class']; ?>">
                                                    <?php echo $statusInfo['label']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="loan_details.php?id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php if ($loan['status'] == 'disbursed' && ($loan['amount'] - ($loan['amount_repaid'] ?? 0)) > 0): ?>
                                                    <a href="loan_repayment.php?id=<?php echo $loan['id']; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-money-bill-wave"></i> Repay
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Loans pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="loans.php?page=1">&laquo; First</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="loans.php?page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#">&laquo; First</a>
                                        </li>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Calculate range of page numbers to display
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="loans.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="loans.php?page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="loans.php?page=<?php echo $total_pages; ?>">Last &raquo;</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#">Next</a>
                                        </li>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#">Last &raquo;</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p>You haven't applied for any loans yet.</p>
                            <a href="apply_loan.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Apply for a Loan
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>