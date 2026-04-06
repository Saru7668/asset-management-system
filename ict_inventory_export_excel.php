<?php
date_default_timezone_set('Asia/Dhaka');
session_start();
ini_set('memory_limit', '512M');
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once('db.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}


// PhpSpreadsheet autoload
require __DIR__ . '/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Filter logic
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

$sql = "SELECT * FROM assets $where ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

// Create Spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Company Info (customize)
$company_name = "Sheltech Ceramics Limited";  // CHANGE THIS
$report_title = "ICT Inventory List";
$logo_path = __DIR__ . "/images/company_logo.png"; // CHANGE THIS

// Row counter
$row = 1;

// HEADER: Company name
$sheet->setCellValue("A$row", $company_name);
$sheet->mergeCells("A$row:O$row");
$sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(16);
$sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// HEADER: Report title
$sheet->setCellValue("A$row", $report_title);
$sheet->mergeCells("A$row:O$row");
$sheet->getStyle("A$row")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

// HEADER: Generated date
$sheet->setCellValue("A$row", "Generated at: " . date('Y-m-d H:i:s'));
$sheet->mergeCells("A$row:O$row");
$sheet->getStyle("A$row")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$row++;

$row++; // blank line

// COLUMN HEADERS
$headers = [
    'Inventory', 'Employee ID', 'Employee Name', 'Department', 'Details',
    'Serial/Model', 'Status', 'Unit', 'Purchase Date', 'Warranty (Months)',
    'Remarks', 'Entry User', 'Entry DateTime', 'Update User', 'Update DateTime'
];

$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col . $row, $header);
    $sheet->getStyle($col . $row)->getFont()->setBold(true);
    $sheet->getStyle($col . $row)->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('FFD9D9D9');
    $sheet->getStyle($col . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;
}
$row++;

// DATA ROWS
if ($result) {
    while ($data = mysqli_fetch_assoc($result)) {
        $sheet->setCellValue('A' . $row, $data['inventory']);
        $sheet->setCellValue('B' . $row, $data['employee_id']);
        $sheet->setCellValue('C' . $row, $data['employee_name']);
        $sheet->setCellValue('D' . $row, $data['department']);
        $sheet->setCellValue('E' . $row, $data['details']);
        $sheet->setCellValue('F' . $row, $data['serial_model']);
        $sheet->setCellValue('G' . $row, $data['status']);
        $sheet->setCellValue('H' . $row, $data['unit']);
        $sheet->setCellValue('I' . $row, $data['purchase_date']);
        $sheet->setCellValue('J' . $row, $data['warranty_months']);
        $sheet->setCellValue('K' . $row, $data['remarks']);
        $sheet->setCellValue('L' . $row, $data['entry_user']);
        $sheet->setCellValue('M' . $row, $data['entry_datetime']);
        $sheet->setCellValue('N' . $row, $data['update_user']);
        $sheet->setCellValue('O' . $row, $data['update_datetime']);
        $row++;
    }
}

// Auto-size columns
foreach (range('A', 'O') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Add borders to data table
$lastRow = $row - 1;
if ($lastRow >= 6) { // To prevent error if no data
    $styleArray = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['argb' => 'FF000000'],
            ],
        ],
    ];
    $sheet->getStyle("A6:O$lastRow")->applyFromArray($styleArray);
}

// Output
$filename = "ict_inventory_" . date('Ymd_His') . ".xlsx";

if (ob_get_length()) {
    ob_end_clean();
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
