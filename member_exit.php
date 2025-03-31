<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // This will redirect to login if not authenticated

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's total contributions
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM contributions WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalContributions = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get user's total withdrawals
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM withdrawals WHERE user_id = ? AND status = 'approved'");
$stmt->execute([$_SESSION['user_id']]);
$totalWithdrawals = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get user's outstanding loans
$stmt = $pdo->prepare("SELECT COALESCE(SUM(l.amount), 0) - COALESCE(SUM(lr.amount), 0) as total 
                      FROM loans l 
                      LEFT JOIN loan_repayments lr ON l.id = lr.loan_id 
                      WHERE l.user_id = ? AND l.status IN ('approved', 'disbursed')");
$stmt->execute([$_SESSION['user_id']]);
$outstandingLoans = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get user's shares in projects
$stmt = $pdo->prepare("SELECT COALESCE(SUM(ps.amount), 0) as total 
                      FROM project_shares ps 
                      WHERE ps.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$projectShares = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get user's dividends
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM dividends WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalDividends = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate net balance
$netBalance = $totalContributions + $totalDividends - $totalWithdrawals - $outstandingLoans;

// Check if user has any pending exit requests
$stmt = $pdo->prepare("SELECT * FROM member_exits WHERE user_id = ? ORDER BY request_date DESC LIMIT 1");
$stmt->execute([$_SESSION['user_id']]);
$exitRequest = $stmt->fetch(PDO::FETCH_ASSOC);

// Process exit request
$message = '';
$alertType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['submit_exit_request'])) {
        $reason = filter_input(INPUT_POST, 'reason', FILTER_SANITIZE_STRING);
        $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_STRING);
        $account_details = filter_input(INPUT_POST, 'account_details', FILTER_SANITIZE_STRING);
        
        // Validate input
        if (empty($reason)) {
            $message = 'Please provide a reason for leaving the CHAMA.';
            $alertType = 'danger';
        } elseif (empty($payment_method)) {
            $message = 'Please select a payment method for your final settlement.';
            $alertType = 'danger';
        } elseif (empty($account_details)) {
            $message = 'Please provide your account details for the final settlement.';
            $alertType = 'danger';
        } elseif ($outstandingLoans > 0) {
            $message = 'You have outstanding loans that must be repaid before you can exit the CHAMA.';
            $alertType = 'danger';
        } else {
            try {
                // Begin transaction
                $pdo->beginTransaction();
                
                // Insert exit request
                $stmt = $pdo->prepare("
                    INSERT INTO member_exits (
                        user_id, 
                        reason, 
                        contribution_balance, 
                        dividend_balance, 
                        project_shares, 
                        outstanding_loans, 
                        net_balance,
                        payment_method,
                        account_details,
                        status,
                        request_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                
                $stmt->execute([
                    $_SESSION['user_id'],
                    $reason,
                    $totalContributions,
                    $totalDividends,
                    $projectShares,
                    $outstandingLoans,
                    $netBalance,
                    $payment_method,
                    $account_details
                ]);
                
                // Log activity
                logActivity('Member exit request submitted', $_SESSION['user_id']);
                
                // Commit transaction
                $pdo->commit();
                
                $message = 'Your exit request has been submitted successfully. An administrator will review your request and contact you regarding the next steps.';
                $alertType = 'success';
                
                // Refresh exit request data
                $stmt = $pdo->prepare("SELECT * FROM member_exits WHERE user_id = ? ORDER BY request_date DESC LIMIT 1");
                $stmt->execute([$_SESSION['user_id']]);
                $exitRequest = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $message = 'An error occurred while processing your request: ' . $e->getMessage();
                $alertType = 'danger';
            }
        }
    } elseif (isset($_POST['cancel_exit_request'])) {
        // Cancel exit request
        try {
            $stmt = $pdo->prepare("UPDATE member_exits SET status = 'cancelled', processed_date = NOW() WHERE id = ? AND user_id = ? AND status = 'pending'");
            $stmt->execute([$_POST['exit_id'], $_SESSION['user_id']]);
            
            // Log activity
            logActivity('Member exit request cancelled', $_SESSION['user_id']);
            
            $message = 'Your exit request has been cancelled successfully.';
            $alertType = 'success';
            
            // Clear exit request data
            $exitRequest = null;
        } catch (PDOException $e) {
            $message = 'An error occurred while cancelling your request: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">Member Exit Process</h6>
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
                    
                    <?php if ($exitRequest && $exitRequest['status'] === 'pending'): ?>
                        <!-- Pending Exit Request -->
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle mr-2"></i> Exit Request Pending</h5>
                            <p>Your request to leave the CHAMA is currently under review. You will be notified once it has been processed.</p>
                            <p><strong>Request Date:</strong> <?php echo date('F j, Y', strtotime($exitRequest['request_date'])); ?></p>
                            
                            <form method="post" class="mt-3">
                                <input type="hidden" name="exit_id" value="<?php echo $exitRequest['id']; ?>">
                                <button type="submit" name="cancel_exit_request" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel your exit request?');">
                                    <i class="fas fa-times mr-2"></i> Cancel Exit Request
                                </button>
                            </form>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h6 class="m-0 font-weight-bold">Exit Request Details</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Reason for Leaving:</strong> <?php echo htmlspecialchars($exitRequest['reason']); ?></p>
                                        <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $exitRequest['payment_method'])); ?></p>
                                        <p><strong>Account Details:</strong> <?php echo nl2br(htmlspecialchars($exitRequest['account_details'])); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Contribution Balance:</strong> KES <?php echo number_format($exitRequest['contribution_balance'], 2); ?></p>
                                        <p><strong>Dividend Balance:</strong> KES <?php echo number_format($exitRequest['dividend_balance'], 2); ?></p>
                                        <p><strong>Project Shares:</strong> KES <?php echo number_format($exitRequest['project_shares'], 2); ?></p>
                                        <p><strong>Outstanding Loans:</strong> KES <?php echo number_format($exitRequest['outstanding_loans'], 2); ?></p>
                                        <p><strong>Net Balance:</strong> <span class="font-weight-bold <?php echo $exitRequest['net_balance'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                            KES <?php echo number_format($exitRequest['net_balance'], 2); ?>
                                        </span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($exitRequest && $exitRequest['status'] === 'approved'): ?>
                        <!-- Approved Exit Request -->
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle mr-2"></i> Exit Request Approved</h5>
                            <p>Your request to leave the CHAMA has been approved. Your final settlement is being processed.</p>
                            <p><strong>Approval Date:</strong> <?php echo date('F j, Y', strtotime($exitRequest['processed_date'])); ?></p>
                            <p><strong>Settlement Amount:</strong> KES <?php echo number_format($exitRequest['net_balance'], 2); ?></p>
                            <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $exitRequest['payment_method'])); ?></p>
                            
                            <?php if (!empty($exitRequest['admin_notes'])): ?>
                                <p><strong>Administrator Notes:</strong> <?php echo nl2br(htmlspecialchars($exitRequest['admin_notes'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <p>Thank you for being a part of our CHAMA. We wish you all the best in your future endeavors!</p>
                                <p>Your account will be deactivated once the final settlement has been completed.</p>
                            </div>
                        </div>
                    <?php elseif ($exitRequest && $exitRequest['status'] === 'rejected'): ?>
                        <!-- Rejected Exit Request -->
                        <div class="alert alert-danger">
                            <h5><i class="fas fa-times-circle mr-2"></i> Exit Request Rejected</h5>
                            <p>Your request to leave the CHAMA has been rejected.</p>
                            <p><strong>Rejection Date:</strong> <?php echo date('F j, Y', strtotime($exitRequest['processed_date'])); ?></p>
                            
                            <?php if (!empty($exitRequest['admin_notes'])): ?>
                                <p><strong>Reason for Rejection:</strong> <?php echo nl2br(htmlspecialchars($exitRequest['admin_notes'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <p>If you have any questions or would like to discuss this further, please contact the CHAMA administrators.</p>
                                <p>You may submit a new exit request if you still wish to leave the CHAMA.</p>
                            </div>
                        </div>
                    <?php elseif ($exitRequest && $exitRequest['status'] === 'cancelled'): ?>
                        <!-- Cancelled Exit Request -->
                        <div class="alert alert-warning">
                            <h5><i class="fas fa-ban mr-2"></i> Exit Request Cancelled</h5>
                            <p>Your request to leave the CHAMA was cancelled on <?php echo date('F j, Y', strtotime($exitRequest['processed_date'])); ?>.</p>
                            <p>You may submit a new exit request if you wish to leave the CHAMA.</p>
                        </div>
                    <?php elseif ($exitRequest && $exitRequest['status'] === 'completed'): ?>
                        <!-- Completed Exit Process -->
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle mr-2"></i> Exit Process Completed</h5>
                            <p>Your exit from the CHAMA has been completed. Your final settlement has been processed.</p>
                            <p><strong>Completion Date:</strong> <?php echo date('F j, Y', strtotime($exitRequest['processed_date'])); ?></p>
                            <p><strong>Settlement Amount:</strong> KES <?php echo number_format($exitRequest['net_balance'], 2); ?></p>
                            <p><strong>Payment Method:</strong> <?php echo ucfirst(str_replace('_', ' ', $exitRequest['payment_method'])); ?></p>
                            
                            <?php if (!empty($exitRequest['admin_notes'])): ?>
                                <p><strong>Administrator Notes:</strong> <?php echo nl2br(htmlspecialchars($exitRequest['admin_notes'])); ?></p>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <p>Thank you for being a part of our CHAMA. We wish you all the best in your future endeavors!</p>
                                <p>Your account will be deactivated shortly.</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- No Exit Request - Show Financial Summary and Exit Form -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="alert alert-warning">
                                    <h5><i class="fas fa-exclamation-triangle mr-2"></i> Important Information</h5>
                                    <p>Leaving the CHAMA is a significant decision. Please review the following information carefully before proceeding:</p>
                                    <ul>
                                        <li>You must repay all outstanding loans before you can exit the CHAMA.</li>
                                        <li>Your project shares will be liquidated according to the CHAMA's bylaws.</li>
                                        <li>The exit process may take up to 30 days to complete.</li>
                                        <li>A processing fee of 2% may be deducted from your final settlement.</li>
                                        <li>Once your exit is approved, you will no longer be eligible for CHAMA benefits.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">Financial Summary</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-bordered">
                                                <tbody>
                                                    <tr>
                                                        <th>Total Contributions</th>
                                                        <td class="text-right">KES <?php echo number_format($totalContributions, 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Total Dividends</th>
                                                        <td class="text-right">KES <?php echo number_format($totalDividends, 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Total Withdrawals</th>
                                                        <td class="text-right text-danger">KES <?php echo number_format($totalWithdrawals, 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Outstanding Loans</th>
                                                        <td class="text-right text-danger">KES <?php echo number_format($outstandingLoans, 2); ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Project Shares</th>
                                                        <td class="text-right">KES <?php echo number_format($projectShares, 2); ?></td>
                                                    </tr>
                                                    <tr class="bg-light">
                                                        <th>Net Balance</th>
                                                        <td class="text-right font-weight-bold <?php echo $netBalance >= 0 ? 'text-success' : 'text-danger'; ?>">
                                                            KES <?php echo number_format($netBalance, 2); ?>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        
                                        <div class="alert alert-info mt-3">
                                            <p class="mb-0"><strong>Note:</strong> This is an estimate of your final settlement. The actual amount may vary based on the liquidation of project shares and any applicable fees.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card shadow h-100">
                                    <div class="card-header py-3">
                                        <h6 class="m-0 font-weight-bold text-primary">Exit Request Form</h6>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($outstandingLoans > 0): ?>
                                            <div class="alert alert-danger">
                                                <i class="fas fa-exclamation-circle mr-2"></i> You have outstanding loans of KES <?php echo number_format($outstandingLoans, 2); ?> that must be repaid before you can exit the CHAMA.
                                                <div class="mt-2">
                                                    <a href="loans.php" class="btn btn-sm btn-danger">View Loans</a>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <form method="post" id="exitForm">
                                                <div class="form-group">
                                                    <label for="reason">Reason for Leaving</label>
                                                    <textarea class="form-control" id="reason" name="reason" rows="3" required></textarea>
                                                    <small class="form-text text-muted">Please provide a brief explanation for why you are leaving the CHAMA.</small>
                                                </div>
                                                
                                                <div class="form-group">
                                                    <label for="payment_method">Payment Method for Final Settlement</label>
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
                                                    <input type="checkbox" class="form-check-input" id="confirm_exit" required>
                                                    <label class="form-check-label" for="confirm_exit">
                                                        I confirm that I want to leave the CHAMA and understand that this decision is final once approved.
                                                    </label>
                                                </div>
                                                
                                                <button type="submit" name="submit_exit_request" class="btn btn-danger" onclick="return confirm('Are you sure you want to leave the CHAMA? This action cannot be undone once approved.');">
                                                    <i class="fas fa-sign-out-alt mr-2"></i> Submit Exit Request
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Exit Process Timeline -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Exit Process Timeline</h6>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo isset($exitRequest) ? 'timeline-marker-complete' : 'timeline-marker-active'; ?>">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h5>Step 1: Submit Exit Request</h5>
                                        <p>Fill out the exit request form with your reason for leaving and payment details.</p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo (isset($exitRequest) && in_array($exitRequest['status'], ['approved', 'rejected', 'completed'])) ? 'timeline-marker-complete' : (isset($exitRequest) && $exitRequest['status'] === 'pending' ? 'timeline-marker-active' : ''); ?>">
                                        <i class="fas fa-search"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h5>Step 2: Administrative Review</h5>
                                        <p>The CHAMA administrators will review your request and financial status.</p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo (isset($exitRequest) && in_array($exitRequest['status'], ['approved', 'completed'])) ? 'timeline-marker-complete' : ''; ?>">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h5>Step 3: Approval and Settlement Calculation</h5>
                                        <p>If approved, your final settlement amount will be calculated based on your contributions, dividends, and any applicable fees.</p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo (isset($exitRequest) && $exitRequest['status'] === 'completed') ? 'timeline-marker-complete' : ''; ?>">
                                        <i class="fas fa-money-bill-wave"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h5>Step 4: Final Settlement</h5>
                                        <p>Your final settlement will be processed according to your chosen payment method.</p>
                                    </div>
                                </div>
                                
                                <div class="timeline-item">
                                    <div class="timeline-marker <?php echo (isset($exitRequest) && $exitRequest['status'] === 'completed') ? 'timeline-marker-complete' : ''; ?>">
                                        <i class="fas fa-user-times"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <h5>Step 5: Account Deactivation</h5>
                                        <p>Your account will be deactivated once the exit process is complete.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FAQ Section -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Frequently Asked Questions</h6>
                        </div>
                        <div class="card-body">
                            <div id="exitFaq" class="accordion">
                                <div class="card">
                                    <div class="card-header" id="faqOne">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left" type="button" data-toggle="collapse" data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                                How long does the exit process take?
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="collapseOne" class="collapse show" aria-labelledby="faqOne" data-parent="#exitFaq">
                                        <div class="card-body">
                                            The exit process typically takes 14-30 days from the date your request is approved. This allows time for the administrators to calculate your final settlement, liquidate any project shares, and process the payment.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header" id="faqTwo">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                                Can I rejoin the CHAMA after leaving?
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="collapseTwo" class="collapse" aria-labelledby="faqTwo" data-parent="#exitFaq">
                                        <div class="card-body">
                                            Yes, you can apply to rejoin the CHAMA after leaving, but your application will be subject to the same approval process as new members. There may also be a waiting period of at least 6 months before you can reapply.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header" id="faqThree">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                                Are there any penalties for leaving the CHAMA?
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="collapseThree" class="collapse" aria-labelledby="faqThree" data-parent="#exitFaq">
                                        <div class="card-body">
                                            There is a processing fee of 2% that may be deducted from your final settlement. Additionally, if you have project shares, they will be liquidated according to the CHAMA's bylaws, which may result in a lower value than their potential future worth.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header" id="faqFour">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                                What happens to my loans when I leave?
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="collapseFour" class="collapse" aria-labelledby="faqFour" data-parent="#exitFaq">
                                        <div class="card-body">
                                            All outstanding loans must be repaid in full before your exit request can be approved. If you have a significant loan balance, you may need to make arrangements with the administrators for repayment.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card">
                                    <div class="card-header" id="faqFive">
                                        <h2 class="mb-0">
                                            <button class="btn btn-link btn-block text-left collapsed" type="button" data-toggle="collapse" data-target="#collapseFive" aria-expanded="false" aria-controls="collapseFive">
                                                Can I cancel my exit request?
                                            </button>
                                        </h2>
                                    </div>
                                    <div id="collapseFive" class="collapse" aria-labelledby="faqFive" data-parent="#exitFaq">
                                        <div class="card-body">
                                            Yes, you can cancel your exit request as long as it has not been approved yet. Once your request is approved and the settlement process begins, it cannot be cancelled.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- CSS for Timeline -->
<style>
    .timeline {
        position: relative;
        padding-left: 30px;
    }
    
    .timeline:before {
        content: '';
        position: absolute;
        top: 0;
        left: 15px;
        height: 100%;
        width: 2px;
        background-color: #e3e6f0;
    }
    
    .timeline-item {
        position: relative;
        margin-bottom: 30px;
    }
    
    .timeline-marker {
        position: absolute;
        left: -30px;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background-color: #e3e6f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #858796;
    }
    
    .timeline-marker-active {
        background-color: #4e73df;
        color: white;
    }
    
    .timeline-marker-complete {
        background-color: #1cc88a;
        color: white;
    }
    
    .timeline-content {
        padding-bottom: 10px;
    }
    
    .timeline-content h5 {
        margin-bottom: 5px;
    }
    
    .timeline-content p {
        margin-bottom: 0;
        color: #6c757d;
    }
</style>

<script>
$(document).ready(function() {
    // Dynamic form validation
    $('#payment_method').on('change', function() {
        const method = $(this).val();
        const accountDetails = $('#account_details');
        const accountDetailsHelp = accountDetails.siblings('.form-text');
        
        if (method === 'bank_transfer') {
            accountDetailsHelp.text('Please provide your bank name, account number, and account name.');
            accountDetails.attr('placeholder', 'Bank: ABC Bank\nAccount Number: 1234567890\nAccount Name: John Doe');
        } else if (method === 'mobile_money') {
            accountDetailsHelp.text('Please provide your mobile money number and registered name.');
            accountDetails.attr('placeholder', 'Phone Number: +1234567890\nRegistered Name: John Doe');
        } else if (method === 'check') {
            accountDetailsHelp.text('Please provide the name to be written on the check and delivery address.');
            accountDetails.attr('placeholder', 'Name on Check: John Doe\nDelivery Address: 123 Main St, City, Country');
        } else if (method === 'cash') {
            accountDetailsHelp.text('Please provide your preferred collection date and ID information.');
            accountDetails.attr('placeholder', 'Preferred Collection Date: MM/DD/YYYY\nID Type and Number: National ID 1234567');
        } else {
            accountDetailsHelp.text('Provide your account details for the selected payment method.');
            accountDetails.attr('placeholder', '');
        }
    });
    
    // Confirmation dialog before submitting exit request
    $('#exitForm').on('submit', function(e) {
        if (!$('#confirm_exit').is(':checked')) {
            e.preventDefault();
            alert('Please confirm that you want to leave the CHAMA by checking the confirmation box.');
            return false;
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>