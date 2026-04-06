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

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function safe_date($value, $format = 'd M Y') {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return '';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : '';
}

function selected_items(array $pairs) {
    $out = [];
    foreach ($pairs as $label => $isSelected) {
        if ((int)$isSelected === 1) {
            $out[] = $label;
        }
    }
    return $out;
}

function display_selected(array $pairs, $fallback = 'N/A') {
    $items = selected_items($pairs);
    return !empty($items) ? implode(', ', $items) : $fallback;
}

function normalize_stage($stage) {
    $stage = strtolower(trim((string)$stage));
    $stage = str_replace(['-', ' '], '_', $stage);

    $map = [
        'department_head' => 'dept_head',
        'dept_head' => 'dept_head',
        'departmenthead' => 'dept_head',

        'hr_head' => 'hr_head',
        'hr' => 'hr_head',
        'human_resource_head' => 'hr_head',
        'human_resources_head' => 'hr_head',

        'ict_assessor' => 'ict_assessor',
        'assessor' => 'ict_assessor',
        'it_assessor' => 'ict_assessor',

        'ict_infra_head' => 'ict_infra_head',
        'ict_infrastructure_head' => 'ict_infra_head',
        'it_infra_head' => 'ict_infra_head',

        'ict_head' => 'ict_head',
        'it_head' => 'ict_head'
    ];

    return $map[$stage] ?? $stage;
}

function find_stage_action($history, array $stageKeys = [], array $statusKeys = []) {
    foreach ($history as $row) {
        $stage = normalize_stage($row['approval_stage'] ?? '');
        $status = strtolower(trim((string)($row['approval_status'] ?? '')));

        $stageMatch = empty($stageKeys) || in_array($stage, $stageKeys, true);
        $statusMatch = empty($statusKeys) || in_array($status, $statusKeys, true);

        if ($stageMatch && $statusMatch) {
            return $row;
        }
    }
    return null;
}

function get_user_signature_by_name($conn, $name) {
    $out = [
        'signature_file' => '',
        'designation' => '',
        'department' => '',
        'full_name' => ''
    ];

    $name = trim((string)$name);
    if ($name === '') return $out;

    $stmt = mysqli_prepare($conn, "
        SELECT full_name, designation, department, signature_file
        FROM users
        WHERE username = ? OR full_name = ?
        LIMIT 1
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "ss", $name, $name);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($res)) {
            $out = $row;
        }

        mysqli_stmt_close($stmt);
    }

    return $out;
}

function get_user_signature_by_id($conn, $user_id) {
    $out = [
        'signature_file' => '',
        'designation' => '',
        'department' => '',
        'full_name' => ''
    ];

    if (empty($user_id)) return $out;

    $stmt = mysqli_prepare($conn, "
        SELECT full_name, designation, department, signature_file
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        if ($row = mysqli_fetch_assoc($res)) {
            $out = $row;
        }

        mysqli_stmt_close($stmt);
    }

    return $out;
}

function get_signature_file_value($primary = '', $fallback = '') {
    $primary = trim((string)$primary);
    if ($primary !== '') return $primary;

    $fallback = trim((string)$fallback);
    if ($fallback !== '') return $fallback;

    return '';
}

function sig_url($file) {
    $file = trim((string)$file);
    if ($file === '') return '';
    return '/uploads/signatures/' . rawurlencode(basename($file));
}

function logo_url() {
    return '/images/company_logo.png';
}

function stage_box_html($label, $status, $date = '', $name = '', $remarks = '') {
    $status = strtolower(trim((string)$status));

    $class = 'pending';
    $text = 'Pending';

    if ($status === 'approved' || $status === 'assessed') {
        $class = 'approved';
        $text = ucwords($status); // This will output "Approved" or "Assessed"
    } elseif ($status === 'rejected') {
        $class = 'rejected';
        $text = 'Rejected';
    } elseif ($status === 'completed') {
        $class = 'completed';
        $text = 'Completed';
    }

    $html = '<div class="matrix-card ' . $class . '">';
    $html .= '<div class="matrix-title">' . h($label) . '</div>';
    $html .= '<div class="matrix-status">' . h($text) . '</div>';

    if ($name !== '') {
        $html .= '<div class="matrix-meta"><strong>By:</strong> ' . h($name) . '</div>';
    }

    if ($date !== '') {
        $html .= '<div class="matrix-meta"><strong>Date:</strong> ' . h(safe_date($date, 'd M Y, h:i A')) . '</div>';
    }

    if ($remarks !== '') {
        $html .= '<div class="matrix-meta"><strong>Remarks:</strong> ' . h($remarks) . '</div>';
    }

    $html .= '</div>';
    return $html;
}

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($request_id <= 0) {
    die('Invalid request ID.');
}

/* Main request */
$stmt = mysqli_prepare($conn, "SELECT * FROM hardware_requests WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$request) {
    die('Request not found.');
}

