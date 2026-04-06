<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once('db.php');

$username = $_SESSION['UserName'] ?? 'Unknown';
$user_role_raw = $_SESSION['UserRole'] ?? 'user';
$user_role = strtolower(trim(str_replace(' ', '_', $user_role_raw)));
$current_page = basename($_SERVER['PHP_SELF']);

$can_show_theme_tools = false;

if ($user_role === 'superadmin') {
    $can_show_theme_tools = true;
} else {
    $stmt_theme = mysqli_prepare($conn, "SELECT allow_theme_control FROM users WHERE username = ? LIMIT 1");
    if ($stmt_theme) {
        mysqli_stmt_bind_param($stmt_theme, "s", $username);
        mysqli_stmt_execute($stmt_theme);
        $theme_result = mysqli_stmt_get_result($stmt_theme);

        if ($theme_result && mysqli_num_rows($theme_result) > 0) {
            $theme_row = mysqli_fetch_assoc($theme_result);
            $can_show_theme_tools = ((int)$theme_row['allow_theme_control'] === 1);
        }

        mysqli_stmt_close($stmt_theme);
    }
}

$is_superadmin = ($user_role === 'superadmin');
$is_admin = ($user_role === 'admin');
$is_hr = in_array($user_role, ['hr', 'hr_head']);
$is_staff = ($user_role === 'staff');
$is_dept_approver = in_array($user_role, ['approver', 'department_head']);
$is_ict_assessor = ($user_role === 'ict_assessor');
$is_ict_infra = in_array($user_role, ['ict_approver_infra', 'ict_infra_head']);
$is_ict_head = ($user_role === 'ict_head');
$is_any_approval_role = $is_superadmin || $is_admin || $is_dept_approver || $is_hr || $is_ict_assessor || $is_ict_infra || $is_ict_head;

$request_pages = [
    'submit_request.php',
    'my_requests.php',
    'approval_matrix.php',
    'request_view.php',
    'request_assessment.php',
    'request_action.php',
    'request_pdf.php'
];
$is_request_active = in_array($current_page, $request_pages);

$user_mgmt_pages = ['admin_users.php', 'role_change_log.php'];
$is_user_mgmt_active = in_array($current_page, $user_mgmt_pages);

$emp_pages = ['add_employee.php', 'employee_list.php', 'upload_employees.php', 'edit_employee.php'];
$is_emp_active = in_array($current_page, $emp_pages);

$ict_pages = ['ict_inventory_list.php', 'ict_inventory_add.php', 'ict_inventory_uploader.php', 'ict_inventory_edit.php'];
$is_ict_active = in_array($current_page, $ict_pages);

// NEW: Temporary Lease pages tracking
$lease_pages = ['temp_accessories_lease.php', 'temp_accessories_lease_list.php', 'temp_accessories_lease_print.php', 'temp_accessories_lease_upload.php'];
$is_lease_active = in_array($current_page, $lease_pages);
?>

