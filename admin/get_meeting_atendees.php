<?php
include '../../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    echo json_encode(['error' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_GET['meeting_id']) || !is_numeric($_GET['meeting_id'])) {
    echo '<p class="text-danger text-center">Invalid meeting ID</p>';
    exit();
}

$meeting_id = $_GET['meeting_id'];

// Get meeting details
$stmt = $pdo->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->execute([$meeting_id]);
$meeting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$meeting) {
    echo '<p class="text-danger text-center">Meeting not found</p>';
    exit();
}

// Get attendees
$stmt = $pdo->prepare("
    SELECT ma.*, u.name, u.email, u.phone 
    FROM meeting_attendees ma
    JOIN users u ON ma.user_id = u.id
    WHERE ma.meeting_id = ?
    ORDER BY u.name
");
$stmt->execute([$meeting_id]);
$attendees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h5 class="mb-3"><?= htmlspecialchars($meeting['title']) ?> - <?= date('M d, Y', strtotime($meeting['date'])) ?></h5>

<?php if (count($attendees) > 0): ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Attendance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendees as $attendee): ?>
                    <tr>
                        <td><?= htmlspecialchars($attendee['name']) ?></td>
                        <td><?= htmlspecialchars($attendee['email']) ?></td>
                        <td><?= htmlspecialchars($attendee['phone']) ?></td>
                        <td>
                            <?php if ($attendee['attended']): ?>
                                <span class="badge badge-success">Present</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Absent</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-<?= $attendee['attended'] ? 'warning' : 'success' ?> toggle-attendance" 
                                    data-attendee-id="<?= $attendee['id'] ?>" 
                                    data-current-status="<?= $attendee['attended'] ? '1' : '0' ?>">
                                <?= $attendee['attended'] ? 'Mark Absent' : 'Mark Present' ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <script>
    $(document).ready(function() {
        $('.toggle-attendance').on('click', function() {
            var button = $(this);
            var attendeeId = button.data('attendee-id');
            var currentStatus = button.data('current-status');
            var newStatus = currentStatus == '1' ? '0' : '1';
            
            $.ajax({
                url: 'ajax/update_attendance.php',
                type: 'POST',
                data: { 
                    attendee_id: attendeeId,
                    status: newStatus
                },
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        if (newStatus == '1') {
                            button.removeClass('btn-success').addClass('btn-warning');
                            button.text('Mark Absent');
                            button.closest('tr').find('.badge').removeClass('badge-danger').addClass('badge-success').text('Present');
                        } else {
                            button.removeClass('btn-warning').addClass('btn-success');
                            button.text('Mark Present');
                            button.closest('tr').find('.badge').removeClass('badge-success').addClass('badge-danger').text('Absent');
                        }
                        button.data('current-status', newStatus);
                    } else {
                        alert('Error: ' + result.message);
                    }
                },
                error: function() {
                    alert('Error updating attendance. Please try again.');
                }
            });
        });
    });
    </script>
<?php else: ?>
    <p class="text-center">No attendees found for this meeting.</p>
    
    <div class="text-center mt-3">
        <button class="btn btn-primary" id="addAttendees">Add Attendees</button>
    </div>
    
    <div id="addAttendeesForm" class="mt-3" style="display: none;">
        <form action="ajax/add_attendees.php" method="post" id="attendeesForm">
            <input type="hidden" name="meeting_id" value="<?= $meeting_id ?>">
            
            <div class="form-group">
                <label>Select Members</label>
                <select class="form-control select2" name="members[]" multiple required>
                    <?php
                    // Get all members who are not already attendees
                    $stmt = $pdo->prepare("
                        SELECT id, name, email 
                        FROM users 
                        WHERE role = 'member' 
                        AND id NOT IN (SELECT user_id FROM meeting_attendees WHERE meeting_id = ?)
                        ORDER BY name
                    ");
                    $stmt->execute([$meeting_id]);
                    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($members as $member) {
                        echo '<option value="' . $member['id'] . '">' . htmlspecialchars($member['name']) . ' (' . htmlspecialchars($member['email']) . ')</option>';
                    }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-success">Add Selected Members</button>
                <button type="button" class="btn btn-secondary" id="cancelAdd">Cancel</button>
            </div>
        </form>
    </div>
    
    <script>
    $(document).ready(function() {
        // Initialize Select2 for better dropdown experience
        if ($.fn.select2) {
            $('.select2').select2();
        }
        
        $('#addAttendees').on('click', function() {
            $(this).hide();
            $('#addAttendeesForm').show();
        });
        
        $('#cancelAdd').on('click', function() {
            $('#addAttendeesForm').hide();
            $('#addAttendees').show();
        });
        
        $('#attendeesForm').on('submit', function(e) {
            e.preventDefault();
            
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: $(this).serialize(),
                success: function(response) {
                    var result = JSON.parse(response);
                    if (result.success) {
                        // Reload the attendees list
                        $.ajax({
                            url: 'ajax/get_meeting_attendees.php',
                            type: 'GET',
                            data: { meeting_id: <?= $meeting_id ?> },
                            success: function(response) {
                                $('#attendeesList').html(response);
                            }
                        });
                    } else {
                        alert('Error: ' + result.message);
                    }
                },
                error: function() {
                    alert('Error adding attendees. Please try again.');
                }
            });
        });
    });
    </script>
<?php endif; ?>