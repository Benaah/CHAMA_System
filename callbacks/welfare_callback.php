<?php
/* welfare_callback.php - Callback handler for welfare contributions */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional welfare-specific processing
if (isset($transaction) && $transaction && $resultCode == 0) {
    // Record the welfare contribution
    $stmt = $pdo->prepare("INSERT INTO welfare_contributions 
        (user_id, amount, payment_method, transaction_id, contribution_date, created_at) 
        VALUES (?, ?, 'mpesa', ?, NOW(), NOW())");
    $stmt->execute([$transaction['user_id'], $transaction['amount'], $transaction['id']]);
    
    // Update the welfare fund balance
    $stmt = $pdo->prepare("UPDATE welfare_fund SET 
        total_balance = total_balance + ?,
        last_contribution_date = NOW()");
    $stmt->execute([$transaction['amount']]);
    
    // Notify the member
    sendNotification(
        $transaction['user_id'],
        'Welfare Contribution Received',
        "Your welfare contribution of " . formatMoney($transaction['amount']) . " has been received. Thank you for supporting our community welfare initiatives."
    );
    
    // Log specific welfare activity
    error_log("Welfare contribution callback processed for user #" . $transaction['user_id']);
}