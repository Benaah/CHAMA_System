<?php
// Include authentication check
include_once('../auth.php');

// Check for admin privileges
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header('Location: ../index.php');
    exit;
}

// Database connection
require_once '../config.php';

// Create backups directory if it doesn't exist
$backup_dir = 'backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle backup request
if (isset($_POST['create_backup'])) {
    $timestamp = date('Ymd_His');
    $backup_file = $backup_dir . '/agape_backup_' . $timestamp . '.sql';
    
    // Use mysqldump with proper escaping to prevent command injection
    $command = sprintf(
        'mysqldump --user=%s --password=%s --host=%s %s > %s',
        escapeshellarg(DB_USER),
        escapeshellarg(DB_PASSWORD),
        escapeshellarg(DB_HOST),
        escapeshellarg(DB_NAME),
        escapeshellarg($backup_file)
    );
    
    // Execute the command
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        // Log the backup action
        $user_id = $_SESSION['user_id'];
        $ip = $_SERVER['REMOTE_ADDR'];
        mysqli_query($conn, "INSERT INTO logs (user_id, action, ip_address) VALUES ('$user_id', 'Created database backup: $backup_file', '$ip')");
        
        $_SESSION['success'] = "Backup created successfully. <a href='$backup_file'>Download</a>";
    } else {
        $_SESSION['error'] = "Backup failed. Check server permissions.";
    }
}

// Handle backup deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $file = $backup_dir . '/' . basename($_GET['delete']);
    
    // Validate that the file is actually a backup file
    if (file_exists($file) && preg_match('/^agape_backup_\d{8}_\d{6}\.sql$/', basename($file))) {
        if (unlink($file)) {
            // Log the deletion
            $user_id = $_SESSION['user_id'];
            $ip = $_SERVER['REMOTE_ADDR'];
            mysqli_query($conn, "INSERT INTO logs (user_id, action, ip_address) VALUES ('$user_id', 'Deleted database backup: $file', '$ip')");
            
            $_SESSION['success'] = "Backup file deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete backup file.";
        }
    } else {
        $_SESSION['error'] = "Invalid backup file.";
    }
    
    header('Location: backup.php');
    exit;
}

// Handle backup restoration
if (isset($_POST['restore_backup']) && !empty($_POST['backup_file'])) {
    $file = $backup_dir . '/' . basename($_POST['backup_file']);
    
    // Validate that the file is actually a backup file
    if (file_exists($file) && preg_match('/^agape_backup_\d{8}_\d{6}\.sql$/', basename($file))) {
        // Use mysql command to restore
        $command = sprintf(
            'mysql --user=%s --password=%s --host=%s %s < %s',
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASSWORD),
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_NAME),
            escapeshellarg($file)
        );
        
        exec($command, $output, $return_var);
        
        if ($return_var === 0) {
            // Log the restoration
            $user_id = $_SESSION['user_id'];
            $ip = $_SERVER['REMOTE_ADDR'];
            mysqli_query($conn, "INSERT INTO logs (user_id, action, ip_address) VALUES ('$user_id', 'Restored database from backup: $file', '$ip')");
            
            $_SESSION['success'] = "Database restored successfully from backup.";
        } else {
            $_SESSION['error'] = "Restoration failed. Check server permissions.";
        }
    } else {
        $_SESSION['error'] = "Invalid backup file.";
    }
    
    header('Location: backup.php');
    exit;
}

// Get list of existing backups
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (preg_match('/^agape_backup_(\d{8})_(\d{6})\.sql$/', $file, $matches)) {
            $date = DateTime::createFromFormat('Ymd_His', $matches[1] . '_' . $matches[2]);
            $backups[] = [
                'filename' => $file,
                'date' => $date->format('Y-m-d H:i:s'),
                'size' => round(filesize($backup_dir . '/' . $file) / (1024 * 1024), 2) // Size in MB
            ];
        }
    }
    
    // Sort backups by date (newest first)
    usort($backups, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup & Restore - AGAPE CHAMA</title>
    <link rel="stylesheet" href="../css/bootstrap.min.css">
    <link rel="stylesheet" href="../css/styles.css">
</head>
<body>
    <?php include_once('../includes/admin_header.php'); ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4>Database Backup & Restore</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-success text-white">
                                        <h5>Create Backup</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Create a full backup of the database. This process may take a few moments depending on the database size.</p>
                                        <form method="POST">
                                            <button type="submit" name="create_backup" class="btn btn-success">
                                                <i class="fas fa-database"></i> Create Backup
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-warning text-dark">
                                        <h5>Restore Backup</h5>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-danger"><strong>Warning:</strong> Restoring a backup will overwrite all current data. This action cannot be undone.</p>
                                        <?php if (count($backups) > 0): ?>
                                            <form method="POST" onsubmit="return confirm('Are you sure you want to restore this backup? All current data will be lost!');">
                                                <div class="mb-3">
                                                    <select name="backup_file" class="form-select" required>
                                                        <option value="">Select a backup file</option>
                                                        <?php foreach ($backups as $backup): ?>
                                                            <option value="<?php echo htmlspecialchars($backup['filename']); ?>">
                                                                <?php echo htmlspecialchars($backup['date']); ?> (<?php echo $backup['size']; ?> MB)
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <button type="submit" name="restore_backup" class="btn btn-warning">
                                                    <i class="fas fa-undo"></i> Restore Selected Backup
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="alert alert-info">No backup files available for restoration.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5>Available Backups</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($backups) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th>Date & Time</th>
                                                    <th>File Name</th>
                                                    <th>Size</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($backups as $backup): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($backup['date']); ?></td>
                                                        <td><?php echo htmlspecialchars($backup['filename']); ?></td>
                                                        <td><?php echo $backup['size']; ?> MB</td>
                                                        <td>
                                                            <a href="<?php echo $backup_dir . '/' . $backup['filename']; ?>" class="btn btn-sm btn-primary" download>
                                                                <i class="fas fa-download"></i> Download
                                                            </a>
                                                            <a href="backup.php?delete=<?php echo urlencode($backup['filename']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this backup file?')">
                                                                <i class="fas fa-trash"></i> Delete
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info">No backup files found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include_once('../includes/admin_footer.php'); ?>
</body>
</html>