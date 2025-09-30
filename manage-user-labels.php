<?php
/**
 * Trang Quản lý Nhãn Nhân viên
 * Cho phép Admin tạo, sửa, xóa các nhãn phân loại (Yếu, Trung bình, Giỏi...)
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_admin();

$page_title = 'Quản Lý Nhãn Nhân Viên';

// Hàm helper để tạo mã định danh (đã có trong các file trước)
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) { return 'n-a'; }
    return $text;
}

// === XỬ LÝ FORM SUBMIT (THÊM / SỬA) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    $name = sanitize($_POST['name'] ?? '');
    $color = sanitize($_POST['color'] ?? '#cccccc');
    $description = sanitize($_POST['description'] ?? '');
    
    if (empty($name)) {
        set_flash('error', 'Tên nhãn là bắt buộc.');
    } else {
        if ($_POST['form_action'] === 'add') {
            $key = slugify($name);
            $exists = db_get_var("SELECT COUNT(*) FROM user_labels WHERE label_key = ?", [$key]);
            if ($exists > 0) {
                $key = $key . '-' . time();
            }
            db_insert('user_labels', [
                'label_key' => $key,
                'label_name' => $name,
                'color' => $color,
                'description' => $description
            ]);
            set_flash('success', 'Đã thêm nhãn nhân viên mới!');

        } elseif ($_POST['form_action'] === 'edit') {
            $key_to_edit = sanitize($_POST['key_to_edit']);
            db_update('user_labels', 
                ['label_name' => $name, 'color' => $color, 'description' => $description], 
                'label_key = ?', [$key_to_edit]
            );
            set_flash('success', 'Đã cập nhật nhãn nhân viên!');
        }
    }
    redirect('manage-user-labels.php');
}

// Lấy danh sách nhãn để hiển thị
$labels = db_get_results("SELECT * FROM user_labels ORDER BY label_name ASC");

include 'includes/header.php';
?>

<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-user-shield me-2"></i>Quản lý Nhãn Phân loại Nhân viên</h5>
        <button class="btn btn-primary" onclick="prepareAddModal()">
            <i class="fas fa-plus me-2"></i> Thêm Nhãn mới
        </button>
    </div>

    <?php display_flash(); ?>

    <table class="table table-hover align-middle">
        <thead>
            <tr>
                <th>Tên Nhãn</th>
                <th>Màu sắc</th>
                <th>Mô tả</th>
                <th>Mã Định danh</th>
                <th style="width: 150px;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($labels)): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Chưa có nhãn nhân viên nào được tạo.</td></tr>
            <?php else: ?>
                <?php foreach ($labels as $label): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($label['label_name']); ?></strong></td>
                    <td>
                        <span class="badge" style="background-color: <?php echo htmlspecialchars($label['color']); ?>; color: #fff; text-shadow: 1px 1px 1px rgba(0,0,0,0.3); font-size: 0.9em;">
                            <?php echo htmlspecialchars($label['label_name']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($label['description']); ?></td>
                    <td><code><?php echo htmlspecialchars($label['label_key']); ?></code></td>
                    <td>
                        <button class="btn btn-sm btn-primary" 
                                onclick="prepareEditModal(
                                    '<?php echo htmlspecialchars($label['label_key'], ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($label['label_name'], ENT_QUOTES); ?>', 
                                    '<?php echo htmlspecialchars($label['color'], ENT_QUOTES); ?>',
                                    '<?php echo htmlspecialchars($label['description'], ENT_QUOTES); ?>'
                                )">
                            <i class="fas fa-edit"></i> Sửa
                        </button>
                        </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="labelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="labelForm" method="POST" action="manage-user-labels.php">
                <div class="modal-body">
                    <input type="hidden" name="form_action" id="form_action">
                    <input type="hidden" name="key_to_edit" id="key_to_edit">
                    
                    <div class="row">
                        <div class="col-8 mb-3">
                            <label for="name" class="form-label">Tên Nhãn (ví dụ: Nhân viên Giỏi)</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-4 mb-3">
                            <label for="color" class="form-label">Màu sắc</label>
                            <input type="color" class="form-control form-control-color w-100" id="color" name="color" value="#198754">
                        </div>
                    </div>
                     <div class="mb-3">
                        <label for="description" class="form-label">Mô tả</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Ví dụ: Dành cho nhân viên có tỷ lệ chốt đơn > 30%"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="submit" class="btn btn-primary">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const labelModal = new bootstrap.Modal(document.getElementById('labelModal'));
const modalTitle = document.getElementById('modalTitle');
const labelForm = document.getElementById('labelForm');

function prepareAddModal() {
    labelForm.reset();
    modalTitle.innerText = 'Thêm Nhãn Nhân viên mới';
    labelForm.querySelector('#form_action').value = 'add';
    labelForm.querySelector('#color').value = '#198754';
    labelModal.show();
}

function prepareEditModal(key, name, color, description) {
    labelForm.reset();
    modalTitle.innerText = 'Sửa Nhãn: ' + name;
    labelForm.querySelector('#form_action').value = 'edit';
    labelForm.querySelector('#key_to_edit').value = key;
    labelForm.querySelector('#name').value = name;
    labelForm.querySelector('#color').value = color;
    labelForm.querySelector('#description').value = description;
    labelModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>