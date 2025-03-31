<?php
// Members API Endpoint

// Handle different request methods
switch ($requestMethod) {
    case 'GET':
        // Get members list or specific member
        if (isset($_GET['id'])) {
            getMember($_GET['id']);
        } else {
            getMembers();
        }
        break;
        
    case 'POST':
        // Create new member
        createMember();
        break;
        
    case 'PUT':
        // Update member
        updateMember();
        break;
        
    case 'DELETE':
        // Delete member
        deleteMember();
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        break;
}

function getMembers() {
    global $pdo, $apiAuth;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, first_name, last_name, email, phone_number, 
                   user_role, status, registration_date, last_login
            FROM users
            ORDER BY id
        ");
        $stmt->execute();
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $members]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function getMember($id) {
    global $pdo, $apiAuth;
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, username, first_name, last_name, email, phone_number, 
                   user_role, status, registration_date, last_login
            FROM users
            WHERE id = ?
        ");
        $stmt->execute([$id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$member) {
            http_response_code(404);
            echo json_encode(['error' => 'Member not found']);
            return;
        }
        
        echo json_encode(['success' => true, 'data' => $member]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function createMember() {
    global $pdo, $apiAuth;
    
    // Only admins can create members via API
    if ($apiAuth['access_level'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['first_name']) || !isset($data['last_name']) || 
        !isset($data['email']) || !isset($data['phone_number']) || !isset($data['password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields']);
        return;
    }
    
    try {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetchColumn() > 0) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already exists']);
            return;
        }
        
        // Generate username
        $username = strtolower(substr($data['first_name'], 0, 1) . $data['last_name']);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username LIKE ?");
        $stmt->execute([$username . '%']);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            $username .= ($count + 1);
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO users (first_name, last_name, username, email, phone_number, password, user_role, status, registration_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $username,
            $data['email'],
            $data['phone_number'],
            $hashedPassword,
            $data['user_role'] ?? 'member'
        ]);
        
        $newId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Member created successfully',
            'data' => [
                'id' => $newId,
                'username' => $username
            ]
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function updateMember() {
    global $pdo, $apiAuth;
    
    // Get JSON data
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data || !isset($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing member ID']);
        return;
    }
    
    // Only admins can update other members
    if ($apiAuth['access_level'] !== 'admin' && $apiAuth['user_id'] != $data['id']) {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    try {
        // Check if member exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $stmt->execute([$data['id']]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Member not found']);
            return;
        }
        
        // Build update query
        $updateFields = [];
        $params = [];
        
        if (isset($data['first_name'])) {
            $updateFields[] = "first_name = ?";
            $params[] = $data['first_name'];
        }
        
        if (isset($data['last_name'])) {
            $updateFields[] = "last_name = ?";
            $params[] = $data['last_name'];
        }
        
        if (isset($data['email'])) {
            $updateFields[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['phone_number'])) {
            $updateFields[] = "phone_number = ?";
            $params[] = $data['phone_number'];
        }
        
        if (isset($data['status']) && $apiAuth['access_level'] === 'admin') {
            $updateFields[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['user_role']) && $apiAuth['access_level'] === 'admin') {
            $updateFields[] = "user_role = ?";
            $params[] = $data['user_role'];
        }
        
        if (isset($data['password'])) {
            $updateFields[] = "password = ?";
            $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        
        if (empty($updateFields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }
        
        // Add member ID to params
        $params[] = $data['id'];
        
        // Execute update
        $stmt = $pdo->prepare("UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?");
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Member updated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}

function deleteMember() {
    global $pdo, $apiAuth;
    
    // Only admins can delete members
    if ($apiAuth['access_level'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Permission denied']);
        return;
    }
    
    // Get member ID
    $id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing member ID']);
        return;
    }
    
    try {
        // Check if member exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() == 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Member not found']);
            return;
        }
        
        // Instead of deleting, set status to inactive
        $stmt = $pdo->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
        $stmt->execute([$id]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Member deactivated successfully'
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>