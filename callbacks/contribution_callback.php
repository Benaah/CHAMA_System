<?php
/* contribution_callback.php - Callback handler for monthly contributions */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional contribution-specific processing
if (isset($transaction) && $transaction && $resultCode == 0) {
    // Update member's contribution record
    $stmt = $pdo->prepare("INSERT INTO contributions 
        (user_id, amount, payment_method, transaction_id, contribution_date, status, created_at) 
        VALUES (?, ?, 'mpesa', ?, NOW(), 'approved', NOW())");
    $stmt->execute([$transaction['user_id'], $transaction['amount'], $transaction['id']]);
    
    // Update member's total savings
    $stmt = $pdo->prepare("UPDATE user_accounts SET 
        total_savings = total_savings + ?, 
        last_contribution_date = NOW() 
        WHERE user_id = ?");
    $stmt->execute([$transaction['amount'], $transaction['user_id']]);
    
    // Notify the member
    sendNotification(
        $transaction['user_id'],
        'Contribution Received',
        "Your contribution of " . formatMoney($transaction['amount']) . " has been received and credited to your account."
    );
    
    // Log specific contribution activity
    error_log("Contribution callback processed for user #" . $transaction['user_id']);
}