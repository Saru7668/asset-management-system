<?php
require_once('db.php');

header('Content-Type: application/json');

$emp_id = trim($_GET['emp_id'] ?? '');

if ($emp_id === '') {
    echo json_encode(['success' => false]);
    exit;
}

$emp_id_safe = mysqli_real_escape_string($conn, $emp_id);

$q = mysqli_query($conn, "
    SELECT employee_name, department, date_of_joining, employee_category, employee_status
    FROM employees 
    WHERE employee_id = '$emp_id_safe' 
    LIMIT 1
");

if ($q && mysqli_num_rows($q) > 0) {
    $row = mysqli_fetch_assoc($q);
    echo json_encode([
        'success' => true,
        'employee_name' => $row['employee_name'] ?? '',
        'department' => $row['department'] ?? '',
        'date_of_joining' => $row['date_of_joining'] ?? '',
        'employee_category' => $row['employee_category'] ?? '',
        'employee_status' => $row['employee_status'] ?? ''
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>