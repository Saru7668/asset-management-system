<?php
session_start();
require_once('db.php');
require_once('header.php');
require_once('auth_guard.php');

require_roles(
    ['SuperAdmin', 'admin', 'staff', 'hr'],
    'You do not have permission to view inventory.',
    'index.php'
);

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['UserName'];
$role = $_SESSION['UserRole'] ?? 'user';
$message = "";

function esc($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function is_superadmin($role) {
    return strtolower((string)$role) === 'superadmin';
}

function move_asset_to_recycle_bin($conn, $asset_id, $deleted_by) {
    $asset_id = (int)$asset_id;
    if ($asset_id <= 0) {
        return false;
    }

    $q = mysqli_query($conn, "SELECT * FROM assets WHERE id = $asset_id LIMIT 1");
    if (!$q || mysqli_num_rows($q) === 0) {
        return false;
    }

    $asset = mysqli_fetch_assoc($q);
    $record_data = mysqli_real_escape_string(
        $conn,
        json_encode($asset, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    $deleted_by = mysqli_real_escape_string($conn, $deleted_by);

    mysqli_begin_transaction($conn);

    try {
        $insert_bin = "
            INSERT INTO recycle_bin
            (original_table, original_id, record_data, deleted_by, deleted_at)
            VALUES
            ('assets', $asset_id, '$record_data', '$deleted_by', NOW())
        ";

        if (!mysqli_query($conn, $insert_bin)) {
            throw new Exception('Recycle bin insert failed: ' . mysqli_error($conn));
        }

        if (!mysqli_query($conn, "DELETE FROM asset_logs WHERE asset_id = $asset_id")) {
            throw new Exception('Asset logs delete failed: ' . mysqli_error($conn));
        }

        if (!mysqli_query($conn, "DELETE FROM assets WHERE id = $asset_id")) {
            throw new Exception('Asset delete failed: ' . mysqli_error($conn));
        }

        mysqli_commit($conn);
        return true;

    } catch (Exception $e) {
        mysqli_rollback($conn);
        error_log($e->getMessage());
        error_log(mysqli_error($conn));
    }
}

// =============================
// HANDOVER SHEET UPLOAD LOGIC
// =============================
if (isset($_POST['upload_doc']) && isset($_POST['asset_id'])) {
    $a_id = (int)$_POST['asset_id'];

    if (isset($_FILES['handover_file']) && $_FILES['handover_file']['error'] == 0) {
        $allowed = ['pdf'];
        $filename = $_FILES['handover_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $upload_dir = 'uploads/handover_docs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = "Handover_Asset_" . $a_id . "_" . time() . ".pdf";
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['handover_file']['tmp_name'], $dest_path)) {
                mysqli_query($conn, "UPDATE assets SET handover_doc = '$dest_path' WHERE id = $a_id");
                mysqli_query($conn, "UPDATE asset_logs SET handover_doc = '$dest_path' WHERE asset_id = $a_id ORDER BY id DESC LIMIT 1");

                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({icon: 'success', title: 'Uploaded!', text: 'Handover sheet uploaded successfully.', timer: 2000, showConfirmButton: false});
                    });
                </script>";
            } else {
                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire('Error', 'Failed to move uploaded file.', 'error');
                    });
                </script>";
            }
        } else {
            $message = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Error', 'Only PDF files are allowed.', 'error');
                });
            </script>";
        }
    }
}

// =============================
// VENDOR DOC UPLOAD LOGIC
// =============================
if (isset($_POST['upload_vendor_doc']) && isset($_POST['vendor_asset_id'])) {
    $va_id = (int)$_POST['vendor_asset_id'];

    if (isset($_FILES['vendor_file']) && $_FILES['vendor_file']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $filename = $_FILES['vendor_file']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {
            $upload_dir = 'uploads/vendor_docs/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $new_filename = "Vendor_Doc_Asset_" . $va_id . "_" . time() . "." . $ext;
            $dest_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['vendor_file']['tmp_name'], $dest_path)) {
                mysqli_query($conn, "UPDATE assets SET vendor_doc = '$dest_path' WHERE id = $va_id");

                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({icon: 'success', title: 'Uploaded!', text: 'Vendor document uploaded successfully.', timer: 2000, showConfirmButton: false});
                    });
                </script>";
            } else {
                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire('Error', 'Failed to move uploaded file.', 'error');
                    });
                </script>";
            }
        } else {
            $message = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Error', 'Only PDF, JPG, JPEG, PNG files are allowed.', 'error');
                });
            </script>";
        }
    }
}

