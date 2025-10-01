<?php
/**
 * API: Transfer Order
 * Chuyển giao đơn hàng cho nhân viên khác
 * Chỉ Admin và Manager có quyền
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';

header('Content-Type: application/json');

// CSRF Protection
require_csrf();

// Authentication
if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

// Authorization - Admin or Manager only
if (!is_admin() && !is_manager()) {
    json_error('Admin or Manager only', 403);
}

// Rate limiting
check_rate_limit('transfer-order', get_logged_user()['id']);

// Get input
$input = get_json_input(["order_id", "target_user_id"]);
$order_id = (int)$input['order_id'];
$target_user_id = (int)$input['target_user_id'];

if (!$order_id || !$target_user_id) {
    json_error('Dữ liệu không hợp lệ', 400);
}

$current_user = get_logged_user();

try {
    // Begin transaction
    begin_transaction();
    
    // ✅ FIX: Validate order
    $order = get_order($order_id);
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    if ($order['is_locked']) {
        throw new Exception('Đơn hàng đã bị khóa, không thể chuyển giao');
    }
    
    // ✅ FIX: Chỉ transfer được đơn đã assigned
    if ($order['system_status'] === 'free') {
        throw new Exception('Không thể chuyển giao đơn chưa được gán. Vui lòng dùng chức năng "Phân công".');
    }
    
    // ✅ FIX: Validate target user
    $target_user = get_user($target_user_id);
    if (!$target_user || $target_user['status'] !== 'active') {
        throw new Exception('Nhân viên không hợp lệ hoặc không hoạt động');
    }
    
    if (!in_array($target_user['role'], ['telesale', 'manager'])) {
        throw new Exception('Chỉ có thể chuyển giao cho telesale hoặc manager');
    }
    
    // Check if same user
    if ($order['assigned_to'] == $target_user_id) {
        throw new Exception('Đơn hàng đã được gán cho nhân viên này rồi');
    }
    
    // ✅ FIX: Manager can only transfer within their team
    if (is_manager() && !is_admin()) {
        $can_transfer = can_manage_user($target_user_id);
        if (!$can_transfer) {
            throw new Exception('Bạn chỉ có thể chuyển giao cho nhân viên trong team của mình');
        }
    }
    
    // Get old assigned user for logging
    $old_user_name = 'hệ thống';
    if ($order['assigned_to']) {
        $old_user = get_user($order['assigned_to']);
        $old_user_name = $old_user ? $old_user['full_name'] : 'Unknown';
    }
    
    // ✅ FIX: Update order với đầy đủ validation
    db_update('orders', [
        'assigned_to' => $target_user_id,
        'assigned_at' => date('Y-m-d H:i:s'),
        'system_status' => 'assigned'  // Đảm bảo system_status = assigned
    ], 'id = ?', [$order_id]);
    
    // ✅ FIX: Add note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'assignment',
        'content' => "Chuyển giao từ {$old_user_name} cho {$target_user['full_name']} bởi {$current_user['full_name']}"
    ]);
    
    // ✅ FIX: Log activity
    log_activity(
        'transfer_order', 
        "Transferred order #{$order['order_number']} from {$old_user_name} to {$target_user['username']}", 
        'order', 
        $order_id
    );
    
    // Commit transaction
    commit_transaction();
    
    json_success('Đã chuyển giao đơn hàng thành công', [
        'order_id' => $order_id,
        'from_user' => $old_user_name,
        'to_user' => $target_user['full_name']
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    error_log('[TRANSFER_ORDER] Error: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}