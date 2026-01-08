<?php
require_once '../../vendor/autoload.php';
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u894882493_educourse');
define('DB_PASS', 'Edufy@25');
define('DB_NAME', 'u894882493_educourse');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't show errors to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');


// Create PDO connection
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    die("Database connection error. Please try again later.");
}

// Optional if using constants for email config

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$client = new Google_Client();
$client->setClientId('902927455984-aq7hrkrojic09434p2pab84njhsula1s.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('https://edufyacademy.com/app/auth/google-callback.php');
$client->addScope(['email', 'profile']);

if (isset($_GET['code'])) {
    try {
        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $idToken = $client->verifyIdToken();

        if (!$idToken || $idToken['aud'] !== $client->getClientId()) {
            throw new Exception('Invalid token');
        }

        $client->setAccessToken($token);
        $oauth = new Google_Service_Oauth2($client);
        $userData = $oauth->userinfo->get();

        if (empty($userData->email) || empty($userData->id)) {
            throw new Exception('Incomplete user data from Google');
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$userData->email]);
        $user = $stmt->fetch();
        $newUser = false;

        if (!$user) {
            // New user registration
            $stmt = $pdo->prepare("INSERT INTO users 
                                  (first_name, last_name, email, google_id, avatar, created_at) 
                                  VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $userData->givenName ?? '',
                $userData->familyName ?? '',
                $userData->email,
                $userData->id,
                $userData->picture ?? null
            ]);
            $userId = $pdo->lastInsertId();
            $newUser = true;

            // 🔔 Send welcome email to new user
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'srinathdev01@gmail.com';
                $mail->Password = 'noruioeobbxwxvxj'; // Your app password (NO spaces)
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                $mail->setFrom('srinathdev01@gmail.com', 'Edufy Academy');
                $mail->addAddress($userData->email, $userData->givenName . ' ' . $userData->familyName);
                $mail->addReplyTo('support@edufyacademy.com', 'Edufy Support');

                $mail->isHTML(true);
                $mail->Subject = 'Welcome to Edufy Academy!';
                $mail->Body = sprintf('
                    <html>
                    <body>
                        <h2>Welcome to Edufy Academy, %s!</h2>
                        <p>Thank you for signing up using your Google account.</p>
                        <p>You can now access all learning materials and manage your profile anytime.</p>
                        <a href="https://edufyacademy.com" style="padding:10px 20px; background:#4CAF50; color:white; border-radius:5px;">Go to Dashboard</a>
                        <p style="margin-top:20px;">If you have any questions, feel free to reach out at support@edufyacademy.com</p>
                        <p style="font-size:12px; color:gray;">&copy; %s Edufy Academy</p>
                    </body>
                    </html>',
                    htmlspecialchars($userData->givenName),
                    date('Y')
                );
                $mail->AltBody = "Welcome to Edufy Academy, {$userData->givenName}! Visit https://edufyacademy.com to get started.";

                $mail->send();

            } catch (Exception $e) {
                error_log('Mailer Error: ' . $mail->ErrorInfo);
            }
        } else {
            $userId = $user['id'];
            $updateFields = [];
            $updateParams = [];

            if (empty($user['google_id'])) {
                $updateFields[] = 'google_id = ?';
                $updateParams[] = $userData->id;
            }

            if (empty($user['avatar']) && !empty($userData->picture)) {
                $updateFields[] = 'avatar = ?';
                $updateParams[] = $userData->picture;
            }

            if (!empty($updateFields)) {
                $updateParams[] = $userId;
                $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?");
                $stmt->execute($updateParams);
            }
        }

        // Persistent Login Token
        $rememberToken = bin2hex(random_bytes(32));
        $updateToken = $pdo->prepare("UPDATE users SET remember_token = ? WHERE id = ?");
        $updateToken->execute([$rememberToken, $userId]);
        
        setcookie('edufy_remember', $rememberToken, [
            'expires' => time() + (86400 * 30),
            'path' => '/',
            'domain' => '.edufyacademy.com',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'None'
        ]);

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_email'] = $userData->email;
        $_SESSION['user_name'] = $userData->givenName ?? 'User';
        $_SESSION['avatar'] = $userData->picture ?? null;
        $_SESSION['auth_provider'] = 'google';

        header('Location: ../home.php');
        exit();

    } catch (Exception $e) {
        error_log('Google login error: ' . $e->getMessage());
        header('Location: login.php?error=google_login_failed&message=' . urlencode($e->getMessage()));
        exit();
    }
} else {
    header('Location: login.php?error=google_auth_failed');
    exit();
}
