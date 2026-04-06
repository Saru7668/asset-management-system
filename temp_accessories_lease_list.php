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

$current_role = strtolower(trim($_SESSION['UserRole'] ?? ''));
$allowed_roles = ['hr', 'staff', 'admin', 'superadmin'];
if (!in_array($current_role, $allowed_roles, true)) {
    header("Location: index.php");
    exit;
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function safe_date($v, $f='d M Y'){
    if (empty($v) || $v === '0000-00-00') return '';
    $ts = strtotime($v);
    return $ts ? date($f, $ts) : '';
}

$msg = '';
$err = '';

// ==========================================
// SOFT DELETE ACTION (Send to Recycle Bin)
// ==========================================
if (isset($_GET['delete_id']) && $current_role === 'superadmin') {
    $del_id = (int)$_GET['delete_id'];
    $table_name = 'temp_accessories_leases'; // ??? ????? ???? ????? ????
    
    if ($del_id > 0) {
        // ?. ??? ?????? ??????? ???? Fetch ???
        $sel_stmt = mysqli_query($conn, "SELECT * FROM $table_name WHERE id = $del_id");
        if ($row = mysqli_fetch_assoc($sel_stmt)) {
            // ?. ???????? JSON ?????
            $json_data = json_encode($row);
            $deleted_by = $_SESSION['UserName'];
            
            // ?. Recycle Bin-? Insert ???
            $insert_rb = mysqli_prepare($conn, "INSERT INTO recycle_bin (original_table, original_id, record_data, deleted_by) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($insert_rb, "siss", $table_name, $del_id, $json_data, $deleted_by);
            
            if (mysqli_stmt_execute($insert_rb)) {
                // ?. ???? ??? ????? ???? ????? ???
                mysqli_query($conn, "DELETE FROM $table_name WHERE id = $del_id");
                $msg = "Record moved to Recycle Bin successfully.";
            } else {
                $err = "Failed to move record to Recycle Bin.";
            }
        }
    }
}

// ==========================================
// PAGINATION LOGIC
// ==========================================
$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)trim($_GET['page']) : 1;
if ($page <= 0) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

// Get total records for pagination
$total_query = "SELECT COUNT(*) as total FROM temp_accessories_leases";
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// ==========================================
// FETCH RECORDS WITH LIMIT & OFFSET
// ==========================================
$rows = [];
$sql = "SELECT l.*, i.details as item_name
        FROM temp_accessories_leases l
        LEFT JOIN assets i ON i.id = l.inventory_id
        ORDER BY l.id DESC
        LIMIT ? OFFSET ?";
        
$stmt = mysqli_prepare($conn, $sql);
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
}

$page_title = 'Temp Lease Records - SCL AMS';
$page_header_icon = 'fas fa-list';
$page_header_title = 'Temporary Lease Records';
$page_header_subtitle = 'View, upload, print and release leased accessories';
$page_top_title = 'Temp Lease Records';
$page_container_class = 'dashboard-container-xl';

// Dark mode and UI styling
$extra_css = "
.card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.05);
    border: 1px solid #e2e8f0;
    margin-bottom: 24px;
}
.card-body {
    padding: 20px;
}
.table > :not(caption) > * > * {
    padding: 12px 16px;
    border-bottom-color: #e2e8f0;
}
.table th {
    background-color: #f8fafc;
    color: #475569;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
}

/* Dark Mode Support */
html[data-theme='dark'] .card {
    background: #1e293b;
    border-color: #334155;
}
html[data-theme='dark'] .table {
    color: #cbd5e1;
}
html[data-theme='dark'] .table th {
    background-color: #0f172a;
    color: #94a3b8;
    border-bottom-color: #334155;
}
html[data-theme='dark'] .table td {
    border-bottom-color: #334155;
}
html[data-theme='dark'] .table-hover tbody tr:hover {
    color: #f8fafc;
    background-color: rgba(255,255,255,0.03);
}

