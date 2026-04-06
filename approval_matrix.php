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

function safe_date($value, $format = 'd M Y, h:i A') {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : 'N/A';
}

function stage_label($stage) {
    $stage = trim((string)$stage);

    $map = [
        'department_head' => 'Department Head Approval',
        'hr_head' => 'HR Approval',
        'ict_assessor' => 'ICT Assessment',
        'ict_infra_head' => 'ICT Infra Approval',
        'ict_head' => 'Final ICT Approval',
        'completed' => 'Completed',
        'returned_to_user' => 'Returned To User'
    ];

    return $map[$stage] ?? ucwords(str_replace('_', ' ', $stage));
}

function workflow_label($workflow_status, $current_stage) {
    $workflow_status = strtolower(trim((string)$workflow_status));
    $current_stage = trim((string)$current_stage);

    if ($workflow_status === 'approved_completed' || $current_stage === 'completed') {
        return 'Approved / Completed';
    }

    if (strpos($workflow_status, 'returned') !== false) {
        return 'Returned';
    }

    if (strpos($workflow_status, 'pending_') === 0) {
        return 'Pending at ' . stage_label($current_stage);
    }

    return ucwords(str_replace('_', ' ', $workflow_status));
}

$user_name = trim($_SESSION['UserName'] ?? '');
$user_role_raw = trim($_SESSION['UserRole'] ?? 'user');
$user_role = strtolower(trim(str_replace(' ', '_', $user_role_raw)));
$is_super_admin = in_array($user_role, ['superadmin', 'admin']);

$user_id = (int)($_SESSION['UserID'] ?? 0);
$logged_user_department = '';

if ($user_name !== '') {
    $user_stmt = mysqli_prepare($conn, "SELECT id, department FROM users WHERE username = ? LIMIT 1");
    if ($user_stmt) {
        mysqli_stmt_bind_param($user_stmt, "s", $user_name);
        mysqli_stmt_execute($user_stmt);
        $user_res = mysqli_stmt_get_result($user_stmt);
        $user_row = mysqli_fetch_assoc($user_res);

        if ($user_row) {
            if ($user_id <= 0) {
                $user_id = (int)$user_row['id'];
            }
            $logged_user_department = trim($user_row['department'] ?? '');
        }
        mysqli_stmt_close($user_stmt);
    }
}

if ($user_id <= 0) {
    die('Logged user not found. Please login again.');
}

// Ensure the correct tab stays open when paginating
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';

// ----------------------------------------------------------------------
// QUERY 1: PENDING APPROVALS (WITH PAGINATION)
// ----------------------------------------------------------------------
$p_limit = 10;
$p_page = isset($_GET['p_page']) && is_numeric($_GET['p_page']) ? (int)$_GET['p_page'] : 1;
$p_offset = ($p_page - 1) * $p_limit;

$where_sql = "";
$params = [];
$types = "";

if ($is_super_admin) {
    $where_sql = "WHERE current_stage <> 'completed'";
} elseif ($user_role === 'approver' || $user_role === 'department_head') {
    $where_sql = "WHERE current_stage = ? AND requested_for_department = ?";
    $params = ['department_head', $logged_user_department];
    $types = "ss";
} elseif ($user_role === 'hr' || $user_role === 'hr_head') {
    $where_sql = "WHERE current_stage = ?";
    $params = ['hr_head'];
    $types = "s";
} elseif ($user_role === 'ict_assessor') {
    $where_sql = "WHERE current_stage = ?";
    $params = ['ict_assessor'];
    $types = "s";
} elseif ($user_role === 'ict_approver_infra' || $user_role === 'ict_infra_head') {
    $where_sql = "WHERE current_stage = ?";
    $params = ['ict_infra_head'];
    $types = "s";
} elseif ($user_role === 'ict_head') {
    $where_sql = "WHERE current_stage = ?";
    $params = ['ict_head'];
    $types = "s";
} else {
    $where_sql = "WHERE 1=0"; 
}

// Count total pending
$count_pending_sql = "SELECT COUNT(*) as total FROM hardware_requests hr $where_sql";
$stmt_count_p = mysqli_prepare($conn, $count_pending_sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count_p, $types, ...$params);
}
mysqli_stmt_execute($stmt_count_p);
$count_pending_res = mysqli_stmt_get_result($stmt_count_p);
$p_total_records = mysqli_fetch_assoc($count_pending_res)['total'] ?? 0;
$p_total_pages = ceil($p_total_records / $p_limit);

