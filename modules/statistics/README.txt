Statistics Modules Documentation
Danh sách CHÍNH XÁC 21 Files trong thư mục Modules/Statistics/
Modules/Statistics/
├── cache_manager_module.php
├── calculator_core_module.php
├── chart_widget_module.php
├── condition_filter_module.php
├── csv_exporter_module.php
├── customer_report_module.php
├── data_provider_module.php
├── date_filter_module.php
├── drilldown_handler_module.php
├── excel_exporter_module.php
├── filter_builder_module.php
├── metric_card_widget.php
├── overview_report_module.php
├── pdf_exporter_module.php
├── permissions_module.php
├── product_report_module.php
├── statistics_api.php
├── statistics_autoload.php
├── statistics_base_module.php
├── table_widget_module.php
├── trend_widget_module.php
└── user_report_module.php
Phân loại theo chức năng
Core Modules (5 files)

statistics_base_module.php - Base class cho tất cả modules
data_provider_module.php - Lấy dữ liệu từ database
calculator_core_module.php - Tính toán metrics và thống kê
permissions_module.php - Kiểm tra quyền truy cập
drilldown_handler_module.php - Xử lý drill-down navigation

Filter Modules (3 files)

filter_builder_module.php - Xây dựng query filters động
date_filter_module.php - Filter theo ngày tháng
condition_filter_module.php - Filter điều kiện phức tạp

Report Modules (4 files)

overview_report_module.php - Báo cáo tổng quan
product_report_module.php - Báo cáo sản phẩm
user_report_module.php - Báo cáo nhân viên
customer_report_module.php - Báo cáo khách hàng

Widget Modules (4 files)

metric_card_widget.php - Widget hiển thị số liệu
chart_widget_module.php - Widget biểu đồ
table_widget_module.php - Widget bảng dữ liệu
trend_widget_module.php - Widget xu hướng

Exporter Modules (3 files)

excel_exporter_module.php - Export Excel
pdf_exporter_module.php - Export PDF
csv_exporter_module.php - Export CSV

Cache Module (1 file)

cache_manager_module.php - Quản lý cache

Support Files (2 files)

statistics_autoload.php - Autoloader và helper functions
statistics_api.php - API endpoint handler

Hướng dẫn sử dụng
1. Include autoloader
phprequire_once 'Modules/Statistics/statistics_autoload.php';
2. Các Core Modules
statistics_base_module.php
php// Base class - tất cả reports kế thừa từ đây
class StatisticsBase {
    // Methods:
    setDateRange($from, $to)
    applyFilter($filter)
    groupBy($fields)
    orderBy($field, $direction)
    limit($limit, $offset)
    getData()
}
data_provider_module.php
phpuse Modules\Statistics\Core\DataProvider;

$provider = new DataProvider($db);
$orders = $provider->getOrders($filters);
$users = $provider->getUsers($filters);
$customers = $provider->getCustomers($filters);
$products = $provider->getProducts($filters);
calculator_core_module.php
phpuse Modules\Statistics\Core\Calculator;

$calc = new Calculator();
$stats = $calc->calculateBasicStats($values);
$growth = $calc->calculateGrowthRate($values);
$forecast = $calc->calculateForecast($values, 7);
$outliers = $calc->detectOutliers($values);
permissions_module.php
phpuse Modules\Statistics\Core\Permissions;

$perms = new Permissions($db, $user);
if ($perms->hasPermission('view_financial_data')) {
    // Show financial reports
}
$scope = $perms->getDataScope(); // all/team/own
drilldown_handler_module.php
phpuse Modules\Statistics\Core\DrilldownHandler;

$drill = new DrilldownHandler($db);
$detail = $drill->process('product', 'SKU123', $params);
$drill->goBack();
$breadcrumbs = $drill->getBreadcrumbs();
3. Filter Modules
filter_builder_module.php
phpuse Modules\Statistics\Filters\FilterBuilder;

$filter = new FilterBuilder();
$filter->addCondition('status', '=', 'completed')
       ->addCondition('amount', '>', 1000000)
       ->addLabelFilter('user', 'top_performer')
       ->setLogic('AND');
date_filter_module.php
phpuse Modules\Statistics\Filters\DateFilter;

$dateFilter = new DateFilter();
$dateFilter->setPreset('last_30_days');
// or
$dateFilter->setRange('2025-01-01', '2025-01-31');
condition_filter_module.php
phpuse Modules\Statistics\Filters\ConditionFilter;