/* Pagination dark mode */
html[data-theme='dark'] .page-link {
    background-color: #1e293b;
    border-color: #334155;
    color: #cbd5e1;
}
html[data-theme='dark'] .page-link:hover {
    background-color: #334155;
    color: #fff;
}
html[data-theme='dark'] .page-item.active .page-link {
    background-color: #3b82f6;
    border-color: #3b82f6;
    color: #fff;
}
html[data-theme='dark'] .page-item.disabled .page-link {
    background-color: #0f172a;
    border-color: #334155;
    color: #64748b;
}
";

ob_start();
?>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo h($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo h($err); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between mb-3 flex-wrap gap-2">
    <a href="temp_accessories_lease.php" class="btn btn-primary" style="background:#0b2545; border:none;">
        <i class="fas fa-plus me-1"></i> New Lease
    </a>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 50px;">SL</th>
                    <th>Ref</th>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Item</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Status</th>
                    <th style="width:280px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($rows): ?>
                    <?php 
                    $sl = $offset + 1; // Start serial number correctly based on pagination
                    foreach ($rows as $r): 
                    ?>
                        <tr>
                            <td><?php echo $sl++; ?></td>
                            <td class="fw-bold"><?php echo h($r['lease_ref']); ?></td>
                            <td><?php echo h($r['employee_name']); ?></td>
                            <td><?php echo h($r['department']); ?></td>
                            <td><?php echo h(($r['item_name'] ?? '') . ' ' . ($r['asset_code'] ?? '')); ?></td>
                            <td><?php echo h(safe_date($r['lease_from'])); ?></td>
                            <td><?php echo h(safe_date($r['lease_to'])); ?></td>
                            <td>
                                <?php if ((int)$r['returned_status'] === 1): ?>
                                    <span class="badge bg-success">Returned</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">On Lease</span>
                                <?php endif; ?>
                            </td>
                            <td class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-sm btn-outline-primary" href="temp_accessories_lease_print.php?id=<?php echo (int)$r['id']; ?>" title="Print">
                                    <i class="fas fa-print"></i>
                                </a>
                                <a class="btn btn-sm btn-outline-secondary" href="temp_accessories_lease_upload.php?id=<?php echo (int)$r['id']; ?>" title="Upload Signed Copy">
                                    <i class="fas fa-upload"></i>
                                </a>
                                <?php if ((int)$r['returned_status'] === 0): ?>
                                    <a class="btn btn-sm btn-success" href="release_temp_lease.php?id=<?php echo (int)$r['id']; ?>" onclick="return confirm('Mark this device as returned and release inventory?')">
                                        <i class="fas fa-check"></i> Release
                                    </a>
                                <?php endif; ?>
                                <?php if (!empty($r['uploaded_form_file'])): ?>
                                    <a class="btn btn-sm btn-dark" target="_blank" href="<?php echo h($r['uploaded_form_file']); ?>">
                                        <i class="fas fa-eye"></i> View File
                                    </a>
                                <?php endif; ?>
                                
                                <?php if ($current_role === 'superadmin'): ?>
                                    <a class="btn btn-sm btn-danger" href="?delete_id=<?php echo (int)$r['id']; ?>&page=<?php echo $page; ?>" onclick="return confirm('Are you sure you want to delete this lease record? It will moved to Recycle Bin')" title="Delete">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="text-center py-4 text-muted">
                            <i class="fas fa-inbox fs-3 d-block mb-2"></i>
                            No lease record found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <!-- Pagination Links -->
        <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <!-- Previous Button -->
                <li class="page-item <?php if($page <= 1){ echo 'disabled'; } ?>">
                    <a class="page-link" href="<?php if($page <= 1){ echo '#'; } else { echo "?page=".($page - 1); } ?>">Previous</a>
                </li>
                
                <!-- Page Numbers -->
                <?php for($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php if($page == $i) {echo 'active'; } ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
                
                <!-- Next Button -->
                <li class="page-item <?php if($page >= $total_pages) { echo 'disabled'; } ?>">
                    <a class="page-link" href="<?php if($page >= $total_pages){ echo '#'; } else {echo "?page=".($page + 1); } ?>">Next</a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>