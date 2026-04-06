<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('db.php');
require_once('header.php');

// Security Check
$role = $_SESSION['UserRole'] ?? '';
$user = $_SESSION['UserName'] ?? 'Unknown';

if (!in_array($role, ['SuperAdmin', 'HR', 'Staff', 'admin'])) {
    header("Location: index.php");
    exit;
}

// Get Employee ID from URL
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: employee_list.php");
    exit;
}

$message = '';
$msg_type = '';

// Fetch Existing Employee Data
$query = mysqli_query($conn, "SELECT * FROM employees WHERE id = $id LIMIT 1");
if (!$query || mysqli_num_rows($query) == 0) {
    header("Location: employee_list.php");
    exit;
}

$emp_data = mysqli_fetch_assoc($query);

// Handle Form Submission for Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_employee'])) {
    $emp_id      = mysqli_real_escape_string($conn, trim($_POST['employee_id'] ?? ''));
    $emp_name    = mysqli_real_escape_string($conn, trim($_POST['employee_name'] ?? ''));
    $designation = mysqli_real_escape_string($conn, trim($_POST['designation'] ?? ''));
    $department  = mysqli_real_escape_string($conn, trim($_POST['department'] ?? ''));
    $location    = mysqli_real_escape_string($conn, trim($_POST['location'] ?? ''));
    $email       = mysqli_real_escape_string($conn, trim($_POST['email'] ?? ''));
    $phone       = mysqli_real_escape_string($conn, trim($_POST['phone'] ?? ''));
    $date_of_joining = mysqli_real_escape_string($conn, trim($_POST['date_of_joining'] ?? ''));
    $employee_category = mysqli_real_escape_string($conn, trim($_POST['employee_category'] ?? ''));
    $employee_status = mysqli_real_escape_string($conn, trim($_POST['employee_status'] ?? ''));

    $check_q = mysqli_query($conn, "SELECT id FROM employees WHERE employee_id = '$emp_id' AND id != $id");

    if ($check_q && mysqli_num_rows($check_q) > 0) {
        $message = "Error: Employee ID '$emp_id' is already assigned to someone else!";
        $msg_type = "danger";
    } else {
        if ($date_of_joining === '' || $date_of_joining === '0000-00-00') {
            $date_of_joining_sql = "NULL";
        } else {
            $date_of_joining_sql = "'$date_of_joining'";
        }

        $sql = "UPDATE employees SET
        employee_id = '$emp_id',
        employee_name = '$emp_name',
        designation = '$designation',
        department = '$department',
        location = '$location',
        email = '$email',
        phone = '$phone',
        date_of_joining = $date_of_joining_sql,
        employee_category = '$employee_category',
        employee_status = '$employee_status'
        WHERE id = $id";

        if (mysqli_query($conn, $sql)) {
            $emp_data['employee_id'] = $emp_id;
            $emp_data['employee_name'] = $emp_name;
            $emp_data['designation'] = $designation;
            $emp_data['department'] = $department;
            $emp_data['location'] = $location;
            $emp_data['email'] = $email;
            $emp_data['phone'] = $phone;
            $emp_data['date_of_joining'] = $date_of_joining;
            $emp_data['employee_category'] = $employee_category;
            $emp_data['employee_status'] = $employee_status;

            $message = "Employee record updated successfully!";
            $msg_type = "success";
        } else {
            $message = "Database Error: " . mysqli_error($conn);
            $msg_type = "danger";
        }
    }
}

// Fetch Unique Locations
$locations = [];
$loc_query = mysqli_query($conn, "SELECT DISTINCT location FROM employees WHERE location IS NOT NULL AND location != '' ORDER BY location ASC");
if ($loc_query) {
    while ($row = mysqli_fetch_assoc($loc_query)) {
        $locations[] = $row['location'];
    }
}

// Fixed Departments List
$departments = [
    "ICT", "HR & Admin", "Accounts & Finance", "Sales & Marketing", "Supply Chain",
    "Production", "Civil Engineering", "Electrical", "Mechanical", "Glazeline",
    "Laboratory & Quality Control", "Power & Generation", "Press", "Sorting & Packing",
    "Squaring & Polishing", "VAT", "Kiln", "Inventory", "Audit", "Brand"
];

if (!empty($emp_data['department']) && !in_array($emp_data['department'], $departments)) {
    $departments[] = $emp_data['department'];
}

if (!empty($emp_data['location']) && !in_array($emp_data['location'], $locations)) {
    $locations[] = $emp_data['location'];
}

$page_title = 'Edit Employee - SCL AMS';
$page_header_icon = 'fas fa-user-edit';
$page_header_title = 'Edit Employee';
$page_header_subtitle = 'Update employee information and contact details';
$page_top_title = 'Edit Employee';
$page_top_actions = '
<a href="employee_list.php" class="btn btn-outline-secondary fw-bold">
    <i class="fas fa-arrow-left me-1"></i> Back to List
</a>';
$page_container_class = 'dashboard-container';

$extra_head = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
';

$extra_css = "
.dashboard-container {
    max-width: 1280px;
    margin: 0 auto;
}

