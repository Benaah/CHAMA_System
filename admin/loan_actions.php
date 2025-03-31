<?php
include '../config.php';
include 'includes/header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if action is set
if (!isset($_POST['action'])) {
    $_SESSION['admin_error'] = "Invalid request.";
    header("Location: loans.php");
    exit();
}

$action = $_POST['action'];

// Add new loan
if ($action == 'add_loan') {
    $member_id = $_POST['member_id'];
    $amount = floatval($_POST['amount']);
    $interest_rate = floatval($_POST['interest_rate']);
    $duration = intval($_POST['duration']);
    $application_date = $_POST['application_date'];
    $purpose = trim($_POST['purpose']);
    $status = $_POST['status'];
    $approval_date = !empty($_POST['approval_date']) ? $_POST['approval_date'] : null;
    $disbursement_date = !empty($_POST['disbursement_date']) ? $_POST['disbursement_date'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    
    // Validate input
    if (empty($member_id) || $amount <= 0 || $interest_rate < 0 || $duration <= 0 || empty($application_date) || empty($purpose) || empty($status)) {
        $_SESSION['admin_error'] = "All required fields must be filled and values must be valid.";
        header("Location: loans.php");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO loans (user_id, amount, interest_rate, duration, application_date, purpose, status, approval_date, disbursement_date, due_date, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if ($stmt->execute([$member_id, $amount, $interest_rate, $duration, $application_date, $purpose, $status, $approval_date, $disbursement_date, $due_date])) {
            $_SESSION['admin_success'] = "Loan added successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to add loan.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: loans.php");
    exit();
}

// Edit loan
if ($action == 'edit_loan') {
    $loan_id = $_POST['loan_id'];
    $member_id = $_POST['member_id'];
    $amount = floatval($_POST['amount']);
    $interest_rate = floatval($_POST['interest_rate']);
    $duration = intval($_POST['duration']);
    $application_date = $_POST['application_date'];
    $purpose = trim($_POST['purpose']);
    $status = $_POST['status'];
    $approval_date = !empty($_POST['approval_date']) ? $_POST['approval_date'] : null;
    $disbursement_date = !empty($_POST['disbursement_date']) ? $_POST['disbursement_date'] : null;
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    
    // Validate input
    if (empty($loan_id) || empty($member_id) || $amount <= 0 || $interest_rate < 0 || $duration <= 0 || empty($application_date) || empty($purpose) || empty($status)) {
        $_SESSION['admin_error'] = "All required fields must be filled and values must be valid.";
        header("Location: loans.php");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE loans 
            SET user_id = ?, amount = ?, interest_rate = ?, duration = ?, application_date = ?, 
                purpose = ?, status = ?, approval_date = ?, disbursement_date = ?, due_date = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$member_id, $amount, $interest_rate, $duration, $application_date, $purpose, $status, $approval_date, $disbursement_date, $due_date, $loan_id])) {
            $_SESSION['admin_success'] = "Loan updated successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to update loan.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: loans.php");
    exit();
}

// Add loan repayment
if ($action == 'add_repayment') {
    $loan_id = $_POST['loan_id'];
    $amount = floatval($_POST['amount']);
    $payment_date = $_POST['payment_date'];
    $payment_method = $_POST['payment_method'];
    $reference_number = trim($_POST['reference_number'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $mark_paid = isset($_POST['mark_paid']) ? true : false;
    
    // Validate input
    if (empty($loan_id) || $amount <= 0 || empty($payment_date) || empty($payment_method)) {
        $_SESSION['admin_error'] = "All required fields must be filled and amount must be greater than zero.";
        header("Location: loans.php");
        exit();
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Insert repayment record
        $stmt = $pdo->prepare("
            INSERT INTO loan_repayments (loan_id, amount, payment_date, payment_method, reference_number, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([$loan_id, $amount, $payment_date, $payment_method, $reference_number, $notes]);
        
        // If mark as paid is checked, update loan status
        if ($mark_paid) {
            $stmt = $pdo->prepare("UPDATE loans SET status = 'paid', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$loan_id]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['admin_success'] = "Loan repayment recorded successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: loans.php");
    exit();
}

// Delete loan
if ($action == 'delete_loan') {
    $loan_id = $_POST['loan_id'];
    
    // Validate input
    if (empty($loan_id)) {
        $_SESSION['admin_error'] = "Invalid loan ID.";
        header("Location: loans.php");
        exit();
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete repayments first
        $stmt = $pdo->prepare("DELETE FROM loan_repayments WHERE loan_id = ?");
        $stmt->execute([$loan_id]);
        
        // Delete the loan
        $stmt = $pdo->prepare("DELETE FROM loans WHERE id = ?");
        
        if ($stmt->execute([$loan_id])) {
            $pdo->commit();
            $_SESSION['admin_success'] = "Loan and all associated repayments deleted successfully.";
        } else {
            $pdo->rollBack();
            $_SESSION['admin_error'] = "Failed to delete loan.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: loans.php");
    exit();
}

// If we get here, the action was not recognized
$_SESSION['admin_error'] = "Invalid action.";
header("Location: loans.php");
exit();