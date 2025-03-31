<?php
// sms_service.php
class SMSService {
    private $username;
    private $apiKey;
    
    public function __construct() {
        $this->username = $_ENV['AT_USERNAME'];
        $this->apiKey = $_ENV['AT_API_KEY'];
    }
    
    public function sendSMS($recipients, $message) {
        // Prepare the data for sending SMS via Africa's Talking REST API
        $url = "https://api.africastalking.com/version1/messaging";
        $data = http_build_query([
            'username' => $this->username,
            'to' => implode(',', (array)$recipients),
            'message' => $message
        ]);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apiKey: " . $this->apiKey,
            "Content-Type: application/x-www-form-urlencoded"
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }
}
?>
