<?php
/**
 * Professional Statistics Dashboard
 * Sử dụng đầy đủ module Statistics với phân quyền role-based
 * Version 4.0
 */
define('TSM_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
// Include autoloader an toàn với require_once
require_once __DIR__ . '/modules/statistics/statistics_autoload.php';

require_login();

$current_user = get_logged_user();
$page_title = 'Thống kê & Phân tích Chuyên nghiệp';

// Initialize database connection for module
$db = get_db_connection();

// Get filters from request
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$time_from = $_GET['time_from'] ?? '00:00';
$time_to = $_GET['time_to'] ?? '23:59';
$report_type = $_GET['report_type'] ?? 'overview';
$export_format = $_GET['export'] ?? '';
$drill_type = $_GET['drill'] ?? '';
$drill_id = $_GET['drill_id'] ?? '';

// Build datetime range
$datetime_from = $date_from . ' ' . $time_from . ':00';
$datetime_to = $date_to . ' ' . $time_to . ':59';

// Handle export request
if ($export_format) {
    $exporter = null;
    $reportData = [];
    
    switch ($export_format) {
        case 'excel':
            $exporter = new ExcelExporter();
            break;
        case 'csv':
            $exporter = new CSVExporter();
            break;
        case 'pdf':
            $exporter = new PDFExporter();
            break;
    }
    
    if ($exporter) {
        // Get report data based on type
        switch ($report_type) {
            case 'users':
                $report = new UserReport($db);
                $reportData = $report->setDateRange($datetime_from, $datetime_to)->getData()['users'];
                $headers = ['Nhân viên', 'Vai trò', 'Tổng đơn', 'Thành công', 'Doanh thu', 'Tỷ lệ'];
                break;
            case 'products':
                $report = new ProductReport($db);
                $reportData = $report->setDateRange($datetime_from, $datetime_to)->getData()['products'];
                $headers = ['SKU', 'Sản phẩm', 'Số lượng', 'Doanh thu', 'Đơn hàng'];
                break;
            case 'customers':
                $report = new CustomerReport($db);
                $reportData = $report->setDateRange($datetime_from, $datetime_to)->getData()['customers'];
                $headers = ['Khách hàng', 'SĐT', 'Tổng đơn', 'Giá trị', 'Lần cuối'];
                break;
            default:
                $report = new OverviewReport($db);
                $reportData = $report->setDateRange($datetime_from, $datetime_to)->getData()['metrics'];
                $headers = array_keys($reportData);
        }
        
        $exporter->setData($reportData)
                 ->setHeaders($headers)
                 ->setFilename('report_' . $report_type . '_' . date('Y-m-d'))
                 ->setTitle('Báo cáo ' . ucfirst($report_type))
                 ->addMetadata('Người xuất', $current_user['full_name'])
                 ->addMetadata('Thời gian', date('Y-m-d H:i:s'))
                 ->addMetadata('Khoảng thời gian', "$date_from đến $date_to")
                 ->download();
        exit;
    }
}

// Initialize main report - Sử dụng helper function để tạo instance an toàn
$overviewReport = getStatisticsModule('overview', $db);
$overviewReport->setDateRange($datetime_from, $datetime_to);

// Get overview data
$overviewData = $overviewReport->getData();
$metrics = $overviewData['metrics'] ?? [];
$comparison = $overviewData['comparison'] ?? [];
$trends = $overviewData['trends'] ?? [];
$topPerformers = $overviewData['topPerformers'] ?? [];
$distribution = $overviewData['distribution'] ?? [];

// Handle drill-down
$drilldownData = null;
if ($drill_type && $drill_id) {
    try {
        $drilldownHandler = getStatisticsModule('drilldown', $db);
        $drilldownData = $drilldownHandler->process($drill_type, $drill_id, [
            'date_from' => $datetime_from,
            'date_to' => $datetime_to
        ]);
    } catch (Exception $e) {
        // Handle error silently
        $drilldownData = null;
    }
}

// Get specific report data based on type
$reportData = null;
switch ($report_type) {
    case 'users':
        try {
            $userReport = getStatisticsModule('user', $db);
            $reportData = $userReport->setDateRange($datetime_from, $datetime_to)
                                     ->orderBy('success_rate', 'DESC')
                                     ->getData();
        } catch (Exception $e) {
            $reportData = ['users' => []];
        }
        break;
    
    case 'products':
        try {
            $productReport = getStatisticsModule('product', $db);
            $reportData = $productReport->setDateRange($datetime_from, $datetime_to)
                                        ->orderBy('total_revenue', 'DESC')
                                        ->limit(50)
                                        ->getData();
        } catch (Exception $e) {
            $reportData = ['products' => []];
        }
        break;
    
    case 'customers':
        try {
            $customerReport = getStatisticsModule('customer', $db);
            $reportData = $customerReport->setDateRange($datetime_from, $datetime_to)
                                         ->orderBy('total_value', 'DESC')
                                         ->limit(100)
                                         ->getData();
        } catch (Exception $e) {
            $reportData = ['customers' => []];
        }
        break;
    
    case 'orders':
        try {
            $orderReport = getStatisticsModule('order', $db);
            
            // Apply filters if needed
            if (isset($_GET['status_filter'])) {
                try {
                    $filter = getStatisticsModule('filter', $db);
                    $filter->addCondition('primary_label', '=', $_GET['status_filter']);
                    $orderReport->applyFilter($filter);
                } catch (Exception $e) {
                    // Skip filter if error
                }
            }
            
            $reportData = $orderReport->setDateRange($datetime_from, $datetime_to)
                                      ->orderBy('created_at', 'DESC')
                                      ->limit(500)
                                      ->getData();
        } catch (Exception $e) {
            $reportData = ['orders' => []];
        }
        break;
}

include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            --warning-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
        
        .dashboard-header {
            background: var(--primary-gradient);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .metric-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }
        
        .metric-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }
        
        .metric-card.clickable {
            cursor: pointer;
        }
        
        .metric-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }
        
        .metric-card .value {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 5px;
        }
        
        .metric-card .label {
            font-size: 0.9rem;
            color: #636e72;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .metric-card .change {
            position: absolute;
            top: 1rem;
            right: 1rem;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .metric-card .change.positive {
            background: #d4edda;
            color: #155724;
        }
        
        .metric-card .change.negative {
            background: #f8d7da;
            color: #721c24;
        }
        
        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f3f5;
        }
        
        .chart-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3436;
        }
        
        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }
        
        .nav-pills .nav-link {
            border-radius: 25px;
            padding: 10px 25px;
            margin: 0 5px;
            transition: all 0.3s;
        }
        
        .nav-pills .nav-link.active {
            background: var(--primary-gradient);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        
        .badge-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
            font-size: 0.85rem;
        }
        
        .breadcrumb-drill {
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .export-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-export {
            padding: 8px 20px;
            border-radius: 25px;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        /* Loading animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading.show {
            display: flex;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .metric-card .value {
                font-size: 1.5rem;
            }
            
            .dashboard-header {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<!-- Loading overlay -->
<div class="loading" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<div class="container-fluid py-4">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="mb-2">
                    <i class="fas fa-chart-line me-2"></i>
                    <?= $page_title ?>
                </h1>
                <p class="mb-0 opacity-75">
                    Dữ liệu từ <?= date('d/m/Y', strtotime($date_from)) ?> 
                    đến <?= date('d/m/Y', strtotime($date_to)) ?>
                </p>
            </div>
            <div class="col-md-6 text-md-end mt-3 mt-md-0">
                <div class="export-buttons justify-content-md-end">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'excel'])) ?>" 
                       class="btn btn-light btn-export">
                        <i class="fas fa-file-excel text-success me-2"></i>Excel
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
                       class="btn btn-light btn-export">
                        <i class="fas fa-file-csv text-info me-2"></i>CSV
                    </a>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'pdf'])) ?>" 
                       class="btn btn-light btn-export">
                        <i class="fas fa-file-pdf text-danger me-2"></i>PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filter Section -->
    <div class="filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Từ ngày</label>
                <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Giờ</label>
                <input type="time" name="time_from" class="form-control" value="<?= $time_from ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Đến ngày</label>
                <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Giờ</label>
                <input type="time" name="time_to" class="form-control" value="<?= $time_to ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Loại báo cáo</label>
                <select name="report_type" class="form-select">
                    <option value="overview" <?= $report_type == 'overview' ? 'selected' : '' ?>>Tổng quan</option>
                    <option value="users" <?= $report_type == 'users' ? 'selected' : '' ?>>Nhân viên</option>
                    <option value="products" <?= $report_type == 'products' ? 'selected' : '' ?>>Sản phẩm</option>
                    <option value="customers" <?= $report_type == 'customers' ? 'selected' : '' ?>>Khách hàng</option>
                    <option value="orders" <?= $report_type == 'orders' ? 'selected' : '' ?>>Đơn hàng</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-filter me-2"></i>Lọc dữ liệu
                </button>
            </div>
        </form>
    </div>
    
    <!-- Drill-down Breadcrumb -->
    <?php if ($drilldownData && isset($drilldownData['breadcrumbs'])): ?>
    <div class="breadcrumb-drill">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item">
                    <a href="?<?= http_build_query(array_diff_key($_GET, array_flip(['drill', 'drill_id']))) ?>">
                        <i class="fas fa-home"></i> Tổng quan
                    </a>
                </li>
                <?php foreach ($drilldownData['breadcrumbs'] as $crumb): ?>
                <li class="breadcrumb-item active"><?= htmlspecialchars($crumb['label']) ?></li>
                <?php endforeach; ?>
            </ol>
        </nav>
    </div>
    <?php endif; ?>
    
    <!-- Main Metrics Cards -->
    <div class="row mb-4">
        <?php
        // Render metric cards using module
        $metricConfigs = [
            [
                'title' => 'Tổng đơn hàng',
                'value' => $metrics['total_orders'],
                'icon' => 'fa-shopping-cart',
                'color' => 'primary',
                'gradient' => 'var(--primary-gradient)',
                'compare' => $comparison['total_orders'] ?? null,
                'drill' => ['type' => 'metric', 'id' => 'total_orders']
            ],
            [
                'title' => 'Doanh thu',
                'value' => $metrics['total_revenue'],
                'format' => 'money',
                'icon' => 'fa-dollar-sign',
                'color' => 'success',
                'gradient' => 'var(--success-gradient)',
                'compare' => $comparison['total_revenue'] ?? null,
                'drill' => ['type' => 'metric', 'id' => 'total_revenue']
            ],
            [
                'title' => 'Tỷ lệ thành công',
                'value' => $metrics['success_rate'],
                'format' => 'percent',
                'icon' => 'fa-chart-line',
                'color' => 'warning',
                'gradient' => 'var(--warning-gradient)',
                'suffix' => '%',
                'compare' => $comparison['success_rate'] ?? null,
                'drill' => ['type' => 'metric', 'id' => 'success_rate']
            ],
            [
                'title' => 'Khách hàng',
                'value' => $metrics['unique_customers'],
                'icon' => 'fa-users',
                'color' => 'info',
                'gradient' => 'var(--info-gradient)',
                'compare' => $comparison['unique_customers'] ?? null,
                'drill' => ['type' => 'metric', 'id' => 'unique_customers']
            ]
        ];
        
        foreach ($metricConfigs as $config):
            $changePercent = isset($config['compare']) ? $config['compare']['change_percent'] : 0;
        ?>
        <div class="col-md-3 mb-3">
            <div class="metric-card clickable" 
                 data-drill-type="<?= $config['drill']['type'] ?>"
                 data-drill-id="<?= $config['drill']['id'] ?>">
                <div class="icon" style="background: <?= $config['gradient'] ?>; color: white;">
                    <i class="fas <?= $config['icon'] ?>"></i>
                </div>
                <div class="value">
                    <?php if ($config['format'] == 'money'): ?>
                        <?= number_format($config['value'], 0, ',', '.') ?>đ
                    <?php elseif ($config['format'] == 'percent'): ?>
                        <?= number_format($config['value'], 1) ?>%
                    <?php else: ?>
                        <?= number_format($config['value'], 0, ',', '.') ?>
                    <?php endif; ?>
                </div>
                <div class="label"><?= $config['title'] ?></div>
                <?php if ($changePercent != 0): ?>
                <div class="change <?= $changePercent > 0 ? 'positive' : 'negative' ?>">
                    <i class="fas fa-arrow-<?= $changePercent > 0 ? 'up' : 'down' ?>"></i>
                    <?= abs($changePercent) ?>%
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Charts Row -->
    <?php if ($report_type == 'overview'): ?>
    <div class="row mb-4">
        <!-- Trend Chart -->
        <div class="col-md-8">
            <div class="chart-container">
                <div class="chart-header">
                    <h5 class="chart-title">
                        <i class="fas fa-chart-area text-primary me-2"></i>
                        Xu hướng theo ngày
                    </h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary active" data-chart="revenue">
                            Doanh thu
                        </button>
                        <button type="button" class="btn btn-outline-secondary" data-chart="orders">
                            Đơn hàng
                        </button>
                    </div>
                </div>
                <canvas id="trendChart" height="80"></canvas>
            </div>
        </div>
        
        <!-- Distribution Pie Chart -->
        <div class="col-md-4">
            <div class="chart-container">
                <div class="chart-header">
                    <h5 class="chart-title">
                        <i class="fas fa-chart-pie text-warning me-2"></i>
                        Phân bố trạng thái
                    </h5>
                </div>
                <canvas id="distributionChart" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Performers -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-container">
                <div class="chart-header">
                    <h5 class="chart-title">
                        <i class="fas fa-trophy text-warning me-2"></i>
                        Top nhân viên xuất sắc
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Xếp hạng</th>
                                <th>Nhân viên</th>
                                <th>Vai trò</th>
                                <th>Tổng đơn</th>
                                <th>Thành công</th>
                                <th>Doanh thu</th>
                                <th>Tỷ lệ</th>
                                <th>Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $rank = 1;
                            foreach ($topPerformers['users'] as $user): 
                                $successRate = $user['total_orders'] > 0 
                                    ? round($user['success_orders'] * 100 / $user['total_orders'], 1) 
                                    : 0;
                            ?>
                            <tr>
                                <td>
                                    <?php if ($rank <= 3): ?>
                                    <span class="badge bg-warning text-dark">
                                        <i class="fas fa-medal"></i> <?= $rank ?>
                                    </span>
                                    <?php else: ?>
                                    <?= $rank ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?= $user['role'] ?></span>
                                </td>
                                <td><?= number_format($user['total_orders']) ?></td>
                                <td><?= number_format($user['success_orders']) ?></td>
                                <td><?= number_format($user['success_revenue'], 0, ',', '.') ?>đ</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-success" 
                                             style="width: <?= $successRate ?>%">
                                            <?= $successRate ?>%
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="?drill=user&drill_id=<?= $user['user_id'] ?>&<?= http_build_query($_GET) ?>" 
                                       class="btn btn-sm btn-primary">
                                        <i class="fas fa-chart-line"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php 
                            $rank++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Specific Report Tables -->
    <?php if ($report_type == 'users' && $reportData): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-container">
                <div class="chart-header">
                    <h5 class="chart-title">
                        <i class="fas fa-users text-info me-2"></i>
                        Báo cáo hiệu suất nhân viên
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="usersTable">
                        <thead>
                            <tr>
                                <th>Nhân viên</th>
                                <th>Vai trò</th>
                                <th>Tổng đơn</th>
                                <th>Mới</th>
                                <th>Đang xử lý</th>
                                <th>Thành công</th>
                                <th>Thất bại</th>
                                <th>Doanh thu</th>
                                <th>Tỷ lệ thành công</th>
                                <th>TB xử lý (giờ)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['users'] as $user): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                    <br>
                                    <small class="text-muted"><?= $user['username'] ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] == 'admin' ? 'danger' : ($user['role'] == 'manager' ? 'warning' : 'info') ?>">
                                        <?= $user['role'] ?>
                                    </span>
                                </td>
                                <td><?= number_format($user['total_orders']) ?></td>
                                <td><?= number_format($user['new_orders']) ?></td>
                                <td><?= number_format($user['processing_orders']) ?></td>
                                <td class="text-success">
                                    <?= number_format($user['success_orders']) ?>
                                </td>
                                <td class="text-danger">
                                    <?= number_format($user['failed_orders']) ?>
                                </td>
                                <td>
                                    <strong><?= number_format($user['success_revenue'], 0, ',', '.') ?>đ</strong>
                                </td>
                                <td>
                                    <div class="progress" style="height: 25px;">
                                        <div class="progress-bar bg-<?= $user['success_rate'] >= 70 ? 'success' : ($user['success_rate'] >= 50 ? 'warning' : 'danger') ?>" 
                                             style="width: <?= $user['success_rate'] ?>%">
                                            <?= number_format($user['success_rate'], 1) ?>%
                                        </div>
                                    </div>
                                </td>
                                <td><?= number_format($user['avg_processing_time'], 1) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($report_type == 'products' && $reportData): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-container">
                <div class="chart-header">
                    <h5 class="chart-title">
                        <i class="fas fa-box text-warning me-2"></i>
                        Báo cáo sản phẩm bán chạy
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="productsTable">
                        <thead>
                            <tr>
                                <th>STT</th>
                                <th>SKU</th>
                                <th>Tên sản phẩm</th>
                                <th>Số lượng bán</th>
                                <th>Doanh thu</th>
                                <th>Số đơn hàng</th>
                                <th>TB/Đơn</th>
                                <th>% Doanh thu</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $totalRevenue = array_sum(array_column($reportData['products'], 'total_revenue'));
                            $rank = 1;
                            foreach ($reportData['products'] as $product): 
                                $avgPerOrder = $product['order_count'] > 0 
                                    ? $product['total_revenue'] / $product['order_count']
                                    : 0;
                                $revenuePercent = $totalRevenue > 0
                                    ? ($product['total_revenue'] / $totalRevenue) * 100
                                    : 0;
                            ?>
                            <tr>
                                <td><?= $rank++ ?></td>
                                <td>
                                    <code><?= htmlspecialchars($product['sku']) ?></code>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($product['name']) ?></strong>
                                </td>
                                <td><?= number_format($product['total_quantity']) ?></td>
                                <td>
                                    <strong class="text-success">
                                        <?= number_format($product['total_revenue'], 0, ',', '.') ?>đ
                                    </strong>
                                </td>
                                <td><?= number_format($product['order_count']) ?></td>
                                <td><?= number_format($avgPerOrder, 0, ',', '.') ?>đ</td>
                                <td>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-info" 
                                             style="width: <?= min($revenuePercent, 100) ?>%">
                                            <?= number_format($revenuePercent, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($report_type == 'customers' && $reportData): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="table-container">
                <div class="chart-header">
                    <h5 class="chart-title">
                        <i class="fas fa-user-friends text-success me-2"></i>
                        Báo cáo khách hàng
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover" id="customersTable">
                        <thead>
                            <tr>
                                <th>Khách hàng</th>
                                <th>Số điện thoại</th>
                                <th>Email</th>
                                <th>Tổng đơn</th>
                                <th>Thành công</th>
                                <th>Tổng giá trị</th>
                                <th>TB/Đơn</th>
                                <th>Lần cuối mua</th>
                                <th>Nhãn</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reportData['customers'] as $customer): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($customer['customer_name']) ?></strong>
                                </td>
                                <td>
                                    <a href="tel:<?= $customer['customer_phone'] ?>">
                                        <?= htmlspecialchars($customer['customer_phone']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?= htmlspecialchars($customer['customer_email'] ?: '-') ?>
                                </td>
                                <td><?= number_format($customer['total_orders']) ?></td>
                                <td class="text-success">
                                    <?= number_format($customer['success_orders']) ?>
                                </td>
                                <td>
                                    <strong><?= number_format($customer['total_value'], 0, ',', '.') ?>đ</strong>
                                </td>
                                <td>
                                    <?= number_format($customer['avg_order_value'], 0, ',', '.') ?>đ
                                </td>
                                <td>
                                    <?= date('d/m/Y H:i', strtotime($customer['last_order_date'])) ?>
                                </td>
                                <td>
                                    <?php if (!empty($customer['labels'])): ?>
                                        <?php foreach (explode(',', $customer['labels']) as $label): ?>
                                        <span class="badge bg-secondary me-1">
                                            <?= htmlspecialchars(trim($label)) ?>
                                        </span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Show loading on form submit
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        document.getElementById('loadingOverlay').classList.add('show');
    });
});

// Handle metric card clicks for drill-down
document.querySelectorAll('.metric-card.clickable').forEach(card => {
    card.addEventListener('click', function() {
        const drillType = this.dataset.drillType;
        const drillId = this.dataset.drillId;
        const currentParams = new URLSearchParams(window.location.search);
        currentParams.set('drill', drillType);
        currentParams.set('drill_id', drillId);
        window.location.href = '?' + currentParams.toString();
    });
});

// Initialize charts if on overview page
<?php if ($report_type == 'overview'): ?>
// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
const trendChart = new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($trends['daily'], 'date')) ?>,
        datasets: [{
            label: 'Doanh thu',
            data: <?= json_encode(array_column($trends['daily'], 'success_revenue')) ?>,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let value = context.parsed.y;
                        return 'Doanh thu: ' + new Intl.NumberFormat('vi-VN', {
                            style: 'currency',
                            currency: 'VND'
                        }).format(value);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('vi-VN', {
                            notation: 'compact',
                            compactDisplay: 'short'
                        }).format(value);
                    }
                }
            }
        }
    }
});

// Distribution Chart
const distributionCtx = document.getElementById('distributionChart').getContext('2d');
const distributionChart = new Chart(distributionCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($distribution, 'label_name')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($distribution, 'count')) ?>,
            backgroundColor: <?= json_encode(array_column($distribution, 'color')) ?>
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                        const percentage = (context.parsed / total * 100).toFixed(1);
                        return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                    }
                }
            }
        }
    }
});

// Handle chart type switch
document.querySelectorAll('[data-chart]').forEach(button => {
    button.addEventListener('click', function() {
        document.querySelectorAll('[data-chart]').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        
        const chartType = this.dataset.chart;
        let newData, label;
        
        if (chartType === 'revenue') {
            newData = <?= json_encode(array_column($trends['daily'], 'success_revenue')) ?>;
            label = 'Doanh thu';
        } else {
            newData = <?= json_encode(array_column($trends['daily'], 'total_orders')) ?>;
            label = 'Đơn hàng';
        }
        
        trendChart.data.datasets[0].data = newData;
        trendChart.data.datasets[0].label = label;
        trendChart.update();
    });
});
<?php endif; ?>

// Auto-refresh every 5 minutes
setTimeout(function() {
    location.reload();
}, 300000);
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>