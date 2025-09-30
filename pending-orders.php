<?php
/**
 * Pending Manual Orders Page (Admin only)
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';



require_admin();

$page_title = 'Duyệt đơn hàng thủ công';

// Lấy danh sách đơn hàng đang chờ duyệt
$pending_orders = db_get_results(
    "SELECT o.*, u.full_name as creator_name
     FROM orders o
     JOIN users u ON o.created_by = u.id
     WHERE o.approval_status = 'pending' AND o.source = 'manual'
     ORDER BY o.created_at DESC"
);

include 'includes/header.php';
?>

<div class="table-card">
    <h5 class="mb-3"><i class="fas fa-user-check me-2"></i>Đơn hàng chờ duyệt (<?php echo count($pending_orders); ?>)</h5>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Người tạo</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Ngày tạo</th>
                    <th width="150">Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pending_orders)): ?>
                <tr><td colspan="6" class="text-center text-muted py-5">Không có đơn hàng nào chờ duyệt.</td></tr>
                <?php else: ?>
                    <?php foreach ($pending_orders as $order): ?>
                    <tr id="order-row-<?php echo $order['id']; ?>">
                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['creator_name']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($order['customer_name']); ?><br>
                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_phone']); ?></small>
                        </td>
                        <td><strong><?php echo format_money($order['total_amount']); ?></strong></td>
                        <td><?php echo time_ago($order['created_at']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-success btn-approve" data-id="<?php echo $order['id']; ?>" title="Duyệt"><i class="fas fa-check"></i></button>
                                <button class="btn btn-danger btn-reject" data-id="<?php echo $order['id']; ?>" title="Từ chối"><i class="fas fa-times"></i></button>
                                <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-info" title="Xem chi tiết"><i class="fas fa-eye"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
$(document).ready(function() {
    function handleApproval(action, orderId) {
        const actionText = action === 'approve' ? 'duyệt' : 'từ chối';
        if (!confirm(`Bạn có chắc muốn ${actionText} đơn hàng này không?`)) return;

        $.ajax({
            url: 'api/approve-order.php',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ order_id: orderId, action: action }),
            success: function(response) {
                if (response.success) {
                    showToast(`Đã ${actionText} đơn hàng thành công.`, 'success');
                    $('#order-row-' + orderId).fadeOut(500, function() { $(this).remove(); });
                } else {
                    showToast(response.message || 'Có lỗi xảy ra', 'error');
                }
            },
            error: function() {
                showToast('Không thể kết nối đến máy chủ.', 'error');
            }
        });
    }

    $('.btn-approve').click(function() {
        handleApproval('approve', $(this).data('id'));
    });

    $('.btn-reject').click(function() {
        handleApproval('reject', $(this).data('id'));
    });
});
</script>

<?php include 'includes/footer.php'; ?>