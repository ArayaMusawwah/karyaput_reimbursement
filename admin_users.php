<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireLogin();
if (getUserRole() !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$conn = getConnection();

$error = '';
$success = '';

// Handle adding a new user
if (isset($_POST['add_user'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $full_name = trim($_POST['full_name']);
    $department = trim($_POST['department']);
    $role = $_POST['role'];
    $no_rekening = trim($_POST['no_rekening'] ?? '');
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($department)) {
        $error = "Please fill in all required fields";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, email, password, role, full_name, department, no_rekening) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        try {
            $stmt->execute([$username, $email, $hashed_password, $role, $full_name, $department, $no_rekening]);
            $success = "User added successfully!";
        } catch(PDOException $e) {
            if($e->getCode() == 23000) { // Duplicate entry error
                $error = "Username or email already exists";
            } else {
                $error = "Error adding user: " . $e->getMessage();
            }
        }
    }
}

// Handle updating a user
if (isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $full_name = trim($_POST['update_full_name']);
    $email = trim($_POST['update_email']);
    $department = trim($_POST['update_department']);
    $role = $_POST['update_role'];
    $no_rekening = trim($_POST['update_no_rekening'] ?? '');
    
    $sql = "UPDATE users SET full_name = ?, email = ?, department = ?, role = ?, no_rekening = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    try {
        $stmt->execute([$full_name, $email, $department, $role, $no_rekening, $user_id]);
        $success = "User updated successfully!";
    } catch(PDOException $e) {
        if($e->getCode() == 23000) { // Duplicate entry error
            $error = "Email already exists";
        } else {
            $error = "Error updating user: " . $e->getMessage();
        }
    }
}

// Handle deleting a user
if (isset($_POST['delete_user'])) {
    $user_id = intval($_POST['user_id']);
    
    // Don't allow deleting the admin user
    if ($user_id == $_SESSION['user_id']) {
        $error = "You cannot delete your own account";
    } else {
        $sql = "DELETE FROM users WHERE id = ? AND id != ?";
        $stmt = $conn->prepare($sql);
        
        try {
            $stmt->execute([$user_id, $_SESSION['user_id']]);
            if ($stmt->rowCount() > 0) {
                $success = "User deleted successfully!";
            } else {
                $error = "User not found or cannot be deleted";
            }
        } catch(PDOException $e) {
            $error = "Error deleting user: " . $e->getMessage();
        }
    }
}

// Get all users
$users_sql = "SELECT * FROM users ORDER BY created_at DESC";
$users_stmt = $conn->query($users_sql);
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Users</h2>
            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addUserModal">
                Add New User
            </button>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Department</th>
                        <th>Role</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['department']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'manager' ? 'warning text-dark' : 'secondary'); ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                Edit
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <input type="hidden" name="delete_user" value="1">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    
                    <!-- Edit User Modal -->
                    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit User: <?php echo htmlspecialchars($user['full_name']); ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <form method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <input type="hidden" name="update_user" value="1">
                                        
                                        <div class="mb-3">
                                            <label for="update_full_name_<?php echo $user['id']; ?>" class="form-label">Full Name</label>
                                            <input type="text" class="form-control" id="update_full_name_<?php echo $user['id']; ?>" name="update_full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="update_email_<?php echo $user['id']; ?>" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="update_email_<?php echo $user['id']; ?>" name="update_email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="update_department_<?php echo $user['id']; ?>" class="form-label">Department</label>
                                            <input type="text" class="form-control" id="update_department_<?php echo $user['id']; ?>" name="update_department" value="<?php echo htmlspecialchars($user['department']); ?>" required>
                                        </div>

                                        <div class="mb-3">
                                            <label for="update_no_rekening_<?php echo $user['id']; ?>" class="form-label">Bank Account Number</label>
                                            <input type="text" class="form-control" id="update_no_rekening_<?php echo $user['id']; ?>" name="update_no_rekening" value="<?php echo htmlspecialchars($user['no_rekening'] ?? ''); ?>">
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="update_role_<?php echo $user['id']; ?>" class="form-label">Role</label>
                                            <select class="form-control" id="update_role_<?php echo $user['id']; ?>" name="update_role" required>
                                                <option value="employee" <?php echo $user['role'] === 'employee' ? 'selected' : ''; ?>>Employee</option>
                                                <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Update User</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Add User Modal -->
        <div class="modal fade" id="addUserModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="add_user" value="1">
                            
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="department" class="form-label">Department</label>
                                <input type="text" class="form-control" id="department" name="department" required>
                            </div>

                            <div class="mb-3">
                                <label for="no_rekening" class="form-label">Bank Account Number</label>
                                <input type="text" class="form-control" id="no_rekening" name="no_rekening">
                            </div>
                            
                            <div class="mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-control" id="role" name="role" required>
                                    <option value="employee">Employee</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-success">Add User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>