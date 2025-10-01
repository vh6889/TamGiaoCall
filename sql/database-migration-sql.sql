-- =====================================================
-- Database Migration Script for Dynamic Workflow System
-- =====================================================

-- 1. Status Definitions Table (Enhanced)
DROP TABLE IF EXISTS `status_definitions`;
CREATE TABLE `status_definitions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `entity_type` ENUM('order', 'customer', 'employee', 'task') NOT NULL,
  `status_key` VARCHAR(50) NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `color` VARCHAR(20) DEFAULT '#6c757d',
  `icon` VARCHAR(50) DEFAULT 'fa-circle',
  `sort_order` INT DEFAULT 0,
  `is_system` BOOLEAN DEFAULT FALSE,
  `is_final` BOOLEAN DEFAULT FALSE,
  `required_fields` JSON,
  `validation_rules` JSON,
  `created_by` INT UNSIGNED,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `entity_status` (`entity_type`, `status_key`),
  INDEX `idx_entity_type` (`entity_type`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Rules Table
DROP TABLE IF EXISTS `rules`;
CREATE TABLE `rules` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `rule_key` VARCHAR(100) UNIQUE NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `entity_type` ENUM('order', 'customer', 'employee', 'task', 'system') NOT NULL,
  `rule_type` ENUM('status_transition', 'time_based', 'event_based', 'condition_based') NOT NULL,
  `priority` INT DEFAULT 50,
  `is_active` BOOLEAN DEFAULT TRUE,
  `trigger_conditions` JSON NOT NULL,
  `actions` JSON NOT NULL,
  `metadata` JSON,
  `created_by` INT UNSIGNED,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_entity_active` (`entity_type`, `is_active`),
  INDEX `idx_priority` (`priority`),
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Rule Executions Table
DROP TABLE IF EXISTS `rule_executions`;
CREATE TABLE `rule_executions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `rule_id` INT NOT NULL,
  `entity_id` INT NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `execution_status` ENUM('success', 'failed', 'skipped') NOT NULL,
  `execution_result` JSON,
  `error_message` TEXT,
  `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`rule_id`) REFERENCES `rules`(`id`) ON DELETE CASCADE,
  INDEX `idx_rule_entity` (`rule_id`, `entity_id`, `entity_type`),
  INDEX `idx_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tasks Table
DROP TABLE IF EXISTS `tasks`;
CREATE TABLE `tasks` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `task_type` VARCHAR(50) NOT NULL,
  `entity_id` INT NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `assigned_to` INT UNSIGNED,
  `assigned_by` INT UNSIGNED,
  `title` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
  `status` ENUM('pending', 'in_progress', 'completed', 'cancelled', 'overdue') DEFAULT 'pending',
  `due_at` DATETIME,
  `reminder_at` DATETIME,
  `reminder_sent` BOOLEAN DEFAULT FALSE,
  `completed_at` DATETIME,
  `completed_by` INT UNSIGNED,
  `metadata` JSON,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`completed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_assigned_status` (`assigned_to`, `status`),
  INDEX `idx_due_at` (`due_at`),
  INDEX `idx_entity` (`entity_id`, `entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Enhanced Customer Labels Table
DROP TABLE IF EXISTS `customer_labels`;
CREATE TABLE `customer_labels` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `label_key` VARCHAR(50) UNIQUE NOT NULL,
  `label_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `color` VARCHAR(20) DEFAULT '#17a2b8',
  `icon` VARCHAR(50) DEFAULT 'fa-tag',
  `auto_assign_rules` JSON,
  `restrictions` JSON,
  `sort_order` INT DEFAULT 0,
  `created_by` INT UNSIGNED,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Enhanced User Labels Table
DROP TABLE IF EXISTS `user_labels`;
CREATE TABLE `user_labels` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `label_key` VARCHAR(50) UNIQUE NOT NULL,
  `label_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `color` VARCHAR(20) DEFAULT '#28a745',
  `icon` VARCHAR(50) DEFAULT 'fa-user-tag',
  `permissions` JSON,
  `auto_assign_rules` JSON,
  `sort_order` INT DEFAULT 0,
  `created_by` INT UNSIGNED,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Customer Metrics Table
