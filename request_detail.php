<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/lang.php';

requireLogin();

// Get request ID from URL parameter
$request_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($request_id <= 0) {
    // Redirect to dashboard if no valid ID provided
    header("Location: dashboard.php");
    exit();
}

$conn = getConnection();

// Get the request details with related data
$sql = "SELECT r.*, c.name as category_name, u.full_name as submitted_by, u.no_rekening as user_no_rekening, approver.full_name as approved_by_name 
        FROM reimbursement_requests r 
        JOIN categories c ON r.category_id = c.id 
        JOIN users u ON r.user_id = u.id 
        LEFT JOIN users approver ON r.approved_by = approver.id
        WHERE r.id = ? AND r.user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->execute([$request_id, $_SESSION['user_id']]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if request exists and belongs to current user
if (!$request) {
    // Redirect to dashboard if request doesn't exist or doesn't belong to user
    header("Location: dashboard.php");
    exit();
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
    <title>Detail Permintaan - <?php echo htmlspecialchars($request['description'] ?? 'Permintaan Reimbursement'); ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Detail Permintaan #<?php echo $request['id']; ?></h2>
                    <a href="dashboard.php" class="btn btn-secondary">Kembali ke Dasbor</a>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Status:</strong>
                                    <span class="badge bg-<?php
                                    switch ($request['status']) {
                                        case 'pending':
                                            echo 'warning';
                                            break;
                                        case 'approved':
                                            echo 'success';
                                            break;
                                        case 'denied':
                                            echo 'danger';
                                            break;
                                        case 'paid':
                                            echo 'info';
                                            break;
                                        default:
                                            echo 'secondary';
                                            break;
                                    }
                                    ?>">
                                        <?php
                                        switch ($request['status']) {
                                            case 'pending':
                                                echo 'Menunggu';
                                                break;
                                            case 'approved':
                                                echo 'Disetujui';
                                                break;
                                            case 'denied':
                                                echo 'Ditolak';
                                                break;
                                            case 'paid':
                                                echo 'Dibayar';
                                                break;
                                            default:
                                                echo ucfirst($request['status']);
                                                break;
                                        }
                                        ?>
                                    </span>
                                </p>
                                <p><strong>Kategori:</strong> <?php echo htmlspecialchars($request['category_name']); ?>
                                </p>
                                <p><strong>Tanggal:</strong>
                                    <?php echo date('d F Y', strtotime($request['requested_date'])); ?></p>
                                <p><strong>Total Jumlah:</strong>
                                    Rp<?php echo number_format($request['total_amount'], 2, ',', '.'); ?></p>
                                <?php if ($request['receipt_path']): ?>
                                    <p><strong>Bukti:</strong>
                                        <?php
                                        $fileExtension = pathinfo($request['receipt_path'], PATHINFO_EXTENSION);
                                        $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif']);
                                        ?>
                                        <?php if ($isImage): ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#receiptModal">Lihat Bukti</a>
                                            <!-- Receipt Lightbox Modal -->
                                        <div class="modal fade" id="receiptModal" tabindex="-1"
                                            aria-labelledby="receiptModalLabel" aria-hidden="true">
                                            <div class="modal-dialog modal-xl modal-dialog-centered">
                                                <div class="modal-content">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title" id="receiptModalLabel">Pratinjau Bukti</h5>
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
                                        <a href="<?php echo htmlspecialchars($request['receipt_path']); ?>"
                                            target="_blank">Lihat Bukti</a>
                                    <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Diajukan Pada:</strong>
                                    <?php echo date('d F Y H:i', strtotime($request['submitted_at'])); ?></p>
                                <?php if ($request['user_no_rekening']): ?>
                                    <p><strong>Nomor Rekening:</strong>
                                        <?php echo htmlspecialchars($request['user_no_rekening']); ?></p>
                                <?php endif; ?>
                                <?php if ($request['approved_at']): ?>
                                    <p><strong>Disetujui Pada:</strong>
                                        <?php echo date('d F Y H:i', strtotime($request['approved_at'])); ?></p>
                                    <?php if ($request['approved_by_name']): ?>
                                        <p><strong>Disetujui Oleh:</strong>
                                            <?php echo htmlspecialchars($request['approved_by_name']); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($request['notes']): ?>
                                    <p><strong>Catatan:</strong> <?php echo htmlspecialchars($request['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <h5 class="mt-4">Item:</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Nama</th>
                                        <th>Jumlah (Rp)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($items)): ?>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?php echo $counter++; ?></td>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td>Rp<?php echo number_format($item['amount'], 2, ',', '.'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="table-light">
                                            <td colspan="2" class="text-end"><strong>Total:</strong></td>
                                            <td><strong>Rp<?php echo number_format($request['total_amount'], 2, ',', '.'); ?></strong>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center">Tidak ada item ditemukan</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>