<?php
/**
 * Statistics API Endpoint
 * Handle all statistics module requests
 */

define('TSM_ACCESS', true);
require_once '../../config.php';
require_once '../../functions.php';
require_once '../../Modules/Statistics/autoload.php';

// Check authentication
require_login();

// JSON response header
header('Content-Type: application/json');

// Get request parameters
$action = $_REQUEST['action'] ?? '';
$type = $_REQUEST['type'] ?? '';
$params = $_REQUEST['params'] ?? [];

// Parse JSON input if present
$jsonInput = file_get_contents('php://input');
if ($jsonInput) {
    $jsonData = json_decode($jsonInput, true);
    if ($jsonData) {
        $action = $jsonData['action'] ?? $action;
        $type = $jsonData['type'] ?? $type;
        $params = $jsonData['params'] ?? $params;
    }
}

try {
    $response = [];
    
    switch ($action) {
        case 'get_report':
            $response = handleGetReport($type, $params);
            break;
            
        case 'get_overview':
            $response = handleGetOverview($params);
            break;
            
        case 'drilldown':
            $response = handleDrilldown($type, $params);
            break;
            
        case 'export':
            handleExport($type, $params);
            break;
            
        case 'get_widget':
            $response = handleGetWidget($type, $params);
            break;
            
        case 'get_chart_data':
            $response = handleGetChartData($type, $params);
            break;
            
        case 'filter_builder':
            $response = handleFilterBuilder($params);
            break;
            
        case 'quick_stats':
            $response = handleQuickStats($type);
            break;
            
        default:
            throw new Exception("Invalid action: $action");
    }
    
    // Success response
    echo json_encode([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    // Error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle get report request
 */
function handleGetReport($type, $params) {
    global $db;
    
    $reportClass = null;
    switch ($type) {
        case 'order':
            $reportClass = new Modules\Statistics\Reports\OrderReport($db);
            break;
            
        case 'product':
            $reportClass = new Modules\Statistics\Reports\ProductReport($db);
            break;
            
        case 'user':
            $reportClass = new Modules\Statistics\Reports\UserReport($db);
            break;
            
        case 'customer':
            $reportClass = new Modules\Statistics\Reports\CustomerReport($db);
            break;
            
        default:
            throw new Exception("Invalid report type: $type");
    }
    
    // Apply date range
    if (isset($params['date_from']) && isset($params['date_to'])) {
        $reportClass->setDateRange($params['date_from'], $params['date_to']);
    }
    
    // Apply filters
    if (isset($params['filters']) && is_array($params['filters'])) {
        $filterBuilder = new Modules\Statistics\Filters\FilterBuilder();
        $filterBuilder->addConditions($params['filters']);
        $reportClass->applyFilter($filterBuilder);
    }
    
    // Apply grouping
    if (isset($params['group_by'])) {
        $reportClass->groupBy($params['group_by']);
    }
    
    // Apply ordering
    if (isset($params['order_by'])) {
        $reportClass->orderBy($params['order_by'], $params['order_direction'] ?? 'DESC');
    }
    
    // Apply limit
    if (isset($params['limit'])) {
        $reportClass->limit($params['limit'], $params['offset'] ?? 0);
    }
    
    return $reportClass->getData();
}

/**
 * Handle get overview request
 */
function handleGetOverview($params) {
    global $db;
    
    $report = new Modules\Statistics\Reports\OverviewReport($db);
    
    // Apply date range
    if (isset($params['date_from']) && isset($params['date_to'])) {
        $report->setDateRange($params['date_from'], $params['date_to']);
    }
    
    return $report->getData();
}

/**
 * Handle drilldown request
 */
function handleDrilldown($type, $params) {
    global $db;
    
    $drilldown = new Modules\Statistics\Core\DrilldownHandler($db);
    
    $id = $params['id'] ?? '';
    $drilldownParams = $params['drilldown_params'] ?? [];
    
    return $drilldown->process($type, $id, $drilldownParams);
}

/**
 * Handle export request
 */
function handleExport($type, $params) {
    global $db;
    
    // Get report data first
    $reportData = handleGetReport($type, $params);
    
    // Create exporter
    $exporter = new Modules\Statistics\Exporters\ExcelExporter();
    
    // Set export parameters
    $exporter->setData($reportData)
             ->setFilename('export_' . $type . '_' . date('Y-m-d'))
             ->setTitle(ucfirst($type) . ' Report');
    
    // Add metadata
    $exporter->addMetadata('Report Type', ucfirst($type))
             ->addMetadata('Generated At', date('Y-m-d H:i:s'))
             ->addMetadata('Generated By', $_SESSION['user']['full_name']);
    
    // Set format
    $format = $params['format'] ?? 'xlsx';
    $exporter->setFormat($format);
    
    // Download
    $exporter->download();
}

/**
 * Handle get widget request
 */
function handleGetWidget($type, $params) {
    global $db;
    
    switch ($type) {
        case 'metric_card':
            $widget = new Modules\Statistics\Widgets\MetricCard($db);
            
            if (isset($params['query'])) {
                $widget->loadFromQuery($params['query'], $params['query_params'] ?? []);
            } else {
                $widget->setTitle($params['title'] ?? '')
                       ->setValue($params['value'] ?? 0)
                       ->setCompare($params['compare'] ?? null)
                       ->setFormat($params['format'] ?? 'number')
                       ->setIcon($params['icon'] ?? 'fa-chart-line')
                       ->setColor($params['color'] ?? 'primary');
            }
            
            if (isset($params['drilldown'])) {
                $widget->setDrilldown(
                    $params['drilldown']['type'],
                    $params['drilldown']['id'],
                    $params['drilldown']['params'] ?? []
                );
            }
            
            return [
                'html' => $widget->render(),
                'data' => $widget->toArray()
            ];
            
        case 'chart':
            $widget = new Modules\Statistics\Widgets\ChartWidget($db);
            
            $widget->setType($params['chart_type'] ?? 'line')
                   ->setTitle($params['title'] ?? '');
            
            if (isset($params['query'])) {
                $widget->loadFromQuery(
                    $params['query'],
                    $params['query_params'] ?? [],
                    $params['label_field'] ?? 'label',
                    $params['value_field'] ?? 'value'
                );
            } else {
                $widget->setData($params['labels'] ?? [], $params['datasets'] ?? []);
            }
            
            if (isset($params['options'])) {
                $widget->setOptions($params['options']);
            }
            
            return [
                'html' => $widget->render()
            ];
            
        default:
            throw new Exception("Invalid widget type: $type");
    }
}

/**
 * Handle get chart data request
 */
function handleGetChartData($type, $params) {
    global $db;
    
    $data = [];
    
    switch ($type) {
        case 'trend':
            $report = new Modules\Statistics\Reports\OverviewReport($db);
            
            if (isset($params['date_from']) && isset($params['date_to'])) {
                $report->setDateRange($params['date_from'], $params['date_to']);
            }
            
            $trends = $report->getTrends();
            
            // Format for Chart.js
            $data = [
                'labels' => array_column($trends['daily'], 'date'),
                'datasets' => [
                    [
                        'label' => 'Tổng đơn',
                        'data' => array_column($trends['daily'], 'total_orders')
                    ],
                    [
                        'label' => 'Đơn thành công',
                        'data' => array_column($trends['daily'], 'success_orders')
                    ],
                    [
                        'label' => 'Doanh thu',
                        'data' => array_column($trends['daily'], 'revenue'),
                        'yAxisID' => 'y1'
                    ]
                ]
            ];
            break;
            
        case 'distribution':
            $report = new Modules\Statistics\Reports\OverviewReport($db);
            
            if (isset($params['date_from']) && isset($params['date_to'])) {
                $report->setDateRange($params['date_from'], $params['date_to']);
            }
            
            $distribution = $report->getDistribution();
            
            // Format for Chart.js pie/doughnut
            $data = [
                'labels' => array_column($distribution, 'label_name'),
                'datasets' => [
                    [
                        'data' => array_column($distribution, 'count'),
                        'backgroundColor' => array_column($distribution, 'color')
                    ]
                ]
            ];
            break;
            
        default:
            throw new Exception("Invalid chart type: $type");
    }
    
    return $data;
}

/**
 * Handle filter builder request
 */
function handleFilterBuilder($params) {
    $builder = new Modules\Statistics\Filters\FilterBuilder();
    
    // Add conditions
    if (isset($params['conditions']) && is_array($params['conditions'])) {
        $builder->addConditions($params['conditions']);
    }
    
    // Set logic
    if (isset($params['logic'])) {
        $builder->setLogic($params['logic']);
    }
    
    // Build SQL
    $sqlParams = [];
    $sql = $builder->buildSQL($sqlParams);
    
    return [
        'sql' => $sql,
        'params' => $sqlParams,
        'conditions' => $builder->getConditions()
    ];
}

/**
 * Handle quick stats request
 */
function handleQuickStats($type) {
    global $db;
    
    switch ($type) {
        case 'today':
            return QuickStats::getTodayMetrics($db);
            
        case 'month':
            return QuickStats::getMonthMetrics($db);
            
        case 'top_products':
            return QuickStats::getTopProducts($db);
            
        default:
            throw new Exception("Invalid quick stats type: $type");
    }
}