DROP TABLE IF EXISTS `customer_metrics`;
CREATE TABLE `customer_metrics` (
  `customer_id` INT PRIMARY KEY,
  `customer_phone` VARCHAR(20) UNIQUE,
  `total_orders` INT DEFAULT 0,
  `completed_orders` INT DEFAULT 0,
  `cancelled_orders` INT DEFAULT 0,
  `total_value` DECIMAL(15,2) DEFAULT 0,
  `avg_order_value` DECIMAL(15,2) DEFAULT 0,
  `first_order_date` DATE,
  `last_order_date` DATE,
  `customer_lifetime_days` INT DEFAULT 0,
  `is_vip` BOOLEAN DEFAULT FALSE,
  `is_blacklisted` BOOLEAN DEFAULT FALSE,
  `risk_score` INT DEFAULT 0,
  `labels` JSON,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_vip` (`is_vip`),
  INDEX `idx_blacklist` (`is_blacklisted`),
  INDEX `idx_phone` (`customer_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Employee Performance Table
DROP TABLE IF EXISTS `employee_performance`;
CREATE TABLE `employee_performance` (
  `user_id` INT UNSIGNED PRIMARY KEY,
  `total_orders_handled` INT DEFAULT 0,
  `successful_orders` INT DEFAULT 0,
  `failed_orders` INT DEFAULT 0,
  `avg_handling_time` INT DEFAULT 0,
  `total_revenue` DECIMAL(15,2) DEFAULT 0,
  `violation_count` INT DEFAULT 0,
  `warning_count` INT DEFAULT 0,
  `suspension_count` INT DEFAULT 0,
  `performance_score` INT DEFAULT 0,
  `last_violation_date` DATETIME,
  `labels` JSON,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_performance` (`performance_score`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert Default Data
-- =====================================================

-- Default Order Statuses
INSERT INTO `status_definitions` (`entity_type`, `status_key`, `label`, `description`, `color`, `icon`, `sort_order`, `is_system`) VALUES
('order', 'new', 'Đơn mới', 'Đơn hàng vừa được tạo', '#007bff', 'fa-plus-circle', 1, TRUE),
('order', 'assigned', 'Đã phân công', 'Đã giao cho nhân viên', '#17a2b8', 'fa-user-check', 2, TRUE),
('order', 'processing', 'Đang xử lý', 'Nhân viên đang xử lý', '#ffc107', 'fa-spinner', 3, TRUE),
('order', 'calling', 'Đang gọi', 'Đang liên hệ khách hàng', '#fd7e14', 'fa-phone', 4, FALSE),
('order', 'no_answer', 'Không nghe máy', 'Khách không nghe máy', '#dc3545', 'fa-phone-slash', 5, FALSE),
('order', 'callback_scheduled', 'Hẹn gọi lại', 'Đã hẹn thời gian gọi lại', '#6f42c1', 'fa-clock', 6, FALSE),
('order', 'confirmed', 'Đã xác nhận', 'Khách đã xác nhận đặt hàng', '#28a745', 'fa-check-circle', 7, FALSE),
('order', 'shipping', 'Đang giao', 'Đơn hàng đang được giao', '#20c997', 'fa-truck', 8, FALSE),
('order', 'completed', 'Hoàn thành', 'Giao hàng thành công', '#198754', 'fa-check-double', 9, TRUE),
('order', 'cancelled', 'Đã hủy', 'Đơn hàng bị hủy', '#6c757d', 'fa-times-circle', 10, TRUE),
('order', 'returned', 'Hoàn trả', 'Hàng bị trả lại', '#dc3545', 'fa-undo', 11, TRUE);

-- Default Task Statuses
INSERT INTO `status_definitions` (`entity_type`, `status_key`, `label`, `description`, `color`, `icon`, `sort_order`, `is_system`) VALUES
('task', 'pending', 'Chờ xử lý', 'Task chưa bắt đầu', '#6c757d', 'fa-hourglass-start', 1, TRUE),
('task', 'in_progress', 'Đang thực hiện', 'Task đang được xử lý', '#ffc107', 'fa-spinner', 2, TRUE),
('task', 'completed', 'Hoàn thành', 'Task đã hoàn thành', '#28a745', 'fa-check', 3, TRUE),
('task', 'overdue', 'Quá hạn', 'Task đã quá thời hạn', '#dc3545', 'fa-exclamation-triangle', 4, TRUE),
('task', 'cancelled', 'Đã hủy', 'Task bị hủy', '#6c757d', 'fa-times', 5, TRUE);

-- Default Customer Labels
INSERT INTO `customer_labels` (`label_key`, `label_name`, `description`, `color`, `icon`) VALUES
('vip', 'VIP', 'Khách hàng VIP', '#ffd700', 'fa-crown'),
('normal', 'Thường', 'Khách hàng thông thường', '#6c757d', 'fa-user'),
('potential', 'Tiềm năng', 'Khách hàng tiềm năng', '#17a2b8', 'fa-star'),
('blacklist', 'Blacklist', 'Khách hàng cần cảnh báo', '#dc3545', 'fa-ban'),
('returning', 'Quay lại', 'Khách hàng quay lại', '#28a745', 'fa-redo');

-- Default User Labels  
INSERT INTO `user_labels` (`label_key`, `label_name`, `description`, `color`, `icon`) VALUES
('top_performer', 'Xuất sắc', 'Nhân viên xuất sắc', '#ffd700', 'fa-trophy'),
('warning', 'Cảnh báo', 'Đang bị cảnh báo', '#ffc107', 'fa-exclamation-triangle'),
('suspended', 'Tạm khóa', 'Tài khoản bị tạm khóa', '#dc3545', 'fa-lock'),
('training', 'Đào tạo', 'Đang trong giai đoạn đào tạo', '#17a2b8', 'fa-graduation-cap'),
('probation', 'Thử việc', 'Nhân viên thử việc', '#6f42c1', 'fa-user-clock');

-- Sample Rule Templates
INSERT INTO `rule_templates` (`template_key`, `name`, `description`, `category`, `entity_type`, `template_data`) VALUES
('auto_vip', 'Tự động nâng cấp VIP', 'Tự động gán nhãn VIP cho khách hàng đủ điều kiện', 'customer', 'customer', 
 '{"trigger_conditions":{"type":"AND","conditions":[{"field":"customer_metrics.total_orders","operator":"greater_than","value":3},{"field":"customer_metrics.total_value","operator":"greater_than","value":2000000}]},"actions":[{"type":"add_label","params":{"label":"vip"}}]}'),

('no_answer_callback', 'Xử lý không nghe máy', 'Tạo task gọi lại khi khách không nghe máy', 'order', 'order',
 '{"trigger_conditions":{"type":"AND","conditions":[{"field":"order.status","operator":"equals","value":"no_answer"}]},"actions":[{"type":"create_task","params":{"task_type":"callback","due_in_hours":2,"reminder_before_minutes":30}}]}'),

('overdue_warning', 'Cảnh báo task quá hạn', 'Gửi cảnh báo khi task sắp quá hạn', 'task', 'task',
 '{"trigger_conditions":{"type":"AND","conditions":[{"field":"task.time_until_due","operator":"less_than","value":30}]},"actions":[{"type":"send_notification","params":{"priority":"high"}}]}');

-- Create triggers for auto-updating metrics
DELIMITER $

CREATE TRIGGER `update_customer_metrics_on_order`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
    IF NEW.status != OLD.status THEN
        -- Update or insert customer metrics
        INSERT INTO customer_metrics (
            customer_phone,
            total_orders,
            completed_orders,
            cancelled_orders,
            total_value,
            last_order_date
        )
        VALUES (
            NEW.customer_phone,
            1,
            IF(NEW.status = 'completed', 1, 0),
            IF(NEW.status = 'cancelled', 1, 0),
            IF(NEW.status = 'completed', NEW.total_amount, 0),
            CURDATE()
        )
        ON DUPLICATE KEY UPDATE
            total_orders = total_orders + IF(OLD.status = 'new' AND NEW.status != 'new', 1, 0),
            completed_orders = completed_orders + IF(NEW.status = 'completed', 1, 0),
            cancelled_orders = cancelled_orders + IF(NEW.status = 'cancelled', 1, 0),
            total_value = total_value + IF(NEW.status = 'completed', NEW.total_amount, 0),
            last_order_date = CURDATE(),
            avg_order_value = total_value / NULLIF(completed_orders, 0);
    END IF;
END$

CREATE TRIGGER `update_employee_performance`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
    IF NEW.assigned_to IS NOT NULL AND NEW.status != OLD.status THEN
        INSERT INTO employee_performance (
            user_id,
            total_orders_handled,
            successful_orders,
            failed_orders
        )
        VALUES (
            NEW.assigned_to,
            1,
            IF(NEW.status = 'completed', 1, 0),
            IF(NEW.status = 'cancelled', 1, 0)
        )
        ON DUPLICATE KEY UPDATE
            total_orders_handled = total_orders_handled + IF(OLD.assigned_to != NEW.assigned_to, 1, 0),
            successful_orders = successful_orders + IF(NEW.status = 'completed', 1, 0),
            failed_orders = failed_orders + IF(NEW.status = 'cancelled', 1, 0);
    END IF;
END$

DELIMITER ;

-- 5. Action Logs Table (Enhanced)
DROP TABLE IF EXISTS `action_logs`;
CREATE TABLE `action_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `entity_id` INT NOT NULL,
  `entity_type` VARCHAR(50) NOT NULL,
  `user_id` INT UNSIGNED,
  `action_type` VARCHAR(100) NOT NULL,
  `action_data` JSON,
  `old_value` JSON,
  `new_value` JSON,
  `ip_address` VARCHAR(45),
  `user_agent` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `idx_entity` (`entity_id`, `entity_type`),
  INDEX `idx_user_action` (`user_id`, `action_type`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Notifications Table
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` TEXT,
  `action_url` VARCHAR(500),
  `priority` ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
  `is_read` BOOLEAN DEFAULT FALSE,
  `read_at` DATETIME,
  `metadata` JSON,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `idx_user_unread` (`user_id`, `is_read`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Rule Templates Table
DROP TABLE IF EXISTS `rule_templates`;
CREATE TABLE `rule_templates` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `template_key` VARCHAR(100) UNIQUE NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `description` TEXT,
  `category` VARCHAR(50),
  `entity_type` VARCHAR(50),
  `template_data` JSON NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Scheduled Jobs Table
DROP TABLE IF EXISTS `scheduled_jobs`;
CREATE TABLE `scheduled_jobs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `job_type` VARCHAR(50) NOT NULL,
  `entity_id` INT,
  `entity_type` VARCHAR(50),
  `scheduled_at` DATETIME NOT NULL,
  `executed_at` DATETIME,
  `status` ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
  `attempts` INT DEFAULT 0,
  `max_attempts` INT DEFAULT 3,
  `payload` JSON,
  `result` JSON,
  `error_message` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_scheduled_status` (`scheduled_at`, `status`),
  INDEX `idx_entity` (`entity_id`, `entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;