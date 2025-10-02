<?php
/**
 * Order Report Module
 * Generate detailed order reports with filters and analysis
 */

namespace Modules\Statistics\Reports;

use Modules\Statistics\Core\StatisticsBase;

class OrderReport extends StatisticsBase {
    
    private $includeProducts = false;
    private $includeCalls = false;
    private $includeLabels = false;
    private $searchTerm = '';
    private $statusFilter = [];
    
    /**
     * Include product details in report
     */
    public function includeProducts($include = true) {
        $this->includeProducts = $include;
        return $this;
    }
    
    /**
     * Include call logs in report
     */
    public function includeCalls($include = true) {
        $this->includeCalls = $include;
        return $this;
    }
    
    /**
     * Include all labels
     */
    public function includeLabels($include = true) {
        $this->includeLabels = $include;
        return $this;
    }
    
    /**
     * Search orders by keyword
     */
    public function search($term) {
        $this->searchTerm = $term;
        return $this;
    }
    
    /**
     * Filter by status
     */
    public function filterByStatus($status) {
        if (is_array($status)) {
            $this->statusFilter = $status;
        } else {
            $this->statusFilter = [$status];
        }
        return $this;
    }
    
    /**
     * Get order report data
     */
    public function getData() {
        return [
            'orders' => $this->getOrders(),
            'summary' => $this->getSummary(),
            'statusBreakdown' => $this->getStatusBreakdown(),
            'timeAnalysis' => $this->getTimeAnalysis(),
            'productAnalysis' => $this->includeProducts ? $this->getProductAnalysis() : null,
            'callAnalysis' => $this->includeCalls ? $this->getCallAnalysis() : null
        ];
    }
    
