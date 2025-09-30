-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th9 30, 2025 lúc 04:23 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `telesale_manager`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `action_logs`
--

CREATE TABLE `action_logs` (
  `id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action_type` varchar(100) NOT NULL,
  `action_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`action_data`)),
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL COMMENT 'Loại đối tượng (order, user, kpi)',
  `related_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng nhật ký hoạt động';

--
-- Đang đổ dữ liệu cho bảng `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `description`, `related_type`, `related_id`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 10:46:19'),
(2, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 10:47:32'),
(3, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 11:06:40'),
(4, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 11:06:54'),
(5, 1, 'update_user', 'Updated user info', 'user', 2, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '2025-09-30 12:46:43'),
(6, 1, 'disable_user', 'Disabled user #3', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 12:51:11'),
(7, 1, 'update_user', 'Updated user info', 'user', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 12:54:20'),
(8, 1, 'update_user', 'Updated user info', 'user', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 12:54:32'),
(9, 1, 'update_settings', 'System settings have been updated.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 12:55:19'),
(10, 1, 'update_settings', 'System settings have been updated.', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 12:55:38'),
(11, 1, 'create_user', 'Created new user: oigioioi', 'user', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 18:50:59');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customer_labels`
--

CREATE TABLE `customer_labels` (
  `id` int(11) NOT NULL,
  `label_key` varchar(50) NOT NULL,
  `label_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#17a2b8',
  `icon` varchar(50) DEFAULT 'fa-tag',
  `auto_assign_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`auto_assign_rules`)),
  `restrictions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`restrictions`)),
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `customer_labels`
--

INSERT INTO `customer_labels` (`id`, `label_key`, `label_name`, `description`, `color`, `icon`, `auto_assign_rules`, `restrictions`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'vip', 'VIP', 'Khách hàng VIP', '#ffd700', 'fa-crown', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35'),
(2, 'normal', 'Thường', 'Khách hàng thông thường', '#6c757d', 'fa-user', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35'),
(3, 'potential', 'Tiềm năng', 'Khách hàng tiềm năng', '#17a2b8', 'fa-star', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35'),
(4, 'blacklist', 'Blacklist', 'Khách hàng cần cảnh báo', '#dc3545', 'fa-ban', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35'),
(5, 'returning', 'Quay lại', 'Khách hàng quay lại', '#28a745', 'fa-redo', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customer_metrics`
--

