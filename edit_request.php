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

$username = trim($_SESSION['UserName'] ?? '');
$user_role = trim($_SESSION['UserRole'] ?? 'user');
$is_super_admin = in_array(strtolower($user_role), ['superadmin', 'admin']);

/* Resolve logged user safely */
$user_id = (int)($_SESSION['UserID'] ?? 0);
$logged_user = null;

$user_stmt = mysqli_prepare($conn, "SELECT id, username, full_name, department FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, "s", $username);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$logged_user = mysqli_fetch_assoc($user_result);

if (!$logged_user) {
    die('User not found.');
}

if ($user_id <= 0) {
    $user_id = (int)$logged_user['id'];
}

$request_id = (int)($_GET['id'] ?? 0);
if ($request_id <= 0) {
    die('Invalid request ID.');
}

/* Fetch request */
$req_stmt = mysqli_prepare($conn, "SELECT * FROM hardware_requests WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($req_stmt, "i", $request_id);
mysqli_stmt_execute($req_stmt);
$req_result = mysqli_stmt_get_result($req_stmt);
$req = mysqli_fetch_assoc($req_result);

if (!$req) {
    die('Request not found.');
}

/*
|--------------------------------------------------------------------------
| Permission Logic
|--------------------------------------------------------------------------
| SuperAdmin/Admin -> can open any request if it's in returned/edit mode
| Normal user -> only own request + returned/edit mode
|--------------------------------------------------------------------------
*/
$is_returned = (strpos(strtolower($req['workflow_status'] ?? ''), 'returned') !== false);

if ($is_super_admin) {
    if (!$is_returned) {
        echo "<script>alert('This request is not currently in editable/returned mode. Please use Return to Edit first.'); window.location.href='my_requests.php';</script>";
        exit;
    }
} else {
    if ((int)$req['requester_user_id'] !== $user_id) {
        die('You do not have permission to edit this request.');
    }

    if (!$is_returned) {
        echo "<script>alert('You can only edit returned requests.'); window.location.href='my_requests.php';</script>";
        exit;
    }
}

/* Latest return note */
$return_history = null;
$history_check = mysqli_query($conn, "SHOW TABLES LIKE 'hardware_request_approval_history'");
if ($history_check && mysqli_num_rows($history_check) > 0) {
    $hist_stmt = mysqli_prepare($conn, "
        SELECT remarks, approver_name, approved_at
        FROM hardware_request_approval_history
        WHERE request_id = ?
          AND LOWER(approval_status) LIKE '%return%'
        ORDER BY id DESC
        LIMIT 1
    ");
    mysqli_stmt_bind_param($hist_stmt, "i", $request_id);
    mysqli_stmt_execute($hist_stmt);
    $hist_result = mysqli_stmt_get_result($hist_stmt);
    $return_history = mysqli_fetch_assoc($hist_result);
}

/* Handle update + re-submit */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

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

    if ($purpose_text === '') {
        echo "<script>alert('Purpose / Work Functions is required.'); window.history.back();</script>";
        exit;
    }

    $next_stage = 'department_head';
    $next_status = 'pending_department_head';

    $update_sql = "UPDATE hardware_requests SET
        purpose_text = ?,
        reason_details = ?,
        request_for_laptop = ?,
        request_for_desktop = ?,
        request_for_scanner = ?,
        request_for_monitor = ?,
        request_for_ram = ?,
        request_for_ssd = ?,
        request_for_hdd = ?,
        request_for_mouse_wired = ?,
        request_for_mouse_wireless = ?,
        request_for_keyboard = ?,
        request_for_printer = ?,
        request_for_other = ?,
        request_for_other_text = ?,
        reason_allocation = ?,
        reason_replacement = ?,
        reason_exchange = ?,
        reason_upgradation = ?,
        reason_maintenance_repair = ?,
        reason_damage = ?,
        reason_other = ?,
        reason_other_text = ?,
        current_stage = ?,
        workflow_status = ?,
        updated_at = NOW()
        WHERE id = ?";

    $up_stmt = mysqli_prepare($conn, $update_sql);

    mysqli_stmt_bind_param(
        $up_stmt,
        "ssiiiiiiiiiiiisiiiiiiisssi",
        $purpose_text,
        $reason_details,
        $request_for_laptop,
        $request_for_desktop,
        $request_for_scanner,
        $request_for_monitor,
        $request_for_ram,
        $request_for_ssd,
        $request_for_hdd,
        $request_for_mouse_wired,
        $request_for_mouse_wireless,
        $request_for_keyboard,
        $request_for_printer,
        $request_for_other,
        $request_for_other_text,
        $reason_allocation,
        $reason_replacement,
        $reason_exchange,
        $reason_upgradation,
        $reason_maintenance_repair,
        $reason_damage,
        $reason_other,
        $reason_other_text,
        $next_stage,
        $next_status,
        $request_id
    );

    if (mysqli_stmt_execute($up_stmt)) {
        echo "<script>alert('Request updated and re-submitted successfully!'); window.location.href='my_requests.php';</script>";
        exit;
    } else {
        echo "<script>alert('Failed to update request: " . addslashes(mysqli_error($conn)) . "');</script>";
    }
}

$page_title = 'Edit Request - SCL AMS';
$page_header_icon = 'fas fa-edit';
$page_header_title = 'Edit Request';
$page_header_subtitle = 'Update returned request and re-submit';
$page_top_title = 'Edit Request';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.return-msg {
    background: #fee2e2;
    border-left: 4px solid #ef4444;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 5px;
    color: #991b1b;
    font-weight: 500;
}
.edit-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
    border: 1px solid #e2e8f0;
}
.choice-box {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 14px;
    background: rgba(255,255,255,0.03);
    min-height: 100%;
}
.form-section-title {
    font-size: 18px;
    font-weight: 700;
    color: #0b2545;
    margin-bottom: 16px;
    padding-bottom: 8px;
    border-bottom: 2px solid #e5e7eb;
}
.request-grid-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px 20px;
}
@media (max-width: 991px) {
    .request-grid-two {
        grid-template-columns: 1fr;
    }
}
html[data-theme='dark'] .return-msg {
    background: #451a1a !important;
    color: #fca5a5 !important;
}
html[data-theme='dark'] .edit-card {
    background: #1f2937 !important;
    border-color: #374151 !important;
}
html[data-theme='dark'] .choice-box {
    background: #111827 !important;
    border-color: #374151 !important;
}
html[data-theme='dark'] .form-section-title {
    color: #93c5fd !important;
    border-bottom-color: #374151 !important;
}
";

