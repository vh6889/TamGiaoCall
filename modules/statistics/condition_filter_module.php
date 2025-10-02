<?php
/**
 * Condition Filter Module  
 * Handle complex conditional filtering with nested logic
 */

namespace Modules\Statistics\Filters;

class ConditionFilter {
    private $conditions = [];
    private $groups = [];
    private $logic = 'AND';
    
    /**
     * Add simple condition
     */
    public function addCondition($field, $operator, $value, $logic = null) {
        $this->conditions[] = [
            'type' => 'simple',
            'field' => $field,
            'operator' => strtoupper($operator),
            'value' => $value,
            'logic' => $logic ?? $this->logic
        ];
        return $this;
    }
    
    /**
     * Add range condition
     */
    public function addRangeCondition($field, $min, $max, $logic = null) {
        $this->conditions[] = [
            'type' => 'range',
            'field' => $field,
            'min' => $min,
            'max' => $max,
            'logic' => $logic ?? $this->logic
        ];
        return $this;
    }
    
    /**
     * Add IN condition
     */
    public function addInCondition($field, array $values, $logic = null) {
        $this->conditions[] = [
            'type' => 'in',
            'field' => $field,
            'values' => $values,
            'logic' => $logic ?? $this->logic
        ];
        return $this;
    }
    
    /**
     * Add EXISTS condition
     */
    public function addExistsCondition($subquery, $params = [], $logic = null) {
        $this->conditions[] = [
            'type' => 'exists',
            'subquery' => $subquery,
            'params' => $params,
            'logic' => $logic ?? $this->logic
        ];
        return $this;
    }
    
    /**
     * Add HAVING condition
     */
    public function addHavingCondition($expression, $operator, $value) {
        $this->conditions[] = [
            'type' => 'having',
            'expression' => $expression,
            'operator' => $operator,
            'value' => $value
        ];
        return $this;
    }
    
    /**
     * Create nested condition group
     */
    public function createGroup($logic = 'AND') {
        $group = new ConditionGroup($logic);
        $this->groups[] = $group;
        return $group;
    }
    
    /**
     * Set default logic
     */
    public function setLogic($logic) {
        $this->logic = strtoupper($logic);
        return $this;
    }
    
    /**
     * Build WHERE clause
     */
    public function buildWhereClause(&$params = []) {
        $clauses = [];
        
        // Process simple conditions
        foreach ($this->conditions as $condition) {
            if ($condition['type'] === 'having') continue;
            
            $clause = $this->buildConditionClause($condition, $params);
            if ($clause) {
                $clauses[] = $clause;
            }
        }
        
        // Process groups
        foreach ($this->groups as $group) {
            $groupClause = $group->buildClause($params);
            if ($groupClause) {
                $clauses[] = '(' . $groupClause . ')';
            }
        }
        
        if (empty($clauses)) {
            return '1=1';
        }
        
        // Combine with logic
        $result = array_shift($clauses);
        foreach ($clauses as $clause) {
            $result .= ' ' . $this->logic . ' ' . $clause;
        }
        
        return $result;
    }
    
    /**
     * Build HAVING clause
     */
    public function buildHavingClause(&$params = []) {
        $havingClauses = [];
        
        foreach ($this->conditions as $condition) {
            if ($condition['type'] !== 'having') continue;
            
            $clause = $condition['expression'] . ' ' . $condition['operator'] . ' ?';
            $params[] = $condition['value'];
            $havingClauses[] = $clause;
        }
        
        return empty($havingClauses) ? '' : implode(' AND ', $havingClauses);
    }
    
    /**
     * Build single condition clause
     */
    private function buildConditionClause($condition, &$params) {
        switch ($condition['type']) {
            case 'simple':
                return $this->buildSimpleClause($condition, $params);
                
            case 'range':
                return $this->buildRangeClause($condition, $params);
                
            case 'in':
                return $this->buildInClause($condition, $params);
                
            case 'exists':
                return $this->buildExistsClause($condition, $params);
                
            default:
                return null;
        }
    }
    
    /**
     * Build simple condition clause
     */
    private function buildSimpleClause($condition, &$params) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        switch ($operator) {
            case 'IS NULL':
            case 'IS NOT NULL':
                return "$field $operator";
                
            case 'LIKE':
            case 'NOT LIKE':
                $params[] = '%' . $value . '%';
                return "$field $operator ?";
                
            case 'STARTS WITH':
                $params[] = $value . '%';
                return "$field LIKE ?";
                
            case 'ENDS WITH':
                $params[] = '%' . $value;
                return "$field LIKE ?";
                
            default:
                $params[] = $value;
                return "$field $operator ?";
        }
    }
    
    /**
     * Build range condition clause
     */
    private function buildRangeClause($condition, &$params) {
        $field = $condition['field'];
        $params[] = $condition['min'];
        $params[] = $condition['max'];
        return "$field BETWEEN ? AND ?";
    }
    
    /**
     * Build IN condition clause
     */
    private function buildInClause($condition, &$params) {
        $field = $condition['field'];
        $values = $condition['values'];
        
        if (empty($values)) {
            return '1=0';
        }
        
        $placeholders = array_fill(0, count($values), '?');
        $params = array_merge($params, $values);
        
        return "$field IN (" . implode(',', $placeholders) . ")";
    }
    
    /**
     * Build EXISTS condition clause
     */
    private function buildExistsClause($condition, &$params) {
        $params = array_merge($params, $condition['params']);
        return "EXISTS (" . $condition['subquery'] . ")";
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
    
    /**
     * Validate conditions
     */
    public function validate() {
        $errors = [];
        
        foreach ($this->conditions as $i => $condition) {
            if ($condition['type'] === 'simple') {
                if (empty($condition['field'])) {
                    $errors[] = "Condition $i: Field is required";
                }
                if (empty($condition['operator'])) {
                    $errors[] = "Condition $i: Operator is required";
                }
            }
        }
        
        return $errors;
    }
}

/**
 * Nested condition group
 */
class ConditionGroup {
    private $logic;
    private $conditions = [];
    
    public function __construct($logic = 'AND') {
        $this->logic = strtoupper($logic);
    }
    
    public function addCondition($field, $operator, $value) {
        $this->conditions[] = [
            'field' => $field,
            'operator' => strtoupper($operator),
            'value' => $value
        ];
        return $this;
    }
    
    public function buildClause(&$params) {
        if (empty($this->conditions)) {
            return '';
        }
        
        $clauses = [];
        foreach ($this->conditions as $condition) {
            $clause = $this->buildConditionClause($condition, $params);
            if ($clause) {
                $clauses[] = $clause;
            }
        }
        
        return implode(' ' . $this->logic . ' ', $clauses);
    }
    
    private function buildConditionClause($condition, &$params) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];
        
        if (in_array($operator, ['IS NULL', 'IS NOT NULL'])) {
            return "$field $operator";
        }
        
        $params[] = $value;
        return "$field $operator ?";
    }
}