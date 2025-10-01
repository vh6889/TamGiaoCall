<?php
/**
 * Dashboard Page - Dynamic Version
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';

require_login();

$current_user = get_logged_user();
if (!$current_user) {
    redirect('index.php?error=session_expired');
}

$page_title = 'Dashboard';

// Get filter parameters
$filter_month = $_GET['month'] ?? date('Y-m');
$filter_from = $_GET['from'] ?? date('Y-m-01', strtotime($filter_month));
$filter_to = $_GET['to'] ?? date('Y-m-t', strtotime($filter_month));

// Build WHERE conditions based on role
$where_conditions = [];
$params = [];

if (is_admin()) {
    // Admin sees everything
    $where_base = "1=1";
    $team_label = "Toàn hệ thống";
} elseif (is_manager()) {
    // Manager sees their team's data
    $managed_users = db_get_results(
        "SELECT telesale_id FROM manager_assignments WHERE manager_id = ?",
        [$current_user['id']]
    );
    
    if (!empty($managed_users)) {
        $team_ids = array_column($managed_users, 'telesale_id');
        $team_ids[] = $current_user['id']; // Include manager's own orders
        $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
        $where_base = "o.assigned_to IN ($placeholders) OR o.manager_id = ?";
        $params = array_merge($team_ids, [$current_user['id']]);
    } else {
        // Manager with no team sees only their orders
        $where_base = "o.assigned_to = ? OR o.manager_id = ?";
        $params = [$current_user['id'], $current_user['id']];
    }
    $team_label = "Team của bạn";
} else {
    // Telesale sees only their data
    $where_base = "o.assigned_to = ?";
    $params = [$current_user['id']];
    $team_label = "Của tôi";
}

// Get all dynamic labels (đổi từ statuses)
$all_statuses = get_all_statuses();

// Calculate statistics with date filter
$date_params = array_merge($params, [$filter_from, $filter_to]);

$stats_query = "
    SELECT 
        COUNT(*) as total_orders,
        SUM(o.total_amount) as total_revenue,
        SUM(CASE WHEN ol.label_name LIKE '%thành công%' OR ol.label_name LIKE '%hoàn thành%' 
                 OR ol.label_name LIKE '%completed%' THEN o.total_amount ELSE 0 END) as confirmed_revenue,
        COUNT(CASE WHEN ol.label_name LIKE '%thành công%' OR ol.label_name LIKE '%hoàn thành%' 
                   OR ol.label_name LIKE '%completed%' THEN 1 END) as confirmed_orders,
        COUNT(CASE WHEN DATE(o.created_at) = CURDATE() THEN 1 END) as today_orders
    FROM orders o
    LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
    WHERE ($where_base) 
    AND DATE(o.created_at) BETWEEN ? AND ?";

$stats = db_get_row($stats_query, $date_params);

// ✅ CHỈ lấy labels có đơn hàng
$status_breakdown_query = "
    SELECT 
        ol.label_key as status_key,
        ol.label_name as label,
        ol.color,
        ol.icon,
        COUNT(o.id) as count,
        SUM(o.total_amount) as revenue
    FROM orders o
    INNER JOIN order_labels ol ON o.primary_label = ol.label_key
    WHERE ($where_base)
      AND DATE(o.created_at) BETWEEN ? AND ?
      AND ol.is_system = 0
    GROUP BY ol.label_key, ol.label_name, ol.color, ol.icon
    ORDER BY ol.sort_order";

$status_breakdown = db_get_results($status_breakdown_query, $date_params);

// Get daily trend for the period (max 30 days)
$daily_trend_query = "
    SELECT 
        DATE(o.created_at) as date,
        COUNT(*) as orders,
        SUM(o.total_amount) as revenue
    FROM orders o
    WHERE ($where_base)
    AND DATE(o.created_at) BETWEEN ? AND ?
    GROUP BY DATE(o.created_at)
    ORDER BY date";

$daily_trend = db_get_results($daily_trend_query, $date_params);

// Get top performers (for admin/manager) (SỬA: đổi join)
$top_performers = [];
if (is_admin() || is_manager()) {
    $top_query = "
        SELECT 
            u.id, 
            u.full_name,
            u.role,
            COUNT(o.id) as total_orders,
            SUM(CASE WHEN ol.label_name LIKE '%thành công%' OR ol.label_name LIKE '%hoàn thành%' 
                     THEN 1 ELSE 0 END) as confirmed_orders,
            SUM(CASE WHEN ol.label_name LIKE '%thành công%' OR ol.label_name LIKE '%hoàn thành%' 
                     THEN o.total_amount ELSE 0 END) as revenue,
            ROUND(
                COUNT(CASE WHEN ol.label_name LIKE '%thành công%' OR ol.label_name LIKE '%hoàn thành%' 
                          THEN 1 END) * 100.0 / NULLIF(COUNT(o.id), 0), 1
            ) as success_rate
        FROM users u
        LEFT JOIN orders o ON u.id = o.assigned_to 
            AND DATE(o.created_at) BETWEEN ? AND ?
        LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
        WHERE u.status = 'active' 
        " . (is_manager() ? "AND u.id IN (SELECT telesale_id FROM manager_assignments WHERE manager_id = ?)" : "") . "
        GROUP BY u.id, u.full_name, u.role
        HAVING total_orders > 0
        ORDER BY confirmed_orders DESC, revenue DESC
        LIMIT 10";
    
    $top_params = is_manager() 
        ? [$filter_from, $filter_to, $current_user['id']] 
        : [$filter_from, $filter_to];
    
    $top_performers = db_get_results($top_query, $top_params);
}

// Recent orders with dynamic status (SỬA: đổi join)
$recent_orders_query = "
    SELECT 
        o.*,
        u.full_name as assigned_name,
        ol.label_name as status_label,
        ol.color as status_color,
        ol.icon as status_icon
    FROM orders o
    LEFT JOIN users u ON o.assigned_to = u.id
    LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
    WHERE ($where_base)
    ORDER BY o.created_at DESC
    LIMIT 10";

$recent_orders = db_get_results($recent_orders_query, $params);

// KPI tracking (for telesales)
$kpi_data = null;
if (is_telesale()) {
    $kpi_month = date('Y-m-01', strtotime($filter_month));
    $kpi_targets = db_get_row(
        "SELECT 
            MAX(CASE WHEN target_type = 'confirmed_orders' THEN target_value END) as order_target,
            MAX(CASE WHEN target_type = 'total_revenue' THEN target_value END) as revenue_target
         FROM kpis 
         WHERE user_id = ? AND target_month = ?",
        [$current_user['id'], $kpi_month]
    );
    
    if ($kpi_targets) {
        $kpi_data = [
            'order_target' => $kpi_targets['order_target'] ?? 0,
            'order_achieved' => $stats['confirmed_orders'] ?? 0,
            'revenue_target' => $kpi_targets['revenue_target'] ?? 0,
            'revenue_achieved' => $stats['confirmed_revenue'] ?? 0
        ];
    }
}

include 'includes/header.php';
?>

<!-- GIỮ NGUYÊN TOÀN BỘ HTML TỪ ĐÂY TRỞ XUỐNG -->
<div class="container-fluid">
    <!-- Header với Filter -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">Dashboard - <?php echo $team_label; ?></h1>
            <small class="text-muted">
                Dữ liệu từ <?php echo date('d/m/Y', strtotime($filter_from)); ?> 
                đến <?php echo date('d/m/Y', strtotime($filter_to)); ?>
            </small>
        </div>
        
        <!-- Filter Form -->
        <form method="GET" class="d-flex gap-2">
            <select name="month" class="form-select" style="width: 150px;" onchange="this.form.submit()">
                <?php for($i = 0; $i < 12; $i++): 
                    $month_value = date('Y-m', strtotime("-$i months"));
                    $month_label = date('m/Y', strtotime("-$i months"));
                ?>
                <option value="<?php echo $month_value; ?>" <?php echo $filter_month == $month_value ? 'selected' : ''; ?>>
                    Tháng <?php echo $month_label; ?>
                </option>
                <?php endfor; ?>
            </select>
            
            <input type="date" name="from" class="form-control" value="<?php echo $filter_from; ?>" style="width: 150px;">
            <input type="date" name="to" class="form-control" value="<?php echo $filter_to; ?>" style="width: 150px;">
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Lọc
            </button>
            
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-redo"></i> Reset
            </a>
        </form>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Tổng đơn hàng</p>
                            <h3 class="mb-0"><?php echo number_format($stats['total_orders'] ?? 0); ?></h3>
                            <small class="text-success">
                                <i class="fas fa-plus"></i> <?php echo $stats['today_orders'] ?? 0; ?> hôm nay
                            </small>
                        </div>
                        <div class="bg-primary bg-opacity-10 p-3 rounded">
                            <i class="fas fa-shopping-cart text-primary"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Doanh thu tổng</p>
                            <h4 class="mb-0"><?php echo format_money($stats['total_revenue'] ?? 0); ?></h4>
                            <small class="text-muted">Danh nghĩa</small>
                        </div>
                        <div class="bg-warning bg-opacity-10 p-3 rounded">
                            <i class="fas fa-chart-line text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Doanh thu xác nhận</p>
                            <h4 class="mb-0 text-success"><?php echo format_money($stats['confirmed_revenue'] ?? 0); ?></h4>
                            <small class="text-muted">Đã giao thành công</small>
                        </div>
                        <div class="bg-success bg-opacity-10 p-3 rounded">
                            <i class="fas fa-check-circle text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <p class="text-muted mb-1">Tỷ lệ thành công</p>
                            <h3 class="mb-0">
                                <?php 
                                $success_rate = $stats['total_orders'] > 0 
                                    ? round(($stats['confirmed_orders'] / $stats['total_orders']) * 100, 1) 
                                    : 0;
                                echo $success_rate;
                                ?>%
                            </h3>
                            <small class="text-muted"><?php echo $stats['confirmed_orders']; ?> đơn thành công</small>
                        </div>
                        <div class="bg-info bg-opacity-10 p-3 rounded">
                            <i class="fas fa-percentage text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- KPI Progress (for telesales) -->
    <?php if (is_telesale() && $kpi_data): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <i class="fas fa-bullseye"></i> KPI Tháng <?php echo date('m/Y', strtotime($filter_month)); ?>
            </h5>
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Mục tiêu đơn hàng</span>
                            <strong>
                                <?php echo $kpi_data['order_achieved']; ?> / <?php echo $kpi_data['order_target']; ?>
                            </strong>
                        </div>
                        <?php 
                        $order_progress = $kpi_data['order_target'] > 0 
                            ? min(100, round(($kpi_data['order_achieved'] / $kpi_data['order_target']) * 100))
                            : 0;
                        ?>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar" style="width: <?php echo $order_progress; ?>%">
                                <?php echo $order_progress; ?>%
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span>Mục tiêu doanh thu</span>
                            <strong class="text-success">
                                <?php echo format_money($kpi_data['revenue_achieved']); ?> / 
                                <?php echo format_money($kpi_data['revenue_target']); ?>
                            </strong>
                        </div>
                        <?php 
                        $revenue_progress = $kpi_data['revenue_target'] > 0 
                            ? min(100, round(($kpi_data['revenue_achieved'] / $kpi_data['revenue_target']) * 100))
                            : 0;
                        ?>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $revenue_progress; ?>%">
                                <?php echo $revenue_progress; ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Charts Row -->
    <div class="row g-3 mb-4">
        <!-- Status Breakdown Chart -->
        <div class="col-md-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">Phân bổ theo trạng thái</h5>
                    <div style="height: 350px; position: relative;">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top Performers -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="card-title mb-3">
                        <?php echo (is_admin() || is_manager()) ? 'Top Nhân viên' : 'Thống kê của bạn'; ?>
                    </h5>
                    
                    <?php if (is_admin() || is_manager()): ?>
                        <?php if (empty($top_performers)): ?>
                            <p class="text-muted text-center">Chưa có dữ liệu</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($top_performers, 0, 5) as $performer): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong><?php echo htmlspecialchars($performer['full_name']); ?></strong>
                                            <?php if ($performer['role'] == 'manager'): ?>
                                            <span class="badge bg-warning ms-1">Manager</span>
                                            <?php endif; ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo $performer['confirmed_orders']; ?>/<?php echo $performer['total_orders']; ?> đơn
                                                • <?php echo format_money($performer['revenue']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-success">
                                            <?php echo $performer['success_rate']; ?>%
                                        </span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Personal stats for telesale -->
                        <ul class="list-unstyled">
                            <li class="mb-2">
                                <span class="text-muted">Tổng đơn:</span> 
                                <strong><?php echo $stats['total_orders']; ?></strong>
                            </li>
                            <li class="mb-2">
                                <span class="text-muted">Thành công:</span> 
                                <strong class="text-success"><?php echo $stats['confirmed_orders']; ?></strong>
                            </li>
                            <li class="mb-2">
                                <span class="text-muted">Tỷ lệ:</span> 
                                <strong><?php echo $success_rate; ?>%</strong>
                            </li>
                            <li>
                                <span class="text-muted">Doanh thu:</span> 
                                <strong class="text-primary"><?php echo format_money($stats['confirmed_revenue']); ?></strong>
                            </li>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Trend Chart -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">Xu hướng theo ngày</h5>
            <div style="height: 250px; position: relative;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h5 class="card-title mb-3">Đơn hàng gần đây</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>Tổng tiền</th>
                            <th>Trạng thái</th>
                            <th>Người xử lý</th>
                            <th>Thời gian</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_orders)): ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                Chưa có đơn hàng nào
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td>
                                    <strong>#<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                <td><?php echo format_money($order['total_amount']); ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo $order['status_color']; ?>">
                                        <i class="fas <?php echo $order['status_icon']; ?>"></i>
                                        <?php echo $order['status_label']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $order['assigned_name'] ? htmlspecialchars($order['assigned_name']) : '<span class="text-muted">Chưa gán</span>'; ?>
                                </td>
                                <td>
                                    <small><?php echo time_ago($order['created_at']); ?></small>
                                </td>
                                <td>
                                    <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Status Chart
    const statusData = <?php echo json_encode($status_breakdown); ?>;
    const statusCtx = document.getElementById('statusChart');
    
    if (statusCtx && statusData.length > 0) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(item => item.label),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: statusData.map(item => item.color),
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.parsed;
                                const revenue = statusData[context.dataIndex].revenue;
                                return [
                                    label + ': ' + value + ' đơn',
                                    'Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(revenue) + '₫'
                                ];
                            }
                        }
                    }
                }
            }
        });
    }

    // Trend Chart
    const trendData = <?php echo json_encode($daily_trend); ?>;
    const trendCtx = document.getElementById('trendChart');
    
    if (trendCtx && trendData.length > 0) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(item => {
                    const date = new Date(item.date);
                    return date.getDate() + '/' + (date.getMonth() + 1);
                }),
                datasets: [
                    {
                        label: 'Đơn hàng',
                        data: trendData.map(item => item.orders),
                        borderColor: 'rgb(102, 126, 234)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        yAxisID: 'y',
                        tension: 0.3
                    },
                    {
                        label: 'Doanh thu',
                        data: trendData.map(item => item.revenue),
                        borderColor: 'rgb(40, 167, 69)',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        yAxisID: 'y1',
                        tension: 0.3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Số đơn'
                        },
                        ticks: {
                            precision: 0
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Doanh thu (₫)'
                        },
                        ticks: {
                            callback: function(value) {
                                return new Intl.NumberFormat('vi-VN', {
                                    notation: 'compact',
                                    maximumFractionDigits: 1
                                }).format(value);
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (context.parsed.y !== null) {
                                    if (context.datasetIndex === 1) { // Revenue
                                        label += ': ' + new Intl.NumberFormat('vi-VN').format(context.parsed.y) + '₫';
                                    } else { // Orders
                                        label += ': ' + context.parsed.y + ' đơn';
                                    }
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>