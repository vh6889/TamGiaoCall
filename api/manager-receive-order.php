<?php
/**
 * API: Manager Receive Handover Order
 * Allows a manager to receive orders from telesales handover
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

if (!is_manager()) {
    json_error('Unauthorized - Manager only', 403);
}

check_rate_limit('manager-receive-order', get_logged_user()['id']);

$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

if (!$order_id) {
    json_error('Invalid order ID');
}

$order = get_order($order_id);
if (!$order) {
    json_error('Order not found', 404);
}

$current_user = get_logged_user();

// Check if manager can receive this order
if (!can_receive_handover($order_id)) {
    json_error('Bạn không có quyền nhận đơn hàng này', 403);
}

try {
    begin_transaction();
    
    // Get previous owner info
    $previous_owner = $order['assigned_to'] 
        ? get_user($order['assigned_to'])['full_name'] 
        : 'hệ thống';
    
    // Update order: assign to manager
    db_update('orders', [
        'manager_id' => $current_user['id'],
        'assigned_to' => $current_user['id'],
        'system_status' => 'assigned',  // ✅ SỬA: đổi từ 'status'
        'assigned_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);
    
    // Add note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'assignment',
        'content' => "Manager {$current_user['full_name']} đã nhận bàn giao đơn hàng từ {$previous_owner}"
    ]);
    
    log_activity(
        'manager_receive_order', 
        "Manager received order #{$order['order_number']}", 
        'order', 
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã nhận đơn hàng thành công!');
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[MANAGER_RECEIVE] Error: ' . $e->getMessage());
    json_error('Database error: ' . $e->getMessage(), 500);
}