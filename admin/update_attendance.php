<?php
include '../../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_POST['attendee_id']) || !is_numeric($_POST['attendee_id']) || !isset($_POST['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$attendee_id = $_POST['attendee_id'];
$status = $_POST['status'] == '1' ? true : false;

try {
    $stmt = $pdo->prepare("UPDATE meeting_attendees SET attended = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $result = $stmt->execute([$status, $attendee_id]);
    
    if ($result) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update attendance']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}