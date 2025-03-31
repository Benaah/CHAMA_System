<?php
require_once 'config.php';
require_once 'includes/functions.php';
require_once 'auth.php'; // Require authentication

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get announcements with pagination and join with users table to get poster information
$stmt = $pdo->prepare("
    SELECT a.*, u.username, u.first_name, u.last_name 
    FROM announcements a
    LEFT JOIN users u ON a.created_by = u.id
    ORDER BY a.created_at DESC 
    LIMIT ? OFFSET ?
");
$stmt->execute([$records_per_page, $offset]);
$announcements = $stmt->fetchAll();

// Get total number of announcements for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM announcements");
$stmt->execute();
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Include header
include 'includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Announcements</h4>
                </div>
                <div class="card-body">
                    <?php
                    // Display flash messages
                    if(isset($_SESSION['error'])) {
                        echo '<div class="alert alert-danger alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                ' . $_SESSION['error'] . '
                              </div>';
                        unset($_SESSION['error']);
                    }
                    
                    if(isset($_SESSION['success'])) {
                        echo '<div class="alert alert-success alert-dismissible fade show">
                                <button type="button" class="close" data-dismiss="alert">&times;</button>
                                ' . $_SESSION['success'] . '
                              </div>';
                        unset($_SESSION['success']);
                    }
                    ?>
                    
                    <!-- Announcements List -->
                    <?php if (count($announcements) > 0): ?>
                        <div class="announcements-list">
                            <?php foreach ($announcements as $announcement): ?>
                                <div class="announcement-item mb-4">
                                    <div class="card">
                                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                            <span class="badge badge-primary">
                                                <?php echo formatDate($announcement['created_at']); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                            
                                            <?php if (!empty($announcement['attachment'])): ?>
                                                <div class="mt-3">
                                                    <a href="uploads/announcements/<?php echo htmlspecialchars($announcement['attachment']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                        <i class="fas fa-paperclip"></i> View Attachment
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer text-muted">
                                            Posted by: 
                                            <?php 
                                            if (!empty($announcement['first_name']) && !empty($announcement['last_name'])) {
                                                echo htmlspecialchars($announcement['first_name'] . ' ' . $announcement['last_name']);
                                            } elseif (!empty($announcement['username'])) {
                                                echo htmlspecialchars($announcement['username']);
                                            } else {
                                                echo 'Admin';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <nav aria-label="Announcements pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="announcements.php?page=1">&laquo; First</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="announcements.php?page=<?php echo $page - 1; ?>">Previous</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#">&laquo; First</a>
                                        </li>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#">Previous</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php
                                    // Calculate range of page numbers to display
                                    $start_page = max(1, $page - 2);
                                    $end_page = min($total_pages, $page + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                            <a class="page-link" href="announcements.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $total_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="announcements.php?page=<?php echo $page + 1; ?>">Next</a>
                                        </li>
                                        <li class="page-item">
                                            <a class="page-link" href="announcements.php?page=<?php echo $total_pages; ?>">Last &raquo;</a>
                                        </li>
                                    <?php else: ?>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#">Next</a>
                                        </li>
                                        <li class="page-item disabled">
                                            <a class="page-link" href="#">Last &raquo;</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <p>No announcements available at this time.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>