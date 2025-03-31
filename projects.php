<?php
include 'includes/header.php';
include 'config.php'; // Database connection

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to access the projects page.";
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if projects table exists, if not create it
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.projects')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Create projects table
        $sql = "CREATE TABLE projects (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            category VARCHAR(100),
            target_amount DECIMAL(12, 2) NOT NULL,
            current_investment DECIMAL(12, 2) DEFAULT 0,
            expected_return DECIMAL(5, 2) NOT NULL,
            duration_months INTEGER NOT NULL,
            start_date DATE NOT NULL,
            end_date DATE,
            status VARCHAR(50) DEFAULT 'planning',
            image_path VARCHAR(255),
            proposer_id INTEGER REFERENCES users(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // Create project_investments table
        $sql = "CREATE TABLE project_investments (
            id SERIAL PRIMARY KEY,
            project_id INTEGER NOT NULL REFERENCES projects(id),
            user_id INTEGER NOT NULL REFERENCES users(id),
            amount DECIMAL(12, 2) NOT NULL,
            investment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(project_id, user_id, investment_date)
        )";
        $pdo->exec($sql);
        
        // Create project_returns table
        $sql = "CREATE TABLE project_returns (
            id SERIAL PRIMARY KEY,
            project_id INTEGER NOT NULL REFERENCES projects(id),
            user_id INTEGER NOT NULL REFERENCES users(id),
            amount DECIMAL(12, 2) NOT NULL,
            return_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            description TEXT
        )";
        $pdo->exec($sql);
        
        // Create indexes for faster queries
        $pdo->exec("CREATE INDEX idx_projects_status ON projects(status)");
        $pdo->exec("CREATE INDEX idx_projects_category ON projects(category)");
        $pdo->exec("CREATE INDEX idx_project_investments_user ON project_investments(user_id)");
        $pdo->exec("CREATE INDEX idx_project_investments_project ON project_investments(project_id)");
    }
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Error checking/creating projects tables: " . $e->getMessage());
}

