<?php
session_start();
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('db.php');

if (!isset($_SESSION['UserName'])) {
    die("Unauthorized");
}

// composer autoload
require __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// ==== FILTER (same as list) ====
$search_text = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_emp  = isset($_GET['emp']) ? trim($_GET['emp']) : '';
$search_dept = isset($_GET['dept']) ? trim($_GET['dept']) : '';
$search_status = isset($_GET['status']) ? trim($_GET['status']) : ''; // NEW: Status Filter
$date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = " WHERE 1=1 ";
if ($search_text !== '') {
    $s = mysqli_real_escape_string($conn, $search_text);
    $where .= " AND (inventory LIKE '%$s%' OR serial_model LIKE '%$s%' OR details LIKE '%$s%')";
}
if ($search_emp !== '') {
    $e = mysqli_real_escape_string($conn, $search_emp);
    $where .= " AND (employee_name LIKE '%$e%' OR employee_id LIKE '%$e%')";
}
if ($search_dept !== '') {
    $d = mysqli_real_escape_string($conn, $search_dept);
    $where .= " AND department LIKE '%$d%'";
}
if ($date_from !== '' && $date_to !== '') {
    $df = mysqli_real_escape_string($conn, $date_from);
    $dt = mysqli_real_escape_string($conn, $date_to);
    $where .= " AND DATE(purchase_date) BETWEEN '$df' AND '$dt'";
} elseif ($date_from !== '') {
    $df = mysqli_real_escape_string($conn, $date_from);
    $where .= " AND DATE(purchase_date) >= '$df'";
} elseif ($date_to !== '') {
    $dt = mysqli_real_escape_string($conn, $date_to);
    $where .= " AND DATE(purchase_date) <= '$dt'";
}
// NEW: Add status to WHERE clause
if ($search_status !== '') {
    $st = mysqli_real_escape_string($conn, $search_status);
    $where .= " AND status = '$st'";
}

// hard limit
$max_rows = 500;   

$sql = "SELECT * FROM assets $where ORDER BY id DESC LIMIT $max_rows";
$result = mysqli_query($conn, $sql);
if(!$result){
    die("DB error: " . mysqli_error($conn));
}

$company_name = "Sheltech Ceramics Limited";
$report_title = "ICT Inventory List (Top $max_rows rows)";

ob_start();
?>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h2, h3 { text-align: center; margin: 2px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #000; padding: 3px; font-size: 8px; }
        th { background: #eee; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <h2><?php echo htmlspecialchars($company_name); ?></h2>
    <h3><?php echo htmlspecialchars($report_title); ?></h3>
    <div class="center">Generated at: <?php echo date('Y-m-d H:i:s'); ?></div>

    <table>
        <thead>
            <tr>
                <th>SL</th>
                <th>Inventory</th>
                <th>Emp ID</th>
                <th>Employee</th>
                <th>Dept</th>
                <th>Serial</th>
                <th>Status</th>
                <th>Unit</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $sl = 1;
        while($row = mysqli_fetch_assoc($result)): ?>
            <tr>
                <td class="center"><?php echo $sl++; ?></td>
                <td><?php echo htmlspecialchars($row['inventory']); ?></td>
                <td><?php echo htmlspecialchars($row['employee_id']); ?></td>
                <td><?php echo htmlspecialchars($row['employee_name']); ?></td>
                <td><?php echo htmlspecialchars($row['department']); ?></td>
                <td><?php echo htmlspecialchars($row['serial_model']); ?></td>
                <td><?php echo htmlspecialchars($row['status']); ?></td>
                <td><?php echo htmlspecialchars($row['unit']); ?></td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
</body>
</html>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isHtml5ParserEnabled', false);
$options->set('isRemoteEnabled', false);
$options->set('dpi', 72);
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isPhpEnabled', false);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$dompdf->stream("ict_inventory_".date('Ymd_His').".pdf", ["Attachment" => true]);
exit;