CREATE TABLE `customer_metrics` (
  `customer_id` int(11) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `total_orders` int(11) DEFAULT 0,
  `completed_orders` int(11) DEFAULT 0,
  `cancelled_orders` int(11) DEFAULT 0,
  `total_value` decimal(15,2) DEFAULT 0.00,
  `avg_order_value` decimal(15,2) DEFAULT 0.00,
  `first_order_date` date DEFAULT NULL,
  `last_order_date` date DEFAULT NULL,
  `customer_lifetime_days` int(11) DEFAULT 0,
  `is_vip` tinyint(1) DEFAULT 0,
  `is_blacklisted` tinyint(1) DEFAULT 0,
  `risk_score` int(11) DEFAULT 0,
  `labels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`labels`)),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `employee_performance`
--

CREATE TABLE `employee_performance` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `total_orders_handled` int(11) DEFAULT 0,
  `successful_orders` int(11) DEFAULT 0,
  `failed_orders` int(11) DEFAULT 0,
  `avg_handling_time` int(11) DEFAULT 0,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `violation_count` int(11) DEFAULT 0,
  `warning_count` int(11) DEFAULT 0,
  `suspension_count` int(11) DEFAULT 0,
  `performance_score` int(11) DEFAULT 0,
  `last_violation_date` datetime DEFAULT NULL,
  `labels` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`labels`)),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `kpis`
--

CREATE TABLE `kpis` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'ID của telesale',
  `target_month` date NOT NULL COMMENT 'Tháng áp dụng (luôn lưu ngày đầu tiên của tháng, VD: 2025-10-01)',
  `target_type` enum('confirmed_orders','total_revenue') NOT NULL COMMENT 'Loại mục tiêu: số đơn chốt, doanh thu',
  `target_value` decimal(15,2) NOT NULL COMMENT 'Giá trị mục tiêu',
  `achieved_value` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Giá trị đã đạt được (cập nhật bằng cron job)',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng quản lý KPIs cho telesale';

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `manager_assignments`
--

CREATE TABLE `manager_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `manager_id` int(10) UNSIGNED NOT NULL,
  `telesale_id` int(10) UNSIGNED NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp(),
  `assigned_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng phân công manager quản lý telesale';

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text DEFAULT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `woo_order_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID đơn hàng từ WooCommerce (NULL nếu tạo thủ công)',
  `order_number` varchar(50) NOT NULL COMMENT 'Mã đơn hàng',
  `customer_name` varchar(100) NOT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_address` text DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'VND',
  `payment_method` varchar(50) DEFAULT NULL,
  `products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Danh sách sản phẩm (JSON)' CHECK (json_valid(`products`)),
  `customer_notes` text DEFAULT NULL COMMENT 'Ghi chú của khách',
  `status` enum('new','assigned','calling','confirmed','rejected','no_answer','callback','completed','cancelled','pending_approval') NOT NULL DEFAULT 'new',
  `assigned_to` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID nhân viên được gán',
  `manager_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID manager đang giám sát đơn',
  `assigned_at` datetime DEFAULT NULL COMMENT 'Thời gian gán',
  `call_count` int(10) UNSIGNED DEFAULT 0,
  `last_call_at` datetime DEFAULT NULL,
  `callback_time` datetime DEFAULT NULL,
  `source` enum('woocommerce','manual') NOT NULL DEFAULT 'woocommerce' COMMENT 'Nguồn đơn hàng',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID nhân viên tạo đơn thủ công',
  `approval_status` enum('approved','pending','rejected') DEFAULT NULL COMMENT 'Trạng thái duyệt đơn thủ công',
  `approved_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID admin duyệt đơn',
  `approved_at` datetime DEFAULT NULL,
  `woo_created_at` datetime DEFAULT NULL COMMENT 'Thời gian tạo đơn trên WooCommerce',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng đơn hàng';

--
-- Bẫy `orders`
--
DELIMITER $$
CREATE TRIGGER `tr_order_status_change` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO `order_notes` (order_id, user_id, note_type, content)
        VALUES (
            NEW.id,
            NEW.assigned_to, -- Gán cho user đang xử lý đơn, có thể là NULL
            'status',
            CONCAT('Trạng thái đổi từ "', OLD.status, '" sang "', NEW.status, '"')
        );
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_notes`
--

CREATE TABLE `order_notes` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID người ghi chú (NULL nếu là hệ thống)',
  `note_type` enum('manual','status','system','assignment') NOT NULL DEFAULT 'manual',
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng ghi chú đơn hàng';

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `reminders`
--

CREATE TABLE `reminders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('callback','overdue_action') NOT NULL,
  `due_time` datetime NOT NULL,
  `remind_time` datetime DEFAULT NULL,
  `status` enum('pending','sent','completed','cancelled','overdue') NOT NULL DEFAULT 'pending',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nhac nho cho workflow';

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `role_permissions`
--

CREATE TABLE `role_permissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` varchar(50) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng phân quyền theo role';

--
-- Đang đổ dữ liệu cho bảng `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role`, `permission`, `created_at`) VALUES
(1, 'admin', 'view_all_orders', '2025-09-30 18:10:11'),
(2, 'admin', 'manage_orders', '2025-09-30 18:10:11'),
(3, 'admin', 'create_users', '2025-09-30 18:10:11'),
(4, 'admin', 'edit_users', '2025-09-30 18:10:11'),
(5, 'admin', 'delete_users', '2025-09-30 18:10:11'),
(6, 'admin', 'disable_users', '2025-09-30 18:10:11'),
(7, 'admin', 'enable_users', '2025-09-30 18:10:11'),
(8, 'admin', 'view_statistics', '2025-09-30 18:10:11'),
(9, 'admin', 'manage_kpi', '2025-09-30 18:10:11'),
(10, 'admin', 'manage_settings', '2025-09-30 18:10:11'),
(11, 'admin', 'approve_orders', '2025-09-30 18:10:11'),
(12, 'admin', 'transfer_orders', '2025-09-30 18:10:11'),
(13, 'admin', 'reclaim_orders', '2025-09-30 18:10:11'),
(14, 'manager', 'view_all_orders', '2025-09-30 18:10:11'),
(15, 'manager', 'manage_orders', '2025-09-30 18:10:11'),
(16, 'manager', 'receive_handover_orders', '2025-09-30 18:10:11'),
(17, 'manager', 'disable_users', '2025-09-30 18:10:11'),
(18, 'manager', 'view_statistics', '2025-09-30 18:10:11'),
(19, 'manager', 'view_kpi', '2025-09-30 18:10:11'),
(20, 'manager', 'transfer_orders', '2025-09-30 18:10:11'),
(21, 'manager', 'approve_orders', '2025-09-30 18:10:11'),
(22, 'telesale', 'view_own_orders', '2025-09-30 18:10:11'),
(23, 'telesale', 'claim_orders', '2025-09-30 18:10:11'),
(24, 'telesale', 'update_own_orders', '2025-09-30 18:10:11');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rules`
--

CREATE TABLE `rules` (
  `id` int(11) NOT NULL,
  `rule_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `entity_type` enum('order','customer','employee','task','system') NOT NULL,
  `rule_type` enum('status_transition','time_based','event_based','condition_based') NOT NULL,
  `priority` int(11) DEFAULT 50,
  `is_active` tinyint(1) DEFAULT 1,
  `trigger_conditions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`trigger_conditions`)),
  `actions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`actions`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rule_executions`
--

CREATE TABLE `rule_executions` (
  `id` int(11) NOT NULL,
  `rule_id` int(11) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `execution_status` enum('success','failed','skipped') NOT NULL,
  `execution_result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`execution_result`)),
  `error_message` text DEFAULT NULL,
  `executed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rule_templates`
