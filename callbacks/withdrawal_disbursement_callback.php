<?php
/* withdrawal_disbursement_callback.php - Callback handler for savings withdrawals */
require_once '../config.php';

// Include the generic B2C callback handler
require_once 'mpesa_b2c_callback.php';

// Additional withdrawal-specific processing can be added here
// For example, updating member's savings balance, generating receipt, etc.

// Log specific withdrawal disbursement activity
error_log("Savings withdrawal callback processed");