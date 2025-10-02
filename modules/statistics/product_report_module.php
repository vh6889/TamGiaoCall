<?php
/**
 * Product Report Module
 * Product sales analysis and statistics
 */

namespace Modules\Statistics\Reports;

use Modules\Statistics\Core\StatisticsBase;

class ProductReport extends StatisticsBase {
    
    private $searchTerm = '';
    private $sortBy = 'revenue';
    private $sortDirection = 'DESC';
    
    /**
     * Set product search term
     */
    public function search($term) {
        $this->searchTerm = $term;
        return $this;
    }
    
    /**
     * Set sorting
     */
    public function sort($by = 'revenue', $direction = 'DESC') {
        $this->sortBy = $by;
        $this->sortDirection = strtoupper($direction);
        return $this;
    }
    
    /**
     * Get product statistics
     */
    public function getData() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Add search filter
        if ($this->searchTerm) {
            $where .= " AND o.products LIKE ?";
            $params[] = '%' . $this->searchTerm . '%';
        }
        
        // Parse products from JSON
        $sql = "SELECT 
                p.product_sku,
                p.product_name,
                SUM(p.quantity) as total_quantity,
                SUM(p.revenue) as total_revenue,
                SUM(CASE WHEN p.order_status = 'success' THEN p.revenue ELSE 0 END) as success_revenue,
                COUNT(DISTINCT p.order_id) as order_count,
                COUNT(DISTINCT CASE WHEN p.order_status = 'success' THEN p.order_id END) as success_orders,
                COUNT(DISTINCT p.customer_phone) as unique_customers,
                AVG(p.price) as avg_price,
                MIN(p.price) as min_price,
                MAX(p.price) as max_price,
                GROUP_CONCAT(DISTINCT p.order_id) as order_ids
                FROM (
                    SELECT 
                        o.id as order_id,
                        o.customer_phone,
                        ol.core_status as order_status,
                        JSON_UNQUOTE(JSON_EXTRACT(product.value, '$.sku')) as product_sku,
                        JSON_UNQUOTE(JSON_EXTRACT(product.value, '$.name')) as product_name,
                        CAST(JSON_EXTRACT(product.value, '$.quantity') AS UNSIGNED) as quantity,
                        CAST(JSON_EXTRACT(product.value, '$.price') AS DECIMAL(15,2)) as price,
                        CAST(JSON_EXTRACT(product.value, '$.quantity') AS UNSIGNED) * 
                        CAST(JSON_EXTRACT(product.value, '$.price') AS DECIMAL(15,2)) as revenue
                    FROM orders o,
                    JSON_TABLE(o.products, '$[*]' COLUMNS (value JSON PATH '$')) as product
                    LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                    WHERE $where
                ) as p
                GROUP BY p.product_sku, p.product_name";
        
        // Add sorting
        switch ($this->sortBy) {
            case 'name':
                $sql .= " ORDER BY product_name " . $this->sortDirection;
                break;
            case 'quantity':
                $sql .= " ORDER BY total_quantity " . $this->sortDirection;
                break;
            case 'orders':
                $sql .= " ORDER BY order_count " . $this->sortDirection;
                break;
            case 'revenue':
            default:
                $sql .= " ORDER BY total_revenue " . $this->sortDirection;
                break;
        }
        
