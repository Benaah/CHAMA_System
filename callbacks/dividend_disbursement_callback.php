<?php
/* dividend_disbursement_callback.php - Callback handler for dividend payments */
require_once '../config.php';

// Include the generic B2C callback handler
require_once 'mpesa_b2c_callback.php';

// Additional dividend-specific processing can be added here
// For example, updating dividend distribution records, generating tax documents, etc.

// Log specific dividend disbursement activity
error_log("Dividend payment callback processed");