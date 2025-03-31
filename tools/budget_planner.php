<?php
include '../includes/header.php';
include '../config.php'; // Database connection

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to access the budget planner.";
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Initialize variables
$income = 0;
$expenses = [];
$total_expenses = 0;
$savings_goal = 0;
$budget_period = 'monthly';
$success_msg = '';
$error_msg = '';

// Fetch user's existing budget if available
$stmt = $pdo->prepare("SELECT * FROM user_budgets WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$user_id]);
$budget = $stmt->fetch(PDO::FETCH_ASSOC);

if ($budget) {
    $income = $budget['income'];
    $budget_period = $budget['period'];
    $savings_goal = $budget['savings_goal'];
    
    // Fetch budget expense items
    $stmt = $pdo->prepare("SELECT * FROM budget_expenses WHERE budget_id = ?");
    $stmt->execute([$budget['id']]);
    $expense_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($expense_items as $item) {
        $expenses[$item['category']] = $item['amount'];
        $total_expenses += $item['amount'];
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_budget'])) {
    $income = floatval($_POST['income']);
    $budget_period = $_POST['budget_period'];
    $savings_goal = floatval($_POST['savings_goal']);
    
    // Collect expense categories and amounts
    $expense_categories = $_POST['expense_category'] ?? [];
    $expense_amounts = $_POST['expense_amount'] ?? [];
    
    // Validate input
    if ($income <= 0) {
        $error_msg = "Income must be greater than zero.";
    } else {
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Create or update budget record
            if ($budget) {
                $stmt = $pdo->prepare("UPDATE user_budgets SET income = ?, period = ?, savings_goal = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$income, $budget_period, $savings_goal, $budget['id']]);
                $budget_id = $budget['id'];
                
                // Delete existing expense items
                $stmt = $pdo->prepare("DELETE FROM budget_expenses WHERE budget_id = ?");
                $stmt->execute([$budget_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO user_budgets (user_id, income, period, savings_goal, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())");
                $stmt->execute([$user_id, $income, $budget_period, $savings_goal]);
                $budget_id = $pdo->lastInsertId();
            }
            
            // Insert expense items
            $total_expenses = 0;
            $expenses = [];
            
            for ($i = 0; $i < count($expense_categories); $i++) {
                if (!empty($expense_categories[$i]) && isset($expense_amounts[$i]) && $expense_amounts[$i] > 0) {
                    $category = trim($expense_categories[$i]);
                    $amount = floatval($expense_amounts[$i]);
                    
                    $stmt = $pdo->prepare("INSERT INTO budget_expenses (budget_id, category, amount) VALUES (?, ?, ?)");
                    $stmt->execute([$budget_id, $category, $amount]);
                    
                    $expenses[$category] = $amount;
                    $total_expenses += $amount;
                }
            }
            
            // Commit transaction
            $pdo->commit();
            
            $success_msg = "Budget plan saved successfully!";
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $error_msg = "Error saving budget plan: " . $e->getMessage();
        }
    }
}

// Calculate remaining amount after expenses
$remaining = $income - $total_expenses;

// Calculate percentage of income spent on each category
$expense_percentages = [];
foreach ($expenses as $category => $amount) {
    $expense_percentages[$category] = ($income > 0) ? ($amount / $income) * 100 : 0;
}

// Common expense categories for suggestions
$common_categories = [
    'Housing' => 'Home rent or mortgage',
    'Utilities' => 'Electricity, water, gas, etc.',
    'Food' => 'Groceries and dining out',
    'Transportation' => 'Fuel, public transport, car maintenance',
    'Healthcare' => 'Medical expenses and insurance',
    'Education' => 'School fees, books, courses',
    'Entertainment' => 'Movies, events, subscriptions',
    'Clothing' => 'Clothes, shoes, accessories',
    'Savings' => 'Emergency fund, investments',
    'Debt Repayment' => 'Loan payments, credit cards',
    'Insurance' => 'Life, health, property insurance',
    'Personal Care' => 'Haircuts, cosmetics, gym',
    'Gifts & Donations' => 'Charity, presents',
    'Miscellaneous' => 'Other expenses'
];
?>

<div class="container py-5 mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-calculator mr-2 text-primary"></i> Personal Budget Planner</h2>
            <p class="lead text-muted">Plan your finances wisely and track your spending habits</p>
        </div>
        <div class="col-md-4 text-md-right">
            <a href="../profile.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left mr-2"></i> Back to Profile
            </a>
        </div>
    </div>
    
    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= $success_msg ?>
        </div>
    <?php endif; ?>
    
    <?php if ($error_msg): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <button type="button" class="close" data-dismiss="alert">&times;</button>
            <?= $error_msg ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Budget Form -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-edit mr-2"></i> Create/Edit Your Budget</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="budgetForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="income">Income</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">KES</span>
                                        </div>
                                        <input type="number" class="form-control" id="income" name="income" value="<?= $income ?>" min="0" step="0.01" required>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="budget_period">Budget Period</label>
                                    <select class="form-control" id="budget_period" name="budget_period">
                                        <option value="weekly" <?= $budget_period == 'weekly' ? 'selected' : '' ?>>Weekly</option>
                                        <option value="monthly" <?= $budget_period == 'monthly' ? 'selected' : '' ?>>Monthly</option>
                                        <option value="quarterly" <?= $budget_period == 'quarterly' ? 'selected' : '' ?>>Quarterly</option>
                                        <option value="annually" <?= $budget_period == 'annually' ? 'selected' : '' ?>>Annually</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="savings_goal">Savings Goal</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">KES</span>
                                </div>
                                <input type="number" class="form-control" id="savings_goal" name="savings_goal" value="<?= $savings_goal ?>" min="0" step="0.01">
                            </div>
                            <small class="form-text text-muted">How much you aim to save during this period</small>
                        </div>
                        
                        <hr>
                        
                        <h5 class="mb-3">Expenses</h5>
                        <div id="expensesContainer">
                            <?php if (count($expenses) > 0): ?>
                                <?php foreach ($expenses as $category => $amount): ?>
                                    <div class="expense-item row mb-3">
                                        <div class="col-md-6">
                                            <input type="text" class="form-control" name="expense_category[]" placeholder="Category" value="<?= htmlspecialchars($category) ?>" required>
                                        </div>
                                        <div class="col-md-5">
                                            <div class="input-group">
                                                <div class="input-group-prepend">
                                                    <span class="input-group-text">KES</span>
                                                </div>
                                                <input type="number" class="form-control expense-amount" name="expense_amount[]" placeholder="Amount" value="<?= $amount ?>" min="0" step="0.01" required>
                                            </div>
                                        </div>
                                        <div class="col-md-1">
                                            <button type="button" class="btn btn-danger remove-expense"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="expense-item row mb-3">
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" name="expense_category[]" placeholder="Category" required>
                                    </div>
                                    <div class="col-md-5">
                                        <div class="input-group">
                                            <div class="input-group-prepend">
                                                <span class="input-group-text">KES</span>
                                            </div>
                                            <input type="number" class="form-control expense-amount" name="expense_amount[]" placeholder="Amount" min="0" step="0.01" required>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" class="btn btn-danger remove-expense"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" id="addExpense" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-plus mr-2"></i> Add Expense Category
                            </button>
                            
                            <div class="dropdown d-inline-block ml-2">
                                <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" id="commonCategoriesDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="fas fa-list mr-2"></i> Common Categories
                                </button>
                                <div class="dropdown-menu" aria-labelledby="commonCategoriesDropdown">
                                    <?php foreach ($common_categories as $category => $description): ?>
                                        <a class="dropdown-item common-category" href="#" data-category="<?= htmlspecialchars($category) ?>" data-toggle="tooltip" title="<?= htmlspecialchars($description) ?>">
                                            <?= htmlspecialchars($category) ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Total Expenses</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">KES</span>
                                        </div>
                                        <input type="text" class="form-control" id="totalExpenses" value="<?= number_format($total_expenses, 2) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Remaining</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">KES</span>
                                        </div>
                                        <input type="text" class="form-control" id="remainingAmount" value="<?= number_format($remaining, 2) ?>" readonly>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" name="save_budget" class="btn btn-primary">
                            <i class="fas fa-save mr-2"></i> Save Budget Plan
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Budget Summary -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-pie mr-2"></i> Budget Summary</h5>
                </div>
                <div class="card-body">
                    <?php if ($income > 0): ?>
                        <canvas id="expenseChart" width="100%" height="200"></canvas>
                        
                        <hr>
                        
                        <h6 class="font-weight-bold">Breakdown</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Amount</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($expenses as $category => $amount): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($category) ?></td>
                                            <td>KES <?= number_format($amount, 2) ?></td>
                                            <td><?= number_format($expense_percentages[$category], 1) ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if ($remaining > 0): ?>
                                        <tr class="table-success">
                                            <td>Remaining</td>
                                            <td>KES <?= number_format($remaining, 2) ?></td>
                                            <td><?= number_format(($remaining / $income) * 100, 1) ?>%</td>
                                        </tr>
                                    <?php elseif ($remaining < 0): ?>
                                        <tr class="table-danger">
                                            <td>Deficit</td>
                                            <td>KES <?= number_format(abs($remaining), 2) ?></td>
                                            <td><?= number_format((abs($remaining) / $income) * 100, 1) ?>%</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-pie fa-4x text-muted mb-3"></i>
                            <p>Enter your income and expenses to see your budget summary.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Budget Tips -->
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb mr-2"></i> Budget Tips</h5>
                </div>
                <div class="card-body">
                    <div id="budgetTips" class="carousel slide" data-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <h6 class="font-weight-bold">50/30/20 Rule</h6>
                                <p>Allocate 50% of your income to needs, 30% to wants, and 20% to savings and debt repayment.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Emergency Fund</h6>
                                <p>Aim to save 3-6 months of living expenses for emergencies.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Track Your Spending</h6>
                                <p>Keep track of all expenses to identify areas where you can cut back.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Pay Yourself First</h6>
                                <p>Set aside savings as soon as you receive income, before paying other expenses.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Avoid Impulse Purchases</h6>
                                <p>Wait 24 hours before making non-essential purchases to avoid impulse buying.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Use Cash Envelopes</h6>
                                <p>For categories where you tend to overspend, use cash envelopes to limit your spending.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Review Regularly</h6>
                                <p>Review your budget monthly and adjust as needed based on changing circumstances.</p>
                            </div>
                        </div>
                        <a class="carousel-control-prev" href="#budgetTips" role="button" data-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="sr-only">Previous</span>
                        </a>
                        <a class="carousel-control-next" href="#budgetTips" role="button" data-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="sr-only">Next</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Financial Health Score Card -->
    <div class="card shadow-sm mt-4">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0"><i class="fas fa-heartbeat mr-2"></i> Financial Health Assessment</h5>
        </div>
        <div class="card-body">
            <?php if ($income > 0): ?>
                <?php 
                    // Calculate financial health metrics
                    $savings_rate = ($income > 0) ? ($savings_goal / $income) * 100 : 0;
                    $expense_to_income = ($income > 0) ? ($total_expenses / $income) * 100 : 0;
                    $has_emergency_fund = isset($expenses['Savings']) && $expenses['Savings'] > 0;
                    $has_debt_repayment = isset($expenses['Debt Repayment']) && $expenses['Debt Repayment'] > 0;
                    
                    // Calculate overall score (simplified)
                    $score = 0;
                    if ($savings_rate >= 20) $score += 30;
                    elseif ($savings_rate >= 10) $score += 20;
                    elseif ($savings_rate > 0) $score += 10;
                    
                    if ($expense_to_income <= 70) $score += 30;
                    elseif ($expense_to_income <= 85) $score += 20;
                    elseif ($expense_to_income < 100) $score += 10;
                    
                    if ($has_emergency_fund) $score += 20;
                    if ($has_debt_repayment) $score += 20;
                    
                    // Determine score category
                    $score_category = '';
                    $score_color = '';
                    if ($score >= 80) {
                        $score_category = 'Excellent';
                        $score_color = 'success';
                    } elseif ($score >= 60) {
                        $score_category = 'Good';
                        $score_color = 'info';
                    } elseif ($score >= 40) {
                        $score_category = 'Fair';
                        $score_color = 'warning';
                    } else {
                        $score_category = 'Needs Improvement';
                        $score_color = 'danger';
                    }
                ?>
                
                <div class="row">
                    <div class="col-md-4 text-center">
                        <div class="display-4 font-weight-bold text-<?= $score_color ?>"><?= $score ?>%</div>
                        <p class="lead">Your Financial Health Score</p>
                        <span class="badge badge-<?= $score_color ?> px-3 py-2"><?= $score_category ?></span>
                    </div>
                    <div class="col-md-8">
                        <h6 class="font-weight-bold">Key Metrics:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-2">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Savings Rate:</span>
                                            <span class="font-weight-bold <?= ($savings_rate >= 20) ? 'text-success' : (($savings_rate > 0) ? 'text-warning' : 'text-danger') ?>">
                                                <?= number_format($savings_rate, 1) ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-2">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Expense-to-Income:</span>
                                            <span class="font-weight-bold <?= ($expense_to_income <= 70) ? 'text-success' : (($expense_to_income < 100) ? 'text-warning' : 'text-danger') ?>">
                                                <?= number_format($expense_to_income, 1) ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-2">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Emergency Fund:</span>
                                            <span class="font-weight-bold <?= $has_emergency_fund ? 'text-success' : 'text-danger' ?>">
                                                <?= $has_emergency_fund ? 'Yes' : 'No' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-2">
                                    <div class="card-body py-2">
                                        <div class="d-flex justify-content-between">
                                            <span>Debt Management:</span>
                                            <span class="font-weight-bold <?= $has_debt_repayment ? 'text-success' : 'text-warning' ?>">
                                                <?= $has_debt_repayment ? 'Active' : 'Not Tracked' ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6 class="font-weight-bold">Recommendations:</h6>
                            <ul class="list-group">
                                <?php if ($savings_rate < 20): ?>
                                    <li class="list-group-item list-group-item-warning">
                                        <i class="fas fa-exclamation-triangle mr-2"></i> Aim to save at least 20% of your income
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($expense_to_income > 80): ?>
                                    <li class="list-group-item list-group-item-warning">
                                        <i class="fas fa-exclamation-triangle mr-2"></i> Your expenses are high relative to income
                                    </li>
                                <?php endif; ?>
                                
                                <?php if (!$has_emergency_fund): ?>
                                    <li class="list-group-item list-group-item-danger">
                                        <i class="fas fa-exclamation-circle mr-2"></i> Create an emergency fund category
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($remaining < 0): ?>
                                    <li class="list-group-item list-group-item-danger">
                                        <i class="fas fa-exclamation-circle mr-2"></i> Your budget has a deficit - reduce expenses
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-heartbeat fa-4x text-muted mb-3"></i>
                    <p>Complete your budget to see your financial health assessment.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- JavaScript for Budget Planner -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize expense chart if data exists
    if (document.getElementById('expenseChart')) {
        const ctx = document.getElementById('expenseChart').getContext('2d');
        
        <?php if (count($expenses) > 0): ?>
            const expenseData = {
                labels: [<?php echo "'" . implode("', '", array_keys($expenses)) . "'"; ?>],
                datasets: [{
                    data: [<?php echo implode(', ', array_values($expenses)); ?>],
                    backgroundColor: [
                        '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                        '#fd7e14', '#6f42c1', '#20c9a6', '#5a5c69', '#858796',
                        '#5a5c69', '#2e59d9', '#17a673', '#2c9faf', '#f8f9fc'
                    ],
                    hoverBackgroundColor: [
                        '#2e59d9', '#17a673', '#2c9faf', '#f4b619', '#e02d1b',
                        '#fd7e14', '#6f42c1', '#20c9a6', '#5a5c69', '#858796',
                        '#5a5c69', '#2e59d9', '#17a673', '#2c9faf', '#f8f9fc'
                    ],
                    hoverBorderColor: "rgba(234, 236, 244, 1)",
                }]
            };
            
            const expenseChart = new Chart(ctx, {
                type: 'doughnut',
                data: expenseData,
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
                                const label = data.labels[tooltipItem.index];
                                const value = data.datasets[0].data[tooltipItem.index];
                                return label + ': KES ' + parseFloat(value).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
                            }
                        }
                    },
                    legend: {
                        display: false
                    },
                    cutoutPercentage: 70,
                }
            });
        <?php endif; ?>
    }
    
    // Add expense row
    document.getElementById('addExpense').addEventListener('click', function() {
        const container = document.getElementById('expensesContainer');
        const newRow = document.createElement('div');
        newRow.className = 'expense-item row mb-3';
        newRow.innerHTML = `
            <div class="col-md-6">
                <input type="text" class="form-control" name="expense_category[]" placeholder="Category" required>
            </div>
            <div class="col-md-5">
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">KES</span>
                    </div>
                    <input type="number" class="form-control expense-amount" name="expense_amount[]" placeholder="Amount" min="0" step="0.01" required>
                </div>
            </div>
            <div class="col-md-1">
                <button type="button" class="btn btn-danger remove-expense"><i class="fas fa-times"></i></button>
            </div>
        `;
        container.appendChild(newRow);
        
        // Add event listener to the new remove button
        newRow.querySelector('.remove-expense').addEventListener('click', function() {
            container.removeChild(newRow);
            updateTotals();
        });
        
        // Add event listener to the new amount input
        newRow.querySelector('.expense-amount').addEventListener('input', updateTotals);
    });
    
    // Remove expense row
    document.querySelectorAll('.remove-expense').forEach(button => {
        button.addEventListener('click', function() {
            this.closest('.expense-item').remove();
            updateTotals();
        });
    });
    
    // Update totals when expense amounts change
    document.querySelectorAll('.expense-amount').forEach(input => {
        input.addEventListener('input', updateTotals);
    });
    
    // Add common category
    document.querySelectorAll('.common-category').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const category = this.getAttribute('data-category');
            
            // Find first empty category input or add a new row
            let emptyInput = null;
            document.querySelectorAll('input[name="expense_category[]"]').forEach(input => {
                if (!input.value && !emptyInput) {
                    emptyInput = input;
                }
            });
            
            if (emptyInput) {
                emptyInput.value = category;
            } else {
                document.getElementById('addExpense').click();
                const lastInput = document.querySelector('#expensesContainer .expense-item:last-child input[name="expense_category[]"]');
                lastInput.value = category;
            }
        });
    });
    
    // Update income input
    document.getElementById('income').addEventListener('input', updateTotals);
    
    // Function to update totals
    function updateTotals() {
        let total = 0;
        document.querySelectorAll('.expense-amount').forEach(input => {
            const amount = parseFloat(input.value) || 0;
            total += amount;
        });
        
        const income = parseFloat(document.getElementById('income').value) || 0;
        const remaining = income - total;
        
        document.getElementById('totalExpenses').value = total.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        document.getElementById('remainingAmount').value = remaining.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        // Update color of remaining amount based on value
        const remainingField = document.getElementById('remainingAmount');
        if (remaining < 0) {
            remainingField.classList.add('text-danger');
            remainingField.classList.remove('text-success');
        } else {
            remainingField.classList.add('text-success');
            remainingField.classList.remove('text-danger');
        }
    }
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Auto-calculate totals on page load
    updateTotals();
    
    // Budget period change warning
    document.getElementById('budget_period').addEventListener('change', function() {
        if (document.querySelectorAll('.expense-item').length > 1) {
            alert('Note: Changing the budget period does not automatically adjust your expense amounts. Please review your expenses to ensure they match the new period.');
        }
    });
    
    // Export budget as PDF
    document.getElementById('exportPdf').addEventListener('click', function() {
        window.print();
    });
    
    // Save budget as template
    document.getElementById('saveTemplate').addEventListener('click', function() {
        const templateName = prompt('Enter a name for this budget template:');
        if (templateName) {
            // Here you would typically save the template via AJAX
            alert('Budget template "' + templateName + '" saved successfully!');
        }
    });
});
</script>

