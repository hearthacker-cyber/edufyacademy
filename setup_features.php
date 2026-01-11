<?php
// Setup script to initialize new feature tables
// Defining constants manually to avoid including auth-protected db.php
define('DB_HOST', 'localhost');
define('DB_USER', 'u894882493_educourse');
define('DB_PASS', 'Edufy@25');
define('DB_NAME', 'u894882493_educourse');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('database/feature_tables.sql');
    if (!$sql) {
        die("Error: Could not read database/feature_tables.sql");
    }

    $pdo->exec($sql);
    echo "Success: New tables (password_resets, user_terms_logs, user_certificates) have been created/verified.";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
