<?php
/**
 * Statistics Page (Admin only)
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';



require_admin();

$page_title = 'Thống kê & Báo cáo';

// Lấy bộ lọc ngày tháng, mặc định là 30 ngày gần nhất
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-29 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// -- 1. LẤY DỮ LIỆU THỐNG KÊ TỔNG QUAN --
$sql_overall = "SELECT
                    COUNT(id) AS total_orders,
                    SUM(CASE WHEN status = 'giao-thanh-cong' THEN total_amount ELSE 0 END) AS total_revenue,
                    COUNT(CASE WHEN status = 'giao-thanh-cong' THEN 1 END) AS confirmed_orders,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) AS rejected_orders,
                    ROUND(COUNT(CASE WHEN status = 'giao-thanh-cong' THEN 1 END) * 100.0 / NULLIF(COUNT(id), 0), 2) as success_rate
                FROM orders
                WHERE DATE(created_at) BETWEEN ? AND ?";
$overall_stats = db_get_row($sql_overall, [$date_from, $date_to]);


// -- 2. LẤY DỮ LIỆU CHO BIỂU ĐỒ TRẠNG THÁI --
$sql_status_dist = "SELECT status, COUNT(id) as count
                    FROM orders
                    WHERE DATE(created_at) BETWEEN ? AND ?
                    GROUP BY status";
$status_distribution = db_get_results($sql_status_dist, [$date_from, $date_to]);


// -- 3. LẤY DỮ LIỆU CHO BIỂU ĐỒ ĐƠN HÀNG THEO NGÀY --
$sql_daily_orders = "SELECT
                        DATE(created_at) as order_date,
                        COUNT(id) as total_orders,
                        COUNT(CASE WHEN status = 'giao-thanh-cong' THEN 1 END) as confirmed_orders
                     FROM orders
                     WHERE DATE(created_at) BETWEEN ? AND ?
                     GROUP BY order_date
                     ORDER BY order_date ASC";
$daily_orders_data = db_get_results($sql_daily_orders, [$date_from, $date_to]);


// -- 4. LẤY DỮ LIỆU HIỆU SUẤT CỦA TỪNG TELESALE --
$sql_telesale_perf = "SELECT
                        u.id,
                        u.full_name,
                        COUNT(o.id) as assigned_orders,
                        COUNT(CASE WHEN o.status = 'giao-thanh-cong' THEN 1 END) as confirmed,
                        COUNT(CASE WHEN o.status = 'rejected' THEN 1 END) as rejected,
                        COUNT(CASE WHEN o.status = 'no_answer' THEN 1 END) as no_answer,
                        SUM(o.call_count) as total_calls,
                        ROUND(COUNT(CASE WHEN o.status = 'giao-thanh-cong' THEN 1 END) * 100.0 / NULLIF(COUNT(o.id), 0), 2) as success_rate
                      FROM users u
                      LEFT JOIN orders o ON u.id = o.assigned_to AND DATE(o.created_at) BETWEEN ? AND ?
                      WHERE u.role = 'telesale' AND u.status = 'active'
                      GROUP BY u.id, u.full_name
                      ORDER BY confirmed DESC";
$telesale_performance = db_get_results($sql_telesale_perf, [$date_from, $date_to]);


include 'includes/header.php';
?>

<div class="table-card mb-4">
    <h5 class="mb-3">Bộ lọc báo cáo</h5>
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-4">
            <label for="date_from" class="form-label">Từ ngày</label>
            <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
        </div>
        <div class="col-md-4">
            <label for="date_to" class="form-label">Đến ngày</label>
            <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-primary w-100">
                <i class="fas fa-filter me-2"></i>Xem báo cáo
            </button>
        </div>
    </form>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="stat-card">
            <h6 class="text-muted">Tổng đơn hàng</h6>
            <h2 class="mb-0"><?php echo number_format($overall_stats['total_orders'] ?? 0); ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <h6 class="text-muted">Tổng doanh thu</h6>
            <h2 class="mb-0 text-success"><?php echo format_money($overall_stats['total_revenue'] ?? 0); ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <h6 class="text-muted">Đơn xác nhận</h6>
            <h2 class="mb-0"><?php echo number_format($overall_stats['confirmed_orders'] ?? 0); ?></h2>
        </div>
    </div>
    <div class="col-md-3">
        <div class="stat-card">
            <h6 class="text-muted">Tỷ lệ thành công</h6>
            <h2 class="mb-0 text-primary"><?php echo ($overall_stats['success_rate'] ?? 0); ?>%</h2>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-8">
        <div class="table-card" style="height: 400px;">
            <h5 class="mb-3">Thống kê đơn hàng theo ngày</h5>
            <canvas id="dailyOrdersChart"></canvas>
        </div>
    </div>
    <div class="col-md-4">
        <div class="table-card" style="height: 400px;">
            <h5 class="mb-3">Phân bổ trạng thái</h5>
            <canvas id="statusDistributionChart"></canvas>
        </div>
    </div>
</div>

<div class="table-card">
    <h5 class="mb-3">Hiệu suất Telesale</h5>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Nhân viên</th>
                    <th>Tổng đơn</th>
                    <th>Xác nhận</th>
                    <th>Từ chối</th>
                    <th>Không nghe máy</th>
                    <th>Tổng cuộc gọi</th>
                    <th>Tỷ lệ chốt (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($telesale_performance)): ?>
                    <tr><td colspan="7" class="text-center text-muted">Không có dữ liệu.</td></tr>
                <?php else: ?>
                    <?php foreach ($telesale_performance as $perf): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($perf['full_name']); ?></strong></td>
                        <td><?php echo number_format($perf['assigned_orders']); ?></td>
                        <td class="text-success fw-bold"><?php echo number_format($perf['confirmed']); ?></td>
                        <td class="text-danger"><?php echo number_format($perf['rejected']); ?></td>
                        <td><?php echo number_format($perf['no_answer']); ?></td>
                        <td><?php echo number_format($perf['total_calls'] ?? 0); ?></td>
                        <td>
                            <span class="badge bg-primary fs-6"><?php echo $perf['success_rate']; ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Status Distribution Chart (Pie Chart)
    const statusData = <?php echo json_encode($status_distribution); ?>;
    const statusLabels = <?php echo json_encode($labels_array); ?>;
    const statusCtx = document.getElementById('statusDistributionChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => statusLabels[item.status] || item.status),
            datasets: [{
                data: statusData.map(item => item.count),
                backgroundColor: ['#667eea', '#17a2b8', '#ffc107', '#28a745', '#dc3545', '#6c757d', '#343a40']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // 2. Daily Orders Chart (Line Chart)
    const dailyData = <?php echo json_encode($daily_orders_data); ?>;
    const dailyCtx = document.getElementById('dailyOrdersChart').getContext('2d');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyData.map(item => item.order_date),
            datasets: [
                {
                    label: 'Tổng đơn',
                    data: dailyData.map(item => item.total_orders),
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    fill: true,
                    tension: 0.3
                },
                {
                    label: 'Đơn xác nhận',
                    data: dailyData.map(item => item.confirmed_orders),
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    fill: true,
                    tension: 0.3
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
});
</script>

<?php
include 'includes/footer.php';
?>