<?php
require_once 'includes/db.php';

$conn = getConnection();

try {
    // Remove the 'title' column if it exists
    try {
        $sql = "ALTER TABLE reimbursement_requests DROP COLUMN title";
        $conn->exec($sql);
        echo "Successfully removed 'title' column from reimbursement_requests table<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            echo "Column 'title' does not exist in reimbursement_requests table (already removed)<br>";
        } else {
            echo "Error removing 'title' column: " . $e->getMessage() . "<br>";
        }
    }

    // Also remove the old 'amount' column if it exists
    try {
        $sql = "ALTER TABLE reimbursement_requests DROP COLUMN amount";
        $conn->exec($sql);
        echo "Successfully removed old 'amount' column from reimbursement_requests table<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Unknown column') !== false) {
            echo "Column 'amount' does not exist in reimbursement_requests table (already removed)<br>";
        } else {
            echo "Error removing old 'amount' column: " . $e->getMessage() . "<br>";
        }
    }

    echo "Database cleanup process completed.<br>";
} catch (PDOException $e) {
    echo "Error connecting to database: " . $e->getMessage() . "<br>";
}