<!-- Add Export and Template buttons -->
<div class="container mb-5">
    <div class="row">
        <div class="col-12 text-right">
            <button type="button" id="exportPdf" class="btn btn-outline-secondary">
                <i class="fas fa-file-pdf mr-2"></i> Export as PDF
            </button>
            <button type="button" id="saveTemplate" class="btn btn-outline-primary ml-2">
                <i class="fas fa-save mr-2"></i> Save as Template
            </button>
        </div>
    </div>
</div>

<!-- Budget Comparison Modal -->
<div class="modal fade" id="comparisonModal" tabindex="-1" role="dialog" aria-labelledby="comparisonModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="comparisonModalLabel">Budget Comparison</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Compare your budget with recommended percentages:</p>
                
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Your Budget</th>
                                <th>Recommended</th>
                                <th>Difference</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Housing</td>
                                <td id="housing-actual">-</td>
                                <td>25-35%</td>
                                <td id="housing-diff">-</td>
                            </tr>
                            <tr>
                                <td>Food</td>
                                <td id="food-actual">-</td>
                                <td>10-15%</td>
                                <td id="food-diff">-</td>
                            </tr>
                            <tr>
                                <td>Transportation</td>
                                <td id="transport-actual">-</td>
                                <td>10-15%</td>
                                <td id="transport-diff">-</td>
                            </tr>
                            <tr>
                                <td>Utilities</td>
                                <td id="utilities-actual">-</td>
                                <td>5-10%</td>
                                <td id="utilities-diff">-</td>
                            </tr>
                            <tr>
                                <td>Savings</td>
                                <td id="savings-actual">-</td>
                                <td>10-20%</td>
                                <td id="savings-diff">-</td>
                            </tr>
                            <tr>
                                <td>Debt Repayment</td>
                                <td id="debt-actual">-</td>
                                <td>5-15%</td>
                                <td id="debt-diff">-</td>
                            </tr>
                            <tr>
                                <td>Entertainment</td>
                                <td id="entertainment-actual">-</td>
                                <td>5-10%</td>
                                <td id="entertainment-diff">-</td>
                            </tr>
                            <tr>
                                <td>Other</td>
                                <td id="other-actual">-</td>
                                <td>5-10%</td>
                                <td id="other-diff">-</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle mr-2"></i> These recommendations are general guidelines. Your personal situation may require different allocations.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="applyRecommendations">Apply Recommendations</button>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>