/* Approval history */
$history = [];
$hist_stmt = mysqli_prepare($conn, "SELECT * FROM hardware_request_approval_history WHERE request_id = ? ORDER BY approved_at ASC, id ASC");
if ($hist_stmt) {
    mysqli_stmt_bind_param($hist_stmt, "i", $request_id);
    mysqli_stmt_execute($hist_stmt);
    $hist_res = mysqli_stmt_get_result($hist_stmt);
    while ($row = mysqli_fetch_assoc($hist_res)) {
        $history[] = $row;
    }
    mysqli_stmt_close($hist_stmt);
}

/* Assessment table */
$assessment = null;
$ass_stmt = mysqli_prepare($conn, "SELECT * FROM hardware_request_assessment WHERE request_id = ? ORDER BY id DESC LIMIT 1");
if ($ass_stmt) {
    mysqli_stmt_bind_param($ass_stmt, "i", $request_id);
    mysqli_stmt_execute($ass_stmt);
    $ass_res = mysqli_stmt_get_result($ass_stmt);
    $assessment = mysqli_fetch_assoc($ass_res);
    mysqli_stmt_close($ass_stmt);
}

/* History based actions */
$deptApproval     = find_stage_action($history, ['dept_head'], []);
$hrApproval       = find_stage_action($history, ['hr_head'], []);
$assessorApproval = find_stage_action($history, ['ict_assessor'], []);
$infraApproval    = find_stage_action($history, ['ict_infra_head'], []);
$ictHeadApproval  = find_stage_action($history, ['ict_head'], []);

/* Signature/Profile info */
$requesterSig = !empty($request['requester_user_id'])
    ? get_user_signature_by_id($conn, $request['requester_user_id'])
    : get_user_signature_by_name($conn, $request['requester_name'] ?? '');

$deptSig     = get_user_signature_by_name($conn, $deptApproval['approver_name'] ?? '');
$hrSig       = get_user_signature_by_name($conn, $hrApproval['approver_name'] ?? '');
$assessorSig = get_user_signature_by_name($conn, $assessorApproval['approver_name'] ?? '');
$infraSig    = get_user_signature_by_name($conn, $infraApproval['approver_name'] ?? '');
$ictHeadSig  = get_user_signature_by_name($conn, $ictHeadApproval['approver_name'] ?? '');

/* signature files */
$requester_signature_file = get_signature_file_value(
    $request['requester_signature_file'] ?? '',
    $requesterSig['signature_file'] ?? ''
);

$dept_signature_file = get_signature_file_value(
    $deptApproval['signature_file'] ?? '',
    $deptSig['signature_file'] ?? ''
);

$hr_signature_file = get_signature_file_value(
    $hrApproval['signature_file'] ?? '',
    $hrSig['signature_file'] ?? ''
);

$assessor_signature_file = get_signature_file_value(
    $assessment['signature_file'] ?? ($assessorApproval['signature_file'] ?? ''),
    $assessorSig['signature_file'] ?? ''
);

$infra_signature_file = get_signature_file_value(
    $infraApproval['signature_file'] ?? '',
    $infraSig['signature_file'] ?? ''
);

$ict_head_signature_file = get_signature_file_value(
    $ictHeadApproval['signature_file'] ?? '',
    $ictHeadSig['signature_file'] ?? ''
);

/* Request For */
$requestForText = display_selected([
    'Laptop' => $request['request_for_laptop'] ?? 0,
    'Desktop' => $request['request_for_desktop'] ?? 0,
    'Mouse Wired' => $request['request_for_mouse_wired'] ?? 0,
    'Mouse Wireless' => $request['request_for_mouse_wireless'] ?? 0,
    'Keyboard' => $request['request_for_keyboard'] ?? 0,
    'Monitor' => $request['request_for_monitor'] ?? 0,
    'Scanner' => $request['request_for_scanner'] ?? 0,
    'Printer' => $request['request_for_printer'] ?? 0,
    'RAM' => $request['request_for_ram'] ?? 0,
    'SSD' => $request['request_for_ssd'] ?? 0,
    'HDD' => $request['request_for_hdd'] ?? 0,
    'Other' => $request['request_for_other'] ?? 0,
]);

if ((int)($request['request_for_other'] ?? 0) === 1) {
    $otherText = trim((string)($request['request_for_other_text'] ?? $request['other_item_text'] ?? ''));
    if ($otherText !== '') {
        $requestForText .= ' (' . $otherText . ')';
    }
}

/* Request Reason */
$requestReasonText = display_selected([
    'Allocation' => $request['reason_allocation'] ?? 0,
    'Replacement' => $request['reason_replacement'] ?? 0,
    'Exchange' => $request['reason_exchange'] ?? 0,
    'Upgradation' => $request['reason_upgradation'] ?? 0,
    'Maintenance/Repair' => ($request['reason_maintenance_repair'] ?? $request['reason_maintenance'] ?? 0),
    'Damage' => $request['reason_damage'] ?? 0,
    'Other' => $request['reason_other'] ?? 0,
]);

