<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_admin();
$page_title = 'Quản Lý Trạng Thái Đơn Hàng';

$configs = get_order_status_configs();  // Load từ DB

// Xử lý POST cho add/edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status_key = sanitize($_POST['status_key'] ?? '');
    $label = sanitize($_POST['label'] ?? '');
    $color = sanitize($_POST['color'] ?? '');
    $icon = sanitize($_POST['icon'] ?? '');
    
    // Build logic từ form (chỉ add nếu checked/set)
    $logic = [];
    if (isset($_POST['callback_delay_minutes']) && $_POST['callback_delay_minutes'] > 0) {
        $logic['callback_delay_minutes'] = (int)$_POST['callback_delay_minutes'];
    }
    if (isset($_POST['remind_before_minutes']) && $_POST['remind_before_minutes'] > 0) {
        $logic['remind_before_minutes'] = (int)$_POST['remind_before_minutes'];
    }
    if (isset($_POST['max_attempts']) && $_POST['max_attempts'] > 0) {
        $logic['max_attempts'] = (int)$_POST['max_attempts'];
    }
    if (isset($_POST['require_note'])) $logic['require_note'] = true;
    if (isset($_POST['auto_lock_on_overdue'])) $logic['auto_lock_on_overdue'] = true;
    // Add more logic fields here if needed (e.g., hen_goi_lai_minutes for "dang_ban")

    $logic_json = json_encode($logic);

    if (empty($status_key) || empty($label)) {
        set_flash('error', 'Key và Label bắt buộc.');
    } else {
        $exists = db_get_var("SELECT COUNT(*) FROM order_status_configs WHERE status_key = ?", [$status_key]);
        if ($exists) {
            db_update('order_status_configs', [
                'label' => $label,
                'color' => $color,
                'icon' => $icon,
                'logic_json' => $logic_json,
                'created_by' => get_logged_user()['id']
            ], 'status_key = ?', [$status_key]);
            log_activity('update_status_config', "Updated config for $status_key");
            set_flash('success', 'Cập nhật thành công!');
        } else {
            db_insert('order_status_configs', [
                'status_key' => $status_key,
                'label' => $label,
                'color' => $color,
                'icon' => $icon,
                'logic_json' => $logic_json,
                'created_by' => get_logged_user()['id']
            ]);
            log_activity('add_status_config', "Added new status $status_key");
            set_flash('success', 'Thêm mới thành công!');
        }
    }
    redirect('admin-status-config.php');
}

include 'includes/header.php';
?>

<div class="table-card">
    <div class="d-flex justify-content-between mb-3">
        <h5><i class="fas fa-cog me-2"></i>Quản Lý Trạng Thái Đơn Hàng</h5>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#statusModal" data-mode="add">
            <i class="fas fa-plus me-2"></i>Thêm Trạng Thái Mới
        </button>
    </div>
    
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Label</th>
                    <th>Color</th>
                    <th>Icon</th>
                    <th>Logic</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($configs)): ?>
                    <tr><td colspan="6" class="text-center">Chưa có trạng thái nào. Hãy thêm mới.</td></tr>
                <?php else: ?>
                    <?php foreach ($configs as $key => $conf): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($key); ?></td>
                        <td><?php echo htmlspecialchars($conf['label']); ?></td>
                        <td><span class="badge bg-<?php echo htmlspecialchars($conf['color']); ?>">Mẫu</span></td>
                        <td><i class="fas <?php echo htmlspecialchars($conf['icon']); ?>"></i></td>
                        <td><?php echo htmlspecialchars(json_encode($conf['logic'], JSON_PRETTY_PRINT)); ?></td>  <!-- Hiển thị JSON pretty -->
                        <td>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal" 
                                    data-mode="edit" data-key="<?php echo htmlspecialchars($key); ?>" 
                                    data-label="<?php echo htmlspecialchars($conf['label']); ?>"
                                    data-color="<?php echo htmlspecialchars($conf['color']); ?>"
                                    data-icon="<?php echo htmlspecialchars($conf['icon']); ?>"
                                    data-logic='<?php echo json_encode($conf['logic']); ?>'>
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-danger btn-sm btn-delete" data-key="<?php echo htmlspecialchars($key); ?>">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal cho Add/Edit -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Thêm/Chỉnh Sửa Trạng Thái</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm" method="POST">
                    <input type="hidden" name="status_key" id="status_key">  <!-- For edit -->
                    <div class="mb-3">
                        <label>Key (không dấu, unique):</label>
                        <input type="text" class="form-control" name="status_key" id="key_input" required>
                    </div>
                    <div class="mb-3">
                        <label>Label:</label>
                        <input type="text" class="form-control" name="label" required>
                    </div>
                    <div class="mb-3">
                        <label>Color (Bootstrap class, e.g., success, warning):</label>
                        <input type="text" class="form-control" name="color" required>
                    </div>
                    <div class="mb-3">
                        <label>Icon (FontAwesome class, e.g., fa-check):</label>
                        <input type="text" class="form-control" name="icon" required>
                    </div>
                    <h6>Logic (tùy chọn):</h6>
                    <div class="mb-3">
                        <label>Callback Delay (minutes):</label>
                        <input type="number" class="form-control" name="callback_delay_minutes" min="0">
                    </div>
                    <div class="mb-3">
                        <label>Remind Before (minutes):</label>
                        <input type="number" class="form-control" name="remind_before_minutes" min="0">
                    </div>
                    <div class="mb-3">
                        <label>Max Attempts:</label>
                        <input type="number" class="form-control" name="max_attempts" min="0">
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="require_note">
                        <label>Require Note</label>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="auto_lock_on_overdue">
                        <label>Auto Lock on Overdue</label>
                    </div>
                    <!-- Add more inputs for other logic fields here, e.g., hen_goi_lai_minutes -->
                    <button type="submit" class="btn btn-primary">Lưu</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Modal show event: Fill data for edit
    $('#statusModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const mode = button.data('mode');
        const modal = $(this);
        
        if (mode === 'edit') {
            modal.find('#status_key').val(button.data('key'));
            modal.find('#key_input').val(button.data('key')).prop('readonly', true);  // Key không edit
            modal.find('input[name="label"]').val(button.data('label'));
            modal.find('input[name="color"]').val(button.data('color'));
            modal.find('input[name="icon"]').val(button.data('icon'));
            const logic = button.data('logic') || {};
            modal.find('input[name="callback_delay_minutes"]').val(logic.callback_delay_minutes || '');
            modal.find('input[name="remind_before_minutes"]').val(logic.remind_before_minutes || '');
            modal.find('input[name="max_attempts"]').val(logic.max_attempts || '');
            modal.find('input[name="require_note"]').prop('checked', !!logic.require_note);
            modal.find('input[name="auto_lock_on_overdue"]').prop('checked', !!logic.auto_lock_on_overdue);
            // Add more for other fields
        } else {  // Add mode
            modal.find('form')[0].reset();
            modal.find('#key_input').prop('readonly', false);
        }
    });

    // Delete button (AJAX)
    $('.btn-delete').click(function() {
        const key = $(this).data('key');
        if (confirm('Xóa trạng thái ' + key + '?')) {
            $.ajax({
                url: 'api/delete-status-config.php',  // Tạo file này ở bước sau
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ status_key: key }),
                success: function(response) {
                    if (response.success) {
                        location.reload();  // Reload để update table
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Error deleting.');
                }
            });
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>