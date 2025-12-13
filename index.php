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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reimbursement System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card mt-5">
                    <div class="card-header">
                        <h3 class="text-center">Welcome to Reimbursement System</h3>
                    </div>
                    <div class="card-body text-center">
                        <p>Please login to access your reimbursement requests or register if you don't have an account.</p>
                        
                        <div class="mt-4">
                            <a href="login.php" class="btn btn-primary">Login</a>
                            <a href="register.php" class="btn btn-success">Register</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-8 offset-md-2">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">About the Reimbursement System</h5>
                        <p class="card-text">
                            Our reimbursement system allows employees to easily submit requests for business-related expenses and track their status.
                            Managers and administrators can review and approve requests efficiently.
                        </p>
                        <ul>
                            <li>Submit expense requests with receipts</li>
                            <li>Track request status in real-time</li>
                            <li>Review and approve requests (for managers/admins)</li>
                            <li>Secure user authentication</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>