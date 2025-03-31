<?php
/* loan_disbursement_callback.php - Callback handler for loan disbursements */
require_once '../config.php';

// Include the generic B2C callback handler
require_once 'mpesa_b2c_callback.php';

// Additional loan-specific processing can be added here
// For example, sending SMS notification to borrower, updating loan status, etc.

// Log specific loan disbursement activity
error_log("Loan disbursement callback processed");