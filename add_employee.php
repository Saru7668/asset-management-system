<?php
session_start();
require_once('db.php');
require_once('header.php');

// Security Check: Only Admin, SuperAdmin, HR, Staff can access
$role = $_SESSION['UserRole'] ?? '';
if (!in_array($role, ['SuperAdmin', 'HR', 'Staff', 'admin'])) {
    header("Location: index.php");
    exit;
}

$message = '';
$msg_type = '';

// ==========================================
// Fetch Unique Locations from Database
// ==========================================
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_employee'])) {
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

    // Check if ID already exists
    $check_q = mysqli_query($conn, "SELECT id FROM employees WHERE employee_id = '$emp_id'");
    if ($check_q && mysqli_num_rows($check_q) > 0) {
        $message = "Error: Employee ID '$emp_id' already exists!";
        $msg_type = "danger";
    } else {
        $user = $_SESSION['UserName'] ?? 'Unknown';

        if ($date_of_joining === '' || $date_of_joining === '0000-00-00') {
            $date_of_joining_sql = "NULL";
        } else {
            $date_of_joining_sql = "'$date_of_joining'";
        }

        $sql = "INSERT INTO employees (
                    employee_id,
                    employee_name,
                    designation,
                    department,
                    location,
                    email,
                    phone,
                    date_of_joining,
                    employee_category,
                    employee_status,
                    created_by,
                    created_at
                ) 
                VALUES (
                    '$emp_id',
                    '$emp_name',
                    '$designation',
                    '$department',
                    '$location',
                    '$email',
                    '$phone',
                    $date_of_joining_sql,
                    '$employee_category',
                    '$employee_status',
                    '$user',
                    NOW()
                )";
        
        if (mysqli_query($conn, $sql)) {
            $message = "Employee '$emp_name' added successfully!";
            $msg_type = "success";
        } else {
            $message = "Database Error: " . mysqli_error($conn);
            $msg_type = "danger";
        }
    }
}

$page_title = 'Add Employee - SCL AMS';
$page_header_icon = 'fas fa-user-plus';
$page_header_title = 'Add New Employee';
$page_header_subtitle = 'Create employee records with department, location, joining info and status details';
$page_top_title = 'Add New Employee';
$page_top_actions = '
<a href="employee_list.php" class="btn btn-outline-secondary">
    <i class="fas fa-arrow-left me-1"></i> Back to List
</a>';
$page_container_class = 'dashboard-container';

$extra_head = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
';

$extra_css = "
.card-custom {
    background: #ffffff;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
    padding: 28px;
    border: 1px solid #e2e8f0;
}
.page-topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 22px;
}
.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #0b2545;
    margin: 0;
}
.form-label {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 8px;
    display: block;
}
.form-control,
.select2-container .select2-selection--single {
    min-height: 44px;
}
.form-control {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 10px 12px;
    background: #fff;
    color: #0f172a;
    width: 100%;
}
.form-control::placeholder {
    color: #94a3b8;
}
.form-control:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59,130,246,0.15);
    outline: none;
}
.btn-custom {
    background-color: #0b2545;
    color: white;
    transition: 0.3s;
    border-radius: 8px;
    padding: 10px 18px;
    border: none;
}
.btn-custom:hover {
    background-color: #1e3a8a;
    color: white;
    transform: translateY(-2px);
}
.select2-container .select2-selection--single {
    height: 44px !important;
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    padding-top: 2px;
    background: #fff !important;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 38px !important;
    padding-left: 12px !important;
    color: #212529 !important;
}
.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #94a3b8 !important;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 42px !important;
    right: 8px !important;
}
.select2-container {
    width: 100% !important;
}
.select2-dropdown {
    border: 1px solid #cbd5e1 !important;
    border-radius: 8px !important;
    overflow: hidden;
}
.select2-search--dropdown {
    padding: 8px !important;
}
.select2-search--dropdown .select2-search__field {
    border: 1px solid #cbd5e1 !important;
    border-radius: 6px !important;
}
.select2-results__option--highlighted[aria-selected] {
    background-color: #1e3a8a !important;
    color: #fff !important;
}
.form-hint {
    font-size: 12px;
    color: #64748b;
    margin-top: 6px;
}

/* Dark mode override */
html[data-theme='dark'] .page-title {
    color: #f8fafc !important;
}

html[data-theme='dark'] .card-custom {
    background: #1f2937 !important;
    border: 1px solid #374151 !important;
    border-top: 4px solid #facc15 !important;
    box-shadow: 0 14px 28px rgba(0,0,0,0.28) !important;
}

html[data-theme='dark'] .form-label {
    color: #e5e7eb !important;
}

html[data-theme='dark'] .form-control {
    background: #111827 !important;
    color: #f9fafb !important;
    border: 1px solid #4b5563 !important;
}

html[data-theme='dark'] .form-control::placeholder {
    color: #9ca3af !important;
}

html[data-theme='dark'] .form-control:focus {
    border-color: #facc15 !important;
    box-shadow: 0 0 0 0.2rem rgba(250,204,21,0.16) !important;
}

html[data-theme='dark'] .text-muted,
html[data-theme='dark'] small.text-muted,
html[data-theme='dark'] .form-hint {
    color: #9ca3af !important;
}

