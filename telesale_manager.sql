-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 01, 2025 lúc 09:27 PM
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
(11, 1, 'create_user', 'Created new user: oigioioi', 'user', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 18:50:59'),
(12, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:23:10'),
(13, 1, 'create_rule', 'Created rule: Xử lý khách không nghe máy lâu', 'rule', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:28:22'),
(14, 1, 'create_rule', 'Created rule: Nâng cấp khách VIP', 'rule', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:28:23'),
(15, 1, 'create_rule', 'Created rule: Suspend nhân viên yếu kém', 'rule', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:28:23'),
(16, 1, 'create_rule', 'Created rule: Xử lý khách không nghe máy lâu', 'rule', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:39:09'),
(17, 1, 'create_rule', 'Created rule: Suspend nhân viên yếu kém', 'rule', 5, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:39:09'),
(18, 1, 'create_rule', 'Created rule: Nâng cấp khách VIP', 'rule', 6, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:39:09'),
(19, 1, 'delete_rule', 'Deleted rule #3', 'rule', 3, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:40:24'),
(20, 1, 'delete_rule', 'Deleted rule #4', 'rule', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:40:28'),
(21, 1, 'delete_rule', 'Deleted rule #2', 'rule', 2, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 23:40:38'),
(22, 1, 'create_rule', 'Created rule: Tự chuyển không nghe thành rác', 'rule', 7, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 00:36:21'),
(23, 1, 'migrate_dynamic_status', 'Migrated system to use dynamic status', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 06:09:44'),
(24, 1, 'start_call', 'Started call for order #DYN001', 'order', 22, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 07:12:38'),
(25, 1, 'end_call', 'Completed call for order #22 (Duration: 00:02:02)', 'order', 22, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 07:14:40'),
(26, 1, 'complete_cleanup', 'Executed complete system cleanup - removed all hardcoded values', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 09:23:38'),
(27, 1, 'final_cleanup', 'Executed final cleanup - removed all hardcode', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 09:36:30'),
(28, 1, 'complete_hardcode_fix', 'Fixed all remaining hardcoded statuses in 14 files', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 09:53:43'),
(29, 1, 'complete_hardcode_fix', 'Fixed all remaining hardcoded statuses in 0 files', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 09:55:36'),
(30, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 11:11:01'),
(31, 1, 'safe_fix_complete', 'Applied 10 fixes', 'system', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 11:11:12'),
(32, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 11:32:29'),
(33, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 11:32:32'),
(34, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 13:25:32'),
(35, 1, 'logout', 'User logged out', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 13:46:22'),
(36, 2, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 13:46:40'),
(37, 1, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 22:16:13'),
(38, 1, 'reclaim_order', 'Reclaimed order #TEST001 to common pool', 'order', 62, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-02 00:45:25'),
(39, 1, 'reclaim_order', 'Reclaimed order #TEST003 to common pool', 'order', 64, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '2025-10-02 00:49:19'),
(40, 1, 'transfer_order', 'Transferred order #TEST002 to telesale2', 'order', 63, '::1', 'Mozilla/5.0 (Linux; Android 6.0; Nexus 5 Build/MRA58N) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36', '2025-10-02 00:54:14'),
(41, 2, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-02 00:55:08');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `call_logs`
--

CREATE TABLE `call_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `user_name` varchar(100) DEFAULT NULL COMMENT 'Lưu tên nhân viên tại thời điểm gọi',
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `duration` int(10) UNSIGNED DEFAULT NULL COMMENT 'Thời lượng cuộc gọi (giây)',
  `note` text DEFAULT NULL COMMENT 'Ghi chú cuộc gọi',
  `status` enum('active','completed','dropped') DEFAULT 'active',
  `recording_url` varchar(255) DEFAULT NULL COMMENT 'URL file ghi âm (nếu có)',
  `customer_feedback` tinyint(1) DEFAULT NULL COMMENT 'Đánh giá của khách (1-5)',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lịch sử cuộc gọi chi tiết';

--
-- Bẫy `call_logs`
--
DELIMITER $$
CREATE TRIGGER `calculate_call_duration` BEFORE UPDATE ON `call_logs` FOR EACH ROW BEGIN
    IF NEW.end_time IS NOT NULL AND OLD.end_time IS NULL THEN
        SET NEW.duration = TIMESTAMPDIFF(SECOND, NEW.start_time, NEW.end_time);
    END IF;
END
$$
DELIMITER ;

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `customer_labels`
--

INSERT INTO `customer_labels` (`id`, `label_key`, `label_name`, `description`, `color`, `icon`, `auto_assign_rules`, `restrictions`, `sort_order`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'vip', 'VIP', 'Khách hàng VIP', '#ffd700', 'fa-crown', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(2, 'normal', 'Thường', 'Khách hàng thông thường', '#6c757d', 'fa-user', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(3, 'potential', 'Tiềm năng', 'Khách hàng tiềm năng', '#17a2b8', 'fa-star', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(4, 'blacklist', 'Blacklist', 'Khách hàng cần cảnh báo', '#dc3545', 'fa-ban', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(5, 'returning', 'Quay lại', 'Khách hàng quay lại', '#28a745', 'fa-redo', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(6, 'khach-bom-hang', 'Khách bom hàng', 'Bom hàng', '#c21d00', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(7, 'khach-hang-rac', 'Khách hàng rác', 'Không đặt gì hoặc thái độ khó chịu, không mua hàng', '#626160', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(8, 'khach-hang-than-quen', 'Khách hàng thân quen', 'Khách đã mua hàng ít nhất 2 lần', '#31c12f', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(9, 'khach-hang-vip', 'Khách hàng VIP', 'Khách hàng mua hàng nhiều lần, giá trị lớn', '#d0bf01', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(10, 'n-a', 'Khách hàng tiềm năng', 'Khách hàng mới đặt hàng, chưa xác nhận', '#6f899f', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(11, 'n-a-1759225492', 'Khách gọi nhiều không nghe', 'Không từ chối nhưng gọi mãi không nghe', '#978e20', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(12, 'n-a-1759225595', 'Khách hàng đã xác nhận', 'Khách hàng đã xác nhận mua hàng chờ gửi hàng', '#51c8f0', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(13, 'n-a-1759225632', 'Khách hàng mới nhận', 'Khách mới nhận hàng lần đầu', '#7ec80e', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(14, 'n-a-1759225700', 'Khách nhận một phần', 'Khách hàng chỉ nhận một phần', '#28b87a', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL),
(15, 'n-a-1759225776', 'Khách đã nhận 5 ngày', 'Khách đã nhận hàng 5 ngày cần chăm sóc lần 1', '#1b94ac', 'fa-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:04', '2025-09-30 14:52:04', NULL);

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
-- Cấu trúc bảng cho bảng `failed_login_attempts`
--

CREATE TABLE `failed_login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp()
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
  `system_status` enum('free','assigned') NOT NULL DEFAULT 'free',
  `core_status` enum('new','processing','success','failed') NOT NULL DEFAULT 'new',
  `primary_label` varchar(50) DEFAULT NULL,
  `assigned_to` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID nhân viên được gán',
  `manager_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID manager đang giám sát đơn',
  `assigned_at` datetime DEFAULT NULL COMMENT 'Thời gian gán',
  `call_count` int(10) UNSIGNED DEFAULT 0,
  `last_call_at` datetime DEFAULT NULL,
  `callback_time` datetime DEFAULT NULL,
  `source` enum('woocommerce','manual') NOT NULL DEFAULT 'woocommerce' COMMENT 'Nguồn đơn hàng',
  `created_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID nhân viên tạo đơn thủ công',
  `approval_status` varchar(50) DEFAULT NULL,
  `approved_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'ID admin duyệt đơn',
  `approved_at` datetime DEFAULT NULL,
  `woo_created_at` datetime DEFAULT NULL COMMENT 'Thời gian tạo đơn trên WooCommerce',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `is_locked` tinyint(1) DEFAULT 0 COMMENT 'Đơn hàng đã khóa sau khi xử lý xong',
  `locked_at` datetime DEFAULT NULL COMMENT 'Thời gian khóa đơn hàng',
  `locked_by` int(10) UNSIGNED DEFAULT NULL COMMENT 'Người khóa đơn hàng',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `version` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng đơn hàng';

--
-- Đang đổ dữ liệu cho bảng `orders`
--

INSERT INTO `orders` (`id`, `woo_order_id`, `order_number`, `customer_name`, `customer_phone`, `customer_email`, `customer_address`, `total_amount`, `currency`, `payment_method`, `products`, `customer_notes`, `system_status`, `core_status`, `primary_label`, `assigned_to`, `manager_id`, `assigned_at`, `call_count`, `last_call_at`, `callback_time`, `source`, `created_by`, `approval_status`, `approved_by`, `approved_at`, `woo_created_at`, `created_at`, `updated_at`, `completed_at`, `is_locked`, `locked_at`, `locked_by`, `deleted_at`, `version`) VALUES
(62, NULL, 'TEST001', 'Nguyễn Văn A', '0901234567', 'nguyenvana@gmail.com', '123 Đường Lê Lợi, Quận 1, TP.HCM', 1500000.00, 'VND', 'COD', '[{\"product_name\":\"Áo thun nam\",\"quantity\":2,\"price\":250000},{\"product_name\":\"Quần jean\",\"quantity\":1,\"price\":500000}]', 'Giao hàng ngoài giờ hành chính', 'assigned', 'processing', 'lbl_new_order', 3, NULL, '2025-10-02 00:45:33', 0, NULL, NULL, 'manual', NULL, NULL, NULL, NULL, NULL, '2025-10-01 21:47:27', '2025-10-02 00:45:33', NULL, 0, NULL, NULL, NULL, 1),
(63, NULL, 'TEST002', 'Trần Thị B', '0912345678', 'tranthib@yahoo.com', '456 Đường Nguyễn Huệ, Quận 3, TP.HCM', 2800000.00, 'VND', 'Banking', '[{\"product_name\":\"Laptop Dell\",\"quantity\":1,\"price\":2800000}]', 'Cần kiểm tra kỹ hàng trước khi nhận', 'assigned', 'processing', 'lbl_processing', 3, NULL, '2025-10-02 00:54:14', 0, NULL, NULL, 'manual', NULL, NULL, NULL, NULL, NULL, '2025-10-01 21:47:27', '2025-10-02 00:54:14', NULL, 0, NULL, NULL, NULL, 1),
(64, NULL, 'TEST003', 'Lê Văn C', '0923456789', 'levanc@hotmail.com', '789 Đường Trần Hưng Đạo, Quận 5, TP.HCM', 5000000.00, 'VND', 'COD', '[{\"product_name\":\"iPhone 15 Pro\",\"quantity\":1,\"price\":5000000}]', 'Khách VIP, ưu tiên gọi buổi sáng', 'assigned', 'processing', 'lbl_new_order', 2, NULL, '2025-10-02 00:49:29', 0, NULL, NULL, 'manual', NULL, NULL, NULL, NULL, NULL, '2025-10-01 21:47:27', '2025-10-02 00:49:29', NULL, 0, NULL, NULL, NULL, 1),
(65, NULL, 'WOO12345', 'Phạm Thị D', '0934567890', 'phamthid@gmail.com', '321 Đường Võ Văn Tần, Quận 10, TP.HCM', 750000.00, 'VND', 'VNPAY', '[{\"product_name\":\"Giày thể thao Nike\",\"quantity\":1,\"price\":750000}]', 'Gọi trước 30 phút', 'free', 'new', 'lbl_new_order', NULL, NULL, NULL, 0, NULL, NULL, 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 21:47:27', '2025-10-02 00:23:59', NULL, 0, NULL, NULL, NULL, 1),
(66, NULL, 'TEST005', 'Hoàng Văn E', '0945678901', 'hoangvane@outlook.com', '654 Đường Hai Bà Trưng, Quận Tân Bình, TP.HCM', 350000.00, 'VND', 'COD', '[{\"product_name\":\"Túi xách nữ\",\"quantity\":1,\"price\":350000}]', NULL, 'free', 'new', 'lbl_new_order', NULL, NULL, NULL, 0, NULL, NULL, 'manual', NULL, NULL, NULL, NULL, NULL, '2025-10-01 21:47:27', '2025-10-02 00:23:59', NULL, 0, NULL, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_labels`
--

CREATE TABLE `order_labels` (
  `label_key` varchar(50) NOT NULL,
  `label_name` varchar(100) NOT NULL,
  `core_status` enum('new','processing','success','failed') NOT NULL DEFAULT 'processing' COMMENT 'Nhãn thuộc nhóm nào (do Admin setup). Khi = success -> TỰ ĐỘNG KHÓA ĐƠN',
  `description` text DEFAULT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#6c757d',
  `icon` varchar(50) NOT NULL DEFAULT 'fa-tag',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_system` tinyint(1) DEFAULT 0,
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'Là nhãn mặc định của core_status',
  `auto_lock` tinyint(1) DEFAULT 0,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Nhãn đơn hàng - Mỗi nhãn PHẢI thuộc 1 core_status. HỆ THỐNG KHÔNG TỰ QUYẾT ĐỊNH, chỉ lưu theo user chọn';

--
-- Đang đổ dữ liệu cho bảng `order_labels`
--

INSERT INTO `order_labels` (`label_key`, `label_name`, `core_status`, `description`, `color`, `icon`, `sort_order`, `is_system`, `is_default`, `auto_lock`, `metadata`, `created_by`, `created_at`, `updated_at`) VALUES
('lbl_callback', 'Hẹn gọi lại', 'processing', 'Khách yêu cầu gọi lại sau', '#FFC107', 'fa-phone-alt', 5, 0, 0, 0, NULL, NULL, '2025-10-01 21:29:24', '2025-10-01 21:29:24'),
('lbl_cancelled', 'Thất bại', 'failed', NULL, '#dc3545', 'fa-times-circle', 9998, 0, 0, 0, NULL, NULL, '2025-10-02 00:23:59', '2025-10-02 02:02:59'),
('lbl_completed', 'Hoàn thành', 'success', NULL, '#28a745', 'fa-check-circle', 9999, 0, 0, 0, NULL, NULL, '2025-10-02 00:23:59', '2025-10-02 02:02:59'),
('lbl_confirmed', 'Đã xác nhận', 'processing', NULL, '#28a745', 'fa-check', 2, 0, 0, 0, NULL, NULL, '2025-10-01 22:38:43', '2025-10-01 22:38:43'),
('lbl_new_order', 'Đơn mới', 'new', 'Đơn mới vào hệ thống - HỆ THỐNG TỰ GÁN', '#17a2b8', 'fa-plus-circle', -1000, 1, 1, 0, NULL, NULL, '2025-10-02 00:23:59', '2025-10-02 02:02:58'),
('lbl_processing', 'Đang xử lý', 'processing', 'Lần đầu nhân viên nhận đơn - HỆ THỐNG TỰ GÁN', '#ffc107', 'fa-spinner', 0, 1, 1, 0, NULL, NULL, '2025-10-02 00:23:59', '2025-10-02 02:02:59');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_label_history`
--

CREATE TABLE `order_label_history` (
  `id` int(11) NOT NULL,
  `order_id` int(10) UNSIGNED NOT NULL,
  `label_key` varchar(50) NOT NULL,
  `action` enum('assigned','removed') NOT NULL DEFAULT 'assigned',
  `assigned_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng ghi chú đơn hàng';

--
-- Đang đổ dữ liệu cho bảng `order_notes`
--

INSERT INTO `order_notes` (`id`, `order_id`, `user_id`, `note_type`, `content`, `created_at`, `deleted_at`) VALUES
(7, 62, 1, 'assignment', 'Phân công cho Nguyễn Văn Ad', '2025-10-02 00:44:15', NULL),
(8, 62, 1, 'system', 'Admin Administrator đã thu hồi đơn hàng về kho chung từ Nguyễn Văn Ad', '2025-10-02 00:45:25', NULL),
(9, 62, 1, 'assignment', 'Phân công cho Trần Thị Booo', '2025-10-02 00:45:33', NULL),
(10, 64, 1, 'assignment', 'Phân công cho Trần Thị Booo', '2025-10-02 00:46:33', NULL),
(11, 64, 1, 'system', 'Admin Administrator đã thu hồi đơn hàng về kho chung từ Trần Thị Booo', '2025-10-02 00:49:19', NULL),
(12, 64, 1, 'assignment', 'Phân công cho Nguyễn Văn Ad', '2025-10-02 00:49:30', NULL),
(13, 63, 1, 'assignment', 'Phân công cho Nguyễn Văn Ad', '2025-10-02 00:54:05', NULL),
(14, 63, 1, 'assignment', 'Chuyển giao từ Nguyễn Văn Ad cho Trần Thị Booo', '2025-10-02 00:54:14', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `password_history`
--

CREATE TABLE `password_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` int(11) NOT NULL,
  `rate_key` varchar(255) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `rate_limits`
--

INSERT INTO `rate_limits` (`id`, `rate_key`, `user_id`, `action`, `created_at`) VALUES
(1, 'rate_limit_assign-order_1', 1, 'assign-order', '2025-10-02 00:44:15'),
(2, 'rate_limit_reclaim-order_1', 1, 'reclaim-order', '2025-10-02 00:45:25'),
(3, 'rate_limit_reclaim-order_1', 1, 'reclaim-order', '2025-10-02 00:49:19');

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

--
-- Đang đổ dữ liệu cho bảng `rules`
--

INSERT INTO `rules` (`id`, `rule_key`, `name`, `description`, `entity_type`, `rule_type`, `priority`, `is_active`, `trigger_conditions`, `actions`, `metadata`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'rule_1759249702_3702', 'Xử lý khách không nghe máy lâu', 'Tự động chuyển đơn sang rác khi gọi nhiều lần không nghe', 'order', 'condition_based', 90, 1, '{\"type\":\"AND\",\"rules\":[{\"field\":\"order.status\",\"operator\":\"equals\",\"value\":\"khong-nghe\"},{\"field\":\"order.hours_since_created\",\"operator\":\"greater_than\",\"value\":\"48\"},{\"field\":\"order.call_count\",\"operator\":\"greater_than\",\"value\":\"5\"}]}', '[{\"type\":\"change_order_status\",\"params\":{\"status\":\"n-a-1759224173\"}},{\"type\":\"add_customer_label\",\"params\":{\"label_key\":\"n-a-1759225492\"}},{\"type\":\"send_notification\",\"params\":{\"to\":\"manager\",\"priority\":\"high\",\"message\":\"\\u0110\\u01a1n h\\u00e0ng chuy\\u1ec3n v\\u00e0o r\\u00e1c do kh\\u00f4ng li\\u00ean l\\u1ea1c \\u0111\\u01b0\\u1ee3c\"}}]', NULL, 1, '2025-09-30 23:28:22', '2025-09-30 23:28:22'),
(5, 'rule_1759250349_6473', 'Suspend nhân viên yếu kém', 'Tự động suspend nhân viên có hiệu suất thấp và vi phạm nhiều', 'employee', 'condition_based', 85, 1, '{\"type\":\"AND\",\"rules\":[{\"field\":\"employee.performance_score\",\"operator\":\"less_than\",\"value\":\"50\"},{\"field\":\"employee.violation_count\",\"operator\":\"greater_than\",\"value\":\"3\"},{\"field\":\"employee.role\",\"operator\":\"equals\",\"value\":\"telesale\"}]}', '[{\"type\":\"add_user_label\",\"params\":{\"label_key\":\"n-a\"}},{\"type\":\"suspend_user\",\"params\":{\"duration_hours\":\"24\",\"reason\":\"Performance k\\u00e9m v\\u00e0 vi ph\\u1ea1m nhi\\u1ec1u\"}},{\"type\":\"send_notification\",\"params\":{\"to\":\"admin\",\"priority\":\"urgent\",\"message\":\"\\u0110\\u00e3 suspend nh\\u00e2n vi\\u00ean do performance k\\u00e9m\"}}]', NULL, 1, '2025-09-30 23:39:09', '2025-09-30 23:39:09'),
(6, 'rule_1759250349_1494', 'Nâng cấp khách VIP', 'Tự động gắn nhãn VIP cho khách hàng mua nhiều', 'customer', 'condition_based', 70, 1, '{\"type\":\"AND\",\"rules\":[{\"field\":\"customer.total_orders\",\"operator\":\"greater_than\",\"value\":\"5\"},{\"field\":\"customer.total_value\",\"operator\":\"greater_than\",\"value\":\"10000000\"}]}', '[{\"type\":\"add_customer_label\",\"params\":{\"label_key\":\"khach-hang-vip\"}},{\"type\":\"mark_customer_vip\",\"params\":[]}]', NULL, 1, '2025-09-30 23:39:09', '2025-09-30 23:39:09'),
(7, 'rule_1759253781_1583', 'Tự chuyển không nghe thành rác', 'Chuyển những đơn gọi nhiều lần khách không nghe thành đơn rác', 'order', 'condition_based', 50, 1, '{\"type\":\"AND\",\"rules\":[{\"field\":\"order.assigned_to_role\",\"operator\":\"equals\",\"value\":\"manager\"},{\"field\":\"order.status\",\"operator\":\"equals\",\"value\":\"khong-nghe\"},{\"field\":\"order.hours_since_created\",\"operator\":\"greater_than_or_equals\",\"value\":\"48\"},{\"field\":\"order.assigned_to_role\",\"operator\":\"equals\",\"value\":\"telesale\"},{\"field\":\"order.total_calls\",\"operator\":\"greater_than\",\"value\":\"5\"}]}', '[{\"type\":\"change_order_status\",\"params\":{\"status\":\"n-a-1759224173\"}},{\"type\":\"add_customer_label\",\"params\":{\"label_key\":\"n-a-1759225492\"}},{\"type\":\"send_notification\",\"params\":{\"to\":\"admin\",\"priority\":\"normal\",\"message\":\"\\u0111\\u01a1n r\\u00e1c\"}}]', NULL, 1, '2025-10-01 00:36:21', '2025-10-01 00:36:21');

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
-- Cấu trúc bảng cho bảng `security_logs`
--

CREATE TABLE `security_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `suspension_until` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng người dùng';

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `status`, `avatar`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`, `suspension_reason`, `suspension_until`, `deleted_at`) VALUES
(1, 'admin', '$2y$10$5EzgChCuFM.LG/yVCMbuseZFP2fxECDOQJb8FzEmssX4iev/sjbVi', 'Administrator', 'admin@example.com', NULL, 'admin', 'active', NULL, '2025-10-01 22:16:13', '::1', '2025-09-30 09:36:19', '2025-10-01 22:16:13', NULL, NULL, NULL),
(2, 'telesale1', '$2y$10$lkpRcTFFgJVlNIawkjprY.n7mubXpkH1/Sa0TOf4pl7rZQw6DVuqa', 'Nguyễn Văn Ad', 'vh6889@gmail.com', '0963470944', 'telesale', 'active', NULL, '2025-10-02 00:55:08', '::1', '2025-09-30 09:36:19', '2025-10-02 00:55:08', NULL, NULL, NULL),
(3, 'telesale2', '$2y$10$lkpRcTFFgJVlNIawkjprY.n7mubXpkH1/Sa0TOf4pl7rZQw6DVuqa', 'Trần Thị Booo', 'telesale2@example.com', '', 'telesale', 'active', NULL, NULL, NULL, '2025-09-30 09:36:19', '2025-09-30 12:54:32', NULL, NULL, NULL),
(4, 'oigioioi', '$2y$10$AxE8XaE9rkf9G7nvTyTFgu1xQNGMAKItLU/tkocwj2ZTJv/JmGppq', 'Hai Vu', 'raintl07@gmail.com', '0963470944', 'manager', 'active', NULL, NULL, NULL, '2025-09-30 18:50:59', '2025-09-30 18:50:59', NULL, NULL, NULL);

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `user_labels`
--

INSERT INTO `user_labels` (`id`, `label_key`, `label_name`, `description`, `color`, `icon`, `permissions`, `auto_assign_rules`, `sort_order`, `created_by`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'top_performer', 'Xuất sắc', 'Nhân viên xuất sắc', '#ffd700', 'fa-trophy', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(2, 'warning', 'Cảnh báo', 'Đang bị cảnh báo', '#ffc107', 'fa-exclamation-triangle', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(3, 'suspended', 'Tạm khóa', 'Tài khoản bị tạm khóa', '#dc3545', 'fa-lock', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(4, 'training', 'Đào tạo', 'Đang trong giai đoạn đào tạo', '#17a2b8', 'fa-graduation-cap', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(5, 'probation', 'Thử việc', 'Nhân viên thử việc', '#6f42c1', 'fa-user-clock', NULL, NULL, 0, NULL, '2025-09-30 20:55:35', '2025-09-30 20:55:35', NULL),
(6, 'n-a', 'Nhân viên yếu kém', 'Nhân viên có tỉ lệ chốt đơn dưới 50% trong tháng', '#383838', 'fa-user-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:05', '2025-09-30 14:52:05', NULL),
(7, 'n-a-1759229091', 'Nhân viên gương mẫu', 'Nhân viên dưới 80%', '#0b74d5', 'fa-user-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:05', '2025-09-30 14:52:05', NULL),
(8, 'n-a-1759229123', 'Nhân viên xuất sắc', 'Nhân viên trên 90%', '#ffbb00', 'fa-user-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:05', '2025-09-30 14:52:05', NULL),
(9, 'n-a-1759229353', 'Nhân viên mới', 'Nhân viên mới gia nhập', '#04ff00', 'fa-user-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:05', '2025-09-30 14:52:05', NULL),
(10, 'nhan-vien-trung-binh', 'Nhân viên trung bình', 'Nhân viên có tỉ lệ trung bình dưới 60%', '#9a972d', 'fa-user-tag', NULL, NULL, 0, NULL, '2025-09-30 14:52:05', '2025-09-30 14:52:05', NULL);

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `call_logs`
--
ALTER TABLE `call_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_start_time` (`start_time`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_active_calls` (`order_id`,`user_id`,`end_time`),
  ADD KEY `idx_call_logs_order_user` (`order_id`,`user_id`),
  ADD KEY `idx_calls_order` (`order_id`),
  ADD KEY `idx_order_user_active` (`order_id`,`user_id`,`status`),
  ADD KEY `idx_user_start_time` (`user_id`,`start_time`);

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
-- Chỉ mục cho bảng `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_attempted` (`attempted_at`);

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
  ADD KEY `idx_assigned_to` (`assigned_to`),
  ADD KEY `idx_customer_phone` (`customer_phone`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_manager_id` (`manager_id`),
  ADD KEY `idx_orders_assigned_to` (`assigned_to`),
  ADD KEY `idx_orders_created_at` (`created_at`),
  ADD KEY `idx_orders_assigned` (`assigned_to`),
  ADD KEY `idx_orders_created` (`created_at`),
  ADD KEY `idx_orders_phone` (`customer_phone`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_approval_status` (`approval_status`),
  ADD KEY `idx_source` (`source`),
  ADD KEY `idx_system_status` (`system_status`),
  ADD KEY `idx_primary_label` (`primary_label`),
  ADD KEY `idx_orders_core` (`core_status`),
  ADD KEY `idx_orders_core_status` (`core_status`);

--
-- Chỉ mục cho bảng `order_labels`
--
ALTER TABLE `order_labels`
  ADD PRIMARY KEY (`label_key`),
  ADD KEY `idx_sort_order` (`sort_order`),
  ADD KEY `idx_order_labels_core` (`core_status`,`is_default`),
  ADD KEY `idx_order_labels_core_status` (`core_status`);

--
-- Chỉ mục cho bảng `order_label_history`
--
ALTER TABLE `order_label_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_label_key` (`label_key`);

--
-- Chỉ mục cho bảng `order_notes`
--
ALTER TABLE `order_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_notes_order` (`order_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `password_history`
--
ALTER TABLE `password_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Chỉ mục cho bảng `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_rate_key` (`rate_key`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_remind_order` (`order_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_due_time` (`due_time`),
  ADD KEY `idx_pending_reminders` (`status`,`due_time`),
  ADD KEY `idx_user_pending` (`user_id`,`status`,`due_time`);

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
-- Chỉ mục cho bảng `security_logs`
--
ALTER TABLE `security_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_event` (`event_type`),
  ADD KEY `idx_created` (`created_at`);

--
-- Chỉ mục cho bảng `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

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
-- AUTO_INCREMENT cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT cho bảng `call_logs`
--
ALTER TABLE `call_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `customer_labels`
--
ALTER TABLE `customer_labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT cho bảng `failed_login_attempts`
--
ALTER TABLE `failed_login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT cho bảng `order_label_history`
--
ALTER TABLE `order_label_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `order_notes`
--
ALTER TABLE `order_notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT cho bảng `password_history`
--
ALTER TABLE `password_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT cho bảng `rules`
--
ALTER TABLE `rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

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
-- AUTO_INCREMENT cho bảng `security_logs`
--
ALTER TABLE `security_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT cho bảng `user_labels`
--
ALTER TABLE `user_labels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `call_logs`
--
ALTER TABLE `call_logs`
  ADD CONSTRAINT `call_logs_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `call_logs_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Các ràng buộc cho bảng `order_label_history`
--
ALTER TABLE `order_label_history`
  ADD CONSTRAINT `order_label_history_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_label_history_ibfk_2` FOREIGN KEY (`label_key`) REFERENCES `order_labels` (`label_key`) ON DELETE CASCADE;

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
