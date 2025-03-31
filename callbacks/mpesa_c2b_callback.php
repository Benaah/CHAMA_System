<?php
/* mpesa_c2b_callback.php - Callback handler for MPesa C2B transactions */
require_once '../config.php';

// Get the response data
$callbackData = file_get_contents('php://input');
error_log("MPesa C2B Callback Data: " . $callbackData);

// Decode the JSON response
$response = json_decode($callbackData);

if ($response) {
    // Extract transaction details
    $transactionType = $response->TransactionType ?? '';
    $transID = $response->TransID ?? '';
    $transTime = $response->TransTime ?? '';
    $transAmount = $response->TransAmount ?? 0;
    $businessShortCode = $response->BusinessShortCode ?? '';
    $billRefNumber = $response->BillRefNumber ?? '';
    $invoiceNumber = $response->InvoiceNumber ?? '';
    $orgAccountBalance = $response->OrgAccountBalance ?? '';
    $thirdPartyTransID = $response->ThirdPartyTransID ?? '';
    $MSISDN = $response->MSISDN ?? ''; // Phone number
    $firstName = $response->FirstName ?? '';
    $middleName = $response->MiddleName ?? '';
    $lastName = $response->LastName ?? '';
    
    // Format transaction date
    $transactionDate = date('Y-m-d H:i:s', strtotime($transTime));
    
    // Try to identify the user by phone number
    $phone = formatPhoneNumber($MSISDN);
    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone LIKE ?");
    $stmt->execute(['%' . substr($phone, -9)]);
    $user = $stmt->fetch();
    
    $userId = null;
    if ($user) {
        $userId = $user['id'];
    }
    
    // Try to identify transaction type from bill reference number
    // Format expected: TYPE-REFERENCE (e.g., CONTRIB-123, LOAN-456)
    $transactionType = 'payment';
    $referenceId = null;
    $description = 'Direct payment via M-Pesa';
    
    if (!empty($billRefNumber)) {
        $parts = explode('-', $billRefNumber);
        if (count($parts) >= 2) {
            $typeCode = strtoupper($parts[0]);
            $referenceId = $parts[1];
            
            switch ($typeCode) {
                case 'CONTRIB':
                    $transactionType = 'contribution';
                    $description = 'Monthly contribution payment';
                    break;
                case 'LOAN':
                    $transactionType = 'loan_repayment';
                    $description = 'Loan repayment';
                    break;
                case 'REG':
                    $transactionType = 'registration';
                    $description = 'Registration fee payment';
                    break;
                case 'PENALTY':
                    $transactionType = 'penalty_payment';
                    $description = 'Penalty payment';
                    break;
                case 'INV':
                    $transactionType = 'investment_contribution';
                    $description = 'Investment contribution';
                    break;
                case 'EMERG':
                    $transactionType = 'emergency_fund';
                    $description = 'Emergency fund contribution';
                    break;
                case 'WELFARE':
                    $transactionType = 'welfare_contribution';
                    $description = 'Welfare contribution';
                    break;
                case 'PROJ':
                    $transactionType = 'project_contribution';
                    $description = 'Project contribution';
                    break;
                case 'WITH':
                    $transactionType = 'withdrawal_fee';
                    $description = 'Withdrawal processing fee';
                    break;
                case 'EVENT':
                    $transactionType = 'event_payment';
                    $description = 'Event payment';
                    break;
                case 'DIV':
                    $transactionType = 'dividend_reinvestment';
                    $description = 'Dividend reinvestment';
                    break;
                case 'RENEW':
                    $transactionType = 'membership_renewal';
                    $description = 'Annual membership renewal';
                    break;
            }
        } else {
            // If user ID is provided as the reference number
            if (is_numeric($billRefNumber)) {
                // Check if this is a valid user ID
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$billRefNumber]);
                $refUser = $stmt->fetch();
                
                if ($refUser) {
                    $userId = $refUser['id'];
                    $description = 'Payment by member #' . $userId;
                }
            }
        }
    }
    
    // Record the transaction
    try {
        $stmt = $pdo->prepare("INSERT INTO transactions 
            (user_id, amount, phone, transaction_type, description, reference_id, mpesa_receipt, 
            transaction_date, first_name, middle_name, last_name, direction, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'inbound', 'completed', NOW())");
        
        $stmt->execute([
            $userId, 
            $transAmount, 
            $MSISDN, 
            $transactionType, 
            $description, 
            $referenceId,
            $transID,
            $transactionDate,
            $firstName,
            $middleName,
            $lastName
        ]);
        
        $transactionId = $pdo->lastInsertId();
        
        // Log activity
        if ($userId) {
            logActivity($userId, 'payment_received', "Received $transactionType payment of " . formatMoney($transAmount) . " via M-Pesa (Receipt: $transID)");
        } else {
            // Log for admin since we don't know the user
            logActivity(1, 'payment_received_unknown', "Received payment of " . formatMoney($transAmount) . " from $firstName $lastName ($MSISDN) via M-Pesa (Receipt: $transID)");
        }
        
        // Process the transaction based on type
        processC2BTransaction($transactionId, $transactionType, $userId, $transAmount, $referenceId);
        
        // Return success response to M-Pesa
        $result = [
            "ResultCode" => 0,
            "ResultDesc" => "Accepted"
        ];
    } catch (Exception $e) {
        error_log("Error processing C2B transaction: " . $e->getMessage());
        
        // Return error response to M-Pesa
        $result = [
            "ResultCode" => 1,
            "ResultDesc" => "Rejected: " . $e->getMessage()
        ];
    }
} else {
    // Invalid data received
    $result = [
        "ResultCode" => 1,
        "ResultDesc" => "Rejected: Invalid data format"
    ];
}

