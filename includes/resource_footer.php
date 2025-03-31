    <footer class="bg-dark text-white py-4 mt-5" style="background: linear-gradient(135deg, #4b421b 0%, #24252a 51%, #070716 100%)">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5>AGAPE YOUTH GROUP</h5>
                    <p class="small">Empowering youth through financial inclusion and community support.</p>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../index.php" class="text-white">Home</a></li>
                        <?php if (isset($_SESSION['user_id'])): ?>
                            <li><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../dashboard.php" class="text-white">Dashboard</a></li>
                            <li><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../profile.php" class="text-white">Profile</a></li>
                            <li><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../logout.php" class="text-white">Logout</a></li>
                        <?php else: ?>
                            <li><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../login.php" class="text-white">Login</a></li>
                            <li><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../register.php" class="text-white">Register</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>../resources.php" class="text-white">Resources</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-3">
                    <h5>Contact Us</h5>
                    <address class="small">
                        <i class="fas fa-map-marker-alt mr-2"></i> Luanda, Vihiga, Kenya<br>
                        <i class="fas fa-phone mr-2"></i> +254 123 456 789<br>
                        <i class="fas fa-envelope mr-2"></i> info@agapechama.org
                    </address>
                </div>
            </div>
            <hr>
            <div class="text-center small">
                <p>&copy; <?php echo date('Y'); ?> AGAPE YOUTH GROUP. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom JS -->
    <script src="<?php echo str_contains($current_page, '/') ? '../' : ''; ?>assets/js/script.js"></script>
</body>
</html>