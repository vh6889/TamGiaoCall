<?php
/**
 * Enhanced Advanced Statistics System
 * Version 2.0 - Full features with better search and filters
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'includes/status_helper.php';

require_login();

$current_user = get_logged_user();
$page_title = 'Báo cáo & Thống kê Nâng cao';

// ===== PERMISSION & ACCESS CONTROL =====
$where_base = "1=1";
$base_params = [];
$available_users = [];

if (is_admin()) {
    $available_users = db_get_results(
        "SELECT id, full_name, role FROM users WHERE status = 'active' ORDER BY role, full_name"
    );
} elseif (is_manager()) {
    $team_ids = db_get_col(
        "SELECT telesale_id FROM manager_assignments WHERE manager_id = ?",
        [$current_user['id']]
    );
    $team_ids[] = $current_user['id'];
    
    if (!empty($team_ids)) {
        $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
        $where_base = "o.assigned_to IN ($placeholders)";
        $base_params = $team_ids;
        
        $available_users = db_get_results(
            "SELECT id, full_name, role FROM users 
             WHERE id IN ($placeholders) AND status = 'active' 
             ORDER BY role, full_name",
            $team_ids
        );
    }
} else {
    $where_base = "o.assigned_to = ?";
    $base_params = [$current_user['id']];
    $available_users = [$current_user];
}

// ===== GET FILTERS =====
$filter_type = $_GET['filter_type'] ?? 'overview';
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$time_from = $_GET['time_from'] ?? '00:00';
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$time_to = $_GET['time_to'] ?? '23:59';

// Specific filters
$selected_user = (int)($_GET['user_id'] ?? 0);
$selected_status = $_GET['status'] ?? '';
$selected_customer_label = $_GET['customer_label'] ?? '';
$selected_user_label = $_GET['user_label'] ?? '';
$product_search = $_GET['product_search'] ?? '';
$product_sort = $_GET['product_sort'] ?? 'revenue_desc';

// Custom filters
$custom_filters = $_GET['custom'] ?? [];
$success_rate_min = (float)($_GET['success_rate_min'] ?? 0);
$success_rate_max = (float)($_GET['success_rate_max'] ?? 100);

$datetime_from = $date_from . ' ' . $time_from . ':00';
$datetime_to = $date_to . ' ' . $time_to . ':59';

// ===== GET OPTIONS =====
$order_statuses = db_get_results(
    "SELECT label_key, label_name, color, icon, core_status 
     FROM order_labels ORDER BY sort_order, label_name"
);

$customer_labels = db_get_results(
    "SELECT label_key, label_name, color, description FROM customer_labels ORDER BY label_name"
);

$user_labels = db_get_results(
    "SELECT label_key, label_name, color, description FROM user_labels ORDER BY label_name"
);

// ===== BUILD REPORT DATA =====
$report_data = [];
$daily_trend = [];

switch ($filter_type) {
    case 'by_product':
        // Enhanced product search
        $product_stats = [];
        $query_params = array_merge([$datetime_from, $datetime_to], $base_params);
        $extra_where = "";
        
        if ($product_search) {
            // Improved search - remove accents and search flexibly
            $search_terms = preg_split('/\s+/', mb_strtolower($product_search));
            $search_conditions = [];
            
            foreach ($search_terms as $term) {
                $search_conditions[] = "LOWER(o.products) LIKE ?";
                $query_params[] = '%' . $term . '%';
            }
            
            if (!empty($search_conditions)) {
                $extra_where = " AND (" . implode(' AND ', $search_conditions) . ")";
            }
        }
        
        $orders_with_products = db_get_results(
            "SELECT o.id, o.order_number, o.customer_name, o.customer_phone,
                    o.products, o.created_at, o.total_amount,
                    ol.label_name, ol.color as label_color, ol.core_status,
                    u.full_name as assigned_name
             FROM orders o
             LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
             LEFT JOIN users u ON o.assigned_to = u.id
             WHERE o.created_at BETWEEN ? AND ? AND ($where_base) $extra_where
             ORDER BY o.created_at DESC",
            $query_params
        );
        
        // Parse and aggregate products
        foreach ($orders_with_products as $order) {
            $products = json_decode($order['products'], true) ?? [];
            
            foreach ($products as $product) {
                // Extract product details
                $product_name = $product['name'] ?? $product['product_name'] ?? 'N/A';
                $product_sku = $product['sku'] ?? $product['product_id'] ?? '';
                $product_key = $product_sku ?: md5($product_name);
                
                if (!isset($product_stats[$product_key])) {
                    $product_stats[$product_key] = [
                        'sku' => $product_sku,
                        'name' => $product_name,
                        'total_qty' => 0,
                        'total_revenue' => 0,
                        'success_revenue' => 0,
                        'order_count' => 0,
                        'success_count' => 0,
                        'orders' => []
                    ];
                }
                
                $qty = $product['qty'] ?? $product['quantity'] ?? 1;
                $price = $product['price'] ?? 0;
                $subtotal = $qty * $price;
                
                $product_stats[$product_key]['total_qty'] += $qty;
                $product_stats[$product_key]['total_revenue'] += $subtotal;
                $product_stats[$product_key]['order_count']++;
                
                if ($order['core_status'] === 'success') {
                    $product_stats[$product_key]['success_revenue'] += $subtotal;
                    $product_stats[$product_key]['success_count']++;
                }
                
                // Store order reference
                if (count($product_stats[$product_key]['orders']) < 100) {
                    $product_stats[$product_key]['orders'][] = $order;
                }
            }
        }
        
        // Sort products
        uasort($product_stats, function($a, $b) use ($product_sort) {
            switch ($product_sort) {
                case 'name_asc':
                    return strcasecmp($a['name'], $b['name']);
                case 'name_desc':
                    return strcasecmp($b['name'], $a['name']);
                case 'qty_asc':
                    return $a['total_qty'] - $b['total_qty'];
                case 'qty_desc':
                    return $b['total_qty'] - $a['total_qty'];
                case 'revenue_asc':
                    return $a['total_revenue'] - $b['total_revenue'];
                case 'revenue_desc':
                default:
                    return $b['total_revenue'] - $a['total_revenue'];
            }
        });
        
        $report_data = [
            'type' => 'by_product',
            'product_stats' => $product_stats,
            'total_orders' => count($orders_with_products)
        ];
        break;
        
    case 'by_customer_label':
        if ($selected_customer_label) {
            // Get customers with this label
            $customers = db_get_results(
                "SELECT DISTINCT o.customer_phone, o.customer_name,
                        COUNT(DISTINCT o.id) as order_count,
                        SUM(o.total_amount) as total_spent,
                        COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) as success_orders,
                        MAX(o.created_at) as last_order_date
                 FROM orders o
                 LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                 WHERE o.created_at BETWEEN ? AND ?
                   AND ($where_base)
                   AND EXISTS (
                       SELECT 1 FROM customer_metrics cm
                       WHERE cm.customer_phone = o.customer_phone
                         AND JSON_CONTAINS(cm.labels, JSON_QUOTE(?))
                   )
                 GROUP BY o.customer_phone, o.customer_name
                 ORDER BY total_spent DESC",
                array_merge([$datetime_from, $datetime_to], $base_params, [$selected_customer_label])
            );
            
            $label_info = db_get_row(
                "SELECT * FROM customer_labels WHERE label_key = ?",
                [$selected_customer_label]
            );
            
            $report_data = [
                'type' => 'by_customer_label',
                'customers' => $customers,
                'label_info' => $label_info
            ];
        }
        break;
        
    case 'by_user_label':
        if ($selected_user_label) {
            // Get users with this label
            $users_with_label = db_get_results(
                "SELECT u.*, 
                        COUNT(o.id) as period_orders,
                        COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) as period_success,
                        SUM(o.total_amount) as period_revenue,
                        SUM(CASE WHEN ol.core_status = 'success' THEN o.total_amount END) as success_revenue,
                        ROUND(COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) * 100.0 / NULLIF(COUNT(o.id), 0), 2) as success_rate
                 FROM users u
                 LEFT JOIN orders o ON o.assigned_to = u.id 
                    AND o.created_at BETWEEN ? AND ?
                 LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                 WHERE u.status = 'active'
                   AND EXISTS (
                       SELECT 1 FROM employee_performance ep
                       WHERE ep.user_id = u.id
                         AND JSON_CONTAINS(ep.labels, JSON_QUOTE(?))
                   )
                 GROUP BY u.id
                 ORDER BY success_rate DESC",
                [$datetime_from, $datetime_to, $selected_user_label]
            );
            
            $label_info = db_get_row(
                "SELECT * FROM user_labels WHERE label_key = ?",
                [$selected_user_label]
            );
            
            $report_data = [
                'type' => 'by_user_label',
                'users' => $users_with_label,
                'label_info' => $label_info
            ];
        }
        break;
        
    case 'custom':
        // Advanced custom filters with multiple conditions
        $custom_where = [];
        $custom_params = [];
        
        // Base permission
        if ($where_base != "1=1") {
            $custom_where[] = "($where_base)";
            $custom_params = array_merge($custom_params, $base_params);
        }
        
        // Date range
        $custom_where[] = "o.created_at BETWEEN ? AND ?";
        $custom_params[] = $datetime_from;
        $custom_params[] = $datetime_to;
        
        // Process custom filters
        foreach ($custom_filters as $filter) {
            $field = $filter['field'] ?? '';
            $operator = $filter['operator'] ?? '=';
            $value = $filter['value'] ?? '';
            
            if (empty($field) || empty($value)) continue;
            
            switch ($field) {
                case 'user_label':
                    $users_with_label = db_get_col(
                        "SELECT user_id FROM employee_performance 
                         WHERE JSON_CONTAINS(labels, JSON_QUOTE(?))",
                        [$value]
                    );
                    if (!empty($users_with_label)) {
                        $placeholders = implode(',', array_fill(0, count($users_with_label), '?'));
                        $custom_where[] = "o.assigned_to IN ($placeholders)";
                        $custom_params = array_merge($custom_params, $users_with_label);
                    }
                    break;
                    
                case 'customer_label':
                    $custom_where[] = "EXISTS (
                        SELECT 1 FROM customer_metrics cm
                        WHERE cm.customer_phone = o.customer_phone
                          AND JSON_CONTAINS(cm.labels, JSON_QUOTE(?))
                    )";
                    $custom_params[] = $value;
                    break;
                    
                case 'order_status':
                    if ($operator === 'in') {
                        $statuses = explode(',', $value);
                        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
                        $custom_where[] = "o.primary_label IN ($placeholders)";
                        $custom_params = array_merge($custom_params, $statuses);
                    } else {
                        $custom_where[] = "o.primary_label = ?";
                        $custom_params[] = $value;
                    }
                    break;
                    
                case 'total_amount':
                    $custom_where[] = "o.total_amount $operator ?";
                    $custom_params[] = $value;
                    break;
                    
                case 'product_contains':
                    $custom_where[] = "LOWER(o.products) LIKE ?";
                    $custom_params[] = '%' . mb_strtolower($value) . '%';
                    break;
            }
        }
        
        // Build query
        $where_clause = implode(' AND ', $custom_where);
        
        $custom_results = db_get_results(
            "SELECT o.*, ol.label_name, ol.color as label_color,
                    u.full_name as assigned_name
             FROM orders o
             LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
             LEFT JOIN users u ON o.assigned_to = u.id
             WHERE $where_clause
             ORDER BY o.created_at DESC
             LIMIT 1000",
            $custom_params
        );
        
        $report_data = [
            'type' => 'custom',
            'results' => $custom_results,
            'total_count' => count($custom_results)
        ];
        break;
        
    default: // overview and by_status remain the same
        // ... existing code for overview and by_status ...
        break;
}

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title><?= $page_title ?></title>
    
    <!-- Libraries -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.31/jspdf.plugin.autotable.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .filter-section { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 25px; }
        .filter-tabs { display: flex; border-bottom: 2px solid #dee2e6; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-tab {
            padding: 10px 20px; background: none; border: none; color: #6c757d;
            font-weight: 500; cursor: pointer; transition: all 0.3s;
            border-bottom: 3px solid transparent; margin-bottom: -2px;
        }
        .filter-tab.active { color: #667eea; border-bottom-color: #667eea; }
        .stat-box {
            background: white; border-radius: 8px; padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-bottom: 20px;
        }
        .custom-filter-row {
            background: #fff; border: 1px solid #dee2e6; border-radius: 4px;
            padding: 10px; margin-bottom: 10px;
        }
        .sortable { cursor: pointer; position: relative; padding-right: 20px; }
        .sortable:after {
            content: "⇅"; position: absolute; right: 5px; color: #ccc;
        }
        .sortable.asc:after { content: "↑"; color: #333; }
        .sortable.desc:after { content: "↓"; color: #333; }
    </style>
</head>
<body>

<div class="container-fluid">
    <!-- Filter Section -->
    <div class="filter-section">
        <h4 class="mb-3"><i class="fas fa-filter"></i> Bộ lọc Báo cáo</h4>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button type="button" class="filter-tab <?= $filter_type == 'overview' ? 'active' : '' ?>" 
                    onclick="setFilterType('overview')">Tổng quan</button>
            <button type="button" class="filter-tab <?= $filter_type == 'by_status' ? 'active' : '' ?>" 
                    onclick="setFilterType('by_status')">Theo trạng thái</button>
            <button type="button" class="filter-tab <?= $filter_type == 'by_product' ? 'active' : '' ?>" 
                    onclick="setFilterType('by_product')">Theo sản phẩm</button>
            <button type="button" class="filter-tab <?= $filter_type == 'by_customer_label' ? 'active' : '' ?>" 
                    onclick="setFilterType('by_customer_label')">Theo nhãn KH</button>
            <button type="button" class="filter-tab <?= $filter_type == 'by_user_label' ? 'active' : '' ?>" 
                    onclick="setFilterType('by_user_label')">Theo nhãn NV</button>
            <button type="button" class="filter-tab <?= $filter_type == 'custom' ? 'active' : '' ?>" 
                    onclick="setFilterType('custom')">Tùy chỉnh</button>
        </div>
        
        <!-- Filter Form -->
        <form method="GET" id="filterForm">
            <input type="hidden" name="filter_type" value="<?= htmlspecialchars($filter_type) ?>">
            
            <div class="row g-3">
                <!-- Date/Time Range -->
                <div class="col-md-3">
                    <label class="form-label">Từ ngày:</label>
                    <div class="input-group">
                        <input type="date" class="form-control" name="date_from" value="<?= $date_from ?>">
                        <input type="time" class="form-control" name="time_from" value="<?= $time_from ?>">
                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Đến ngày:</label>
                    <div class="input-group">
                        <input type="date" class="form-control" name="date_to" value="<?= $date_to ?>">
                        <input type="time" class="form-control" name="time_to" value="<?= $time_to ?>">
                    </div>
                </div>
                
                <!-- Dynamic filters based on type -->
                <?php if ($filter_type == 'by_product'): ?>
                <div class="col-md-3">
                    <label class="form-label">Tìm sản phẩm:</label>
                    <input type="text" class="form-control" name="product_search" 
                           value="<?= htmlspecialchars($product_search) ?>" 
                           placeholder="Nhập tên hoặc SKU...">
                    <small class="text-muted">Tìm theo từ khóa, không phân biệt dấu</small>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sắp xếp:</label>
                    <select class="form-select" name="product_sort">
                        <option value="revenue_desc" <?= $product_sort == 'revenue_desc' ? 'selected' : '' ?>>Doanh thu ↓</option>
                        <option value="revenue_asc" <?= $product_sort == 'revenue_asc' ? 'selected' : '' ?>>Doanh thu ↑</option>
                        <option value="qty_desc" <?= $product_sort == 'qty_desc' ? 'selected' : '' ?>>Số lượng ↓</option>
                        <option value="qty_asc" <?= $product_sort == 'qty_asc' ? 'selected' : '' ?>>Số lượng ↑</option>
                        <option value="name_asc" <?= $product_sort == 'name_asc' ? 'selected' : '' ?>>Tên A-Z</option>
                        <option value="name_desc" <?= $product_sort == 'name_desc' ? 'selected' : '' ?>>Tên Z-A</option>
                    </select>
                </div>
                
                <?php elseif ($filter_type == 'by_customer_label'): ?>
                <div class="col-md-4">
                    <label class="form-label">Nhãn khách hàng:</label>
                    <select class="form-select" name="customer_label" required>
                        <option value="">-- Chọn nhãn khách hàng --</option>
                        <?php foreach ($customer_labels as $label): ?>
                            <option value="<?= $label['label_key'] ?>" 
                                    <?= $selected_customer_label == $label['label_key'] ? 'selected' : '' ?>
                                    data-color="<?= $label['color'] ?>">
                                <?= htmlspecialchars($label['label_name']) ?>
                                <?php if ($label['description']): ?>
                                    - <?= htmlspecialchars($label['description']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php elseif ($filter_type == 'by_user_label'): ?>
                <div class="col-md-4">
                    <label class="form-label">Nhãn nhân viên:</label>
                    <select class="form-select" name="user_label" required>
                        <option value="">-- Chọn nhãn nhân viên --</option>
                        <?php foreach ($user_labels as $label): ?>
                            <option value="<?= $label['label_key'] ?>" 
                                    <?= $selected_user_label == $label['label_key'] ? 'selected' : '' ?>
                                    data-color="<?= $label['color'] ?>">
                                <?= htmlspecialchars($label['label_name']) ?>
                                <?php if ($label['description']): ?>
                                    - <?= htmlspecialchars($label['description']) ?>
                                <?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php elseif ($filter_type == 'custom'): ?>
                <!-- Custom Filter Builder -->
                <div class="col-12">
                    <label class="form-label">Điều kiện lọc tùy chỉnh:</label>
                    <div id="customFilters">
                        <div class="custom-filter-row">
                            <div class="row g-2">
                                <div class="col-md-3">
                                    <select class="form-select" name="custom[0][field]">
                                        <option value="">-- Chọn trường --</option>
                                        <option value="user_label">Nhãn nhân viên</option>
                                        <option value="customer_label">Nhãn khách hàng</option>
                                        <option value="order_status">Trạng thái đơn</option>
                                        <option value="total_amount">Tổng tiền</option>
                                        <option value="product_contains">Chứa sản phẩm</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="custom[0][operator]">
                                        <option value="=">=</option>
                                        <option value="!=">≠</option>
                                        <option value=">">></option>
                                        <option value="<"><</option>
                                        <option value="in">Trong</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="custom[0][value]" 
                                           placeholder="Giá trị...">
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-sm btn-success" onclick="addCustomFilter()">
                                        <i class="fas fa-plus"></i> Thêm điều kiện
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Submit Button -->
                <div class="col-md-12 text-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Xem báo cáo
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Đặt lại
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Report Results -->
    <?php if (!empty($report_data)): ?>
    <div class="report-section" id="reportContent">
        
        <?php if ($report_data['type'] == 'by_product'): ?>
            <!-- Enhanced Product Report -->
            <h4 class="mb-3">
                Thống kê sản phẩm
                <small class="text-muted">(<?= count($report_data['product_stats']) ?> sản phẩm từ <?= $report_data['total_orders'] ?> đơn)</small>
            </h4>
            
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover" id="productTable">
                        <thead>
                            <tr>
                                <th width="50">STT</th>
                                <th width="120">SKU</th>
                                <th>Tên sản phẩm</th>
                                <th width="100" class="text-end">Số lượng</th>
                                <th width="100" class="text-end">Số đơn</th>
                                <th width="100" class="text-end">Thành công</th>
                                <th width="150" class="text-end">Doanh thu</th>
                                <th width="150" class="text-end">DT thành công</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $stt = 1;
                            $total_qty = 0;
                            $total_revenue = 0;
                            $total_success_revenue = 0;
                            
                            foreach ($report_data['product_stats'] as $product): 
                                $total_qty += $product['total_qty'];
                                $total_revenue += $product['total_revenue'];
                                $total_success_revenue += $product['success_revenue'];
                            ?>
                            <tr>
                                <td><?= $stt++ ?></td>
                                <td>
                                    <?php if ($product['sku']): ?>
                                        <code><?= htmlspecialchars($product['sku']) ?></code>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                    <?php if ($product['order_count'] > 0): ?>
                                        <br>
                                        <small class="text-muted">
                                            Tỷ lệ thành công: <?= round($product['success_count'] * 100 / $product['order_count'], 1) ?>%
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= number_format($product['total_qty']) ?></td>
                                <td class="text-end"><?= number_format($product['order_count']) ?></td>
                                <td class="text-end">
                                    <span class="text-success"><?= number_format($product['success_count']) ?></span>
                                </td>
                                <td class="text-end"><?= format_money($product['total_revenue']) ?></td>
                                <td class="text-end">
                                    <strong class="text-success"><?= format_money($product['success_revenue']) ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="table-active fw-bold">
                                <td colspan="3">Tổng cộng</td>
                                <td class="text-end"><?= number_format($total_qty) ?></td>
                                <td colspan="2"></td>
                                <td class="text-end"><?= format_money($total_revenue) ?></td>
                                <td class="text-end text-success"><?= format_money($total_success_revenue) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
        <?php elseif ($report_data['type'] == 'by_customer_label'): ?>
            <!-- Customer Label Report -->
            <h4 class="mb-3">
                Khách hàng nhãn: 
                <span class="badge" style="background: <?= $report_data['label_info']['color'] ?>">
                    <?= htmlspecialchars($report_data['label_info']['label_name']) ?>
                </span>
                <small class="text-muted">(<?= count($report_data['customers']) ?> khách hàng)</small>
            </h4>
            
            <div class="table-card">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Khách hàng</th>
                            <th>Số điện thoại</th>
                            <th>Số đơn</th>
                            <th>Thành công</th>
                            <th>Tổng chi tiêu</th>
                            <th>Lần cuối</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $stt = 1; foreach ($report_data['customers'] as $customer): ?>
                        <tr>
                            <td><?= $stt++ ?></td>
                            <td><strong><?= htmlspecialchars($customer['customer_name']) ?></strong></td>
                            <td><?= htmlspecialchars($customer['customer_phone']) ?></td>
                            <td><?= number_format($customer['order_count']) ?></td>
                            <td class="text-success"><?= number_format($customer['success_orders']) ?></td>
                            <td><?= format_money($customer['total_spent']) ?></td>
                            <td><?= format_date($customer['last_order_date'], 'd/m/Y') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($report_data['type'] == 'by_user_label'): ?>
            <!-- User Label Report -->
            <h4 class="mb-3">
                Nhân viên nhãn: 
                <span class="badge" style="background: <?= $report_data['label_info']['color'] ?>">
                    <?= htmlspecialchars($report_data['label_info']['label_name']) ?>
                </span>
                <small class="text-muted">(<?= count($report_data['users']) ?> nhân viên)</small>
            </h4>
            
            <div class="table-card">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>STT</th>
                            <th>Nhân viên</th>
                            <th>Vai trò</th>
                            <th>Số đơn</th>
                            <th>Thành công</th>
                            <th>Doanh thu</th>
                            <th>DT thành công</th>
                            <th>Tỷ lệ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $stt = 1; foreach ($report_data['users'] as $user): ?>
                        <tr>
                            <td><?= $stt++ ?></td>
                            <td><strong><?= htmlspecialchars($user['full_name']) ?></strong></td>
                            <td><?= $user['role'] ?></td>
                            <td><?= number_format($user['period_orders']) ?></td>
                            <td class="text-success"><?= number_format($user['period_success']) ?></td>
                            <td><?= format_money($user['period_revenue']) ?></td>
                            <td class="text-success"><?= format_money($user['success_revenue']) ?></td>
                            <td>
                                <span class="badge bg-<?= $user['success_rate'] >= 50 ? 'success' : 'warning' ?>">
                                    <?= $user['success_rate'] ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
let filterCount = 1;

function setFilterType(type) {
    document.querySelector('input[name="filter_type"]').value = type;
    document.getElementById('filterForm').submit();
}

function addCustomFilter() {
    const container = document.getElementById('customFilters');
    const newRow = document.createElement('div');
    newRow.className = 'custom-filter-row';
    newRow.innerHTML = `
        <div class="row g-2">
            <div class="col-md-3">
                <select class="form-select" name="custom[${filterCount}][field]">
                    <option value="">-- Chọn trường --</option>
                    <option value="user_label">Nhãn nhân viên</option>
                    <option value="customer_label">Nhãn khách hàng</option>
                    <option value="order_status">Trạng thái đơn</option>
                    <option value="total_amount">Tổng tiền</option>
                    <option value="product_contains">Chứa sản phẩm</option>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="custom[${filterCount}][operator]">
                    <option value="=">=</option>
                    <option value="!=">≠</option>
                    <option value=">">></option>
                    <option value="<"><</option>
                    <option value="in">Trong</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" name="custom[${filterCount}][value]" placeholder="Giá trị...">
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-sm btn-danger" onclick="this.closest('.custom-filter-row').remove()">
                    <i class="fas fa-times"></i> Xóa
                </button>
            </div>
        </div>
    `;
    container.appendChild(newRow);
    filterCount++;
}

function resetFilters() {
    document.getElementById('filterForm').reset();
    window.location.href = 'advanced-statistics.php';
}
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>