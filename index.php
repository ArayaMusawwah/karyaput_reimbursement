<?php
// Check if user is already logged in
session_start();
if (isset($_SESSION['user_id'])) {
    // Redirect based on user role
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
        header("Location: admin_requests.php");
    } else {
        header("Location: dashboard.php");
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Reimbursement</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-header">
                        <h3 class="text-center">Selamat Datang di Sistem Reimbursement</h3>
                    </div>
                    <div class="card-body text-center">
                        <p>Silakan masuk untuk mengakses permintaan reimbursement Anda atau daftar jika Anda belum
                            memiliki akun.</p>

                        <div class="mt-4">
                            <a href="login.php" class="btn btn-primary">Masuk</a>
                            <a href="register.php" class="btn btn-success">Daftar</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Tentang Sistem Reimbursement</h5>
                        <p class="card-text">
                            Sistem reimbursement kami memungkinkan karyawan untuk dengan mudah mengajukan permintaan
                            pengeluaran terkait bisnis dan melacak statusnya.
                            Manajer dan administrator dapat meninjau dan menyetujui permintaan dengan efisien.
                        </p>
                        <ul>
                            <li>Ajukan permintaan pengeluaran dengan bukti</li>
                            <li>Lacak status permintaan secara real-time</li>
                            <li>Tinjau dan setujui permintaan (untuk manajer/admin)</li>
                            <li>Otentikasi pengguna yang aman</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>