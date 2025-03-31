<?php
// Get current year for copyright
$current_year = date('Y');

// Get database stats if needed
if (isset($conn) && $conn) {
    // Count members
    $members_query = "SELECT COUNT(*) as total FROM members";
    $members_result = mysqli_query($conn, $members_query);
    $members_count = mysqli_fetch_assoc($members_result)['total'];
    
    // Count active loans
    $loans_query = "SELECT COUNT(*) as total FROM loans WHERE status IN ('approved', 'disbursed')";
    $loans_result = mysqli_query($conn, $loans_query);
    $loans_count = mysqli_fetch_assoc($loans_result)['total'];
    
    // Get total loan amount
    $loan_amount_query = "SELECT SUM(amount) as total FROM loans WHERE status IN ('approved', 'disbursed')";
    $loan_amount_result = mysqli_query($conn, $loan_amount_query);
    $loan_amount = mysqli_fetch_assoc($loan_amount_result)['total'] ?: 0;
}
?>

<footer class="footer mt-5 py-3 bg-dark text-white">
    <div class="container">
        <div class="row">
            <div class="col-md-4">
                <h5>AGAPE CHAMA</h5>
                <p class="small">Empowering members through financial inclusion and community support.</p>
                <p class="small">
                    <i class="fas fa-envelope"></i> Email: info@agapechama.org<br>
                    <i class="fas fa-phone"></i> Phone: +254 700 000000<br>
                    <i class="fas fa-map-marker-alt"></i> Location: Luanda, Vihiga, Kenya
                </p>
            </div>
            
            <div class="col-md-4">
                <?php if (isset($members_count)): ?>
                <h5>System Statistics</h5>
                <ul class="list-unstyled">
                    <li>Total Members: <?php echo $members_count; ?></li>
                    <li>Active Loans: <?php echo $loans_count; ?></li>
                    <li>Total Loan Amount: KES <?php echo number_format($loan_amount, 2); ?></li>
                    <li>Last Updated: <?php echo date('d M Y, H:i'); ?></li>
                </ul>
                <?php else: ?>
                <h5>Admin Resources</h5>
                <ul class="list-unstyled">
                    <li><a href="../admin/backup.php" class="text-white">Database Backup</a></li>
                    <li><a href="../admin/settings.php" class="text-white">System Settings</a></li>
                    <li><a href="../admin/user_manage.php" class="text-white">User Management</a></li>
                    <li><a href="../admin/logs.php" class="text-white">System Logs</a></li>
                </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <hr class="my-2">
        
        <div class="row">
            <div class="col-md-6">
                <p class="small mb-0">&copy; <?php echo $current_year; ?> AGAPE YOUTH GROUP. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="small mb-0">
                    <a href="../privacy_policy.php" class="text-white">Privacy Policy</a> | 
                    <a href="../terms.php" class="text-white">Terms of Service</a>
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Admin-specific JavaScript -->
<script>
// Confirm before deleting items
function confirmDelete(url, itemName) {
    if (confirm('Are you sure you want to delete this ' + itemName + '? This action cannot be undone.')) {
        window.location.href = url;
    }
    return false;
}

// Toggle sidebar on mobile
document.addEventListener('DOMContentLoaded', function() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }
    
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
});
</script>

<!-- Font Awesome for icons -->
<script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>

<!-- Bootstrap JS Bundle with Popper -->
<script src="../js/bootstrap.bundle.min.js"></script>

<!-- Custom Admin Scripts -->
<script src="../js/admin-scripts.js"></script>