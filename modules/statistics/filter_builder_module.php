<?php
/**
 * Dynamic Filter Builder
 * Build complex filter conditions with AND/OR logic
 */

namespace Modules\Statistics\Filters;

class FilterBuilder {
    private $conditions = [];
    private $logic = 'AND';
    private $groups = [];
    
    /**
     * Add a condition
     */
    public function addCondition($field, $operator, $value, $logic = null) {
        $this->conditions[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
            'logic' => $logic ?? $this->logic
        ];
        return $this;
    }
    
    /**
     * Add multiple conditions at once
     */
    public function addConditions(array $conditions) {
        foreach ($conditions as $condition) {
            $this->addCondition(
                $condition['field'],
                $condition['operator'],
                $condition['value'],
                $condition['logic'] ?? null
            );
        }
        return $this;
    }
    
    /**
     * Create a condition group with its own logic
     */
    public function addGroup($logic = 'AND') {
        $group = new FilterGroup($logic);
        $this->groups[] = $group;
        return $group;
    }
    
    /**
     * Set default logic (AND/OR)
     */
    public function setLogic($logic) {
        $this->logic = strtoupper($logic);
        return $this;
    }
    
    /**
     * Add label filter (order, customer, or user label)
     */
    public function addLabelFilter($type, $labelKey, $operator = 'HAS') {
        switch ($type) {
            case 'order':
                $this->addCondition('primary_label', '=', $labelKey);
                break;
                
            case 'customer':
                $this->conditions[] = [
                    'type' => 'customer_label',
                    'label_key' => $labelKey,
                    'operator' => $operator
                ];
                break;
                
            case 'user':
                $this->conditions[] = [
                    'type' => 'user_label',
                    'label_key' => $labelKey,
                    'operator' => $operator
                ];
                break;
        }
        return $this;
    }
    
    /**
     * Add date range filter
     */
    public function addDateRange($field, $from, $to) {
        $this->conditions[] = [
            'field' => $field,
            'operator' => 'BETWEEN',
            'value' => [$from, $to]
        ];
        return $this;
    }
    
    /**
     * Add comparison filter (>, <, >=, <=)
     */
    public function addComparison($field, $operator, $value) {
        $validOperators = ['>', '<', '>=', '<=', '=', '!='];
        if (!in_array($operator, $validOperators)) {
            throw new \InvalidArgumentException("Invalid comparison operator: $operator");
        }
        
        $this->addCondition($field, $operator, $value);
        return $this;
    }
    
    /**
     * Add IN filter for multiple values
     */
    public function addIn($field, array $values) {
        $this->addCondition($field, 'IN', $values);
        return $this;
    }
    
    /**
     * Add text search filter
     */
    public function addSearch($field, $searchTerm) {
        $this->addCondition($field, 'LIKE', $searchTerm);
        return $this;
    }
    
    /**
     * Build SQL WHERE clause
     */
    public function buildSQL(&$params = []) {
        if (empty($this->conditions) && empty($this->groups)) {
            return '1=1';
        }
        
        $sql = [];
        
        // Process regular conditions
        foreach ($this->conditions as $i => $condition) {
            $clause = $this->buildConditionSQL($condition, $params);
            if ($clause) {
                if ($i > 0 && isset($condition['logic'])) {
                    $sql[] = $condition['logic'] . ' ' . $clause;
                } else {
                    $sql[] = $clause;
                }
            }
        }
        
        // Process groups
        foreach ($this->groups as $group) {
            $groupSQL = $group->buildSQL($params);
            if ($groupSQL && $groupSQL !== '1=1') {
                $sql[] = $this->logic . ' (' . $groupSQL . ')';
            }
        }
        
        return '(' . implode(' ', $sql) . ')';
    }
    
