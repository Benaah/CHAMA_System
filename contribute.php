<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // Require authentication

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get payment methods
try {
    $stmt = $pdo->prepare("SELECT * FROM payment_methods WHERE status = 'active'");
    $stmt->execute();
    $paymentMethods = $stmt->fetchAll();
} catch (PDOException $e) {
    // If table doesn't exist, create default payment methods
    if ($e->getCode() == '42P01') { // Undefined table error code
        // Create default payment methods array
        $paymentMethods = [
            ['code' => 'mpesa', 'name' => 'M-Pesa'],
            ['code' => 'bank', 'name' => 'Bank Transfer'],
            ['code' => 'cash', 'name' => 'Cash']
        ];
        
        // Optionally, create the table and insert default methods
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS payment_methods (
                    id SERIAL PRIMARY KEY,
                    name VARCHAR(100) NOT NULL,
                    code VARCHAR(50) NOT NULL UNIQUE,
                    description TEXT,
                    instructions TEXT,
                    status VARCHAR(20) DEFAULT 'active',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");
            
            // Insert default payment methods
            $stmt = $pdo->prepare("INSERT INTO payment_methods (name, code, description, instructions, status) VALUES (?, ?, ?, ?, ?) ON CONFLICT (code) DO NOTHING");
            
            $stmt->execute(['M-Pesa', 'mpesa', 'Pay using M-Pesa mobile money', 'Use Paybill Number: ' . MPESA_SHORTCODE . ', Account: Your Member ID', 'active']);
            $stmt->execute(['Bank Transfer', 'bank', 'Pay via bank transfer', 'Transfer to Account: 1234567890, Bank: Sample Bank, Branch: Main Branch', 'active']);
            $stmt->execute(['Cash', 'cash', 'Pay in cash at our office', 'Visit our office during working hours with the exact amount', 'active']);
            
            // Log the action
            logActivity('Created payment_methods table with default methods');
        } catch (PDOException $createError) {
            // If we can't create the table, just continue with the default methods
            error_log("Failed to create payment_methods table: " . $createError->getMessage());
        }
    } else {
        // For other database errors, rethrow
        throw $e;
    }
}

// Process contribution form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = sanitizeInput($_POST['amount']);
    $paymentMethod = sanitizeInput($_POST['payment_method']);
    $transactionId = sanitizeInput($_POST['transaction_id']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Validate input
    $errors = [];
    
    if (empty($amount) || !is_numeric($amount) || $amount <= 0) {
        $errors[] = "Please enter a valid contribution amount.";
    } elseif ($amount < MIN_CONTRIBUTION) {
        $errors[] = "Minimum contribution amount is " . formatCurrency(MIN_CONTRIBUTION) . ".";
    }
    
    if (empty($paymentMethod)) {
        $errors[] = "Please select a payment method.";
    }
    
    if ($paymentMethod !== 'cash' && empty($transactionId)) {
        $errors[] = "Transaction ID is required for electronic payments.";
    }
    
    // If no errors, proceed with contribution
    if (empty($errors)) {
        // Generate reference number
        $reference = generateReferenceNumber('CONT');
        
        // Insert contribution record
        $stmt = $pdo->prepare("INSERT INTO contributions (user_id, amount, payment_method, transaction_id, reference_number, notes, contribution_date, status) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE, 'pending')");
        
        if ($stmt->execute([$_SESSION['user_id'], $amount, $paymentMethod, $transactionId, $reference, $notes])) {
            // Log activity
            logActivity('Made contribution of ' . formatCurrency($amount));
            
            $_SESSION['success'] = "Your contribution of " . formatCurrency($amount) . " has been recorded and is pending approval.";
            header('Location: contributions.php');
            exit;
        } else {
            $_SESSION['error'] = "Failed to record contribution. Please try again.";
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
                    <h4 class="mb-0">Make a Contribution</h4>
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
                    
                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle"></i> Contribution Guidelines</h5>
                        <ul class="mb-0">
                            <li>Minimum contribution amount is <?php echo formatCurrency(MIN_CONTRIBUTION); ?></li>
                            <li>For M-Pesa payments, use Paybill Number: <?php echo MPESA_SHORTCODE; ?></li>
                            <li>Use your member ID as the account number</li>
                            <li>All contributions will be verified before approval</li>
                        </ul>
                    </div>
                    
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="amount">Contribution Amount (KES)</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">KES</span>
                                </div>
                                <input type="number" class="form-control" id="amount" name="amount" min="<?php echo MIN_CONTRIBUTION; ?>" step="100" required
                                       value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : MIN_CONTRIBUTION; ?>">
                            </div>
                            <small class="form-text text-muted">Minimum contribution: <?php echo formatCurrency(MIN_CONTRIBUTION); ?></small>
                        </div>
                        
                        <div class="form-group">
                            <label for="payment_method">Payment Method</label>
                            <select class="form-control" id="payment_method" name="payment_method" required>
                                <option value="">-- Select Payment Method --</option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?php echo htmlspecialchars($method['code']); ?>" 
                                            <?php echo (isset($_POST['payment_method']) && $_POST['payment_method'] == $method['code']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($method['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" id="transaction_id_group">
                            <label for="transaction_id">Transaction ID / Reference Number</label>
                            <input type="text" class="form-control" id="transaction_id" name="transaction_id" 
                                   value="<?php echo isset($_POST['transaction_id']) ? htmlspecialchars($_POST['transaction_id']) : ''; ?>">
                            <small class="form-text text-muted">For M-Pesa, enter the M-Pesa confirmation code (e.g., QK7AHDGSJK)</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="notes">Additional Notes (Optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-hand-holding-usd"></i> Submit Contribution
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment_method');
    const transactionIdGroup = document.getElementById('transaction_id_group');
    
    // Show/hide transaction ID field based on payment method
    function toggleTransactionIdField() {
        if (paymentMethodSelect.value === 'cash') {
            transactionIdGroup.style.display = 'none';
            document.getElementById('transaction_id').removeAttribute('required');
        } else {
            transactionIdGroup.style.display = 'block';
            document.getElementById('transaction_id').setAttribute('required', 'required');
        }
    }
    
    // Initial check
    toggleTransactionIdField();
    
    // Add event listener
    paymentMethodSelect.addEventListener('change', toggleTransactionIdField);
});
</script>

<?php include 'includes/footer.php'; ?>