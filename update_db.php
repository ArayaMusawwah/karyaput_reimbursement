<?php
require_once 'includes/db.php';

$conn = getConnection();

try {
    // Add the new column if it doesn't exist
    $sql = "ALTER TABLE reimbursement_requests ADD COLUMN adjusted_amount DECIMAL(10, 2) NULL";
    $conn->exec($sql);
    echo "Database updated successfully. Added 'adjusted_amount' column to reimbursement_requests table.<br>";
} catch (PDOException $e) {
    // Check if the error is because column already exists
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Column 'adjusted_amount' already exists in the table.<br>";
    } else {
        echo "Error updating database: " . $e->getMessage() . "<br>";
    }
}

echo "Database update process completed.";