    /**
     * Build SQL for single condition
     */
    private function buildConditionSQL($condition, &$params) {
        // Handle special condition types
        if (isset($condition['type'])) {
            return $this->buildSpecialConditionSQL($condition, $params);
        }
        
        $field = $condition['field'];
        $operator = strtoupper($condition['operator']);
        $value = $condition['value'];
        
        switch ($operator) {
            case 'IN':
                if (is_array($value) && !empty($value)) {
                    $placeholders = array_fill(0, count($value), '?');
                    $params = array_merge($params, $value);
                    return "$field IN (" . implode(',', $placeholders) . ")";
                }
                break;
                
            case 'NOT IN':
                if (is_array($value) && !empty($value)) {
                    $placeholders = array_fill(0, count($value), '?');
                    $params = array_merge($params, $value);
                    return "$field NOT IN (" . implode(',', $placeholders) . ")";
                }
                break;
                
            case 'BETWEEN':
                if (is_array($value) && count($value) == 2) {
                    $params[] = $value[0];
                    $params[] = $value[1];
                    return "$field BETWEEN ? AND ?";
                }
                break;
                
            case 'LIKE':
                $params[] = '%' . $value . '%';
                return "$field LIKE ?";
                
            case 'NOT LIKE':
                $params[] = '%' . $value . '%';
                return "$field NOT LIKE ?";
                
            case 'IS NULL':
                return "$field IS NULL";
                
            case 'IS NOT NULL':
                return "$field IS NOT NULL";
                
            default:
                $params[] = $value;
                return "$field $operator ?";
        }
        
        return null;
    }
    
    /**
     * Build SQL for special conditions (labels, etc.)
     */
    private function buildSpecialConditionSQL($condition, &$params) {
        switch ($condition['type']) {
            case 'customer_label':
                $params[] = $condition['label_key'];
                if ($condition['operator'] === 'HAS') {
                    return "EXISTS (
                        SELECT 1 FROM customer_metrics cm
                        WHERE cm.customer_phone = o.customer_phone
                        AND JSON_CONTAINS(cm.labels, JSON_QUOTE(?))
                    )";
                } else {
                    return "NOT EXISTS (
                        SELECT 1 FROM customer_metrics cm
                        WHERE cm.customer_phone = o.customer_phone
                        AND JSON_CONTAINS(cm.labels, JSON_QUOTE(?))
                    )";
                }
                
            case 'user_label':
                $params[] = $condition['label_key'];
                if ($condition['operator'] === 'HAS') {
                    return "EXISTS (
                        SELECT 1 FROM employee_performance ep
                        WHERE ep.user_id = o.assigned_to
                        AND JSON_CONTAINS(ep.labels, JSON_QUOTE(?))
                    )";
                } else {
                    return "NOT EXISTS (
                        SELECT 1 FROM employee_performance ep
                        WHERE ep.user_id = o.assigned_to
                        AND JSON_CONTAINS(ep.labels, JSON_QUOTE(?))
                    )";
                }
        }
        
        return null;
    }
    
    /**
     * Get all conditions
     */
    public function getConditions() {
        return $this->conditions;
    }
    
    /**
     * Clear all conditions
     */
    public function clear() {
        $this->conditions = [];
        $this->groups = [];
        return $this;
    }
}

/**
 * Filter Group for nested conditions
 */
class FilterGroup {
    private $logic;
    private $conditions = [];
    
    public function __construct($logic = 'AND') {
        $this->logic = strtoupper($logic);
    }
    
    public function addCondition($field, $operator, $value) {
        $this->conditions[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        ];
        return $this;
    }
    
    public function buildSQL(&$params) {
        if (empty($this->conditions)) {
            return '';
        }
        
        $sql = [];
        $builder = new FilterBuilder();
        
        foreach ($this->conditions as $condition) {
            $clause = $builder->buildConditionSQL($condition, $params);
            if ($clause) {
                $sql[] = $clause;
            }
        }
        
        return implode(' ' . $this->logic . ' ', $sql);
    }
}