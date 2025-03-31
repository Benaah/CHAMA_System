<?php
/* emergency_disbursement_callback.php - Callback handler for emergency assistance */
require_once '../config.php';

// Include the generic B2C callback handler
require_once 'mpesa_b2c_callback.php';

// Additional emergency assistance-specific processing can be added here
// For example, updating emergency fund balance, notifying welfare committee, etc.

// Log specific emergency disbursement activity
error_log("Emergency assistance callback processed");