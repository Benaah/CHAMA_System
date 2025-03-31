<?php
// API Entry Point
header('Content-Type: application/json');
require_once '../config.php';
require_once '../includes/functions.php';

// API Authentication
function authenticateApiRequest() {
    $headers = getallheaders();
    
    // Check for API key in header
    if (!isset($headers['X-Api-Key'])) {
        return false;
    }
    
    global $pdo;
    $apiKey = $headers['X-Api-Key'];
    
    // Verify API key
    $stmt = $pdo->prepare("SELECT * FROM api_keys WHERE api_key = ? AND status = 'active'");
    $stmt->execute([$apiKey]);
    $apiKeyData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$apiKeyData) {
        return false;
    }
    
    // Update last used timestamp
    $stmt = $pdo->prepare("UPDATE api_keys SET last_used = NOW() WHERE id = ?");
    $stmt->execute([$apiKeyData['id']]);
    
    return $apiKeyData;
}

// Route API requests
$requestMethod = $_SERVER['REQUEST_METHOD'];
$endpoint = isset($_GET['endpoint']) ? $_GET['endpoint'] : '';

// Authenticate all requests except documentation
if ($endpoint !== 'docs') {
    $apiAuth = authenticateApiRequest();
    if (!$apiAuth) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Invalid or missing API key.']);
        exit;
    }
}

// Handle different endpoints
switch ($endpoint) {
    case 'members':
        include 'endpoints/members.php';
        break;
        
    case 'contributions':
        include 'endpoints/contributions.php';
        break;
        
    case 'loans':
        include 'endpoints/loans.php';
        break;
        
    case 'meetings':
        include 'endpoints/meetings.php';
        break;
        
    case 'savings':
        include 'endpoints/savings.php';
        break;
        
    case 'docs':
        include 'docs.php';
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Endpoint not found']);
        break;
}
?>