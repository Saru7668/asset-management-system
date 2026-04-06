<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$current_role = strtolower(trim($_SESSION['UserRole'] ?? ''));
$username = $_SESSION['UserName'] ?? 'User';
$allowed_roles = ['hr', 'staff', 'admin', 'superadmin'];

if (!in_array($current_role, $allowed_roles, true)) {
    header("Location: index.php");
    exit;
}

function h($v) {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function generate_lease_ref(mysqli $conn): string {
    $prefix = 'LEASE-' . date('Ymd') . '-';
    $sql = "SELECT lease_ref FROM temp_accessories_leases WHERE lease_ref LIKE ? ORDER BY id DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "s", $prefixLike);
    $prefixLike = $prefix . '%';
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $next = 1;
    if ($row = mysqli_fetch_assoc($res)) {
        $last = $row['lease_ref'];
        $parts = explode('-', $last);
        $seq = (int)end($parts);
        $next = $seq + 1;
    }
    mysqli_stmt_close($stmt);
    return $prefix . str_pad((string)$next, 3, '0', STR_PAD_LEFT);
}

$success = '';
$error = '';
$inventory_items = [];

// FETCH FROM actual table: assets
$item_sql = "SELECT id, inventory as asset_id, details as item_name, serial_model as serial_no, status 
             FROM assets
             WHERE status IN ('Available', 'In Stock')
             ORDER BY details ASC, inventory ASC";
$item_q = mysqli_query($conn, $item_sql);
if ($item_q) {
    while ($r = mysqli_fetch_assoc($item_q)) {
        $inventory_items[] = $r;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_lease') {
    $inventory_id      = (int)($_POST['inventory_id'] ?? 0);
    $employee_name     = trim($_POST['employee_name'] ?? '');
    $employee_id       = trim($_POST['employee_id'] ?? '');
    $department        = trim($_POST['department'] ?? '');
    $lease_date        = trim($_POST['lease_date'] ?? date('Y-m-d'));
    $product_details   = trim($_POST['product_details'] ?? '');
    $serial_no         = trim($_POST['serial_no'] ?? '');
    $type_description  = trim($_POST['type_description'] ?? '');
    $lease_from        = trim($_POST['lease_from'] ?? '');
    $lease_to          = trim($_POST['lease_to'] ?? '');
    $remarks           = trim($_POST['remarks'] ?? '');
    $hod_name          = trim($_POST['hod_name'] ?? '');

    $uploaded_form_file = '';

    if ($inventory_id <= 0) {
        $error = 'Please select a device/accessory from inventory.';
    } elseif ($employee_name === '' || $department === '') {
        $error = 'Employee name and department are required.';
    } elseif ($product_details === '') {
        $error = 'Product details are required.';
    } elseif ($lease_from === '' || $lease_to === '') {
        $error = 'Lease period is required.';
    } else {
        $fromTs = strtotime($lease_from);
        $toTs   = strtotime($lease_to);

        if (!$fromTs || !$toTs || $toTs < $fromTs) {
            $error = 'Invalid lease date range.';
        } else {
            $days = (($toTs - $fromTs) / 86400) + 1;
            if ($days > 3) {
                $error = 'Lease period cannot exceed 3 days.';
            }
        }
    }

    if (!$error && isset($_FILES['uploaded_form_file']) && ($_FILES['uploaded_form_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['uploaded_form_file']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMime, true)) {
            $error = 'Uploaded signed form must be JPG, PNG, WEBP or PDF.';
        } elseif ($_FILES['uploaded_form_file']['size'] > 5 * 1024 * 1024) {
            $error = 'Uploaded file size must be under 5 MB.';
        } else {
            $ext = strtolower(pathinfo($_FILES['uploaded_form_file']['name'], PATHINFO_EXTENSION));
            $dir = __DIR__ . '/uploads/temp_lease/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $fileName = 'lease_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . $fileName;

            if (move_uploaded_file($_FILES['uploaded_form_file']['tmp_name'], $dest)) {
                $uploaded_form_file = 'uploads/temp_lease/' . $fileName;
            } else {
                $error = 'Failed to upload signed form.';
            }
        }
    }

    if (!$error) {
        mysqli_begin_transaction($conn);

        try {
            // VERIFY using assets table
            $lockStmt = mysqli_prepare($conn, "SELECT id, inventory as asset_id, details as item_name, serial_model as serial_no, status FROM assets WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($lockStmt, "i", $inventory_id);
            mysqli_stmt_execute($lockStmt);
            $lockRes = mysqli_stmt_get_result($lockStmt);
            $item = mysqli_fetch_assoc($lockRes);
            mysqli_stmt_close($lockStmt);

            if (!$item) {
                throw new Exception('Inventory item not found.');
            }

            if (!in_array($item['status'], ['Available', 'In Stock'], true)) {
                throw new Exception('This inventory item is not available for lease.');
            }

            $lease_ref = generate_lease_ref($conn);

            if ($product_details === '') {
                $product_details = trim(($item['item_name'] ?? '') . ' ' . ($item['asset_id'] ?? ''));
            }
            if ($serial_no === '') {
                $serial_no = trim((string)($item['serial_no'] ?? ''));
            }

            $insertSql = "INSERT INTO temp_accessories_leases
                (lease_ref, inventory_id, asset_code, product_details, serial_no, type_description,
                 employee_name, employee_id, department, lease_date, lease_from, lease_to,
                 remarks, hod_name, uploaded_form_file, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $insertStmt = mysqli_prepare($conn, $insertSql);
            $asset_code = $item['asset_id'] ?? '';

            mysqli_stmt_bind_param(
                $insertStmt,
                "sissssssssssssss",
                $lease_ref,
                $inventory_id,
                $asset_code,
                $product_details,
                $serial_no,
                $type_description,
                $employee_name,
                $employee_id,
                $department,
                $lease_date,
                $lease_from,
                $lease_to,
                $remarks,
                $hod_name,
                $uploaded_form_file,
                $username
            );

            if (!mysqli_stmt_execute($insertStmt)) {
                throw new Exception(mysqli_error($conn));
            }

            $lease_id = mysqli_insert_id($conn);
            mysqli_stmt_close($insertStmt);

            // UPDATE STATUS in assets
            $updateStmt = mysqli_prepare($conn, "UPDATE assets SET status = 'Leased' WHERE id = ? LIMIT 1");
            mysqli_stmt_bind_param($updateStmt, "i", $inventory_id);
            if (!mysqli_stmt_execute($updateStmt)) {
                throw new Exception(mysqli_error($conn));
            }
            mysqli_stmt_close($updateStmt);

            mysqli_commit($conn);

            header("Location: temp_accessories_lease_print.php?id=" . (int)$lease_id);
            exit;
        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $error = 'Save failed: ' . $e->getMessage();
        }
    }
}

$page_title = 'Temp Accessories Lease - SCL AMS';
$page_header_icon = 'fas fa-headset';
$page_header_title = 'Temporary Accessories Lease';
$page_header_subtitle = 'Create, save and print lease form';
$page_top_title = 'Temp Accessories Lease';
$page_container_class = 'dashboard-container-xl';

$extra_css = "
.lease-card{background:var(--bg-card,#fff);border:1px solid var(--border-color,#dbe2ea);border-radius:14px;box-shadow:var(--shadow-main,0 4px 18px rgba(0,0,0,.06));overflow:hidden;margin-bottom:22px}
.lease-card-header{background:linear-gradient(135deg,#0b2545 0%,#1e3a8a 100%);color:#fff;padding:16px 20px;font-size:20px;font-weight:700}
.lease-card-body{padding:22px}
.lease-grid-2,.lease-grid-3{display:grid;gap:16px 20px}
.lease-grid-2{grid-template-columns:1fr 1fr}
.lease-grid-3{grid-template-columns:1fr 1fr 1fr}
.paper-note{background:#fff8cf;border:1px solid #eed76a;border-radius:12px;padding:14px 16px;margin-bottom:18px;color:#6d5600;font-weight:600}
.form-label{font-weight:700}
.preview-paper{background:#fff;border:1px solid #cfd8e3;border-radius:12px;padding:16px}
html[data-theme='dark'] .preview-paper{background:#0f172a;border-color:#334155}
@media (max-width:991px){.lease-grid-2,.lease-grid-3{grid-template-columns:1fr}.lease-card-body{padding:16px}}
";

ob_start();
?>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo h($success); ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="paper-note">
    ***NOTE: Lease period will not exceed more than 03 days. When Save printable copy open will open, and signed scanned copy can be  upload to the system
</div>

<form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="save_lease">

    <div class="lease-card">
        <div class="lease-card-header">Lease Form</div>
        <div class="lease-card-body">
            <div class="lease-grid-3">
                <div>
                    <label class="form-label">Date</label>
                    <input type="date" name="lease_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div>
                    <label class="form-label">Inventory Item</label>
                    <select name="inventory_id" id="inventory_id" class="form-select" required>
                        <option value="">Choose item</option>
                        <?php foreach ($inventory_items as $it): ?>
                            <option
                                value="<?php echo (int)$it['id']; ?>"
                                data-asset="<?php echo h($it['asset_id'] ?? ''); ?>"
                                data-name="<?php echo h($it['item_name'] ?? ''); ?>"
                                data-serial="<?php echo h($it['serial_no'] ?? ''); ?>"
                            >
                                <?php echo h(($it['item_name'] ?? 'Item') . ' - ' . ($it['asset_id'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">HOD Name</label>
                    <input type="text" name="hod_name" class="form-control" placeholder="Sign & Seal of HOD">
                </div>
            </div>

            <hr>

            <div class="lease-grid-3">
                <div>
                    <label class="form-label">Employee Name</label>
                    <input type="text" name="employee_name" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Employee ID</label>
                    <input type="text" name="employee_id" class="form-control">
                </div>
                <div>
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control" required>
                </div>
            </div>

            <div class="lease-grid-2 mt-3">
                <div>
                    <label class="form-label">Product Details</label>
                    <input type="text" name="product_details" id="product_details" class="form-control" required>
                </div>
                <div>
                    <label class="form-label">Serial No.</label>
                    <input type="text" name="serial_no" id="serial_no" class="form-control">
                </div>
                <div>
                    <label class="form-label">Type / Description</label>
                    <input type="text" name="type_description" class="form-control">
                </div>
                <div class="lease-grid-2">
                    <div>
                        <label class="form-label">From</label>
                        <input type="date" name="lease_from" class="form-control" required>
                    </div>
                    <div>
                        <label class="form-label">To</label>
                        <input type="date" name="lease_to" class="form-control" required>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <label class="form-label">Remarks</label>
                <textarea name="remarks" class="form-control" rows="4"></textarea>
            </div>

            <div class="mt-3">
                <label class="form-label">Upload Signed / Scanned Copy (Optional)</label>
                <input type="file" name="uploaded_form_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf">
            </div>

            <div class="d-flex justify-content-end gap-2 flex-wrap mt-4">
                <a href="hr_dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                <a href="temp_accessories_lease_list.php" class="btn btn-dark">Lease Records</a>
                <button type="submit" class="btn btn-primary">Save & Open Print View</button>
            </div>
        </div>
    </div>
</form>

<script>
document.getElementById('inventory_id').addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (!opt || !opt.value) return;
    document.getElementById('product_details').value = ((opt.dataset.name || '') + ' ' + (opt.dataset.asset || '')).trim();
    document.getElementById('serial_no').value = opt.dataset.serial || '';
});
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>