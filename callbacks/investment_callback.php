<?php
/* investment_callback.php - Callback handler for investment contributions */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional investment-specific processing
if (isset($transaction) && $transaction && $resultCode == 0) {
    // Record the investment contribution
    $stmt = $pdo->prepare("INSERT INTO investment_contributions 
        (user_id, amount, payment_method, transaction_id, contribution_date, created_at) 
        VALUES (?, ?, 'mpesa', ?, NOW(), NOW())");
    $stmt->execute([$transaction['user_id'], $transaction['amount'], $transaction['id']]);
    
    // If there's a reference to a specific investment, update its records
    if (!empty($transaction['reference_id'])) {
        // Get the investment details
        $stmt = $pdo->prepare("SELECT * FROM investments WHERE id = ?");
        $stmt->execute([$transaction['reference_id']]);
        $investment = $stmt->fetch();
        
        if ($investment) {
            // Update investment amount
            $stmt = $pdo->prepare("UPDATE investments SET 
                total_contributed = total_contributed + ?,
                last_contribution_date = NOW(),
                updated_at = NOW()
                WHERE id = ?");
            $stmt->execute([$transaction['amount'], $investment['id']]);
            
            // Update user's investment share
            $stmt = $pdo->prepare("INSERT INTO investment_shares 
                (investment_id, user_id, amount, contribution_date, created_at) 
                VALUES (?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                amount = amount + ?, 
                updated_at = NOW()");
            $stmt->execute([
                $investment['id'], 
                $transaction['user_id'], 
                $transaction['amount'],
                $transaction['amount']
            ]);
        }
    }
    
    // Notify the member
    sendNotification(
        $transaction['user_id'],
        'Investment Contribution Received',
        "Your investment contribution of " . formatMoney($transaction['amount']) . " has been received and recorded."
    );
    
    // Log specific investment activity
    error_log("Investment contribution callback processed for user #" . $transaction['user_id']);
}