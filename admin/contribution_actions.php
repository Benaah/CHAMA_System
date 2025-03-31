<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Check if action is set
if (!isset($_POST['action'])) {
    $_SESSION['admin_error'] = "Invalid request.";
    header("Location: contributions.php");
    exit();
}

$action = $_POST['action'];
$redirect_url = $_POST['redirect_url'] ?? 'contributions.php';

// Add new contribution
if ($action == 'add_contribution') {
    $member_id = $_POST['member_id'];
    $amount = floatval($_POST['amount']);
    $contribution_date = $_POST['contribution_date'];
    $payment_method = $_POST['payment_method'];
    $reference_number = trim($_POST['reference_number'] ?? '');
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate input
    if (empty($member_id) || $amount <= 0 || empty($contribution_date) || empty($payment_method) || empty($status)) {
        $_SESSION['admin_error'] = "All required fields must be filled and amount must be greater than zero.";
        header("Location: $redirect_url");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO contributions (user_id, amount, contribution_date, payment_method, reference_number, status, notes, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if ($stmt->execute([$member_id, $amount, $contribution_date, $payment_method, $reference_number, $status, $notes])) {
            $_SESSION['admin_success'] = "Contribution added successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to add contribution.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: $redirect_url");
    exit();
}

// Edit contribution
if ($action == 'edit_contribution') {
    $contribution_id = $_POST['contribution_id'];
    $member_id = $_POST['member_id'];
    $amount = floatval($_POST['amount']);
    $contribution_date = $_POST['contribution_date'];
    $payment_method = $_POST['payment_method'];
    $reference_number = trim($_POST['reference_number'] ?? '');
    $status = $_POST['status'];
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate input
    if (empty($contribution_id) || empty($member_id) || $amount <= 0 || empty($contribution_date) || empty($payment_method) || empty($status)) {
        $_SESSION['admin_error'] = "All required fields must be filled and amount must be greater than zero.";
        header("Location: $redirect_url");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE contributions 
            SET user_id = ?, amount = ?, contribution_date = ?, payment_method = ?, 
                reference_number = ?, status = ?, notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$member_id, $amount, $contribution_date, $payment_method, $reference_number, $status, $notes, $contribution_id])) {
            $_SESSION['admin_success'] = "Contribution updated successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to update contribution.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: $redirect_url");
    exit();
}

// Delete contribution
if ($action == 'delete_contribution') {
    $contribution_id = $_POST['contribution_id'];
    
    // Validate input
    if (empty($contribution_id)) {
        $_SESSION['admin_error'] = "Invalid contribution ID.";
        header("Location: $redirect_url");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM contributions WHERE id = ?");
        
        if ($stmt->execute([$contribution_id])) {
            $_SESSION['admin_success'] = "Contribution deleted successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to delete contribution.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: $redirect_url");
    exit();
}

// If we get here, the action was not recognized
$_SESSION['admin_error'] = "Invalid action.";
header("Location: contributions.php");
exit();