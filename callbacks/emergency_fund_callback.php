<?php
/* emergency_fund_callback.php - Callback handler for emergency fund contributions */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional emergency fund-specific processing
if (isset($transaction) && $transaction && $resultCode == 0) {
    // Record the emergency fund contribution
    $stmt = $pdo->prepare("INSERT INTO emergency_fund_contributions 
        (user_id, amount, payment_method, transaction_id, contribution_date, created_at) 
        VALUES (?, ?, 'mpesa', ?, NOW(), NOW())");
    $stmt->execute([$transaction['user_id'], $transaction['amount'], $transaction['id']]);
    
    // Update the emergency fund balance
    $stmt = $pdo->prepare("UPDATE emergency_fund SET 
        total_balance = total_balance + ?,
        last_contribution_date = NOW()");
    $stmt->execute([$transaction['amount']]);
    
    // Notify the member
    sendNotification(
        $transaction['user_id'],
        'Emergency Fund Contribution Received',
        "Your emergency fund contribution of " . formatMoney($transaction['amount']) . " has been received. Thank you for supporting our community safety net."
    );
    
    // Log specific emergency fund activity
    error_log("Emergency fund contribution callback processed for user #" . $transaction['user_id']);
}