<?php
/**
 * Rule Engine Core Classes
 * File: includes/RuleEngine.php
 */

namespace TSM\RuleEngine;

/**
 * Main Rule Engine Class
 */
class RuleEngine {
    private $db;
    private $conditionEvaluator;
    private $actionExecutor;
    private $logger;
    
    public function __construct($db) {
        $this->db = $db;
        $this->conditionEvaluator = new ConditionEvaluator($db);
        $this->actionExecutor = new ActionExecutor($db);
        $this->logger = new RuleLogger($db);
    }
    
    /**
     * Trigger rules based on event
     */
    public function triggerEvent($eventType, $entityType, $entityId, $eventData = []) {
        // Get all active rules for this event
        $rules = $this->getActiveRules($eventType, $entityType);
        
        // Sort by priority (higher priority first)
        usort($rules, function($a, $b) {
            return $b['priority'] - $a['priority'];
        });
        
        $results = [];
        foreach ($rules as $rule) {
            $result = $this->executeRule($rule, $entityId, $eventData);
            $results[] = $result;
            
            // Stop processing if a rule marked as "stop_on_match" executed successfully
            if ($result['status'] === 'success' && isset($rule['metadata']['stop_on_match']) && $rule['metadata']['stop_on_match']) {
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Execute a single rule
     */
    private function executeRule($rule, $entityId, $eventData) {
        $startTime = microtime(true);
        
        try {
            // Decode JSON fields
            $triggerConditions = json_decode($rule['trigger_conditions'], true);
            $actions = json_decode($rule['actions'], true);
            
            // Evaluate conditions
            $context = array_merge($eventData, [
                'entity_id' => $entityId,
                'entity_type' => $rule['entity_type']
            ]);
            
            $conditionsMet = $this->conditionEvaluator->evaluate($triggerConditions, $context);
            
            if (!$conditionsMet) {
                $result = [
                    'rule_id' => $rule['id'],
                    'status' => 'skipped',
                    'reason' => 'Conditions not met'
                ];
            } else {
                // Execute actions
                $actionResults = [];
                foreach ($actions as $action) {
                    // Handle delayed actions
                    if (isset($action['delay_minutes']) && $action['delay_minutes'] > 0) {
                        $this->scheduleDelayedAction($rule['id'], $entityId, $action, $action['delay_minutes']);
                        $actionResults[] = ['type' => $action['type'], 'status' => 'scheduled'];
                    } else {
                        $actionResult = $this->actionExecutor->execute($action, $context);
                        $actionResults[] = $actionResult;
                    }
                }
                
                $result = [
                    'rule_id' => $rule['id'],
                    'status' => 'success',
                    'actions' => $actionResults
                ];
            }
            
            // Log execution
            $this->logger->logExecution($rule['id'], $entityId, $rule['entity_type'], $result['status'], $result);
            
        } catch (\Exception $e) {
            $result = [
                'rule_id' => $rule['id'],
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
            
            $this->logger->logExecution($rule['id'], $entityId, $rule['entity_type'], 'failed', null, $e->getMessage());
        }
        
        $result['execution_time'] = microtime(true) - $startTime;
        return $result;
    }
    
    /**
     * Get active rules for an event
     */
    private function getActiveRules($eventType, $entityType) {
        $sql = "SELECT * FROM rules 
                WHERE entity_type = ? 
                AND is_active = 1 
                AND trigger_conditions LIKE ?
                ORDER BY priority DESC";
        
        $stmt = $this->db->prepare($sql);
        $searchPattern = '%"event":"' . $eventType . '"%';
        $stmt->execute([$entityType, $searchPattern]);
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Schedule a delayed action
     */
    private function scheduleDelayedAction($ruleId, $entityId, $action, $delayMinutes) {
        $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));
        
        $sql = "INSERT INTO scheduled_jobs (job_type, entity_id, entity_type, scheduled_at, payload) 
                VALUES ('delayed_action', ?, 'rule', ?, ?)";
        
        $payload = json_encode([
            'rule_id' => $ruleId,
            'entity_id' => $entityId,
            'action' => $action
        ]);
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$ruleId, $scheduledAt, $payload]);
    }
}

/**
 * Condition Evaluator Class
 */
class ConditionEvaluator {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Evaluate condition tree
     */
    public function evaluate($conditions, $context) {
        if (!isset($conditions['type'])) {
            return $this->evaluateSingleCondition($conditions, $context);
        }
        
        $type = $conditions['type'];
        
        switch ($type) {
            case 'AND':
                return $this->evaluateAnd($conditions['conditions'] ?? [], $context);
            
            case 'OR':
                return $this->evaluateOr($conditions['conditions'] ?? [], $context);
            
            case 'NOT':
                return !$this->evaluate($conditions['conditions'] ?? [], $context);
            
            default:
                return false;
        }
    }
    
