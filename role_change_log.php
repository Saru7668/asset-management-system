<?php
session_start();
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$current_role = strtolower(trim($_SESSION['UserRole'] ?? 'user'));
if ($current_role !== 'superadmin') {
    die('Only SuperAdmin can access this page.');
}

$check_table = mysqli_query($conn, "SHOW TABLES LIKE 'role_change_log'");
if (!$check_table || mysqli_num_rows($check_table) === 0) {
    die('role_change_log table does not exist.');
}

$result = mysqli_query($conn, "SELECT * FROM role_change_log ORDER BY id DESC");

$page_title = 'Role Change Log - SCL AMS';
$page_header_icon = 'fas fa-clock-rotate-left';
$page_header_title = 'Role Change Log';
$page_header_subtitle = 'Track all user role changes';
$page_top_title = 'Role Change Log';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.log-card {
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
html[data-theme='dark'] .log-card {
    background: #1f2937 !important;
    border-color: #374151 !important;
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
";

ob_start();
?>

<div class="log-card">
    <div class="table-responsive">
        <table class="table table-hover table-bordered table-custom align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Old Role</th>
                    <th>New Role</th>
                    <th>Changed By</th>
                    <th>Changed At</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <tr>
                            <td><?php echo (int)$row['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['username']); ?></strong><br>
                                <small class="text-muted">User ID: <?php echo (int)$row['user_id']; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars(strtoupper($row['old_role'])); ?></td>
                            <td><?php echo htmlspecialchars(strtoupper($row['new_role'])); ?></td>
                            <td><?php echo htmlspecialchars($row['changed_by']); ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($row['changed_at'])); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">No role change log found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>