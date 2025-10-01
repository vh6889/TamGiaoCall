<?php
/**
 * API: Reclaim Order
 * ✅ PATCHED: Hardcode 'lbl_new_order'
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

if (!is_admin()) {
    json_error('Admin only', 403);
}

check_rate_limit('reclaim-order', get_logged_user()['id']);

$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

if (!$order_id || $order_id <= 0) {
    json_error('Invalid order ID', 400);
}

$order = get_order($order_id);
if (!$order) {
    json_error('Không tìm thấy đơn hàng', 404);
}

try {
    begin_transaction();
    
    $assigned_user_name = 'N/A';
    if ($order['assigned_to']) {
        $assigned_user = get_user($order['assigned_to']);
        $assigned_user_name = $assigned_user ? $assigned_user['full_name'] : 'Unknown';
    }
    
    // ✅ PATCH: Hardcode 'lbl_new_order'
    db_update('orders', [
        'system_status' => 'free',
        'assigned_to' => NULL,
        'assigned_at' => NULL,
        'primary_label' => 'lbl_new_order'
    ], 'id = ?', [$order_id]);
    
    $current_user = get_logged_user();
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'system',
        'content' => "Admin {$current_user['full_name']} đã thu hồi đơn hàng về kho chung" . 
                     ($order['assigned_to'] ? " từ {$assigned_user_name}" : "")
    ]);
    
    db_update('reminders', 
        ['status' => 'cancelled'],
        'order_id = ? AND status = ?',
        [$order_id, 'pending']
    );
    
    log_activity(
        'reclaim_order', 
        "Reclaimed order #{$order['order_number']} to common pool", 
        'order', 
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã thu hồi đơn hàng về kho chung thành công');
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[RECLAIM_ORDER] Error: ' . $e->getMessage());
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}
