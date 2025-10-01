<?php
/**
 * API: Transfer Order - Chuyển giao đơn hàng
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../functions.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

// Chỉ admin và manager được chuyển giao
if (!is_admin() && !is_manager()) {
    json_error('Chỉ Admin hoặc Manager mới có quyền chuyển giao', 403);
}

// Lấy input
$input = get_json_input(['order_id', 'target_user_id']);
$order_id = (int)($input['order_id'] ?? 0);
$target_user_id = (int)($input['target_user_id'] ?? 0);

if (!$order_id || !$target_user_id) {
    json_error('Vui lòng chọn đơn hàng và người nhận');
}

try {
    begin_transaction();
    
    // 1. Lấy đơn hàng
    $order = db_get_row(
        "SELECT * FROM orders WHERE id = ? FOR UPDATE",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    if ($order['is_locked']) {
        throw new Exception('Đơn hàng đã bị khóa');
    }
    
    // 2. Kiểm tra user mới
    $target_user = db_get_row(
        "SELECT * FROM users WHERE id = ? AND status = 'active'",
        [$target_user_id]
    );
    
    if (!$target_user) {
        throw new Exception('Nhân viên không hợp lệ');
    }
    
    if (!in_array($target_user['role'], ['telesale', 'manager'])) {
        throw new Exception('Chỉ có thể chuyển cho Telesale hoặc Manager');
    }
    
    // Lấy thông tin người cũ
    $old_user_name = 'Chưa phân công';
    if ($order['assigned_to']) {
        $old_user = db_get_row(
            "SELECT full_name FROM users WHERE id = ?",
            [$order['assigned_to']]
        );
        $old_user_name = $old_user ? $old_user['full_name'] : 'Unknown';
    }
    
    // 3. Update đơn - GIỮ NGUYÊN core_status và primary_label
    db_update('orders', [
        'assigned_to' => $target_user_id,
        'assigned_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);
    
    // 4. Ghi log
    $current_user = get_logged_user();
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'assignment',
        'content' => "Chuyển giao từ {$old_user_name} cho {$target_user['full_name']}"
    ]);
    
    log_activity(
        'transfer_order',
        "Transferred order #{$order['order_number']} to {$target_user['username']}",
        'order',
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã chuyển giao đơn hàng thành công!', [
        'from' => $old_user_name,
        'to' => $target_user['full_name']
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}
?>