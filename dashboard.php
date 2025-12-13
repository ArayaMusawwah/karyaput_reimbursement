<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/lang.php';

requireLogin();

$conn = getConnection();

// Get categories for filter dropdown
$categories_sql = "SELECT id, name FROM categories ORDER BY name";
$categories_stmt = $conn->query($categories_sql);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination setup
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$itemsPerPage = 10; // Number of items per page
$offset = ($page - 1) * $itemsPerPage;

// Get user's requests with search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$month_filter = isset($_GET['month']) && $_GET['month'] !== '' ? $_GET['month'] : date('Y-m'); // Month filter (default to current month)

// SQL query for counting total results (for pagination)
$countSql = "SELECT COUNT(*) FROM reimbursement_requests r 
             JOIN categories c ON r.category_id = c.id 
             JOIN users u ON r.user_id = u.id 
             LEFT JOIN users approver ON r.approved_by = approver.id
             WHERE r.user_id = ?";

$countParams = [$_SESSION['user_id']];

if (!empty($search)) {
    $countSql .= " AND (r.description LIKE ?)";
    $countParams[] = "%$search%";
}

if (!empty($status_filter)) {
    $countSql .= " AND r.status = ?";
    $countParams[] = $status_filter;
}

if (!empty($category_filter)) {
    $countSql .= " AND r.category_id = ?";
    $countParams[] = $category_filter;
}

if (!empty($date_from)) {
    $countSql .= " AND r.requested_date >= ?";
    $countParams[] = $date_from;
}

if (!empty($date_to)) {
    $countSql .= " AND r.requested_date <= ?";
    $countParams[] = $date_to;
}

// Add month filter if specified
if (!empty($month_filter)) {
    $countSql .= " AND MONTH(r.requested_date) = ? AND YEAR(r.requested_date) = ?";
    $countParams[] = date('m', strtotime($month_filter . '-01'));
    $countParams[] = date('Y', strtotime($month_filter . '-01'));
}

$countStmt = $conn->prepare($countSql);
$countStmt->execute($countParams);
$totalItems = $countStmt->fetchColumn();
$totalPages = ceil($totalItems / $itemsPerPage);

// Main query with pagination limit
$sql = "SELECT r.*, c.name as category_name, u.full_name as submitted_by, approver.full_name as approved_by_name FROM reimbursement_requests r 
        JOIN categories c ON r.category_id = c.id 
        JOIN users u ON r.user_id = u.id 
        LEFT JOIN users approver ON r.approved_by = approver.id
        WHERE r.user_id = ?";

$params = [$_SESSION['user_id']];

if (!empty($search)) {
    $sql .= " AND (r.description LIKE ?)";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $sql .= " AND r.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $sql .= " AND r.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($date_from)) {
    $sql .= " AND r.requested_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $sql .= " AND r.requested_date <= ?";
    $params[] = $date_to;
}

// Add month filter if specified
if (!empty($month_filter)) {
    $sql .= " AND MONTH(r.requested_date) = ? AND YEAR(r.requested_date) = ?";
    $params[] = date('m', strtotime($month_filter . '-01'));
    $params[] = date('Y', strtotime($month_filter . '-01'));
}

// Add the LIMIT and OFFSET directly to the SQL string
$sql .= " ORDER BY r.submitted_at DESC LIMIT " . (int) $itemsPerPage . " OFFSET " . (int) $offset;

$user_requests_stmt = $conn->prepare($sql);
$user_requests_stmt->execute($params);
$user_requests = $user_requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all items for all requests in a single query
if (!empty($user_requests)) {
    $requestIds = array_column($user_requests, 'id');
    $placeholders = str_repeat('?,', count($requestIds) - 1) . '?';
    $items_sql = "SELECT * FROM reimbursement_items WHERE request_id IN ($placeholders) ORDER BY request_id, id";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->execute($requestIds);
    $all_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group items by request_id
    $items_by_request = [];
    foreach ($all_items as $item) {
        $request_id = $item['request_id'];
        if (!isset($items_by_request[$request_id])) {
            $items_by_request[$request_id] = [];
        }
        $items_by_request[$request_id][] = $item;
    }

    // Attach items to each request
    foreach ($user_requests as $key => $request) {
        $request_id = $request['id'];
        $user_requests[$key]['items'] = isset($items_by_request[$request_id]) ? $items_by_request[$request_id] : [];
    }
} else {
    // If no requests, ensure each request has an empty items array
    foreach ($user_requests as $key => $request) {
        $user_requests[$key]['items'] = [];
    }
}