// =============================
// SINGLE DELETE => RECYCLE BIN (SuperAdmin Only)
// =============================
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
    if (is_superadmin($role)) {
        $d_id = (int)$_GET['id'];

        $chk_del = mysqli_query($conn, "SELECT inventory FROM assets WHERE id = $d_id LIMIT 1");
        if ($chk_del && mysqli_num_rows($chk_del) > 0) {
            $del_asset = mysqli_fetch_assoc($chk_del);
            $inv_name = mysqli_real_escape_string($conn, $del_asset['inventory']);

            if (move_asset_to_recycle_bin($conn, $d_id, $user)) {
                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Moved to Recycle Bin!',
                            text: 'Asset \\\"{$inv_name}\\\" has been moved to recycle bin.',
                            timer: 2200,
                            showConfirmButton: false
                        }).then(() => window.location='ict_inventory_list.php');
                    });
                </script>";
            } else {
                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire('Error', 'Failed to move asset to recycle bin.', 'error');
                    });
                </script>";
            }
        }
    } else {
        $message = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Access Denied', 'Only SuperAdmin can delete assets.', 'error');
            });
        </script>";
    }
}

// =============================
// BULK DELETE => RECYCLE BIN (SuperAdmin Only)
// =============================
if (isset($_POST['bulk_delete_selected'])) {
    if (is_superadmin($role)) {
        $selected_ids = $_POST['selected_ids'] ?? [];
        $deleted_count = 0;

        if (!empty($selected_ids) && is_array($selected_ids)) {
            foreach ($selected_ids as $selected_id) {
                $asset_id = (int)$selected_id;
                if ($asset_id > 0) {
                    if (move_asset_to_recycle_bin($conn, $asset_id, $user)) {
                        $deleted_count++;
                    }
                }
            }
        }

        if ($deleted_count > 0) {
            $message = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Moved to Recycle Bin!',
                        text: '{$deleted_count} asset(s) moved successfully.',
                        timer: 2200,
                        showConfirmButton: false
                    }).then(() => window.location='ict_inventory_list.php');
                });
            </script>";
        } else {
            $message = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Warning', 'No asset selected or no asset moved.', 'warning');
                });
            </script>";
        }
    } else {
        $message = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Access Denied', 'Only SuperAdmin can bulk delete assets.', 'error');
            });
        </script>";
    }
}

// =============================
// UNASSIGN ASSET LOGIC & LOG
// =============================
if (isset($_GET['action']) && $_GET['action'] == 'unassign' && isset($_GET['id'])) {
    $u_id = (int)$_GET['id'];
    $chk = mysqli_query($conn, "SELECT * FROM assets WHERE id = $u_id");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $asset = mysqli_fetch_assoc($chk);
        $emp_id = mysqli_real_escape_string($conn, $asset['employee_id']);
        $emp_name = mysqli_real_escape_string($conn, $asset['employee_name']);
        $dept = mysqli_real_escape_string($conn, $asset['department']);
        $inventory_name = mysqli_real_escape_string($conn, $asset['inventory']);
        $current_status = strtolower($asset['status']);

        $log_sql = "INSERT INTO asset_logs (asset_id, inventory, action_type, employee_id, employee_name, department, action_date, action_by)
                    VALUES ($u_id, '$inventory_name', 'Unassigned', '$emp_id', '$emp_name', '$dept', NOW(), '$user')";
        mysqli_query($conn, $log_sql);

        $new_status = ($current_status === 'damage') ? 'Damage' : 'Available';

        mysqli_query($conn, "UPDATE assets SET employee_id='', employee_name='', department='', status='$new_status', handover_doc=NULL, update_user='$user', update_datetime=NOW() WHERE id=$u_id");

        $message = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({icon: 'success', title: 'Asset Unassigned!', text: 'The device has been removed from the user.', timer: 2000, showConfirmButton: false}).then(() => window.location='ict_inventory_list.php');
            });
        </script>";
    }
}

// =============================
// FILTER & SEARCH
// =============================
$search_text = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_emp  = isset($_GET['emp']) ? trim($_GET['emp']) : '';
$search_dept = isset($_GET['dept']) ? trim($_GET['dept']) : '';
$search_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = " WHERE 1=1 ";
if ($search_text !== '') {
    $s = mysqli_real_escape_string($conn, $search_text);
    $where .= " AND (inventory LIKE '%$s%' OR serial_model LIKE '%$s%' OR details LIKE '%$s%')";
}
if ($search_emp !== '') {
    $e = mysqli_real_escape_string($conn, $search_emp);
    $where .= " AND (employee_name LIKE '%$e%' OR employee_id LIKE '%$e%')";
}
if ($search_dept !== '') {
    $d = mysqli_real_escape_string($conn, $search_dept);
    $where .= " AND department LIKE '%$d%'";
}
if ($search_status !== '') {
    $st = mysqli_real_escape_string($conn, $search_status);
    if ($st == 'Assigned') {
        $where .= " AND (employee_id IS NOT NULL AND employee_id != '') AND status != 'Damage'";
    } elseif ($st == 'Unassigned' || $st == 'Available') {
        $where .= " AND (employee_id IS NULL OR employee_id = '') AND status != 'Damage'";
    } else {
        $where .= " AND status = '$st'";
    }
}
if ($date_from !== '' && $date_to !== '') {
    $df = mysqli_real_escape_string($conn, $date_from);
    $dt = mysqli_real_escape_string($conn, $date_to);
    $where .= " AND DATE(purchase_date) BETWEEN '$df' AND '$dt'";
} elseif ($date_from !== '') {
    $df = mysqli_real_escape_string($conn, $date_from);
    $where .= " AND DATE(purchase_date) >= '$df'";
} elseif ($date_to !== '') {
    $dt = mysqli_real_escape_string($conn, $date_to);
    $where .= " AND DATE(purchase_date) <= '$dt'";
}

