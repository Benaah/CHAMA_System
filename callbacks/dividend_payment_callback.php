<?php
/* dividend_callback.php - Callback handler for dividend reinvestments */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional dividend reinvestment-specific processing
if (isset($transaction) && $transaction && $resultCode == 0) {
    // Record the dividend reinvestment
    $stmt = $pdo->prepare("INSERT INTO dividend_reinvestments 
        (user_id, amount, payment_method, transaction_id, reinvestment_date, created_at) 
        VALUES (?, ?, 'mpesa', ?, NOW(), NOW())");
    $stmt->execute([$transaction['user_id'], $transaction['amount'], $transaction['id']]);
    
    // Update user's investment balance
    $stmt = $pdo->prepare("UPDATE user_accounts SET 
        total_investments = total_investments + ? 
        WHERE user_id = ?");
    $stmt->execute([$transaction['amount'], $transaction['user_id']]);
    
    // If there's a reference to a specific dividend distribution, mark it as reinvested
    if (!empty($transaction['reference_id'])) {
        $stmt = $pdo->prepare("UPDATE dividend_distributions SET 
            status = 'reinvested', 
            reinvestment_date = NOW() 
            WHERE id = ?");
        $stmt->execute([$transaction['reference_id']]);
    }
    
    // Notify the member
    sendNotification(
        $transaction['user_id'],
        'Dividend Reinvestment Processed',
        "Your dividend reinvestment of " . formatMoney($transaction['amount']) . " has been processed successfully."
    );
    
    // Log specific dividend reinvestment activity
    error_log("Dividend reinvestment callback processed for user #" . $transaction['user_id']);
}