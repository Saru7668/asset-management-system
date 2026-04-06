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

$current_role = strtolower(trim($_SESSION['UserRole'] ?? ''));
if ($current_role !== 'superadmin') {
    die("Access Denied. Only Super Admin can access the Recycle Bin.");
}

$msg = '';
$err = '';

function esc($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function extract_deleted_item_label(array $data): string {
    $candidates = [
        'inventory',
        'inventory_no',
        'asset_name',
        'asset',
        'item_name',
        'name',
        'serial_model',
        'serial',
        'details'
    ];

    foreach ($candidates as $key) {
        if (!empty($data[$key])) {
            return (string)$data[$key];
        }
    }

    return 'Unknown Item';
}

function extract_deleted_subtitle(array $data): string {
    $parts = [];
    foreach (['employee_name', 'employee_id', 'department', 'status'] as $key) {
        if (!empty($data[$key])) {
            $label = ucwords(str_replace('_', ' ', $key));
            $parts[] = $label . ': ' . $data[$key];
        }
    }
    return implode(' | ', $parts);
}

function restore_record($conn, $recycle_id) {
    $stmt = mysqli_prepare($conn, "SELECT original_table, original_id, record_data FROM recycle_bin WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $recycle_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    if ($row = mysqli_fetch_assoc($res)) {
        $table = $row['original_table'];
        $data = json_decode($row['record_data'], true);

        if (!$data || !is_array($data)) {
            return false;
        }

        unset($data['id']);

        $columns = array_keys($data);
        $values = array_values($data);

        if (empty($columns)) {
            return false;
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $colString = implode(',', array_map(function($col) {
            return "`" . str_replace("`", "", $col) . "`";
        }, $columns));

        $insert_sql = "INSERT INTO `$table` ($colString) VALUES ($placeholders)";
        $insert_stmt = mysqli_prepare($conn, $insert_sql);

        if ($insert_stmt) {
            $types = '';
            foreach ($values as $val) {
                if (is_int($val)) $types .= 'i';
                elseif (is_float($val)) $types .= 'd';
                else $types .= 's';
            }

            $bind = [];
            $bind[] = $types;
            for ($i = 0; $i < count($values); $i++) {
                $bind[] = &$values[$i];
            }

            call_user_func_array([$insert_stmt, 'bind_param'], $bind);

            if (mysqli_stmt_execute($insert_stmt)) {
                mysqli_query($conn, "DELETE FROM recycle_bin WHERE id = $recycle_id");
                mysqli_stmt_close($insert_stmt);
                mysqli_stmt_close($stmt);
                return true;
            }

            mysqli_stmt_close($insert_stmt);
        }
    }

    mysqli_stmt_close($stmt);
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $ids = $_POST['ids'] ?? [];

    if (in_array($action, ['bulk_restore', 'bulk_delete'], true) && empty($ids)) {
        $err = "Please select at least one record.";
    } else {
        if ($action === 'bulk_restore') {
            $success_count = 0;
            foreach ($ids as $id) {
                if (restore_record($conn, (int)$id)) {
                    $success_count++;
                }
            }
            $msg = "$success_count record(s) restored successfully.";
        } elseif ($action === 'bulk_delete') {
            $id_list = implode(',', array_map('intval', $ids));
            if (mysqli_query($conn, "DELETE FROM recycle_bin WHERE id IN ($id_list)")) {
                $msg = count($ids) . " record(s) permanently deleted.";
            } else {
                $err = "Failed to delete records.";
            }
        } elseif ($action === 'single_restore') {
            $single_id = (int)$_POST['single_id'];
            if (restore_record($conn, $single_id)) {
                $msg = "Record restored successfully.";
            } else {
                $err = "Failed to restore record.";
            }
        } elseif ($action === 'single_delete') {
            $single_id = (int)$_POST['single_id'];
            if (mysqli_query($conn, "DELETE FROM recycle_bin WHERE id = $single_id")) {
                $msg = "Record permanently deleted.";
            } else {
                $err = "Failed to delete record.";
            }
        }
    }
}

$rows = [];
$q = mysqli_query($conn, "SELECT * FROM recycle_bin ORDER BY deleted_at DESC");
while ($r = mysqli_fetch_assoc($q)) {
    $decoded = json_decode($r['record_data'], true);
    $r['decoded_data'] = is_array($decoded) ? $decoded : [];
    $rows[] = $r;
}

$page_title = 'Recycle Bin - SCL AMS';
$page_header_icon = 'fas fa-trash-restore';
$page_header_title = 'System Recycle Bin';
$page_header_subtitle = 'Restore or permanently delete removed records';
$page_top_title = 'Recycle Bin';
$page_container_class = 'dashboard-container-xl';

$extra_css = "
.recycle-toolbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:16px;
    flex-wrap:wrap;
}
.count-pill {
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:8px 12px;
    border-radius:999px;
    background:#e8f1ff;
    color:#0b2545;
    font-weight:700;
    font-size:13px;
}
.table-wrap {
    overflow:auto;
}
.recycle-table thead th {
    background:#0b2545;
    color:#fff;
    font-size:12px;
    text-transform:uppercase;
    letter-spacing:.04em;
    white-space:nowrap;
}
.recycle-table td {
    vertical-align:middle;
}
.deleted-item {
    min-width:240px;
}
.deleted-title {
    font-weight:700;
    color:#0f172a;
    margin-bottom:4px;
}
.deleted-sub {
    font-size:12px;
    color:#64748b;
    line-height:1.45;
    white-space:normal;
}
.meta-badge {
    display:inline-flex;
    align-items:center;
    gap:6px;
    padding:4px 8px;
    border-radius:999px;
    font-size:12px;
    font-weight:600;
}
.meta-badge.table {
    background:#e2e8f0;
    color:#0f172a;
}
.meta-badge.id {
    background:#dbeafe;
    color:#1d4ed8;
}
.action-form {
    display:inline;
}
/* Updated Checkbox CSS */
.item-checkbox,
#selectAll,
#selectAllHeader {
    width: 20px !important;
    height: 20px !important;
    accent-color: #dc3545;
    cursor: pointer;
    border: 2px solid #94a3b8;
    border-radius: 4px;
    margin: 0;
    display: inline-block;
    position: relative;
    opacity: 1 !important;
    visibility: visible !important;
}
.item-checkbox:focus,
#selectAll:focus,
#selectAllHeader:focus {
    outline: 2px solid rgba(37, 99, 235, 0.25);
    outline-offset: 2px;
}
.checkbox-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100%;
}
.bulk-bar {
    display:none;
    margin-bottom:14px;
    padding:12px 14px;
    border:1px solid #dbe3ef;
    background:#f8fbff;
    border-radius:12px;
}
.bulk-bar.active {
    display:flex;
}
.bulk-left {
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}
.bulk-count {
    background:#fff;
    border:1px solid #cbd5e1;
    border-radius:999px;
    padding:6px 10px;
    font-weight:700;
    font-size:13px;
    color:#0b2545;
}
html[data-theme='dark'] .bulk-bar {
    background:#111827;
    border-color:#334155;
}
html[data-theme='dark'] .count-pill,
html[data-theme='dark'] .bulk-count {
    background:#0f172a;
    color:#dbeafe;
    border-color:#334155;
}
html[data-theme='dark'] .recycle-table thead th {
    background:#0f172a;
    color:#f8fafc;
}
html[data-theme='dark'] .deleted-title {
    color:#f8fafc;
}
html[data-theme='dark'] .deleted-sub {
    color:#94a3b8;
}
html[data-theme='dark'] .meta-badge.table {
    background:#1f2937;
    color:#cbd5e1;
}
html[data-theme='dark'] .meta-badge.id {
    background:#1e3a8a;
    color:#bfdbfe;
}
html[data-theme='dark'] .item-checkbox,
html[data-theme='dark'] #selectAll,
html[data-theme='dark'] #selectAllHeader {
    border-color: #64748b;
    accent-color: #f43f5e;
}
";