if ((int)($request['reason_other'] ?? 0) === 1) {
    $reasonOtherText = trim((string)($request['reason_other_text'] ?? ''));
    if ($reasonOtherText !== '') {
        $requestReasonText .= ' (' . $reasonOtherText . ')';
    }
}

/* Assessment */
$assessmentComment = '';
$assessmentBy = '';
$assessmentDate = '';
$assessmentDesignation = '';
$assessmentDepartment = '';

if (!empty($assessment['assessment_comment'])) {
    $assessmentComment = trim((string)$assessment['assessment_comment']);
    $assessmentBy = $assessment['assessor_name'] ?? '';
    $assessmentDate = $assessment['created_at'] ?? '';
} elseif (!empty($assessment['comment'])) {
    $assessmentComment = trim((string)$assessment['comment']);
    $assessmentBy = $assessment['assessor_name'] ?? '';
    $assessmentDate = $assessment['created_at'] ?? '';
} elseif (!empty($assessorApproval['remarks'])) {
    $assessmentComment = trim((string)$assessorApproval['remarks']);
    $assessmentBy = $assessorApproval['approver_name'] ?? '';
    $assessmentDate = $assessorApproval['approved_at'] ?? '';
}

if (!empty($assessmentBy)) {
    $assessmentDesignation = $assessorSig['designation'] ?? '';
    $assessmentDepartment = $assessorSig['department'] ?? '';
}

$workflow_status = trim((string)($request['workflow_status'] ?? ''));
if ($workflow_status === '') {
    $workflow_status = trim((string)($request['status'] ?? 'Pending'));
}

/* ========================================================
   FIXED FLOW DETECTION - TARGETING request_type
   ======================================================== */
$req_category = strtolower(trim((string)($request['request_type'] ?? '')));
$req_for = strtolower(trim((string)($request['requested_for_type'] ?? '')));

$new_joiner_keywords = [
    'new joiner',
    'new_joiner',
    'new-joiner',
    'new joining',
    'new_joining',
    'new-joining',
    'new joined',
    'new employee'
];

$is_new_joiner = in_array($req_category, $new_joiner_keywords, true) || 
                 in_array($req_for, $new_joiner_keywords, true);
/* ======================================================== */

$dept_stage_status = strtolower(trim((string)($deptApproval['approval_status'] ?? 'pending')));
$hr_stage_status = strtolower(trim((string)($hrApproval['approval_status'] ?? 'pending')));
$assessor_stage_status = strtolower(trim((string)($assessorApproval['approval_status'] ?? (!empty($assessmentComment) ? 'approved' : 'pending'))));
$infra_stage_status = strtolower(trim((string)($infraApproval['approval_status'] ?? 'pending')));
$ict_head_stage_status = strtolower(trim((string)($ictHeadApproval['approval_status'] ?? 'pending')));

$completed_stage_status = 'pending';
$lower_workflow_status = strtolower(trim((string)$workflow_status));

// If the overall status contains these words OR the final ICT Head has approved it
if (
    strpos($lower_workflow_status, 'completed') !== false || 
    strpos($lower_workflow_status, 'done') !== false || 
    $lower_workflow_status === 'approved' ||
    $ict_head_stage_status === 'approved' 
) {
    $completed_stage_status = 'completed';
}

/* Layout config */
$page_title = 'Print Request - ' . h($request['ref_no'] ?? ('REQ-' . $request_id));
$page_header_icon = 'fas fa-print';
$page_header_title = 'Hardware Request Print View';
$page_header_subtitle = 'Printable request details with approvals and signatures';
$page_top_title = 'Print Request';
$page_container_class = 'dashboard-container-xl';
$page_top_actions = '
    <a href="my_requests.php" class="btn btn-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i> Back
    </a>
    <button class="btn btn-primary btn-sm" onclick="window.print()">
        <i class="fas fa-print me-1"></i> Print
    </button>
';

// ADDED: .remarks-highlight css to style the remarks box nicely
$extra_css = '
/* Update these classes in your $extra_css */
.textarea {
    min-height: 80px;
    white-space: pre-wrap;
    border: 1px solid #bfc7d1;
    background: #fdfdfd;
    padding: 8px;
    font-size: 11px;
    margin-bottom: 8px;
    color: #111827;
}

