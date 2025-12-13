-- ====================================
-- Reimbursement System Database Schema
-- ====================================
-- This schema creates all necessary tables for the reimbursement management system
-- including users, categories, requests, and tracking items

-- Drop tables if they exist (in reverse dependency order)
DROP TABLE IF EXISTS reimbursement_items;
DROP TABLE IF EXISTS reimbursement_requests;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- ====================================
-- Table: users
-- ====================================
-- Stores user information for authentication and authorization
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    department VARCHAR(100) NOT NULL,
    role ENUM('employee', 'manager', 'admin') NOT NULL DEFAULT 'employee',
    no_rekening VARCHAR(50) NULL, -- Added to match existing code
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- Table: categories
-- ====================================
-- Stores reimbursement categories (e.g., Travel, Meals, Medical, etc.)
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- Table: reimbursement_requests
-- ====================================
-- Stores reimbursement requests submitted by employees
CREATE TABLE reimbursement_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    description TEXT NOT NULL,
    category_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,
    adjusted_amount DECIMAL(10, 2) NULL,
    receipt_path VARCHAR(255),
    requested_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'denied', 'paid') NOT NULL DEFAULT 'pending',
    approved_by INT,
    approved_at TIMESTAMP NULL,
    notes TEXT,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_category_id (category_id),
    INDEX idx_status (status),
    INDEX idx_requested_date (requested_date),
    INDEX idx_submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- Table: reimbursement_items
-- ====================================
-- Stores individual items/line items for each reimbursement request
CREATE TABLE reimbursement_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES reimbursement_requests(id) ON DELETE CASCADE,
    INDEX idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ====================================
-- Initial Data: Default Admin User
-- ====================================
-- Create default admin account
-- Username: admin
-- Password: Setup1PW!
INSERT INTO users (username, email, password, full_name, department, role) VALUES
('admin', 'admin@karyaputrabersama.com', '$2y$12$ZhXBblGDbZXlYpncojogTOl/w9ey40ewIky6vdC71rzsNph6qf3Za', 'System Administrator', 'IT', 'admin');

-- ====================================
-- Initial Data: Default Categories
-- ====================================
-- Create common reimbursement categories
INSERT INTO categories (name, description) VALUES
('Perjalanan Dinas', 'Biaya transportasi termasuk tiket pesawat, kereta, bus, dan taksi'),
('Makan & Minum', 'Biaya makan dan minum selama perjalanan dinas atau pertemuan'),
('Akomodasi', 'Biaya hotel dan penginapan'),
('Kesehatan', 'Biaya kesehatan dan medis'),
('Perlengkapan Kantor', 'Peralatan kantor dan alat tulis'),
('Komunikasi', 'Biaya telepon, internet, dan komunikasi'),
('Hiburan', 'Biaya hiburan klien dan hiburan bisnis'),
('Pelatihan', 'Biaya pelatihan dan pengembangan profesional'),
('Lain-lain', 'Biaya lain yang tidak tercakup dalam kategori khusus');

-- ====================================
-- Views (Optional): Useful for reporting
-- ====================================

-- View: Pending Requests Summary
CREATE OR REPLACE VIEW v_pending_requests AS
SELECT 
    r.id,
    r.description,
    r.total_amount,
    r.requested_date,
    r.submitted_at,
    u.full_name AS employee_name,
    u.department,
    c.name AS category_name
FROM reimbursement_requests r
JOIN users u ON r.user_id = u.id
JOIN categories c ON r.category_id = c.id
WHERE r.status = 'pending'
ORDER BY r.submitted_at DESC;

-- View: Approved Requests Summary
CREATE OR REPLACE VIEW v_approved_requests AS
SELECT 
    r.id,
    r.description,
    r.total_amount,
    r.adjusted_amount,
    r.requested_date,
    r.approved_at,
    u.full_name AS employee_name,
    u.department,
    c.name AS category_name,
    approver.full_name AS approved_by_name
FROM reimbursement_requests r
JOIN users u ON r.user_id = u.id
JOIN categories c ON r.category_id = c.id
LEFT JOIN users approver ON r.approved_by = approver.id
WHERE r.status = 'approved'
ORDER BY r.approved_at DESC;

-- View: Monthly Reimbursement Summary
CREATE OR REPLACE VIEW v_monthly_summary AS
SELECT 
    DATE_FORMAT(r.requested_date, '%Y-%m') AS month,
    c.name AS category,
    COUNT(*) AS total_requests,
    SUM(r.total_amount) AS total_amount,
    r.status
FROM reimbursement_requests r
JOIN categories c ON r.category_id = c.id
GROUP BY DATE_FORMAT(r.requested_date, '%Y-%m'), c.name, r.status
ORDER BY month DESC, category;

-- ====================================
-- Database Information
-- ====================================
-- Database version: 1.0
-- Created: 2025-12-13
-- Compatible with: MySQL 5.7+, MariaDB 10.2+
-- Character Set: UTF8MB4 (supports emoji and international characters)
