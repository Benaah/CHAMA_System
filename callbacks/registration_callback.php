<?php
/* registration_callback.php - Callback handler for registration fees */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional registration-specific processing
if (isset($transaction) && $transaction && $resultCode == 0) {
    // Update user's registration status
    $stmt = $pdo->prepare("UPDATE users SET 
        registration_fee_paid = 1, 
        registration_date = NOW(),
        status = 'active'
        WHERE id = ?");
    $stmt->execute([$transaction['user_id']]);
    
    // Record the registration payment
    $stmt = $pdo->prepare("INSERT INTO registration_payments 
        (user_id, amount, payment_method, transaction_id, payment_date, created_at) 
        VALUES (?, ?, 'mpesa', ?, NOW(), NOW())");
    $stmt->execute([$transaction['user_id'], $transaction['amount'], $transaction['id']]);
    
    // Notify the member
    sendNotification(
        $transaction['user_id'],
        'Registration Complete',
        "Your registration fee of " . formatMoney($transaction['amount']) . " has been received. Your membership is now active."
    );
    
    // Log specific registration activity
    error_log("Registration fee callback processed for user #" . $transaction['user_id']);
}