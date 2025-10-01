<?php
/**
 * API: Claim Order
 * Nhận đơn hàng vào xử lý
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

// Rate limiting
check_rate_limit('claim-order', get_logged_user()['id']);

// Get input
$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

if (!$order_id || $order_id <= 0) {
    json_error('Invalid order ID', 400);
}

$user = get_logged_user();

try {
    // Begin transaction
    begin_transaction();
    
    // Lock row for update to prevent race condition
    $order = db_get_row("SELECT * FROM orders WHERE id = ? FOR UPDATE", [$order_id]);
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // Check if already assigned
    if ($order['assigned_to']) {
        throw new Exception('Đơn hàng đã được nhận bởi người khác');
    }
    
    // Update order - only assign to user, keep current status
    // User sẽ tự chọn status sau khi xử lý xong
    db_update('orders', [
        'assigned_to' => $user['id'],
        'assigned_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);
    
    // Add system note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'system',
        'content' => "{$user['full_name']} đã nhận đơn hàng"
    ]);
    
    // Log activity
    log_activity('claim_order', "Claimed order #{$order['order_number']}", 'order', $order_id);
    
    // Commit transaction
    commit_transaction();
    
    json_success('Đã nhận đơn hàng thành công!');
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    error_log('[CLAIM_ORDER] Error: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}
