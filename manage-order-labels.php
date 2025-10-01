<?php
/**
 * Quản lý Nhãn Đơn hàng
 * Admin CHỈ TẠO label có label_value = 0
 * KHÔNG TẠO được label_value = 1 (hardcode)
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_admin();

$page_title = 'Quản Lý Nhãn Đơn Hàng';

/**
 * Generate unique label key - UUID random
 */
function generate_label_key() {
    $timestamp = time();
    $random = substr(md5(uniqid(mt_rand(), true)), 0, 4);
    $key = "lbl_{$timestamp}_{$random}";
    
    $exists = db_get_var("SELECT COUNT(*) FROM order_labels WHERE label_key = ?", [$key]);
    if ($exists) {
        return generate_label_key();
    }
    
    return $key;
}

// XỬ LÝ FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $color = sanitize($_POST['color'] ?? '#6c757d');
    $icon = sanitize($_POST['icon'] ?? 'fa-tag');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    
    if (empty($name)) {
        set_flash('error', 'Tên nhãn là bắt buộc.');
    } else {
        if ($_POST['form_action'] === 'add') {
            $key = generate_label_key();

            db_insert('order_labels', [
                'label_key' => $key,
                'label_name' => $name,
                'label_value' => 0, // HARDCODE
                'description' => $description,
                'color' => $color,
                'icon' => $icon,
                'sort_order' => $sort_order,
                'is_system' => 0,
                'created_by' => get_logged_user()['id']
            ]);
            
            log_activity('create_label', "Created label: {$name} (key: {$key})");
            set_flash('success', 'Đã thêm nhãn mới!');

        } elseif ($_POST['form_action'] === 'edit') {
            $key_to_edit = sanitize($_POST['key_to_edit']);
            
            $is_system = db_get_var("SELECT is_system FROM order_labels WHERE label_key = ?", [$key_to_edit]);
            if ($is_system) {
                set_flash('error', 'Không thể sửa nhãn hệ thống!');
            } else {
                db_update('order_labels', [
                    'label_name' => $name,
                    'description' => $description,
                    'color' => $color,
                    'icon' => $icon,
                    'sort_order' => $sort_order
                ], 'label_key = ?', [$key_to_edit]);
                
                log_activity('update_label', "Updated label: {$name}");
                set_flash('success', 'Đã cập nhật!');
            }
        }
    }
    redirect('manage-order-labels.php');
}

// XÓA
if (isset($_GET['delete'])) {
    $key = sanitize($_GET['delete']);
    $is_system = db_get_var("SELECT is_system FROM order_labels WHERE label_key = ?", [$key]);
    
    if ($is_system) {
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

$labels = get_order_labels(true);
include 'includes/header.php';
?>

<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-tags me-2"></i>Quản lý Nhãn</h5>
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus me-2"></i> Thêm Nhãn
        </button>
    </div>

    <div class="alert alert-info">
        <strong>Hệ thống có 2 nhãn cố định:</strong>
        <ul class="mb-0 mt-2">
            <li><code>Đơn mới</code> (label_value=0) - Mặc định</li>
            <li><code>Hoàn thành</code> (label_value=1) - Tính doanh thu</li>
        </ul>
        Các nhãn bạn tạo chỉ để ghi chú, không ảnh hưởng doanh thu.
    </div>

    <?php display_flash(); ?>

    <table class="table table-hover">
        <thead>
            <tr>
                <th>STT</th>
                <th>Tên</th>
                <th>Màu</th>
                <th>Giá trị</th>
                <th>Key</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($labels as $l): ?>
            <tr>
                <td><?php echo $l['sort_order']; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($l['label_name']); ?></strong>
                    <?php if ($l['is_system']): ?>
                        <span class="badge bg-danger ms-2">HỆ THỐNG</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span class="badge" style="background: <?php echo $l['color']; ?>;">
                        <i class="<?php echo $l['icon']; ?>"></i> <?php echo htmlspecialchars($l['label_name']); ?>
                    </span>
                </td>
                <td>
                    <?php if ($l['label_value'] == 1): ?>
                        <span class="badge bg-success">1 (Doanh thu)</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">0</span>
                    <?php endif; ?>
                </td>
                <td><code><?php echo $l['label_key']; ?></code></td>
                <td>
                    <?php if (!$l['is_system']): ?>
                        <button class="btn btn-sm btn-primary" onclick='openEditModal(<?php echo json_encode($l); ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?delete=<?php echo urlencode($l['label_key']); ?>" class="btn btn-sm btn-danger" onclick="return confirm('Xóa?')">
                            <i class="fas fa-trash"></i>
                        </a>
                    <?php else: ?>
                        <i class="fas fa-lock text-muted"></i>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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
                        <label>Tên nhãn *</label>
                        <input type="text" class="form-control" name="name" id="labelName" required>
                    </div>
                    
                    <div class="mb-3">
                        <label>Mô tả</label>
                        <textarea class="form-control" name="description" id="labelDesc"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label>Màu</label>
                            <input type="color" class="form-control" name="color" id="labelColor" value="#6c757d">
                        </div>
                        <div class="col-6 mb-3">
                            <label>Icon</label>
                            <input type="text" class="form-control" name="icon" id="labelIcon" value="fa-tag">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label>Thứ tự</label>
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
    document.getElementById('modalTitle').textContent = 'Thêm Nhãn';
    document.getElementById('formAction').value = 'add';
    document.getElementById('labelName').value = '';
    document.getElementById('labelDesc').value = '';
    document.getElementById('labelColor').value = '#6c757d';
    document.getElementById('labelIcon').value = 'fa-tag';
    document.getElementById('sortOrder').value = '0';
    modal.show();
}

function openEditModal(data) {
    document.getElementById('modalTitle').textContent = 'Sửa Nhãn';
    document.getElementById('formAction').value = 'edit';
    document.getElementById('keyToEdit').value = data.label_key;
    document.getElementById('labelName').value = data.label_name;
    document.getElementById('labelDesc').value = data.description || '';
    document.getElementById('labelColor').value = data.color;
    document.getElementById('labelIcon').value = data.icon;
    document.getElementById('sortOrder').value = data.sort_order;
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>