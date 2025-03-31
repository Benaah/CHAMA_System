<?php
// Prevent multiple inclusions
if (!defined('FUNCTIONS_INCLUDED')) {
    define('FUNCTIONS_INCLUDED', true);
}
/**
 * Helper Functions for Agape Youth Group Application
 * 
 * This file contains common utility functions used throughout the application.
 */

/**
 * Check if a user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Format currency amounts with KES symbol
 * 
 * @param float $amount The amount to format
 * @param bool $withSymbol Whether to include the currency symbol
 * @return string Formatted currency amount
 */
function formatCurrency($amount, $withSymbol = true) {
    $formattedAmount = number_format($amount, 2, '.', ',');
    return $withSymbol ? 'KES ' . $formattedAmount : $formattedAmount;
}

/**
 * Format date in a human-readable format
 * 
 * @param string $date The date string to format
 * @param string $format The desired output format
 * @return string Formatted date
 */
function formatDate($date, $format = 'd M Y') {
    if (empty($date)) return 'N/A';
    $timestamp = strtotime($date);
    return date($format, $timestamp);
}

/**
 * Format time in a human-readable format
 * 
 * @param string $time The time string to format
 * @param string $format The desired output format
 * @return string Formatted time
 */
function formatTime($time, $format = 'h:i A') {
    if (empty($time)) return 'N/A';
    $timestamp = strtotime($time);
    return date($format, $timestamp);
}

/**
 * Calculate loan eligibility based on user's contributions
 * 
 * @param float $totalContributions User's total contributions
 * @return float Maximum eligible loan amount
 */
function calculateLoanEligibility($totalContributions) {
    return $totalContributions * MAX_LOAN_MULTIPLIER;
}

/**
 * Calculate loan interest amount
 * 
 * @param float $principal Loan principal amount
 * @param float $rate Interest rate (percentage)
 * @param int $duration Loan duration in months
 * @return float Interest amount
 */
function calculateLoanInterest($principal, $rate = LOAN_INTEREST_RATE, $duration = 1) {
    // Simple interest calculation: P * R * T / 100
    // Where P is principal, R is rate, T is time in years
    return ($principal * $rate * ($duration / 12)) / 100;
}

/**
 * Calculate loan repayment amount
 * 
 * @param float $principal Loan principal amount
 * @param float $rate Interest rate (percentage)
 * @param int $duration Loan duration in months
 * @return float Total repayment amount (principal + interest)
 */
function calculateLoanRepayment($principal, $rate = LOAN_INTEREST_RATE, $duration = 1) {
    $interest = calculateLoanInterest($principal, $rate, $duration);
    return $principal + $interest;
}

/**
 * Generate a unique reference number
 * 
 * @param string $prefix Prefix for the reference number
 * @return string Unique reference number
 */
function generateReferenceNumber($prefix = 'AGP') {
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    return $prefix . $timestamp . $random;
}

/**
 * Sanitize input data to prevent XSS attacks
 * 
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Check if user has a specific role
 * 
 * @param string|array $roles Role or array of roles to check
 * @return bool True if user has the role, false otherwise
 */
function hasRole($roles) {
    if (!isset($_SESSION['user_role'])) return false;
    
    if (is_array($roles)) {
        return in_array($_SESSION['user_role'], $roles);
    } else {
        return $_SESSION['user_role'] === $roles;
    }
}

/**
 * Check if user is an admin
 * 
 * @return bool True if user is an admin, false otherwise
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user is a manager
 * 
 * @return bool True if user is a manager, false otherwise
 */
function isManager() {
    return hasRole('manager');
}

/**
 * Check if user is either an admin or a manager
 * 
 * @return bool True if user is an admin or manager, false otherwise
 */
function isAdminOrManager() {
    return hasRole(['admin', 'manager']);
}

/**
 * Log user activity
 * 
 * @param string $action Description of the action
 * @param int $userId User ID (defaults to current user)
 * @return bool True if log was created, false otherwise
 */
function logActivity($action, $userId = null) {
    global $pdo;
    
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return false;
    
    $ip = $_SERVER['REMOTE_ADDR'];
    
    try {
        $stmt = $pdo->prepare("INSERT INTO logs (user_id, action, ip_address) VALUES (?, ?, ?)");
        return $stmt->execute([$userId, $action, $ip]);
    } catch (PDOException $e) {
        // Silently fail but could log to error file
        return false;
    }
}

