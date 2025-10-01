<?php
/**
 * API: Start Call
 * Bắt đầu cuộc gọi cho đơn hàng
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
check_rate_limit('start-call', get_logged_user()['id']);

// Get input
$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

if (!$order_id || $order_id <= 0) {
    json_error('Invalid order ID', 400);
}

$user = get_logged_user();

try {
    // Get order
    $order = get_order($order_id);
    
    if (!$order) {
        json_error('Không tìm thấy đơn hàng', 404);
    }
    
    // Check permission - must be assigned to current user
    if ($order['assigned_to'] != $user['id']) {
        json_error('Bạn không có quyền gọi đơn này', 403);
    }
    
    // Check if there's already an active call
    $active_call = db_get_row(
        "SELECT * FROM call_logs 
         WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
        [$order_id, $user['id']]
    );
    
    if ($active_call) {
        json_error('Cuộc gọi đang hoạt động. Vui lòng kết thúc trước khi bắt đầu cuộc mới.', 400);
    }
    
    // Begin transaction
    begin_transaction();
    
    // Get "calling" status dynamically
    $calling_status = db_get_var(
        "SELECT status_key FROM order_status_configs 
         WHERE label LIKE '%gọi%' OR label LIKE '%calling%' OR label LIKE '%đang gọi%'
         ORDER BY sort_order 
         LIMIT 1"
    );
    
    // Fallback
    if (!$calling_status) {
        $calling_status = 'calling';
    }
    
    // Create call log
    $call_id = db_insert('call_logs', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'user_name' => $user['full_name'],
        'start_time' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ]);
    
    // Update order status
    db_update('orders', [
        'status' => $calling_status,
        'last_call_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);
    
    // Add system note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'system',
        'content' => 'Bắt đầu cuộc gọi'
    ]);
    
    // Log activity
    log_activity('start_call', "Started call for order #{$order['order_number']}", 'order', $order_id);
    
    // Commit transaction
    commit_transaction();
    
    json_success('Đã bắt đầu cuộc gọi!', ['call_id' => $call_id]);
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    error_log('[START_CALL] Error: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}