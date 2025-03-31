        </main>
    </div>
    
    <!-- Footer -->
    <footer class="footer py-5" style="background: linear-gradient(135deg, #1e5799 0%, #207cca 51%, #2989d8 100%);">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="text-white font-weight-bold">Agape Youth Group</h5>
                    <p class="text-white-50">Empowering young people through financial growth, unity, and sustainable development.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white mr-3 social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white mr-3 social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white mr-3 social-icon"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white social-icon"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="col-md-2 mb-4">
                    <h5 class="text-white font-weight-bold">Quick Links</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="index.php" class="text-white-50"><i class="fas fa-chevron-right mr-2 small"></i>Home</a></li>
                        <li><a href="about.php" class="text-white-50"><i class="fas fa-chevron-right mr-2 small"></i>About Us</a></li>
                        <li><a href="programs.php" class="text-white-50"><i class="fas fa-chevron-right mr-2 small"></i>Programs</a></li>
                        <li><a href="faq.php" class="text-white-50"><i class="fas fa-chevron-right mr-2 small"></i>FAQ</a></li>
                        <li><a href="contact.php" class="text-white-50"><i class="fas fa-chevron-right mr-2 small"></i>Contact Us</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h5 class="text-white font-weight-bold">Member Resources</h5>
                    <ul class="list-unstyled footer-links">
                        <li><a href="login.php" class="text-white-50"><i class="fas fa-sign-in-alt mr-2"></i>Member Login</a></li>
                        <li><a href="register.php" class="text-white-50"><i class="fas fa-user-plus mr-2"></i>Join Agape Youth Group</a></li>
                        <li><a href="resources.php" class="text-white-50"><i class="fas fa-file-alt mr-2"></i>Financial Resources</a></li>
                    </ul>
                </div>
                
                <div class="col-md-3 mb-4">
                    <h5 class="text-white font-weight-bold">Contact Information</h5>
                    <address class="text-white-50">
                        <p><i class="fas fa-map-marker-alt mr-2 text-white"></i> Luanda, Vihiga, Kenya</p>
                        <p><i class="fas fa-phone mr-2 text-white"></i> +254 712 345 678</p>
                        <p><i class="fas fa-envelope mr-2 text-white"></i> info@agapeyouthchama.org</p>
                    </address>
                </div>
            </div>
            
            <hr class="border-light opacity-25">
            
            <div class="row">
                <div class="col-md-6">
                    <p class="text-white-50 mb-0">&copy; <?php echo date('Y'); ?> Agape Youth Group. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-right">
                    <p class="text-white-50 mb-0">Designed with <i class="fas fa-heart text-danger"></i> for our members</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS, Popper.js, and jQuery -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- AOS Animation Library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <!-- Chart.js for financial reports -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.10.25/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.10.25/js/dataTables.bootstrap4.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.2.9/js/responsive.bootstrap4.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/main.js"></script>
    
    <script>
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });
        
        // Initialize DataTables
        $(document).ready(function() {
            if ($.fn.DataTable) {
                $('.datatable').DataTable({
                    responsive: true,
                    lengthMenu: [[10, 25, 50, -1], [10, 25, 50, "All"]],
                    autoWidth: false,
                    columnDefs: [
                        { responsivePriority: 1, targets: 0 },
                        { responsivePriority: 2, targets: -1 }
                    ]
                });
            }
            
            // Initialize tooltips
            $('[data-toggle="tooltip"]').tooltip();
            
            // Initialize popovers
            $('[data-toggle="popover"]').popover();
            
            // Handle dropdown submenu on hover
            $('.dropdown-submenu').hover(function() {
                $(this).find('.dropdown-menu').first().stop(true, true).delay(250).slideDown();
            }, function() {
                $(this).find('.dropdown-menu').first().stop(true, true).delay(100).slideUp();
            });
            
            // Auto-dismiss alerts after 5 seconds
            window.setTimeout(function() {
                $(".alert-dismissible").fadeTo(500, 0).slideUp(500, function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Add hover effect to footer links
            $('.footer-links a').hover(function() {
                $(this).removeClass('text-white-50').addClass('text-white');
                $(this).find('i').animate({marginLeft: '5px'}, 200);
            }, function() {
                $(this).removeClass('text-white').addClass('text-white-50');
                $(this).find('i').animate({marginLeft: '0'}, 200);
            });
            
            // Add hover effect to social icons
            $('.social-icon').hover(function() {
                $(this).css('transform', 'translateY(-5px)');
                $(this).css('transition', 'all 0.3s ease');
            }, function() {
                $(this).css('transform', 'translateY(0)');
            });
        });
    </script>
</body>
</html>