<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');

/* session check */
if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['UserName'];

/* upload dir */
$upload_dir = '/var/www/html/asset_manager/uploads/signatures/';

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

/* request check */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: profile.php");
    exit;
}

if (!isset($_FILES['signature_file'])) {
    die('No file uploaded');
}

$file = $_FILES['signature_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    die('Upload error: ' . $file['error']);
}

/* validation */
$allowed_ext = ['png', 'jpg', 'jpeg', 'webp'];
$max_size = 2 * 1024 * 1024;

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($ext, $allowed_ext)) {
    die('Invalid file type');
}

if ($file['size'] > $max_size) {
    die('File too large');
}

/* get old file */
$stmt = mysqli_prepare($conn, "SELECT signature_file FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);

$old_signature = '';
if ($row = mysqli_fetch_assoc($res)) {
    $old_signature = trim((string)$row['signature_file']);
}
mysqli_stmt_close($stmt);

/* new file */
$new_file_name = 'sig_' . time() . '.' . $ext;
$target_path = $upload_dir . $new_file_name;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    die('Upload failed');
}

/* update DB */
$update_stmt = mysqli_prepare($conn, "UPDATE users SET signature_file = ? WHERE username = ?");
mysqli_stmt_bind_param($update_stmt, "ss", $new_file_name, $username);

if (!mysqli_stmt_execute($update_stmt)) {
    die('DB update failed');
}
mysqli_stmt_close($update_stmt);

/* delete old */
if (!empty($old_signature)) {
    $old_path = $upload_dir . basename($old_signature);
    if (file_exists($old_path)) {
        unlink($old_path);
    }
}

/* redirect */
header("Location: profile.php?sig_saved=1");
exit;