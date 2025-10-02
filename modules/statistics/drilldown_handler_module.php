<?php
/**
 * Drilldown Handler Module
 * Handle interactive drilldown navigation
 */

namespace Modules\Statistics\Core;

class DrilldownHandler {
    private $db;
    private $currentLevel = 0;
    private $breadcrumbs = [];
    private $context = [];
    private $maxLevels = 5;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Process drilldown request
     */
    public function process($type, $id, $params = []) {
        // Add to breadcrumbs
        $this->addBreadcrumb($type, $id, $params);
        
        // Get drilldown data based on type
        switch ($type) {
            case 'overview':
                return $this->drilldownFromOverview($id, $params);
                
            case 'product':
                return $this->drilldownProduct($id, $params);
                
            case 'user':
                return $this->drilldownUser($id, $params);
                
            case 'customer':
                return $this->drilldownCustomer($id, $params);
                
            case 'order_label':
                return $this->drilldownOrderLabel($id, $params);
                
            case 'date':
                return $this->drilldownDate($id, $params);
                
            case 'metric':
                return $this->drilldownMetric($id, $params);
                
            default:
                throw new \Exception("Unknown drilldown type: $type");
        }
    }
    
    /**
     * Add to breadcrumb trail
     */
    private function addBreadcrumb($type, $id, $params) {
        $this->breadcrumbs[] = [
            'level' => $this->currentLevel++,
            'type' => $type,
            'id' => $id,
            'params' => $params,
            'label' => $this->getBreadcrumbLabel($type, $id)
        ];
        
        // Limit breadcrumb depth
        if (count($this->breadcrumbs) > $this->maxLevels) {
            array_shift($this->breadcrumbs);
        }
    }
    
    /**
     * Get label for breadcrumb
     */
    private function getBreadcrumbLabel($type, $id) {
        switch ($type) {
            case 'overview':
                return 'Tổng quan';
                
            case 'product':
                return "Sản phẩm: $id";
                
            case 'user':
                $stmt = $this->db->prepare("SELECT full_name FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $user = $stmt->fetch();
                return $user ? $user['full_name'] : "User #$id";
                
            case 'customer':
                return "Khách hàng: $id";
                
            case 'order_label':
                $stmt = $this->db->prepare("SELECT label_name FROM order_labels WHERE label_key = ?");
                $stmt->execute([$id]);
                $label = $stmt->fetch();
                return $label ? $label['label_name'] : "Label: $id";
                
            case 'date':
                return "Ngày: $id";
                
            default:
                return ucfirst($type) . ": $id";
        }
    }
    
    /**
     * Drilldown from overview to specific metric
     */
    private function drilldownFromOverview($metricType, $params) {
        $dateRange = $params['date_range'] ?? ['from' => date('Y-m-d', strtotime('-30 days')), 'to' => date('Y-m-d')];
        
        switch ($metricType) {
            case 'total_orders':
                return [
                    'type' => 'order_list',
                    'title' => 'Tất cả đơn hàng',
                    'data' => $this->getOrderList($dateRange),
                    'next_drilldown' => ['type' => 'order', 'field' => 'id']
                ];
                
            case 'total_revenue':
                return [
                    'type' => 'revenue_breakdown',
                    'title' => 'Chi tiết doanh thu',
                    'data' => $this->getRevenueBreakdown($dateRange),
                    'charts' => $this->getRevenueCharts($dateRange)
                ];
                
            case 'success_rate':
                return [
                    'type' => 'success_analysis',
                    'title' => 'Phân tích tỷ lệ thành công',
                    'data' => $this->getSuccessAnalysis($dateRange)
                ];
                
            case 'active_users':
                return [
                    'type' => 'user_list',
                    'title' => 'Nhân viên hoạt động',
                    'data' => $this->getActiveUsers($dateRange),
                    'next_drilldown' => ['type' => 'user', 'field' => 'user_id']
                ];
                
            default:
                return ['error' => 'Unknown metric type'];
        }
    }
    
    /**
     * Drilldown metric details
     */
    private function drilldownMetric($metricId, $params) {
        $dateRange = isset($params['date_range']) ? $params['date_range'] : [
            'from' => date('Y-m-d', strtotime('-30 days')), 
            'to' => date('Y-m-d')
        ];
        
        switch ($metricId) {
            case 'total_orders':
                return $this->drilldownTotalOrders($dateRange);
                
            case 'total_revenue':
                return $this->drilldownTotalRevenue($dateRange);
                
            case 'success_orders':
                return $this->drilldownSuccessOrders($dateRange);
                
            case 'failed_orders':
                return $this->drilldownFailedOrders($dateRange);
                
            case 'new_orders':
                return $this->drilldownNewOrders($dateRange);
                
            case 'processing_orders':
                return $this->drilldownProcessingOrders($dateRange);
                
            case 'unique_customers':
                return $this->drilldownUniqueCustomers($dateRange);
                
            case 'active_users':
                return $this->drilldownActiveUsers($dateRange);
                
            default:
                return $this->drilldownFromOverview($metricId, $params);
        }
    }
    
    /**
     * Drilldown total orders
     */
    private function drilldownTotalOrders($dateRange) {
        $sql = "SELECT 
                o.id,
                o.order_number,
                o.customer_name,
                o.customer_phone,
                o.total_amount,
                o.created_at,
                ol.label_name,
                ol.color as label_color,
                u.full_name as assigned_to
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                LEFT JOIN users u ON o.assigned_to = u.id
                WHERE o.created_at BETWEEN ? AND ?
                ORDER BY o.created_at DESC
                LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'type' => 'order_list',
            'title' => 'Tất cả đơn hàng',
            'data' => $orders,
            'summary' => $this->getOrderSummary($dateRange),
            'next_drilldown' => ['type' => 'order', 'field' => 'id']
        ];
    }
    
