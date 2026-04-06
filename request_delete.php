<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$user_role = trim($_SESSION['UserRole'] ?? 'user');
$is_super_admin = in_array(strtolower($user_role), ['superadmin', 'admin']);

if (!$is_super_admin) {
    die('You do not have permission to delete requests.');
}

$request_id = (int)($_GET['id'] ?? 0);
if ($request_id <= 0) {
    die('Invalid request ID.');
}

/* Delete related assessment rows first if table exists */
$assessment_check = mysqli_query($conn, "SHOW TABLES LIKE 'hardware_request_assessment'");
if ($assessment_check && mysqli_num_rows($assessment_check) > 0) {
    $del_assessment_stmt = mysqli_prepare($conn, "DELETE FROM hardware_request_assessment WHERE request_id = ?");
    mysqli_stmt_bind_param($del_assessment_stmt, "i", $request_id);
    mysqli_stmt_execute($del_assessment_stmt);
}

/* Delete approval history first if table exists */
$history_check = mysqli_query($conn, "SHOW TABLES LIKE 'hardware_request_approval_history'");
if ($history_check && mysqli_num_rows($history_check) > 0) {
    $del_hist_stmt = mysqli_prepare($conn, "DELETE FROM hardware_request_approval_history WHERE request_id = ?");
    mysqli_stmt_bind_param($del_hist_stmt, "i", $request_id);
    mysqli_stmt_execute($del_hist_stmt);
}

/* Delete main request */
$del_req_stmt = mysqli_prepare($conn, "DELETE FROM hardware_requests WHERE id = ?");
mysqli_stmt_bind_param($del_req_stmt, "i", $request_id);

if (mysqli_stmt_execute($del_req_stmt)) {
    echo "<script>alert('Request deleted successfully.'); window.location.href='my_requests.php';</script>";
    exit;
} else {
    die('Failed to delete request: ' . mysqli_error($conn));
}
?>