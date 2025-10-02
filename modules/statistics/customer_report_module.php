<?php
/**
 * Customer Report Module
 * Customer analytics and segmentation
 */

namespace Modules\Statistics\Reports;

use Modules\Statistics\Core\StatisticsBase;

class CustomerReport extends StatisticsBase {
    
    private $segmentBy = null;
    private $includeLabels = true;
    private $includeOrders = false;
    
    /**
     * Set customer segmentation
     */
    public function segmentBy($criteria) {
        $this->segmentBy = $criteria;
        return $this;
    }
    
    /**
     * Include customer labels
     */
    public function includeLabels($include = true) {
        $this->includeLabels = $include;
        return $this;
    }
    
    /**
     * Include order history
     */
    public function includeOrders($include = false) {
        $this->includeOrders = $include;
        return $this;
    }
    
    /**
     * Get customer report data
     */
    public function getData() {
        $params = [];
        $where = $this->buildWhereClause($params);
        
        // Build query
        $sql = "SELECT 
                c.customer_phone,
                MAX(o.customer_name) as customer_name,
                MAX(o.customer_email) as customer_email,
                COUNT(DISTINCT o.id) as total_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'success' THEN o.id END) as success_orders,
                COUNT(DISTINCT CASE WHEN ol.core_status = 'failed' THEN o.id END) as failed_orders,
                SUM(o.total_amount) as total_spent,
                SUM(CASE WHEN ol.core_status = 'success' THEN o.total_amount END) as success_value,
                AVG(o.total_amount) as avg_order_value,
                MIN(o.created_at) as first_order_date,
                MAX(o.created_at) as last_order_date,
                DATEDIFF(MAX(o.created_at), MIN(o.created_at)) as customer_lifetime_days,
                COUNT(DISTINCT o.assigned_to) as different_agents,
                cm.is_vip,
                cm.is_blacklisted,
                cm.risk_score
                FROM (
                    SELECT DISTINCT customer_phone FROM orders WHERE $where
                ) c
                LEFT JOIN orders o ON o.customer_phone = c.customer_phone AND $where
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                LEFT JOIN customer_metrics cm ON cm.customer_phone = c.customer_phone
                GROUP BY c.customer_phone";
        
        // Add ordering
        if (!empty($this->orderBy)) {
            $orderClauses = array_map(function($order) {
                return $order['field'] . ' ' . $order['direction'];
            }, $this->orderBy);
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        } else {
            $sql .= " ORDER BY total_spent DESC";
        }
        
        // Add limit
        if ($this->limit) {
            $sql .= " LIMIT " . $this->limit['limit'];
            if ($this->limit['offset'] > 0) {
                $sql .= " OFFSET " . $this->limit['offset'];
            }
        }
        
        $customers = $this->executeQuery($sql, $params)->fetchAll(\PDO::FETCH_ASSOC);
        
        // Add labels if requested
        if ($this->includeLabels) {
            foreach ($customers as &$customer) {
                $customer['labels'] = $this->getCustomerLabels($customer['customer_phone']);
            }
        }
        
        // Add order history if requested
        if ($this->includeOrders) {
            foreach ($customers as &$customer) {
                $customer['recent_orders'] = $this->getCustomerOrders($customer['customer_phone'], 5);
            }
        }
        
        // Segment customers if requested
        if ($this->segmentBy) {
            $customers = $this->segmentCustomers($customers);
        }
        
        // Calculate summary
        $summary = $this->calculateSummary($customers);
        
