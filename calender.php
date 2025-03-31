<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // This will redirect to login if not authenticated

// Get user information
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get current month and year
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Validate month and year
if ($month < 1 || $month > 12) {
    $month = date('m');
}
if ($year < 2000 || $year > 2100) {
    $year = date('Y');
}

// Calculate previous and next month/year
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Get first day of the month
$firstDayOfMonth = mktime(0, 0, 0, $month, 1, $year);
$numberDays = date('t', $firstDayOfMonth);
$dateComponents = getdate($firstDayOfMonth);
$monthName = $dateComponents['month'];
$dayOfWeek = $dateComponents['wday']; // 0 for Sunday, 6 for Saturday

// Get events for the current month
$events = [];

// Get meetings
$stmt = $pdo->prepare("
    SELECT m.id, m.title, m.date, m.time, m.location, m.description, 'meeting' as event_type,
           CASE WHEN ma.user_id IS NOT NULL THEN true ELSE false END as attending
    FROM meetings m
    LEFT JOIN meeting_attendees ma ON m.id = ma.meeting_id AND ma.user_id = ?
    WHERE EXTRACT(YEAR FROM m.date) = ? AND EXTRACT(MONTH FROM m.date) = ?
");
$stmt->execute([$_SESSION['user_id'], $year, $month]);
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($meetings as $meeting) {
    $day = intval(date('j', strtotime($meeting['date'])));
    if (!isset($events[$day])) {
        $events[$day] = [];
    }
    $events[$day][] = $meeting;
}

// Get contribution deadlines
$stmt = $pdo->prepare("
    SELECT c.id, 'Contribution Due' as title, c.due_date as date, NULL as time, 
           NULL as location, CONCAT('Amount: KES ', c.amount) as description, 
           'contribution' as event_type,
           CASE WHEN EXISTS (
               SELECT 1 FROM contributions 
               WHERE user_id = ? AND contribution_date <= c.due_date
               AND contribution_date >= DATE_TRUNC('month', c.due_date)
           ) THEN true ELSE false END as completed
    FROM contribution_schedules c
    WHERE EXTRACT(YEAR FROM c.due_date) = ? AND EXTRACT(MONTH FROM c.due_date) = ?
");
$stmt->execute([$_SESSION['user_id'], $year, $month]);
$contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($contributions as $contribution) {
    $day = intval(date('j', strtotime($contribution['date'])));
    if (!isset($events[$day])) {
        $events[$day] = [];
    }
    $events[$day][] = $contribution;
}

// Get loan repayment deadlines
$stmt = $pdo->prepare("
    SELECT l.id, 'Loan Repayment Due' as title, l.next_payment_date as date, NULL as time,
           NULL as location, CONCAT('Amount: KES ', l.monthly_payment) as description,
           'loan' as event_type,
           CASE WHEN EXISTS (
               SELECT 1 FROM loan_repayments 
               WHERE loan_id = l.id AND payment_date <= l.next_payment_date
               AND payment_date >= DATE_TRUNC('month', l.next_payment_date)
           ) THEN true ELSE false END as completed
    FROM loans l
    WHERE l.user_id = ? AND l.status = 'approved'
    AND EXTRACT(YEAR FROM l.next_payment_date) = ? AND EXTRACT(MONTH FROM l.next_payment_date) = ?
");
$stmt->execute([$_SESSION['user_id'], $year, $month]);
$loanRepayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($loanRepayments as $repayment) {
    $day = intval(date('j', strtotime($repayment['date'])));
    if (!isset($events[$day])) {
        $events[$day] = [];
    }
    $events[$day][] = $repayment;
}

// Get personal reminders
$stmt = $pdo->prepare("
    SELECT id, title, reminder_date as date, reminder_time as time, 
           NULL as location, notes as description, 'reminder' as event_type,
           completed
    FROM personal_reminders
    WHERE user_id = ?
    AND EXTRACT(YEAR FROM reminder_date) = ? AND EXTRACT(MONTH FROM reminder_date) = ?
");
$stmt->execute([$_SESSION['user_id'], $year, $month]);
$reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($reminders as $reminder) {
    $day = intval(date('j', strtotime($reminder['date'])));
    if (!isset($events[$day])) {
        $events[$day] = [];
    }
    $events[$day][] = $reminder;
}

// Handle adding a new personal reminder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_reminder'])) {
    $title = filter_input(INPUT_POST, 'title', FILTER_SANITIZE_STRING);
    $reminderDate = filter_input(INPUT_POST, 'reminder_date', FILTER_SANITIZE_STRING);
    $reminderTime = filter_input(INPUT_POST, 'reminder_time', FILTER_SANITIZE_STRING);
    $notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
    
    if (empty($title) || empty($reminderDate)) {
        $error = "Title and date are required.";
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO personal_reminders (user_id, title, reminder_date, reminder_time, notes, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $title, $reminderDate, $reminderTime, $notes]);
            
            // Redirect to avoid form resubmission
            header("Location: calendar.php?month=$month&year=$year&success=reminder_added");
            exit();
        } catch (PDOException $e) {
            $error = "Error adding reminder: " . $e->getMessage();
        }
    }
}

// Handle marking a reminder as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_reminder'])) {
    $reminderId = filter_input(INPUT_POST, 'reminder_id', FILTER_VALIDATE_INT);
    
    if (!$reminderId) {
        $error = "Invalid reminder ID.";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE personal_reminders 
                SET completed = TRUE, completed_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$reminderId, $_SESSION['user_id']]);
            
            // Redirect to avoid form resubmission
            header("Location: calendar.php?month=$month&year=$year&success=reminder_completed");
            exit();
        } catch (PDOException $e) {
            $error = "Error updating reminder: " . $e->getMessage();
        }
    }
}

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $error; ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php 
                switch ($_GET['success']) {
                    case 'reminder_added':
                        echo 'Reminder added successfully!';
                        break;
                    case 'reminder_completed':
                        echo 'Reminder marked as completed!';
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
            <div class="card shadow">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">CHAMA Calendar</h4>
                    <button type="button" class="btn btn-light" data-toggle="modal" data-target="#addReminderModal">
                        <i class="fas fa-plus"></i> Add Reminder
                    </button>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="btn-group">
                                <a href="calendar.php?month=<?php echo $prevMonth; ?>&year=<?php echo $prevYear; ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-chevron-left"></i> Previous Month
                                </a>
                                <a href="calendar.php" class="btn btn-outline-secondary">Today</a>
                                <a href="calendar.php?month=<?php echo $nextMonth; ?>&year=<?php echo $nextYear; ?>" class="btn btn-outline-primary">
                                    Next Month <i class="fas fa-chevron-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <h3><?php echo $monthName . ' ' . $year; ?></h3>
                        </div>
                        <div class="col-md-4">
                            <div class="float-right">
                                <div class="dropdown">
                                    <button class="btn btn-outline-primary dropdown-toggle" type="button" id="exportDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                        <i class="fas fa-download"></i> Export
                                    </button>
                                    <div class="dropdown-menu" aria-labelledby="exportDropdown">
                                        <a class="dropdown-item" href="#" id="exportICS">
                                            <i class="far fa-calendar-alt mr-2"></i> Export as iCalendar (.ics)
                                        </a>
                                        <a class="dropdown-item" href="#" id="exportPDF">
                                            <i class="far fa-file-pdf mr-2"></i> Export as PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered calendar-table">
                            <thead>
                                <tr>
                                    <th>Sunday</th>
                                    <th>Monday</th>
                                    <th>Tuesday</th>
                                    <th>Wednesday</th>
                                    <th>Thursday</th>
                                    <th>Friday</th>
                                    <th>Saturday</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <?php
                                    // Fill in blank cells for the first week
                                    for ($i = 0; $i < $dayOfWeek; $i++) {
                                        echo "<td class='calendar-day empty'></td>";
                                    }
                                    
                                    // Fill in days of the month
                                    $currentDay = 1;
                                    $currentDayOfWeek = $dayOfWeek;
                                    
                                    while ($currentDay <= $numberDays) {
                                        // If we've reached the end of the week, start a new row
                                        if ($currentDayOfWeek == 7) {
                                            echo "</tr><tr>";
                                            $currentDayOfWeek = 0;
                                        }
                                        
                                        $today = ($currentDay == date('j') && $month == date('m') && $year == date('Y'));
                                        $class = $today ? 'calendar-day today' : 'calendar-day';
                                        
                                        echo "<td class='$class'>";
                                        echo "<div class='day-number'>$currentDay</div>";
                                        
                                        // Display events for this day
                                        if (isset($events[$currentDay]) && !empty($events[$currentDay])) {
                                            echo "<div class='events-container'>";
                                            foreach ($events[$currentDay] as $event) {
                                                $eventClass = '';
                                                $icon = '';
                                                
                                                switch ($event['event_type']) {
                                                    case 'meeting':
                                                        $eventClass = 'event-meeting';
                                                        $icon = 'fas fa-users';
                                                        break;
                                                    case 'contribution':
                                                        $eventClass = 'event-contribution';
                                                        $icon = 'fas fa-hand-holding-usd';
                                                        break;
                                                    case 'loan':
                                                        $eventClass = 'event-loan';
                                                        $icon = 'fas fa-money-bill-wave';
                                                        break;
                                                    case 'reminder':
                                                        $eventClass = 'event-reminder';
                                                        $icon = 'fas fa-bell';
                                                        break;
                                                }
                                                
                                                if (isset($event['completed']) && $event['completed']) {
                                                    $eventClass .= ' event-completed';
                                                } else if (isset($event['attending']) && $event['attending']) {
                                                    $eventClass .= ' event-attending';
                                                }
                                                
                                                echo "<div class='event $eventClass' data-toggle='popover' data-placement='top' 
                                                        data-title='" . htmlspecialchars($event['title']) . "'
                                                      data-content='" . htmlspecialchars($event['description'] ?? '') . "'>
                                                    <i class='$icon mr-1'></i> " . htmlspecialchars($event['title']);
                                                
                                                if ($event['event_type'] === 'reminder' && !$event['completed']) {
                                                    echo "<form method='post' class='d-inline ml-1'>
                                                            <input type='hidden' name='reminder_id' value='" . $event['id'] . "'>
                                                            <button type='submit' name='complete_reminder' class='btn btn-sm btn-link p-0 text-success'>
                                                                <i class='fas fa-check'></i>
                                                            </button>
                                                          </form>";
                                                }
                                                
                                                echo "</div>";
                                            }
                                            echo "</div>";
                                        }
                                        
                                        echo "</td>";
                                        
                                        $currentDay++;
                                        $currentDayOfWeek++;
                                    }
                                    
                                    // Fill in blank cells for the last week
                                    while ($currentDayOfWeek < 7) {
                                        echo "<td class='calendar-day empty'></td>";
                                        $currentDayOfWeek++;
                                    }
                                    ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        <h5>Legend</h5>
                        <div class="d-flex flex-wrap">
                            <div class="mr-4 mb-2">
                                <span class="legend-item event-meeting"><i class="fas fa-users"></i></span>
                                Meeting
                            </div>
                            <div class="mr-4 mb-2">
                                <span class="legend-item event-contribution"><i class="fas fa-hand-holding-usd"></i></span>
                                Contribution Due
                            </div>
                            <div class="mr-4 mb-2">
                                <span class="legend-item event-loan"><i class="fas fa-money-bill-wave"></i></span>
                                Loan Repayment
                            </div>
                            <div class="mr-4 mb-2">
                                <span class="legend-item event-reminder"><i class="fas fa-bell"></i></span>
                                Personal Reminder
                            </div>
                            <div class="mr-4 mb-2">
                                <span class="legend-item event-completed"><i class="fas fa-check"></i></span>
                                Completed
                            </div>
                            <div class="mr-4 mb-2">
                                <span class="legend-item event-attending"><i class="fas fa-check-circle"></i></span>
                                Attending
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Upcoming Events</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get all upcoming events for the next 30 days
                    $upcomingEvents = [];
                    
                    // Get upcoming meetings
                    $stmt = $pdo->prepare("
                        SELECT m.id, m.title, m.date, m.time, m.location, m.description, 'meeting' as event_type,
                               CASE WHEN ma.user_id IS NOT NULL THEN true ELSE false END as attending
                        FROM meetings m
                        LEFT JOIN meeting_attendees ma ON m.id = ma.meeting_id AND ma.user_id = ?
                        WHERE m.date >= CURRENT_DATE AND m.date <= CURRENT_DATE + INTERVAL '30 days'
                        ORDER BY m.date, m.time
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $upcomingMeetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $upcomingEvents = array_merge($upcomingEvents, $upcomingMeetings);
                    
                    // Get upcoming contribution deadlines
                    $stmt = $pdo->prepare("
                        SELECT c.id, 'Contribution Due' as title, c.due_date as date, NULL as time, 
                               NULL as location, CONCAT('Amount: KES ', c.amount) as description, 
                               'contribution' as event_type,
                               CASE WHEN EXISTS (
                                   SELECT 1 FROM contributions 
                                   WHERE user_id = ? AND contribution_date <= c.due_date
                                   AND contribution_date >= DATE_TRUNC('month', c.due_date)
                               ) THEN true ELSE false END as completed
                        FROM contribution_schedules c
                        WHERE c.due_date >= CURRENT_DATE AND c.due_date <= CURRENT_DATE + INTERVAL '30 days'
                        ORDER BY c.due_date
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $upcomingContributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $upcomingEvents = array_merge($upcomingEvents, $upcomingContributions);
                    
                    // Get upcoming loan repayments
                    $stmt = $pdo->prepare("
                        SELECT l.id, 'Loan Repayment Due' as title, l.next_payment_date as date, NULL as time,
                               NULL as location, CONCAT('Amount: KES ', l.monthly_payment) as description,
                               'loan' as event_type,
                               CASE WHEN EXISTS (
                                   SELECT 1 FROM loan_repayments 
                                   WHERE loan_id = l.id AND payment_date <= l.next_payment_date
                                   AND payment_date >= DATE_TRUNC('month', l.next_payment_date)
                               ) THEN true ELSE false END as completed
                        FROM loans l
                        WHERE l.user_id = ? AND l.status = 'approved'
                        AND l.next_payment_date >= CURRENT_DATE AND l.next_payment_date <= CURRENT_DATE + INTERVAL '30 days'
                        ORDER BY l.next_payment_date
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $upcomingRepayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $upcomingEvents = array_merge($upcomingEvents, $upcomingRepayments);
                    
                    // Get upcoming personal reminders
                    $stmt = $pdo->prepare("
                        SELECT id, title, reminder_date as date, reminder_time as time, 
                               NULL as location, notes as description, 'reminder' as event_type,
                               completed
                        FROM personal_reminders
                        WHERE user_id = ? AND completed = FALSE
                        AND reminder_date >= CURRENT_DATE AND reminder_date <= CURRENT_DATE + INTERVAL '30 days'
                        ORDER BY reminder_date, reminder_time
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $upcomingReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    $upcomingEvents = array_merge($upcomingEvents, $upcomingReminders);
                    
                    // Sort all events by date
                    usort($upcomingEvents, function($a, $b) {
                        $dateA = strtotime($a['date'] . ' ' . ($a['time'] ?? '00:00:00'));
                        $dateB = strtotime($b['date'] . ' ' . ($b['time'] ?? '00:00:00'));
                        return $dateA - $dateB;
                    });
                    
                    if (!empty($upcomingEvents)):
                    ?>
                        <div class="list-group">
                            <?php foreach ($upcomingEvents as $event): ?>
                                <?php
                                $eventClass = '';
                                $icon = '';
                                
                                switch ($event['event_type']) {
                                    case 'meeting':
                                        $eventClass = 'list-group-item-primary';
                                        $icon = 'fas fa-users';
                                        break;
                                    case 'contribution':
                                        $eventClass = 'list-group-item-success';
                                        $icon = 'fas fa-hand-holding-usd';
                                        break;
                                    case 'loan':
                                        $eventClass = 'list-group-item-danger';
                                        $icon = 'fas fa-money-bill-wave';
                                        break;
                                    case 'reminder':
                                        $eventClass = 'list-group-item-warning';
                                        $icon = 'fas fa-bell';
                                        break;
                                }
                                
                                if (isset($event['completed']) && $event['completed']) {
                                    $eventClass .= ' event-completed-item';
                                }
                                ?>
                                <div class="list-group-item <?php echo $eventClass; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1">
                                            <i class="<?php echo $icon; ?> mr-2"></i>
                                            <?php echo htmlspecialchars($event['title']); ?>
                                        </h5>
                                        <small>
                                            <?php 
                                                echo date('M d, Y', strtotime($event['date']));
                                                if (!empty($event['time'])) {
                                                    echo ' at ' . date('h:i A', strtotime($event['time']));
                                                }
                                            ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($event['description'])): ?>
                                        <p class="mb-1"><?php echo htmlspecialchars($event['description']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($event['location'])): ?>
                                        <small><i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($event['location']); ?></small>
                                    <?php endif; ?>
                                    
                                    <?php if ($event['event_type'] === 'reminder' && !$event['completed']): ?>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="reminder_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" name="complete_reminder" class="btn btn-sm btn-success">
                                                <i class="fas fa-check mr-1"></i> Mark as Completed
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> No upcoming events in the next 30 days.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">My Reminders</h5>
                </div>
                <div class="card-body">
                    <?php
                    // Get all personal reminders
                    $stmt = $pdo->prepare("
                        SELECT id, title, reminder_date, reminder_time, notes, completed, completed_at, created_at
                        FROM personal_reminders
                        WHERE user_id = ?
                        ORDER BY completed, reminder_date DESC
                        LIMIT 10
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $allReminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (!empty($allReminders)):
                    ?>
                        <div class="list-group">
                            <?php foreach ($allReminders as $reminder): ?>
                                <div class="list-group-item <?php echo $reminder['completed'] ? 'list-group-item-light' : ''; ?>">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h5 class="mb-1 <?php echo $reminder['completed'] ? 'text-muted' : ''; ?>">
                                            <?php if ($reminder['completed']): ?>
                                                <i class="fas fa-check-circle mr-2 text-success"></i>
                                            <?php else: ?>
                                                <i class="fas fa-bell mr-2 text-warning"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($reminder['title']); ?>
                                        </h5>
                                        <small>
                                            <?php 
                                                echo date('M d, Y', strtotime($reminder['reminder_date']));
                                                if (!empty($reminder['reminder_time'])) {
                                                    echo ' at ' . date('h:i A', strtotime($reminder['reminder_time']));
                                                }
                                            ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($reminder['notes'])): ?>
                                        <p class="mb-1 <?php echo $reminder['completed'] ? 'text-muted' : ''; ?>">
                                            <?php echo htmlspecialchars($reminder['notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($reminder['completed']): ?>
                                        <small class="text-muted">
                                            Completed on <?php echo date('M d, Y h:i A', strtotime($reminder['completed_at'])); ?>
                                        </small>
                                    <?php else: ?>
                                        <form method="post" class="mt-2">
                                            <input type="hidden" name="reminder_id" value="<?php echo $reminder['id']; ?>">
                                            <button type="submit" name="complete_reminder" class="btn btn-sm btn-success">
                                                <i class="fas fa-check mr-1"></i> Mark as Completed
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="text-center mt-3">
                            <a href="reminders.php" class="btn btn-outline-primary">View All Reminders</a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle mr-2"></i> You don't have any reminders yet.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Reminder Modal -->
<div class="modal fade" id="addReminderModal" tabindex="-1" role="dialog" aria-labelledby="addReminderModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addReminderModalLabel">Add Personal Reminder</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reminder_date">Date</label>
                        <input type="date" class="form-control" id="reminder_date" name="reminder_date" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="reminder_time">Time (Optional)</label>
                        <input type="time" class="form-control" id="reminder_time" name="reminder_time">
                    </div>
                    
                    <div class="form-group">
                        <label for="notes">Notes (Optional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_reminder" class="btn btn-primary">Add Reminder</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CSS for Calendar -->
<style>
    .calendar-table {
        table-layout: fixed;
    }
    
    .calendar-day {
        height: 120px;
        padding: 5px !important;
        vertical-align: top;
        position: relative;
    }
    
    .calendar-day.empty {
        background-color: #f8f9fa;
    }
    
    .calendar-day.today {
        background-color: rgba(78, 115, 223, 0.1);
    }
    
    .day-number {
        font-weight: bold;
        margin-bottom: 5px;
    }
    
    .today .day-number {
        background-color: #4e73df;
        color: white;
        border-radius: 50%;
        width: 25px;
        height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .events-container {
        overflow-y: auto;
        max-height: 85px;
    }
    
    .event {
        font-size: 0.8rem;
        padding: 2px 4px;
        margin-bottom: 2px;
        border-radius: 3px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
    }
    
    .event-meeting {
        background-color: rgba(78, 115, 223, 0.2);
        border-left: 3px solid #4e73df;
    }
    
    .event-contribution {
        background-color: rgba(28, 200, 138, 0.2);
        border-left: 3px solid #1cc88a;
    }
    
    .event-loan {
        background-color: rgba(231, 74, 59, 0.2);
        border-left: 3px solid #e74a3b;
    }
    
    .event-reminder {
        background-color: rgba(246, 194, 62, 0.2);
        border-left: 3px solid #f6c23e;
    }
    
    .event-completed {
        text-decoration: line-through;
        opacity: 0.7;
    }
    
    .event-attending {
        font-weight: bold;
    }
    
    .event-completed-item {
        opacity: 0.7;
    }
    
    .legend-item {
        display: inline-block;
        width: 20px;
        height: 20px;
        margin-right: 5px;
        vertical-align: middle;
        border-radius: 3px;
    }
    
    .legend-item.event-meeting {
        background-color: rgba(78, 115, 223, 0.2);
        border-left: 3px solid #4e73df;
    }
    
    .legend-item.event-contribution {
        background-color: rgba(28, 200, 138, 0.2);
        border-left: 3px solid #1cc88a;
    }
    
    .legend-item.event-loan {
        background-color: rgba(231, 74, 59, 0.2);
        border-left: 3px solid #e74a3b;
    }
    
    .legend-item.event-reminder {
        background-color: rgba(246, 194, 62, 0.2);
        border-left: 3px solid #f6c23e;
    }
    
    .legend-item.event-completed {
        text-decoration: line-through;
        opacity: 0.7;
    }
    
    .legend-item.event-attending {
        font-weight: bold;
    }
</style>

<script>
$(document).ready(function() {
    // Initialize popovers for events
    $('[data-toggle="popover"]').popover({
        trigger: 'hover',
        html: true
    });
    
    // Set default date for reminder to today
    $('#reminder_date').val(new Date().toISOString().substr(0, 10));
    
    // Export calendar as ICS file
    $('#exportICS').click(function(e) {
        e.preventDefault();
        
        // Create ICS content
        let icsContent = 'BEGIN:VCALENDAR\r\n';
        icsContent += 'VERSION:2.0\r\n';
        icsContent += 'PRODID:-//AGAPE CHAMA//Calendar//EN\r\n';
        
        <?php
        // Prepare all events for ICS export
        $allEvents = [];
        foreach ($events as $day => $dayEvents) {
            $allEvents = array_merge($allEvents, $dayEvents);
        }
        
        foreach ($allEvents as $event) {
            $eventDate = $event['date'];
            $eventTime = $event['time'] ?? '00:00:00';
            $eventTitle = $event['title'];
            $eventDesc = $event['description'] ?? '';
            $eventLoc = $event['location'] ?? '';
            
            echo "icsContent += 'BEGIN:VEVENT\\r\\n';\r\n";
            echo "icsContent += 'UID:" . uniqid() . "@agapechama.com\\r\\n';\r\n";
            echo "icsContent += 'DTSTAMP:" . date('Ymd\THis\Z') . "\\r\\n';\r\n";
            echo "icsContent += 'DTSTART:" . date('Ymd\THis\Z', strtotime($eventDate . ' ' . $eventTime)) . "\\r\\n';\r\n";
            echo "icsContent += 'DTEND:" . date('Ymd\THis\Z', strtotime($eventDate . ' ' . $eventTime . ' +1 hour')) . "\\r\\n';\r\n";
            echo "icsContent += 'SUMMARY:" . addslashes($eventTitle) . "\\r\\n';\r\n";
            
            if (!empty($eventDesc)) {
                echo "icsContent += 'DESCRIPTION:" . addslashes($eventDesc) . "\\r\\n';\r\n";
            }
            
            if (!empty($eventLoc)) {
                echo "icsContent += 'LOCATION:" . addslashes($eventLoc) . "\\r\\n';\r\n";
            }
            
            echo "icsContent += 'END:VEVENT\\r\\n';\r\n";
        }
        ?>
        
        icsContent += 'END:VCALENDAR';
        
        // Create download link
        const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = 'agape_chama_calendar_<?php echo $monthName . '_' . $year; ?>.ics';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
    
    // Export calendar as PDF
    $('#exportPDF').click(function(e) {
        e.preventDefault();
        window.print();
    });
});
</script>

<?php include 'includes/footer.php'; ?>