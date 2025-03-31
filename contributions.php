<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // Require authentication

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get user's contributions with pagination
$stmt = $pdo->prepare("SELECT * FROM contributions WHERE user_id = ? ORDER BY contribution_date DESC LIMIT ? OFFSET ?");
$stmt->execute([$_SESSION['user_id'], $records_per_page, $offset]);
$contributions = $stmt->fetchAll();

// Get total number of contributions for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM contributions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Get user's total contributions
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM contributions WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['user_id']]);
$totalContributions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;

// Get user's pending contributions
$stmt = $pdo->prepare("SELECT SUM(amount) as total FROM contributions WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pendingContributions = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">My Contributions</h4>
                    <a href="contribute.php" class="btn btn-light">
                        <i class="fas fa-plus"></i> Make New Contribution
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
                    
                    <!-- Contribution Summary -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Total Approved Contributions</h5>
                                    <h2 class="text-success"><?php echo formatCurrency($totalContributions); ?></h2>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-light">
                                <div class="card-body">
                                    <h5 class="card-title">Pending Contributions</h5>
                                    <h2 class="text-warning"><?php echo formatCurrency($pendingContributions); ?></h2>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Contributions Table -->
                    <?php if (count($contributions) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Reference</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Transaction ID</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($contributions as $contribution): ?>
                                        <tr>
                                            <td><?php echo formatDate($contribution['contribution_date']); ?></td>
                                            <td><?php echo htmlspecialchars($contribution['reference_number']); ?></td>
                                            <td><?php echo formatCurrency($contribution['amount']); ?></td>
                                            <td><?php echo ucfirst($contribution['payment_method']); ?></td>
                                            <td><?php echo htmlspecialchars($contribution['transaction_id'] ?: 'N/A'); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo ($contribution['status'] == 'approved') ? 'success' : 
                                                        (($contribution['status'] == 'pending') ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($contribution['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="contribution_details.php?id=<?php echo $contribution['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Contributions pagination">
                                    <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="contributions.php?page=1">&laquo; First</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="contributions.php?page=<?php echo $page - 1; ?>">Previous</a>
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
                                            <a class="page-link" href="contributions.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="contributions.php?page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="contributions.php?page=<?php echo $total_pages; ?>">Last &raquo;</a>
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
                            <p>You haven't made any contributions yet.</p>
                            <a href="contribute.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Make Your First Contribution
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>