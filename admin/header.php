<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    $_SESSION['error'] = "You must be logged in as an administrator to access this page.";
    header("Location: ../login.php");
    exit();
}

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Agape Youth Group - Admin Panel</title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <!-- Custom Admin CSS -->
    <link rel="stylesheet" href="assets/css/admin-style.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        /* Admin Panel Custom Styling */
        :root {
            --primary: #4e73df;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --secondary: #858796;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        body {
            background-color: #f8f9fc;
            font-family: 'Nunito', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        }
        
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, var(--primary) 10%, #224abe 100%);
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            z-index: 1;
            transition: all 0.2s;
        }
        
        .sidebar .nav-item {
            position: relative;
        }
        
        .sidebar .nav-item .nav-link {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 700;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
        }
        
        .sidebar .nav-item .nav-link:hover {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-item .nav-link.active {
            color: #fff;
            font-weight: 700;
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .sidebar .nav-item .nav-link i {
            margin-right: 0.5rem;
            width: 1.25rem;
            text-align: center;
        }
        
        .sidebar-divider {
            border-top: 1px solid rgba(255, 255, 255, 0.15);
            margin: 1rem 0;
        }
        
        .sidebar-heading {
            color: rgba(255, 255, 255, 0.4);
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            padding: 0 1rem;
            margin-top: 1rem;
        }
        
        .content-wrapper {
            margin-left: 14rem;
            padding: 1.5rem;
            transition: all 0.2s;
        }
        
        .navbar-admin {
            background-color: #fff;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            padding: 0.5rem 1rem;
        }
        
        .navbar-admin .navbar-brand {
            color: var(--dark);
            font-weight: 700;
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
        }
        
        .dropdown-item {
            padding: 0.5rem 1.5rem;
            font-weight: 500;
        }
        
        .dropdown-item:active {
            background-color: var(--primary);
        }
        
        .card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background-color: #f8f9fc;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .border-left-primary {
            border-left: 0.25rem solid var(--primary) !important;
        }
        
        .border-left-success {
            border-left: 0.25rem solid var(--success) !important;
        }
        
        .border-left-info {
            border-left: 0.25rem solid var(--info) !important;
        }
        
        .border-left-warning {
            border-left: 0.25rem solid var(--warning) !important;
        }
        
        .border-left-danger {
            border-left: 0.25rem solid var(--danger) !important;
        }
        
        .chart-area {
            position: relative;
            height: 20rem;
            width: 100%;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 6.5rem !important;
            }
            
            .sidebar .nav-item .nav-link {
                text-align: center;
                padding: 0.75rem 1rem;
                width: 6.5rem;
            }
            
            .sidebar .nav-item .nav-link span {
                display: none;
            }
            
            .sidebar .nav-item .nav-link i {
                margin-right: 0;
                font-size: 1.25rem;
            }
            
            .sidebar .sidebar-heading {
                text-align: center;
                padding: 0;
                font-size: 0.65rem;
            }
            
            .content-wrapper {
                margin-left: 6.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .sidebar {
                width: 100% !important;
                height: auto;
                min-height: auto;
                position: relative !important;
            }
            
            .sidebar .nav-item .nav-link {
                width: 100%;
                text-align: left;
            }
            
            .sidebar .nav-item .nav-link span {
                display: inline;
            }
            
            .sidebar .nav-item .nav-link i {
                margin-right: 0.5rem;
                font-size: 1rem;
            }
            
            .content-wrapper {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="sidebar bg-gradient-primary position-fixed">
            <a class="sidebar-brand d-flex align-items-center justify-content-center py-4" href="index.php">
                <div class="sidebar-brand-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="sidebar-brand-text mx-3">Admin Panel</div>
            </a>
            
            <hr class="sidebar-divider my-0">
            
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'index.php') ? 'active' : '' ?>" href="index.php">
                        <i class="fas fa-fw fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                
                <hr class="sidebar-divider">
                
                <div class="sidebar-heading">
                    Management
                </div>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'users.php') ? 'active' : '' ?>" href="users.php">
                        <i class="fas fa-fw fa-users"></i>
                        <span>Members</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'contributions.php') ? 'active' : '' ?>" href="contributions.php">
                        <i class="fas fa-fw fa-hand-holding-usd"></i>
                        <span>Contributions</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'loans.php') ? 'active' : '' ?>" href="loans.php">
                        <i class="fas fa-fw fa-money-bill-wave"></i>
                        <span>Loans</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'projects.php') ? 'active' : '' ?>" href="projects.php">
                        <i class="fas fa-fw fa-project-diagram"></i>
                        <span>Projects</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'meetings.php') ? 'active' : '' ?>" href="meetings.php">
                        <i class="fas fa-fw fa-calendar-alt"></i>
                        <span>Meetings</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'welfare.php') ? 'active' : '' ?>" href="welfare.php">
                        <i class="fas fa-fw fa-hands-helping"></i>
                        <span>Welfare</span>
                    </a>
                </li>
                
                <hr class="sidebar-divider">
                
                <div class="sidebar-heading">
                    Reports
                </div>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'reports.php') ? 'active' : '' ?>" href="reports.php">
                        <i class="fas fa-fw fa-chart-bar"></i>
                        <span>Financial Reports</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'analytics.php') ? 'active' : '' ?>" href="analytics.php">
                        <i class="fas fa-fw fa-chart-line"></i>
                        <span>Analytics</span>
                    </a>
                </li>
                
                <hr class="sidebar-divider">
                
                <div class="sidebar-heading">
                    Settings
                </div>
                
                <li class="nav-item">
                    <a class="nav-link <?= ($current_page == 'settings.php') ? 'active' : '' ?>" href="settings.php">
                        <i class="fas fa-fw fa-cog"></i>
                        <span>System Settings</span>
                    </a>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link" href="../index.php">
                        <i class="fas fa-fw fa-home"></i>
                        <span>Back to Site</span>
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Top Navbar -->
            <nav class="navbar navbar-expand navbar-light navbar-admin mb-4">
                <button class="btn btn-link d-md-none rounded-circle mr-3" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <h1 class="h3 mb-0 text-gray-800 d-none d-sm-inline-block">
                    <?php
                        // Set page title based on current page
                        switch ($current_page) {
                            case 'index.php':
                                echo 'Dashboard';
                                break;
                            case 'users.php':
                                echo 'Member Management';
                                break;
                            case 'contributions.php':
                                echo 'Contribution Management';
                                break;
                            case 'loans.php':
                                echo 'Loan Management';
                                break;
                            case 'projects.php':
                                echo 'Project Management';
                                break;
                            case 'meetings.php':
                                echo 'Meeting Management';
                                break;
                            case 'welfare.php':
                                echo 'Welfare Management';
                                break;
                            case 'reports.php':
                                echo 'Financial Reports';
                                break;
                            case 'analytics.php':
                                echo 'Analytics';
                                break;
                            case 'settings.php':
                                echo 'System Settings';
                                break;
                            default:
                                echo 'Admin Panel';
                        }
                    ?>
                </h1>
                
                <div class="ml-auto"></div>
                
                <!-- Navbar Search -->
                <form class="d-none d-sm-inline-block form-inline ml-auto mr-md-3 my-2 my-md-0 navbar-search">
                    <div class="input-group">
                        <input type="text" class="form-control bg-light border-0 small" placeholder="Search for..." aria-label="Search">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button">
                                <i class="fas fa-search fa-sm"></i>
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Navbar Links -->
                <ul class="navbar-nav ml-auto ml-md-0">
                    <li class="nav-item dropdown no-arrow mx-1">
                        <a class="nav-link dropdown-toggle" href="#" id="alertsDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-bell fa-fw"></i>
                            <!-- Counter - Alerts -->
                            <span class="badge badge-danger badge-counter">3+</span>
                        </a>
                        <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="alertsDropdown">
                            <h6 class="dropdown-header bg-primary text-white">
                                Alerts Center
                            </h6>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <div class="mr-3">
                                    <div class="icon-circle bg-primary">
                                    <i class="fas fa-file-alt text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-gray-500">Today</div>
                                    <span class="font-weight-bold">New loan application needs approval</span>
                                </div>
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <div class="mr-3">
                                    <div class="icon-circle bg-success">
                                        <i class="fas fa-donate text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-gray-500">Yesterday</div>
                                    <span>New contribution of KES 5,000 received</span>
                                </div>
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <div class="mr-3">
                                    <div class="icon-circle bg-warning">
                                        <i class="fas fa-exclamation-triangle text-white"></i>
                                    </div>
                                </div>
                                <div>
                                    <div class="small text-gray-500">2 days ago</div>
                                    <span class="font-weight-bold">Welfare case needs attention</span>
                                </div>
                            </a>
                            <a class="dropdown-item text-center small text-gray-500" href="#">Show All Alerts</a>
                        </div>
                    </li>

                    <li class="nav-item dropdown no-arrow mx-1">
                        <a class="nav-link dropdown-toggle" href="#" id="messagesDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="fas fa-envelope fa-fw"></i>
                            <!-- Counter - Messages -->
                            <span class="badge badge-danger badge-counter">7</span>
                        </a>
                        <div class="dropdown-list dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="messagesDropdown">
                            <h6 class="dropdown-header bg-primary text-white">
                                Message Center
                            </h6>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <div class="dropdown-list-image mr-3">
                                    <img class="rounded-circle" src="../assets/images/default-avatar.png" alt="User Avatar">
                                    <div class="status-indicator bg-success"></div>
                                </div>
                                <div class="font-weight-bold">
                                    <div class="text-truncate">Hi there! I need help with my loan application.</div>
                                    <div class="small text-gray-500">John Doe · 58m</div>
                                </div>
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <div class="dropdown-list-image mr-3">
                                    <img class="rounded-circle" src="../assets/images/default-avatar.png" alt="User Avatar">
                                    <div class="status-indicator"></div>
                                </div>
                                <div>
                                    <div class="text-truncate">When is the next meeting scheduled?</div>
                                    <div class="small text-gray-500">Jane Smith · 1d</div>
                                </div>
                            </a>
                            <a class="dropdown-item d-flex align-items-center" href="#">
                                <div class="dropdown-list-image mr-3">
                                    <img class="rounded-circle" src="../assets/images/default-avatar.png" alt="User Avatar">
                                    <div class="status-indicator bg-warning"></div>
                                </div>
                                <div>
                                    <div class="text-truncate">I'd like to propose a new project for the group.</div>
                                    <div class="small text-gray-500">David Johnson · 2d</div>
                                </div>
                            </a>
                            <a class="dropdown-item text-center small text-gray-500" href="#">Read More Messages</a>
                        </div>
                    </li>

                    <div class="topbar-divider d-none d-sm-block"></div>

                    <!-- Nav Item - User Information -->
                    <li class="nav-item dropdown no-arrow">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <span class="mr-2 d-none d-lg-inline text-gray-600 small"><?= htmlspecialchars($_SESSION['username']) ?></span>
                            <img class="img-profile rounded-circle" src="../assets/images/default-avatar.png" width="32" height="32">
                        </a>
                        <!-- Dropdown - User Information -->
                        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
                            <a class="dropdown-item" href="../profile.php">
                                <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
                                Profile
                            </a>
                            <a class="dropdown-item" href="settings.php">
                                <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
                                Settings
                            </a>
                            <a class="dropdown-item" href="activity_log.php">
                                <i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i>
                                Activity Log
                            </a>
                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="../logout.php">
                                <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
                                Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </nav>
            
            <!-- Display error messages if any -->
            <?php if(isset($_SESSION['admin_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $_SESSION['admin_error']; unset($_SESSION['admin_error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Display success messages if any -->
            <?php if(isset($_SESSION['admin_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $_SESSION['admin_success']; unset($_SESSION['admin_success']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Begin Page Content -->