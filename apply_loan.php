<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // Require authentication

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get user's total contributions for loan eligibility
$totalContributions = getUserTotalContributions($_SESSION['user_id']);
$loanEligibility = calculateLoanEligibility($totalContributions);

// Get user's outstanding loans
$outstandingLoans = getUserOutstandingLoans($_SESSION['user_id']);

// Check if user has any pending loan applications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pendingLoans = $stmt->fetchColumn();

// Process loan application form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = sanitizeInput($_POST['amount']);
    $duration = sanitizeInput($_POST['duration']);
    $purpose = sanitizeInput($_POST['purpose']);
    $loanType = sanitizeInput($_POST['loan_type']);
    
    // Validate input
    $errors = [];
    
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid loan amount.";
    } elseif ($amount > $loanEligibility) {
        $errors[] = "The requested loan amount exceeds your eligibility of " . formatCurrency($loanEligibility) . ".";
    }
    
    if (empty($duration) || !is_numeric($duration) || $duration <= 0) {
        $errors[] = "Please enter a valid loan duration.";
    } elseif ($duration > 12) {
        $errors[] = "Maximum loan duration is 12 months.";
    }
    
    if (empty($purpose)) {
        $errors[] = "Please specify the purpose of the loan.";
    }
    
    if (empty($loanType)) {
        $errors[] = "Please select a loan type.";
    }
    
    // Check if user already has a pending loan application
    if ($pendingLoans > 0) {
        $errors[] = "You already have a pending loan application. Please wait for it to be processed before applying for another loan.";
    }
    
    // If no errors, proceed with loan application
    if (empty($errors)) {
        // Calculate interest rate based on loan type
        $interestRate = LOAN_INTEREST_RATE;
        if ($loanType == 'emergency') {
            $interestRate += 2; // Higher interest for emergency loans
        } elseif ($loanType == 'business') {
            $interestRate -= 1; // Lower interest for business loans
        }
        
        // Calculate due date
        $dueDate = date('Y-m-d', strtotime('+' . $duration . ' months'));
        
        // Insert loan application
        $stmt = $pdo->prepare("INSERT INTO loans (user_id, amount, interest_rate, duration, purpose, application_date, status, due_date, loan_type) VALUES (?, ?, ?, ?, ?, CURRENT_DATE, 'pending', ?, ?)");
        
        if ($stmt->execute([$_SESSION['user_id'], $amount, $interestRate, $duration, $purpose, $dueDate, $loanType])) {
            // Log activity
            logActivity('Applied for a ' . formatCurrency($amount) . ' loan');
            
            $_SESSION['success'] = "Your loan application for " . formatCurrency($amount) . " has been submitted successfully and is pending approval.";
            header('Location: loans.php');
            exit;
        } else {
            $_SESSION['error'] = "Failed to submit loan application. Please try again.";
        }
    } else {
        // Set error message
        $errorMessage = '<ul class="mb-0">';
        foreach ($errors as $error) {
            $errorMessage .= '<li>' . $error . '</li>';
        }
        $errorMessage .= '</ul>';
        
        $_SESSION['error'] = $errorMessage;
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Apply for a Loan</h4>
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
                    
                    // Check if user is eligible for a loan
                    if ($totalContributions < MIN_CONTRIBUTION * 3) {
                        echo '<div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle"></i> Loan Eligibility Notice</h5>
                                <p>You need to have made at least ' . formatCurrency(MIN_CONTRIBUTION * 3) . ' in contributions to be eligible for a loan.</p>
                                <p>Your current contribution total is ' . formatCurrency($totalContributions) . '.</p>
                                <a href="contribute.php" class="btn btn-primary">Make a Contribution</a>
                              </div>';
                    } elseif ($pendingLoans > 0) {
                        echo '<div class="alert alert-warning">
                                <h5><i class="fas fa-exclamation-triangle"></i> Pending Loan Notice</h5>
                                <p>You already have a pending loan application. Please wait for it to be processed before applying for another loan.</p>
                                <a href="loans.php" class="btn btn-primary">View My Loans</a>
                              </div>';
                    } else {
                    ?>
                    
                    <!-- Loan Eligibility Summary -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Total Contributions</h5>
                                    <h3 class="text-primary"><?php echo formatCurrency($totalContributions); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Outstanding Loans</h5>
                                    <h3 class="text-danger"><?php echo formatCurrency($outstandingLoans); ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h5 class="card-title">Loan Eligibility</h5>
                                    <h3 class="text-success"><?php echo formatCurrency($loanEligibility); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info mb-4">
                        <h5><i class="fas fa-info-circle"></i> Loan Guidelines</h5>
                        <ul class="mb-0">
                            <li>Loan eligibility is <?php echo MAX_LOAN_MULTIPLIER; ?>x your total contributions</li>
                            <li>Current base interest rate is <?php echo LOAN_INTEREST_RATE; ?>% per annum</li>
                            <li>Maximum loan repayment period is 12 months</li>
                            <li>Early repayment is allowed without penalties</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="amount">Loan Amount (KES)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">KES</span>
                                </div>
                                <input type="number" class="form-control" id="amount" name="amount" min="1000" max="<?php echo $loanEligibility; ?>" step="1000" required
                                       value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">
                            </div>
                            <small class="form-text text-muted">Maximum amount: <?php echo formatCurrency($loanEligibility); ?></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="duration">Loan Duration (Months)</label>
                            <select class="form-control" id="duration" name="duration" required>
                                <option value="">-- Select Duration --</option>
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (isset($_POST['duration']) && $_POST['duration'] == $i) ? 'selected' : ''; ?>>
                                        <?php echo $i; ?> month<?php echo ($i > 1) ? 's' : ''; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="loan_type">Loan Type</label>
                            <select class="form-control" id="loan_type" name="loan_type" required>
                                <option value="">-- Select Loan Type --</option>
                                <option value="personal" <?php echo (isset($_POST['loan_type']) && $_POST['loan_type'] == 'personal') ? 'selected' : ''; ?>>
                                    Personal Loan (<?php echo LOAN_INTEREST_RATE; ?>% interest)
                                </option>
                                <option value="business" <?php echo (isset($_POST['loan_type']) && $_POST['loan_type'] == 'business') ? 'selected' : ''; ?>>
                                    Business Loan (<?php echo LOAN_INTEREST_RATE - 1; ?>% interest)
                                </option>
                                <option value="emergency" <?php echo (isset($_POST['loan_type']) && $_POST['loan_type'] == 'emergency') ? 'selected' : ''; ?>>
                                    Emergency Loan (<?php echo LOAN_INTEREST_RATE + 2; ?>% interest)
                                </option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="purpose">Loan Purpose</label>
                            <textarea class="form-control" id="purpose" name="purpose" rows="3" required><?php echo isset($_POST['purpose']) ? htmlspecialchars($_POST['purpose']) : ''; ?></textarea>
                            <small class="form-text text-muted">Please provide a detailed explanation of how you plan to use the loan.</small>
                        </div>
                        
                        <div class="form-group">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" id="terms" name="terms" required>
                                <label class="custom-control-label" for="terms">
                                    I agree to the loan terms and conditions, and I confirm that all information provided is accurate.
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> Submit Loan Application
                            </button>
                            <a href="loans.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                    
                    <!-- Loan Calculator -->
                    <div class="card mt-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">Loan Repayment Calculator</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div id="calculator-results">
                                        <p>Enter loan details to calculate repayment.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="calc-amount">Amount (KES)</label>
                                        <input type="number" class="form-control" id="calc-amount" min="1000" step="1000">
                                    </div>
                                    <div class="form-group">
                                        <label for="calc-duration">Duration (Months)</label>
                                        <select class="form-control" id="calc-duration">
                                            <?php for ($i = 1; $i <= 12; $i++): ?>
                                                <option value="<?php echo $i; ?>"><?php echo $i; ?> month<?php echo ($i > 1) ? 's' : ''; ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="calc-interest">Interest Rate (%)</label>
                                        <select class="form-control" id="calc-interest">
                                            <option value="<?php echo LOAN_INTEREST_RATE; ?>">Personal Loan (<?php echo LOAN_INTEREST_RATE; ?>%)</option>
                                            <option value="<?php echo LOAN_INTEREST_RATE - 1; ?>">Business Loan (<?php echo LOAN_INTEREST_RATE - 1; ?>%)</option>
                                            <option value="<?php echo LOAN_INTEREST_RATE + 2; ?>">Emergency Loan (<?php echo LOAN_INTEREST_RATE + 2; ?>%)</option>
                                        </select>
                                    </div>
                                    <button type="button" id="calculate-btn" class="btn btn-info">Calculate</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php } // End of eligibility check ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Loan calculator functionality
    const calculateBtn = document.getElementById('calculate-btn');
    if (calculateBtn) {
        calculateBtn.addEventListener('click', function() {
            const amount = parseFloat(document.getElementById('calc-amount').value) || 0;
            const duration = parseInt(document.getElementById('calc-duration').value) || 1;
            const interestRate = parseFloat(document.getElementById('calc-interest').value) || <?php echo LOAN_INTEREST_RATE; ?>;
            
            // Calculate interest amount (simple interest)
            const interestAmount = (amount * interestRate * (duration / 12)) / 100;
            const totalRepayment = amount + interestAmount;
            const monthlyPayment = totalRepayment / duration;
            
            // Display results
            const resultsDiv = document.getElementById('calculator-results');
            resultsDiv.innerHTML = `
                <h6>Loan Summary:</h6>
                <table class="table table-sm">
                    <tr>
                        <td>Principal Amount:</td>
                        <td class="text-right">KES ${amount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    </tr>
                    <tr>
                        <td>Interest Amount:</td>
                        <td class="text-right">KES ${interestAmount.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    </tr>
                    <tr>
                        <td>Total Repayment:</td>
                            <td class="text-right">KES ${totalRepayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    </tr>
                    <tr>
                        <td>Monthly Payment:</td>
                        <td class="text-right">KES ${monthlyPayment.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                    </tr>
                </table>
            `;
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>