/**
 * Get user's full name
 * 
 * @param int $userId User ID (defaults to current user)
 * @return string User's full name
 */
function getUserFullName($userId = null) {
    global $pdo;
    
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return 'Guest';
    
    try {
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if ($user) {
            return $user['first_name'] . ' ' . $user['last_name'];
        }
    } catch (PDOException $e) {
        // Silently fail
    }
    
    return 'Unknown User';
}

/**
 * Calculate user's total contributions
 * 
 * @param int $userId User ID (defaults to current user)
 * @return float Total contributions amount
 */
function getUserTotalContributions($userId = null) {
    global $pdo;
    
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return 0;
    
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount) as total FROM contributions WHERE user_id = ? AND status = 'approved'");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result['total'] ?: 0;
    } catch (PDOException $e) {
        // Silently fail
        return 0;
    }
}

/**
 * Calculate user's outstanding loans
 * 
 * @param int $userId User ID (defaults to current user)
 * @return float Total outstanding loan amount
 */
function getUserOutstandingLoans($userId = null) {
    global $pdo;
    
    if ($userId === null && isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
    }
    
    if (!$userId) return 0;
    
    try {
        $stmt = $pdo->prepare("SELECT SUM(amount - amount_repaid) as outstanding FROM loans WHERE user_id = ? AND status IN ('approved', 'disbursed')");
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        return $result['outstanding'] ?: 0;
    } catch (PDOException $e) {
        // Silently fail
        return 0;
    }
}

/**
 * Check if a meeting date is in the past
 * 
 * @param string $meetingDate The meeting date to check
 * @return bool True if the meeting is in the past, false otherwise
 */
function isMeetingPast($meetingDate) {
    $meetingTimestamp = strtotime($meetingDate);
    $currentTimestamp = time();
    
    return $meetingTimestamp < $currentTimestamp;
}

/**
 * Generate pagination links
 * 
 * @param int $currentPage Current page number
 * @param int $totalPages Total number of pages
 * @param string $baseUrl Base URL for pagination links
 * @param array $params Additional query parameters
 * @return string HTML pagination links
 */
function generatePagination($currentPage, $totalPages, $baseUrl, $params = []) {
    if ($totalPages <= 1) return '';
    
    $queryString = '';
    if (!empty($params)) {
        $queryString = '&' . http_build_query($params);
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1' . $queryString . '">First</a></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage - 1) . $queryString . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">First</a></li>';
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        if ($i == $currentPage) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . $queryString . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($currentPage + 1) . $queryString . '">Next</a></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $totalPages . $queryString . '">Last</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Next</a></li>';
        $html .= '<li class="page-item disabled"><a class="page-link" href="#">Last</a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Send email notification
 * 
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $message Email message body
 * @param array $headers Additional email headers
 * @return bool True if email was sent, false otherwise
 */
function sendEmail($to, $subject, $message, $headers = []) {
    // Set default headers
    $defaultHeaders = [
        'From' => 'noreply@agapeyouthgroup.org',
        'Reply-To' => 'info@agapeyouthgroup.org',
        'X-Mailer' => 'PHP/' . phpversion(),
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8'
    ];
    
    // Merge default headers with custom headers
    $headers = array_merge($defaultHeaders, $headers);
    
    // Format headers for mail() function
    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= $key . ': ' . $value . "\r\n";
    }
    
    // Send email
    return mail($to, $subject, $message, $headerString);
}

/**
 * Get meeting status label with appropriate color class
 * 
 * @param string $status Meeting status
 * @return array Associative array with label and class
 */
function getMeetingStatusLabel($status) {
    switch ($status) {
        case 'upcoming':
            return ['label' => 'Upcoming', 'class' => 'primary'];
        case 'ongoing':
            return ['label' => 'Ongoing', 'class' => 'success'];
        case 'completed':
            return ['label' => 'Completed', 'class' => 'secondary'];
        case 'cancelled':
            return ['label' => 'Cancelled', 'class' => 'danger'];
        default:
            return ['label' => ucfirst($status), 'class' => 'info'];
    }
}

/**
 * Get loan status label with appropriate color class
 * 
 * @param string $status Loan status
 * @return array Associative array with label and class
 */
function getLoanStatusLabel($status) {
    switch ($status) {
        case 'pending':
            return ['label' => 'Pending', 'class' => 'warning'];
        case 'approved':
            return ['label' => 'Approved', 'class' => 'success'];
        case 'disbursed':
            return ['label' => 'Disbursed', 'class' => 'primary'];
        case 'repaid':
            return ['label' => 'Repaid', 'class' => 'info'];
        case 'overdue':
            return ['label' => 'Overdue', 'class' => 'danger'];
        case 'rejected':
            return ['label' => 'Rejected', 'class' => 'danger'];
        default:
            return ['label' => ucfirst($status), 'class' => 'secondary'];
    }
}

/**
 * Check if a file upload is valid
 * 
 * @param array $file File upload array from $_FILES
 * @param array $allowedTypes Allowed MIME types
 * @param int $maxSize Maximum file size in bytes
 * @return array Result with status and message
 */
function validateFileUpload($file, $allowedTypes = [], $maxSize = 2097152) {
    // Check if file was uploaded
    if (!isset($file) || $file['error'] != UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'File upload failed. Error code: ' . $file['error']];
    }
    
    // Check file size
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'File is too large. Maximum size is ' . formatFileSize($maxSize)];
    }
    
    // Check file type if allowed types are specified
    if (!empty($allowedTypes)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $fileType = $finfo->file($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            return ['status' => false, 'message' => 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes)];
        }
    }
    
    return ['status' => true, 'message' => 'File is valid'];
}

