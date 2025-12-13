<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'jasanetc_reim');
define('DB_PASS', '+J.CZ2$K-tPi7vY-');
define('DB_NAME', 'jasanetc_reim');

// Create connection
function getConnection() {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
?>