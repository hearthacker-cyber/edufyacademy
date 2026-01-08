<?php
session_start();
session_unset();
session_destroy();

// Clear Remember Me cookie
if (isset($_COOKIE['edufy_remember'])) {
    setcookie('edufy_remember', '', time() - 3600, '/', '.edufyacademy.com', true, true); // secure, httponly
}

header("Location: ../index.php");
exit;
?>