<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is NOT logged in but HAS the remember cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['edufy_remember'])) {
    
    // Define constants if not already defined (safe guard)
    if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
    if (!defined('DB_USER')) define('DB_USER', 'u894882493_educourse');
    if (!defined('DB_PASS')) define('DB_PASS', 'Edufy@25');
    if (!defined('DB_NAME')) define('DB_NAME', 'u894882493_educourse');

    try {
        $pdoAuth = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
        $pdoAuth->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $token = $_COOKIE['edufy_remember'];
        
        // Validate token
        $stmt = $pdoAuth->prepare("SELECT * FROM users WHERE remember_token = ?");
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Restore Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            // Reconstruct name as in login.php
            $_SESSION['user_name'] = isset($user['first_name']) ? $user['first_name'] . ' ' . $user['last_name'] : 'User';
            if (isset($user['avatar'])) {
                $_SESSION['avatar'] = $user['avatar'];
            }
            if (!empty($user['google_id'])) {
                $_SESSION['auth_provider'] = 'google';
            }

            // Refresh cookie expiry (30 days) ONLY on restore
            setcookie('edufy_remember', $token, [
                'expires' => time() + (86400 * 30),
                'path' => '/',
                'domain' => '.edufyacademy.com',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None'
            ]);

            // Optional: Update last login
            $upd = $pdoAuth->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $upd->execute([$user['id']]);
        }
    } catch (Exception $e) {
        // Log error silently
        error_log("Auto login error: " . $e->getMessage());
    }
}
?>
