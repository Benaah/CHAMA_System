<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // Require authentication

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get user's loans with pagination
$stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? ORDER BY application_date DESC LIMIT ? OFFSET ?");
$stmt->execute([$_SESSION['user_id'], $records_per_page, $offset]);
$loans = $stmt->fetchAll();

// Get total number of loans for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

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
                            <li>Loan eligibility is <?php echo MAX_LOAN_MULTIPLIER; ?>x your total contributions</li>
                            <li>Current interest rate is <?php echo LOAN_INTEREST_RATE; ?>% per annum</li>
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
                                            <td><?php echo formatDate($loan['application_date']); ?></td>
                                            <td><?php echo formatCurrency($loan['amount']); ?></td>
                                            <td><?php echo $loan['interest_rate']; ?>%</td>
                                            <td><?php echo $loan['duration']; ?> months</td>
                                            <td><?php echo formatCurrency($loan['amount_repaid']); ?></td>
                                            <td><?php echo formatCurrency($loan['amount'] - $loan['amount_repaid']); ?></td>
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
                                                <?php if ($loan['status'] == 'disbursed' && ($loan['amount'] - $loan['amount_repaid']) > 0): ?>
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