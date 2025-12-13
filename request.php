<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';
require_once 'includes/logger.php';

requireLogin();

// Managers are not allowed to submit requests
if (getUserRole() === 'manager') {
    header("Location: dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $description = trim($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $requested_date = $_POST['requested_date'];
    $items = $_POST['items'] ?? [];

    // Validate items
    $validItems = [];
    $totalAmount = 0;

    foreach ($items as $item) {
        if (!empty(trim($item['name'])) && isset($item['amount']) && $item['amount'] > 0) {
            $name = trim($item['name']);
            $amount = floatval($item['amount']);

            $validItems[] = [
                'name' => $name,
                'amount' => $amount
            ];

            $totalAmount += $amount;
        }
    }

    if (empty($validItems)) {
        $error = "Mohon tambahkan setidaknya satu item dengan nama dan jumlah yang valid.";
    }

    Logger::info("Processing reimbursement request submission", [
        'user_id' => $_SESSION['user_id'],
        'description' => $description,
        'category_id' => $category_id,
        'items_count' => count($validItems),
        'total_amount' => $totalAmount
    ]);

    // Handle file upload if provided
    $receipt_path = null;
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $filename = $_FILES['receipt']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);

        if (in_array($filetype, $allowed)) {
            $new_filename = uniqid() . '.' . $filetype;
            $upload_path = 'uploads/' . $new_filename;

            if (move_uploaded_file($_FILES['receipt']['tmp_name'], $upload_path)) {
                $receipt_path = $upload_path;
                Logger::info("Receipt uploaded successfully", ['file_path' => $upload_path]);
            } else {
                $error = "Terjadi kesalahan saat mengunggah bukti Anda.";
                Logger::error("Error uploading receipt", ['user_id' => $_SESSION['user_id'], 'error' => $error]);
            }
        } else {
            $error = "Jenis file tidak valid. Hanya file JPG, JPEG, PNG, dan PDF yang diperbolehkan.";
            Logger::warning("Invalid file type for receipt", [
                'user_id' => $_SESSION['user_id'],
                'file_type' => $filetype,
                'allowed_types' => $allowed
            ]);
        }
    }

    if (empty($error)) {
        $conn = getConnection();
        $conn->beginTransaction();

        try {
            // Insert the main request
            $sql = "INSERT INTO reimbursement_requests (user_id, description, category_id, total_amount, receipt_path, requested_date) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$_SESSION['user_id'], $description, $category_id, $totalAmount, $receipt_path, $requested_date]);

            $request_id = $conn->lastInsertId();

            // Insert each item
            $itemSql = "INSERT INTO reimbursement_items (request_id, name, amount) VALUES (?, ?, ?)";
            $itemStmt = $conn->prepare($itemSql);

            foreach ($validItems as $item) {
                $itemStmt->execute([$request_id, $item['name'], $item['amount']]);
            }

            $conn->commit();

            $success = "Permintaan reimbursement berhasil diajukan!";
            Logger::info("Reimbursement request submitted successfully", [
                'user_id' => $_SESSION['user_id'],
                'request_id' => $request_id,
                'items_count' => count($validItems),
                'total_amount' => $totalAmount
            ]);

            // Redirect to dashboard after successful submission
            header("Location: dashboard.php");
            exit();

        } catch (PDOException $e) {
            $conn->rollback();
            $error = "Kesalahan saat mengajukan permintaan: " . $e->getMessage();
            Logger::error("Error submitting reimbursement request", [
                'user_id' => $_SESSION['user_id'],
                'error' => $e->getMessage()
            ]);
        }
    } else {
        Logger::warning("Reimbursement request submission failed", [
            'user_id' => $_SESSION['user_id'],
            'error' => $error
        ]);
    }
}

// Get categories for the form
$conn = getConnection();
$categories_sql = "SELECT id, name FROM categories ORDER BY name";
$categories_stmt = $conn->query($categories_sql);
$categories = $categories_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajukan Permintaan Reimbursement</title>
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

        .item-row {
            border: 1px solid #ddd;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 5px;
        }

        .remove-item {
            margin-top: 22px;
        }
    </style>
</head>

