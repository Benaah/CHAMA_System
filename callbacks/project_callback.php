<?php
/* project_callback.php - Callback handler for project contributions */
require_once '../config.php';

// Include the generic callback handler
require_once 'mpesa_callback.php';

// Additional project-specific processing
if (isset($transaction) && $transaction && $resultCode == 0 && !empty($transaction['reference_id'])) {
    // Get project details
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$transaction['reference_id']]);
    $project = $stmt->fetch();
    
    if ($project) {
        // Record the project contribution
        $stmt = $pdo->prepare("INSERT INTO project_contributions 
            (project_id, user_id, amount, payment_method, transaction_id, contribution_date, created_at) 
            VALUES (?, ?, ?, 'mpesa', ?, NOW(), NOW())");
        $stmt->execute([
            $project['id'], 
            $transaction['user_id'], 
            $transaction['amount'], 
            $transaction['id']
        ]);
        
        // Update project funding
        $stmt = $pdo->prepare("UPDATE projects SET 
            total_raised = total_raised + ?,
            last_contribution_date = NOW(),
            updated_at = NOW()
            WHERE id = ?");
        $stmt->execute([$transaction['amount'], $project['id']]);
        
        // Update user's project share
        $stmt = $pdo->prepare("INSERT INTO project_shares 
            (project_id, user_id, amount, contribution_date, created_at) 
            VALUES (?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE 
            amount = amount + ?, 
            updated_at = NOW()");
        $stmt->execute([
            $project['id'], 
            $transaction['user_id'], 
            $transaction['amount'],
            $transaction['amount']
        ]);
        
        // Check if project is now fully funded
        if (($project['total_raised'] + $transaction['amount']) >= $project['funding_goal']) {
            $stmt = $pdo->prepare("UPDATE projects SET status = 'funded' WHERE id = ?");
            $stmt->execute([$project['id']]);
            
            // Notify project admin
            $adminId = $project['created_by'];
            sendNotification(
                $adminId,
                'Project Fully Funded',
                "The project '" . $project['title'] . "' has reached its funding goal of " . formatMoney($project['funding_goal']) . "."
            );
        }
        
        // Notify the member
        sendNotification(
            $transaction['user_id'],
            'Project Contribution Received',
            "Your contribution of " . formatMoney($transaction['amount']) . " to project '" . $project['title'] . "' has been received."
        );
        
        // Log specific project activity
        error_log("Project contribution callback processed for project #" . $project['id']);
    }
}