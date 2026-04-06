<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function safe_date($value, $format = 'd M Y, h:i A') {
    if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') {
        return 'N/A';
    }
    $ts = strtotime($value);
    return $ts ? date($format, $ts) : 'N/A';
}

function stage_label($stage) {
    $map = [
        'department_head'  => 'Department Head',
        'hr_head'          => 'HR Head',
        'ict_assessor'     => 'ICT Assessor',
        'ict_infra_head'   => 'ICT Infra Head',
        'ict_head'         => 'ICT Head',
        'completed'        => 'Completed',
        'returned_to_user' => 'Returned to User'
    ];
    return $map[$stage] ?? ucwords(str_replace('_', ' ', $stage));
}

function stage_badge_class($stage) {
    switch ($stage) {
        case 'department_head': return 'stage-dept';
        case 'hr_head': return 'stage-hr';
        case 'ict_assessor': return 'stage-assessor';
        case 'ict_infra_head': return 'stage-infra';
        case 'ict_head': return 'stage-icthead';
        case 'completed': return 'stage-completed';
        case 'returned_to_user': return 'stage-returned';
        default: return 'stage-default';
    }
}

function status_badge_class($status) {
    $status = strtolower(trim((string)$status));
    if (strpos($status, 'approved') !== false || $status === 'approved_completed') {
        return 'status-approved';
    }
    if (strpos($status, 'returned') !== false) {
        return 'status-returned';
    }
    if (strpos($status, 'pending') !== false) {
        return 'status-pending';
    }
    return 'status-default';
}

// Function to move record to Recycle Bin
function move_to_recycle_bin($conn, $request_id, $deleted_by) {
    $sel_stmt = mysqli_query($conn, "SELECT * FROM hardware_requests WHERE id = $request_id");
    if ($row = mysqli_fetch_assoc($sel_stmt)) {
        $json_data = json_encode($row);
        $table_name = 'hardware_requests';
        
        $insert_rb = mysqli_prepare($conn, "INSERT INTO recycle_bin (original_table, original_id, record_data, deleted_by) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($insert_rb, "siss", $table_name, $request_id, $json_data, $deleted_by);
        if (mysqli_stmt_execute($insert_rb)) {
            return true;
        }
    }
    return false;
}

$user_role = strtolower(trim(str_replace(' ', '_', $_SESSION['UserRole'] ?? 'user')));
$username = $_SESSION['UserName'];
$is_superadmin = ($user_role === 'superadmin');

if (!in_array($user_role, ['admin', 'superadmin'])) {
    die('Access denied.');
}

/* Handle superadmin actions (Return to Edit, Single Delete, Bulk Delete) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_superadmin && isset($_POST['super_action'])) {
    $action = trim($_POST['super_action']);

    // Single Return to Edit
    if ($action === 'return_to_edit' && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        if ($request_id > 0) {
            $sql = "UPDATE hardware_requests 
                    SET current_stage = 'returned_to_user',
                        workflow_status = 'returned_for_edit',
                        updated_at = NOW()
                    WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $request_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);

            echo "<script>alert('Request returned to user for edit successfully.'); window.location.href='all_requests.php';</script>";
            exit;
        }
    }

    // Single Delete (Soft Delete)
    if ($action === 'delete' && isset($_POST['request_id'])) {
        $request_id = (int)$_POST['request_id'];
        if ($request_id > 0) {
            if (move_to_recycle_bin($conn, $request_id, $username)) {
                mysqli_query($conn, "DELETE FROM hardware_request_assessment WHERE request_id = $request_id");
                mysqli_query($conn, "DELETE FROM hardware_request_approval_history WHERE request_id = $request_id");
                mysqli_query($conn, "DELETE FROM hardware_requests WHERE id = $request_id");
                
                echo "<script>alert('Request moved to Recycle Bin.'); window.location.href='all_requests.php';</script>";
            } else {
                echo "<script>alert('Failed to move request to Recycle Bin.'); window.location.href='all_requests.php';</script>";
            }
            exit;
        }
    }

    // Bulk Delete (Soft Delete)
    if ($action === 'bulk_delete' && !empty($_POST['ids'])) {
        $success_count = 0;
        foreach ($_POST['ids'] as $id) {
            $req_id = (int)$id;
            if ($req_id > 0 && move_to_recycle_bin($conn, $req_id, $username)) {
                mysqli_query($conn, "DELETE FROM hardware_request_assessment WHERE request_id = $req_id");
                mysqli_query($conn, "DELETE FROM hardware_request_approval_history WHERE request_id = $req_id");
                mysqli_query($conn, "DELETE FROM hardware_requests WHERE id = $req_id");
                $success_count++;
            }
        }
        echo "<script>alert('$success_count request(s) moved to Recycle Bin.'); window.location.href='all_requests.php';</script>";
        exit;
    }
}

/* Summary cards */
$summary = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'returned' => 0
];