// Fetch Pending Records
$sql_pending = "
SELECT hr.* 
FROM hardware_requests hr 
$where_sql 
ORDER BY hr.created_at DESC 
LIMIT ? OFFSET ?";
$stmt_pending = mysqli_prepare($conn, $sql_pending);
$p_params = $params;
$p_params[] = $p_limit;
$p_params[] = $p_offset;
$p_types = $types . "ii";
mysqli_stmt_bind_param($stmt_pending, $p_types, ...$p_params);
mysqli_stmt_execute($stmt_pending);
$result_pending = mysqli_stmt_get_result($stmt_pending);


// ----------------------------------------------------------------------
// QUERY 2: ACTIONED BY ME (WITH PAGINATION)
// ----------------------------------------------------------------------
$a_limit = 10;
$a_page = isset($_GET['a_page']) && is_numeric($_GET['a_page']) ? (int)$_GET['a_page'] : 1;
$a_offset = ($a_page - 1) * $a_limit;

// Count total actioned
$count_actioned_sql = "
SELECT COUNT(DISTINCT hr.id) as total 
FROM hardware_requests hr
INNER JOIN hardware_request_approval_history h ON h.request_id = hr.id
WHERE h.approver_name = ? OR h.approver_id = ?";
$stmt_count_a = mysqli_prepare($conn, $count_actioned_sql);
mysqli_stmt_bind_param($stmt_count_a, "si", $user_name, $user_id);
mysqli_stmt_execute($stmt_count_a);
$count_actioned_res = mysqli_stmt_get_result($stmt_count_a);
$a_total_records = mysqli_fetch_assoc($count_actioned_res)['total'] ?? 0;
$a_total_pages = ceil($a_total_records / $a_limit);

// Fetch Actioned Records
$sql_actioned = "
SELECT hr.*, MAX(h.approved_at) as last_action_date, 
(SELECT approval_status FROM hardware_request_approval_history h2 WHERE h2.request_id = hr.id AND (h2.approver_name = ? OR h2.approver_id = ?) ORDER BY h2.id DESC LIMIT 1) as my_action
FROM hardware_requests hr
INNER JOIN hardware_request_approval_history h ON h.request_id = hr.id
WHERE h.approver_name = ? OR h.approver_id = ?
GROUP BY hr.id
ORDER BY last_action_date DESC
LIMIT ? OFFSET ?
";
$stmt_actioned = mysqli_prepare($conn, $sql_actioned);
mysqli_stmt_bind_param($stmt_actioned, "sissii", $user_name, $user_id, $user_name, $user_id, $a_limit, $a_offset);
mysqli_stmt_execute($stmt_actioned);
$result_actioned = mysqli_stmt_get_result($stmt_actioned);


// ----------------------------------------------------------------------
// PAGE RENDERING
// ----------------------------------------------------------------------
$page_title = 'Approval Matrix - SCL AMS';
$page_header_icon = 'fas fa-check-double';
$page_header_title = 'Approval Matrix';
$page_header_subtitle = 'Pending requests and your approval history';
$page_top_title = 'Approval Matrix';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.nav-tabs .nav-link {
    color: #475569;
    font-weight: 600;
    border: none;
    border-bottom: 3px solid transparent;
    padding: 12px 20px;
    background: transparent;
}
.nav-tabs .nav-link:hover {
    border-color: transparent;
    color: #0b2545;
}
.nav-tabs .nav-link.active {
    color: #0b2545;
    background-color: transparent;
    border-color: transparent;
    border-bottom: 3px solid #0b2545;
}
.action-btn-group {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    flex-wrap: nowrap;
}
.action-btn-group .btn {
    min-width: 38px;
    height: 38px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
}
.action-btn-group .btn i {
    margin: 0 !important;
}
.approval-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.approval-card-header {
    background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
    color: #fff;
    padding: 18px 22px;
    font-size: 20px;
    font-weight: 700;
}
.approval-card-body {
    padding: 20px;
}
.badge-stage {
    background: #e0f2fe;
    color: #075985;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
}
.badge-type {
    background: #ede9fe;
    color: #5b21b6;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
}
.badge-status {
    background: #fef3c7;
    color: #92400e;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
}
.pagination-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 15px;
    flex-wrap: wrap;
    gap: 10px;
}
.page-info {
    font-size: 13px;
    color: #64748b;
}

