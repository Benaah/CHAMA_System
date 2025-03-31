<?php
include 'includes/header.php';
include 'config.php'; // Database connection

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to access the welfare page.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if welfare_cases table exists, if not create it
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.welfare_cases')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Create welfare_cases table
        $sql = "CREATE TABLE welfare_cases (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            amount_needed DECIMAL(10, 2) NOT NULL,
            amount_raised DECIMAL(10, 2) DEFAULT 0,
            beneficiary_id INTEGER REFERENCES users(id),
            status VARCHAR(50) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            deadline DATE NOT NULL
        )";
        $pdo->exec($sql);
        
        // Create welfare_contributions table
        $sql = "CREATE TABLE welfare_contributions (
            id SERIAL PRIMARY KEY,
            welfare_id INTEGER NOT NULL REFERENCES welfare_cases(id),
            user_id INTEGER NOT NULL REFERENCES users(id),
            amount DECIMAL(10, 2) NOT NULL,
            anonymous BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // Create indexes for faster queries
        $pdo->exec("CREATE INDEX idx_welfare_status ON welfare_cases(status)");
        $pdo->exec("CREATE INDEX idx_welfare_beneficiary ON welfare_cases(beneficiary_id)");
        $pdo->exec("CREATE INDEX idx_welfare_contributions_user ON welfare_contributions(user_id)");
    }
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Error checking/creating welfare tables: " . $e->getMessage());
}

