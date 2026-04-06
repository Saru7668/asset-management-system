<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('db.php');
require_once('header.php');
require_once('auth_guard.php');


require_roles(
    ['SuperAdmin', 'admin', 'staff', 'hr'],
    'You do not have permission to view inventory.',
    'index.php'
);

// Access Control
if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['UserName'];
$role = $_SESSION['UserRole'] ?? 'user';
$swal_script = "";
$preview_rows = [];
$tmp_path = "";

// UTF-8 sanitize helper
function clean_str($str) {
    $str = (string)$str;
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8,ISO-8859-1,WINDOWS-1252');
    $str = preg_replace('/[^\\P{C}\\n\\t]/u', '', $str);
    return $str;
}

// ===============================
// STEP-2: FINAL PROCESS CSV
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_csv']) && isset($_POST['csv_tmp_path'])) {

    $tmp_path = $_POST['csv_tmp_path'];

    if (!file_exists($tmp_path)) {
        $swal_script = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'File not found!',
                    text: 'Temporary CSV file is missing. Please upload again.'
                });
            });
        </script>";
    } else {
        $success = 0;
        $updated = 0;
        $failed  = 0;
        $error_msg = "";

        if (($handle = fopen($tmp_path, "r")) !== false) {
            $row = 0;
            while (($data = fgetcsv($handle, 5000, ",")) !== false) {
                $row++;
                if ($row == 1) continue;

                $inventory = mysqli_real_escape_string($conn, clean_str(trim($data[0] ?? '')));
                $emp_name  = mysqli_real_escape_string($conn, clean_str(trim($data[1] ?? '')));
                $dept      = mysqli_real_escape_string($conn, clean_str(trim($data[2] ?? '')));
                $details   = mysqli_real_escape_string($conn, clean_str(trim($data[3] ?? '')));
                $serial    = mysqli_real_escape_string($conn, clean_str(trim($data[4] ?? '')));
                $status    = mysqli_real_escape_string($conn, clean_str(trim($data[5] ?? '')));
                $unit      = mysqli_real_escape_string($conn, clean_str(trim($data[6] ?? '')));
                $raw_date  = clean_str(trim($data[7] ?? ''));
                $warranty  = (int)($data[8] ?? 0);
                $remarks   = mysqli_real_escape_string($conn, clean_str(trim($data[9] ?? '')));

                if ($inventory === '') continue;

                if ($raw_date === '' || $raw_date === '0000-00-00' || $raw_date === '-') {
                    $purchase_date = "NULL";
                } else {
                    $parsed = strtotime(str_replace('/', '-', $raw_date));
                    if ($parsed !== false) {
                        $purchase_date = "'" . mysqli_real_escape_string($conn, date('Y-m-d', $parsed)) . "'";
                    } else {
                        $purchase_date = "NULL";
                    }
                }

                $emp_id = '';
                if ($emp_name !== '') {
                    $q_emp = mysqli_query(
                        $conn,
                        "SELECT employee_id 
                         FROM employees 
                         WHERE employee_name = '" . mysqli_real_escape_string($conn, $emp_name) . "'
                         LIMIT 1"
                    );
                    if ($q_emp && mysqli_num_rows($q_emp) > 0) {
                        $emp_row = mysqli_fetch_assoc($q_emp);
                        $emp_id  = mysqli_real_escape_string($conn, clean_str($emp_row['employee_id']));
                    }
                }

                $check_dup = mysqli_query($conn, "SELECT id FROM assets WHERE inventory = '$inventory' LIMIT 1");

                if ($check_dup && mysqli_num_rows($check_dup) > 0) {
                    $dup_id = mysqli_fetch_assoc($check_dup)['id'];

                    $sql = "UPDATE assets SET
                            employee_id='$emp_id',
                            employee_name='$emp_name',
                            department='$dept',
                            details='$details',
                            serial_model='$serial',
                            status='$status',
                            unit='$unit',
                            purchase_date=$purchase_date,
                            warranty_months=$warranty,
                            remarks='$remarks',
                            update_user='$user',
                            update_datetime=NOW()
                            WHERE id = $dup_id";

                    if (mysqli_query($conn, $sql)) {
                        $updated++;
                    } else {
                        $failed++;
                        $error_msg .= "U($inventory): " . mysqli_error($conn) . "<br>";
                    }
                } else {
                    $sl_no = "AST-" . time() . "-" . rand(1000, 9999);

                    $sql = "INSERT INTO assets
                            (sl_no, inventory, employee_id, employee_name, department, details,
                             serial_model, status, unit, purchase_date, warranty_months, remarks,
                             entry_user, entry_datetime)
                            VALUES
                            ('$sl_no', '$inventory', '$emp_id', '$emp_name', '$dept', '$details',
                             '$serial', '$status', '$unit', $purchase_date, $warranty, '$remarks',
                             '$user', NOW())";

                    if (mysqli_query($conn, $sql)) {
                        $success++;
                    } else {
                        $failed++;
                        $error_msg .= "I($inventory): " . mysqli_error($conn) . "<br>";
                    }
                }
            }
            fclose($handle);
        }

        $safe_error = addslashes(str_replace(["\r", "\n"], ["", ""], $error_msg));

        if ($failed > 0) {
            $swal_script = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Completed with issues',
                        html: '<b>Inserted:</b> $success<br><b>Updated:</b> $updated<br><b style=\"color:red;\">Failed:</b> $failed<br><br><div style=\"max-height:150px;overflow:auto;font-size:12px;\">$safe_error</div>'
                    });
                });
            </script>";
        } else {
            $swal_script = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'All data processed!',
                        html: '<b>Inserted:</b> $success<br><b>Updated:</b> $updated',
                        confirmButtonText: 'Go to Inventory'
                    }).then(() => window.location='ict_inventory_list.php');
                });
            </script>";
        }
    }
}

