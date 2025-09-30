<?php
/**
 * Customer History Page
 */
define('TSM_ACCESS', true);


require_login();

$page_title = 'Lịch sử khách hàng';

$customer_phone = sanitize($_GET['phone'] ?? '');
$customer_info = null;
$orders = [];
$all_notes = [];

if (!empty($customer_phone)) {
    // 1. Lấy danh sách các đơn hàng của SĐT này
    $orders = db_get_results(
        "SELECT o.*, u.full_name as assigned_name
         FROM orders o
         LEFT JOIN users u ON o.assigned_to = u.id
         WHERE o.customer_phone = ?
         ORDER BY o.created_at DESC",
        [$customer_phone]
    );

    if (!empty($orders)) {
        // 2. Lấy thông tin khách hàng từ đơn hàng gần nhất
        $latest_order = $orders[0];
        $customer_info = [
            'name' => $latest_order['customer_name'],
            'phone' => $latest_order['customer_phone'],
            'email' => $latest_order['customer_email'],
            'address' => $latest_order['customer_address']
        ];
        
        // 3. Lấy tất cả ghi chú từ các đơn hàng tìm được
        $order_ids = array_column($orders, 'id');
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
        
        $all_notes = db_get_results(
            "SELECT n.*, u.full_name as user_name, o.order_number
             FROM order_notes n
             JOIN users u ON n.user_id = u.id
             JOIN orders o ON n.order_id = o.id
             WHERE n.order_id IN ({$placeholders})
             ORDER BY n.created_at DESC",
            $order_ids
        );
    }
}

include 'includes/header.php';
?>

<div class="table-card mb-4">
    <h5 class="mb-3"><i class="fas fa-search me-2"></i>Tra cứu lịch sử khách hàng</h5>
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-6">
            <label for="phone" class="form-label">Nhập số điện thoại khách hàng</label>
            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($customer_phone); ?>" placeholder="Ví dụ: 0901234567" required>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-search me-2"></i>Tìm kiếm
            </button>
        </div>
    </form>
</div>

<?php if (!empty($customer_phone)): ?>
    <?php if (empty($orders)): ?>
        <div class="alert alert-warning text-center">
            <i class="fas fa-exclamation-circle fa-2x mb-2"></i><br>
            Không tìm thấy khách hàng nào với số điện thoại <strong><?php echo htmlspecialchars($customer_phone); ?></strong>.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-md-5">
                <div class="table-card mb-4">
                    <h5 class="mb-3"><i class="fas fa-user-circle me-2"></i>Thông tin khách hàng</h5>
                    <table class="table table-sm">
                        <tr>
                            <td width="100"><strong>Họ tên:</strong></td>
                            <td><?php echo htmlspecialchars($customer_info['name']); ?></td>
                        </tr>
                        <tr>
                            <td><strong>SĐT:</strong></td>
                            <td><a href="tel:<?php echo $customer_info['phone']; ?>"><?php echo htmlspecialchars($customer_info['phone']); ?></a></td>
                        </tr>
                        <tr>
                            <td><strong>Email:</strong></td>
                            <td><?php echo htmlspecialchars($customer_info['email'] ?? 'N/A'); ?></td>
                        </tr>
                         <tr>
                            <td><strong>Địa chỉ:</strong></td>
                            <td><?php echo htmlspecialchars($customer_info['address'] ?? 'N/A'); ?></td>
                        </tr>
                    </table>
                </div>

                <div class="table-card">
                    <h5 class="mb-3"><i class="fas fa-comments me-2"></i>Toàn bộ lịch sử trao đổi</h5>
                    <?php if (empty($all_notes)): ?>
                        <p class="text-muted">Chưa có ghi chú nào.</p>
                    <?php else: ?>
                        <div class="timeline" style="max-height: 600px; overflow-y: auto;">
                            <?php foreach ($all_notes as $note): ?>
                            <div class="timeline-item mb-3">
                                <div class="d-flex">
                                    <div class="timeline-content flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <span>
                                                <strong><?php echo htmlspecialchars($note['user_name']); ?></strong>
                                                <small class="text-muted">trên đơn <a href="order-detail.php?id=<?php echo $note['order_id']; ?>">#<?php echo $note['order_number']; ?></a></small>
                                            </span>
                                            <small class="text-muted"><?php echo time_ago($note['created_at']); ?></small>
                                        </div>
                                        <p class="mb-0 mt-1"><?php echo nl2br(htmlspecialchars($note['content'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-7">
                <div class="table-card">
                    <h5 class="mb-3"><i class="fas fa-history me-2"></i>Lịch sử đơn hàng (<?php echo count($orders); ?>)</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Mã đơn</th>
                                    <th>Ngày đặt</th>
                                    <th>Tổng tiền</th>
                                    <th>Trạng thái</th>
                                    <th>Người xử lý</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><a href="order-detail.php?id=<?php echo $order['id']; ?>"><strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong></a></td>
                                    <td><?php echo format_date($order['created_at'], 'd/m/Y'); ?></td>
                                    <td><?php echo format_money($order['total_amount']); ?></td>
                                    <td><?php echo get_status_badge($order['status']); ?></td>
                                    <td><?php echo htmlspecialchars($order['assigned_name'] ?? 'Chưa gán'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <style> /* Simple timeline style */
            .timeline-content { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 3px solid var(--primary-color); }
        </style>
    <?php endif; ?>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>