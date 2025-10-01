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

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('end-call', get_logged_user()['id']);

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
    $order = get_order($order_id);
    
    if (!$order) {
        json_error('Không tìm thấy đơn hàng', 404);
    }
    
    if ($order['assigned_to'] != $user['id'] && !is_admin()) {
        json_error('Bạn không có quyền kết thúc cuộc gọi này', 403);
    }
    
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
    
    begin_transaction();
    
    db_update('call_logs', [
        'end_time' => date('Y-m-d H:i:s'),
        'note' => sanitize($notes),
        'primary_label' => 'completed'
    ], 'id = ?', [$call['id']]);
    
    $new_call_count = intval($order['call_count'] ?? 0) + 1;
    
    $update_data = [
        'call_count' => $new_call_count,
        'last_call_at' => date('Y-m-d H:i:s')
    ];
    
    if ($callback_time) {
        $update_data['callback_time'] = date('Y-m-d H:i:s', strtotime($callback_time));
        
        // LỖI CŨ: SELECT label_key FROM ... (dấu phẩy thừa)
        // SỬA THÀNH:
        $callback_label = db_get_var("
            SELECT label_key 
            FROM order_labels 
            WHERE label_name LIKE '%gọi lại%' 
               OR label_name LIKE '%callback%' 
               OR label_name LIKE '%hẹn%'
            ORDER BY sort_order 
            LIMIT 1
        ");
        
        if ($callback_label) {
            $update_data['primary_label'] = $callback_label;
        }
    }
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'call',
        'content' => $notes
    ]);
    
    $duration = strtotime($call['end_time'] ?? 'now') - strtotime($call['start_time']);
    $duration_formatted = gmdate('H:i:s', $duration);
    
    log_activity(
        'end_call', 
        "Completed call for order #{$order['order_number']} (Duration: {$duration_formatted})",
        'order', 
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã kết thúc cuộc gọi', [
        'call_duration' => $duration,
        'call_count' => $new_call_count
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[END_CALL] Error: ' . $e->getMessage());
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}