html[data-theme='dark'] .btn-custom {
    background: linear-gradient(135deg, #facc15 0%, #eab308 50%, #d97706 100%) !important;
    color: #111827 !important;
}

html[data-theme='dark'] .btn-custom:hover {
    background: linear-gradient(135deg, #fde047 0%, #f59e0b 100%) !important;
    color: #111827 !important;
}

html[data-theme='dark'] .btn-light {
    background: #374151 !important;
    border-color: #4b5563 !important;
    color: #f3f4f6 !important;
}

html[data-theme='dark'] .btn-outline-secondary {
    border-color: #6b7280 !important;
    color: #e5e7eb !important;
}

html[data-theme='dark'] .btn-outline-secondary:hover {
    background: #374151 !important;
    color: #fff !important;
    border-color: #9ca3af !important;
}

html[data-theme='dark'] .alert-success {
    background: #052e16 !important;
    color: #bbf7d0 !important;
    border-color: #166534 !important;
}

html[data-theme='dark'] .alert-danger {
    background: #3f0d12 !important;
    color: #fecaca !important;
    border-color: #991b1b !important;
}

html[data-theme='dark'] .btn-close {
    filter: invert(1) grayscale(100%);
}

html[data-theme='dark'] .select2-container .select2-selection--single {
    background: #111827 !important;
    border: 1px solid #4b5563 !important;
    color: #f9fafb !important;
}

html[data-theme='dark'] .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #f9fafb !important;
}

html[data-theme='dark'] .select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #9ca3af !important;
}

html[data-theme='dark'] .select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #d1d5db transparent transparent transparent !important;
}

html[data-theme='dark'] .select2-dropdown {
    background: #1f2937 !important;
    border: 1px solid #4b5563 !important;
    color: #f9fafb !important;
}

html[data-theme='dark'] .select2-search--dropdown {
    background: #1f2937 !important;
}

html[data-theme='dark'] .select2-search--dropdown .select2-search__field {
    background: #111827 !important;
    color: #f9fafb !important;
    border: 1px solid #4b5563 !important;
}

html[data-theme='dark'] .select2-results__option {
    background: #1f2937 !important;
    color: #f9fafb !important;
}

html[data-theme='dark'] .select2-results__option[aria-selected=true] {
    background: #374151 !important;
    color: #f9fafb !important;
}

html[data-theme='dark'] .select2-results__option--highlighted[aria-selected] {
    background: #f59e0b !important;
    color: #111827 !important;
}

html[data-theme='dark'] .select2-container--default.select2-container--open .select2-selection--single {
    border-color: #facc15 !important;
}

@media (max-width: 991px) {
    .page-topbar {
        flex-direction: column;
        align-items: stretch;
    }
    .page-title {
        font-size: 24px;
    }
    .card-custom {
        padding: 18px 15px;
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
    <div class="col-lg-10">
        <div class="card-custom">
            <form action="" method="POST">
                <div class="row g-4">
                    
                    <div class="col-md-6">
                        <label class="form-label">Employee ID <span class="text-danger">*</span></label>
                        <input type="text" name="employee_id" class="form-control border-secondary" placeholder="e.g. 00255721" required value="<?php echo htmlspecialchars($_POST['employee_id'] ?? ''); ?>">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="employee_name" class="form-control border-secondary" placeholder="e.g. John Doe" required value="<?php echo htmlspecialchars($_POST['employee_name'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Designation <span class="text-danger">*</span></label>
                        <input type="text" name="designation" class="form-control border-secondary" placeholder="e.g. Software Engineer" required value="<?php echo htmlspecialchars($_POST['designation'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Department <span class="text-danger">*</span></label>
                        <select name="department" class="form-control select2-search" required>
                            <option value=""></option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo (($_POST['department'] ?? '') === $dept) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label">Location <span class="text-danger">*</span></label>
                        <select name="location" class="form-control select2-tags" required>
                            <option value=""></option>
                            <?php foreach ($locations as $loc): ?>
                                <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo (($_POST['location'] ?? '') === $loc) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($loc); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">You can select from the list or type a new location and press Enter.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control border-secondary" placeholder="e.g. john@sheltechceramics.com" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control border-secondary" placeholder="e.g. 01712345678" required value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Date of Joining</label>
                        <input type="date" name="date_of_joining" class="form-control" value="<?php echo htmlspecialchars($_POST['date_of_joining'] ?? ''); ?>">
                        <div class="form-hint">Use YYYY-MM-DD format from the date picker.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Employee Category</label>
                        <select name="employee_category" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Management" <?php echo (($_POST['employee_category'] ?? '') === 'Management') ? 'selected' : ''; ?>>Management</option>
                            <option value="Non-Management" <?php echo (($_POST['employee_category'] ?? '') === 'Non-Management') ? 'selected' : ''; ?>>Non-Management</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Employee Status</label>
                        <select name="employee_status" class="form-control" required>
                            <option value="">Select Status</option>
                            <option value="Probation" <?php echo (($_POST['employee_status'] ?? '') === 'Probation') ? 'selected' : ''; ?>>Probation</option>
                            <option value="Contractual" <?php echo (($_POST['employee_status'] ?? '') === 'Contractual') ? 'selected' : ''; ?>>Contractual</option>
                            <option value="Permanent" <?php echo (($_POST['employee_status'] ?? '') === 'Permanent') ? 'selected' : ''; ?>>Permanent</option>
                            <option value="Temporary" <?php echo (($_POST['employee_status'] ?? '') === 'Temporary') ? 'selected' : ''; ?>>Temporary</option>
                            <option value="Intern" <?php echo (($_POST['employee_status'] ?? '') === 'Intern') ? 'selected' : ''; ?>>Intern</option>
                        </select>
                    </div>

                </div>

                <div class="mt-5 text-end border-top pt-3">
                    <button type="reset" class="btn btn-light border me-2">Clear Form</button>
                    <button type="submit" name="add_employee" class="btn btn-custom px-4 fw-bold">
                        <i class="fas fa-save me-1"></i> Save Employee
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
        placeholder: 'Search & Select Department',
        allowClear: true,
        width: '100%'
    });

    $('.select2-tags').select2({
        placeholder: 'Search or Type New Location',
        tags: true,
        allowClear: true,
        width: '100%'
    });
});
</script>

<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
?>