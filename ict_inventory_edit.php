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

// Get Asset ID
$asset_id = (int)($_GET['id'] ?? 0);
if ($asset_id <= 0) {
    header("Location: ict_inventory_list.php");
    exit;
}

// Fetch existing asset data
$q = mysqli_query($conn, "SELECT * FROM assets WHERE id = $asset_id LIMIT 1");
if (!$q || mysqli_num_rows($q) === 0) {
    header("Location: ict_inventory_list.php");
    exit;
}
$asset = mysqli_fetch_assoc($q);

// ===============================
// HANDLE UPDATE FORM SUBMIT
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_asset'])) {
    
    $employee_id   = mysqli_real_escape_string($conn, trim($_POST['employee_id'] ?? ''));
    $employee_name = mysqli_real_escape_string($conn, trim($_POST['employee_name'] ?? ''));
    $department    = mysqli_real_escape_string($conn, trim($_POST['department'] ?? ''));
    $inventory     = mysqli_real_escape_string($conn, trim($_POST['inventory'] ?? ''));
    $details       = mysqli_real_escape_string($conn, trim($_POST['details'] ?? ''));
    $serial        = mysqli_real_escape_string($conn, trim($_POST['serial_model'] ?? ''));
    $status        = mysqli_real_escape_string($conn, trim($_POST['status'] ?? ''));
    $unit          = mysqli_real_escape_string($conn, trim($_POST['unit'] ?? ''));
    $purchase_date = mysqli_real_escape_string($conn, trim($_POST['purchase_date'] ?? ''));
    $warranty      = (int)($_POST['warranty_months'] ?? 0);
    $remarks       = mysqli_real_escape_string($conn, trim($_POST['remarks'] ?? ''));

    if ($inventory === '') {
        $swal_script = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({ icon: 'error', title: 'Inventory field is required!' });
            });
        </script>";
    } else {
        if ($purchase_date === '' || $purchase_date === '0000-00-00') {
            $purchase_date_sql = "NULL";
        } else {
            $purchase_date_sql = "'$purchase_date'";
        }

        $sql = "UPDATE assets SET
                employee_id='$employee_id',
                employee_name='$employee_name',
                department='$department',
                inventory='$inventory',
                details='$details',
                serial_model='$serial',
                status='$status',
                unit='$unit',
                purchase_date=$purchase_date_sql,
                warranty_months=$warranty,
                remarks='$remarks',
                update_user='$user',
                update_datetime=NOW()
                WHERE id = $asset_id";

        if (mysqli_query($conn, $sql)) {
            $safe_inventory = htmlspecialchars($inventory, ENT_QUOTES);
            $swal_script = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        icon: 'success',
                        title: 'Asset Updated Successfully!',
                        text: 'Inventory: $safe_inventory',
                        confirmButtonText: 'Go to List'
                    }).then(() => window.location='ict_inventory_list.php');
                });
            </script>";
        } else {
            $safe_error = addslashes(mysqli_error($conn));
            $swal_script = "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({ icon: 'error', title: 'Update Failed!', text: '$safe_error' });
                });
            </script>";
        }

        $q = mysqli_query($conn, "SELECT * FROM assets WHERE id = $asset_id LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $asset = mysqli_fetch_assoc($q);
        }
    }
}

$page_title = 'Edit Asset - SCL AMS';
$page_header_icon = 'fas fa-edit';
$page_header_title = 'Edit ICT Asset';
$page_header_subtitle = 'Update assignment, status, purchase, and warranty information';
$page_top_title = 'Edit Asset - ' . $asset['inventory'];
$page_top_actions = '<a href="ict_inventory_list.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to List</a>';
$page_container_class = 'dashboard-container';

$body_extra_top = $swal_script;

