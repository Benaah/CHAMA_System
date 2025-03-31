<?php
// audit_trail.php
require 'config.php';

/**
 * Log an event in the audit trail.
 *
 * @param int    $userId The user performing the action.
 * @param string $action A short description of the action.
 * @param string $details Detailed information (optional).
 */
function logEvent($userId, $action, $details = '')
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, details, event_date) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$userId, $action, $details]);
}

// Example usage:
// logEvent(5, 'Contribution Update', 'User updated contribution amount from 1000 to 1200');
?>
