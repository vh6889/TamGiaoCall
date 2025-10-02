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
                $user = $this->db->query(
                    "SELECT full_name FROM users WHERE id = ?",
                    [$id]
                )->fetch();
                return $user ? $user['full_name'] : "User #$id";
                
            case 'customer':
                return "Khách hàng: $id";
                
            case 'order_label':
                $label = $this->db->query(
                    "SELECT label_name FROM order_labels WHERE label_key = ?",
                    [$id]
                )->fetch();
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
     * Drilldown into product details
     */
    private function drilldownProduct($productId, $params) {
        $dateRange = $params['date_range'] ?? null;
        
        // Get product info
        $productInfo = $this->getProductInfo($productId);
        
        // Get product statistics
        $stats = $this->getProductStatistics($productId, $dateRange);
        
        // Get orders containing this product
        $orders = $this->getProductOrders($productId, $dateRange);
        
        // Get customer analysis
        $customers = $this->getProductCustomers($productId, $dateRange);
        
        // Get trend data
        $trends = $this->getProductTrends($productId, $dateRange);
        
        return [
            'type' => 'product_detail',
            'title' => 'Chi tiết sản phẩm',
            'info' => $productInfo,
            'statistics' => $stats,
            'orders' => $orders,
            'customers' => $customers,
            'trends' => $trends,
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
        $dateRange = $params['date_range'] ?? null;
        
        // Get user info
        $userInfo = $this->getUserInfo($userId);
        
        // Get performance metrics
        $performance = $this->getUserPerformance($userId, $dateRange);
        
        // Get order history
        $orders = $this->getUserOrders($userId, $dateRange);
        
        // Get daily/hourly patterns
        $patterns = $this->getUserPatterns($userId, $dateRange);
        
        return [
            'type' => 'user_detail',
            'title' => 'Chi tiết nhân viên',
            'info' => $userInfo,
            'performance' => $performance,
            'orders' => $orders,
            'patterns' => $patterns,
            'next_drilldown' => [
                ['type' => 'order', 'field' => 'order_id'],
                ['type' => 'date', 'field' => 'date']
            ]
        ];
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
    
    private function getProductInfo($productId) {
        // Extract from orders JSON
        return ['id' => $productId, 'name' => 'Product Name'];
    }
    
    private function getProductStatistics($productId, $dateRange) {
        $where = "o.products LIKE ?";
        $params = ['%' . $productId . '%'];
        
        if ($dateRange) {
            $where .= " AND o.created_at BETWEEN ? AND ?";
            $params[] = $dateRange['from'];
            $params[] = $dateRange['to'];
        }
        
        $sql = "SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'success' THEN o.id END) as success_orders,
                SUM(o.total_amount) as total_revenue,
                COUNT(DISTINCT o.customer_phone) as unique_customers
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    private function getUserInfo($userId) {
        $sql = "SELECT u.*, ep.* 
                FROM users u
                LEFT JOIN employee_performance ep ON u.id = ep.user_id
                WHERE u.id = ?";
        
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