html[data-theme='dark'] .approval-card { background: #1f2937 !important; border-color: #374151 !important; }
html[data-theme='dark'] .table { color: #f3f4f6 !important; }
html[data-theme='dark'] .table td, html[data-theme='dark'] .table th { border-color: #374151 !important; }
html[data-theme='dark'] .table-light th { background: #111827 !important; color: #f3f4f6 !important; }
html[data-theme='dark'] .nav-tabs .nav-link { color: #cbd5e1; }
html[data-theme='dark'] .nav-tabs .nav-link.active { color: #93c5fd; border-bottom: 3px solid #3b82f6; }
html[data-theme='dark'] .nav-tabs .nav-link:hover { color: #e2e8f0; }
html[data-theme='dark'] .btn-outline-dark { color: #f8fafc !important; border-color: #f8fafc !important; }
html[data-theme='dark'] .btn-outline-dark:hover { background-color: #f8fafc !important; color: #0f172a !important; }
html[data-theme='dark'] .page-info { color: #9ca3af; }
";

ob_start();
?>

<div class="approval-card">
    <div class="approval-card-header d-flex justify-content-between align-items-center">
        <div><i class="fas fa-tasks me-2"></i> Action Center</div>
    </div>
    
    <div class="approval-card-body p-0">
        <!-- Tabs Nav -->
        <ul class="nav nav-tabs px-3 pt-2" id="approvalTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab == 'pending' ? 'active' : ''; ?>" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="<?php echo $active_tab == 'pending' ? 'true' : 'false'; ?>">
                    <i class="fas fa-clock me-1"></i> Pending Actions 
                    <span class="badge bg-danger ms-1 rounded-pill"><?php echo $p_total_records; ?></span>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $active_tab == 'actioned' ? 'active' : ''; ?>" id="actioned-tab" data-bs-toggle="tab" data-bs-target="#actioned" type="button" role="tab" aria-controls="actioned" aria-selected="<?php echo $active_tab == 'actioned' ? 'true' : 'false'; ?>">
                    <i class="fas fa-history me-1"></i> Actioned by Me
                </button>
            </li>
        </ul>

        <!-- Tabs Content -->
        <div class="tab-content p-4" id="approvalTabsContent">
            
            <!-- Pending Tab -->
            <div class="tab-pane fade <?php echo $active_tab == 'pending' ? 'show active' : ''; ?>" id="pending" role="tabpanel" aria-labelledby="pending-tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL</th>
                                <th>Ref No</th>
                                <th>Requester</th>
                                <th>Requested For</th>
                                <th>Department</th>
                                <th>Request Type</th>
                                <th>Current Stage</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_pending && mysqli_num_rows($result_pending) > 0): ?>
                                <?php $sl_p = $p_offset + 1; while ($row = mysqli_fetch_assoc($result_pending)): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $sl_p++; ?></td>
                                        <td><strong><?php echo h($row['ref_no']); ?></strong></td>
                                        <td><?php echo h($row['requester_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo h($row['requested_for_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo h($row['requested_for_department'] ?? 'N/A'); ?></td>
                                        <td><span class="badge-type"><?php echo h(ucwords(str_replace('_', ' ', $row['request_type'] ?? 'N/A'))); ?></span></td>
                                        <td><span class="badge-stage"><?php echo h(stage_label($row['current_stage'] ?? '')); ?></span></td>
                                        <td><span class="badge-status"><?php echo h(workflow_label($row['workflow_status'] ?? '', $row['current_stage'] ?? '')); ?></span></td>
                                        <td><?php echo h(safe_date($row['created_at'] ?? null)); ?></td>
                                        <td class="text-center">
                                            <div class="action-btn-group">
                                                <a href="request_print.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank" title="Print Request"><i class="fas fa-print"></i></a>
                                                <?php if (($row['current_stage'] ?? '') === 'ict_assessor'): ?>
                                                    <a href="request_assessment.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-warning text-dark" title="Assess"><i class="fas fa-clipboard-check"></i></a>
                                                <?php else: ?>
                                                    <a href="request_action.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-success" title="Approve / Action"><i class="fas fa-check"></i></a>
                                                <?php endif; ?>
                                                <?php if ($is_super_admin): ?>
                                                    <a href="request_delete.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure you want to delete this request?');"><i class="fas fa-trash"></i></a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="10" class="text-center text-muted py-4">No pending requests found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pending Pagination -->
                <?php if ($p_total_pages > 1): ?>
                <div class="pagination-wrap">
                    <div class="page-info">Showing <?php echo $p_offset + 1; ?> to <?php echo min($p_offset + $p_limit, $p_total_records); ?> of <?php echo $p_total_records; ?> entries</div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($p_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?tab=pending&p_page=<?php echo $p_page - 1; ?>&a_page=<?php echo $a_page; ?>">Prev</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Prev</span></li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $p_page - 2); $i <= min($p_total_pages, $p_page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $p_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?tab=pending&p_page=<?php echo $i; ?>&a_page=<?php echo $a_page; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($p_page < $p_total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?tab=pending&p_page=<?php echo $p_page + 1; ?>&a_page=<?php echo $a_page; ?>">Next</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Next</span></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actioned By Me Tab -->
            <div class="tab-pane fade <?php echo $active_tab == 'actioned' ? 'show active' : ''; ?>" id="actioned" role="tabpanel" aria-labelledby="actioned-tab">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light text-center">
                            <tr>
                                <th>SL</th>
                                <th>Ref No</th>
                                <th>Requester</th>
                                <th>Requested For</th>
                                <th>Department</th>
                                <th>My Action</th>
                                <th>Overall Status</th>
                                <th>Action Date</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_actioned && mysqli_num_rows($result_actioned) > 0): ?>
                                <?php $sl_a = $a_offset + 1; while ($row = mysqli_fetch_assoc($result_actioned)): ?>
                                    <tr>
                                        <td class="text-center"><?php echo $sl_a++; ?></td>
                                        <td><strong><?php echo h($row['ref_no']); ?></strong></td>
                                        <td><?php echo h($row['requester_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo h($row['requested_for_name'] ?? 'N/A'); ?></td>
                                        <td><?php echo h($row['requested_for_department'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php 
                                            $my_action = strtolower($row['my_action'] ?? '');
                                            if($my_action == 'approved' || $my_action == 'assessed') {
                                                echo '<span class="badge bg-success">'.ucfirst($my_action).'</span>';
                                            } elseif(strpos($my_action, 'return') !== false) {
                                                echo '<span class="badge bg-warning text-dark">Returned</span>';
                                            } elseif($my_action == 'rejected') {
                                                echo '<span class="badge bg-danger">Rejected</span>';
                                            } else {
                                                echo '<span class="badge bg-secondary">'.ucfirst($my_action).'</span>';
                                            }
                                            ?>
                                        </td>
                                        <td><span class="badge-status"><?php echo h(workflow_label($row['workflow_status'] ?? '', $row['current_stage'] ?? '')); ?></span></td>
                                        <td><?php echo h(safe_date($row['last_action_date'] ?? null)); ?></td>
                                        <td class="text-center">
                                            <div class="action-btn-group">
                                                <a href="request_print.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-outline-dark" target="_blank" title="Print Request"><i class="fas fa-print"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">You have not actioned any requests yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Actioned Pagination -->
                <?php if ($a_total_pages > 1): ?>
                <div class="pagination-wrap">
                    <div class="page-info">Showing <?php echo $a_offset + 1; ?> to <?php echo min($a_offset + $a_limit, $a_total_records); ?> of <?php echo $a_total_records; ?> entries</div>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($a_page > 1): ?>
                                <li class="page-item"><a class="page-link" href="?tab=actioned&p_page=<?php echo $p_page; ?>&a_page=<?php echo $a_page - 1; ?>">Prev</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Prev</span></li>
                            <?php endif; ?>

                            <?php for ($i = max(1, $a_page - 2); $i <= min($a_total_pages, $a_page + 2); $i++): ?>
                                <li class="page-item <?php echo ($i == $a_page) ? 'active' : ''; ?>">
                                    <a class="page-link" href="?tab=actioned&p_page=<?php echo $p_page; ?>&a_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($a_page < $a_total_pages): ?>
                                <li class="page-item"><a class="page-link" href="?tab=actioned&p_page=<?php echo $p_page; ?>&a_page=<?php echo $a_page + 1; ?>">Next</a></li>
                            <?php else: ?>
                                <li class="page-item disabled"><span class="page-link">Next</span></li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>