<?php
/* penalty_callback.php - Callback handler for penalty payments */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional penalty-specific processing
if (isset($transaction) && $transaction && $resultCode == 0) {
    // Record the penalty payment
    $stmt = $pdo->prepare("INSERT INTO penalty_payments 
        (user_id, amount, payment_method, transaction_id, payment_date, description, created_at) 
        VALUES (?, ?, 'mpesa', ?, NOW(), ?, NOW())");
    $stmt->execute([
        $transaction['user_id'], 
        $transaction['amount'], 
        $transaction['id'],
        $transaction['description'] ?: 'Penalty payment'
    ]);
    
    // If there's a reference to a specific penalty, mark it as paid
    if (!empty($transaction['reference_id'])) {
        $stmt = $pdo->prepare("UPDATE penalties SET 
            status = 'paid', 
            payment_date = NOW() 
            WHERE id = ?");
        $stmt->execute([$transaction['reference_id']]);
    }
    
    // Notify the member
    sendNotification(
        $transaction['user_id'],
        'Penalty Payment Received',
        "Your penalty payment of " . formatMoney($transaction['amount']) . " has been received."
    );
    
    // Log specific penalty payment activity
    error_log("Penalty payment callback processed for user #" . $transaction['user_id']);
}