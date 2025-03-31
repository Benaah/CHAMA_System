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
    header("Location: dividends.php");
    exit();
}

$action = $_POST['action'];

// Add new dividend
if ($action == 'add_dividend') {
    $member_id = $_POST['member_id'];
    $amount = floatval($_POST['amount']);
    $declaration_date = $_POST['declaration_date'];
    $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    
    // Validate input
    if (empty($member_id) || $amount <= 0 || empty($declaration_date) || empty($status)) {
        $_SESSION['admin_error'] = "All required fields must be filled and amount must be greater than zero.";
        header("Location: dividends.php");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO dividends (user_id, amount, declaration_date, payment_date, status, description, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if ($stmt->execute([$member_id, $amount, $declaration_date, $payment_date, $status, $description])) {
            $_SESSION['admin_success'] = "Dividend added successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to add dividend.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: dividends.php");
    exit();
}

// Edit dividend
if ($action == 'edit_dividend') {
    $dividend_id = $_POST['dividend_id'];
    $member_id = $_POST['member_id'];
    $amount = floatval($_POST['amount']);
    $declaration_date = $_POST['declaration_date'];
    $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    
    // Validate input
    if (empty($dividend_id) || empty($member_id) || $amount <= 0 || empty($declaration_date) || empty($status)) {
        $_SESSION['admin_error'] = "All required fields must be filled and amount must be greater than zero.";
        header("Location: dividends.php");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE dividends 
            SET user_id = ?, amount = ?, declaration_date = ?, payment_date = ?, status = ?, description = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$member_id, $amount, $declaration_date, $payment_date, $status, $description, $dividend_id])) {
            $_SESSION['admin_success'] = "Dividend updated successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to update dividend.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: dividends.php");
    exit();
}

// Delete dividend
if ($action == 'delete_dividend') {
    $dividend_id = $_POST['dividend_id'];
    
    // Validate input
    if (empty($dividend_id)) {
        $_SESSION['admin_error'] = "Invalid dividend ID.";
        header("Location: dividends.php");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM dividends WHERE id = ?");
        
        if ($stmt->execute([$dividend_id])) {
            $_SESSION['admin_success'] = "Dividend deleted successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to delete dividend.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: dividends.php");
    exit();
}

// Bulk dividends
if ($action == 'bulk_dividends') {
    $distribution_method = $_POST['distribution_method'];
    $total_amount = floatval($_POST['total_amount']);
    $declaration_date = $_POST['declaration_date'];
    $payment_date = !empty($_POST['payment_date']) ? $_POST['payment_date'] : null;
    $status = $_POST['status'];
    $description = trim($_POST['description']);
    $selected_members = $_POST['selected_members'] ?? [];
    
    // Validate input
    if ($total_amount <= 0 || empty($declaration_date) || empty($status) || empty($selected_members)) {
        $_SESSION['admin_error'] = "All required fields must be filled, amount must be greater than zero, and at least one member must be selected.";
        header("Location: dividends.php");
        exit();
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Calculate individual amounts based on distribution method
        $member_amounts = [];
        
        if ($distribution_method == 'equal') {
            // Equal distribution
            $individual_amount = $total_amount / count($selected_members);
            foreach ($selected_members as $member_id) {
                $member_amounts[$member_id] = $individual_amount;
            }
        } else if ($distribution_method == 'contribution') {
            // Based on contribution
            $total_contributions = 0;
            $member_contributions = [];
            
            // Get total contributions for selected members
            $placeholders = str_repeat('?,', count($selected_members) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT user_id, SUM(amount) as total 
                FROM contributions 
                WHERE user_id IN ($placeholders) 
                GROUP BY user_id
            ");
            $stmt->execute($selected_members);
            $contributions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($contributions as $contribution) {
                $member_contributions[$contribution['user_id']] = $contribution['total'];
                $total_contributions += $contribution['total'];
            }
            
            // Calculate proportional amounts
            if ($total_contributions > 0) {
                foreach ($selected_members as $member_id) {
                    $contribution = $member_contributions[$member_id] ?? 0;
                    $proportion = $contribution / $total_contributions;
                    $member_amounts[$member_id] = $total_amount * $proportion;
                }
            } else {
                // Fallback to equal distribution if no contributions
                $individual_amount = $total_amount / count($selected_members);
                foreach ($selected_members as $member_id) {
                    $member_amounts[$member_id] = $individual_amount;
                }
            }
        } else if ($distribution_method == 'shares') {
            // Based on shares
            $total_shares = 0;
            $member_shares = [];
            
            // Get total shares for selected members
            $placeholders = str_repeat('?,', count($selected_members) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT user_id, shares 
                FROM user_shares 
                WHERE user_id IN ($placeholders)
            ");
            $stmt->execute($selected_members);
            $shares = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($shares as $share) {
                $member_shares[$share['user_id']] = $share['shares'];
                $total_shares += $share['shares'];
            }
            
            // Calculate proportional amounts
            if ($total_shares > 0) {
                foreach ($selected_members as $member_id) {
                    $share = $member_shares[$member_id] ?? 0;
                    $proportion = $share / $total_shares;
                    $member_amounts[$member_id] = $total_amount * $proportion;
                }
            } else {
                // Fallback to equal distribution if no shares
                $individual_amount = $total_amount / count($selected_members);
                foreach ($selected_members as $member_id) {
                    $member_amounts[$member_id] = $individual_amount;
                }
            }
        }
        
        // Insert dividend records
        $stmt = $pdo->prepare("
            INSERT INTO dividends (user_id, amount, declaration_date, payment_date, status, description, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        foreach ($member_amounts as $member_id => $amount) {
            if ($amount > 0) {
                $stmt->execute([$member_id, $amount, $declaration_date, $payment_date, $status, $description]);
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['admin_success'] = "Bulk dividends distributed successfully.";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: dividends.php");
    exit();
}

// If we get here, the action was not recognized
$_SESSION['admin_error'] = "Invalid action.";
header("Location: dividends.php");
exit();