.card-custom {
    background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    border-radius: 18px;
    box-shadow: 0 10px 35px rgba(15,23,42,0.08);
    border-top: 4px solid #eab308;
    border: 1px solid #e5e7eb;
    padding: 30px;
}

.form-section-title {
    font-size: 15px;
    font-weight: 800;
    color: #0f172a;
    margin-bottom: 4px;
    letter-spacing: 0.2px;
}

.form-section-subtitle {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 18px;
}

.section-block {
    background: #ffffff;
    border: 1px solid #edf2f7;
    border-radius: 16px;
    padding: 20px;
}

.form-label {
    display: block;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
    font-size: 14px;
}

.form-control {
    border-radius: 10px;
    border: 1px solid #cbd5e1;
    min-height: 46px;
    padding: 10px 12px;
    background: #fff;
    color: #0f172a;
    width: 100%;
}

.form-control:focus {
    border-color: #f59e0b;
    box-shadow: 0 0 0 0.2rem rgba(245,158,11,0.15);
    outline: none;
}

select.form-control {
    padding-right: 36px;
}

.select2-container {
    width: 100% !important;
}

.select2-container .select2-selection--single {
    height: 46px !important;
    border-radius: 10px !important;
    border: 1px solid #cbd5e1 !important;
    background: #fff !important;
}

.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 44px !important;
    padding-left: 12px !important;
    color: #212529 !important;
}

.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 44px !important;
    right: 8px !important;
}

.select2-dropdown {
    border: 1px solid #cbd5e1 !important;
    border-radius: 10px !important;
    overflow: hidden;
}

.select2-search--dropdown {
    padding: 8px !important;
}

.select2-search--dropdown .select2-search__field {
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    padding: 8px 10px !important;
}

.select2-results__option {
    padding: 8px 12px !important;
}

.meta-chip {
    display: inline-block;
    background: #fef3c7;
    color: #92400e;
    font-size: 12px;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 999px;
}

.form-hint {
    font-size: 12px;
    color: #64748b;
    margin-top: 6px;
}

.action-wrap {
    margin-top: 28px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid #e5e7eb;
    padding-top: 18px;
    flex-wrap: wrap;
}

.alert {
    border-radius: 12px;
}

html[data-theme='dark'] .card-custom {
    background: linear-gradient(180deg, #111827 0%, #1f2937 100%) !important;
    border: 1px solid #374151 !important;
    border-top: 4px solid #facc15 !important;
    box-shadow: 0 16px 38px rgba(0,0,0,0.32) !important;
}

html[data-theme='dark'] .section-block {
    background: rgba(15, 23, 42, 0.55) !important;
    border: 1px solid #334155 !important;
}

html[data-theme='dark'] .form-section-title,
html[data-theme='dark'] .form-label {
    color: #f8fafc !important;
}

html[data-theme='dark'] .form-section-subtitle,
html[data-theme='dark'] .form-hint {
    color: #94a3b8 !important;
}

html[data-theme='dark'] .form-control {
    background: #020617 !important;
    color: #f8fafc !important;
    border: 1px solid #334155 !important;
}

html[data-theme='dark'] .form-control:focus {
    background: #020617 !important;
    color: #f8fafc !important;
    border-color: #facc15 !important;
    box-shadow: 0 0 0 0.2rem rgba(250,204,21,0.14) !important;
}

html[data-theme='dark'] .form-control::placeholder {
    color: #94a3b8 !important;
}

html[data-theme='dark'] .select2-container .select2-selection--single {
    background: #020617 !important;
    border: 1px solid #334155 !important;
}

html[data-theme='dark'] .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #f8fafc !important;
}

html[data-theme='dark'] .select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #cbd5e1 transparent transparent transparent !important;
}

html[data-theme='dark'] .select2-dropdown {
    background: #0f172a !important;
    border: 1px solid #334155 !important;
}

html[data-theme='dark'] .select2-search--dropdown {
    background: #0f172a !important;
}

html[data-theme='dark'] .select2-search--dropdown .select2-search__field {
    background: #020617 !important;
    color: #f8fafc !important;
    border: 1px solid #334155 !important;
}

html[data-theme='dark'] .select2-results__option {
    background: #0f172a !important;
    color: #e2e8f0 !important;
}

html[data-theme='dark'] .select2-results__option--highlighted.select2-results__option--selectable {
    background: #1d4ed8 !important;
    color: #ffffff !important;
}

html[data-theme='dark'] .select2-results__option--selected {
    background: #1e293b !important;
    color: #facc15 !important;
}

html[data-theme='dark'] .meta-chip {
    background: rgba(250,204,21,0.16) !important;
    color: #fde68a !important;
}

html[data-theme='dark'] .btn-light.border {
    background: #111827 !important;
    border-color: #475569 !important;
    color: #f8fafc !important;
}

