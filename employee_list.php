<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once('db.php');
require_once('header.php');

// Security: Only SuperAdmin, HR, Staff, Admin
$role = $_SESSION['UserRole'] ?? '';
if (!in_array($role, ['SuperAdmin', 'HR', 'Staff', 'admin'])) {
    header("Location: login.php");
    exit;
}

// Fetch all employees from the database
$query = "
    SELECT *,
           DATE_ADD(date_of_joining, INTERVAL 6 MONTH) AS probation_review_date
    FROM employees
    ORDER BY id DESC
";
$result = mysqli_query($conn, $query);

$page_title = 'Employee List - SCL AMS';
$page_header_icon = 'fas fa-users';
$page_header_title = 'Employee Directory';
$page_header_subtitle = 'Browse, manage, and review employee records';
$page_top_title = 'Employee Directory';
$page_top_actions = '
<div class="page-actions">
    <a href="add_employee.php" class="btn btn-success fw-bold">
        <i class="fas fa-user-plus me-1"></i> Add Employee
    </a>
    <a href="upload_employees.php" class="btn fw-bold text-white" style="background-color: #0b2545;">
        <i class="fas fa-file-upload me-1"></i> Upload CSV
    </a>
</div>';
$page_container_class = 'dashboard-container-wide';

$extra_css = "
.dashboard-container-wide {
    max-width: 1600px;
    margin: 0 auto;
}
.page-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.card-custom {
    background: #ffffff;
    border-radius: 14px;
    padding: 25px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
    border: 1px solid #e2e8f0;
}
.page-title {
    color: #0b2545;
}
.table-custom th {
    background: #1e293b !important;
    color: white !important;
    font-weight: 500;
    font-size: 13px;
    white-space: nowrap;
}
.table-custom td {
    vertical-align: middle;
    font-size: 13px;
    background: #ffffff;
    color: #0f172a;
}
.badge-dept {
    background-color: #eab308;
    color: black;
    font-weight: bold;
}
.badge-category {
    background-color: #dbeafe;
    color: #1d4ed8;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 999px;
    display: inline-block;
}
.badge-status {
    background-color: #dcfce7;
    color: #166534;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 999px;
    display: inline-block;
}
.badge-status.probation {
    background-color: #fef3c7;
    color: #92400e;
}
.badge-alert-overdue {
    background: #fee2e2;
    color: #991b1b;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 999px;
    display: inline-block;
    font-size: 12px;
}
.badge-alert-upcoming {
    background: #fef3c7;
    color: #92400e;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 999px;
    display: inline-block;
    font-size: 12px;
}
.badge-alert-none {
    background: #e2e8f0;
    color: #475569;
    font-weight: 700;
    padding: 6px 10px;
    border-radius: 999px;
    display: inline-block;
    font-size: 12px;
}
.row-overdue td {
    background-color: #fff7f7 !important;
}
.row-upcoming td {
    background-color: #fffdf2 !important;
}
.info-cell {
    font-size: 11px;
    color: #6c757d;
}
.info-cell strong {
    color: #0b2545;
}
.table-custom a {
    text-decoration: none;
}
table.dataTable thead th,
table.dataTable thead td {
    border-bottom: none !important;
}
.dataTables_wrapper .dataTables_filter input {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 6px 10px;
    background: #fff;
    color: #0f172a;
}
.dataTables_wrapper .dataTables_length select {
    border-radius: 8px;
    border: 1px solid #cbd5e1;
    padding: 4px 8px;
    background: #fff;
    color: #0f172a;
}
.dataTables_wrapper .dataTables_info,
.dataTables_wrapper .dataTables_length,
.dataTables_wrapper .dataTables_filter label {
    color: #475569 !important;
}
.dataTables_wrapper .page-link {
    color: #0b2545;
    border-color: #cbd5e1;
}
.dataTables_wrapper .page-item.active .page-link {
    background-color: #0b2545;
    border-color: #0b2545;
    color: #fff;
}
.dataTables_wrapper .page-item.disabled .page-link {
    color: #94a3b8;
}
.table-hover tbody tr:hover td {
    background: #f8fafc;
}

/* Dark mode overrides */
html[data-theme='dark'] .page-title {
    color: #f8fafc !important;
}

html[data-theme='dark'] .card-custom {
    background: #1f2937 !important;
    border: 1px solid #374151 !important;
    border-top: 4px solid #facc15 !important;
    box-shadow: 0 14px 28px rgba(0,0,0,0.28) !important;
}

html[data-theme='dark'] .table {
    color: #f3f4f6 !important;
}

html[data-theme='dark'] .table-custom th {
    background: #111827 !important;
    color: #f9fafb !important;
    border-color: #374151 !important;
}

html[data-theme='dark'] .table-custom td {
    background: #1f2937 !important;
    color: #f3f4f6 !important;
    border-color: #374151 !important;
}

html[data-theme='dark'] .table-hover tbody tr:hover td {
    background: #2b3545 !important;
}