// =============================
// PAGINATION
// =============================
$limit = 20;
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$count_sql = "SELECT COUNT(*) AS total FROM assets $where";
$count_res = mysqli_query($conn, $count_sql);
$total_rows = $count_res ? (int)mysqli_fetch_assoc($count_res)['total'] : 0;
$total_pages = max(1, ceil($total_rows / $limit));

$sql = "SELECT * FROM assets $where ORDER BY id DESC LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);

$page_title = 'ICT Inventory List - SCL DMS';
$page_header_icon = 'fas fa-desktop';
$page_header_title = 'ICT Inventory List';
$page_header_subtitle = 'Track, assign, export, and manage all ICT inventory records';
$page_top_title = 'ICT Inventory List';

$export_qs = http_build_query([
    'q' => $search_text,
    'emp' => $search_emp,
    'dept' => $search_dept,
    'status' => $search_status,
    'date_from' => $date_from,
    'date_to' => $date_to
]);

$page_top_actions = '
<div class="page-actions">
    <a href="ict_inventory_add.php" class="btn btn-sm btn-info text-white">
        <i class="fas fa-plus-circle me-1"></i>Add Inventory
    </a>
    <a href="ict_inventory_export_excel.php?' . $export_qs . '" target="_blank" class="btn btn-sm btn-success">
        <i class="fas fa-file-excel me-1"></i>Excel
    </a>
    <a href="ict_inventory_export_pdf.php?' . $export_qs . '" target="_blank" class="btn btn-sm btn-danger">
        <i class="fas fa-file-pdf me-1"></i>PDF
    </a>
    <a href="sample_ict_assets.csv" class="btn btn-sm btn-outline-secondary" download>
        <i class="fas fa-download me-1"></i>Demo CSV
    </a>
    <a href="ict_inventory_uploader.php" class="btn btn-sm btn-primary" style="background-color: #0b2545;">
        <i class="fas fa-upload me-1"></i>Uploader
    </a>
</div>';

$page_container_class = 'dashboard-container-wide';
$body_extra_top = $message;

$extra_css = "
.dashboard-container-wide {
    max-width: 1600px;
    margin: 0 auto;
}
.page-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.filter-card,
.table-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
}
.filter-card {
    padding: 18px;
    margin-bottom: 18px;
}
.table-card {
    overflow: hidden;
}
.table-wrapper {
    max-height: calc(100vh - 320px);
    overflow-y: auto;
    border-bottom: 1px solid #dee2e6;
}
.inventory-table {
    min-width: 1560px;
    margin-bottom: 0;
}
.inventory-table thead th {
    position: sticky;
    top: 0;
    background-color: #1e293b;
    color: #fff;
    z-index: 1;
    white-space: nowrap;
    font-size: 13px;
    text-align: center;
    border-right: 1px solid #495057;
    padding: 10px;
}
.inventory-table tbody td {
    font-size: 13px;
    vertical-align: middle;
    white-space: nowrap;
    border-right: 1px solid #dee2e6;
    padding: 8px 10px;
}
.col-details {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.missing-id-row {
    background: #fff3cd !important;
}
.missing-badge {
    background: #dc3545;
}
.bulk-action-bar {
    display: none;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    background: #f8fafc;
    border-bottom: 1px solid #dee2e6;
}
.bulk-action-bar.active {
    display: flex;
}
.bulk-left {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}
.bulk-selected-count {
    font-size: 13px;
    font-weight: 700;
    color: #0b2545;
    background: #eaf2ff;
    border: 1px solid #bfd4ff;
    padding: 5px 10px;
    border-radius: 999px;
}
.bulk-delete-wrap {
    display: none;
}
.bulk-delete-wrap.active {
    display: inline-flex;
}
.select-col {
    width: 52px;
    text-align: center;
}
.row-check-wrap,
.header-check-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
}
.inventory-check {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: #dc3545;
    border: 1px solid #94a3b8;
    border-radius: 4px;
}
.inventory-check:focus {
    outline: 2px solid rgba(37, 99, 235, 0.25);
    outline-offset: 2px;
}
@media (max-width: 991px) {
    .page-topbar {
        flex-direction: column;
        align-items: stretch;
    }
    .page-actions {
        width: 100%;
    }
    .filter-card {
        padding: 15px;
    }
    .table-wrapper {
        max-height: none;
        overflow-y: visible;
    }
    .bulk-action-bar.active {
        flex-direction: column;
        align-items: stretch;
    }
}