    /**
     * Drilldown total revenue
     */
    private function drilldownTotalRevenue($dateRange) {
        // Revenue by status
        $sql = "SELECT 
                ol.label_name,
                ol.color,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_revenue
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE o.created_at BETWEEN ? AND ?
                GROUP BY ol.label_key, ol.label_name, ol.color
                ORDER BY total_revenue DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        $revenueByStatus = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Top revenue orders
        $sql = "SELECT 
                o.id,
                o.order_number,
                o.customer_name,
                o.total_amount,
                o.created_at,
                u.full_name as assigned_to
                FROM orders o
                LEFT JOIN users u ON o.assigned_to = u.id
                WHERE o.created_at BETWEEN ? AND ?
                ORDER BY o.total_amount DESC
                LIMIT 20";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        $topOrders = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'type' => 'revenue_analysis',
            'title' => 'Phân tích doanh thu',
            'revenue_by_status' => $revenueByStatus,
            'top_orders' => $topOrders,
            'summary' => $this->getRevenueSummary($dateRange)
        ];
    }
    
    /**
     * Drilldown success orders
     */
    private function drilldownSuccessOrders($dateRange) {
        return $this->getOrdersByStatus('success', $dateRange, 'Đơn hàng thành công');
    }
    
    /**
     * Drilldown failed orders
     */
    private function drilldownFailedOrders($dateRange) {
        return $this->getOrdersByStatus('failed', $dateRange, 'Đơn hàng thất bại');
    }
    
    /**
     * Drilldown new orders
     */
    private function drilldownNewOrders($dateRange) {
        return $this->getOrdersByStatus('new', $dateRange, 'Đơn hàng mới');
    }
    
    /**
     * Drilldown processing orders
     */
    private function drilldownProcessingOrders($dateRange) {
        return $this->getOrdersByStatus('processing', $dateRange, 'Đơn hàng đang xử lý');
    }
    
    /**
     * Drilldown unique customers
     */
    private function drilldownUniqueCustomers($dateRange) {
        $sql = "SELECT 
                customer_phone,
                customer_name,
                COUNT(*) as order_count,
                SUM(total_amount) as total_spent,
                MAX(created_at) as last_order
                FROM orders
                WHERE created_at BETWEEN ? AND ?
                GROUP BY customer_phone, customer_name
                ORDER BY total_spent DESC
                LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        
        return [
            'type' => 'customer_list',
            'title' => 'Khách hàng',
            'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'next_drilldown' => ['type' => 'customer', 'field' => 'customer_phone']
        ];
    }
    
    /**
     * Drilldown active users
     */
    private function drilldownActiveUsers($dateRange) {
        $sql = "SELECT 
                u.id,
                u.full_name,
                u.role,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_revenue
                FROM users u
                LEFT JOIN orders o ON o.assigned_to = u.id 
                    AND o.created_at BETWEEN ? AND ?
                WHERE u.status = 'active'
                GROUP BY u.id, u.full_name, u.role
                HAVING order_count > 0
                ORDER BY total_revenue DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        
        return [
            'type' => 'user_list',
            'title' => 'Nhân viên hoạt động',
            'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'next_drilldown' => ['type' => 'user', 'field' => 'id']
        ];
    }
    
    /**
     * Drilldown into product details
     */
    private function drilldownProduct($productId, $params) {
        $dateRange = isset($params['date_range']) ? $params['date_range'] : null;
        
        return [
            'type' => 'product_detail',
            'title' => 'Chi tiết sản phẩm',
            'info' => $this->getProductInfo($productId),
            'statistics' => $this->getProductStatistics($productId, $dateRange),
            'orders' => $this->getProductOrders($productId, $dateRange),
            'customers' => $this->getProductCustomers($productId, $dateRange),
            'trends' => $this->getProductTrends($productId, $dateRange),
            'next_drilldown' => [
                ['type' => 'order', 'field' => 'order_id'],
                ['type' => 'customer', 'field' => 'customer_phone']
            ]
        ];
    }
    
    /**
     * Drilldown into user performance
     */
    private function drilldownUser($userId, $params) {
        $dateRange = isset($params['date_range']) ? $params['date_range'] : null;
        
        return [
            'type' => 'user_detail',
            'title' => 'Chi tiết nhân viên',
            'info' => $this->getUserInfo($userId),
            'performance' => $this->getUserPerformance($userId, $dateRange),
            'orders' => $this->getUserOrders($userId, $dateRange),
            'patterns' => $this->getUserPatterns($userId, $dateRange),
            'next_drilldown' => [
                ['type' => 'order', 'field' => 'order_id'],
                ['type' => 'date', 'field' => 'date']
            ]
        ];
    }
    
    /**
     * Drilldown customer details
     */
    private function drilldownCustomer($customerId, $params) {
        $dateRange = isset($params['date_range']) ? $params['date_range'] : null;
        
        return [
            'type' => 'customer_detail',
            'title' => 'Chi tiết khách hàng',
            'info' => $this->getCustomerInfo($customerId),
            'orders' => $this->getCustomerOrders($customerId, $dateRange),
            'statistics' => $this->getCustomerStatistics($customerId, $dateRange)
        ];
    }
    
    /**
     * Drilldown order label
     */
    private function drilldownOrderLabel($labelKey, $params) {
        $dateRange = isset($params['date_range']) ? $params['date_range'] : null;
        
        $sql = "SELECT * FROM order_labels WHERE label_key = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$labelKey]);
        $labelInfo = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'type' => 'label_detail',
            'title' => 'Chi tiết nhãn: ' . ($labelInfo['label_name'] ?? $labelKey),
            'info' => $labelInfo,
            'orders' => $this->getLabelOrders($labelKey, $dateRange),
            'statistics' => $this->getLabelStatistics($labelKey, $dateRange)
        ];
    }
    
    /**
     * Drilldown date
     */
    private function drilldownDate($date, $params) {
        return [
            'type' => 'date_detail',
            'title' => 'Chi tiết ngày: ' . $date,
            'orders' => $this->getDateOrders($date),
            'hourly_data' => $this->getHourlyData($date),
            'statistics' => $this->getDateStatistics($date)
        ];
    }
    
    /**
     * Helper: Get orders by status
     */
    private function getOrdersByStatus($status, $dateRange, $title) {
        $sql = "SELECT 
                o.*,
                ol.label_name,
                ol.color as label_color,
                u.full_name as assigned_to
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                LEFT JOIN users u ON o.assigned_to = u.id
                WHERE o.created_at BETWEEN ? AND ?
                AND ol.core_status = ?
                ORDER BY o.created_at DESC
                LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to'], $status]);
        
        return [
            'type' => 'order_list',
            'title' => $title,
            'data' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'filter' => $status,
            'next_drilldown' => ['type' => 'order', 'field' => 'id']
        ];
    }
    
    /**
     * Helper: Get order summary
     */
    private function getOrderSummary($dateRange) {
        $sql = "SELECT 
                COUNT(*) as total_count,
                SUM(total_amount) as total_revenue,
                AVG(total_amount) as avg_amount,
                MIN(total_amount) as min_amount,
                MAX(total_amount) as max_amount
                FROM orders
                WHERE created_at BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Helper: Get revenue summary
     */
    private function getRevenueSummary($dateRange) {
        $sql = "SELECT 
                COUNT(*) as total_orders,
                SUM(total_amount) as total_revenue,
                SUM(CASE WHEN ol.core_status = 'success' THEN total_amount ELSE 0 END) as success_revenue,
                SUM(CASE WHEN ol.core_status = 'failed' THEN total_amount ELSE 0 END) as failed_revenue
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE o.created_at BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Helper methods for data retrieval
     */
    private function getOrderList($dateRange) {
        $sql = "SELECT o.*, ol.label_name, ol.color, u.full_name as assigned_to
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                LEFT JOIN users u ON o.assigned_to = u.id
                WHERE o.created_at BETWEEN ? AND ?
                ORDER BY o.created_at DESC
                LIMIT 100";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function getRevenueBreakdown($dateRange) {
        $sql = "SELECT 
                DATE(o.created_at) as date,
                ol.label_name as status,
                COUNT(*) as count,
                SUM(o.total_amount) as revenue
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE o.created_at BETWEEN ? AND ?
                GROUP BY DATE(o.created_at), ol.label_key
                ORDER BY date DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function getRevenueCharts($dateRange) {
        return [];
    }
    
    private function getSuccessAnalysis($dateRange) {
        return [];
    }
    
    private function getActiveUsers($dateRange) {
        $sql = "SELECT DISTINCT u.id as user_id, u.full_name, u.role
                FROM users u
                INNER JOIN orders o ON o.assigned_to = u.id
                WHERE o.created_at BETWEEN ? AND ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$dateRange['from'], $dateRange['to']]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    private function getProductInfo($productId) {
        return ['id' => $productId, 'name' => 'Product Name'];
    }
    
    private function getProductStatistics($productId, $dateRange) {
        return [];
    }
    
    private function getProductOrders($productId, $dateRange) {
        return [];
    }
    
    private function getProductCustomers($productId, $dateRange) {
        return [];
    }
    
    private function getProductTrends($productId, $dateRange) {
        return [];
    }
    
    private function getUserInfo($userId) {
        $sql = "SELECT u.* FROM users u WHERE u.id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    private function getUserPerformance($userId, $dateRange) {
        $where = "o.assigned_to = ?";
        $params = [$userId];
        
        if ($dateRange) {
            $where .= " AND o.created_at BETWEEN ? AND ?";
            $params[] = $dateRange['from'];
            $params[] = $dateRange['to'];
        }
        
        $sql = "SELECT 
                COUNT(*) as total_orders,
                COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) as success_orders,
                SUM(o.total_amount) as total_revenue,
                AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.updated_at)) as avg_processing_time
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    private function getUserOrders($userId, $dateRange) {
        return [];
    }
    
    private function getUserPatterns($userId, $dateRange) {
        return [];
    }
    
    private function getCustomerInfo($customerId) {
        return ['phone' => $customerId];
    }
    
    private function getCustomerOrders($customerId, $dateRange) {
        return [];
    }
    
    private function getCustomerStatistics($customerId, $dateRange) {
        return [];
    }
    
    private function getLabelOrders($labelKey, $dateRange) {
        return [];
    }
    
    private function getLabelStatistics($labelKey, $dateRange) {
        return [];
    }
    
    private function getDateOrders($date) {
        return [];
    }
    
    private function getHourlyData($date) {
        return [];
    }
    
    private function getDateStatistics($date) {
        return [];
    }
    
    /**
     * Get breadcrumb trail
     */
    public function getBreadcrumbs() {
        return $this->breadcrumbs;
    }
    
    /**
     * Go back one level
     */
    public function goBack() {
        if (count($this->breadcrumbs) > 1) {
            array_pop($this->breadcrumbs);
            $this->currentLevel--;
            
            $lastBreadcrumb = end($this->breadcrumbs);
            return $this->process($lastBreadcrumb['type'], $lastBreadcrumb['id'], $lastBreadcrumb['params']);
        }
        
        return null;
    }
    
    /**
     * Reset navigation
     */
    public function reset() {
        $this->breadcrumbs = [];
        $this->currentLevel = 0;
        $this->context = [];
    }
}