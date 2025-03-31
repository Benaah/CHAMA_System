<?php
include '../includes/header.php';
include '../config.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: ../dashboard.php");
    exit();
}

// Create api_keys table if it doesn't exist
try {
    $stmt = $pdo->prepare("SELECT to_regclass('public.api_keys')");
    $stmt->execute();
    $tableExists = $stmt->fetchColumn();
    
    if (!$tableExists) {
        $sql = "CREATE TABLE api_keys (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            api_key VARCHAR(64) NOT NULL UNIQUE,
            access_level VARCHAR(50) NOT NULL DEFAULT 'read',
            user_id INTEGER REFERENCES users(id),
            status VARCHAR(50) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_used TIMESTAMP
        )";
        $pdo->exec($sql);
    }
} catch (PDOException $e) {
    error_log("Error checking/creating api_keys table: " . $e->getMessage());
}

// Handle API key generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_key'])) {
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $accessLevel = filter_input(INPUT_POST, 'access_level', FILTER_SANITIZE_STRING);
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if (empty($name) || empty($accessLevel)) {
        $_SESSION['error'] = "Name and access level are required.";
    } else {
        try {
            // Generate a random API key
            $apiKey = bin2hex(random_bytes(32));
            
            // Insert new API key
            $stmt = $pdo->prepare("
                INSERT INTO api_keys (name, api_key, access_level, user_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$name, $apiKey, $accessLevel, $userId]);
            
            $_SESSION['success'] = "API key generated successfully.";
            
            // Redirect to avoid form resubmission
            header("Location: api_management.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error generating API key: " . $e->getMessage();
        }
    }
}

// Handle API key revocation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revoke_key'])) {
    $keyId = filter_input(INPUT_POST, 'key_id', FILTER_VALIDATE_INT);
    
    if (!$keyId) {
        $_SESSION['error'] = "Invalid API key ID.";
    } else {
        try {
            // Revoke API key
            $stmt = $pdo->prepare("UPDATE api_keys SET status = 'revoked' WHERE id = ?");
            $stmt->execute([$keyId]);
            
            $_SESSION['success'] = "API key revoked successfully.";
            
            // Redirect to avoid form resubmission
            header("Location: api_management.php");
            exit();
        } catch (Exception $e) {
            $_SESSION['error'] = "Error revoking API key: " . $e->getMessage();
        }
    }
}

