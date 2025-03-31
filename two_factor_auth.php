<?php
// two_factor_auth.php
require 'config.php';
require 'sms_service.php';
session_start();

// When a user successfully logs in, generate an OTP.
function generateOTP($length = 6) {
    $otp = '';
    for ($i = 0; $i < $length; $i++) {
        $otp .= mt_rand(0, 9);
    }
    return $otp;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp'])) {
    // Validate OTP entered by the user.
    $enteredOTP = trim($_POST['otp']);
    if (isset($_SESSION['otp']) && $_SESSION['otp'] === $enteredOTP && time() < $_SESSION['otp_expiration']) {
        // OTP is valid; mark 2FA as complete.
        $_SESSION['2fa_verified'] = true;
        header("Location: dashboard.php");
        exit();
    } else {
        $error = "Invalid or expired OTP.";
    }
} else {
    // Generate and send OTP
    $otp = generateOTP();
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiration'] = time() + 300; // OTP valid for 5 minutes
    
    // Assume the user's phone number is stored in the session from the login process.
    $userPhone = $_SESSION['user']['phone'] ?? null;
    if ($userPhone) {
        $smsService = new SMSService();
        $smsService->sendSMS($userPhone, "Your verification code is: " . $otp);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Two-Factor Authentication</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-5">
  <h2>Two-Factor Authentication</h2>
  <?php if(isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <p>Please enter the OTP sent to your registered phone number.</p>
  <form method="post" action="two_factor_auth.php">
    <div class="form-group">
      <label for="otp">OTP</label>
      <input type="text" name="otp" id="otp" class="form-control" maxlength="6" required>
    </div>
    <button type="submit" class="btn btn-primary">Verify</button>
  </form>
</div>
</body>
</html>