--

CREATE TABLE `rule_templates` (
  `id` int(11) NOT NULL,
  `template_key` varchar(100) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `template_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`template_data`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `rule_templates`
--

INSERT INTO `rule_templates` (`id`, `template_key`, `name`, `description`, `category`, `entity_type`, `template_data`, `is_active`, `created_at`) VALUES
(1, 'auto_vip', 'Tự động nâng cấp VIP', 'Tự động gán nhãn VIP cho khách hàng đủ điều kiện', 'customer', 'customer', '{\"trigger_conditions\":{\"type\":\"AND\",\"conditions\":[{\"field\":\"customer_metrics.total_orders\",\"operator\":\"greater_than\",\"value\":3},{\"field\":\"customer_metrics.total_value\",\"operator\":\"greater_than\",\"value\":2000000}]},\"actions\":[{\"type\":\"add_label\",\"params\":{\"label\":\"vip\"}}]}', 1, '2025-09-30 21:02:04'),
(2, 'no_answer_callback', 'Xử lý không nghe máy', 'Tạo task gọi lại khi khách không nghe máy', 'order', 'order', '{\"trigger_conditions\":{\"type\":\"AND\",\"conditions\":[{\"field\":\"order.status\",\"operator\":\"equals\",\"value\":\"no_answer\"}]},\"actions\":[{\"type\":\"create_task\",\"params\":{\"task_type\":\"callback\",\"due_in_hours\":2,\"reminder_before_minutes\":30}}]}', 1, '2025-09-30 21:02:04'),
(3, 'overdue_warning', 'Cảnh báo task quá hạn', 'Gửi cảnh báo khi task sắp quá hạn', 'task', 'task', '{\"trigger_conditions\":{\"type\":\"AND\",\"conditions\":[{\"field\":\"task.time_until_due\",\"operator\":\"less_than\",\"value\":30}]},\"actions\":[{\"type\":\"send_notification\",\"params\":{\"priority\":\"high\"}}]}', 1, '2025-09-30 21:02:04');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `scheduled_jobs`
--

CREATE TABLE `scheduled_jobs` (
  `id` int(11) NOT NULL,
  `job_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `scheduled_at` datetime NOT NULL,
  `executed_at` datetime DEFAULT NULL,
  `status` enum('pending','processing','completed','failed','cancelled') DEFAULT 'pending',
  `attempts` int(11) DEFAULT 0,
  `max_attempts` int(11) DEFAULT 3,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`)),
  `result` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`result`)),
  `error_message` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng cài đặt';

--
-- Đang đổ dữ liệu cho bảng `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'Tâm Giao Call Center'),
('woo_api_url', ''),
('woo_consumer_key', ''),
('woo_consumer_secret', '');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `status_definitions`
--