/* DARK MODE FIXES */
html[data-theme='dark'] .filter-card,
html[data-theme='dark'] .table-card {
    background: #111827;
    border-top-color: #3b82f6;
    box-shadow: 0 8px 25px rgba(0,0,0,0.35);
}
html[data-theme='dark'] .bulk-action-bar {
    background: #0f172a;
    border-bottom-color: #334155;
}
html[data-theme='dark'] .bulk-selected-count {
    color: #dbeafe;
    background: rgba(37, 99, 235, 0.15);
    border-color: rgba(59, 130, 246, 0.35);
}
html[data-theme='dark'] .inventory-check {
    accent-color: #f43f5e;
    border-color: #64748b;
}
html[data-theme='dark'] .card-header.bg-dark {
    background: #0f172a !important;
    color: #f8fafc !important;
}
html[data-theme='dark'] .table-wrapper {
    border-bottom-color: #334155;
}
html[data-theme='dark'] .inventory-table thead th {
    background-color: #0f172a;
    color: #f8fafc;
    border-right-color: #334155;
}
html[data-theme='dark'] .inventory-table tbody td {
    color: #e5e7eb;
    border-right-color: #334155;
    background: #111827;
}
html[data-theme='dark'] .inventory-table tbody tr {
    background: #111827;
}
html[data-theme='dark'] .inventory-table tbody tr:nth-child(even) {
    background: #0f172a;
}
html[data-theme='dark'] .missing-id-row {
    background: rgba(234, 179, 8, 0.12) !important;
}
html[data-theme='dark'] .text-muted,
html[data-theme='dark'] .text-secondary {
    color: #94a3b8 !important;
}
html[data-theme='dark'] .form-control,
html[data-theme='dark'] .form-select {
    background-color: #0f172a;
    color: #f8fafc;
    border-color: #334155;
}
html[data-theme='dark'] .form-control::placeholder {
    color: #64748b;
}
html[data-theme='dark'] .form-control:focus,
html[data-theme='dark'] .form-select:focus {
    background-color: #0f172a;
    color: #f8fafc;
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59, 130, 246, 0.25);
}
html[data-theme='dark'] .table-light,
html[data-theme='dark'] .bg-light {
    background-color: #0f172a !important;
    color: #f8fafc !important;
}
html[data-theme='dark'] .badge.bg-success {
    background-color: #15803d !important;
}
html[data-theme='dark'] .badge.bg-danger {
    background-color: #dc2626 !important;
}
html[data-theme='dark'] .badge.bg-warning {
    background-color: #ca8a04 !important;
    color: #111827 !important;
}
html[data-theme='dark'] .badge.bg-info {
    background-color: #0891b2 !important;
}
html[data-theme='dark'] .badge.bg-secondary {
    background-color: #475569 !important;
}
html[data-theme='dark'] .btn-outline-dark {
    color: #e5e7eb;
    border-color: #64748b;
}
html[data-theme='dark'] .btn-outline-secondary {
    color: #cbd5e1;
    border-color: #64748b;
}
html[data-theme='dark'] .btn-outline-primary {
    color: #93c5fd;
    border-color: #3b82f6;
}
html[data-theme='dark'] .btn-outline-info {
    color: #67e8f9;
    border-color: #06b6d4;
}
html[data-theme='dark'] .btn-outline-danger {
    color: #fca5a5;
    border-color: #ef4444;
}
html[data-theme='dark'] .page-link {
    background-color: #0f172a;
    border-color: #334155;
    color: #e5e7eb;
}
html[data-theme='dark'] .page-item.disabled .page-link {
    background-color: #111827;
    border-color: #334155;
    color: #64748b;
}
html[data-theme='dark'] .pagination {
    --bs-pagination-hover-bg: #1e293b;
    --bs-pagination-hover-color: #f8fafc;
    --bs-pagination-active-bg: #2563eb;
    --bs-pagination-active-border-color: #2563eb;
}
html[data-theme='dark'] .modal-content {
    background: #111827;
    color: #f8fafc;
}
html[data-theme='dark'] .modal-header,
html[data-theme='dark'] .modal-footer {
    border-color: #334155;
}
html[data-theme='dark'] .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}
html[data-theme='dark'] .table-card .card-header {
    background: #0f172a !important;
    border-bottom: 1px solid #334155;
}
";

