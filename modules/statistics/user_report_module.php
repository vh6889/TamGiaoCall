<?php
/**
 * User Report Module - FIXED VERSION
 * Employee performance statistics and analysis
 */

namespace Modules\Statistics\Reports;

use Modules\Statistics\Core\StatisticsBase;

class UserReport extends StatisticsBase {
    
    private $includePerformance = true;
    private $includeLabels = true;
    private $includeComparison = false;
    
    /**
     * Include performance metrics
     */
    public function includePerformance($include = true) {
        $this->includePerformance = $include;
        return $this;
    }
    
    /**
     * Include user labels
     */
    public function includeLabels($include = true) {
        $this->includeLabels = $include;
        return $this;
    }
    
    /**
     * Include period comparison
     */
    public function includeComparison($include = true) {
        $this->includeComparison = $include;
        return $this;
    }
    
    /**
     * Get user report data
     */
    public function getData() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Build query
        $sql = "SELECT 
                u.id, u.username, u.full_name, u.email, u.phone, u.role, u.status,
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'new' THEN o.id END) as new_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'success' THEN o.id END) as success_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'failed' THEN o.id END) as failed_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'processing' THEN o.id END) as processing_orders,
                SUM(o.total_amount) as total_revenue,
                SUM(CASE WHEN ol.core_status = 'success' THEN o.total_amount END) as success_revenue,
                AVG(o.total_amount) as avg_order_value,
                COUNT(DISTINCT o.customer_phone) as unique_customers,
                AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.updated_at)) as avg_processing_time,
                ROUND(COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) * 100.0 / NULLIF(COUNT(o.id), 0), 2) as success_rate
                ";
        
        if ($this->includePerformance) {
            $sql .= ",
                ep.total_orders_handled,
                ep.successful_orders as lifetime_success,
                ep.failed_orders as lifetime_failed,
                ep.avg_handling_time,
                ep.total_revenue as lifetime_revenue,
                ep.violation_count,
                ep.warning_count,
                ep.suspension_count,
                ep.performance_score,
                ep.last_violation_date
            ";
        }
        
        $sql .= "
            FROM users u
            LEFT JOIN orders o ON o.assigned_to = u.id AND $where
            LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
        ";
        
        if ($this->includePerformance) {
            $sql .= " LEFT JOIN employee_performance ep ON u.id = ep.user_id";
        }
        
        $sql .= " WHERE u.status = 'active'";
        
        // Add grouping
        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $this->groupBy);
        } else {
            $sql .= " GROUP BY u.id";
        }
        
        // Add ordering
        if (!empty($this->orderBy)) {
            $orderClauses = array_map(function($order) {
                return $order['field'] . ' ' . $order['direction'];
            }, $this->orderBy);
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        } else {
            $sql .= " ORDER BY success_rate DESC, total_orders DESC";
        }
        
        // Add limit
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit['limit'];
            if ($this->limit['offset'] > 0) {
                $sql .= " OFFSET " . $this->limit['offset'];
            }
        }
        
        $users = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        // Add labels if requested
        if ($this->includeLabels) {
            foreach ($users as &$user) {
                $user['labels'] = $this->getUserLabels($user['id']);
            }
        }
        
        // Add comparison if requested
        if ($this->includeComparison && !empty($this->dateRange)) {
            foreach ($users as &$user) {
                $user['comparison'] = $this->getUserComparison($user['id']);
            }
        }
        
        // Calculate summary
        $summary = $this->calculateSummary($users);
        
        // Return complete data structure
        return [
            'users' => $users,
            'summary' => $summary
        ];
    }
    
    /**
     * Get specific user performance
     */
    public function getUserPerformance($userId) {
        $params = [$userId];
        $where = "u.id = ?";
        
        // Add date range if set
        $dateWhere = "";
        if (!empty($this->dateRange)) {
            $dateWhere = " AND o.created_at BETWEEN ? AND ?";
            $params[] = $this->dateRange['from'];
            $params[] = $this->dateRange['to'];
        }
        
        $sql = "SELECT 
                u.*,
                COUNT(DISTINCT o.id) as period_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'success' THEN o.id END) as period_success,
                SUM(o.total_amount) as period_revenue,
                SUM(CASE WHEN ol.core_status = 'success' THEN o.total_amount END) as period_success_revenue,
                AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.updated_at)) as avg_processing_time,
                COUNT(DISTINCT o.customer_phone) as unique_customers,
                ep.*
                FROM users u
                LEFT JOIN orders o ON o.assigned_to = u.id $dateWhere
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                LEFT JOIN employee_performance ep ON u.id = ep.user_id
                WHERE $where
                GROUP BY u.id";
        
        $user = $this->executeQuery($sql, $params)->fetch(\PDO::FETCH_ASSOC);
        
        if ($user) {
            // Add daily breakdown
            $user['daily_breakdown'] = $this->getUserDailyBreakdown($userId);
            
            // Add hourly patterns
            $user['hourly_patterns'] = $this->getUserHourlyPatterns($userId);
            
            // Add customer insights
            $user['customer_insights'] = $this->getUserCustomerInsights($userId);
            
            // Add labels
            $user['labels'] = $this->getUserLabels($userId);
        }
        
        return $user;
    }
    
    /**
     * Get user daily breakdown
     */
    private function getUserDailyBreakdown($userId) {
        $params = [$userId];
        $where = "o.assigned_to = ?";
        
        if (!empty($this->dateRange)) {
            $where .= " AND o.created_at BETWEEN ? AND ?";
            $params[] = $this->dateRange['from'];
            $params[] = $this->dateRange['to'];
        }
        
        $sql = "SELECT 
                DATE(o.created_at) as date,
                COUNT(*) as orders,
                COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) as success,
                SUM(o.total_amount) as revenue
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where
                GROUP BY DATE(o.created_at)
                ORDER BY date ASC";
        
        return $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user hourly patterns
     */
    private function getUserHourlyPatterns($userId) {
        $params = [$userId];
        $where = "o.assigned_to = ?";
        
        if (!empty($this->dateRange)) {
            $where .= " AND o.created_at BETWEEN ? AND ?";
            $params[] = $this->dateRange['from'];
            $params[] = $this->dateRange['to'];
        }
        
        $sql = "SELECT 
                HOUR(o.created_at) as hour,
                COUNT(*) as orders,
                ROUND(COUNT(CASE WHEN ol.core_status = 'success' THEN 1 END) * 100.0 / COUNT(*), 2) as success_rate
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE $where
                GROUP BY HOUR(o.created_at)
                ORDER BY hour ASC";
        
        return $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user customer insights
     */
    private function getUserCustomerInsights($userId) {
        $params = [$userId];
        $where = "o.assigned_to = ?";
        
        if (!empty($this->dateRange)) {
            $where .= " AND o.created_at BETWEEN ? AND ?";
            $params[] = $this->dateRange['from'];
            $params[] = $this->dateRange['to'];
        }
        
        $sql = "SELECT 
                COUNT(DISTINCT o.customer_phone) as total_customers,
                COUNT(DISTINCT CASE WHEN cm.is_vip = 1 THEN o.customer_phone END) as vip_customers,
                AVG(cm.total_orders) as avg_customer_orders,
                AVG(cm.total_value) as avg_customer_value
                FROM orders o
                LEFT JOIN customer_metrics cm ON cm.customer_phone = o.customer_phone
                WHERE $where";
        
        return $this->executeQuery($sql, $params)->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Get user labels
     */
    private function getUserLabels($userId) {
        $sql = "SELECT labels FROM employee_performance WHERE user_id = ?";
        $result = $this->executeQuery($sql, [$userId])->fetch(\PDO::FETCH_ASSOC);
        
        if ($result && $result['labels']) {
            $labelKeys = json_decode($result['labels'], true) ?? [];
            
            if (!empty($labelKeys)) {
                $placeholders = array_fill(0, count($labelKeys), '?');
                $sql = "SELECT * FROM user_labels WHERE label_key IN (" . implode(',', $placeholders) . ")";
                return $this->executeQuery($sql, $labelKeys)->fetchAll(\PDO::FETCH_ASSOC);
            }
        }
        
        return [];
    }
    
    /**
     * Get user comparison with previous period
     */
    private function getUserComparison($userId) {
        if (empty($this->dateRange)) {
            return null;
        }
        
        // Calculate previous period
        $currentFrom = $this->dateRange['from'];
        $currentTo = $this->dateRange['to'];
        $daysDiff = (strtotime($currentTo) - strtotime($currentFrom)) / 86400;
        
        $previousFrom = date('Y-m-d H:i:s', strtotime($currentFrom) - ($daysDiff * 86400));
        $previousTo = $currentFrom;
        
        $sql = "SELECT 
                COUNT(DISTINCT o.id) as orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'success' THEN o.id END) as success,
                SUM(o.total_amount) as revenue
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE o.assigned_to = ? AND o.created_at BETWEEN ? AND ?";
        
        $current = $this->executeQuery($sql, [$userId, $currentFrom, $currentTo])->fetch(\PDO::FETCH_ASSOC);
        $previous = $this->executeQuery($sql, [$userId, $previousFrom, $previousTo])->fetch(\PDO::FETCH_ASSOC);
        
        return [
            'current' => $current,
            'previous' => $previous,
            'change' => [
                'orders' => ($current['orders'] ?? 0) - ($previous['orders'] ?? 0),
                'success' => ($current['success'] ?? 0) - ($previous['success'] ?? 0),
                'revenue' => ($current['revenue'] ?? 0) - ($previous['revenue'] ?? 0)
            ],
            'change_percent' => [
                'orders' => $previous['orders'] > 0 ? round((($current['orders'] ?? 0) - $previous['orders']) * 100 / $previous['orders'], 2) : 0,
                'success' => $previous['success'] > 0 ? round((($current['success'] ?? 0) - $previous['success']) * 100 / $previous['success'], 2) : 0,
                'revenue' => $previous['revenue'] > 0 ? round((($current['revenue'] ?? 0) - $previous['revenue']) * 100 / $previous['revenue'], 2) : 0
            ]
        ];
    }
    
    /**
     * Calculate summary statistics
     */
    private function calculateSummary($users) {
        $summary = [
            'total_users' => count($users),
            'total_orders' => 0,
            'total_revenue' => 0,
            'avg_success_rate' => 0,
            'top_performer' => null,
            'bottom_performer' => null
        ];
        
        foreach ($users as $user) {
            $summary['total_orders'] += $user['total_orders'] ?? 0;
            $summary['total_revenue'] += $user['total_revenue'] ?? 0;
        }
        
        if (count($users) > 0) {
            $successRates = array_column($users, 'success_rate');
            $successRates = array_filter($successRates); // Remove null values
            if (count($successRates) > 0) {
                $summary['avg_success_rate'] = round(array_sum($successRates) / count($successRates), 2);
            }
            $summary['top_performer'] = $users[0] ?? null;
            $summary['bottom_performer'] = end($users) ?: null;
        }
        
        return $summary;
    }
}