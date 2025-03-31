<?php
/* welfare_disbursement_callback.php - Callback handler for welfare payments */
require_once '../config.php';

// Include the generic B2C callback handler
require_once 'mpesa_b2c_callback.php';

// Additional welfare-specific processing can be added here
// For example, updating welfare application status, notifying welfare committee, etc.

// Log specific welfare disbursement activity
error_log("Welfare payment callback processed");