    /**
     * Evaluate AND conditions
     */
    private function evaluateAnd($conditions, $context) {
        foreach ($conditions as $condition) {
            if (!$this->evaluate($condition, $context)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Evaluate OR conditions
     */
    private function evaluateOr($conditions, $context) {
        foreach ($conditions as $condition) {
            if ($this->evaluate($condition, $context)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Evaluate single condition
     */
    private function evaluateSingleCondition($condition, $context) {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '';
        $value = $condition['value'] ?? null;
        
        // Get field value from context
        $fieldValue = $this->getFieldValue($field, $context);
        
        // Evaluate based on operator
        switch ($operator) {
            case 'equals':
                return $fieldValue == $value;
            
            case 'not_equals':
                return $fieldValue != $value;
            
            case 'greater_than':
                return $fieldValue > $value;
            
            case 'less_than':
                return $fieldValue < $value;
            
            case 'greater_than_or_equal':
                return $fieldValue >= $value;
            
            case 'less_than_or_equal':
                return $fieldValue <= $value;
            
            case 'contains':
                return strpos($fieldValue, $value) !== false;
            
            case 'not_contains':
                return strpos($fieldValue, $value) === false;
            
            case 'in':
                return in_array($fieldValue, (array)$value);
            
            case 'not_in':
                return !in_array($fieldValue, (array)$value);
            
            case 'is_empty':
                return empty($fieldValue);
            
            case 'is_not_empty':
                return !empty($fieldValue);
            
            case 'regex':
                return preg_match($value, $fieldValue);
            
            default:
                return false;
        }
    }
    
    /**
     * Get field value from context or database
     */
    private function getFieldValue($field, $context) {
        // Check if value exists in context
        if (isset($context[$field])) {
            return $context[$field];
        }
        
        // Parse field path (e.g., "order.status", "customer.total_orders")
        $parts = explode('.', $field);
        
        if (count($parts) === 2) {
            $entity = $parts[0];
            $attribute = $parts[1];
            
            // Load from database based on entity type
            switch ($entity) {
                case 'order':
                    return $this->getOrderAttribute($context['entity_id'], $attribute);
                
                case 'customer':
                    return $this->getCustomerAttribute($context, $attribute);
                
                case 'employee':
                    return $this->getEmployeeAttribute($context, $attribute);
                
                case 'customer_metrics':
                    return $this->getCustomerMetric($context, $attribute);
                
                case 'employee_performance':
                    return $this->getEmployeeMetric($context, $attribute);
            }
        }
        
        return null;
    }
    
    private function getOrderAttribute($orderId, $attribute) {
        $sql = "SELECT $attribute FROM orders WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orderId]);
        return $stmt->fetchColumn();
    }
    
    private function getCustomerAttribute($context, $attribute) {
        // Get customer from order
        $sql = "SELECT customer_phone FROM orders WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$context['entity_id']]);
        $phone = $stmt->fetchColumn();
        
        if ($phone) {
            $sql = "SELECT $attribute FROM customer_metrics WHERE customer_phone = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$phone]);
            return $stmt->fetchColumn();
        }
        
        return null;
    }
    
    private function getEmployeeAttribute($context, $attribute) {
        $sql = "SELECT assigned_to FROM orders WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$context['entity_id']]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            $sql = "SELECT $attribute FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
        }
        
        return null;
    }
    
    private function getCustomerMetric($context, $metric) {
        $sql = "SELECT customer_phone FROM orders WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$context['entity_id']]);
        $phone = $stmt->fetchColumn();
        
        if ($phone) {
            $sql = "SELECT $metric FROM customer_metrics WHERE customer_phone = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$phone]);
            return $stmt->fetchColumn();
        }
        
        return null;
    }
    
    private function getEmployeeMetric($context, $metric) {
        $sql = "SELECT assigned_to FROM orders WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$context['entity_id']]);
        $userId = $stmt->fetchColumn();
        
        if ($userId) {
            $sql = "SELECT $metric FROM employee_performance WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchColumn();
        }
        
        return null;
    }
}

/**
 * Action Executor Class
 */
class ActionExecutor {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Execute an action
     */
    public function execute($action, $context) {
        $type = $action['type'] ?? '';
        $params = $action['params'] ?? [];
        
        // Replace variables in params
        $params = $this->replaceVariables($params, $context);
        
        try {
            switch ($type) {
                case 'change_status':
                    return $this->changeStatus($params, $context);
                
                case 'create_task':
                    return $this->createTask($params, $context);
                
                case 'send_notification':
                    return $this->sendNotification($params, $context);
                
                case 'add_label':
                    return $this->addLabel($params, $context);
                
                case 'remove_label':
                    return $this->removeLabel($params, $context);
                
                case 'suspend_user':
                    return $this->suspendUser($params, $context);
                
                case 'create_note':
                    return $this->createNote($params, $context);
                
                case 'schedule_job':
                    return $this->scheduleJob($params, $context);
                
                case 'send_email':
                    return $this->sendEmail($params, $context);
                
                case 'webhook':
                    return $this->callWebhook($params, $context);
                
                default:
                    return ['type' => $type, 'status' => 'unknown_action'];
            }
        } catch (\Exception $e) {
            return [
                'type' => $type,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Change entity status
     */
    private function changeStatus($params, $context) {
        $entityType = $params['entity'] ?? $context['entity_type'];
        $newStatus = $params['new_status'] ?? '';
        $reason = $params['reason'] ?? '';
        
        if ($entityType === 'order') {
            $sql = "UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$newStatus, $context['entity_id']]);
            
            // Add note
            if ($reason) {
                $this->addOrderNote($context['entity_id'], 'Status changed: ' . $reason, 'system');
            }
        }
        
        return ['type' => 'change_status', 'status' => 'success', 'new_status' => $newStatus];
    }
    
    /**
     * Create a task
     */
    private function createTask($params, $context) {
        $taskType = $params['task_type'] ?? 'general';
        $title = $params['title'] ?? 'New Task';
        $assignTo = $params['assign_to'] ?? null;
        $dueHours = $params['due_in_hours'] ?? 24;
        $reminderMinutes = $params['reminder_before_minutes'] ?? 30;
        
        // Calculate due time
        $dueAt = date('Y-m-d H:i:s', strtotime("+{$dueHours} hours"));
        $reminderAt = date('Y-m-d H:i:s', strtotime($dueAt) - ($reminderMinutes * 60));
        
        $sql = "INSERT INTO tasks (task_type, entity_id, entity_type, assigned_to, title, description, priority, due_at, reminder_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $taskType,
            $context['entity_id'],
            $context['entity_type'],
            $assignTo,
            $title,
            $params['description'] ?? '',
            $params['priority'] ?? 'normal',
            $dueAt,
            $reminderAt
        ]);
        
        return ['type' => 'create_task', 'status' => 'success', 'task_id' => $this->db->lastInsertId()];
    }
    
    /**
     * Send notification
     */
    private function sendNotification($params, $context) {
        $userId = $params['to'] ?? $context['user_id'] ?? null;
        $title = $params['title'] ?? 'Notification';
        $message = $params['message'] ?? '';
        $priority = $params['priority'] ?? 'normal';
        
        if (!$userId) {
            // Get user from order if not specified
            $sql = "SELECT assigned_to FROM orders WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$context['entity_id']]);
            $userId = $stmt->fetchColumn();
        }
        
        if ($userId) {
            $sql = "INSERT INTO notifications (user_id, type, title, message, priority, action_url) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $userId,
                $params['type'] ?? 'system',
                $title,
                $message,
                $priority,
                $params['action_url'] ?? null
            ]);
            
            return ['type' => 'send_notification', 'status' => 'success', 'notification_id' => $this->db->lastInsertId()];
        }
        
        return ['type' => 'send_notification', 'status' => 'failed', 'error' => 'No user specified'];
    }
    
    /**
     * Add label to entity
     */
    private function addLabel($params, $context) {
        $entity = $params['entity'] ?? 'customer';
        $label = $params['label'] ?? '';
        
        if ($entity === 'customer') {
            // Get customer phone from order
            $sql = "SELECT customer_phone FROM orders WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$context['entity_id']]);
            $phone = $stmt->fetchColumn();
            
            if ($phone) {
                // Update customer metrics
                $sql = "UPDATE customer_metrics SET labels = JSON_ARRAY_APPEND(COALESCE(labels, '[]'), '
            , ?) 
                        WHERE customer_phone = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$label, $phone]);
                
                // Update special flags
                if ($label === 'vip') {
                    $sql = "UPDATE customer_metrics SET is_vip = 1 WHERE customer_phone = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$phone]);
                } elseif ($label === 'blacklist') {
                    $sql = "UPDATE customer_metrics SET is_blacklisted = 1 WHERE customer_phone = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$phone]);
                }
            }
        } elseif ($entity === 'employee') {
            $sql = "SELECT assigned_to FROM orders WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$context['entity_id']]);
            $userId = $stmt->fetchColumn();
            
            if ($userId) {
                $sql = "UPDATE employee_performance SET labels = JSON_ARRAY_APPEND(COALESCE(labels, '[]'), '
            , ?) 
                        WHERE user_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$label, $userId]);
            }
        }
        
