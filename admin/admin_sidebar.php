<div class="admin-sidebar">
    <div class="list-group">
        <a href="index.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        <a href="members.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'members.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Members
        </a>
        <a href="contributions.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'contributions.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i> Contributions
        </a>
        <a href="loans.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'loans.php' ? 'active' : ''; ?>">
            <i class="fas fa-hand-holding-usd"></i> Loans
        </a>
        <a href="investments.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'investments.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i> Investments
        </a>
        <a href="meetings.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'meetings.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i> Meetings
        </a>
        <a href="announcements.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'active' : ''; ?>">
            <i class="fas fa-bullhorn"></i> Announcements
        </a>
        <a href="reports.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i> Reports
        </a>
        <a href="settings.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i> Settings
        </a>
        <a href="logs.php" class="list-group-item list-group-item-action <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : ''; ?>">
            <i class="fas fa-history"></i> Activity Logs
        </a>
    </div>
</div>