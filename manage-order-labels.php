<?php
/**
 * Quản lý Nhãn Đơn hàng (ORDER LABELS)
 * Admin tạo/sửa/xóa các nhãn động
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_admin();

$page_title = 'Quản Lý Nhãn Đơn Hàng';

/**
 * Slugify - Tạo mã định danh
 */
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return empty($text) ? 'n-a' : $text;
}

// XỬ LÝ FORM SUBMIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $color = sanitize($_POST['color'] ?? '#6c757d');
    $icon = sanitize($_POST['icon'] ?? 'fa-tag');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    $is_final = isset($_POST['is_final']) ? 1 : 0;
    
    if (empty($name)) {
        set_flash('error', 'Tên nhãn là bắt buộc.');
    } else {
        if ($_POST['form_action'] === 'add') {
            $key = slugify($name);
            
            // Kiểm tra trùng
            $exists = db_get_var("SELECT COUNT(*) FROM order_labels WHERE label_key = ?", [$key]);
            if ($exists > 0) {
                $key = $key . '-' . time();
            }

            db_insert('order_labels', [
                'label_key' => $key,
                'label_name' => $name,
                'description' => $description,
                'color' => $color,
                'icon' => $icon,
                'sort_order' => $sort_order,
                'is_final' => $is_final,
                'is_system' => 0,
                'created_by' => get_logged_user()['id']
            ]);
            
            log_activity('create_label', "Created label: {$name}");
            set_flash('success', 'Đã thêm nhãn mới thành công!');

        } elseif ($_POST['form_action'] === 'edit') {
            $key_to_edit = sanitize($_POST['key_to_edit']);
            
            // Không cho sửa nhãn hệ thống
            $is_system = db_get_var("SELECT is_system FROM order_labels WHERE label_key = ?", [$key_to_edit]);
            if ($is_system) {
                set_flash('error', 'Không thể sửa nhãn hệ thống!');
            } else {
                db_update('order_labels', [
                    'label_name' => $name,
                    'description' => $description,
                    'color' => $color,
                    'icon' => $icon,
                    'sort_order' => $sort_order,
                    'is_final' => $is_final
                ], 'label_key = ?', [$key_to_edit]);
                
                log_activity('update_label', "Updated label: {$name}");
                set_flash('success', 'Đã cập nhật nhãn!');
            }
        }
    }
    redirect('manage-order-labels.php');
}

// XỬ LÝ XÓA
if (isset($_GET['delete'])) {
    $key_to_delete = sanitize($_GET['delete']);
    
    // Kiểm tra nhãn hệ thống
    $is_system = db_get_var("SELECT is_system FROM order_labels WHERE label_key = ?", [$key_to_delete]);
    if ($is_system) {
        set_flash('error', 'Không thể xóa nhãn hệ thống!');
    } else {
        // Kiểm tra đơn đang dùng
        $count = db_get_var("SELECT COUNT(*) FROM orders WHERE primary_label = ?", [$key_to_delete]);
        if ($count > 0) {
            set_flash('error', "Không thể xóa nhãn đang được sử dụng bởi {$count} đơn hàng.");
        } else {
            db_delete('order_labels', 'label_key = ?', [$key_to_delete]);
            log_activity('delete_label', "Deleted label: {$key_to_delete}");
            set_flash('success', 'Đã xóa nhãn!');
        }
    }
    redirect('manage-order-labels.php');
}

// Lấy danh sách nhãn
$labels = get_order_labels(true); // Include system labels

include 'includes/header.php';
?>

<style>
.label-badge {
    padding: 6px 12px;
    border-radius: 4px;
    color: #fff;
    font-weight: 500;
    text-shadow: 1px 1px 1px rgba(0,0,0,0.3);
    display: inline-block;
}
.badge-final {
    background: #28a745;
    color: #fff;
    font-size: 0.75rem;
    margin-left: 5px;
}
.badge-system {
    background: #6c757d;
    color: #fff;
    font-size: 0.75rem;
    margin-left: 5px;
}
</style>

