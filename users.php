<?php
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';



require_admin();

$page_title = 'Quản lý nhân viên';

// Lấy tất cả users và đếm số đơn đang xử lý của họ
$users = db_get_results("
    SELECT u.*, COUNT(o.id) as pending_orders
    FROM users u
    LEFT JOIN orders o ON u.id = o.assigned_to AND o.status IN ('assigned', 'calling', 'callback')
    GROUP BY u.id
    ORDER BY u.created_at DESC
");

// Lấy danh sách telesale đang hoạt động để bàn giao
$active_telesales = get_telesales('active');

include 'includes/header.php';
?>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Họ tên</th>
                    <th>Vai trò</th>
                    <th>Đơn đang xử lý</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                    <td>
                        <?php if ($user['role'] === 'admin'): ?>
                            <span class="badge bg-danger">Admin</span>
                        <?php else: ?>
                            <span class="badge bg-info">Telesale</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge bg-warning"><?php echo $user['pending_orders']; ?></span></td>
                    <td>
                        <?php if ($user['status'] === 'active'): ?>
                            <span class="badge bg-success">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><?php echo ucfirst($user['status']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($user['id'] != get_current_user()['id']): ?>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-secondary btn-disable" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>" data-pending-orders="<?php echo $user['pending_orders']; ?>" title="Vô hiệu hóa & Bàn giao">
                                <i class="fas fa-user-lock"></i>
                            </button>
                            <button class="btn btn-danger btn-delete" data-user-id="<?php echo $user['id']; ?>" data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>" data-pending-orders="<?php echo $user['pending_orders']; ?>" title="Xóa & Bàn giao">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="handoverModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="handoverModalTitle">Bàn giao công việc</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="handoverUserId">
                <input type="hidden" id="handoverAction">
                
                <p>Bạn sắp <strong id="actionText"></strong> tài khoản của <strong id="userNameText"></strong>.</p>
                <p>Nhân viên này đang có <strong id="pendingOrdersText" class="text-danger"></strong> đơn hàng cần xử lý. Vui lòng chọn phương án bàn giao:</p>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="handover_option" id="optionReclaim" value="reclaim" checked>
                    <label class="form-check-label" for="optionReclaim">
                        <strong>Trả về kho đơn mới</strong><br>
                        <small class="text-muted">Các đơn hàng sẽ trở về trạng thái "Mới" để người khác nhận.</small>
                    </label>
                </div>
                <hr>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="handover_option" id="optionTransfer" value="transfer">
                    <label class="form-check-label" for="optionTransfer">
                        <strong>Bàn giao cho nhân viên khác</strong>
                    </label>
                    <select class="form-select form-select-sm mt-2" id="transferToUser" disabled>
                        <option value="">-- Chọn nhân viên nhận bàn giao --</option>
                        <?php foreach($active_telesales as $ts): ?>
                        <option value="<?php echo $ts['id']; ?>"><?php echo htmlspecialchars($ts['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="btnConfirmHandover">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const handoverModal = new bootstrap.Modal(document.getElementById('handoverModal'));

    // Event listener for showing the modal
    $('#handoverModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const userId = button.data('user-id');
        const userName = button.data('user-name');
        const pendingOrders = button.data('pending-orders');
        const action = button.hasClass('btn-delete') ? 'delete' : 'disable';
        
        const modal = $(this);
        modal.find('#handoverUserId').val(userId);
        modal.find('#handoverAction').val(action);
        modal.find('#userNameText').text(userName);
        modal.find('#pendingOrdersText').text(pendingOrders);
        modal.find('#actionText').text(action === 'delete' ? 'xóa' : 'vô hiệu hóa');
        
        // Disable handover options if user has no pending orders
        if (pendingOrders == 0) {
            modal.find('input[name="handover_option"]').prop('disabled', true);
        } else {
            modal.find('input[name="handover_option"]').prop('disabled', false);
        }
    });

    // Toggle dropdown based on radio button
    $('input[name="handover_option"]').change(function() {
        if ($(this).val() === 'transfer') {
            $('#transferToUser').prop('disabled', false);
        } else {
            $('#transferToUser').prop('disabled', true);
        }
    });

    // Handle final confirmation
    $('#btnConfirmHandover').click(function() {
        const btn = $(this);
        const userId = $('#handoverUserId').val();
        const action = $('#handoverAction').val();
        const handoverOption = $('input[name="handover_option"]:checked').val();
        const targetUserId = $('#transferToUser').val();

        if (handoverOption === 'transfer' && !targetUserId) {
            showToast('Vui lòng chọn nhân viên để bàn giao.', 'error');
            return;
        }

        const apiUrl = (action === 'delete') ? 'api/delete-user.php' : 'api/disable-user.php';
        const postData = {
            user_id: userId,
            handover_option: handoverOption,
            target_user_id: targetUserId
        };

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: apiUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(postData),
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    handoverModal.hide();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('Có lỗi kết nối máy chủ.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('Xác nhận');
            }
        });
    });

    // Re-purpose buttons to open modal instead of direct action
    $('.btn-delete, .btn-disable').click(function() {
        const userId = $(this).data('user-id');
        // Prevent trying to select the user being actioned on in the transfer list
        $('#transferToUser option').show();
        $('#transferToUser option[value="' + userId + '"]').hide();
        handoverModal.show($(this));
    });
});
</script>

<?php include 'includes/footer.php'; ?>