<?php
/**
 * API: Set Callback Time
 * Đặt lịch gọi lại cho đơn hàng
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('set-callback', get_logged_user()['id']);

$input = get_json_input(["order_id","callback_time"]);
$order_id = (int)$input['order_id'];
$callback_time = $input['callback_time'] ?? '';

if (!$order_id || !$callback_time) {
    json_error('Invalid parameters', 400);
}

// Verify user has access to this order
$order = require_order_access($order_id, false);

$current_user = get_logged_user();

try {
    begin_transaction();
    
    // LỖI CŨ: SELECT label_key FROM ... (dấu phẩy thừa)
    // SỬA THÀNH: Tìm nhãn callback
    $callback_label = db_get_var("
        SELECT label_key 
        FROM order_labels 
        WHERE label_name LIKE '%gọi lại%' 
           OR label_name LIKE '%callback%' 
           OR label_name LIKE '%hẹn%'
        ORDER BY sort_order 
        LIMIT 1
    ");
    
    $update_data = [
        'callback_time' => date('Y-m-d H:i:s', strtotime($callback_time))
    ];
    
    // Chỉ update label nếu tìm thấy
    if ($callback_label) {
        $update_data['primary_label'] = $callback_label;
    }
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'manual',
        'content' => 'Đặt lịch gọi lại: ' . date('d/m/Y H:i', strtotime($callback_time))
    ]);
    
    log_activity('set_callback', 'Set callback time', 'order', $order_id);
    
    commit_transaction();
    
    json_success('Đã đặt lịch gọi lại');
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[SET_CALLBACK] Error: ' . $e->getMessage());
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}