        return ['type' => 'add_label', 'status' => 'success', 'label' => $label];
    }
    
    /**
     * Remove label from entity
     */
    private function removeLabel($params, $context) {
        $entity = $params['entity'] ?? 'customer';
        $label = $params['label'] ?? '';
        
        if ($entity === 'customer') {
            $sql = "SELECT customer_phone FROM orders WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$context['entity_id']]);
            $phone = $stmt->fetchColumn();
            
            if ($phone) {
                $sql = "UPDATE customer_metrics SET labels = JSON_REMOVE(labels, JSON_UNQUOTE(JSON_SEARCH(labels, 'one', ?))) 
                        WHERE customer_phone = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$label, $phone]);
                
                if ($label === 'vip') {
                    $sql = "UPDATE customer_metrics SET is_vip = 0 WHERE customer_phone = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$phone]);
                } elseif ($label === 'blacklist') {
                    $sql = "UPDATE customer_metrics SET is_blacklisted = 0 WHERE customer_phone = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$phone]);
                }
            }
        }
        
        return ['type' => 'remove_label', 'status' => 'success', 'label' => $label];
    }
    
    /**
     * Suspend user account
     */
    private function suspendUser($params, $context) {
        $userId = $params['user_id'] ?? null;
        $duration = $params['duration_hours'] ?? 24;
        $reason = $params['reason'] ?? 'Rule violation';
        
        if (!$userId) {
            $sql = "SELECT assigned_to FROM orders WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$context['entity_id']]);
            $userId = $stmt->fetchColumn();
        }
        
        if ($userId) {
            // Update user status
            $sql = "UPDATE users SET status = 'suspended', suspension_reason = ?, suspension_until = ? WHERE id = ?";
            $suspendUntil = date('Y-m-d H:i:s', strtotime("+{$duration} hours"));
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$reason, $suspendUntil, $userId]);
            
            // Update performance metrics
            $sql = "UPDATE employee_performance SET suspension_count = suspension_count + 1, 
                    last_violation_date = NOW() WHERE user_id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            
            // Create notification for admin
            $this->createAdminNotification("User Suspended", 
                "User ID {$userId} has been suspended for {$duration} hours. Reason: {$reason}");
            
            return ['type' => 'suspend_user', 'status' => 'success', 'user_id' => $userId];
        }
        
        return ['type' => 'suspend_user', 'status' => 'failed', 'error' => 'User not found'];
    }
    
    /**
     * Create note
     */
    private function createNote($params, $context) {
        $content = $params['content'] ?? '';
        $noteType = $params['note_type'] ?? 'system';
        
        $this->addOrderNote($context['entity_id'], $content, $noteType);
        
        return ['type' => 'create_note', 'status' => 'success'];
    }
    
    /**
     * Schedule a job
     */
    private function scheduleJob($params, $context) {
        $jobType = $params['job_type'] ?? '';
        $scheduledHours = $params['scheduled_after_hours'] ?? 1;
        
        $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$scheduledHours} hours"));
        
        $sql = "INSERT INTO scheduled_jobs (job_type, entity_id, entity_type, scheduled_at, payload) 
                VALUES (?, ?, ?, ?, ?)";
        
        $payload = json_encode(array_merge($params, ['context' => $context]));
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $jobType,
            $context['entity_id'],
            $context['entity_type'],
            $scheduledAt,
            $payload
        ]);
        
        return ['type' => 'schedule_job', 'status' => 'success', 'job_id' => $this->db->lastInsertId()];
    }
    
    /**
     * Helper: Replace variables in params
     */
    private function replaceVariables($params, $context) {
        foreach ($params as $key => $value) {
            if (is_string($value) && strpos($value, '{{') !== false) {
                // Replace variables like {{order.order_number}}
                preg_match_all('/\{\{([^}]+)\}\}/', $value, $matches);
                foreach ($matches[1] as $var) {
                    $varValue = $this->getVariableValue($var, $context);
                    $value = str_replace('{{' . $var . '}}', $varValue, $value);
                }
                $params[$key] = $value;
            }
        }
        return $params;
    }
    
    /**
     * Helper: Get variable value
     */
    private function getVariableValue($var, $context) {
        $parts = explode('.', $var);
        
        if (count($parts) === 2 && $parts[0] === 'order') {
            $sql = "SELECT {$parts[1]} FROM orders WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$context['entity_id']]);
            return $stmt->fetchColumn() ?: '';
        }
        
        return $context[$var] ?? '';
    }
    
    /**
     * Helper: Add order note
     */
    private function addOrderNote($orderId, $content, $noteType = 'system') {
        $sql = "INSERT INTO order_notes (order_id, user_id, note_type, content) VALUES (?, NULL, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orderId, $noteType, $content]);
    }
    
    /**
     * Helper: Create admin notification
     */
    private function createAdminNotification($title, $message) {
        $sql = "INSERT INTO notifications (user_id, type, title, message, priority) 
                SELECT id, 'admin', ?, ?, 'high' FROM users WHERE role = 'admin'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$title, $message]);
    }
}

