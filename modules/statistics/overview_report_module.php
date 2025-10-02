<?php
/**
 * Overview Report Module - FIXED VERSION
 * Sửa lỗi: Invalid parameter number trong getDistribution() và getStatusBreakdown()
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
                'change_percent' => $prevValue > 0 
                    ? round(($value - $prevValue) / $prevValue * 100, 2)
                    : ($value > 0 ? 100 : 0)
            ];
        }
        
        return $comparison;
    }
    
    /**
     * Get trends (daily, weekly, monthly)
     */
    public function getTrends() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Daily trends
        $sql = "SELECT 
                DATE(o.created_at) as date,
                COUNT(o.id) as orders,
                SUM(o.total_amount) as revenue,
                COUNT(DISTINCT o.customer_phone) as customers,
                COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) as success_orders
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where
                GROUP BY DATE(o.created_at)
                ORDER BY date DESC
                LIMIT 30";
        
        $daily = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        return [
            'daily' => array_reverse($daily)
        ];
    }
    
    /**
     * Get top performers
     */
public function getTopPerformers() {
    $params = [];
    $where = $this->buildWhereClause($params);
    
    // Top users
    $sql = "SELECT 
            u.id, u.full_name, u.role,
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT CASE WHEN ol.core_status = 'success' THEN o.id END) as success_orders,
            SUM(CASE WHEN ol.core_status = 'success' THEN o.total_amount ELSE 0 END) as revenue,
            ROUND(COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) * 100.0 / 
                  NULLIF(COUNT(*), 0), 2) as success_rate
            FROM users u
            LEFT JOIN orders o ON o.assigned_to = u.id AND ($where)
            LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
            WHERE u.status = 'active'
            GROUP BY u.id, u.full_name, u.role
            HAVING total_orders > 0
            ORDER BY revenue DESC
            LIMIT 10";
    
    $topUsers = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    
    // Top customers - Thay thế cho top products vì không có bảng order_items
    $paramsCustomers = [];
    $whereCustomers = $this->buildWhereClause($paramsCustomers);
    
    $sql = "SELECT 
            o.customer_name,
            o.customer_phone,
            COUNT(DISTINCT o.id) as order_count,
            SUM(o.total_amount) as total_spent,
            MAX(o.created_at) as last_order_date,
            COUNT(DISTINCT CASE WHEN ol.core_status = 'success' THEN o.id END) as success_orders
            FROM orders o
            LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
            WHERE $whereCustomers
            AND o.customer_phone IS NOT NULL
            GROUP BY o.customer_phone, o.customer_name
            ORDER BY total_spent DESC
            LIMIT 10";
    
    $topCustomers = $this->executeQuery($sql, $paramsCustomers)->fetchAll(\PDO::FETCH_ASSOC);
    
    return [
        'users' => $topUsers,
        'customers' => $topCustomers  // Đổi từ 'products' thành 'customers'
    ];
}
    
    /**
     * Get order distribution by labels
     * ✅ SỬA: Duplicate params cho subquery
     */
    public function getDistribution() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // ✅ SỬA: Tạo params riêng cho subquery
        $subqueryParams = [];
        $subqueryWhere = $this->buildWhereClause($subqueryParams);
        
        $sql = "SELECT 
                ol.label_key, ol.label_name, ol.color, ol.icon,
                COUNT(o.id) as count,
                SUM(o.total_amount) as revenue,
                ROUND(COUNT(o.id) * 100.0 / (SELECT COUNT(*) FROM orders o2 WHERE $subqueryWhere), 2) as percentage
                FROM orders o
                JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where
                GROUP BY ol.label_key, ol.label_name, ol.color, ol.icon
                ORDER BY count DESC";
        
        // ✅ SỬA: Merge params cho cả main query và subquery
        $allParams = array_merge($params, $subqueryParams);
        
        return $this->executeQuery($sql, $allParams)->fetchAll(\PDO::FETCH_ASSOC);
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