ob_start();
?>

<form method="GET" action="ict_inventory_list.php" class="filter-card">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label small text-muted fw-bold">Inventory / Serial / Details</label>
            <input type="text" name="q" class="form-control form-control-sm" value="<?php echo esc($search_text); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted fw-bold">Employee (Name / ID)</label>
            <input type="text" name="emp" class="form-control form-control-sm" value="<?php echo esc($search_emp); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted fw-bold">Department</label>
            <input type="text" name="dept" class="form-control form-control-sm" value="<?php echo esc($search_dept); ?>">
        </div>
        <div class="col-md-2">
            <label class="form-label small text-muted fw-bold">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Status</option>
                <option value="Assigned" <?php if($search_status == 'Assigned') echo 'selected'; ?>>Assigned (In Use)</option>
                <option value="Unassigned" <?php if($search_status == 'Unassigned') echo 'selected'; ?>>Unassigned (In Stock)</option>
                <option value="Damage" <?php if($search_status == 'Damage') echo 'selected'; ?>>Damage</option>
                <option value="Repair" <?php if($search_status == 'Repair') echo 'selected'; ?>>Repair</option>
            </select>
        </div>
        <div class="col-md-1">
            <label class="form-label small text-muted fw-bold">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?php echo esc($date_from); ?>">
        </div>
        <div class="col-md-1">
            <label class="form-label small text-muted fw-bold">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?php echo esc($date_to); ?>">
        </div>
        <div class="col-md-1">
            <button type="submit" class="btn btn-dark btn-sm w-100 mb-1">
                <i class="fas fa-search me-1"></i>Search
            </button>
            <?php if($search_text || $search_emp || $search_dept || $search_status || $date_from || $date_to): ?>
                <a href="ict_inventory_list.php" class="btn btn-outline-danger btn-sm w-100">
                    <i class="fas fa-times-circle me-1"></i>Clear
                </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<form method="POST" id="bulkDeleteForm">
