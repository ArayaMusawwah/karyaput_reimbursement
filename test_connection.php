<?php
// Test script to verify database connection and setup
require_once 'includes/db.php';

try {
    $conn = getConnection();
    echo "✓ Koneksi database berhasil!\n";

    // Test if tables exist
    $tables = ['users', 'categories', 'reimbursement_requests'];
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) FROM $table");
        if ($result !== false) {
            echo "✓ Tabel '$table' ada\n";
        } else {
            echo "✗ Tabel '$table' tidak ada\n";
        }
    }

    // Check if default admin user exists
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE username='admin'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] > 0) {
        echo "✓ Pengguna admin default ada\n";
    } else {
        echo "✗ Pengguna admin default tidak ada\n";
    }

    // Check if default categories exist
    $stmt = $conn->query("SELECT COUNT(*) as count FROM categories");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result['count'] >= 5) {
        echo "✓ Kategori default ada\n";
    } else {
        echo "✗ Kategori default tidak ada (ditemukan " . $result['count'] . ")\n";
    }

    echo "\nUntuk menyelesaikan pengaturan:\n";
    echo "1. Pastikan server web Anda dikonfigurasi untuk melayani direktori ini\n";
    echo "2. Pastikan server MariaDB Anda berjalan dan dapat diakses\n";
    echo "3. Impor skema database dari database_schema.sql:\n";
    echo "   - Buat database: CREATE DATABASE reimbursement_system;\n";
    echo "   - Impor skema: mysql -u root -p reimbursement_system < database_schema.sql\n";
    echo "4. Akses sistem di http://alamat-server-anda/index.php\n";
    echo "5. Masuk dengan admin/admin123 untuk mengakses panel admin\n";

} catch (Exception $e) {
    echo "✗ Koneksi database gagal: " . $e->getMessage() . "\n";
    echo "Silakan periksa konfigurasi database Anda di includes/db.php\n";
}
?>