/**
 * Rule Logger Class
 */
class RuleLogger {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Log rule execution
     */
    public function logExecution($ruleId, $entityId, $entityType, $status, $result = null, $error = null) {
        $sql = "INSERT INTO rule_executions (rule_id, entity_id, entity_type, execution_status, execution_result, error_message) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $ruleId,
            $entityId,
            $entityType,
            $status,
            $result ? json_encode($result) : null,
            $error
        ]);
    }
    
    /**
     * Log action
     */
    public function logAction($entityId, $entityType, $userId, $actionType, $actionData, $oldValue = null, $newValue = null) {
        $sql = "INSERT INTO action_logs (entity_id, entity_type, user_id, action_type, action_data, old_value, new_value, ip_address, user_agent) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $entityId,
            $entityType,
            $userId,
            $actionType,
            json_encode($actionData),
            $oldValue ? json_encode($oldValue) : null,
            $newValue ? json_encode($newValue) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

/**
 * Scheduled Job Processor
 */
class ScheduledJobProcessor {
    private $db;
    private $ruleEngine;
    private $actionExecutor;
    
    public function __construct($db) {
        $this->db = $db;
        $this->ruleEngine = new RuleEngine($db);
        $this->actionExecutor = new ActionExecutor($db);
    }
    
    /**
     * Process pending scheduled jobs
     */
    public function processPendingJobs() {
        // Get pending jobs that are due
        $sql = "SELECT * FROM scheduled_jobs 
                WHERE status = 'pending' 
                AND scheduled_at <= NOW() 
                ORDER BY scheduled_at ASC 
                LIMIT 50";
        
        $stmt = $this->db->query($sql);
        $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($jobs as $job) {
            $this->processJob($job);
        }
    }
    
    /**
     * Process a single job
     */
    private function processJob($job) {
        // Mark as processing
        $this->updateJobStatus($job['id'], 'processing');
        
        try {
            $payload = json_decode($job['payload'], true);
            
            switch ($job['job_type']) {
                case 'delayed_action':
                    $this->processDelayedAction($payload);
                    break;
                
                case 'callback':
                    $this->processCallback($job, $payload);
                    break;
                
                case 'check_overdue':
                    $this->checkOverdueTasks($payload);
                    break;
                
                case 'auto_reassign':
                    $this->autoReassignOrder($payload);
                    break;
                
                default:
                    throw new \Exception("Unknown job type: {$job['job_type']}");
            }
            
            $this->updateJobStatus($job['id'], 'completed');
            
        } catch (\Exception $e) {
            $attempts = $job['attempts'] + 1;
            
            if ($attempts >= $job['max_attempts']) {
                $this->updateJobStatus($job['id'], 'failed', $e->getMessage());
            } else {
                // Reschedule with exponential backoff
                $delayMinutes = pow(2, $attempts) * 5;
                $this->rescheduleJob($job['id'], $delayMinutes, $attempts);
            }
        }
    }
    
    /**
     * Process delayed action
     */
    private function processDelayedAction($payload) {
        $action = $payload['action'] ?? [];
        $context = [
            'entity_id' => $payload['entity_id'],
            'entity_type' => 'order'
        ];
        
        $this->actionExecutor->execute($action, $context);
    }
    
    /**
     * Check overdue tasks
     */
    private function checkOverdueTasks($payload) {
        // Find overdue tasks
        $sql = "UPDATE tasks SET status = 'overdue' 
                WHERE status = 'pending' 
                AND due_at < NOW()";
        
        $this->db->exec($sql);
        
        // Get overdue tasks for notifications
        $sql = "SELECT t.*, u.full_name as assignee_name 
                FROM tasks t 
                LEFT JOIN users u ON t.assigned_to = u.id 
                WHERE t.status = 'overdue' 
                AND t.reminder_sent = 0";
        
        $stmt = $this->db->query($sql);
        $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        foreach ($tasks as $task) {
            // Send reminder notification
            if ($task['assigned_to']) {
                $this->sendTaskReminder($task);
            }
            
            // Mark reminder as sent
            $sql = "UPDATE tasks SET reminder_sent = 1 WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$task['id']]);
        }
    }
    
    /**
     * Helper: Update job status
     */
    private function updateJobStatus($jobId, $status, $error = null) {
        $sql = "UPDATE scheduled_jobs SET status = ?, executed_at = NOW(), error_message = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $error, $jobId]);
    }
    
    /**
     * Helper: Reschedule job
     */
    private function rescheduleJob($jobId, $delayMinutes, $attempts) {
        $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));
        $sql = "UPDATE scheduled_jobs SET status = 'pending', scheduled_at = ?, attempts = ? WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$scheduledAt, $attempts, $jobId]);
    }
    
    /**
     * Helper: Send task reminder
     */
    private function sendTaskReminder($task) {
        $sql = "INSERT INTO notifications (user_id, type, title, message, priority, action_url) 
                VALUES (?, 'task_reminder', ?, ?, 'high', ?)";
        
        $title = "Task Overdue: {$task['title']}";
        $message = "Your task '{$task['title']}' is overdue. Please complete it as soon as possible.";
        $actionUrl = "/task-detail.php?id={$task['id']}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$task['assigned_to'], $title, $message, $actionUrl]);
    }
}