ob_start();
?>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?php echo esc($msg); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($err): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?php echo esc($err); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST" id="recycleForm">
    <div class="recycle-toolbar">
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="submit" name="action" value="bulk_restore" class="btn btn-success" onclick="return confirmBulk('restore', event);">
                <i class="fas fa-undo-alt me-1"></i> Restore Selected
            </button>
            <button type="submit" name="action" value="bulk_delete" class="btn btn-danger" onclick="return confirmBulk('delete', event);">
                <i class="fas fa-trash me-1"></i> Delete Selected
            </button>
        </div>
        <div class="count-pill" id="selectedCount">Selected: 0 item(s)</div>
    </div>

    <div class="bulk-bar" id="bulkBar">
        <div class="bulk-left">
            <label class="mb-0 d-flex align-items-center gap-2 fw-semibold">
                <input class="item-checkbox" type="checkbox" id="selectAll">
                Select All
            </label>
            <span class="bulk-count" id="selectedCountInline">0 selected</span>
        </div>
    </div>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered table-hover recycle-table mb-0">
                <thead>
                    <tr>
                        <th style="width:50px; text-align:center;">
                            <div class="checkbox-wrapper">
                                <input class="item-checkbox" type="checkbox" id="selectAllHeader">
                            </div>
                        </th>
                        <th>Table Name</th>
                        <th>Deleted Item</th>
                        <th>Record ID</th>
                        <th>Deleted By</th>
                        <th>Deleted At</th>
                        <th style="width:180px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rows): ?>
                        <?php foreach ($rows as $r): ?>
                            <?php
                                $data = $r['decoded_data'];
                                $item_label = extract_deleted_item_label($data);
                                $subtitle = extract_deleted_subtitle($data);
                                $inventory = $data['inventory'] ?? '';
                                $employee = $data['employee_name'] ?? '';
                                $serial = $data['serial_model'] ?? '';
                            ?>
                            <tr>
                                <td class="text-center">
                                    <div class="checkbox-wrapper">
                                        <input class="item-checkbox" type="checkbox" name="ids[]" value="<?php echo (int)$r['id']; ?>">
                                    </div>
                                </td>
                                <td>
                                    <span class="meta-badge table">
                                        <i class="fas fa-database"></i>
                                        <?php echo esc($r['original_table']); ?>
                                    </span>
                                </td>
                                <td class="deleted-item">
                                    <div class="deleted-title">
                                        <?php echo esc($item_label); ?>
                                    </div>
                                    <div class="deleted-sub">
                                        <?php if ($inventory): ?><strong>Inventory:</strong> <?php echo esc($inventory); ?><br><?php endif; ?>
                                        <?php if ($serial): ?><strong>Serial/Model:</strong> <?php echo esc($serial); ?><br><?php endif; ?>
                                        <?php if ($employee): ?><strong>Employee:</strong> <?php echo esc($employee); ?><br><?php endif; ?>
                                        <?php if ($subtitle): ?><?php echo esc($subtitle); ?><?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="meta-badge id">
                                        #<?php echo (int)$r['original_id']; ?>
                                    </span>
                                </td>
                                <td><?php echo esc($r['deleted_by']); ?></td>
                                <td><?php echo !empty($r['deleted_at']) ? date('d M Y, h:i A', strtotime($r['deleted_at'])) : '-'; ?></td>
                                <td>
                                    <form method="POST" class="action-form">
                                        <input type="hidden" name="single_id" value="<?php echo (int)$r['id']; ?>">
                                        <button type="submit" name="action" value="single_restore" class="btn btn-sm btn-success" onclick="return confirm('Restore this record?')">
                                            Restore
                                        </button>
                                        <button type="submit" name="action" value="single_delete" class="btn btn-sm btn-danger" onclick="return confirm('Permanently delete this record?')">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">Recycle bin is empty.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