// Send response
header('Content-Type: application/json');
echo json_encode($result);

/**
 * Process C2B transaction based on transaction type
 */
function processC2BTransaction($transactionId, $transactionType, $userId, $amount, $referenceId) {
    global $pdo;
    
    // Skip processing if we don't know the user
    if (!$userId) {
        // Create a notification for admin to manually process this payment
        $stmt = $pdo->prepare("INSERT INTO admin_notifications 
            (type, title, message, is_read, created_at) 
            VALUES ('unidentified_payment', 'Unidentified Payment Received', ?, 0, NOW())");
        $stmt->execute(["A payment of " . formatMoney($amount) . " was received but could not be linked to a user. Transaction ID: $transactionId"]);
        return;
    }
    
    switch ($transactionType) {
        case 'contribution':
            // Record contribution
            $stmt = $pdo->prepare("INSERT INTO contributions 
                (user_id, amount, payment_method, transaction_id, contribution_date, status, created_at) 
                VALUES (?, ?, 'mpesa_c2b', ?, NOW(), 'approved', NOW())");
            $stmt->execute([$userId, $amount, $transactionId]);
            
            // Update user's total savings
            $stmt = $pdo->prepare("UPDATE user_accounts SET 
                total_savings = total_savings + ?, 
                last_contribution_date = NOW() 
                WHERE user_id = ?");
            $stmt->execute([$amount, $userId]);
            
            // Notify the member
            sendNotification(
                $userId,
                'Contribution Received',
                "Your contribution of " . formatMoney($amount) . " has been received and credited to your account."
            );
            break;
            
        case 'loan_repayment':
            // If reference ID is provided, use it to find the loan
            if ($referenceId) {
                $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND user_id = ?");
                $stmt->execute([$referenceId, $userId]);
                $loan = $stmt->fetch();
            } else {
                // Otherwise, find the user's active loan with the highest balance
                $stmt = $pdo->prepare("SELECT * FROM loans WHERE user_id = ? AND status = 'active' AND paid = 0 ORDER BY balance DESC LIMIT 1");
                $stmt->execute([$userId]);
                $loan = $stmt->fetch();
            }
            
            if ($loan) {
                // Record loan repayment
                $stmt = $pdo->prepare("INSERT INTO loan_repayments 
                    (loan_id, user_id, amount, payment_method, transaction_id, repayment_date, created_at) 
                    VALUES (?, ?, ?, 'mpesa_c2b', ?, NOW(), NOW())");
                $stmt->execute([$loan['id'], $userId, $amount, $transactionId]);
                
                // Update loan balance
                $newBalance = $loan['balance'] - $amount;
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
                    $userId,
                    'Loan Repayment Received',
                    "Your loan repayment of " . formatMoney($amount) . " has been received. " . 
                    ($newBalance > 0 ? "Remaining balance: " . formatMoney($newBalance) : "Your loan has been fully repaid!")
                );
            } else {
                // No active loan found, create a notification for admin
                $stmt = $pdo->prepare("INSERT INTO admin_notifications 
                    (type, title, message, is_read, created_at) 
                    VALUES ('unmatched_loan_payment', 'Unmatched Loan Payment', ?, 0, NOW())");
                $stmt->execute(["A loan payment of " . formatMoney($amount) . " was received from user #$userId but no matching active loan was found."]);
            }
            break;
            
        // Add cases for other transaction types as needed
        // The pattern would be similar to the above cases
        
        default:
            // For other transaction types, create a notification for admin to review
            $stmt = $pdo->prepare("INSERT INTO admin_notifications 
                (type, title, message, is_read, created_at) 
                VALUES ('manual_review_needed', 'Payment Needs Review', ?, 0, NOW())");
            $stmt->execute(["A payment of " . formatMoney($amount) . " was received from user #$userId as '$transactionType' and needs manual review. Transaction ID: $transactionId"]);
            break;
    }
}

/**
 * Format phone number to standard format
 */
function formatPhoneNumber($phone) {
    // Remove any non-digit characters
    $phone = preg_replace('/\D/', '', $phone);
    
    // Ensure it starts with 254 (Kenya code)
    if (substr($phone, 0, 1) == '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 3) != '254') {
        $phone = '254' . $phone;
    }
    
    return $phone;
}