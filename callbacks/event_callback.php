<?php
/* event_callback.php - Callback handler for event payments */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional event-specific processing
if (isset($transaction) && $transaction && $resultCode == 0 && !empty($transaction['reference_id'])) {
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ?");
    $stmt->execute([$transaction['reference_id']]);
    $event = $stmt->fetch();
    
    if ($event) {
        // Record the event payment
        $stmt = $pdo->prepare("INSERT INTO event_payments 
            (event_id, user_id, amount, payment_method, transaction_id, payment_date, created_at) 
            VALUES (?, ?, ?, 'mpesa', ?, NOW(), NOW())");
        $stmt->execute([
            $event['id'], 
            $transaction['user_id'], 
            $transaction['amount'], 
            $transaction['id']
        ]);
        
        // Register the user for the event
        $stmt = $pdo->prepare("INSERT INTO event_attendees 
            (event_id, user_id, registration_date, payment_status, created_at) 
            VALUES (?, ?, NOW(), 'paid', NOW())
            ON DUPLICATE KEY UPDATE 
            payment_status = 'paid', 
            updated_at = NOW()");
        $stmt->execute([$event['id'], $transaction['user_id']]);
        
        // Notify the member
        sendNotification(
            $transaction['user_id'],
            'Event Registration Confirmed',
            "Your payment of " . formatMoney($transaction['amount']) . " for the event '" . $event['title'] . "' has been received. Your registration is confirmed."
        );
        
        // Log specific event payment activity
        error_log("Event payment callback processed for event #" . $event['id']);
    }
}