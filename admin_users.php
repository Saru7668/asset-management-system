<?php
session_start();
require_once('db.php');
require_once('header.php');
require_once('request_mail_helper.php');

if (!isset($_SESSION['UserName']) || ($_SESSION['UserRole'] ?? '') !== 'SuperAdmin') {
    header("Location: index.php");
    exit;
}

$current_user = $_SESSION['UserName'];

$success_msg = "";
$error_msg = "";

$department_list = [
    "ICT", "HR & Admin", "Accounts & Finance", "Sales & Marketing", "Supply Chain",
    "Production", "Civil Engineering", "Electrical", "Mechanical", "Glazeline",
    "Laboratory & Quality Control", "Power & Generation", "Press", "Sorting & Packing",
    "Squaring & Polishing", "VAT", "Kiln", "Inventory", "Audit", "Brand"
];

$role_list = [
    "SuperAdmin",
    "admin",
    "user",
    "HR",
    "Staff",
    "Approver",
    "HR Head",
    "ICT Assessor",
    "ICT Infra Head",
    "ICT Head"
];

/* Ensure role change log table exists */
$create_role_log_sql = "
CREATE TABLE IF NOT EXISTS role_change_log (
    id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    username VARCHAR(100) NOT NULL,
    old_role VARCHAR(100) DEFAULT NULL,
    new_role VARCHAR(100) DEFAULT NULL,
    changed_by VARCHAR(100) DEFAULT NULL,
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
";
mysqli_query($conn, $create_role_log_sql);

/* ADD USER */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $username   = trim($_POST['username'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $role       = trim($_POST['user_role'] ?? 'user');
    $password   = $_POST['password'] ?? '';
    $allow_theme_control = isset($_POST['allow_theme_control']) ? 1 : 0;

    if ($username === '' || $email === '' || $department === '' || $password === '') {
        $error_msg = "All required fields must be filled.";
    } elseif (!in_array($role, $role_list)) {
        $error_msg = "Invalid role selected.";
    } else {
        $sql_check = "SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1";
        $stmt_check = mysqli_prepare($conn, $sql_check);
        mysqli_stmt_bind_param($stmt_check, "ss", $username, $email);
        mysqli_stmt_execute($stmt_check);
        $res_check = mysqli_stmt_get_result($stmt_check);

        if (mysqli_num_rows($res_check) > 0) {
            $error_msg = "Username or Email already exists!";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $sql_insert = "INSERT INTO users (username, password, email, department, user_role, allow_theme_control, created_at)
                           VALUES (?, ?, ?, ?, ?, ?, NOW())";
            $stmt_ins = mysqli_prepare($conn, $sql_insert);
            mysqli_stmt_bind_param($stmt_ins, "sssssi", $username, $password_hash, $email, $department, $role, $allow_theme_control);

            if (mysqli_stmt_execute($stmt_ins)) {
                $success_msg = "New user added successfully!";

                if (!empty($email)) {
                    send_role_change_mail(
                        $email,
                        $username,
                        $role,
                        'http://sclams.sheltechceramics.com/login.php'
                    );
                }
            } else {
                $error_msg = "Failed to add user.";
            }
        }
    }
}

/* EDIT USER */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $edit_id = (int)($_POST['user_id'] ?? 0);
    $new_role = trim($_POST['edit_role'] ?? '');
    $new_pass = trim($_POST['edit_password'] ?? '');
    $allow_theme_control = isset($_POST['edit_allow_theme_control']) ? 1 : 0;

    if ($edit_id <= 0) {
        $error_msg = "Invalid user selected.";
    } elseif (!in_array($new_role, $role_list)) {
        $error_msg = "Invalid role selected.";
    } else {
        $fetch_old_sql = "SELECT username, email, full_name, user_role FROM users WHERE id = ? LIMIT 1";
        $fetch_old_stmt = mysqli_prepare($conn, $fetch_old_sql);
        mysqli_stmt_bind_param($fetch_old_stmt, "i", $edit_id);
        mysqli_stmt_execute($fetch_old_stmt);
        $fetch_old_result = mysqli_stmt_get_result($fetch_old_stmt);
        $old_user = mysqli_fetch_assoc($fetch_old_result);

        if (!$old_user) {
            $error_msg = "User not found.";
        } else {
            $old_role = $old_user['user_role'];

            if (!empty($new_pass)) {
                $password_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $update_sql = "UPDATE users SET user_role = ?, password = ?, allow_theme_control = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, "ssii", $new_role, $password_hash, $allow_theme_control, $edit_id);
            } else {
                $update_sql = "UPDATE users SET user_role = ?, allow_theme_control = ? WHERE id = ?";
                $stmt = mysqli_prepare($conn, $update_sql);
                mysqli_stmt_bind_param($stmt, "sii", $new_role, $allow_theme_control, $edit_id);
            }

            if (mysqli_stmt_execute($stmt)) {
                $success_msg = "User info updated successfully!";

                if ($old_role !== $new_role) {
                    $log_stmt = mysqli_prepare($conn, "INSERT INTO role_change_log (user_id, username, old_role, new_role, changed_by, changed_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    mysqli_stmt_bind_param(
                        $log_stmt,
                        "issss",
                        $edit_id,
                        $old_user['username'],
                        $old_role,
                        $new_role,
                        $current_user
                    );
                    mysqli_stmt_execute($log_stmt);

                    if (!empty($old_user['email'])) {
                        send_role_change_mail(
                            $old_user['email'],
                            $old_user['full_name'] ?: $old_user['username'],
                            $new_role,
                            'http://sclams.sheltechceramics.com/login.php'
                        );
                    }
                }
            } else {
                $error_msg = "Failed to update user.";
            }
        }
    }
}

/* DELETE USER */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'delete_user') {
    $delete_id = (int)($_POST['delete_user_id'] ?? 0);

    if ($delete_id <= 0) {
        $error_msg = "Invalid user selected.";
    } else {
        $check_user_sql = "SELECT id, username FROM users WHERE id = ? LIMIT 1";
        $stmt_check_user = mysqli_prepare($conn, $check_user_sql);
        mysqli_stmt_bind_param($stmt_check_user, "i", $delete_id);
        mysqli_stmt_execute($stmt_check_user);
        $result_check_user = mysqli_stmt_get_result($stmt_check_user);

        if ($result_check_user && mysqli_num_rows($result_check_user) > 0) {
            $user_row = mysqli_fetch_assoc($result_check_user);

            if ($user_row['username'] === $current_user) {
                $error_msg = "You cannot delete your own account.";
            } else {
                $delete_sql = "DELETE FROM users WHERE id = ? LIMIT 1";
                $stmt_delete = mysqli_prepare($conn, $delete_sql);
                mysqli_stmt_bind_param($stmt_delete, "i", $delete_id);

                if (mysqli_stmt_execute($stmt_delete)) {
                    $success_msg = "User deleted successfully!";
                } else {
                    $error_msg = "Failed to delete user.";
                }
            }
        } else {
            $error_msg = "User not found.";
        }
    }
}