.signature-box {
    border: 1px solid #bfc7d1;
    padding: 10px;
    background: #fff;
    min-height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.signature-box img {
    display: block;
    max-width: 150px;
    max-height: 50px;
    width: auto;
    height: auto;
    object-fit: contain;
    margin-bottom: 10px;
}

.meta-line {
    margin-top: 6px;
    font-size: 10px;
    line-height: 1.5;
    border-top: 1px dashed #e2e8f0;
    padding-top: 6px;
    color: #334155;
}

.remarks-text {
    font-size: 11px;
    font-style: italic;
    color: #334155;
    margin-bottom: 10px;
    flex-grow: 1; /* Pushes the signature to the bottom if remarks are short */
}
.workflow-matrix {
    border: 1px solid #cfd8e3;
    border-top: none;
    padding: 10px;
    background: #fff;
}

.matrix-grid {
    display: grid;
    gap: 10px;
    align-items: stretch;
}

.matrix-grid-5 {
    grid-template-columns: repeat(5, 1fr);
}

.matrix-grid-6 {
    grid-template-columns: repeat(6, 1fr);
}

.matrix-card {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 10px;
    min-height: 120px;
    background: #f8fafc;
}

.matrix-card.pending {
    background: #f8fafc;
    border-color: #cbd5e1;
}

.matrix-card.approved {
    background: #ecfdf5;
    border-color: #10b981;
}

.matrix-card.rejected {
    background: #fef2f2;
    border-color: #ef4444;
}

.matrix-card.completed {
    background: #eff6ff;
    border-color: #2563eb;
}

.matrix-title {
    font-size: 12px;
    font-weight: 700;
    margin-bottom: 8px;
    color: #0f172a;
    text-transform: uppercase;
}

.matrix-status {
    display: inline-block;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 999px;
    margin-bottom: 8px;
    background: #e2e8f0;
    color: #0f172a;
}

.matrix-card.approved .matrix-status {
    background: #10b981;
    color: #fff;
}

.matrix-card.rejected .matrix-status {
    background: #ef4444;
    color: #fff;
}

.matrix-card.completed .matrix-status {
    background: #2563eb;
    color: #fff;
}

.matrix-meta {
    font-size: 10px;
    line-height: 1.5;
    color: #334155;
    margin-top: 4px;
    word-break: break-word;
}
.print-page {
    background: #fff;
    border-radius: 12px;
    box-shadow: var(--shadow-main);
    overflow: hidden;
    border: 1px solid var(--border-color);
}
.print-wrap {
    padding: 12px;
    background: #f8fafc;
}
.page-sheet {
    width: 210mm;
    min-height: 297mm;
    margin: 0 auto;
    background: #fff;
    padding: 8px;
    box-sizing: border-box;
    color: #111827;
    box-shadow: 0 4px 16px rgba(0,0,0,0.08);
}
.topbar {
    background: #17365d;
    color: #fff;
    border-radius: 4px;
    margin-bottom: 8px;
    padding: 10px 12px;
}
.topbar-inner {
    display: flex;
    align-items: center;
    gap: 12px;
}
.topbar-logo {
    width: 250px;
    min-width: 72px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.topbar-logo img {
    max-width: 100%;
    max-height: 60px;
    height: auto;
    width: auto;
    object-fit: contain;
    display: block;
}
.topbar-text {
    flex: 1;
    text-align: center;
}
.topbar-title {
    font-size: 18px;
    font-weight: 700;
    margin: 0;
    line-height: 1.3;
}
.topbar-text small {
    display: block;
    font-weight: 400;
    font-size: 10px;
    margin-top: 4px;
    color: #e5eef8;
}
.ref-line {
    background: #fff3cd;
    border: 1px solid #f6d365;
    padding: 5px 8px;
    font-size: 11px;
    margin-bottom: 8px;
    color: #111827;
}
.section-title {
    background: #1f4e79;
    color: #fff;
    padding: 6px 8px;
    font-weight: 700;
    margin-top: 8px;
    margin-bottom: 0;
    border-radius: 2px 2px 0 0;
}
.grid-2, .grid-4 {
    display: grid;
    gap: 8px;
    border: 1px solid #cfd8e3;
    border-top: none;
    padding: 8px;
}
.grid-2 { grid-template-columns: 1fr 1fr; }
.grid-4 { grid-template-columns: 1fr 1fr 1fr 1fr; }
.field label {
    display: block;
    font-size: 10px;
    font-weight: 700;
    margin-bottom: 3px;
    color: #111827;
}
.box, .textarea, .signature-box {
    border: 1px solid #bfc7d1;
    min-height: 28px;
    padding: 6px;
    background: #fff;
    box-sizing: border-box;
    color: #111827;
}
.textarea {
    min-height: 80px;
    white-space: pre-wrap;
}
.signature-box {
    min-height: 135px;
    overflow: hidden;
}
.signature-box img {
    display: block;
    max-width: 180px;
    max-height: 60px;
    width: auto;
    height: auto;
    object-fit: contain;
    margin-bottom: 8px;
}

/* NEW CSS for highlighting remarks inside signature box */
.remarks-highlight {
    background-color: #fffde7;
    border-left: 3px solid #f6d365;
    padding: 6px 10px;
    margin-bottom: 10px;
    font-size: 11px;
    font-style: italic;
    color: #333;
    border-radius: 2px;
}

.approval-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    border: 1px solid #cfd8e3;
    border-top: none;
    padding: 8px;
}
.audit-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
    color: #111827;
}
.audit-table th, .audit-table td {
    border: 1px solid #bfc7d1;
    padding: 5px;
    vertical-align: top;
}
.audit-table th {
    background: #eaf0f6;
}

