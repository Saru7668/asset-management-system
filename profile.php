<?php
session_start();
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName']) || $_SESSION['UserName'] == "") {
    header("Location: login.php");
    exit;
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$username = $_SESSION['UserName'];
$user_role = $_SESSION['UserRole'] ?? 'user';
$success_msg = false;
$error_msg = "";
$sig_success_msg = isset($_GET['sig_saved']) && $_GET['sig_saved'] == 1;
$sig_deleted_msg = isset($_GET['sig_removed']) && $_GET['sig_removed'] == 1;

$department_list = ["ICT", "HR & Admin", "Accounts & Finance", "Sales & Marketing", "Supply Chain", "Production", "Civil Engineering", "Electrical", "Mechanical", "Glazeline", "Laboratory & Quality Control", "Power & Generation", "Press", "Sorting & Packing", "Squaring & Polishing", "VAT", "Kiln", "Inventory", "Audit", "Brand"];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['profile_update_submit'])) {
    $title = $_POST['title'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $designation = trim($_POST['designation'] ?? '');
    $department = $_POST['department'] ?? '';
    $emergency_contact = trim($_POST['emergency_contact'] ?? '');
    $nid_company_id = trim($_POST['nid_company_id'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if (empty($full_name) || empty($phone) || empty($email) || empty($nid_company_id) || empty($address) || empty($designation) || empty($department) || empty($emergency_contact)) {
        $error_msg = "Please fill all mandatory (*) fields.";
    } else {
        $update_sql = "UPDATE users SET 
            title = ?, full_name = ?, phone = ?, email = ?, 
            designation = ?, department = ?, emergency_contact = ?, 
            nid_company_id = ?, address = ? 
            WHERE username = ?";

        $stmt = mysqli_prepare($conn, $update_sql);
        mysqli_stmt_bind_param(
            $stmt,
            "ssssssssss",
            $title,
            $full_name,
            $phone,
            $email,
            $designation,
            $department,
            $emergency_contact,
            $nid_company_id,
            $address,
            $username
        );

        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['force_profile_update'] = false;
            $success_msg = true;
        } else {
            $error_msg = "Failed to update profile. " . mysqli_error($conn);
        }
        mysqli_stmt_close($stmt);
    }
}

$sql = "SELECT * FROM users WHERE username = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "s", $username);
mysqli_stmt_execute($stmt);
$user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);

if ($success_msg) {
    $body_extra_top = "
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: 'success',
            title: 'Profile Updated!',
            text: 'Your profile information has been saved successfully.',
            confirmButtonColor: '#3b82f6',
            confirmButtonText: 'Great!'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'profile.php';
            }
        });
    });
    </script>";
} else {
    $body_extra_top = "";
}

$page_title = 'My Profile - SCL AMS';
$page_header_icon = 'fas fa-user-circle';
$page_header_title = 'My Profile';
$page_header_subtitle = 'Update your personal and official information';
$page_top_title = 'My Profile';
$page_container_class = 'dashboard-container';

$extra_css = "
.profile-card {
    background: var(--bg-card);
    border-radius: 12px;
    box-shadow: var(--shadow-main);
    overflow: hidden;
    border-top: 4px solid #3b82f6;
    border-left: 1px solid var(--border-color);
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    margin-bottom: 20px;
}

html[data-theme='dark'] .profile-card {
    border-top: 4px solid #facc15 !important;
}

.profile-header {
    background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
    color: #1e293b;
    padding: 18px 22px;
    font-size: 20px;
    font-weight: 700;
    border-bottom: 1px solid #e2e8f0;
}

html[data-theme='dark'] .profile-header {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
    color: #f8fafc !important;
    border-bottom: 1px solid var(--border-color) !important;
}

html[data-theme='dark'] .profile-header i {
    color: #60a5fa !important;
}

.profile-body {
    padding: 30px;
    background: var(--bg-card);
    color: var(--text-main);
}

.form-block {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 20px;
}

html[data-theme='dark'] .form-block {
    background: #0f172a !important;
    border: 1px solid var(--border-color) !important;
}

.form-section-title {
    font-size: 16px;
    font-weight: 700;
    color: #0b2545;
    margin-bottom: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e7eb;
}

html[data-theme='dark'] .form-section-title {
    color: #f8fafc !important;
    border-bottom: 1px solid var(--border-color) !important;
}

.profile-card label {
    font-weight: 600;
    color: #475569;
    font-size: 14px;
    margin-bottom: 6px;
}

html[data-theme='dark'] .profile-card label {
    color: #cbd5e1 !important;
}

.asterisk {
    color: #ef4444;
}

textarea.form-control {
    min-height: 110px;
}

.btn-save {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white !important;
    font-weight: 700;
    padding: 13px;
    font-size: 16px;
    border-radius: 8px;
    width: 100%;
    border: none;
    transition: 0.3s;
}

.btn-save:hover {
    background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
    color: white !important;
}

.btn-delete-signature {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white !important;
    font-weight: 700;
    padding: 13px;
    font-size: 16px;
    border-radius: 8px;
    width: 100%;
    border: none;
    transition: 0.3s;
}

.btn-delete-signature:hover {
    background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
    color: white !important;
}

.signature-preview-box {
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    padding: 15px;
    background: #f8fafc;
}

html[data-theme='dark'] .signature-preview-box {
    background: #0f172a !important;
    border-color: var(--border-color) !important;
}

.signature-preview-box img {
    display: block;
    margin-top: 10px;
    max-width: 220px;
    max-height: 90px;
    width: auto;
    height: auto;
    object-fit: contain;
    border: 1px solid #e5e7eb;
    padding: 4px;
    background: #fff;
}

.sig-file-name {
    margin-top: 6px;
    font-size: 13px;
    color: #64748b;
    word-break: break-all;
}

