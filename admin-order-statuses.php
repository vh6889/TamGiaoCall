<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_admin();

$page_title = 'Quản Lý Trạng Thái Đơn Hàng';

// Hàm helper để tạo mã định danh (bạn có thể đặt trong functions.php)
function slugify($text) {
    // ... (giữ nguyên hàm slugify)
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    if (empty($text)) { return 'n-a'; }
    return $text;
}

// Xử lý form POST để Thêm/Sửa (giữ nguyên)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action'])) {
    // ... (toàn bộ logic PHP ở đầu file giữ nguyên)
    $name = sanitize($_POST['name'] ?? '');
    $color = sanitize($_POST['color'] ?? '#cccccc');
    $sort_order = (int)($_POST['sort_order'] ?? 0);
    
    if (empty($name)) {
        set_flash('error', 'Tên trạng thái là bắt buộc.');
    } else {
        if ($_POST['form_action'] === 'add') {
            $key = slugify($name);
            $exists = db_get_var("SELECT COUNT(*) FROM order_status_configs WHERE status_key = ?", [$key]);
            if ($exists > 0) {
                $key = $key . '-' . time();
            }

            db_insert('order_status_configs', [
                'status_key' => $key,
                'label' => $name,
                'color' => $color,
                'sort_order' => $sort_order,
                'icon' => 'fa-tag'
            ]);
            set_flash('success', 'Đã thêm trạng thái mới!');

        } elseif ($_POST['form_action'] === 'edit') {
            $key_to_edit = sanitize($_POST['key_to_edit']);
            db_update('order_status_configs', 
                ['label' => $name, 'color' => $color, 'sort_order' => $sort_order], 
                'status_key = ?', [$key_to_edit]
            );
            set_flash('success', 'Đã cập nhật trạng thái!');
        }
    }
    redirect('admin-order-statuses.php');
}

$statuses = db_get_results("SELECT * FROM order_status_configs ORDER BY sort_order ASC, label ASC");

include 'includes/header.php';
?>

<div class="table-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5><i class="fas fa-tags me-2"></i>Trạng thái Đơn hàng</h5>
        <button class="btn btn-primary" onclick="prepareAddModal()">
            <i class="fas fa-plus me-2"></i> Thêm mới
        </button>
    </div>

    <table class="table table-hover">
        <thead>
            <tr>
                <th>STT</th>
                <th>Tên Trạng thái</th>
                <th>Màu sắc</th>
                <th>Mã Định danh</th>
                <th>Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($statuses as $status): ?>
            <tr>
                <td><?php echo $status['sort_order']; ?></td>
                <td><strong><?php echo htmlspecialchars($status['label']); ?></strong></td>
                <td>
                    <span class="badge" style="background-color: <?php echo htmlspecialchars($status['color']); ?>; color: #fff; text-shadow: 1px 1px 1px rgba(0,0,0,0.3);">
                        <?php echo htmlspecialchars($status['label']); ?>
                    </span>
                </td>
                <td><code><?php echo htmlspecialchars($status['status_key']); ?></code></td>
                <td>
                    <button class="btn btn-sm btn-primary" 
                            onclick="prepareEditModal(
                                '<?php echo htmlspecialchars($status['status_key'], ENT_QUOTES); ?>', 
                                '<?php echo htmlspecialchars($status['label'], ENT_QUOTES); ?>', 
                                '<?php echo htmlspecialchars($status['color'], ENT_QUOTES); ?>',
                                '<?php echo $status['sort_order']; ?>'
                            )">
                        <i class="fas fa-edit"></i> Sửa
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="statusForm" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="form_action" id="form_action">
                    <input type="hidden" name="key_to_edit" id="key_to_edit">
                    
                    <div class="mb-3">
                        <label for="name" class="form-label">Tên Trạng thái</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="sort_order" class="form-label">Số thứ tự</label>
                            <input type="number" class="form-control" id="sort_order" name="sort_order" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="color" class="form-label">Màu sắc</label>
                            <input type="color" class="form-control form-control-color" id="color" name="color" value="#cccccc">
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
// Giữ nguyên JavaScript
const statusModal = new bootstrap.Modal(document.getElementById('statusModal'));
const modalTitle = document.getElementById('modalTitle');
const statusForm = document.getElementById('statusForm');

function prepareAddModal() {
    statusForm.reset();
    document.getElementById('color').value = '#cccccc'; // Reset màu mặc định
    modalTitle.innerText = 'Thêm Trạng thái mới';
    statusForm.querySelector('#form_action').value = 'add';
    statusModal.show();
}

function prepareEditModal(key, name, color, sort_order) {
    statusForm.reset();
    modalTitle.innerText = 'Sửa Trạng thái: ' + name;
    statusForm.querySelector('#form_action').value = 'edit';
    statusForm.querySelector('#key_to_edit').value = key;
    statusForm.querySelector('#name').value = name;
    statusForm.querySelector('#color').value = color;
    statusForm.querySelector('#sort_order').value = sort_order;
    statusModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>