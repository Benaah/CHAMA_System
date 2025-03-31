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
    header("Location: members.php");
    exit();
}

$action = $_POST['action'];
$redirect_url = $_POST['redirect_url'] ?? 'members.php';

// Edit member
if ($action == 'edit_member') {
    $member_id = $_POST['member_id'];
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $status = $_POST['status'];
    
    // Validate input
    if (empty($member_id) || empty($name) || empty($email) || empty($phone) || empty($status)) {
        $_SESSION['admin_error'] = "All fields are required.";
        header("Location: member_details.php?id=$member_id");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE users 
            SET name = ?, email = ?, phone = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$name, $email, $phone, $status, $member_id])) {
            $_SESSION['admin_success'] = "Member information updated successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to update member information.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: member_details.php?id=$member_id");
    exit();
}

// Reset password
if ($action == 'reset_password') {
    $member_id = $_POST['member_id'];
    $new_password = $_POST['new_password'];
    
    // Validate input
    if (empty($member_id) || empty($new_password) || strlen($new_password) < 8) {
        $_SESSION['admin_error'] = "Password must be at least 8 characters long.";
        header("Location: member_details.php?id=$member_id");
        exit();
    }
    
    try {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        
        if ($stmt->execute([$hashed_password, $member_id])) {
            $_SESSION['admin_success'] = "Password reset successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to reset password.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: member_details.php?id=$member_id");
    exit();
}

// Delete member
if ($action == 'delete_member') {
    $member_id = $_POST['member_id'];
    
    // Validate input
    if (empty($member_id)) {
        $_SESSION['admin_error'] = "Invalid member ID.";
        header("Location: members.php");
        exit();
    }
    
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete related records first (contributions, loans, etc.)
        $tables = [
            'contributions',
            'loans',
            'loan_repayments',
            'meeting_attendances',
            'savings',
            'welfare_contributions',
            'welfare_cases'
        ];
        
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("DELETE FROM $table WHERE user_id = ?");
            $stmt->execute([$member_id]);
        }
        
        // Delete the user
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        
        if ($stmt->execute([$member_id])) {
            $pdo->commit();
            $_SESSION['admin_success'] = "Member and all associated data deleted successfully.";
        } else {
            $pdo->rollBack();
            $_SESSION['admin_error'] = "Failed to delete member.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: members.php");
    exit();
}

// Add new member
if ($action == 'add_member') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $status = $_POST['status'];
    
    // Validate input
    if (empty($name) || empty($email) || empty($phone) || empty($password) || strlen($password) < 8) {
        $_SESSION['admin_error'] = "All fields are required and password must be at least 8 characters long.";
        header("Location: members.php");
        exit();
    }
    
    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['admin_error'] = "Email address already in use.";
            header("Location: members.php");
            exit();
        }
        
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, 'member', ?, NOW(), NOW())
        ");
        
        if ($stmt->execute([$name, $email, $phone, $hashed_password, $status])) {
            $_SESSION['admin_success'] = "Member added successfully.";
        } else {
            $_SESSION['admin_error'] = "Failed to add member.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: members.php");
    exit();
}

// If we get here, the action was not recognized
$_SESSION['admin_error'] = "Invalid action.";
header("Location: members.php");
exit();