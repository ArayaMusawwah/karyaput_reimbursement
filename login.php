<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    // Redirect based on user role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
        header("Location: admin_requests.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}

$error = '';
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Mohon isi semua kolom";
    } else {
        if (loginUser($username, $password)) {
            // Redirect based on user role after successful login
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
                header("Location: admin_requests.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error = "Username atau kata sandi tidak valid";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk - Sistem Reimbursement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-header">
                        <h3 class="text-center">Masuk Sistem Reimbursement</h3>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label for="username" class="form-label">Username atau Email</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">Masuk</button>
                        </form>

                        <div class="text-center mt-3">
                            <p><a href="register.php">Belum punya akun? Daftar di sini</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>