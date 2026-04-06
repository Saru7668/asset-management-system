<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');

echo "<pre>";
print_r($_SESSION);
echo "</pre>";

require_once('header.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$user_role = strtolower(trim(str_replace(' ', '_', $_SESSION['UserRole'] ?? 'user')));
if ($user_role !== 'superadmin') {
    die('Access denied. Only superadmin can edit requests.');
}

$request_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($request_id <= 0) {
    die('Invalid request ID.');
}

$stmt = mysqli_prepare($conn, "SELECT * FROM hardware_requests WHERE id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "i", $request_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$request = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$request) {
    die('Request not found.');
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_type = trim($_POST['request_type'] ?? '');
    $requested_for_name = trim($_POST['requested_for_name'] ?? '');
    $requested_for_department = trim($_POST['requested_for_department'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if ($request_type === '' || $requested_for_name === '' || $requested_for_department === '') {
        $error = 'Please fill in all required fields.';
    } else {
        $update_sql = "UPDATE hardware_requests 
                       SET request_type = ?, 
                           requested_for_name = ?, 
                           requested_for_department = ?, 
                           remarks = ?, 
                           updated_at = NOW()
                       WHERE id = ?";
        $update_stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param(
            $update_stmt,
            "ssssi",
            $request_type,
            $requested_for_name,
            $requested_for_department,
            $remarks,
            $request_id
        );

        if (mysqli_stmt_execute($update_stmt)) {
            mysqli_stmt_close($update_stmt);

            $admin_name = $_SESSION['UserName'] ?? 'Superadmin';
            $admin_id = $_SESSION['UserID'] ?? 0;
            $current_stage = $request['current_stage'] ?? 'returned_to_user';

            $hist_sql = "INSERT INTO hardware_request_approval_history
            (request_id, approver_id, approver_name, approval_stage, approval_status, action_type, from_stage, to_stage, remarks, approved_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $hist_stmt = mysqli_prepare($conn, $hist_sql);
            $approval_status = 'Edited by Superadmin';
            $action_type = 'edit';
            $from_stage = $current_stage;
            $to_stage = $current_stage;
            $hist_remarks = 'Request edited by Superadmin';

            mysqli_stmt_bind_param(
                $hist_stmt,
                "iisssssss",
                $request_id,
                $admin_id,
                $admin_name,
                $current_stage,
                $approval_status,
                $action_type,
                $from_stage,
                $to_stage,
                $hist_remarks
            );
            mysqli_stmt_execute($hist_stmt);
            mysqli_stmt_close($hist_stmt);

            header("Location: request_view.php?id=" . $request_id . "&updated=1");
            exit;
        } else {
            $error = 'Update failed: ' . mysqli_error($conn);
            mysqli_stmt_close($update_stmt);
        }
    }
}

$page_title = 'Edit Request';
$page_header_icon = 'fas fa-pen';
$page_header_title = 'Edit Request';
$page_header_subtitle = 'Superadmin can modify request details';
$page_top_title = 'Edit Request';
$page_container_class = 'dashboard-container';

$extra_css = "
.edit-card{
    background:#fff;
    border-radius:16px;
    border:1px solid #e2e8f0;
    box-shadow:0 10px 30px rgba(0,0,0,.08);
    overflow:hidden;
}
.edit-card-header{
    background:linear-gradient(135deg,#0f172a 0%, #1d4ed8 100%);
    color:#fff;
    padding:18px 22px;
    font-size:20px;
    font-weight:800;
}
.edit-card-body{
    padding:24px;
}
.form-label{
    font-weight:700;
}
";

ob_start();
?>

<div class="edit-card">
    <div class="edit-card-header">
        <i class="fas fa-file-pen me-2"></i> Edit Request #<?php echo (int)$request_id; ?>
    </div>
    <div class="edit-card-body">

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Reference No</label>
                    <input type="text" class="form-control" value="<?php echo h($request['ref_no'] ?? ''); ?>" readonly>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Request Type</label>
                    <input type="text" name="request_type" class="form-control" value="<?php echo h($request['request_type'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Requested For Name</label>
                    <input type="text" name="requested_for_name" class="form-control" value="<?php echo h($request['requested_for_name'] ?? ''); ?>" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Department</label>
                    <input type="text" name="requested_for_department" class="form-control" value="<?php echo h($request['requested_for_department'] ?? ''); ?>" required>
                </div>

                <div class="col-12">
                    <label class="form-label">Remarks</label>
                    <textarea name="remarks" class="form-control" rows="4"><?php echo h($request['remarks'] ?? ''); ?></textarea>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Update Request
                    </button>
                    <a href="request_view.php?id=<?php echo (int)$request_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>