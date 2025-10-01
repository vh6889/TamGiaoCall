<?php
/**
 * API: Reclaim Order
 * Thu hồi đơn hàng về kho chung (trạng thái free)
 * Chỉ Admin mới có quyền
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

// Authorization - Admin only
if (!is_admin()) {
    json_error('Admin only', 403);
}

// Rate limiting
check_rate_limit('reclaim-order', get_logged_user()['id']);

// Get input
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

try {
    // Begin transaction
    begin_transaction();
    
    // Get current assigned user for logging
    $assigned_user_name = 'N/A';
    if ($order['assigned_to']) {
        $assigned_user = get_user($order['assigned_to']);
        $assigned_user_name = $assigned_user ? $assigned_user['full_name'] : 'Unknown';
    }
    
    // Update order to free status
    db_update('orders', [
        'assigned_to' => NULL,
        'assigned_at' => NULL,
        'system_status' => 'free',
		'primary_label' => get_new_status_key(),
		'assigned_to' => NULL,
		'assigned_at' => NULL
    ], 'id = ?', [$order_id]);
    
    // Add system note
    $current_user = get_logged_user();
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'system',
        'content' => "Admin {$current_user['full_name']} đã thu hồi đơn hàng về kho chung" . 
                     ($order['assigned_to'] ? " từ {$assigned_user_name}" : "")
    ]);
    
    // Cancel pending reminders
    db_update('reminders', 
        ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')],
        'order_id = ? AND status = ?',
        [$order_id, 'pending']
    );
    
    // Log activity
    log_activity(
        'reclaim_order', 
        "Reclaimed order #{$order['order_number']} to common pool", 
        'order', 
        $order_id
    );
    
    // Commit transaction
    commit_transaction();
    
    json_success('Đã thu hồi đơn hàng về kho chung thành công');
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    error_log('[RECLAIM_ORDER] Error: ' . $e->getMessage());
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}