<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-tags me-2"></i>Quản lý Nhãn Đơn hàng</h5>
        <button class="btn btn-primary" onclick="prepareAddModal()">
            <i class="fas fa-plus me-2"></i> Thêm Nhãn Mới
        </button>
    </div>

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Logic mới:</strong> Đơn hàng chỉ có 2 trạng thái hệ thống: <code>free</code> (chưa nhận) và <code>assigned</code> (đã nhận). 
        Các "nhãn" này là để phân loại nghiệp vụ, không ảnh hưởng logic hệ thống.
    </div>

    <?php display_flash(); ?>

    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th style="width: 60px;">STT</th>
                <th>Tên Nhãn</th>
                <th>Màu sắc</th>
                <th style="width: 80px;">Loại</th>
                <th style="width: 120px;">Mã</th>
                <th style="width: 150px;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($labels)): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">Chưa có nhãn nào.</td></tr>
            <?php else: ?>
                <?php foreach ($labels as $label): ?>
                <tr>
                    <td><?php echo $label['sort_order']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($label['label_name']); ?></strong>
                        <?php if ($label['is_final']): ?>
                            <span class="badge-final">FINAL</span>
                        <?php endif; ?>
                        <?php if ($label['is_system']): ?>
                            <span class="badge-system">HỆ THỐNG</span>
                        <?php endif; ?>
                        <?php if ($label['description']): ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($label['description']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="label-badge" style="background-color: <?php echo htmlspecialchars($label['color']); ?>;">
                            <i class="<?php echo htmlspecialchars($label['icon']); ?> me-1"></i>
                            <?php echo htmlspecialchars($label['label_name']); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($label['is_final']): ?>
                            <span class="badge bg-success">Kết thúc</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Thường</span>
                        <?php endif; ?>
                    </td>
                    <td><code><?php echo htmlspecialchars($label['label_key']); ?></code></td>
                    <td>
                        <?php if (!$label['is_system']): ?>
                            <button class="btn btn-sm btn-primary" 
                                    onclick="prepareEditModal(
                                        '<?php echo htmlspecialchars($label['label_key'], ENT_QUOTES); ?>', 
                                        '<?php echo htmlspecialchars($label['label_name'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($label['description'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($label['color'], ENT_QUOTES); ?>',
                                        '<?php echo htmlspecialchars($label['icon'], ENT_QUOTES); ?>',
                                        '<?php echo $label['sort_order']; ?>',
                                        <?php echo $label['is_final'] ? 'true' : 'false'; ?>
                                    )">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?php echo urlencode($label['label_key']); ?>" 
                               class="btn btn-sm btn-danger" 
                               onclick="return confirm('Xóa nhãn này?')">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php else: ?>
                            <span class="text-muted"><i class="fas fa-lock"></i> Hệ thống</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal Add/Edit -->
<div class="modal fade" id="labelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Thêm Nhãn Mới</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="form_action" id="form_action" value="add">
                    <input type="hidden" name="key_to_edit" id="key_to_edit" value="">
                    
                    <div class="mb-3">
                        <label class="form-label">Tên nhãn <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" id="label_name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Mô tả</label>
                        <textarea class="form-control" name="description" id="label_description" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Màu sắc</label>
                            <input type="color" class="form-control form-control-color" name="color" id="label_color" value="#6c757d">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Icon (FontAwesome)</label>
                            <input type="text" class="form-control" name="icon" id="label_icon" value="fa-tag" placeholder="fa-tag">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Thứ tự hiển thị</label>
                            <input type="number" class="form-control" name="sort_order" id="sort_order" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" name="is_final" id="is_final">
                                <label class="form-check-label" for="is_final">
                                    <strong>Nhãn kết thúc</strong><br>
                                    <small class="text-muted">Tự động khóa đơn khi gán nhãn này</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const labelModal = new bootstrap.Modal(document.getElementById('labelModal'));

function prepareAddModal() {
    document.getElementById('modalTitle').textContent = 'Thêm Nhãn Mới';
    document.getElementById('form_action').value = 'add';
    document.getElementById('key_to_edit').value = '';
    document.getElementById('label_name').value = '';
    document.getElementById('label_description').value = '';
    document.getElementById('label_color').value = '#6c757d';
    document.getElementById('label_icon').value = 'fa-tag';
    document.getElementById('sort_order').value = '0';
    document.getElementById('is_final').checked = false;
    labelModal.show();
}

function prepareEditModal(key, name, description, color, icon, sortOrder, isFinal) {
    document.getElementById('modalTitle').textContent = 'Sửa Nhãn';
    document.getElementById('form_action').value = 'edit';
    document.getElementById('key_to_edit').value = key;
    document.getElementById('label_name').value = name;
    document.getElementById('label_description').value = description;
    document.getElementById('label_color').value = color;
    document.getElementById('label_icon').value = icon;
    document.getElementById('sort_order').value = sortOrder;
    document.getElementById('is_final').checked = isFinal;
    labelModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>