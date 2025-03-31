<?php
// Include authentication check
include_once('auth.php');

// Database connection
require_once 'config.php';
require_once 'includes/functions.php'; // This already has getMeetingStatusLabel()

// Check if meetings table exists, if not create it
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.meetings')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        // Create meetings table
        $sql = "CREATE TABLE meetings (
            id SERIAL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            meeting_date DATE NOT NULL,
            meeting_time TIME NOT NULL,
            end_time TIME,
            location VARCHAR(255) NOT NULL,
            status VARCHAR(50) DEFAULT 'upcoming',
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )";
        $pdo->exec($sql);
        
        // Create meeting_registrations table
        $sql = "CREATE TABLE meeting_registrations (
            id SERIAL PRIMARY KEY,
            meeting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            registration_date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(meeting_id, user_id)
        )";
        $pdo->exec($sql);
        
        // Create meeting_attendance table
        $sql = "CREATE TABLE meeting_attendance (
            id SERIAL PRIMARY KEY,
            meeting_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE(meeting_id, user_id)
        )";
        $pdo->exec($sql);
        
        // Create indexes for faster queries
        $pdo->exec("CREATE INDEX idx_meetings_date ON meetings(meeting_date)");
        $pdo->exec("CREATE INDEX idx_meetings_status ON meetings(status)");
    }
} catch (PDOException $e) {
    // Log the error but continue
    error_log("Error checking/creating meetings tables: " . $e->getMessage());
}

// Check if meeting_date column exists, if not use date or add it
try {
    $stmt = $pdo->prepare("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = 'meetings' AND column_name = 'meeting_date'
    ");
    $stmt->execute();
    $hasMeetingDate = $stmt->fetchColumn();
    
    if (!$hasMeetingDate) {
        // Check if date exists
        $stmt = $pdo->prepare("
            SELECT column_name 
            FROM information_schema.columns 
            WHERE table_name = 'meetings' AND column_name = 'date'
        ");
        $stmt->execute();
        $hasDate = $stmt->fetchColumn();
        
        if ($hasDate) {
            // Use date instead of meeting_date
            $meetingDateColumn = 'date';
        } else {
            // Add meeting_date column
            $pdo->exec("ALTER TABLE meetings ADD COLUMN meeting_date DATE");
            $meetingDateColumn = 'meeting_date';
        }
    } else {
        $meetingDateColumn = 'meeting_date';
    }
} catch (PDOException $e) {
    // Default to meeting_date and handle errors in queries
    $meetingDateColumn = 'meeting_date';
    error_log("Error checking meeting table columns: " . $e->getMessage());
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$search_condition = '';
if (!empty($search)) {
    $search_condition = "WHERE title LIKE :search OR location LIKE :search OR description LIKE :search";
}

// Date filter
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

if (!empty($date_from) && !empty($date_to)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE $meetingDateColumn BETWEEN :date_from AND :date_to";
    } else {
        $search_condition .= " AND $meetingDateColumn BETWEEN :date_from AND :date_to";
    }
} else if (!empty($date_from)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE $meetingDateColumn >= :date_from";
    } else {
        $search_condition .= " AND $meetingDateColumn >= :date_from";
    }
} else if (!empty($date_to)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE $meetingDateColumn <= :date_to";
    } else {
        $search_condition .= " AND $meetingDateColumn <= :date_to";
    }
}

// Status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
if (!empty($status_filter)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE status = :status";
    } else {
        $search_condition .= " AND status = :status";
    }
}

