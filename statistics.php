<?php
/**
 * Statistics Page (Admin only)
 * ✅ FIXED: Dùng primary_label + label_value thay vì status
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

// ✅ FIXED: Dùng label_value thay vì hardcode status
$sql_overall = "SELECT
                    COUNT(o.id) AS total_orders,
                    SUM(CASE WHEN ol.label_value = 1 THEN o.total_amount ELSE 0 END) AS total_revenue,
                    COUNT(CASE WHEN ol.label_value = 1 THEN 1 END) AS confirmed_orders,
                    COUNT(CASE WHEN ol.label_value = 0 THEN 1 END) AS in_progress_orders,
                    ROUND(COUNT(CASE WHEN ol.label_value = 1 THEN 1 END) * 100.0 / NULLIF(COUNT(o.id), 0), 2) as success_rate
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE DATE(o.created_at) BETWEEN ? AND ?";
$overall_stats = db_get_row($sql_overall, [$date_from, $date_to]);

// ✅ FIXED: Dùng primary_label + label_name thay vì status
$sql_status_dist = "SELECT ol.label_name as status, COUNT(o.id) as count
                    FROM orders o
                    LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                    WHERE DATE(o.created_at) BETWEEN ? AND ?
                    GROUP BY ol.label_name
                    ORDER BY count DESC";
$status_distribution = db_get_results($sql_status_dist, [$date_from, $date_to]);

// ✅ FIXED: Dùng label_value để đếm confirmed
$sql_daily_orders = "SELECT
                        DATE(o.created_at) as order_date,
                        COUNT(o.id) as total_orders,
                        COUNT(CASE WHEN ol.label_value = 1 THEN 1 END) as confirmed_orders
                     FROM orders o
                     LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                     WHERE DATE(o.created_at) BETWEEN ? AND ?
                     GROUP BY order_date
                     ORDER BY order_date ASC";
$daily_orders_data = db_get_results($sql_daily_orders, [$date_from, $date_to]);

// ✅ FIXED: Dùng label_value để đếm confirmed
$sql_telesale_perf = "SELECT
                        u.id,
                        u.full_name,
                        COUNT(o.id) as assigned_orders,
                        COUNT(CASE WHEN ol.label_value = 1 THEN 1 END) as confirmed,
                        COUNT(CASE WHEN ol.label_value = 0 AND o.is_locked = 0 THEN 1 END) as in_progress,
                        SUM(o.call_count) as total_calls,
                        ROUND(COUNT(CASE WHEN ol.label_value = 1 THEN 1 END) * 100.0 / NULLIF(COUNT(o.id), 0), 2) as success_rate
                      FROM users u
                      LEFT JOIN orders o ON u.id = o.assigned_to AND DATE(o.created_at) BETWEEN ? AND ?
                      LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
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
            <h6 class="text-muted">Đơn hoàn thành</h6>
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
                    <th>Hoàn thành</th>
                    <th>Đang xử lý</th>
                    <th>Tổng cuộc gọi</th>
                    <th>Tỷ lệ chốt (%)</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($telesale_performance)): ?>
                    <tr><td colspan="6" class="text-center text-muted">Không có dữ liệu.</td></tr>
                <?php else: ?>
                    <?php foreach ($telesale_performance as $perf): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($perf['full_name']); ?></strong></td>
                        <td><?php echo number_format($perf['assigned_orders']); ?></td>
                        <td class="text-success fw-bold"><?php echo number_format($perf['confirmed']); ?></td>
                        <td><?php echo number_format($perf['in_progress'] ?? 0); ?></td>
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
    const statusCtx = document.getElementById('statusDistributionChart').getContext('2d');
    new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: statusData.map(item => item.status || 'Không xác định'),
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
                    label: 'Đơn hoàn thành',
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
