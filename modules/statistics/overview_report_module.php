<?php
/**
 * Overview Report Module
 * Generate comprehensive dashboard overview
 */

namespace Modules\Statistics\Reports;

use Modules\Statistics\Core\StatisticsBase;

class OverviewReport extends StatisticsBase {
    
    /**
     * Get complete overview data
     */
    public function getData() {
        return [
            'metrics' => $this->getMetrics(),
            'comparison' => $this->getComparison(),
            'trends' => $this->getTrends(),
            'topPerformers' => $this->getTopPerformers(),
            'distribution' => $this->getDistribution(),
            'recentActivity' => $this->getRecentActivity()
        ];
    }
    
    /**
     * Get key metrics
     */
    public function getMetrics() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Main metrics query
        $sql = "SELECT 
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'new' THEN o.id END) as new_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'processing' THEN o.id END) as processing_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'success' THEN o.id END) as success_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'failed' THEN o.id END) as failed_orders,
                
                SUM(o.total_amount) as total_revenue,
                SUM(CASE WHEN ol.core_status = 'success' THEN o.total_amount ELSE 0 END) as success_revenue,
                AVG(o.total_amount) as avg_order_value,
                
                COUNT(DISTINCT o.customer_phone) as unique_customers,
                COUNT(DISTINCT o.assigned_to) as active_users,
                
                ROUND(COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0), 2) as success_rate,
                ROUND(AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.updated_at)), 2) as avg_processing_time
                
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where";
        
        $metrics = $this->executeQuery($sql, $params)->fetch(\PDO::FETCH_ASSOC);
        
        // Add calculated metrics
        $metrics['conversion_rate'] = $metrics['total_orders'] > 0 
            ? round($metrics['success_orders'] * 100 / $metrics['total_orders'], 2) 
            : 0;
            
        $metrics['avg_success_value'] = $metrics['success_orders'] > 0
            ? round($metrics['success_revenue'] / $metrics['success_orders'], 0)
            : 0;
        
        return $metrics;
    }
    
    /**
     * Get period comparison
     */
    public function getComparison() {
        if (empty($this->dateRange)) {
            $this->setDateRange(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
        }
        
        // Calculate previous period
        $currentFrom = $this->dateRange['from'];
        $currentTo = $this->dateRange['to'];
        $daysDiff = (strtotime($currentTo) - strtotime($currentFrom)) / 86400;
        
        $previousFrom = date('Y-m-d H:i:s', strtotime($currentFrom) - ($daysDiff * 86400));
        $previousTo = $currentFrom;
        
        // Get current period metrics
        $current = $this->getMetrics();
        
        // Get previous period metrics
        $this->setDateRange($previousFrom, $previousTo);
        $previous = $this->getMetrics();
        
        // Restore date range
        $this->setDateRange($currentFrom, $currentTo);
        
        // Calculate changes
        $comparison = [];
        foreach ($current as $key => $value) {
            $prevValue = $previous[$key] ?? 0;
            $comparison[$key] = [
                'current' => $value,
                'previous' => $prevValue,
                'change' => $value - $prevValue,
                'change_percent' => $prevValue > 0 ? round(($value - $prevValue) * 100 / $prevValue, 2) : 0
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Get trend data for charts
     */
    public function getTrends() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Daily trends
        $sql = "SELECT 
                DATE(o.created_at) as date,
                COUNT(*) as total_orders,
                COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) as success_orders,
                SUM(o.total_amount) as revenue,
                SUM(CASE WHEN ol.core_status = 'success' THEN o.total_amount END) as success_revenue
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where
                GROUP BY DATE(o.created_at)
                ORDER BY date ASC";
        
        $daily = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        // Hourly distribution
        $sql = "SELECT 
                HOUR(o.created_at) as hour,
                COUNT(*) as count
                FROM orders o
                WHERE $where
                GROUP BY HOUR(o.created_at)
                ORDER BY hour ASC";
        
        $hourly = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'daily' => $daily,
            'hourly' => $hourly
        ];
    }
    
    /**
     * Get top performers
     */
    public function getTopPerformers() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        $performers = [];
        
        // Top users
        $sql = "SELECT 
                u.id, u.full_name, u.role,
                COUNT(o.id) as total_orders,
                COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) as success_orders,
                SUM(CASE WHEN ol.core_status = 'success' THEN o.total_amount END) as revenue,
                ROUND(COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) * 100.0 / NULLIF(COUNT(o.id), 0), 2) as success_rate
                FROM orders o
                JOIN users u ON o.assigned_to = u.id
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where AND o.assigned_to IS NOT NULL
                GROUP BY u.id
                ORDER BY revenue DESC
                LIMIT 10";
        
        $performers['users'] = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        // Top products
        $sql = "SELECT 
                product_name,
                SUM(quantity) as total_quantity,
                SUM(revenue) as total_revenue,
                COUNT(DISTINCT order_id) as order_count
                FROM (
                    SELECT 
                        o.id as order_id,
                        JSON_UNQUOTE(JSON_EXTRACT(product.value, '$.name')) as product_name,
                        CAST(JSON_EXTRACT(product.value, '$.quantity') AS UNSIGNED) as quantity,
                        CAST(JSON_EXTRACT(product.value, '$.price') AS DECIMAL(15,2)) * 
                        CAST(JSON_EXTRACT(product.value, '$.quantity') AS UNSIGNED) as revenue
                    FROM orders o,
                    JSON_TABLE(o.products, '$[*]' COLUMNS (value JSON PATH '$')) as product
                    LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                    WHERE $where AND ol.core_status = 'success'
                ) as products
                GROUP BY product_name
                ORDER BY total_revenue DESC
                LIMIT 10";
        
        try {
            $performers['products'] = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            // Fallback for older MySQL versions
            $performers['products'] = [];
        }
        
        // Top customers
        $sql = "SELECT 
                o.customer_phone, o.customer_name,
                COUNT(o.id) as total_orders,
                SUM(o.total_amount) as total_spent,
                MAX(o.created_at) as last_order
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where
                GROUP BY o.customer_phone, o.customer_name
                ORDER BY total_spent DESC
                LIMIT 10";
        
        $performers['customers'] = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        return $performers;
    }
    
    /**
     * Get order distribution by labels
     */
    public function getDistribution() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        $sql = "SELECT 
                ol.label_key, ol.label_name, ol.color, ol.icon,
                COUNT(o.id) as count,
                SUM(o.total_amount) as revenue,
                ROUND(COUNT(o.id) * 100.0 / (SELECT COUNT(*) FROM orders WHERE $where), 2) as percentage
                FROM orders o
                JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where
                GROUP BY ol.label_key
                ORDER BY count DESC";
        
        return $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get recent activity
     */
    public function getRecentActivity() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        $sql = "SELECT 
                o.id, o.order_number, o.customer_name, 
                o.total_amount, o.created_at,
                ol.label_name, ol.color as label_color,
                u.full_name as assigned_to
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                LEFT JOIN users u ON o.assigned_to = u.id
                WHERE $where
                ORDER BY o.created_at DESC
                LIMIT 20";
        
        return $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
}