<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/lang.php';

requireLogin();
if (getUserRole() !== 'admin' && getUserRole() !== 'manager') {
    header("Location: dashboard.php");
    exit();
}

$conn = getConnection();

// Get categories for filter dropdown
$categories_sql = "SELECT id, name FROM categories ORDER BY name";
$categories_stmt = $conn->query($categories_sql);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle approval/denial actions
if (isset($_POST['action']) && isset($_POST['request_id'])) {
    $action = $_POST['action'];
    $request_id = intval($_POST['request_id']);
    $notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

    // Only allow admin/manager to approve if they have proper permissions
    if ($action === 'approve' || $action === 'deny') {
        $status = ($action === 'approve') ? 'approved' : 'denied';
        $sql = "UPDATE reimbursement_requests SET status = ?, approved_by = ?, approved_at = NOW(), notes = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);

        try {
            $stmt->execute([$status, $_SESSION['user_id'], $notes, $request_id]);
            $success = "Permintaan berhasil di" . ($status == 'approved' ? 'setujui' : 'tolak') . "!";
        } catch (PDOException $e) {
            $error = "Kesalahan saat memperbarui permintaan: " . $e->getMessage();
        }
    } elseif ($action === 'pay') {
        $sql = "UPDATE reimbursement_requests SET status = 'paid' WHERE id = ?";
        $stmt = $conn->prepare($sql);

        try {
            $stmt->execute([$request_id]);
            $success = "Permintaan ditandai sebagai dibayar!";
        } catch (PDOException $e) {
            $error = "Kesalahan saat menandai sebagai dibayar: " . $e->getMessage();
        }
    }
}

// Get counts for different statuses (without filters)
$stats_sql = "SELECT 
                 (SELECT COUNT(*) FROM reimbursement_requests WHERE status = 'pending') as pending_count,
                 (SELECT COUNT(*) FROM reimbursement_requests WHERE status = 'approved') as approved_count,
                 (SELECT COUNT(*) FROM reimbursement_requests WHERE status = 'denied') as denied_count,
                 (SELECT COUNT(*) FROM reimbursement_requests WHERE status = 'paid') as paid_count";
$stats_stmt = $conn->query($stats_sql);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get requests with search and filter
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

$sql = "SELECT r.*, c.name as category_name, u.full_name as submitted_by, approver.full_name as approved_by_name 
        FROM reimbursement_requests r 
        JOIN categories c ON r.category_id = c.id 
        JOIN users u ON r.user_id = u.id 
        LEFT JOIN users approver ON r.approved_by = approver.id";

$params = [];

$where_conditions = [];

if (!empty($search)) {
    $where_conditions[] = "(r.description LIKE ? OR u.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status_filter)) {
    $where_conditions[] = "r.status = ?";
    $params[] = $status_filter;
}

if (!empty($category_filter)) {
    $where_conditions[] = "r.category_id = ?";
    $params[] = $category_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "r.requested_date >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "r.requested_date <= ?";
    $params[] = $date_to;
}

if (!empty($where_conditions)) {
    $sql .= " WHERE " . implode(' AND ', $where_conditions);
}

