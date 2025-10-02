Module Thống kê & Báo cáo (Statistics & Reports)
1. Tổng quan
Module Statistics là một bộ công cụ Business Intelligence (BI) mạnh mẽ, được thiết kế để cung cấp khả năng phân tích, thống kê, và trực quan hóa dữ liệu cho hệ thống. Module cho phép tạo ra các báo cáo động, theo dõi chỉ số hiệu suất (KPI), phân tích hiệu quả làm việc của nhân viên, hành vi khách hàng, và hiệu suất bán hàng của sản phẩm.

Với thiết kế module hóa, các thành phần có thể được sử dụng độc lập hoặc kết hợp với nhau để xây dựng các trang tổng quan (dashboard) và hệ thống báo cáo phức tạp.

2. Cấu trúc Thư mục
Toàn bộ module được đặt trong modules/statistics/ và tổ chức theo chức năng. Dưới đây là cấu trúc file chính xác:

modules/statistics/
├── Cache/
│   └── cache_manager_module.php
├── Core/
│   ├── calculator_core_module.php
│   ├── data_provider_module.php
│   ├── drilldown_handler_module.php
│   ├── permissions_module.php
│   └── statistics_base_module.php
├── Exporters/
│   ├── csv_exporter_module.php
│   ├── excel_exporter_module.php
│   └── pdf_exporter_module.php
├── Filters/
│   ├── condition_filter_module.php
│   ├── date_filter_module.php
│   └── filter_builder_widget.php
├── Reports/
│   ├── customer_report_module.php
│   ├── order_report_module.php
│   ├── overview_report_module.php
│   ├── product_report_module.php
│   └── user_report_module.php
├── Widgets/
│   ├── chart_widget_module.php
│   ├── metric_card_widget.php
│   ├── table_widget_module.php
│   └── trend_widget_module.php
├── statistics_api.php
└── statistics_autoload.php
3. Cài đặt & Tích hợp
Để sử dụng module, bạn chỉ cần nhúng file statistics_autoload.php vào đầu dự án. File này sẽ tự động đăng ký một bộ nạp (autoloader) cho tất cả các lớp trong module.

PHP

<?php
// Bất kỳ file nào trong dự án của bạn (ví dụ: index.php)

// 1. Nhúng file autoload của module
require_once 'path/to/your/project/modules/statistics/statistics_autoload.php';

// 2. Chuẩn bị đối tượng kết nối CSDL ($db), module sẽ sử dụng đối tượng này
$db = new PDO(...); 

// 3. Bây giờ bạn có thể bắt đầu sử dụng các lớp của module
use Modules\Statistics\Reports\OverviewReport;

$overview = new OverviewReport($db);
$data = $overview->getData();

// ...
4. Các Thành phần Chính
Core: Các lớp lõi cung cấp nền tảng và logic trung tâm cho toàn bộ module.

Filters: Các lớp giúp xây dựng các điều kiện truy vấn SQL một cách an toàn và linh hoạt.

Reports: Các lớp chịu trách nhiệm tổng hợp và xử lý dữ liệu để tạo ra một báo cáo cụ thể (ví dụ: báo cáo về người dùng, sản phẩm).

Widgets: Các lớp dùng để trực quan hóa dữ liệu, biến các con số khô khan thành biểu đồ, bảng biểu, thẻ số liệu dễ hiểu.

Exporters: Các lớp chuyên dụng để chuyển đổi dữ liệu báo cáo thành các định dạng file có thể tải về (Excel, PDF, CSV).

Cache: Cung cấp cơ chế lưu cache để tăng tốc độ tải các báo cáo nặng.

5. Hướng dẫn sử dụng
Tất cả các module báo cáo và widget đều được khởi tạo bằng cách truyền vào một đối tượng kết nối CSDL ($db).

5.1. Lấy Báo cáo Tổng quan (Dashboard)
Sử dụng 

OverviewReport để lấy tất cả dữ liệu cần thiết cho một trang dashboard.

PHP

use Modules\Statistics\Reports\OverviewReport;

$report = new OverviewReport($db);
$overviewData = $report
    ->setDateRange('2025-09-01 00:00:00', '2025-09-30 23:59:59')
    ->getData();

// Dữ liệu trả về là một mảng lớn chứa nhiều thông tin
$metrics = $overviewData['metrics'];
$topUsers = $overviewData['topPerformers']['users'];
$dailyTrends = $overviewData['trends']['daily'];

echo "Tổng doanh thu thành công: " . number_format($metrics['success_revenue']) . " đ";
5.2. Tạo Báo cáo Chi tiết
Sử dụng các lớp báo cáo cụ thể như UserReport, ProductReport, OrderReport.

PHP

use Modules\Statistics\Reports\UserReport;

// Lấy báo cáo hiệu suất của tất cả nhân viên trong 7 ngày qua
$userReport = new UserReport($db);
$reportData = $userReport
    ->setDateRange(date('Y-m-d', strtotime('-7 days')), date('Y-m-d'))
    ->orderBy('success_rate', 'DESC') // Sắp xếp theo tỷ lệ thành công giảm dần
    ->limit(10) // Lấy 10 người dùng hàng đầu
    ->getData();

$users = $reportData['users'];
5.3. Áp dụng Bộ lọc
Kết hợp FilterBuilder và phương thức applyFilter() để tạo các báo cáo phức tạp.

PHP

use Modules\Statistics\Reports\OrderReport;
use Modules\Statistics\Filters\FilterBuilder;

