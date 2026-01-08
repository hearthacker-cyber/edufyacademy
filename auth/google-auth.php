<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$credential = $data['credential'] ?? '';

if (empty($credential)) {
    echo json_encode(['success' => false, 'message' => 'No credential provided']);
    exit;
}

// Split the JWT to get the payload
$jwtParts = explode('.', $credential);
if (count($jwtParts) !== 3) {
    echo json_encode(['success' => false, 'message' => 'Invalid credential format']);
    exit;
}

$payload = base64_decode(str_replace(['-', '_'], ['+', '/'], $jwtParts[1]));
$userData = json_decode($payload, true);

if (!$userData) {
    echo json_encode(['success' => false, 'message' => 'Failed to decode user data']);
    exit;
}

// Validate required fields
if (empty($userData['email']) || empty($userData['given_name']) || empty($userData['sub'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required user data']);
    exit;
}

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$userData['email']]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Update user data if needed
        $updateStmt = $pdo->prepare("
            UPDATE users 
            SET google_id = ?, avatar = ?, last_login = NOW(), updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([
            $userData['sub'],
            $userData['picture'] ?? null,
            $user['id']
        ]);
    } else {
        // Create new user
        $insertStmt = $pdo->prepare("
            INSERT INTO users 
            (first_name, last_name, email, google_id, avatar, auth_provider, created_at, updated_at, last_login) 
            VALUES (?, ?, ?, ?, ?, 'google', NOW(), NOW(), NOW())
        ");
        
        $last_name = $userData['family_name'] ?? '';
        $insertStmt->execute([
            $userData['given_name'],
            $last_name,
            $userData['email'],
            $userData['sub'],
            $userData['picture'] ?? null
        ]);
        
        $userId = $pdo->lastInsertId();
        $user = ['id' => $userId, 'email' => $userData['email'], 'first_name' => $userData['given_name']];
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['first_name'] . ' ' . ($user['last_name'] ?? '');
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>