$sum_sql = "SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN workflow_status LIKE 'pending_%' THEN 1 ELSE 0 END) AS pending_count,
    SUM(CASE WHEN workflow_status = 'approved_completed' OR current_stage = 'completed' THEN 1 ELSE 0 END) AS approved_count,
    SUM(CASE WHEN workflow_status LIKE 'returned_%' OR workflow_status = 'returned_for_edit' OR current_stage = 'returned_to_user' THEN 1 ELSE 0 END) AS returned_count
    FROM hardware_requests";
$sum_res = mysqli_query($conn, $sum_sql);
if ($sum_res && mysqli_num_rows($sum_res) > 0) {
    $summary = mysqli_fetch_assoc($sum_res);
}

// ==========================================
// PAGINATION LOGIC
// ==========================================
$limit = 10; // Number of records per page
$page = isset($_GET['page']) ? (int)trim($_GET['page']) : 1;
if ($page <= 0) $page = 1;
$offset = ($page - 1) * $limit;

// Get total records for pagination
$total_query = "SELECT COUNT(*) as total FROM hardware_requests";
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

/* Main table fetch with limit and offset */
$sql = "
SELECT 
    hr.*,
    COUNT(h.id) AS total_actions,
    MAX(h.approved_at) AS last_action_at
FROM hardware_requests hr
LEFT JOIN hardware_request_approval_history h ON h.request_id = hr.id
GROUP BY hr.id
ORDER BY hr.created_at DESC
LIMIT ? OFFSET ?
";

