<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // This will redirect to login if not authenticated

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Check if merry_go_round table exists, if not create it
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.merry_go_round')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Create merry_go_round table
        $sql = "CREATE TABLE merry_go_round (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            amount_per_member DECIMAL(10, 2) NOT NULL,
            frequency VARCHAR(50) NOT NULL, -- weekly, monthly, etc.
            start_date DATE NOT NULL,
            end_date DATE,
            status VARCHAR(50) DEFAULT 'active',
            created_by INTEGER REFERENCES users(id),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // Create merry_go_round_members table
        $sql = "CREATE TABLE merry_go_round_members (
            id SERIAL PRIMARY KEY,
            merry_go_round_id INTEGER NOT NULL REFERENCES merry_go_round(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id),
            position INTEGER NOT NULL, -- Order in which they receive funds
            received BOOLEAN DEFAULT FALSE,
            received_date DATE,
            joined_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE(merry_go_round_id, user_id),
            UNIQUE(merry_go_round_id, position)
        )";
        $pdo->exec($sql);
        
        // Create merry_go_round_contributions table
        $sql = "CREATE TABLE merry_go_round_contributions (
            id SERIAL PRIMARY KEY,
            merry_go_round_id INTEGER NOT NULL REFERENCES merry_go_round(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id),
            amount DECIMAL(10, 2) NOT NULL,
            contribution_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            cycle_number INTEGER NOT NULL, -- Which rotation cycle this contribution belongs to
            status VARCHAR(50) DEFAULT 'completed'
        )";
        $pdo->exec($sql);
        
        // Create indexes for faster queries
        $pdo->exec("CREATE INDEX idx_mgr_status ON merry_go_round(status)");
        $pdo->exec("CREATE INDEX idx_mgr_members_user ON merry_go_round_members(user_id)");
        $pdo->exec("CREATE INDEX idx_mgr_contributions_user ON merry_go_round_contributions(user_id)");
    }
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Error checking/creating merry_go_round tables: " . $e->getMessage());
}

// Initialize variables
$message = '';
$alertType = '';
$active_mgrs = [];
$my_mgrs = [];
$upcoming_payout = null;
$my_contributions = [];
$total_contributed = 0;
$total_received = 0;
$current_cycle = 1;