<div class="table-card">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold"><i class="fas fa-database me-2"></i><?php echo $total_rows; ?> Records Found</span>
    </div>

    <?php if (is_superadmin($role)): ?>
    <div class="bulk-action-bar" id="bulkActionBar">
        <div class="bulk-left">
            <label class="fw-bold mb-0 d-flex align-items-center">
                <input type="checkbox" id="selectAllRows" class="inventory-check me-2">
                Select All
            </label>
            <span class="bulk-selected-count" id="selectedCountBadge">Selected: 0 item(s)</span>
        </div>
        <div class="bulk-delete-wrap" id="bulkDeleteWrap">
            <button type="submit" name="bulk_delete_selected" class="btn btn-sm btn-danger" onclick="return confirmBulkDelete(event);">
                <i class="fas fa-trash-alt me-1"></i>Move Selected to Recycle Bin
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="card-body p-0">
        <?php if($result && mysqli_num_rows($result) > 0): ?>
        <div class="table-responsive">
            <div class="table-wrapper">
                <table class="table table-hover table-bordered mb-0 align-middle inventory-table">
                    <thead>
                        <tr>
                            <?php if (is_superadmin($role)): ?>
                                <th class="select-col">
                                    <div class="header-check-wrap">
                                        <input type="checkbox" id="selectAllHeader" class="inventory-check">
                                    </div>
                                </th>
                            <?php endif; ?>
                            <th>SL</th>
                            <th>Inventory</th>
                            <th>Employee (ID & Name)</th>
                            <th>Department</th>
                            <th>Details</th>
                            <th>Serial/Model</th>
                            <th>Status</th>
                            <th>Unit</th>
                            <th>Purchase Date</th>
                            <th>Warranty</th>
                            <th>Warranty Validity</th>
                            <th>Handover Doc</th>
                            <th>Bill/Challan Doc</th>
                            <th>Created By/Time</th>
                            <th>Updated By/Time</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $sl = $offset + 1;
                    while($row = mysqli_fetch_assoc($result)):
                        $missing = empty($row['employee_id']);
                    ?>
                        <tr class="<?php echo $missing ? 'missing-id-row' : ''; ?>">
                            <?php if (is_superadmin($role)): ?>
                                <td class="text-center">
                                    <div class="row-check-wrap">
                                        <input type="checkbox" class="inventory-check row-checkbox" name="selected_ids[]" value="<?php echo (int)$row['id']; ?>">
                                    </div>
                                </td>
                            <?php endif; ?>

                            <td class="text-center"><?php echo $sl++; ?></td>
                            <td class="fw-bold text-primary"><?php echo esc($row['inventory']); ?></td>
                            <td>
                                <?php if($missing): ?>
                                    <span class="badge missing-badge mb-1">ID Missing</span><br>
                                <?php else: ?>
                                    <span class="badge bg-success text-white mb-1"><?php echo esc($row['employee_id']); ?></span><br>
                                <?php endif; ?>
                                <small class="text-muted"><?php echo esc($row['employee_name']); ?></small>
                            </td>
                            <td><?php echo esc($row['department']); ?></td>
                            <td class="col-details" title="<?php echo esc($row['details']); ?>">
                                <?php echo esc($row['details']); ?>
                            </td>
                            <td><?php echo esc($row['serial_model']); ?></td>
                            <td class="text-center">
                                <?php
                                    $raw_status = strtolower((string)$row['status']);
                                    if ($raw_status === 'damage') {
                                        $display_status = 'Damage';
                                        $badge_class = 'bg-danger';
                                    } elseif (!empty($row['employee_id']) && ($raw_status == 'active' || $raw_status == 'assigned')) {
                                        $display_status = 'Assigned';
                                        $badge_class = 'bg-success';
                                    } else {
                                        $display_status = esc($row['status']);
                                        if ($raw_status == 'available') {
                                            $badge_class = 'bg-info text-dark';
                                        } elseif ($raw_status == 'repair') {
                                            $badge_class = 'bg-warning text-dark';
                                        } else {
                                            $badge_class = 'bg-secondary';
                                        }
                                    }
                                ?>
                                <span class="badge <?php echo $badge_class; ?>"><?php echo $display_status; ?></span>
                            </td>
                            <td class="text-center"><?php echo esc($row['unit'] ?? '-'); ?></td>
                            <td class="text-center"><?php echo !empty($row['purchase_date']) ? date('d M Y', strtotime($row['purchase_date'])) : '-'; ?></td>
                            <td class="text-center">
                                <?php
                                    $warranty = $row['warranty_months'] ?? '';
                                    echo !empty($warranty) ? esc($warranty) . " Months" : '-';
                                ?>
                            </td>

                            <td class="text-center">
                                <?php
                                if (!empty($row['purchase_date']) && !empty($row['warranty_months'])) {
                                    try {
                                        $purchase_dt = new DateTime($row['purchase_date']);
                                        $months = (int)$row['warranty_months'];

                                        $expire_dt = clone $purchase_dt;
                                        $expire_dt->modify("+$months months");

                                        $now = new DateTime();

                                        if ($now > $expire_dt) {
                                            echo '<span class="badge bg-danger">Expired</span><br>';
                                            echo '<small class="text-muted" style="font-size:10px;">Exp: ' . $expire_dt->format('d M Y') . '</small>';
                                        } else {
                                            $interval = $now->diff($expire_dt);
                                            $y = $interval->y;
                                            $m = $interval->m;
                                            $d = $interval->d;

                                            $rem = [];
                                            if ($y > 0) $rem[] = $y . 'y';
                                            if ($m > 0) $rem[] = $m . 'm';
                                            if ($y == 0 && $m == 0 && $d > 0) $rem[] = $d . 'd';

                                            $rem_str = empty($rem) ? 'Expires Today' : implode(' ', $rem) . ' left';

                                            echo '<span class="badge bg-success">' . esc($rem_str) . '</span><br>';
                                            echo '<small class="text-muted" style="font-size:10px;">Exp: ' . $expire_dt->format('d M Y') . '</small>';
                                        }
                                    } catch (Exception $e) {
                                        echo '-';
                                    }
                                } else {
                                    echo '<span class="text-muted">-</span>';
                                }
                                ?>
                            </td>

                            <td class="text-center">
                                <?php if(!empty($row['employee_id'])): ?>
                                    <?php if(!empty($row['handover_doc'])): ?>
                                        <a href="<?php echo esc($row['handover_doc']); ?>" target="_blank" class="btn btn-sm btn-outline-danger py-0 px-2" title="View Handover PDF">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" title="Upload Handover Sheet" onclick="openUploadModal(<?php echo (int)$row['id']; ?>, '<?php echo addslashes($row['inventory']); ?>')">
                                            <i class="fas fa-upload"></i>
                                        </button>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted small">-</span>
                                <?php endif; ?>
                            </td>

                            <td class="text-center">
                                <?php if(!empty($row['vendor_doc'])): ?>
                                    <a href="<?php echo esc($row['vendor_doc']); ?>" target="_blank" class="btn btn-sm btn-outline-info py-0 px-2" title="View Vendor Doc">
                                        <i class="fas fa-file-invoice"></i>
                                    </a>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2" title="Upload Vendor Doc" onclick="openVendorUploadModal(<?php echo (int)$row['id']; ?>, '<?php echo addslashes($row['inventory']); ?>')">
                                        <i class="fas fa-upload"></i>
                                    </button>
                                <?php endif; ?>
                            </td>

                            <td>
                                <small class="fw-bold text-muted"><?php echo esc($row['created_user'] ?? 'System'); ?></small><br>
                                <small class="text-secondary" style="font-size:10px;"><?php echo !empty($row['created_datetime']) ? date('d M Y h:i A', strtotime($row['created_datetime'])) : 'N/A'; ?></small>
                            </td>
                            <td>
                                <small class="fw-bold text-muted"><?php echo esc($row['update_user'] ?? '-'); ?></small><br>
                                <small class="text-secondary" style="font-size:10px;"><?php echo !empty($row['update_datetime']) ? date('d M Y h:i A', strtotime($row['update_datetime'])) : 'N/A'; ?></small>
                            </td>

                            <td class="text-center">
                                <div class="d-flex flex-wrap gap-1 justify-content-center">
                                    <?php $can_edit = in_array(strtolower($role), ['admin','superadmin','staff','hr']); ?>

                                    <?php if (!empty($row['employee_id'])): ?>
                                        <a href="handover_print.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-dark py-0 px-2" title="Print Handover" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                        <a href="employee_devices.php?emp_id=<?php echo urlencode($row['employee_id']); ?>" class="btn btn-sm btn-success py-0 px-2" title="View all devices">
                                            <i class="fas fa-desktop"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if($can_edit): ?>
                                        <?php if (!empty($row['employee_id'])): ?>
                                            <button type="button" class="btn btn-sm btn-warning py-0 px-2" title="Unassign Device" onclick="unassignAsset(<?php echo (int)$row['id']; ?>, '<?php echo addslashes($row['inventory']); ?>')">
                                                <i class="fas fa-user-times"></i>
                                            </button>
                                        <?php else: ?>
                                            <a href="ict_inventory_assign.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-info text-white py-0 px-2" title="Assign">
                                                <i class="fas fa-user-plus"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="ict_inventory_edit.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-primary py-0 px-2" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if($can_edit): ?>
                                        <a href="ict_inventory_log.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-secondary py-0 px-2" title="History">
                                            <i class="fas fa-history"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a href="ict_inventory_qr.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-dark py-0 px-2" title="QR Code" target="_blank">
                                        <i class="fas fa-qrcode"></i>
                                    </a>

                                    <?php if(is_superadmin($role)): ?>
                                        <button type="button" class="btn btn-sm btn-danger py-0 px-2" title="Move to Recycle Bin" onclick="deleteAsset(<?php echo (int)$row['id']; ?>, '<?php echo addslashes($row['inventory']); ?>')">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center p-3 bg-light">
            <small class="text-muted">Showing page <?php echo $page; ?> of <?php echo $total_pages; ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&q=<?php echo urlencode($search_text); ?>&emp=<?php echo urlencode($search_emp); ?>&dept=<?php echo urlencode($search_dept); ?>&status=<?php echo urlencode($search_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Prev</a>
                    </li>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&q=<?php echo urlencode($search_text); ?>&emp=<?php echo urlencode($search_emp); ?>&dept=<?php echo urlencode($search_dept); ?>&status=<?php echo urlencode($search_status); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php else: ?>
            <div class="p-5 text-center text-muted bg-white">
                <i class="fas fa-folder-open fa-3x mb-3 text-secondary opacity-50"></i>
                <h5>No Assets Found</h5>
            </div>
        <?php endif; ?>
    </div>
</div>
</form>

<!-- Upload Handover PDF Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1" aria-labelledby="uploadModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
          <div class="modal-header bg-primary text-white">
            <h5 class="modal-title" id="uploadModalLabel"><i class="fas fa-upload me-2"></i>Upload Handover Doc</h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="asset_id" id="upload_asset_id">
            <p>Upload signed Handover Sheet (PDF) for <strong id="upload_inv_name" class="text-primary"></strong>.</p>
            <div class="mb-3">
                <label class="form-label fw-bold">Select PDF File:</label>
                <input class="form-control" type="file" name="handover_file" accept=".pdf" required>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="upload_doc" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save File</button>
          </div>
      </form>
    </div>
  </div>
</div>

<!-- Upload Vendor Doc Modal -->
<div class="modal fade" id="vendorUploadModal" tabindex="-1" aria-labelledby="vendorUploadModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" enctype="multipart/form-data">
          <div class="modal-header bg-info text-dark">
            <h5 class="modal-title" id="vendorUploadModalLabel"><i class="fas fa-file-invoice me-2"></i>Upload Vendor Doc</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="vendor_asset_id" id="upload_vendor_asset_id">
            <p>Upload Invoice/Warranty Document for <strong id="upload_vendor_inv_name" class="text-primary"></strong>.</p>
            <div class="mb-3">
                <label class="form-label fw-bold">Select File (PDF/Image):</label>
                <input class="form-control" type="file" name="vendor_file" accept=".pdf, .jpg, .jpeg, .png" required>
            </div>
          </div>
          <div class="modal-footer bg-light">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="upload_vendor_doc" class="btn btn-info"><i class="fas fa-save me-1"></i>Save File</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script>
function openUploadModal(id, inv) {
    document.getElementById('upload_asset_id').value = id;
    document.getElementById('upload_inv_name').innerText = inv;
    var uploadModal = new bootstrap.Modal(document.getElementById('uploadModal'));
    uploadModal.show();
}

function openVendorUploadModal(id, inv) {
    document.getElementById('upload_vendor_asset_id').value = id;
    document.getElementById('upload_vendor_inv_name').innerText = inv;
    var vendorUploadModal = new bootstrap.Modal(document.getElementById('vendorUploadModal'));
    vendorUploadModal.show();
}

function unassignAsset(id, inventory) {
    Swal.fire({
        title: 'Unassign ' + inventory + '?',
        text: 'This will remove the device from the current employee and hide the current handover document.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#eab308',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class=\"fas fa-user-times\"></i> Yes, Unassign'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'ict_inventory_list.php?action=unassign&id=' + id;
        }
    });
}