html[data-theme='dark'] .btn-warning {
    background: linear-gradient(135deg, #facc15 0%, #f59e0b 100%) !important;
    border-color: #f59e0b !important;
    color: #111827 !important;
    font-weight: 700 !important;
}

html[data-theme='dark'] .btn-warning:hover,
html[data-theme='dark'] .btn-warning:focus {
    background: linear-gradient(135deg, #fde047 0%, #facc15 100%) !important;
    border-color: #facc15 !important;
    color: #000 !important;
}

html[data-theme='dark'] .alert-success {
    background: rgba(34,197,94,0.12) !important;
    color: #bbf7d0 !important;
    border-color: rgba(34,197,94,0.22) !important;
}

html[data-theme='dark'] .alert-danger {
    background: rgba(239,68,68,0.12) !important;
    color: #fecaca !important;
    border-color: rgba(239,68,68,0.22) !important;
}

html[data-theme='dark'] .btn-close {
    filter: invert(1) grayscale(100%);
}

@media (max-width: 991px) {
    .card-custom {
        padding: 18px 15px;
        border-radius: 14px;
    }
    .section-block {
        padding: 16px;
    }
    .action-wrap {
        flex-direction: column;
    }
    .action-wrap a,
    .action-wrap button {
        width: 100%;
    }
}
";

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-<?php echo $msg_type; ?> alert-dismissible fade show shadow-sm" role="alert">
        <i class="fas <?php echo $msg_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-xl-11 col-lg-12">
        <div class="card-custom">

            <div class="mb-4">
                <div class="form-section-title">Employee Information</div>
                <div class="form-section-subtitle">Update employee profile, joining info, category, and status</div>
                <span class="meta-chip">
                    <i class="fas fa-id-badge me-1"></i>
                    Record ID: <?php echo (int)$emp_data['id']; ?>
                </span>
            </div>

            <form action="" method="POST">
                <div class="section-block mb-4">
                    <div class="row g-4">

                        <div class="col-md-6">
                            <label class="form-label">Employee ID <span class="text-danger">*</span></label>
                            <input type="text" name="employee_id" class="form-control" required value="<?php echo htmlspecialchars($emp_data['employee_id']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="employee_name" class="form-control" required value="<?php echo htmlspecialchars($emp_data['employee_name']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Designation <span class="text-danger">*</span></label>
                            <input type="text" name="designation" class="form-control" required value="<?php echo htmlspecialchars($emp_data['designation']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Department <span class="text-danger">*</span></label>
                            <select name="department" class="form-control select2-search" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo ($emp_data['department'] === $dept) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label">Location <span class="text-danger">*</span></label>
                            <select name="location" class="form-control select2-tags" required>
                                <option value="">Select or Type Location</option>
                                <?php foreach ($locations as $loc): ?>
                                    <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo ($emp_data['location'] === $loc) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($emp_data['email']); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" required value="<?php echo htmlspecialchars($emp_data['phone']); ?>">
                        </div>
                    </div>
                </div>

                <div class="section-block">
                    <div class="row g-4">
                        <div class="col-md-4">
                            <label class="form-label">Date of Joining</label>
                            <input type="date" name="date_of_joining" class="form-control"
                                   value="<?php echo (!empty($emp_data['date_of_joining']) && $emp_data['date_of_joining'] !== '0000-00-00') ? htmlspecialchars($emp_data['date_of_joining']) : ''; ?>">
                            <div class="form-hint">Use YYYY-MM-DD format from the date picker.</div>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Employee Category</label>
                            <select name="employee_category" class="form-control" required>
                                <option value="">Select Category</option>
                                <option value="Management" <?php echo (($emp_data['employee_category'] ?? '') === 'Management') ? 'selected' : ''; ?>>Management</option>
                                <option value="Non-Management" <?php echo (($emp_data['employee_category'] ?? '') === 'Non-Management') ? 'selected' : ''; ?>>Non-Management</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Employee Status</label>
                            <select name="employee_status" class="form-control" required>
                                <option value="">Select Status</option>
                                <option value="Probation" <?php echo (($emp_data['employee_status'] ?? '') === 'Probation') ? 'selected' : ''; ?>>Probation</option>
                                <option value="Contractual" <?php echo (($emp_data['employee_status'] ?? '') === 'Contractual') ? 'selected' : ''; ?>>Contractual</option>
                                <option value="Permanent" <?php echo (($emp_data['employee_status'] ?? '') === 'Permanent') ? 'selected' : ''; ?>>Permanent</option>
                                <option value="Temporary" <?php echo (($emp_data['employee_status'] ?? '') === 'Temporary') ? 'selected' : ''; ?>>Temporary</option>
                                <option value="Intern" <?php echo (($emp_data['employee_status'] ?? '') === 'Intern') ? 'selected' : ''; ?>>Intern</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="action-wrap">
                    <a href="employee_list.php" class="btn btn-light border px-4 fw-bold">
                        <i class="fas fa-times me-1"></i> Cancel
                    </a>
                    <button type="submit" name="update_employee" class="btn btn-warning px-5 fw-bold text-dark">
                        <i class="fas fa-save me-1"></i> Update Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.select2-search').select2({
        placeholder: 'Search Department',
        width: '100%',
        allowClear: false
    });

    $('.select2-tags').select2({
        placeholder: 'Search or Type Location',
        tags: true,
        width: '100%',
        allowClear: false
    });
});
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>