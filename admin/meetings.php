<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle meeting deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $meeting_id = $_GET['delete'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete attendees first (to maintain referential integrity)
        $stmt = $pdo->prepare("DELETE FROM meeting_attendees WHERE meeting_id = ?");
        $stmt->execute([$meeting_id]);
        
        // Delete the meeting
        $stmt = $pdo->prepare("DELETE FROM meetings WHERE id = ?");
        $stmt->execute([$meeting_id]);
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Meeting deleted successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting meeting: " . $e->getMessage();
    }
    
    // Redirect to refresh the page
    header("Location: meetings.php");
    exit();
}

// Handle form submission for adding/editing meetings
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $date = $_POST['date'];
    $time = $_POST['time'];
    $location = trim($_POST['location']);
    
    // Validate input
    if (empty($title) || empty($date) || empty($time) || empty($location)) {
        $_SESSION['error'] = "All required fields must be filled out.";
    } else {
        try {
            if (isset($_POST['meeting_id']) && is_numeric($_POST['meeting_id'])) {
                // Update existing meeting
                $stmt = $pdo->prepare("
                    UPDATE meetings 
                    SET title = ?, description = ?, date = ?, time = ?, location = ?, updated_at = CURRENT_TIMESTAMP
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $date, $time, $location, $_POST['meeting_id']]);
                $_SESSION['success'] = "Meeting updated successfully.";
            } else {
                // Add new meeting
                $stmt = $pdo->prepare("
                    INSERT INTO meetings (title, description, date, time, location, created_at)
                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                $stmt->execute([$title, $description, $date, $time, $location]);
                $_SESSION['success'] = "Meeting added successfully.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error saving meeting: " . $e->getMessage();
        }
        
        // Redirect to refresh the page
        header("Location: meetings.php");
        exit();
    }
}

// Get meeting details if editing
$meeting = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $meeting = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$meeting) {
        $_SESSION['error'] = "Meeting not found.";
        header("Location: meetings.php");
        exit();
    }
}

// Fetch all meetings for display
$stmt = $pdo->prepare("
    SELECT m.*, 
           COUNT(ma.id) as attendee_count
    FROM meetings m
    LEFT JOIN meeting_attendees ma ON m.id = ma.meeting_id
    GROUP BY m.id
    ORDER BY m.date DESC, m.time DESC
");
$stmt->execute();
$meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">           </h1>
        <button class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm" data-toggle="modal" data-target="#addMeetingModal">
            <i class="fas fa-plus fa-sm text-white-50"></i> Add New Meeting
        </button>
    </div>

    <!-- Display Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $_SESSION['success'] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error'] ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Meetings Table -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">All Meetings</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="meetingsTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Location</th>
                            <th>Attendees</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($meetings) > 0): ?>
                            <?php foreach ($meetings as $meeting_item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($meeting_item['title']) ?></td>
                                    <td><?= date('M d, Y', strtotime($meeting_item['date'])) ?></td>
                                    <td><?= date('h:i A', strtotime($meeting_item['time'])) ?></td>
                                    <td><?= htmlspecialchars($meeting_item['location']) ?></td>
                                    <td>
                                        <span class="badge badge-primary"><?= $meeting_item['attendee_count'] ?></span>
                                        <a href="meeting_attendees.php?id=<?= $meeting_item['id'] ?>" class="btn btn-sm btn-link">View</a>
                                    </td>
                                    <td>
                                        <a href="meetings.php?edit=<?= $meeting_item['id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="#" class="btn btn-sm btn-danger" data-toggle="modal" data-target="#deleteMeetingModal" 
                                           data-meeting-id="<?= $meeting_item['id'] ?>" data-meeting-title="<?= htmlspecialchars($meeting_item['title']) ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center">No meetings found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Meeting Modal -->
<div class="modal fade" id="addMeetingModal" tabindex="-1" role="dialog" aria-labelledby="addMeetingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addMeetingModalLabel"><?= $meeting ? 'Edit Meeting' : 'Add New Meeting' ?></h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="meetings.php">
                <div class="modal-body">
                    <?php if ($meeting): ?>
                        <input type="hidden" name="meeting_id" value="<?= $meeting['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label for="title">Meeting Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required 
                               value="<?= $meeting ? htmlspecialchars($meeting['title']) : '' ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?= $meeting ? htmlspecialchars($meeting['description']) : '' ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="date">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date" name="date" required 
                                       value="<?= $meeting ? $meeting['date'] : date('Y-m-d') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="time">Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="time" name="time" required 
                                       value="<?= $meeting ? $meeting['time'] : '10:00' ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="location">Location <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="location" name="location" required 
                                       value="<?= $meeting ? htmlspecialchars($meeting['location']) : '' ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <?= $meeting ? 'Update Meeting' : 'Add Meeting' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Meeting Confirmation Modal -->
<div class="modal fade" id="deleteMeetingModal" tabindex="-1" role="dialog" aria-labelledby="deleteMeetingModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteMeetingModalLabel">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the meeting "<span id="meetingTitle"></span>"?</p>
                <p class="text-danger">This action cannot be undone and will also delete all attendee records for this meeting.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- Meeting Attendees Modal -->
<div class="modal fade" id="attendeesModal" tabindex="-1" role="dialog" aria-labelledby="attendeesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attendeesModalLabel">Meeting Attendees</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="attendeesList">
                    <!-- Attendees will be loaded here via AJAX -->
                    <p class="text-center">Loading attendees...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Page level plugins -->
<script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#meetingsTable').DataTable({
        order: [[1, 'desc'], [2, 'desc']]
    });
    
    // Auto-open modal if editing
    <?php if ($meeting): ?>
    $('#addMeetingModal').modal('show');
    <?php endif; ?>
    
    // Set up delete confirmation modal
    $('#deleteMeetingModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var meetingId = button.data('meeting-id');
        var meetingTitle = button.data('meeting-title');
        
        $('#meetingTitle').text(meetingTitle);
        $('#confirmDelete').attr('href', 'meetings.php?delete=' + meetingId);
    });
    
    // Load attendees via AJAX
    $('a[href^="meeting_attendees.php"]').on('click', function(e) {
        e.preventDefault();
        var meetingId = $(this).attr('href').split('=')[1];
        
        $('#attendeesModal').modal('show');
        
        $.ajax({
            url: 'ajax/get_meeting_attendees.php',
            type: 'GET',
            data: { meeting_id: meetingId },
            success: function(response) {
                $('#attendeesList').html(response);
            },
            error: function() {
                $('#attendeesList').html('<p class="text-danger text-center">Error loading attendees. Please try again.</p>');
            }
        });
    });
});
</script>

<?php include 'footer.php'; ?>