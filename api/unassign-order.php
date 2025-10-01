<?php
/**
 * API: Unassign Order (Force Unassign)
 * Hủy phân công đơn hàng - đưa đơn về trạng thái free (kho chung)
 * Chỉ Admin mới có quyền thực hiện
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

// Authentication check
if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

// Authorization check - Only Admin
if (!is_admin()) {
    json_error('Admin only', 403);
}

// Rate limiting
check_rate_limit('unassign-order', get_logged_user()['id']);

// Get and validate input
$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

if (!$order_id || $order_id <= 0) {
    json_error('Invalid order ID', 400);
}

// Get order info
$order = get_order($order_id);
if (!$order) {
    json_error('Không tìm thấy đơn hàng', 404);
}

// Check if order is already unassigned
if (!$order['assigned_to']) {
    json_error('Đơn hàng chưa được phân công', 400);
}

try {
    // Begin transaction
    begin_transaction();
    
    // Get assigned user info for logging
    $assigned_user = get_user($order['assigned_to']);
    $assigned_user_name = $assigned_user ? $assigned_user['full_name'] : 'Unknown';
    
    // Update order to free status
    db_update('orders', [
        'assigned_to' => NULL,
        'assigned_at' => NULL,
        'status' => get_free_status_key()
    ], 'id = ?', [$order_id]);
    
    // Add system note
    $current_user = get_logged_user();
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'system',
        'content' => "Admin {$current_user['full_name']} đã hủy phân công từ {$assigned_user_name}. Đơn hàng trở về kho chung."
    ]);
    
    // Cancel any pending reminders for this order
    db_update('reminders', 
        ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')],
        'order_id = ? AND status = ?',
        [$order_id, 'pending']
    );
    
    // Log activity
    log_activity(
        'unassign_order', 
        "Unassigned order #{$order['order_number']} from {$assigned_user_name}", 
        'order', 
        $order_id
    );
    
    // Commit transaction
    commit_transaction();
    
    json_success('Đã hủy phân công thành công. Đơn hàng đã trở về kho chung.');
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    // Log error
    error_log('[UNASSIGN_ORDER] Error: ' . $e->getMessage());
    
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}
