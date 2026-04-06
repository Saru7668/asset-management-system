<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
require_once('db.php');
require_once('header.php');

if (!isset($_SESSION['UserName'])) {
    header("Location: login.php");
    exit;
}

function h($v){ return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Invalid ID.');

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['uploaded_form_file']) && ($_FILES['uploaded_form_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['uploaded_form_file']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMime, true)) {
            $err = 'Only JPG, PNG, WEBP or PDF allowed.';
        } else {
            $ext = strtolower(pathinfo($_FILES['uploaded_form_file']['name'], PATHINFO_EXTENSION));
            $dir = __DIR__ . '/uploads/temp_lease/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            $fileName = 'lease_upload_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            $dest = $dir . $fileName;

            if (move_uploaded_file($_FILES['uploaded_form_file']['tmp_name'], $dest)) {
                $path = 'uploads/temp_lease/' . $fileName;
                $stmt = mysqli_prepare($conn, "UPDATE temp_accessories_leases SET uploaded_form_file = ? WHERE id = ? LIMIT 1");
                mysqli_stmt_bind_param($stmt, "si", $path, $id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                header("Location: temp_accessories_lease_list.php");
                exit;
            } else {
                $err = 'Upload failed.';
            }
        }
    } else {
        $err = 'Please choose a file.';
    }
}

$page_title = 'Upload Lease Copy';
$page_header_icon = 'fas fa-upload';
$page_header_title = 'Upload Lease Copy';
$page_header_subtitle = 'Attach signed scan or PDF';
$page_top_title = 'Upload Lease Copy';
$page_container_class = 'dashboard-container-xl';

ob_start();
?>

<?php if ($msg): ?><div class="alert alert-success"><?php echo h($msg); ?></div><?php endif; ?>
<?php if ($err): ?><div class="alert alert-danger"><?php echo h($err); ?></div><?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label class="form-label">Choose signed form file</label>
                <input type="file" name="uploaded_form_file" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
            </div>
            <div class="d-flex gap-2">
                <a href="temp_accessories_lease_list.php" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Upload File</button>
            </div>
        </form>
    </div>
</div>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');