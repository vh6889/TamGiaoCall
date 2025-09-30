<?php
/**
 * Manager Assignments Page (Admin only)
 * Assign telesales to managers
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

require_admin();

$page_title = 'Phân công Manager quản lý Telesale';

// Get all managers
$managers = get_managers('active');

// Get all telesales
$telesales = get_telesales('active');

// Get current assignments
$assignments = db_get_results(
    "SELECT ma.*, m.full_name as manager_name, t.full_name as telesale_name
     FROM manager_assignments ma
     JOIN users m ON ma.manager_id = m.id
     JOIN users t ON ma.telesale_id = t.id
     ORDER BY m.full_name, t.full_name"
);

// Group assignments by manager
$assignments_by_manager = [];
foreach ($assignments as $assignment) {
    $manager_id = $assignment['manager_id'];
    if (!isset($assignments_by_manager[$manager_id])) {
        $assignments_by_manager[$manager_id] = [
            'manager_name' => $assignment['manager_name'],
            'telesales' => []
        ];
    }
    $assignments_by_manager[$manager_id]['telesales'][] = $assignment;
}

include 'includes/header.php';
?>

<div class="row g-4">
    <div class="col-md-8">
        <div class="table-card">
            <h5 class="mb-3"><i class="fas fa-sitemap me-2"></i>Phân công hiện tại</h5>
            
            <?php if (empty($managers)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Chưa có Manager nào trong hệ thống. Vui lòng tạo user với role Manager trước.
            </div>
            <?php elseif (empty($assignments_by_manager)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Chưa có phân công nào. Sử dụng form bên phải để phân công telesale cho manager.
            </div>
            <?php else: ?>
                <?php foreach ($assignments_by_manager as $manager_id => $data): ?>
                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <i class="fas fa-user-tie me-2"></i>
                        Manager: <strong><?php echo htmlspecialchars($data['manager_name']); ?></strong>
                        <span class="badge bg-light text-dark float-end">
                            <?php echo count($data['telesales']); ?> telesales
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($data['telesales'] as $assignment): ?>
                            <div class="col-md-6 mb-2">
                                <div class="d-flex justify-content-between align-items-center p-2 border rounded">
                                    <span>
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo htmlspecialchars($assignment['telesale_name']); ?>
                                    </span>
                                    <button class="btn btn-sm btn-danger btn-remove-assignment" 
                                            data-manager="<?php echo $manager_id; ?>"
                                            data-telesale="<?php echo $assignment['telesale_id']; ?>"
                                            title="Xóa phân công">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="table-card">
            <h5 class="mb-3"><i class="fas fa-user-plus me-2"></i>Thêm phân công mới</h5>
            
            <form id="assignmentForm">
                <div class="mb-3">
                    <label for="manager_id" class="form-label">Chọn Manager</label>
                    <select class="form-select" id="manager_id" name="manager_id" required>
                        <option value="">-- Chọn Manager --</option>
                        <?php foreach ($managers as $manager): ?>
                        <option value="<?php echo $manager['id']; ?>">
                            <?php echo htmlspecialchars($manager['full_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Chọn Telesales để phân công</label>
                    <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($telesales as $telesale): ?>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="telesale_ids[]" 
                                   value="<?php echo $telesale['id']; ?>" 
                                   id="telesale_<?php echo $telesale['id']; ?>">
                            <label class="form-check-label" for="telesale_<?php echo $telesale['id']; ?>">
                                <?php echo htmlspecialchars($telesale['full_name']); ?>
                                <?php
                                // Show if already assigned
                                $assigned_to = null;
                                foreach ($assignments as $a) {
                                    if ($a['telesale_id'] == $telesale['id']) {
                                        $assigned_to = $a['manager_name'];
                                        break;
                                    }
                                }
                                if ($assigned_to): ?>
                                <small class="text-muted">(Đang thuộc: <?php echo $assigned_to; ?>)</small>
                                <?php endif; ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-save me-2"></i>Lưu phân công
                </button>
            </form>
        </div>
        
        <div class="table-card mt-3">
            <h6 class="text-muted"><i class="fas fa-info-circle me-2"></i>Lưu ý</h6>
            <ul class="small">
                <li>Manager chỉ có thể xem và quản lý các telesale được phân công</li>
                <li>Manager có thể nhận đơn hàng khi telesale bàn giao</li>
                <li>Manager có thể disable telesale nhưng không thể enable lại</li>
                <li>Một telesale có thể được phân cho nhiều manager</li>
            </ul>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Submit assignment form
    $('#assignmentForm').submit(function(e) {
        e.preventDefault();
        
        const managerId = $('#manager_id').val();
        const telesaleIds = [];
        $('input[name="telesale_ids[]"]:checked').each(function() {
            telesaleIds.push($(this).val());
        });
        
        if (!managerId || telesaleIds.length === 0) {
            showToast('Vui lòng chọn Manager và ít nhất một Telesale', 'error');
            return;
        }
        
        $.ajax({
            url: 'api/assign-manager.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                manager_id: managerId,
                telesale_ids: telesaleIds
            }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã phân công thành công!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                showToast('Không thể kết nối máy chủ', 'error');
            }
        });
    });
    
    // Remove assignment
    $('.btn-remove-assignment').click(function() {
        const managerId = $(this).data('manager');
        const telesaleId = $(this).data('telesale');
        
        if (!confirm('Xác nhận xóa phân công này?')) {
            return;
        }
        
        $.ajax({
            url: 'api/remove-assignment.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({
                manager_id: managerId,
                telesale_id: telesaleId
            }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã xóa phân công', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                showToast('Không thể kết nối máy chủ', 'error');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>