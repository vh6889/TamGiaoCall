<?php
/**
 * Permissions Module - FIXED VERSION
 * Check and enforce access control for statistics
 */

namespace Modules\Statistics\Core;

class Permissions {
    private $user;
    private $db;
    private $rolePermissions = [];
    private $teamMembers = [];
    
    public function __construct($db, $user = null) {
        $this->db = $db;
        $this->user = $user ?? $_SESSION['user'] ?? null;
        $this->loadPermissions();
    }
    
    /**
     * Load role permissions
     */
    private function loadPermissions() {
        if (!$this->user) return;
        
        // Load role-based permissions
        $this->rolePermissions = $this->getRolePermissions($this->user['role']);
        
        // Load team members for managers
        if ($this->user['role'] === 'manager') {
            $this->teamMembers = $this->loadTeamMembers($this->user['id']);
        }
    }
    
    /**
     * Get role permissions
     */
    private function getRolePermissions($role) {
        $permissions = [
            'admin' => [
                'view_all_statistics' => true,
                'view_all_orders' => true,
                'view_all_users' => true,
                'view_all_customers' => true,
                'view_financial_data' => true,
                'export_all_data' => true,
                'modify_reports' => true,
                'access_sensitive_data' => true
            ],
            'manager' => [
                'view_team_statistics' => true,
                'view_team_orders' => true,
                'view_team_users' => true,
                'view_customers' => true,
                'view_financial_data' => true,
                'export_team_data' => true,
                'modify_reports' => false,
                'access_sensitive_data' => false
            ],
            'telesale' => [
                'view_own_statistics' => true,
                'view_own_orders' => true,
                'view_own_customers' => true,
                'view_financial_data' => false,
                'export_own_data' => true,
                'modify_reports' => false,
                'access_sensitive_data' => false
            ]
        ];
        
        return $permissions[$role] ?? [];
    }
    
    /**
     * Load team members for manager (PRIVATE method)
     */
    private function loadTeamMembers($managerId) {
        $stmt = $this->db->prepare(
            "SELECT telesale_id FROM manager_assignments WHERE manager_id = ?"
        );
        $stmt->execute([$managerId]);
        $members = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $members[] = $managerId; // Include manager themselves
        return $members;
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($permission) {
        if (!$this->user) return false;
        return $this->rolePermissions[$permission] ?? false;
    }
    
    /**
     * Check if user can view data
     */
    public function canView($type, $id = null) {
        if (!$this->user) return false;
        
        switch ($type) {
            case 'order':
                return $this->canViewOrder($id);
            case 'user':
                return $this->canViewUser($id);
            case 'customer':
                return $this->canViewCustomer($id);
            case 'financial':
                return $this->hasPermission('view_financial_data');
            default:
                return false;
        }
    }
    
    /**
     * Check if user can view specific order
     */
    private function canViewOrder($orderId) {
        // Admin can view all
        if ($this->user['role'] === 'admin') {
            return true;
        }
        
        if (!$orderId) {
            // General order view permission
            return $this->hasPermission('view_all_orders') || 
                   $this->hasPermission('view_team_orders') || 
                   $this->hasPermission('view_own_orders');
        }
        
        // Get order details
        $stmt = $this->db->prepare("SELECT assigned_to FROM orders WHERE id = ?");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        
        if (!$order) return false;
        
        // Check specific permissions
        if ($this->user['role'] === 'manager') {
            return in_array($order['assigned_to'], $this->teamMembers);
        } elseif ($this->user['role'] === 'telesale') {
            return $order['assigned_to'] == $this->user['id'];
        }
        
        return false;
    }
    
    /**
     * Check if user can view specific user data
     */
    private function canViewUser($userId) {
        // Admin can view all
        if ($this->user['role'] === 'admin') {
            return true;
        }
        
        if (!$userId) {
            return $this->hasPermission('view_all_users') || 
                   $this->hasPermission('view_team_users');
        }
        
        // Manager can view team members
        if ($this->user['role'] === 'manager') {
            return in_array($userId, $this->teamMembers);
        }
        
        // User can view themselves
        return $userId == $this->user['id'];
    }
    
    /**
     * Check if user can view customer data
     */
    private function canViewCustomer($customerPhone) {
        // Admin can view all
        if ($this->user['role'] === 'admin') {
            return true;
        }
        
        if (!$customerPhone) {
            return $this->hasPermission('view_customers') || 
                   $this->hasPermission('view_own_customers');
        }
        
        // Check if customer has orders assigned to user/team
        if ($this->user['role'] === 'manager') {
            $placeholders = array_fill(0, count($this->teamMembers), '?');
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM orders 
                 WHERE customer_phone = ? AND assigned_to IN (" . implode(',', $placeholders) . ")"
            );
            $params = array_merge([$customerPhone], $this->teamMembers);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } elseif ($this->user['role'] === 'telesale') {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM orders WHERE customer_phone = ? AND assigned_to = ?"
            );
            $stmt->execute([$customerPhone, $this->user['id']]);
            return $stmt->fetchColumn() > 0;
        }
        
