<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AGAPE GROUP - Financial Resources</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../assets/css/style.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container">
            <a class="navbar-brand" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../index.php">
                <i class="fas fa-users mr-2"></i> AGAPE YOUTH GROUP
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <!-- Links for authenticated users -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../dashboard.php">
                                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../resources.php">
                                <i class="fas fa-book mr-1"></i> Resources
                            </a>
                        </li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle mr-1"></i> <?php echo htmlspecialchars($_SESSION['username']); ?>
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="navbarDropdown">
                                <li><a class="dropdown-item" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../profile.php">Profile</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Links for non-authenticated users -->
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../index.php">
                                <i class="fas fa-home mr-1"></i> Home
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../resources.php">
                                <i class="fas fa-book mr-1"></i> Resources
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../login.php">
                                <i class="fas fa-sign-in-alt mr-1"></i> Login
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../register.php">
                                <i class="fas fa-user-plus mr-1"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="bg-light py-2 mt-5">
        <div class="container">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../resources.php">Resources</a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?php echo ucfirst(str_replace('_', ' ', pathinfo($current_page, PATHINFO_FILENAME))); ?></li>
                </ol>
            </nav>
        </div>
    </div>