<?php
session_start();
require_once '../includes/functions.php';

// Database configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'u894882493_educourse');
if (!defined('DB_PASS')) define('DB_PASS', 'Edufy@25');
if (!defined('DB_NAME')) define('DB_NAME', 'u894882493_educourse');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database error");
}

$error = '';
$success = '';
$email = '';

// Validate Link
if (isset($_GET['data']) && isset($_GET['sig'])) {
    $payloadBase64 = $_GET['data'];
    $providedSig = $_GET['sig'];
    
    $payload = json_decode(base64_decode($payloadBase64), true);
    
    if (!$payload || !isset($payload['email']) || !isset($payload['exp'])) {
        $error = "Invalid link.";
    } elseif ($payload['exp'] < time()) {
        $error = "Link expired.";
    } else {
        $email = $payload['email'];
        
        // Verify Signature
        $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error = "User not found.";
        } else {
            $serverKey = 'EdufySecretKey2024!';
            $expectedSig = hash_hmac('sha256', $payloadBase64, $serverKey . $user['password']);
            
            if (!hash_equals($expectedSig, $providedSig)) {
                $error = "Invalid or expired link.";
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = "Missing parameters.";
}

// Handle Reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $email = $_POST['email']; 
    // Re-verify signature implicitly by regenerating it using the *current* password
    // But since we are posting, we need to pass the data/sig through the form or trust the check above?
    // Better to re-verify in POST.
    
    $payloadBase64 = $_POST['data'];
    $providedSig = $_POST['sig'];
    $newPass = $_POST['password'];
    $confirmPass = $_POST['confirm_password'];
    
    // Re-validation logic...
    $payload = json_decode(base64_decode($payloadBase64), true);
    if ($payload['exp'] < time()) {
        $error = "Link expired.";
    } else {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        $serverKey = 'EdufySecretKey2024!';
        $expectedSig = hash_hmac('sha256', $payloadBase64, $serverKey . $user['password']);
        
        if (!hash_equals($expectedSig, $providedSig)) {
             $error = "Invalid link.";
        } elseif ($newPass !== $confirmPass) {
            $error = "Passwords do not match.";
        } elseif (strlen($newPass) < 8) {
            $error = "Password too short.";
        } else {
            // Update Password
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE email = ?");
            if ($updateStmt->execute([$newHash, $email])) {
                $success = "Password reset successful! You can now <a href='login.php'>login</a>.";
            } else {
                $error = "Update failed.";
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reset Password - Edufy</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-header">
                <h1>Reset Password</h1>
            </div>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="success-message"><?php echo $success; ?></div>
            <?php elseif (empty($error)): ?>
                <form action="" method="POST">
                    <input type="hidden" name="data" value="<?php echo htmlspecialchars($payloadBase64); ?>">
                    <input type="hidden" name="sig" value="<?php echo htmlspecialchars($providedSig); ?>">
                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">
                    
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn">Reset Password</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
