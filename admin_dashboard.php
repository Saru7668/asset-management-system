<?php
date_default_timezone_set('Asia/Dhaka');

session_start();
require_once('db.php');
require_once('header.php');

// Security Check
$role = strtolower($_SESSION['UserRole'] ?? '');
if (!isset($_SESSION['UserName']) || !in_array($role, ['admin', 'superadmin'])) {
    header("Location: index.php");
    exit;
}

// ==================== REAL DATA QUERIES ====================

// 1. Total Assets
$total_assets_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets");
$total_assets = mysqli_fetch_assoc($total_assets_q)['total'] ?? 0;

// 2. Assigned Assets
$assigned_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE employee_id IS NOT NULL AND employee_id != ''");
$assigned_assets = mysqli_fetch_assoc($assigned_q)['total'] ?? 0;

// 3. In Stock / Unassigned
$in_stock_q = mysqli_query($conn, "SELECT COUNT(*) as total FROM assets WHERE (employee_id IS NULL OR employee_id = '') AND status != 'Damage'");
$in_stock = mysqli_fetch_assoc($in_stock_q)['total'] ?? 0;

// 4. Total Users
$user_query = "SELECT COUNT(id) as total_users FROM users";
$user_result = mysqli_query($conn, $user_query);
$total_users = mysqli_fetch_assoc($user_result)['total_users'] ?? 0;

