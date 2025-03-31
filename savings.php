<?php
session_start();
include 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";

// Check if savings table exists, if not create it
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.savings')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Create savings table
        $sql = "CREATE TABLE savings (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            amount DECIMAL(10, 2) NOT NULL,
            savings_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            notes TEXT,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )";
        $pdo->exec($sql);
        
        // Create index for faster queries
        $pdo->exec("CREATE INDEX idx_savings_user_id ON savings(user_id)");
    }
} catch (PDOException $e) {
    // Log the error but continue with empty data
    error_log("Error checking/creating savings table: " . $e->getMessage());
}

// Fetch savings history
try {
    $stmt = $pdo->prepare("SELECT * FROM savings WHERE user_id = ? ORDER BY savings_date DESC");
    $stmt->execute([$user_id]);
    $savings = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's an error, set empty array
    $savings = [];
    $message = "<div class='alert alert-warning'>Could not load savings history. The system is being set up.</div>";
}

// Handle new savings deposit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $amount = floatval($_POST['amount']);
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';
    
    if ($amount > 0) {
        try {
            $savings_stmt = $pdo->prepare("INSERT INTO savings (user_id, amount, notes) VALUES (?, ?, ?)");
            if ($savings_stmt->execute([$user_id, $amount, $notes])) {
                $message = "<div class='alert alert-success'>Savings added successfully!</div>";
                
                // Refresh savings list
                $stmt = $pdo->prepare("SELECT * FROM savings WHERE user_id = ? ORDER BY savings_date DESC");
                $stmt->execute([$user_id]);
                $savings = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $message = "<div class='alert alert-danger'>Failed to add savings.</div>";
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        $message = "<div class='alert alert-danger'>Invalid amount. Please enter a positive number.</div>";
    }
}

// Calculate total savings
$total_savings = 0;
foreach ($savings as $save) {
    $total_savings += $save['amount'];
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-piggy-bank mr-2"></i> Savings Management</h4>
                </div>
                <div class="card-body">
                    <?= $message ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100 border-success">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-success">Total Savings</h5>
                                    <div class="display-4 font-weight-bold text-success">
                                        KES <?= number_format($total_savings, 2) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title">Add New Savings</h5>
                                    <form method="post" action="">
                                        <div class="form-group">
                                            <label for="amount">Amount (KES)</label>
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">KES</span>
                                                </div>
                                                <input type="number" name="amount" id="amount" class="form-control" step="0.01" min="0.01" required>
                                            </div>
                                        </div>
                                        
                                        <div class="form-group">
                                            <label for="notes">Notes (Optional)</label>
                                            <textarea name="notes" id="notes" class="form-control" rows="2"></textarea>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-plus-circle mr-2"></i> Deposit Savings
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <h5 class="mb-3"><i class="fas fa-history mr-2"></i> Savings History</h5>
                    
                    <?php if (count($savings) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Amount (KES)</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($savings as $save): ?>
                                    <tr>
                                        <td><?= date('M d, Y h:i A', strtotime($save['savings_date'])) ?></td>
                                        <td class="text-success font-weight-bold"><?= number_format($save['amount'], 2) ?></td>
                                        <td><?= htmlspecialchars($save['notes'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> You haven't made any savings deposits yet. Start saving today!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb mr-2"></i> Savings Tips</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-calendar-alt mr-2 text-primary"></i> Regular Savings</h5>
                                    <p class="card-text">Set aside a fixed amount each month. Even small regular deposits add up over time.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-bullseye mr-2 text-danger"></i> Set Goals</h5>
                                    <p class="card-text">Define clear savings goals. Having a purpose makes it easier to stay committed.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h5 class="card-title"><i class="fas fa-chart-line mr-2 text-success"></i> Track Progress</h5>
                                    <p class="card-text">Regularly monitor your savings growth. Seeing progress is motivating.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>