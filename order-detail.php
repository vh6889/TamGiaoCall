<?php
/**
 * Order Detail Page
 */
define('TSM_ACCESS', true);


require_login();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    redirect('orders.php');
}

$order = get_order($order_id);

if (!$order) {
    set_flash('error', 'Không tìm thấy đơn hàng');
    redirect('orders.php');
}

// Check permission
$current_user = get_current_user();
if (!is_admin() && $order['assigned_to'] != $current_user['id'] && $order['status'] !== 'new') {
    set_flash('error', 'Bạn không có quyền xem đơn hàng này');
    redirect('orders.php');
}

// Get order notes
$notes = get_order_notes($order_id);

// Parse products JSON
$products = json_decode($order['products'], true) ?? [];

// === PHẦN BỔ SUNG (1): Lấy danh sách telesale cho modal chuyển giao ===
$telesales_list = is_admin() ? get_telesales('active') : [];
// ====================================================================

$page_title = 'Chi tiết đơn hàng #' . $order['order_number'];

include 'includes/header.php';
?>

<div class="row g-4">
        <div class="col-md-8">
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">
                    <i class="fas fa-shopping-cart me-2"></i>
                    Đơn hàng #<?php echo htmlspecialchars($order['order_number']); ?>
                </h5>
                <?php echo get_status_badge($order['status']); ?>
            </div>
            
            <hr>
            
                        <h6 class="mb-3"><i class="fas fa-user me-2"></i>Thông tin khách hàng</h6>
            <table class="table table-sm">
                <tr>
                    <td width="150"><strong>Họ tên:</strong></td>
                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                </tr>
                <tr>
                    <td><strong>Số điện thoại:</strong></td>
                    <td>
                        <div class="btn-group">
                        <a href="tel:<?php echo $order['customer_phone']; ?>" class="btn btn-sm btn-success">
                            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['customer_phone']); ?>
                        </a>
                            <a href="customer-history.php?phone=<?php echo urlencode($order['customer_phone']); ?>" class="btn btn-sm btn-info" title="Xem lịch sử khách hàng">
                                <i class="fas fa-address-book"></i> Xem lịch sử
                            </a>
                        </div>
                    </td>
                </tr>
                <?php if ($order['customer_email']): ?>
                <tr>
                    <td><strong>Email:</strong></td>
                    <td><?php echo htmlspecialchars($order['customer_email']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td><strong>Địa chỉ:</strong></td>
                    <td><?php echo htmlspecialchars($order['customer_address'] ?? 'N/A'); ?></td>
                </tr>
                <?php if ($order['customer_notes']): ?>
                <tr>
                    <td><strong>Ghi chú:</strong></td>
                    <td><?php echo htmlspecialchars($order['customer_notes']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            
            <hr>
            
                        <h6 class="mb-3"><i class="fas fa-box me-2"></i>Sản phẩm</h6>
            <?php if (!empty($products)): ?>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>Tên sản phẩm</th>
                        <th width="80">SL</th>
                        <th width="120" class="text-end">Đơn giá</th>
                        <th width="120" class="text-end">Thành tiền</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($product['name']); ?></td>
                        <td><?php echo $product['qty']; ?></td>
                        <td class="text-end"><?php echo format_money($product['price'] / $product['qty']); ?></td>
                        <td class="text-end"><?php echo format_money($product['price']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-active">
                        <td colspan="3" class="text-end"><strong>Tổng cộng:</strong></td>
                        <td class="text-end">
                            <strong class="text-primary fs-5">
                                <?php echo format_money($order['total_amount']); ?>
                            </strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <?php else: ?>
            <p class="text-muted">Không có thông tin sản phẩm</p>
            <?php endif; ?>
            
            <hr>
            
                        <div class="row">
                <div class="col-md-6">
                    <small class="text-muted">Ngày đặt:</small><br>
                    <strong><?php echo format_date($order['woo_created_at']); ?></strong>
                </div>
                <div class="col-md-6">
                    <small class="text-muted">Số cuộc gọi:</small><br>
                    <strong><?php echo $order['call_count']; ?> cuộc</strong>
                </div>
            </div>
        </div>
        
                <div class="table-card mt-4">
            <h5 class="mb-3"><i class="fas fa-comments me-2"></i>Lịch sử ghi chú</h5>
            
            <?php if (!empty($notes)): ?>
            <div class="timeline">
                <?php foreach ($notes as $note): ?>
                <div class="timeline-item mb-3">
                    <div class="d-flex">
                        <div class="timeline-marker">
                            <i class="fas fa-circle text-primary"></i>
                        </div>
                        <div class="timeline-content flex-grow-1 ms-3">
                            <div class="d-flex justify-content-between">
                                <strong><?php echo htmlspecialchars($note['full_name']); ?></strong>
                                <small class="text-muted"><?php echo time_ago($note['created_at']); ?></small>
                            </div>
                            <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                            <?php if ($note['note_type'] === 'status'): ?>
                            <span class="badge bg-info mt-1">Thay đổi trạng thái</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted text-center py-3">Chưa có ghi chú nào</p>
            <?php endif; ?>
        </div>
    </div>
    
        <div class="col-md-4">
                <div class="table-card mb-3">
            <h6 class="mb-3"><i class="fas fa-bolt me-2"></i>Thao tác nhanh</h6>
            
            <?php if ($order['status'] === 'new' && !$order['assigned_to']): ?>
            <button class="btn btn-primary w-100 mb-2" id="btnClaimOrder">
                <i class="fas fa-hand-paper me-2"></i>Nhận đơn này
            </button>
            <?php endif; ?>
            
            <?php if ($order['assigned_to'] == $current_user['id'] || is_admin()): ?>
                        <div class="mb-3">
                <label class="form-label">Cập nhật trạng thái:</label>
                <select class="form-select" id="orderStatus">
                    <?php foreach (ORDER_STATUS as $key => $status): ?>
                    <option value="<?php echo $key; ?>" <?php echo $order['status'] === $key ? 'selected' : ''; ?>>
                        <?php echo $status['label']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-success w-100 mt-2" id="btnUpdateStatus">
                    <i class="fas fa-save me-2"></i>Cập nhật trạng thái
                </button>
            </div>
            
            <hr>
            
                        <div class="mb-3">
                <label class="form-label">Thêm ghi chú cuộc gọi:</label>
                <textarea class="form-control" id="noteContent" rows="4" placeholder="Nhập nội dung ghi chú..."></textarea>
                <button class="btn btn-info w-100 mt-2" id="btnAddNote">
                    <i class="fas fa-plus-circle me-2"></i>Thêm ghi chú
                </button>
            </div>
            
            <?php if ($order['status'] === 'callback'): ?>
            <hr>
            <div class="mb-3">
                <label class="form-label">Thời gian gọi lại:</label>
                <input type="datetime-local" class="form-control" id="callbackTime" 
                       value="<?php echo $order['callback_time'] ? date('Y-m-d\TH:i', strtotime($order['callback_time'])) : ''; ?>">
                <button class="btn btn-warning w-100 mt-2" id="btnSetCallback">
                    <i class="fas fa-clock me-2"></i>Đặt lịch gọi lại
                </button>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <?php if (is_admin() && $order['assigned_to']): ?>
        <div class="table-card mb-3">
            <h6 class="mb-3 text-danger"><i class="fas fa-user-shield me-2"></i>Hành động của Admin</h6>
            <div class="d-grid gap-2">
                <button class="btn btn-warning" id="btnTransferOrder" data-bs-toggle="modal" data-bs-target="#transferOrderModal">
                    <i class="fas fa-random me-2"></i>Chuyển giao đơn
                </button>
                <button class="btn btn-danger" id="btnReclaimOrder">
                    <i class="fas fa-undo me-2"></i>Thu hồi đơn
                </button>
            </div>
        </div>
        <?php endif; ?>
                        <div class="table-card">
            <h6 class="mb-3"><i class="fas fa-info-circle me-2"></i>Thông tin</h6>
            <table class="table table-sm">
                <tr>
                    <td>Mã WooCommerce:</td>
                    <td class="text-end"><strong>#<?php echo $order['woo_order_id']; ?></strong></td>
                </tr>
                <tr>
                    <td>Người xử lý:</td>
                    <td class="text-end">
                        <?php 
                        if ($order['assigned_to']) {
                            $assigned_user = get_user($order['assigned_to']);
                            echo '<strong>' . htmlspecialchars($assigned_user['full_name']) . '</strong>';
                        } else {
                            echo '<span class="text-muted">Chưa gán</span>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>Số cuộc gọi:</td>
                    <td class="text-end"><strong><?php echo $order['call_count']; ?></strong></td>
                </tr>
                <tr>
                    <td>Lần gọi cuối:</td>
                    <td class="text-end">
                        <?php echo $order['last_call_at'] ? time_ago($order['last_call_at']) : 'Chưa gọi'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Nhận vào hệ thống:</td>
                    <td class="text-end"><?php echo format_date($order['created_at'], 'd/m/Y H:i'); ?></td>
                </tr>
            </table>
        </div>
        
        <a href="orders.php" class="btn btn-outline-secondary w-100 mt-3">
            <i class="fas fa-arrow-left me-2"></i>Quay lại danh sách
        </a>
    </div>
</div>

<?php if (is_admin()): ?>
<div class="modal fade" id="transferOrderModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Chuyển giao đơn hàng #<?php echo $order['order_number']; ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Chọn nhân viên bạn muốn chuyển giao đơn hàng này đến.</p>
                <select id="transferToUser" class="form-select">
                    <option value="">-- Chọn nhân viên --</option>
                    <?php foreach ($telesales_list as $ts): ?>
                        <?php if ($ts['id'] != $order['assigned_to']): // Không hiển thị người đang được gán ?>
                        <option value="<?php echo $ts['id']; ?>"><?php echo htmlspecialchars($ts['full_name']); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-primary" id="btnSaveTransfer">Xác nhận chuyển giao</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<style>
.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: 0;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #667eea;
}
</style>

<script>
const orderId = <?php echo $order_id; ?>;

$(document).ready(function() {
    // Claim order
    $('#btnClaimOrder').click(function() {
        if (!confirm('Bạn có chắc muốn nhận đơn hàng này?')) return;
        
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Đang xử lý...');
        
        $.ajax({
            url: 'api/claim-order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ order_id: orderId }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã nhận đơn hàng thành công!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-hand-paper me-2"></i>Nhận đơn này');
                }
            },
            error: function() {
                showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-hand-paper me-2"></i>Nhận đơn này');
            }
        });
    });
    
    // Update status
    $('#btnUpdateStatus').click(function() {
        const status = $('#orderStatus').val();
        const btn = $(this);
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Đang cập nhật...');
        
        $.ajax({
            url: 'api/update-status.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                order_id: orderId,
                status: status
            }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã cập nhật trạng thái', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Cập nhật trạng thái');
                }
            },
            error: function() {
                showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-save me-2"></i>Cập nhật trạng thái');
            }
        });
    });
    
    // Add note
    $('#btnAddNote').click(function() {
        const content = $('#noteContent').val().trim();
        
        if (!content) {
            showToast('Vui lòng nhập nội dung ghi chú', 'error');
            return;
        }
        
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Đang thêm...');
        
        $.ajax({
            url: 'api/add-note.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                order_id: orderId,
                content: content
            }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã thêm ghi chú', 'success');
                    $('#noteContent').val('');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
                btn.prop('disabled', false).html('<i class="fas fa-plus-circle me-2"></i>Thêm ghi chú');
            },
            error: function() {
                showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-plus-circle me-2"></i>Thêm ghi chú');
            }
        });
    });
    
    // Set callback time
    $('#btnSetCallback').click(function() {
        const callbackTime = $('#callbackTime').val();
        
        if (!callbackTime) {
            showToast('Vui lòng chọn thời gian', 'error');
            return;
        }
        
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...');
        
        $.ajax({
            url: 'api/set-callback.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ 
                order_id: orderId,
                callback_time: callbackTime
            }),
            success: function(response) {
                if (response.success) {
                    showToast('Đã đặt lịch gọi lại', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
                btn.prop('disabled', false).html('<i class="fas fa-clock me-2"></i>Đặt lịch gọi lại');
            },
            error: function() {
                showToast('Có lỗi xảy ra, vui lòng thử lại', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-clock me-2"></i>Đặt lịch gọi lại');
            }
        });
    });

    // === PHẦN BỔ SUNG BẮT ĐẦU (4): JS cho Admin Actions ===
    // Reclaim Order
    $('#btnReclaimOrder').click(function() {
        if (!confirm('Bạn có chắc muốn thu hồi đơn hàng này? Nó sẽ trở về trạng thái "Đơn mới".')) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: 'api/reclaim-order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ order_id: orderId }),
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message, 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-undo me-2"></i>Thu hồi đơn');
                }
            },
            error: function() {
                showToast('Lỗi kết nối máy chủ.', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-undo me-2"></i>Thu hồi đơn');
            }
        });
    });

    // Transfer Order
    $('#btnSaveTransfer').click(function() {
        const targetUserId = $('#transferToUser').val();
        if (!targetUserId) {
            showToast('Vui lòng chọn một nhân viên để chuyển giao.', 'error');
            return;
        }

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: 'api/transfer-order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ order_id: orderId, target_user_id: targetUserId }),
            success: function(response) {
                if (response.success) {
                    showToast(response.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast(response.message, 'error');
                    btn.prop('disabled', false).text('Xác nhận chuyển giao');
                }
            },
            error: function() {
                showToast('Lỗi kết nối máy chủ.', 'error');
                btn.prop('disabled', false).text('Xác nhận chuyển giao');
            }
        });
    });
    // === PHẦN BỔ SUNG KẾT THÚC (4) ===
});
</script>

<?php include 'includes/footer.php'; ?>