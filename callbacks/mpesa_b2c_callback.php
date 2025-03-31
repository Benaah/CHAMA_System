<?php
/* mpesa_b2c_callback.php - Generic callback handler for MPesa B2C disbursements */
require_once '../config.php';

// Get the response data
$callbackData = file_get_contents('php://input');
error_log("MPesa B2C Callback Data: " . $callbackData);

// Decode the JSON response
$response = json_decode($callbackData);

if ($response && isset($response->Result)) {
    $result = $response->Result;
    $conversationID = $result->ConversationID;
    $originatorConversationID = $result->OriginatorConversationID;
    $resultCode = $result->ResultCode;
    
    // Find the transaction in our database
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE conversation_id = ? OR originator_conversation_id = ?");
    $stmt->execute([$conversationID, $originatorConversationID]);
    $transaction = $stmt->fetch();
    
    if ($transaction) {
        if ($resultCode == 0) {
            // Disbursement was successful
            $resultParameters = $result->ResultParameters->ResultParameter;
            $transactionAmount = null;
            $mpesaReceiptNumber = null;
            $transactionCompletedDateTime = null;
            $phoneNumber = null;
            
            // Extract the parameters
            foreach ($resultParameters as $param) {
                if ($param->Key == "TransactionAmount") $transactionAmount = $param->Value;
                if ($param->Key == "TransactionReceipt") $mpesaReceiptNumber = $param->Value;
                if ($param->Key == "TransactionCompletedDateTime") $transactionCompletedDateTime = $param->Value;
                if ($param->Key == "ReceiverPartyPublicName") $phoneNumber = $param->Value;
            }
            
            // Update transaction status
            $stmt = $pdo->prepare("UPDATE transactions SET 
                status = 'completed', 
                mpesa_receipt = ?, 
                transaction_date = ?, 
                updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$mpesaReceiptNumber, date('Y-m-d H:i:s', strtotime($transactionCompletedDateTime)), $transaction['id']]);
            
            // Log activity
            logActivity($transaction['user_id'], 'disbursement_completed', "Completed {$transaction['transaction_type']} disbursement of " . formatMoney($transaction['amount']) . " to user #{$transaction['recipient_id']} (Receipt: $mpesaReceiptNumber)");
            
            // Handle specific transaction types
            handleSpecificDisbursementCompletion($transaction);
            
        } else {
            // Disbursement failed
            $resultDescription = $result->ResultDesc ?? "Unknown error";
            
            $stmt = $pdo->prepare("UPDATE transactions SET 
                status = 'failed', 
                failure_reason = ?, 
                updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$resultDescription, $transaction['id']]);
            
            // Log activity
            logActivity($transaction['user_id'], 'disbursement_failed', "Failed {$transaction['transaction_type']} disbursement of " . formatMoney($transaction['amount']) . " to user #{$transaction['recipient_id']} (Reason: {$resultDescription})");
            
            // Handle specific transaction types for failure
            handleSpecificDisbursementFailure($transaction, $resultDescription);
        }
    } else {
        error_log("Transaction not found for ConversationID: " . $conversationID);
    }
}

// Return a response to acknowledge receipt of the callback
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'B2C callback received successfully']);

/**
 * Handle specific actions when a disbursement is completed successfully
 */
function handleSpecificDisbursementCompletion($transaction) {
    global $pdo;
    
    switch ($transaction['transaction_type']) {
        case 'loan_disbursement':
            // Update loan status if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE loans SET 
                    status = 'active', 
                    disbursed = 1, 
                    disbursement_date = NOW(),
                    due_date = DATE_ADD(NOW(), INTERVAL loan_term MONTH)
                    WHERE id = ?");
                $stmt->execute([$transaction['reference_id']]);
                
                // Notify the recipient
                sendNotification(
                    $transaction['recipient_id'],
                    'Loan Disbursed',
                    "Your loan of " . formatMoney($transaction['amount']) . " has been disbursed to your M-Pesa account."
                );
            }
            break;
            
        case 'welfare_payment':
            // Update welfare application if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE welfare_applications SET 
                    status = 'disbursed', 
                    disbursement_date = NOW()
                    WHERE id = ?");
                $stmt->execute([$transaction['reference_id']]);
                
                // Notify the recipient
                sendNotification(
                    $transaction['recipient_id'],
                    'Welfare Payment Received',
                    "Your welfare payment of " . formatMoney($transaction['amount']) . " has been sent to your M-Pesa account."
                );
            }
            break;
            
        case 'savings_withdrawal':
            // Update withdrawal request if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE withdrawal_requests SET 
                    status = 'completed', 
                    completion_date = NOW()
                    WHERE id = ?");
                $stmt->execute([$transaction['reference_id']]);
                
                // Notify the recipient
                sendNotification(
                    $transaction['recipient_id'],
                    'Withdrawal Completed',
                    "Your withdrawal of " . formatMoney($transaction['amount']) . " has been sent to your M-Pesa account."
                );
            }
            break;
            
        case 'dividend_payment':
            // Update dividend record if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE dividends SET 
                    status = 'paid', 
                    payment_date = NOW()
                    WHERE id = ?");
                $stmt->execute([$transaction['reference_id']]);
                
                // Notify the recipient
                sendNotification(
                    $transaction['recipient_id'],
                    'Dividend Payment Received',
                    "Your dividend payment of " . formatMoney($transaction['amount']) . " has been sent to your M-Pesa account."
                );
            }
            break;
    }
}