.sig-error {
    display: none;
    margin-top: 10px;
    color: #dc2626;
    font-size: 14px;
    font-weight: 600;
}

.sig-action-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

@media (max-width: 991px) {
    .profile-body {
        padding: 20px 15px;
    }

    .form-block {
        padding: 15px;
    }

    .sig-action-row {
        grid-template-columns: 1fr;
    }
}
";

ob_start();
?>

<?php if($error_msg): ?>
    <div class="alert alert-danger alert-dismissible fade show mb-4">
        <i class="fas fa-exclamation-circle me-2"></i><?php echo h($error_msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if($sig_success_msg): ?>
    <div class="alert alert-success alert-dismissible fade show mb-4">
        <i class="fas fa-check-circle me-2"></i> Signature saved successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if($sig_deleted_msg): ?>
    <div class="alert alert-warning alert-dismissible fade show mb-4">
        <i class="fas fa-trash-alt me-2"></i> Signature deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="profile-card fade-in">
    <div class="profile-header">
        <i class="fas fa-user-edit me-2"></i> My Profile
    </div>

    <div class="profile-body">
        <form method="POST" action="">
            <input type="hidden" name="profile_update_submit" value="1">

            <div class="form-block">
                <div class="form-section-title">Basic Information</div>

                <div class="row mb-3">
                    <div class="col-md-4 mb-3 mb-md-0">
                        <label>Title</label>
                        <select name="title" class="form-select">
                            <option value="Mr." <?php echo (($user_data['title'] ?? '') == 'Mr.') ? 'selected' : ''; ?>>Mr.</option>
                            <option value="Ms." <?php echo (($user_data['title'] ?? '') == 'Ms.') ? 'selected' : ''; ?>>Ms.</option>
                            <option value="Mrs." <?php echo (($user_data['title'] ?? '') == 'Mrs.') ? 'selected' : ''; ?>>Mrs.</option>
                        </select>
                    </div>

                    <div class="col-md-8">
                        <label>Full Name <span class="asterisk">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?php echo h($user_data['full_name'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label>Phone <span class="asterisk">*</span></label>
                        <input type="text" name="phone" class="form-control" value="<?php echo h($user_data['phone'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label>Email <span class="asterisk">*</span></label>
                        <input type="email" name="email" class="form-control" value="<?php echo h($user_data['email'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-block">
                <div class="form-section-title">Office Information</div>

                <div class="row mb-3">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label>Designation <span class="asterisk">*</span></label>
                        <input type="text" name="designation" class="form-control" value="<?php echo h($user_data['designation'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label>Department <span class="asterisk">*</span></label>
                        <select name="department" class="form-select" required>
                            <?php foreach($department_list as $dept): ?>
                                <option value="<?php echo h($dept); ?>" <?php echo (($user_data['department'] ?? '') == $dept) ? 'selected' : ''; ?>>
                                    <?php echo h($dept); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3 mb-md-0">
                        <label>Emergency Contact <span class="asterisk">*</span></label>
                        <input type="text" name="emergency_contact" class="form-control" value="<?php echo h($user_data['emergency_contact'] ?? ''); ?>" required>
                    </div>

                    <div class="col-md-6">
                        <label>NID/Company ID <span class="asterisk">*</span></label>
                        <input type="text" name="nid_company_id" class="form-control" value="<?php echo h($user_data['nid_company_id'] ?? ''); ?>" required>
                    </div>
                </div>
            </div>

            <div class="form-block">
                <div class="form-section-title">Address</div>

                <div class="mb-0">
                    <label>Address <span class="asterisk">*</span></label>
                    <textarea name="address" class="form-control" rows="4" required><?php echo h($user_data['address'] ?? ''); ?></textarea>
                </div>
            </div>

            <button type="submit" class="btn-save">
                <i class="fas fa-save me-2"></i> Save Profile
            </button>
        </form>
    </div>
</div>

<div class="profile-card fade-in">
    <div class="profile-header">
        <i class="fas fa-signature me-2"></i> Signature Setup
    </div>
    <div class="profile-body">
        <form action="profile_signature_save.php" method="POST" enctype="multipart/form-data">
            <div class="form-block">
                <div class="form-section-title">Upload Signature</div>

                <div class="mb-3">
                    <label>Upload Signature Image</label>
                    <input type="file" name="signature_file" class="form-control" accept=".png,.jpg,.jpeg,.webp" required>
                    <small class="text-muted">Recommended: transparent PNG, max 2MB.</small>
                </div>

                <div class="signature-preview-box">
                    <strong>Current Signature:</strong>

                    <?php if (!empty($user_data['signature_file'])): ?>
                        <?php
                            $sig_file = trim((string)$user_data['signature_file']);
                            $sig_url  = '/uploads/signatures/' . rawurlencode($sig_file) . '?v=' . time();
                        ?>
                        <div class="sig-file-name"><?php echo h($sig_file); ?></div>

                        <img
                            src="<?php echo h($sig_url); ?>"
                            alt="Signature"
                            onerror="this.style.display='none'; document.getElementById('sig-error-box').style.display='block';"
                        >

                        <div id="sig-error-box" class="sig-error">
                            Signature image failed to load.
                        </div>
                    <?php else: ?>
                        <div class="text-muted mt-2">No signature uploaded yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sig-action-row">
                <button type="submit" class="btn-save">
                    <i class="fas fa-upload me-2"></i> Save Signature
                </button>
            </div>
        </form>

        <?php if (!empty($user_data['signature_file'])): ?>
            <form action="remove_signature.php" method="POST" class="mt-3" onsubmit="return confirm('Are you sure you want to delete your signature?');">
                <button type="submit" class="btn-delete-signature">
                    <i class="fas fa-trash-alt me-2"></i> Delete Signature
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>