    /**
     * Get orders with all details
     */
    public function getOrders() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Add search conditions
        if ($this->searchTerm) {
            $searchWhere = "(o.order_number LIKE ? OR o.customer_name LIKE ? 
                            OR o.customer_phone LIKE ? OR o.products LIKE ?)";
            $where .= " AND " . $searchWhere;
            $searchParam = '%' . $this->searchTerm . '%';
            array_push($params, $searchParam, $searchParam, $searchParam, $searchParam);
        }
        
        // Add status filter
        if (!empty($this->statusFilter)) {
            $placeholders = array_fill(0, count($this->statusFilter), '?');
            $where .= " AND o.primary_label IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $this->statusFilter);
        }
        
        $sql = "SELECT 
                o.id,
                o.order_number,
                o.woo_order_id,
                o.customer_name,
                o.customer_phone,
                o.customer_address,
                o.total_amount,
                o.notes,
                o.products,
                o.primary_label,
                o.additional_labels,
                o.call_count,
                o.assigned_to,
                o.created_at,
                o.updated_at,
                ol.label_name,
                ol.color as label_color,
                ol.icon as label_icon,
                ol.core_status,
                u.full_name as assigned_name,
                u.username as assigned_username
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                LEFT JOIN users u ON o.assigned_to = u.id
                WHERE $where";
        
        // Add order by
        if (!empty($this->orderBy)) {
            $orderParts = [];
            foreach ($this->orderBy as $order) {
                $orderParts[] = $order['field'] . ' ' . $order['direction'];
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        } else {
            $sql .= " ORDER BY o.created_at DESC";
        }
        
        // Add limit
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit['limit'];
            if ($this->limit['offset'] > 0) {
                $sql .= " OFFSET " . $this->limit['offset'];
            }
        }
        
        $orders = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        // Process orders
        foreach ($orders as &$order) {
            // Parse products JSON
            $order['products_parsed'] = json_decode($order['products'], true) ?? [];
            
            // Parse additional labels
            $order['additional_labels_parsed'] = json_decode($order['additional_labels'], true) ?? [];
            
            // Get customer labels if requested
            if ($this->includeLabels) {
                $order['customer_labels'] = $this->getCustomerLabels($order['customer_phone']);
            }
            
            // Get call details if requested
            if ($this->includeCalls) {
                $order['calls'] = $this->getOrderCalls($order['id']);
            }
        }
        
        return $orders;
    }
    
    /**
     * Get order summary statistics
     */
    public function getSummary() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Add status filter
        if (!empty($this->statusFilter)) {
            $placeholders = array_fill(0, count($this->statusFilter), '?');
            $where .= " AND o.primary_label IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $this->statusFilter);
        }
        
        $sql = "SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT o.customer_phone) as unique_customers,
                COUNT(DISTINCT o.assigned_to) as active_users,
                SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_order_value,
                MIN(o.total_amount) as min_order_value,
                MAX(o.total_amount) as max_order_value,
                SUM(o.call_count) as total_calls,
                AVG(o.call_count) as avg_calls_per_order,
                MIN(o.created_at) as first_order,
                MAX(o.created_at) as last_order
                FROM orders o
                WHERE $where";
        
        return $this->executeQuery($sql, $params)->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get status breakdown
     */
    public function getStatusBreakdown() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        $sql = "SELECT 
                ol.label_key,
                ol.label_name,
                ol.color,
                ol.icon,
                ol.core_status,
                COUNT(o.id) as order_count,
                SUM(o.total_amount) as total_revenue,
                AVG(o.total_amount) as avg_amount,
                ROUND(COUNT(o.id) * 100.0 / NULLIF((
                    SELECT COUNT(*) FROM orders WHERE $where
                ), 0), 2) as percentage
                FROM order_labels ol
                LEFT JOIN orders o ON o.primary_label = ol.label_key AND ($where)
                GROUP BY ol.label_key, ol.label_name, ol.color, ol.icon, ol.core_status
                HAVING order_count > 0
                ORDER BY order_count DESC";
        
        // Need to duplicate params for subquery
        $allParams = array_merge($params, $params);
        
        return $this->executeQuery($sql, $allParams)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get time-based analysis
     */
    public function getTimeAnalysis() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Add status filter
        if (!empty($this->statusFilter)) {
            $placeholders = array_fill(0, count($this->statusFilter), '?');
            $where .= " AND o.primary_label IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $this->statusFilter);
        }
        
        // Daily breakdown
        $dailySql = "SELECT 
                    DATE(o.created_at) as date,
                    COUNT(o.id) as orders,
                    SUM(o.total_amount) as revenue,
                    COUNT(DISTINCT o.customer_phone) as customers
                    FROM orders o
                    WHERE $where
                    GROUP BY DATE(o.created_at)
                    ORDER BY date DESC
                    LIMIT 30";
        
        // Hourly breakdown
        $hourlySql = "SELECT 
                     HOUR(o.created_at) as hour,
                     COUNT(o.id) as orders,
                     SUM(o.total_amount) as revenue
                     FROM orders o
                     WHERE $where
                     GROUP BY HOUR(o.created_at)
                     ORDER BY hour";
        
        // Day of week breakdown
        $dowSql = "SELECT 
                  DAYOFWEEK(o.created_at) as day_of_week,
                  DAYNAME(o.created_at) as day_name,
                  COUNT(o.id) as orders,
                  SUM(o.total_amount) as revenue
                  FROM orders o
                  WHERE $where
                  GROUP BY DAYOFWEEK(o.created_at), DAYNAME(o.created_at)
                  ORDER BY DAYOFWEEK(o.created_at)";
        
        return [
            'daily' => $this->executeQuery($dailySql, $params)->fetchAll(\PDO::FETCH_ASSOC),
            'hourly' => $this->executeQuery($hourlySql, $params)->fetchAll(\PDO::FETCH_ASSOC),
            'dayOfWeek' => $this->executeQuery($dowSql, $params)->fetchAll(\PDO::FETCH_ASSOC)
        ];
    }
    
    /**
     * Get product analysis
     */
    private function getProductAnalysis() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Add status filter
        if (!empty($this->statusFilter)) {
            $placeholders = array_fill(0, count($this->statusFilter), '?');
            $where .= " AND o.primary_label IN (" . implode(',', $placeholders) . ")";
            $params = array_merge($params, $this->statusFilter);
        }
        
        $sql = "SELECT o.id, o.products, o.total_amount FROM orders o WHERE $where";
        $orders = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        $productStats = [];
        
        foreach ($orders as $order) {
            $products = json_decode($order['products'], true) ?? [];
            
            foreach ($products as $product) {
                $name = $product['name'] ?? $product['product_name'] ?? 'Unknown';
                $sku = $product['sku'] ?? $product['product_id'] ?? '';
                $key = $sku ?: md5($name);
                
                if (!isset($productStats[$key])) {
                    $productStats[$key] = [
                        'sku' => $sku,
                        'name' => $name,
                        'total_quantity' => 0,
                        'total_revenue' => 0,
                        'order_count' => 0,
                        'avg_price' => 0
                    ];
                }
                
                $qty = $product['quantity'] ?? $product['qty'] ?? 1;
                $price = $product['price'] ?? 0;
                
                $productStats[$key]['total_quantity'] += $qty;
                $productStats[$key]['total_revenue'] += ($qty * $price);
                $productStats[$key]['order_count']++;
            }
        }
        
        // Calculate averages and sort
        foreach ($productStats as &$stat) {
            $stat['avg_price'] = $stat['total_quantity'] > 0 
                ? $stat['total_revenue'] / $stat['total_quantity'] 
                : 0;
        }
        
        // Sort by revenue desc
        uasort($productStats, function($a, $b) {
            return $b['total_revenue'] - $a['total_revenue'];
        });
        
        return array_slice($productStats, 0, 50, true);
    }
    
    /**
     * Get call analysis
     */
    private function getCallAnalysis() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        $sql = "SELECT 
                COUNT(DISTINCT cl.id) as total_calls,
                AVG(cl.duration) as avg_duration,
                MIN(cl.duration) as min_duration,
                MAX(cl.duration) as max_duration,
                SUM(CASE WHEN cl.status = 'completed' THEN 1 ELSE 0 END) as completed_calls,
                SUM(CASE WHEN cl.status = 'no_answer' THEN 1 ELSE 0 END) as no_answer_calls,
                SUM(CASE WHEN cl.status = 'busy' THEN 1 ELSE 0 END) as busy_calls
                FROM orders o
                LEFT JOIN call_logs cl ON o.id = cl.order_id
                WHERE $where AND cl.id IS NOT NULL";
        
        return $this->executeQuery($sql, $params)->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get customer labels
     */
    private function getCustomerLabels($phone) {
        $sql = "SELECT cl.label_key, cl.label_name, cl.color, cl.description
                FROM customer_metrics cm
                INNER JOIN customer_labels cl ON JSON_CONTAINS(cm.labels, JSON_QUOTE(cl.label_key))
                WHERE cm.customer_phone = ?";
        
        return $this->executeQuery($sql, [$phone])->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get order call logs
     */
    private function getOrderCalls($orderId) {
        $sql = "SELECT cl.*, u.full_name as user_name
                FROM call_logs cl
                LEFT JOIN users u ON cl.user_id = u.id
                WHERE cl.order_id = ?
                ORDER BY cl.start_time DESC";
        
        return $this->executeQuery($sql, [$orderId])->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Export-friendly data format
     */
    public function getExportData() {
        $orders = $this->getOrders();
        $exportData = [];
        
        foreach ($orders as $order) {
            $row = [
                'ID' => $order['id'],
                'Mã đơn' => $order['order_number'],
                'Khách hàng' => $order['customer_name'],
                'SĐT' => $order['customer_phone'],
                'Địa chỉ' => $order['customer_address'],
                'Tổng tiền' => $order['total_amount'],
                'Trạng thái' => $order['label_name'],
                'Nhân viên' => $order['assigned_name'],
                'Số cuộc gọi' => $order['call_count'],
                'Ngày tạo' => $order['created_at'],
                'Cập nhật' => $order['updated_at']
            ];
            
            // Add product columns if included
            if ($this->includeProducts && !empty($order['products_parsed'])) {
                $productNames = [];
                $productQtys = [];
                foreach ($order['products_parsed'] as $product) {
                    $productNames[] = $product['name'] ?? '';
                    $productQtys[] = $product['qty'] ?? 1;
                }
                $row['Sản phẩm'] = implode(', ', $productNames);
                $row['Số lượng'] = implode(', ', $productQtys);
            }
            
            $exportData[] = $row;
        }
        
        return $exportData;
    }
}