// ===============================
// STEP-1: UPLOAD + PREVIEW
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preview_csv']) && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    $tmp_dir = __DIR__ . '/tmp_csv';
    if (!is_dir($tmp_dir)) {
        mkdir($tmp_dir, 0775, true);
    }

    $tmp_path = $tmp_dir . '/csv_' . time() . '_' . rand(1000, 9999) . '.csv';
    move_uploaded_file($_FILES['csv_file']['tmp_name'], $tmp_path);

    if (($handle = fopen($tmp_path, "r")) !== false) {
        $row = 0;
        while (($data = fgetcsv($handle, 5000, ",")) !== false) {
            $row++;
            if ($row == 1) continue;

            $emp_name_clean = clean_str(trim($data[1] ?? ''));

            $emp_id_found = '';
            if ($emp_name_clean !== '') {
                $q_emp = mysqli_query(
                    $conn,
                    "SELECT employee_id 
                     FROM employees 
                     WHERE employee_name = '" . mysqli_real_escape_string($conn, $emp_name_clean) . "'
                     LIMIT 1"
                );
                if ($q_emp && mysqli_num_rows($q_emp) > 0) {
                    $emp_id_found = mysqli_fetch_assoc($q_emp)['employee_id'];
                }
            }

            $preview_rows[] = [
                'inventory' => clean_str(trim($data[0] ?? '')),
                'emp_name'  => $emp_name_clean,
                'emp_id'    => $emp_id_found,
                'dept'      => clean_str(trim($data[2] ?? '')),
                'details'   => clean_str(trim($data[3] ?? '')),
                'serial'    => clean_str(trim($data[4] ?? '')),
                'status'    => clean_str(trim($data[5] ?? '')),
                'unit'      => clean_str(trim($data[6] ?? '')),
                'p_date'    => clean_str(trim($data[7] ?? '')),
                'warranty'  => clean_str(trim($data[8] ?? '0')),
                'remarks'   => clean_str(trim($data[9] ?? ''))
            ];
        }
        fclose($handle);
    }
}

$page_title = 'ICT Asset Uploader - SCL AMS';
$page_header_icon = 'fas fa-upload';
$page_header_title = 'ICT Asset Uploader';
$page_header_subtitle = 'Bulk upload, preview, and process ICT inventory records from CSV';
$page_top_title = 'Upload ICT Assets (CSV)';
$page_top_actions = '<a href="sample_ict_assets.csv" class="btn btn-outline-secondary" download><i class="fas fa-download me-1"></i>Download Demo CSV</a>';
$page_container_class = 'dashboard-container-wide';
$body_extra_top = $swal_script;

