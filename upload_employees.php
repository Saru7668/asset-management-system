<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');
require_once('header.php');

// Security: Only SuperAdmin, HR, Staff
$role = $_SESSION['UserRole'] ?? '';
if (!in_array($role, ['SuperAdmin', 'HR', 'Staff', 'admin'])) {
    header("Location: login.php");
    exit;
}

$preview_data = [];
$show_preview = false;
$success_msg = "";
$error_msg = "";
$sweet_alert_script = "";

// ==========================================
// 1. CSV file preview logic
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file']['tmp_name'];

    $existing_emails = [];
    $existing_ids = [];
    $res = mysqli_query($conn, "SELECT email, employee_id FROM employees");
    while ($row = mysqli_fetch_assoc($res)) {
        $existing_emails[] = strtolower(trim($row['email']));
        $existing_ids[] = strtolower(trim($row['employee_id']));
    }

    if (($handle = fopen($file, "r")) !== false) {
        $header = fgetcsv($handle, 1000, ",");

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            $emp_id = trim($data[0] ?? '');
            $emp_name = trim($data[1] ?? '');
            $location = trim($data[2] ?? '');
            $email = trim($data[3] ?? '');
            $designation = trim($data[4] ?? '');
            $department = trim($data[5] ?? '');
            $phone = trim($data[6] ?? '');
            $date_of_joining = trim($data[7] ?? '');
            $employee_category = trim($data[8] ?? '');
            $employee_status = trim($data[9] ?? '');

            $email_valid = filter_var($email, FILTER_VALIDATE_EMAIL);
            $id_valid = !empty($emp_id);
            $name_valid = !empty($emp_name);

            $is_duplicate = in_array(strtolower($email), $existing_emails) || in_array(strtolower($emp_id), $existing_ids);

            if ($id_valid && $name_valid && $email_valid) {
                $preview_data[] = [
                    'emp_id' => $emp_id,
                    'emp_name' => $emp_name,
                    'location' => $location,
                    'email' => $email,
                    'designation' => $designation,
                    'department' => $department,
                    'phone' => $phone,
                    'date_of_joining' => $date_of_joining,
                    'employee_category' => $employee_category,
                    'employee_status' => $employee_status,
                    'is_duplicate' => $is_duplicate
                ];
            }
        }
        fclose($handle);
        $show_preview = true;

        $_SESSION['csv_upload_data'] = $preview_data;
    }
}

// ==========================================
// 2. Save previewed data
// ==========================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save_valid_data'])) {
    $data_to_save = $_SESSION['csv_upload_data'] ?? [];
    $inserted_count = 0;
    $updated_count = 0;
    $failed_count = 0;

    if (!empty($data_to_save)) {
        $query = "INSERT INTO employees (
                    employee_id,
                    employee_name,
                    location,
                    email,
                    designation,
                    department,
                    phone,
                    date_of_joining,
                    employee_category,
                    employee_status
                  ) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                  ON DUPLICATE KEY UPDATE 
                  employee_name = VALUES(employee_name),
                  location = VALUES(location),
                  designation = VALUES(designation),
                  department = VALUES(department),
                  phone = VALUES(phone),
                  date_of_joining = VALUES(date_of_joining),
                  employee_category = VALUES(employee_category),
                  employee_status = VALUES(employee_status)";
                  
        $stmt = mysqli_prepare($conn, $query);
        $deleted_rows = json_decode($_POST['deleted_rows'] ?? '[]', true);

        foreach ($data_to_save as $index => $row) {
            if (!in_array($index, $deleted_rows)) {
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssssss",
                    $row['emp_id'],
                    $row['emp_name'],
                    $row['location'],
                    $row['email'],
                    $row['designation'],
                    $row['department'],
                    $row['phone'],
                    $row['date_of_joining'],
                    $row['employee_category'],
                    $row['employee_status']
                );
                mysqli_stmt_execute($stmt);

                $affected_rows = mysqli_stmt_affected_rows($stmt);

                if ($affected_rows == 1) {
                    $inserted_count++;
                } elseif ($affected_rows == 2) {
                    $updated_count++;
                } elseif ($affected_rows == 0) {
                    $updated_count++;
                } else {
                    $failed_count++;
                }
            }
        }

        $html_message = "<div style='text-align:left; font-size:15px;'>
                            <ul class='list-group mb-0'>
                                <li class='list-group-item d-flex justify-content-between align-items-center'>
                                  <strong>New Records Added:</strong>
                                  <span class='badge bg-success rounded-pill' style='font-size:14px;'>$inserted_count</span>
                                </li>
                                <li class='list-group-item d-flex justify-content-between align-items-center mt-2'>
                                  <strong>Existing Records Updated:</strong>
                                  <span class='badge bg-info text-dark rounded-pill' style='font-size:14px;'>$updated_count</span>
                                </li>
                            </ul>
                         </div>";

        $sweet_alert_script = "
            Swal.fire({
                icon: 'success',
                title: 'Upload Successful!',
                html: `$html_message`,
                confirmButtonColor: '#0b2545',
                confirmButtonText: 'Great!'
            });
        ";

        unset($_SESSION['csv_upload_data']);
        $show_preview = false;
    } else {
        $sweet_alert_script = "
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No valid data found to save.',
                confirmButtonColor: '#0b2545'
            });
        ";
    }
}