// Tìm các đơn hàng thành công có giá trị trên 500,000đ
$filter = new FilterBuilder();
$filter->addCondition('ol.core_status', '=', 'success')
       ->addCondition('o.total_amount', '>', 500000);

$orderReport = new OrderReport($db);
$orders = $orderReport
    ->applyFilter($filter) // Áp dụng bộ lọc
    ->getOrders();

foreach ($orders as $order) {
    echo "Đơn hàng #" . $order['order_number'] . "\n";
}
5.4. Sử dụng Widgets để Hiển thị Dữ liệu
Hiển thị thẻ chỉ số KPI với MetricCard
PHP

use Modules\Statistics\Widgets\MetricCard;

$card = new MetricCard();
$html = $card->setTitle('Doanh thu tháng này')
             ->setValue(150000000)
             ->setCompare(120000000) // So với giá trị kỳ trước
             ->setFormat('money')
             ->setIcon('fa-dollar-sign')
             ->setColor('success')
             ->render();

echo $html;
Vẽ biểu đồ với 

ChartWidget 

PHP

use Modules\Statistics\Widgets\ChartWidget;

// $dailyTrends là dữ liệu lấy từ OverviewReport
$labels = array_column($dailyTrends, 'date');
$revenueData = array_column($dailyTrends, 'revenue');

$chart = new ChartWidget();
$html = $chart->setType('line')
             ->setTitle('Xu hướng doanh thu theo ngày')
             ->setData($labels, [
                ['label' => 'Doanh thu', 'data' => $revenueData]
             ])
             ->render();

echo $html;
Hiển thị bảng dữ liệu với TableWidget
PHP

use Modules\Statistics\Widgets\TableWidget;

// $users là dữ liệu lấy từ UserReport
$table = new TableWidget();
$html = $table->setColumns([
                ['key' => 'full_name', 'label' => 'Tên nhân viên'],
                ['key' => 'total_orders', 'label' => 'Tổng đơn', 'align' => 'center'],
                ['key' => 'success_rate', 'label' => 'Tỷ lệ thành công', 'type' => 'percent'],
                ['key' => 'success_revenue', 'label' => 'Doanh thu', 'type' => 'money']
             ])
             ->setData($users)
             ->enableSorting(true) // Bật tính năng sắp xếp
             ->enableSearch(true) // Bật tính năng tìm kiếm
             ->render();

echo $html;
5.5. Xuất Dữ liệu
Sử dụng các lớp trong Exporters để tải báo cáo về.

PHP

use Modules\Statistics\Exporters\ExcelExporter;
use Modules\Statistics\Reports\ProductReport;

// 1. Lấy dữ liệu
$productReport = new ProductReport($db);
$products = $productReport->getData()['products'];

// 2. Xuất ra file Excel
$exporter = new ExcelExporter();
$exporter->setData($products)
         ->setHeaders(['SKU', 'Tên sản phẩm', 'Số lượng bán', 'Doanh thu'])
         ->setFilename('bao_cao_san_pham_' . date('Y_m_d'))
         ->setTitle('Báo cáo Hiệu suất Sản phẩm')
         ->download();
5.6. Phân quyền
Module tự động xử lý phân quyền dựa trên 

$_SESSION['user']['role'].


admin: Có thể xem tất cả dữ liệu.


manager: Chỉ có thể xem dữ liệu của các nhân viên trong team của mình.


telesale: Chỉ có thể xem dữ liệu của chính mình.

Logic này được thực thi trong 

Permissions và tự động áp dụng bởi lớp StatisticsBase, bạn không cần can thiệp thủ công.

5.7. Sử dụng Cache
Sử dụng 

CacheManager để tăng tốc độ các báo cáo nặng hoặc ít thay đổi.

PHP

use Modules\Statistics\Cache\CacheManager;
use Modules\Statistics\Reports\ProductReport;

$cache = new CacheManager($db);

// Tạo một cache key duy nhất dựa trên tham số
$cacheKey = $cache->generateKey('top_products_report', ['limit' => 10]);

// Dùng hàm remember()
$topProducts = $cache->remember($cacheKey, function() use ($db) {
    // Hàm này chỉ chạy khi không có cache hoặc cache đã hết hạn
    echo "Đang truy vấn CSDL...";
    $report = new ProductReport($db);
    return $report->limit(10)->getData();
}, 3600); // Lưu cache trong 1 giờ (3600 giây)

print_r($topProducts);
5.8. Điều hướng Chi tiết (Drill-down)
Các widget như MetricCard có thể được cấu hình để cho phép drill-down. Khi người dùng nhấp vào, một yêu cầu sẽ được gửi đến backend, nơi DrilldownHandler sẽ xử lý và trả về dữ liệu chi tiết hơn.

PHP

// Ví dụ trong MetricCard
$card->setDrilldown('overview', 'total_revenue'); 
// -> Sẽ tạo ra thuộc tính data-drilldown-type="overview" data-drilldown-id="total_revenue"
6. Các Hàm Hỗ trợ Nhanh (Quick Helpers)
File 

statistics_autoload.php cung cấp lớp QuickStats giúp truy cập các chức năng phổ biến một cách nhanh chóng.

PHP

// Phải có use QuickStats; ở đầu file
use QuickStats;

// Lấy nhanh các chỉ số của ngày hôm nay
$todayMetrics = QuickStats::getTodayMetrics($db);
echo "Đơn hàng hôm nay: " . $todayMetrics['total_orders'];

// Render nhanh một dãy 4 thẻ KPI cho dashboard
echo QuickStats::renderMetricCards($db);