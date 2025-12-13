<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'karyaput_admin');
define('DB_PASS', 'Setup1PW!');
define('DB_NAME', 'karyaput_reimbursement');

// Create connection
function getConnection()
{
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>