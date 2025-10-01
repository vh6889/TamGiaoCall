<?php
/**
 * Advanced Rule Engine for Telesale Manager
 * Compatible with actual database schema
 */

class RuleEngine {
    private $db;
    private $logger;
    private $entityLoaders = [];
    private $actionHandlers = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->logger = new RuleLogger($db);
        $this->initializeHandlers();
    }
    
    /**
     * Initialize entity loaders and action handlers
     */
    private function initializeHandlers() {
        // Entity loaders
        $this->entityLoaders = [
            'order' => [$this, 'loadOrderContext'],
            'customer' => [$this, 'loadCustomerContext'],
            'employee' => [$this, 'loadEmployeeContext'],
            'task' => [$this, 'loadTaskContext']
        ];
        
        // Action handlers
        $this->actionHandlers = [
            // Order actions
            'change_order_status' => [$this, 'changeOrderStatus'],
            'assign_order_to_role' => [$this, 'assignOrderToRole'],
            'assign_order_to_user' => [$this, 'assignOrderToUser'],
            'add_order_label' => [$this, 'addOrderLabel'],
            
            // Customer actions
            'add_customer_label' => [$this, 'addCustomerLabel'],
            'mark_customer_vip' => [$this, 'markCustomerVIP'],
            'add_to_blacklist' => [$this, 'addToBlacklist'],
            
            // Employee actions
            'add_user_label' => [$this, 'addUserLabel'],
            'suspend_user' => [$this, 'suspendUser'],
            'send_warning' => [$this, 'sendWarning'],
            
            // System actions
            'create_task' => [$this, 'createTask'],
            'send_notification' => [$this, 'sendNotification'],
            'create_reminder' => [$this, 'createReminder'],
            'log_action' => [$this, 'logAction']
        ];
    }
    
    /**
     * Evaluate all active rules for an entity
     */
    public function evaluate($entityType, $entityId, $triggerEvent = null) {
        try {
            // Load entity context with all related data
            $context = $this->loadEntityContext($entityType, $entityId);
            
            if (!$context) {
                throw new Exception("Entity not found: $entityType #$entityId");
            }
            
            // Get applicable rules
            $rules = $this->getApplicableRules($entityType, $triggerEvent);
            
            $executedRules = [];
            
            foreach ($rules as $rule) {
                // Check if conditions match
                if ($this->evaluateConditions($rule['trigger_conditions'], $context)) {
                    // Execute actions
                    $results = $this->executeActions($rule['actions'], $context);
                    
                    // Log execution
                    $this->logger->logExecution($rule, $context, $results);
                    
                    $executedRules[] = [
                        'rule_id' => $rule['id'],
                        'rule_name' => $rule['name'],
                        'results' => $results
                    ];
                }
            }
            
            return $executedRules;
            
        } catch (Exception $e) {
            $this->logger->logError($e->getMessage(), $entityType, $entityId);
            throw $e;
        }
    }
    
    /**
     * Load entity context with all related data
     */
    private function loadEntityContext($entityType, $entityId) {
        if (!isset($this->entityLoaders[$entityType])) {
            throw new Exception("Unknown entity type: $entityType");
        }
        
        return call_user_func($this->entityLoaders[$entityType], $entityId);
    }
    
    /**
     * Load order context with labels, customer, employee
     */
    private function loadOrderContext($orderId) {
        // Get order data
        $order = $this->db->query(
            "SELECT o.*, 
                    u.role as assigned_to_role,
                    u.username as assigned_to_username,
                    TIMESTAMPDIFF(HOUR, o.created_at, NOW()) as hours_since_created,
                    TIMESTAMPDIFF(HOUR, o.assigned_at, NOW()) as hours_since_assigned
             FROM orders o
             LEFT JOIN users u ON o.assigned_to = u.id
             WHERE o.id = ?",
            [$orderId]
        )->fetch();
        
        if (!$order) return null;
        
        // Get order labels (FROM order_labels)
        $order['labels'] = $this->getOrderLabels($orderId);
        
        // Get customer data and labels
        $order['customer'] = $this->loadCustomerByPhone($order['customer_phone']);
        
        // Get assigned employee data
        if ($order['assigned_to']) {
            $order['employee'] = $this->loadEmployeeContext($order['assigned_to']);
        }
        
        return [
            'entity_type' => 'order',
            'entity_id' => $orderId,
            'order' => $order,
            'customer' => $order['customer'],
            'employee' => $order['employee'] ?? null
        ];
    }
    
    /**
     * Load customer context with metrics and labels
     */
    private function loadCustomerContext($customerId) {
        // For customer, we use phone as identifier
        $customer = $this->db->query(
            "SELECT cm.*, 
                    COUNT(DISTINCT o.id) as total_orders_real,
                    SUM(o.total_amount) as total_value_real
             FROM customer_metrics cm
             LEFT JOIN orders o ON o.customer_phone = cm.customer_phone
             WHERE cm.customer_id = ?
             GROUP BY cm.customer_id",
            [$customerId]
        )->fetch();
        
        if (!$customer) return null;
        
        // Get customer labels
        $customer['labels'] = $this->getCustomerLabels($customer['customer_phone']);
        
        return [
            'entity_type' => 'customer',
            'entity_id' => $customerId,
            'customer' => $customer
        ];
    }
    
    /**
     * Load employee context with performance and labels
     */
    private function loadEmployeeContext($userId) {
        $employee = $this->db->query(
            "SELECT u.*,
                    ep.total_orders_handled,
                    ep.successful_orders,
                    ep.violation_count,
                    ep.performance_score,
                    COUNT(DISTINCT o.id) as current_active_orders
             FROM users u
             LEFT JOIN employee_performance ep ON u.id = ep.user_id
             LEFT JOIN orders o ON o.assigned_to = u.id AND o.status NOT IN ('completed', 'cancelled')
             WHERE u.id = ?
             GROUP BY u.id",
            [$userId]
        )->fetch();
        
        if (!$employee) return null;
        
        // Get user labels
        $employee['labels'] = $this->getUserLabels($userId);
        
        return [
            'entity_type' => 'employee',
            'entity_id' => $userId,
            'employee' => $employee
        ];
    }
    
    /**
     * Evaluate conditions tree
     */
    private function evaluateConditions($conditions, $context) {
        $type = $conditions['type'] ?? 'AND';
        $rules = $conditions['rules'] ?? [];
        
        if (empty($rules)) return false;
        
        switch ($type) {
            case 'AND':
                foreach ($rules as $rule) {
                    if (!$this->evaluateSingleCondition($rule, $context)) {
                        return false;
                    }
                }
                return true;
                
            case 'OR':
                foreach ($rules as $rule) {
                    if ($this->evaluateSingleCondition($rule, $context)) {
                        return true;
                    }
                }
                return false;
                
            default:
                return false;
        }
    }
    
    /**
     * Evaluate single condition
     */
    private function evaluateSingleCondition($condition, $context) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $expectedValue = $condition['value'];
        
        // Get actual value from context
        $actualValue = $this->getFieldValue($field, $context);
        
        // Special handling for labels
        if (strpos($field, '_label') !== false || strpos($field, '.label') !== false) {
            return $this->evaluateLabelCondition($field, $operator, $expectedValue, $context);
        }
        
        // Regular field comparison
        switch ($operator) {
            case 'equals':
                return $actualValue == $expectedValue;
                
            case 'not_equals':
                return $actualValue != $expectedValue;
                
            case 'greater_than':
                return $actualValue > $expectedValue;
                
            case 'less_than':
                return $actualValue < $expectedValue;
                
            case 'greater_than_or_equals':
                return $actualValue >= $expectedValue;
                
            case 'less_than_or_equals':
                return $actualValue <= $expectedValue;
                
            case 'contains':
                return strpos($actualValue, $expectedValue) !== false;
                
            case 'not_contains':
                return strpos($actualValue, $expectedValue) === false;
                
            case 'in':
                $values = is_array($expectedValue) ? $expectedValue : explode(',', $expectedValue);
                return in_array($actualValue, $values);
                
            case 'not_in':
                $values = is_array($expectedValue) ? $expectedValue : explode(',', $expectedValue);
                return !in_array($actualValue, $values);
                
            case 'is_true':
                return $actualValue == true;
                
            case 'is_false':
                return $actualValue == false;
                
            default:
                return false;
        }
    }
    
    /**
     * Get field value from context using dot notation
     */
    private function getFieldValue($field, $context) {
        $parts = explode('.', $field);
        $value = $context;
        
        foreach ($parts as $part) {
            if (is_array($value) && isset($value[$part])) {
                $value = $value[$part];
            } else {
                return null;
            }
        }
        
        return $value;
    }
    
    /**
     * Execute actions
     */
    private function executeActions($actions, $context) {
        $results = [];
        
        foreach ($actions as $action) {
            $type = $action['type'];
            $params = $action['params'] ?? [];
            
            if (!isset($this->actionHandlers[$type])) {
                $results[] = [
                    'action' => $type,
                    'status' => 'failed',
                    'error' => "Unknown action type: $type"
                ];
                continue;
            }
            
            try {
                $result = call_user_func($this->actionHandlers[$type], $params, $context);
                $results[] = [
                    'action' => $type,
                    'status' => 'success',
                    'result' => $result
                ];
            } catch (Exception $e) {
                $results[] = [
                    'action' => $type,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }
    
    // ========== ACTION HANDLERS ==========
    
    /**
     * Change order status
     */
    private function changeOrderStatus($params, $context) {
        $orderId = $context['order']['id'];
        $newStatus = $params['status'];
        
        $this->db->query(
            "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?",
            [$newStatus, $orderId]
        );
        
        // Log to order_notes
        $this->db->query(
            "INSERT INTO order_notes (order_id, note_type, content, user_id) 
             VALUES (?, 'system', ?, NULL)",
            [$orderId, "Rule engine changed status to: $newStatus"]
        );
        
        return "Order status changed to $newStatus";
    }
    
    /**
     * Assign order to role
     */
    private function assignOrderToRole($params, $context) {
        $orderId = $context['order']['id'];
        $role = $params['role'];
        
        // Find available user with this role
        $user = $this->db->query(
            "SELECT id FROM users 
             WHERE role = ? AND status = 'active' 
             ORDER BY (SELECT COUNT(*) FROM orders WHERE assigned_to = users.id AND status NOT IN ('completed', 'cancelled')) ASC
             LIMIT 1",
            [$role]
        )->fetch();
        
        if (!$user) {
            throw new Exception("No available user with role: $role");
        }
        
        $this->db->query(
            "UPDATE orders SET assigned_to = ?, assigned_at = NOW() WHERE id = ?",
            [$user['id'], $orderId]
        );
        
        return "Order assigned to user #{$user['id']} (role: $role)";
    }
    
    /**
     * Add customer label
     */
    private function addCustomerLabel($params, $context) {
        $phone = $context['customer']['customer_phone'] ?? $context['order']['customer_phone'];
        $labelKey = $params['label_key'];
        
        // Check if label exists
        $label = $this->db->query(
            "SELECT id FROM customer_labels WHERE label_key = ?",
            [$labelKey]
        )->fetch();
        
        if (!$label) {
            throw new Exception("Label not found: $labelKey");
        }
        
        // Update customer_metrics labels (JSON)
        $this->db->query(
            "UPDATE customer_metrics 
             SET labels = JSON_ARRAY_APPEND(COALESCE(labels, '[]'), '$', ?)
             WHERE customer_phone = ?",
            [$labelKey, $phone]
        );
        
        return "Added label $labelKey to customer";
    }
    
    /**
     * Suspend user
     */
    private function suspendUser($params, $context) {
        $userId = $context['employee']['id'];
        $hours = $params['duration_hours'] ?? 24;
        $reason = $params['reason'] ?? 'Rule engine suspension';
        
        $suspendUntil = date('Y-m-d H:i:s', time() + ($hours * 3600));
        
        $this->db->query(
            "UPDATE users 
             SET status = 'suspended', 
                 suspension_reason = ?, 
                 suspension_until = ?
             WHERE id = ?",
            [$reason, $suspendUntil, $userId]
        );
        
        // Reassign active orders
        $this->db->query(
            "UPDATE orders 
             SET assigned_to = NULL, status = " . get_new_status_key() . "
             WHERE assigned_to = ? AND status NOT IN ('completed', 'cancelled')",
            [$userId]
        );
        
        return "User suspended until $suspendUntil";
    }
    
    /**
     * Create task
     */
    private function createTask($params, $context) {
        $taskType = $params['task_type'];
        $dueInHours = $params['due_in_hours'] ?? 1;
        $description = $params['description'] ?? '';
        
        $entityType = $context['entity_type'];
        $entityId = $context['entity_id'];
        
        $dueAt = date('Y-m-d H:i:s', time() + ($dueInHours * 3600));
        
        $this->db->query(
            "INSERT INTO tasks (task_type, entity_type, entity_id, title, description, due_at, status)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')",
            [$taskType, $entityType, $entityId, "Task: $taskType", $description, $dueAt]
        );
        
        return "Task created, due at $dueAt";
    }
    
    /**
     * Send notification
     */
    private function sendNotification($params, $context) {
        $to = $params['to']; // user_id or role
        $priority = $params['priority'] ?? 'normal';
        $message = $params['message'];
        
        // Determine recipient(s)
        $userIds = [];
        
        if (is_numeric($to)) {
            $userIds[] = $to;
        } elseif (in_array($to, ['admin', 'manager', 'telesale'])) {
            $users = $this->db->query(
                "SELECT id FROM users WHERE role = ? AND status = 'active'",
                [$to]
            )->fetchAll();
            
            foreach ($users as $user) {
                $userIds[] = $user['id'];
            }
        }
        
        // Insert notifications
        foreach ($userIds as $userId) {
            $this->db->query(
                "INSERT INTO notifications (user_id, type, title, message, priority, action_url)
                 VALUES (?, 'rule_engine', 'Rule Notification', ?, ?, ?)",
                [$userId, $message, $priority, '/orders']
            );
        }
        
        return "Notification sent to " . count($userIds) . " users";
    }
    
    // ========== HELPER METHODS ==========
    
    /**
     * Get applicable rules for entity type
     */
    private function getApplicableRules($entityType, $triggerEvent = null) {
        $query = "SELECT * FROM rules 
                  WHERE entity_type = ? 
                  AND is_active = 1 
                  ORDER BY priority DESC, id ASC";
        
        return $this->db->query($query, [$entityType])->fetchAll();
    }
    
    /**
     * Get order labels
     */
    private function getOrderLabels($orderId) {
        $order = $this->db->query(
            "SELECT status FROM orders WHERE id = ?",
            [$orderId]
        )->fetch();
        
        // In this system, order status itself acts as label
        return [$order['status']];
    }
    
    /**
     * Get customer labels
     */
    private function getCustomerLabels($phone) {
        $metrics = $this->db->query(
            "SELECT labels FROM customer_metrics WHERE customer_phone = ?",
            [$phone]
        )->fetch();
        
        if ($metrics && $metrics['labels']) {
            return json_decode($metrics['labels'], true);
        }
        
        return [];
    }
    
    /**
     * Get user labels
     */
    private function getUserLabels($userId) {
        $performance = $this->db->query(
            "SELECT labels FROM employee_performance WHERE user_id = ?",
            [$userId]
        )->fetch();
        
        if ($performance && $performance['labels']) {
            return json_decode($performance['labels'], true);
        }
        
        return [];
    }
    
    /**
     * Load customer by phone
     */
    private function loadCustomerByPhone($phone) {
        // Check if customer exists in metrics
        $customer = $this->db->query(
            "SELECT * FROM customer_metrics WHERE customer_phone = ?",
            [$phone]
        )->fetch();
        
        if (!$customer) {
            // Create new customer metrics entry
            $this->db->query(
                "INSERT INTO customer_metrics (customer_phone, updated_at) 
                 VALUES (?, NOW())
                 ON DUPLICATE KEY UPDATE updated_at = NOW()",
                [$phone]
            );
            
            $customer = [
                'customer_phone' => $phone,
                'total_orders' => 0,
                'labels' => []
            ];
        } else {
            $customer['labels'] = $this->getCustomerLabels($phone);
        }
        
        return $customer;
    }
}

/**
 * Rule Logger class
 */
class RuleLogger {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function logExecution($rule, $context, $results) {
        $this->db->query(
            "INSERT INTO rule_executions (rule_id, entity_id, entity_type, execution_status, execution_result, executed_at)
             VALUES (?, ?, ?, ?, ?, NOW())",
            [
                $rule['id'],
                $context['entity_id'],
                $context['entity_type'],
                $this->determineStatus($results),
                json_encode($results)
            ]
        );
    }
    
    public function logError($message, $entityType, $entityId) {
        $this->db->query(
            "INSERT INTO action_logs (entity_id, entity_type, action_type, action_data, created_at)
             VALUES (?, ?, 'rule_error', ?, NOW())",
            [
                $entityId,
                $entityType,
                json_encode(['error' => $message])
            ]
        );
    }
    
    private function determineStatus($results) {
        $failed = 0;
        $success = 0;
        
        foreach ($results as $result) {
            if ($result['status'] === 'success') {
                $success++;
            } else {
                $failed++;
            }
        }
        
        if ($failed === 0) return 'success';
        if ($success === 0) return 'failed';
        return 'partial';
    }
}

/**
 * Rule Manager - CRUD operations for rules
 */
class RuleManager {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Create new rule
     */
    public function createRule($data) {
        $ruleKey = 'rule_' . uniqid();
        
        $this->db->query(
            "INSERT INTO rules (rule_key, name, description, entity_type, rule_type, priority, trigger_conditions, actions, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $ruleKey,
                $data['name'],
                $data['description'] ?? '',
                $data['entity_type'],
                $data['rule_type'] ?? 'condition_based',
                $data['priority'] ?? 50,
                json_encode($data['trigger_conditions']),
                json_encode($data['actions']),
                $_SESSION['user_id'] ?? null
            ]
        );
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update rule
     */
    public function updateRule($ruleId, $data) {
        $updates = [];
        $params = [];
        
        $allowedFields = ['name', 'description', 'priority', 'is_active', 'trigger_conditions', 'actions'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updates[] = "$field = ?";
                if (in_array($field, ['trigger_conditions', 'actions'])) {
                    $params[] = json_encode($data[$field]);
                } else {
                    $params[] = $data[$field];
                }
            }
        }
        
        if (empty($updates)) return false;
        
        $params[] = $ruleId;
        
        $this->db->query(
            "UPDATE rules SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?",
            $params
        );
        
        return true;
    }
    
    /**
     * Delete rule
     */
    public function deleteRule($ruleId) {
        $this->db->query("DELETE FROM rules WHERE id = ?", [$ruleId]);
        return true;
    }
    
    /**
     * Get rule by ID
     */
    public function getRule($ruleId) {
        $rule = $this->db->query(
            "SELECT * FROM rules WHERE id = ?",
            [$ruleId]
        )->fetch();
        
        if ($rule) {
            $rule['trigger_conditions'] = json_decode($rule['trigger_conditions'], true);
            $rule['actions'] = json_decode($rule['actions'], true);
        }
        
        return $rule;
    }
    
    /**
     * List all rules
     */
    public function listRules($filters = []) {
        $where = ["1=1"];
        $params = [];
        
        if (isset($filters['entity_type'])) {
            $where[] = "entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (isset($filters['is_active'])) {
            $where[] = "is_active = ?";
            $params[] = $filters['is_active'];
        }
        
        $rules = $this->db->query(
            "SELECT * FROM rules 
             WHERE " . implode(' AND ', $where) . "
             ORDER BY priority DESC, id ASC",
            $params
        )->fetchAll();
        
        foreach ($rules as &$rule) {
            $rule['trigger_conditions'] = json_decode($rule['trigger_conditions'], true);
            $rule['actions'] = json_decode($rule['actions'], true);
        }
        
        return $rules;
    }
    
    /**
     * Test rule against sample data
     */
    public function testRule($ruleId, $sampleEntityId) {
        $rule = $this->getRule($ruleId);
        if (!$rule) {
            throw new Exception("Rule not found");
        }
        
        $engine = new RuleEngine($this->db);
        
        // Create a test context
        $context = [
            'entity_type' => $rule['entity_type'],
            'entity_id' => $sampleEntityId,
            'test_mode' => true
        ];
        
        // Test conditions
        $conditionsMatch = $engine->evaluateConditions($rule['trigger_conditions'], $context);
        
        return [
            'rule' => $rule,
            'conditions_match' => $conditionsMatch,
            'would_execute' => $conditionsMatch ? $rule['actions'] : []
        ];
    }
}

// Usage example:
/*
$db = new PDO('mysql:host=localhost;dbname=telesale_manager', 'root', '');
$engine = new RuleEngine($db);

// Trigger rules when order status changes
$results = $engine->evaluate('order', 123, 'status_changed');

// Create new rule
$manager = new RuleManager($db);
$ruleId = $manager->createRule([
    'name' => 'Auto handle no answer',
    'entity_type' => 'order',
    'trigger_conditions' => [
        'type' => 'AND',
        'rules' => [
            ['field' => 'order.status', 'operator' => 'equals', 'value' => 'no_answer'],
            ['field' => 'order.total_calls', 'operator' => 'greater_than', 'value' => 5]
        ]
    ],
    'actions' => [
        ['type' => 'change_order_status', 'params' => ['status' => 'trash']],
        ['type' => 'add_customer_label', 'params' => ['label_key' => 'khach-khong-nghe']]
    ]
]);
*/
?>