function deleteAsset(id, inventory) {
    Swal.fire({
        title: 'Move ' + inventory + ' to recycle bin?',
        text: 'You can restore it later from the central recycle bin.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class=\"fas fa-trash-alt\"></i> Yes, Move'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'ict_inventory_list.php?action=delete&id=' + id;
        }
    });
}

var selectAllRows = document.getElementById('selectAllRows');
var selectAllHeader = document.getElementById('selectAllHeader');
var bulkActionBar = document.getElementById('bulkActionBar');
var bulkDeleteWrap = document.getElementById('bulkDeleteWrap');
var selectedCountBadge = document.getElementById('selectedCountBadge');

function getRowCheckboxes() {
    return document.querySelectorAll('.row-checkbox');
}

function getCheckedRowCheckboxes() {
    return document.querySelectorAll('.row-checkbox:checked');
}

function syncSelectAll(source) {
    getRowCheckboxes().forEach(function(cb) {
        cb.checked = source.checked;
    });
    if (selectAllRows && selectAllRows !== source) selectAllRows.checked = source.checked;
    if (selectAllHeader && selectAllHeader !== source) selectAllHeader.checked = source.checked;
    updateBulkBar();
}

function updateBulkBar() {
    var all = getRowCheckboxes();
    var checked = getCheckedRowCheckboxes();
    var checkedCount = checked.length;
    var allSelected = all.length > 0 && checkedCount === all.length;

    if (selectAllRows) selectAllRows.checked = allSelected;
    if (selectAllHeader) selectAllHeader.checked = allSelected;

    if (selectedCountBadge) {
        selectedCountBadge.textContent = 'Selected: ' + checkedCount + ' item(s)';
    }

    if (bulkActionBar) {
        if (checkedCount > 0) {
            bulkActionBar.classList.add('active');
        } else {
            bulkActionBar.classList.remove('active');
        }
    }

    if (bulkDeleteWrap) {
        if (checkedCount > 0) {
            bulkDeleteWrap.classList.add('active');
        } else {
            bulkDeleteWrap.classList.remove('active');
        }
    }
}