ob_start();
?>

<?php if ($return_history): ?>
<div class="return-msg">
    <i class="fas fa-exclamation-triangle me-2"></i>
    <strong>Returned by <?php echo h($return_history['approver_name'] ?? 'Approver'); ?>:</strong><br>
    <?php echo nl2br(h($return_history['remarks'] ?? '')); ?>
</div>
<?php endif; ?>

<div class="edit-card">
    <form method="POST">
        <div class="request-grid-two">
            <div class="choice-box">
                <div class="form-section-title">Request For</div>

                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_laptop" <?php echo !empty($req['request_for_laptop']) ? 'checked' : ''; ?>><label class="form-check-label">Laptop</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_desktop" <?php echo !empty($req['request_for_desktop']) ? 'checked' : ''; ?>><label class="form-check-label">Desktop</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_scanner" <?php echo !empty($req['request_for_scanner']) ? 'checked' : ''; ?>><label class="form-check-label">Scanner</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_monitor" <?php echo !empty($req['request_for_monitor']) ? 'checked' : ''; ?>><label class="form-check-label">Monitor</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_ram" <?php echo !empty($req['request_for_ram']) ? 'checked' : ''; ?>><label class="form-check-label">RAM</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_ssd" <?php echo !empty($req['request_for_ssd']) ? 'checked' : ''; ?>><label class="form-check-label">SSD</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_hdd" <?php echo !empty($req['request_for_hdd']) ? 'checked' : ''; ?>><label class="form-check-label">HDD</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_mouse_wired" <?php echo !empty($req['request_for_mouse_wired']) ? 'checked' : ''; ?>><label class="form-check-label">Wired Mouse</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_mouse_wireless" <?php echo !empty($req['request_for_mouse_wireless']) ? 'checked' : ''; ?>><label class="form-check-label">Wireless Mouse</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_keyboard" <?php echo !empty($req['request_for_keyboard']) ? 'checked' : ''; ?>><label class="form-check-label">Keyboard</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_printer" <?php echo !empty($req['request_for_printer']) ? 'checked' : ''; ?>><label class="form-check-label">Printer</label></div>
                <div class="mb-3">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="request_for_other" <?php echo !empty($req['request_for_other']) ? 'checked' : ''; ?>><label class="form-check-label">Other Accessories</label></div>
                    <input type="text" name="request_for_other_text" class="form-control" placeholder="Other accessories" value="<?php echo h($req['request_for_other_text'] ?? ''); ?>">
                </div>
            </div>

            <div class="choice-box">
                <div class="form-section-title">Purpose / Work Functions</div>
                <textarea name="purpose_text" class="form-control" rows="8" required><?php echo h($req['purpose_text'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="request-grid-two mt-4">
            <div class="choice-box">
                <div class="form-section-title">Request Reason</div>

                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="reason_allocation" <?php echo !empty($req['reason_allocation']) ? 'checked' : ''; ?>><label class="form-check-label">Allocation</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="reason_replacement" <?php echo !empty($req['reason_replacement']) ? 'checked' : ''; ?>><label class="form-check-label">Replacement</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="reason_exchange" <?php echo !empty($req['reason_exchange']) ? 'checked' : ''; ?>><label class="form-check-label">Exchange</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="reason_upgradation" <?php echo !empty($req['reason_upgradation']) ? 'checked' : ''; ?>><label class="form-check-label">Upgradation</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="reason_maintenance_repair" <?php echo !empty($req['reason_maintenance_repair']) ? 'checked' : ''; ?>><label class="form-check-label">Maintenance / Repair</label></div>
                <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="reason_damage" <?php echo !empty($req['reason_damage']) ? 'checked' : ''; ?>><label class="form-check-label">Damage</label></div>
                <div class="mb-3">
                    <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="reason_other" <?php echo !empty($req['reason_other']) ? 'checked' : ''; ?>><label class="form-check-label">Other</label></div>
                    <input type="text" name="reason_other_text" class="form-control" placeholder="Other reason" value="<?php echo h($req['reason_other_text'] ?? ''); ?>">
                </div>
            </div>

            <div class="choice-box">
                <div class="form-section-title">Reason Details</div>
                <textarea name="reason_details" class="form-control" rows="8"><?php echo h($req['reason_details'] ?? ''); ?></textarea>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4">
            <a href="my_requests.php" class="btn btn-outline-secondary px-4">Cancel</a>
            <button type="submit" class="btn btn-primary px-4" style="background:#0b2545; border-color:#0b2545;">
                <i class="fas fa-paper-plane me-2"></i>Update & Re-submit
            </button>
        </div>
    </form>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>