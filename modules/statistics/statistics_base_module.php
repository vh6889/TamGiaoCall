<?php
/**
 * Statistics Base Class
 * Foundation for all statistics modules
 */

namespace Modules\Statistics\Core;

abstract class StatisticsBase {
    protected $db;
    protected $user;
    protected $dateRange = [];
    protected $filters = [];
    protected $groupBy = [];
    protected $orderBy = [];
    protected $limit = null;
    protected $cache = null;
    protected $errors = [];
    
    public function __construct($db, $user = null) {
        $this->db = $db;
        $this->user = $user ?? $_SESSION['user'] ?? null;
    }
    
    /**
     * Set date range
     */
    public function setDateRange($from, $to = null) {
        if ($to === null) {
            $to = date('Y-m-d H:i:s');
        }
        
        $this->dateRange = [
            'from' => $from,
            'to' => $to
        ];
        return $this;
    }
    
    /**
     * Apply filter object
     */
    public function applyFilter($filter) {
        if (is_object($filter) && method_exists($filter, 'getConditions')) {
            $this->filters = array_merge($this->filters, $filter->getConditions());
        } elseif (is_array($filter)) {
            $this->filters = array_merge($this->filters, $filter);
        }
        return $this;
    }
    
    /**
     * Add single filter condition
     */
    public function addFilter($field, $operator, $value) {
        $this->filters[] = [
            'field' => $field,
            'operator' => $operator,
            'value' => $value
        ];
        return $this;
    }
    
    /**
     * Group by fields
     */
    public function groupBy($fields) {
        $this->groupBy = is_array($fields) ? $fields : [$fields];
        return $this;
    }
    
    /**
     * Order by field
     */
    public function orderBy($field, $direction = 'DESC') {
        $this->orderBy[] = [
            'field' => $field,
            'direction' => strtoupper($direction)
        ];
        return $this;
    }
    
    /**
     * Set limit
     */
    public function limit($limit, $offset = 0) {
        $this->limit = [
            'limit' => (int)$limit,
            'offset' => (int)$offset
        ];
        return $this;
    }
    
    /**
     * Get SQL WHERE clause from filters
     */
    protected function buildWhereClause(&$params = []) {
        $conditions = [];
        
        // Add permission-based conditions
        $permissionWhere = $this->getPermissionWhere($params);
        if ($permissionWhere) {
            $conditions[] = $permissionWhere;
        }
        
        // Add date range
        if (!empty($this->dateRange)) {
            $conditions[] = "created_at BETWEEN ? AND ?";
            $params[] = $this->dateRange['from'];
            $params[] = $this->dateRange['to'];
        }
        
        // Add custom filters
        foreach ($this->filters as $filter) {
            $condition = $this->buildFilterCondition($filter, $params);
            if ($condition) {
                $conditions[] = $condition;
            }
        }
        
        return !empty($conditions) ? implode(' AND ', $conditions) : '1=1';
    }
    
    /**
     * Build single filter condition
     */
    protected function buildFilterCondition($filter, &$params) {
        $field = $filter['field'];
        $operator = strtoupper($filter['operator']);
        $value = $filter['value'];
        
        // Handle special operators
        switch ($operator) {
            case 'IN':
                if (is_array($value)) {
                    $placeholders = array_fill(0, count($value), '?');
                    $params = array_merge($params, $value);
                    return "$field IN (" . implode(',', $placeholders) . ")";
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
            case 'NOT LIKE':
                $params[] = '%' . $value . '%';
                return "$field $operator ?";
                
            case 'IS NULL':
            case 'IS NOT NULL':
                return "$field $operator";
                
            default:
                $params[] = $value;
                return "$field $operator ?";
        }
        
        return null;
    }
    
    /**
     * Get permission-based WHERE clause
     */
    protected function getPermissionWhere(&$params) {
        if (!$this->user) return null;
        
        switch ($this->user['role']) {
            case 'admin':
                return null; // No restrictions
                
            case 'manager':
                $teamIds = $this->getTeamIds();
                if (!empty($teamIds)) {
                    $placeholders = array_fill(0, count($teamIds), '?');
                    $params = array_merge($params, $teamIds);
                    return "assigned_to IN (" . implode(',', $placeholders) . ")";
                }
                break;
                
            case 'telesale':
                $params[] = $this->user['id'];
                return "assigned_to = ?";
        }
        
        return "1=0"; // No access
    }
    
    /**
     * Get team IDs for manager
     */
    protected function getTeamIds() {
        if ($this->user['role'] !== 'manager') {
            return [];
        }
        
        $teamIds = $this->db->query(
            "SELECT telesale_id FROM manager_assignments WHERE manager_id = ?",
            [$this->user['id']]
        )->fetchAll(\PDO::FETCH_COLUMN);
        
        $teamIds[] = $this->user['id'];
        return $teamIds;
    }
    
    /**
     * Execute query with error handling
     */
    protected function executeQuery($sql, $params = []) {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (\PDOException $e) {
            $this->errors[] = $e->getMessage();
            throw new \Exception("Database query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Get errors
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Clear all filters and settings
     */
    public function reset() {
        $this->dateRange = [];
        $this->filters = [];
        $this->groupBy = [];
        $this->orderBy = [];
        $this->limit = null;
        $this->errors = [];
        return $this;
    }
    
    /**
     * Abstract method - must be implemented by child classes
     */
    abstract public function getData();
}