/**
 * Format file size in human-readable format
 * 
 * @param int $bytes File size in bytes
 * @param int $precision Number of decimal places
 * @return string Formatted file size
 */
function formatFileSize($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Display flash messages (success, error, info, warning)
 * 
 * @return void
 */
function displayFlashMessage() {
    // Display error messages if any
    if(isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ' . $_SESSION['error'] . '
              </div>';
        unset($_SESSION['error']);
    }
    
    // Display success messages if any
    if(isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ' . $_SESSION['success'] . '
              </div>';
        unset($_SESSION['success']);
    }
    
    // Display info messages if any
    if(isset($_SESSION['info'])) {
        echo '<div class="alert alert-info alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ' . $_SESSION['info'] . '
              </div>';
        unset($_SESSION['info']);
    }
    
    // Display warning messages if any
    if(isset($_SESSION['warning'])) {
        echo '<div class="alert alert-warning alert-dismissible fade show">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                ' . $_SESSION['warning'] . '
              </div>';
        unset($_SESSION['warning']);
    }
}

/**
 * Generate a secure random token
 * 
 * @param int $length Token length
 * @return string Random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Check if a string is a valid JSON
 * 
 * @param string $string String to check
 * @return bool True if valid JSON, false otherwise
 */
function isValidJson($string) {
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

/**
 * Get the current page URL
 * 
 * @param bool $withQueryString Include query string
 * @return string Current page URL
 */
function getCurrentPageUrl($withQueryString = true) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $url = $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    
    if (!$withQueryString) {
        $url = strtok($url, '?');
    }
    
    return $url;
}

/**
 * Check if a date is valid
 * 
 * @param string $date Date string
 * @param string $format Date format
 * @return bool True if valid date, false otherwise
 */
function isValidDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * Calculate age from date of birth
 * 
 * @param string $dob Date of birth
 * @return int Age in years
 */
function calculateAge($dob) {
    $dobDate = new DateTime($dob);
    $now = new DateTime();
    $interval = $now->diff($dobDate);
    return $interval->y;
}

/**
 * Get time elapsed string (e.g., "2 days ago")
 * 
 * @param string $datetime Date and time string
 * @param bool $full Show full date
 * @return string Time elapsed string
 */
function timeElapsed($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $diff->d = floor($diff->d / 7);
    $diff->d -= $diff->d * 7;

    $string = [
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}

/**
 * Truncate text to a specified length
 * 
 * @param string $text Text to truncate
 * @param int $length Maximum length
 * @param string $append String to append if truncated
 * @return string Truncated text
 */
function truncateText($text, $length = 100, $append = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    
    $text = substr($text, 0, $length);
    $text = substr($text, 0, strrpos($text, ' '));
    
    return $text . $append;
}

/**
 * Convert a string to slug format
 * 
 * @param string $text Text to convert
 * @return string Slug
 */
function slugify($text) {
    // Replace non letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);

    // Transliterate
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);

    // Trim
    $text = trim($text, '-');

    // Remove duplicate -
    $text = preg_replace('~-+~', '-', $text);

    // Lowercase
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }

    return $text;
}

