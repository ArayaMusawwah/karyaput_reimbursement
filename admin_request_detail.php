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
            $success_message = Language::t("Request ") . $status . Language::t(" successfully!");
        } catch(PDOException $e) {
            $error_message = Language::t("Error updating request: ") . $e->getMessage();
        }
    } elseif ($action === 'pay') {
        $sql = "UPDATE reimbursement_requests SET status = 'paid' WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        try {
            $stmt->execute([$request_id]);
            $success_message = Language::t("Request marked as paid!");
        } catch(PDOException $e) {
            $error_message = Language::t("Error marking as paid: ") . $e->getMessage();
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
    <title><?php echo Language::t('Request Details'); ?> - <?php echo htmlspecialchars($request['description'] ?? 'Reimbursement Request'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    </head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-12">

                <div class="d-flex justify-content-end mb-3 gap-2">
                    <h2 class="flex-grow-1"><?php echo Language::t('Request Details'); ?> #<?php echo $request['id']; ?></h2>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='admin_requests.php'">
                            <?php echo Language::t('Back to Requests'); ?>
                        </button>
                        <?php if ($request['status'] === 'pending'): ?>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                <?php echo Language::t('Approve'); ?>
                            </button>
                            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#denyModal">
                                <?php echo Language::t('Deny'); ?>
                            </button>
                        <?php elseif ($request['status'] === 'approved'): ?>
                            <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#payModal">
                                <?php echo Language::t('Mark as Paid'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong><?php echo Language::t('Status'); ?>:</strong> 
                                    <span class="badge bg-<?php
                                        switch($request['status']) {
                                            case 'pending': echo 'warning'; break;
                                            case 'approved': echo 'success'; break;
                                            case 'denied': echo 'danger'; break;
                                            case 'paid': echo 'info'; break;
                                            default: echo 'secondary'; break;
                                        }
                                    ?>">
                                        <?php echo ucfirst(Language::t($request['status'])); ?>
                                    </span>
                                </p>
                                <p><strong><?php echo Language::t('Submitted By'); ?>:</strong> <?php echo htmlspecialchars($request['submitted_by']); ?></p>
                                <p><strong><?php echo Language::t('Category'); ?>:</strong> <?php echo htmlspecialchars($request['category_name']); ?></p>
                                <p><strong><?php echo Language::t('Date'); ?>:</strong> <?php echo date('d F Y', strtotime($request['requested_date'])); ?></p>
                                <p><strong><?php echo Language::t('Total Amount'); ?>:</strong> Rp<?php echo number_format($request['total_amount'], 2, ',', '.'); ?></p>
                                <?php if ($request['receipt_path']): ?>
                                    <p><strong><?php echo Language::t('Receipt'); ?>:</strong> 
                                        <?php 
                                        $fileExtension = pathinfo($request['receipt_path'], PATHINFO_EXTENSION);
                                        $isImage = in_array(strtolower($fileExtension), ['jpg', 'jpeg', 'png', 'gif']);
                                        ?>
                                        <?php if ($isImage): ?>
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#receiptModal"><?php echo Language::t('View Receipt'); ?></a>
                                            <!-- Receipt Lightbox Modal -->
                                            <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
                                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="receiptModalLabel"><?php echo Language::t('Receipt Preview'); ?></h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Language::t('Close'); ?>"></button>
                                                        </div>
                                                        <div class="modal-body text-center">
                                                            <img src="<?php echo htmlspecialchars($request['receipt_path']); ?>" class="img-fluid" alt="<?php echo Language::t('Receipt'); ?>">
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Language::t('Close'); ?></button>
                                                            <a href="<?php echo htmlspecialchars($request['receipt_path']); ?>" target="_blank" class="btn btn-primary"><?php echo Language::t('Open in New Tab'); ?></a>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <a href="<?php echo htmlspecialchars($request['receipt_path']); ?>" target="_blank"><?php echo Language::t('View Receipt'); ?></a>
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <p><strong><?php echo Language::t('Submitted At'); ?>:</strong> <?php echo date('d F Y H:i', strtotime($request['submitted_at'])); ?></p>
                                <?php if ($request['user_no_rekening']): ?>
                                    <p><strong><?php echo Language::t('Bank Account Number'); ?>:</strong> <?php echo htmlspecialchars($request['user_no_rekening']); ?></p>
                                <?php endif; ?>
                                <?php if ($request['approved_at']): ?>
                                    <p><strong><?php echo Language::t('Approved At'); ?>:</strong> <?php echo date('d F Y H:i', strtotime($request['approved_at'])); ?></p>
                                    <?php if ($request['approved_by_name']): ?>
                                        <p><strong><?php echo Language::t('Approved By'); ?>:</strong> <?php echo htmlspecialchars($request['approved_by_name']); ?></p>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($request['notes']): ?>
                                    <p><strong><?php echo Language::t('Notes'); ?>:</strong> <?php echo htmlspecialchars($request['notes']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <h5 class="mt-4"><?php echo Language::t('Items'); ?>:</h5>
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th><?php echo Language::t('No'); ?></th>
                                        <th><?php echo Language::t('Name'); ?></th>
                                        <th><?php echo Language::t('Amount (Rp)'); ?></th>
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
                                            <td colspan="2" class="text-end"><strong><?php echo Language::t('Total'); ?>:</strong></td>
                                            <td><strong>Rp<?php echo number_format($request['total_amount'], 2, ',', '.'); ?></strong></td>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="3" class="text-center"><?php echo Language::t('No items found'); ?></td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if ($request['description']): ?>
                            <h5 class="mt-4"><?php echo Language::t('Description'); ?>:</h5>
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
                    <h5 class="modal-title" id="approveModalLabel"><?php echo Language::t('Approve Request'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Language::t('Close'); ?>"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <p><?php echo Language::t('Are you sure you want to approve this request?'); ?></p>
                        <div class="mb-3">
                            <label for="approve_notes" class="form-label"><?php echo Language::t('Notes (optional)'); ?></label>
                            <textarea class="form-control" id="approve_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Language::t('Cancel'); ?></button>
                        <button type="submit" class="btn btn-success"><?php echo Language::t('Approve'); ?></button>
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
                    <h5 class="modal-title" id="denyModalLabel"><?php echo Language::t('Deny Request'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Language::t('Close'); ?>"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <input type="hidden" name="action" value="deny">
                        <p><?php echo Language::t('Are you sure you want to deny this request?'); ?></p>
                        <div class="mb-3">
                            <label for="deny_notes" class="form-label"><?php echo Language::t('Notes (optional)'); ?></label>
                            <textarea class="form-control" id="deny_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Language::t('Cancel'); ?></button>
                        <button type="submit" class="btn btn-danger"><?php echo Language::t('Deny'); ?></button>
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
                    <h5 class="modal-title" id="payModalLabel"><?php echo Language::t('Mark as Paid'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo Language::t('Close'); ?>"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                        <input type="hidden" name="action" value="pay">
                        <p><?php echo Language::t('Are you sure you want to mark this request as paid?'); ?></p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo Language::t('Cancel'); ?></button>
                        <button type="submit" class="btn btn-info"><?php echo Language::t('Mark as Paid'); ?></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>