CREATE TABLE `status_definitions` (
  `id` int(11) NOT NULL,
  `entity_type` enum('order','customer','employee','task') NOT NULL,
  `status_key` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6c757d',
  `icon` varchar(50) DEFAULT 'fa-circle',
  `sort_order` int(11) DEFAULT 0,
  `is_system` tinyint(1) DEFAULT 0,
  `is_final` tinyint(1) DEFAULT 0,
  `required_fields` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`required_fields`)),
  `validation_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`validation_rules`)),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `status_definitions`
--

INSERT INTO `status_definitions` (`id`, `entity_type`, `status_key`, `label`, `description`, `color`, `icon`, `sort_order`, `is_system`, `is_final`, `required_fields`, `validation_rules`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'order', 'new', 'Đơn mới', 'Đơn hàng vừa được tạo', '#007bff', 'fa-plus-circle', 1, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(2, 'order', 'assigned', 'Đã phân công', 'Đã giao cho nhân viên', '#17a2b8', 'fa-user-check', 2, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(3, 'order', 'processing', 'Đang xử lý', 'Nhân viên đang xử lý', '#ffc107', 'fa-spinner', 3, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(4, 'order', 'calling', 'Đang gọi', 'Đang liên hệ khách hàng', '#fd7e14', 'fa-phone', 4, 0, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(5, 'order', 'no_answer', 'Không nghe máy', 'Khách không nghe máy', '#dc3545', 'fa-phone-slash', 5, 0, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(6, 'order', 'callback_scheduled', 'Hẹn gọi lại', 'Đã hẹn thời gian gọi lại', '#6f42c1', 'fa-clock', 6, 0, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(7, 'order', 'confirmed', 'Đã xác nhận', 'Khách đã xác nhận đặt hàng', '#28a745', 'fa-check-circle', 7, 0, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(8, 'order', 'shipping', 'Đang giao', 'Đơn hàng đang được giao', '#20c997', 'fa-truck', 8, 0, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(9, 'order', 'completed', 'Hoàn thành', 'Giao hàng thành công', '#198754', 'fa-check-double', 9, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(10, 'order', 'cancelled', 'Đã hủy', 'Đơn hàng bị hủy', '#6c757d', 'fa-times-circle', 10, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(11, 'order', 'returned', 'Hoàn trả', 'Hàng bị trả lại', '#dc3545', 'fa-undo', 11, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:03', '2025-09-30 21:02:03'),
(12, 'task', 'pending', 'Chờ xử lý', 'Task chưa bắt đầu', '#6c757d', 'fa-hourglass-start', 1, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:04', '2025-09-30 21:02:04'),
(13, 'task', 'in_progress', 'Đang thực hiện', 'Task đang được xử lý', '#ffc107', 'fa-spinner', 2, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:04', '2025-09-30 21:02:04'),
(14, 'task', 'completed', 'Hoàn thành', 'Task đã hoàn thành', '#28a745', 'fa-check', 3, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:04', '2025-09-30 21:02:04'),
(15, 'task', 'overdue', 'Quá hạn', 'Task đã quá thời hạn', '#dc3545', 'fa-exclamation-triangle', 4, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:04', '2025-09-30 21:02:04'),
(16, 'task', 'cancelled', 'Đã hủy', 'Task bị hủy', '#6c757d', 'fa-times', 5, 1, 0, NULL, NULL, NULL, '2025-09-30 21:02:04', '2025-09-30 21:02:04');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `tasks`
--

CREATE TABLE `tasks` (
  `id` int(11) NOT NULL,
  `task_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL,
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `status` enum('pending','in_progress','completed','cancelled','overdue') DEFAULT 'pending',
  `due_at` datetime DEFAULT NULL,
  `reminder_at` datetime DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `completed_at` datetime DEFAULT NULL,
  `completed_by` int(10) UNSIGNED DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL COMMENT 'Tên đăng nhập',
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL COMMENT 'Họ và tên',
  `email` varchar(100) DEFAULT NULL COMMENT 'Email',
  `phone` varchar(20) DEFAULT NULL COMMENT 'Số điện thoại',
  `role` enum('admin','manager','telesale') NOT NULL DEFAULT 'telesale' COMMENT 'Vai trò: admin, manager, telesale',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active' COMMENT 'Trạng thái: active, inactive, suspended (bị treo)',
  `avatar` varchar(255) DEFAULT NULL COMMENT 'Đường dẫn ảnh đại diện',
  `last_login_at` datetime DEFAULT NULL COMMENT 'Lần đăng nhập cuối',
  `last_login_ip` varchar(45) DEFAULT NULL COMMENT 'IP đăng nhập cuối',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `suspension_reason` text DEFAULT NULL,
  `suspension_until` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng người dùng';

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `status`, `avatar`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`, `suspension_reason`, `suspension_until`) VALUES
(1, 'admin', '$2y$10$5EzgChCuFM.LG/yVCMbuseZFP2fxECDOQJb8FzEmssX4iev/sjbVi', 'Administrator', 'admin@example.com', NULL, 'admin', 'active', NULL, '2025-09-30 11:06:54', '::1', '2025-09-30 09:36:19', '2025-09-30 11:06:54', NULL, NULL),
(2, 'telesale1', '$2y$10$lkpRcTFFgJVlNIawkjprY.n7mubXpkH1/Sa0TOf4pl7rZQw6DVuqa', 'Nguyễn Văn Ad', 'vh6889@gmail.com', '0963470944', 'telesale', 'active', NULL, NULL, NULL, '2025-09-30 09:36:19', '2025-09-30 12:46:43', NULL, NULL),
(3, 'telesale2', '$2y$10$lkpRcTFFgJVlNIawkjprY.n7mubXpkH1/Sa0TOf4pl7rZQw6DVuqa', 'Trần Thị Booo', 'telesale2@example.com', '', 'telesale', 'active', NULL, NULL, NULL, '2025-09-30 09:36:19', '2025-09-30 12:54:32', NULL, NULL),
(4, 'oigioioi', '$2y$10$AxE8XaE9rkf9G7nvTyTFgu1xQNGMAKItLU/tkocwj2ZTJv/JmGppq', 'Hai Vu', 'raintl07@gmail.com', '0963470944', 'manager', 'active', NULL, NULL, NULL, '2025-09-30 18:50:59', '2025-09-30 18:50:59', NULL, NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_actions`
--

