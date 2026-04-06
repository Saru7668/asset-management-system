<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
date_default_timezone_set('Asia/Dhaka');
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['UserName'];

// User profile check
$u_sql = "SELECT full_name, nid_company_id, phone, email, designation, department 
          FROM users 
          WHERE username = '" . mysqli_real_escape_string($conn, $username) . "' 
          LIMIT 1";
$user_result = mysqli_query($conn, $u_sql);
$user_info = mysqli_fetch_assoc($user_result) ?: [];

$profile_complete = !empty($user_info['full_name']) &&
                    !empty($user_info['nid_company_id']) &&
                    !empty($user_info['phone']) &&
                    !empty($user_info['email']) &&
                    !empty($user_info['department']);

$emp_id = $user_info['nid_company_id'] ?? '';
$emp_name = $user_info['full_name'] ?? '';

// Assets query
$device_count = 0;
$assets = [];
if ($profile_complete) {
    $assets_sql = "SELECT inventory, serial_model, details, status, unit
                   FROM assets
                   WHERE (employee_id = '" . mysqli_real_escape_string($conn, $emp_id) . "'
                       OR employee_name LIKE '%" . mysqli_real_escape_string($conn, $emp_name) . "%')
                   AND status IN ('Assigned', 'Active')
                   ORDER BY inventory ASC";

    $assets_result = mysqli_query($conn, $assets_sql);
    if ($assets_result) {
        $device_count = mysqli_num_rows($assets_result);
        while ($row = mysqli_fetch_assoc($assets_result)) {
            $assets[] = $row;
        }
    }
}

$page_title = 'My Dashboard - SCL AMS';
$page_header_icon = 'fas fa-tachometer-alt';
$page_header_title = 'My Allocated Devices';
$page_header_subtitle = date('l, F j, Y \a\t g:i A');
$page_top_title = 'Devices Assigned to Me';
$page_container_class = 'dashboard-container';

$extra_css = "
.dashboard-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
    transition: all 0.3s;
    margin-bottom: 20px;
}
.dashboard-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0,0,0,0.12);
}
.metric-card {
    color: white;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    text-decoration: none;
    display: block;
}
.device-card {
    border-left: 4px solid #10b981;
}
.device-icon {
    font-size: 45px;
    color: #10b981;
    margin-bottom: 15px;
}
.profile-alert {
    background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    border: none;
    border-radius: 12px;
}
.profile-badge {
    background: #ef4444;
    color: white;
}
@media (max-width: 991px) {
    .dashboard-card {
        margin-bottom: 15px;
        padding: 20px;
    }
    .metric-card {
        padding: 20px 15px;
    }
    .main-header {
        margin-bottom: 18px;
    }
}

/* =========================================
   DARK MODE SUPPORT
   ========================================= */
html[data-theme='dark'] .dashboard-card {
    background: #111827;
    border-top-color: #3b82f6;
    box-shadow: 0 8px 25px rgba(0,0,0,0.35);
    color: #f8fafc;
}

html[data-theme='dark'] .dashboard-card:hover {
    box-shadow: 0 15px 35px rgba(0,0,0,0.5);
}

html[data-theme='dark'] .dashboard-card h4[style] {
    color: #f8fafc !important; /* Overrides the inline style #0b2545 */
}

html[data-theme='dark'] .text-muted {
    color: #94a3b8 !important;
}

html[data-theme='dark'] .device-card {
    border-left-color: #10b981;
}

