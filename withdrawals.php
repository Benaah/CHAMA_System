<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // This will redirect to login if not authenticated

// Check if withdrawals table exists, if not create it
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.withdrawals')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Create the withdrawals table
        $sql = "CREATE TABLE withdrawals (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            reason TEXT,
            payment_method VARCHAR(50) NOT NULL,
            account_details TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            request_date TIMESTAMP NOT NULL,
            processed_date TIMESTAMP,
            processed_by INTEGER,
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (processed_by) REFERENCES users(id)
        );
        
        CREATE INDEX idx_withdrawals_user_id ON withdrawals(user_id);
        CREATE INDEX idx_withdrawals_status ON withdrawals(status);";
        
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    // Log the error but continue with default values
    error_log("Error checking/creating withdrawals table: " . $e->getMessage());
}

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's total contributions
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM contributions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalContributions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get user's total withdrawals - safely handle if table doesn't exist
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM withdrawals WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $totalWithdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
} catch (PDOException $e) {
    // If table doesn't exist, set withdrawals to 0
    $totalWithdrawals = 0;
}

// Calculate available balance
$availableBalance = $totalContributions - $totalWithdrawals;

// Get user's outstanding loans
$stmt = $pdo->prepare("SELECT COALESCE(SUM(l.amount), 0) - COALESCE(SUM(lr.amount), 0) as total 
                      FROM loans l 
                      LEFT JOIN loan_repayments lr ON l.id = lr.loan_id 
                      WHERE l.user_id = ? AND l.status IN ('approved', 'disbursed')");
$stmt->execute([$_SESSION['user_id']]);
$outstandingLoans = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate withdrawable amount (available balance minus outstanding loans)
$withdrawableAmount = max(0, $availableBalance - $outstandingLoans);

// Process withdrawal request
$message = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_withdrawal'])) {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
    $account_details = filter_input(INPUT_POST, 'account_details', FILTER_SANITIZE_STRING);
    
    // Validate amount
    if (!$amount || $amount <= 0) {
        $message = 'Please enter a valid withdrawal amount.';
        $alertType = 'danger';
    } elseif ($amount > $withdrawableAmount) {
        $message = 'The requested amount exceeds your withdrawable balance.';
        $alertType = 'danger';
    } else {
        // Insert withdrawal request
        $stmt = $pdo->prepare("INSERT INTO withdrawals (user_id, amount, reason, payment_method, account_details, status, request_date) 
                              VALUES (?, ?, ?, ?, ?, 'pending', NOW())");
        
        if ($stmt->execute([$_SESSION['user_id'], $amount, $reason, $payment_method, $account_details])) {
            $message = 'Your withdrawal request has been submitted successfully and is pending approval.';
            $alertType = 'success';
        } else {
            $message = 'An error occurred while processing your request. Please try again.';
            $alertType = 'danger';
        }
    }
}

// Get withdrawal history
$stmt = $pdo->prepare("SELECT * FROM withdrawals WHERE user_id = ? ORDER BY request_date DESC");
$stmt->execute([$_SESSION['user_id']]);
$withdrawalHistory = $stmt->fetchAll();

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Withdrawal Management</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-primary">Total Contributions</h5>
                                    <div class="h2 font-weight-bold text-primary">
                                        <?php echo number_format($totalContributions, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-danger h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-danger">Outstanding Loans</h5>
                                    <div class="h2 font-weight-bold text-danger">
                                        <?php echo number_format($outstandingLoans, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card border-success h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-success">Withdrawable Amount</h5>
                                    <div class="h2 font-weight-bold text-success">
                                        <?php echo number_format($withdrawableAmount, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Request Withdrawal</h6>
                                </div>
                                <div class="card-body">
                                    <form method="post" action="">
                                        <div class="form-group">
                                            <label for="amount">Withdrawal Amount</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">KES</span>
                                                </div>
                                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" max="<?php echo $withdrawableAmount; ?>" required>
                                            </div>
                                            <small class="form-text text-muted">Maximum withdrawable amount: KES<?php echo number_format($withdrawableAmount, 2); ?></small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="reason">Reason for Withdrawal</label>
                                            <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                                            <small class="form-text text-muted">Please provide a brief explanation for your withdrawal request.</small>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="payment_method">Payment Method</label>
                                            <select class="form-control" id="payment_method" name="payment_method" required>
                                                <option value="">Select payment method</option>
                                                <option value="bank_transfer">Bank Transfer</option>
                                                <option value="mobile_money">Mobile Money</option>
                                                <option value="check">Check</option>
                                                <option value="cash">Cash</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="account_details">Account Details</label>
                                            <textarea class="form-control" id="account_details" name="account_details" rows="3" required></textarea>
                                            <small class="form-text text-muted">Provide your account details for the selected payment method.</small>
                                        </div>
                                        
                                        <div class="form-group form-check">
                                            <input type="checkbox" class="form-check-input" id="terms" required>
                                            <label class="form-check-label" for="terms">I understand that withdrawal requests are subject to approval and may take 2-3 business days to process.</label>
                                        </div>
                                        
                                        <button type="submit" name="submit_withdrawal" class="btn btn-primary" <?php echo $withdrawableAmount <= 0 ? 'disabled' : ''; ?>>
                                            Submit Withdrawal Request
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Withdrawal Policy</h6>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-info mb-4">
                                        <h6 class="font-weight-bold">Important Information</h6>
                                        <p class="mb-0">Please review our withdrawal policy before submitting a request.</p>
                                    </div>
                                    
                                    <ul class="list-group mb-4">
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success mr-2"></i>
                                            Withdrawals are subject to approval by the CHAMA administrators.
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success mr-2"></i>
                                            Processing time is typically 2-3 business days.
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success mr-2"></i>
                                            You cannot withdraw more than your available balance minus outstanding loans.
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success mr-2"></i>
                                            A withdrawal fee of 2% may apply for urgent requests.
                                        </li>
                                        <li class="list-group-item">
                                            <i class="fas fa-check-circle text-success mr-2"></i>
                                            Withdrawals may affect your loan eligibility and dividend calculations.
                                        </li>
                                    </ul>
                                    
                                    <div class="alert alert-warning">
                                        <h6 class="font-weight-bold">Need Help?</h6>
                                        <p class="mb-0">If you have any questions about withdrawals, please contact the CHAMA administrators for assistance.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Withdrawal History -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Withdrawal History</h6>
                </div>
                <div class="card-body">
                    <?php if (count($withdrawalHistory) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="withdrawalsTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Request Date</th>
                                        <th>Amount</th>
                                        <th>Payment Method</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Processed Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($withdrawalHistory as $withdrawal): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y', strtotime($withdrawal['request_date'])); ?></td>
                                            <td><?php echo number_format($withdrawal['amount'], 2); ?></td>
                                            <td><?php echo ucfirst(str_replace('_', ' ', $withdrawal['payment_method'])); ?></td>
                                            <td><?php echo htmlspecialchars($withdrawal['reason']); ?></td>
                                            <td>
                                                <span class="badge badge-<?php 
                                                    echo $withdrawal['status'] == 'approved' ? 'success' : 
                                                        ($withdrawal['status'] == 'pending' ? 'warning' : 
                                                            ($withdrawal['status'] == 'rejected' ? 'danger' : 'secondary')); 
                                                ?>">
                                                    <?php echo ucfirst($withdrawal['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo $withdrawal['processed_date'] ? date('M d, Y', strtotime($withdrawal['processed_date'])) : 'N/A'; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            You haven't made any withdrawal requests yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Initialize DataTable for withdrawal history
        $('#withdrawalsTable').DataTable({
            order: [[0, 'desc']], // Sort by request date (newest first)
            pageLength: 10,
            responsive: true
        });
        
        // Dynamic form validation
        $('#amount').on('input', function() {
            const amount = parseFloat($(this).val());
            const maxAmount = <?php echo $withdrawableAmount; ?>;
            
            if (amount > maxAmount) {
                $(this).addClass('is-invalid');
                $(this).after('<div class="invalid-feedback">Amount exceeds your withdrawable balance.</div>');
            } else {
                $(this).removeClass('is-invalid');
                $(this).siblings('.invalid-feedback').remove();
            }
        });
        
        // Show/hide account details field based on payment method
        $('#payment_method').on('change', function() {
            const method = $(this).val();
            const accountDetails = $('#account_details');
            const accountDetailsLabel = $('label[for="account_details"]');
            const accountDetailsHelp = accountDetails.siblings('.form-text');
            
            if (method === 'bank_transfer') {
                accountDetailsLabel.text('Bank Account Details');
                accountDetailsHelp.text('Please provide your bank name, account number, and account name.');
                accountDetails.attr('placeholder', 'Bank: ABC Bank\nAccount Number: 1234567890\nAccount Name: John Doe');
            } else if (method === 'mobile_money') {
                accountDetailsLabel.text('Mobile Money Details');
                accountDetailsHelp.text('Please provide your mobile money number and registered name.');
                accountDetails.attr('placeholder', 'Phone Number: +1234567890\nRegistered Name: John Doe');
            } else if (method === 'check') {
                accountDetailsLabel.text('Check Details');
                accountDetailsHelp.text('Please provide the name to be written on the check and delivery address.');
                accountDetails.attr('placeholder', 'Name on Check: John Doe\nDelivery Address: 123 Main St, City, Country');
            } else if (method === 'cash') {
                accountDetailsLabel.text('Cash Collection Details');
                accountDetailsHelp.text('Please provide your preferred collection date and ID information.');
                accountDetails.attr('placeholder', 'Preferred Collection Date: MM/DD/YYYY\nID Type and Number: National ID 1234567');
            } else {
                accountDetailsLabel.text('Account Details');
                accountDetailsHelp.text('Provide your account details for the selected payment method.');
                accountDetails.attr('placeholder', '');
            }
        });
        
        // Confirmation dialog before submitting withdrawal request
        $('form').on('submit', function(e) {
            if (!confirm('Are you sure you want to submit this withdrawal request?')) {
                e.preventDefault();
                return false;
            }
            return true;
        });
        
        // Add animation effects to cards
        $('.card').addClass('animate__animated animate__fadeIn');
        $('.card-header').addClass('animate__animated animate__fadeInDown');
        
        // Highlight row on hover
        $('.table tbody tr').hover(
            function() {
                $(this).addClass('bg-light');
            },
            function() {
                $(this).removeClass('bg-light');
            }
        );
    });
</script>

<!-- Add some custom styles for better visual appeal -->
<style>
    .card {
        transition: all 0.3s ease;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
    }
    
    .card-header {
        background: linear-gradient(135deg, #4b421b 0%, #24252a 51%, #070716 100%);
        color: white;
    }
    
    .form-control:focus {
        border-color: #4e73df;
        box-shadow: 0 0 0 0.2rem rgba(78, 115, 223, 0.25);
    }
    
    .btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
        transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
        background-color: #2e59d9;
        border-color: #2653d4;
        transform: translateY(-2px);
    }
    
    .list-group-item {
        transition: all 0.2s ease;
    }
    
    .list-group-item:hover {
        background-color: #f8f9fc;
        transform: translateX(5px);
    }
    
    .badge {
        font-size: 85%;
        font-weight: 600;
        padding: 0.35em 0.65em;
        border-radius: 10rem;
    }
    
    .table th {
        background-color: #f8f9fc;
        border-top: none;
    }
    
    .table-hover tbody tr:hover {
        background-color: rgba(78, 115, 223, 0.05);
    }
    
    /* Animated progress bar for processing status */
    .badge-warning {
        position: relative;
        overflow: hidden;
    }
    
    .badge-warning::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        height: 100%;
        width: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
        animation: shimmer 1.5s infinite;
    }
    
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
</style>

<?php include 'includes/footer.php'; ?>