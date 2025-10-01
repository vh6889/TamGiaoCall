-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th9 30, 2025 lúc 02:29 PM
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
(11, 1, 'create_user', 'Created new user: oigioioi', 'user', 4, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-30 18:50:59');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `customer_labels`
--

CREATE TABLE `customer_labels` (
  `label_key` varchar(50) NOT NULL,
  `label_name` varchar(100) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#cccccc',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `customer_labels`
--

INSERT INTO `customer_labels` (`label_key`, `label_name`, `color`, `description`) VALUES
('khach-bom-hang', 'Khách bom hàng', '#c21d00', 'Bom hàng'),
('khach-hang-rac', 'Khách hàng rác', '#626160', 'Không đặt gì hoặc thái độ khó chịu, không mua hàng'),
('khach-hang-than-quen', 'Khách hàng thân quen', '#31c12f', 'Khách đã mua hàng ít nhất 2 lần'),
('khach-hang-vip', 'Khách hàng VIP', '#d0bf01', 'Khách hàng mua hàng nhiều lần, giá trị lớn'),
('n-a', 'Khách hàng tiềm năng', '#6f899f', 'Khách hàng mới đặt hàng, chưa xác nhận'),
('n-a-1759225492', 'Khách gọi nhiều không nghe', '#978e20', 'Không từ chối nhưng gọi mãi không nghe'),
('n-a-1759225595', 'Khách hàng đã xác nhận', '#51c8f0', 'Khách hàng đã xác nhận mua hàng chờ gửi hàng'),
('n-a-1759225632', 'Khách hàng mới nhận', '#7ec80e', 'Khách mới nhận hàng lần đầu'),
('n-a-1759225700', 'Khách nhận một phần', '#28b87a', 'Khách hàng chỉ nhận một phần'),
('n-a-1759225776', 'Khách đã nhận 5 ngày', '#1b94ac', 'Khách đã nhận hàng 5 ngày cần chăm sóc lần 1');

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Configs cho trạng thái tùy chỉnh';

--
-- Đang đổ dữ liệu cho bảng `order_status_configs`
--

INSERT INTO `order_status_configs` (`status_key`, `label`, `color`, `icon`, `sort_order`, `logic_json`, `created_by`, `created_at`, `updated_at`) VALUES
('bom-hang', 'Bom hàng', '#ff0000', 'fa-tag', 15, '', NULL, '2025-09-30 16:34:13', '2025-09-30 16:34:13'),
('dang-giao', 'Đang giao', '#639419', 'fa-tag', 11, '', NULL, '2025-09-30 16:28:29', '2025-09-30 16:28:29'),
('dang-hoan', 'Đang hoàn', '#8c460d', 'fa-tag', 16, '', NULL, '2025-09-30 16:34:44', '2025-09-30 16:34:44'),
('dong-goi-sai', 'Đóng gói sai', '#7c3131', 'fa-tag', 12, '', NULL, '2025-09-30 16:30:16', '2025-09-30 16:30:16'),
('giao-thanh-cong', 'Giao thành công', '#00d604', 'fa-tag', 20, '', NULL, '2025-09-30 16:37:32', '2025-09-30 16:37:32'),
('hoan-thanh-cong', 'Hoàn thành công', '#1a1a1a', 'fa-tag', 17, '', NULL, '2025-09-30 16:35:25', '2025-09-30 16:35:25'),
('khong-nghe', 'Không nghe', '#dfc834', 'fa-tag', 3, '', NULL, '2025-09-30 16:23:37', '2025-09-30 16:23:37'),
('n-a', 'Đơn mới', '#08f7cf', 'fa-tag', 1, '', NULL, '2025-09-30 16:18:10', '2025-09-30 16:18:10'),
('n-a-1759223929', 'Đang gọi', '#2a88df', 'fa-tag', 2, '', NULL, '2025-09-30 16:18:49', '2025-09-30 16:18:49'),
('n-a-1759224057', 'Hẹn gọi lại', '#9e62a3', 'fa-tag', 4, '', NULL, '2025-09-30 16:20:57', '2025-09-30 16:20:57'),
('n-a-1759224134', 'Sai số', '#d10000', 'fa-tag', 5, '', NULL, '2025-09-30 16:22:14', '2025-09-30 16:22:14'),
('n-a-1759224173', 'Đơn rác', '#4c2a2a', 'fa-tag', 6, '', NULL, '2025-09-30 16:22:53', '2025-09-30 16:22:53'),
('n-a-1759224282', 'Từ chối', '#b30036', 'fa-tag', 7, '', NULL, '2025-09-30 16:24:42', '2025-09-30 16:24:42'),
('n-a-1759224317', 'Xác nhận', '#1d9f4b', 'fa-tag', 8, '', NULL, '2025-09-30 16:25:17', '2025-09-30 16:25:17'),
('n-a-1759224363', 'Chờ giao', '#e1ff00', 'fa-tag', 9, '', NULL, '2025-09-30 16:26:03', '2025-09-30 16:26:03'),
('n-a-1759224426', 'Hết hàng', '#4f2a74', 'fa-tag', 10, '', NULL, '2025-09-30 16:27:06', '2025-09-30 16:27:06'),
('n-a-1759224683', 'Gửi đơn mới', '#624771', 'fa-tag', 13, '', NULL, '2025-09-30 16:31:23', '2025-09-30 16:31:23'),
('n-a-1759224783', 'Giao một phần', '#15932a', 'fa-tag', 14, '', NULL, '2025-09-30 16:33:03', '2025-09-30 16:33:03'),
('n-a-1759224985', 'Đổi hàng', '#2a4635', 'fa-tag', 18, '', NULL, '2025-09-30 16:36:25', '2025-09-30 16:36:25'),
('n-a-1759225011', 'Trả hàng', '#7a0012', 'fa-tag', 19, '', NULL, '2025-09-30 16:36:51', '2025-09-30 16:36:51');

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
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bảng người dùng';

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `role`, `status`, `avatar`, `last_login_at`, `last_login_ip`, `created_at`, `updated_at`) VALUES
(1, 'admin', '$2y$10$5EzgChCuFM.LG/yVCMbuseZFP2fxECDOQJb8FzEmssX4iev/sjbVi', 'Administrator', 'admin@example.com', NULL, 'admin', 'active', NULL, '2025-09-30 11:06:54', '::1', '2025-09-30 09:36:19', '2025-09-30 11:06:54'),
(2, 'telesale1', '$2y$10$lkpRcTFFgJVlNIawkjprY.n7mubXpkH1/Sa0TOf4pl7rZQw6DVuqa', 'Nguyễn Văn Ad', 'vh6889@gmail.com', '0963470944', 'telesale', 'active', NULL, NULL, NULL, '2025-09-30 09:36:19', '2025-09-30 12:46:43'),
(3, 'telesale2', '$2y$10$lkpRcTFFgJVlNIawkjprY.n7mubXpkH1/Sa0TOf4pl7rZQw6DVuqa', 'Trần Thị Booo', 'telesale2@example.com', '', 'telesale', 'active', NULL, NULL, NULL, '2025-09-30 09:36:19', '2025-09-30 12:54:32'),
(4, 'oigioioi', '$2y$10$AxE8XaE9rkf9G7nvTyTFgu1xQNGMAKItLU/tkocwj2ZTJv/JmGppq', 'Hai Vu', 'raintl07@gmail.com', '0963470944', 'manager', 'active', NULL, NULL, NULL, '2025-09-30 18:50:59', '2025-09-30 18:50:59');

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
  `label_key` varchar(50) NOT NULL,
  `label_name` varchar(100) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#cccccc',
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `user_labels`
--

INSERT INTO `user_labels` (`label_key`, `label_name`, `color`, `description`) VALUES
('n-a', 'Nhân viên yếu kém', '#383838', 'Nhân viên có tỉ lệ chốt đơn dưới 50% trong tháng'),
('n-a-1759229091', 'Nhân viên gương mẫu', '#0b74d5', 'Nhân viên dưới 80%'),
('n-a-1759229123', 'Nhân viên xuất sắc', '#ffbb00', 'Nhân viên trên 90%'),
('n-a-1759229353', 'Nhân viên mới', '#04ff00', 'Nhân viên mới gia nhập'),
('nhan-vien-trung-binh', 'Nhân viên trung bình', '#9a972d', 'Nhân viên có tỉ lệ trung bình dưới 60%');

--
-- Chỉ mục cho các bảng đã đổ
--

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
  ADD PRIMARY KEY (`label_key`);

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
-- Chỉ mục cho bảng `order_status_configs`
--
ALTER TABLE `order_status_configs`
  ADD PRIMARY KEY (`status_key`);

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
-- Chỉ mục cho bảng `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

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
  ADD PRIMARY KEY (`label_key`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

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
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
