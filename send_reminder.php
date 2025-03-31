<?php
// send_reminder.php
require 'config.php';
require 'sms_service.php';

$smsService = new SMSService();
$recipients = ['+254712345678'];
$message = "Reminder: Your contribution is due tomorrow. Please ensure you make the payment.";
$smsService->sendSMS($recipients, $message);
?>