<body>
    <?php include 'includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-10">
                <h2>Ajukan Permintaan Reimbursement</h2>

                <?php if ($error || $success): ?>
                    <div id="toast-container" class="toast-container"></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" id="requestForm">
                    <div class="mb-3">
                        <label for="description" class="form-label">Deskripsi</label>
                        <textarea class="form-control" id="description" name="description" rows="3"
                            placeholder="Masukkan deskripsi singkat permintaan reimbursement Anda"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">Kategori</label>
                            <select class="form-control" id="category_id" name="category_id" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" <?php
                                       // Auto-select category with ID 1 if none is selected via POST
                                       if (
                                           (!isset($_POST['category_id']) && $category['id'] == 1) ||
                                           (isset($_POST['category_id']) && $_POST['category_id'] == $category['id'])
                                       ) {
                                           echo 'selected';
                                       }
                                       ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="requested_date" class="form-label">Tanggal Pengeluaran</label>
                            <input type="date" class="form-control" id="requested_date" name="requested_date" required
                                value="<?php echo isset($_POST['requested_date']) ? htmlspecialchars($_POST['requested_date']) : date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Item</label>
                        <div id="items-container">
                            <?php
                            // Server-render initial item rows so the first input is always present
                            $postedItems = $_POST['items'] ?? [];
                            if (!empty($postedItems) && is_array($postedItems)) {
                                foreach ($postedItems as $i => $it) {
                                    $nameVal = isset($it['name']) ? htmlspecialchars($it['name']) : '';
                                    $amountVal = isset($it['amount']) ? htmlspecialchars($it['amount']) : '';
                                    echo '<div class="item-row">';
                                    echo '<div class="row">';
                                    echo '<div class="col-md-6">';
                                    echo '<label class="form-label" for="item-name-' . $i . '">Nama *</label>';
                                    echo '<input id="item-name-' . $i . '" type="text" class="form-control item-name" name="items[' . $i . '][name]" required value="' . $nameVal . '" placeholder="Masukkan nama item">';
                                    echo '</div>';
                                    echo '<div class="col-md-5">';
                                    echo '<label class="form-label" for="item-amount-' . $i . '">Jumlah (Rp) *</label>';
                                    echo '<input id="item-amount-' . $i . '" type="number" step="0.01" class="form-control item-amount" name="items[' . $i . '][amount]" min="0" required value="' . $amountVal . '" placeholder="0.00">';
                                    echo '</div>';
                                    echo '<div class="col-md-1">';
                                    echo '<label class="form-label">&nbsp;</label>';
                                    echo '<button type="button" class="btn btn-danger remove-item">X</button>';
                                    echo '</div>';
                                    echo '</div>';
                                    echo '</div>';
                                }
                            } else {
                                // Render one empty row by default
                                echo '<div class="item-row">';
                                echo '<div class="row">';
                                echo '<div class="col-md-6">';
                                echo '<label class="form-label" for="item-name-0">Nama *</label>';
                                echo '<input id="item-name-0" type="text" class="form-control item-name" name="items[0][name]" required value="" placeholder="Masukkan nama item">';
                                echo '</div>';
                                echo '<div class="col-md-5">';
                                echo '<label class="form-label" for="item-amount-0">Jumlah (Rp) *</label>';
                                echo '<input id="item-amount-0" type="number" step="0.01" class="form-control item-amount" name="items[0][amount]" min="0" required value="" placeholder="0.00">';
                                echo '</div>';
                                echo '<div class="col-md-1">';
                                echo '<label class="form-label">&nbsp;</label>';
                                echo '<button type="button" class="btn btn-danger remove-item">X</button>';
                                echo '</div>';
                                echo '</div>';
                                echo '</div>';
                            }
                            ?>
                        </div>
                        <button type="button" class="btn btn-outline-primary" id="add-item">
                            + Tambah Item
                        </button>
                        <span id="js-status" class="badge bg-info text-dark ms-2">JS: waiting</span>
                    </div>

                    <div class="mb-3">
                        <label for="receipt" class="form-label">Bukti (opsional)</label>
                        <input type="file" class="form-control" id="receipt" name="receipt"
                            accept=".jpg,.jpeg,.png,.pdf" placeholder="Pilih file bukti...">
                        <div class="form-text">Unggah bukti gambar atau PDF (file JPG, PNG, PDF diperbolehkan)</div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <div>
                            <strong>Total Jumlah: </strong>
                            <span id="total-amount">Rp0</span>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-success">Ajukan Permintaan</button>
                            <a href="dashboard.php" class="btn btn-secondary">Batal</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function () {
            // Prevent duplicate script initialization
            if (window.reimbursementScriptLoaded) {
                return;
            }
            window.reimbursementScriptLoaded = true;

            // Update visible JS status
            const jsStatus = document.getElementById('js-status');
            if (jsStatus) jsStatus.textContent = 'Active';

            // Define language variables for JavaScript
            const langName = "Nama";
            const langAmount = "Jumlah (Rp)";

            // Pass any previously-posted items from PHP to JS
            const initialItems = <?php echo json_encode(isset($_POST['items']) ? $_POST['items'] : []); ?>;

            // Initialize items on DOM ready: populate from POST if present, otherwise add one empty row
            function initItems() {
                const container = document.getElementById('items-container');

                // If rows were server-rendered (for non-JS fallback or after POST), attach listeners instead
                if (container && container.children.length > 0) {
                    // Attach listeners to existing rows
                    const rows = container.querySelectorAll('.item-row');
                    rows.forEach(row => {
                        const amountInput = row.querySelector('.item-amount');
                        if (amountInput) amountInput.addEventListener('input', updateTotalAmount);
                        const removeBtn = row.querySelector('.remove-item');
                        if (removeBtn) removeBtn.addEventListener('click', function () { removeItem(this); });
                    });
                    // Ensure names are sequential
                    reindexItems();
                } else {
                    if (initialItems && Array.isArray(initialItems) && initialItems.length > 0) {
                        initialItems.forEach(it => addItemRow(it));
                    } else {
                        addItemRow();
                    }
                }

                // Attach add-item click handler now that the button exists
                const addBtn = document.getElementById('add-item');
                if (addBtn && !addBtn.hasAttribute('data-handler-attached')) {
                    addBtn.setAttribute('data-handler-attached', 'true');
                    addBtn.addEventListener('click', function () {
                        addItemRow();
                    });
                }

                updateTotalAmount();
            }

            // Ensure init runs whether DOMContentLoaded already fired or not
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initItems);
            } else {
                initItems();
            }

            // Add new item row (optionally with data)
            // Attach listener after DOM is ready (below) to avoid null when script runs early

            function addItemRow(data = null) {
                const container = document.getElementById('items-container');
                const itemIndex = container.children.length;

                const itemDiv = document.createElement('div');
                itemDiv.className = 'item-row';
                itemDiv.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <label class="form-label" for="item-name-` + itemIndex + `">` + langName + ` *</label>
                        <input id="item-name-` + itemIndex + `" type="text" class="form-control item-name" name="items[` + itemIndex + `][name]" required placeholder="Masukkan nama item">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label" for="item-amount-` + itemIndex + `">` + langAmount + ` *</label>
                        <input id="item-amount-` + itemIndex + `" type="number" step="0.01" class="form-control item-amount" name="items[` + itemIndex + `][amount]" min="0" required placeholder="0.00">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-danger remove-item">X</button>
                    </div>
                </div>
            `;

                container.appendChild(itemDiv);

                // If data provided, populate inputs
                if (data) {
                    const nameInput = itemDiv.querySelector('.item-name');
                    const amountInput = itemDiv.querySelector('.item-amount');
                    if (data.name !== undefined) nameInput.value = data.name;
                    if (data.amount !== undefined) amountInput.value = data.amount;
                }

                // Attach listeners
                const amountInput = itemDiv.querySelector('.item-amount');
                amountInput.addEventListener('input', updateTotalAmount);

                const removeBtn = itemDiv.querySelector('.remove-item');
                removeBtn.addEventListener('click', function () {
                    removeItem(this);
                });

                // Ensure naming is sequential (important after removals)
                reindexItems();

                updateTotalAmount();
            }

            function removeItem(button) {
                const itemDiv = button.closest('.item-row');
                if (itemDiv) itemDiv.remove();
                reindexItems();
                updateTotalAmount();
            }

            // Make sure item input names are sequential (items[0], items[1], ...)
            function reindexItems() {
                const container = document.getElementById('items-container');
                const rows = container.querySelectorAll('.item-row');
                rows.forEach((row, idx) => {
                    const nameInput = row.querySelector('.item-name');
                    const amountInput = row.querySelector('.item-amount');
                    const nameLabel = row.querySelector('label.form-label');
                    if (nameInput) {
                        nameInput.setAttribute('name', `items[` + idx + `][name]`);
                        nameInput.setAttribute('id', 'item-name-' + idx);
                    }
                    if (amountInput) {
                        amountInput.setAttribute('name', `items[` + idx + `][amount]`);
                        amountInput.setAttribute('id', 'item-amount-' + idx);
                    }
                    // Update the label that corresponds to the name input (first label in the row)
                    if (nameLabel) {
                        try {
                            // If label has 'for', update it; otherwise set it to point to name input
                            nameLabel.setAttribute('for', 'item-name-' + idx);
                        } catch (e) { }
                    }
                });
            }

            function updateTotalAmount() {
                const amountInputs = document.querySelectorAll('.item-amount');
                let total = 0;

                amountInputs.forEach(input => {
                    total += parseFloat(input.value) || 0;
                });

                document.getElementById('total-amount').textContent = 'Rp' + total.toLocaleString('id-ID');
            }



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
        })(); // Close the IIFE
    </script>
</body>

</html>