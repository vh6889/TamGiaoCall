<?php
/**
 * Cron Job: Process Rules and Scheduled Jobs
 * File: cron/process-rules.php
 * 
 * Run every minute via cron:
 * * * * * * /usr/bin/php /path/to/cron/process-rules.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

define('TSM_ACCESS', true);
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/includes/RuleEngine.php';

use TSM\RuleEngine\RuleEngine;
use TSM\RuleEngine\ScheduledJobProcessor;

class RuleCronProcessor {
    private $db;
    private $ruleEngine;
    private $jobProcessor;
    private $startTime;
    private $maxExecutionTime = 50; // seconds
    private $log = [];
    
    public function __construct($db) {
        $this->db = $db;
        $this->ruleEngine = new RuleEngine($db);
        $this->jobProcessor = new ScheduledJobProcessor($db);
        $this->startTime = time();
    }
    
    /**
     * Main process method
     */
    public function process() {
        $this->log('Starting rule processor...');
        
        try {
            // 1. Process scheduled jobs
            $this->processScheduledJobs();
            
            // 2. Process time-based rules
            $this->processTimeBasedRules();
            
            // 3. Process overdue tasks
            $this->processOverdueTasks();
            
            // 4. Process task reminders
            $this->processTaskReminders();
            
            // 5. Check suspended users for reactivation
            $this->checkSuspendedUsers();
            
            // 6. Update metrics
            $this->updateMetrics();
            
            // 7. Clean up old logs
            $this->cleanupOldLogs();
            
        } catch (Exception $e) {
            $this->log('Error: ' . $e->getMessage(), 'error');
        }
        
        $this->log('Process completed in ' . (time() - $this->startTime) . ' seconds');
        $this->saveLog();
    }
    
    /**
     * Process scheduled jobs
     */
    private function processScheduledJobs() {
        $this->log('Processing scheduled jobs...');
        
        $sql = "SELECT * FROM scheduled_jobs 
                WHERE status = 'pending' 
                AND scheduled_at <= NOW() 
                ORDER BY scheduled_at ASC 
                LIMIT 50";
        
        $stmt = $this->db->query($sql);
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $this->log('Found ' . count($jobs) . ' pending jobs');
        
        foreach ($jobs as $job) {
            if ($this->isTimeout()) break;
            
            $this->processJob($job);
        }
    }
    
    /**
     * Process a single job
     */
    private function processJob($job) {
        $this->log("Processing job #{$job['id']} ({$job['job_type']})");
        
        // Mark as processing
        $this->updateJobStatus($job['id'], 'processing');
        
        try {
            $payload = json_decode($job['payload'], true);
            
            switch ($job['job_type']) {
                case 'delayed_action':
                    $this->processDelayedAction($job, $payload);
                    break;
                    
                case 'callback':
                    $this->processCallback($job, $payload);
                    break;
                    
                case 'check_overdue':
                    $this->processOverdueCheck($job, $payload);
                    break;
                    
                case 'auto_reassign':
                    $this->processAutoReassign($job, $payload);
                    break;
                    
                default:
                    throw new Exception("Unknown job type: {$job['job_type']}");
            }
            
            $this->updateJobStatus($job['id'], 'completed');
            $this->log("Job #{$job['id']} completed successfully");
            
        } catch (Exception $e) {
            $this->handleJobError($job, $e);
        }
    }
    
    /**
     * Process time-based rules
     */
    private function processTimeBasedRules() {
        $this->log('Processing time-based rules...');
        
        // Get all active time-based rules
        $sql = "SELECT * FROM rules 
                WHERE is_active = 1 
                AND rule_type = 'time_based'";
        
        $stmt = $this->db->query($sql);
        $rules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rules as $rule) {
            if ($this->isTimeout()) break;
            
            $this->processTimeRule($rule);
        }
    }
    
    /**
     * Process a single time-based rule
     */
    private function processTimeRule($rule) {
        $conditions = json_decode($rule['trigger_conditions'], true);
        
        // Check if time conditions are met
        if (!$this->evaluateTimeConditions($conditions)) {
            return;
        }
        
        // Find entities that match the rule
        $entities = $this->findMatchingEntities($rule);
        
        foreach ($entities as $entity) {
            if ($this->isTimeout()) break;
            
            // Trigger rule for this entity
            $this->ruleEngine->triggerEvent('time_based', $rule['entity_type'], $entity['id'], $entity);
        }
    }
    
    /**
     * Process overdue tasks
     */
    private function processOverdueTasks() {
        $this->log('Checking for overdue tasks...');
        
        // Update task status to overdue
        $sql = "UPDATE tasks 
                SET status = 'overdue' 
                WHERE status IN ('pending', 'in_progress') 
                AND due_at < NOW()";
        
        $result = $this->db->exec($sql);
        
        if ($result > 0) {
            $this->log("Marked $result tasks as overdue");
        }
        
        // Get overdue tasks for rule processing
        $sql = "SELECT t.*, o.customer_phone, o.total_amount 
                FROM tasks t 
                LEFT JOIN orders o ON t.entity_id = o.id AND t.entity_type = 'order'
                WHERE t.status = 'overdue' 
                AND t.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)";
        
        $stmt = $this->db->query($sql);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tasks as $task) {
            if ($this->isTimeout()) break;
            
            // Trigger overdue event
            $this->ruleEngine->triggerEvent('task_overdue', 'task', $task['id'], $task);
        }
    }
    
    /**
     * Process task reminders
     */
    private function processTaskReminders() {
        $this->log('Processing task reminders...');
        
        $sql = "SELECT t.*, u.full_name as assignee_name, u.email 
                FROM tasks t 
                JOIN users u ON t.assigned_to = u.id 
                WHERE t.status IN ('pending', 'in_progress') 
                AND t.reminder_at <= NOW() 
                AND t.reminder_sent = 0 
                LIMIT 50";
        
        $stmt = $this->db->query($sql);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tasks as $task) {
            if ($this->isTimeout()) break;
            
            $this->sendTaskReminder($task);
            
            // Mark reminder as sent
            $sql = "UPDATE tasks SET reminder_sent = 1 WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$task['id']]);
        }
        
        $this->log('Sent ' . count($tasks) . ' task reminders');
    }
    
    /**
     * Check suspended users for reactivation
     */
    private function checkSuspendedUsers() {
        $this->log('Checking suspended users...');
        
        $sql = "UPDATE users 
                SET status = 'active', 
                    suspension_reason = NULL, 
                    suspension_until = NULL 
                WHERE status = 'suspended' 
                AND suspension_until <= NOW()";
        
        $result = $this->db->exec($sql);
        
        if ($result > 0) {
            $this->log("Reactivated $result suspended users");
        }
    }
    
    /**
     * Update metrics
     */
    private function updateMetrics() {
        $this->log('Updating metrics...');
        
        // Update customer metrics
        $this->updateCustomerMetrics();
        
        // Update employee performance
        $this->updateEmployeePerformance();
    }
    
    /**
     * Update customer metrics
     */
    private function updateCustomerMetrics() {
        // Update order counts and values
        $sql = "INSERT INTO customer_metrics (customer_phone, total_orders, completed_orders, cancelled_orders, total_value)
                SELECT 
                    customer_phone,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as total_value
                FROM orders
                WHERE customer_phone IS NOT NULL
                GROUP BY customer_phone
                ON DUPLICATE KEY UPDATE
                    total_orders = VALUES(total_orders),
                    completed_orders = VALUES(completed_orders),
                    cancelled_orders = VALUES(cancelled_orders),
                    total_value = VALUES(total_value),
                    avg_order_value = VALUES(total_value) / NULLIF(VALUES(completed_orders), 0)";
        
        $this->db->exec($sql);
        
        // Update first and last order dates
        $sql = "UPDATE customer_metrics cm
                JOIN (
                    SELECT 
                        customer_phone,
                        MIN(created_at) as first_order,
                        MAX(created_at) as last_order
                    FROM orders
                    GROUP BY customer_phone
                ) o ON cm.customer_phone = o.customer_phone
                SET 
                    cm.first_order_date = DATE(o.first_order),
                    cm.last_order_date = DATE(o.last_order),
                    cm.customer_lifetime_days = DATEDIFF(o.last_order, o.first_order)";
        
        $this->db->exec($sql);
    }
    
    /**
     * Update employee performance
     */
    private function updateEmployeePerformance() {
        $sql = "INSERT INTO employee_performance (
                    user_id, 
                    total_orders_handled, 
                    successful_orders, 
                    failed_orders, 
                    total_revenue
                )
                SELECT 
                    assigned_to as user_id,
                    COUNT(*) as total_orders,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as failed,
                    SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) as revenue
                FROM orders
                WHERE assigned_to IS NOT NULL
                GROUP BY assigned_to
                ON DUPLICATE KEY UPDATE
                    total_orders_handled = VALUES(total_orders_handled),
                    successful_orders = VALUES(successful_orders),
                    failed_orders = VALUES(failed_orders),
                    total_revenue = VALUES(total_revenue)";
        
        $this->db->exec($sql);
        
        // Calculate performance score
        $sql = "UPDATE employee_performance 
                SET performance_score = 
                    LEAST(100, GREATEST(0,
                        (successful_orders * 100 / NULLIF(total_orders_handled, 0)) 
                        - (violation_count * 5)
                        - (warning_count * 2)
                    ))";
        
        $this->db->exec($sql);
    }
    
    /**
     * Clean up old logs
     */
    private function cleanupOldLogs() {
        $this->log('Cleaning up old logs...');
        
        // Delete old rule executions (keep 30 days)
        $sql = "DELETE FROM rule_executions 
                WHERE executed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $this->db->exec($sql);
        
        // Delete old action logs (keep 90 days)
        $sql = "DELETE FROM action_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)";
        $this->db->exec($sql);
        
        // Delete completed jobs (keep 7 days)
        $sql = "DELETE FROM scheduled_jobs 
                WHERE status IN ('completed', 'failed') 
                AND executed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $this->db->exec($sql);
        
        // Delete old notifications (keep 30 days)
        $sql = "DELETE FROM notifications 
                WHERE is_read = 1 
                AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $this->db->exec($sql);
    }
    
    /**
     * Helper: Process delayed action
     */
    private function processDelayedAction($job, $payload) {
        $action = $payload['action'] ?? [];
        $context = $payload['context'] ?? [];
        
        $executor = new \TSM\RuleEngine\ActionExecutor($this->db);
        $result = $executor->execute($action, $context);
        
        if ($result['status'] !== 'success') {
            throw new Exception($result['error'] ?? 'Action execution failed');
        }
    }
    
    /**
     * Helper: Process callback task
     */
    private function processCallback($job, $payload) {
        $orderId = $payload['entity_id'] ?? 0;
        
        // Create callback task
        $sql = "INSERT INTO tasks (
                    task_type, entity_id, entity_type, 
                    assigned_to, title, priority, 
                    due_at, reminder_at
                ) 
                SELECT 
                    'callback', ?, 'order',
                    assigned_to, 'Gọi lại khách hàng', 'high',
                    DATE_ADD(NOW(), INTERVAL 2 HOUR),
                    DATE_ADD(NOW(), INTERVAL 90 MINUTE)
                FROM orders 
                WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$orderId, $orderId]);
    }
    
    /**
     * Helper: Send task reminder
     */
    private function sendTaskReminder($task) {
        // Create notification
        $sql = "INSERT INTO notifications (user_id, type, title, message, priority, action_url) 
                VALUES (?, 'task_reminder', ?, ?, 'high', ?)";
        
        $title = "Nhắc nhở: {$task['title']}";
        $message = "Task của bạn sẽ đến hạn lúc " . date('H:i d/m/Y', strtotime($task['due_at']));
        $actionUrl = "/task-detail.php?id={$task['id']}";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$task['assigned_to'], $title, $message, $actionUrl]);
        
        // Send email if configured
        if (!empty($task['email'])) {
            // Implement email sending
        }
    }
    
    /**
     * Helper methods
     */
    private function evaluateTimeConditions($conditions) {
        // Implement time condition evaluation
        return true;
    }
    
    private function findMatchingEntities($rule) {
        // Find entities matching rule conditions
        $limit = 100;
        $entities = [];
        
        switch ($rule['entity_type']) {
            case 'order':
                $sql = "SELECT * FROM orders WHERE status != 'completed' LIMIT $limit";
                break;
            case 'task':
                $sql = "SELECT * FROM tasks WHERE status = 'pending' LIMIT $limit";
                break;
            default:
                return [];
        }
        
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function updateJobStatus($jobId, $status, $error = null) {
        $sql = "UPDATE scheduled_jobs 
                SET status = ?, executed_at = NOW(), error_message = ? 
                WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $error, $jobId]);
    }
    
    private function handleJobError($job, $exception) {
        $attempts = $job['attempts'] + 1;
        
        if ($attempts >= $job['max_attempts']) {
            $this->updateJobStatus($job['id'], 'failed', $exception->getMessage());
            $this->log("Job #{$job['id']} failed after $attempts attempts: " . $exception->getMessage(), 'error');
        } else {
            // Reschedule with exponential backoff
            $delayMinutes = pow(2, $attempts) * 5;
            $scheduledAt = date('Y-m-d H:i:s', strtotime("+{$delayMinutes} minutes"));
            
            $sql = "UPDATE scheduled_jobs 
                    SET status = 'pending', scheduled_at = ?, attempts = ? 
                    WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$scheduledAt, $attempts, $job['id']]);
            
            $this->log("Job #{$job['id']} rescheduled for $scheduledAt (attempt $attempts)");
        }
    }
    
    private function isTimeout() {
        return (time() - $this->startTime) > $this->maxExecutionTime;
    }
    
    private function log($message, $level = 'info') {
        $timestamp = date('Y-m-d H:i:s');
        $this->log[] = "[$timestamp] [$level] $message";
        
        if ($level === 'error') {
            error_log("RuleCron: $message");
        }
    }
    
    private function saveLog() {
        $logFile = dirname(__DIR__) . '/logs/cron_' . date('Y-m-d') . '.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, implode("\n", $this->log) . "\n", FILE_APPEND);
    }
}

// Run the processor
try {
    $processor = new RuleCronProcessor($db);
    $processor->process();
} catch (Exception $e) {
    error_log('Rule cron fatal error: ' . $e->getMessage());
    exit(1);
}

exit(0);