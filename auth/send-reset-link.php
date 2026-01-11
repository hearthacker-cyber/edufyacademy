<?php
session_start();
require_once '../includes/functions.php';

// Database configuration (Direct because includes/db.php might have auth check)
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'u894882493_educourse');
if (!defined('DB_PASS')) define('DB_PASS', 'Edufy@25');
if (!defined('DB_NAME')) define('DB_NAME', 'u894882493_educourse');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error.";
    header("Location: forgot-password.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email']);
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Generate Token
        // Token = base64(json_encode([email, expiry]))
        // Signature = hmac(token, secret + user_password_hash)
        // We include user_password_hash in secret so if they change password, token invalidates.
        
        $expiry = time() + 3600; // 1 hour
        $payload = base64_encode(json_encode(['email' => $email, 'exp' => $expiry]));
        
        // Use a server key + user's current password hash as the secret
        // Ideally server key should be in env/config, using a hardcoded one for this constrained env.
        $serverKey = 'EdufySecretKey2024!'; 
        $signature = hash_hmac('sha256', $payload, $serverKey . $user['password']);
        
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/app/auth/reset-password.php?data=$payload&sig=$signature";
        
        // Send Email
        $to = $email;
        $subject = "Password Reset Request";
        $message = "Please click the link below to reset your password:\n\n$resetLink\n\nThis link expires in 1 hour.";
        $headers = "From: no-reply@edufyacademy.com";
        
        mail($to, $subject, $message, $headers);
        
        $_SESSION['success'] = "If that email exists, we have sent a reset link.";
    } else {
        // Don't reveal user existence
        $_SESSION['success'] = "If that email exists, we have sent a reset link.";
    }
}

header("Location: forgot-password.php");
exit();