$users_query = "SELECT id, username, email, department, user_role, full_name, created_at, allow_theme_control 
                FROM users 
                ORDER BY id DESC";
$users_result = mysqli_query($conn, $users_query);

$page_title = 'User Management - SCL AMS';
$page_header_icon = 'fas fa-users-cog';
$page_header_title = 'User Management';
$page_header_subtitle = 'Create users, manage roles, reset passwords, delete users, and permit theme access';
$page_top_title = 'User Management';
$page_top_actions = '
<a href="role_change_log.php" class="btn btn-outline-secondary fw-bold me-2">
    <i class="fas fa-clock-rotate-left me-1"></i> Role Change Log
</a>
<button class="btn btn-primary fw-bold" style="background:#0b2545; border-color:#0b2545;" data-bs-toggle="modal" data-bs-target="#addUserModal">
    <i class="fas fa-plus-circle me-1"></i> Add New User
</button>';
$page_container_class = 'dashboard-container-wide';

$body_extra_top = '';
if ($success_msg) {
    $body_extra_top .= "<script>Swal.fire({icon:'success', title:'Success!', text:'" . addslashes($success_msg) . "', confirmButtonColor:'#0b2545'});</script>";
}
if ($error_msg) {
    $body_extra_top .= "<script>Swal.fire({icon:'error', title:'Oops...', text:'" . addslashes($error_msg) . "', confirmButtonColor:'#0b2545'});</script>";
}

