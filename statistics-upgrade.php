<?php
/**
 * Professional Statistics & Analytics System
 * Version 3.0 - Complete with all tabs and interactivity
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

require_login();

$current_user = get_logged_user();
$page_title = 'Hệ thống Báo cáo Chuyên nghiệp';

// Permission control
$permission_where = "1=1";
$permission_params = [];

if (is_admin()) {
    // Admin sees all
} elseif (is_manager()) {
    $team_ids = db_get_col("SELECT telesale_id FROM manager_assignments WHERE manager_id = ?", [$current_user['id']]);
    $team_ids[] = $current_user['id'];
    if (!empty($team_ids)) {
        $permission_where = "o.assigned_to IN (" . implode(',', array_fill(0, count($team_ids), '?')) . ")";
        $permission_params = $team_ids;
    }
} else {
    $permission_where = "o.assigned_to = ?";
    $permission_params = [$current_user['id']];
}

// Get filters
$filter_type = $_GET['filter_type'] ?? 'overview';
$selected_label = $_GET['label'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$time_from = $_GET['time_from'] ?? '00:00';
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$time_to = $_GET['time_to'] ?? '23:59';
$datetime_from = $date_from . ' ' . $time_from . ':00';
$datetime_to = $date_to . ' ' . $time_to . ':59';

// Drill-down parameters
$drill_type = $_GET['drill'] ?? '';
$drill_value = $_GET['drill_value'] ?? '';

// Load all labels from database
$order_labels = db_get_results("SELECT * FROM order_labels ORDER BY sort_order, label_name");
$customer_labels = db_get_results("SELECT * FROM customer_labels ORDER BY label_name");
$user_labels = db_get_results("SELECT * FROM user_labels ORDER BY label_name");

// Build report data
$report_data = [];

if ($filter_type == 'overview' || $drill_type) {
    // COMPREHENSIVE OVERVIEW WITH ALL METRICS
    
    // 1. Overall KPIs
    $kpis = db_get_row(
        "SELECT 
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT o.customer_phone) as unique_customers,
            COUNT(DISTINCT o.assigned_to) as active_staff,
            SUM(o.total_amount) as total_revenue,
            AVG(o.total_amount) as avg_order_value,
            SUM(o.call_count) as total_calls,
            AVG(o.call_count) as avg_calls_per_order,
            MAX(o.created_at) as last_order_time
         FROM orders o
         WHERE o.created_at BETWEEN ? AND ?
           AND ($permission_where)",
        array_merge([$datetime_from, $datetime_to], $permission_params)
    );
    
    // 2. Status Distribution (from order_labels NOT core_status)
    $status_dist = db_get_results(
        "SELECT 
            ol.label_key,
            ol.label_name,
            ol.color,
            ol.icon,
            COUNT(o.id) as count,
            SUM(o.total_amount) as revenue,
            AVG(o.total_amount) as avg_amount,
            ROUND(COUNT(o.id) * 100.0 / NULLIF((
                SELECT COUNT(*) FROM orders 
                WHERE created_at BETWEEN ? AND ? 
                AND ($permission_where)
            ), 0), 2) as percentage
         FROM order_labels ol
         LEFT JOIN orders o ON o.primary_label = ol.label_key
            AND o.created_at BETWEEN ? AND ?
            AND ($permission_where)
         GROUP BY ol.label_key, ol.label_name, ol.color, ol.icon
         ORDER BY count DESC",
        array_merge(
            [$datetime_from, $datetime_to], 
            $permission_params,
            [$datetime_from, $datetime_to], 
            $permission_params
        )
    );
    
    // 3. Top Products with full details
    $top_products = [];
    $product_query = db_get_results(
        "SELECT o.id, o.products, o.primary_label
         FROM orders o
         WHERE o.created_at BETWEEN ? AND ?
           AND ($permission_where)",
        array_merge([$datetime_from, $datetime_to], $permission_params)
    );
    
    foreach ($product_query as $order) {
        $products = json_decode($order['products'], true) ?? [];
        foreach ($products as $product) {
            $name = $product['name'] ?? $product['product_name'] ?? 'N/A';
            $sku = $product['sku'] ?? $product['product_id'] ?? '';
            $key = $sku ?: md5($name);
            
            if (!isset($top_products[$key])) {
                $top_products[$key] = [
                    'sku' => $sku,
                    'name' => $name,
                    'qty' => 0,
                    'revenue' => 0,
                    'orders' => 0,
                    'order_ids' => []
                ];
            }
            
            $top_products[$key]['qty'] += $product['qty'] ?? 1;
            $top_products[$key]['revenue'] += ($product['price'] ?? 0) * ($product['qty'] ?? 1);
            $top_products[$key]['orders']++;
            $top_products[$key]['order_ids'][] = $order['id'];
        }
    }
    
    // Sort and limit top products
    uasort($top_products, fn($a, $b) => $b['revenue'] - $a['revenue']);
    $top_products = array_slice($top_products, 0, 10, true);
    
    // 4. Staff Performance
    $staff_performance = db_get_results(
        "SELECT 
            u.id,
            u.full_name,
            u.role,
            COUNT(o.id) as total_orders,
            SUM(o.total_amount) as total_revenue,
            AVG(o.total_amount) as avg_order,
            SUM(o.call_count) as total_calls,
            GROUP_CONCAT(DISTINCT ol.label_name ORDER BY ol.label_name) as statuses
         FROM users u
         LEFT JOIN orders o ON o.assigned_to = u.id
            AND o.created_at BETWEEN ? AND ?
         LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
         WHERE u.status = 'active'
           AND ($permission_where)
         GROUP BY u.id
         HAVING total_orders > 0
         ORDER BY total_revenue DESC
         LIMIT 10",
        array_merge([$datetime_from, $datetime_to], $permission_params)
    );
    
    // 5. Daily Trend with more metrics
    $daily_trend = db_get_results(
        "SELECT 
            DATE(o.created_at) as date,
            COUNT(o.id) as orders,
            SUM(o.total_amount) as revenue,
            COUNT(DISTINCT o.customer_phone) as customers,
            AVG(o.total_amount) as avg_order
         FROM orders o
         WHERE o.created_at BETWEEN ? AND ?
           AND ($permission_where)
         GROUP BY DATE(o.created_at)
         ORDER BY date",
        array_merge([$datetime_from, $datetime_to], $permission_params)
    );
    
    // 6. Customer Labels Distribution
    $customer_label_stats = db_get_results(
        "SELECT 
            cl.label_key,
            cl.label_name,
            cl.color,
            COUNT(DISTINCT cm.customer_phone) as customer_count
         FROM customer_labels cl
         LEFT JOIN customer_metrics cm ON JSON_CONTAINS(cm.labels, JSON_QUOTE(cl.label_key))
         GROUP BY cl.label_key, cl.label_name, cl.color
         ORDER BY customer_count DESC",
        []
    );
    
    // 7. User Labels Distribution
    $user_label_stats = db_get_results(
        "SELECT 
            ul.label_key,
            ul.label_name,
            ul.color,
            COUNT(DISTINCT ep.user_id) as user_count
         FROM user_labels ul
         LEFT JOIN employee_performance ep ON JSON_CONTAINS(ep.labels, JSON_QUOTE(ul.label_key))
         GROUP BY ul.label_key, ul.label_name, ul.color
         ORDER BY user_count DESC",
        []
    );
    
    $report_data = [
        'type' => 'overview',
        'kpis' => $kpis,
        'status_dist' => $status_dist,
        'top_products' => $top_products,
        'staff_performance' => $staff_performance,
        'daily_trend' => $daily_trend,
        'customer_label_stats' => $customer_label_stats,
        'user_label_stats' => $user_label_stats
    ];
}

// Filter by specific label
if ($filter_type == 'by_label' && $selected_label) {
    $label_info = db_get_row("SELECT * FROM order_labels WHERE label_key = ?", [$selected_label]);
    
    if ($label_info) {
        // Get all orders with this label
        $orders = db_get_results(
            "SELECT o.*, u.full_name as assigned_name
             FROM orders o
             LEFT JOIN users u ON o.assigned_to = u.id
             WHERE o.primary_label = ?
               AND o.created_at BETWEEN ? AND ?
               AND ($permission_where)
             ORDER BY o.created_at DESC",
            array_merge([$selected_label, $datetime_from, $datetime_to], $permission_params)
        );
        
        // Calculate stats for this label
        $label_stats = db_get_row(
            "SELECT 
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_order,
                COUNT(DISTINCT o.customer_phone) as unique_customers,
                COUNT(DISTINCT o.assigned_to) as staff_count
             FROM orders o
             WHERE o.primary_label = ?
               AND o.created_at BETWEEN ? AND ?
               AND ($permission_where)",
            array_merge([$selected_label, $datetime_from, $datetime_to], $permission_params)
        );
        
        $report_data = [
            'type' => 'by_label',
            'label_info' => $label_info,
            'orders' => $orders,
            'stats' => $label_stats
        ];
    }
}

// Handle drill-down with full context
if ($drill_type) {
    $drill_data = [];
    
    switch ($drill_type) {
        case 'status':
            // Get label info
            $label = db_get_row("SELECT * FROM order_labels WHERE label_key = ?", [$drill_value]);
            
            // Get all orders with this status
            $orders = db_get_results(
                "SELECT o.*, u.full_name as assigned_name
                 FROM orders o
                 LEFT JOIN users u ON o.assigned_to = u.id
                 WHERE o.primary_label = ?
                   AND o.created_at BETWEEN ? AND ?
                   AND ($permission_where)
                 ORDER BY o.created_at DESC",
                array_merge([$drill_value, $datetime_from, $datetime_to], $permission_params)
            );
            
            // Group by staff
            $by_staff = [];
            foreach ($orders as $order) {
                $staff_name = $order['assigned_name'] ?? 'Chưa phân';
                if (!isset($by_staff[$staff_name])) {
                    $by_staff[$staff_name] = [
                        'count' => 0,
                        'revenue' => 0,
                        'orders' => []
                    ];
                }
                $by_staff[$staff_name]['count']++;
                $by_staff[$staff_name]['revenue'] += $order['total_amount'];
                $by_staff[$staff_name]['orders'][] = $order;
            }
            
            $drill_data = [
                'type' => 'status_detail',
                'label' => $label,
                'orders' => $orders,
                'by_staff' => $by_staff
            ];
            break;
            
        case 'product':
            // Find all orders containing this product
            $orders = db_get_results(
                "SELECT o.*, u.full_name as assigned_name, ol.label_name, ol.color
                 FROM orders o
                 LEFT JOIN users u ON o.assigned_to = u.id
                 LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                 WHERE LOWER(o.products) LIKE ?
                   AND o.created_at BETWEEN ? AND ?
                   AND ($permission_where)
                 ORDER BY o.created_at DESC",
                array_merge(['%' . strtolower($drill_value) . '%', $datetime_from, $datetime_to], $permission_params)
            );
            
            // Calculate product stats across orders
            $product_total = ['qty' => 0, 'revenue' => 0];
            foreach ($orders as $order) {
                $products = json_decode($order['products'], true) ?? [];
                foreach ($products as $product) {
                    $name = $product['name'] ?? $product['product_name'] ?? '';
                    if (stripos($name, $drill_value) !== false) {
                        $product_total['qty'] += $product['qty'] ?? 1;
                        $product_total['revenue'] += ($product['price'] ?? 0) * ($product['qty'] ?? 1);
                    }
                }
            }
            
            $drill_data = [
                'type' => 'product_detail',
                'product_name' => $drill_value,
                'orders' => $orders,
                'totals' => $product_total
            ];
            break;
            
        case 'staff':
            // Get staff details
            $staff = db_get_row("SELECT * FROM users WHERE id = ?", [$drill_value]);
            
            if ($staff) {
                // Get all orders by this staff
                $orders = db_get_results(
                    "SELECT o.*, ol.label_name, ol.color
                     FROM orders o
                     LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                     WHERE o.assigned_to = ?
                       AND o.created_at BETWEEN ? AND ?
                     ORDER BY o.created_at DESC",
                    [$drill_value, $datetime_from, $datetime_to]
                );
                
                // Group by status
                $by_status = [];
                foreach ($orders as $order) {
                    $status = $order['label_name'] ?? 'N/A';
                    if (!isset($by_status[$status])) {
                        $by_status[$status] = [
                            'count' => 0,
                            'revenue' => 0,
                            'color' => $order['color']
                        ];
                    }
                    $by_status[$status]['count']++;
                    $by_status[$status]['revenue'] += $order['total_amount'];
                }
                
                $drill_data = [
                    'type' => 'staff_detail',
                    'staff' => $staff,
                    'orders' => $orders,
                    'by_status' => $by_status
                ];
            }
            break;
    }
    
    $report_data['drill'] = $drill_data;
}

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?></title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .kpi-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        .kpi-card:hover { 
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        .kpi-value { font-size: 2rem; font-weight: bold; }
        .kpi-label { opacity: 0.9; font-size: 0.9rem; }
        .kpi-change { font-size: 0.8rem; margin-top: 5px; }
        
        .clickable { cursor: pointer; transition: background 0.2s; }
        .clickable:hover { background: #f8f9fa; }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            color: white;
            cursor: pointer;
            display: inline-block;
            margin: 2px;
            font-size: 0.9rem;
            transition: transform 0.2s;
        }
        .status-badge:hover { transform: scale(1.1); }
        
        .drill-breadcrumb {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        
        .tab-label {
            display: inline-block;
            padding: 8px 16px;
            margin: 0 2px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        .tab-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            color: white;
            text-decoration: none;
        }
        
        .metric-card {
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .metric-card:hover {
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-body">
            <h4><i class="fas fa-filter"></i> Bộ lọc Báo cáo</h4>
            
            <!-- Dynamic Tabs from Database -->
            <div class="mb-3" style="overflow-x: auto; white-space: nowrap;">
                <a href="?filter_type=overview" 
                   class="tab-label <?= $filter_type == 'overview' ? 'active' : '' ?>"
                   style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <i class="fas fa-chart-pie"></i> Tổng quan
                </a>
                
                <?php foreach ($order_labels as $label): ?>
                <a href="?filter_type=by_label&label=<?= $label['label_key'] ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>"
                   class="tab-label <?= ($filter_type == 'by_label' && $selected_label == $label['label_key']) ? 'active' : '' ?>"
                   style="background: <?= $label['color'] ?>;">
                    <i class="fas <?= $label['icon'] ?>"></i>
                    <?= htmlspecialchars($label['label_name']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            
            <!-- Date Range Filter -->
            <form method="GET" class="row g-3 mt-2">
                <input type="hidden" name="filter_type" value="<?= $filter_type ?>">
                <?php if ($selected_label): ?>
                <input type="hidden" name="label" value="<?= $selected_label ?>">
                <?php endif; ?>
                
                <div class="col-md-3">
                    <label>Từ ngày:</label>
                    <div class="input-group">
                        <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
                        <input type="time" class="form-control" name="time_from" value="<?= $time_from ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <label>Đến ngày:</label>
                    <div class="input-group">
                        <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
                        <input type="time" class="form-control" name="time_to" value="<?= $time_to ?>">
                    </div>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Xem báo cáo
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Drill-down Breadcrumb -->
    <?php if ($drill_type && isset($report_data['drill'])): ?>
    <div class="drill-breadcrumb">
        <a href="?filter_type=<?= $filter_type ?>&date_from=<?= $date_from ?>&date_to=<?= $date_to ?>" 
           class="btn btn-sm btn-outline-primary">
            <i class="fas fa-arrow-left"></i> Quay lại
        </a>
        <span class="ms-3">
            <strong>Chi tiết:</strong> 
            <?php
            if ($drill_type == 'status') {
                echo $report_data['drill']['label']['label_name'] ?? $drill_value;
            } elseif ($drill_type == 'product') {
                echo "Sản phẩm: " . htmlspecialchars($drill_value);
            } elseif ($drill_type == 'staff') {
                echo "Nhân viên: " . ($report_data['drill']['staff']['full_name'] ?? 'N/A');
            }
            ?>
        </span>
    </div>
    <?php endif; ?>
    
    <!-- Report Content -->
    <?php if ($report_data['type'] == 'overview'): ?>
    
    <!-- KPI Cards Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="kpi-card">
                <div class="kpi-label">TỔNG ĐƠN HÀNG</div>
                <div class="kpi-value"><?= number_format($report_data['kpis']['total_orders']) ?></div>
                <div class="kpi-change">
                    <i class="fas fa-clock"></i> 
                    Cập nhật: <?= date('H:i', strtotime($report_data['kpis']['last_order_time'] ?? 'now')) ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%)">
                <div class="kpi-label">KHÁCH HÀNG</div>
                <div class="kpi-value"><?= number_format($report_data['kpis']['unique_customers']) ?></div>
                <div class="kpi-change">
                    <i class="fas fa-users"></i> Khách hàng duy nhất
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)">
                <div class="kpi-label">DOANH THU</div>
                <div class="kpi-value"><?= format_money($report_data['kpis']['total_revenue']) ?></div>
                <div class="kpi-change">
                    TB/đơn: <?= format_money($report_data['kpis']['avg_order_value']) ?>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)">
                <div class="kpi-label">CUỘC GỌI</div>
                <div class="kpi-value"><?= number_format($report_data['kpis']['total_calls']) ?></div>
                <div class="kpi-change">
                    TB: <?= number_format($report_data['kpis']['avg_calls_per_order'], 1) ?> cuộc/đơn
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content Grid -->
    <div class="row">
        <!-- Status Distribution -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-tags"></i> Phân bố theo trạng thái</h5>
                </div>
                <div class="card-body">
                    <?php foreach ($report_data['status_dist'] as $status): if ($status['count'] > 0): ?>
                    <div class="metric-card">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="status-badge" 
                                      style="background: <?= $status['color'] ?>"
                                      onclick="drillDown('status', '<?= $status['label_key'] ?>')">
                                    <i class="fas <?= $status['icon'] ?>"></i>
                                    <?= htmlspecialchars($status['label_name']) ?></span>
                                <?= number_format($status['count']) ?> đơn
                                <small class="text-muted">(<?= $status['percentage'] ?>%)</small>
                            </div>
                            <div class="text-end">
                                <strong><?= format_money($status['revenue']) ?></strong><br>
                                <small class="text-muted">TB: <?= format_money($status['avg_amount']) ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endif; endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Top Products -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-box"></i> Top 10 Sản phẩm</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>STT</th>
                                    <th>Sản phẩm</th>
                                    <th>SL</th>
                                    <th>Đơn</th>
                                    <th>Doanh thu</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $i = 1; foreach ($top_products as $key => $product): ?>
                                <tr class="clickable" onclick="drillDown('product', '<?= htmlspecialchars($product['name']) ?>')">
                                    <td><?= $i++ ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($product['name']) ?></strong>
                                        <?php if ($product['sku']): ?>
                                            <br><code class="small"><?= $product['sku'] ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= number_format($product['qty']) ?></td>
                                    <td><?= number_format($product['orders']) ?></td>
                                    <td class="text-nowrap"><?= format_money($product['revenue']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Staff Performance -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-users"></i> Hiệu suất nhân viên</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Nhân viên</th>
                            <th>Vai trò</th>
                            <th>Số đơn</th>
                            <th>Doanh thu</th>
                            <th>TB/đơn</th>
                            <th>Cuộc gọi</th>
                            <th>Trạng thái xử lý</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data['staff_performance'] as $staff): ?>
                        <tr class="clickable" onclick="drillDown('staff', <?= $staff['id'] ?>)">
                            <td><strong><?= htmlspecialchars($staff['full_name']) ?></strong></td>
                            <td><span class="badge bg-secondary"><?= $staff['role'] ?></span></td>
                            <td><?= number_format($staff['total_orders']) ?></td>
                            <td><?= format_money($staff['total_revenue']) ?></td>
                            <td><?= format_money($staff['avg_order']) ?></td>
                            <td><?= number_format($staff['total_calls']) ?></td>
                            <td>
                                <small><?= str_replace(',', ', ', $staff['statuses']) ?></small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Xu hướng theo ngày</h5>
                </div>
                <div class="card-body" style="height: 300px">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Tỷ lệ trạng thái</h5>
                </div>
                <div class="card-body" style="height: 300px">
                    <canvas id="statusPieChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <?php elseif ($report_data['type'] == 'by_label'): ?>
    <!-- Report by specific label -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="metric-card text-center">
                <h3><?= number_format($report_data['stats']['total_orders']) ?></h3>
                <small>Tổng đơn</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card text-center">
                <h3><?= format_money($report_data['stats']['total_revenue']) ?></h3>
                <small>Doanh thu</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card text-center">
                <h3><?= number_format($report_data['stats']['unique_customers']) ?></h3>
                <small>Khách hàng</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="metric-card text-center">
                <h3><?= number_format($report_data['stats']['staff_count']) ?></h3>
                <small>Nhân viên</small>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">
                Danh sách đơn hàng: 
                <span class="badge" style="background: <?= $report_data['label_info']['color'] ?>">
                    <?= htmlspecialchars($report_data['label_info']['label_name']) ?>
                </span>
            </h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Mã đơn</th>
                            <th>Khách hàng</th>
                            <th>SĐT</th>
                            <th>Tổng tiền</th>
                            <th>Nhân viên</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($report_data['orders'] as $order): ?>
                        <tr>
                            <td>#<?= htmlspecialchars($order['order_number']) ?></td>
                            <td><?= htmlspecialchars($order['customer_name']) ?></td>
                            <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                            <td><?= format_money($order['total_amount']) ?></td>
                            <td><?= htmlspecialchars($order['assigned_name'] ?? 'Chưa phân') ?></td>
                            <td><?= format_date($order['created_at']) ?></td>
                            <td>
                                <a href="order-detail.php?id=<?= $order['id'] ?>" 
                                   class="btn btn-sm btn-primary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Drill-down Details -->
    <?php if (isset($report_data['drill'])): ?>
        <?php if ($report_data['drill']['type'] == 'staff_detail'): ?>
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Thông tin nhân viên</h5>
                    </div>
                    <div class="card-body">
                        <h4><?= htmlspecialchars($report_data['drill']['staff']['full_name']) ?></h4>
                        <p>Vai trò: <strong><?= $report_data['drill']['staff']['role'] ?></strong></p>
                        <p>Tổng đơn: <strong><?= count($report_data['drill']['orders']) ?></strong></p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5>Phân bố trạng thái</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($report_data['drill']['by_status'] as $status => $data): ?>
                        <div class="mb-2">
                            <span class="badge" style="background: <?= $data['color'] ?>">
                                <?= $status ?>
                            </span>
                            <strong><?= $data['count'] ?> đơn</strong> - 
                            <?= format_money($data['revenue']) ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Orders Table for drill-down -->
        <?php if (!empty($report_data['drill']['orders'])): ?>
        <div class="card">
            <div class="card-header">
                <h5>Chi tiết đơn hàng</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Mã đơn</th>
                                <th>Khách hàng</th>
                                <th>Tổng tiền</th>
                                <th>Trạng thái</th>
                                <th>Ngày</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($report_data['drill']['orders'] as $order): ?>
                            <tr>
                                <td>#<?= htmlspecialchars($order['order_number']) ?></td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= format_money($order['total_amount']) ?></td>
                                <td>
                                    <span class="badge" style="background: <?= $order['color'] ?? '#6c757d' ?>">
                                        <?= htmlspecialchars($order['label_name'] ?? 'N/A') ?>
                                    </span>
                                </td>
                                <td><?= format_date($order['created_at'], 'd/m/Y H:i') ?></td>
                                <td>
                                    <a href="order-detail.php?id=<?= $order['id'] ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Drill-down navigation
function drillDown(type, value) {
    const params = new URLSearchParams(window.location.search);
    params.set('drill', type);
    params.set('drill_value', value);
    window.location.href = '?' + params.toString();
}

// Render charts
<?php if ($report_data['type'] == 'overview' && !empty($report_data['daily_trend'])): ?>
// Daily trend chart
const trendCtx = document.getElementById('trendChart');
if (trendCtx) {
    const trendData = <?= json_encode($report_data['daily_trend']) ?>;
    new Chart(trendCtx.getContext('2d'), {
        type: 'line',
        data: {
            labels: trendData.map(d => d.date),
            datasets: [{
                label: 'Đơn hàng',
                data: trendData.map(d => d.orders),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102,126,234,0.1)',
                yAxisID: 'y'
            }, {
                label: 'Doanh thu (triệu)',
                data: trendData.map(d => d.revenue / 1000000),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40,167,69,0.1)',
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { type: 'linear', display: true, position: 'left' },
                y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }}
            }
        }
    });
}

// Status pie chart
const pieCtx = document.getElementById('statusPieChart');
if (pieCtx) {
    const statusData = <?= json_encode($report_data['status_dist']) ?>;
    new Chart(pieCtx.getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: statusData.map(s => s.label_name),
            datasets: [{
                data: statusData.map(s => s.count),
                backgroundColor: statusData.map(s => s.color)
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { padding: 10, font: { size: 11 }}}
            }
        }
    });
}
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>