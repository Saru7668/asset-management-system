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

$username = $_SESSION['UserName'];
$current_role = $_SESSION['UserRole'] ?? 'user';
$success_msg = '';
$error_msg = '';

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function format_emp_id($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    return str_pad($value, 8, '0', STR_PAD_LEFT);
}

// NEW FUNCTION: Generates REQ-YYYYMMDD-DEPT-01
function generate_request_ref($conn, $department_name) {
    $today_date = date('Ymd'); 

    $dept_codes = [
      'ICT' => 'ICT',
      'HR & Admin' => 'HRAD',
      'Accounts & Finance' => 'ACF',
      'Sales & Marketing' => 'SM',
      'Supply Chain' => 'SC',
      'Production' => 'PRD',
      'Civil Engineering' => 'CE',
      'Electrical' => 'ELE',
      'Mechanical' => 'MECH',
      'Glazeline' => 'GLZ',
      'Laboratory & Quality Control' => 'LQC',
      'Power & Generation' => 'PG',
      'Press' => 'PRS',
      'Sorting & Packing' => 'SP',
      'Squaring & Polishing' => 'SQP',
      'VAT' => 'VAT',
      'Kiln' => 'KLN',
      'Inventory' => 'INV',
      'Audit' => 'AUD',
      'Brand' => 'BRD',
    ];

    $dept_short = $dept_codes[$department_name] ?? strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $department_name), 0, 3));
    if (empty($dept_short)) {
        $dept_short = 'GEN';
    }

    $search_prefix = "REQ-{$today_date}-{$dept_short}-%";

    $seq_stmt = mysqli_prepare($conn, "SELECT ref_no FROM hardware_requests WHERE ref_no LIKE ? ORDER BY id DESC LIMIT 1");
    mysqli_stmt_bind_param($seq_stmt, "s", $search_prefix);
    mysqli_stmt_execute($seq_stmt);
    $seq_res = mysqli_stmt_get_result($seq_stmt);

    $next_seq = 1;

    if ($row = mysqli_fetch_assoc($seq_res)) {
        $parts = explode('-', $row['ref_no']);
        $last_seq = (int)end($parts);
        $next_seq = $last_seq + 1;
    }

    return sprintf("REQ-%s-%s-%02d", $today_date, $dept_short, $next_seq);
}