if (!empty($sweet_alert_script)) {
    $body_extra_top = "
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                {$sweet_alert_script}
            });
        </script>
    ";
} else {
    $body_extra_top = "";
}

$page_title = 'Upload Employees - SCL AMS';
$page_header_icon = 'fas fa-file-csv';
$page_header_title = 'Bulk Employee Upload';
$page_header_subtitle = 'Preview and upload employee records from CSV files';
$page_top_title = 'Bulk Employee Upload';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.dashboard-container-wide {
    max-width: 1400px;
    margin: 0 auto;
}
.upload-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
}
.duplicate-cell {
    background-color: #e6f7ff !important;
    color: #005580;
    font-weight: bold;
    border: 1px solid #005580 !important;
    position: relative;
}
.duplicate-cell:hover::after {
    content: 'Record exists. Will be UPDATED!';
    position: absolute;
    top: -25px;
    left: 50%;
    transform: translateX(-50%);
    background: #005580;
    color: white;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    white-space: nowrap;
    z-index: 10;
}
.table-custom th {
    background: #1e293b;
    color: white;
    font-weight: 500;
    white-space: nowrap;
}
.table-custom td {
    vertical-align: middle;
    font-size: 14px;
}
@media (max-width: 991px) {
    .upload-card {
        padding: 18px 15px;
    }
    .page-topbar {
        flex-direction: column;
        align-items: stretch;
    }
}

/* Dark mode fixes */
html[data-theme='dark'] .upload-card {
    background: #1e293b !important;
    color: #e5e7eb !important;
    border-top: 4px solid #facc15 !important;
    box-shadow: 0 10px 30px rgba(0,0,0,0.35) !important;
}

html[data-theme='dark'] .upload-card label,
html[data-theme='dark'] .upload-card h5,
html[data-theme='dark'] .upload-card h6,
html[data-theme='dark'] .upload-card .fw-bold {
    color: #f8fafc !important;
}

html[data-theme='dark'] .upload-card p,
html[data-theme='dark'] .upload-card span,
html[data-theme='dark'] .upload-card small,
html[data-theme='dark'] .upload-card .text-muted,
html[data-theme='dark'] .upload-card .small {
    color: #cbd5e1 !important;
}

html[data-theme='dark'] .upload-card strong {
    color: #f8fafc !important;
}

html[data-theme='dark'] .upload-card .form-control,
html[data-theme='dark'] .upload-card input[type='file'] {
    background: #0f172a !important;
    color: #f8fafc !important;
    border: 1px solid #334155 !important;
}

html[data-theme='dark'] .upload-card .form-control:focus,
html[data-theme='dark'] .upload-card input[type='file']:focus {
    background: #0f172a !important;
    color: #f8fafc !important;
    border-color: #facc15 !important;
    box-shadow: 0 0 0 0.2rem rgba(250, 204, 21, 0.18) !important;
}

html[data-theme='dark'] .upload-card input[type='file']::file-selector-button {
    background: #334155 !important;
    color: #f8fafc !important;
    border: none !important;
    border-right: 1px solid #475569 !important;
    padding: 0.375rem 0.75rem !important;
    margin-right: 0.75rem !important;
}

html[data-theme='dark'] .upload-card .input-group-text {
    background: #0f172a !important;
    color: #f8fafc !important;
    border: 1px solid #334155 !important;
}

html[data-theme='dark'] .upload-card .btn-outline-success,
html[data-theme='dark'] .upload-card .btn-outline-success:link,
html[data-theme='dark'] .upload-card .btn-outline-success:visited {
    color: #86efac !important;
    border-color: #22c55e !important;
    background: transparent !important;
}

html[data-theme='dark'] .upload-card .btn-outline-success:hover,
html[data-theme='dark'] .upload-card .btn-outline-success:focus,
html[data-theme='dark'] .upload-card .btn-outline-success:active {
    background: #22c55e !important;
    color: #062e16 !important;
    border-color: #22c55e !important;
}

html[data-theme='dark'] .upload-card .btn-primary {
    background: linear-gradient(135deg, #1d4ed8 0%, #0b2545 100%) !important;
    border-color: #1d4ed8 !important;
    color: #ffffff !important;
}

html[data-theme='dark'] .upload-card .btn-primary:hover,
html[data-theme='dark'] .upload-card .btn-primary:focus,
html[data-theme='dark'] .upload-card .btn-primary:active {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
    border-color: #2563eb !important;
    color: #ffffff !important;
}

html[data-theme='dark'] .upload-card .btn-secondary {
    background: #475569 !important;
    border-color: #475569 !important;
    color: #f8fafc !important;
}

html[data-theme='dark'] .upload-card .btn-success {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%) !important;
    border-color: #16a34a !important;
    color: #ffffff !important;
}