        // Add limit if specified
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit['limit'];
            if ($this->limit['offset'] > 0) {
                $sql .= " OFFSET " . $this->limit['offset'];
            }
        }
        
        try {
            $products = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback for older MySQL without JSON_TABLE
            $products = $this->getProductsFallback($where, $params);
        }
        
        // Calculate additional metrics
        foreach ($products as &$product) {
            $product['success_rate'] = $product['order_count'] > 0
                ? round($product['success_orders'] * 100 / $product['order_count'], 2)
                : 0;
            
            $product['avg_quantity_per_order'] = $product['order_count'] > 0
                ? round($product['total_quantity'] / $product['order_count'], 2)
                : 0;
                
            $product['revenue_per_customer'] = $product['unique_customers'] > 0
                ? round($product['total_revenue'] / $product['unique_customers'], 2)
                : 0;
        }
        
        // Get summary
        $summary = $this->getProductSummary($products);
        
        return [
            'products' => $products,
            'summary' => $summary
        ];
    }
    
    /**
     * Fallback method for older MySQL versions
     */
    private function getProductsFallback($where, $params) {
        $sql = "SELECT o.id, o.products, o.customer_phone, ol.core_status
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where";
        
        $orders = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        $productStats = [];
        
        foreach ($orders as $order) {
            $products = json_decode($order['products'], true) ?? [];
            
            foreach ($products as $product) {
                $sku = $product['sku'] ?? '';
                $name = $product['name'] ?? $product['product_name'] ?? 'Unknown';
                $key = $sku ?: md5($name);
                
                if (!isset($productStats[$key])) {
                    $productStats[$key] = [
                        'product_sku' => $sku,
                        'product_name' => $name,
                        'total_quantity' => 0,
                        'total_revenue' => 0,
                        'success_revenue' => 0,
                        'order_count' => 0,
                        'success_orders' => 0,
                        'unique_customers' => [],
                        'prices' => [],
                        'order_ids' => []
                    ];
                }
                
                $qty = $product['quantity'] ?? $product['qty'] ?? 1;
                $price = $product['price'] ?? 0;
                $revenue = $qty * $price;
                
                $productStats[$key]['total_quantity'] += $qty;
                $productStats[$key]['total_revenue'] += $revenue;
                $productStats[$key]['order_count']++;
                $productStats[$key]['unique_customers'][$order['customer_phone']] = true;
                $productStats[$key]['prices'][] = $price;
                $productStats[$key]['order_ids'][] = $order['id'];
                
                if ($order['core_status'] === 'success') {
                    $productStats[$key]['success_revenue'] += $revenue;
                    $productStats[$key]['success_orders']++;
                }
            }
        }
        
        // Format results
        $results = [];
        foreach ($productStats as $stats) {
            $results[] = [
                'product_sku' => $stats['product_sku'],
                'product_name' => $stats['product_name'],
                'total_quantity' => $stats['total_quantity'],
                'total_revenue' => $stats['total_revenue'],
                'success_revenue' => $stats['success_revenue'],
                'order_count' => $stats['order_count'],
                'success_orders' => $stats['success_orders'],
                'unique_customers' => count($stats['unique_customers']),
                'avg_price' => count($stats['prices']) > 0 ? array_sum($stats['prices']) / count($stats['prices']) : 0,
                'min_price' => !empty($stats['prices']) ? min($stats['prices']) : 0,
                'max_price' => !empty($stats['prices']) ? max($stats['prices']) : 0,
                'order_ids' => implode(',', array_slice($stats['order_ids'], 0, 100))
            ];
        }
        
        return $results;
    }
    
    /**
     * Get product summary statistics
     */
    private function getProductSummary($products) {
        $summary = [
            'total_products' => count($products),
            'total_quantity_sold' => 0,
            'total_revenue' => 0,
            'total_success_revenue' => 0,
            'total_orders' => 0,
            'avg_product_revenue' => 0,
            'top_product' => null,
            'worst_product' => null
        ];
        
        foreach ($products as $product) {
            $summary['total_quantity_sold'] += $product['total_quantity'];
            $summary['total_revenue'] += $product['total_revenue'];
            $summary['total_success_revenue'] += $product['success_revenue'];
            $summary['total_orders'] += $product['order_count'];
        }
        
        if (count($products) > 0) {
            $summary['avg_product_revenue'] = round($summary['total_revenue'] / count($products), 2);
            $summary['top_product'] = $products[0]; // Already sorted
            $summary['worst_product'] = end($products);
        }
        
        return $summary;
    }
    
    /**
     * Get product performance over time
     */
    public function getProductTrends($productSku = null) {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        if ($productSku) {
            $where .= " AND o.products LIKE ?";
            $params[] = '%"sku":"' . $productSku . '"%';
        }
        
        $sql = "SELECT 
                DATE(o.created_at) as date,
                COUNT(DISTINCT o.id) as orders,
                SUM(
                    CASE 
                        WHEN o.products LIKE ? THEN 1 
                        ELSE 0 
                    END
                ) as product_orders
                FROM orders o
                WHERE $where
                GROUP BY DATE(o.created_at)
                ORDER BY date ASC";
        
        if ($productSku) {
            array_unshift($params, '%"sku":"' . $productSku . '"%');
        }
        
        return $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
}