$user_stmt = mysqli_prepare($conn, "SELECT id, username, full_name, nid_company_id, designation, department, email, phone, employee_category, employment_status, date_of_joining FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, "s", $username);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$logged_user = mysqli_fetch_assoc($user_result);

if (!$logged_user) {
    die('Logged in user not found.');
}

$self_place_of_posting = '';
if (!empty($logged_user['nid_company_id'])) {
    $emp_lookup_id = str_pad(trim((string)$logged_user['nid_company_id']), 8, '0', STR_PAD_LEFT);
    $emp_lookup_stmt = mysqli_prepare($conn, "SELECT location FROM employees WHERE employee_id = ? LIMIT 1");
    mysqli_stmt_bind_param($emp_lookup_stmt, "s", $emp_lookup_id);
    mysqli_stmt_execute($emp_lookup_stmt);
    $emp_lookup_result = mysqli_stmt_get_result($emp_lookup_stmt);
    $emp_lookup_row = mysqli_fetch_assoc($emp_lookup_result);
    if ($emp_lookup_row && !empty($emp_lookup_row['location'])) {
        $self_place_of_posting = $emp_lookup_row['location'];
    }
}

$logged_user_department = trim($logged_user['department'] ?? '');
$is_super_admin = (strtolower(trim($current_role)) === 'superadmin');

$request_for_users = [];
if ($is_super_admin) {
    $list_sql = "SELECT id, employee_id, employee_name, location, email, designation, department, phone, date_of_joining, employee_category, employee_status FROM employees ORDER BY department ASC, employee_name ASC";
    $stmt_emp_list = mysqli_prepare($conn, $list_sql);
} else {
    $list_sql = "SELECT id, employee_id, employee_name, location, email, designation, department, phone, date_of_joining, employee_category, employee_status FROM employees WHERE department = ? ORDER BY employee_name ASC";
    $stmt_emp_list = mysqli_prepare($conn, $list_sql);
    mysqli_stmt_bind_param($stmt_emp_list, "s", $logged_user_department);
}
mysqli_stmt_execute($stmt_emp_list);
$list_result = mysqli_stmt_get_result($stmt_emp_list);
if ($list_result) {
    while ($row = mysqli_fetch_assoc($list_result)) {
        $request_for_users[] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_request') {

    $request_type = $_POST['request_type'] ?? 'regular';
    $requested_for_type = $_POST['requested_for_type'] ?? 'self';

    $selected_user_id = (int)($_POST['requested_for_user_id'] ?? 0);
    $requested_for_name = trim($_POST['requested_for_name'] ?? '');
    $requested_for_emp_id = trim($_POST['requested_for_emp_id'] ?? '');
    $requested_for_designation = trim($_POST['requested_for_designation'] ?? '');
    $requested_for_department = trim($_POST['requested_for_department'] ?? '');
    $requested_for_category = trim($_POST['requested_for_category'] ?? '');
    $requested_for_status = trim($_POST['requested_for_status'] ?? '');
    $requested_for_joining_date = trim($_POST['requested_for_joining_date'] ?? '');
    $requested_for_place_posting = trim($_POST['requested_for_place_posting'] ?? '');

    $date_of_request = trim($_POST['date_of_request'] ?? date('Y-m-d'));
    $purpose_text = trim($_POST['purpose_text'] ?? '');
    $reason_details = trim($_POST['reason_details'] ?? '');

    $request_for_laptop = isset($_POST['request_for_laptop']) ? 1 : 0;
    $request_for_desktop = isset($_POST['request_for_desktop']) ? 1 : 0;
    $request_for_scanner = isset($_POST['request_for_scanner']) ? 1 : 0;
    $request_for_monitor = isset($_POST['request_for_monitor']) ? 1 : 0;
    $request_for_ram = isset($_POST['request_for_ram']) ? 1 : 0;
    $request_for_ssd = isset($_POST['request_for_ssd']) ? 1 : 0;
    $request_for_hdd = isset($_POST['request_for_hdd']) ? 1 : 0;
    $request_for_mouse_wired = isset($_POST['request_for_mouse_wired']) ? 1 : 0;
    $request_for_mouse_wireless = isset($_POST['request_for_mouse_wireless']) ? 1 : 0;
    $request_for_keyboard = isset($_POST['request_for_keyboard']) ? 1 : 0;
    $request_for_printer = isset($_POST['request_for_printer']) ? 1 : 0;
    $request_for_other = isset($_POST['request_for_other']) ? 1 : 0;
    $request_for_other_text = trim($_POST['request_for_other_text'] ?? '');

    $reason_allocation = isset($_POST['reason_allocation']) ? 1 : 0;
    $reason_replacement = isset($_POST['reason_replacement']) ? 1 : 0;
    $reason_exchange = isset($_POST['reason_exchange']) ? 1 : 0;
    $reason_upgradation = isset($_POST['reason_upgradation']) ? 1 : 0;
    $reason_maintenance_repair = isset($_POST['reason_maintenance_repair']) ? 1 : 0;
    $reason_damage = isset($_POST['reason_damage']) ? 1 : 0;
    $reason_other = isset($_POST['reason_other']) ? 1 : 0;
    $reason_other_text = trim($_POST['reason_other_text'] ?? '');

    $signature_mode = $_POST['signature_mode'] ?? 'draw';
    $signature_draw_data = trim($_POST['signature_draw_data'] ?? '');
    $signature_file_path = null;

    if ($requested_for_type === 'self') {
        $selected_user_id = (int)$logged_user['id'];
        $requested_for_name = $logged_user['full_name'] ?: $logged_user['username'];
        $requested_for_emp_id = format_emp_id($logged_user['nid_company_id'] ?? '');
        $requested_for_designation = $logged_user['designation'] ?? '';
        $requested_for_department = $logged_user['department'] ?? '';
        $requested_for_category = $logged_user['employee_category'] ?? 'N/A';
        $requested_for_status = $logged_user['employment_status'] ?? 'N/A';
        $requested_for_joining_date = !empty($logged_user['date_of_joining']) ? $logged_user['date_of_joining'] : null;
        $requested_for_place_posting = $self_place_of_posting;
    } else {
        if ($is_super_admin) {
            $verify_sql = "SELECT id, employee_id, employee_name, location, designation, department, phone, email FROM employees WHERE id = ? LIMIT 1";
            $verify_stmt = mysqli_prepare($conn, $verify_sql);
            mysqli_stmt_bind_param($verify_stmt, "i", $selected_user_id);
        } else {
            $verify_sql = "SELECT id, employee_id, employee_name, location, designation, department, phone, email FROM employees WHERE id = ? AND department = ? LIMIT 1";
            $verify_stmt = mysqli_prepare($conn, $verify_sql);
            mysqli_stmt_bind_param($verify_stmt, "is", $selected_user_id, $logged_user_department);
        }
        mysqli_stmt_execute($verify_stmt);
        $verify_result = mysqli_stmt_get_result($verify_stmt);
        $target_user = mysqli_fetch_assoc($verify_result);

        if ($target_user) {
            $requested_for_name = $target_user['employee_name'] ?? $requested_for_name;
            $requested_for_emp_id = format_emp_id($target_user['employee_id'] ?? '') ?: $requested_for_emp_id;
            $requested_for_designation = $target_user['designation'] ?? $requested_for_designation;
            $requested_for_department = $target_user['department'] ?? $requested_for_department;
            $requested_for_category = 'N/A';
            $requested_for_status = 'N/A';
            $requested_for_joining_date = null;
            $requested_for_place_posting = $target_user['location'] ?? '';
        } else {
            $error_msg = "You are not allowed to request for this employee.";
        }
    }

    $at_least_one_item = ($request_for_laptop || $request_for_desktop || $request_for_scanner || $request_for_monitor || $request_for_ram || $request_for_ssd || $request_for_hdd || $request_for_mouse_wired || $request_for_mouse_wireless || $request_for_keyboard || $request_for_printer || $request_for_other);
    $at_least_one_reason = ($reason_allocation || $reason_replacement || $reason_exchange || $reason_upgradation || $reason_maintenance_repair || $reason_damage || $reason_other);

    if (!$requested_for_name) {
        $error_msg = "Requested employee name is required.";
    } elseif (!$requested_for_department) {
        $error_msg = "Department is required.";
    } elseif (!$at_least_one_item) {
        $error_msg = "Please select at least one hardware/item.";
    } elseif (!$at_least_one_reason) {
        $error_msg = "Please select at least one request reason.";
    } elseif (!$purpose_text) {
        $error_msg = "Please mention purpose/work functions.";
    } elseif ($request_for_other && !$request_for_other_text) {
        $error_msg = "Please write 'other' accessories item.";
    } elseif ($reason_other && !$reason_other_text) {
        $error_msg = "Please write 'other' reason.";
    } elseif ($signature_mode === 'draw' && !$signature_draw_data) {
        $error_msg = "Please draw your signature.";
    }

    if (!$error_msg && $signature_mode === 'upload') {
        if (isset($_FILES['signature_file']) && $_FILES['signature_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $allowed_mime = ['image/png', 'image/jpeg', 'image/jpg', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['signature_file']['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_mime)) {
                $error_msg = "Invalid signature file type. Only PNG/JPG/WEBP allowed.";
            } elseif ($_FILES['signature_file']['size'] > 2 * 1024 * 1024) {
                $error_msg = "Signature file too large. Max 2MB.";
            } else {
                $ext = pathinfo($_FILES['signature_file']['name'], PATHINFO_EXTENSION);
                $safe_name = 'sig_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . strtolower($ext);
                $upload_dir = __DIR__ . '/uploads/signatures/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $dest = $upload_dir . $safe_name;
                if (move_uploaded_file($_FILES['signature_file']['tmp_name'], $dest)) {
                    $signature_file_path = 'uploads/signatures/' . $safe_name;
                } else {
                    $error_msg = "Failed to upload signature file.";
                }
            }
        }
    }

    if (!$error_msg) {
        $ref_no = generate_request_ref($conn, $requested_for_department);
        
        $workflow_status = 'pending_department_head';
        $current_stage = 'department_head';

        $insert_sql = "INSERT INTO hardware_requests (
            ref_no, request_type, requested_for_type, requested_for_user_id,
            requested_for_name, requested_for_emp_id, requested_for_designation, requested_for_department,
            requested_for_category, requested_for_status, requested_for_joining_date, requested_for_place_posting, date_of_request,
            request_for_laptop, request_for_desktop, request_for_scanner, request_for_monitor, request_for_ram, request_for_ssd,
            request_for_hdd, request_for_mouse_wired, request_for_mouse_wireless, request_for_keyboard, request_for_printer,
            request_for_other, request_for_other_text, reason_allocation, reason_replacement, reason_exchange, reason_upgradation,
            reason_maintenance_repair, reason_damage, reason_other, reason_other_text, purpose_text, reason_details,
            requester_user_id, requester_emp_id, requester_name, requester_designation, requester_department, requester_email, requester_phone,
            requester_signature_type, requester_signature_file, requester_signature_draw, workflow_status, current_stage
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?
        )";

        $stmt = mysqli_prepare($conn, $insert_sql);

        $requester_user_id = (int)$logged_user['id'];
        $requester_emp_id = format_emp_id($logged_user['nid_company_id'] ?? '');
        $requester_name = $logged_user['full_name'] ?: $logged_user['username'];
        $requester_designation = $logged_user['designation'] ?? '';
        $requester_department = $logged_user['department'] ?? '';
        $requester_email = $logged_user['email'] ?? '';
        $requester_phone = $logged_user['phone'] ?? '';
        $requester_signature_type = $signature_mode;
        $requester_signature_file = $signature_file_path;
        $requester_signature_draw = ($signature_mode === 'draw') ? $signature_draw_data : null;

        if ($requested_for_joining_date === '') {
            $requested_for_joining_date = null;
        }

        $bind_types = "sssisssssssssiiiiiiiiiiiiisiiiiiiississsssssssss";
        mysqli_stmt_bind_param(
            $stmt,
            $bind_types,
            $ref_no, $request_type, $requested_for_type, $selected_user_id,
            $requested_for_name, $requested_for_emp_id, $requested_for_designation, $requested_for_department,
            $requested_for_category, $requested_for_status, $requested_for_joining_date, $requested_for_place_posting, $date_of_request,
            $request_for_laptop, $request_for_desktop, $request_for_scanner, $request_for_monitor, $request_for_ram, $request_for_ssd,
            $request_for_hdd, $request_for_mouse_wired, $request_for_mouse_wireless, $request_for_keyboard, $request_for_printer,
            $request_for_other, $request_for_other_text, $reason_allocation, $reason_replacement, $reason_exchange, $reason_upgradation,
            $reason_maintenance_repair, $reason_damage, $reason_other, $reason_other_text, $purpose_text, $reason_details,
            $requester_user_id, $requester_emp_id, $requester_name, $requester_designation, $requester_department, $requester_email, $requester_phone,
            $requester_signature_type, $requester_signature_file, $requester_signature_draw, $workflow_status, $current_stage
        );

        if (mysqli_stmt_execute($stmt)) {
            $success_msg = "Request submitted successfully! Reference No: " . $ref_no;
        } else {
            $error_msg = "Failed to submit request. " . mysqli_error($conn);
        }
    }
}

