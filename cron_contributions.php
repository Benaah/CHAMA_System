<?php
// cron_contributions.php
require 'config.php';
require 'sms_service.php';

// Define the penalty percentage
define('PENALTY_PERCENTAGE', 0.05);

// Get current date (format YYYY-MM-DD)
$currentDate = date('Y-m-d');

// 1. Auto-generate contribution deadlines for upcoming month if not already generated.
// (Assume a contributions_schedule table with fields: id, user_id, deadline, amount, paid_status)
// Here we simply demonstrate checking and inserting missing deadlines.
$stmt = $pdo->query("SELECT user_id FROM users WHERE status = 'active'");
$activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($activeUsers as $user) {
    $userId = $user['user_id'] ?? $user['id']; // adjust field name accordingly
    // Check if a deadline exists for next month
    $nextDeadline = date('Y-m-d', strtotime('first day of next month'));
    $stmtCheck = $pdo->prepare("SELECT id FROM contributions_schedule WHERE user_id = ? AND deadline = ?");
    $stmtCheck->execute([$userId, $nextDeadline]);
    if (!$stmtCheck->fetch()) {
        // Insert a new contribution schedule
        // Assume default amount is defined or stored per user
        $defaultAmount = 1000; // Example amount
        $stmtInsert = $pdo->prepare("INSERT INTO contributions_schedule (user_id, deadline, amount, paid_status) VALUES (?, ?, ?, 'pending')");
        $stmtInsert->execute([$userId, $nextDeadline, $defaultAmount]);
    }
}

// 2. Calculate penalties for missed contributions.
// Assume that the contributions_schedule table has a field 'paid_status' that is 'pending' if not paid.
$stmt = $pdo->prepare("SELECT cs.id, cs.user_id, cs.amount, u.phone 
    FROM contributions_schedule cs
    JOIN users u ON cs.user_id = u.id
    WHERE cs.deadline < ? AND cs.paid_status = 'pending'");
$stmt->execute([$currentDate]);
$overdueContributions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$smsService = new SMSService();
foreach ($overdueContributions as $contribution) {
    // Calculate the penalty as 5% of the expected contribution amount.
    $penalty = $contribution['amount'] * PENALTY_PERCENTAGE;
    
    // Update the record with the penalty.
    // (Assume you have a penalty field; if not, update the amount or record the penalty separately)
    $updateStmt = $pdo->prepare("UPDATE contributions_schedule SET penalty = ?, paid_status = 'late' WHERE id = ?");
    $updateStmt->execute([$penalty, $contribution['id']]);
    
    // Trigger an SMS reminder including the penalty amount.
    $message = "Reminder: Your contribution due on {$contribution['deadline']} is overdue. A penalty of KES " . number_format($penalty, 2) . " has been applied. Please make your payment.";
    $smsService->sendSMS($contribution['phone'], $message);
}

echo "Contribution scheduling and penalty management completed.\n";
?>
