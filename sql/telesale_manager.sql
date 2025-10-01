-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th10 01, 2025 lúc 09:22 AM
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

DELIMITER $$
--
-- Thủ tục
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `get_next_valid_statuses` (IN `current_status` VARCHAR(50))   BEGIN
    DECLARE current_sort_order INT;
    DECLARE logic_json TEXT;
    
    -- Lấy thông tin status hiện tại
    SELECT sort_order, logic_json INTO current_sort_order, logic_json
    FROM order_status_configs
    WHERE status_key = current_status
    LIMIT 1;
    
    -- Nếu có định nghĩa next_statuses trong logic_json
    IF logic_json IS NOT NULL AND logic_json != '' AND JSON_VALID(logic_json) AND JSON_EXTRACT(logic_json, '$.next_statuses') IS NOT NULL THEN
        -- Return configured next statuses
        SELECT osc.status_key, osc.label, osc.color, osc.icon
        FROM order_status_configs osc
        WHERE JSON_CONTAINS(
            JSON_EXTRACT(logic_json, '$.next_statuses'),
            JSON_QUOTE(osc.status_key)
        );
    ELSE
        -- Mặc định: return tất cả status có sort_order >= hiện tại
        SELECT status_key, label, color, icon
        FROM order_status_configs
        WHERE sort_order > IFNULL(current_sort_order, 0)
        ORDER BY sort_order
        LIMIT 5;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_status_info` (IN `status_key_param` VARCHAR(50))   BEGIN
    SELECT *
    FROM order_status_configs
    WHERE status_key = status_key_param
    LIMIT 1;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `get_status_list` ()   BEGIN
    SELECT 
        status_key,
        label,
        color,
        icon,
        sort_order
    FROM order_status_configs
    ORDER BY sort_order;
END$$

--
-- Các hàm
--
CREATE DEFINER=`root`@`localhost` FUNCTION `get_default_status` () RETURNS VARCHAR(50) CHARSET utf8mb4 COLLATE utf8mb4_general_ci DETERMINISTIC READS SQL DATA BEGIN
                DECLARE default_status VARCHAR(50);
                SELECT status_key INTO default_status
                FROM order_status_configs
                ORDER BY sort_order ASC
                LIMIT 1;
                RETURN default_status;
            END$$

CREATE DEFINER=`root`@`localhost` FUNCTION `is_valid_status` (`check_status` VARCHAR(50)) RETURNS TINYINT(1) DETERMINISTIC READS SQL DATA BEGIN
    DECLARE is_valid INT;
    
    SELECT COUNT(*) INTO is_valid
    FROM order_status_configs
    WHERE status_key = check_status;
    
    RETURN is_valid > 0;
END$$

DELIMITER ;

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
(36, 2, 'login', 'User logged in', NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-01 13:46:40');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `audit_trail`
--

CREATE TABLE `audit_trail` (
  `id` int(11) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) NOT NULL,
  `action` enum('CREATE','UPDATE','DELETE') NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Đang đổ dữ liệu cho bảng `call_logs`
--

INSERT INTO `call_logs` (`id`, `order_id`, `user_id`, `user_name`, `start_time`, `end_time`, `duration`, `note`, `status`, `recording_url`, `customer_feedback`, `created_at`) VALUES
(1, 22, 1, 'Administrator', '2025-10-01 07:12:38', '2025-10-01 07:14:40', 122, 'gọi thử', 'completed', NULL, NULL, '2025-10-01 07:12:38'),
(2, 22, 1, 'Administrator', '2025-10-01 07:54:49', '2025-10-01 07:57:59', 190, 'gọi thử', 'completed', NULL, NULL, '2025-10-01 07:54:49'),
(3, 28, 1, 'Administrator', '2025-10-01 07:59:26', '2025-10-01 08:02:05', 159, 'mua thử', 'completed', NULL, NULL, '2025-10-01 07:59:26'),
(4, 28, 1, 'Administrator', '2025-10-01 08:02:27', '2025-10-01 08:02:35', 8, 'cập nhật', 'completed', NULL, NULL, '2025-10-01 08:02:27'),
(5, 27, 1, 'Administrator', '2025-10-01 09:56:24', '2025-10-01 09:57:10', 46, 'test', 'completed', NULL, NULL, '2025-10-01 09:56:24');

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
  `status` varchar(50) DEFAULT 'new',
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

INSERT INTO `orders` (`id`, `woo_order_id`, `order_number`, `customer_name`, `customer_phone`, `customer_email`, `customer_address`, `total_amount`, `currency`, `payment_method`, `products`, `customer_notes`, `status`, `assigned_to`, `manager_id`, `assigned_at`, `call_count`, `last_call_at`, `callback_time`, `source`, `created_by`, `approval_status`, `approved_by`, `approved_at`, `woo_created_at`, `created_at`, `updated_at`, `completed_at`, `is_locked`, `locked_at`, `locked_by`, `deleted_at`, `version`) VALUES
(22, NULL, 'DYN001', 'Anh Hải', '0963864597', '', '55 Ngô Kim Tài, Kênh Dương, Hải Phòng', 300000.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":3,\"price\":100000,\"sku\":\"N/A\",\"regular_price\":100000,\"sale_price\":100000,\"attributes\":[],\"line_total\":300000}]', NULL, 'n-a', 1, NULL, '2025-10-01 07:54:42', 2, '2025-10-01 07:57:59', '0000-00-00 00:00:00', 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 09:22:37', NULL, 0, NULL, NULL, NULL, 1),
(23, NULL, 'DYN002', 'Test Đang giao', '0900000001', NULL, '123 Test Street, District 2', 1628333.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":1,\"price\":100000}]', NULL, 'dang-giao', NULL, NULL, NULL, 0, NULL, NULL, 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 06:09:44', NULL, 0, NULL, NULL, NULL, 1),
(24, NULL, 'DYN003', 'Test Đang hoàn', '0900000002', NULL, '123 Test Street, District 3', 1233607.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":1,\"price\":100000}]', NULL, 'dang-hoan', NULL, NULL, NULL, 0, NULL, NULL, 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 06:09:44', NULL, 0, NULL, NULL, NULL, 1),
(25, NULL, 'DYN004', 'Test Đóng gói sai', '0900000003', NULL, '123 Test Street, District 4', 1030515.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":1,\"price\":100000}]', NULL, 'dong-goi-sai', NULL, NULL, NULL, 0, NULL, NULL, 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 06:09:44', NULL, 0, NULL, NULL, NULL, 1),
(26, NULL, 'DYN005', 'Test Giao thành công', '0900000004', NULL, '123 Test Street, District 5', 1169812.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":1,\"price\":100000}]', NULL, 'giao-thanh-cong', NULL, NULL, NULL, 0, NULL, NULL, 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 06:09:44', NULL, 0, NULL, NULL, NULL, 1),
(27, NULL, 'DYN006', 'Test Hoàn thành công', '0900000005', NULL, '123 Test Street, District 6', 400000.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":1,\"price\":100000,\"id\":1,\"sku\":\"N/A\",\"regular_price\":100000,\"sale_price\":100000,\"line_total\":100000},{\"id\":202,\"name\":\"Sản phẩm bổ sung B\",\"price\":300000,\"sku\":\"ADD-B\",\"regular_price\":300000,\"sale_price\":300000,\"qty\":1,\"line_total\":300000}]', NULL, 'dong-goi-sai', 1, NULL, '2025-10-01 09:56:18', 1, '2025-10-01 09:57:10', '0000-00-00 00:00:00', 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 09:57:37', NULL, 0, NULL, NULL, NULL, 1),
(28, NULL, 'DYN007', 'Trang', '0962864599', 'raintl07@gmail.com', '55 Ngô Kim Tài, Kênh Dương, Hải Phòng', 500000.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":3,\"price\":100000,\"sku\":\"N/A\",\"regular_price\":100000,\"sale_price\":100000,\"attributes\":[],\"line_total\":300000},{\"name\":\"Product Test\",\"qty\":2,\"price\":100000,\"sku\":\"N/A\",\"regular_price\":100000,\"sale_price\":100000,\"attributes\":[],\"line_total\":200000}]', NULL, 'n-a', 1, NULL, '2025-10-01 07:59:00', 2, '2025-10-01 08:02:35', '0000-00-00 00:00:00', 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 09:22:37', NULL, 0, NULL, NULL, NULL, 1),
(29, NULL, 'DYN008', 'Test Đơn mới', '0900000007', NULL, '123 Test Street, District 8', 1925618.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":1,\"price\":100000}]', NULL, 'n-a', NULL, NULL, NULL, 0, NULL, NULL, 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 06:09:44', NULL, 0, NULL, NULL, NULL, 1),
(30, NULL, 'DYN009', 'Test Đang gọi', '0900000008', NULL, '123 Test Street, District 9', 1795834.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":1,\"price\":100000}]', NULL, 'n-a', NULL, NULL, NULL, 0, NULL, NULL, 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 09:22:37', NULL, 0, NULL, NULL, NULL, 1),
(31, NULL, 'DYN010', 'Test Hẹn gọi lại', '0900000009', NULL, '123 Test Street, District 10', 822067.00, 'VND', NULL, '[{\"name\":\"Product Test\",\"qty\":1,\"price\":100000}]', NULL, 'n-a', NULL, NULL, NULL, 0, NULL, NULL, 'woocommerce', NULL, NULL, NULL, NULL, NULL, '2025-10-01 06:09:44', '2025-10-01 09:22:37', NULL, 0, NULL, NULL, NULL, 1);

--
-- Bẫy `orders`
--
DELIMITER $$
CREATE TRIGGER `tr_order_status_change` AFTER UPDATE ON `orders` FOR EACH ROW BEGIN
    DECLARE old_label VARCHAR(100);
    DECLARE new_label VARCHAR(100);
    
    IF OLD.status != NEW.status THEN
        -- Lấy label từ order_status_configs
        SELECT label INTO old_label FROM order_status_configs WHERE status_key = OLD.status LIMIT 1;
        SELECT label INTO new_label FROM order_status_configs WHERE status_key = NEW.status LIMIT 1;
        
        -- Fallback to status_key nếu không tìm thấy
        SET old_label = IFNULL(old_label, OLD.status);
        SET new_label = IFNULL(new_label, NEW.status);
        
        INSERT INTO order_notes (order_id, user_id, note_type, content)
        VALUES (
            NEW.id,
            NEW.assigned_to,
            'status',
            CONCAT('Trạng thái đổi từ "', old_label, '" sang "', new_label, '"')
        );
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `validate_status_insert` BEFORE INSERT ON `orders` FOR EACH ROW BEGIN
    DECLARE status_exists INT;
    DECLARE default_status VARCHAR(50);
    
    -- Check if status exists
    SELECT COUNT(*) INTO status_exists 
    FROM order_status_configs 
    WHERE status_key = NEW.status;
    
    -- If not exists, use first status from configs
    IF status_exists = 0 THEN
        SELECT status_key INTO default_status
        FROM order_status_configs 
        ORDER BY sort_order ASC 
        LIMIT 1;
        
        SET NEW.status = IFNULL(default_status, 'n-a');
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `validate_status_update` BEFORE UPDATE ON `orders` FOR EACH ROW BEGIN
    DECLARE status_exists INT;
    
    SELECT COUNT(*) INTO status_exists 
    FROM order_status_configs 
    WHERE status_key = NEW.status;
    
    -- Keep old status if new one is invalid
    IF status_exists = 0 THEN
        SET NEW.status = OLD.status;
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
  `created_at` datetime DEFAULT current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng ghi chú đơn hàng';

--
-- Đang đổ dữ liệu cho bảng `order_notes`
--

INSERT INTO `order_notes` (`id`, `order_id`, `user_id`, `note_type`, `content`, `created_at`, `deleted_at`) VALUES
(1, 22, 1, '', 'Cuộc gọi 00:02:02 - gọi thử', '2025-10-01 07:14:40', NULL),
(2, 22, 1, 'system', 'Nhận đơn hàng', '2025-10-01 07:54:42', NULL),
(3, 22, 1, 'system', 'Cập nhật thông tin khách hàng', '2025-10-01 07:57:36', NULL),
(4, 22, 1, '', 'gọi thử', '2025-10-01 07:57:59', NULL),
(5, 22, 1, 'status', 'Trạng thái đổi từ \"Bom hàng\" sang \"Chờ giao\"', '2025-10-01 07:58:15', NULL),
(6, 22, 1, 'status', 'Cập nhật trạng thái: shipping', '2025-10-01 07:58:15', NULL),
(7, 28, 1, 'system', 'Nhận đơn hàng', '2025-10-01 07:59:00', NULL),
(8, 28, 1, '', 'mua thử', '2025-10-01 08:02:05', NULL),
(9, 28, 1, 'system', 'Cập nhật thông tin khách hàng', '2025-10-01 08:02:16', NULL),
(10, 28, 1, '', 'cập nhật', '2025-10-01 08:02:35', NULL),
(11, 28, 1, 'status', 'Trạng thái đổi từ \"Không nghe\" sang \"Chờ giao\"', '2025-10-01 08:02:39', NULL),
(12, 28, 1, 'status', 'Cập nhật trạng thái: shipping', '2025-10-01 08:02:39', NULL),
(13, 22, 1, 'status', 'Trạng thái đổi từ \"shipping\" sang \"Đơn mới\"', '2025-10-01 08:21:05', NULL),
(14, 28, 1, 'status', 'Trạng thái đổi từ \"shipping\" sang \"Đơn mới\"', '2025-10-01 08:21:05', NULL),
(15, 30, NULL, 'status', 'Trạng thái đổi từ \"n-a-1759223929\" sang \"Đơn mới\"', '2025-10-01 08:21:05', NULL),
(16, 31, NULL, 'status', 'Trạng thái đổi từ \"n-a-1759224057\" sang \"Đơn mới\"', '2025-10-01 08:21:05', NULL),
(17, 22, 1, 'status', 'Trạng thái đổi từ \"Đơn mới\" sang \"Đơn mới\"', '2025-10-01 09:22:37', NULL),
(18, 28, 1, 'status', 'Trạng thái đổi từ \"Đơn mới\" sang \"Đơn mới\"', '2025-10-01 09:22:37', NULL),
(19, 30, NULL, 'status', 'Trạng thái đổi từ \"Đơn mới\" sang \"Đơn mới\"', '2025-10-01 09:22:37', NULL),
(20, 31, NULL, 'status', 'Trạng thái đổi từ \"Đơn mới\" sang \"Đơn mới\"', '2025-10-01 09:22:37', NULL),
(21, 27, 1, 'system', 'Nhận đơn hàng', '2025-10-01 09:56:18', NULL),
(22, 27, 1, 'status', 'Trạng thái đổi từ \"Hoàn thành công\" sang \"Đóng gói sai\"', '2025-10-01 09:56:24', NULL),
(23, 27, 1, '', 'test', '2025-10-01 09:57:10', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `order_status_configs`
--

CREATE TABLE `order_status_configs` (
  `status_key` varchar(50) NOT NULL,
  `label` varchar(100) NOT NULL,
  `color` varchar(20) NOT NULL,
  `icon` varchar(50) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `logic_json` text NOT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_system` tinyint(1) DEFAULT 0 COMMENT 'Status hệ thống không được xóa'
) ;

