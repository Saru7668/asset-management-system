<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function safe_date($v, $f='d M Y'){
    if (empty($v) || $v === '0000-00-00') return '';
    $ts = strtotime($v);
    return $ts ? date($f, $ts) : '';
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Invalid lease ID.');

// JOIN with assets
$sql = "SELECT l.*, i.details as item_name, i.inventory as asset_id
        FROM temp_accessories_leases l
        LEFT JOIN assets i ON i.id = l.inventory_id
        WHERE l.id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$row = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$row) die('Lease record not found.');

$page_title = 'Print Lease - ' . h($row['lease_ref']);
$page_header_icon = 'fas fa-print';
$page_header_title = 'Print Temp Lease';
$page_header_subtitle = 'Printable lease form';
$page_top_title = 'Print Lease';
$page_container_class = 'dashboard-container-xl';

$extra_css = "
.print-page{background:#fff;border-radius:12px;border:1px solid #dbe2ea;overflow:hidden}
.print-wrap{padding:14px;background:#f8fafc}
.sheet{width:210mm;min-height:297mm;margin:0 auto;background:#fff;padding:18mm 14mm;box-sizing:border-box;color:#111827}
.brand{text-align:center;margin-bottom:18px}
.brand h1{font-size:18px;margin:0;color:#1241b1;font-weight:800}
.title{font-size:22px;font-weight:800;color:#d48b00;text-align:center;margin:12px 0 18px}
.meta{text-align:right;margin-bottom:16px;font-size:13px}
.tbl{width:100%;border-collapse:collapse;font-size:14px}
.tbl td,.tbl th{border:1px solid #666;padding:8px;vertical-align:top}
.stamp-box{border:1px solid #666;height:82px;display:flex;align-items:flex-start;justify-content:center;font-size:18px;font-weight:800;color:#d5a100;padding-top:8px}
.note-box{border:1px solid #666;padding:10px;min-height:140px;font-size:13px}
.sign-line{margin-top:46px;border-top:1px solid #666;width:220px;text-align:center;padding-top:8px;font-size:13px}
.footer-row{display:grid;grid-template-columns:1fr 160px;gap:20px;margin-top:36px}
@media print{
    .page-topbar,.main-header,.top-mobile-bar,.sidebar,.sidebar-overlay,.page-actions{display:none!important}
    .main-content{padding:0!important;margin:0!important}
    .print-page,.print-wrap,.sheet{box-shadow:none!important;border:none!important;background:#fff!important}
}
";

ob_start();
?>

<div class="page-actions mb-3 d-flex justify-content-end gap-2">
    <a href="temp_accessories_lease_list.php" class="btn btn-secondary btn-sm">Back</a>
    <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Print / Download</button>
</div>

<div class="print-page">
    <div class="print-wrap">
        <div class="sheet">
            <div class="brand">
                <h1>SHELTECH CERAMICS</h1>
            </div>
            <div class="title">ICT Accessories Lease Form</div>
            <div class="meta">Date: <?php echo h(safe_date($row['lease_date'], 'd / m / Y')); ?></div>
            <table class="tbl">
                <tr>
                    <td style="width:24%;">Employee Name</td>
                    <td colspan="3"><?php echo h($row['employee_name']); ?></td>
                </tr>
                <tr>
                    <td>Department</td>
                    <td colspan="3"><?php echo h($row['department']); ?></td>
                </tr>
                <tr>
                    <td rowspan="2">Product Details</td>
                    <td style="width:28%;">Serial No.</td>
                    <td colspan="2"><?php echo h($row['serial_no']); ?></td>
                </tr>
                <tr>
                    <td>Type or Description</td>
                    <td colspan="2"><?php echo h($row['product_details'] . (!empty($row['type_description']) ? ' / ' . $row['type_description'] : '')); ?></td>
                </tr>
                <tr>
                    <td rowspan="2">Lease Period</td>
                    <td>From</td>
                    <td colspan="2"><?php echo h(safe_date($row['lease_from'])); ?></td>
                </tr>
                <tr>
                    <td>To</td>
                    <td colspan="2"><?php echo h(safe_date($row['lease_to'])); ?></td>
                </tr>
                <tr>
                    <td>Remarks</td>
                    <td colspan="3"><?php echo nl2br(h($row['remarks'])); ?></td>
                </tr>
            </table>

            <div class="footer-row">
                <div>
                    <div class="sign-line">Sign &amp; Seal of HOD</div>
                    <div style="margin-top:6px;font-size:12px;"><?php echo h($row['hod_name']); ?></div>
                </div>
                <div class="stamp-box"><?php echo $row['received_status'] ? 'RECEIVED' : ''; ?></div>
            </div>

            <div class="footer-row" style="margin-top:70px;">
                <div class="note-box">
                    <strong>Note:</strong><br><br>
                    ** This form contained by ICT department during the leasing period.<br><br>
                    ** Employee must re-obtain this form in the time of return accessories at his/her own risk.<br><br>
                    ** If unable to collect form at time of return, may claim again for return by ICT Department.<br><br>
                    ** Lease period will not exceed more than 03 days.
                </div>
                <div class="stamp-box"><?php echo $row['returned_status'] ? 'RETURNED' : ''; ?></div>
            </div>
        </div>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>