$sql .= " ORDER BY r.submitted_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// For each request, get the associated items
foreach ($requests as $key => $request) {
    $items_sql = "SELECT * FROM reimbursement_items WHERE request_id = ? ORDER BY id";
    $items_stmt = $conn->prepare($items_sql);
    $items_stmt->execute([$request['id']]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    $requests[$key]['items'] = $items ?: []; // Ensure it's an array even if no items
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Kelola Permintaan</title>
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
        <h2>Kelola Permintaan Reimbursement</h2>

        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $stats['pending_count']; ?></h5>
                        <p class="card-text">Menunggu</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $stats['approved_count']; ?></h5>
                        <p class="card-text">Disetujui</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $stats['denied_count']; ?></h5>
                        <p class="card-text">Ditolak</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title"><?php echo $stats['paid_count']; ?></h5>
                        <p class="card-text">Dibayar</p>
                    </div>
                </div>
            </div>
        </div>


        <div id="toast-container" class="toast-container"></div>

        <!-- Search and Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET">
                    <div class="row">
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
                                    <option value="<?php echo $category['id']; ?>" <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_from" placeholder="Dari Tanggal"
                                value="<?php echo isset($_GET['date_from']) ? htmlspecialchars($_GET['date_from']) : ''; ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="date" class="form-control" name="date_to" placeholder="Sampai Tanggal"
                                value="<?php echo isset($_GET['date_to']) ? htmlspecialchars($_GET['date_to']) : ''; ?>">
                        </div>
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-12">
                            <a href="admin_requests.php" class="btn btn-secondary">Hapus Filter</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php
        // Pagination setup
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $page = max(1, $page); // Ensure page is at least 1
        $itemsPerPage = 10; // Number of items per page
        $offset = ($page - 1) * $itemsPerPage;

        // Get requests with search and filter
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
        $category_filter = isset($_GET['category']) ? $_GET['category'] : '';
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

        // SQL query for counting total results (for pagination)
        $countSql = "SELECT COUNT(*) FROM reimbursement_requests r 
                             JOIN categories c ON r.category_id = c.id 
                             JOIN users u ON r.user_id = u.id 
                             LEFT JOIN users approver ON r.approved_by = approver.id";

        $countParams = [];

        $countWhereConditions = [];

        if (!empty($search)) {
            $countWhereConditions[] = "(r.description LIKE ? OR u.full_name LIKE ?)";
            $countParams[] = "%$search%";
            $countParams[] = "%$search%";
        }

        if (!empty($status_filter)) {
            $countWhereConditions[] = "r.status = ?";
            $countParams[] = $status_filter;
        }

        if (!empty($category_filter)) {
            $countWhereConditions[] = "r.category_id = ?";
            $countParams[] = $category_filter;
        }

        if (!empty($date_from)) {
            $countWhereConditions[] = "r.requested_date >= ?";
            $countParams[] = $date_from;
        }

        if (!empty($date_to)) {
            $countWhereConditions[] = "r.requested_date <= ?";
            $countParams[] = $date_to;
        }

        if (!empty($countWhereConditions)) {
            $countSql .= " WHERE " . implode(' AND ', $countWhereConditions);
        }

        $countStmt = $conn->prepare($countSql);
        $countStmt->execute($countParams);
        $totalItems = $countStmt->fetchColumn();
        $totalPages = ceil($totalItems / $itemsPerPage);

        // Main query with pagination limit
        $sql = "SELECT r.*, c.name as category_name, u.full_name as submitted_by, approver.full_name as approved_by_name 
                        FROM reimbursement_requests r 
                        JOIN categories c ON r.category_id = c.id 
                        JOIN users u ON r.user_id = u.id 
                        LEFT JOIN users approver ON r.approved_by = approver.id";

        $params = [];

        $where_conditions = [];

        if (!empty($search)) {
            $where_conditions[] = "(r.description LIKE ? OR u.full_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        if (!empty($status_filter)) {
            $where_conditions[] = "r.status = ?";
            $params[] = $status_filter;
        }

        if (!empty($category_filter)) {
            $where_conditions[] = "r.category_id = ?";
            $params[] = $category_filter;
        }

        if (!empty($date_from)) {
            $where_conditions[] = "r.requested_date >= ?";
            $params[] = $date_from;
        }

        if (!empty($date_to)) {
            $where_conditions[] = "r.requested_date <= ?";
            $params[] = $date_to;
        }

        if (!empty($where_conditions)) {
            $sql .= " WHERE " . implode(' AND ', $where_conditions);
        }

        // Add the LIMIT and OFFSET directly to the SQL string
        $sql .= " ORDER BY r.submitted_at DESC LIMIT " . (int) $itemsPerPage . " OFFSET " . (int) $offset;

        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all items for all requests in a single query
        if (!empty($requests)) {
            $requestIds = array_column($requests, 'id');
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
            foreach ($requests as $key => $request) {
                $request_id = $request['id'];
                $requests[$key]['items'] = isset($items_by_request[$request_id]) ? $items_by_request[$request_id] : [];
            }
        } else {
            // If no requests, ensure each request has an empty items array
            foreach ($requests as $key => $request) {
                $requests[$key]['items'] = [];
            }
        }
        ?>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Diajukan Oleh</th>
                        <th>Deskripsi</th>
                        <th>Kategori</th>
                        <th>Total Jumlah (Rp)</th>
                        <th>Tanggal</th>
                        <th>Status</th>
                        <th>Diajukan Pada</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                        <tr>
                            <td><?php echo $request['id']; ?></td>
                            <td><?php echo htmlspecialchars($request['submitted_by']); ?></td>
                            <td><?php echo htmlspecialchars($request['description']); ?></td>
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
                                <div class="btn-group-vertical" role="group">
                                    <!-- Detail button -->
                                    <a href="admin_request_detail.php?id=<?php echo $request['id']; ?>"
                                        class="btn btn-sm btn-info btn-block mb-1">
                                        Detail
                                    </a>

                                    <?php if ($request['receipt_path']): ?>
                                        <?php
                                        $fileExtension = pathinfo($request['receipt_path'], PATHINFO_EXTENSION);
                                        $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif']);
                                        ?>
                                        <?php if ($isImage): ?>
                                            <a href="<?php echo htmlspecialchars($request['receipt_path']); ?>"
                                                class="btn btn-sm btn-outline-primary btn-block mb-1" data-bs-toggle="modal"
                                                data-bs-target="#receiptModal<?php echo $request['id']; ?>">
                                                Bukti
                                            </a>
                                            <!-- Receipt Lightbox Modal -->
                                            <div class="modal fade" id="receiptModal<?php echo $request['id']; ?>" tabindex="-1"
                                                aria-labelledby="receiptModalLabel<?php echo $request['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title"
                                                                id="receiptModalLabel<?php echo $request['id']; ?>">Pratinjau Bukti
                                                            </h5>
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
                                                class="btn btn-sm btn-outline-primary btn-block mb-1">
                                                Bukti
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($request['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline mb-1">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="confirm_action" value="1">
                                            <button type="button"
                                                class="btn btn-sm btn-success approve-btn btn-block">Setujui</button>
                                        </form>

                                        <!-- Deny button with modal -->
                                        <button type="button" class="btn btn-sm btn-danger btn-block mb-1"
                                            data-bs-toggle="modal" data-bs-target="#denyModal<?php echo $request['id']; ?>">
                                            Tolak
                                        </button>

                                        <!-- Deny Modal -->
                                        <div class="modal fade" id="denyModal<?php echo $request['id']; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Tolak Permintaan #<?php echo $request['id']; ?>
                                                        </h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <form method="POST">
                                                        <div class="modal-body">
                                                            <input type="hidden" name="request_id"
                                                                value="<?php echo $request['id']; ?>">
                                                            <input type="hidden" name="action" value="deny">
                                                            <div class="mb-3">
                                                                <label for="notes<?php echo $request['id']; ?>"
                                                                    class="form-label">Catatan (opsional)</label>
                                                                <textarea class="form-control"
                                                                    id="notes<?php echo $request['id']; ?>" name="notes"
                                                                    rows="3"></textarea>
                                                            </div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">Batal</button>
                                                            <button type="submit" class="btn btn-danger">Tolak
                                                                Permintaan</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                    <?php elseif ($request['status'] === 'approved'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <input type="hidden" name="action" value="pay">
                                            <input type="hidden" name="confirm_action" value="1">
                                            <button type="button" class="btn btn-sm btn-info pay-btn btn-block">Tandai
                                                Dibayar</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (isset($success) || isset($error)): ?>
            // Show toast notifications
            document.addEventListener('DOMContentLoaded', function () {
                const toastContainer = document.getElementById('toast-container');

                <?php if (isset($success)): ?>
                    showToast('<?php echo addslashes($success); ?>', 'success');
                <?php endif; ?>

                <?php if (isset($error)): ?>
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

        // Handle approval confirmation
        document.querySelectorAll('.approve-btn').forEach(button => {
            button.addEventListener('click', function () {
                const form = this.closest('form');
                const requestId = form.getAttribute('data-request-id');

                // Show confirmation toast
                showConfirmationToast(
                    '<?php echo addslashes("Apakah Anda yakin ingin menyetujui permintaan ini?"); ?>',
                    function () {
                        // Submit the form
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'approve';
                        form.appendChild(actionInput);

                        form.submit();
                    }
                );
            });
        });

        // Handle payment confirmation
        document.querySelectorAll('.pay-btn').forEach(button => {
            button.addEventListener('click', function () {
                const form = this.closest('form');
                const requestId = form.getAttribute('data-request-id');

                // Show confirmation toast
                showConfirmationToast(
                    '<?php echo addslashes("Apakah Anda yakin ingin menandai ini sebagai dibayar?"); ?>',
                    function () {
                        // Submit the form
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = 'pay';
                        form.appendChild(actionInput);

                        form.submit();
                    }
                );
            });
        });

        function showConfirmationToast(message, callback) {
            const toastId = 'confirm-toast-' + Date.now();
            const toastHTML = `
                <div id="${toastId}" class="toast toast-custom text-bg-warning" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="toast-header">
                        <strong class="me-auto">Konfirmasi</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body">
                        <div>${message}</div>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-success me-2 confirm-yes">Ya</button>
                            <button class="btn btn-sm btn-secondary confirm-no">Tidak</button>
                        </div>
                    </div>
                </div>
            `;

            document.getElementById('toast-container').innerHTML = toastHTML;

            const toastEl = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastEl, {
                delay: 10000, // Show longer for confirmation
                autohide: false
            });
            toast.show();

            // Handle confirmation buttons
            toastEl.querySelector('.confirm-yes').addEventListener('click', function () {
                toast.hide();
                callback();
            });

            toastEl.querySelector('.confirm-no').addEventListener('click', function () {
                toast.hide();
            });
        }
    </script>
</body>

</html>