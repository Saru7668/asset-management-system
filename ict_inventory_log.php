<?php
session_start();
require_once('db.php');
require_once('header.php');

$role = strtolower($_SESSION['UserRole'] ?? '');
if (!in_array($role, ['admin', 'superadmin', 'staff', 'hr'])) {
    header("Location: index.php");
    exit;
}

$asset_id = (int)($_GET['id'] ?? 0);
$is_admin = in_array($role, ['admin', 'superadmin']);

$asset_q = mysqli_query($conn, "SELECT inventory, details FROM assets WHERE id = $asset_id LIMIT 1");
if (!$asset_q || mysqli_num_rows($asset_q) == 0) {
    header("Location: ict_inventory_list.php");
    exit;
}
$asset = mysqli_fetch_assoc($asset_q);

// Admin Delete Logic
if (isset($_GET['del']) && $is_admin) {
    $del = (int)$_GET['del'];
    mysqli_query($conn, "DELETE FROM asset_logs WHERE id = $del");
    header("Location: ict_inventory_log.php?id=$asset_id");
    exit;
}

$logs = mysqli_query($conn, "SELECT * FROM asset_logs WHERE asset_id = $asset_id ORDER BY action_date DESC");

$page_title = 'Asset History Log - SCL AMS';
$page_header_icon = 'fas fa-history';
$page_header_title = 'Asset History Log';
$page_header_subtitle = 'Review assignment and movement history for this asset';
$page_top_title = 'Asset History Log';
$page_top_actions = '<a href="ict_inventory_list.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to List</a>';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.history-table {
    min-width: 900px;
    margin-bottom: 0;
}
.history-table thead th {
    background-color: #1e293b;
    color: #fff;
    white-space: nowrap;
    font-size: 13px;
    text-align: center;
    padding: 10px;
    border-right: 1px solid #495057;
}
.history-table tbody td {
    font-size: 13px;
    vertical-align: middle;
    white-space: nowrap;
    padding: 10px;
    border-right: 1px solid #dee2e6;
}
.table-card .card-header {
    background: #1f2937;
    color: #fff;
    padding: 14px 18px;
    font-weight: 600;
}
";

ob_start();
?>
<div class="info-box">
    <div class="fw-bold text-primary mb-1"><?php echo htmlspecialchars($asset['inventory']); ?></div>
    <div class="text-muted small"><?php echo htmlspecialchars($asset['details'] ?? 'No details available'); ?></div>
</div>

<div class="table-card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span class="fw-semibold">
            <i class="fas fa-clock me-2"></i>History Records
        </span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover history-table mb-0 bg-white">
                <thead class="table-dark">
                    <tr>
                        <th>Date</th>
                        <th>Action</th>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Performed By</th>
                        <?php if ($is_admin): ?>
                            <th>Admin</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs && mysqli_num_rows($logs) > 0): ?>
                        <?php while ($r = mysqli_fetch_assoc($logs)): ?>
                        <tr>
                            <td><?php echo date('d M Y, h:i A', strtotime($r['action_date'])); ?></td>
                            <td class="text-center">
                                <span class="badge <?php echo $r['action_type'] == 'Assigned' ? 'bg-success' : 'bg-warning text-dark'; ?>">
                                    <?php echo htmlspecialchars($r['action_type']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($r['employee_id']); ?></td>
                            <td><?php echo htmlspecialchars($r['employee_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['department']); ?></td>
                            <td><?php echo htmlspecialchars($r['action_by']); ?></td>
                            <?php if ($is_admin): ?>
                                <td class="text-center">
                                    <button type="button"
                                            class="btn btn-sm btn-danger py-0 px-2"
                                            onclick="deleteLog(<?php echo $r['id']; ?>)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            <?php endif; ?>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $is_admin ? 7 : 6; ?>" class="text-center text-muted py-4">
                                <i class="fas fa-folder-open me-2"></i>No history found for this asset.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function deleteLog(logId) {
    Swal.fire({
        title: 'Delete this log?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-trash"></i> Yes, Delete'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?id=<?php echo $asset_id; ?>&del=' + logId;
        }
    });
}
</script>
<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
