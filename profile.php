<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

requireLogin();

$error = '';
$success = '';

$conn = getConnection();
$role = getUserRole();

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $no_rekening = trim($_POST['no_rekening']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Only admin can change these
    if ($role === 'admin') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $department = trim($_POST['department']);
    } else {
        // For non-admins, we don't update these, but we need variables for validation if we were to use them
        // Actually we just won't update them in the SQL
        $full_name = null;
        $email = null;
        $department = null;
    }

    // Validation
    if ($role === 'admin' && (empty($full_name) || empty($email))) {
        $error = "Mohon isi semua kolom yang wajib diisi";
    } else {
        if (!empty($new_password)) {
            if (strlen($new_password) < 6) {
                $error = "Password baru minimal 6 karakter";
            } elseif ($new_password !== $confirm_password) {
                $error = "Konfirmasi password tidak cocok";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                try {
                    if ($role === 'admin') {
                        $sql = "UPDATE users SET full_name = ?, email = ?, department = ?, no_rekening = ?, password = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$full_name, $email, $department, $no_rekening, $hashed_password, $_SESSION['user_id']]);
                        $_SESSION['full_name'] = $full_name;
                    } else {
                        $sql = "UPDATE users SET no_rekening = ?, password = ? WHERE id = ?";
                        $stmt = $conn->prepare($sql);
                        $stmt->execute([$no_rekening, $hashed_password, $_SESSION['user_id']]);
                    }

                    $success = "Profil dan password berhasil diperbarui!";
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        $error = "Email sudah terdaftar";
                    } else {
                        $error = "Pembaruan gagal: " . $e->getMessage();
                    }
                }
            }
        } else {
            try {
                if ($role === 'admin') {
                    $sql = "UPDATE users SET full_name = ?, email = ?, department = ?, no_rekening = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$full_name, $email, $department, $no_rekening, $_SESSION['user_id']]);
                    $_SESSION['full_name'] = $full_name;
                } else {
                    $sql = "UPDATE users SET no_rekening = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$no_rekening, $_SESSION['user_id']]);
                }

                $success = "Profil berhasil diperbarui!";
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate entry error
                    $error = "Email sudah terdaftar";
                } else {
                    $error = "Pembaruan gagal: " . $e->getMessage();
                }
            }
        }
    }
}

// Get current user data
$user_sql = "SELECT * FROM users WHERE id = ?";
$user_stmt = $conn->prepare($user_sql);
$user_stmt->execute([$_SESSION['user_id']]);
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Sistem Reimbursement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }

        .toast-custom {
            min-width: 300px;
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8">
                <h2>Pengaturan Profil</h2>

                <?php if ($error || $success): ?>
                    <div id="toast-container" class="toast-container"></div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="username"
                            value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        <div class="form-text">Username tidak dapat diubah</div>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label">Nama Lengkap
                            <?php echo $role === 'admin' ? '*' : ''; ?></label>
                        <input type="text" class="form-control" id="full_name" name="full_name"
                            value="<?php echo htmlspecialchars($user['full_name']); ?>" <?php echo $role === 'admin' ? 'required' : 'disabled'; ?>>
                    </div>

                    <div class="mb-3">
                        <label for="email" class="form-label">Email <?php echo $role === 'admin' ? '*' : ''; ?></label>
                        <input type="email" class="form-control" id="email" name="email"
                            value="<?php echo htmlspecialchars($user['email']); ?>" <?php echo $role === 'admin' ? 'required' : 'disabled'; ?>>
                    </div>

                    <div class="mb-3">
                        <label for="department" class="form-label">Departemen</label>
                        <input type="text" class="form-control" id="department" name="department"
                            value="<?php echo htmlspecialchars($user['department']); ?>" <?php echo $role === 'admin' ? '' : 'disabled'; ?>>
                    </div>

                    <div class="mb-3">
                        <label for="no_rekening" class="form-label">Nomor Rekening</label>
                        <input type="text" class="form-control" id="no_rekening" name="no_rekening"
                            value="<?php echo htmlspecialchars($user['no_rekening']); ?>">
                    </div>

                    <hr class="my-4">
                    <h5>Ganti Password</h5>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Password Baru (Kosongkan jika tidak ingin
                            mengubah)</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Konfirmasi Password Baru</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                            minlength="6">
                    </div>

                    <button type="submit" class="btn btn-primary">Perbarui Profil</button>
                    <a href="dashboard.php" class="btn btn-secondary">Kembali ke Dasbor</a>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if ($error || $success): ?>
            // Show toast notifications
            document.addEventListener('DOMContentLoaded', function () {
                const toastContainer = document.getElementById('toast-container');

                <?php if ($success): ?>
                    showToast('<?php echo addslashes($success); ?>', 'success');
                <?php endif; ?>

                <?php if ($error): ?>
                    showToast('<?php echo addslashes($error); ?>', 'error');
                <?php endif; ?>
            });

            function showToast(message, type) {
                const toastId = 'toast-' + Date.now();
                const toastClass = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
                const toastHTML = `
                <div id="${toastId}" class="toast toast-custom ${toastClass}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <strong class="me-auto">
                            ${type === 'success' ? 'Berhasil' : 'Kesalahan'}
                        </strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;

                document.getElementById('toast-container').innerHTML = toastHTML;

                const toastEl = document.getElementById(toastId);
                const toast = new bootstrap.Toast(toastEl, {
                    delay: 5000,
                    autohide: true
                });
                toast.show();
            }
        <?php endif; ?>
    </script>
</body>

</html>