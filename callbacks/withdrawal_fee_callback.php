<?php
/* withdrawal_fee_callback.php - Callback handler for withdrawal processing fees */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional withdrawal fee-specific processing
if (isset($transaction) && $transaction && $resultCode == 0 && !empty($transaction['reference_id'])) {
    // Get withdrawal request details
    $stmt = $pdo->prepare("SELECT * FROM withdrawal_requests WHERE id = ?");
    $stmt->execute([$transaction['reference_id']]);
    $withdrawal = $stmt->fetch();
    
    if ($withdrawal) {
        // Update withdrawal request status
        $stmt = $pdo->prepare("UPDATE withdrawal_requests SET 
            fee_paid = 1,
            fee_payment_date = NOW(),
            status = 'processing',
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$withdrawal['id']]);
        
        // Record the fee payment
        $stmt = $pdo->prepare("INSERT INTO fee_payments 
            (user_id, amount, payment_type, reference_id, payment_method, transaction_id, payment_date, created_at) 
            VALUES (?, ?, 'withdrawal_fee', ?, 'mpesa', ?, NOW(), NOW())");
        $stmt->execute([
            $transaction['user_id'], 
            $transaction['amount'], 
            $withdrawal['id'],
            $transaction['id']
        ]);
        
        // Notify the member
        sendNotification(
            $transaction['user_id'],
            'Withdrawal Fee Received',
            "Your withdrawal processing fee of " . formatMoney($transaction['amount']) . " has been received. Your withdrawal request is now being processed."
        );
        
        // Notify the treasurer
        $treasurers = getUsersByRole('treasurer');
        foreach ($treasurers as $treasurer) {
            sendNotification(
                $treasurer['id'],
                'Withdrawal Ready for Processing',
                "A withdrawal request (#" . $withdrawal['id'] . ") for " . formatMoney($withdrawal['amount']) . " is ready for processing. The fee has been paid."
            );
        }
        
        // Log specific withdrawal fee activity
        error_log("Withdrawal fee callback processed for withdrawal request #" . $withdrawal['id']);
    }
}