$filter = new ConditionFilter();
$filter->addCondition('status', '=', 'success')
       ->addRangeCondition('amount', 100000, 500000)
       ->addInCondition('user_id', [1, 2, 3]);
4. Report Modules
overview_report_module.php
phpuse Modules\Statistics\Reports\OverviewReport;

$report = new OverviewReport($db);
$data = $report->setDateRange('2025-01-01', '2025-01-31')
               ->getData();
// Returns: metrics, comparison, trends, topPerformers, distribution
product_report_module.php
phpuse Modules\Statistics\Reports\ProductReport;

$report = new ProductReport($db);
$data = $report->search('iPhone')
               ->sort('revenue', 'DESC')
               ->limit(10)
               ->getData();
user_report_module.php
phpuse Modules\Statistics\Reports\UserReport;

$report = new UserReport($db);
$data = $report->includePerformance()
               ->includeLabels()
               ->getData();
customer_report_module.php
phpuse Modules\Statistics\Reports\CustomerReport;

$report = new CustomerReport($db);
$data = $report->segmentBy('rfm')
               ->includeLabels()
               ->getData();
5. Widget Modules
metric_card_widget.php
phpuse Modules\Statistics\Widgets\MetricCard;

$card = new MetricCard($db);
echo $card->setTitle('Doanh thu')
          ->setValue(50000000)
          ->setCompare(45000000)
          ->setFormat('money')
          ->render();
chart_widget_module.php
phpuse Modules\Statistics\Widgets\ChartWidget;

$chart = new ChartWidget($db);
echo $chart->setType('line')
           ->setData($labels, $datasets)
           ->render();
table_widget_module.php
phpuse Modules\Statistics\Widgets\TableWidget;

$table = new TableWidget($db);
echo $table->setColumns($columns)
           ->setData($data)
           ->enableSorting()
           ->render();
trend_widget_module.php
phpuse Modules\Statistics\Widgets\TrendWidget;

$trend = new TrendWidget($db);
echo $trend->setTitle('Xu hướng')
           ->setData($trendData)
           ->render();
6. Exporter Modules
excel_exporter_module.php
phpuse Modules\Statistics\Exporters\ExcelExporter;

$exporter = new ExcelExporter();
$exporter->setData($data)
         ->setHeaders(['Tên', 'Doanh thu'])
         ->download('report.xlsx');
pdf_exporter_module.php
phpuse Modules\Statistics\Exporters\PDFExporter;

$exporter = new PDFExporter();
$exporter->setData($data)
         ->setTitle('Báo cáo')
         ->download('report.pdf');
csv_exporter_module.php
phpuse Modules\Statistics\Exporters\CSVExporter;

$exporter = new CSVExporter();
$exporter->setData($data)
         ->download('report.csv');
7. Cache Module
cache_manager_module.php
phpuse Modules\Statistics\Cache\CacheManager;

$cache = new CacheManager($db);

// Remember query
$data = $cache->remember('report_key', function() {
    // Heavy query
    return $db->query("...")->fetchAll();
}, 3600); // Cache 1 hour

// Clear cache
$cache->clear();
$cache->clearExpired();
8. API Usage
statistics_api.php
php// GET request
/Modules/Statistics/statistics_api.php?action=get_overview&params[date_from]=2025-01-01

// POST request
POST /Modules/Statistics/statistics_api.php
{
    "action": "get_report",
    "type": "order",
    "params": {
        "filters": [...],
        "limit": 10
    }
}
Namespace Structure
Mặc dù files nằm cùng thư mục, code sử dụng namespace logic:
phpnamespace Modules\Statistics\Core;      // Core modules
namespace Modules\Statistics\Filters;   // Filter modules
namespace Modules\Statistics\Reports;   // Report modules
namespace Modules\Statistics\Widgets;   // Widget modules
namespace Modules\Statistics\Exporters; // Exporter modules
namespace Modules\Statistics\Cache;     // Cache module
Lưu ý quan trọng

Tất cả 21 files nằm trong cùng thư mục Modules/Statistics/
statistics_autoload.php phải được include trước khi sử dụng
Namespace trong code khác với cấu trúc thư mục thực - autoloader xử lý mapping
statistics_api.php là endpoint chính cho mọi API request
Không có file OrderReport - cần bổ sung nếu cần báo cáo đơn hàng chi tiết

Dependencies

PHP >= 7.4
PDO MySQL
JSON extension
Optional: PHPSpreadsheet, TCPDF, wkhtmltopdf