$page_title = 'Submit Request - SCL AMS';
$page_header_icon = 'fas fa-file-signature';
$page_header_title = 'Submit ICT Hardware Request';
$page_header_subtitle = 'Request laptop, desktop, accessories, upgrades, repair, replacement and more';
$page_top_title = 'Submit Request';
$page_container_class = 'dashboard-container-xl';

$body_extra_top = '
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
';
if ($success_msg) {
    $body_extra_top .= '<script>Swal.fire({icon:"success", title:"Success!", text:"' . addslashes($success_msg) . '", confirmButtonColor:"#0b2545"});</script>';
}
if ($error_msg) {
    $body_extra_top .= '<script>Swal.fire({icon:"error", title:"Oops...", text:"' . addslashes($error_msg) . '", confirmButtonColor:"#dc2626"});</script>';
}

$extra_css = '
/* --- TOGGLE SWITCH STYLING FOR CHOICE BOXES --- */
.choice-box .form-check {
    margin-bottom: 10px;
    padding: 10px 15px; 
    border-radius: 8px;
    border: 1px solid var(--border-color, #cbd5e1);
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    background: rgba(255,255,255,0.03); 
}
.choice-box .form-check:hover {
    background-color: rgba(11, 37, 69, 0.05);
}

.choice-box .form-check-input {
    appearance: none !important;
    -webkit-appearance: none !important;
    width: 44px !important;
    height: 24px !important;
    background-color: #94a3b8 !important; /* Darker gray for light mode visibility */
    background-image: none !important; /* Force remove bootstrap checkbox tick */
    border-radius: 30px !important;
    position: relative;
    cursor: pointer;
    outline: none !important;
    margin-top: 0;
    margin-right: 12px;
    transition: background-color 0.3s ease, box-shadow 0.3s ease;
    flex-shrink: 0;
    border: 2px solid #94a3b8 !important;
}

.choice-box .form-check-input::after {
    content: "";
    position: absolute;
    top: 1px;
    left: 1px;
    width: 18px;
    height: 18px;
    background-color: #ffffff;
    border-radius: 50%;
    transition: transform 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
}

.choice-box .form-check-input:checked {
    background-color: #059669 !important; /* Emerald Green Glowing */
    border-color: #059669 !important;
    box-shadow: 0 0 12px rgba(5, 150, 105, 0.4) !important;
}

.choice-box .form-check-input:checked::after {
    transform: translateX(20px);
}

.choice-box .form-check-label {
    cursor: pointer;
    width: 100%;
    margin-bottom: 0;
    user-select: none;
    font-weight: 500;
    color: var(--text-heading);
}

.choice-box .form-check:has(.form-check-input:checked) {
    background-color: rgba(5, 150, 105, 0.05);
    border-color: #059669; 
}

/* Dark mode support for toggle switches */
html[data-theme="dark"] .choice-box .form-check {
    border-color: #334155;
    background: rgba(255, 255, 255, 0.02);
}
html[data-theme="dark"] .choice-box .form-check:hover {
    background-color: rgba(255, 255, 255, 0.05);
}
html[data-theme="dark"] .choice-box .form-check-input {
    background-color: #334155 !important;
    border-color: #334155 !important;
}
html[data-theme="dark"] .choice-box .form-check-input:checked {
    background-color: #10b981 !important;
    border-color: #10b981 !important;
    box-shadow: 0 0 15px rgba(16, 185, 129, 0.3) !important;
}
html[data-theme="dark"] .choice-box .form-check:has(.form-check-input:checked) {
    background-color: rgba(16, 185, 129, 0.1); 
    border-color: #10b981; 
}
html[data-theme="dark"] .choice-box .form-check-label {
    color: #f8fafc;
}

/* Rest of your CSS */
.request-card {
    background: var(--bg-card);
    border-radius: 14px;
    box-shadow: var(--shadow-main);
    border: 1px solid var(--border-color);
    margin-bottom: 22px;
    overflow: hidden;
}
.request-card-header {
    background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
    color: #fff;
    padding: 16px 20px;
    font-size: 20px;
    font-weight: 700;
}
.request-card-body {
    padding: 22px;
}
.request-grid-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 20px;
}
.request-grid-three {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px 20px;
}
.label-title {
    font-weight: 700;
    margin-bottom: 6px;
    color: var(--text-heading);
}
.form-section-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-heading);
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--border-color);
}
.choice-box {
    border: 1px solid var(--border-color);
    border-radius: 12px;
    padding: 14px;
    background: rgba(255,255,255,0.03);
    min-height: 100%;
}
.signature-pad-wrap {
    border: 1px dashed #94a3b8;
    border-radius: 12px;
    padding: 12px;
    background: rgba(255,255,255,0.02);
}
.signature-canvas {
    width: 100%;
    height: 180px;
    border-radius: 10px;
    background: #fff;
    border: 1px solid #cbd5e1;
    cursor: crosshair;
    touch-action: none;
}
.preview-block {
    border: 1px solid var(--border-color);
    background: rgba(255,255,255,0.03);
    border-radius: 12px;
    padding: 14px;
    min-height: 140px;
}
.readonly-sign-box {
    min-height: 110px;
    border: 1px dashed #94a3b8;
    border-radius: 10px;
    background: rgba(255,255,255,0.02);
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
    text-align: center;
    padding: 12px;
}
.hr-block {
    display: none;
}
.note-box {
    background: linear-gradient(135deg, #ffefb0 0%, #f7d774 100%);
    color: #714f00;
    border-radius: 12px;
    padding: 14px 16px;
    font-weight: 600;
    margin-bottom: 18px;
}
.select2-container .select2-selection--single {
    height: 38px !important;
    border: 1px solid #ced4da !important;
    border-radius: 0.375rem !important;
    padding: 4px 12px !important;
}
.select2-container .select2-selection--single .select2-selection__rendered {
    line-height: 28px !important;
}
.select2-container .select2-selection--single .select2-selection__arrow {
    height: 36px !important;
}
@media (max-width: 991px) {
    .request-grid-two, .request-grid-three {
        grid-template-columns: 1fr;
    }
    .request-card-body {
        padding: 16px;
    }
}
html[data-theme="dark"] .select2-container--default .select2-selection--single {
    background-color: #0f172a !important; 
    border-color: #334155 !important; 
}
html[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #f8fafc !important; 
}
html[data-theme="dark"] .select2-dropdown {
    background-color: #1e293b !important; 
    border-color: #334155 !important; 
}
html[data-theme="dark"] .select2-container--default .select2-results__option {
    color: #f8fafc !important; 
}
html[data-theme="dark"] .select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #334155 !important; 
}
html[data-theme="dark"] .select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #2563eb !important; 
    color: #ffffff !important; 
}
html[data-theme="dark"] .select2-container--default .select2-search--dropdown .select2-search__field {
    background-color: #0f172a !important; 
    color: #f8fafc !important; 
    border-color: #334155 !important; 
}
html[data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__arrow b {
    border-color: #94a3b8 transparent transparent transparent !important;
}


/* --- DARK MODE FIX FOR TEXTAREAS, INPUTS & PLACEHOLDERS --- */
html[data-theme="dark"] .form-control,
html[data-theme="dark"] .form-select {
    background-color: #0f172a !important;
    border-color: #334155 !important;
    color: #f8fafc !important; /* Makes typed text white/visible */
}

/* Fix for the placeholder notes in Purpose and Reason Details */
html[data-theme="dark"] .form-control::placeholder {
    color: #94a3b8 !important; /* Lighter slate gray for visibility */
    opacity: 1 !important;
}

/* Focus state for dark mode */
html[data-theme="dark"] .form-control:focus,
html[data-theme="dark"] .form-select:focus {
    background-color: #0f172a !important;
    color: #f8fafc !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25) !important;
}

/* Fix for disabled/readonly inputs in dark mode */
html[data-theme="dark"] .form-control:disabled,
html[data-theme="dark"] .form-control[readonly] {
    background-color: #1e293b !important;
    color: #cbd5e1 !important;
    border-color: #334155 !important;
}
';

ob_start();
?>

<form method="POST" enctype="multipart/form-data" id="requestForm">
    <input type="hidden" name="action" value="submit_request">
    <input type="hidden" name="signature_draw_data" id="signature_draw_data">

    <div class="note-box">
        <i class="fas fa-info-circle me-2"></i> Fill all required fields carefully. You can submit for yourself or on behalf of another employee. For signature, either upload a clean signature image or draw using mouse/touch.
    </div>

    <!-- 1. Request Type -->
    <div class="request-card">
        <div class="request-card-header">Request Type</div>
        <div class="request-card-body">
            <div class="request-grid-three">
                <div>
                    <label class="label-title">Request Category <span class="text-danger">*</span></label>
                    <select name="request_type" id="request_type" class="form-select" required>
                        <option value="regular">Regular</option>
                        <option value="new_joining">New Joining</option>
                    </select>
                </div>
                <div>
                    <label class="label-title">Request For <span class="text-danger">*</span></label>
                    <select name="requested_for_type" id="requested_for_type" class="form-select" required>
                        <option value="self">Self</option>
                        <option value="other">Another Employee</option>
                    </select>
                </div>
                <div id="requested_for_user_wrap" style="display:none;">
                    <label class="label-title">Select Employee <span class="text-danger">*</span></label>
                    <select name="requested_for_user_id" id="requested_for_user_id" class="form-select">
                        <option value="">Choose employee...</option>
                        <?php foreach ($request_for_users as $u): ?>
                            <option value="<?php echo (int)$u['id']; ?>"
                                data-name="<?php echo h($u['employee_name']); ?>"
                                data-empid="<?php echo h(format_emp_id($u['employee_id'])); ?>"
                                data-designation="<?php echo h($u['designation']); ?>"
                                data-department="<?php echo h($u['department']); ?>"
                                data-category="<?php echo h($u['employee_category'] ?? 'N/A'); ?>"
                                data-status="<?php echo h($u['employee_status'] ?? 'N/A'); ?>"
                                data-joining="<?php echo h($u['date_of_joining'] ?? ''); ?>"
                                data-posting="<?php echo h($u['location']); ?>">
                                <?php echo h($u['employee_name']); ?> (<?php echo h(format_emp_id($u['employee_id'])); ?>) - <?php echo h($u['department']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. User Information -->
    <div class="request-card">
        <div class="request-card-header">User Information</div>
        <div class="request-card-body">
            <div class="request-grid-three">
                <div>
                    <label class="label-title">Date of Request <span class="text-danger">*</span></label>
                    <input type="date" name="date_of_request" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div>
                    <label class="label-title">Employee ID</label>
                    <input type="text" name="requested_for_emp_id" id="requested_for_emp_id" class="form-control bg-light" value="<?php echo h(format_emp_id($logged_user['nid_company_id'] ?? '')); ?>" readonly>
                </div>
                <div>
                    <label class="label-title">Employee Name <span class="text-danger">*</span></label>
                    <input type="text" name="requested_for_name" id="requested_for_name" class="form-control bg-light" value="<?php echo h($logged_user['full_name'] ?: $logged_user['username']); ?>" readonly required>
                </div>
                <div>
                    <label class="label-title">Employee Designation</label>
                    <input type="text" name="requested_for_designation" id="requested_for_designation" class="form-control bg-light" value="<?php echo h($logged_user['designation']); ?>" readonly>
                </div>
                <div>
                    <label class="label-title">Employee Category</label>
                    <input type="text" name="requested_for_category" id="requested_for_category" class="form-control bg-light" value="<?php echo h($logged_user['employee_category'] ?? 'N/A'); ?>" readonly>
                </div>
                <div>
                    <label class="label-title">Employment Status</label>
                    <input type="text" name="requested_for_status" id="requested_for_status" class="form-control bg-light" value="<?php echo h($logged_user['employment_status'] ?? 'N/A'); ?>" readonly>
                </div>
                <div>
                    <label class="label-title">Date of Joining</label>
                    <input type="date" name="requested_for_joining_date" id="requested_for_joining_date" class="form-control bg-light" value="<?php echo h($logged_user['date_of_joining'] ?? ''); ?>" readonly>
                </div>
                <div>
                    <label class="label-title">Department <span class="text-danger">*</span></label>
                    <input type="text" name="requested_for_department" id="requested_for_department" class="form-control bg-light" value="<?php echo h($logged_user['department']); ?>" readonly required>
                </div>
                <div>
                    <label class="label-title">Place of Posting</label>
                    <input type="text" name="requested_for_place_posting" id="requested_for_place_posting" class="form-control bg-light" value="<?php echo h($self_place_of_posting); ?>" readonly>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Requirement Section -->
    <div class="request-card">
        <div class="request-card-header">Requirement Section</div>
        <div class="request-card-body">
            <div class="request-grid-two">
                
                <!-- Request For -->
                <div class="choice-box">
                    <div class="form-section-title">Request For <span class="text-danger">*</span></div>
                    <div class="request-grid-two">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_laptop" id="request_for_laptop">
                            <label class="form-check-label" for="request_for_laptop">Laptop</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_desktop" id="request_for_desktop">
                            <label class="form-check-label" for="request_for_desktop">Desktop</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_scanner" id="request_for_scanner">
                            <label class="form-check-label" for="request_for_scanner">Scanner</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_monitor" id="request_for_monitor">
                            <label class="form-check-label" for="request_for_monitor">Monitor</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_ram" id="request_for_ram">
                            <label class="form-check-label" for="request_for_ram">RAM</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_ssd" id="request_for_ssd">
                            <label class="form-check-label" for="request_for_ssd">SSD</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_hdd" id="request_for_hdd">
                            <label class="form-check-label" for="request_for_hdd">HDD</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_mouse_wired" id="request_for_mouse_wired">
                            <label class="form-check-label" for="request_for_mouse_wired">Wired Mouse</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_mouse_wireless" id="request_for_mouse_wireless">
                            <label class="form-check-label" for="request_for_mouse_wireless">Wireless Mouse</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_keyboard" id="request_for_keyboard">
                            <label class="form-check-label" for="request_for_keyboard">Keyboard</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_printer" id="request_for_printer">
                            <label class="form-check-label" for="request_for_printer">Printer</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="request_for_other" id="request_for_other">
                            <label class="form-check-label" for="request_for_other">Other Accessories</label>
                        </div>
                    </div>
                    
                    <div class="mt-3" id="request_for_other_wrap" style="display:none;">
                        <label class="label-title">Other Hardware / Accessories</label>
                        <input type="text" name="request_for_other_text" id="request_for_other_text" class="form-control" placeholder="Example: Webcam, Docking Station, LAN Cable">
                    </div>
                </div>

                <!-- Purpose -->
                <div class="choice-box">
                    <div class="form-section-title">Purpose / Work Functions <span class="text-danger">*</span></div>
                    <textarea name="purpose_text" class="form-control" rows="8" placeholder="Please mention purposes, work functions, business use, justification..." required></textarea>
                </div>
            </div>
            
            <div class="mt-4 request-grid-two">
                <!-- Request Reason -->
                <div class="choice-box">
                    <div class="form-section-title">Request Reason <span class="text-danger">*</span></div>
                    <div class="request-grid-two">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reason_allocation" id="reason_allocation">
                            <label class="form-check-label" for="reason_allocation">Allocation</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reason_replacement" id="reason_replacement">
                            <label class="form-check-label" for="reason_replacement">Replacement</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reason_exchange" id="reason_exchange">
                            <label class="form-check-label" for="reason_exchange">Exchange</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reason_upgradation" id="reason_upgradation">
                            <label class="form-check-label" for="reason_upgradation">Upgradation</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reason_maintenance_repair" id="reason_maintenance_repair">
                            <label class="form-check-label" for="reason_maintenance_repair">Maintenance / Repair</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reason_damage" id="reason_damage">
                            <label class="form-check-label" for="reason_damage">Damage</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="reason_other" id="reason_other">
                            <label class="form-check-label" for="reason_other">Others</label>
                        </div>
                    </div>
                    <div class="mt-3" id="reason_other_wrap" style="display:none;">
                        <label class="label-title">Other Reason</label>
                        <input type="text" name="reason_other_text" id="reason_other_text" class="form-control" placeholder="Write additional reason if needed">
                    </div>
                </div>

                <!-- Reason Details -->
                <div class="choice-box">
                    <div class="form-section-title">Reason Details</div>
                    <textarea name="reason_details" class="form-control" rows="8" placeholder="Explain why this request is needed, current issue, impact, urgency..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Signatures / Recommendations -->
    <div class="request-card">
        <div class="request-card-header">Request Raised by / Recommendation</div>
        <div class="request-card-body">
            <div class="request-grid-two">
                <!-- User Sign -->
                <div>
                    <div class="form-section-title">Request Raised by (Users / Line Managers)</div>
                    <div class="preview-block mb-3">
                        <div class="mb-2"><strong>Name:</strong> <?php echo h($logged_user['full_name'] ?: $logged_user['username']); ?></div>
                        <div class="mb-2"><strong>Employee ID:</strong> <?php echo h($logged_user['nid_company_id'] ?? ''); ?></div>
                        <div class="mb-2"><strong>Designation:</strong> <?php echo h($logged_user['designation']); ?></div>
                        <div class="mb-3"><strong>Department:</strong> <?php echo h($logged_user['department']); ?></div>
                        
                        <label class="label-title">Signature Mode</label>
                        <select class="form-select mb-3" id="signature_mode" name="signature_mode">
                            <option value="draw">Draw Signature</option>
                            <option value="upload">Upload Signature Image</option>
                        </select>
                        
                        <div id="draw_signature_wrap" class="signature-pad-wrap">
                            <div class="small text-muted mb-2"><i class="fas fa-pencil-alt"></i> Use mouse or touch to sign inside the box below.</div>
                            <canvas id="signature-pad" class="signature-canvas"></canvas>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="clear-signature">Clear Signature</button>
                            </div>
                        </div>
                        
                        <div id="upload_signature_wrap" class="signature-pad-wrap" style="display:none;">
                            <div class="small text-muted mb-2"><i class="fas fa-file-image"></i> Upload a clean PNG/JPG/WEBP signature image, max 2MB.</div>
                            <input type="file" name="signature_file" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                        </div>
                    </div>
                </div>

                <!-- Dept Head -->
                <div>
                    <div class="form-section-title">Dept. Head Recommendation</div>
                    <div class="readonly-sign-box">
                        This section will be auto-filled during Department Head approval.<br>
                        Signature, name, designation, and department will appear automatically if available.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- HR Block -->
    <div class="request-card hr-block" id="hrBlock">
        <div class="request-card-header">Request Checked by Head of HR</div>
        <div class="request-card-body">
            <div class="readonly-sign-box">
                This HR check block appears only for New Joining requests and will remain non-editable for requester.
            </div>
        </div>
    </div>

    <!-- ICT Assessment Block -->
    <div class="request-card">
        <div class="request-card-header">ICT Assessment Section</div>
        <div class="request-card-body">
            <div class="readonly-sign-box">
                ICT Assessment section is reserved for ICT Assessor only.<br>
                After initial approvals, the assessor will fill the assessment report, allocation date, lifetime, configuration, stock/new purchase, cost, purchase indent, signature, and comment here.
            </div>
        </div>
    </div>

    <!-- Final Approval Block -->
    <div class="request-card">
        <div class="request-card-header">Final Approval Section</div>
        <div class="request-card-body">
            <div class="request-grid-two">
                <div class="preview-block">
                    <div class="form-section-title">Approved by ICT Incharge (Infrastructure)</div>
                    <div class="readonly-sign-box mb-3">Digital signature and comment will appear here after infrastructure head approval.</div>
                    <div><strong>Name:</strong> Tanvir Ahmed</div>
                    <div><strong>Desig.:</strong> Manager (Infra), ICT</div>
                </div>
                <div class="preview-block">
                    <div class="form-section-title">Approved by ICT Head Of The Department</div>
                    <div class="readonly-sign-box mb-3">Digital signature and comment will appear here after final ICT head approval.</div>
                    <div><strong>Name:</strong> Md. Zakaria Haider</div>
                    <div><strong>Desig.:</strong> GM, Sheltech Ceramics Limited</div>
                </div>
            </div>
        </div>
    </div>

    <div class="request-card">
        <div class="request-card-body">
            <div class="d-flex justify-content-end gap-2 flex-wrap">
                <a href="index.php" class="btn btn-outline-secondary px-4">Cancel</a>
                <button type="submit" class="btn btn-primary px-4" style="background:#0b2545; border-color:#0b2545;">
                    <i class="fas fa-paper-plane me-2"></i>Submit Request
                </button>
            </div>
        </div>
    </div>

</form>

<!-- Select2 Library -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    const requestType = document.getElementById('request_type');
    const hrBlock = document.getElementById('hrBlock');
    
    const requestedForType = document.getElementById('requested_for_type');
    const requestedForWrap = document.getElementById('requested_for_user_wrap');
    const requestedForUser = document.getElementById('requested_for_user_id');
    
    const empId = document.getElementById('requested_for_emp_id');
    const empName = document.getElementById('requested_for_name');
    const designation = document.getElementById('requested_for_designation');
    const department = document.getElementById('requested_for_department');
    const category = document.getElementById('requested_for_category');
    const status = document.getElementById('requested_for_status');
    const joining = document.getElementById('requested_for_joining_date');
    const posting = document.getElementById('requested_for_place_posting');

    const selfData = {
        empid: <?php echo json_encode(format_emp_id($logged_user['nid_company_id'] ?? '')); ?>,
        name: <?php echo json_encode($logged_user['full_name'] ?: $logged_user['username']); ?>,
        designation: <?php echo json_encode($logged_user['designation'] ?? ''); ?>,
        department: <?php echo json_encode($logged_user['department'] ?? ''); ?>,
        category: <?php echo json_encode($logged_user['employee_category'] ?? 'N/A'); ?>,
        status: <?php echo json_encode($logged_user['employment_status'] ?? 'N/A'); ?>,
        joining: <?php echo json_encode($logged_user['date_of_joining'] ?? ''); ?>,
        posting: <?php echo json_encode($self_place_of_posting); ?>
    };

    function toggleHRBlock() {
        hrBlock.style.display = (requestType.value === 'new_joining') ? 'block' : 'none';
    }

    function fillFromOption(option) {
        if (!option || !option.value) return;
        empId.value = option.dataset.empid;
        empName.value = option.dataset.name;
        designation.value = option.dataset.designation;
        department.value = option.dataset.department;
        category.value = option.dataset.category || 'N/A';
        status.value = option.dataset.status || 'N/A';
        joining.value = option.dataset.joining;
        posting.value = option.dataset.posting;
    }

    function fillSelf() {
        empId.value = selfData.empid;
        empName.value = selfData.name;
        designation.value = selfData.designation;
        department.value = selfData.department;
        category.value = selfData.category;
        status.value = selfData.status;
        joining.value = selfData.joining;
        posting.value = selfData.posting;
    }

    function toggleRequestedFor() {
        if (requestedForType.value === 'other') {
            requestedForWrap.style.display = 'block';
        } else {
            requestedForWrap.style.display = 'none';
            // Select2 reset
            if (window.jQuery && jQuery.fn.select2) {
                $(requested_for_user_id).val('').trigger('change.select2');
            } else {
                requestedForUser.value = '';
            }
            fillSelf();
        }
    }

    // Initialize Select2
    if (window.jQuery && jQuery.fn.select2) {
        $(requested_for_user_id).select2({
            placeholder: "Search employee by name, ID or department...",
            allowClear: true,
            width: '100%'
        });
        
        // Use Select2 change event
        $(requested_for_user_id).on('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            fillFromOption(selectedOption);
        });
    } else {
        // Fallback if Select2 fails to load
        requestedForUser.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            fillFromOption(option);
        });
    }

    requestType.addEventListener('change', toggleHRBlock);
    requestedForType.addEventListener('change', toggleRequestedFor);

    toggleHRBlock();
    toggleRequestedFor();
