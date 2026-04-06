<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');

/* POST check */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit;
}

/* use UserName instead of UserID */
if (!isset($_SESSION['UserName'])) {
    die("Session missing");
}

$username = $_SESSION['UserName'];

$upload_dir = '/var/www/html/asset_manager/uploads/signatures/';

/* get old signature */
$stmt = mysqli_prepare($conn, "SELECT signature_file FROM users WHERE username = ? LIMIT 1");
if (!$stmt) {
    die(mysqli_error($conn));
}

mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$old_signature = '';
if ($row = mysqli_fetch_assoc($res)) {
    $old_signature = trim((string)$row['signature_file']);
}
mysqli_stmt_close($stmt);

/* delete file */
if (!empty($old_signature)) {
    $old_signature = basename($old_signature);
    $old_path = $upload_dir . $old_signature;

    if (file_exists($old_path) && is_file($old_path)) {
        if (!unlink($old_path)) {
            die("Delete failed: " . $old_path);
        }
    }
}

/* update DB */
$update_stmt = mysqli_prepare($conn, "UPDATE users SET signature_file = NULL WHERE username = ?");
if (!$update_stmt) {
    die(mysqli_error($conn));
}

mysqli_stmt_bind_param($update_stmt, "s", $username);

if (!mysqli_stmt_execute($update_stmt)) {
    die(mysqli_error($conn));
}

mysqli_stmt_close($update_stmt);

/* redirect */
header("Location: profile.php?sig_deleted=1");
exit;