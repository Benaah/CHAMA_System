<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Define which pages require authentication
$restricted_pages = [
    'dashboard.php',
    'profile.php',
    'contributions.php',
    'loans.php',
    'meetings.php',
    'meeting_details.php',
    'projects.php',
    'welfare.php',
    'dividends.php',
    'withdrawals.php',
    'savings.php'
];

// Get current page filename
$current_page = basename($_SERVER['PHP_SELF']);

// Check if current page is restricted and user is not logged in
if (in_array($current_page, $restricted_pages) && !isset($_SESSION['user_id'])) {
    // Store the requested URL for redirection after login
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    
    // Set error message
    $_SESSION['error'] = "You must be logged in to access this page.";
    
    // Redirect to login page
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Agape-Portal</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Datepicker CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.25/css/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.2.9/css/responsive.bootstrap4.min.css">
    <style>
        /* Custom header styling */
        .navbar-custom {
            background: linear-gradient(135deg, #4b421b 0%, #24252a 51%, #070716 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            padding: 0.7rem 1rem;
        }
        .navbar-brand {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .nav-item {
            margin: 0 2px;
        }
        .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 4px;
            padding: 0.5rem 1rem;
        }
        .nav-link:hover {
            background-color: #376a63;
            text-transform: var(#d7eae2);
            transform: translateY(-2px);
        }
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        .dropdown-item {
            padding: 0.6rem 1.5rem;
            transition: all 0.2s ease;
        }
        .dropdown-item:hover {
            background-color: #d3e7e8;
            transform: translateX(5px);
        }
        .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
            color: #376a63;
        }
        .btn-auth {
            border-radius: 20px;
            font-weight: 600;
            padding: 0.4rem 1.2rem;
            transition: all 0.3s ease;
        }
        .btn-login {
            background-color: transparent;
            border: 2px solid white;
        }
        .btn-register {
            background-color: white;
            color: #bbb2ef !important;
            border: 2px solid white;
        }
        .btn-auth:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .dropdown-submenu {
            position: relative;
        }
        .dropdown-submenu .dropdown-menu {
            top: 0;
            left: 100%;
            margin-top: -1px;
        }
        .dropdown-submenu:hover > .dropdown-menu {
            display: block;
        }
        .dropdown-toggle::after {
            vertical-align: middle;
        }
        .active-nav-item {
            background-color: rgba(255,255,255,0.2);
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-custom">
        <div class="container">
            <a class="navbar-brand rounded">
                <img src="assets/images/logo.png" alt="Agape" height="40" class="mr-2">
                Agape-Portal
            </a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarMain">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarMain">
                <ul class="navbar-nav ml-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <!-- Dashboard for authenticated users -->
                        <li class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active-nav-item' : ''; ?>">
                            <a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                        </li>
                        
                        <!-- Transactions Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo (in_array($current_page, ['contributions.php', 'loans.php', 'dividends.php', 'withdrawals.php', 'savings.php'])) ? 'active-nav-item' : ''; ?>" href="#" id="transactionsDropdown" role="button" data-toggle="dropdown">
                                <i class="fas fa-exchange-alt"></i> Transactions
                            </a>
                            <div class="dropdown-menu" aria-labelledby="transactionsDropdown">
                                <a class="dropdown-item <?php echo ($current_page == 'contributions.php') ? 'active' : ''; ?>" href="contributions.php">
                                    <i class="fas fa-hand-holding-usd"></i> Contributions
                                </a>
                                <a class="dropdown-item <?php echo ($current_page == 'loans.php') ? 'active' : ''; ?>" href="loans.php">
                                    <i class="fas fa-money-bill-wave"></i> Loans
                                </a>
                                <a class="dropdown-item <?php echo ($current_page == 'dividends.php') ? 'active' : ''; ?>" href="dividends.php">
                                    <i class="fas fa-chart-pie"></i> Dividends
                                </a>
                                <a class="dropdown-item <?php echo ($current_page == 'withdrawals.php') ? 'active' : ''; ?>" href="withdrawals.php">
                                    <i class="fas fa-money-check"></i> Withdrawals
                                </a>
                                <a class="dropdown-item <?php echo ($current_page == 'savings.php') ? 'active' : ''; ?>" href="savings.php">
                                    <i class="fas fa-piggy-bank"></i> Savings
                                </a>
                            </div>
                        </li>
                        
                        <!-- Group Activities Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo (in_array($current_page, ['meetings.php', 'meeting_details.php', 'projects.php', 'welfare.php'])) ? 'active-nav-item' : ''; ?>" href="#" id="activitiesDropdown" role="button" data-toggle="dropdown">
                                <i class="fas fa-users"></i> Group Activities
                            </a>
                            <div class="dropdown-menu" aria-labelledby="activitiesDropdown">
                                <a class="dropdown-item <?php echo ($current_page == 'meetings.php' || $current_page == 'meeting_details.php') ? 'active' : ''; ?>" href="meetings.php">
                                    <i class="fas fa-calendar-alt"></i> Meetings
                                </a>
                                <a class="dropdown-item <?php echo ($current_page == 'projects.php') ? 'active' : ''; ?>" href="projects.php">
                                    <i class="fas fa-project-diagram"></i> Projects
                                </a>
                                <a class="dropdown-item <?php echo ($current_page == 'welfare.php') ? 'active' : ''; ?>" href="welfare.php">
                                    <i class="fas fa-hands-helping"></i> Welfare
                                </a>
                            </div>
                        </li>
                        
                        <!-- Resources Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="resourcesDropdown" role="button" data-toggle="dropdown">
                                <i class="fas fa-book"></i> Resources
                            </a>
                            <div class="dropdown-menu" aria-labelledby="resourcesDropdown">
                                <a class="dropdown-item" href="resources.php">
                                    <i class="fas fa-file-alt"></i> Financial Resources
                                </a>
                                <a class="dropdown-item" href="faq.php">
                                    <i class="fas fa-question-circle"></i> FAQ
                                </a>
                            </div>
                        </li>
                        
                        <!-- About & Contact -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo (in_array($current_page, ['about.php', 'contact.php'])) ? 'active-nav-item' : ''; ?>" href="#" id="infoDropdown" role="button" data-toggle="dropdown">
                                <i class="fas fa-info-circle"></i> Info
                            </a>
                            <div class="dropdown-menu" aria-labelledby="infoDropdown">
                                <a class="dropdown-item <?php echo ($current_page == 'about.php') ? 'active' : ''; ?>" href="about.php">
                                    <i class="fas fa-building"></i> About Us
                                </a>
                                <a class="dropdown-item <?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>" href="contact.php">
                                    <i class="fas fa-envelope"></i> Contact Us
                                </a>
                            </div>
                        </li>
                        
                        <!-- User Profile Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle <?php echo ($current_page == 'profile.php') ? 'active-nav-item' : ''; ?>" href="#" id="userDropdown" role="button" data-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> 
                                <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <div class="dropdown-menu dropdown-menu-right">
                                <a class="dropdown-item <?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>" href="profile.php">
                                    <i class="fas fa-user"></i> My Profile
                                </a>
                                
                                <?php if($_SESSION['user_role'] == 'admin'): ?>
                                    <a class="dropdown-item" href="admin/index.php">
                                        <i class="fas fa-cogs"></i> Admin Panel
                                    </a>
                                <?php elseif($_SESSION['user_role'] == 'manager'): ?>
                                    <a class="dropdown-item" href="admin/index.php">
                                        <i class="fas fa-tasks"></i> Management Panel
                                    </a>
                                <?php endif; ?>
                                
                                <div class="dropdown-divider"></div>
                                
                                <a class="dropdown-item" href="logout.php">
                                    <i class="fas fa-sign-out-alt"></i> Logout
                                </a>
                            </div>
                        </li>
                    <?php else: ?>
                        <!-- Navigation for unauthenticated users -->
                        <li class="nav-item <?php echo ($current_page == 'index.php') ? 'active-nav-item' : ''; ?>">
                            <a class="nav-link" href="index.php"><i class="fas fa-home"></i> Home</a>
                        </li>
                        <li class="nav-item <?php echo ($current_page == 'about.php') ? 'active-nav-item' : ''; ?>">
                            <a class="nav-link" href="about.php"><i class="fas fa-info-circle"></i> About Us</a>
                        </li>
                        <li class="nav-item <?php echo ($current_page == 'programs.php') ? 'active-nav-item' : ''; ?>">
                            <a class="nav-link" href="programs.php"><i class="fas fa-list-alt"></i> Programs</a>
                        </li>
                        <li class="nav-item <?php echo ($current_page == 'faq.php') ? 'active-nav-item' : ''; ?>">
                            <a class="nav-link" href="faq.php"><i class="fas fa-question-circle"></i> FAQ</a>
                        </li>
                        <li class="nav-item <?php echo ($current_page == 'contact.php') ? 'active-nav-item' : ''; ?>">
                            <a class="nav-link" href="contact.php"><i class="fas fa-envelope"></i> Contact</a>
                        </li>
                            <li class="nav-item">
                            <a class="nav-link btn btn-auth btn-login ml-2 <?php echo ($current_page == 'login.php') ? 'active-nav-item' : ''; ?>" href="login.php">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link btn btn-auth btn-register ml-2 <?php echo ($current_page == 'register.php') ? 'active-nav-item' : ''; ?>" href="register.php">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Page content container -->
    <div class="content-wrapper">
        <!-- Display error messages if any -->
        <?php if(isset($_SESSION['error'])): ?>
            <div class="container mt-5 pt-4">
                <div class="alert alert-danger alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Display success messages if any -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="container mt-5 pt-4">
                <div class="alert alert-success alert-dismissible fade show">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- Main Content -->
        <main class="py-4 mt-5">