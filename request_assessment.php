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
    echo "<script>alert('No request selected for assessment.'); window.location.href='approval_matrix.php';</script>";
    exit;
}

$user_name = trim($_SESSION['UserName'] ?? '');
$user_role = trim($_SESSION['UserRole'] ?? 'user');

/* FIX: normalize role */
$role_l = strtolower(trim(str_replace(' ', '_', $user_role)));
$is_super_admin = in_array($role_l, ['superadmin', 'admin']);

/* Resolve user id safely */
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

/* Ensure tables exist */
$create_assessment_sql = "
CREATE TABLE IF NOT EXISTS hardware_request_assessment (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT(11) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    allocation_date DATE DEFAULT NULL,
    lifetime_years INT(11) DEFAULT NULL,
    configuration_details TEXT DEFAULT NULL,
    source VARCHAR(50) DEFAULT NULL,
    approx_cost DECIMAL(10,2) DEFAULT NULL,
    assessor_comment TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
mysqli_query($conn, $create_assessment_sql);

$create_history_sql = "
CREATE TABLE IF NOT EXISTS hardware_request_approval_history (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    request_id INT(11) NOT NULL,
    approver_id INT(11) DEFAULT NULL,
    approver_name VARCHAR(150) DEFAULT NULL,
    approval_stage VARCHAR(100) DEFAULT NULL,
    approval_status VARCHAR(100) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    approved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (request_id),
    INDEX (approver_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
mysqli_query($conn, $create_history_sql);

/* Fetch request */
$req_stmt = mysqli_prepare($conn, "SELECT * FROM hardware_requests WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($req_stmt, "i", $request_id);
mysqli_stmt_execute($req_stmt);
$req_result = mysqli_stmt_get_result($req_stmt);
$request = mysqli_fetch_assoc($req_result);

if (!$request) {
    die('Request not found.');
}

/* Permission check
   SuperAdmin/Admin always allowed
   ICT Assessor allowed
*/
if (!$is_super_admin && $role_l !== 'ict_assessor') {
    die('You do not have permission to assess this request.');
}

if (($request['current_stage'] ?? '') !== 'ict_assessor') {
    echo "<script>alert('This request is not currently at the ICT Assessment stage.'); window.location.href='approval_matrix.php';</script>";
    exit;
}

/* Handle form submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_assessment') {

    $item_names = $_POST['item_name'] ?? [];
    $alloc_dates = $_POST['allocation_date'] ?? [];
    $lifetimes = $_POST['lifetime'] ?? [];
    $configs = $_POST['config_details'] ?? [];
    $sources = $_POST['source'] ?? [];
    $costs = $_POST['approx_cost'] ?? [];
    $assessor_comment = trim($_POST['assessor_overall_comment'] ?? '');

    if (empty($item_names) || trim($item_names[0] ?? '') === '') {
        echo "<script>alert('At least one item is required.'); window.history.back();</script>";
        exit;
    }

    if ($assessor_comment === '') {
        echo "<script>alert('Overall remarks is required.'); window.history.back();</script>";
        exit;
    }

    $insert_sql = "INSERT INTO hardware_request_assessment
        (request_id, item_name, allocation_date, lifetime_years, configuration_details, source, approx_cost, assessor_comment)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $insert_sql);

    if (!$stmt) {
        die('Assessment insert prepare failed: ' . mysqli_error($conn));
    }

    for ($i = 0; $i < count($item_names); $i++) {
        $iname = trim($item_names[$i] ?? '');
        if ($iname === '') {
            continue;
        }

        $adate = trim($alloc_dates[$i] ?? '');
        $adate = ($adate !== '') ? $adate : null;

        $life = (int)($lifetimes[$i] ?? 0);
        $conf = trim($configs[$i] ?? '');
        $src = trim($sources[$i] ?? '');
        $cst = (float)($costs[$i] ?? 0);

        mysqli_stmt_bind_param(
            $stmt,
            "ississds",
            $request_id,
            $iname,
            $adate,
            $life,
            $conf,
            $src,
            $cst,
            $assessor_comment
        );
        mysqli_stmt_execute($stmt);
    }

    $next_stage = 'ict_infra_head';
    $next_status = 'pending_ict_infra_head';

    $update_sql = "UPDATE hardware_requests
                   SET current_stage = ?, workflow_status = ?, updated_at = NOW()
                   WHERE id = ?";
    $up_stmt = mysqli_prepare($conn, $update_sql);
    mysqli_stmt_bind_param($up_stmt, "ssi", $next_stage, $next_status, $request_id);
    mysqli_stmt_execute($up_stmt);

$history_status = 'Assessed';
$action_type = 'assess';
$from_stage = $request['current_stage'];
$to_stage = $next_stage;

$hist_sql = "INSERT INTO hardware_request_approval_history
    (request_id, approver_id, approver_name, approval_stage, approval_status, action_type, from_stage, to_stage, remarks, approved_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

$hist_stmt = mysqli_prepare($conn, $hist_sql);
$stage = $request['current_stage'];

mysqli_stmt_bind_param(
    $hist_stmt,
    "iisssssss",
    $request_id,
    $user_id,
    $user_name,
    $stage,
    $history_status,
    $action_type,
    $from_stage,
    $to_stage,
    $assessor_comment
);
mysqli_stmt_execute($hist_stmt);

    echo "<script>alert('Assessment submitted successfully! Forwarded to ICT Infra Head.'); window.location.href='approval_matrix.php';</script>";
    exit;
}

$page_title = 'ICT Assessment - SCL AMS';
$page_header_icon = 'fas fa-laptop-code';
$page_header_title = 'ICT Assessment';
$page_header_subtitle = 'Assess and forward pending ICT hardware requests';
$page_top_title = 'ICT Assessment';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.card-header { background: #0b2545; color: white; font-weight: bold; }
.info-box { background: #f8fafc; border: 1px solid #dbeafe; padding: 15px; border-radius: 8px; margin-bottom: 15px; }
.dynamic-row { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 15px; position: relative; }
.remove-btn { position: absolute; top: 10px; right: 10px; color: red; cursor: pointer; font-size: 18px; border:none; background:none; }
html[data-theme='dark'] .dynamic-row { background: #1f2937 !important; border-color: #374151 !important; }
html[data-theme='dark'] .info-box { background: #111827 !important; border-color: #374151 !important; color:#f3f4f6; }
";

ob_start();
?>

<div class="card mb-4 shadow-sm border-0">
    <div class="card-header bg-dark text-white fw-bold">
        <i class="fas fa-file-alt me-2"></i> Request Snapshot (Ref: <?php echo h($request['ref_no']); ?>)
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-2">
                <strong>Requester:</strong> <?php echo h($request['requester_name']); ?>
            </div>
            <div class="col-md-6 mb-2">
                <strong>Requested For:</strong> <?php echo h($request['requested_for_name']); ?>
            </div>
            <div class="col-md-6 mb-2">
                <strong>Department:</strong> <?php echo h($request['requested_for_department']); ?>
            </div>
            <div class="col-md-6 mb-2">
                <strong>Date:</strong> <?php echo h(safe_date($request['created_at'], 'd M Y')); ?>
            </div>
            <div class="col-md-12 mt-3 p-3" style="background:#f8fafc; border:1px solid #dbeafe; border-radius:8px;">
                <strong>Purpose / Justification:</strong><br>
                <?php echo nl2br(h($request['purpose_text'] ?? '')); ?>
            </div>
            <div class="col-12 mt-3">
                <a href="request_view.php?id=<?php echo (int)$request_id; ?>" target="_blank" class="btn btn-sm btn-info text-dark fw-bold">
                    <i class="fas fa-eye"></i> View Full Request Details
                </a>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-info">
    <div class="card-header bg-info text-dark fw-bold">
        <i class="fas fa-laptop-code"></i> ICT Assessment Form
    </div>
    <div class="card-body">
        <form method="POST" id="assessmentForm">
            <input type="hidden" name="action" value="submit_assessment">

            <div id="dynamic-items-container">
                <div class="dynamic-row">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Item Name (e.g. Laptop/RAM) *</label>
                            <input type="text" name="item_name[]" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Allocation Date</label>
                            <input type="date" name="allocation_date[]" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Lifetime (Years)</label>
                            <input type="number" name="lifetime[]" class="form-control" min="1">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Configuration Details *</label>
                            <input type="text" name="config_details[]" class="form-control" placeholder="e.g. Core i5, 8GB RAM, 512GB SSD" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Source</label>
                            <select name="source[]" class="form-select">
                                <option value="In Stock">In Stock</option>
                                <option value="New Purchase">New Purchase</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Approx Cost</label>
                            <input type="number" name="approx_cost[]" class="form-control" placeholder="e.g. 50000">
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <button type="button" class="btn btn-sm btn-outline-success fw-bold" onclick="addAssessmentRow()">
                    <i class="fas fa-plus"></i> Add Another Item
                </button>
            </div>

            <div class="mb-3 mt-4">
                <label class="form-label fw-bold">Overall Remarks / Comments (Internal) *</label>
                <textarea name="assessor_overall_comment" class="form-control" rows="3" required placeholder="Write your assessment conclusion here..."></textarea>
            </div>

            <div class="d-flex gap-2 text-end">
                <button type="submit" class="btn btn-primary px-4 fw-bold">
                    <i class="fas fa-paper-plane"></i> Submit Assessment & Forward
                </button>
                <a href="approval_matrix.php" class="btn btn-secondary px-4">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function addAssessmentRow() {
    const container = document.getElementById('dynamic-items-container');
    const rowHTML = `
        <div class="dynamic-row">
            <button type="button" class="remove-btn" onclick="this.parentElement.remove()" title="Remove Item"><i class="fas fa-times-circle"></i></button>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Item Name *</label>
                    <input type="text" name="item_name[]" class="form-control" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Allocation Date</label>
                    <input type="date" name="allocation_date[]" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Lifetime (Years)</label>
                    <input type="number" name="lifetime[]" class="form-control" min="1">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Configuration Details *</label>
                    <input type="text" name="config_details[]" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Source</label>
                    <select name="source[]" class="form-select">
                        <option value="In Stock">In Stock</option>
                        <option value="New Purchase">New Purchase</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Approx Cost</label>
                    <input type="number" name="approx_cost[]" class="form-control">
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', rowHTML);
}
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>