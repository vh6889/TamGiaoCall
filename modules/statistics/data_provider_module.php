<?php
/**
 * Data Provider Module
 * Centralized data retrieval from database
 */

namespace Modules\Statistics\Core;

class DataProvider {
    private $db;
    private $cache = [];
    private $queryLog = [];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Get orders data with filters
     */
    public function getOrders($filters = [], $fields = '*') {
        $cacheKey = md5('orders_' . json_encode($filters) . $fields);
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $params = [];
        $where = $this->buildWhereClause($filters, $params);
        
        $sql = "SELECT $fields FROM orders o 
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key 
                WHERE $where";
        
        if (isset($filters['order_by'])) {
            $sql .= " ORDER BY " . $filters['order_by'];
        }
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
            if (isset($filters['offset'])) {
                $sql .= " OFFSET " . (int)$filters['offset'];
            }
        }
        
        $result = $this->executeQuery($sql, $params);
        $this->cache[$cacheKey] = $result;
        
        return $result;
    }
    
    /**
     * Get users data
     */
    public function getUsers($filters = [], $fields = '*') {
        $params = [];
        $where = $this->buildWhereClause($filters, $params);
        
        $sql = "SELECT $fields FROM users u 
                LEFT JOIN employee_performance ep ON u.id = ep.user_id 
                WHERE $where";
        
        if (isset($filters['order_by'])) {
            $sql .= " ORDER BY " . $filters['order_by'];
        }
        
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Get customers data
     */
    public function getCustomers($filters = [], $fields = '*') {
        $params = [];
        $where = $this->buildWhereClause($filters, $params);
        
        $sql = "SELECT $fields FROM customer_metrics cm WHERE $where";
        
        if (isset($filters['order_by'])) {
            $sql .= " ORDER BY " . $filters['order_by'];
        }
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }
        
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Get products from orders JSON
     */
    public function getProducts($filters = []) {
        $orders = $this->getOrders($filters, 'id, products, primary_label');
        $products = [];
        
        foreach ($orders as $order) {
            $orderProducts = json_decode($order['products'], true) ?? [];
            foreach ($orderProducts as $product) {
                $key = $product['sku'] ?? md5($product['name'] ?? '');
                if (!isset($products[$key])) {
                    $products[$key] = [
                        'sku' => $product['sku'] ?? '',
                        'name' => $product['name'] ?? $product['product_name'] ?? '',
                        'total_quantity' => 0,
                        'total_revenue' => 0,
                        'order_count' => 0
                    ];
                }
                
                $qty = $product['quantity'] ?? $product['qty'] ?? 1;
                $price = $product['price'] ?? 0;
                
                $products[$key]['total_quantity'] += $qty;
                $products[$key]['total_revenue'] += $qty * $price;
                $products[$key]['order_count']++;
            }
        }
        
        return array_values($products);
    }
    
    /**
     * Get aggregated data
     */
    public function getAggregatedData($table, $groupBy, $aggregates, $filters = []) {
        $params = [];
        $where = $this->buildWhereClause($filters, $params);
        
        $selectParts = [$groupBy];
        foreach ($aggregates as $alias => $expression) {
            $selectParts[] = "$expression as $alias";
        }
        
        $sql = "SELECT " . implode(', ', $selectParts) . "
                FROM $table 
                WHERE $where 
                GROUP BY $groupBy";
        
        if (isset($filters['having'])) {
            $sql .= " HAVING " . $filters['having'];
        }
        
        if (isset($filters['order_by'])) {
            $sql .= " ORDER BY " . $filters['order_by'];
        }
        
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Get time series data
     */
    public function getTimeSeries($table, $dateField, $interval, $aggregates, $filters = []) {
        $params = [];
        $where = $this->buildWhereClause($filters, $params);
        
        $dateFormat = $this->getDateFormat($interval);
        $selectParts = ["DATE_FORMAT($dateField, '$dateFormat') as period"];
        
        foreach ($aggregates as $alias => $expression) {
            $selectParts[] = "$expression as $alias";
        }
        
        $sql = "SELECT " . implode(', ', $selectParts) . "
                FROM $table 
                WHERE $where 
                GROUP BY period 
                ORDER BY period ASC";
        
        return $this->executeQuery($sql, $params);
    }
    
    /**
     * Get single value
     */
    public function getValue($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(\PDO::FETCH_NUM);
        return $result ? $result[0] : null;
    }
    
    /**
     * Get row
     */
    public function getRow($sql, $params = []) {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Build WHERE clause from filters
     */
    private function buildWhereClause($filters, &$params) {
        $conditions = ['1=1'];
        
        foreach ($filters as $key => $value) {
            // Skip non-filter keys
            if (in_array($key, ['order_by', 'limit', 'offset', 'having', 'group_by'])) {
                continue;
            }
            
            if (is_array($value)) {
                if (isset($value['operator'])) {
                    $conditions[] = $this->buildCondition($key, $value['operator'], $value['value'], $params);
                } else {
                    // IN clause
                    $placeholders = array_fill(0, count($value), '?');
                    $conditions[] = "$key IN (" . implode(',', $placeholders) . ")";
                    $params = array_merge($params, $value);
                }
            } else {
                $conditions[] = "$key = ?";
                $params[] = $value;
            }
        }
        
        return implode(' AND ', $conditions);
    }
    
    /**
     * Build single condition
     */
    private function buildCondition($field, $operator, $value, &$params) {
        switch (strtoupper($operator)) {
            case 'BETWEEN':
                $params[] = $value[0];
                $params[] = $value[1];
                return "$field BETWEEN ? AND ?";
                
            case 'LIKE':
                $params[] = '%' . $value . '%';
                return "$field LIKE ?";
                
            case 'IS NULL':
            case 'IS NOT NULL':
                return "$field $operator";
                
            default:
                $params[] = $value;
                return "$field $operator ?";
        }
    }
    
    /**
     * Get date format for interval
     */
    private function getDateFormat($interval) {
        switch ($interval) {
            case 'hour':
                return '%Y-%m-%d %H:00:00';
            case 'day':
                return '%Y-%m-%d';
            case 'week':
                return '%Y-%u';
            case 'month':
                return '%Y-%m';
            case 'year':
                return '%Y';
            default:
                return '%Y-%m-%d';
        }
    }
    
    /**
     * Execute query with logging
     */
    private function executeQuery($sql, $params = []) {
        $startTime = microtime(true);
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $this->logQuery($sql, $params, microtime(true) - $startTime);
            
            return $result;
        } catch (\Exception $e) {
            $this->logQuery($sql, $params, microtime(true) - $startTime, $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Log query for debugging
     */
    private function logQuery($sql, $params, $duration, $error = null) {
        $this->queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'error' => $error,
            'timestamp' => microtime(true)
        ];
    }
    
    /**
     * Get query log
     */
    public function getQueryLog() {
        return $this->queryLog;
    }
    
    /**
     * Clear cache
     */
    public function clearCache() {
        $this->cache = [];
    }
}