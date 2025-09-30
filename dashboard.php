<?php
/**
 * Dashboard Page
 */
define('TSM_ACCESS', true);


require_login();

$current_user = get_current_user();
$page_title = 'Trang chủ';

// Lấy thống kê
if (is_admin()) {
    // Admin: Thống kê tổng quan
    $total_orders = count_orders([]);
    $new_orders = count_orders(['status' => 'new']);
    $confirmed_orders = count_orders(['status' => 'confirmed']);
    $total_telesales = count(get_telesales('active'));
    
    // Đơn hàng hôm nay
    $today_orders = db_get_var(
        "SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()"
    );
    
    // Thống kê theo trạng thái
    $status_stats = db_get_results(
        "SELECT status, COUNT(*) as count FROM orders GROUP BY status"
    );
    
    // Top telesales
    $top_telesales = db_get_results(
        "SELECT u.id, u.full_name, 
                COUNT(o.id) as total_orders,
                COUNT(CASE WHEN o.status = 'confirmed' THEN 1 END) as confirmed_orders,
                ROUND(COUNT(CASE WHEN o.status = 'confirmed' THEN 1 END) * 100.0 / NULLIF(COUNT(o.id), 0), 2) as success_rate
         FROM users u
         LEFT JOIN orders o ON u.id = o.assigned_to
         WHERE u.role = 'telesale' AND u.status = 'active'
         GROUP BY u.id, u.full_name
         ORDER BY confirmed_orders DESC
         LIMIT 5"
    );
    
} else {
    // Telesale: Thống kê cá nhân
    $user_id = $current_user['id'];
    
    $my_orders = count_orders(['assigned_to' => $user_id]);
    $my_confirmed = count_orders(['assigned_to' => $user_id, 'status' => 'confirmed']);
    $my_pending = count_orders(['assigned_to' => $user_id, 'status' => 'assigned']) +
                  count_orders(['assigned_to' => $user_id, 'status' => 'calling']);
    $available_orders = count_orders(['available' => true]);
    
    // Đơn cần gọi lại
    $callback_orders = db_get_results(
        "SELECT * FROM orders 
         WHERE assigned_to = ? AND status = 'callback' AND callback_time <= NOW()
         ORDER BY callback_time ASC
         LIMIT 5",
        [$user_id]
    );

    // === PHẦN CẬP NHẬT: LẤY DỮ LIỆU KPI ===
    $current_month_sql = date('Y-m-01');
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');

    // Lấy mục tiêu KPI đã đặt
    $kpi_targets_raw = db_get_results("SELECT target_type, target_value FROM kpis WHERE user_id = ? AND target_month = ?", [$user_id, $current_month_sql]);
    $kpi_targets = ['confirmed_orders' => 0, 'total_revenue' => 0];
    foreach ($kpi_targets_raw as $target) {
        $kpi_targets[$target['target_type']] = $target['target_value'];
    }

    // Lấy kết quả KPI đã đạt được
    $kpi_achieved = db_get_row(
        "SELECT COUNT(id) as confirmed_orders, SUM(CASE WHEN status = 'confirmed' THEN total_amount ELSE 0 END) as total_revenue
         FROM orders
         WHERE assigned_to = ? AND status = 'confirmed' AND DATE(completed_at) BETWEEN ? AND ?",
        [$user_id, $month_start, $month_end]
    );
    // =======================================
}

// Đơn hàng gần đây
$recent_orders_query = "SELECT o.*, u.full_name as assigned_name 
     FROM orders o
     LEFT JOIN users u ON o.assigned_to = u.id
     " . (!is_admin() ? "WHERE o.assigned_to = {$current_user['id']}" : "") . "
     ORDER BY o.created_at DESC
     LIMIT 10";
$recent_orders = db_get_results($recent_orders_query);

include 'includes/header.php';
?>

<?php if (is_admin()): ?>
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Tổng đơn hàng</h6>
                        <h2 class="mb-0"><?php echo number_format($total_orders); ?></h2>
                        <small class="text-success">
                            <i class="fas fa-arrow-up"></i> <?php echo $today_orders; ?> hôm nay
                        </small>
                    </div>
                    <div class="icon" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Đơn mới</h6>
                        <h2 class="mb-0"><?php echo number_format($new_orders); ?></h2>
                        <small class="text-warning">
                            <i class="fas fa-clock"></i> Chưa xử lý
                        </small>
                    </div>
                    <div class="icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Đã xác nhận</h6>
                        <h2 class="mb-0"><?php echo number_format($confirmed_orders); ?></h2>
                        <small class="text-success">
                            <i class="fas fa-check-circle"></i> Thành công
                        </small>
                    </div>
                    <div class="icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                        <i class="fas fa-check-double"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Nhân viên</h6>
                        <h2 class="mb-0"><?php echo number_format($total_telesales); ?></h2>
                        <small class="text-info">
                            <i class="fas fa-users"></i> Đang hoạt động
                        </small>
                    </div>
                    <div class="icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                        <i class="fas fa-user-friends"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <div class="col-md-8">
            <div class="table-card">
                <h5 class="mb-3">Thống kê theo trạng thái</h5>
                <canvas id="statusChart" height="100"></canvas>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="table-card">
                <h5 class="mb-3">Top Telesales</h5>
                <div class="list-group list-group-flush">
                    <?php foreach ($top_telesales as $ts): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?php echo htmlspecialchars($ts['full_name']); ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo $ts['confirmed_orders']; ?>/<?php echo $ts['total_orders']; ?> đơn
                                </small>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-success"><?php echo $ts['success_rate']; ?>%</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Chart data
        const statusData = <?php echo json_encode($status_stats); ?>;
        const labels = statusData.map(item => {
            const statusLabels = {
                'new': 'Đơn mới', 'assigned': 'Đã nhận', 'calling': 'Đang gọi', 'confirmed': 'Xác nhận',
                'rejected': 'Từ chối', 'no_answer': 'Không bắt máy', 'callback': 'Gọi lại'
            };
            return statusLabels[item.status] || item.status;
        });
        const data = statusData.map(item => item.count);
        
        const ctx = document.getElementById('statusChart').getContext('2d');
        new Chart(ctx, { type: 'bar', data: { labels: labels, datasets: [{ label: 'Số lượng đơn', data: data, backgroundColor: ['#667eea', '#17a2b8', '#ffc107', '#28a745', '#dc3545', '#6c757d'] }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } } });
    </script>

