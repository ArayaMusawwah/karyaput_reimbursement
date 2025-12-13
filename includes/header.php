<nav class="navbar navbar-expand-lg navbar-light bg-white shadow">
    <div class="container">
        <a class="navbar-brand"
            href="<?php echo (getUserRole() === 'manager') ? 'admin_requests.php' : 'dashboard.php'; ?>">Sistem
            Reimbursement</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if (getUserRole() !== 'manager'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'fw-bold' : ''; ?>"
                            href="dashboard.php">Dasbor</a>
                    </li>
                <?php endif; ?>
                <?php if (getUserRole() !== 'manager'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'request.php') ? 'fw-bold' : ''; ?>"
                            href="request.php">Permintaan Baru</a>
                    </li>
                <?php endif; ?>

                <?php if (getUserRole() === 'admin' || getUserRole() === 'manager'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_requests.php') ? 'fw-bold' : ''; ?>"
                            href="admin_requests.php">Kelola Permintaan</a>
                    </li>
                <?php endif; ?>

                <?php if (getUserRole() === 'admin'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'admin_users.php') ? 'fw-bold' : ''; ?>"
                            href="admin_users.php">Kelola Pengguna</a>
                    </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'fw-bold' : ''; ?> dropdown-toggle"
                        href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        (<?php echo htmlspecialchars($_SESSION['role']); ?>)
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo (basename($_SERVER['PHP_SELF']) == 'profile.php') ? 'fw-bold' : ''; ?>"
                                href="profile.php">Profil</a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="logout.php">Keluar</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>