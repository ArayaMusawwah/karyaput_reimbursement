<?php
require_once 'includes/db.php';

$conn = getConnection();

try {
    // Add total_amount column
    try {
        $sql = "ALTER TABLE reimbursement_requests ADD COLUMN total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0";
        $conn->exec($sql);
        echo "Added 'total_amount' column to reimbursement_requests table<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "Column 'total_amount' already exists in reimbursement_requests table<br>";
        } else {
            echo "Error adding 'total_amount' column: " . $e->getMessage() . "<br>";
        }
    }

    // Create the reimbursement_items table if it doesn't exist
    try {
        $sql = "CREATE TABLE IF NOT EXISTS reimbursement_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            request_id INT NOT NULL,
            name VARCHAR(255) NOT NULL, -- Nama
            amount DECIMAL(10, 2) NOT NULL, -- Jumlah
            FOREIGN KEY (request_id) REFERENCES reimbursement_requests(id) ON DELETE CASCADE
        )";
        $conn->exec($sql);
        echo "Created/verified 'reimbursement_items' table<br>";
    } catch (PDOException $e) {
        echo "Error creating 'reimbursement_items' table: " . $e->getMessage() . "<br>";
    }

    echo "Database update process completed.<br>";
} catch (PDOException $e) {
    echo "Error connecting to database: " . $e->getMessage() . "<br>";
}