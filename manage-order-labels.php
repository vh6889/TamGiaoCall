<?php
/**
 * Quản lý Nhãn Đơn hàng - VERSION MỚI với core_status
 * Admin tạo nhãn và BẮT BUỘC chọn core_status
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_admin();

$page_title = 'Quản Lý Nhãn Đơn Hàng';

function generate_label_key() {
    $timestamp = time();
    $random = substr(md5(uniqid(mt_rand(), true)), 0, 4);
    return "lbl_{$timestamp}_{$random}";
}

// XỬ LÝ FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $color = sanitize($_POST['color'] ?? '#6c757d');
    $icon = sanitize($_POST['icon'] ?? 'fa-tag');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $core_status = $_POST['core_status'] ?? 'processing'; // BẮT BUỘC
    
    if (empty($name)) {
        set_flash('error', 'Tên nhãn là bắt buộc.');
    } else {
        if ($_POST['form_action'] === 'add') {
            $key = generate_label_key();
            
            // Tự động set label_value dựa vào core_status
            $label_value = ($core_status === 'success') ? 1 : 0;

            db_insert('order_labels', [
                'label_key' => $key,
                'label_name' => $name,
                'label_value' => $label_value,
                'core_status' => $core_status, // MỚI: Bắt buộc chọn
                'description' => $description,
                'color' => $color,
                'icon' => $icon,
                'sort_order' => $sort_order,
                'is_system' => 0,
                'is_default' => 0,
                'created_by' => get_logged_user()['id']
            ]);
            
            log_activity('create_label', "Created label: {$name} (core: {$core_status})");
            set_flash('success', "Đã thêm nhãn '{$name}' vào trạng thái '{$core_status}'!");

        } elseif ($_POST['form_action'] === 'edit') {
            $key_to_edit = sanitize($_POST['key_to_edit']);
            
            $label = db_get_row("SELECT * FROM order_labels WHERE label_key = ?", [$key_to_edit]);
            if ($label['is_system']) {
                set_flash('error', 'Không thể sửa nhãn hệ thống!');
            } else {
                // Tự động set label_value dựa vào core_status
                $label_value = ($core_status === 'success') ? 1 : 0;
                
                db_update('order_labels', [
                    'label_name' => $name,
                    'label_value' => $label_value,
                    'core_status' => $core_status,
                    'description' => $description,
                    'color' => $color,
                    'icon' => $icon,
                    'sort_order' => $sort_order
                ], 'label_key = ?', [$key_to_edit]);
                
                log_activity('update_label', "Updated label: {$name} (core: {$core_status})");
                set_flash('success', 'Đã cập nhật!');
            }
        }
    }
    redirect('manage-order-labels.php');
}

// XÓA
if (isset($_GET['delete'])) {
    $key = sanitize($_GET['delete']);
    $label = db_get_row("SELECT * FROM order_labels WHERE label_key = ?", [$key]);
    
    if (!$label) {
        set_flash('error', 'Nhãn không tồn tại!');
    } elseif ($label['is_system'] || $label['is_default']) {
        set_flash('error', 'Không thể xóa nhãn hệ thống!');
    } else {
        $count = db_get_var("SELECT COUNT(*) FROM orders WHERE primary_label = ?", [$key]);
        if ($count > 0) {
            set_flash('error', "Nhãn đang được dùng bởi {$count} đơn.");
        } else {
            db_delete('order_labels', 'label_key = ?', [$key]);
            set_flash('success', 'Đã xóa!');
        }
    }
    redirect('manage-order-labels.php');
}

// Lấy nhãn theo core_status
$labels_by_core = [
    'new' => [],
    'processing' => [],
    'success' => [],
    'failed' => []
];

$all_labels = db_get_results("
    SELECT * FROM order_labels 
    ORDER BY core_status, is_system DESC, is_default DESC, sort_order ASC
");

foreach ($all_labels as $label) {
    $labels_by_core[$label['core_status']][] = $label;
}

include 'includes/header.php';
?>

<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-tags me-2"></i>Quản lý Nhãn Đơn Hàng</h5>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i> Thêm Nhãn
        </button>
    </div>

    <?php display_flash(); ?>

    <!-- Tabs cho từng core_status -->
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-new">
                <i class="fas fa-plus-circle text-info"></i> Mới (<?php echo count($labels_by_core['new']); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-processing">
                <i class="fas fa-spinner text-warning"></i> Đang xử lý (<?php echo count($labels_by_core['processing']); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-success">
                <i class="fas fa-check-circle text-success"></i> Thành công (<?php echo count($labels_by_core['success']); ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-failed">
                <i class="fas fa-times-circle text-danger"></i> Thất bại (<?php echo count($labels_by_core['failed']); ?>)
            </a>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <?php foreach (['new', 'processing', 'success', 'failed'] as $core): ?>
        <div class="tab-pane <?php echo $core === 'new' ? 'show active' : ''; ?>" id="tab-<?php echo $core; ?>">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="30">STT</th>
                        <th>Tên nhãn</th>
                        <th width="150">Hiển thị</th>
                        <th width="100">Loại</th>
                        <th width="150">Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($labels_by_core[$core])): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            Chưa có nhãn nào trong trạng thái này
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($labels_by_core[$core] as $label): ?>
                        <tr>
                            <td><?php echo $label['sort_order']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($label['label_name']); ?></strong>
                                <?php if ($label['description']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($label['description']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge" style="background: <?php echo $label['color']; ?>;">
                                    <i class="<?php echo $label['icon']; ?>"></i> 
                                    <?php echo htmlspecialchars($label['label_name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($label['is_system']): ?>
                                    <span class="badge bg-danger">HỆ THỐNG</span>
                                <?php elseif ($label['is_default']): ?>
                                    <span class="badge bg-primary">MẶC ĐỊNH</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Tùy chỉnh</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$label['is_system'] && !$label['is_default']): ?>
                                    <button class="btn btn-sm btn-primary" 
                                            onclick='openEditModal(<?php echo json_encode($label); ?>)'>
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo urlencode($label['label_key']); ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Xóa nhãn này?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                <?php else: ?>
                                    <i class="fas fa-lock text-muted"></i>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="labelModal">
    <div class="modal-dialog">
        <form method="POST">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="modalTitle">Thêm Nhãn</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="form_action" id="formAction">
                    <input type="hidden" name="key_to_edit" id="keyToEdit">
                    
                    <div class="mb-3">
                        <label>Tên nhãn <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="labelName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label>Thuộc trạng thái <span class="text-danger">*</span></label>
                        <select class="form-select" name="core_status" id="coreStatus" required>
                            <option value="">-- Chọn trạng thái --</option>
                            <option value="processing">🔄 Đang xử lý</option>
                            <option value="failed">❌ Thất bại</option>
                            <!-- new và success không cho tạo thêm -->
                        </select>
                        <small class="text-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            BẮT BUỘC! Nhãn này sẽ thuộc trạng thái bạn chọn
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label>Mô tả</label>
                        <textarea class="form-control" name="description" id="labelDesc" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Màu sắc</label>
                            <input type="color" class="form-control" name="color" id="labelColor" value="#6c757d">
                        </div>
                        <div class="col-6 mb-3">
                            <label>Icon (Font Awesome)</label>
                            <select class="form-select" name="icon" id="labelIcon">
                                <option value="fa-tag">fa-tag</option>
                                <option value="fa-phone">fa-phone</option>
                                <option value="fa-clock">fa-clock</option>
                                <option value="fa-user">fa-user</option>
                                <option value="fa-ban">fa-ban</option>
                                <option value="fa-phone-slash">fa-phone-slash</option>
                                <option value="fa-exclamation">fa-exclamation</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Thứ tự hiển thị</label>
                        <input type="number" class="form-control" name="sort_order" id="sortOrder" value="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
const modal = new bootstrap.Modal(document.getElementById('labelModal'));

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Thêm Nhãn Mới';
    document.getElementById('formAction').value = 'add';
    document.getElementById('labelName').value = '';
    document.getElementById('coreStatus').value = '';
    document.getElementById('labelDesc').value = '';
    document.getElementById('labelColor').value = '#6c757d';
    document.getElementById('labelIcon').value = 'fa-tag';
    document.getElementById('sortOrder').value = '0';
    modal.show();
}

function openEditModal(data) {
    document.getElementById('modalTitle').textContent = 'Sửa: ' + data.label_name;
    document.getElementById('formAction').value = 'edit';
    document.getElementById('keyToEdit').value = data.label_key;
    document.getElementById('labelName').value = data.label_name;
    document.getElementById('coreStatus').value = data.core_status;
    document.getElementById('labelDesc').value = data.description || '';
    document.getElementById('labelColor').value = data.color;
    document.getElementById('labelIcon').value = data.icon;
    document.getElementById('sortOrder').value = data.sort_order;
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>