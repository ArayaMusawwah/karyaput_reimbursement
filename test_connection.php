<?php
// Test script to verify database connection and setup
require_once 'includes/db.php';

try {
    $conn = getConnection();
    echo "✓ Database connection successful!\n";
    
    // Test if tables exist
    $tables = ['users', 'categories', 'reimbursement_requests'];
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) FROM $table");
        if ($result !== false) {
            echo "✓ Table '$table' exists\n";
        } else {
            echo "✗ Table '$table' does not exist\n";
        }
    }
    
    // Check if default admin user exists
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE username='admin'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        echo "✓ Default admin user exists\n";
    } else {
        echo "✗ Default admin user does not exist\n";
    }
    
    // Check if default categories exist
    $stmt = $conn->query("SELECT COUNT(*) as count FROM categories");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] >= 5) {
        echo "✓ Default categories exist\n";
    } else {
        echo "✗ Default categories do not exist (found " . $result['count'] . ")\n";
    }
    
    echo "\nTo complete the setup:\n";
    echo "1. Make sure your web server is configured to serve this directory\n";
    echo "2. Ensure your MariaDB server is running and accessible\n";
    echo "3. Import the database schema from database_schema.sql:\n";
    echo "   - Create the database: CREATE DATABASE reimbursement_system;\n";
    echo "   - Import the schema: mysql -u root -p reimbursement_system < database_schema.sql\n";
    echo "4. Access the system at http://your-server-address/index.php\n";
    echo "5. Login with admin/admin123 to access the admin panel\n";
    
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    echo "Please check your database configuration in includes/db.php\n";
}
?>