// Get all API keys
try {
    $stmt = $pdo->prepare("
        SELECT k.*, u.username, u.email 
        FROM api_keys k
        LEFT JOIN users u ON k.user_id = u.id
        ORDER BY k.created_at DESC
    ");
    $stmt->execute();
    $apiKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving API keys: " . $e->getMessage();
    $apiKeys = [];
}

// Get all users for dropdown
try {
    $stmt = $pdo->prepare("
        SELECT id, username, email, first_name, last_name
        FROM users
        WHERE status = 'active'
        ORDER BY username
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $_SESSION['error'] = "Error retrieving users: " . $e->getMessage();
    $users = [];
}
?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">API Key Management</h6>
                    <button class="btn btn-primary" data-toggle="modal" data-target="#generateKeyModal">
                        <i class="fas fa-key mr-1"></i> Generate New API Key
                    </button>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered" id="apiKeysTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>API Key</th>
                                    <th>Access Level</th>
                                    <th>Associated User</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Last Used</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($apiKeys as $key): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($key['name']); ?></td>
                                        <td>
                                            <div class="input-group">
                                                <input type="text" class="form-control api-key-field" value="<?php echo htmlspecialchars($key['api_key']); ?>" readonly>
                                                <div class="input-group-append">
                                                    <button class="btn btn-outline-secondary copy-btn" type="button" data-toggle="tooltip" title="Copy to clipboard">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $key['access_level'] == 'admin' ? 'danger' : 
                                                    ($key['access_level'] == 'write' ? 'warning' : 'info'); 
                                            ?>">
                                                <?php echo ucfirst($key['access_level']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($key['user_id']): ?>
                                                <?php echo htmlspecialchars($key['username'] . ' (' . $key['email'] . ')'); ?>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?php 
                                                echo $key['status'] == 'active' ? 'success' : 'danger'; 
                                            ?>">
                                                <?php echo ucfirst($key['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($key['created_at'])); ?></td>
                                        <td>
                                            <?php if ($key['last_used']): ?>
                                                <?php echo date('M d, Y H:i', strtotime($key['last_used'])); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($key['status'] == 'active'): ?>
                                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to revoke this API key? This action cannot be undone.');">
                                                    <input type="hidden" name="key_id" value="<?php echo $key['id']; ?>">
                                                    <button type="submit" name="revoke_key" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-ban"></i> Revoke
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" disabled>
                                                    <i class="fas fa-ban"></i> Revoked
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                
                                <?php if (empty($apiKeys)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center">No API keys found. Generate a new key to get started.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">API Documentation</h6>
                </div>
                <div class="card-body">
                    <h5>API Endpoints</h5>
                    <p>The following endpoints are available for integration with third-party systems:</p>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Endpoint</th>
                                    <th>Methods</th>
                                    <th>Description</th>
                                    <th>Required Access</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><code>/api/?endpoint=members</code></td>
                                    <td>GET, POST, PUT, DELETE</td>
                                    <td>Manage CHAMA members</td>
                                    <td>Read (GET), Write/Admin (POST, PUT, DELETE)</td>
                                </tr>
                                <tr>
                                    <td><code>/api/?endpoint=contributions</code></td>
                                    <td>GET, POST</td>
                                    <td>View and record contributions</td>
                                    <td>Read (GET), Write/Admin (POST)</td>
                                </tr>
                                <tr>
                                    <td><code>/api/?endpoint=loans</code></td>
                                    <td>GET, POST, PUT</td>
                                    <td>Manage loans and repayments</td>
                                    <td>Read (GET), Write/Admin (POST, PUT)</td>
                                </tr>
                                <tr>
                                    <td><code>/api/?endpoint=meetings</code></td>
                                    <td>GET, POST, PUT, DELETE</td>
                                    <td>Manage meetings and attendance</td>
                                    <td>Read (GET), Write/Admin (POST, PUT, DELETE)</td>
                                </tr>
                                <tr>
                                    <td><code>/api/?endpoint=savings</code></td>
                                    <td>GET, POST</td>
                                    <td>View and record savings</td>
                                    <td>Read (GET), Write/Admin (POST)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <h5 class="mt-4">Authentication</h5>
                    <p>All API requests must include the API key in the <code>X-Api-Key</code> header:</p>
                    <pre><code>X-Api-Key: your_api_key_here</code></pre>
                    
                    <h5 class="mt-4">Response Format</h5>
                    <p>All API responses are in JSON format and include a <code>success</code> boolean indicating whether the request was successful.</p>
                    <p>Successful responses include a <code>data</code> object with the requested information.</p>
                    <p>Error responses include an <code>error</code> string with details about what went wrong.</p>
                    
                    <h5 class="mt-4">For More Information</h5>
                    <p>For detailed API documentation, visit <a href="/api/?endpoint=docs" target="_blank">/api/?endpoint=docs</a>.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Generate API Key Modal -->
<div class="modal fade" id="generateKeyModal" tabindex="-1" role="dialog" aria-labelledby="generateKeyModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="generateKeyModalLabel">Generate New API Key</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="name">Key Name</label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g., Mobile App Integration">
                        <small class="form-text text-muted">A descriptive name to identify this API key's purpose.</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="access_level">Access Level</label>
                        <select class="form-control" id="access_level" name="access_level" required>
                            <option value="read">Read Only</option>
                            <option value="write">Read & Write</option>
                            <option value="admin">Admin (Full Access)</option>
                        </select>
                        <small class="form-text text-muted">
                            <strong>Read Only:</strong> Can only retrieve data<br>
                            <strong>Read & Write:</strong> Can retrieve and modify data<br>
                            <strong>Admin:</strong> Full access to all API endpoints
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id">Associated User (Optional)</label>
                        <select class="form-control" id="user_id" name="user_id">
                            <option value="">None</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username'] . ' (' . $user['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">If this API key is for a specific user, select them here.</small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <strong>Important:</strong> The API key will only be shown once after generation. Make sure to copy it immediately.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" name="generate_key" class="btn btn-primary">Generate Key</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize DataTable
    $('#apiKeysTable').DataTable({
        order: [[5, 'desc']], // Sort by created date by default
        responsive: true
    });
    
    // Initialize tooltips
    $('[data-toggle="tooltip"]').tooltip();
    
    // Copy API key to clipboard
    $('.copy-btn').click(function() {
        var apiKeyField = $(this).closest('.input-group').find('.api-key-field');
        apiKeyField.select();
        document.execCommand('copy');
        
        // Show tooltip with "Copied!" text
        $(this).attr('data-original-title', 'Copied!').tooltip('show');
        
        // Reset tooltip text after 2 seconds
        var btn = $(this);
        setTimeout(function() {
            btn.attr('data-original-title', 'Copy to clipboard').tooltip('hide');
        }, 2000);
    });
});
</script>

<?php include '../includes/footer.php'; ?>