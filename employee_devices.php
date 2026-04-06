<?php
session_start();
require_once('db.php');
require_once('header.php');

// Security: Only SuperAdmin, HR, Staff, Admin
$role = $_SESSION['UserRole'] ?? '';
if (!in_array($role, ['SuperAdmin', 'HR', 'Staff', 'admin'])) {
    header("Location: login.php");
    exit;
}

$emp_id = isset($_GET['emp_id']) ? trim($_GET['emp_id']) : '';
if ($emp_id === '') {
    header("Location: employee_list.php");
    exit;
}

// Leading zero handle: 255721 -> 00255721
$emp_id_padded = str_pad($emp_id, 8, '0', STR_PAD_LEFT);
$emp_id_safe = mysqli_real_escape_string($conn, $emp_id);
$emp_id_padded_safe = mysqli_real_escape_string($conn, $emp_id_padded);

// 1) Employee info from assets table, check both original and padded ID
$info_q = mysqli_query($conn, "
    SELECT DISTINCT employee_id, employee_name, department
    FROM assets
    WHERE employee_id = '$emp_id_safe' OR employee_id = '$emp_id_padded_safe'
    LIMIT 1
");

if (!$info_q || mysqli_num_rows($info_q) == 0) {
    header("Location: employee_list.php?msg=No+devices+found");
    exit;
}

$emp = mysqli_fetch_assoc($info_q);

// 2) Device list
$dev_q = mysqli_query($conn, "
    SELECT inventory, details, serial_model, status, unit,
           purchase_date, warranty_months, remarks, entry_datetime
    FROM assets
    WHERE employee_id = '$emp_id_safe' OR employee_id = '$emp_id_padded_safe'
    ORDER BY inventory ASC
");

$device_count = $dev_q ? mysqli_num_rows($dev_q) : 0;

$page_title = htmlspecialchars($emp['employee_name']) . ' - ICT Devices';
$page_header_icon = 'fas fa-desktop';
$page_header_title = 'Employee ICT Devices';
$page_header_subtitle = 'Assigned devices and inventory details';
$page_top_title = $emp['employee_name'];
$page_top_actions = '
<a href="employee_list.php" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to Employee List
</a>';
$page_container_class = 'dashboard-container-wide';

$extra_head = '<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">';

$extra_css = "
.dashboard-container-wide {
    max-width: 1400px;
    margin: 0 auto;
}
.card-custom {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
}
.table-custom th {
    background: #1e293b !important;
    color: white !important;
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}
.table-custom td {
    vertical-align: middle;
    font-size: 13px;
}
.device-badge {
    background-color: #10b981;
    color: white;
    font-weight: bold;
}
.emp-meta {
    color: #64748b;
    font-size: 14px;
    font-weight: 500;
    margin-top: -8px;
    margin-bottom: 18px;
}
table.dataTable thead th,
table.dataTable thead td {
    border-bottom: none !important;
}
.dataTables_wrapper .dataTables_filter input {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 6px 10px;
}
.dataTables_wrapper .dataTables_length select {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 4px 8px;
}
@media (max-width: 991px) {
    .card-custom {
        padding: 15px;
    }
}
";

ob_start();
?>

<div class="emp-meta">
    ID: <?php echo htmlspecialchars($emp['employee_id']); ?> |
    Dept: <?php echo htmlspecialchars($emp['department']); ?>
</div>

<div class="card-custom mb-4">
    <div class="card-body text-center p-4">
        <h6 class="text-uppercase text-muted mb-3">Total ICT Devices Assigned</h6>
        <div class="display-4 fw-bold text-primary"><?php echo $device_count; ?></div>
        <div class="device-badge px-3 py-2 mt-2 d-inline-block">
            <?php echo $device_count; ?> Device<?php echo $device_count != 1 ? 's' : ''; ?>
        </div>
    </div>
</div>

<?php if ($device_count > 0): ?>
<div class="card-custom">
    <div class="table-responsive">
        <table id="deviceTable" class="table table-hover table-bordered table-custom">
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Inventory Item</th>
                    <th>Details</th>
                    <th>Serial/Model No</th>
                    <th>Status</th>
                    <th>Unit</th>
                    <th>Purchase Date</th>
                    <th>Warranty (Months)</th>
                    <th>Remarks</th>
                    <th>Assigned On</th>
                </tr>
            </thead>
            <tbody>
            <?php $sl = 1; while ($dev = mysqli_fetch_assoc($dev_q)): ?>
                <tr>
                    <td class="text-center"><?php echo $sl++; ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($dev['inventory']); ?></td>
                    <td><?php echo htmlspecialchars($dev['details']); ?></td>
                    <td><code><?php echo htmlspecialchars($dev['serial_model']); ?></code></td>
                    <td><?php echo htmlspecialchars($dev['status']); ?></td>
                    <td><?php echo htmlspecialchars($dev['unit']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($dev['purchase_date']); ?></td>
                    <td class="text-center"><?php echo htmlspecialchars($dev['warranty_months']); ?></td>
                    <td><?php echo htmlspecialchars($dev['remarks']); ?></td>
                    <td class="text-center small"><?php echo htmlspecialchars($dev['entry_datetime']); ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<?php else: ?>
<div class="card-custom">
    <div class="card-body text-center p-5">
        <i class="fas fa-desktop fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">No Devices Found</h5>
        <p class="text-muted mb-0">This employee has no ICT inventory assigned yet.</p>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#deviceTable').DataTable({
        pageLength: 15,
        language: { search: "Search Devices:" }
    });
});
</script>

<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
