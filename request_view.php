<?php
session_start();
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['UserName'];
$user_role = trim($_SESSION['UserRole'] ?? 'user');
$role_l = strtolower($user_role);
$is_super_admin = in_array($role_l, ['superadmin', 'admin']);

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function format_emp_id($value) {
    $value = trim((string)$value);
    if ($value === '' || strtolower($value) === 'n/a') {
        return 'N/A';
    }
    return str_pad($value, 8, '0', STR_PAD_LEFT);
}

function safe_date($value, $format = 'd M Y, h:i A') {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $ts = strtotime($value);
    if ($ts === false) {
        return 'N/A';
    }
    return date($format, $ts);
}

function render_signature_html($type, $file, $draw) {
    $type = trim((string)$type);
    $file = trim((string)$file);
    $draw = trim((string)$draw);

    if ($type === 'upload' && $file !== '' && file_exists(__DIR__ . '/' . $file)) {
        return '<img src="' . h($file) . '" alt="Signature" style="max-width:220px; max-height:100px; border:1px solid #d1d5db; padding:6px; background:#fff; border-radius:8px;">';
    }

    if ($type === 'draw' && $draw !== '') {
        return '<img src="' . h($draw) . '" alt="Drawn Signature" style="max-width:220px; max-height:100px; border:1px solid #d1d5db; padding:6px; background:#fff; border-radius:8px;">';
    }

    if ($file !== '' && file_exists(__DIR__ . '/' . $file)) {
        return '<img src="' . h($file) . '" alt="Signature" style="max-width:220px; max-height:100px; border:1px solid #d1d5db; padding:6px; background:#fff; border-radius:8px;">';
    }

    if ($draw !== '') {
        return '<img src="' . h($draw) . '" alt="Drawn Signature" style="max-width:220px; max-height:100px; border:1px solid #d1d5db; padding:6px; background:#fff; border-radius:8px;">';
    }

    return '<div class="muted-box">No signature available.</div>';
}

function stage_label($stage) {
    $stage = trim((string)$stage);
    if ($stage === '' || $stage === '0') return 'N/A';

    $map = [
        'department_head'   => 'Department Head',
        'pending_department_head' => 'Pending - Department Head',
        'hr_head'           => 'HR Head',
        'pending_hr_head'   => 'Pending - HR Head',
        'ict_assessor'      => 'ICT Assessor',
        'pending_ict_assessor' => 'Pending - ICT Assessor',
        'ict_infra_head'    => 'ICT Infra Head',
        'pending_ict_infra_head' => 'Pending - ICT Infra Head',
        'ict_head'          => 'ICT Head',
        'pending_ict_head'  => 'Pending - ICT Head',
        'completed'         => 'Completed',
        'returned_to_user'  => 'Returned to User'
    ];

    return $map[$stage] ?? ucwords(str_replace('_', ' ', $stage));
}

function status_badge($status) {
    $status = strtolower(trim((string)$status));
    if ($status === '' || $status === '0') {
        return '<span class="badge badge-secondary">N/A</span>';
    }

    if (strpos($status, 'approved') !== false || strpos($status, 'completed') !== false) {
        $class = 'badge-success';
    } elseif (strpos($status, 'rejected') !== false || strpos($status, 'returned') !== false) {
        $class = 'badge-danger';
    } elseif (strpos($status, 'pending') !== false) {
        $class = 'badge-warning';
    } else {
        $class = 'badge-secondary';
    }

    return '<span class="badge ' . $class . '">' . h(ucwords(str_replace('_', ' ', $status))) . '</span>';
}