html[data-theme='dark'] .row-overdue td {
    background-color: rgba(239, 68, 68, 0.08) !important;
}

html[data-theme='dark'] .row-upcoming td {
    background-color: rgba(250, 204, 21, 0.08) !important;
}

html[data-theme='dark'] .info-cell {
    color: #9ca3af !important;
}

html[data-theme='dark'] .info-cell strong {
    color: #f9fafb !important;
}

html[data-theme='dark'] .badge-dept {
    background-color: #facc15 !important;
    color: #111827 !important;
}

html[data-theme='dark'] .badge-category {
    background-color: rgba(59, 130, 246, 0.18) !important;
    color: #93c5fd !important;
}

html[data-theme='dark'] .badge-status {
    background-color: rgba(34, 197, 94, 0.18) !important;
    color: #86efac !important;
}

html[data-theme='dark'] .badge-status.probation {
    background-color: rgba(250, 204, 21, 0.18) !important;
    color: #fde68a !important;
}

html[data-theme='dark'] .badge-alert-overdue {
    background: rgba(239, 68, 68, 0.18) !important;
    color: #fca5a5 !important;
}

html[data-theme='dark'] .badge-alert-upcoming {
    background: rgba(250, 204, 21, 0.18) !important;
    color: #fde68a !important;
}

html[data-theme='dark'] .badge-alert-none {
    background: rgba(148, 163, 184, 0.16) !important;
    color: #cbd5e1 !important;
}

html[data-theme='dark'] .text-primary,
html[data-theme='dark'] .fw-bold.text-primary {
    color: #93c5fd !important;
}

html[data-theme='dark'] .text-muted {
    color: #9ca3af !important;
}

html[data-theme='dark'] .table-custom a {
    color: #93c5fd !important;
}

html[data-theme='dark'] .table-custom a:hover {
    color: #fde68a !important;
}

html[data-theme='dark'] .btn-success {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%) !important;
    border-color: #16a34a !important;
    color: #fff !important;
}

