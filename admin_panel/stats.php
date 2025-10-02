<?php
/**
 * Professional Statistics Dashboard
 * Sử dụng đầy đủ module Statistics với phân quyền role-based
 * Version 4.1 - FIXED ALL BUGS
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../system/functions.php';
require_once __DIR__ . '../../modules/statistics/statistics_autoload.php';

require_login();

$current_user = get_logged_user();
$page_title = 'Báo cáo hoạt động kinh doanh';

// Helper function để tạo module instance an toàn
function getStatisticsModule($type, $db) {
    switch($type) {
        case 'overview':
            return new Modules\Statistics\Reports\OverviewReport($db);
        case 'user':
            return new Modules\Statistics\Reports\UserReport($db);
        case 'product':
            return new Modules\Statistics\Reports\ProductReport($db);
        case 'customer':
            return new Modules\Statistics\Reports\CustomerReport($db);
        case 'order':
            return new Modules\Statistics\Reports\OrderReport($db);
        case 'drilldown':
            return new Modules\Statistics\Core\DrilldownHandler($db);
        case 'filter':
            return new Modules\Statistics\Filters\FilterBuilder();
        default:
            throw new Exception("Unknown module type: $type");
    }
}

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
            $exporter = new Modules\Statistics\Exporters\ExcelExporter();
            break;
        case 'csv':
            $exporter = new Modules\Statistics\Exporters\CSVExporter();
            break;
        case 'pdf':
            $exporter = new Modules\Statistics\Exporters\PDFExporter();
            break;
    }
    
    if ($exporter) {
        // Get report data based on type
        switch ($report_type) {
            case 'users':
                $report = getStatisticsModule('user', $db);
                $reportData = $report->setDateRange($datetime_from, $datetime_to)->getData()['users'] ?? [];
                $headers = ['Nhân viên', 'Vai trò', 'Tổng đơn', 'Thành công', 'Doanh thu', 'Tỷ lệ'];
                break;
            case 'products':
                $report = getStatisticsModule('product', $db);
                $reportData = $report->setDateRange($datetime_from, $datetime_to)->getData()['products'] ?? [];
                $headers = ['SKU', 'Sản phẩm', 'Số lượng', 'Doanh thu', 'Đơn hàng'];
                break;
            case 'customers':
                $report = getStatisticsModule('customer', $db);
                $reportData = $report->setDateRange($datetime_from, $datetime_to)->getData()['customers'] ?? [];
                $headers = ['Khách hàng', 'SĐT', 'Tổng đơn', 'Giá trị', 'Lần cuối'];
                break;
            default:
                $report = getStatisticsModule('overview', $db);
                $reportData = $report->setDateRange($datetime_from, $datetime_to)->getData()['metrics'] ?? [];
                $headers = array_keys($reportData);
        }
        
        if (!empty($reportData)) {
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
}

// Initialize main report
try {
    $overviewReport = getStatisticsModule('overview', $db);
    $overviewReport->setDateRange($datetime_from, $datetime_to);
    $overviewData = $overviewReport->getData();
} catch (Exception $e) {
    $overviewData = [];
    // Log error: error_log($e->getMessage());
}

$metrics = $overviewData['metrics'] ?? [
    'total_orders' => 0,
    'total_revenue' => 0,
    'success_rate' => 0,
    'unique_customers' => 0
];
$comparison = $overviewData['comparison'] ?? [];
$trends = $overviewData['trends'] ?? ['daily' => []];
$topPerformers = $overviewData['topPerformers'] ?? ['users' => []];
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
        $drilldownData = null;
        // Log error: error_log($e->getMessage());
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

// Prepare data for JS Charts
if ($report_type == 'overview') {
    $trendLabels = [];
    $trendRevenue = [];
    $trendOrders = [];

    if (isset($trends['daily']) && is_array($trends['daily'])) {
        foreach ($trends['daily'] as $day) {
            $trendLabels[] = isset($day['date']) ? date('d/m', strtotime($day['date'])) : '';
            $trendRevenue[] = $day['success_revenue'] ?? 0;
            $trendOrders[] = $day['total_orders'] ?? 0;
        }
    }

    if (empty($trendLabels)) {
        $trendLabels = ['Không có dữ liệu'];
        $trendRevenue = [0];
        $trendOrders = [0];
    }

    $distributionLabels = [];
    $distributionData = [];
    $distributionColors = [];

    if (isset($distribution) && is_array($distribution)) {
        foreach ($distribution as $item) {
            if (isset($item['label_name']) && isset($item['count'])) {
                $distributionLabels[] = $item['label_name'];
                $distributionData[] = $item['count'];
                $distributionColors[] = $item['color'] ?? '#cccccc';
            }
        }
    }

    if (empty($distributionLabels)) {
        $distributionLabels = ['Không có dữ liệu'];
        $distributionData = [1];
        $distributionColors = ['#cccccc'];
    }
}


// --- START VIEW ---
include '../includes/header.php';
?>
<link rel="stylesheet" href="../assets/css/stats_css.css">

<div class="loading" id="loadingOverlay">
    <div class="spinner"></div>
</div>

<div class="container-fluid py-4">
    <?php 
    // Include all view blocks
    require '../blocks/stats/stats_header.php';
    require '../blocks/stats/stats_filters.php';
    
    if ($drilldownData && isset($drilldownData['breadcrumbs'])) {
        require '../blocks/stats/stats_breadcrumb.php';
    }

    require '../blocks/stats/stats_metrics.php';

    // Overview-specific blocks
    if ($report_type == 'overview') {
        require '../blocks/stats/stats_charts_overview.php';
        require '../blocks/stats/stats_table_top_performers.php';
    }

    // Report-specific tables
    if ($report_type == 'users' && $reportData) {
        require '../blocks/stats/stats_table_users.php';
    }
    if ($report_type == 'products' && $reportData) {
        require '../blocks/stats/stats_table_products.php';
    }
    if ($report_type == 'customers' && $reportData) {
        require '../blocks/stats/stats_table_customers.php';
    }
    ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php if ($report_type == 'overview'): ?>
<script>
    const statsChartData = {
        trendLabels: <?= json_encode($trendLabels) ?>,
        trendRevenue: <?= json_encode($trendRevenue) ?>,
        trendOrders: <?= json_encode($trendOrders) ?>,
        distributionLabels: <?= json_encode($distributionLabels) ?>,
        distributionData: <?= json_encode($distributionData) ?>,
        distributionColors: <?= json_encode($distributionColors) ?>
    };
</script>
<?php endif; ?>

<script src="../assets/js/stats_js.js"></script>

<?php 
include '../includes/footer.php'; 
?>