$extra_css = "
.table-wrapper { max-height: 55vh; overflow-y: auto; }
.table thead th { position: sticky; top: 0; background-color: #212529; color: #fff; z-index: 1; white-space: nowrap; }
.badge-missing { background-color:#0d6efd !important; }
.form-control { min-height: 44px; border-radius: 8px; border: 1px solid #cbd5e1; }
.form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 0.2rem rgba(59,130,246,0.15); }
.upload-note { background: #f8fbff; border: 1px solid #dbeafe; border-radius: 10px; }
.preview-card-header { background: #f59e0b; color: #212529; font-weight: 700; padding: 16px 20px; }
";

ob_start();
?>
<div class="form-card mb-4">
    <div class="form-card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0"><i class="fas fa-file-csv me-2"></i>Upload ICT Assets (CSV)</h5>
        <a href="sample_ict_assets.csv" class="btn btn-sm btn-outline-light" download>
            <i class="fas fa-download me-1"></i>Download Demo CSV
        </a>
    </div>

    <div class="form-card-body">
        <form action="" method="POST" enctype="multipart/form-data" class="d-flex flex-wrap align-items-center gap-3">
            <input type="file" name="csv_file" class="form-control border-secondary" style="max-width: 420px;" accept=".csv" required>
            <button type="submit" name="preview_csv" value="1" class="btn btn-primary fw-bold" style="background-color: #0b2545; border-color: #0b2545;">
                <i class="fas fa-eye me-1"></i> Preview Data
            </button>
        </form>

        <div class="mt-3 text-muted small upload-note p-3">
            <strong><i class="fas fa-info-circle text-info"></i> CSV Column Format:</strong><br>
            <code>Inventory | Employee Name | Department | Details | Serial/Model | Status | Unit | Purchase Date | Warranty (Months) | Remarks</code><br>
            <span class="text-primary mt-1 d-block">
                <i class="fas fa-sync-alt"></i> Same <strong>Inventory</strong> found ? existing row will be updated (no duplicate).<br>
                <span class="badge badge-missing text-white">BLUE</span> Employee ID will show when not found (optional field, data will still be saved).
            </span>
        </div>
    </div>
</div>

<?php if (!empty($preview_rows)): ?>
<form method="POST" action="">
    <input type="hidden" name="process_csv" value="1">
    <input type="hidden" name="csv_tmp_path" value="<?php echo htmlspecialchars($tmp_path, ENT_QUOTES); ?>">

    <div class="table-card">
        <div class="preview-card-header">
            <i class="fas fa-list-check me-2"></i>Preview (<?php echo count($preview_rows); ?> rows)
        </div>

        <div class="card-body p-0">
            <div class="table-wrapper">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Inventory</th>
                            <th>Employee</th>
                            <th>Emp. ID (Auto)</th>
                            <th>Dept</th>
                            <th>Details</th>
                            <th>Serial</th>
                            <th>Status</th>
                            <th>Unit</th>
                            <th>Purchase Date</th>
                            <th>Warranty</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $i = 1; foreach ($preview_rows as $r): ?>
                        <tr>
                            <td class="text-center"><?php echo $i++; ?></td>
                            <td class="fw-bold text-primary"><?php echo htmlspecialchars($r['inventory']); ?></td>
                            <td><?php echo htmlspecialchars($r['emp_name']); ?></td>
                            <td class="text-center">
                                <?php if ($r['emp_id'] !== ''): ?>
                                    <span class="badge bg-success text-white"><?php echo htmlspecialchars($r['emp_id']); ?></span>
                                <?php else: ?>
                                    <span class="badge badge-missing text-white">Missing / Manual</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($r['dept']); ?></td>
                            <td>
                                <span class="d-inline-block text-truncate" style="max-width:180px;" title="<?php echo htmlspecialchars($r['details']); ?>">
                                    <?php echo htmlspecialchars($r['details']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($r['serial']); ?></td>
                            <td><?php echo htmlspecialchars($r['status']); ?></td>
                            <td><?php echo htmlspecialchars($r['unit']); ?></td>
                            <td><?php echo htmlspecialchars($r['p_date']); ?></td>
                            <td><?php echo htmlspecialchars($r['warranty']); ?> m</td>
                            <td>
                                <span class="d-inline-block text-truncate" style="max-width:180px;" title="<?php echo htmlspecialchars($r['remarks']); ?>">
                                    <?php echo htmlspecialchars($r['remarks']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card-footer bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="small text-muted">
                <i class="fas fa-info-circle me-1"></i>Employee ID is auto-fetched from employees table by name. Blue badge = not found (optional, data will still be saved).
            </span>
            <div class="d-flex gap-2 mt-2 mt-md-0">
                <a href="ict_inventory_uploader.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times-circle me-1"></i>Cancel
                </a>
                <button type="submit" class="btn btn-success fw-bold">
                    <i class="fas fa-save me-1"></i>Confirm & Process All Data
                </button>
            </div>
        </div>
    </div>
</form>
<?php endif; ?>
<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
