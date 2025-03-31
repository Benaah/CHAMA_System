<?php
// Include authentication check
include_once('auth.php');

// Database connection
require_once 'config.php';

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
        $search_condition = "WHERE meeting_date BETWEEN :date_from AND :date_to";
    } else {
        $search_condition .= " AND meeting_date BETWEEN :date_from AND :date_to";
    }
} else if (!empty($date_from)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE meeting_date >= :date_from";
    } else {
        $search_condition .= " AND meeting_date >= :date_from";
    }
} else if (!empty($date_to)) {
    if (empty($search_condition)) {
        $search_condition = "WHERE meeting_date <= :date_to";
    } else {
        $search_condition .= " AND meeting_date <= :date_to";
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

// Fetch meetings for current page
$query = "SELECT * FROM meetings $search_condition ORDER BY meeting_date DESC LIMIT :limit OFFSET :offset";
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

// Handle meeting attendance
if (isset($_POST['mark_attendance'])) {
    $meeting_id = (int)$_POST['meeting_id'];
    $user_id = $_SESSION['user_id'];
    
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
    
    header('Location: meetings.php');
    exit;
}

// Handle meeting registration
if (isset($_POST['register_meeting'])) {
    $meeting_id = (int)$_POST['meeting_id'];
    $user_id = $_SESSION['user_id'];
    
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
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <?php if (!empty($search) || !empty($date_from) || !empty($date_to) || !empty($status_filter)): ?>
                                    <a href="meetings.php" class="btn btn-secondary w-100">Clear Filters</a>
                                <?php else: ?>
                                    <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                    
                    <!-- Upcoming Meetings Section -->
                    <div class="upcoming-meetings mb-4">
                        <h5 class="border-bottom pb-2">Upcoming Meetings</h5>
                        
                        <?php
                        // Fetch upcoming meetings
                        $upcoming_stmt = $pdo->prepare("SELECT * FROM meetings WHERE meeting_date >= CURRENT_DATE AND status != 'cancelled' ORDER BY meeting_date ASC LIMIT 3");
                        $upcoming_stmt->execute();
                        $upcoming_result = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($upcoming_result) > 0):
                        ?>
                            <div class="row">
                                <?php foreach ($upcoming_result as $upcoming): ?>
                                    <div class="col-md-4 mb-3">
                                        <div class="card h-100 <?php echo ($upcoming['status'] == 'urgent') ? 'border-danger' : ''; ?>">
                                            <div class="card-header bg-light">
                                                <h5 class="card-title mb-0"><?php echo htmlspecialchars($upcoming['title']); ?></h5>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text">
                                                    <strong>Date:</strong> <?php echo date('l, F j, Y', strtotime($upcoming['meeting_date'])); ?><br>
                                                    <strong>Time:</strong> <?php echo date('h:i A', strtotime($upcoming['meeting_time'])); ?> - 
                                                                        <?php echo !empty($upcoming['end_time']) ? date('h:i A', strtotime($upcoming['end_time'])) : 'TBD'; ?><br>
                                                    <strong>Location:</strong> <?php echo htmlspecialchars($upcoming['location']); ?>
                                                </p>
                                                <p class="card-text"><?php echo nl2br(htmlspecialchars(substr($upcoming['description'] ?? '', 0, 100))); ?>...</p>
                                            </div>
                                            <div class="card-footer bg-white">
                                                <div class="d-flex justify-content-between">
                                                    <a href="meeting_details.php?id=<?php echo $upcoming['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-info-circle"></i> Details
                                                    </a>
                                                    
                                                    <?php
                                                    // Check if user has registered
                                                    $user_id = $_SESSION['user_id'];
                                                    $reg_check = $pdo->prepare("SELECT * FROM meeting_registrations WHERE meeting_id = ? AND user_id = ?");
                                                    $reg_check->execute([$upcoming['id'], $user_id]);
                                                    $is_registered = $reg_check->rowCount() > 0;
                                                    
                                                    if (!$is_registered):
                                                    ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="meeting_id" value="<?php echo $upcoming['id']; ?>">
                                                            <button type="submit" name="register_meeting" class="btn btn-sm btn-success">
                                                                <i class="fas fa-user-check"></i> Register
                                                            </button>
                                                        </form>
                                                    <?php elseif ($is_registered): ?>
                                                        <span class="badge bg-success">Registered</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mb-4">
                                <a href="meetings.php?status=upcoming" class="btn btn-outline-primary">View All Upcoming Meetings</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">No upcoming meetings scheduled at this time.</div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- All Meetings List -->
                    <h5 class="border-bottom pb-2">All Meetings</h5>
                    
                    <?php if (count($result) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="bg-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($result as $meeting): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($meeting['title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($meeting['meeting_date'])); ?></td>
                                            <td>
                                                <?php echo date('h:i A', strtotime($meeting['meeting_time'])); ?> - 
                                                <?php echo !empty($meeting['end_time']) ? date('h:i A', strtotime($meeting['end_time'])) : 'TBD'; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($meeting['location']); ?></td>
                                            <td>
                                                <?php 
                                                    $statusInfo = getMeetingStatusLabel($meeting['status']);
                                                ?>
                                                <span class="badge badge-<?php echo $statusInfo['class']; ?>">
                                                    <?php echo $statusInfo['label']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="meeting_details.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                
                                                <?php if ($meeting['status'] == 'upcoming' || $meeting['status'] == 'ongoing'): ?>
                                                    <?php
                                                    // Check if user has registered
                                                    $user_id = $_SESSION['user_id'];
                                                    $reg_check = $pdo->prepare("SELECT * FROM meeting_registrations WHERE meeting_id = ? AND user_id = ?");
                                                    $reg_check->execute([$meeting['id'], $user_id]);
                                                    $is_registered = $reg_check->rowCount() > 0;
                                                    
                                                    if (!$is_registered):
                                                    ?>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                                            <button type="submit" name="register_meeting" class="btn btn-sm btn-success">
                                                                <i class="fas fa-user-check"></i> Register
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($meeting['status'] == 'ongoing'): ?>
                                                        <?php
                                                        // Check if user has marked attendance
                                                        $att_check = $pdo->prepare("SELECT * FROM meeting_attendance WHERE meeting_id = ? AND user_id = ?");
                                                        $att_check->execute([$meeting['id'], $user_id]);
                                                        $has_attended = $att_check->rowCount() > 0;
                                                        
                                                        if (!$has_attended):
                                                        ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="meeting_id" value="<?php echo $meeting['id']; ?>">
                                                                <button type="submit" name="mark_attendance" class="btn btn-sm btn-warning">
                                                                    <i class="fas fa-clipboard-check"></i> Mark Attendance
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Meetings pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="meetings.php?page=1<?php 
                                                echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : '';
                                            ?>">&laquo; First</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="meetings.php?page=<?php echo $page - 1; ?><?php 
                                                echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : '';
                                            ?>">Previous</a>
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
                                            <a class="page-link" href="meetings.php?page=<?php echo $i; ?><?php 
                                                echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : '';
                                            ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="meetings.php?page=<?php echo $page + 1; ?><?php 
                                                echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : '';
                                            ?>">Next</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="meetings.php?page=<?php echo $total_pages; ?><?php 
                                                echo !empty($search) ? '&search=' . urlencode($search) : ''; 
                                                echo !empty($date_from) ? '&date_from=' . urlencode($date_from) : '';
                                                echo !empty($date_to) ? '&date_to=' . urlencode($date_to) : '';
                                                echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : '';
                                            ?>">Last &raquo;</a>
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
                            <p>No meetings found matching your criteria.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>