html[data-theme='dark'] .profile-alert {
    background: linear-gradient(135deg, #7c2d12 0%, #991b1b 100%);
    color: #f8fafc;
}

html[data-theme='dark'] .profile-alert h5 {
    color: #f8fafc;
}

html[data-theme='dark'] .profile-alert p {
    color: #cbd5e1;
}
";

ob_start();
?>

<?php if (!$profile_complete): ?>
    <div class="dashboard-card profile-alert shadow-lg p-4">
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <h5 class="mb-2">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    <strong>Complete Your Profile First</strong>
                </h5>
                <p class="mb-0">To view your dashboard and assigned devices, please update your complete profile information.</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="profile.php" class="btn btn-warning btn-lg px-4 fw-bold">
                    <i class="fas fa-user-edit me-2"></i>Update Profile Now
                </a>
                <span class="badge profile-badge ms-2 fs-6 px-2 py-1">Required</span>
            </div>
        </div>
    </div>
<?php else: ?>

    <div class="dashboard-card text-center mb-4 p-4">
        <h4 class="mb-2" style="color: #0b2545;">
            <i class="fas fa-user-circle me-2"></i>
            Welcome back, <?php echo htmlspecialchars($emp_name); ?>!
        </h4>
        <p class="text-muted mb-2">Here's your ICT inventory summary</p>
        <div class="mb-0">
            <span class="badge bg-success fs-6 px-3 py-2">
                <i class="fas fa-check-circle me-1"></i>Profile Completed
            </span>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3 col-sm-6">
            <div class="metric-card dashboard-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                <i class="fas fa-desktop fa-2x mb-3"></i>
                <div class="display-5 fw-bold mb-1"><?php echo $device_count; ?></div>
                <div class="fs-6 opacity-90">Total Devices</div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="metric-card dashboard-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                <i class="fas fa-box fa-2x mb-3"></i>
                <div class="display-5 fw-bold mb-1"><?php echo $device_count; ?></div>
                <div class="fs-6 opacity-90">Inventory Items</div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <div class="metric-card dashboard-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                <i class="fas fa-check-circle fa-2x mb-3"></i>
                <div class="display-5 fw-bold mb-1"><?php echo $device_count; ?></div>
                <div class="fs-6 opacity-90">Active Items</div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6">
            <a href="employee_devices.php?emp_id=<?php echo str_pad($emp_id, 8, '0', STR_PAD_LEFT); ?>"
               class="metric-card text-white text-decoration-none h-100 d-flex flex-column justify-content-center align-items-center"
               style="background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);">
                <i class="fas fa-list fa-2x mb-2"></i>
                <div class="display-5 fw-bold mb-1">View All</div>
                <div class="fs-6 opacity-90">Device List</div>
            </a>
        </div>
    </div>

    <div class="row g-3">
        <?php if (!empty($assets)): ?>
            <?php foreach ($assets as $row): ?>
                <?php
                $asset_desc = strtolower(($row['details'] ?? '') . " " . ($row['unit'] ?? ''));
                $icon = "fa-box";

                if (strpos($asset_desc, 'laptop') !== false) {
                    $icon = "fa-laptop";
                } elseif (strpos($asset_desc, 'desktop') !== false || strpos($asset_desc, 'pc') !== false) {
                    $icon = "fa-desktop";
                } elseif (strpos($asset_desc, 'printer') !== false) {
                    $icon = "fa-print";
                } elseif (strpos($asset_desc, 'monitor') !== false || strpos($asset_desc, 'display') !== false) {
                    $icon = "fa-tv";
                } elseif (strpos($asset_desc, 'mouse') !== false) {
                    $icon = "fa-mouse";
                } elseif (strpos($asset_desc, 'keyboard') !== false) {
                    $icon = "fa-keyboard";
                }
                ?>
                <div class="col-xl-3 col-md-4 col-sm-6">
                    <div class="dashboard-card device-card h-100 p-3">
                        <i class="fas <?php echo $icon; ?> device-icon"></i>
                        <h6 class="fw-bold mb-2"><?php echo htmlspecialchars($row['unit'] ?? 'Device'); ?></h6>
                        <div class="mb-3 small">
                            <div class="text-muted mb-1">
                                Serial: <strong><?php echo htmlspecialchars($row['serial_model'] ?? 'N/A'); ?></strong>
                            </div>
                            <div class="text-muted">
                                Inv: <strong><?php echo htmlspecialchars($row['inventory']); ?></strong>
                            </div>
                        </div>
                        <span class="badge bg-success px-3 py-1 fs-6">Active</span>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="dashboard-card text-center p-5">
                    <i class="fas fa-desktop fa-4x text-muted mb-4 opacity-50"></i>
                    <h4 class="text-muted mb-3">No Assigned Devices</h4>
                    <p class="text-muted mb-4 lead">You currently have no ICT inventory assigned to your profile.</p>
                    <a href="employee_list.php" class="btn btn-outline-primary px-4 py-2">
                        <i class="fas fa-users me-2"></i>View Employee Directory
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php endif; ?>

<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
?>