/**
 * Handle specific actions when a disbursement fails
 */
function handleSpecificDisbursementFailure($transaction, $reason) {
    global $pdo;
    
    switch ($transaction['transaction_type']) {
        case 'loan_disbursement':
            // Update loan status if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE loans SET 
                    disbursed = 0,
                    disbursement_notes = CONCAT(IFNULL(disbursement_notes, ''), '\nDisbursement failed on ', NOW(), ': ', ?)
                    WHERE id = ?");
                $stmt->execute([$reason, $transaction['reference_id']]);
                
                // Notify the admin
                sendNotification(
                    $transaction['user_id'],
                    'Loan Disbursement Failed',
                    "Loan disbursement of " . formatMoney($transaction['amount']) . " to user #{$transaction['recipient_id']} failed. Reason: $reason"
                );
            }
            break;
            
        case 'welfare_payment':
            // Update welfare application if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE welfare_applications SET 
                    status = 'disbursement_failed',
                    notes = CONCAT(IFNULL(notes, ''), '\nDisbursement failed on ', NOW(), ': ', ?)
                    WHERE id = ?");
                $stmt->execute([$reason, $transaction['reference_id']]);
                
                // Notify the admin
                sendNotification(
                    $transaction['user_id'],
                    'Welfare Payment Failed',
                    "Welfare payment of " . formatMoney($transaction['amount']) . " to user #{$transaction['recipient_id']} failed. Reason: $reason"
                );
            }
            break;
            
        case 'savings_withdrawal':
            // Update withdrawal request if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE withdrawal_requests SET 
                    status = 'failed',
                    notes = CONCAT(IFNULL(notes, ''), '\nDisbursement failed on ', NOW(), ': ', ?)
                    WHERE id = ?");
                $stmt->execute([$reason, $transaction['reference_id']]);
                
                // Notify the admin
                sendNotification(
                    $transaction['user_id'],
                    'Withdrawal Failed',
                    "Withdrawal of " . formatMoney($transaction['amount']) . " to user #{$transaction['recipient_id']} failed. Reason: $reason"
                );
            }
            break;
            
        case 'dividend_payment':
            // Update dividend record if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE dividends SET 
                    status = 'failed',
                    notes = CONCAT(IFNULL(notes, ''), '\nDisbursement failed on ', NOW(), ': ', ?)
                    WHERE id = ?");
                $stmt->execute([$reason, $transaction['reference_id']]);
                
                // Notify the admin
                sendNotification(
                    $transaction['user_id'],
                    'Dividend Payment Failed',
                    "Dividend payment of " . formatMoney($transaction['amount']) . " to user #{$transaction['recipient_id']} failed. Reason: $reason"
                );
            }
            break;
            
        case 'project_payout':
            // Update project payout record if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE project_payouts SET 
                    status = 'failed',
                    notes = CONCAT(IFNULL(notes, ''), '\nDisbursement failed on ', NOW(), ': ', ?)
                    WHERE id = ?");
                $stmt->execute([$reason, $transaction['reference_id']]);
                
                // Notify the admin
                sendNotification(
                    $transaction['user_id'],
                    'Project Payout Failed',
                    "Project payout of " . formatMoney($transaction['amount']) . " to user #{$transaction['recipient_id']} failed. Reason: $reason"
                );
            }
            break;
            
        case 'emergency_assistance':
            // Update emergency assistance record if reference exists
            if (!empty($transaction['reference_id'])) {
                $stmt = $pdo->prepare("UPDATE emergency_assistance SET 
                    status = 'failed',
                    notes = CONCAT(IFNULL(notes, ''), '\nDisbursement failed on ', NOW(), ': ', ?)
                    WHERE id = ?");
                $stmt->execute([$reason, $transaction['reference_id']]);
                
                // Notify the admin
                sendNotification(
                    $transaction['user_id'],
                    'Emergency Assistance Failed',
                    "Emergency assistance payment of " . formatMoney($transaction['amount']) . " to user #{$transaction['recipient_id']} failed. Reason: $reason"
                );
            }
            break;
    }
}
                