// 5. Departments with most assets
$dept_stats = [];
$dept_q = mysqli_query($conn, "
    SELECT department, COUNT(*) as count 
    FROM assets 
    GROUP BY department 
    ORDER BY count DESC 
    LIMIT 3
");
if ($dept_q) {
    while ($dept = mysqli_fetch_assoc($dept_q)) {
        $dept_stats[] = $dept;
    }
}

// 6. Recent Assets
$recent_q = mysqli_query($conn, "
    SELECT inventory, employee_name, department, status, entry_datetime 
    FROM assets 
    ORDER BY id DESC 
    LIMIT 5
");
$recent_assets = [];
if ($recent_q) {
    while ($asset = mysqli_fetch_assoc($recent_q)) {
        $recent_assets[] = $asset;
    }
}

// 7. Assets by Status
$status_q = mysqli_query($conn, "
    SELECT status, COUNT(*) as count 
    FROM assets 
    GROUP BY status
");
$status_stats = [];
if ($status_q) {
    while ($stat = mysqli_fetch_assoc($status_q)) {
        $status_stats[$stat['status']] = $stat['count'];
    }
}

$assignment_rate = ($total_assets > 0) ? round(($assigned_assets / $total_assets) * 100) : 0;

$page_title = 'Admin Dashboard - SCL AMS';
$page_header_icon = 'fas fa-tachometer-alt';
$page_header_title = 'Admin Dashboard';
$page_header_subtitle = 'Complete overview of SCL IT Asset Management System';
$page_top_title = 'Admin Dashboard';
$page_top_actions = '<div class="page-subtle"><i class="fas fa-calendar-alt me-1"></i>' . date('l, d F Y \a\t g:i A') . '</div>';
$page_container_class = 'dashboard-container-xl';

$extra_css = "
.welcome-box {
    background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
    color: white;
    padding: 30px;
    border-radius: 16px;
    box-shadow: 0 15px 35px rgba(11,37,69,0.3);
    margin-bottom: 30px;
    position: relative;
    overflow: hidden;
}
.welcome-box::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: float 6s ease-in-out infinite;
}
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-20px); }
}
.welcome-box h4,
.welcome-box p,
.welcome-box .progress {
    position: relative;
    z-index: 1;
}
.welcome-box h4 {
    font-size: 28px;
    margin: 0 0 15px 0;
    font-weight: 700;
}
.welcome-box p {
    font-size: 16px;
    opacity: 0.95;
    margin: 0 0 20px 0;
}
.dash-card {
    border-radius: 12px;
    color: white;
    padding: 25px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    margin-bottom: 25px;
    border-top: 4px solid;
}
.dash-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(0,0,0,0.15);
}
.dash-card h3 {
    font-size: 42px;
    font-weight: 800;
    margin: 0 0 8px 0;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
}
.dash-card p {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.5px;
}
.dash-card i.bg-icon {
    position: absolute;
    right: 20px;
    bottom: 20px;
    font-size: 90px;
    color: rgba(255, 255, 255, 0.15);
    transition: all 0.3s ease;
}
.dash-card:hover i.bg-icon {
    transform: scale(1.1) rotate(5deg);
}
.card-footer-link {
    display: block;
    background: rgba(255,255,255,0.2);
    color: white;
    text-align: center;
    padding: 12px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    border-radius: 0 0 8px 8px;
    transition: all 0.3s;
    margin: 20px -25px -25px -25px;
}
.card-footer-link:hover {
    background: rgba(255,255,255,0.3);
    transform: translateY(-2px);
    color: white;
}
.bg-teal { background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%); border-top-color: #17a2b8; }
.bg-pink { background: linear-gradient(135deg, #e83e8c 0%, #d63384 100%); border-top-color: #c2185b; }
.bg-orange { background: linear-gradient(135deg, #fd7e14 0%, #e65c00 100%); border-top-color: #d1480f; }
.bg-purple { background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%); border-top-color: #4c1d7e; }
.bg-blue { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); border-top-color: #084298; }
.stats-grid {
    margin-bottom: 30px;
}
.recent-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
    margin-bottom: 28px;
}
.recent-table .table thead th {
    background: linear-gradient(135deg, #0b2545 0%, #1e3a8a 100%);
    color: white;
    border: none;
    font-weight: 600;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}
.recent-table .table tbody td {
    border-color: #e9ecef;
    vertical-align: middle;
    padding: 15px 12px;
}
.status-badge {
    font-size: 11px;
    padding: 4px 8px;
}
.action-btns .btn {
    padding: 15px 25px;
    font-weight: 600;
    font-size: 15px;
    border-radius: 10px;
    width: 100%;
    margin-bottom: 15px;
    transition: all 0.3s;
    border: none;
}
.action-btns .btn:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}
@media (max-width: 991px) {
    .welcome-box {
        padding: 22px 18px;
    }
    .welcome-box h4 {
        font-size: 23px;
    }
    .dash-card h3 {
        font-size: 34px;
    }
}
";

ob_start();
?>
<div class="welcome-box mb-5">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h4>Welcome back, <?php echo htmlspecialchars($_SESSION['UserName']); ?>!</h4>
            <p>Complete overview of SCL IT Asset Management System. Monitor stock, track assignments, and manage users efficiently.</p>
        </div>
        <div class="col-md-4 text-md-end">
            <div class="progress mb-3 mx-auto mx-md-0" style="width: 250px; height: 12px;">
                <div class="progress-bar bg-success"
                     role="progressbar"
                     style="width: <?php echo $assignment_rate; ?>%;"
                     aria-valuenow="<?php echo $assignment_rate; ?>"
                     aria-valuemin="0"
                     aria-valuemax="100">
                    <?php echo $assignment_rate; ?>% Assigned
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row stats-grid g-4">
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="dash-card bg-teal h-100">
            <h3><?php echo $total_assets; ?></h3>
            <p>Total Assets</p>
            <i class="fas fa-barcode bg-icon"></i>
            <a href="ict_inventory_list.php" class="card-footer-link">
                View Inventory <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="dash-card bg-pink h-100">
            <h3><?php echo $assigned_assets; ?></h3>
            <p>Assigned Assets</p>
            <i class="fas fa-user-check bg-icon"></i>
            <a href="ict_inventory_list.php?status=Assigned" class="card-footer-link">
                View Assigned <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="dash-card bg-orange h-100">
            <h3><?php echo $in_stock; ?></h3>
            <p>Available Stock</p>
            <i class="fas fa-boxes bg-icon"></i>
            <a href="ict_inventory_list.php?status=Available" class="card-footer-link">
                View Stock <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="dash-card bg-blue h-100">
            <h3><?php echo $total_users; ?></h3>
            <p>System Users</p>
            <i class="fas fa-users bg-icon"></i>
            <a href="admin_users.php" class="card-footer-link">
                Manage Users <i class="fas fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="mini-card">
            <h6 class="fw-bold mb-3" style="color:#0b2545;">
                <i class="fas fa-sitemap me-2"></i>Top Departments
            </h6>
            <?php if (!empty($dept_stats)): ?>
                <?php foreach ($dept_stats as $dept): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span><?php echo htmlspecialchars($dept['department'] ?: 'Unspecified'); ?></span>
                        <span class="badge bg-primary"><?php echo (int)$dept['count']; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted">No department data found.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="mini-card">
            <h6 class="fw-bold mb-3" style="color:#0b2545;">
                <i class="fas fa-chart-pie me-2"></i>Status Overview
            </h6>
            <?php if (!empty($status_stats)): ?>
                <?php foreach ($status_stats as $status => $count): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span><?php echo htmlspecialchars($status ?: 'Unknown'); ?></span>
                        <span class="badge bg-dark"><?php echo (int)$count; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-muted">No status data found.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="recent-table">
    <div class="p-3 border-bottom" style="background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);">
        <h6 class="mb-0 fw-bold" style="color: #1e293b;">
            <i class="fas fa-clock me-2"></i>
            Recent Assets (Last 5)
        </h6>
    </div>

    <div class="table-responsive">
        <table class="table mb-0">
            <thead>
                <tr>
                    <th width="25%">Inventory</th>
                    <th width="20%">Employee</th>
                    <th width="15%">Department</th>
                    <th width="15%">Status</th>
                    <th width="25%">Added On</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent_assets as $asset): ?>
                <tr>
                    <td class="fw-bold"><?php echo htmlspecialchars($asset['inventory']); ?></td>
                    <td><?php echo htmlspecialchars($asset['employee_name']); ?></td>
                    <td class="text-muted"><?php echo htmlspecialchars($asset['department']); ?></td>
                    <td>
                        <span class="badge status-badge 
                            <?php 
                                $status = strtolower($asset['status'] ?? '');
                                echo $status == 'assigned' || $status == 'active' ? 'bg-success' : 
                                     ($status == 'available' ? 'bg-info text-dark' : 'bg-warning text-dark');
                            ?>">
                            <?php echo htmlspecialchars(ucfirst($asset['status'] ?? 'Unknown')); ?>
                        </span>
                    </td>
                    <td class="small">
                        <?php echo !empty($asset['entry_datetime']) ? date('M j, Y', strtotime($asset['entry_datetime'])) : '-'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if (empty($recent_assets)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i><br>
                        No recent assets
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row action-btns">
    <div class="col-lg-3 col-md-6">
        <a href="ict_inventory_uploader.php" class="btn shadow-lg" style="background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%); color: white;">
            <i class="fas fa-upload me-2"></i>Add New Asset
        </a>
    </div>
    <div class="col-lg-3 col-md-6">
        <a href="ict_inventory_list.php" class="btn shadow-lg" style="background: linear-gradient(135deg, #e83e8c 0%, #d63384 100%); color: white;">
            <i class="fas fa-user-tag me-2"></i>Assign Asset
        </a>
    </div>
    <div class="col-lg-3 col-md-6">
        <a href="ict_inventory_list.php" class="btn shadow-lg" style="background: linear-gradient(135deg, #fd7e14 0%, #e65c00 100%); color: white;">
            <i class="fas fa-list me-2"></i>Full Inventory
        </a>
    </div>
    <div class="col-lg-3 col-md-6">
        <a href="ict_inventory_log.php?id=1" class="btn shadow-lg" style="background: linear-gradient(135deg, #6f42c1 0%, #5a2d91 100%); color: white;">
            <i class="fas fa-chart-bar me-2"></i>Reports
        </a>
    </div>
</div>
<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