function get_request_items_html($row) {
    $items = [];

    if (!empty($row['request_for_laptop'])) $items[] = 'Laptop';
    if (!empty($row['request_for_desktop'])) $items[] = 'Desktop';
    if (!empty($row['request_for_scanner'])) $items[] = 'Scanner';
    if (!empty($row['request_for_monitor'])) $items[] = 'Monitor';
    if (!empty($row['request_for_ram'])) $items[] = 'RAM';
    if (!empty($row['request_for_ssd'])) $items[] = 'SSD';
    if (!empty($row['request_for_hdd'])) $items[] = 'HDD';
    if (!empty($row['request_for_mouse_wired'])) $items[] = 'Wired Mouse';
    if (!empty($row['request_for_mouse_wireless'])) $items[] = 'Wireless Mouse';
    if (!empty($row['request_for_keyboard'])) $items[] = 'Keyboard';
    if (!empty($row['request_for_printer'])) $items[] = 'Printer';
    if (!empty($row['request_for_other'])) {
        $items[] = !empty($row['request_for_other_text']) ? $row['request_for_other_text'] : 'Other Accessories';
    }

    if (empty($items)) return '<div class="muted-box">No item selected.</div>';

    $html = '<ul class="plain-list">';
    foreach ($items as $item) {
        $html .= '<li>' . h($item) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

function get_request_reasons_html($row) {
    $items = [];

    if (!empty($row['reason_allocation'])) $items[] = 'Allocation';
    if (!empty($row['reason_replacement'])) $items[] = 'Replacement';
    if (!empty($row['reason_exchange'])) $items[] = 'Exchange';
    if (!empty($row['reason_upgradation'])) $items[] = 'Upgradation';
    if (!empty($row['reason_maintenance_repair'])) $items[] = 'Maintenance / Repair';
    if (!empty($row['reason_damage'])) $items[] = 'Damage';
    if (!empty($row['reason_other'])) {
        $items[] = !empty($row['reason_other_text']) ? $row['reason_other_text'] : 'Other Reason';
    }

    if (empty($items)) return '<div class="muted-box">No reason selected.</div>';

    $html = '<ul class="plain-list">';
    foreach ($items as $item) {
        $html .= '<li>' . h($item) . '</li>';
    }
    $html .= '</ul>';
    return $html;
}

/* Logged user info */
$user_stmt = mysqli_prepare($conn, "SELECT id, username, full_name, department, user_role FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, "s", $username);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$logged_user = mysqli_fetch_assoc($user_result);

if (!$logged_user) {
    die('User not found.');
}

$request_id = (int)($_GET['id'] ?? 0);
if ($request_id <= 0) {
    die('Invalid request ID.');
}

$req_stmt = mysqli_prepare($conn, "SELECT * FROM hardware_requests WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($req_stmt, "i", $request_id);
mysqli_stmt_execute($req_stmt);
$req_result = mysqli_stmt_get_result($req_stmt);
$request = mysqli_fetch_assoc($req_result);

if (!$request) {
    die('Request not found.');
}

/*
|--------------------------------------------------------------------------
| Permission Logic
|--------------------------------------------------------------------------
| SuperAdmin/Admin -> all
| requester -> own request
| Approver -> only same department request
| HR/HR Head/ICT Assessor/ICT Infra Head/ICT Head -> any request they are handling by role stage
|--------------------------------------------------------------------------
*/
$has_permission = false;

if ($is_super_admin) {
    $has_permission = true;
} elseif ((int)$request['requester_user_id'] === (int)$logged_user['id']) {
    $has_permission = true;
} elseif ($role_l === 'approver') {
    if (
        trim((string)$request['requested_for_department']) === trim((string)$logged_user['department'])
    ) {
        $has_permission = true;
    }
} elseif (in_array($role_l, ['hr', 'hr head', 'hr_head', 'ict assessor', 'ict_assessor', 'ict infra head', 'ict_infra_head', 'ict head', 'ict_head'])) {
    $has_permission = true;
}

if (!$has_permission) {
    die('You do not have permission to view this request.');
}

$requester_profile = null;
if (!empty($request['requester_user_id'])) {
    $req_user_stmt = mysqli_prepare($conn, "SELECT id, nid_company_id, full_name, designation, department, email, phone FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($req_user_stmt, "i", $request['requester_user_id']);
    mysqli_stmt_execute($req_user_stmt);
    $req_user_result = mysqli_stmt_get_result($req_user_stmt);
    $requester_profile = mysqli_fetch_assoc($req_user_result);
}

$display_requester_name = (!empty($request['requester_name']) && $request['requester_name'] !== '0')
    ? $request['requester_name']
    : ($requester_profile['full_name'] ?? 'N/A');

$raw_requester_emp_id = (!empty($request['requester_emp_id']) && $request['requester_emp_id'] !== '0')
    ? $request['requester_emp_id']
    : ($requester_profile['nid_company_id'] ?? 'N/A');

$display_requester_emp_id = format_emp_id($raw_requester_emp_id);

$display_requester_department = (!empty($request['requester_department']) && $request['requester_department'] !== '0')
    ? $request['requester_department']
    : ($requester_profile['department'] ?? 'N/A');

$display_requester_designation = (!empty($request['requester_designation']) && $request['requester_designation'] !== '0')
    ? $request['requester_designation']
    : ($requester_profile['designation'] ?? 'N/A');

$display_requester_email = (!empty($request['requester_email']) && $request['requester_email'] !== '0')
    ? $request['requester_email']
    : ($requester_profile['email'] ?? 'N/A');

$display_requester_phone = (!empty($request['requester_phone']) && $request['requester_phone'] !== '0')
    ? $request['requester_phone']
    : ($requester_profile['phone'] ?? 'N/A');

$display_requester_signature = render_signature_html(
    $request['requester_signature_type'] ?? '',
    $request['requester_signature_file'] ?? '',
    $request['requester_signature_draw'] ?? ''
);

$display_purpose_text = (!empty($request['purpose_text']) && $request['purpose_text'] !== '0')
    ? $request['purpose_text']
    : 'N/A';

$display_reason_details = (!empty($request['reason_details']) && $request['reason_details'] !== '0')
    ? $request['reason_details']
    : 'N/A';

$display_current_stage = (!empty($request['current_stage']) && $request['current_stage'] !== '0')
    ? $request['current_stage']
    : 'N/A';

$display_workflow_status = (!empty($request['workflow_status']) && $request['workflow_status'] !== '0')
    ? $request['workflow_status']
    : 'N/A';

/* Fetch requested employee extra details */
$requested_employee_location = '';
$fetched_joining_date = '';
$fetched_emp_category = '';
$fetched_emp_status = '';

if (!empty($request['requested_for_emp_id'])) {
    $emp_id_lookup = str_pad(trim((string)$request['requested_for_emp_id']), 8, '0', STR_PAD_LEFT);

    $emp_place_stmt = mysqli_prepare($conn, "SELECT location, date_of_joining, employee_category, employee_status FROM employees WHERE employee_id = ? LIMIT 1");
    mysqli_stmt_bind_param($emp_place_stmt, "s", $emp_id_lookup);
    mysqli_stmt_execute($emp_place_stmt);
    $emp_place_result = mysqli_stmt_get_result($emp_place_stmt);
    $emp_place_row = mysqli_fetch_assoc($emp_place_result);

    if ($emp_place_row) {
        $requested_employee_location = $emp_place_row['location'];
        $fetched_joining_date = $emp_place_row['date_of_joining'];
        $fetched_emp_category = $emp_place_row['employee_category'];
        $fetched_emp_status = $emp_place_row['employee_status'];
    }
}

$display_requested_for_place_posting = (!empty($request['requested_for_place_posting']) && $request['requested_for_place_posting'] !== '0')
    ? $request['requested_for_place_posting']
    : ($requested_employee_location ?: 'N/A');

$requested_for_joining_clean = trim((string)($request['requested_for_joining_date'] ?? ''));

$display_date_of_joining = (
    $requested_for_joining_clean !== '' &&
    $requested_for_joining_clean !== '0' &&
    $requested_for_joining_clean !== '0000-00-00' &&
    strtolower($requested_for_joining_clean) !== 'n/a'
)
    ? safe_date($requested_for_joining_clean, 'd M Y')
    : (!empty($fetched_joining_date) ? safe_date($fetched_joining_date, 'd M Y') : 'N/A');

$requested_for_category_clean = trim((string)($request['requested_for_category'] ?? ''));
$requested_for_status_clean = trim((string)($request['requested_for_status'] ?? ''));

$display_employee_category = (
    $requested_for_category_clean !== '' &&
    $requested_for_category_clean !== '0' &&
    strtolower($requested_for_category_clean) !== 'n/a'
)
    ? $requested_for_category_clean
    : (!empty($fetched_emp_category) ? $fetched_emp_category : 'N/A');

$display_employment_status = (
    $requested_for_status_clean !== '' &&
    $requested_for_status_clean !== '0' &&
    strtolower($requested_for_status_clean) !== 'n/a'
)
    ? $requested_for_status_clean
    : (!empty($fetched_emp_status) ? $fetched_emp_status : 'N/A');
    
$history = [];
$hist_stmt = mysqli_prepare($conn, "SELECT * FROM hardware_request_approval_history WHERE request_id = ? ORDER BY approved_at ASC, id ASC");
if ($hist_stmt) {
    mysqli_stmt_bind_param($hist_stmt, "i", $request_id);
    mysqli_stmt_execute($hist_stmt);
    $hist_res = mysqli_stmt_get_result($hist_stmt);
    while ($hist_row = mysqli_fetch_assoc($hist_res)) {
        $history[] = $hist_row;
    }
    mysqli_stmt_close($hist_stmt);
}    

$approvals = [];
$history_table_check = mysqli_query($conn, "SHOW TABLES LIKE 'hardware_request_approval_history'");
if ($history_table_check && mysqli_num_rows($history_table_check) > 0) {
    $history_stmt = mysqli_prepare($conn, "
        SELECT *
        FROM hardware_request_approval_history
        WHERE request_id = ?
        ORDER BY approved_at ASC
    ");
    mysqli_stmt_bind_param($history_stmt, "i", $request_id);
    mysqli_stmt_execute($history_stmt);
    $history_result = mysqli_stmt_get_result($history_stmt);

    while ($row = mysqli_fetch_assoc($history_result)) {
        $approvals[] = $row;
    }
}

$page_title = 'Request Details - SCL AMS';
$page_header_icon = 'fas fa-file-alt';
$page_header_title = 'Request Details';
$page_header_subtitle = 'View submitted ICT hardware request';
$page_top_title = 'Request Details';
$page_top_actions = '
<a href="javascript:history.back()" class="btn btn-outline-secondary me-2">
    <i class="fas fa-arrow-left me-1"></i> Back
</a>
<button class="btn btn-primary" onclick="window.print();" style="background:#0b2545; border-color:#0b2545;">
    <i class="fas fa-print me-1"></i> Print
</button>';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.request-view-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.request-view-header {
    background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
    color: #fff;
    padding: 20px 24px;
}
.request-view-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
}
.request-view-header p {
    margin: 6px 0 0 0;
    opacity: 0.95;
}
.request-section {
    padding: 22px 24px;
    border-top: 1px solid #e5e7eb;
}
.request-section-title {
    font-size: 18px;
    font-weight: 700;
    color: #0b2545;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e5e7eb;
}
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 22px;
}
.info-box {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 14px 16px;
    background: #fafafa;
}
.info-label {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    margin-bottom: 6px;
}
.info-value {
    font-size: 15px;
    color: #111827;
    word-break: break-word;
}
.full-row {
    grid-column: 1 / -1;
}
.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
}
.badge-success { background: #dcfce7; color: #166534; }
.badge-warning { background: #fef3c7; color: #92400e; }
.badge-danger { background: #fee2e2; color: #991b1b; }
.badge-secondary { background: #e5e7eb; color: #374151; }
.plain-list {
    margin: 0;
    padding-left: 20px;
}
.plain-list li {
    margin-bottom: 6px;
}
.muted-box {
    color: #6b7280;
}
.approval-item {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 14px 16px;
    background: #fafafa;
    margin-bottom: 12px;
}
.approval-title {
    font-weight: 700;
    color: #0b2545;
    margin-bottom: 6px;
}
.approval-meta {
    color: #475569;
    font-size: 14px;
}
@media (max-width: 991px) {
    .info-grid {
        grid-template-columns: 1fr;
    }
}
@media print {
    .btn, .sidebar, nav, .topbar { display: none !important; }
    .request-view-card { box-shadow: none; border: 1px solid #ccc; }
}
html[data-theme='dark'] .request-view-card {
    background: #1f2937 !important;
    border-color: #374151 !important;
}
html[data-theme='dark'] .request-section {
    border-top-color: #374151 !important;
}
html[data-theme='dark'] .request-section-title {
    color: #93c5fd !important;
    border-bottom-color: #374151 !important;
}
html[data-theme='dark'] .info-box,
html[data-theme='dark'] .approval-item {
    background: #111827 !important;
    border-color: #374151 !important;
}
html[data-theme='dark'] .info-label {
    color: #9ca3af !important;
}
html[data-theme='dark'] .info-value,
html[data-theme='dark'] .approval-meta {
    color: #f3f4f6 !important;
}
html[data-theme='dark'] .approval-title {
    color: #93c5fd !important;
}
";

ob_start();
?>

<div class="card mt-4 shadow-sm border-0">
    <div class="card-header bg-dark text-white fw-bold">
        <i class="fas fa-clock-rotate-left me-2"></i> Approval & Assessment History
    </div>
    <div class="card-body">
        <?php if (!empty($history)): ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle">
                    <thead class="table-light text-center">
                        <tr>
                            <th>#</th>
                            <th>Action By</th>
                            <th>Stage</th>
                            <th>Action</th>
                            <th>From Stage</th>
                            <th>To Stage</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Date Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $index => $hx): ?>
                            <tr>
                                <td class="text-center"><?php echo $index + 1; ?></td>
                                <td><?php echo h($hx['approver_name'] ?? 'N/A'); ?></td>
                                <td><?php echo h(ucwords(str_replace('_', ' ', $hx['approval_stage'] ?? ''))); ?></td>
                                <td><?php echo h(ucfirst($hx['action_type'] ?? 'N/A')); ?></td>
                                <td><?php echo h(ucwords(str_replace('_', ' ', $hx['from_stage'] ?? ''))); ?></td>
                                <td><?php echo h(ucwords(str_replace('_', ' ', $hx['to_stage'] ?? ''))); ?></td>
                                <td><?php echo h($hx['approval_status'] ?? 'N/A'); ?></td>
                                <td><?php echo nl2br(h($hx['remarks'] ?? '')); ?></td>
                                <td><?php echo h(safe_date($hx['approved_at'] ?? null)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="text-muted">No approval history found yet.</div>
        <?php endif; ?>
    </div>
</div>

<div class="request-view-card">
    <div class="request-view-header">
        <h2>ICT Hardware Request Form</h2>
        <p>
            Ref No: <?php echo h($request['ref_no'] ?? 'N/A'); ?> |
            Submitted: <?php echo h(safe_date($request['created_at'] ?? null)); ?>
        </p>
    </div>

    <div class="request-section">
        <div class="request-section-title">Requester Information</div>
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Full Name</div>
                <div class="info-value"><?php echo h($display_requester_name); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Employee ID</div>
                <div class="info-value"><?php echo h($display_requester_emp_id); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Department</div>
                <div class="info-value"><?php echo h($display_requester_department); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Designation</div>
                <div class="info-value"><?php echo h($display_requester_designation); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Email</div>
                <div class="info-value"><?php echo h($display_requester_email); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Phone</div>
                <div class="info-value"><?php echo h($display_requester_phone); ?></div>
            </div>

            <div class="info-box full-row">
                <div class="info-label">Requester Signature</div>
                <div class="info-value"><?php echo $display_requester_signature; ?></div>
            </div>
        </div>
    </div>

    <div class="request-section">
        <div class="request-section-title">Requested For</div>
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Employee Name</div>
                <div class="info-value"><?php echo h($request['requested_for_name'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Employee ID</div>
                <div class="info-value"><?php echo h(format_emp_id($request['requested_for_emp_id'] ?? 'N/A')); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Department</div>
                <div class="info-value"><?php echo h($request['requested_for_department'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Place of Posting</div>
                <div class="info-value"><?php echo h($display_requested_for_place_posting); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Designation</div>
                <div class="info-value"><?php echo h($request['requested_for_designation'] ?? 'N/A'); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Date of Joining</div>
                <div class="info-value"><?php echo h($display_date_of_joining); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Employee Category</div>
                <div class="info-value"><?php echo h($display_employee_category); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Employment Status</div>
                <div class="info-value"><?php echo h($display_employment_status); ?></div>
            </div>
        </div>
    </div>

    <div class="request-section">
        <div class="request-section-title">Request Details</div>
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Hardware / Accessories Requested</div>
                <div class="info-value"><?php echo get_request_items_html($request); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Request Reason</div>
                <div class="info-value"><?php echo get_request_reasons_html($request); ?></div>
            </div>
            <div class="info-box full-row">
                <div class="info-label">Purpose / Work Functions</div>
                <div class="info-value"><?php echo nl2br(h($display_purpose_text)); ?></div>
            </div>
            <div class="info-box full-row">
                <div class="info-label">Reason Details</div>
                <div class="info-value"><?php echo nl2br(h($display_reason_details)); ?></div>
            </div>
        </div>
    </div>

    <div class="request-section">
        <div class="request-section-title">Request Status</div>
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Request Type</div>
                <div class="info-value"><?php echo h(ucwords(str_replace('_', ' ', (string)($request['request_type'] ?? 'N/A')))); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Current Stage</div>
                <div class="info-value"><?php echo h(stage_label($display_current_stage)); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Workflow Status</div>
                <div class="info-value"><?php echo status_badge($display_workflow_status); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Date of Request</div>
                <div class="info-value"><?php echo h(safe_date($request['date_of_request'] ?? null, 'd M Y')); ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($approvals)): ?>
    <div class="request-section">
        <div class="request-section-title">Approval History</div>
        <?php foreach ($approvals as $approval): ?>
            <div class="approval-item">
                <div class="approval-title">
                    <?php echo h(stage_label($approval['approval_stage'] ?? '')); ?>
                </div>
                <div class="approval-meta">
                    Status: <?php echo strip_tags(status_badge($approval['approval_status'] ?? '')); ?><br>
                    Approver: <?php echo h($approval['approver_name'] ?? 'N/A'); ?><br>
                    Time: <?php echo h(safe_date($approval['approved_at'] ?? null)); ?><br>
                    Remarks: <?php echo h($approval['remarks'] ?? 'N/A'); ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="request-section">
        <div class="request-section-title">Submission Information</div>
        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Submitted On</div>
                <div class="info-value"><?php echo h(safe_date($request['created_at'] ?? null)); ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Last Updated</div>
                <div class="info-value"><?php echo h(safe_date($request['updated_at'] ?? null)); ?></div>
            </div>
        </div>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>