        return [
            'customers' => $customers,
            'summary' => $summary,
            'segments' => $this->segmentBy ? $this->getSegmentSummary($customers) : null
        ];
    }
    
    /**
     * Get customer labels
     */
    private function getCustomerLabels($customerPhone) {
        $sql = "SELECT labels FROM customer_metrics WHERE customer_phone = ?";
        $result = $this->executeQuery($sql, [$customerPhone])->fetch(\PDO::FETCH_ASSOC);
        
        if ($result && $result['labels']) {
            $labelKeys = json_decode($result['labels'], true) ?? [];
            
            if (!empty($labelKeys)) {
                $placeholders = array_fill(0, count($labelKeys), '?');
                $sql = "SELECT * FROM customer_labels WHERE label_key IN (" . implode(',', $placeholders) . ")";
                return $this->executeQuery($sql, $labelKeys)->fetchAll(\PDO::FETCH_ASSOC);
            }
        }
        
        return [];
    }
    
    /**
     * Get customer orders
     */
    private function getCustomerOrders($customerPhone, $limit = 10) {
        $sql = "SELECT o.*, ol.label_name, ol.color as label_color
                FROM orders o
                LEFT JOIN order_labels ol ON o.primary_label = ol.label_key
                WHERE o.customer_phone = ?
                ORDER BY o.created_at DESC
                LIMIT ?";
        
        return $this->executeQuery($sql, [$customerPhone, $limit])->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Segment customers
     */
    private function segmentCustomers($customers) {
        foreach ($customers as &$customer) {
            $customer['segment'] = $this->determineSegment($customer);
        }
        
        return $customers;
    }
    
    /**
     * Determine customer segment
     */
    private function determineSegment($customer) {
        switch ($this->segmentBy) {
            case 'value':
                if ($customer['total_spent'] >= 10000000) {
                    return 'high_value';
                } elseif ($customer['total_spent'] >= 5000000) {
                    return 'medium_value';
                } else {
                    return 'low_value';
                }
                
            case 'frequency':
                if ($customer['total_orders'] >= 10) {
                    return 'frequent';
                } elseif ($customer['total_orders'] >= 5) {
                    return 'regular';
                } else {
                    return 'occasional';
                }
                
            case 'recency':
                $daysSinceLastOrder = (time() - strtotime($customer['last_order_date'])) / 86400;
                if ($daysSinceLastOrder <= 7) {
                    return 'active';
                } elseif ($daysSinceLastOrder <= 30) {
                    return 'recent';
                } elseif ($daysSinceLastOrder <= 90) {
                    return 'dormant';
                } else {
                    return 'lost';
                }
                
            case 'rfm':
                // RFM Segmentation
                $recencyScore = $this->calculateRecencyScore($customer);
                $frequencyScore = $this->calculateFrequencyScore($customer);
                $monetaryScore = $this->calculateMonetaryScore($customer);
                
                if ($recencyScore >= 4 && $frequencyScore >= 4 && $monetaryScore >= 4) {
                    return 'champions';
                } elseif ($recencyScore >= 3 && $frequencyScore >= 3 && $monetaryScore >= 3) {
                    return 'loyal_customers';
                } elseif ($recencyScore >= 3 && $frequencyScore <= 2) {
                    return 'new_customers';
                } elseif ($recencyScore <= 2 && $frequencyScore >= 3) {
                    return 'at_risk';
                } else {
                    return 'needs_attention';
                }
                
            default:
                return 'unclassified';
        }
    }
    
    /**
     * Calculate RFM scores
     */
    private function calculateRecencyScore($customer) {
        $daysSince = (time() - strtotime($customer['last_order_date'])) / 86400;
        if ($daysSince <= 7) return 5;
        if ($daysSince <= 14) return 4;
        if ($daysSince <= 30) return 3;
        if ($daysSince <= 60) return 2;
        return 1;
    }
    
    private function calculateFrequencyScore($customer) {
        if ($customer['total_orders'] >= 20) return 5;
        if ($customer['total_orders'] >= 10) return 4;
        if ($customer['total_orders'] >= 5) return 3;
        if ($customer['total_orders'] >= 2) return 2;
        return 1;
    }
    
    private function calculateMonetaryScore($customer) {
        if ($customer['total_spent'] >= 20000000) return 5;
        if ($customer['total_spent'] >= 10000000) return 4;
        if ($customer['total_spent'] >= 5000000) return 3;
        if ($customer['total_spent'] >= 1000000) return 2;
        return 1;
    }
    
    /**
     * Get segment summary
     */
    private function getSegmentSummary($customers) {
        $segments = [];
        
        foreach ($customers as $customer) {
            $segment = $customer['segment'] ?? 'unclassified';
            
            if (!isset($segments[$segment])) {
                $segments[$segment] = [
                    'count' => 0,
                    'total_value' => 0,
                    'avg_orders' => 0,
                    'avg_value' => 0
                ];
            }
            
            $segments[$segment]['count']++;
            $segments[$segment]['total_value'] += $customer['total_spent'];
            $segments[$segment]['avg_orders'] += $customer['total_orders'];
        }
        
        // Calculate averages
        foreach ($segments as $name => &$data) {
            if ($data['count'] > 0) {
                $data['avg_orders'] = round($data['avg_orders'] / $data['count'], 2);
                $data['avg_value'] = round($data['total_value'] / $data['count'], 2);
            }
        }
        
        return $segments;
    }
    
    /**
     * Calculate summary statistics
     */
    private function calculateSummary($customers) {
        $summary = [
            'total_customers' => count($customers),
            'total_lifetime_value' => 0,
            'avg_lifetime_value' => 0,
            'total_orders' => 0,
            'vip_customers' => 0,
            'blacklisted_customers' => 0,
            'avg_orders_per_customer' => 0
        ];
        
        foreach ($customers as $customer) {
            $summary['total_lifetime_value'] += $customer['total_spent'] ?? 0;
            $summary['total_orders'] += $customer['total_orders'] ?? 0;
            
            if ($customer['is_vip'] ?? false) {
                $summary['vip_customers']++;
            }
            
            if ($customer['is_blacklisted'] ?? false) {
                $summary['blacklisted_customers']++;
            }
        }
        
        if ($summary['total_customers'] > 0) {
            $summary['avg_lifetime_value'] = round($summary['total_lifetime_value'] / $summary['total_customers'], 2);
            $summary['avg_orders_per_customer'] = round($summary['total_orders'] / $summary['total_customers'], 2);
        }
        
        return $summary;
    }
}