<style>
    .sidebar {
        display: flex;
        flex-direction: column;
        height: 100vh;
        overflow: hidden;
        background: linear-gradient(180deg, #0b2545 0%, #081c35 100%);
    }

    .sidebar-top {
        flex-shrink: 0;
        background: #0b2545;
    }

    .sidebar-brand {
        padding: 20px 15px;
        text-align: left;
        background: linear-gradient(135deg, #061830 0%, #0a2242 100%);
        border-bottom: 1px solid #1e3a5f;
        display: flex;
        align-items: center;
        min-height: 72px;
    }

    .sidebar-brand h4 {
        margin: 0;
        font-weight: 700;
        color: #ffffff;
        font-size: 20px;
        letter-spacing: 1px;
    }

    .sidebar-theme-box {
        margin: 8px 15px 0 15px;
        padding: 7px 8px;
        border-radius: 10px;
        background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.08);
    }

    .sidebar-theme-topline {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 7px;
    }

    .sidebar-theme-label {
        color: #dbeafe;
        font-size: 11px;
        font-weight: 700;
        display: flex;
        align-items: center;
        margin: 0;
        letter-spacing: 0.2px;
    }

    .theme-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        background: #22c55e;
        box-shadow: 0 0 8px rgba(34,197,94,0.55);
    }

    .theme-status-dot.auto-on {
        background: #ef4444;
        box-shadow: 0 0 10px rgba(239,68,68,0.60);
    }

    .theme-status-dot.auto-off {
        background: #22c55e;
        box-shadow: 0 0 10px rgba(34,197,94,0.60);
    }

    .sidebar-theme-actions {
        display: flex;
        gap: 5px;
        align-items: center;
    }

    .theme-btn {
        border: 1px solid rgba(255,255,255,0.12);
        background: rgba(15,23,42,0.90);
        color: #dbeafe;
        border-radius: 8px;
        height: 28px;
        min-width: 28px;
        padding: 0 9px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        font-size: 11px;
        font-weight: 700;
        transition: all 0.25s ease;
        outline: none;
    }

    .theme-btn:hover {
        border-color: rgba(250,204,21,0.45);
        color: #fde68a;
        transform: translateY(-1px);
    }

    .theme-btn.active {
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 100%);
        color: #ffffff;
        border-color: #60a5fa;
        box-shadow: 0 4px 12px rgba(37,99,235,0.30);
    }

    .theme-btn-auto {
        min-width: 42px;
        padding: 0 9px;
        font-size: 11px;
    }

    .sidebar-user {
        padding: 14px 15px 16px 15px;
        text-align: center;
        border-bottom: 1px solid #1e3a5f;
        background: rgba(255,255,255,0.02);
        margin-top: 8px;
    }

    .sidebar-user p {
        margin: 0 0 5px 0;
        font-size: 13px;
        color: #94a3b8;
    }

    .sidebar-user .username {
        font-weight: bold;
        color: #ffffff;
        font-size: 16px;
    }

    .role-badge {
        background: #eab308;
        color: #000;
        font-size: 11px;
        font-weight: bold;
        padding: 3px 10px;
        border-radius: 12px;
        display: inline-block;
        margin-top: 8px;
        text-transform: uppercase;
        box-shadow: 0 2px 8px rgba(234, 179, 8, 0.35);
    }

    .sidebar-nav {
        flex: 1 1 auto;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 12px 0;
        min-height: 0;
        scroll-behavior: smooth;
    }

    .sidebar-nav::-webkit-scrollbar {
        width: 8px;
    }

    .sidebar-nav::-webkit-scrollbar-track {
        background: #08203c;
    }

    .sidebar-nav::-webkit-scrollbar-thumb {
        background: #27496d;
        border-radius: 10px;
    }

    .sidebar-nav::-webkit-scrollbar-thumb:hover {
        background: #3b82f6;
    }

    .sidebar-nav ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-nav > ul > li,
    .sidebar-nav .nav-item {
        padding: 0 15px;
        margin-bottom: 6px;
    }

    .sidebar-nav a {
        display: flex;
        align-items: center;
        padding: 12px 15px;
        color: #dbeafe;
        text-decoration: none;
        border-radius: 8px;
        transition: all 0.28s ease;
        font-weight: 500;
        font-size: 15px;
        width: 100%;
        border: 1px solid transparent;
        position: relative;
    }

    .sidebar-nav a i.icon-left {
        margin-right: 10px;
        width: 20px;
        text-align: center;
        transition: all 0.28s ease;
    }

    .sidebar-nav a i.icon-right {
        margin-left: auto;
        font-size: 12px;
        transition: transform 0.3s ease, color 0.28s ease;
    }

    .sidebar-nav a:hover {
        background: linear-gradient(135deg, rgba(250,204,21,0.12) 0%, rgba(30,58,95,0.95) 100%);
        color: #fde68a;
        border-left: 4px solid #facc15;
        border-color: rgba(250,204,21,0.18);
        transform: translateX(4px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.18);
    }

    .sidebar-nav a:hover i.icon-left,
    .sidebar-nav a:hover i.icon-right {
        color: #facc15;
        text-shadow: 0 0 8px rgba(250,204,21,0.45);
    }

    .sidebar-nav a.active {
        background: linear-gradient(135deg, #1e3a5f 0%, #234a78 100%);
        color: #f8fbff;
        border-left: 4px solid #3b82f6;
        box-shadow: inset 0 0 0 1px rgba(255,255,255,0.04), 0 6px 16px rgba(0,0,0,0.20);
    }

    .sidebar-nav a.active i.icon-left,
    .sidebar-nav a.active i.icon-right {
        color: #93c5fd;
        text-shadow: 0 0 10px rgba(147,197,253,0.45);
    }

    .sidebar-nav .collapse {
        margin-top: 3px;
    }

    .sidebar-nav a[data-bs-toggle="collapse"] {
        background-color: transparent;
        border-left: 4px solid transparent;
    }

    .sidebar-nav a[data-bs-toggle="collapse"]:hover {
        background: linear-gradient(135deg, rgba(59,130,246,0.20) 0%, rgba(30,58,95,0.95) 100%);
    }

    .sidebar-nav a[data-bs-toggle="collapse"][aria-expanded="true"] {
        background: linear-gradient(135deg, #1e3a5f 0%, #234a78 100%);
        color: white;
        border-left: 4px solid #3b82f6;
    }

    .sidebar-nav a[data-bs-toggle="collapse"][aria-expanded="true"] i.icon-right {
        transform: rotate(180deg);
        color: #bfdbfe;
    }

    .sidebar-nav .sub-menu-box {
        background: rgba(0, 0, 0, 0.22);
        border-radius: 8px;
        padding: 6px 0;
        margin-top: 6px;
        border: 1px solid rgba(255,255,255,0.04);
    }

    .sidebar-nav .sub-menu-box a {
        padding: 10px 15px 10px 45px;
        font-size: 14px;
        border-left: none;
        border-radius: 0;
        margin-bottom: 2px;
        transform: none !important;
        box-shadow: none !important;
        color: #c7d2fe;
    }

    .sidebar-nav .sub-menu-box a:hover,
    .sidebar-nav .sub-menu-box a.active {
        background: rgba(250, 204, 21, 0.10);
        color: #fde68a;
        border-left: none;
    }

    .sidebar-footer {
        flex-shrink: 0;
        padding: 15px;
        border-top: 1px solid #1e3a5f;
        background: #0b2545;
    }

    .btn-logout {
        background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        color: white;
        display: block;
        text-align: center;
        padding: 11px 12px;
        border-radius: 8px;
        text-decoration: none;
        font-weight: bold;
        transition: all 0.28s ease;
        box-shadow: 0 4px 12px rgba(239,68,68,0.25);
    }

    .btn-logout:hover {
        background: linear-gradient(135deg, #f87171 0%, #dc2626 100%);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 8px 18px rgba(239,68,68,0.32);
    }
</style>

<div class="sidebar-top">
    <div class="sidebar-brand">
        <h4><i class="fas fa-building me-2"></i> SCL AMS</h4>
    </div>

    <?php if ($can_show_theme_tools): ?>
    <div class="sidebar-theme-box">
        <div class="sidebar-theme-topline">
            <div class="sidebar-theme-label">
                <i class="fas fa-circle-half-stroke me-2"></i> Theme
            </div>
            <span id="autoThemeIndicator" class="theme-status-dot auto-off"></span>
        </div>

        <div class="sidebar-theme-actions">
            <button type="button" class="theme-btn" id="themeLightBtn" onclick="setThemeMode('light'); updateThemeButtons();" title="Light Mode">
                <i class="fas fa-sun"></i>
            </button>

            <button type="button" class="theme-btn" id="themeDarkBtn" onclick="setThemeMode('dark'); updateThemeButtons();" title="Dark Mode">
                <i class="fas fa-moon"></i>
            </button>

            <button type="button" class="theme-btn theme-btn-auto" id="themeAutoBtn" onclick="setThemeMode('auto'); updateThemeButtons();" title="Auto Mode">
                Auto
            </button>
        </div>
    </div>
    <?php endif; ?>

    <div class="sidebar-user">
        <p>Logged in as</p>
        <div class="username"><?php echo htmlspecialchars($username); ?></div>
        <div class="role-badge"><?php echo htmlspecialchars($user_role_raw); ?></div>
    </div>
</div>

<div class="sidebar-nav">
    <ul>
        <li>
            <a href="index.php" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                <i class="icon-left fas fa-home"></i> My Allocated Devices
            </a>
        </li>

        <li>
            <a href="profile.php" class="<?php echo ($current_page == 'profile.php') ? 'active' : ''; ?>">
                <i class="icon-left fas fa-user"></i> My Profile
            </a>
        </li>

        <li class="nav-item">
            <a href="#requestSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_request_active ? 'true' : 'false'; ?>" class="<?php echo $is_request_active ? 'active' : ''; ?>">
                <i class="icon-left fas fa-file-signature"></i>
                <span>Request System</span>
                <i class="icon-right fas fa-caret-down"></i>
            </a>
            <div class="collapse <?php echo $is_request_active ? 'show' : ''; ?>" id="requestSubmenu">
                <div class="sub-menu-box">
                    <a href="submit_request.php" class="<?php echo ($current_page == 'submit_request.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-paper-plane"></i> Submit Request
                    </a>
                    <a href="my_requests.php" class="<?php echo ($current_page == 'my_requests.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-clipboard-list"></i> My Requests
                    </a>

                    <?php if ($is_any_approval_role): ?>
                    <a href="approval_matrix.php" class="<?php echo ($current_page == 'approval_matrix.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-list-check"></i> Approval Matrix
                    </a>
                    <?php endif; ?>

                    <?php if ($is_ict_assessor || $is_superadmin || $is_admin): ?>
                    <a href="approval_matrix.php" class="<?php echo in_array($current_page, ['approval_matrix.php', 'request_assessment.php']) ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-laptop-code"></i> ICT Assessment Queue
                    </a>
                    <?php endif; ?>
                    
                </div>
            </div>
        </li>

        <?php if (in_array($user_role, ['hr', 'staff', 'admin', 'superadmin'])): ?>
        <li>
            <a href="hr_dashboard.php" class="<?php echo ($current_page == 'hr_dashboard.php') ? 'active' : ''; ?>">
                <i class="icon-left fas fa-briefcase"></i> HR/Staff Dashboard
            </a>
        </li>
        <?php if ($is_admin || $is_superadmin): ?>
        <li>
            <a href="all_requests.php" class="<?php echo ($current_page == 'all_requests.php') ? 'active' : ''; ?>">
                <i class="icon-left fas fa-table-list"></i> All Requests Tracking
            </a>
        </li>
        <?php endif; ?>

        <li class="nav-item">
            <a href="#empSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_emp_active ? 'true' : 'false'; ?>" class="<?php echo $is_emp_active ? 'active' : ''; ?>">
                <i class="icon-left fas fa-users"></i>
                <span>Employee Mgmt</span>
                <i class="icon-right fas fa-caret-down"></i>
            </a>
            <div class="collapse <?php echo $is_emp_active ? 'show' : ''; ?>" id="empSubmenu">
                <div class="sub-menu-box">
                    <a href="add_employee.php" class="<?php echo ($current_page == 'add_employee.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-user-plus"></i> Add Employee
                    </a>
                    <a href="employee_list.php" class="<?php echo ($current_page == 'employee_list.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-list"></i> Employee List
                    </a>
                    <a href="upload_employees.php" class="<?php echo ($current_page == 'upload_employees.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-file-excel"></i> Upload Employees
                    </a>
                </div>
            </div>
        </li>
        <?php endif; ?>

        <?php if (in_array($user_role, ['hr', 'staff', 'admin', 'superadmin'])): ?>
        <li class="nav-item">
            <a href="#assetSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_ict_active ? 'true' : 'false'; ?>" class="<?php echo $is_ict_active ? 'active' : ''; ?>">
                <i class="icon-left fas fa-desktop"></i>
                <span>ICT Inventory</span>
                <i class="icon-right fas fa-caret-down"></i>
            </a>
            <div class="collapse <?php echo $is_ict_active ? 'show' : ''; ?>" id="assetSubmenu">
                <div class="sub-menu-box">
                    <a href="ict_inventory_list.php" class="<?php echo ($current_page == 'ict_inventory_list.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-list"></i> View Inventory
                    </a>
                    <a href="ict_inventory_add.php" class="<?php echo ($current_page == 'ict_inventory_add.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-plus-circle"></i> Add Inventory
                    </a>
                    <a href="ict_inventory_uploader.php" class="<?php echo ($current_page == 'ict_inventory_uploader.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-upload"></i> Inventory Uploader
                    </a>
                </div>
            </div>
        </li>

        <!-- NEW: Temporary Lease Menu -->
        <li class="nav-item">
            <a href="#leaseSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_lease_active ? 'true' : 'false'; ?>" class="<?php echo $is_lease_active ? 'active' : ''; ?>">
                <i class="icon-left fas fa-headset"></i>
                <span>Temporary Lease</span>
                <i class="icon-right fas fa-caret-down"></i>
            </a>
            <div class="collapse <?php echo $is_lease_active ? 'show' : ''; ?>" id="leaseSubmenu">
                <div class="sub-menu-box">
                    <a href="temp_accessories_lease.php" class="<?php echo ($current_page == 'temp_accessories_lease.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-file-contract"></i> Lease Form
                    </a>
                    <a href="temp_accessories_lease_list.php" class="<?php echo ($current_page == 'temp_accessories_lease_list.php' || $current_page == 'temp_accessories_lease_upload.php' || $current_page == 'temp_accessories_lease_print.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-clipboard-list"></i> Lease Records
                    </a>
                </div>
            </div>
        </li>
        <!-- END NEW -->

        <?php endif; ?>

        <?php if ($is_admin || $is_superadmin): ?>
        <li>
            <a href="admin_dashboard.php" class="<?php echo ($current_page == 'admin_dashboard.php') ? 'active' : ''; ?>">
                <i class="icon-left fas fa-tachometer-alt"></i> Admin Dashboard
            </a>
        </li>
        <?php endif; ?>

        <?php if ($is_superadmin): ?>
        <li class="nav-item">
            <a href="#userMgmtSubmenu" data-bs-toggle="collapse" aria-expanded="<?php echo $is_user_mgmt_active ? 'true' : 'false'; ?>" class="<?php echo $is_user_mgmt_active ? 'active' : ''; ?>">
                <i class="icon-left fas fa-users-cog"></i>
                <span>User Management</span>
                <i class="icon-right fas fa-caret-down"></i>
            </a>
            <div class="collapse <?php echo $is_user_mgmt_active ? 'show' : ''; ?>" id="userMgmtSubmenu">
                <div class="sub-menu-box">
                    <a href="admin_users.php" class="<?php echo ($current_page == 'admin_users.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-user-shield"></i> Manage Users
                    </a>
                    <a href="role_change_log.php" class="<?php echo ($current_page == 'role_change_log.php') ? 'active' : ''; ?>">
                        <i class="icon-left fas fa-clock-rotate-left"></i> Role Change Log
                    </a>
                </div>
            </div>
        </li>
        <?php endif; ?>
        <?php if ($is_superadmin): ?>
        <li class="nav-item">
            <a href="recycle_bin.php" class="<?php echo ($current_page == 'recycle_bin.php') ? 'active' : ''; ?>">
                <i class="icon-left fas fa-trash-restore"></i> Recycle Bin
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>

<div class="sidebar-footer">
    <a href="logout.php" class="btn-logout">
        <i class="fas fa-sign-out-alt me-2"></i> Logout
    </a>
</div>

<script>
function updateThemeButtons() {
    const mode = localStorage.getItem('theme-mode') || 'auto';

    const lightBtn = document.getElementById('themeLightBtn');
    const darkBtn = document.getElementById('themeDarkBtn');
    const autoBtn = document.getElementById('themeAutoBtn');
    const indicator = document.getElementById('autoThemeIndicator');

    if (lightBtn) lightBtn.classList.remove('active');
    if (darkBtn) darkBtn.classList.remove('active');
    if (autoBtn) autoBtn.classList.remove('active');

    if (mode === 'light' && lightBtn) {
        lightBtn.classList.add('active');
    } else if (mode === 'dark' && darkBtn) {
        darkBtn.classList.add('active');
    } else if (mode === 'auto' && autoBtn) {
        autoBtn.classList.add('active');
    }

    if (indicator) {
        if (mode === 'auto') {
            indicator.classList.remove('auto-off');
            indicator.classList.add('auto-on');
        } else {
            indicator.classList.remove('auto-on');
            indicator.classList.add('auto-off');
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    updateThemeButtons();
});

window.addEventListener('storage', function () {
    updateThemeButtons();
});
</script>