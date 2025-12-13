<?php
// Test script to diagnose database connection issues

echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP SAPI: " . PHP_SAPI . "\n\n";

// Check for PDO MySQL driver
echo "Checking for PDO MySQL support:\n";
if (extension_loaded('pdo_mysql')) {
    echo "✓ PDO MySQL extension is loaded\n";
} else {
    echo "✗ PDO MySQL extension is NOT loaded\n";
    echo "You need to install and enable the PDO MySQL extension.\n";
}

echo "\nOther PDO drivers available:\n";
$drivers = PDO::getAvailableDrivers();
foreach ($drivers as $driver) {
    echo "- $driver\n";
}

echo "\nChecking database configuration:\n";
echo "Host: localhost\n";
echo "Database: karyaput_reimbursement\n";
echo "User: karyaput_admin\n";
echo "Password: (hidden)\n";

// Test connection without credentials to check if MySQL driver exists
echo "\nTesting basic MySQL connection capability...\n";
if (extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO("mysql:host=localhost", 'karyaput_admin', 'Setup1PW!');
        echo "✓ Basic connection capability exists\n";
    } catch (PDOException $e) {
        echo "✗ Connection error: " . $e->getMessage() . "\n";
        // Test if MySQL server is running
        echo "\nPossible issues:\n";
        echo "1. MySQL/MariaDB server is not running\n";
        echo "2. Database 'karyaput_reimbursement' does not exist\n";
        echo "3. User 'karyaput_admin' does not exist or password is incorrect\n";
        echo "4. Connection credentials in db.php are incorrect\n";
    }
} else {
    echo "Cannot test connection - PDO MySQL extension is not loaded\n";
    echo "\nTo fix this issue:\n";
    echo "For Ubuntu/Debian: sudo apt-get install php-mysql\n";
    echo "For CentOS/RHEL: sudo yum install php-mysql or sudo dnf install php-mysql\n";
    echo "For Windows: Enable extension=pdo_mysql in php.ini\n";
    echo "Then restart your web server.\n";
}
?>