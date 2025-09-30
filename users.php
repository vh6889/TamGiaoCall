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
    <div class="d-flex justify-content-end mb-3">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="fas fa-user-plus me-2"></i> Tạo nhân viên mới
        </button>
    </div>
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
                        <?php if ($user['id'] != get_logged_user()['id']): ?>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-primary btn-edit" 
                                    data-bs-toggle="modal" data-bs-target="#editUserModal"
                                    data-user-id="<?php echo $user['id']; ?>"
                                    data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                    data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                    data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                    data-phone="<?php echo htmlspecialchars($user['phone']); ?>"
                                    data-role="<?php echo $user['role']; ?>"
                                    data-status="<?php echo $user['status']; ?>"
                                    title="Sửa thông tin">
                                <i class="fas fa-edit"></i>
                            </button>
                            <?php if ($user['status'] === 'active'): ?>
                            <button class="btn btn-secondary btn-disable" 
                                    data-bs-toggle="modal" data-bs-target="#handoverModal"
                                    data-user-id="<?php echo $user['id']; ?>" 
                                    data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                    data-pending-orders="<?php echo $user['pending_orders']; ?>" 
                                    title="Vô hiệu hóa & Bàn giao">
                                <i class="fas fa-user-lock"></i>
                            </button>
                            <?php else: ?>
                            <button class="btn btn-success btn-enable" 
                                    data-user-id="<?php echo $user['id']; ?>" 
                                    title="Kích hoạt lại">
                                <i class="fas fa-user-check"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-delete" 
                                    data-bs-toggle="modal" data-bs-target="#handoverModal"
                                    data-user-id="<?php echo $user['id']; ?>" 
                                    data-user-name="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                    data-pending-orders="<?php echo $user['pending_orders']; ?>" 
                                    title="Xóa & Bàn giao">
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

<!-- Modal Bàn Giao (Handover) - Giữ nguyên -->
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

<!-- Modal Tạo Nhân Viên Mới - Giữ nguyên -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Tạo Nhân Viên Mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createUserForm">
                    <div class="mb-3">
                        <label for="new_username" class="form-label">Username</label>
                        <input type="text" class="form-control" id="new_username" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">Password</label>
                        <input type="password" class="form-control" id="new_password" name="password" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="form-text text-muted">Mật khẩu phải có ít nhất <?php echo PASSWORD_MIN_LENGTH; ?> ký tự.</small>
                    </div>
                    <div class="mb-3">
                        <label for="new_full_name" class="form-label">Họ tên</label>
                        <input type="text" class="form-control" id="new_full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="new_email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="new_phone" class="form-label">Số điện thoại</label>
                        <input type="text" class="form-control" id="new_phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="new_role" class="form-label">Vai trò</label>
                        <select class="form-select" id="new_role" name="role" required>
                            <option value="telesale">Telesale</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="btnCreateUser">Tạo mới</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Sửa Nhân Viên -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Sửa Thông Tin Nhân Viên</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="edit_user_id" name="user_id">
                    <div class="mb-3">
                        <label for="edit_username" class="form-label">Username (Không thể sửa)</label>
                        <input type="text" class="form-control" id="edit_username" disabled>
                    </div>
                    <div class="mb-3">
                        <label for="edit_password" class="form-label">Password Mới (Để trống nếu không đổi)</label>
                        <input type="password" class="form-control" id="edit_password" name="password" minlength="<?php echo PASSWORD_MIN_LENGTH; ?>">
                        <small class="form-text text-muted">Mật khẩu phải có ít nhất <?php echo PASSWORD_MIN_LENGTH; ?> ký tự nếu đổi.</small>
                    </div>
                    <div class="mb-3">
                        <label for="edit_full_name" class="form-label">Họ tên</label>
                        <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="edit_email" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="edit_phone" class="form-label">Số điện thoại</label>
                        <input type="text" class="form-control" id="edit_phone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="edit_role" class="form-label">Vai trò</label>
                        <select class="form-select" id="edit_role" name="role" required>
                            <option value="telesale">Telesale</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Trạng thái</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="btnUpdateUser">Lưu thay đổi</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Event cho modal handover
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
        
        // Disable handover options if no pending orders
        if (pendingOrders == 0) {
            modal.find('input[name="handover_option"]').prop('disabled', true);
        } else {
            modal.find('input[name="handover_option"]').prop('disabled', false);
        }

        // Hide option của chính user trong transfer list
        $('#transferToUser option').show();
        $('#transferToUser option[value="' + userId + '"]').hide();
    });

    // Toggle dropdown based on radio button
    $('input[name="handover_option"]').change(function() {
        if ($(this).val() === 'transfer') {
            $('#transferToUser').prop('disabled', false);
        } else {
            $('#transferToUser').prop('disabled', true);
        }
    });

    // Xác nhận handover
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
                    $('#handoverModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message, 'error');
                }
            },
            error: function() {
                showToast('Có lỗi xảy ra.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('Xác nhận');
            }
        });
    });

    // Create User
    $('#btnCreateUser').click(function() {
        const btn = $(this);
        const form = $('#createUserForm');
        
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        const formData = form.serializeArray();
        let postData = {};
        formData.forEach(item => {
            postData[item.name] = item.value;
        });

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang tạo...');

        $.ajax({
            url: 'api/create-user.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(postData),
            success: function(response) {
                if (response.success) {
                    showToast('Đã tạo nhân viên thành công!', 'success');
                    $('#createUserModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                showToast('Có lỗi kết nối máy chủ.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('Tạo mới');
            }
        });
    });

    // Edit User
    $('#editUserModal').on('show.bs.modal', function (event) {
        const button = $(event.relatedTarget);
        const userId = button.data('user-id');
        const username = button.data('username');
        const fullName = button.data('full-name');
        const email = button.data('email');
        const phone = button.data('phone');
        const role = button.data('role');
        const status = button.data('status');

        const modal = $(this);
        modal.find('#edit_user_id').val(userId);
        modal.find('#edit_username').val(username);
        modal.find('#edit_full_name').val(fullName);
        modal.find('#edit_email').val(email);
        modal.find('#edit_phone').val(phone);
        modal.find('#edit_role').val(role);
        modal.find('#edit_status').val(status);
    });

    $('#btnUpdateUser').click(function() {
        const btn = $(this);
        const form = $('#editUserForm');
        
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        const formData = form.serializeArray();
        let postData = {};
        formData.forEach(item => {
            postData[item.name] = item.value;
        });

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Đang lưu...');

        $.ajax({
            url: 'api/update-user.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(postData),
            success: function(response) {
                if (response.success) {
                    showToast('Đã cập nhật thành công!', 'success');
                    $('#editUserModal').modal('hide');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                showToast('Có lỗi kết nối máy chủ.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).text('Lưu thay đổi');
            }
        });
    });

    // Enable User (Mới Thêm)
    $('.btn-enable').click(function() {
        const btn = $(this);
        const userId = btn.data('user-id');

        if (!confirm('Bạn có chắc chắn muốn kích hoạt lại tài khoản này?')) {
            return;
        }

        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: 'api/update-user.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ user_id: userId, status: 'active' }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã kích hoạt tài khoản thành công!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                showToast('Có lỗi kết nối máy chủ.', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-user-check"></i>');
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>