// Get counts for different statuses
$stats_sql = "SELECT 
                 (SELECT COUNT(*) FROM reimbursement_requests WHERE user_id = ? AND status = 'pending') as pending_count,
                 (SELECT COUNT(*) FROM reimbursement_requests WHERE user_id = ? AND status = 'approved') as approved_count,
                 (SELECT COUNT(*) FROM reimbursement_requests WHERE user_id = ? AND status = 'denied') as denied_count,
                 (SELECT COUNT(*) FROM reimbursement_requests WHERE user_id = ? AND status = 'paid') as paid_count";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dasbor - Sistem Reimbursement</title>
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

        /* Stats card tweaks for better mobile spacing and sizing */
        .stats-card .card-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
            padding-top: 1rem;
            padding-bottom: 1rem;
        }

        .stats-card .card-title {
            font-size: 1.5rem;
            margin-bottom: 0;
            line-height: 1;
        }

        @media (max-width: 576px) {
            .stats-card .card-title {
                font-size: 1.25rem;
            }

            .stats-card {
                margin-bottom: 0.6rem;
            }
        }

        /* Search/filter spacing tweaks */
        .filters-row .form-control,
        .filters-row .btn {
            margin-bottom: 0.5rem;
        }

        @media (min-width: 768px) {

            /* Remove extra bottom margin on md+ where grid provides spacing */
            .filters-row .form-control,
            .filters-row .btn {
                margin-bottom: 0;
            }
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <h2>Selamat Datang, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h2>
                <p>Ringkasan reimbursement Anda</p>

                <div class="row mb-4">
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card text-center bg-primary text-white stats-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stats['pending_count']; ?></h5>
                                <p class="card-text">Menunggu</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card text-center bg-success text-white stats-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stats['approved_count']; ?></h5>
                                <p class="card-text">Disetujui</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card text-center bg-danger text-white stats-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stats['denied_count']; ?></h5>
                                <p class="card-text">Ditolak</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 mb-2">
                        <div class="card text-center bg-info text-white stats-card">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo $stats['paid_count']; ?></h5>
                                <p class="card-text">Dibayar</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3>Permintaan Anda</h3>
                    <a href="request.php" class="btn btn-success">Permintaan Baru</a>
                </div>

                <!-- Search and Filter Section -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET">
                            <div class="row g-2 filters-row">
                                <div class="col-md-3">
                                    <input type="text" class="form-control" name="search" placeholder="Cari"
                                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <select class="form-control" name="status">
                                        <option value="">Semua Status</option>
                                        <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Menunggu</option>
                                        <option value="approved" <?php echo (isset($_GET['status']) && $_GET['status'] == 'approved') ? 'selected' : ''; ?>>Disetujui</option>
                                        <option value="denied" <?php echo (isset($_GET['status']) && $_GET['status'] == 'denied') ? 'selected' : ''; ?>>Ditolak</option>
                                        <option value="paid" <?php echo (isset($_GET['status']) && $_GET['status'] == 'paid') ? 'selected' : ''; ?>>Dibayar</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-control" name="category">
                                        <option value="">Semua Kategori</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="month" class="form-control" name="month"
                                        value="<?php echo htmlspecialchars($month_filter); ?>">
                                </div>
                                <div class="col-md-1">
                                    <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                                </div>
                                <div class="col-md-2">
                                    <a href="dashboard.php" class="btn btn-secondary">Hapus Filter</a>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-md-12">
                                    <div class="form-text">Gunakan rentang tanggal atau filter bulan (rentang tanggal
                                        diprioritaskan)</div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <?php
                // Get user's requests with search and filter
                $search = isset($_GET['search']) ? $_GET['search'] : '';
                $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
                $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
                $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
                $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

                $sql = "SELECT r.*, c.name as category_name, u.full_name as submitted_by FROM reimbursement_requests r 
                        JOIN categories c ON r.category_id = c.id 
                        JOIN users u ON r.user_id = u.id 
                        WHERE r.user_id = ?";

                $params = [$_SESSION['user_id']];

                if (!empty($search)) {
                    $sql .= " AND (r.description LIKE ?)";
                    $params[] = "%$search%";
                }

                if (!empty($status_filter)) {
                    $sql .= " AND r.status = ?";
                    $params[] = $status_filter;
                }

                if (!empty($category_filter)) {
                    $sql .= " AND r.category_id = ?";
                    $params[] = $category_filter;
                }

                if (!empty($date_from)) {
                    $sql .= " AND r.requested_date >= ?";
                    $params[] = $date_from;
                }

                if (!empty($date_to)) {
                    $sql .= " AND r.requested_date <= ?";
                    $params[] = $date_to;
                }

                $sql .= " ORDER BY r.submitted_at DESC";

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $user_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
                ?>

                <?php if (count($user_requests) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Kategori</th>
                                    <th>Total Jumlah (Rp)</th>
                                    <th>Tanggal</th>
                                    <th>Status</th>
                                    <th>Diajukan Pada</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($user_requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                        <td>Rp<?php echo number_format($request['total_amount'], 2, ',', '.'); ?></td>
                                        <td><?php echo date('d M Y', strtotime($request['requested_date'])); ?></td>
                                        <td>
                                            <?php
                                            $status_class = '';
                                            switch ($request['status']) {
                                                case 'pending':
                                                    $status_class = 'warning';
                                                    break;
                                                case 'approved':
                                                    $status_class = 'success';
                                                    break;
                                                case 'denied':
                                                    $status_class = 'danger';
                                                    break;
                                                case 'paid':
                                                    $status_class = 'info';
                                                    break;
                                            }
                                            ?>
                                            <span
                                                class="badge bg-<?php echo $status_class; ?>"><?php echo ucfirst($request['status']); ?></span>
                                            <?php if ($request['status'] === 'approved'): ?>
                                                <br><small class="text-muted">Disetujui oleh:
                                                    <?php echo htmlspecialchars($request['approved_by_name']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y g:i A', strtotime($request['submitted_at'])); ?></td>
                                        <td>
                                            <!-- Detail button -->
                                            <a href="request_detail.php?id=<?php echo $request['id']; ?>"
                                                class="btn btn-sm btn-info">
                                                Detail
                                            </a>

                                            <?php if ($request['receipt_path']): ?>
                                                <?php
                                                $fileExtension = pathinfo($request['receipt_path'], PATHINFO_EXTENSION);
                                                $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif']);
                                                ?>
                                                <?php if ($isImage): ?>
                                                    <a href="<?php echo htmlspecialchars($request['receipt_path']); ?>"
                                                        class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                                        data-bs-target="#receiptModal<?php echo $request['id']; ?>">
                                                        Lihat Bukti
                                                    </a>
                                                    <!-- Receipt Lightbox Modal -->
                                                    <div class="modal fade" id="receiptModal<?php echo $request['id']; ?>" tabindex="-1"
                                                        aria-labelledby="receiptModalLabel<?php echo $request['id']; ?>"
                                                        aria-hidden="true">
                                                        <div class="modal-dialog modal-xl modal-dialog-centered">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title"
                                                                        id="receiptModalLabel<?php echo $request['id']; ?>">Pratinjau
                                                                        Bukti</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                                        aria-label="Tutup"></button>
                                                                </div>
                                                                <div class="modal-body text-center">
                                                                    <img src="<?php echo htmlspecialchars($request['receipt_path']); ?>"
                                                                        class="img-fluid" alt="Bukti">
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary"
                                                                        data-bs-dismiss="modal">Tutup</button>
                                                                    <a href="<?php echo htmlspecialchars($request['receipt_path']); ?>"
                                                                        target="_blank" class="btn btn-primary">Buka di Tab Baru</a>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <a href="<?php echo htmlspecialchars($request['receipt_path']); ?>" target="_blank"
                                                        class="btn btn-sm btn-outline-primary">
                                                        Lihat Bukti
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Tidak ada bukti</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>Anda belum mengajukan permintaan reimbursement apapun.</p>
                    <a href="request.php" class="btn btn-success">Ajukan Permintaan Pertama Anda</a>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Reimbursement requests pagination">
                        <ul class="pagination justify-content-center mt-4">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Sebelumnya</a>
                                </li>
                            <?php endif; ?>

                            <?php
                            // Show first page, current page, and last page with ellipsis if needed
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);

                            if ($startPage > 1): ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                </li>
                                <?php if ($startPage > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                    <a class="page-link"
                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a>
                                </li>
                            <?php endif; ?>

                            <?php if ($page < $totalPages): ?>
                                <li class="page-item">
                                    <a class="page-link"
                                        href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Selanjutnya</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="toast-container" class="toast-container"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to show toast notifications
        function showToast(message, type) {
            const toastId = 'toast-' + Date.now();
            const toastClass = type === 'success' ? 'text-bg-success' : 'text-bg-danger';
            const toastHTML = `
                <div id="${toastId}" class="toast toast-custom ${toastClass}" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <strong class="me-auto">
                            ${type === 'success' ? 'Success' : 'Error'}
                        </strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;

            document.getElementById('toast-container').innerHTML += toastHTML;

            const toastEl = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastEl, {
                delay: 5000,
                autohide: true
            });
            toast.show();
        }
    </script>
</body>

</html>