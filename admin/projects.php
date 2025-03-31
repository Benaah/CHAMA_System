<?php
include '../config.php';
include 'header.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle project deletion if requested
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $success_message = "Project deleted successfully.";
    } catch (PDOException $e) {
        $error_message = "Error deleting project: " . $e->getMessage();
    }
}

// Handle status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';

// Prepare query based on filter
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM project_contributions WHERE project_id = p.id) as contribution_count,
          (SELECT SUM(amount) FROM project_contributions WHERE project_id = p.id) as total_contributions
          FROM projects p";

$params = [];
// Remove this condition since status doesn't exist
// if ($status_filter != 'all') {
//     $query .= " WHERE p.status = ?";
//     $params[] = $status_filter;
// }

$query .= " ORDER BY p.created_at DESC";
// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Instead of querying by status
// $stmt = $pdo->query("
//     SELECT status, COUNT(*) as count 
//     FROM projects 
//     GROUP BY status
// ");
// $status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Just get the total count
$stmt = $pdo->query("SELECT COUNT(*) as total FROM projects");
$total_projects = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Create a stats array with default values
$stats = [
    'total' => $total_projects,
    'active' => 0,
    'planning' => 0,
    'completed' => 0
];

// Skip the foreach loop that processes status counts
// foreach ($status_counts as $count) {
//     $stats[$count['status']] = $count['count'];
//     $stats['total'] += $count['count'];
// }

?>

<div class="container-fluid">

    <!-- Stats Row -->
    <div class="row">
        <!-- Total Projects Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Total Projects</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['total'] ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-project-diagram fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Active Projects Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                Active Projects</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['active'] ?? 0 ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-tasks fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planning Projects Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                Planning Projects</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['planning'] ?? 0 ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Completed Projects Card -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                Completed Projects</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $stats['completed'] ?? 0 ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <div class="col-12">
            <!-- Projects List -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">All Projects</h6>
                    <div class="dropdown no-arrow">
                        <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-filter fa-sm fa-fw text-gray-400"></i> Filter
                        </a>
                        <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                            <div class="dropdown-header">Status Filter:</div>
                            <a class="dropdown-item <?= $status_filter == 'all' ? 'active' : '' ?>" href="projects.php">All Projects</a>
                            <a class="dropdown-item <?= $status_filter == 'active' ? 'active' : '' ?>" href="projects.php?status=active">Active</a>
                            <a class="dropdown-item <?= $status_filter == 'planning' ? 'active' : '' ?>" href="projects.php?status=planning">Planning</a>
                            <a class="dropdown-item <?= $status_filter == 'completed' ? 'active' : '' ?>" href="projects.php?status=completed">Completed</a>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (isset($success_message)): ?>
                        <div class="alert alert-success"><?= $success_message ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <?php if (count($projects) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" id="projectsTable" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Target Amount</th>
                                        <th>Current Amount</th>
                                        <th>Progress</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projects as $project): ?>
                                        <?php 
                                            $progress = 0;
                                            if (isset($project['target_amount']) && $project['target_amount'] > 0) {
                                                $progress = min(100, round(($project['current_amount'] / $project['target_amount']) * 100));
                                            }
                                        ?>
                                        <tr>
                                            <td><?= $project['id'] ?></td>
                                            <td><?= htmlspecialchars($project['name'] ?? $project['title']) ?></td>
                                            <td><?= htmlspecialchars(substr($project['description'], 0, 100)) . (strlen($project['description']) > 100 ? '...' : '') ?></td>
                                            <td>KES <?= number_format($project['target_amount'] ?? 0, 2) ?></td>
                                            <td>KES <?= number_format($project['current_amount'] ?? $project['total_contributions'] ?? 0, 2) ?></td>
                                            <td>
                                                <div class="progress">
                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%"
                                                         aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100">
                                                        <?= $progress ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if (isset($project['status'])): ?>
                                                    <?php if ($project['status'] == 'completed'): ?>
                                                        <span class="badge badge-success">Completed</span>
                                                    <?php elseif ($project['status'] == 'active'): ?>
                                                        <span class="badge badge-primary">Active</span>
                                                    <?php elseif ($project['status'] == 'planning'): ?>
                                                        <span class="badge badge-info">Planning</span>
                                                    <?php else: ?>
                                                        <span class="badge badge-secondary"><?= ucfirst($project['status']) ?></span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge badge-secondary">Unknown</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M d, Y', strtotime($project['created_at'])) ?></td>
                                            <td>
                                                <a href="project_edit.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="project_view.php?id=<?= $project['id'] ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="projects.php?delete=<?= $project['id'] ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this project? This action cannot be undone.')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            No projects found. 
                            <?php if ($status_filter != 'all'): ?>
                                <a href="projects.php">View all projects</a>
                            <?php else: ?>
                                <a href="project_add.php">Add a new project</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Initialize DataTables -->
<script>
$(document).ready(function() {
    $('#projectsTable').DataTable({
        order: [[7, 'desc']], // Sort by created date by default
        pageLength: 10,
        responsive: true
    });
});
</script>

<?php include 'footer.php'; ?>