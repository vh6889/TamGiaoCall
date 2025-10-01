<?php
/**
 * API: End Call
 * Kết thúc cuộc gọi
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
check_rate_limit('end-call', get_logged_user()['id']);

// Get input
$input = get_json_input(["order_id", "notes"]);
$order_id = (int)$input['order_id'];
$notes = trim($input['notes'] ?? '');
$callback_time = $input['callback_time'] ?? null;

if (!$order_id || $order_id <= 0) {
    json_error('Invalid order ID', 400);
}

if (empty($notes)) {
    json_error('Vui lòng nhập ghi chú cuộc gọi', 400);
}

$user = get_logged_user();

try {
    // Get order
    $order = get_order($order_id);
    
    if (!$order) {
        json_error('Không tìm thấy đơn hàng', 404);
    }
    
    // Check permission
    if ($order['assigned_to'] != $user['id'] && !is_admin()) {
        json_error('Bạn không có quyền kết thúc cuộc gọi này', 403);
    }
    
    // Find active call
    $call = db_get_row(
        "SELECT * FROM call_logs 
         WHERE order_id = ? AND user_id = ? AND end_time IS NULL
         ORDER BY start_time DESC
         LIMIT 1",
        [$order_id, $user['id']]
    );
    
    if (!$call) {
        json_error('Không tìm thấy cuộc gọi đang hoạt động', 404);
    }
    
    // Begin transaction
    begin_transaction();
    
    // End call log
    db_update('call_logs', [
        'end_time' => date('Y-m-d H:i:s'),
        'note' => sanitize($notes),
        'status' => 'completed'
    ], 'id = ?', [$call['id']]);
    
    // Update order call count
    $new_call_count = intval($order['call_count'] ?? 0) + 1;
    
    $update_data = [
        'call_count' => $new_call_count,
        'last_call_at' => date('Y-m-d H:i:s')
    ];
    
    // Add callback time if provided
    if ($callback_time) {
        $update_data['callback_time'] = date('Y-m-d H:i:s', strtotime($callback_time));
        
        // Get callback status
        $callback_status = db_get_var(
            "SELECT label_key AS status_key, FROM order_labels 
             WHERE label LIKE '%gọi lại%' OR label LIKE '%callback%' OR label LIKE '%hẹn%'
             ORDER BY sort_order 
             LIMIT 1"
        );
        
        if ($callback_status) {
            $update_data['status'] = $callback_status;
        }
    }
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    // Add call note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'manual',
        'content' => sanitize($notes)
    ]);
    
    // Create reminder if callback time set
    if ($callback_time) {
        db_insert('reminders', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'type' => 'callback',
            'due_time' => date('Y-m-d H:i:s', strtotime($callback_time)),
            'remind_time' => date('Y-m-d H:i:s', strtotime($callback_time . ' -15 minutes')),
            'status' => 'pending'
        ]);
        
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'note_type' => 'system',
            'content' => 'Đặt lịch gọi lại: ' . date('d/m/Y H:i', strtotime($callback_time))
        ]);
    }
    
    // Log activity
    log_activity('end_call', "Ended call for order #{$order['order_number']}", 'order', $order_id);
    
    // Commit transaction
    commit_transaction();
    
    json_success('Đã kết thúc cuộc gọi!');
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    error_log('[END_CALL] Error: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}