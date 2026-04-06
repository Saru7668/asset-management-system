<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');
require_once('header.php');

// =========================================
// SECURITY: STRICT ROLE CHECK
// =========================================
if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$current_role = strtolower(trim($_SESSION['UserRole'] ?? ''));
$username = $_SESSION['UserName'] ?? 'User';

$allowed_roles = ['hr', 'staff', 'admin', 'superadmin'];
if (!in_array($current_role, $allowed_roles, true)) {
    header("Location: index.php");
    exit;
}
/* =========================================
   DATABASE QUERIES FOR DASHBOARD METRICS
========================================= */
$total_assets = 0;
$assigned_assets = 0;
$available_stock = 0;
$recent_assignments = [];
$total_recent_assignments = 0;

try {
    // 1. Total Assets
    $query_total = mysqli_query($conn, "SELECT COUNT(*) AS count FROM assets");
    if ($query_total && $row = mysqli_fetch_assoc($query_total)) {
        $total_assets = (int)($row['count'] ?? 0);
    }

    // 2. Assigned Assets - same logic as admin dashboard
    $query_assigned = mysqli_query($conn, "
        SELECT COUNT(*) AS count
        FROM assets
        WHERE employee_id IS NOT NULL
          AND employee_id != ''
    ");
    if ($query_assigned && $row = mysqli_fetch_assoc($query_assigned)) {
        $assigned_assets = (int)($row['count'] ?? 0);
    }

    // 3. Available Stock - same logic as admin dashboard
    $query_stock = mysqli_query($conn, "
        SELECT COUNT(*) AS count
        FROM assets
        WHERE (employee_id IS NULL OR employee_id = '')
          AND status != 'Damage'
    ");
    if ($query_stock && $row = mysqli_fetch_assoc($query_stock)) {
        $available_stock = (int)($row['count'] ?? 0);
    }
} catch (Throwable $e) {
    // Optional: error_log($e->getMessage());
}

$assignment_percentage = ($total_assets > 0) ? round(($assigned_assets / $total_assets) * 100) : 0;

try {
    $last_month_query = "
        SELECT 
            aa.id,
            a.inventory AS asset_id,
            COALESCE(u.fullname, u.username, 'Unknown') AS assigned_to_name,
            COALESCE(a.details, a.item, a.category, 'Hardware') AS asset_type,
            aa.assign_date AS assigned_date
        FROM asset_assignments aa
        LEFT JOIN assets a ON aa.asset_id = a.id
        LEFT JOIN users u ON aa.user_id = u.id
        WHERE aa.assign_date IS NOT NULL
          AND aa.assign_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH)
        ORDER BY aa.assign_date DESC
        LIMIT 10
    ";

    $query_recent = mysqli_query($conn, $last_month_query);
    if ($query_recent) {
        while ($row = mysqli_fetch_assoc($query_recent)) {
            $recent_assignments[] = $row;
        }
        $total_recent_assignments = count($recent_assignments);
    }
} catch (Throwable $e) {
    // Optional: error_log($e->getMessage());
}

$page_title = 'HR & Staff Dashboard - SCL AMS';
$page_header_icon = 'fas fa-briefcase';
$page_header_title = 'HR / Staff Dashboard';
$page_header_subtitle = 'Manage assets, assignments, and temporary leases';
$page_top_title = 'HR / Staff Dashboard';
$page_container_class = 'dashboard-container-xl';

$extra_css = "
/* Welcome Header Card */
.welcome-card {
    background: linear-gradient(135deg, #1e3a5f 0%, #0b2545 100%);
    border-radius: 12px;
    padding: 25px 30px;
    color: white;
    box-shadow: 0 10px 20px rgba(11, 37, 69, 0.15);
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.welcome-text h2 { margin: 0 0 8px 0; font-weight: 700; font-size: 26px; }
.welcome-text p { margin: 0; color: #cbd5e1; font-size: 15px; max-width: 600px; }
.live-clock-container { text-align: right; background: rgba(255, 255, 255, 0.1); padding: 12px 20px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.15); min-width: 220px; }
.live-time { font-size: 24px; font-weight: 700; font-variant-numeric: tabular-nums; letter-spacing: 1px; margin-bottom: 2px; display: flex; align-items: baseline; justify-content: flex-end; }
.live-time .seconds { font-size: 14px; color: #94a3b8; margin-left: 4px; margin-right: 6px; }
.live-time .ampm { font-size: 16px; color: #e2e8f0; }
.live-date { font-size: 13px; color: #cbd5e1; font-weight: 500; }
.progress-section { margin-top: 15px; text-align: left; }
.progress-label { font-size: 12px; color: #cbd5e1; margin-bottom: 6px; display: block; }
.progress-bar-container { background: rgba(255, 255, 255, 0.2); border-radius: 20px; height: 8px; overflow: hidden; }
.progress-bar-fill { background: #10b981; height: 100%; border-radius: 20px; transition: width 1s ease-in-out; }
.metric-card { border-radius: 12px; padding: 24px; color: white; box-shadow: 0 8px 15px rgba(0,0,0,0.1); position: relative; overflow: hidden; margin-bottom: 24px; transition: transform 0.3s ease; display: flex; flex-direction: column; justify-content: space-between; min-height: 140px; }
.metric-card:hover { transform: translateY(-5px); }
.metric-card h3 { font-size: 36px; font-weight: 800; margin: 0; position: relative; z-index: 2; }
.metric-card p { font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin: 5px 0 0 0; opacity: 0.9; position: relative; z-index: 2; }
.metric-card .card-link { margin-top: 15px; font-size: 14px; color: rgba(255, 255, 255, 0.9); text-decoration: none; font-weight: 600; display: inline-block; position: relative; z-index: 2; }
.metric-card .card-link:hover { color: white; }
.metric-icon { position: absolute; right: -10px; bottom: -15px; font-size: 80px; opacity: 0.15; z-index: 1; }
.bg-teal { background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%); }
.bg-pink { background: linear-gradient(135deg, #ec4899 0%, #be185d 100%); }
.bg-orange { background: linear-gradient(135deg, #f97316 0%, #ea580c 100%); }
.action-card { background: white; border-radius: 12px; padding: 24px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; text-align: center; height: 100%; transition: all 0.3s; }
.action-card:hover { box-shadow: 0 10px 25px rgba(0,0,0,0.08); border-color: #cbd5e1; }
.action-card i { font-size: 40px; color: #0b2545; margin-bottom: 15px; }
.action-card h5 { font-weight: 700; color: #1e293b; margin-bottom: 10px; }
.dashboard-table-card { background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 24px; }
.dashboard-table-title { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center; }
.table > :not(caption) > * > * { padding: 12px 16px; border-bottom-color: #e2e8f0; }
.table th { background-color: #f8fafc; color: #475569; font-weight: 600; font-size: 13px; text-transform: uppercase; }
html[data-theme='dark'] .action-card, html[data-theme='dark'] .dashboard-table-card { background: #1e293b; border-color: #334155; }
html[data-theme='dark'] .action-card h5, html[data-theme='dark'] .dashboard-table-title { color: #f8fafc; }
html[data-theme='dark'] .table th { background-color: #0f172a; color: #94a3b8; border-bottom-color: #334155; }
html[data-theme='dark'] .table td { color: #cbd5e1; border-bottom-color: #334155; }
html[data-theme='dark'] .welcome-card { background: linear-gradient(135deg, #0f172a 0%, #020617 100%); border: 1px solid #1e293b; }
@media (max-width: 768px) { .welcome-card { flex-direction: column; text-align: center; } .live-clock-container { margin-top: 20px; text-align: center; width: 100%; } .live-time { justify-content: center; } }
";

ob_start();

if (!function_exists('safe_date')) {
    function safe_date($value, $format = 'd M Y') {
        if (empty($value) || $value === '0000-00-00' || $value === '0000-00-00 00:00:00') return '';
        $ts = strtotime($value);
        return $ts ? date($format, $ts) : '';
    }
}

$server_time = time() * 1000; 
?>

<!-- 1. Welcome Header with Clock -->
<div class="welcome-card">
    <div class="welcome-text">
        <h2>Welcome back, <?php echo htmlspecialchars((string)$username, ENT_QUOTES, 'UTF-8'); ?>!</h2>
        <p>Complete overview of SCL IT Asset Management System. Monitor stock, track assignments, and manage leases efficiently.</p>
        <div class="progress-section" style="max-width: 350px;">
            <span class="progress-label"><?php echo $assignment_percentage; ?>% Assigned (<?php echo number_format($assigned_assets); ?> out of <?php echo number_format($total_assets); ?>)</span>
            <div class="progress-bar-container">
                <div class="progress-bar-fill" style="width: <?php echo $assignment_percentage; ?>%;"></div>
            </div>
        </div>
    </div>
    <div class="live-clock-container">
        <div class="live-time">
            <span id="clock-hm">12:00</span>
            <span id="clock-s" class="seconds">00</span>
            <span id="clock-ampm" class="ampm">AM</span>
        </div>
        <div class="live-date" id="clock-date">
            <?php echo date('l, d F Y'); ?>
        </div>
    </div>
</div>

<!-- 2. Metric Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="metric-card bg-teal">
            <i class="fas fa-barcode metric-icon"></i>
            <div>
                <h3><?php echo number_format($total_assets); ?></h3>
                <p>Total Assets</p>
            </div>
            <a href="ict_inventory_list.php" class="card-link">View Inventory &rarr;</a>
        </div>
    </div>

    <div class="col-md-4">
        <div class="metric-card bg-pink">
            <i class="fas fa-user-check metric-icon"></i>
            <div>
                <h3><?php echo number_format($assigned_assets); ?></h3>
                <p>Assigned Assets</p>
            </div>
            <a href="ict_inventory_list.php?status=Assigned" class="card-link">View Assigned &rarr;</a>
        </div>
    </div>

    <div class="col-md-4">
        <div class="metric-card bg-orange">
            <i class="fas fa-boxes metric-icon"></i>
            <div>
                <h3><?php echo number_format($available_stock); ?></h3>
                <p>Available Stock</p>
            </div>
            <a href="ict_inventory_list.php?status=Available" class="card-link">View Stock &rarr;</a>
        </div>
    </div>
</div>

<!-- 3. Actions and History Grid -->
<div class="row g-4">
    <div class="col-lg-4">
        <div class="row g-4">
            <div class="col-12">
                <div class="action-card">
                    <i class="fas fa-laptop-house"></i>
                    <h5>Assign Asset</h5>
                    <p class="text-muted small">Permanently assign laptops or desktops to employees.</p>
                    <a href="ict_inventory_list.php" class="btn btn-primary w-100 mt-2" style="background:#0b2545; border:none;">Assign Device</a>
                </div>
            </div>
            <div class="col-12">
                <div class="action-card">
                    <i class="fas fa-headset"></i>
                    <h5>Temp Accessories Lease</h5>
                    <p class="text-muted small">Issue temporary accessories on lease.</p>
                    <a href="temp_accessories_lease.php" class="btn btn-outline-primary w-100 mt-2">Open Lease Form</a>
                    <a href="temp_accessories_lease_list.php" class="btn btn-dark w-100 mt-2">Lease Records</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="dashboard-table-card h-100">
            <div class="dashboard-table-title">
                <span><i class="fas fa-history text-primary me-2"></i> Last Month's Assignments</span>
                <?php if ($total_recent_assignments > 10): ?>
                    <span class="badge bg-info text-dark" style="font-size:12px;">
                        Showing 10 of <?php echo $total_recent_assignments; ?>
                    </span>
                <?php endif; ?>
            </div>
        
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Asset ID</th>
                            <th>Assigned To</th>
                            <th>Asset Type</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recent_assignments)): ?>
                            <?php foreach ($recent_assignments as $assign): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo h($assign['asset_id'] ?? 'N/A'); ?></td>
                                    <td><?php echo h($assign['assigned_to_name'] ?? 'Unknown'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo h($assign['asset_type'] ?? 'Hardware'); ?></span></td>
                                    <td><?php echo h(safe_date($assign['assigned_date'] ?? '', 'd M Y')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">
                                    <i class="fas fa-inbox fs-4 mb-2 d-block"></i>
                                    No assignments recorded in the last 30 days.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        
            <?php if ($total_recent_assignments > 10): ?>
                <div class="text-end mt-3">
                    <a href="temp_accessories_lease_list.php" class="btn btn-sm btn-light border">
                        View All <?php echo $total_recent_assignments; ?> Records <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    let serverTime = new Date(<?php echo $server_time; ?>);
    function updateClock() {
        serverTime.setSeconds(serverTime.getSeconds() + 1);
        let hours = serverTime.getHours();
        let minutes = serverTime.getMinutes();
        let seconds = serverTime.getSeconds();
        let ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        hours = hours < 10 ? '0' + hours : hours;
        minutes = minutes < 10 ? '0' + minutes : minutes;
        seconds = seconds < 10 ? '0' + seconds : seconds;
        document.getElementById('clock-hm').textContent = hours + ':' + minutes;
        document.getElementById('clock-s').textContent = seconds;
        document.getElementById('clock-ampm').textContent = ampm;
        if (hours === 12 && minutes === 0 && seconds === 0 && ampm === 'AM') {
            const options = { weekday: 'long', day: '2-digit', month: 'long', year: 'numeric' };
            document.getElementById('clock-date').textContent = serverTime.toLocaleDateString('en-GB', options);
        }
    }
    updateClock();
    setInterval(updateClock, 1000);
})();
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>