/* =========================================
   DARK MODE SUPPORT
   ========================================= */
html[data-theme="dark"] .print-page {
    background: #111827;
    border-color: #334155;
}

html[data-theme="dark"] .print-wrap {
    background: #0f172a;
}

html[data-theme="dark"] .page-sheet {
    background: #1e293b;
    color: #f8fafc;
    box-shadow: 0 4px 16px rgba(0,0,0,0.5);
}

html[data-theme="dark"] .workflow-matrix {
    background: #1e293b;
    border-color: #334155;
}

html[data-theme="dark"] .matrix-card {
    background: #0f172a;
    border-color: #334155;
}

html[data-theme="dark"] .matrix-card.pending {
    background: #0f172a;
    border-color: #334155;
}

html[data-theme="dark"] .matrix-card.approved {
    background: #064e3b;
    border-color: #059669;
}

html[data-theme="dark"] .matrix-card.rejected {
    background: #7f1d1d;
    border-color: #dc2626;
}

html[data-theme="dark"] .matrix-card.completed {
    background: #1e3a8a;
    border-color: #2563eb;
}

html[data-theme="dark"] .matrix-title {
    color: #f8fafc;
}

html[data-theme="dark"] .matrix-status {
    background: #334155;
    color: #f8fafc;
}

html[data-theme="dark"] .matrix-card.approved .matrix-status {
    background: #10b981;
}

html[data-theme="dark"] .matrix-card.rejected .matrix-status {
    background: #ef4444;
}

html[data-theme="dark"] .matrix-card.completed .matrix-status {
    background: #3b82f6;
}

html[data-theme="dark"] .matrix-meta {
    color: #cbd5e1;
}

html[data-theme="dark"] .topbar {
    background: #1e293b;
    border: 1px solid #334155;
}

html[data-theme="dark"] .topbar-text small {
    color: #94a3b8;
}

html[data-theme="dark"] .ref-line {
    background: #334155;
    border-color: #475569;
    color: #f8fafc;
}

html[data-theme="dark"] .section-title {
    background: #334155;
    color: #f8fafc;
}

html[data-theme="dark"] .grid-2, 
html[data-theme="dark"] .grid-4, 
html[data-theme="dark"] .approval-grid {
    border-color: #334155;
}

html[data-theme="dark"] .field label {
    color: #cbd5e1;
}

html[data-theme="dark"] .box, 
html[data-theme="dark"] .textarea, 
html[data-theme="dark"] .signature-box {
    background: #0f172a;
    border-color: #334155;
    color: #f8fafc;
}

  html[data-theme="dark"] .remarks-highlight {
    background-color: #334155;
    border-left-color: #f59e0b;
    color: #f8fafc;
}

html[data-theme="dark"] .meta-line {
    border-top-color: #334155;
    color: #94a3b8;
}

html[data-theme="dark"] .remarks-text {
    color: #cbd5e1;
}

html[data-theme="dark"] .audit-table th {
    background: #334155;
    color: #f8fafc;
    border-color: #475569;
}

html[data-theme="dark"] .audit-table td {
    background: #1e293b;
    color: #cbd5e1;
    border-color: #475569;
}

/* Ensure printed output remains standard regardless of dark mode */
@media print {
    html[data-theme="dark"] .page-sheet,
    html[data-theme="dark"] .box,
    html[data-theme="dark"] .textarea,
    html[data-theme="dark"] .signature-box,
    html[data-theme="dark"] .workflow-matrix {
        background: #fff !important;
        color: #111827 !important;
        border-color: #bfc7d1 !important;
    }
    
    html[data-theme="dark"] .section-title {
        background: #1f4e79 !important;
        color: #fff !important;
    }
    
    html[data-theme="dark"] .matrix-title,
    html[data-theme="dark"] .matrix-meta,
    html[data-theme="dark"] .field label {
        color: #111827 !important;
    }
}

@media (max-width: 991px) {
    .print-wrap {
        padding: 0;
        background: transparent;
    }
    .page-sheet {
        width: 100%;
        min-height: auto;
        box-shadow: none;
        padding: 6px;
    }
    .grid-2, .grid-4, .approval-grid {
        grid-template-columns: 1fr;
    }
    .topbar-inner {
        flex-direction: column;
    }
    .topbar-logo {
        width: 100%;
    }
    .matrix-grid-5,
    .matrix-grid-6 {
        grid-template-columns: 1fr 1fr;
    }
}
@media print {
    .page-topbar,
    .main-header,
    .top-mobile-bar,
    .sidebar,
    .sidebar-overlay,
    .page-actions {
        display: none !important;
    }
    .main-content {
        padding: 0 !important;
        margin: 0 !important;
    }
    .print-page,
    .print-wrap,
    .page-sheet {
        box-shadow: none !important;
        border: none !important;
        background: #fff !important;
    }
    body {
        background: #fff !important;
    }
    /* Ensure background color prints correctly for remarks */
    .remarks-highlight {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        background-color: #fffde7 !important;
         border-left: 3px solid #f6d365 !important;
        color: #333 !important;
    }
}
';