/**
 * Get a list of countries
 * 
 * @return array List of countries
 */
function getCountries() {
    return [
        'KE' => 'Kenya',
        'UG' => 'Uganda',
        'TZ' => 'Tanzania',
        'RW' => 'Rwanda',
        'ET' => 'Ethiopia',
        'SO' => 'Somalia',
        'SS' => 'South Sudan',
        'CD' => 'DR Congo',
        'ZA' => 'South Africa',
        'NG' => 'Nigeria',
        'GH' => 'Ghana',
        'US' => 'United States',
        'GB' => 'United Kingdom',
        'CA' => 'Canada',
        'AU' => 'Australia',
        // Add more countries as needed
    ];
}

/**
 * Get user IP address
 * 
 * @return string User IP address
 */
function getUserIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

/**
 * Check if the current request is an AJAX request
 * 
 * @return bool True if AJAX request, false otherwise
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Generate a CSV file from an array
 * 
 * @param array $data Data array
 * @param string $filename Output filename
 * @param array $headers CSV headers
 * @return bool True on success, false on failure
 */
function generateCsv($data, $filename, $headers = []) {
    if (empty($data)) {
        return false;
    }
    
    // Set headers for download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Add headers if provided
    if (!empty($headers)) {
        fputcsv($output, $headers);
    }
    
    // Add data rows
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    // Close the output stream
    fclose($output);
    
    return true;
}

/**
 * Get file extension from filename
 * 
 * @param string $filename Filename
 * @return string File extension
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if current user can perform an action
 * 
 * @param string $action Action to check
 * @return bool True if allowed, false otherwise
 */
function canPerformAction($action) {
    // Define permissions for different roles
    $permissions = [
        'admin' => [
            'manage_users',
            'manage_settings',
            'manage_loans',
            'manage_contributions',
            'manage_meetings',
            'manage_projects',
            'manage_welfare',
            'manage_dividends',
            'view_reports',
            'export_data',
            'backup_database'
        ],
        'manager' => [
            'manage_loans',
            'manage_contributions',
            'manage_meetings',
            'manage_projects',
            'manage_welfare',
            'view_reports'
        ],
        'member' => [
            'view_own_profile',
            'edit_own_profile',
            'view_own_contributions',
            'make_contribution',
            'view_own_loans',
            'apply_loan',
            'view_meetings',
            'register_meeting',
            'mark_attendance'
        ]
    ];
    
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $role = $_SESSION['user_role'];
    
    if (!isset($permissions[$role])) {
        return false;
    }
    
    return in_array($action, $permissions[$role]);
}

/**
 * Debug function to print variables in a readable format
 * 
 * @param mixed $var Variable to debug
 * @param bool $die Whether to stop execution after debugging
 * @return void
 */
function debug($var, $die = true) {
    echo '<pre>';
    print_r($var);
    echo '</pre>';
    
    if ($die) {
        die();
    }
}

/**
 * Get a list of available payment methods
 * 
 * @return array List of payment methods
 */
function getPaymentMethods() {
    return [
        'mpesa' => 'M-PESA',
        'bank' => 'Bank Transfer',
        'cash' => 'Cash Payment',
        'cheque' => 'Cheque'
    ];
}

/**
 * Get a list of loan types
 * 
 * @return array List of loan types
 */
function getLoanTypes() {
    return [
        'emergency' => 'Emergency Loan',
        'development' => 'Development Loan',
        'education' => 'Education Loan',
        'business' => 'Business Loan',
        'personal' => 'Personal Loan'
    ];
}

/**
 * Get a list of contribution types
 * 
 * @return array List of contribution types
 */
function getContributionTypes() {
    return [
        'monthly' => 'Monthly Contribution',
        'special' => 'Special Contribution',
        'registration' => 'Registration Fee',
        'annual' => 'Annual Membership Fee',
        'penalty' => 'Penalty Payment',
        'other' => 'Other'
    ];
}