<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/lang.php';

requireLogin();
if (getUserRole() !== 'admin' && getUserRole() !== 'manager') {
    header("Location: dashboard.php");
    exit();
}

// Get request ID from URL parameter
$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($request_id <= 0) {
    // Redirect to admin requests page if no valid ID provided
    header("Location: admin_requests.php");
    exit();
}

$conn = getConnection();

// Get the request details with related data (for admin, user can view any request)
$sql = "SELECT r.*, c.name as category_name, u.full_name as submitted_by, u.no_rekening as user_no_rekening, approver.full_name as approved_by_name 
        FROM reimbursement_requests r 
        JOIN categories c ON r.category_id = c.id 
        JOIN users u ON r.user_id = u.id 
        LEFT JOIN users approver ON r.approved_by = approver.id
        WHERE r.id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$request_id]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if request exists 
if (!$request) {
    // Redirect to admin requests page if request doesn't exist
    header("Location: admin_requests.php");
    exit();
}

// Handle approval/denial actions
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && isset($_POST['request_id'])) {
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
            $success_message = "Permintaan berhasil di" . ($status == 'approved' ? 'setujui' : 'tolak') . "!";
        } catch(PDOException $e) {
            $error_message = "Kesalahan saat memperbarui permintaan: " . $e->getMessage();
        }
    } elseif ($action === 'pay') {
        $sql = "UPDATE reimbursement_requests SET status = 'paid' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        try {
            $stmt->execute([$request_id]);
            $success_message = "Permintaan ditandai sebagai dibayar!";
        } catch(PDOException $e) {
            $error_message = "Kesalahan saat menandai sebagai dibayar: " . $e->getMessage();
        }
    }
    
    // Refresh the request data after update
    $stmt = $conn->prepare($sql = "SELECT r.*, c.name as category_name, u.full_name as submitted_by, approver.full_name as approved_by_name 
            FROM reimbursement_requests r 
            JOIN categories c ON r.category_id = c.id 
            JOIN users u ON r.user_id = u.id 
            LEFT JOIN users approver ON r.approved_by = approver.id
            WHERE r.id = ?");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get items for this specific request
$items_sql = "SELECT * FROM reimbursement_items WHERE request_id = ? ORDER BY id";
$items_stmt = $conn->prepare($items_sql);
$items_stmt->execute([$request_id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Permintaan - <?php echo htmlspecialchars($request['description'] ?? 'Permintaan Reimbursement'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    </head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">

                <div class="d-flex justify-content-end mb-3 gap-2">
                    <h2 class="flex-grow-1">Detail Permintaan #<?php echo $request['id']; ?></h2>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_requests.php'">
                            Kembali ke Permintaan
                        </button>
                        <?php if ($request['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                Setujui
                            </button>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#denyModal">
                                Tolak
                            </button>
                        <?php elseif ($request['status'] === 'approved'): ?>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#payModal">
                                Tandai Dibayar
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Status:</strong>
                                    <span class="badge bg-<?php
                                        switch($request['status']) {
                                            case 'pending': echo 'warning'; break;
                                            case 'approved': echo 'success'; break;
                                            case 'denied': echo 'danger'; break;
                                            case 'paid': echo 'info'; break;
                                            default: echo 'secondary'; break;
                                        }
                                    ?>">
                                        <?php
                                        switch($request['status']) {
                                            case 'pending': echo 'Pending'; break;
                                            case 'approved': echo 'Disetujui'; break;
                                            case 'denied': echo 'Ditolak'; break;
                                            case 'paid': echo 'Dibayar'; break;
                                            default: echo ucfirst($request['status']); break;
                                        }
                                        ?>
                                    </span>
                                </p>
                                <p><strong>Total Jumlah:</strong> Rp<?php echo number_format($request['total_amount'], 2, ',', '.'); ?></p>
                                <p><strong>Tanggal Pengajuan:</strong> <?php echo date('d M Y', strtotime($request['requested_date'])); ?></p>
                                <?php if ($request['submitted_at']): ?>
                                    <p><strong>Tanggal Pengiriman:</strong> <?php echo date('d M Y H:i', strtotime($request['submitted_at'])); ?></p>
                                <?php endif; ?>
                                <?php if ($request['approved_at']): ?>
                                    <p><strong>Tanggal Disetujui:</strong> <?php echo date('d M Y H:i', strtotime($request['approved_at'])); ?></p>
                                    <p><strong>Disetujui oleh:</strong> <?php echo htmlspecialchars($request['approved_by_name'] ?? 'Tidak ada'); ?></p>
                                <?php endif; ?>
                                <?php if ($request['notes']): ?>
                                    <p><strong>Catatan:</strong> <?php echo htmlspecialchars($request['notes']); ?></p>
                                <?php endif; ?>
                                <p><strong>Nama Lengkap:</strong> <?php echo htmlspecialchars($request['submitted_by']); ?></p>
                                <p><strong>Departemen:</strong> <?php echo htmlspecialchars($request['department']); ?></p>
                                <p><strong>Nomor Rekening:</strong> <?php echo htmlspecialchars($request['user_no_rekening'] ?? 'Tidak diset'); ?></p>
                            </div>
                        </div>

                        <div class="card mt-4">
                            <div class="card-header">
                                <h5>Item Reimbursement</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Nama Item</th>
                                            <th>Jumlah</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($items)): ?>
                                            <?php foreach ($items as $item): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                    <td>Rp<?php echo number_format($item['amount'], 2, ',', '.'); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr>
                                                <td colspan="2" class="text-end"><strong>Total:</strong></td>
                                                <td><strong>Rp<?php echo number_format($request['total_amount'], 2, ',', '.'); ?></strong></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="3" class="text-center">Tidak ada item ditemukan</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($request['description']): ?>
                            <h5 class="mt-4">Deskripsi:</h5>
                            <p><?php echo htmlspecialchars($request['description']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">Setujui Permintaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <p>Apakah Anda yakin ingin menyetujui permintaan ini?</p>
                        <div class="mb-3">
                            <label for="approve_notes" class="form-label">Catatan (opsional)</label>
                            <textarea class="form-control" id="approve_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success">Setujui</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Deny Modal -->
    <div class="modal fade" id="denyModal" tabindex="-1" aria-labelledby="denyModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="denyModalLabel">Tolak Permintaan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <input type="hidden" name="action" value="deny">
                        <p>Apakah Anda yakin ingin menolak permintaan ini?</p>
                        <div class="mb-3">
                            <label for="deny_notes" class="form-label">Catatan (opsional)</label>
                            <textarea class="form-control" id="deny_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-danger">Tolak</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Mark as Paid Modal -->
    <div class="modal fade" id="payModal" tabindex="-1" aria-labelledby="payModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="payModalLabel">Tandai Dibayar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <input type="hidden" name="action" value="pay">
                        <p>Apakah Anda yakin ingin menandai permintaan ini sebagai dibayar?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-info">Tandai Dibayar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>