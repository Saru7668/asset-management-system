<?php
session_start();
require_once('db.php');
require_once('header.php');
require_once('request_mail_helper.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['UserName'];
$user_role = $_SESSION['UserRole'] ?? 'user';
$is_super_admin = in_array(strtolower($user_role), ['superadmin', 'admin']);

$user_stmt = mysqli_prepare($conn, "SELECT id, username, full_name, department FROM users WHERE username = ? LIMIT 1");
mysqli_stmt_bind_param($user_stmt, "s", $username);
mysqli_stmt_execute($user_stmt);
$user_result = mysqli_stmt_get_result($user_stmt);
$logged_user = mysqli_fetch_assoc($user_result);

if (!$logged_user) {
    die('User not found.');
}

/* Super Admin can return any request to edit mode */
if ($is_super_admin && isset($_GET['return_edit_id'])) {
    $return_edit_id = (int)($_GET['return_edit_id'] ?? 0);

    if ($return_edit_id > 0) {
        $req_fetch_stmt = mysqli_prepare($conn, "SELECT id, ref_no, requester_user_id, requester_name, requester_email FROM hardware_requests WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($req_fetch_stmt, "i", $return_edit_id);
        mysqli_stmt_execute($req_fetch_stmt);
        $req_fetch_result = mysqli_stmt_get_result($req_fetch_stmt);
        $req_data = mysqli_fetch_assoc($req_fetch_result);

        $upd_stmt = mysqli_prepare($conn, "UPDATE hardware_requests 
            SET workflow_status = 'returned_to_user', current_stage = 'department_head', updated_at = NOW()
            WHERE id = ?");
        mysqli_stmt_bind_param($upd_stmt, "i", $return_edit_id);
        mysqli_stmt_execute($upd_stmt);

        $history_check = mysqli_query($conn, "SHOW TABLES LIKE 'hardware_request_approval_history'");
        if ($history_check && mysqli_num_rows($history_check) > 0) {
            $remarks = 'Returned to edit mode by Super Admin';
            $approver_name = $username;
            $approval_stage = 'superadmin_return';
            $approval_status = 'Returned';
            $admin_id = (int)$logged_user['id'];

            $hist_stmt = mysqli_prepare($conn, "INSERT INTO hardware_request_approval_history
                (request_id, approver_id, approver_name, approval_stage, approval_status, remarks, approved_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($hist_stmt, "iissss", $return_edit_id, $admin_id, $approver_name, $approval_stage, $approval_status, $remarks);
            mysqli_stmt_execute($hist_stmt);
        }

        if (!empty($req_data['requester_email'])) {
            send_return_mail(
                $req_data['requester_email'],
                $req_data['requester_name'] ?? 'User',
                $req_data['ref_no'] ?? 'N/A',
                'Super Admin',
                'Your request has been returned to editable mode. Please review, update and re-submit it.',
                'http://sclams.sheltechceramics.com/edit_request.php?id=' . $return_edit_id
            );
        }

        echo "<script>alert('Request returned to editable mode successfully.'); window.location.href='my_requests.php';</script>";
        exit;
    }
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function status_badge_class($status) {
    $status = strtolower(trim((string)$status));
    if (strpos($status, 'pending') !== false) return 'bg-warning text-dark';
    if (strpos($status, 'approved') !== false || strpos($status, 'completed') !== false) return 'bg-success';
    if (strpos($status, 'returned') !== false) return 'bg-danger';
    if (strpos($status, 'rejected') !== false) return 'bg-danger';
    return 'bg-secondary';
}

function stage_label($stage) {
    $map = [
        'department_head' => 'Department Head',
        'hr_head' => 'HR Head',
        'ict_assessor' => 'ICT Assessor',
        'ict_infra_head' => 'ICT Infra Head',
        'ict_head' => 'ICT Head',
        'completed' => 'Completed',
        'returned_to_user' => 'Returned to User'
    ];
    return $map[$stage] ?? ucwords(str_replace('_', ' ', (string)$stage));
}

function workflow_label($workflow_status, $current_stage) {
    $workflow_status = strtolower(trim((string)$workflow_status));
    $current_stage = trim((string)$current_stage);

    if ($workflow_status === 'approved_completed' || $current_stage === 'completed') {
        return 'Approved / Completed';
    }

    if (strpos($workflow_status, 'returned') !== false) {
        return 'Returned';
    }

    if (strpos($workflow_status, 'pending_') === 0) {
        return 'Pending at ' . stage_label($current_stage);
    }

    if (strpos($workflow_status, 'approved_') === 0) {
        return 'Approved';
    }

    return ucwords(str_replace('_', ' ', $workflow_status));
}

function request_items_text($row) {
    $items = [];

    if (!empty($row['request_for_laptop'])) $items[] = 'Laptop';
    if (!empty($row['request_for_desktop'])) $items[] = 'Desktop';
    if (!empty($row['request_for_scanner'])) $items[] = 'Scanner';
    if (!empty($row['request_for_monitor'])) $items[] = 'Monitor';
    if (!empty($row['request_for_ram'])) $items[] = 'RAM';
    if (!empty($row['request_for_ssd'])) $items[] = 'SSD';
    if (!empty($row['request_for_hdd'])) $items[] = 'HDD';
    if (!empty($row['request_for_mouse_wired'])) $items[] = 'Wired Mouse';
    if (!empty($row['request_for_mouse_wireless'])) $items[] = 'Wireless Mouse';
    if (!empty($row['request_for_keyboard'])) $items[] = 'Keyboard';
    if (!empty($row['request_for_printer'])) $items[] = 'Printer';
    if (!empty($row['request_for_other'])) {
        $items[] = !empty($row['request_for_other_text']) ? $row['request_for_other_text'] : 'Other Accessories';
    }

    return !empty($items) ? implode(', ', $items) : 'N/A';
}

/* --- Pagination & Search Logic --- */
$search = trim($_GET['q'] ?? '');
$limit = 10; // Number of records per page
$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0 ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Building conditions
$conditions = [];
$params = [];
$types = "";

if (!$is_super_admin) {
    $conditions[] = "requester_user_id = ?";
    $params[] = $logged_user['id'];
    $types .= "i";
}

if ($search !== '') {
    $like = "%" . $search . "%";
    $search_cond = "(ref_no LIKE ? OR requester_name LIKE ? OR requester_email LIKE ? OR requested_for_name LIKE ? OR requested_for_emp_id LIKE ? OR requested_for_department LIKE ? OR request_type LIKE ?)";
    $conditions[] = $search_cond;
    // Add param 7 times
    for ($i=0; $i<7; $i++) {
        $params[] = $like;
        $types .= "s";
    }
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

// 1. Get total records for pagination
$count_query = "SELECT COUNT(*) as total FROM hardware_requests $where_clause";
$stmt_count = mysqli_prepare($conn, $count_query);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_count, $types, ...$params);
}
mysqli_stmt_execute($stmt_count);
$count_result = mysqli_stmt_get_result($stmt_count);
$total_row = mysqli_fetch_assoc($count_result);
$total_records = $total_row['total'];
$total_pages = ceil($total_records / $limit);

// 2. Fetch limited records for current page
$data_query = "SELECT * FROM hardware_requests $where_clause ORDER BY id DESC LIMIT ? OFFSET ?";
$stmt_data = mysqli_prepare($conn, $data_query);

// Append limit and offset to params
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

mysqli_stmt_bind_param($stmt_data, $types, ...$params);
mysqli_stmt_execute($stmt_data);
$result = mysqli_stmt_get_result($stmt_data);
/* --- End Pagination & Search Logic --- */

$page_title = 'My Requests - SCL AMS';
$page_header_icon = 'fas fa-clipboard-list';
$page_header_title = 'My Requests';
$page_header_subtitle = 'Track submitted ICT hardware requests and approval progress';
$page_top_title = 'My Requests';
$page_top_actions = '
<a href="submit_request.php" class="btn btn-primary fw-bold" style="background:#0b2545; border-color:#0b2545;">
    <i class="fas fa-plus-circle me-1"></i> New Request
</a>';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.request-list-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
    border: 1px solid #e2e8f0;
}
.table-custom th {
    background: #1e293b !important;
    color: #fff !important;
    white-space: nowrap;
    font-size: 13px;
}
.table-custom td {
    vertical-align: middle;
    font-size: 13px;
}
.ref-no {
    font-weight: 700;
    color: #0b2545;
}
.item-text {
    max-width: 300px;
    white-space: normal;
}
.action-btns {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}
.search-box-wrap {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}
.search-box-wrap .form-control {
    min-width: 280px;
}
/* Pagination styles */
.pagination-wrap {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    flex-wrap: wrap;
    gap: 10px;
}
.page-info {
    font-size: 13px;
    color: #64748b;
}

html[data-theme='dark'] .request-list-card {
    background: #1f2937 !important;
    border-color: #374151 !important;
    border-top-color: #facc15 !important;
}
html[data-theme='dark'] .table-custom td {
    background: #1f2937 !important;
    color: #f3f4f6 !important;
    border-color: #374151 !important;
}
html[data-theme='dark'] .table-custom th {
    background: #111827 !important;
    color: #f9fafb !important;
    border-color: #374151 !important;
}
html[data-theme='dark'] .ref-no {
    color: #93c5fd !important;
}
html[data-theme='dark'] .page-info {
    color: #9ca3af;
}

/* Fix Print Icon color in dark mode */
html[data-theme='dark'] .btn-outline-dark {
    color: #f8fafc !important;
    border-color: #f8fafc !important;
}
html[data-theme='dark'] .btn-outline-dark:hover {
    background-color: #f8fafc !important;
    color: #0f172a !important;
}
";

ob_start();
?>

<div class="request-list-card">

    <form method="GET" action="my_requests.php" class="search-box-wrap">
        <input
            type="text"
            name="q"
            class="form-control"
            placeholder="Search by Ref No / Department / Requester Name"
            value="<?php echo h($search); ?>"
        >
        <button type="submit" class="btn btn-primary" style="background:#0b2545; border-color:#0b2545;">
            <i class="fas fa-search me-1"></i> Search
        </button>
        <?php if ($search !== ''): ?>
            <a href="my_requests.php" class="btn btn-secondary">
                <i class="fas fa-times me-1"></i> Reset
            </a>
        <?php endif; ?>
    </form>

    <div class="table-responsive">
        <table class="table table-hover table-bordered table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Ref No</th>
                    <th>Request Type</th>
                    <th>Requested For</th>
                    <th>Department</th>
                    <th>Requirement</th>
                    <th>Approval Section</th>
                    <th>Status</th>
                    <th>Submitted At</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php 
                    // Calculate starting serial number based on page
                    $sl = $offset + 1; 
                    while ($row = mysqli_fetch_assoc($result)): 
                        $is_returned = (strpos(strtolower($row['workflow_status'] ?? ''), 'returned') !== false);
                    ?>
                        <tr>
                            <td><?php echo $sl++; ?></td>
                            <td class="ref-no"><?php echo h($row['ref_no']); ?></td>
                            <td><?php echo h(ucwords(str_replace('_', ' ', $row['request_type']))); ?></td>
                            <td>
                                <strong><?php echo h($row['requested_for_name']); ?></strong><br>
                                <small class="text-muted"><?php echo h($row['requested_for_emp_id']); ?></small>
                            </td>
                            <td><?php echo h($row['requested_for_department']); ?></td>
                            <td class="item-text"><?php echo h(request_items_text($row)); ?></td>
                            <td><?php echo h(stage_label($row['current_stage'])); ?></td>
                            <td>
                                <span class="badge <?php echo status_badge_class($row['workflow_status']); ?>">
                                    <?php echo h(workflow_label($row['workflow_status'], $row['current_stage'])); ?>
                                </span>
                            </td>
                            <td><?php echo h(date('d M Y, h:i A', strtotime($row['created_at']))); ?></td>
                            <td>
                                <div class="action-btns">
                                    <?php if ($is_returned): ?>
                                        <a href="edit_request.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-warning text-dark">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($is_super_admin): ?>
                                        <a href="my_requests.php?return_edit_id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-warning text-dark"
                                           onclick="return confirm('Return this request to editable mode?');">
                                            <i class="fas fa-rotate-left"></i> Return to Edit
                                        </a>

                                        <a href="request_delete.php?id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-danger"
                                           onclick="return confirm('Are you sure you want to delete this request?');">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                        
                                        <a href="request_print.php?id=<?php echo (int)$row['id']; ?>" 
                                           class="btn btn-sm btn-outline-dark"
                                           target="_blank"
                                           title="Print Request">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center py-5">
                            <div class="text-muted mb-3">No requests found.</div>
                            <a href="submit_request.php" class="btn btn-primary" style="background:#0b2545; border-color:#0b2545;">
                                <i class="fas fa-plus-circle me-1"></i> Submit New Request
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination UI -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination-wrap">
        <div class="page-info">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $limit, $total_records); ?> of <?php echo $total_records; ?> entries
        </div>
        <nav aria-label="Page navigation">
            <ul class="pagination pagination-sm mb-0">
                <?php
                // Preserve search query in pagination links
                $q_param = ($search !== '') ? '&q=' . urlencode($search) : '';
                
                // Previous button
                if ($page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($page - 1) . $q_param . '">Previous</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
                }

                // Page numbers (Limit to display a few around the current page)
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                if ($start_page > 1) {
                    echo '<li class="page-item"><a class="page-link" href="?page=1' . $q_param . '">1</a></li>';
                    if ($start_page > 2) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                }

                for ($i = $start_page; $i <= $end_page; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="?page=' . $i . $q_param . '">' . $i . '</a></li>';
                }

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $q_param . '">' . $total_pages . '</a></li>';
                }

                // Next button
                if ($page < $total_pages) {
                    echo '<li class="page-item"><a class="page-link" href="?page=' . ($page + 1) . $q_param . '">Next</a></li>';
                } else {
                    echo '<li class="page-item disabled"><span class="page-link">Next</span></li>';
                }
                ?>
            </ul>
        </nav>
    </div>
    <?php endif; ?>

</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>