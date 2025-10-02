<?php
/**
 * Statistics Base Class - FIXED VERSION
 * Foundation for all statistics modules
 * Sửa lỗi: Column 'created_at' in where clause is ambiguous
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
     * ✅ SỬA: Thêm tham số $tableAlias để chỉ định bảng cho created_at
     */
    protected function buildWhereClause(&$params = [], $tableAlias = 'o') {
        $conditions = [];
        
        // Add permission-based conditions
        $permissionWhere = $this->getPermissionWhere($params);
        if ($permissionWhere) {
            $conditions[] = $permissionWhere;
        }
        
        // ✅ SỬA: Add date range với table alias
        if (!empty($this->dateRange)) {
            $conditions[] = "$tableAlias.created_at BETWEEN ? AND ?";
            $params[] = $this->dateRange['from'];
            $params[] = $this->dateRange['to'];
        }
        
        // Add custom filters
        foreach ($this->filters as $filter) {
            $conditions[] = $this->buildFilterCondition($filter, $params);
        }
        
        // Clean empty conditions
        $conditions = array_filter($conditions);
        
        return empty($conditions) ? '1=1' : implode(' AND ', $conditions);
    }
    
    /**
     * Build filter condition
     */
    protected function buildFilterCondition($filter, &$params) {
        $field = $filter['field'];
        $operator = strtoupper($filter['operator']);
        $value = $filter['value'];
        
        switch ($operator) {
            case 'IN':
                if (is_array($value) && !empty($value)) {
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
                $params[] = '%' . $value . '%';
                return "$field LIKE ?";
                
            case 'IS NULL':
                return "$field IS NULL";
                
            case 'IS NOT NULL':
                return "$field IS NOT NULL";
                
            default:
                $params[] = $value;
                return "$field $operator ?";
        }
        
        return '1=1';
    }
    
    /**
     * Get permission-based WHERE conditions
     */
    protected function getPermissionWhere(&$params) {
        if (!$this->user) {
            return null;
        }
        
        switch ($this->user['role']) {
            case 'admin':
                return null; // No restrictions
                
            case 'manager':
                // Get team members
                $team = $this->db->query(
                    "SELECT telesale_id FROM manager_assignments WHERE manager_id = " . (int)$this->user['id']
                )->fetchAll(\PDO::FETCH_COLUMN);
                
                if (empty($team)) {
                    $params[] = $this->user['id'];
                    return "o.assigned_to = ?";
                }
                
                $team[] = $this->user['id'];
                $placeholders = array_fill(0, count($team), '?');
                $params = array_merge($params, $team);
                return "o.assigned_to IN (" . implode(',', $placeholders) . ")";
                
            case 'telesale':
                $params[] = $this->user['id'];
                return "o.assigned_to = ?";
                
            default:
                return "1=0"; // No access
        }
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
     * Clear errors
     */
    public function clearErrors() {
        $this->errors = [];
        return $this;
    }
    
    /**
     * Abstract method - must be implemented by child classes
     */
    abstract public function getData();
}
