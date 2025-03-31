<?php
/* mpesa_payment.php - Handle MPesa payments for all chama transactions */
include 'config.php';
require 'MpesaAPI.php'; // Custom MPesa library

// Check if user is logged in
if (!isLoggedIn()) {
    setFlashMessage('danger', 'You must be logged in to make payments');
    redirect('login.php');
    exit;
}

// Determine if this is a payment or disbursement
$transaction_direction = sanitizeInput($_POST['transaction_direction'] ?? 'payment');

if ($transaction_direction == 'payment') {
    // HANDLE PAYMENTS (Money coming into the chama)
    handlePaymentTransaction();
} else if ($transaction_direction == 'disbursement') {
    // HANDLE DISBURSEMENTS (Money going to members)
    handleDisbursementTransaction();
} else {
    setFlashMessage('danger', 'Invalid transaction direction');
    redirect('dashboard.php');
    exit;
}

/**
 * Handle payment transactions (money coming into the chama)
 */
function handlePaymentTransaction() {
    global $pdo;
    
    // Get transaction details
    $transaction_type = sanitizeInput($_POST['transaction_type'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $user_id = $_SESSION['user_id'];
    $description = sanitizeInput($_POST['description'] ?? '');
    $reference_id = sanitizeInput($_POST['reference_id'] ?? '');

    // Validate input
    $errors = [];

    if (empty($transaction_type)) {
        $errors[] = "Transaction type is required";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^(\+\d{1,3}[- ]?)?\d{9,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }

    if ($amount <= 0) {
        $errors[] = "Amount must be greater than zero";
    }

    // Format phone number for MPesa (ensure it starts with 254)
    if (substr($phone, 0, 1) == '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 1) == '+') {
        $phone = substr($phone, 1);
    }

    // Process different transaction types
    $callback_url = 'https://localhost/callbacks/mpesa_callback.php';
    $transaction_desc = '';

    switch ($transaction_type) {
        case 'contribution':
            $transaction_desc = 'Monthly Contribution';
            $callback_url = 'https://localhost/callbacks/contribution_callback.php';
            break;
            
        case 'registration':
            $transaction_desc = 'Registration Fee';
            $callback_url = 'https://localhost/callbacks/registration_callback.php';
            break;
            
        case 'loan_repayment':
            // Validate loan reference
            if (empty($reference_id)) {
                $errors[] = "Loan reference ID is required";
            } else {
                // Check if loan exists
                $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND user_id = ?");
                $stmt->execute([$reference_id, $user_id]);
                if (!$stmt->fetch()) {
                    $errors[] = "Invalid loan reference";
                }
            }
            $transaction_desc = 'Loan Repayment';
            $callback_url = 'https://localhost/callbacks/loan_repayment_callback.php';
            break;
            
        case 'penalty_payment':
            $transaction_desc = 'Penalty Payment';
            $callback_url = 'https://localhost/callbacks/penalty_callback.php';
            break;
            
        case 'investment_contribution':
            $transaction_desc = 'Investment Contribution';
            $callback_url = 'https://localhost/callbacks/investment_callback.php';
            break;
            
        case 'emergency_fund':
            $transaction_desc = 'Emergency Fund Contribution';
            $callback_url = 'https://localhost/callbacks/emergency_fund_callback.php';
            break;
            
        case 'welfare_contribution':
            $transaction_desc = 'Welfare Contribution';
            $callback_url = 'https://localhost/callbacks/welfare_callback.php';
            break;
            
        case 'project_contribution':
            // Validate project reference
            if (empty($reference_id)) {
                $errors[] = "Project reference ID is required";
            } else {
                // Check if project exists
                $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
                $stmt->execute([$reference_id]);
                if (!$stmt->fetch()) {
                    $errors[] = "Invalid project reference";
                }
            }
            $transaction_desc = 'Project Contribution';
            $callback_url = 'https://localhost/callbacks/project_callback.php';
            break;
            
        case 'withdrawal_fee':
            $transaction_desc = 'Withdrawal Processing Fee';
            $callback_url = 'https://localhost/callbacks/withdrawal_fee_callback.php';
            break;
            
        case 'event_payment':
            $transaction_desc = 'Event Payment';
            $callback_url = 'https://localhost/callbacks/event_callback.php';
            break;
            
        case 'dividend_reinvestment':
            $transaction_desc = 'Dividend Reinvestment';
            $callback_url = 'https://localhost/callbacks/dividend_callback.php';
            break;
            
        case 'dividend_payment':
            $transaction_desc = 'Dividend Payment';
            $callback_url = 'https://localhost/callbacks/dividend_payment_callback.php';
            break;
            
        default:
            $errors[] = "Invalid transaction type";
            break;
    }

    // If there are errors, redirect back with error message
    if (!empty($errors)) {
        $errorMessage = implode(', ', $errors);
        setFlashMessage('danger', $errorMessage);
        
        // Redirect back to the appropriate page based on transaction type
        switch ($transaction_type) {
            case 'contribution':
                redirect('contributions_add.php');
                break;
            case 'loan_repayment':
                redirect('loans.php');
                break;
            case 'registration':
                redirect('register.php');
                break;
            default:
                redirect('dashboard.php');
                break;
        }
        exit;
    }

    try {
        // Initiate MPesa payment
        $mpesa = new MpesaAPI();
        $response = $mpesa->STKPushSimulation(
            MPESA_SHORTCODE,
            'CustomerPayBillOnline',
            $amount,
            $phone,
            MPESA_SHORTCODE,
            $phone,
            $callback_url,
            $user_id,
            $transaction_desc
        );

        if ($response && isset($response->ResponseCode) && $response->ResponseCode == '0') {
            // Save transaction to database
            $stmt = $pdo->prepare("INSERT INTO transactions 
                (user_id, amount, phone, transaction_type, description, reference_id, checkout_request_id, direction, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'inbound', 'pending', NOW())");
            
            $checkout_request_id = $response->CheckoutRequestID ?? '';
            
            if ($stmt->execute([
                $user_id, 
                $amount, 
                $phone, 
                $transaction_type, 
                $description, 
                $reference_id,
                $checkout_request_id
            ])) {
                // Log activity
                logActivity($user_id, 'payment_initiated', "Initiated $transaction_type payment of " . formatMoney($amount));
                
                // Set success message
                setFlashMessage('success', "Payment request sent to $phone. Please check your phone to complete the transaction.");
            } else {
                setFlashMessage('danger', "Failed to record transaction. Please try again or contact support.");
            }
        } else {
            $error_message = isset($response->ResponseDescription) ? $response->ResponseDescription : "Payment request failed";
            setFlashMessage('danger', "Payment failed: " . $error_message);
        }
    } catch (Exception $e) {
        error_log("MPesa payment error: " . $e->getMessage());
        setFlashMessage('danger', "An error occurred while processing your payment. Please try again later.");
    }

    // Redirect based on transaction type
    switch ($transaction_type) {
        case 'contribution':
            redirect('contributions.php');
            break;
        case 'loan_repayment':
            redirect('loans.php');
            break;
        case 'registration':
            redirect('login.php');
            break;
        case 'project_contribution':
            redirect('projects.php');
            break;
        case 'investment_contribution':
            redirect('investments.php');
            break;
        default:
            redirect('dashboard.php');
            break;
    }
}

/**
 * Handle disbursement transactions (money going to members)
 */
function handleDisbursementTransaction() {
    global $pdo;
    
    // Only admins and treasurers can initiate disbursements
    if (!isAdmin() && !isTreasurer()) {
        setFlashMessage('danger', 'You do not have permission to initiate disbursements');
        redirect('dashboard.php');
        exit;
    }
    
    // Get transaction details
    $transaction_type = sanitizeInput($_POST['transaction_type'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $recipient_id = intval($_POST['recipient_id'] ?? 0);
    $description = sanitizeInput($_POST['description'] ?? '');
    $reference_id = sanitizeInput($_POST['reference_id'] ?? '');
    $initiator_id = $_SESSION['user_id'];
    
    // Validate input
    $errors = [];

    if (empty($transaction_type)) {
        $errors[] = "Transaction type is required";
    }

    if (empty($phone)) {
        $errors[] = "Phone number is required";
    } elseif (!preg_match('/^(\+\d{1,3}[- ]?)?\d{9,15}$/', $phone)) {
        $errors[] = "Invalid phone number format";
    }

    if ($amount <= 0) {
        $errors[] = "Amount must be greater than zero";
    }
    
    if ($recipient_id <= 0) {
        $errors[] = "Valid recipient is required";
    } else {
        // Verify recipient exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$recipient_id]);
        if (!$stmt->fetch()) {
            $errors[] = "Invalid recipient";
        }
    }

    // Format phone number for MPesa (ensure it starts with 254)
    if (substr($phone, 0, 1) == '0') {
        $phone = '254' . substr($phone, 1);
    } elseif (substr($phone, 0, 1) == '+') {
        $phone = substr($phone, 1);
    }

    // Process different disbursement types
    $callback_url = 'https://localhost/callbacks/mpesa_b2c_callback.php';
    $transaction_desc = '';
    $occasion = '';

    switch ($transaction_type) {
        case 'loan_disbursement':
            // Validate loan reference
            if (empty($reference_id)) {
                $errors[] = "Loan reference ID is required";
            } else {
                // Check if loan exists and is approved but not disbursed
                $stmt = $pdo->prepare("SELECT * FROM loans WHERE id = ? AND user_id = ? AND status = 'approved' AND disbursed = 0");
                $stmt->execute([$reference_id, $recipient_id]);
                if (!$stmt->fetch()) {
                    $errors[] = "Invalid loan reference or loan already disbursed";
                }
            }
            $transaction_desc = 'Loan Disbursement';
            $occasion = 'Loan Approval';
            $callback_url = 'https://localhost/callbacks/loan_disbursement_callback.php';
            break;
            
        case 'welfare_payment':
            $transaction_desc = 'Welfare Support Payment';
            $occasion = 'Member Welfare';
            $callback_url = 'https://localhost/callbacks/welfare_disbursement_callback.php';
            break;
            
        case 'dividend_payment':
            $transaction_desc = 'Dividend Payment';
            $occasion = 'Profit Sharing';
            $callback_url = 'https://localhost/callbacks/dividend_disbursement_callback.php';
            break;
            
        case 'savings_withdrawal':
            $transaction_desc = 'Savings Withdrawal';
            $occasion = 'Member Withdrawal';
            $callback_url = 'https://localhost/callbacks/withdrawal_disbursement_callback.php';
            break;
            
        case 'project_payout':
            $transaction_desc = 'Project Earnings Payout';
            $occasion = 'Project Returns';
            $callback_url = 'https://localhost/callbacks/project_payout_callback.php';
            break;
            
        case 'emergency_assistance':
            $transaction_desc = 'Emergency Financial Assistance';
            $occasion = 'Emergency Support';
            $callback_url = 'https://localhost/callbacks/emergency_disbursement_callback.php';
            break;
            
        default:
            $errors[] = "Invalid disbursement type";
            break;
    }

    // If there are errors, redirect back with error message
    if (!empty($errors)) {
        $errorMessage = implode(', ', $errors);
        setFlashMessage('danger', $errorMessage);
        
        // Redirect back to the appropriate page based on transaction type
        switch ($transaction_type) {
            case 'loan_disbursement':
                redirect('admin/loans_manage.php');
                break;
            case 'welfare_payment':
                redirect('admin/welfare_manage.php');
                break;
            case 'savings_withdrawal':
                redirect('admin/withdrawals_manage.php');
                break;
            default:
                redirect('pages/admin/admin_dashboard.php');
                break;
        }
        exit;
    }

    try {
        // Initiate MPesa B2C payment (Business to Customer)
        $mpesa = new MpesaAPI();
        $response = $mpesa->B2CPayment(
            MPESA_B2C_SHORTCODE,
            MPESA_B2C_SECURITY_CREDENTIAL,
            'BusinessPayment', // TransactionType
            $amount,
            $phone,
            $transaction_desc,
            $callback_url,
            $occasion
        );

        if ($response && isset($response->ResponseCode) && $response->ResponseCode == '0') {
            // Save transaction to database
            $stmt = $pdo->prepare("INSERT INTO transactions 
                (user_id, recipient_id, amount, phone, transaction_type, description, reference_id, 
                conversation_id, originator_conversation_id, direction, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'outbound', 'pending', NOW())");
            
            $conversation_id = $response->ConversationID ?? '';
            $originator_conversation_id = $response->OriginatorConversationID ?? '';
            
            if ($stmt->execute([
                $initiator_id, 
                $recipient_id,
                $amount, 
                $phone, 
                $transaction_type, 
                $description, 
                $reference_id,
                $conversation_id,
                $originator_conversation_id
            ])) {
                // Log activity
                logActivity($initiator_id, 'disbursement_initiated', "Initiated $transaction_type disbursement of " . formatMoney($amount) . " to user #$recipient_id");
                
                // Update specific records based on transaction type
                if ($transaction_type == 'loan_disbursement' && !empty($reference_id)) {
                    // Update loan as disbursed
                    $stmt = $pdo->prepare("UPDATE loans SET disbursed = 1, disbursement_date = NOW() WHERE id = ?");
                    $stmt->execute([$reference_id]);
                }
                
                // Set success message
                setFlashMessage('success', "Disbursement of " . formatMoney($amount) . " to $phone has been initiated. The recipient will receive the funds shortly.");
            } else {
                setFlashMessage('danger', "Failed to record transaction. Please try again or contact support.");
            }
        } else {
            $error_message = isset($response->ResponseDescription) ? $response->ResponseDescription : "Disbursement request failed";
            setFlashMessage('danger', "Disbursement failed: " . $error_message);
        }
    } catch (Exception $e) {
        error_log("MPesa B2C payment error: " . $e->getMessage());
        setFlashMessage('danger', "An error occurred while processing the disbursement. Please try again later.");
    }

    // Redirect based on transaction type
    switch ($transaction_type) {
        case 'loan_disbursement':
            redirect('admin/loans_manage.php');
            break;
        case 'welfare_payment':
            redirect('admin/welfare_manage.php');
            break;
        case 'savings_withdrawal':
            redirect('admin/withdrawals_manage.php');
            break;
        case 'dividend_payment':
            redirect('admin/dividends_manage.php');
            break;
        case 'project_payout':
            redirect('admin/projects_manage.php');
            break;
        default:
            redirect('pages/admin/admin_dashboard.php');
            break;
    }
}