ob_start();
?>

<div class="print-page">
    <div class="print-wrap">
        <div class="page-sheet">
            <div class="topbar">
                <div class="topbar-inner">
                    <div class="topbar-logo">
                        <img src="<?php echo h(logo_url()); ?>" alt="Company Logo">
                    </div>
                    <div class="topbar-text">
                        <div class="topbar-title">Submit ICT Hardware Request</div>
                        <small>Request laptop, desktop, accessories, upgrades, repair, replacement and more</small>
                    </div>
                </div>
            </div>

            <div class="ref-line">
                <strong>Reference No:</strong> <?php echo h($request['ref_no'] ?? ''); ?> |
                <strong>Date of Request:</strong> <?php echo h(safe_date($request['date_of_request'] ?? '')); ?> |
                <strong>Status:</strong> <?php echo h(ucwords(str_replace('_', ' ', $workflow_status))); ?> |
                <strong>Flow:</strong> <?php echo h($is_new_joiner ? 'New Joiner' : 'Regular Employee'); ?>
            </div>

            <div class="section-title">Approval Flow Matrix</div>
            <div class="workflow-matrix">
                <div class="matrix-grid <?php echo $is_new_joiner ? 'matrix-grid-6' : 'matrix-grid-5'; ?>">
                    <?php
                    echo stage_box_html(
                        'Department Head',
                        $dept_stage_status,
                        $deptApproval['approved_at'] ?? '',
                        $deptApproval['approver_name'] ?? '',
                        $deptApproval['remarks'] ?? ''
                    );

                    if ($is_new_joiner) {
                        echo stage_box_html(
                            'HR Head',
                            $hr_stage_status,
                            $hrApproval['approved_at'] ?? '',
                            $hrApproval['approver_name'] ?? '',
                            $hrApproval['remarks'] ?? ''
                        );
                    }

                    echo stage_box_html(
                        'ICT Assessor',
                        $assessor_stage_status,
                        $assessorApproval['approved_at'] ?? ($assessmentDate ?? ''),
                        $assessorApproval['approver_name'] ?? ($assessmentBy ?? ''),
                        $assessorApproval['remarks'] ?? ($assessmentComment ?? '')
                    );

                    echo stage_box_html(
                        'ICT Infra Head',
                        $infra_stage_status,
                        $infraApproval['approved_at'] ?? '',
                        $infraApproval['approver_name'] ?? '',
                        $infraApproval['remarks'] ?? ''
                    );

                    echo stage_box_html(
                        'ICT Head',
                        $ict_head_stage_status,
                        $ictHeadApproval['approved_at'] ?? '',
                        $ictHeadApproval['approver_name'] ?? '',
                        $ictHeadApproval['remarks'] ?? ''
                    );

                    echo stage_box_html(
                        'Completed',
                        $completed_stage_status,
                        '',
                        '',
                        $completed_stage_status === 'completed' ? 'Workflow finished' : 'Waiting final completion'
                    );
                    ?>
                </div>
            </div>

            <div class="section-title">Request Type</div>
            <div class="grid-2">
                <div class="field">
                    <label>Request Category</label>
                    <div class="box"><?php echo h($request['request_type'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <label>Request For</label>
                    <div class="box"><?php echo h($request['requested_for_type'] ?? ''); ?></div>
                </div>
            </div>

            <div class="section-title">User Information</div>
            <div class="grid-4">
                <div class="field">
                    <label>Full Name</label>
                    <div class="box"><?php echo h($request['requested_for_name'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <label>Employee ID</label>
                    <div class="box"><?php echo h($request['requested_for_emp_id'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <label>Employee Status</label>
                    <div class="box"><?php echo h(($request['requested_for_status'] ?? '') ?: 'N/A'); ?></div>
                </div>
                <div class="field">
                    <label>Employee Category</label>
                    <div class="box"><?php echo h(($request['requested_for_category'] ?? '') ?: 'N/A'); ?></div>
                </div>

                <div class="field">
                    <label>Designation</label>
                    <div class="box"><?php echo h($request['requested_for_designation'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <label>Date of Joining</label>
                    <div class="box"><?php echo h(safe_date(($request['requested_for_joining_date'] ?? '') ?: ($request['date_of_joining'] ?? ''))); ?></div>
                </div>
                <div class="field">
                    <label>Department</label>
                    <div class="box"><?php echo h($request['requested_for_department'] ?? ''); ?></div>
                </div>
                <div class="field">
                    <label>Place of Posting</label>
                    <div class="box"><?php echo h(($request['requested_for_place_posting'] ?? '') ?: ($request['place_of_posting'] ?? '')); ?></div>
                </div>
            </div>

            <div class="section-title">Requirement Section</div>
            <div class="grid-2">
                <div class="field">
                    <label>Request For</label>
                    <div class="box"><?php echo h($requestForText); ?></div>
                </div>

                <div class="field">
                    <label>Purpose / Work Function</label>
                    <div class="textarea"><?php echo h($request['purpose_text'] ?? ''); ?></div>
                </div>

                <div class="field">
                    <label>Request Reason</label>
                    <div class="box"><?php echo h($requestReasonText); ?></div>
                </div>

                <div class="field">
                    <label>Reason Details</label>
                    <div class="textarea"><?php echo h(($request['reason_details'] ?? '') ?: ($request['reason_text'] ?? '')); ?></div>
                </div>
            </div>

            <div class="section-title">Request Raised By / Recommendations</div>
            <div class="grid-2">
                <div class="field">
                    <label>Request Raised By</label>
                    <div class="box">
                        <strong>Name:</strong> <?php echo h($request['requester_name'] ?? ''); ?><br>
                        <strong>Employee ID:</strong> <?php echo h($request['requester_emp_id'] ?? ''); ?><br>
                        <strong>Designation:</strong> <?php echo h($request['requester_designation'] ?? ''); ?><br>
                        <strong>Department:</strong> <?php echo h($request['requester_department'] ?? ''); ?><br>
                        <strong>Email:</strong> <?php echo h($request['requester_email'] ?? ''); ?>
                    </div>

                    <div class="signature-box" style="margin-top:8px;">
                        <?php if (!empty($requester_signature_file)): ?>
                            <img src="<?php echo h(sig_url($requester_signature_file)); ?>" alt="Requester Signature">
                        <?php endif; ?>

                        <div class="meta-line">
                            <strong>Name:</strong> <?php echo h($request['requester_name'] ?? ''); ?><br>
                            <strong>Designation:</strong> <?php echo h(($request['requester_designation'] ?? '') ?: ($requesterSig['designation'] ?? '')); ?><br>
                            <strong>Department:</strong> <?php echo h(($request['requester_department'] ?? '') ?: ($requesterSig['department'] ?? '')); ?><br>
                            <strong>Status:</strong> Submitted
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label>Dept. Head Recommendation</label>
                    <div class="signature-box">
                        <?php if ($deptApproval && !empty($deptApproval['remarks'])): ?>
                            <div class="remarks-highlight">
                                <strong>Remarks:</strong> <?php echo nl2br(h($deptApproval['remarks'])); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($dept_signature_file)): ?>
                            <img src="<?php echo h(sig_url($dept_signature_file)); ?>" alt="Dept Head Signature">
                        <?php endif; ?>

                        <?php if ($deptApproval): ?>
                            <div class="meta-line">
                                <strong>Name:</strong> <?php echo h($deptApproval['approver_name'] ?? ''); ?><br>
                                <strong>Designation:</strong> <?php echo h($deptSig['designation'] ?? ''); ?><br>
                                <strong>Department:</strong> <?php echo h($deptSig['department'] ?? ''); ?><br>
                                <strong>Status:</strong> <?php echo h($deptApproval['approval_status'] ?? ''); ?><br>
                                <strong>Date:</strong> <?php echo h(safe_date($deptApproval['approved_at'] ?? '', 'd M Y, h:i A')); ?>
                            </div>
                        <?php else: ?>
                            Pending
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if ($is_new_joiner): ?>
                <div class="section-title">HR Approval Section</div>
                <div class="approval-grid">
                    <div class="field">
                        <label>Approved by HR Head</label>
                        <div class="signature-box">
                            <?php if ($hrApproval && !empty($hrApproval['remarks'])): ?>
                                <div class="remarks-highlight">
                                    <strong>Remarks:</strong> <?php echo nl2br(h($hrApproval['remarks'])); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($hr_signature_file)): ?>
                                <img src="<?php echo h(sig_url($hr_signature_file)); ?>" alt="HR Signature">
                            <?php endif; ?>

                            <?php if ($hrApproval): ?>
                                <div class="meta-line">
                                    <strong>Name:</strong> <?php echo h($hrApproval['approver_name'] ?? ''); ?><br>
                                    <strong>Designation:</strong> <?php echo h($hrSig['designation'] ?? ''); ?><br>
                                    <strong>Department:</strong> <?php echo h($hrSig['department'] ?? ''); ?><br>
                                    <strong>Status:</strong> <?php echo h($hrApproval['approval_status'] ?? ''); ?><br>
                                    <strong>Date:</strong> <?php echo h(safe_date($hrApproval['approved_at'] ?? '', 'd M Y, h:i A')); ?>
                                </div>
                            <?php else: ?>
                                Pending
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="section-title">ICT Assessment Section</div>
              <div class="approval-grid" style="grid-template-columns: 1fr;"> <!-- Made it single column like the top -->
                  <div class="field">
                      <label>Assessment Details / Approval</label>
                      <div class="signature-box">
                          
                          <?php if (!empty($assessmentComment)): ?>
                              <div class="remarks-highlight">
                                  <strong>Assessment:</strong><br>
                                  <?php echo nl2br(h($assessmentComment)); ?>
                              </div>
                          <?php else: ?>
                              <div class="remarks-text text-muted">No assessment comments provided.</div>
                          <?php endif; ?>
              
                          <?php if (!empty($assessor_signature_file)): ?>
                              <img src="<?php echo h(sig_url($assessor_signature_file)); ?>" alt="Assessor Signature">
                          <?php endif; ?>
              
                          <div class="meta-line">
                              <strong>Assessor:</strong> <?php echo h($assessmentBy ?: ($assessorApproval['approver_name'] ?? '')); ?><br>
                              <strong>Designation:</strong> <?php echo h($assessmentDesignation); ?><br>
                              <strong>Department:</strong> <?php echo h($assessmentDepartment); ?><br>
                              <strong>Date:</strong> <?php echo h(safe_date($assessmentDate ?: ($assessorApproval['approved_at'] ?? ''), 'd M Y, h:i A')); ?>
                          </div>
                      </div>
                  </div>
              </div>

            <div class="section-title">Final Approval Section</div>
              <div class="approval-grid">
                  <div class="field">
                      <label>Approved by ICT Head (Infrastructure)</label>
                      <div class="signature-box">
                          <?php if ($infraApproval): ?>
                              <div class="remarks-highlight">
                                  <strong>Remarks:</strong><br>
                                  <?php echo nl2br(h($infraApproval['remarks'] ?? 'Approved')); ?>
                              </div>
                          <?php endif; ?>
              
                          <?php if (!empty($infra_signature_file)): ?>
                              <img src="<?php echo h(sig_url($infra_signature_file)); ?>" alt="ICT Infra Signature">
                          <?php endif; ?>
              
                          <?php if ($infraApproval): ?>
                              <div class="meta-line">
                                  <strong>Name:</strong> <?php echo h($infraApproval['approver_name'] ?? ''); ?><br>
                                  <strong>Designation:</strong> <?php echo h($infraSig['designation'] ?? ''); ?><br>
                                  <strong>Department:</strong> <?php echo h($infraSig['department'] ?? ''); ?><br>
                                  <strong>Status:</strong> <?php echo h($infraApproval['approval_status'] ?? ''); ?><br>
                                  <strong>Date:</strong> <?php echo h(safe_date($infraApproval['approved_at'] ?? '', 'd M Y, h:i A')); ?>
                              </div>
                          <?php else: ?>
                              <div class="meta-line">Pending</div>
                          <?php endif; ?>
                      </div>
                  </div>
              
                  <div class="field">
                      <label>Approved by ICT Head</label>
                      <div class="signature-box">
                          <?php if ($ictHeadApproval): ?>
                              <div class="remarks-highlight">
                                  <strong>Remarks:</strong><br>
                                  <?php echo nl2br(h($ictHeadApproval['remarks'] ?? 'Approved')); ?>
                              </div>
                          <?php endif; ?>
              
                          <?php if (!empty($ict_head_signature_file)): ?>
                              <img src="<?php echo h(sig_url($ict_head_signature_file)); ?>" alt="ICT Head Signature">
                          <?php endif; ?>
              
                          <?php if ($ictHeadApproval): ?>
                              <div class="meta-line">
                                  <strong>Name:</strong> <?php echo h($ictHeadApproval['approver_name'] ?? ''); ?><br>
                                  <strong>Designation:</strong> <?php echo h($ictHeadSig['designation'] ?? ''); ?><br>
                                  <strong>Department:</strong> <?php echo h($ictHeadSig['department'] ?? ''); ?><br>
                                  <strong>Status:</strong> <?php echo h($ictHeadApproval['approval_status'] ?? ''); ?><br>
                                  <strong>Date:</strong> <?php echo h(safe_date($ictHeadApproval['approved_at'] ?? '', 'd M Y, h:i A')); ?>
                              </div>
                          <?php else: ?>
                              <div class="meta-line">Pending</div>
                          <?php endif; ?>
                      </div>
                  </div>
              </div>

            <div class="section-title">Approval Audit Trail</div>
            <div style="border:1px solid #cfd8e3; border-top:none; padding:8px;">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>SL</th>
                            <th>Action By</th>
                            <th>Stage</th>
                            <th>Status</th>
                            <th>Remarks</th>
                            <th>Date Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($history)): ?>
                            <?php foreach ($history as $i => $row): ?>
                                <tr>
                                    <td><?php echo $i + 1; ?></td>
                                    <td><?php echo h($row['approver_name'] ?? ''); ?></td>
                                    <td><?php echo h(ucwords(str_replace('_', ' ', $row['approval_stage'] ?? ''))); ?></td>
                                    <td><?php echo h($row['approval_status'] ?? ''); ?></td>
                                    <td><?php echo nl2br(h($row['remarks'] ?? '')); ?></td>
                                    <td><?php echo h(safe_date($row['approved_at'] ?? '', 'd M Y, h:i A')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align:center;">No approval history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>