// Check if beneficiary_id column exists, if not add it
try {
    $stmt = $pdo->prepare("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'welfare_cases' AND column_name = 'beneficiary_id'
    ");
    $stmt->execute();
    $hasBeneficiaryId = $stmt->fetchColumn();
    
    if (!$hasBeneficiaryId) {
        // Add beneficiary_id column
        $pdo->exec("ALTER TABLE welfare_cases ADD COLUMN beneficiary_id INTEGER REFERENCES users(id)");
        
        // Set default beneficiary for existing cases
        $pdo->exec("UPDATE welfare_cases SET beneficiary_id = (SELECT id FROM users LIMIT 1) WHERE beneficiary_id IS NULL");
    }
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Error checking/adding beneficiary_id column: " . $e->getMessage());
}

// Initialize variables
$welfare_cases = [];
$my_contributions = [];
$total_contributed = 0;
$total_received = 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Fetch welfare cases with filter
try {
    $query = "
        SELECT w.id, w.title, w.description, w.amount_needed, w.amount_raised, 
               w.status, w.created_at, w.deadline, w.beneficiary_id, 
               u.name as beneficiary_name, u.profile_picture as beneficiary_picture
        FROM welfare_cases w
        JOIN users u ON w.beneficiary_id = u.id
    ";

    if ($status_filter !== 'all') {
        $query .= " WHERE w.status = :status";
    }

    $query .= " ORDER BY 
        CASE 
            WHEN w.status = 'active' THEN 1
            WHEN w.status = 'pending' THEN 2
            WHEN w.status = 'completed' THEN 3
            ELSE 4
        END, 
        w.created_at DESC";

    $stmt = $pdo->prepare($query);

    if ($status_filter !== 'all') {
        $stmt->bindParam(':status', $status_filter);
    }

    $stmt->execute();
    $welfare_cases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's an error, set empty array
    $welfare_cases = [];
    error_log("Error fetching welfare cases: " . $e->getMessage());
}

// Fetch my contributions to welfare cases
try {
    $stmt = $pdo->prepare("
        SELECT wc.welfare_id, wc.amount, wc.created_at, w.title
        FROM welfare_contributions wc
        JOIN welfare_cases w ON wc.welfare_id = w.id
        WHERE wc.user_id = ?
        ORDER BY wc.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $my_contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's an error, set empty array
    $my_contributions = [];
    error_log("Error fetching user contributions: " . $e->getMessage());
}

// Calculate total contributed
try {
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total
        FROM welfare_contributions
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_contributed = $result['total'] ?? 0;
} catch (PDOException $e) {
    // If there's an error, set to 0
    $total_contributed = 0;
    error_log("Error calculating total contributed: " . $e->getMessage());
}

// Calculate total received (if user has been a beneficiary)
try {
    $stmt = $pdo->prepare("
        SELECT SUM(amount_raised) as total
        FROM welfare_cases
        WHERE beneficiary_id = ? AND status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_received = $result['total'] ?? 0;
} catch (PDOException $e) {
    // If there's an error, set to 0
    $total_received = 0;
    error_log("Error calculating total received: " . $e->getMessage());
}

// Handle welfare contribution form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['contribute'])) {
    $welfare_id = $_POST['welfare_id'];
    $amount = floatval($_POST['amount']);
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;
    
    // Validate input
    if ($amount <= 0) {
        $_SESSION['error'] = "Contribution amount must be greater than zero.";
    } else {
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Insert contribution record
            $stmt = $pdo->prepare("
                INSERT INTO welfare_contributions (welfare_id, user_id, amount, anonymous, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$welfare_id, $user_id, $amount, $anonymous]);
            
            // Update welfare case amount raised
            $stmt = $pdo->prepare("
                UPDATE welfare_cases 
                SET amount_raised = amount_raised + ?,
                    status = CASE 
                        WHEN amount_raised + ? >= amount_needed THEN 'completed'
                        ELSE status
                    END
                WHERE id = ?
            ");
            $stmt->execute([$amount, $amount, $welfare_id]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success'] = "Your contribution of KES " . number_format($amount, 2) . " has been recorded. Thank you for your generosity!";
            
            // Redirect to avoid form resubmission
            header("Location: welfare.php");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error'] = "Error processing contribution: " . $e->getMessage();
        }
    }
}

// Handle new welfare case submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_case'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $amount_needed = floatval($_POST['amount_needed']);
    $beneficiary_id = $_POST['beneficiary_id'];
    $deadline = $_POST['deadline'];
    
    // Validate input
    if (empty($title) || empty($description) || $amount_needed <= 0 || empty($deadline)) {
        $_SESSION['error'] = "All fields are required and amount needed must be greater than zero.";
    } else {
        try {
            // Insert new welfare case
            $stmt = $pdo->prepare("
                INSERT INTO welfare_cases (title, description, amount_needed, amount_raised, beneficiary_id, status, created_at, deadline)
                VALUES (?, ?, ?, 0, ?, 'pending', NOW(), ?)
            ");
            $stmt->execute([$title, $description, $amount_needed, $beneficiary_id, $deadline]);
            
            $_SESSION['success'] = "Welfare case submitted successfully and is pending approval.";
            
            // Redirect to avoid form resubmission
            header("Location: welfare.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error submitting welfare case: " . $e->getMessage();
        }
    }
}

// Display error or success messages
if (isset($_SESSION['error'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error']) . '</div>';
    unset($_SESSION['error']);
} elseif (isset($_SESSION['success'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
    unset($_SESSION['success']);
}
?>

<div class="container py-5 mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-hands-helping mr-2 text-primary"></i> Welfare Program</h2>
            <p class="lead text-muted">Support fellow members in times of need</p>
        </div>
        <div class="col-md-4 text-md-right">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#newCaseModal">
                <i class="fas fa-plus mr-2"></i> Submit New Case
            </button>
        </div>
    </div>
    
    <!-- Status Filter Tabs -->
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $status_filter == 'all' ? 'active' : '' ?>" href="welfare.php?status=all">All Cases</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter == 'active' ? 'active' : '' ?>" href="welfare.php?status=active">Active</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter == 'pending' ? 'active' : '' ?>" href="welfare.php?status=pending">Pending</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $status_filter == 'completed' ? 'active' : '' ?>" href="welfare.php?status=completed">Completed</a>
        </li>
    </ul>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Welfare Cases -->
            <?php if (count($welfare_cases) > 0): ?>
                <?php foreach ($welfare_cases as $case): ?>
                    <?php 
                        $progress = ($case['amount_raised'] / $case['amount_needed']) * 100;
                        $days_left = (strtotime($case['deadline']) - time()) / (60 * 60 * 24);
                        $status_class = '';
                        $status_text = '';
                        
                        switch ($case['status']) {
                            case 'active':
                                $status_class = 'success';
                                $status_text = 'Active';
                                break;
                            case 'pending':
                                $status_class = 'warning';
                                $status_text = 'Pending Approval';
                                break;
                            case 'completed':
                                $status_class = 'info';
                                $status_text = 'Completed';
                                break;
                            default:
                                $status_class = 'secondary';
                                $status_text = 'Closed';
                        }
                    ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?= htmlspecialchars($case['title']) ?></h5>
                                <span class="badge badge-<?= $status_class ?> px-3 py-2"><?= $status_text ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 text-center mb-3 mb-md-0">
                                    <?php if (!empty($case['beneficiary_picture']) && file_exists($case['beneficiary_picture'])): ?>
                                        <img src="<?= htmlspecialchars($case['beneficiary_picture']) ?>" alt="Beneficiary" class="rounded-circle img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                                    <?php else: ?>
                                        <img src="assets/images/default-avatar.png" alt="Beneficiary" class="rounded-circle img-thumbnail" style="width: 100px; height: 100px; object-fit: cover;">
                                    <?php endif; ?>
                                    <p class="mt-2 mb-0 font-weight-bold"><?= htmlspecialchars($case['beneficiary_name']) ?></p>
                                    <small class="text-muted">Beneficiary</small>
                                </div>
                                <div class="col-md-9">
                                    <p><?= nl2br(htmlspecialchars($case['description'])) ?></p>
                                    
                                    <div class="progress mb-3" style="height: 20px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= number_format($progress, 0) ?>%
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <small class="text-muted">Target Amount</small>
                                            <p class="font-weight-bold mb-0">KES <?= number_format($case['amount_needed'], 2) ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Raised So Far</small>
                                            <p class="font-weight-bold mb-0">KES <?= number_format($case['amount_raised'], 2) ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Deadline</small>
                                            <p class="font-weight-bold mb-0">
                                                <?= date('M d, Y', strtotime($case['deadline'])) ?>
                                                <?php if ($days_left > 0 && $case['status'] == 'active'): ?>
                                                    <span class="text-muted">(<?= floor($days_left) ?> days left)</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($case['status'] == 'active'): ?>
                                        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#contributeModal" data-welfare-id="<?= $case['id'] ?>" data-welfare-title="<?= htmlspecialchars($case['title']) ?>">
                                            <i class="fas fa-donate mr-2"></i> Contribute
                                        </button>
                                    <?php elseif ($case['status'] == 'completed'): ?>
                                        <div class="alert alert-success mb-0">
                                            <i class="fas fa-check-circle mr-2"></i> This case has been fully funded. Thank you to all contributors!
                                        </div>
                                    <?php elseif ($case['status'] == 'pending'): ?>
                                        <div class="alert alert-warning mb-0">
                                            <i class="fas fa-clock mr-2"></i> This case is pending approval from administrators.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white text-muted">
                            <small>Case submitted on <?= date('M d, Y', strtotime($case['created_at'])) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-hands-helping fa-4x text-muted mb-3"></i>
                        <h5>No welfare cases found</h5>
                        <p class="text-muted">
                            <?php if ($status_filter !== 'all'): ?>
                                There are no cases with the "<?= $status_filter ?>" status.
                                <a href="welfare.php?status=all">View all cases</a>
                            <?php else: ?>
                                There are currently no welfare cases in the system.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <!-- My Welfare Summary -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i> My Welfare Summary</h5>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <div class="display-4 text-primary">
                            <i class="fas fa-hand-holding-heart"></i>
                        </div>
                        <p class="lead">Thank you for your support!</p>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span>Total Contributed:</span>
                        <span class="font-weight-bold">KES <?= number_format($total_contributed, 2) ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between mb-3">
                        <span>Cases Supported:</span>
                        <span class="font-weight-bold"><?= count($my_contributions) ?></span>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <span>Total Received:</span>
                        <span class="font-weight-bold">KES <?= number_format($total_received, 2) ?></span>
                    </div>
                    
                    <hr>
                    
                    <h6 class="font-weight-bold">My Recent Contributions</h6>
                    <?php if (count($my_contributions) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach (array_slice($my_contributions, 0, 5) as $contribution): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <small class="text-muted"><?= date('M d, Y', strtotime($contribution['created_at'])) ?></small>
                                            <p class="mb-0"><?= htmlspecialchars($contribution['title']) ?></p>
                                        </div>
                                        <div class="text-success">
                                            KES <?= number_format($contribution['amount'], 2) ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($my_contributions) > 5): ?>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#allContributionsModal">
                                    View All Contributions
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted">You haven't made any contributions yet.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Welfare Guidelines Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-info-circle mr-2"></i> Welfare Guidelines</h5>
                </div>
                <div class="card-body">
                    <h6 class="font-weight-bold">Eligibility Criteria</h6>
                    <ul>
                        <li>Must be an active member for at least 3 months</li>
                        <li>Must have contributed to at least one previous welfare case</li>
                        <li>Emergency cases may be considered regardless of the above criteria</li>
                    </ul>
                    
                    <h6 class="font-weight-bold">Application Process</h6>
                    <ol>
                        <li>Submit a new case with all required details</li>
                        <li>Administrators review the case (typically within 48 hours)</li>
                        <li>Once approved, the case becomes active for contributions</li>
                        <li>Funds are disbursed when the target is reached or deadline is met</li>
                    </ol>
                    
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-exclamation-triangle mr-2"></i> All welfare cases are subject to verification by the welfare committee.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contribute Modal -->
<div class="modal fade" id="contributeModal" tabindex="-1" role="dialog" aria-labelledby="contributeModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="contributeModalLabel">Contribute to Welfare Case</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="welfare_id" id="welfare_id">
                    
                    <div class="form-group">
                        <label>Welfare Case</label>
                        <input type="text" class="form-control" id="welfare_title" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Contribution Amount (KES)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="100" step="100" required>
                        <small class="form-text text-muted">Minimum contribution is KES 100</small>
                    </div>
                    
                    <div class="form-group">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="anonymous" name="anonymous">
                            <label class="custom-control-label" for="anonymous">Make my contribution anonymous</label>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> Your contribution will help a fellow member in need. Thank you for your generosity!
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="contribute" class="btn btn-primary">
                        <i class="fas fa-donate mr-2"></i> Contribute
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- New Case Modal -->
<div class="modal fade" id="newCaseModal" tabindex="-1" role="dialog" aria-labelledby="newCaseModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newCaseModalLabel">Submit New Welfare Case</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">Case Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                        <small class="form-text text-muted">Provide a clear, concise title for the welfare case</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                        <small class="form-text text-muted">Explain the situation and why assistance is needed</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="amount_needed">Amount Needed (KES)</label>
                                <input type="number" class="form-control" id="amount_needed" name="amount_needed" min="1000" step="500" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="deadline">Deadline</label>
                                <input type="date" class="form-control" id="deadline" name="deadline" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="beneficiary_id">Beneficiary</label>
                        <select class="form-control" id="beneficiary_id" name="beneficiary_id" required>
                            <option value="<?= $user_id ?>" selected>Myself</option>
                            <!-- Option to add other beneficiaries could be implemented here -->
                        </select>
                        <small class="form-text text-muted">Currently, you can only submit cases for yourself</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i> By submitting this case, you agree to provide any additional documentation or verification that may be requested by the welfare committee.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_case" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Case
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- All Contributions Modal -->
<?php if (count($my_contributions) > 0): ?>
<div class="modal fade" id="allContributionsModal" tabindex="-1" role="dialog" aria-labelledby="allContributionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allContributionsModalLabel">All My Welfare Contributions</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-hover datatable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Welfare Case</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_contributions as $contribution): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($contribution['created_at'])) ?></td>
                                    <td><?= htmlspecialchars($contribution['title']) ?></td>
                                    <td class="text-success font-weight-bold">
                                        KES <?= number_format($contribution['amount'], 2) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Set welfare case ID and title in contribute modal
$('#contributeModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var welfareId = button.data('welfare-id');
    var welfareTitle = button.data('welfare-title');
    
    var modal = $(this);
    modal.find('#welfare_id').val(welfareId);
    modal.find('#welfare_title').val(welfareTitle);
});

// Initialize datepicker for deadline field
$(document).ready(function() {
    // Initialize DataTable for contributions list
    $('.datatable').DataTable({
        responsive: true,
        language: {
            search: "_INPUT_",
            searchPlaceholder: "Search...",
        },
        order: [[0, 'desc']]
    });
});
</script>

<?php include 'includes/footer.php'; ?>