<?php
/* mpesa_callback.php - Generic callback handler for MPesa STK Push payments */
require_once '../config.php';

// Get the response data
$callbackData = file_get_contents('php://input');
error_log("MPesa Callback Data: " . $callbackData);

// Decode the JSON response
$response = json_decode($callbackData);

if ($response && isset($response->Body->stkCallback)) {
    $result = $response->Body->stkCallback;
    $merchantRequestID = $result->MerchantRequestID;
    $checkoutRequestID = $result->CheckoutRequestID;
    $resultCode = $result->ResultCode;
    
    // Find the transaction in our database
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE checkout_request_id = ?");
    $stmt->execute([$checkoutRequestID]);
    $transaction = $stmt->fetch();
    
    if ($transaction) {
        if ($resultCode == 0) {
            // Payment was successful
            $callbackMetadata = $result->CallbackMetadata->Item;
            $amount = null;
            $mpesaReceiptNumber = null;
            $transactionDate = null;
            $phoneNumber = null;
            
            // Extract the metadata
            foreach ($callbackMetadata as $item) {
                if ($item->Name == "Amount") $amount = $item->Value;
                if ($item->Name == "MpesaReceiptNumber") $mpesaReceiptNumber = $item->Value;
                if ($item->Name == "TransactionDate") $transactionDate = $item->Value;
                if ($item->Name == "PhoneNumber") $phoneNumber = $item->Value;
            }
            
            // Update transaction status
            $stmt = $pdo->prepare("UPDATE transactions SET 
                status = 'completed', 
                mpesa_receipt = ?, 
                transaction_date = ?, 
                updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$mpesaReceiptNumber, date('Y-m-d H:i:s', strtotime($transactionDate)), $transaction['id']]);
            
            // Log activity
            logActivity($transaction['user_id'], 'payment_completed', "Completed {$transaction['transaction_type']} payment of " . formatMoney($transaction['amount']) . " (Receipt: $mpesaReceiptNumber)");
            
        } else {
            // Payment failed
            $stmt = $pdo->prepare("UPDATE transactions SET 
                status = 'failed', 
                failure_reason = ?, 
                updated_at = NOW() 
                WHERE id = ?");
            $stmt->execute([$result->ResultDesc, $transaction['id']]);
            
            // Log activity
            logActivity($transaction['user_id'], 'payment_failed', "Failed {$transaction['transaction_type']} payment of " . formatMoney($transaction['amount']) . " (Reason: {$result->ResultDesc})");
        }
    } else {
        error_log("Transaction not found for CheckoutRequestID: " . $checkoutRequestID);
    }
}

// Return a response to acknowledge receipt of the callback
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Callback received successfully']);