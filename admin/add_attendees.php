<?php
include '../../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Validate input
if (!isset($_POST['meeting_id']) || !is_numeric($_POST['meeting_id']) || !isset($_POST['members']) || !is_array($_POST['members'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$meeting_id = $_POST['meeting_id'];
$members = $_POST['members'];

try {
    // Begin transaction
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO meeting_attendees (meeting_id, user_id, attended, created_at)
        VALUES (?, ?, false, CURRENT_TIMESTAMP)
    ");
    
    $success = true;
    $added_count = 0;
    
    foreach ($members as $member_id) {
        if (is_numeric($member_id)) {
            $result = $stmt->execute([$meeting_id, $member_id]);
            if ($result) {
                $added_count++;
            } else {
                $success = false;
                break;
            }
        }
    }
    
    if ($success) {
        // Commit transaction
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => $added_count . ' members added successfully']);
    } else {
        // Rollback transaction
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Failed to add members']);
    }
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}