$stmt = mysqli_prepare($conn, $sql);
$rows = [];
if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $limit, $offset);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$page_title = 'All Requests - SCL AMS';
$page_header_icon = 'fas fa-table';
$page_header_title = 'All Requests Tracking';
$page_header_subtitle = 'Admin can monitor all requests, stages, actions and special controls';
$page_top_title = 'All Requests Tracking';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.summary-grid{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:16px;
    margin-bottom:20px;
}
.summary-card{
    background:#fff;
    border:1px solid #e2e8f0;
    border-radius:16px;
    padding:18px;
    box-shadow:0 8px 20px rgba(0,0,0,0.06);
}
.summary-card h6{
    margin:0 0 8px 0;
    color:#64748b;
    font-size:13px;
    text-transform:uppercase;
    font-weight:700;
    letter-spacing:.4px;
}
.summary-card .num{
    font-size:28px;
    font-weight:800;
    color:#0f172a;
}
.card-shell{
    background:#fff;
    border-radius:18px;
    border:1px solid #e2e8f0;
    overflow:hidden;
    box-shadow:0 10px 30px rgba(2,6,23,0.08);
}
.card-shell-header{
    background:linear-gradient(135deg,#0b2545 0%,#1e3a8a 100%);
    color:#fff;
    padding:20px 22px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}
.card-shell-title{
    font-size:20px;
    font-weight:800;
    display:flex;
    align-items:center;
    gap:15px;
}
.selection-stats{
    font-size:14px;
    font-weight:600;
    background:rgba(255,255,255,0.15);
    padding:4px 12px;
    border-radius:20px;
    display:inline-block;
}
.card-shell-actions{
    display:flex;
    align-items:center;
    gap:15px;
}
.card-shell-body{
    padding:20px;
}
.search-box{
    max-width:320px;
}
.search-box input{
    border-radius:12px;
    border:1px solid #cbd5e1;
    padding:10px 14px;
    color:#1e293b;
    background:#fff;
}
.badge-stage, .badge-status{
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:7px 12px;
    border-radius:999px;
    font-size:12px;
    font-weight:800;
    letter-spacing:.2px;
}
.stage-dept{ background:#dbeafe; color:#1d4ed8; }
.stage-hr{ background:#fce7f3; color:#be185d; }
.stage-assessor{ background:#ede9fe; color:#6d28d9; }
.stage-infra{ background:#dcfce7; color:#15803d; }
.stage-icthead{ background:#fef3c7; color:#b45309; }
.stage-completed{ background:#dcfce7; color:#166534; }
.stage-returned{ background:#fee2e2; color:#b91c1c; }
.stage-default{ background:#e5e7eb; color:#374151; }

.status-approved{ background:#dcfce7; color:#166534; }
.status-pending{ background:#fef3c7; color:#92400e; }
.status-returned{ background:#fee2e2; color:#b91c1c; }
.status-default{ background:#e5e7eb; color:#374151; }

.action-group{
    display:flex;
    gap:6px;
    flex-wrap:wrap;
    justify-content:center;
}
.action-group .btn{
    min-width:36px;
    border-radius:10px;
}
.table thead th{
    vertical-align:middle;
    white-space:nowrap;
}
.table tbody td{
    vertical-align:middle;
    color:#1f2937;
}
.ref-chip{
    font-weight:800;
    color:#0f172a;
}

@media (max-width: 992px){
    .summary-grid{
        grid-template-columns:repeat(2,1fr);
    }
}
@media (max-width: 576px){
    .summary-grid{
        grid-template-columns:1fr;
    }
    .search-box{
        max-width:100%;
        width:100%;
    }
    .card-shell-header{
        flex-direction:column;
        align-items:flex-start;
    }
    .card-shell-actions{
        width:100%;
        justify-content:space-between;
        flex-wrap:wrap;
    }
}

/* Native checkbox fix */
.custom-chk{
    width:18px;
    height:18px;
    cursor:pointer;
    accent-color:#2563eb;
    vertical-align:middle;
}

/* Dark Mode Support */
html[data-theme='dark'] .summary-card,
html[data-theme='dark'] .card-shell{
    background:#1f2937 !important;
    border-color:#374151 !important;
}
html[data-theme='dark'] .summary-card .num{
    color:#f9fafb !important;
}
html[data-theme='dark'] .summary-card h6{
    color:#cbd5e1 !important;
}
html[data-theme='dark'] .search-box input{
    background:#0f172a;
    border-color:#334155;
    color:#e2e8f0;
}
html[data-theme='dark'] .search-box input::placeholder{
    color:#94a3b8;
}
html[data-theme='dark'] .table{
    color:#e2e8f0 !important;
}
html[data-theme='dark'] .table thead th{
    background:#111827 !important;
    color:#f8fafc !important;
    border-color:#374151 !important;
}
html[data-theme='dark'] .table td{
    color:#e5e7eb !important;
    border-color:#374151 !important;
}
html[data-theme='dark'] .table tbody td,
html[data-theme='dark'] .table tbody th{
    color:#e5e7eb !important;
}
html[data-theme='dark'] .table-hover tbody tr:hover{
    background-color:rgba(255,255,255,0.04) !important;
}
html[data-theme='dark'] .ref-chip{
    color:#f8fafc !important;
}
html[data-theme='dark'] .custom-chk{
    accent-color:#60a5fa;
}
html[data-theme='dark'] ::selection{
    background:rgba(59,130,246,0.4);
    color:#f8fafc;
}

/* Pagination dark mode */
html[data-theme='dark'] .page-link{
    background-color:#1f2937;
    border-color:#374151;
    color:#cbd5e1;
}
html[data-theme='dark'] .page-link:hover{
    background-color:#374151;
    color:#fff;
}
html[data-theme='dark'] .page-item.active .page-link{
    background-color:#3b82f6;
    border-color:#3b82f6;
    color:#fff;
}
html[data-theme='dark'] .page-item.disabled .page-link{
    background-color:#111827;
    border-color:#374151;
    color:#64748b;
}
";

ob_start();
?>

<div class="summary-grid">
    <div class="summary-card">
        <h6>Total Requests</h6>
        <div class="num"><?php echo (int)($summary['total'] ?? 0); ?></div>
    </div>
    <div class="summary-card">
        <h6>Pending</h6>
        <div class="num"><?php echo (int)($summary['pending_count'] ?? 0); ?></div>
    </div>
    <div class="summary-card">
        <h6>Approved</h6>
        <div class="num"><?php echo (int)($summary['approved_count'] ?? 0); ?></div>
    </div>
    <div class="summary-card">
        <h6>Returned</h6>
        <div class="num"><?php echo (int)($summary['returned_count'] ?? 0); ?></div>
    </div>
</div>

<form method="POST" id="bulkDeleteForm">
    <input type="hidden" name="super_action" value="bulk_delete">
    
    <div class="card-shell">
        <div class="card-shell-header">
            <div class="card-shell-title">
                <i class="fas fa-list-check"></i> Full Request Tracking
                <div class="selection-stats">
                    Total: <?php echo $total_records; ?> | Selected: <span id="selectedCount">0</span>
                </div>
            </div>
            <div class="card-shell-actions">
                <div class="search-box">
                    <input type="text" id="requestSearch" class="form-control form-control-sm" placeholder="Search requests...">
                </div>
                <?php if ($is_superadmin): ?>
                <button type="submit" class="btn btn-danger btn-sm" id="btnBulkDelete" disabled onclick="return confirm('Move selected records to Recycle Bin?')">
                    <i class="fas fa-trash"></i> Delete Selected
                </button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-shell-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" id="requestTable">
                    <thead class="table-light text-center">
                        <tr>
                            <?php if ($is_superadmin): ?>
                            <th style="width: 40px;">
                                <input type="checkbox" class="custom-chk" id="selectAll">
                            </th>
                            <?php endif; ?>
                            <th style="width: 50px;">SL</th>
                            <th>Ref No</th>
                            <th>Requester</th>
                            <th>Requested For</th>
                            <th>Department</th>
                            <th>Request Type</th>
                            <th>Current Stage</th>
                            <th>Status</th>
                            <th>Last Action</th>
                            <th>Controls</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php 
                            $sl = $offset + 1; // Correct serial generation based on page
                            foreach ($rows as $row): 
                            ?>
                                <tr>
                                    <?php if ($is_superadmin): ?>
                                    <td class="text-center">
                                        <input type="checkbox" class="custom-chk item-checkbox" name="ids[]" value="<?php echo (int)$row['id']; ?>">
                                    </td>
                                    <?php endif; ?>
                                    <td class="text-center"><?php echo $sl++; ?></td>
                                    <td><span class="ref-chip"><?php echo h($row['ref_no']); ?></span></td>
                                    <td><?php echo h($row['requester_name']); ?></td>
                                    <td><?php echo h($row['requested_for_name']); ?></td>
                                    <td><?php echo h($row['requested_for_department']); ?></td>
                                    <td><?php echo h(ucwords(str_replace('_', ' ', $row['request_type'] ?? 'N/A'))); ?></td>
                                    <td class="text-center">
                                        <span class="badge-stage <?php echo h(stage_badge_class($row['current_stage'] ?? '')); ?>">
                                            <?php echo h(stage_label($row['current_stage'] ?? '')); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-status <?php echo h(status_badge_class($row['workflow_status'] ?? '')); ?>">
                                            <?php echo h(ucwords(str_replace('_', ' ', $row['workflow_status'] ?? ''))); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><?php echo h(safe_date($row['last_action_at'])); ?></td>
                                    <td class="text-center">
                                        <div class="action-group">
                                            <a href="request_view.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-primary" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>

                                            <?php if ($is_superadmin): ?>
                                                <a href="request_edit.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-warning text-dark" title="Edit">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                                <a href="request_print.php?id=<?php echo (int)$row['id']; ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Print">
                                                    <i class="fas fa-print"></i>
                                                </a>

                                                <button type="button" class="btn btn-sm btn-info text-dark" title="Return to Edit"
                                                    onclick="singleAction('return_to_edit', <?php echo (int)$row['id']; ?>, 'Return this request to user for edit?')">
                                                    <i class="fas fa-rotate-left"></i>
                                                </button>

                                                <button type="button" class="btn btn-sm btn-danger" title="Delete"
                                                    onclick="singleAction('delete', <?php echo (int)$row['id']; ?>, 'Move this request to Recycle Bin?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $is_superadmin ? '11' : '10'; ?>" class="text-center text-muted py-4">No requests found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Controls -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php if($page <= 1) echo 'disabled'; ?>">
                        <a class="page-link" href="<?php if($page > 1) echo "?page=".($page - 1); else echo '#'; ?>">Previous</a>
                    </li>
                    
                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                        <li class="page-item <?php if($page == $i) echo 'active'; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php if($page >= $total_pages) echo 'disabled'; ?>">
                        <a class="page-link" href="<?php if($page < $total_pages) echo "?page=".($page + 1); else echo '#'; ?>">Next</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        </div>
    </div>
</form>

<!-- Hidden form for Single Actions -->
<form id="singleActionForm" method="POST" style="display: none;">
    <input type="hidden" name="super_action" id="sa_action" value="">
    <input type="hidden" name="request_id" id="sa_id" value="">
</form>

<script>
// Search Functionality
document.getElementById('requestSearch').addEventListener('keyup', function () {
    const filter = this.value.toLowerCase();
    const rows = document.querySelectorAll('#requestTable tbody tr');

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
});

// Checkbox Selection Logic
const selectAll = document.getElementById('selectAll');
const checkboxes = document.querySelectorAll('.item-checkbox');
const selectedCount = document.getElementById('selectedCount');
const btnBulkDelete = document.getElementById('btnBulkDelete');

function updateCount() {
    let count = 0;
    checkboxes.forEach(cb => { if (cb.checked) count++; });
    selectedCount.innerText = count;
    
    if (btnBulkDelete) {
        btnBulkDelete.disabled = count === 0;
    }
    
    if (selectAll) {
        selectAll.checked = (count > 0 && count === checkboxes.length);
    }
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(cb => {
            // Only select visible rows (respecting search filter)
            if (cb.closest('tr').style.display !== 'none') {
                cb.checked = this.checked;
            }
        });
        updateCount();
    });
}

checkboxes.forEach(cb => {
    cb.addEventListener('change', updateCount);
});

// Helper for Single Actions
function singleAction(actionType, id, msg) {
    if (confirm(msg)) {
        document.getElementById('sa_action').value = actionType;
        document.getElementById('sa_id').value = id;
        document.getElementById('singleActionForm').submit();
    }
}
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>