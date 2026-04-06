<?php
session_start();
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName']) || !in_array(strtolower($_SESSION['UserRole'] ?? ''), ['admin', 'superadmin', 'staff', 'hr'])) {
    header("Location: index.php");
    exit;
}

$user = $_SESSION['UserName'];
$asset_id = (int)($_GET['id'] ?? 0);

$q = mysqli_query($conn, "SELECT * FROM assets WHERE id = $asset_id");
if (!$q || mysqli_num_rows($q) == 0) {
    header("Location: ict_inventory_list.php");
    exit;
}
$asset = mysqli_fetch_assoc($q);

$swal_script = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['assign_asset'])) {
    $emp_id   = mysqli_real_escape_string($conn, trim($_POST['employee_id']));
    $emp_name = mysqli_real_escape_string($conn, trim($_POST['employee_name']));
    $dept     = mysqli_real_escape_string($conn, trim($_POST['department']));
    $inv      = mysqli_real_escape_string($conn, $asset['inventory']);

    mysqli_query($conn, "INSERT INTO asset_logs (asset_id, inventory, action_type, employee_id, employee_name, department, action_date, action_by) VALUES ($asset_id, '$inv', 'Assigned', '$emp_id', '$emp_name', '$dept', NOW(), '$user')");
    
    mysqli_query($conn, "UPDATE assets SET employee_id='$emp_id', employee_name='$emp_name', department='$dept', status='Assigned', update_user='$user', update_datetime=NOW() WHERE id=$asset_id");

    $swal_script = "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Assigned Successfully!',
                text: 'Asset has been assigned to the selected employee.',
                confirmButtonColor: '#0b2545'
            }).then(() => {
                window.location='ict_inventory_list.php';
            });
        });
    </script>";
}

$page_title = 'Assign Asset - SCL AMS';
$page_header_icon = 'fas fa-user-plus';
$page_header_title = 'Assign Asset';
$page_header_subtitle = 'Link this inventory item to an employee profile';
$page_top_title = 'Assign Asset: ' . htmlspecialchars($asset['inventory']);
$page_top_actions = '<a href="ict_inventory_list.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to List</a>';
$page_container_class = 'dashboard-container';

$body_extra_top = $swal_script;

$extra_css = "
.dashboard-container {
    max-width: 950px;
    margin: 0 auto;
}
.asset-badge-box {
    background: #f8fbff;
    border: 1px solid #dbeafe;
    border-radius: 10px;
    padding: 14px 16px;
    margin-bottom: 20px;
}
.form-label {
    font-weight: 600;
    font-size: 13px;
    color: #495057;
    margin-bottom: 8px;
}
.form-control,
.form-select {
    min-height: 44px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
}
.form-control:focus,
.form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59,130,246,0.15);
}
@media (max-width: 991px) {
    .assign-card-body {
        padding: 18px 15px;
    }
}
";

ob_start();
?>
<div class="assign-card">
    <div class="assign-card-header">
        <h5 class="mb-0">
            <i class="fas fa-desktop me-2"></i>Employee Assignment Form
        </h5>
    </div>

    <div class="assign-card-body">
        <div class="asset-badge-box">
            <div class="fw-bold text-primary mb-1"><?php echo htmlspecialchars($asset['inventory']); ?></div>
            <div class="text-muted small">
                <?php echo htmlspecialchars($asset['details'] ?? 'No details available'); ?>
            </div>
        </div>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Employee ID</label>
                <div class="input-group">
                    <input type="text" id="employee_id" name="employee_id" class="form-control" required placeholder="Type ID to search...">
                    <a href="add_employee.php" id="add_emp_btn" class="btn btn-outline-success d-none" target="_blank" title="Add New Employee">
                        <i class="fas fa-plus"></i> Add
                    </a>
                </div>
                <small class="text-muted">Type employee ID and click outside or tab to auto-check.</small>
            </div>

            <div class="mb-3">
                <label class="form-label">Employee Name</label>
                <input type="text" id="employee_name" name="employee_name" class="form-control bg-light" readonly required>
            </div>

            <div class="mb-4">
                <label class="form-label">Department</label>
                <input type="text" id="department" name="department" class="form-control bg-light" readonly required>
            </div>

            <div class="d-flex gap-2 flex-wrap">
                <a href="ict_inventory_list.php" class="btn btn-secondary">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
                <button type="submit" name="assign_asset" class="btn btn-primary flex-grow-1" style="background-color:#0b2545; border-color:#0b2545;">
                    <i class="fas fa-user-check me-1"></i> Assign to Employee
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('employee_id').addEventListener('blur', function() {
    let id = this.value.trim();

    if (id === '') {
        document.getElementById('employee_name').value = '';
        document.getElementById('department').value = '';
        document.getElementById('add_emp_btn').classList.add('d-none');
        return;
    }

    fetch('fetch_employee_details.php?emp_id=' + encodeURIComponent(id))
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('employee_name').value = data.employee_name;
            document.getElementById('department').value = data.department;
            document.getElementById('add_emp_btn').classList.add('d-none');
        } else {
            document.getElementById('employee_name').value = '';
            document.getElementById('department').value = '';
            document.getElementById('add_emp_btn').classList.remove('d-none');

            Swal.fire({
                icon: 'warning',
                title: 'Employee ID not found',
                text: 'Click the Add button to register this employee.',
                confirmButtonColor: '#f59e0b'
            });
        }
    })
    .catch(() => {
        Swal.fire({
            icon: 'error',
            title: 'Lookup failed',
            text: 'Could not fetch employee information right now.'
        });
    });
});
</script>
<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
