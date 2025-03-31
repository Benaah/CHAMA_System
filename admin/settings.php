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

// Define settings categories
$categories = [
    'general' => 'General Settings',
    'financial' => 'Financial Settings',
    'email' => 'Email Configuration',
    'security' => 'Security Settings',
    'appearance' => 'Appearance Settings'
];

// Fetch current settings
$settings = [];
$query = "SELECT * FROM settings ORDER BY category, `key`";
$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $settings[$row['category']][$row['key']] = [
            'value' => $row['value'],
            'description' => $row['description'] ?? '',
            'type' => $row['type'] ?? 'text'
        ];
    }
}

// Update settings
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updated = 0;
    $errors = 0;
    
    // Get the category being updated
    $category = isset($_POST['category']) ? $_POST['category'] : '';
    
    if (!empty($category) && isset($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            // Validate the setting based on type
            $valid = true;
            $setting_type = isset($settings[$category][$key]['type']) ? $settings[$category][$key]['type'] : 'text';
            
            switch ($setting_type) {
                case 'email':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $valid = false;
                    }
                    break;
                case 'number':
                    if (!empty($value) && !is_numeric($value)) {
                        $valid = false;
                    }
                    break;
                case 'url':
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                        $valid = false;
                    }
                    break;
            }
            
            if ($valid) {
                // Escape the value to prevent SQL injection
                $escaped_value = mysqli_real_escape_string($conn, $value);
                $escaped_key = mysqli_real_escape_string($conn, $key);
                $escaped_category = mysqli_real_escape_string($conn, $category);
                
                // Update the setting
                $update_query = "UPDATE settings SET value='$escaped_value' WHERE `key`='$escaped_key' AND category='$escaped_category'";
                if (mysqli_query($conn, $update_query)) {
                    $updated++;
                    
                    // Log the change
                    $user_id = $_SESSION['user_id'];
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $log_action = "Updated setting: $category.$key to '$value'";
                    mysqli_query($conn, "INSERT INTO logs (user_id, action, ip_address) VALUES ('$user_id', '$log_action', '$ip')");
                } else {
                    $errors++;
                }
            } else {
                $errors++;
                $_SESSION['error_fields'][] = $key;
            }
        }
    }
    
    if ($updated > 0 && $errors == 0) {
        $_SESSION['success'] = "Settings updated successfully!";
    } elseif ($updated > 0) {
        $_SESSION['warning'] = "Some settings were updated, but there were $errors errors.";
    } else {
        $_SESSION['error'] = "Failed to update settings. Please check your input.";
    }
    
    header('Location: settings.php?category=' . urlencode($category));
    exit;
}

// Get the current category from the URL or default to 'general'
$current_category = isset($_GET['category']) && array_key_exists($_GET['category'], $categories) 
    ? $_GET['category'] 
    : 'general';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - AGAPE CHAMA</title>
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
                        <h4>System Settings</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['warning'])): ?>
                            <div class="alert alert-warning"><?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-3">
                                <div class="list-group mb-4">
                                    <?php foreach ($categories as $cat_key => $cat_name): ?>
                                        <a href="settings.php?category=<?php echo $cat_key; ?>" 
                                           class="list-group-item list-group-item-action <?php echo ($current_category == $cat_key) ? 'active' : ''; ?>">
                                            <?php echo $cat_name; ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="col-md-9">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><?php echo $categories[$current_category]; ?></h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (isset($settings[$current_category]) && !empty($settings[$current_category])): ?>
                                            <form method="POST">
                                                <input type="hidden" name="category" value="<?php echo $current_category; ?>">
                                                
                                                <?php foreach ($settings[$current_category] as $key => $setting): ?>
                                                    <div class="mb-3">
                                                        <label for="<?php echo $key; ?>" class="form-label">
                                                            <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                                        </label>
                                                        
                                                        <?php if ($setting['type'] == 'textarea'): ?>
                                                            <textarea name="settings[<?php echo $key; ?>]" id="<?php echo $key; ?>" 
                                                                class="form-control <?php echo isset($_SESSION['error_fields']) && in_array($key, $_SESSION['error_fields']) ? 'is-invalid' : ''; ?>" 
                                                                rows="3"><?php echo htmlspecialchars($setting['value']); ?></textarea>
                                                        
                                                        <?php elseif ($setting['type'] == 'boolean'): ?>
                                                            <select name="settings[<?php echo $key; ?>]" id="<?php echo $key; ?>" class="form-select">
                                                                <option value="1" <?php echo $setting['value'] == '1' ? 'selected' : ''; ?>>Yes</option>
                                                                <option value="0" <?php echo $setting['value'] == '0' ? 'selected' : ''; ?>>No</option>
                                                            </select>
                                                        
                                                        <?php elseif ($setting['type'] == 'select' && isset($setting['options'])): ?>
                                                            <select name="settings[<?php echo $key; ?>]" id="<?php echo $key; ?>" class="form-select">
                                                                <?php foreach (explode(',', $setting['options']) as $option): ?>
                                                                    <option value="<?php echo trim($option); ?>" <?php echo $setting['value'] == trim($option) ? 'selected' : ''; ?>>
                                                                        <?php echo trim($option); ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        
                                                        <?php else: ?>
                                                            <input type="<?php echo $setting['type']; ?>" 
                                                                name="settings[<?php echo $key; ?>]" 
                                                                id="<?php echo $key; ?>" 
                                                                value="<?php echo htmlspecialchars($setting['value']); ?>" 
                                                                class="form-control <?php echo isset($_SESSION['error_fields']) && in_array($key, $_SESSION['error_fields']) ? 'is-invalid' : ''; ?>">
                                                        <?php endif; ?>
                                                        
                                                        <?php if (!empty($setting['description'])): ?>
                                                            <div class="form-text text-muted"><?php echo $setting['description']; ?></div>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (isset($_SESSION['error_fields']) && in_array($key, $_SESSION['error_fields'])): ?>
                                                            <div class="invalid-feedback">Please enter a valid value for this field.</div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endforeach; ?>
                                                
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="fas fa-save"></i> Save <?php echo $categories[$current_category]; ?>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="alert alert-info">No settings found for this category.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php 
    // Clear any error fields
    if (isset($_SESSION['error_fields'])) {
        unset($_SESSION['error_fields']);
    }
    ?>
    
    <?php include_once('../includes/admin_footer.php'); ?>
</body>
</html>