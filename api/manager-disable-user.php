<?php
/**
 * API: Manager Disable User (cannot enable, only admin can)
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('manager-disable-user', get_logged_user()['id']);

$input = get_json_input(["user_id","action"]);
$user_id = (int)$input['user_id'];
$action = $input['action'] ?? '';

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

$current_user = get_logged_user();

// Must be admin or manager
if (!is_admin() && !is_manager()) {
    json_error('Unauthorized - Admin or Manager only', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($input['user_id'] ?? 0);
$action = $input['action'] ?? 'disable';

if (!$user_id) {
    json_error('User ID không hợp lệ');
}

// Manager can only disable, not enable
if (is_manager() && $action === 'enable') {
    json_error('Manager không có quyền kích hoạt lại tài khoản. Chỉ Admin mới có quyền này.', 403);
}

// Check if manager can manage this user
if (is_manager() && !can_manage_user($user_id)) {
    json_error('Bạn không có quyền quản lý nhân viên này', 403);
}

$target_user = get_user($user_id);
if (!$target_user) {
    json_error('User not found', 404);
}

// Prevent disabling yourself
if ($user_id === $current_user['id']) {
    json_error('Bạn không thể vô hiệu hóa chính mình', 400);
}

try {
    if ($action === 'disable') {
        // Handle handover for pending orders
        $pending_orders = db_get_results(
            "SELECT id FROM orders 
             WHERE assigned_to = ? AND status NOT IN (SELECT label_key AS status_key, FROM order_labels WHERE label LIKE '%mới%' OR label LIKE '%hoàn%' OR label LIKE '%hủy%')",
            [$user_id]
        );
        
        if (!empty($pending_orders)) {
            // If manager is disabling, orders go to the manager
            if (is_manager()) {
                $order_ids = array_column($pending_orders, 'id');
                $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                
                db_query(
                    "UPDATE orders SET assigned_to = ?, manager_id = ? WHERE id IN ({$placeholders})",
                    array_merge([$current_user['id'], $current_user['id']], $order_ids)
                );
                
                log_activity('manager_takeover', 'Manager took over ' . count($order_ids) . ' orders from disabled user #' . $user_id);
            } else {
                // Admin disabling - return to pool
                $order_ids = array_column($pending_orders, 'id');
                $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
                
                db_query(
                    "UPDATE orders SET assigned_to = NULL, manager_id = NULL, status = " . get_new_status_key() . " WHERE id IN ({$placeholders})",
                    $order_ids
                );
            }
        }
        
        // Disable user
        db_update('users', ['status' => 'inactive'], 'id = ?', [$user_id]);
        
        log_activity('disable_user', "User {$target_user['username']} disabled by {$current_user['role']} {$current_user['username']}", 'user', $user_id);
        
        json_success('Đã vô hiệu hóa tài khoản thành công');
        
    } else if ($action === 'enable' && is_admin()) {
        // Only admin can enable
        db_update('users', ['status' => 'active'], 'id = ?', [$user_id]);
        
        log_activity('enable_user', "User {$target_user['username']} enabled by admin", 'user', $user_id);
        
        json_success('Đã kích hoạt tài khoản thành công');
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_error('Error: ' . $e->getMessage(), 500);
}
    }
    
} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}