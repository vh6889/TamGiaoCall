<?php
/**
 * Pending Manual Orders Page (Admin only)
 * ✅ FIXED: Dùng primary_label thay vì status
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

require_admin();

$page_title = 'Duyệt đơn hàng thủ công';

// ✅ FIXED: Dùng primary_label với label đặc biệt 'pending_approval'
$pending_orders = db_get_results(
    "SELECT o.*, u.full_name as creator_name, ol.label_name, ol.color, ol.icon
     FROM orders o
     LEFT JOIN users u ON o.created_by = u.id
     LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
     WHERE o.primary_label = 'pending_approval' AND o.source = 'manual'
     ORDER BY o.created_at DESC"
);

include 'includes/header.php';
?>

<div class="table-card">
    <h5 class="mb-3"><i class="fas fa-user-check me-2"></i>Đơn hàng chờ duyệt</h5>
    
    <?php if (empty($pending_orders)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Không có đơn hàng nào đang chờ duyệt.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Mã đơn</th>
                        <th>Khách hàng</th>
                        <th>SĐT</th>
                        <th>Tổng tiền</th>
                        <th>Người tạo</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_orders as $order): ?>
                    <tr id="order-row-<?php echo $order['id']; ?>">
                        <td>
                            <a href="order-detail.php?id=<?php echo $order['id']; ?>" target="_blank">
                                <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                            </a>
                        </td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                        <td><?php echo format_money($order['total_amount']); ?></td>
                        <td><?php echo htmlspecialchars($order['creator_name'] ?? 'N/A'); ?></td>
                        <td><?php echo format_datetime($order['created_at']); ?></td>
                        <td>
                            <button class="btn btn-sm btn-success btn-approve me-1" 
                                    data-id="<?php echo $order['id']; ?>" 
                                    title="Duyệt đơn">
                                <i class="fas fa-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger btn-reject" 
                                    data-id="<?php echo $order['id']; ?>" 
                                    title="Từ chối">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
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
            data: JSON.stringify({ 
                csrf_token: '<?php echo generate_csrf_token(); ?>',
                order_id: orderId, 
                action: action 
            }),
            success: function(response) {
                if (response.success) {
                    alert(`Đã ${actionText} đơn hàng thành công.`);
                    $('#order-row-' + orderId).fadeOut(500, function() { $(this).remove(); });
                } else {
                    alert(response.message || 'Có lỗi xảy ra');
                }
            },
            error: function() {
                alert('Không thể kết nối đến máy chủ.');
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