if (selectAllRows) {
    selectAllRows.addEventListener('change', function() {
        syncSelectAll(this);
    });
}

if (selectAllHeader) {
    selectAllHeader.addEventListener('change', function() {
        syncSelectAll(this);
    });
}

getRowCheckboxes().forEach(function(cb) {
    cb.addEventListener('change', updateBulkBar);
});

function confirmBulkDelete(ev) {
    if (ev) ev.preventDefault();

    // Check if any checkbox is selected
    var checked = document.querySelectorAll('.row-checkbox:checked');
    if (checked.length === 0) {
        Swal.fire('No Selection', 'Please select at least one asset.', 'warning');
        return false;
    }

    Swal.fire({
        title: 'Move selected assets to recycle bin?',
        text: checked.length + ' item(s) will be moved and can be restored later.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash-alt"></i> Yes, Move'
    }).then((result) => {
        if (result.isConfirmed) {
            // Create a hidden input to pass the action name
            let bulkActionInput = document.createElement('input');
            bulkActionInput.type = 'hidden';
            bulkActionInput.name = 'bulk_delete_selected';
            bulkActionInput.value = '1';
            
            let form = document.getElementById('bulkDeleteForm');
            form.appendChild(bulkActionInput);
            
            // Force submit the form
            form.submit();
        }
    });

    return false;
}

document.addEventListener('DOMContentLoaded', function() {
    updateBulkBar();
});
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>