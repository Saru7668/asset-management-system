<?php
session_start();
require_once('db.php');
require_once('header.php');
require_once('auth_guard.php');


require_roles(
    ['SuperAdmin', 'admin', 'staff', 'hr'],
    'You do not have permission to view inventory.',
    'index.php'
);

// 1. Check if user is logged in
if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

$user = $_SESSION['UserName'];
$role = strtolower($_SESSION['UserRole'] ?? '');
$message = "";

// 2. Role Restriction: Only SuperAdmin, Staff, or HR can access this page
$allowed_roles = ['superadmin', 'staff', 'hr'];

if (!in_array($role, $allowed_roles)) {
    echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
    echo "<script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'error',
                title: 'Access Denied',
                text: 'You do not have permission to add new inventory.',
                confirmButtonColor: '#d33'
            }).then(() => {
                window.location = 'ict_inventory_list.php';
            });
        });
    </script>";
    exit;
}

// 3. Process Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_inventory'])) {
    $inventory      = mysqli_real_escape_string($conn, trim($_POST['inventory']));
    $details        = mysqli_real_escape_string($conn, trim($_POST['details']));
    $serial_model   = mysqli_real_escape_string($conn, trim($_POST['serial_model']));
    $brand          = mysqli_real_escape_string($conn, trim($_POST['brand']));
    $unit           = mysqli_real_escape_string($conn, trim($_POST['unit']));
    $vendor         = mysqli_real_escape_string($conn, trim($_POST['vendor']));
    $vendor_date    = mysqli_real_escape_string($conn, trim($_POST['vendor_bill_date']));
    $warranty       = (int)$_POST['warranty_months'];
    $dep_in         = (float)$_POST['depreciation_in_warranty'];
    $dep_out        = (float)$_POST['depreciation_after_warranty'];
    
    $chk = mysqli_query($conn, "SELECT id FROM assets WHERE inventory = '$inventory'");
    if (mysqli_num_rows($chk) > 0) {
        $message = "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Warning!', 'This Inventory Name is already in the system!', 'warning');
            });
        </script>";
    } else {
        $vendor_bill_path = "";
        if (isset($_FILES['vendor_bill']) && $_FILES['vendor_bill']['error'] == 0) {
            $ext = pathinfo($_FILES['vendor_bill']['name'], PATHINFO_EXTENSION);
            if (strtolower($ext) === 'pdf') {
                $upload_dir = 'uploads/vendor_bills/';
                if (!is_dir($upload_dir)) { 
                    mkdir($upload_dir, 0777, true); 
                }
                
                $file_name = time() . "_" . preg_replace('/[^A-Za-z0-9]/', '_', $inventory) . ".pdf";
                $target_file = $upload_dir . $file_name;
                
                if (move_uploaded_file($_FILES['vendor_bill']['tmp_name'], $target_file)) {
                    $vendor_bill_path = $target_file;
                }
            } else {
                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire('Error!', 'Only PDF files are allowed for Vendor Bill.', 'error');
                    });
                </script>";
            }
        }
        
        if (empty($message)) {
            $sql = "INSERT INTO assets (
                        inventory, details, serial_model, brand, unit, vendor, 
                        vendor_bill, vendor_bill_date, warranty_months, 
                        depreciation_in_warranty, depreciation_after_warranty, 
                        status, created_user, created_datetime
                    ) VALUES (
                        '$inventory', '$details', '$serial_model', '$brand', '$unit', '$vendor', 
                        '$vendor_bill_path', '$vendor_date', $warranty, 
                        $dep_in, $dep_out, 
                        'Available', '$user', NOW()
                    )";
            
            if (mysqli_query($conn, $sql)) {
                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire({
                            icon: 'success',
                            title: 'Added!',
                            text: 'Inventory successfully added.',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => window.location='ict_inventory_list.php');
                    });
                </script>";
            } else {
                $message = "<script>
                    document.addEventListener('DOMContentLoaded', function() {
                        Swal.fire('Database Error', 'Could not add inventory.', 'error');
                    });
                </script>";
            }
        }
    }
}