const selectAll = document.getElementById('selectAll');
const selectAllHeader = document.getElementById('selectAllHeader');
const itemCheckboxes = () => document.querySelectorAll('input[name="ids[]"].item-checkbox');
const bulkBar = document.getElementById('bulkBar');
const selectedCount = document.getElementById('selectedCount');
const selectedCountInline = document.getElementById('selectedCountInline');

function updateSelectionUI() {
    const checked = document.querySelectorAll('input[name="ids[]"].item-checkbox:checked');
    const total = itemCheckboxes().length;
    const count = checked.length;
    const allChecked = total > 0 && count === total;

    if (selectAll) selectAll.checked = allChecked;
    if (selectAllHeader) selectAllHeader.checked = allChecked;

    if (selectedCount) selectedCount.textContent = 'Selected: ' + count + ' item(s)';
    if (selectedCountInline) selectedCountInline.textContent = count + ' selected';

    if (bulkBar) {
        bulkBar.classList.toggle('active', count > 0);
    }
}

function syncSelectAll(source) {
    itemCheckboxes().forEach(cb => cb.checked = source.checked);
    updateSelectionUI();
}

if (selectAll) {
    selectAll.addEventListener('change', function() {
        syncSelectAll(this);
    });
}

if (selectAllHeader) {
    selectAllHeader.addEventListener('change', function() {
        syncSelectAll(this);
    });
}

itemCheckboxes().forEach(cb => {
    cb.addEventListener('change', updateSelectionUI);
});

function confirmBulk(type, event) {
    if (event) event.preventDefault();

    const checked = document.querySelectorAll('input[name="ids[]"].item-checkbox:checked');
    if (checked.length === 0) {
        alert('Please select at least one record.');
        return false;
    }

    const text = type === 'restore'
        ? 'Restore ' + checked.length + ' selected record(s)?'
        : 'Permanently delete ' + checked.length + ' selected record(s)? This cannot be undone!';

    if (confirm(text)) {
        // Create hidden input to pass the button value
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = type === 'restore' ? 'bulk_restore' : 'bulk_delete';
        
        const form = document.getElementById('recycleForm');
        form.appendChild(actionInput);
        form.submit();
    }
    return false;
}

document.addEventListener('DOMContentLoaded', updateSelectionUI);
</script>

<?php
$body_content = ob_get_clean();
require_once('layout_inventory.php');
?>