</script>

<script>
    const signatureMode = document.getElementById('signature_mode');
    const drawWrap = document.getElementById('draw_signature_wrap');
    const uploadWrap = document.getElementById('upload_signature_wrap');

    function toggleMode() {
        if (signatureMode.value === 'upload') {
            drawWrap.style.display = 'none';
            uploadWrap.style.display = 'block';
        } else {
            drawWrap.style.display = 'block';
            uploadWrap.style.display = 'none';
        }
    }

    signatureMode.addEventListener('change', toggleMode);
    toggleMode();
</script>

<script>
    const otherItemCheckbox = document.getElementById('request_for_other');
    const otherItemWrap = document.getElementById('request_for_other_wrap');
    const otherItemInput = document.getElementById('request_for_other_text');

    const otherReasonCheckbox = document.getElementById('reason_other');
    const otherReasonWrap = document.getElementById('reason_other_wrap');
    const otherReasonInput = document.getElementById('reason_other_text');

    function toggleOtherFields() {
        if (otherItemCheckbox && otherItemWrap && otherItemInput) {
            if (otherItemCheckbox.checked) {
                otherItemWrap.style.display = 'block';
            } else {
                otherItemWrap.style.display = 'none';
                otherItemInput.value = '';
            }
        }
        if (otherReasonCheckbox && otherReasonWrap && otherReasonInput) {
            if (otherReasonCheckbox.checked) {
                otherReasonWrap.style.display = 'block';
            } else {
                otherReasonWrap.style.display = 'none';
                otherReasonInput.value = '';
            }
        }
    }

    if (otherItemCheckbox) otherItemCheckbox.addEventListener('change', toggleOtherFields);
    if (otherReasonCheckbox) otherReasonCheckbox.addEventListener('change', toggleOtherFields);
    toggleOtherFields();
