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

$request_id = (int)($_GET['id'] ?? 0);
if ($request_id <= 0) {
    echo "<script>alert('No request selected.'); window.location.href='approval_matrix.php';</script>";
    exit;
}

$user_role = trim($_SESSION['UserRole'] ?? 'user');
$user_name = trim($_SESSION['UserName'] ?? '');
$role_l = strtolower(trim(str_replace(' ', '_', $user_role)));
$is_super_admin = in_array($role_l, ['superadmin', 'admin']);

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
    die('Logged user ID not found. Please login again.');
}

/* approval history table */
$create_history_sql = "
CREATE TABLE IF NOT EXISTS hardware_request_approval_history (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT(11) NOT NULL,
    approver_id INT(11) DEFAULT NULL,
    approver_name VARCHAR(150) DEFAULT NULL,
    approval_stage VARCHAR(100) DEFAULT NULL,
    approval_status VARCHAR(100) DEFAULT NULL,
    action_type VARCHAR(50) DEFAULT NULL,
    from_stage VARCHAR(100) DEFAULT NULL,
    to_stage VARCHAR(100) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    approved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (request_id),
    INDEX (approver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
mysqli_query($conn, $create_history_sql);

/* request data */
$req_stmt = mysqli_prepare($conn, "SELECT * FROM hardware_requests WHERE id = ? LIMIT 1");
if (!$req_stmt) {
    die('Request query prepare failed: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($req_stmt, "i", $request_id);
mysqli_stmt_execute($req_stmt);
$req_result = mysqli_stmt_get_result($req_stmt);
$req = mysqli_fetch_assoc($req_result);
mysqli_stmt_close($req_stmt);

if (!$req) {
    die('Request not found.');
}

$current_stage = trim($req['current_stage'] ?? '');
$request_department = trim($req['requested_for_department'] ?? '');
$request_type = trim($req['request_type'] ?? '');

$has_permission = false;

/* permission check */
if ($is_super_admin) {
    $has_permission = true;
} elseif (in_array($role_l, ['approver', 'department_head'])) {
    if ($current_stage === 'department_head' && $logged_user_department === $request_department) {
        $has_permission = true;
    }
} elseif (in_array($role_l, ['hr', 'hr_head'])) {
    if ($current_stage === 'hr_head') {
        $has_permission = true;
    }
} elseif (in_array($role_l, ['ict_assessor'])) {
    if ($current_stage === 'ict_assessor') {
        $has_permission = true;
    }
} elseif (in_array($role_l, ['ict_approver_infra', 'ict_infra_head'])) {
    if ($current_stage === 'ict_infra_head') {
        $has_permission = true;
    }
} elseif ($role_l === 'ict_head') {
    if ($current_stage === 'ict_head') {
        $has_permission = true;
    }
}

if (!$has_permission) {
    die('You do not have permission to take action on this request.');
}

/* action submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action = trim($_POST['action_type']);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($remarks === '') {
        echo "<script>alert('Remarks is required.'); window.history.back();</script>";
        exit;
    }

    $next_stage = '';
    $next_status = '';
    $history_status = '';
    $success_msg = '';

    if ($action === 'approve') {

        if ($current_stage === 'department_head') {
            if ($request_type === 'new_joining') {
                $next_stage = 'hr_head';
            } else {
                $next_stage = 'ict_assessor';
            }
        } elseif ($current_stage === 'hr_head') {
            $next_stage = 'ict_assessor';
        } elseif ($current_stage === 'ict_assessor') {
            $next_stage = 'ict_infra_head';
        } elseif ($current_stage === 'ict_infra_head') {
            $next_stage = 'ict_head';
        } elseif ($current_stage === 'ict_head') {
            $next_stage = 'completed';
        }

        if ($next_stage === '') {
            echo "<script>alert('Invalid workflow stage for approval.'); window.location.href='approval_matrix.php';</script>";
            exit;
        }

        $next_status = ($next_stage === 'completed') ? 'approved_completed' : 'pending_' . $next_stage;
        $history_status = 'Approved';
        $success_msg = ($next_stage === 'completed')
            ? 'Request approved and completed successfully!'
            : 'Request approved and forwarded successfully!';

    } elseif ($action === 'return') {

        if ($current_stage === 'ict_head') {
            $next_stage = 'ict_infra_head';
        } elseif ($current_stage === 'ict_infra_head') {
            $next_stage = 'ict_assessor';
        } elseif ($current_stage === 'ict_assessor') {
            if ($request_type === 'new_joining') {
                $next_stage = 'hr_head';
            } else {
                $next_stage = 'department_head';
            }
        } elseif ($current_stage === 'hr_head') {
            $next_stage = 'department_head';
        } elseif ($current_stage === 'department_head') {
            $next_stage = 'returned_to_user';
        } else {
            echo "<script>alert('Invalid workflow stage for return.'); window.location.href='approval_matrix.php';</script>";
            exit;
        }

        $next_status = 'returned_by_' . $current_stage;
        $history_status = 'Returned';
        $success_msg = 'Request returned successfully.';

    } else {
        echo "<script>alert('Invalid action.'); window.location.href='approval_matrix.php';</script>";
        exit;
    }

    /* update request */
    $update_sql = "UPDATE hardware_requests
                   SET current_stage = ?, workflow_status = ?, updated_at = NOW()
                   WHERE id = ?";
    $up_stmt = mysqli_prepare($conn, $update_sql);
    if (!$up_stmt) {
        die('Update prepare failed: ' . mysqli_error($conn));
    }

    mysqli_stmt_bind_param($up_stmt, "ssi", $next_stage, $next_status, $request_id);

    if (!mysqli_stmt_execute($up_stmt)) {
        die('Failed to update request: ' . mysqli_error($conn));
    }
    mysqli_stmt_close($up_stmt);

    /* history insert */
    $hist_sql = "INSERT INTO hardware_request_approval_history
        (request_id, approver_id, approver_name, approval_stage, approval_status, action_type, from_stage, to_stage, remarks, approved_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

    $hist_stmt = mysqli_prepare($conn, $hist_sql);
    if (!$hist_stmt) {
        die('History prepare failed: ' . mysqli_error($conn));
    }

    $stage_for_history = $current_stage;
    $action_type = $action;
    $from_stage = $current_stage;
    $to_stage = $next_stage;

    mysqli_stmt_bind_param(
        $hist_stmt,
        "iisssssss",
        $request_id,
        $user_id,
        $user_name,
        $stage_for_history,
        $history_status,
        $action_type,
        $from_stage,
        $to_stage,
        $remarks
    );

    if (!mysqli_stmt_execute($hist_stmt)) {
        die('History insert failed: ' . mysqli_error($conn));
    }
    mysqli_stmt_close($hist_stmt);

    echo "<script>alert('" . addslashes($success_msg) . "'); window.location.href='approval_matrix.php';</script>";
    exit;
}

$page_title = 'Take Action - SCL AMS';
$page_header_icon = 'fas fa-check-circle';
$page_header_title = 'Request Action';
$page_header_subtitle = 'Approve or return a pending request';
$page_top_title = 'Request Action';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.request-action-card {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    overflow: hidden;
}
.request-action-header {
    background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
    color: #fff;
    padding: 18px 22px;
    font-size: 20px;
    font-weight: 700;
}
.request-action-body {
    padding: 22px;
}
.info-card {
    background: #f8fafc;
    border: 1px solid #dbeafe;
    border-radius: 12px;
    padding: 16px;
}
.info-item {
    margin-bottom: 10px;
}
.info-item strong {
    color: #0b2545;
}
.action-card {
    background: #fff7ed;
    border: 1px solid #fdba74;
    border-radius: 12px;
    padding: 18px;
}
html[data-theme='dark'] .request-action-card {
    background: #1f2937 !important;
    border-color: #374151 !important;
}
html[data-theme='dark'] .info-card {
    background: #111827 !important;
    border-color: #374151 !important;
    color: #f3f4f6 !important;
}
html[data-theme='dark'] .info-item strong {
    color: #93c5fd !important;
}
html[data-theme='dark'] .action-card {
    background: #3f2b17 !important;
    border-color: #92400e !important;
}
";

ob_start();
?>

<div class="request-action-card mb-4">
    <div class="request-action-header">
        <i class="fas fa-file-alt me-2"></i> Request Snapshot
    </div>
    <div class="request-action-body">
        <div class="info-card">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-item"><strong>Ref No:</strong> <?php echo h($req['ref_no'] ?? ''); ?></div>
                    <div class="info-item"><strong>Requester:</strong> <?php echo h($req['requester_name'] ?? 'N/A'); ?></div>
                    <div class="info-item"><strong>Requested For:</strong> <?php echo h($req['requested_for_name'] ?? 'N/A'); ?></div>
                    <div class="info-item"><strong>Department:</strong> <?php echo h($req['requested_for_department'] ?? 'N/A'); ?></div>
                </div>
                <div class="col-md-6">
                    <div class="info-item"><strong>Request Type:</strong> <?php echo h(ucwords(str_replace('_', ' ', $req['request_type'] ?? 'N/A'))); ?></div>
                    <div class="info-item"><strong>Current Stage:</strong> <?php echo h(ucwords(str_replace('_', ' ', $current_stage))); ?></div>
                    <div class="info-item"><strong>Status:</strong> <?php echo h(ucwords(str_replace('_', ' ', $req['workflow_status'] ?? 'N/A'))); ?></div>
                    <div class="info-item"><strong>Submitted:</strong> <?php echo h(safe_date($req['created_at'] ?? null)); ?></div>
                </div>
                <div class="col-12 mt-2">
                    <a href="request_view.php?id=<?php echo (int)$request_id; ?>" target="_blank" class="btn btn-sm btn-info text-dark fw-bold">
                        <i class="fas fa-eye me-1"></i> View Full Request
                    </a>
                    <a href="request_print.php?id=<?php echo (int)$request_id; ?>" target="_blank" class="btn btn-sm btn-secondary fw-bold">
                        <i class="fas fa-print me-1"></i> Print Request
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="request-action-card">
    <div class="request-action-header" style="background:linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
        <i class="fas fa-pen-nib me-2"></i> Action Panel
    </div>
    <div class="request-action-body">
        <div class="action-card">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-bold">Comments / Remarks *</label>
                    <textarea name="remarks" class="form-control" rows="4" required placeholder="Write your approval/return remarks here..."></textarea>
                </div>

                <div class="d-flex gap-2 flex-wrap mt-4">
                    <button type="submit" name="action_type" value="approve" class="btn btn-success fw-bold px-4">
                        <i class="fas fa-check me-1"></i> Approve & Proceed
                    </button>

                    <?php if ($is_super_admin || in_array($role_l, ['ict_head', 'ict_assessor', 'ict_approver_infra', 'ict_infra_head', 'hr', 'hr_head', 'approver', 'department_head'])): ?>
                        <button type="submit" name="action_type" value="return" class="btn btn-danger fw-bold px-4">
                            <i class="fas fa-undo me-1"></i> Return
                        </button>
                    <?php endif; ?>

                    <a href="approval_matrix.php" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>