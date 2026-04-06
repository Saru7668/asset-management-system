<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: temp_accessories_lease_list.php");
    exit;
}

mysqli_begin_transaction($conn);

try {
    $stmt = mysqli_prepare($conn, "SELECT inventory_id, returned_status FROM temp_accessories_leases WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lease = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$lease) {
        throw new Exception('Lease record not found.');
    }

    if ((int)$lease['returned_status'] === 1) {
        mysqli_commit($conn);
        header("Location: temp_accessories_lease_list.php");
        exit;
    }

    $stmt1 = mysqli_prepare($conn, "UPDATE temp_accessories_leases SET returned_status = 1, returned_at = NOW() WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt1, "i", $id);
    mysqli_stmt_execute($stmt1);
    mysqli_stmt_close($stmt1);

    $inventory_id = (int)$lease['inventory_id'];
    
    // UPDATE assets back to 'In Stock'
    $stmt2 = mysqli_prepare($conn, "UPDATE assets SET status = 'In Stock' WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt2, "i", $inventory_id);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);

    mysqli_commit($conn);
} catch (Throwable $e) {
    mysqli_rollback($conn);
}

header("Location: temp_accessories_lease_list.php");
exit;