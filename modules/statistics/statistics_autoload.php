<?php
/**
 * Statistics Module Autoloader
 * Automatically load all statistics classes
 */

spl_autoload_register(function ($class) {
    // Check if class belongs to Statistics namespace
    if (strpos($class, 'Modules\\Statistics\\') === 0) {
        // Convert namespace to path
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $path = str_replace('Modules/Statistics/', '', $path);
        
        // Build file path
        $file = __DIR__ . DIRECTORY_SEPARATOR . $path . '.php';
        
        // Load file if exists
        if (file_exists($file)) {
            require_once $file;
            return true;
        }
    }
    
    return false;
});

/**
 * Helper function to get statistics module instance
 */
function getStatisticsModule($type, $db = null) {
    if (!$db) {
        global $db;
    }
    
    $moduleMap = [
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
        'csv' => 'Modules\\Statistics\\Exporters\\CSVExporter'
    ];
    
    if (!isset($moduleMap[$type])) {
        throw new Exception("Unknown statistics module: $type");
    }
    
    $className = $moduleMap[$type];
    
    if (!class_exists($className)) {
        throw new Exception("Statistics module class not found: $className");
    }
    
    return new $className($db);
}

/**
 * Quick statistics helpers
 */
class QuickStats {
    
    /**
     * Get today's metrics
     */
    public static function getTodayMetrics($db) {
        $report = new Modules\Statistics\Reports\OverviewReport($db);
        return $report->setDateRange(date('Y-m-d'), date('Y-m-d'))
                      ->getMetrics();
    }
    
    /**
     * Get this month's metrics
     */
    public static function getMonthMetrics($db) {
        $report = new Modules\Statistics\Reports\OverviewReport($db);
        return $report->setDateRange(date('Y-m-01'), date('Y-m-d'))
                      ->getMetrics();
    }
    
    /**
     * Get user performance
     */
    public static function getUserPerformance($db, $userId, $dateRange = null) {
        $report = new Modules\Statistics\Reports\UserReport($db);
        
        if ($dateRange) {
            $report->setDateRange($dateRange['from'], $dateRange['to']);
        }
        
        return $report->getUserPerformance($userId);
    }
    
    /**
     * Get top products
     */
    public static function getTopProducts($db, $limit = 10) {
        $report = new Modules\Statistics\Reports\ProductReport($db);
        return $report->limit($limit)
                      ->sort('revenue', 'DESC')
                      ->getData();
    }
    
    /**
     * Render metric cards
     */
    public static function renderMetricCards($db, $metrics = ['orders', 'revenue', 'users', 'success_rate']) {
        $html = '<div class="row g-3">';
        
        $data = self::getTodayMetrics($db);
        $yesterday = self::getYesterdayMetrics($db);
        
        foreach ($metrics as $metric) {
            $card = new Modules\Statistics\Widgets\MetricCard($db);
            
            switch ($metric) {
                case 'orders':
                    $card->setTitle('Đơn hàng hôm nay')
                         ->setValue($data['total_orders'])
                         ->setCompare($yesterday['total_orders'])
                         ->setIcon('fa-shopping-cart')
                         ->setColor('primary')
                         ->setDrilldown('overview', 'total_orders');
                    break;
                    
                case 'revenue':
                    $card->setTitle('Doanh thu hôm nay')
                         ->setValue($data['total_revenue'])
                         ->setCompare($yesterday['total_revenue'])
                         ->setFormat('money')
                         ->setIcon('fa-dollar-sign')
                         ->setColor('success')
                         ->setDrilldown('overview', 'total_revenue');
                    break;
                    
                case 'users':
                    $card->setTitle('Nhân viên hoạt động')
                         ->setValue($data['active_users'])
                         ->setCompare($yesterday['active_users'])
                         ->setIcon('fa-users')
                         ->setColor('info')
                         ->setDrilldown('overview', 'active_users');
                    break;
                    
                case 'success_rate':
                    $card->setTitle('Tỷ lệ thành công')
                         ->setValue($data['success_rate'])
                         ->setCompare($yesterday['success_rate'])
                         ->setFormat('percent')
                         ->setIcon('fa-check-circle')
                         ->setColor('warning')
                         ->setSuffix('%')
                         ->setDrilldown('overview', 'success_rate');
                    break;
            }
            
            $html .= '<div class="col-md-3">' . $card->render() . '</div>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Get yesterday's metrics
     */
    private static function getYesterdayMetrics($db) {
        $report = new Modules\Statistics\Reports\OverviewReport($db);
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        return $report->setDateRange($yesterday, $yesterday)
                      ->getMetrics();
    }
}