<?php
/**
 * Safe Statistics Module Autoloader
 * Sử dụng require_once và kiểm tra class tồn tại
 */

// Define base path
$statistics_module_path = __DIR__;

// List of module files to include
$module_files = [
    // Core files - load first
    'statistics_base_module.php',
    'calculator_core_module.php',
    'data_provider_module.php',
    'permissions_module.php',
    'drilldown_handler_module.php',
    
    // Report files
    'overview_report_module.php',
    'order_report_module.php', 
    'product_report_module.php',
    'user_report_module.php',
    'customer_report_module.php',
    
    // Widget files
    'metric_card_widget.php',
    'chart_widget_module.php',
    'table_widget_module.php',
    'trend_widget_module.php',
    
    // Filter files
    'filter_builder_module.php',
    'date_filter_module.php',
    'condition_filter_module.php',
    
    // Exporter files
    'excel_exporter_module.php',
    'pdf_exporter_module.php',
    'csv_exporter_module.php',
    
    // Cache files
    'cache_manager_module.php'
];

// Include files safely with error handling
foreach ($module_files as $file) {
    $filepath = $statistics_module_path . '/' . $file;
    
    // Only include if file exists and hasn't been included
    if (file_exists($filepath)) {
        // Use require_once to prevent duplicate inclusion
        require_once $filepath;
    }
}

// Register simple autoloader for any missing classes
spl_autoload_register(function($class) use ($statistics_module_path) {
    // Remove any namespace prefix
    $className = $class;
    if (strpos($class, 'Modules\\Statistics\\') === 0) {
        $parts = explode('\\', $class);
        $className = end($parts);
    }
    
    // Try to find matching file
    $possibleFiles = [
        $className . '.php',
        strtolower($className) . '.php',
        strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className)) . '.php',
        strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className)) . '_module.php',
        strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $className)) . '_widget.php',
    ];
    
    foreach ($possibleFiles as $file) {
        $filepath = $statistics_module_path . '/' . $file;
        if (file_exists($filepath) && !class_exists($className, false)) {
            require_once $filepath;
            return true;
        }
    }
    
    return false;
});

/**
 * Helper function to safely get module instance
 */
if (!function_exists('getStatisticsModule')) {
    function getStatisticsModule($type, $db = null) {
        if (!$db) {
            global $db;
            if (!$db) {
                $db = get_db_connection();
            }
        }
        
        // Map of module types to class names
        $moduleMap = [
            'overview' => 'OverviewReport',
            'order' => 'OrderReport',
            'product' => 'ProductReport',
            'user' => 'UserReport',
            'customer' => 'CustomerReport',
            'filter' => 'FilterBuilder',
            'drilldown' => 'DrilldownHandler',
            'metric_card' => 'MetricCard',
            'chart' => 'ChartWidget',
            'table' => 'TableWidget',
            'excel' => 'ExcelExporter',
            'pdf' => 'PDFExporter',
            'csv' => 'CSVExporter',
            'cache' => 'CacheManager'
        ];
        
        // Check with namespace first
        $namespaceMap = [
            'overview' => 'Modules\\Statistics\\Reports\\OverviewReport',
            'order' => 'Modules\\Statistics\\Reports\\OrderReport',
            'product' => 'Modules\\Statistics\\Reports\\ProductReport',
            'user' => 'Modules\\Statistics\\Reports\\UserReport',
            'customer' => 'Modules\\Statistics\\Reports\\CustomerReport',
            'filter' => 'Modules\\Statistics\\Filters\\FilterBuilder',
            'drilldown' => 'Modules\\Statistics\\Core\\DrilldownHandler',
            'metric_card' => 'Modules\\Statistics\\Widgets\\MetricCard',
            'chart' => 'Modules\\Statistics\\Widgets\\ChartWidget',
            'table' => 'Modules\\Statistics\\Widgets\\TableWidget',
            'excel' => 'Modules\\Statistics\\Exporters\\ExcelExporter',
            'pdf' => 'Modules\\Statistics\\Exporters\\PDFExporter',
            'csv' => 'Modules\\Statistics\\Exporters\\CSVExporter',
            'cache' => 'Modules\\Statistics\\Cache\\CacheManager'
        ];
        
        if (!isset($moduleMap[$type])) {
            throw new Exception("Unknown statistics module: $type");
        }
        
        // Try with namespace first
        if (isset($namespaceMap[$type]) && class_exists($namespaceMap[$type])) {
            $className = $namespaceMap[$type];
            return new $className($db);
        }
        
        // Try without namespace
        $className = $moduleMap[$type];
        if (class_exists($className)) {
            return new $className($db);
        }
        
        throw new Exception("Statistics module class not found: $className");
    }
}

/**
 * QuickStats helper class
 */
if (!class_exists('QuickStats')) {
    class QuickStats {
        
        public static function getTodayMetrics($db) {
            $report = getStatisticsModule('overview', $db);
            return $report->setDateRange(date('Y-m-d'), date('Y-m-d'))
                          ->getMetrics();
        }
        
        public static function getYesterdayMetrics($db) {
            $report = getStatisticsModule('overview', $db);
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            return $report->setDateRange($yesterday, $yesterday)
                          ->getMetrics();
        }
        
        public static function getMonthMetrics($db) {
            $report = getStatisticsModule('overview', $db);
            return $report->setDateRange(date('Y-m-01'), date('Y-m-d'))
                          ->getMetrics();
        }
        
        public static function renderMetricCards($db, $metrics = ['orders', 'revenue', 'users', 'success_rate']) {
            $html = '<div class="row g-3">';
            
            $data = self::getTodayMetrics($db);
            $yesterday = self::getYesterdayMetrics($db);
            
            foreach ($metrics as $metric) {
                try {
                    $card = getStatisticsModule('metric_card', $db);
                    
                    switch ($metric) {
                        case 'orders':
                            $card->setTitle('Đơn hàng hôm nay')
                                 ->setValue($data['total_orders'] ?? 0)
                                 ->setCompare($yesterday['total_orders'] ?? 0)
                                 ->setIcon('fa-shopping-cart')
                                 ->setColor('primary');
                            break;
                            
                        case 'revenue':
                            $card->setTitle('Doanh thu hôm nay')
                                 ->setValue($data['total_revenue'] ?? 0)
                                 ->setCompare($yesterday['total_revenue'] ?? 0)
                                 ->setFormat('money')
                                 ->setIcon('fa-dollar-sign')
                                 ->setColor('success');
                            break;
                            
                        case 'users':
                            $card->setTitle('Nhân viên hoạt động')
                                 ->setValue($data['active_users'] ?? 0)
                                 ->setCompare($yesterday['active_users'] ?? 0)
                                 ->setIcon('fa-users')
                                 ->setColor('info');
                            break;
                            
                        case 'success_rate':
                            $card->setTitle('Tỷ lệ thành công')
                                 ->setValue($data['success_rate'] ?? 0)
                                 ->setCompare($yesterday['success_rate'] ?? 0)
                                 ->setFormat('percent')
                                 ->setIcon('fa-check-circle')
                                 ->setColor('warning')
                                 ->setSuffix('%');
                            break;
                    }
                    
                    $html .= '<div class="col-md-3">' . $card->render() . '</div>';
                } catch (Exception $e) {
                    // Skip if card can't be created
                    continue;
                }
            }
            
            $html .= '</div>';
            return $html;
        }
    }
}