// Fetch active merry-go-rounds
try {
    $stmt = $pdo->prepare("
        SELECT m.*, 
               COUNT(mm.id) as member_count,
               SUM(CASE WHEN mm.received = TRUE THEN 1 ELSE 0 END) as completed_count
        FROM merry_go_round m
        LEFT JOIN merry_go_round_members mm ON m.id = mm.merry_go_round_id
        WHERE m.status = 'active'
        GROUP BY m.id
        ORDER BY m.start_date DESC
    ");
    $stmt->execute();
    $active_mgrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching active merry-go-rounds: " . $e->getMessage());
}

// Fetch merry-go-rounds the user is part of
try {
    $stmt = $pdo->prepare("
        SELECT m.*, mm.position, mm.received, mm.received_date,
               COUNT(mmall.id) as member_count,
               SUM(CASE WHEN mmall.received = TRUE THEN 1 ELSE 0 END) as completed_count
        FROM merry_go_round m
        JOIN merry_go_round_members mm ON m.id = mm.merry_go_round_id AND mm.user_id = ?
        LEFT JOIN merry_go_round_members mmall ON m.id = mmall.merry_go_round_id
        GROUP BY m.id, mm.position, mm.received, mm.received_date
        ORDER BY m.start_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $my_mgrs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching user's merry-go-rounds: " . $e->getMessage());
}

// Find upcoming payout (if any)
if (!empty($my_mgrs)) {
    foreach ($my_mgrs as $mgr) {
        if ($mgr['status'] == 'active' && !$mgr['received']) {
            // Calculate when this user will receive funds
            try {
                $stmt = $pdo->prepare("
                    SELECT 
                        CASE 
                            WHEN m.frequency = 'weekly' THEN 
                                m.start_date + ((mm.position - 1) * 7)
                            WHEN m.frequency = 'biweekly' THEN 
                                m.start_date + ((mm.position - 1) * 14)
                            WHEN m.frequency = 'monthly' THEN 
                                m.start_date + ((mm.position - 1) * 30)
                            ELSE 
                                m.start_date + ((mm.position - 1) * 7)
                        END as payout_date,
                        m.name,
                        m.amount_per_member * COUNT(mmall.id) as total_amount,
                        mm.position,
                        COUNT(mmall.id) as total_members
                    FROM merry_go_round m
                    JOIN merry_go_round_members mm ON m.id = mm.merry_go_round_id AND mm.user_id = ?
                    JOIN merry_go_round_members mmall ON m.id = mmall.merry_go_round_id
                    WHERE m.id = ?
                    GROUP BY m.id, mm.position
                ");
                $stmt->execute([$_SESSION['user_id'], $mgr['id']]);
                $payout_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($payout_info) {
                    // If this is the closest upcoming payout, save it
                    if (!$upcoming_payout || strtotime($payout_info['payout_date']) < strtotime($upcoming_payout['payout_date'])) {
                        $upcoming_payout = $payout_info;
                    }
                }
            } catch (PDOException $e) {
                error_log("Error calculating payout date: " . $e->getMessage());
            }
        }
    }
}

// Fetch user's contributions
try {
    $stmt = $pdo->prepare("
        SELECT mgc.*, m.name as mgr_name
        FROM merry_go_round_contributions mgc
        JOIN merry_go_round m ON mgc.merry_go_round_id = m.id
        WHERE mgc.user_id = ?
        ORDER BY mgc.contribution_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $my_contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching user contributions: " . $e->getMessage());
}

// Calculate total contributed
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM merry_go_round_contributions
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total_contributed = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error calculating total contributed: " . $e->getMessage());
}

// Calculate total received
try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(m.amount_per_member * (
            SELECT COUNT(*) FROM merry_go_round_members 
            WHERE merry_go_round_id = m.id
        )), 0) as total
        FROM merry_go_round m
        JOIN merry_go_round_members mm ON m.id = mm.merry_go_round_id
        WHERE mm.user_id = ? AND mm.received = TRUE
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $total_received = $stmt->fetchColumn();
} catch (PDOException $e) {
    error_log("Error calculating total received: " . $e->getMessage());
}

// Handle joining a merry-go-round
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_mgr'])) {
    $mgr_id = filter_input(INPUT_POST, 'mgr_id', FILTER_VALIDATE_INT);
    
    if (!$mgr_id) {
        $message = 'Invalid merry-go-round selection.';
        $alertType = 'danger';
    } else {
        try {
            // Check if already a member
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM merry_go_round_members WHERE merry_go_round_id = ? AND user_id = ?");
            $stmt->execute([$mgr_id, $_SESSION['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'You are already a member of this merry-go-round.';
                $alertType = 'warning';
            } else {
                // Get the next available position
                $stmt = $pdo->prepare("
                    SELECT COALESCE(MAX(position), 0) + 1 as next_position
                    FROM merry_go_round_members
                    WHERE merry_go_round_id = ?
                ");
                $stmt->execute([$mgr_id]);
                $next_position = $stmt->fetchColumn();
                
                // Add user to merry-go-round
                $stmt = $pdo->prepare("
                    INSERT INTO merry_go_round_members (merry_go_round_id, user_id, position, joined_date)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$mgr_id, $_SESSION['user_id'], $next_position]);
                
                $message = 'You have successfully joined the merry-go-round!';
                $alertType = 'success';
                
                // Redirect to avoid form resubmission
                header("Location: merry_go_round.php?success=joined");
                exit();
            }
        } catch (PDOException $e) {
            $message = 'Error joining merry-go-round: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Handle making a contribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_contribution'])) {
    $mgr_id = filter_input(INPUT_POST, 'mgr_id', FILTER_VALIDATE_INT);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $cycle = filter_input(INPUT_POST, 'cycle', FILTER_VALIDATE_INT);
    
    if (!$mgr_id || !$amount || $amount <= 0 || !$cycle) {
        $message = 'Please provide valid contribution details.';
        $alertType = 'danger';
    } else {
        try {
            // Check if already contributed for this cycle
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM merry_go_round_contributions 
                WHERE merry_go_round_id = ? AND user_id = ? AND cycle_number = ?
            ");
            $stmt->execute([$mgr_id, $_SESSION['user_id'], $cycle]);
            if ($stmt->fetchColumn() > 0) {
                $message = 'You have already contributed for this cycle.';
                $alertType = 'warning';
            } else {
                // Add contribution
                $stmt = $pdo->prepare("
                    INSERT INTO merry_go_round_contributions 
                    (merry_go_round_id, user_id, amount, cycle_number, contribution_date)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$mgr_id, $_SESSION['user_id'], $amount, $cycle]);
                
                // Check if all members have contributed for this cycle
                $stmt = $pdo->prepare("
                    SELECT 
                        (SELECT COUNT(*) FROM merry_go_round_members WHERE merry_go_round_id = ?) as total_members,
                        (SELECT COUNT(*) FROM merry_go_round_contributions WHERE merry_go_round_id = ? AND cycle_number = ?) as total_contributions
                ");
                $stmt->execute([$mgr_id, $mgr_id, $cycle]);
                $counts = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // If all members have contributed, mark the member for this cycle as having received funds
                if ($counts['total_members'] == $counts['total_contributions']) {
                    // Find which member should receive funds for this cycle
                    $stmt = $pdo->prepare("
                        SELECT user_id 
                        FROM merry_go_round_members 
                        WHERE merry_go_round_id = ? AND position = ?
                    ");
                    $stmt->execute([$mgr_id, $cycle]);
                    $recipient_id = $stmt->fetchColumn();
                    
                    if ($recipient_id) {
                        // Mark as received
                        $stmt = $pdo->prepare("
                            UPDATE merry_go_round_members 
                            SET received = TRUE, received_date = CURRENT_DATE
                            WHERE merry_go_round_id = ? AND user_id = ?
                        ");
                        $stmt->execute([$mgr_id, $recipient_id]);
                        
                        // Check if this was the last cycle
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as total, 
                                   SUM(CASE WHEN received = TRUE THEN 1 ELSE 0 END) as received
                            FROM merry_go_round_members
                            WHERE merry_go_round_id = ?
                        ");
                        $stmt->execute([$mgr_id]);
                        $completion = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // If all members have received, mark the merry-go-round as completed
                        if ($completion['total'] == $completion['received']) {
                            $stmt = $pdo->prepare("
                                UPDATE merry_go_round 
                                SET status = 'completed', end_date = CURRENT_DATE
                                WHERE id = ?
                            ");
                            $stmt->execute([$mgr_id]);
                        }
                    }
                }
                
                $message = 'Your contribution has been recorded successfully!';
                $alertType = 'success';
                
                // Redirect to avoid form resubmission
                header("Location: merry_go_round.php?success=contributed");
                exit();
            }
        } catch (PDOException $e) {
            $message = 'Error making contribution: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Handle creating a new merry-go-round (admin or authorized users only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_mgr']) && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'manager')) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING);
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $frequency = filter_input(INPUT_POST, 'frequency', FILTER_SANITIZE_STRING);
    $start_date = filter_input(INPUT_POST, 'start_date', FILTER_SANITIZE_STRING);
    
    if (!$name || !$amount || $amount <= 0 || !$frequency || !$start_date) {
        $message = 'Please fill in all required fields with valid values.';
        $alertType = 'danger';
    } else {
        try {
            // Create new merry-go-round
            $stmt = $pdo->prepare("
                INSERT INTO merry_go_round 
                (name, description, amount_per_member, frequency, start_date, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $description, $amount, $frequency, $start_date, $_SESSION['user_id']]);
            
            $new_mgr_id = $pdo->lastInsertId();
            
            // Add creator as first member
            $stmt = $pdo->prepare("
                INSERT INTO merry_go_round_members 
                (merry_go_round_id, user_id, position, joined_date)
                VALUES (?, ?, 1, NOW())
            ");
            $stmt->execute([$new_mgr_id, $_SESSION['user_id']]);
            
            $message = 'New merry-go-round created successfully!';
            $alertType = 'success';
            
            // Redirect to avoid form resubmission
            header("Location: merry_go_round.php?success=created");
            exit();
        } catch (PDOException $e) {
            $message = 'Error creating merry-go-round: ' . $e->getMessage();
            $alertType = 'danger';
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $alertType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
                switch ($_GET['success']) {
                    case 'joined':
                        echo 'You have successfully joined the merry-go-round!';
                        break;
                    case 'contributed':
                        echo 'Your contribution has been recorded successfully!';
                        break;
                    case 'created':
                        echo 'New merry-go-round created successfully!';
                        break;
                    default:
                        echo 'Operation completed successfully!';
                }
            ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Merry-Go-Round Savings</h4>
                    <?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'manager'): ?>
                        <button type="button" class="btn btn-light" data-toggle="modal" data-target="#createMGRModal">
                            <i class="fas fa-plus"></i> Create New
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-4">
                            <div class="card border-primary h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-primary">Total Contributed</h5>
                                    <div class="h2 font-weight-bold text-primary">
                                        KES <?php echo number_format($total_contributed, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="card border-success h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-success">Total Received</h5>
                                    <div class="h2 font-weight-bold text-success">
                                        KES <?php echo number_format($total_received, 2); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4 mb-4">
                            <div class="card border-info h-100">
                                <div class="card-body text-center">
                                    <h5 class="card-title text-info">Active Memberships</h5>
                                    <div class="h2 font-weight-bold text-info">
                                        <?php echo count($my_mgrs); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($upcoming_payout): ?>
                        <div class="alert alert-success mb-4">
                            <div class="d-flex align-items-center">
                                <div class="mr-3">
                                    <i class="fas fa-calendar-check fa-3x"></i>
                                </div>
                                <div>
                                    <h5 class="alert-heading">Your Next Payout</h5>
                                    <p class="mb-0">You are scheduled to receive <strong>KES <?php echo number_format($upcoming_payout['total_amount'], 2); ?></strong> on <strong><?php echo date('F j, Y', strtotime($upcoming_payout['payout_date'])); ?></strong> from <strong><?php echo htmlspecialchars($upcoming_payout['name']); ?></strong>.</p>
                                    <p class="mb-0">Your position: <?php echo $upcoming_payout['position']; ?> of <?php echo $upcoming_payout['total_members']; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <ul class="nav nav-tabs mb-4" id="mgrTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="my-mgr-tab" data-toggle="tab" href="#my-mgr" role="tab" aria-controls="my-mgr" aria-selected="true">
                                <i class="fas fa-user-circle mr-2"></i>My Memberships
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="available-mgr-tab" data-toggle="tab" href="#available-mgr" role="tab" aria-controls="available-mgr" aria-selected="false">
                                <i class="fas fa-list mr-2"></i>Available Groups
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="contributions-tab" data-toggle="tab" href="#contributions" role="tab" aria-controls="contributions" aria-selected="false">
                                <i class="fas fa-history mr-2"></i>Contribution History
                            </a>
                        </li>
                    </ul>
                    
                    <div class="tab-content" id="mgrTabsContent">
                        <!-- My Memberships Tab -->
                        <div class="tab-pane fade show active" id="my-mgr" role="tabpanel" aria-labelledby="my-mgr-tab">
                            <?php if (count($my_mgrs) > 0): ?>
                                <div class="row">
                                    <?php foreach ($my_mgrs as $mgr): ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($mgr['name']); ?></h5>
                                                    <span class="badge badge-<?php echo $mgr['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo ucfirst($mgr['status']); ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <p><?php echo nl2br(htmlspecialchars($mgr['description'])); ?></p>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-6">
                                                            <small class="text-muted">Amount per member:</small>
                                                            <div class="font-weight-bold">KES <?php echo number_format($mgr['amount_per_member'], 2); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Frequency:</small>
                                                            <div class="font-weight-bold"><?php echo ucfirst($mgr['frequency']); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-6">
                                                            <small class="text-muted">Start Date:</small>
                                                            <div class="font-weight-bold"><?php echo date('M d, Y', strtotime($mgr['start_date'])); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Your Position:</small>
                                                            <div class="font-weight-bold"><?php echo $mgr['position']; ?> of <?php echo $mgr['member_count']; ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="progress mb-3" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo ($mgr['completed_count'] / $mgr['member_count']) * 100; ?>%;" 
                                                             aria-valuenow="<?php echo $mgr['completed_count']; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="<?php echo $mgr['member_count']; ?>">
                                                            <?php echo $mgr['completed_count']; ?>/<?php echo $mgr['member_count']; ?> Completed
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if ($mgr['status'] == 'active'): ?>
                                                        <?php
                                                            // Determine current cycle
                                                            $current_cycle = $mgr['completed_count'] + 1;
                                                            if ($current_cycle > $mgr['member_count']) {
                                                                $current_cycle = $mgr['member_count'];
                                                            }
                                                            
                                                            // Check if user has already contributed for this cycle
                                                            $stmt = $pdo->prepare("
                                                                SELECT COUNT(*) 
                                                                FROM merry_go_round_contributions 
                                                                WHERE merry_go_round_id = ? AND user_id = ? AND cycle_number = ?
                                                            ");
                                                            $stmt->execute([$mgr['id'], $_SESSION['user_id'], $current_cycle]);
                                                            $already_contributed = $stmt->fetchColumn() > 0;
                                                            
                                                            // Check if this user has already received
                                                            $already_received = $mgr['received'];
                                                        ?>
                                                        
                                                        <?php if (!$already_received): ?>
                                                            <?php if (!$already_contributed): ?>
                                                                <button type="button" class="btn btn-primary btn-block" 
                                                                        data-toggle="modal" 
                                                                        data-target="#contributeModal"
                                                                        data-mgr-id="<?php echo $mgr['id']; ?>"
                                                                        data-mgr-name="<?php echo htmlspecialchars($mgr['name']); ?>"
                                                                        data-amount="<?php echo $mgr['amount_per_member']; ?>"
                                                                        data-cycle="<?php echo $current_cycle; ?>">
                                                                    <i class="fas fa-hand-holding-usd mr-2"></i>Contribute for Cycle <?php echo $current_cycle; ?>
                                                                </button>
                                                            <?php else: ?>
                                                                <div class="alert alert-success mb-0">
                                                                    <i class="fas fa-check-circle mr-2"></i>You have contributed for the current cycle.
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <div class="alert alert-info mb-0">
                                                                <i class="fas fa-info-circle mr-2"></i>You have already received your payout on <?php echo date('M d, Y', strtotime($mgr['received_date'])); ?>.
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <div class="alert alert-secondary mb-0">
                                                            <i class="fas fa-check-circle mr-2"></i>This merry-go-round has been completed.
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i>You are not currently a member of any merry-go-round groups. Check the "Available Groups" tab to join one.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Available Groups Tab -->
                        <div class="tab-pane fade" id="available-mgr" role="tabpanel" aria-labelledby="available-mgr-tab">
                            <?php if (count($active_mgrs) > 0): ?>
                                <div class="row">
                                    <?php 
                                    // Get list of merry-go-rounds the user is already part of
                                    $my_mgr_ids = array_column($my_mgrs, 'id');
                                    
                                    foreach ($active_mgrs as $mgr): 
                                        // Skip if user is already a member
                                        if (in_array($mgr['id'], $my_mgr_ids)) continue;
                                    ?>
                                        <div class="col-md-6 mb-4">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-header bg-light">
                                                    <h5 class="mb-0"><?php echo htmlspecialchars($mgr['name']); ?></h5>
                                                </div>
                                                <div class="card-body">
                                                    <p><?php echo nl2br(htmlspecialchars($mgr['description'])); ?></p>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-6">
                                                            <small class="text-muted">Amount per member:</small>
                                                            <div class="font-weight-bold">KES <?php echo number_format($mgr['amount_per_member'], 2); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Frequency:</small>
                                                            <div class="font-weight-bold"><?php echo ucfirst($mgr['frequency']); ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="row mb-3">
                                                        <div class="col-6">
                                                            <small class="text-muted">Start Date:</small>
                                                            <div class="font-weight-bold"><?php echo date('M d, Y', strtotime($mgr['start_date'])); ?></div>
                                                        </div>
                                                        <div class="col-6">
                                                            <small class="text-muted">Current Members:</small>
                                                            <div class="font-weight-bold"><?php echo $mgr['member_count']; ?></div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="progress mb-3" style="height: 20px;">
                                                        <div class="progress-bar bg-success" role="progressbar" 
                                                             style="width: <?php echo ($mgr['completed_count'] / $mgr['member_count']) * 100; ?>%;" 
                                                             aria-valuenow="<?php echo $mgr['completed_count']; ?>" 
                                                             aria-valuemin="0" 
                                                             aria-valuemax="<?php echo $mgr['member_count']; ?>">
                                                            <?php echo $mgr['completed_count']; ?>/<?php echo $mgr['member_count']; ?> Completed
                                                        </div>
                                                    </div>
                                                    
                                                    <form method="post" action="">
                                                        <input type="hidden" name="mgr_id" value="<?php echo $mgr['id']; ?>">
                                                        <button type="submit" name="join_mgr" class="btn btn-primary btn-block">
                                                            <i class="fas fa-user-plus mr-2"></i>Join This Group
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <?php 
                                // Check if all available groups are already joined by the user
                                $available_count = 0;
                                foreach ($active_mgrs as $mgr) {
                                    if (!in_array($mgr['id'], $my_mgr_ids)) {
                                        $available_count++;
                                    }
                                }
                                
                                if ($available_count == 0): 
                                ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle mr-2"></i>You have already joined all available merry-go-round groups.
                                    </div>
                                <?php endif; ?>
                                
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i>There are no active merry-go-round groups available at the moment.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Contribution History Tab -->
                        <div class="tab-pane fade" id="contributions" role="tabpanel" aria-labelledby="contributions-tab">
                            <?php if (count($my_contributions) > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Group</th>
                                                <th>Cycle</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($my_contributions as $contribution): ?>
                                                <tr>
                                                    <td><?php echo date('M d, Y', strtotime($contribution['contribution_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($contribution['mgr_name']); ?></td>
                                                    <td><?php echo $contribution['cycle_number']; ?></td>
                                                    <td>KES <?php echo number_format($contribution['amount'], 2); ?></td>
                                                    <td>
                                                        <span class="badge badge-<?php echo $contribution['status'] == 'completed' ? 'success' : 'warning'; ?>">
                                                            <?php echo ucfirst($contribution['status']); ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i>You haven't made any contributions to merry-go-round groups yet.
                                </div>
                            <?php endif; ?>
                        </div>
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
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="contributeModalLabel">Make Contribution</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <input type="hidden" name="mgr_id" id="mgr_id">
                    <input type="hidden" name="cycle" id="cycle">
                    
                    <div class="form-group">
                        <label for="mgr_name">Merry-Go-Round Group:</label>
                        <input type="text" class="form-control" id="mgr_name" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="cycle_display">Cycle Number:</label>
                        <input type="text" class="form-control" id="cycle_display" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Contribution Amount (KES):</label>
                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                        <small class="form-text text-muted">Required amount: <span id="required_amount"></span></small>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>Your contribution will be used to pay out the member whose turn it is in this cycle.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="make_contribution" class="btn btn-primary">Submit Contribution</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Create Merry-Go-Round Modal -->
<?php if ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'manager'): ?>
<div class="modal fade" id="createMGRModal" tabindex="-1" role="dialog" aria-labelledby="createMGRModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="createMGRModalLabel">Create New Merry-Go-Round</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Group Name:</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description:</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="amount">Amount per Member (KES):</label>
                        <input type="number" class="form-control" id="amount" name="amount" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="frequency">Frequency:</label>
                        <select class="form-control" id="frequency" name="frequency" required>
                            <option value="weekly">Weekly</option>
                            <option value="biweekly">Bi-weekly</option>
                            <option value="monthly">Monthly</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle mr-2"></i>You will automatically be added as the first member of this merry-go-round group.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_mgr" class="btn btn-primary">Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    $(document).ready(function() {
        // Set up contribute modal
        $('#contributeModal').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget);
            var mgrId = button.data('mgr-id');
            var mgrName = button.data('mgr-name');
            var amount = button.data('amount');
            var cycle = button.data('cycle');
            
            var modal = $(this);
            modal.find('#mgr_id').val(mgrId);
            modal.find('#mgr_name').val(mgrName);
            modal.find('#cycle').val(cycle);
            modal.find('#cycle_display').val(cycle);
            modal.find('#amount').val(amount);
            modal.find('#required_amount').text('KES ' + amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        });
        
        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();
        
        // Add animation to cards
        $('.card').addClass('animate__animated animate__fadeIn');
    });
</script>

<?php include 'includes/footer.php'; ?>