$page_title = 'Add ICT Inventory';
$page_header_icon = 'fas fa-plus-circle';
$page_header_title = 'Add ICT Inventory';
$page_header_subtitle = 'Create a new inventory record with vendor and warranty details';
$page_top_title = 'Add New Product to Inventory';
$page_top_actions = '<a href="ict_inventory_list.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Back to List</a>';
$page_container_class = 'dashboard-container';

$body_extra_top = $message;

$extra_css = "
.dashboard-container {
    max-width: 1100px;
    margin: 0 auto;
}
.form-label {
    font-weight: 600;
    font-size: 13px;
    color: #495057;
    margin-bottom: 8px;
}
.form-control,
.form-select {
    min-height: 44px;
    border-radius: 8px;
    border: 1px solid #cbd5e1;
}
.form-control:focus,
.form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.2rem rgba(59,130,246,0.15);
}
textarea.form-control {
    min-height: 90px;
}
@media (max-width: 991px) {
    .page-topbar {
        flex-direction: column;
        align-items: stretch;
    }
    .form-card-body {
        padding: 18px 15px;
    }
}
";

ob_start();
?>
<div class="form-card">
    <div class="form-card-header">
        <h5 class="mb-0">
            <i class="fas fa-box-open me-2"></i>Inventory Information
        </h5>
    </div>

    <div class="form-card-body">
        <form action="ict_inventory_add.php" method="POST" enctype="multipart/form-data">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Inventory Name/ID <span class="text-danger">*</span></label>
                    <input type="text" name="inventory" class="form-control" placeholder="e.g. INV-LAP-161" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Serial / Model <span class="text-danger">*</span></label>
                    <input type="text" name="serial_model" class="form-control" required>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Brand</label>
                    <select name="brand" class="form-select" required>
                        <option value="">Select Brand</option>
                        <option value="HP">HP</option>
                        <option value="Dell">Dell</option>
                        <option value="Lenovo">Lenovo</option>
                        <option value="Asus">Asus</option>
                        <option value="Hikvision">Hikvision</option>
                        <option value="Logitech">Logitech</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Unit / Location</label>
                    <select name="unit" class="form-select" required>
                        <option value="">Select Unit</option>
                        <option value="Corporate office">Corporate office</option>
                        <option value="Factory">Factory</option>
                        <option value="District Level">District Level</option>
                        <option value="Hatirpool Showroom">Hatirpool Showroom</option>
                        <option value="Warehouse-Lakshmipur">Warehouse-Lakshmipur</option>
                        <option value="Chittagong Showroom">Chittagong Showroom</option>
                        <option value="Tejgaon Showroom">Tejgaon Showroom</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Product Details (Specs) <span class="text-danger">*</span></label>
                <textarea name="details" class="form-control" rows="2" placeholder="e.g. Core i5 13th Gen, 16GB RAM, 512 SSD..." required></textarea>
            </div>

            <hr class="my-4">

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Vendor Name</label>
                    <input type="text" name="vendor" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Vendor Bill Receive Date</label>
                    <input type="date" name="vendor_bill_date" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Vendor Bill Attachment (PDF Only)</label>
                    <input type="file" name="vendor_bill" class="form-control" accept="application/pdf">
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-md-4">
                    <label class="form-label">Warranty Period (Months)</label>
                    <input type="number" name="warranty_months" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Depreciation Cost (In warranty)</label>
                    <input type="number" step="0.01" name="depreciation_in_warranty" class="form-control" value="0">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Depreciation Cost (After warranty)</label>
                    <input type="number" step="0.01" name="depreciation_after_warranty" class="form-control" value="0">
                </div>
            </div>

            <div class="d-flex justify-content-end flex-wrap gap-2">
                <a href="ict_inventory_list.php" class="btn btn-secondary me-2">Cancel</a>
                <button type="submit" name="add_inventory" class="btn btn-success px-4">
                    <i class="fas fa-save me-1"></i> Save Product
                </button>
            </div>
        </form>
    </div>
</div>
<?php
$body_content = ob_get_clean();

require_once('layout_inventory.php');