$extra_css = "
.card-header-custom {
    background: #0b2545;
    color: white;
    border-radius: 12px 12px 0 0;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.table-custom {
    background: white;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    overflow: hidden;
}
.table-custom th {
    background-color: #f8fafc;
    color: #1e293b;
    font-weight: 600;
    border-bottom: 2px solid #e2e8f0;
    white-space: nowrap;
}
.role-badge {
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    display: inline-block;
}
.role-SuperAdmin { background: #dc3545; color: white; }
.role-admin { background: #0d6efd; color: white; }
.role-user { background: #198754; color: white; }
.role-approver { background: #8b5cf6; color: white; }
.role-hr { background: #d97706; color: white; }
.role-staff { background: #16a34a; color: white; }
.role-hr-head { background: #ea580c; color: white; }
.role-assessor { background: #f59e0b; color: white; }
.role-ict-infra { background: #0ea5e9; color: white; }
.role-ict-head { background: #0284c7; color: white; }
.role-default { background: #6c757d; color: white; }

.user-table-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
    border-top: 4px solid #0b2545;
}
.action-btns {
    display: flex;
    justify-content: center;
    gap: 6px;
    flex-wrap: wrap;
}
.theme-permit-badge-on {
    background: #198754;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
.theme-permit-badge-off {
    background: #6c757d;
    color: white;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}
@media (max-width: 991px) {
    .table-custom table {
        min-width: 1200px;
    }
}

/* =========================================
   DARK MODE SUPPORT
   ========================================= */
html[data-theme='dark'] .user-table-card {
    background: #111827;
    border-top-color: #3b82f6;
    box-shadow: 0 8px 25px rgba(0,0,0,0.35);
}

html[data-theme='dark'] .card-header-custom {
    background: #0f172a;
    border-bottom: 1px solid #334155;
}

html[data-theme='dark'] .table-custom {
    background: #111827;
}

html[data-theme='dark'] .table-custom table {
    color: #e5e7eb;
}

html[data-theme='dark'] .table-custom th {
    background-color: #0f172a;
    color: #f8fafc;
    border-bottom-color: #334155;
}

html[data-theme='dark'] .table-custom td {
    border-bottom-color: #334155;
    color: #e5e7eb;
}

html[data-theme='dark'] .table-custom tbody tr:hover {
    background-color: rgba(255, 255, 255, 0.05);
    color: #f8fafc;
}

html[data-theme='dark'] .text-muted {
    color: #94a3b8 !important;
}

html[data-theme='dark'] .badge.bg-light {
    background-color: #334155 !important;
    color: #f8fafc !important;
}

/* Dark Mode Modals */
html[data-theme='dark'] .modal-content {
    background-color: #111827;
    color: #f8fafc;
    border: 1px solid #334155;
}

html[data-theme='dark'] .modal-header {
    background-color: #0f172a !important;
    border-bottom: 1px solid #334155;
}

html[data-theme='dark'] .modal-footer {
    border-top: 1px solid #334155;
}

html[data-theme='dark'] .form-control,
html[data-theme='dark'] .form-select {
    background-color: #0f172a;
    border-color: #334155;
    color: #f8fafc;
}

html[data-theme='dark'] .form-control:focus,
html[data-theme='dark'] .form-select:focus {
    background-color: #0f172a;
    border-color: #3b82f6;
    color: #f8fafc;
    box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
}

html[data-theme='dark'] .btn-close-white,
html[data-theme='dark'] .btn-close {
    filter: invert(1) grayscale(100%) brightness(200%);
}

html[data-theme='dark'] .btn-secondary {
    background-color: #475569;
    border-color: #475569;
    color: #f8fafc;
}

html[data-theme='dark'] .btn-secondary:hover {
    background-color: #334155;
    border-color: #334155;
}
/* =========================================
   CUSTOM TOGGLE SWITCH (NEON STYLE)
   ========================================= */
.form-switch .form-check-input {
    width: 3.5rem;
    height: 1.75rem;
    background-color: #6c757d; /* OFF State Background */
    border: none;
    border-radius: 2rem;
    position: relative;
    cursor: pointer;
    outline: none;
    transition: background-color 0.3s ease-in-out;
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.3);
}

/* OFF State Indicator (The circle) */
.form-switch .form-check-input::after {
    content: '';
    position: absolute;
    top: 0.15rem;
    left: 0.2rem;
    width: 1.45rem;
    height: 1.45rem;
    background-color: white;
    border-radius: 50%;
    transition: transform 0.3s ease-in-out;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

/* ON State (Checked) */
.form-switch .form-check-input:checked {
    background-color: #0ea5e9; /* Neon Blue Background */
    box-shadow: 0 0 10px #0ea5e9, inset 0 2px 4px rgba(0, 0, 0, 0.3); /* Neon Glow */
}

/* ON State Indicator sliding to the right */
.form-switch .form-check-input:checked::after {
    transform: translateX(1.75rem);
    background-color: white;
    box-shadow: 0 0 8px white, 0 2px 4px rgba(0, 0, 0, 0.2); /* Extra glow on the circle */
}

/* Label styling to align nicely with the bigger switch */
.form-switch .form-check-label {
    margin-top: 0.3rem;
    margin-left: 0.5rem;
    font-weight: 500;
    cursor: pointer;
}

/* -----------------------------------------
   Dark Mode Adjustments for Toggle Switch
   ----------------------------------------- */
html[data-theme='dark'] .form-switch .form-check-input {
    background-color: #334155; /* Darker OFF State */
    box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.5);
}

html[data-theme='dark'] .form-switch .form-check-input::after {
    background-color: #94a3b8; /* Dimmer circle in OFF state for dark mode */
}

html[data-theme='dark'] .form-switch .form-check-input:checked {
    background-color: #10b981; /* Neon Green for Dark Mode (Or use #0ea5e9 if you prefer blue) */
    box-shadow: 0 0 12px #10b981, inset 0 2px 4px rgba(0, 0, 0, 0.5);
}

html[data-theme='dark'] .form-switch .form-check-input:checked::after {
    background-color: #ffffff;
    box-shadow: 0 0 10px #ffffff;
}

/* Removes Bootstrap's default focus ring that ruins the custom shape */
.form-switch .form-check-input:focus {
    background-image: none;
    outline: none;
    box-shadow: none;
}
.form-switch .form-check-input:checked:focus {
    box-shadow: 0 0 10px #0ea5e9;
}
html[data-theme='dark'] .form-switch .form-check-input:checked:focus {
    box-shadow: 0 0 12px #10b981;
}
";

ob_start();
?>

<div class="user-table-card">
    <div class="card-header-custom">
        <h5 class="mb-0"><i class="fas fa-users-cog me-2"></i>User List</h5>
        <span class="badge bg-light text-dark"><?php echo mysqli_num_rows($users_result); ?> Users</span>
    </div>

    <div class="table-responsive table-custom">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Role</th>
                    <th>Theme Permit</th>
                    <th class="text-center">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($users_result)): ?>
                    <?php
                    $badge_class = "role-default";
                    if ($row['user_role'] == 'SuperAdmin') $badge_class = 'role-SuperAdmin';
                    elseif ($row['user_role'] == 'admin') $badge_class = 'role-admin';
                    elseif ($row['user_role'] == 'user') $badge_class = 'role-user';
                    elseif ($row['user_role'] == 'Approver') $badge_class = 'role-approver';
                    elseif ($row['user_role'] == 'HR') $badge_class = 'role-hr';
                    elseif ($row['user_role'] == 'Staff') $badge_class = 'role-staff';
                    elseif ($row['user_role'] == 'HR Head') $badge_class = 'role-hr-head';
                    elseif ($row['user_role'] == 'ICT Assessor') $badge_class = 'role-assessor';
                    elseif ($row['user_role'] == 'ICT Infra Head') $badge_class = 'role-ict-infra';
                    elseif ($row['user_role'] == 'ICT Head') $badge_class = 'role-ict-head';
                    ?>
                    <tr>
                        <td><?php echo $row['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($row['username']); ?></strong><br>
                            <small class="text-muted"><?php echo htmlspecialchars($row['full_name']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($row['email']); ?></td>
                        <td><?php echo htmlspecialchars($row['department']); ?></td>
                        <td>
                            <span class="role-badge <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($row['user_role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ((int)$row['allow_theme_control'] === 1): ?>
                                <span class="theme-permit-badge-on">Allowed</span>
                            <?php else: ?>
                                <span class="theme-permit-badge-off">Blocked</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <div class="action-btns">
                                <button class="btn btn-sm btn-outline-primary"
                                    onclick="openEditModal(
                                        <?php echo $row['id']; ?>,
                                        '<?php echo addslashes($row['username']); ?>',
                                        '<?php echo addslashes($row['user_role']); ?>',
                                        <?php echo (int)$row['allow_theme_control']; ?>
                                    )">
                                    <i class="fas fa-edit"></i> Edit
                                </button>

                                <?php if ($row['username'] !== $current_user): ?>
                                    <button class="btn btn-sm btn-outline-danger"
                                        onclick="confirmDeleteUser(<?php echo $row['id']; ?>, '<?php echo addslashes($row['username']); ?>')">
                                        <i class="fas fa-trash-alt"></i> Delete
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-sm btn-outline-secondary" disabled>
                                        <i class="fas fa-lock"></i> Current User
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#0b2545; color:white;">
                <h5 class="modal-title"><i class="fas fa-user-plus me-1"></i> Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_user">

                    <div class="mb-3">
                        <label class="form-label">Username *</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Department *</label>
                        <select name="department" class="form-select" required>
                            <option value="">Select Department</option>
                            <?php foreach ($department_list as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">User Role *</label>
                        <select name="user_role" class="form-select" required>
                            <?php foreach ($role_list as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>" <?php echo ($role == 'user') ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($role); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Password *</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="allow_theme_control" id="allow_theme_control">
                        <label class="form-check-label" for="allow_theme_control">
                            Allow this user to use sidebar theme controls
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" style="background:#0b2545; border-color:#0b2545;">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background:#1e3a5f; color:white;">
                <h5 class="modal-title">
                    <i class="fas fa-user-edit me-1"></i> Edit User: <span id="edit_username_display"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="mb-3">
                        <label class="form-label">Change Role</label>
                        <select name="edit_role" id="edit_role" class="form-select" required>
                            <?php foreach ($role_list as $role): ?>
                                <option value="<?php echo htmlspecialchars($role); ?>"><?php echo htmlspecialchars($role); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">New Password (Optional)</label>
                        <input type="password" name="edit_password" class="form-control" placeholder="Leave blank to keep current password">
                        <small class="text-danger">Only fill this if you want to reset the user's password.</small>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="edit_allow_theme_control" id="edit_allow_theme_control">
                        <label class="form-check-label" for="edit_allow_theme_control">
                            Allow this user to use sidebar theme controls
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" style="background:#1e3a5f; border-color:#1e3a5f;">Update User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<form method="POST" id="deleteUserForm" style="display:none;">
    <input type="hidden" name="action" value="delete_user">
    <input type="hidden" name="delete_user_id" id="delete_user_id">
</form>

<script>
function openEditModal(id, username, role, allowTheme) {
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username_display').innerText = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_allow_theme_control').checked = (parseInt(allowTheme) === 1);

    var editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
    editModal.show();
}

function confirmDeleteUser(id, username) {
    Swal.fire({
        title: 'Delete user ' + username + '?',
        text: 'This action cannot be undone.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete user'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('delete_user_id').value = id;
            document.getElementById('deleteUserForm').submit();
        }
    });
}
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>