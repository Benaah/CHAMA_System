<?php
/* project_payout_callback.php - Callback handler for project earnings payouts */
require_once '../config.php';

// Include the generic B2C callback handler
require_once 'mpesa_b2c_callback.php';

// Additional project payout-specific processing can be added here
// For example, updating project investment records, calculating ROI, etc.

// Log specific project payout activity
error_log("Project payout callback processed");