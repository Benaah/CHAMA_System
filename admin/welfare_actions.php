<?php
include '../config.php';
include 'includes/header.php';

// Check if action is set
if (!isset($_POST['action'])) {
    $_SESSION['admin_error'] = "Invalid request.";
    header("Location: welfare.php");
    exit();
}

$action = $_POST['action'];
$redirect_url = $_POST['redirect_url'] ?? 'welfare.php';

// Add new welfare case
if ($action == 'add_case') {
    $user_id = $_POST['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $amount_needed = floatval($_POST['amount_needed']);
    $status = $_POST['status'];
    
    // Validate input
    if (empty($user_id) || empty($title) || empty($description) || $amount_needed <= 0) {
        $_SESSION['admin_error'] = "All fields are required and amount must be greater than zero.";
        header("Location: $redirect_url");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO welfare_cases (user_id, title, description, amount_needed, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        if ($stmt->execute([$user_id, $title, $description, $amount_needed, $status])) {
            $_SESSION['admin_success'] = "Welfare case added successfully.";
            
            // Log the activity
            $case_id = $pdo->lastInsertId();
            $admin_id = $_SESSION['user_id'];
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $admin_id, 
                'create', 
                'welfare_case', 
                $case_id, 
                "Added new welfare case: $title"
            ]);
        } else {
            $_SESSION['admin_error'] = "Failed to add welfare case.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: $redirect_url");
    exit();
}

// Add contribution to welfare case
elseif ($action == 'add_contribution') {
    $case_id = $_POST['case_id'];
    $user_id = $_POST['user_id'];
    $amount = floatval($_POST['amount']);
    $contribution_date = $_POST['contribution_date'];
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate input
    if (empty($case_id) || empty($user_id) || $amount <= 0 || empty($contribution_date)) {
        $_SESSION['admin_error'] = "All fields are required and amount must be greater than zero.";
        header("Location: $redirect_url");
        exit();
    }
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // Insert contribution
        $stmt = $pdo->prepare("
            INSERT INTO welfare_contributions (case_id, user_id, amount, contribution_date, notes, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        if ($stmt->execute([$case_id, $user_id, $amount, $contribution_date, $notes])) {
            // Check if case should be marked as completed
            $stmt = $pdo->prepare("
                SELECT w.amount_needed, 
                       (SELECT SUM(amount) FROM welfare_contributions WHERE case_id = w.id) as total_contributions,
                       w.status
                FROM welfare_cases w
                WHERE w.id = ?
            ");
            $stmt->execute([$case_id]);
            $case = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // If the case is approved and total contributions meet or exceed the amount needed, mark as completed
            if ($case['status'] == 'approved' && $case['total_contributions'] >= $case['amount_needed']) {
                $update_stmt = $pdo->prepare("UPDATE welfare_cases SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$case_id]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['admin_success'] = "Contribution added successfully.";
            
            // Log the activity
            $contribution_id = $pdo->lastInsertId();
            $admin_id = $_SESSION['user_id'];
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $admin_id, 
                'create', 
                'welfare_contribution', 
                $contribution_id, 
                "Added contribution of KES " . number_format($amount, 2) . " to welfare case #$case_id"
            ]);
        } else {
            $pdo->rollBack();
            $_SESSION['admin_error'] = "Failed to add contribution.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: $redirect_url");
    exit();
}

// Delete welfare case
elseif ($action == 'delete_case') {
    $case_id = $_POST['case_id'];
    
    try {
        // Begin transaction
        $pdo->beginTransaction();
        
        // First delete all contributions for this case
        $stmt = $pdo->prepare("DELETE FROM welfare_contributions WHERE case_id = ?");
        $stmt->execute([$case_id]);
        
        // Then delete the case
        $stmt = $pdo->prepare("DELETE FROM welfare_cases WHERE id = ?");
        
        if ($stmt->execute([$case_id])) {
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['admin_success'] = "Welfare case deleted successfully.";
            
            // Log the activity
            $admin_id = $_SESSION['user_id'];
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $admin_id, 
                'delete', 
                'welfare_case', 
                $case_id, 
                "Deleted welfare case #$case_id"
            ]);
        } else {
            $pdo->rollBack();
            $_SESSION['admin_error'] = "Failed to delete welfare case.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: $redirect_url");
    exit();
}

// Delete contribution
elseif ($action == 'delete_contribution') {
    $contribution_id = $_POST['contribution_id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT c.*, w.id as case_id, w.status as case_status, w.amount_needed,
                  (SELECT SUM(amount) FROM welfare_contributions WHERE case_id = w.id) as total_contributions
            FROM welfare_contributions c
            JOIN welfare_cases w ON c.case_id = w.id
            WHERE c.id = ?
        ");
        $stmt->execute([$contribution_id]);
        $contribution = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contribution) {
            $_SESSION['admin_error'] = "Contribution not found.";
            header("Location: $redirect_url");
            exit();
        }
        
        // Begin transaction
        $pdo->beginTransaction();
        
        // Delete the contribution
        $stmt = $pdo->prepare("DELETE FROM welfare_contributions WHERE id = ?");
        
        if ($stmt->execute([$contribution_id])) {
            // If case was completed and now total is less than needed, revert to approved
            if ($contribution['case_status'] == 'completed' && 
                ($contribution['total_contributions'] - $contribution['amount']) < $contribution['amount_needed']) {
                $update_stmt = $pdo->prepare("UPDATE welfare_cases SET status = 'approved', updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$contribution['case_id']]);
            }
            
            // Commit transaction
            $pdo->commit();
            
            $_SESSION['admin_success'] = "Contribution deleted successfully.";
            
            // Log the activity
            $admin_id = $_SESSION['user_id'];
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $admin_id, 
                'delete', 
                'welfare_contribution', 
                $contribution_id, 
                "Deleted contribution of KES " . number_format($contribution['amount'], 2) . " from welfare case #" . $contribution['case_id']
            ]);
        } else {
            $pdo->rollBack();
            $_SESSION['admin_error'] = "Failed to delete contribution.";
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: $redirect_url");
    exit();
}

// Update welfare case
elseif ($action == 'update_case') {
    $case_id = $_POST['case_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $amount_needed = floatval($_POST['amount_needed']);
    $status = $_POST['status'];
    
    // Validate input
    if (empty($title) || empty($description) || $amount_needed <= 0) {
        $_SESSION['admin_error'] = "All fields are required and amount must be greater than zero.";
        header("Location: $redirect_url");
        exit();
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE welfare_cases 
            SET title = ?, description = ?, amount_needed = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        if ($stmt->execute([$title, $description, $amount_needed, $status, $case_id])) {
            $_SESSION['admin_success'] = "Welfare case updated successfully.";
            
            // Log the activity
            $admin_id = $_SESSION['user_id'];
            $log_stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $log_stmt->execute([
                $admin_id, 
                'update', 
                'welfare_case', 
                $case_id, 
                "Updated welfare case: $title"
            ]);
        } else {
            $_SESSION['admin_error'] = "Failed to update welfare case.";
        }
    } catch (PDOException $e) {
        $_SESSION['admin_error'] = "Database error: " . $e->getMessage();
    }
    
    header("Location: $redirect_url");
    exit();
}

else {
    $_SESSION['admin_error'] = "Invalid action.";
    header("Location: welfare.php");
    exit();
}