--
-- Đang đổ dữ liệu cho bảng `order_status_configs`
--

INSERT INTO `order_status_configs` (`status_key`, `label`, `color`, `icon`, `sort_order`, `logic_json`, `created_by`, `created_at`, `updated_at`, `is_system`) VALUES
('assigned', '[HỆ THỐNG] Đã gán', '#17a2b8', 'fa-user-check', -1, '', NULL, '2025-10-01 13:55:12', '2025-10-01 13:55:12', 1),
('bom-hang', 'Bom hàng', '#dc3545', 'fa-bomb', 12, '', NULL, '2025-09-30 16:34:13', '2025-10-01 08:21:05', 0),
('dang-giao', 'Đang giao', '#639419', 'fa-tag', 11, '', NULL, '2025-09-30 16:28:29', '2025-09-30 16:28:29', 0),
('dang-hoan', 'Đang hoàn', '#8c460d', 'fa-tag', 16, '', NULL, '2025-09-30 16:34:44', '2025-09-30 16:34:44', 0),
('dong-goi-sai', 'Đóng gói sai', '#7c3131', 'fa-tag', 12, '', NULL, '2025-09-30 16:30:16', '2025-09-30 16:30:16', 0),
('free', '[HỆ THỐNG] Chưa gán', '#6c757d', 'fa-inbox', -1, '', NULL, '2025-10-01 13:55:12', '2025-10-01 13:55:12', 1),
('giao-thanh-cong', 'Giao thành công', '#00d604', 'fa-tag', 20, '', NULL, '2025-09-30 16:37:32', '2025-09-30 16:37:32', 0),
('hoan-thanh-cong', 'Hoàn thành công', '#1a1a1a', 'fa-tag', 17, '', NULL, '2025-09-30 16:35:25', '2025-09-30 16:35:25', 0),
('khong-nghe', 'Không nghe', '#dfc834', 'fa-tag', 3, '', NULL, '2025-09-30 16:23:37', '2025-09-30 16:23:37', 0),
('n-a', 'Đơn mới', '#08f7cf', 'fa-tag', 1, '', NULL, '2025-09-30 16:18:10', '2025-09-30 16:18:10', 0);

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
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `window_start` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `suspension_until` datetime DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng người dùng';

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `status`, `avatar`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`, `suspension_reason`, `suspension_until`, `deleted_at`) VALUES
(1, 'admin', '$2y$10$5EzgChCuFM.LG/yVCMbuseZFP2fxECDOQJb8FzEmssX4iev/sjbVi', 'Administrator', 'admin@example.com', NULL, 'admin', 'active', NULL, '2025-10-01 13:25:32', '::1', '2025-09-30 09:36:19', '2025-10-01 13:25:32', NULL, NULL, NULL),
(2, 'telesale1', '$2y$10$lkpRcTFFgJVlNIawkjprY.n7mubXpkH1/Sa0TOf4pl7rZQw6DVuqa', 'Nguyễn Văn Ad', 'vh6889@gmail.com', '0963470944', 'telesale', 'active', NULL, '2025-10-01 13:46:40', '::1', '2025-09-30 09:36:19', '2025-10-01 13:46:40', NULL, NULL, NULL),
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

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_call_statistics`
-- (See below for the actual view)
--
CREATE TABLE `v_call_statistics` (
`user_id` int(10) unsigned
,`full_name` varchar(100)
,`username` varchar(50)
,`total_calls` bigint(21)
,`unique_orders` bigint(21)
,`avg_duration` decimal(14,4)
,`total_duration` decimal(32,0)
,`completed_calls` bigint(21)
,`dropped_calls` bigint(21)
,`call_date` date
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_latest_calls`
-- (See below for the actual view)
--
CREATE TABLE `v_latest_calls` (
`id` int(10) unsigned
,`order_id` int(10) unsigned
,`user_id` int(10) unsigned
,`user_name` varchar(100)
,`start_time` datetime
,`end_time` datetime
,`duration` int(10) unsigned
,`note` text
,`status` enum('active','completed','dropped')
,`recording_url` varchar(255)
,`customer_feedback` tinyint(1)
,`created_at` datetime
,`order_number` varchar(50)
,`customer_name` varchar(100)
,`customer_phone` varchar(20)
,`order_status` varchar(50)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_orders_with_status`
-- (See below for the actual view)
--
CREATE TABLE `v_orders_with_status` (
`id` int(10) unsigned
,`woo_order_id` int(10) unsigned
,`order_number` varchar(50)
,`customer_name` varchar(100)
,`customer_phone` varchar(20)
,`customer_email` varchar(100)
,`customer_address` text
,`total_amount` decimal(15,2)
,`currency` varchar(10)
,`payment_method` varchar(50)
,`products` longtext
,`customer_notes` text
,`status` varchar(50)
,`assigned_to` int(10) unsigned
,`manager_id` int(10) unsigned
,`assigned_at` datetime
,`call_count` int(10) unsigned
,`last_call_at` datetime
,`callback_time` datetime
,`source` enum('woocommerce','manual')
,`created_by` int(10) unsigned
,`approval_status` varchar(50)
,`approved_by` int(10) unsigned
,`approved_at` datetime
,`woo_created_at` datetime
,`created_at` datetime
,`updated_at` datetime
,`completed_at` datetime
,`status_label` varchar(100)
,`status_color` varchar(20)
,`status_icon` varchar(50)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_order_status_summary`
-- (See below for the actual view)
--
CREATE TABLE `v_order_status_summary` (
`status_key` varchar(50)
,`label` varchar(100)
,`color` varchar(20)
,`icon` varchar(50)
,`sort_order` int(11)
,`total_orders` bigint(21)
);

-- --------------------------------------------------------

--
-- Cấu trúc đóng vai cho view `v_status_options`
-- (See below for the actual view)
--
CREATE TABLE `v_status_options` (
`value` varchar(50)
,`text` varchar(100)
,`color` varchar(20)
,`icon` varchar(50)
,`sort_order` int(11)
);

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_call_statistics`
--
DROP TABLE IF EXISTS `v_call_statistics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_call_statistics`  AS SELECT `cl`.`user_id` AS `user_id`, `u`.`full_name` AS `full_name`, `u`.`username` AS `username`, count(`cl`.`id`) AS `total_calls`, count(distinct `cl`.`order_id`) AS `unique_orders`, avg(`cl`.`duration`) AS `avg_duration`, sum(`cl`.`duration`) AS `total_duration`, count(case when `cl`.`status` = 'completed' then 1 end) AS `completed_calls`, count(case when `cl`.`status` = 'dropped' then 1 end) AS `dropped_calls`, cast(`cl`.`start_time` as date) AS `call_date` FROM (`call_logs` `cl` left join `users` `u` on(`cl`.`user_id` = `u`.`id`)) WHERE `cl`.`end_time` is not null GROUP BY `cl`.`user_id`, cast(`cl`.`start_time` as date) ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_latest_calls`
--
DROP TABLE IF EXISTS `v_latest_calls`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_latest_calls`  AS SELECT `cl1`.`id` AS `id`, `cl1`.`order_id` AS `order_id`, `cl1`.`user_id` AS `user_id`, `cl1`.`user_name` AS `user_name`, `cl1`.`start_time` AS `start_time`, `cl1`.`end_time` AS `end_time`, `cl1`.`duration` AS `duration`, `cl1`.`note` AS `note`, `cl1`.`status` AS `status`, `cl1`.`recording_url` AS `recording_url`, `cl1`.`customer_feedback` AS `customer_feedback`, `cl1`.`created_at` AS `created_at`, `o`.`order_number` AS `order_number`, `o`.`customer_name` AS `customer_name`, `o`.`customer_phone` AS `customer_phone`, `o`.`status` AS `order_status` FROM ((`call_logs` `cl1` join (select `call_logs`.`order_id` AS `order_id`,max(`call_logs`.`start_time`) AS `max_start` from `call_logs` group by `call_logs`.`order_id`) `cl2` on(`cl1`.`order_id` = `cl2`.`order_id` and `cl1`.`start_time` = `cl2`.`max_start`)) left join `orders` `o` on(`cl1`.`order_id` = `o`.`id`)) ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_orders_with_status`
--
DROP TABLE IF EXISTS `v_orders_with_status`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_orders_with_status`  AS SELECT `o`.`id` AS `id`, `o`.`woo_order_id` AS `woo_order_id`, `o`.`order_number` AS `order_number`, `o`.`customer_name` AS `customer_name`, `o`.`customer_phone` AS `customer_phone`, `o`.`customer_email` AS `customer_email`, `o`.`customer_address` AS `customer_address`, `o`.`total_amount` AS `total_amount`, `o`.`currency` AS `currency`, `o`.`payment_method` AS `payment_method`, `o`.`products` AS `products`, `o`.`customer_notes` AS `customer_notes`, `o`.`status` AS `status`, `o`.`assigned_to` AS `assigned_to`, `o`.`manager_id` AS `manager_id`, `o`.`assigned_at` AS `assigned_at`, `o`.`call_count` AS `call_count`, `o`.`last_call_at` AS `last_call_at`, `o`.`callback_time` AS `callback_time`, `o`.`source` AS `source`, `o`.`created_by` AS `created_by`, `o`.`approval_status` AS `approval_status`, `o`.`approved_by` AS `approved_by`, `o`.`approved_at` AS `approved_at`, `o`.`woo_created_at` AS `woo_created_at`, `o`.`created_at` AS `created_at`, `o`.`updated_at` AS `updated_at`, `o`.`completed_at` AS `completed_at`, coalesce(`osc`.`label`,`o`.`status`) AS `status_label`, coalesce(`osc`.`color`,'#6c757d') AS `status_color`, coalesce(`osc`.`icon`,'fa-tag') AS `status_icon` FROM (`orders` `o` left join `order_status_configs` `osc` on(`o`.`status` = `osc`.`status_key`)) ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_order_status_summary`
--
DROP TABLE IF EXISTS `v_order_status_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_order_status_summary`  AS SELECT `osc`.`status_key` AS `status_key`, `osc`.`label` AS `label`, `osc`.`color` AS `color`, `osc`.`icon` AS `icon`, `osc`.`sort_order` AS `sort_order`, count(`o`.`id`) AS `total_orders` FROM (`order_status_configs` `osc` left join `orders` `o` on(`o`.`status` = `osc`.`status_key`)) GROUP BY `osc`.`status_key`, `osc`.`label`, `osc`.`color`, `osc`.`icon`, `osc`.`sort_order` ORDER BY `osc`.`sort_order` ASC ;

-- --------------------------------------------------------

--
-- Cấu trúc cho view `v_status_options`
--
DROP TABLE IF EXISTS `v_status_options`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_status_options`  AS SELECT `order_status_configs`.`status_key` AS `value`, `order_status_configs`.`label` AS `text`, `order_status_configs`.`color` AS `color`, `order_status_configs`.`icon` AS `icon`, `order_status_configs`.`sort_order` AS `sort_order` FROM `order_status_configs` ORDER BY `order_status_configs`.`sort_order` ASC ;

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
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_activity_user` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Chỉ mục cho bảng `audit_trail`
--
ALTER TABLE `audit_trail`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_table_record` (`table_name`,`record_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_created` (`created_at`);

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
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_orders_status` (`status`),
  ADD KEY `idx_orders_assigned_to` (`assigned_to`),
  ADD KEY `idx_orders_created_at` (`created_at`),
  ADD KEY `idx_orders_assigned` (`assigned_to`),
  ADD KEY `idx_orders_created` (`created_at`),
  ADD KEY `idx_orders_phone` (`customer_phone`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_approval_status` (`approval_status`),
  ADD KEY `idx_source` (`source`);

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
-- Chỉ mục cho bảng `order_status_configs`
--
ALTER TABLE `order_status_configs`
  ADD PRIMARY KEY (`status_key`);

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
  ADD KEY `idx_user_action` (`user_id`,`action`),
  ADD KEY `idx_window` (`window_start`),
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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT cho bảng `audit_trail`
--
ALTER TABLE `audit_trail`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `call_logs`
--
ALTER TABLE `call_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

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
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT cho bảng `order_notes`
--
ALTER TABLE `order_notes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT cho bảng `password_history`
--
ALTER TABLE `password_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