html[data-theme='dark'] .btn-warning {
    background: linear-gradient(135deg, #facc15 0%, #f59e0b 100%) !important;
    border-color: #f59e0b !important;
    color: #111111 !important;
    font-weight: 700 !important;
}

html[data-theme='dark'] .btn-warning i {
    color: #111111 !important;
}

html[data-theme='dark'] .btn-warning:hover,
html[data-theme='dark'] .btn-warning:focus,
html[data-theme='dark'] .btn-warning:active {
    background: linear-gradient(135deg, #fde047 0%, #facc15 100%) !important;
    border-color: #facc15 !important;
    color: #000000 !important;
    box-shadow: 0 0 0 0.2rem rgba(250, 204, 21, 0.18) !important;
}

html[data-theme='dark'] .btn-warning:hover i,
html[data-theme='dark'] .btn-warning:focus i,
html[data-theme='dark'] .btn-warning:active i {
    color: #000000 !important;
}

html[data-theme='dark'] a.btn.btn-sm.btn-warning,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:link,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:visited,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:hover,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:focus,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:active {
    background: linear-gradient(135deg, #facc15 0%, #f59e0b 100%) !important;
    border-color: #f59e0b !important;
    color: #111111 !important;
    font-weight: 700 !important;
}

html[data-theme='dark'] a.btn.btn-sm.btn-warning i,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:hover i,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:focus i,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:active i,
html[data-theme='dark'] a.btn.btn-sm.btn-warning:visited i {
    color: #111111 !important;
}

html[data-theme='dark'] .btn-primary {
    background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%) !important;
    border-color: #1d4ed8 !important;
    color: #fff !important;
}

html[data-theme='dark'] .dataTables_wrapper .dataTables_filter input {
    background: #111827 !important;
    color: #f9fafb !important;
    border: 1px solid #4b5563 !important;
}

html[data-theme='dark'] .dataTables_wrapper .dataTables_length select {
    background: #111827 !important;
    color: #f9fafb !important;
    border: 1px solid #4b5563 !important;
}

html[data-theme='dark'] .dataTables_wrapper .dataTables_info,
html[data-theme='dark'] .dataTables_wrapper .dataTables_length,
html[data-theme='dark'] .dataTables_wrapper .dataTables_filter label {
    color: #cbd5e1 !important;
}

html[data-theme='dark'] .dataTables_wrapper .page-link {
    background: #111827 !important;
    color: #f3f4f6 !important;
    border-color: #4b5563 !important;
}

html[data-theme='dark'] .dataTables_wrapper .page-item.active .page-link {
    background: #facc15 !important;
    color: #111827 !important;
    border-color: #facc15 !important;
}

html[data-theme='dark'] .dataTables_wrapper .page-item.disabled .page-link {
    background: #1f2937 !important;
    color: #6b7280 !important;
    border-color: #374151 !important;
}

@media (max-width: 991px) {
    .card-custom {
        padding: 15px;
    }
    .page-actions {
        width: 100%;
    }
    .page-actions a {
        flex: 1 1 auto;
        text-align: center;
    }
}
";

ob_start();
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

<div class="card-custom">
    <div class="table-responsive">
        <table id="employeeTable" class="table table-hover table-bordered table-custom">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Employee Name</th>
                    <th>Designation & Dept</th>
                    <th>Contact Info</th>
                    <th>Joining Info</th>
                    <th>Category & Status</th>
                    <th>Notification</th>
                    <th>Entry Info</th>
                    <th>Last Update</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($result)): ?>
                <?php
                    $status_raw = trim((string)($row['employee_status'] ?? ''));
                    $status = strtolower($status_raw);
                    $joining_date = $row['date_of_joining'] ?? '';
                    $review_date = $row['probation_review_date'] ?? '';
                    $today = date('Y-m-d');

                    $notification_html = '<span class="badge-alert-none">No Alert</span>';
                    $row_class = '';

                    if ($status === 'probation' && !empty($joining_date) && !empty($review_date)) {
                        if ($today > $review_date) {
                            $notification_html = '<span class="badge-alert-overdue"><i class="fas fa-exclamation-circle me-1"></i>Overdue for Confirmation</span>';
                            $row_class = 'row-overdue';
                        } else {
                            $days_remaining = floor((strtotime($review_date) - strtotime($today)) / 86400);

                            if ($days_remaining >= 0 && $days_remaining <= 30) {
                                $notification_html = '<span class="badge-alert-upcoming"><i class="fas fa-bell me-1"></i>Review Due in ' . $days_remaining . ' day(s)</span>';
                                $row_class = 'row-upcoming';
                            } else {
                                $notification_html = '<span class="badge-alert-none"><i class="fas fa-clock me-1"></i>Probation Running</span>';
                            }
                        }
                    }
                ?>
                <tr class="<?php echo $row_class; ?>">
                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($row['employee_id']); ?></td>
                    <td class="fw-bold"><?php echo htmlspecialchars($row['employee_name']); ?></td>

                    <td>
                        <div><?php echo htmlspecialchars($row['designation']); ?></div>
                        <span class="badge badge-dept mt-1"><?php echo htmlspecialchars($row['department']); ?></span>
                    </td>

                    <td>
                        <div><i class="fas fa-map-marker-alt text-muted me-1"></i> <?php echo htmlspecialchars($row['location'] ?? 'N/A'); ?></div>
                        <div><i class="fas fa-envelope text-muted me-1"></i> <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>"><?php echo htmlspecialchars($row['email']); ?></a></div>
                        <div><i class="fas fa-phone text-muted me-1"></i> <?php echo htmlspecialchars($row['phone'] ?? 'N/A'); ?></div>
                    </td>

                    <td>
                        <?php if (!empty($row['date_of_joining']) && $row['date_of_joining'] !== '0000-00-00'): ?>
                            <div>
                                <i class="fas fa-calendar-alt text-muted me-1"></i>
                                <?php echo htmlspecialchars(date('d M Y', strtotime($row['date_of_joining']))); ?>
                            </div>

                            <?php if (!empty($row['probation_review_date'])): ?>
                                <div class="info-cell mt-1">
                                    <strong>Review:</strong> <?php echo htmlspecialchars(date('d M Y', strtotime($row['probation_review_date']))); ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="mb-1">
                            <span class="badge-category">
                                <?php echo htmlspecialchars($row['employee_category'] ?? 'N/A'); ?>
                            </span>
                        </div>
                        <div>
                            <span class="badge-status <?php echo strtolower(($row['employee_status'] ?? '')) === 'probation' ? 'probation' : ''; ?>">
                                <?php echo htmlspecialchars($row['employee_status'] ?? 'N/A'); ?>
                            </span>
                        </div>
                    </td>

                    <td>
                        <?php echo $notification_html; ?>
                    </td>
                    
                    <td class="info-cell">
                        <?php if(!empty($row['created_by'])): ?>
                            <strong><?php echo htmlspecialchars($row['created_by']); ?></strong><br>
                            <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($row['created_at']))); ?>
                        <?php else: ?>
                            <span class="text-muted">N/A</span>
                        <?php endif; ?>
                    </td>

                    <td class="info-cell">
                        <?php if(!empty($row['updated_by'])): ?>
                            <strong><?php echo htmlspecialchars($row['updated_by']); ?></strong><br>
                            <?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($row['updated_at']))); ?>
                        <?php else: ?>
                            <span class="text-muted">Not updated</span>
                        <?php endif; ?>
                    </td>

                    <td style="white-space: nowrap;">
                        <a href="edit_employee.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning mb-1" title="Edit Employee">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <br>
                        <a href="employee_devices.php?emp_id=<?php echo urlencode($row['employee_id']); ?>" class="btn btn-sm btn-primary" title="View ICT Devices">
                            <i class="fas fa-desktop"></i> Devices
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#employeeTable').DataTable({
        pageLength: 10,
        language: { search: "Quick Search:" },
        order: [[0, "desc"]]
    });
});
</script>

<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
?>