</script>

<script>
    const canvas = document.getElementById('signature-pad');
    const clearBtn = document.getElementById('clear-signature');
    const hiddenInput = document.getElementById('signature_draw_data');

    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    let drawing = false;

    function setupCanvas() {
        const rect = canvas.getBoundingClientRect();
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = rect.width * ratio;
        canvas.height = 180 * ratio;
        ctx.setTransform(1, 0, 0, 1, 0, 0);
        ctx.scale(ratio, ratio);
        ctx.lineWidth = 2;
        ctx.lineCap = 'round';
        ctx.strokeStyle = '#111827';
        
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, rect.width, 180);
    }

    function getPos(e) {
        const rect = canvas.getBoundingClientRect();
        if (e.touches && e.touches.length > 0) {
            return {
                x: e.touches[0].clientX - rect.left,
                y: e.touches[0].clientY - rect.top
            };
        }
        return {
            x: e.clientX - rect.left,
            y: e.clientY - rect.top
        };
    }

    function startDraw(e) {
        drawing = true;
        const pos = getPos(e);
        ctx.beginPath();
        ctx.moveTo(pos.x, pos.y);
        e.preventDefault();
    }

    function draw(e) {
        if (!drawing) return;
        const pos = getPos(e);
        ctx.lineTo(pos.x, pos.y);
        ctx.stroke();
        hiddenInput.value = canvas.toDataURL('image/png');
        e.preventDefault();
    }

    function endDraw() {
        if (!drawing) return;
        drawing = false;
        hiddenInput.value = canvas.toDataURL('image/png');
    }

    clearBtn.addEventListener('click', function() {
        setupCanvas();
        hiddenInput.value = '';
    });

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', endDraw);

    canvas.addEventListener('touchstart', startDraw, {passive: false});
    canvas.addEventListener('touchmove', draw, {passive: false});
    canvas.addEventListener('touchend', endDraw);

    setupCanvas();
    window.addEventListener('resize', setupCanvas);
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>