$extra_css = "
.dashboard-container {
    max-width: 1200px;
    margin: 0 auto;
}
.info-alert {
    background: #f8fbff;
    border: 1px solid #b6e0fe;
    border-radius: 10px;
}
.form-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}
.form-label {
    font-weight: 600;
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
textarea.form-control {
    min-height: 100px;
}
.badge-info-custom {
    background-color: #17a2b8;
    color: white;
}
@media (max-width: 991px) {
    .form-card-body {
        padding: 18px 15px;
    }
    .form-card-header {
        align-items: flex-start;
    }
    .info-alert {
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 10px;
    }
}
";

ob_start();
?>
<div class="form-card">
    <div class="form-card-header">
        <h5 class="mb-0"><i class="fas fa-pen-to-square me-2"></i>Asset Information</h5>
        <a href="ict_inventory_list.php" class="btn btn-sm btn-light text-dark fw-bold">
            <i class="fas fa-arrow-left me-1"></i>Back to List
        </a>
    </div>

    <div class="form-card-body">

        <div class="alert info-alert d-flex justify-content-between align-items-center mb-4">
            <span class="text-secondary">
                <i class="fas fa-user-plus me-2 text-info"></i>
                <strong>Entry by:</strong> <?php echo htmlspecialchars($asset['entry_user'] ?? $asset['created_user'] ?? 'System'); ?>
                <span class="badge badge-info-custom ms-2">
                    <?php echo htmlspecialchars($asset['entry_datetime'] ?? $asset['created_datetime'] ?? 'N/A'); ?>
                </span>
            </span>

            <?php if (!empty($asset['update_user'])): ?>
            <span class="text-secondary">
                <i class="fas fa-user-edit me-2 text-warning"></i>
                <strong>Last Updated by:</strong> <?php echo htmlspecialchars($asset['update_user']); ?>
                <span class="badge bg-warning text-dark ms-2">
                    <?php echo htmlspecialchars($asset['update_datetime']); ?>
                </span>
            </span>
            <?php endif; ?>
        </div>

        <form method="POST" action="" id="editForm">
            <div class="row g-4">

                <div class="col-md-4">
                    <label class="form-label">Employee ID</label>
                    <input type="text" name="employee_id" id="employee_id" class="form-control bg-light"
                           value="<?php echo htmlspecialchars($asset['employee_id']); ?>" placeholder="Optional">
                    <small class="text-muted">Leave blank if not assigned to employee</small>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Employee Name</label>
                    <input type="text" name="employee_name" id="employee_name" class="form-control"
                           value="<?php echo htmlspecialchars($asset['employee_name']); ?>" placeholder="Auto-filled">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" id="department" class="form-control"
                           value="<?php echo htmlspecialchars($asset['department']); ?>" placeholder="Auto-filled">
                </div>

                <div class="col-12"><hr class="text-muted"></div>

                <div class="col-md-4">
                    <label class="form-label text-danger">Inventory * (Required)</label>
                    <input type="text" name="inventory" class="form-control border-danger" required
                           value="<?php echo htmlspecialchars($asset['inventory']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Serial/Model No.</label>
                    <input type="text" name="serial_model" class="form-control"
                           value="<?php echo htmlspecialchars($asset['serial_model']); ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Current Status</label>
                    <select name="status" class="form-select border-primary">
                        <option value="">-- Select --</option>
                        <option value="Active" <?php echo $asset['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Assigned" <?php echo $asset['status'] === 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                        <option value="Available" <?php echo $asset['status'] === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Under Repair" <?php echo $asset['status'] === 'Under Repair' ? 'selected' : ''; ?>>Under Repair</option>
                        <option value="Damage" <?php echo $asset['status'] === 'Damage' ? 'selected' : ''; ?>>Damage</option>
                        <option value="Retired" <?php echo $asset['status'] === 'Retired' ? 'selected' : ''; ?>>Retired</option>
                        <option value="Lost" <?php echo $asset['status'] === 'Lost' ? 'selected' : ''; ?>>Lost</option>
                    </select>
                </div>

                <div class="col-md-8">
                    <label class="form-label">Device Details / Specs</label>
                    <input type="text" name="details" class="form-control"
                           value="<?php echo htmlspecialchars($asset['details']); ?>" placeholder="e.g. Dell Core i5, 8GB RAM">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Unit / Type</label>
                    <input type="text" name="unit" class="form-control"
                           value="<?php echo htmlspecialchars($asset['unit']); ?>" placeholder="e.g. Laptop, Monitor">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Purchase Date</label>
                    <input type="date" name="purchase_date" class="form-control"
                           value="<?php echo $asset['purchase_date'] !== '0000-00-00' ? htmlspecialchars($asset['purchase_date']) : ''; ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Warranty (Months)</label>
                    <input type="number" name="warranty_months" class="form-control"
                           value="<?php echo htmlspecialchars($asset['warranty_months']); ?>" min="0">
                </div>

                <div class="col-md-12">
                    <label class="form-label">Remarks / Notes</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Any additional information..."><?php echo htmlspecialchars($asset['remarks']); ?></textarea>
                </div>

            </div>

            <div class="mt-5 d-flex gap-2 justify-content-end border-top pt-3 flex-wrap">
                <a href="ict_inventory_list.php" class="btn btn-light border px-4">
                    <i class="fas fa-times me-1"></i> Cancel
                </a>
                <button type="submit" name="update_asset" class="btn btn-primary px-5 fw-bold" style="background-color: #0b2545; border-color: #0b2545;">
                    <i class="fas fa-save me-1"></i> Save Changes
                </button>
            </div>
        </form>

    </div>
</div>

<script>
document.getElementById('employee_id').addEventListener('blur', function() {
    const empId = this.value.trim();

    if (empId === '') {
        document.getElementById('employee_name').value = '';
        document.getElementById('department').value = '';
        return;
    }

    fetch('fetch_employee_details.php?emp_id=' + encodeURIComponent(empId))
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('employee_name').value = data.employee_name;
                document.getElementById('department').value = data.department;
            } else {
                Swal.fire({
                    icon: 'warning',
                    title: 'Employee Not Found',
                    text: 'No employee found with ID: ' + empId,
                    toast: true,
                    position: 'top-end',
                    timer: 3000,
                    showConfirmButton: false
                });
                document.getElementById('employee_name').value = '';
                document.getElementById('department').value = '';
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