<?php else: ?>
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Đơn của tôi</h6>
                        <h2 class="mb-0"><?php echo number_format($my_orders); ?></h2>
                        <small class="text-info">Tổng số đơn</small>
                    </div>
                    <div class="icon" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Đã xác nhận</h6>
                        <h2 class="mb-0"><?php echo number_format($my_confirmed); ?></h2>
                        <small class="text-success">
                            <?php 
                            $rate = $my_orders > 0 ? round(($my_confirmed / $my_orders) * 100, 1) : 0;
                            echo $rate . '% tỷ lệ thành công';
                            ?>
                        </small>
                    </div>
                    <div class="icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Đang xử lý</h6>
                        <h2 class="mb-0"><?php echo number_format($my_pending); ?></h2>
                        <small class="text-warning">Cần gọi điện</small>
                    </div>
                    <div class="icon" style="background: rgba(255, 193, 7, 0.1); color: #ffc107;">
                        <i class="fas fa-phone"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-2">Đơn có thể nhận</h6>
                        <h2 class="mb-0"><?php echo number_format($available_orders); ?></h2>
                        <small class="text-primary">
                            <a href="orders.php?status=available">Xem ngay</a>
                        </small>
                    </div>
                    <div class="icon" style="background: rgba(0, 123, 255, 0.1); color: #007bff;">
                        <i class="fas fa-star"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="table-card mb-4">
        <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i>Tiến độ KPI tháng này (<?php echo date('m/Y'); ?>)</h5>
        <?php
            $achieved_orders = $kpi_achieved['confirmed_orders'] ?? 0;
            $target_orders = $kpi_targets['confirmed_orders'];
            $order_progress = $target_orders > 0 ? round(($achieved_orders / $target_orders) * 100) : 0;
            
            $achieved_revenue = $kpi_achieved['total_revenue'] ?? 0;
            $target_revenue = $kpi_targets['total_revenue'];
            $revenue_progress = $target_revenue > 0 ? round(($achieved_revenue / $target_revenue) * 100) : 0;
        ?>
        <div class="mb-3">
            <div class="d-flex justify-content-between">
                <span>Mục tiêu đơn hàng</span>
                <strong><?php echo number_format($achieved_orders); ?> / <?php echo number_format($target_orders); ?></strong>
            </div>
            <div class="progress mt-1" style="height: 15px;">
                <div class="progress-bar" role="progressbar" style="width: <?php echo $order_progress; ?>%;" aria-valuenow="<?php echo $order_progress; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $order_progress; ?>%</div>
            </div>
        </div>
        <div>
            <div class="d-flex justify-content-between">
                <span>Mục tiêu doanh thu</span>
                <strong class="text-success"><?php echo format_money($achieved_revenue); ?> / <?php echo format_money($target_revenue); ?></strong>
            </div>
            <div class="progress mt-1" style="height: 15px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $revenue_progress; ?>%;" aria-valuenow="<?php echo $revenue_progress; ?>" aria-valuemin="0" aria-valuemax="100"><?php echo $revenue_progress; ?>%</div>
            </div>
        </div>
    </div>
    <?php if (!empty($callback_orders)): ?>
    <div class="alert alert-warning mb-4">
        <h5><i class="fas fa-bell me-2"></i>Đơn cần gọi lại (<?php echo count($callback_orders); ?>)</h5>
        <ul class="mb-0">
            <?php foreach ($callback_orders as $order): ?>
            <li>
                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong> - <?php echo $order['customer_phone']; ?> - <a href="order-detail.php?id=<?php echo $order['id']; ?>">Xem chi tiết</a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
<?php endif; ?>

<div class="table-card mt-4">
    <h5 class="mb-3">Đơn hàng gần đây</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Mã đơn</th>
                    <th>Khách hàng</th>
                    <th>Tổng tiền</th>
                    <th>Trạng thái</th>
                    <?php if (is_admin()): ?><th>Người xử lý</th><?php endif; ?>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recent_orders)): ?>
                    <tr><td colspan="<?php echo is_admin() ? 7 : 6; ?>" class="text-center text-muted py-4">Chưa có đơn hàng nào.</td></tr>
                <?php else: ?>
                    <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td><strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                        <td><?php echo format_money($order['total_amount']); ?></td>
                        <td><?php echo get_status_badge($order['status']); ?></td>
                        <?php if (is_admin()): ?><td><?php echo $order['assigned_name'] ? htmlspecialchars($order['assigned_name']) : '<span class="text-muted">Chưa gán</span>'; ?></td><?php endif; ?>
                        <td><?php echo time_ago($order['created_at']); ?></td>
                        <td><a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include 'includes/footer.php'; ?>