        return false;
    }
    
    /**
     * Check if user can export data
     */
    public function canExport($type = 'all') {
        if (!$this->user) return false;
        
        switch ($type) {
            case 'all':
                return $this->hasPermission('export_all_data');
            case 'team':
                return $this->hasPermission('export_team_data') || 
                       $this->hasPermission('export_all_data');
            case 'own':
                return $this->hasPermission('export_own_data') || 
                       $this->hasPermission('export_team_data') || 
                       $this->hasPermission('export_all_data');
            default:
                return false;
        }
    }
    
    /**
     * Get data access scope for current user
     */
    public function getDataScope() {
        if (!$this->user) {
            return ['scope' => 'none'];
        }
        
        switch ($this->user['role']) {
            case 'admin':
                return [
                    'scope' => 'all',
                    'user_ids' => null,
                    'where_clause' => '1=1',
                    'params' => []
                ];
                
            case 'manager':
                $placeholders = array_fill(0, count($this->teamMembers), '?');
                return [
                    'scope' => 'team',
                    'user_ids' => $this->teamMembers,
                    'where_clause' => 'assigned_to IN (' . implode(',', $placeholders) . ')',
                    'params' => $this->teamMembers
                ];
                
            case 'telesale':
                return [
                    'scope' => 'own',
                    'user_ids' => [$this->user['id']],
                    'where_clause' => 'assigned_to = ?',
                    'params' => [$this->user['id']]
                ];
                
            default:
                return ['scope' => 'none'];
        }
    }
    
    /**
     * Apply data scope to query
     */
    public function applyScopeToQuery($baseQuery, $alias = 'o') {
        $scope = $this->getDataScope();
        
        if ($scope['scope'] === 'none') {
            return ['query' => $baseQuery . ' WHERE 1=0', 'params' => []];
        }
        
        if ($scope['scope'] === 'all') {
            return ['query' => $baseQuery, 'params' => []];
        }
        
        // Apply scope filter
        $whereClause = str_replace('assigned_to', $alias . '.assigned_to', $scope['where_clause']);
        
        if (stripos($baseQuery, 'WHERE') !== false) {
            $query = $baseQuery . ' AND ' . $whereClause;
        } else {
            $query = $baseQuery . ' WHERE ' . $whereClause;
        }
        
        return ['query' => $query, 'params' => $scope['params']];
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin() {
        return $this->user && $this->user['role'] === 'admin';
    }
    
    /**
     * Check if user is manager
     */
    public function isManager() {
        return $this->user && $this->user['role'] === 'manager';
    }
    
    /**
     * Check if user is telesale
     */
    public function isTelesale() {
        return $this->user && $this->user['role'] === 'telesale';
    }
    
    /**
     * Get current user
     */
    public function getUser() {
        return $this->user;
    }
    
    /**
     * Get team members (PUBLIC method - returns the loaded team members)
     */
    public function getTeamMembers() {
        return $this->teamMembers;
    }
}