// Count total records for pagination
try {
    $count_query = "SELECT COUNT(*) as total FROM meetings $search_condition";
    $count_stmt = $pdo->prepare($count_query);

    // Bind parameters for search and filters
    if (!empty($search)) {
        $search_param = '%' . $search . '%';
        $count_stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    if (!empty($date_from)) {
        $count_stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    }
    if (!empty($date_to)) {
        $count_stmt->bindParam(':date_to', $date_to, PDO::PARAM_STR);
    }
    if (!empty($status_filter)) {
        $count_stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }

    $count_stmt->execute();
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
} catch (PDOException $e) {
    // If there's an error, set default values
    $total_records = 0;
    $total_pages = 1;
    error_log("Error counting meetings: " . $e->getMessage());
}

// Fetch meetings for current page
try {
    $query = "SELECT * FROM meetings $search_condition ORDER BY $meetingDateColumn DESC LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($query);

    // Bind parameters for search and filters
    if (!empty($search)) {
        $search_param = '%' . $search . '%';
        $stmt->bindParam(':search', $search_param, PDO::PARAM_STR);
    }
    if (!empty($date_from)) {
        $stmt->bindParam(':date_from', $date_from, PDO::PARAM_STR);
    }
    if (!empty($date_to)) {
        $stmt->bindParam(':date_to', $date_to, PDO::PARAM_STR);
    }
    if (!empty($status_filter)) {
        $stmt->bindParam(':status', $status_filter, PDO::PARAM_STR);
    }

    $stmt->bindParam(':limit', $records_per_page, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If there's an error, set empty array
    $result = [];
    error_log("Error fetching meetings: " . $e->getMessage());
}

// Handle meeting attendance
if (isset($_POST['mark_attendance'])) {
    $meeting_id = (int)$_POST['meeting_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        // Check if already marked
        $check_stmt = $pdo->prepare("SELECT * FROM meeting_attendance WHERE meeting_id = ? AND user_id = ?");
        $check_stmt->execute([$meeting_id, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $_SESSION['error'] = "You have already marked your attendance for this meeting.";
        } else {
            // Insert attendance record
            $insert_stmt = $pdo->prepare("INSERT INTO meeting_attendance (meeting_id, user_id, timestamp) VALUES (?, ?, NOW())");
            if ($insert_stmt->execute([$meeting_id, $user_id])) {
                // Log the action
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                $log_stmt->execute([$user_id, "Marked attendance for meeting ID: $meeting_id", $ip]);
                
                $_SESSION['success'] = "Your attendance has been recorded successfully.";
            } else {
                $_SESSION['error'] = "Error recording attendance.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Error marking attendance: " . $e->getMessage());
    }
    
    header('Location: meetings.php');
    exit;
}

// Handle meeting registration
if (isset($_POST['register_meeting'])) {
    $meeting_id = (int)$_POST['meeting_id'];
    $user_id = $_SESSION['user_id'];
    
    try {
        // Check if already registered
        $check_stmt = $pdo->prepare("SELECT * FROM meeting_registrations WHERE meeting_id = ? AND user_id = ?");
        $check_stmt->execute([$meeting_id, $user_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $_SESSION['error'] = "You have already registered for this meeting.";
        } else {
            // Insert registration record
            $insert_stmt = $pdo->prepare("INSERT INTO meeting_registrations (meeting_id, user_id, registration_date) VALUES (?, ?, NOW())");
            if ($insert_stmt->execute([$meeting_id, $user_id])) {
                // Log the action
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
                $log_stmt->execute([$user_id, "Registered for meeting ID: $meeting_id", $ip]);
                
                $_SESSION['success'] = "You have successfully registered for the meeting.";
            } else {
                $_SESSION['error'] = "Error registering for meeting.";
            }
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Database error: " . $e->getMessage();
        error_log("Error registering for meeting: " . $e->getMessage());
    }
    
    header('Location: meetings.php');
    exit;
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4>Meetings & Events</h4>
                    <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'manager')): ?>
                        <a href="admin/meeting_add.php" class="btn btn-light">
                            <i class="fas fa-plus"></i> Schedule New Meeting
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <!-- Rest of the code remains the same -->

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4>Meetings & Events</h4>
                    <?php if (isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'manager')): ?>
                        <a href="admin/meeting_add.php" class="btn btn-light">
                            <i class="fas fa-plus"></i> Schedule New Meeting
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                    <?php endif; ?>
                    
                    <!-- Search and Filter Form -->
                    <form method="GET" class="mb-4">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control" placeholder="Search meetings..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-2">
                                <input type="date" name="date_from" class="form-control" placeholder="From Date" value="<?php echo $date_from; ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <input type="date" name="date_to" class="form-control" placeholder="To Date" value="<?php echo $date_to; ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <select name="status" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Status</option>
                                    <option value="upcoming" <?php echo ($status_filter == 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="ongoing" <?php echo ($status_filter == 'ongoing') ? 'selected' : ''; ?>>Ongoing</option>
                                    <option value="completed" <?php echo ($status_filter == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo ($status_filter == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="urgent" <?php echo ($status_filter == 'urgent') ? 'selected' : ''; ?>>Urgent</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Meetings List -->
                    <?php if (count($result) > 0): ?>
                        <div class="row">
                            <?php foreach ($result as $meeting): ?>
                                <?php 
                                    $statusInfo = getMeetingStatusLabel($meeting['status']);
                                    $meetingDate = isset($meeting[$meetingDateColumn]) ? $meeting[$meetingDateColumn] : $meeting['created_at'];
                                ?>
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100 shadow-sm">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($meeting['title']); ?></h5>
                                            <span class="badge badge-<?php echo $statusInfo['class']; ?>">
                                                <?php echo $statusInfo['label']; ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <div class="row mb-3">
                                                <div class="col-md-4 text-center">
                                                    <div class="meeting-date-box p-2 rounded">
                                                        <div class="month"><?php echo date('M', strtotime($meetingDate)); ?></div>
                                                        <div class="day"><?php echo date('d', strtotime($meetingDate)); ?></div>
                                                        <div class="year"><?php echo date('Y', strtotime($meetingDate)); ?></div>
                                                    </div>
                                                </div>
                                                <div class="col-md-8">
                                                    <p><i class="fas fa-clock mr-2"></i> <?php echo isset($meeting['meeting_time']) ? date('h:i A', strtotime($meeting['meeting_time'])) : 'Time not set'; ?></p>
                                                    <p><i class="fas fa-map-marker-alt mr-2"></i> <?php echo htmlspecialchars($meeting['location'] ?? 'Location not set'); ?></p>
                                                </div>
                                            </div>
                                            
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($meeting['description'] ?? '', 0, 150))); ?>
                                                <?php if (isset($meeting['description']) && strlen($meeting['description']) > 150): ?>...<?php endif; ?>
                                            </p>
                                            
                                            <div class="d-flex justify-content-between mt-3">
                                                <a href="meeting_details.php?id=<?php echo $meeting['id']; ?>" class="btn btn-info btn-sm">
                                                    <i class="fas fa-info-circle"></i> Details
                                                </a>
                                                
                                                <?php if ($meeting['status'] == 'upcoming'): ?>
                                                    <form method="post" action="">
                                                        <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                                        <button type="submit" name="register_meeting" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-calendar-check"></i> Register
                                                        </button>
                                                    </form>
                                                <?php elseif ($meeting['status'] == 'ongoing'): ?>
                                                    <form method="post" action="">
                                                        <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                                        <button type="submit" name="mark_attendance" class="btn btn-success btn-sm">
                                                            <i class="fas fa-check"></i> Mark Attendance
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Meetings pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=1<?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">&laquo; First</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">Previous</a>
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
                                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">Next</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : ''; ?><?php echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?>">Last &raquo;</a>
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
                            <i class="fas fa-info-circle mr-2"></i> No meetings found. 
                            <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || !empty($status_filter)): ?>
                                Please try different search criteria.
                                <a href="meetings.php" class="alert-link">Clear all filters</a>
                            <?php else: ?>
                                Check back later for upcoming meetings.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .meeting-date-box {
        background-color: #4e73df;
        color: white;
        border-radius: 5px;
    }
    
    .meeting-date-box .month {
        font-size: 14px;
        font-weight: bold;
        text-transform: uppercase;
    }
    
    .meeting-date-box .day {
        font-size: 24px;
        font-weight: bold;
    }
    
    .meeting-date-box .year {
        font-size: 14px;
    }
    
    .card-header .badge {
        font-size: 85%;
    }
</style>

<?php include 'includes/footer.php'; ?>