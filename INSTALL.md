# Reimbursement System Installation Guide

## Prerequisites

- Web server (Apache/Nginx) with PHP 7.4 or higher
- MariaDB or MySQL database server
- PHP extensions: PDO, GD (for file uploads)

## Setup Instructions

### 1. Database Setup

First, create the database and import the schema:

```sql
-- Connect to MariaDB
mysql -u root -p

-- Create the database
CREATE DATABASE reimbursement_system;

-- Exit MariaDB
EXIT;
```

Then import the schema:

```bash
mysql -u root -p reimbursement_system < database_schema.sql
```

### 2. Web Server Configuration

Place all files in your web server's document root (e.g., `/var/www/html/reimbursement/` or configure a virtual host).

Make sure the `uploads/` directory is writable by the web server:

```bash
chmod 755 uploads/
```

### 3. Database Configuration

Edit `includes/db.php` to match your database credentials:

```php
define('DB_HOST', 'localhost');      // Database host
define('DB_USER', 'root');           // Database user
define('DB_PASS', 'your_password');  // Database password
define('DB_NAME', 'reimbursement_system'); // Database name
```

### 4. Default Login Credentials

After setup, use the following default admin credentials:

- **Username:** `admin` or **Email:** `admin@karyaputrabersama.com`
- **Password:** `admin123`

**Important:** Change the default admin password immediately after first login for security.

### 5. Features

- Employee: Submit and track reimbursement requests
- Manager: Approve/deny requests
- Admin: Full system access including user management

## Security Notes

- Change the default admin password after first login
- Update database credentials in production
- Ensure proper file permissions on the server
- Use HTTPS in production

## File Structure

```
reimbursement/
├── index.php               # Main entry point
├── login.php               # Login page
├── register.php            # Registration page
├── dashboard.php           # User dashboard
├── request.php             # Submit new requests
├── profile.php             # User profile management
├── admin_requests.php      # Manage requests (admin/manager)
├── admin_users.php         # Manage users (admin only)
├── logout.php              # Logout handler
├── migrate_admin_password.php # Migration script to update admin password
├── update_admin_password.sql  # SQL script to update admin password
├── includes/
│   ├── db.php              # Database connection
│   ├── auth.php            # Authentication functions
│   └── header.php          # Navigation header
├── css/
│   └── style.css           # Styling
├── js/
│   └── (JavaScript files)
├── uploads/                # Receipt uploads (needs write permission)
└── database_schema.sql     # Database structure
```
