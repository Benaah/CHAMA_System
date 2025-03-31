<?php
/* loan_repayment_callback.php - Callback handler for loan repayments */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional loan repayment-specific processing
if (isset($transaction) && $transaction && $resultCode == 0 && !empty($transaction['reference_id'])) {
    // Get loan details
    $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->execute([$transaction['reference_id']]);
    $loan = $stmt->fetch();
    
    if ($loan) {
        // Record loan repayment
        $stmt = $pdo->prepare("INSERT INTO loan_repayments 
            (loan_id, user_id, amount, payment_method, transaction_id, repayment_date, created_at) 
            VALUES (?, ?, ?, 'mpesa', ?, NOW(), NOW())");
        $stmt->execute([$loan['id'], $transaction['user_id'], $transaction['amount'], $transaction['id']]);
        
        // Update loan balance
        $newBalance = $loan['balance'] - $transaction['amount'];
        $newStatus = ($newBalance <= 0) ? 'paid' : 'active';
        
        $stmt = $pdo->prepare("UPDATE loans SET 
            balance = ?, 
            last_payment_date = NOW(),
            status = ?,
            paid = ? 
            WHERE id = ?");
        $stmt->execute([$newBalance, $newStatus, ($newBalance <= 0 ? 1 : 0), $loan['id']]);
        
        // Notify the member
        sendNotification(
            $transaction['user_id'],
            'Loan Repayment Received',
            "Your loan repayment of " . formatMoney($transaction['amount']) . " has been received. " . 
            ($newBalance > 0 ? "Remaining balance: " . formatMoney($newBalance) : "Your loan has been fully repaid!")
        );
        
        // Log specific loan repayment activity
        error_log("Loan repayment callback processed for loan #" . $loan['id']);
    }
}