html[data-theme='dark'] .upload-card .btn-danger {
    color: #ffffff !important;
}

html[data-theme='dark'] .upload-card .alert-info {
    background: rgba(14, 165, 233, 0.12) !important;
    border-color: rgba(14, 165, 233, 0.35) !important;
    color: #dbeafe !important;
}

html[data-theme='dark'] .upload-card .alert-info strong {
    color: #ffffff !important;
}

html[data-theme='dark'] .table-custom {
    color: #e5e7eb !important;
    border-color: #334155 !important;
}

html[data-theme='dark'] .table-custom th {
    background: #0f172a !important;
    color: #f8fafc !important;
    border-color: #334155 !important;
}

html[data-theme='dark'] .table-custom td {
    background: #1e293b !important;
    color: #e5e7eb !important;
    border-color: #334155 !important;
}

html[data-theme='dark'] .duplicate-cell {
    background-color: rgba(56, 189, 248, 0.18) !important;
    color: #7dd3fc !important;
    border: 1px solid #38bdf8 !important;
}

html[data-theme='dark'] .duplicate-cell:hover::after {
    background: #0369a1 !important;
    color: #ffffff !important;
}
";

ob_start();
?>

<div class="upload-card mb-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <label class="fw-bold mb-0">Select CSV File to Upload:</label>
        <a href="sample_format.csv" download class="btn btn-sm btn-outline-success">
            <i class="fas fa-download me-1"></i> Download Sample CSV
        </a>
    </div>

    <p class="text-muted small mb-2">
        Column Order MUST be:
        <strong>ID | Employee Name | Location | Mail Address | Designation | Department | Phone | Date of Joining | Employee Category | Employee Status</strong>
    </p>

    <form method="POST" enctype="multipart/form-data">
        <div class="input-group">
            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
            <button type="submit" class="btn btn-primary" style="background:#0b2545; border-color:#0b2545;">
                <i class="fas fa-eye me-1"></i> Preview Data
            </button>
        </div>
    </form>
</div>

<?php if ($show_preview && !empty($preview_data)): ?>
<div class="upload-card fade-in">
    <h5 class="mb-3 text-warning fw-bold"><i class="fas fa-exclamation-triangle me-2"></i>Data Preview (Review before saving)</h5>

    <div class="alert alert-info py-2 small border-info">
        <i class="fas fa-info-circle me-1"></i>
        Cells highlighted in <strong style="color:#005580;">BLUE</strong> indicate the employee already exists in the system. Their information will be <strong>UPDATED (Rewritten)</strong>. New records will be <strong>ADDED</strong>. You can delete rows using the trash icon.
    </div>

    <form id="saveForm" method="POST" action="">
        <input type="hidden" name="save_valid_data" value="1">
        <input type="hidden" name="deleted_rows" id="deleted_rows_input" value="[]">

        <div class="table-responsive">
            <table class="table table-bordered text-center table-hover table-custom" id="previewTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee Name</th>
                        <th>Location</th>
                        <th>Mail Address</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Phone</th>
                        <th>Date of Joining</th>
                        <th>Employee Category</th>
                        <th>Employee Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($preview_data as $index => $row): ?>
                    <tr id="row_<?php echo $index; ?>">
                        <td class="<?php echo $row['is_duplicate'] ? 'duplicate-cell' : ''; ?>">
                            <?php echo htmlspecialchars($row['emp_id']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['emp_name']); ?></td>
                        <td><?php echo htmlspecialchars($row['location']); ?></td>
                        <td class="<?php echo $row['is_duplicate'] ? 'duplicate-cell' : ''; ?>">
                            <?php echo htmlspecialchars($row['email']); ?>
                        </td>
                        <td><?php echo htmlspecialchars($row['designation']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td><?php echo htmlspecialchars($row['phone']); ?></td>
                        <td><?php echo htmlspecialchars($row['date_of_joining']); ?></td>
                        <td><?php echo htmlspecialchars($row['employee_category']); ?></td>
                        <td><?php echo htmlspecialchars($row['employee_status']); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteRow(<?php echo $index; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-3 text-end">
            <a href="upload_employees.php" class="btn btn-secondary me-2">Cancel</a>
            <button type="submit" class="btn btn-success fw-bold">
                <i class="fas fa-save me-1"></i> Confirm & Save All Records
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
let deletedRows = [];

function deleteRow(index) {
    const row = document.getElementById('row_' + index);
    if (row) {
        row.style.display = 'none';
    }

    deletedRows.push(index);
    document.getElementById('deleted_rows_input').value = JSON.stringify(deletedRows);
}
</script>

<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
?>