// Check if category column exists, if not add it
try {
    $stmt = $pdo->prepare("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'projects' AND column_name = 'category'
    ");
    $stmt->execute();
    $hasCategory = $stmt->fetchColumn();
    
    if (!$hasCategory) {
        // Add category column
        $pdo->exec("ALTER TABLE projects ADD COLUMN category VARCHAR(100)");
        
        // Set default categories for existing projects
        $pdo->exec("UPDATE projects SET category = 'General' WHERE category IS NULL");
    }
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Error checking/adding category column: " . $e->getMessage());
}

// Initialize variables
$projects = [];
$my_investments = [];
$total_invested = 0;
$total_returns = 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';

// Fetch all project categories
try {
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM projects WHERE category IS NOT NULL ORDER BY category");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // If no categories found, provide default ones
    if (empty($categories)) {
        $categories = ['Agriculture', 'Real Estate', 'Technology', 'Education', 'Healthcare', 'Retail', 'Manufacturing', 'Services', 'Other'];
    }
} catch (PDOException $e) {
    // Default categories if query fails
    $categories = ['Agriculture', 'Real Estate', 'Technology', 'Education', 'Healthcare', 'Retail', 'Manufacturing', 'Services', 'Other'];
    error_log("Error fetching project categories: " . $e->getMessage());
}

// Fetch projects with filters
try {
    $query = "
        SELECT p.*, 
               COALESCE(SUM(pi.amount), 0) as total_invested,
               COUNT(DISTINCT pi.user_id) as investor_count
        FROM projects p
        LEFT JOIN project_investments pi ON p.id = pi.project_id
    ";

    $where_clauses = [];
    $params = [];

    if ($status_filter !== 'all') {
        $where_clauses[] = "p.status = ?";
        $params[] = $status_filter;
    }

    if ($category_filter !== 'all') {
        $where_clauses[] = "p.category = ?";
        $params[] = $category_filter;
    }

    if (!empty($where_clauses)) {
        $query .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $query .= " GROUP BY p.id ORDER BY 
        CASE 
            WHEN p.status = 'active' THEN 1
            WHEN p.status = 'planning' THEN 2
            WHEN p.status = 'completed' THEN 3
            ELSE 4
        END, 
        p.start_date DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's an error, set empty array
    $projects = [];
    error_log("Error fetching projects: " . $e->getMessage());
}

// Fetch user's investments
try {
    $stmt = $pdo->prepare("
        SELECT pi.*, p.name as project_name, p.status as project_status
        FROM project_investments pi
        JOIN projects p ON pi.project_id = p.id
        WHERE pi.user_id = ?
        ORDER BY pi.investment_date DESC
    ");
    $stmt->execute([$user_id]);
    $my_investments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's an error, set empty array
    $my_investments = [];
    error_log("Error fetching user investments: " . $e->getMessage());
}

// Calculate total invested
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM project_investments
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_invested = $result['total'] ?? 0;
} catch (PDOException $e) {
    // If there's an error, set to 0
    $total_invested = 0;
    error_log("Error calculating total invested: " . $e->getMessage());
}

// Calculate total returns
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM project_returns
        WHERE user_id = ?
    ");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_returns = $result['total'] ?? 0;
} catch (PDOException $e) {
    // If there's an error, set to 0
    $total_returns = 0;
    error_log("Error calculating total returns: " . $e->getMessage());
}

// Handle project investment form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['invest'])) {
    $project_id = $_POST['project_id'];
    $amount = floatval($_POST['amount']);
    
    // Validate input
    if ($amount <= 0) {
        $_SESSION['error'] = "Investment amount must be greater than zero.";
    } else {
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            // Insert investment record
            $stmt = $pdo->prepare("
                INSERT INTO project_investments (project_id, user_id, amount, investment_date)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$project_id, $user_id, $amount]);
            
            // Update project's total investment
            $stmt = $pdo->prepare("
                UPDATE projects 
                SET current_investment = current_investment + ?
                WHERE id = ?
            ");
            $stmt->execute([$amount, $project_id]);
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['success'] = "Your investment of KES " . number_format($amount, 2) . " has been recorded successfully!";
            
            // Redirect to avoid form resubmission
            header("Location: projects.php");
            exit();
        } catch (Exception $e) {
            // Rollback transaction on error
            $pdo->rollBack();
            $_SESSION['error'] = "Error processing investment: " . $e->getMessage();
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_proposal'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $target_amount = floatval($_POST['target_amount']);
    $expected_return = floatval($_POST['expected_return']);
    $duration = intval($_POST['duration']);
    $start_date = $_POST['start_date'];
    
    // Validate input
    if (empty($name) || empty($description) || empty($category) || $target_amount <= 0 || $expected_return <= 0 || $duration <= 0 || empty($start_date)) {
        $_SESSION['error'] = "All fields are required and numeric values must be greater than zero.";
    } else {
        try {
            // Handle project image upload
            $image_path = null;
            if (isset($_FILES['project_image']) && $_FILES['project_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['project_image']['name'];
                $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                
                if (in_array(strtolower($filetype), $allowed)) {
                    $new_filename = 'project_' . uniqid() . '.' . $filetype;
                    $upload_path = 'uploads/projects/' . $new_filename;
                    
                    // Create directory if it doesn't exist
                    if (!file_exists('uploads/projects/')) {
                        mkdir('uploads/projects/', 0777, true);
                    }
                    
                    if (move_uploaded_file($_FILES['project_image']['tmp_name'], $upload_path)) {
                        $image_path = $upload_path;
                    }
                }
            }
            
            // Insert new project proposal
            $stmt = $pdo->prepare("
                INSERT INTO projects (name, description, category, target_amount, current_investment, 
                                     expected_return, duration_months, start_date, status, image_path, 
                                     proposer_id, created_at)
                VALUES (?, ?, ?, ?, 0, ?, ?, ?, 'planning', ?, ?, NOW())
            ");
            $stmt->execute([
                $name, $description, $category, $target_amount, $expected_return, 
                $duration, $start_date, $image_path, $user_id
            ]);
            
            $_SESSION['success'] = "Project proposal submitted successfully and is pending review.";
            
            // Redirect to avoid form resubmission
            header("Location: projects.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error submitting project proposal: " . $e->getMessage();
        }
    }
}
?>

<!-- Rest of the HTML code remains the same -->

<div class="container py-5 mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2><i class="fas fa-project-diagram mr-2 text-primary"></i> Group Projects</h2>
            <p class="lead text-muted">Invest in group projects and earn returns</p>
        </div>
        <div class="col-md-4 text-md-right">
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#proposalModal">
                <i class="fas fa-plus mr-2"></i> Submit Project Proposal
            </button>
        </div>
    </div>
    
    <!-- Filter Controls -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-md-0">
                        <label for="statusFilter">Status</label>
                        <select id="statusFilter" class="form-control" onchange="window.location.href='projects.php?status='+this.value+'&category=<?= $category_filter ?>'">
                            <option value="all" <?= $status_filter == 'all' ? 'selected' : '' ?>>All Statuses</option>
                            <option value="planning" <?= $status_filter == 'planning' ? 'selected' : '' ?>>Planning</option>
                            <option value="active" <?= $status_filter == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="completed" <?= $status_filter == 'completed' ? 'selected' : '' ?>>Completed</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-0">
                        <label for="categoryFilter">Category</label>
                        <select id="categoryFilter" class="form-control" onchange="window.location.href='projects.php?status=<?= $status_filter ?>&category='+this.value">
                            <option value="all" <?= $category_filter == 'all' ? 'selected' : '' ?>>All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>" <?= $category_filter == $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($category) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Projects List -->
            <?php if (count($projects) > 0): ?>
                <?php foreach ($projects as $project): ?>
                    <?php 
                        $progress = ($project['current_investment'] / $project['target_amount']) * 100;
                        $status_class = '';
                        $status_text = '';
                        
                        switch ($project['status']) {
                            case 'active':
                                $status_class = 'success';
                                $status_text = 'Active';
                                break;
                            case 'planning':
                                $status_class = 'warning';
                                $status_text = 'Planning';
                                break;
                            case 'completed':
                                $status_class = 'info';
                                $status_text = 'Completed';
                                break;
                            default:
                                $status_class = 'secondary';
                                $status_text = 'Unknown';
                        }
                        
                        // Check if user has already invested in this project
                        $user_invested = false;
                        $user_investment = 0;
                        foreach ($my_investments as $investment) {
                            if ($investment['project_id'] == $project['id']) {
                                $user_invested = true;
                                $user_investment += $investment['amount'];
                            }
                        }
                    ?>
                    <div class="card shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?= htmlspecialchars($project['name']) ?></h5>
                                <span class="badge badge-<?= $status_class ?> px-3 py-2"><?= $status_text ?></span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3 mb-md-0">
                                    <?php if (!empty($project['image_path']) && file_exists($project['image_path'])): ?>
                                        <img src="<?= htmlspecialchars($project['image_path']) ?>" alt="Project Image" class="img-fluid rounded">
                                    <?php else: ?>
                                        <img src="assets/images/default-project.jpg" alt="Default Project Image" class="img-fluid rounded">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-8">
                                    <p><?= nl2br(htmlspecialchars($project['description'])) ?></p>
                                    
                                    <div class="progress mb-3" style="height: 20px;">
                                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%;" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                                            <?= number_format($progress, 0) ?>%
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <small class="text-muted">Target Investment</small>
                                            <p class="font-weight-bold mb-0">KES <?= number_format($project['target_amount'], 2) ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Current Investment</small>
                                            <p class="font-weight-bold mb-0">KES <?= number_format($project['current_investment'], 2) ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Expected Return</small>
                                            <p class="font-weight-bold mb-0"><?= $project['expected_return'] ?>%</p>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <small class="text-muted">Duration</small>
                                            <p class="font-weight-bold mb-0"><?= $project['duration_months'] ?> months</p>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Start Date</small>
                                            <p class="font-weight-bold mb-0"><?= date('M d, Y', strtotime($project['start_date'])) ?></p>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">Category</small>
                                            <p class="font-weight-bold mb-0"><?= htmlspecialchars($project['category']) ?></p>
                                        </div>
                                    </div>
                                    
                                    <?php if ($project['status'] == 'active'): ?>
                                        <div class="d-flex align-items-center">
                                            <button type="button" class="btn btn-primary mr-3" data-toggle="modal" data-target="#investModal" 
                                                    data-project-id="<?= $project['id'] ?>" 
                                                    data-project-name="<?= htmlspecialchars($project['name']) ?>"
                                                    data-min-investment="<?= $project['min_investment'] ?? 1000 ?>">
                                                <i class="fas fa-hand-holding-usd mr-2"></i> Invest Now
                                            </button>
                                            
                                            <?php if ($user_invested): ?>
                                                <span class="badge badge-light p-2">
                                                    <i class="fas fa-check-circle text-success mr-1"></i> 
                                                    You invested: KES <?= number_format($user_investment, 2) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($project['status'] == 'completed'): ?>
                                        <div class="alert alert-success mb-0">
                                            <i class="fas fa-check-circle mr-2"></i> This project has been completed successfully.
                                            <?php if ($user_invested): ?>
                                                <strong>Your investment: KES <?= number_format($user_investment, 2) ?></strong>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif ($project['status'] == 'planning'): ?>
                                        <div class="alert alert-warning mb-0">
                                            <i class="fas fa-clock mr-2"></i> This project is in the planning phase and will be open for investment soon.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-users mr-1"></i> <?= $project['investor_count'] ?> investors
                                </small>
                                <a href="#" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#projectDetailsModal" 
                                   data-project-id="<?= $project['id'] ?>"
                                   data-project-name="<?= htmlspecialchars($project['name']) ?>"
                                   data-project-desc="<?= htmlspecialchars($project['description']) ?>"
                                   data-project-category="<?= htmlspecialchars($project['category']) ?>"
                                   data-project-target="<?= $project['target_amount'] ?>"
                                   data-project-current="<?= $project['current_investment'] ?>"
                                   data-project-return="<?= $project['expected_return'] ?>"
                                   data-project-duration="<?= $project['duration_months'] ?>"
                                   data-project-start="<?= $project['start_date'] ?>"
                                   data-project-status="<?= $project['status'] ?>"
                                   data-project-image="<?= !empty($project['image_path']) ? $project['image_path'] : 'assets/images/default-project.jpg' ?>">
                                    <i class="fas fa-info-circle mr-1"></i> View Details
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-project-diagram fa-4x text-muted mb-3"></i>
                        <h5>No projects found</h5>
                        <p class="text-muted">
                            <?php if ($status_filter !== 'all' || $category_filter !== 'all'): ?>
                                No projects match your current filters.
                                <a href="projects.php">Clear all filters</a>
                            <?php else: ?>
                                There are currently no projects in the system.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-lg-4">
            <!-- My Investments Summary -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-chart-line mr-2"></i> My Investment Summary</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-4">
                        <div class="col-6">
                            <h3 class="text-primary mb-0">KES <?= number_format($total_invested, 2) ?></h3>
                            <p class="text-muted">Total Invested</p>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success mb-0">KES <?= number_format($total_returns, 2) ?></h3>
                            <p class="text-muted">Total Returns</p>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <h6 class="font-weight-bold">My Active Investments</h6>
                    <?php 
                        $active_investments = array_filter($my_investments, function($inv) {
                            return $inv['project_status'] == 'active';
                        });
                    ?>
                    
                    <?php if (count($active_investments) > 0): ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach (array_slice($active_investments, 0, 5) as $investment): ?>
                                <li class="list-group-item px-0">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <small class="text-muted"><?= date('M d, Y', strtotime($investment['investment_date'])) ?></small>
                                            <p class="mb-0"><?= htmlspecialchars($investment['project_name']) ?></p>
                                        </div>
                                        <div class="text-primary">
                                            KES <?= number_format($investment['amount'], 2) ?>
                                        </div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php if (count($active_investments) > 5): ?>
                            <div class="text-center mt-3">
                                <button type="button" class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#allInvestmentsModal">
                                    View All Investments
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted">You don't have any active investments.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Project Categories Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-tags mr-2"></i> Project Categories</h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($categories as $category): ?>
                            <a href="projects.php?category=<?= urlencode($category) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($category) ?>
                                <?php
                                    // Count projects in this category
                                    $category_count = 0;
                                    foreach ($projects as $p) {
                                        if ($p['category'] == $category) {
                                            $category_count++;
                                        }
                                    }
                                ?>
                                <span class="badge badge-primary badge-pill"><?= $category_count ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Investment Tips Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="fas fa-lightbulb mr-2"></i> Investment Tips</h5>
                </div>
                <div class="card-body">
                    <div id="investmentTips" class="carousel slide" data-ride="carousel">
                        <div class="carousel-inner">
                            <div class="carousel-item active">
                                <h6 class="font-weight-bold">Diversify Your Investments</h6>
                                <p>Spread your investments across different projects to reduce risk.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Start Small</h6>
                                <p>Begin with smaller investments to understand the process before committing larger amounts.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Research Projects</h6>
                                <p>Take time to understand the project details, expected returns, and risks involved.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Consider the Timeline</h6>
                                <p>Ensure the project duration aligns with your financial goals and liquidity needs.</p>
                            </div>
                            <div class="carousel-item">
                                <h6 class="font-weight-bold">Track Performance</h6>
                                <p>Regularly monitor your investments to understand their performance and make informed decisions.</p>
                            </div>
                        </div>
                        <a class="carousel-control-prev" href="#investmentTips" role="button" data-slide="prev">
                            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                            <span class="sr-only">Previous</span>
                        </a>
                        <a class="carousel-control-next" href="#investmentTips" role="button" data-slide="next">
                            <span class="carousel-control-next-icon" aria-hidden="true"></span>
                            <span class="sr-only">Next</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Invest Modal -->
<div class="modal fade" id="investModal" tabindex="-1" role="dialog" aria-labelledby="investModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="investModalLabel">Invest in Project</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <input type="hidden" name="project_id" id="project_id">
                    
                    <div class="form-group">
                        <label>Project</label>
                        <input type="text" class="form-control" id="project_name" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Investment Amount (KES)</label>
                        <input type="number" class="form-control" id="amount" name="amount" min="1000" step="500" required>
                        <small class="form-text text-muted">Minimum investment is KES <span id="min_investment">1,000</span></small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> Your investment will be pooled with other members to fund this project. Returns will be distributed based on your investment proportion.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="invest" class="btn btn-primary">
                        <i class="fas fa-hand-holding-usd mr-2"></i> Confirm Investment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Project Proposal Modal -->
<div class="modal fade" id="proposalModal" tabindex="-1" role="dialog" aria-labelledby="proposalModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proposalModalLabel">Submit Project Proposal</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Project Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                        <small class="form-text text-muted">Provide a detailed description of the project, its goals, and expected outcomes.</small>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select class="form-control" id="category" name="category" required>
                                    <option value="">Select Category</option>
                                    <option value="Agriculture">Agriculture</option>
                                    <option value="Real Estate">Real Estate</option>
                                    <option value="Technology">Technology</option>
                                    <option value="Education">Education</option>
                                    <option value="Healthcare">Healthcare</option>
                                    <option value="Retail">Retail</option>
                                    <option value="Manufacturing">Manufacturing</option>
                                    <option value="Services">Services</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="target_amount">Target Amount (KES)</label>
                                <input type="number" class="form-control" id="target_amount" name="target_amount" min="10000" step="1000" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="expected_return">Expected Return (%)</label>
                                <input type="number" class="form-control" id="expected_return" name="expected_return" min="1" max="100" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="duration">Duration (Months)</label>
                                <input type="number" class="form-control" id="duration" name="duration" min="1" max="60" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" min="<?= date('Y-m-d') ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="project_image">Project Image</label>
                        <input type="file" class="form-control-file" id="project_image" name="project_image" accept="image/*">
                        <small class="form-text text-muted">Upload an image that represents the project (optional).</small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i> Your proposal will be reviewed by the management committee before being approved for investment.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="submit_proposal" class="btn btn-primary">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Proposal
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Project Details Modal -->
<div class="modal fade" id="projectDetailsModal" tabindex="-1" role="dialog" aria-labelledby="projectDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="projectDetailsModalLabel">Project Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-5 text-center mb-3">
                        <img id="modal-project-image" src="" alt="Project Image" class="img-fluid rounded">
                    </div>
                    <div class="col-md-7">
                        <h4 id="modal-project-name"></h4>
                        <p id="modal-project-desc"></p>
                        
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Category</small>
                                <p class="font-weight-bold" id="modal-project-category"></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Status</small>
                                <p class="font-weight-bold" id="modal-project-status"></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Target Amount</small>
                                <p class="font-weight-bold" id="modal-project-target"></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Current Investment</small>
                                <p class="font-weight-bold" id="modal-project-current"></p>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <small class="text-muted">Expected Return</small>
                                <p class="font-weight-bold" id="modal-project-return"></p>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Duration</small>
                                <p class="font-weight-bold" id="modal-project-duration"></p>
                            </div>
                        </div>
                        
                        <div class="progress mb-3" style="height: 20px;">
                            <div class="progress-bar bg-success" id="modal-project-progress" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                0%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="modal-invest-btn" data-dismiss="modal" data-toggle="modal" data-target="#investModal">
                    <i class="fas fa-hand-holding-usd mr-2"></i> Invest
                </button>
            </div>
        </div>
    </div>
</div>

<!-- All Investments Modal -->
<?php if (count($my_investments) > 0): ?>
<div class="modal fade" id="allInvestmentsModal" tabindex="-1" role="dialog" aria-labelledby="allInvestmentsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="allInvestmentsModalLabel">All My Investments</h5>
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
                                <th>Project</th>
                                <th>Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($my_investments as $investment): ?>
                                <tr>
                                    <td><?= date('M d, Y', strtotime($investment['investment_date'])) ?></td>
                                    <td><?= htmlspecialchars($investment['project_name']) ?></td>
                                    <td class="text-primary font-weight-bold">
                                        KES <?= number_format($investment['amount'], 2) ?>
                                    </td>
                                    <td>
                                        <?php 
                                            $inv_status_class = '';
                                            switch ($investment['project_status']) {
                                                case 'active':
                                                    $inv_status_class = 'success';
                                                    break;
                                                case 'planning':
                                                    $inv_status_class = 'warning';
                                                    break;
                                                case 'completed':
                                                    $inv_status_class = 'info';
                                                    break;
                                                default:
                                                    $inv_status_class = 'secondary';
                                            }
                                        ?>
                                        <span class="badge badge-<?= $inv_status_class ?>">
                                            <?= ucfirst($investment['project_status']) ?>
                                        </span>
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
// Set project ID and name in invest modal
$('#investModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var projectId = button.data('project-id');
    var projectName = button.data('project-name');
    var minInvestment = button.data('min-investment');
    
    var modal = $(this);
    modal.find('#project_id').val(projectId);
    modal.find('#project_name').val(projectName);
    modal.find('#amount').attr('min', minInvestment);
    modal.find('#min_investment').text(minInvestment.toLocaleString());
});

// Set project details in details modal
$('#projectDetailsModal').on('show.bs.modal', function (event) {
    var button = $(event.relatedTarget);
    var projectId = button.data('project-id');
    var projectName = button.data('project-name');
    var projectDesc = button.data('project-desc');
    var projectCategory = button.data('project-category');
    var projectTarget = button.data('project-target');
    var projectCurrent = button.data('project-current');
    var projectReturn = button.data('project-return');
    var projectDuration = button.data('project-duration');
    var projectStart = button.data('project-start');
    var projectStatus = button.data('project-status');
    var projectImage = button.data('project-image');
    
    var progress = (projectCurrent / projectTarget) * 100;
    
    var modal = $(this);
    modal.find('#modal-project-name').text(projectName);
    modal.find('#modal-project-desc').text(projectDesc);
    modal.find('#modal-project-category').text(projectCategory);
    modal.find('#modal-project-target').text('KES ' + parseFloat(projectTarget).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    modal.find('#modal-project-current').text('KES ' + parseFloat(projectCurrent).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
    modal.find('#modal-project-return').text(projectReturn + '%');
    modal.find('#modal-project-duration').text(projectDuration + ' months');
    modal.find('#modal-project-image').attr('src', projectImage);
    
    // Set status with appropriate styling
    var statusText = '';
    var statusClass = '';
    switch (projectStatus) {
        case 'active':
            statusText = 'Active';
            statusClass = 'text-success';
            break;
        case 'planning':
            statusText = 'Planning';
            statusClass = 'text-warning';
            break;
        case 'completed':
            statusText = 'Completed';
            statusClass = 'text-info';
            break;
        default:
            statusText = 'Unknown';
            statusClass = 'text-secondary';
    }
    modal.find('#modal-project-status').text(statusText).addClass(statusClass);
    
    // Set progress bar
    modal.find('#modal-project-progress').css('width', progress + '%').text(Math.round(progress) + '%');
    
    // Configure invest button
    if (projectStatus === 'active') {
        modal.find('#modal-invest-btn').show().data('project-id', projectId).data('project-name', projectName);
    } else {
        modal.find('#modal-invest-btn').hide();
    }
});

// Initialize datepicker for start date field
$(document).ready(function() {
    // Initialize DataTable for investments list
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