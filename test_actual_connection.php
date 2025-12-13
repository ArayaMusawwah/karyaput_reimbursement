<?php
// More detailed test of the actual database connection used in the application
require_once 'includes/db.php';

echo "Testing the getConnection() function from includes/db.php...\n";

try {
    $conn = getConnection();
    echo "✓ Connection successful!\n";
    echo "Connected to database: " . $conn->getAttribute(PDO::ATTR_SERVER_INFO) . "\n";
} catch (Exception $e) {
    echo "✗ Connection failed: " . $e->getMessage() . "\n";
    
    // Let's debug step by step
    echo "\nDebugging connection parameters:\n";
    echo "DB_HOST: " . DB_HOST . "\n";
    echo "DB_USER: " . DB_USER . "\n";
    echo "DB_NAME: " . DB_NAME . "\n";
    echo "DB_PASS: " . (DB_PASS ? "[SET]" : "[EMPTY]") . "\n";
    
    // Try to connect without specifying database first
    echo "\nTrying to connect without specifying database...\n";
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "✓ Connected to MySQL server without database\n";
        
        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
        $databases = $stmt->fetchAll();
        if (count($databases) > 0) {
            echo "✓ Database '" . DB_NAME . "' exists\n";
        } else {
            echo "✗ Database '" . DB_NAME . "' does not exist\n";
        }
        
        // Check if user exists
        $stmt = $pdo->query("SELECT User, Host FROM mysql.user WHERE User = '" . DB_USER . "'");
        $users = $stmt->fetchAll();
        if (count($users) > 0) {
            echo "✓ User '" . DB_USER . "' exists\n";
        } else {
            echo "✗ User '" . DB_USER . "' does not exist\n";
        }
        
    } catch (PDOException $e) {
        echo "✗ Failed to connect to MySQL server without database: " . $e->getMessage() . "\n";
        echo "This suggests the issue might be with MySQL server not running\n";
    }
}
?>