CREATE TABLE `user_actions` (
  `action_key` varchar(50) NOT NULL,
  `action_name` varchar(100) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `color` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `user_actions`
--

INSERT INTO `user_actions` (`action_key`, `action_name`, `icon`, `color`) VALUES
('ASSIGN_TRAINING', 'Gán vào chương trình Đào tạo', 'fas fa-chalkboard-teacher', 'info'),
('CHANGE_USER_LABEL', 'Đổi nhãn nhân viên', 'fas fa-user-tag', 'primary'),
('PROPOSE_PROMOTION', 'Đề xuất lên cấp', 'fas fa-level-up-alt', 'success'),
('REMIND_CALLBACK', 'Nhắc gọi lại', 'fas fa-phone-alt', 'info'),
('REMIND_CARE', 'Nhắc chăm sóc', 'fas fa-heart', 'primary'),
('REMIND_NOTE', 'Nhắc ghi chú', 'fas fa-sticky-note', 'secondary'),
('RESTRICT_CLAIMING', 'Hạn chế quyền nhận đơn', 'fas fa-hand-paper', 'secondary'),
('REWARD_PRAISE', 'Nhắc khen thưởng', 'fas fa-star', 'success'),
('SEND_WARNING', 'Gửi cảnh báo', 'fas fa-exclamation-triangle', 'warning'),
('SUGGEST_BONUS_PENALTY', 'Đề xuất Thưởng/Phạt', 'fas fa-coins', 'warning'),
('SUSPEND_ACCOUNT', 'Cấm tài khoản', 'fas fa-user-lock', 'danger');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `user_labels`
--

CREATE TABLE `user_labels` (
  `id` int(11) NOT NULL,
  `label_key` varchar(50) NOT NULL,
  `label_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `color` varchar(20) DEFAULT '#28a745',
  `icon` varchar(50) DEFAULT 'fa-user-tag',
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `auto_assign_rules` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`auto_assign_rules`)),
  `sort_order` int(11) DEFAULT 0,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `user_labels`
--

INSERT INTO `user_labels` (`id`, `label_key`, `label_name`, `description`, `color`, `icon`, `permissions`, `auto_assign_rules`, `sort_order`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'top_performer', 'Xuất sắc', 'Nhân viên xuất sắc', '#ffd700', 'fa-trophy', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35'),
(2, 'warning', 'Cảnh báo', 'Đang bị cảnh báo', '#ffc107', 'fa-exclamation-triangle', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35'),
(3, 'suspended', 'Tạm khóa', 'Tài khoản bị tạm khóa', '#dc3545', 'fa-lock', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35'),
(4, 'training', 'Đào tạo', 'Đang trong giai đoạn đào tạo', '#17a2b8', 'fa-graduation-cap', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35'),
(5, 'probation', 'Thử việc', 'Nhân viên thử việc', '#6f42c1', 'fa-user-clock', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `action_logs`
--
ALTER TABLE `action_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_id`,`entity_type`),
  ADD KEY `idx_user_action` (`user_id`,`action_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`);

--
-- Chỉ mục cho bảng `customer_labels`
--
ALTER TABLE `customer_labels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `label_key` (`label_key`),
  ADD KEY `created_by` (`created_by`);

--
-- Chỉ mục cho bảng `customer_metrics`
--
ALTER TABLE `customer_metrics`
  ADD PRIMARY KEY (`customer_id`),
  ADD UNIQUE KEY `customer_phone` (`customer_phone`),
  ADD KEY `idx_vip` (`is_vip`),
  ADD KEY `idx_blacklist` (`is_blacklisted`),
  ADD KEY `idx_phone` (`customer_phone`);

--
-- Chỉ mục cho bảng `employee_performance`
--
ALTER TABLE `employee_performance`
  ADD PRIMARY KEY (`user_id`),
  ADD KEY `idx_performance` (`performance_score`);

--
-- Chỉ mục cho bảng `kpis`
--
ALTER TABLE `kpis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_user_month_type` (`user_id`,`target_month`,`target_type`);

--
-- Chỉ mục cho bảng `manager_assignments`
--
ALTER TABLE `manager_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_manager_telesale` (`manager_id`,`telesale_id`),
  ADD KEY `idx_manager` (`manager_id`),
  ADD KEY `idx_telesale` (`telesale_id`),
  ADD KEY `assigned_by` (`assigned_by`);

--
-- Chỉ mục cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_unread` (`user_id`,`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `woo_order_id` (`woo_order_id`),
  ADD KEY `idx_woo_order_id` (`woo_order_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_customer_phone` (`customer_phone`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_manager_id` (`manager_id`);

--
-- Chỉ mục cho bảng `order_notes`
--
ALTER TABLE `order_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Chỉ mục cho bảng `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_role_permission` (`role`,`permission`);

--
-- Chỉ mục cho bảng `rules`
--
ALTER TABLE `rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rule_key` (`rule_key`),
  ADD KEY `idx_entity_active` (`entity_type`,`is_active`),
  ADD KEY `idx_priority` (`priority`);

--
-- Chỉ mục cho bảng `rule_executions`
--
ALTER TABLE `rule_executions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rule_entity` (`rule_id`,`entity_id`,`entity_type`),
  ADD KEY `idx_executed_at` (`executed_at`);

--
-- Chỉ mục cho bảng `rule_templates`
--
ALTER TABLE `rule_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `template_key` (`template_key`);

--
-- Chỉ mục cho bảng `scheduled_jobs`
--
ALTER TABLE `scheduled_jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scheduled_status` (`scheduled_at`,`status`),
  ADD KEY `idx_entity` (`entity_id`,`entity_type`);

--
-- Chỉ mục cho bảng `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Chỉ mục cho bảng `status_definitions`
--
ALTER TABLE `status_definitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `entity_status` (`entity_type`,`status_key`),
  ADD KEY `idx_entity_type` (`entity_type`),
  ADD KEY `created_by` (`created_by`);

--
-- Chỉ mục cho bảng `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assigned_by` (`assigned_by`),
  ADD KEY `completed_by` (`completed_by`),
  ADD KEY `idx_assigned_status` (`assigned_to`,`status`),
  ADD KEY `idx_due_at` (`due_at`),
  ADD KEY `idx_entity` (`entity_id`,`entity_type`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Chỉ mục cho bảng `user_actions`
--
ALTER TABLE `user_actions`
  ADD PRIMARY KEY (`action_key`);

--
-- Chỉ mục cho bảng `user_labels`
--
ALTER TABLE `user_labels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `label_key` (`label_key`),
  ADD KEY `created_by` (`created_by`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `action_logs`
--
ALTER TABLE `action_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `customer_labels`
--
ALTER TABLE `customer_labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `kpis`
--
ALTER TABLE `kpis`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `manager_assignments`
--
ALTER TABLE `manager_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `order_notes`
--
ALTER TABLE `order_notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT cho bảng `rules`
--
ALTER TABLE `rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `rule_executions`
--
ALTER TABLE `rule_executions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `rule_templates`
--
ALTER TABLE `rule_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `scheduled_jobs`
--
ALTER TABLE `scheduled_jobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `status_definitions`
--
ALTER TABLE `status_definitions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT cho bảng `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `user_labels`
--
ALTER TABLE `user_labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `customer_labels`
--
ALTER TABLE `customer_labels`
  ADD CONSTRAINT `customer_labels_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `employee_performance`
--
ALTER TABLE `employee_performance`
  ADD CONSTRAINT `employee_performance_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `kpis`
--
ALTER TABLE `kpis`
  ADD CONSTRAINT `kpis_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `manager_assignments`
--
ALTER TABLE `manager_assignments`
  ADD CONSTRAINT `manager_assignments_ibfk_1` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manager_assignments_ibfk_2` FOREIGN KEY (`telesale_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `manager_assignments_ibfk_3` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `order_notes`
--
ALTER TABLE `order_notes`
  ADD CONSTRAINT `order_notes_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_notes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reminders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `status_definitions`
--
ALTER TABLE `status_definitions`
  ADD CONSTRAINT `status_definitions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `tasks_ibfk_3` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `user_labels`
--
ALTER TABLE `user_labels`
  ADD CONSTRAINT `user_labels_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
