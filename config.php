<?php
// Prevent multiple inclusions
if (!defined('CONFIG_INCLUDED')) {
    define('CONFIG_INCLUDED', true);
    
    // Database configuration
    $host = 'db';
    $dbname = 'agape_youth_group';
    $username = 'postgres';
    $password = '.PointBlank16328';
    
    // Create PDO connection
    try {
        $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
    
    // Other configuration settings
    define('MIN_CONTRIBUTION', 1000);
    define('LOAN_INTEREST_RATE', 10);
    define('MAX_LOAN_MULTIPLIER', 3);
    define('REGISTRATION_FEE', 500);
    define('ANNUAL_MEMBERSHIP_FEE', 1000);
    define('MPESA_SHORTCODE', '123456');
    define('MPESA_B2C_SHORTCODE', '123456');
    define('MPESA_B2C_SECURITY_CREDENTIAL', 'your_security_credential');
    
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set timezone
    date_default_timezone_set('Africa/Nairobi');
    
    // Set locale for currency formatting
    setlocale(LC_MONETARY, 'en_US.UTF-8'); // Adjust as needed for your locale
    
    // Define base URL for the application
    define('BASE_URL', 'http://localhost:8080'); // Adjust as needed
}

// Always include functions.php (it should have its own protection against multiple inclusion)
require_once 'includes/functions.php';