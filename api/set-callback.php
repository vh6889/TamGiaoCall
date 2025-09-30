<?php
// Đặt lịch gọi lại
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? 0;
$callback_time = $input['callback_time'] ?? '';

if (!$order_id || !$callback_time) {
    json_error('Invalid parameters');
}

$order = get_order($order_id);
if (!$order) {
    json_error('Order not found', 404);
}

$current_user = get_current_user();
if (!is_admin() && $order['assigned_to'] != $current_user['id']) {
    json_error('Access denied', 403);
}

try {
    db_update('orders', [
        'callback_time' => date('Y-m-d H:i:s', strtotime($callback_time)),
        'status' => 'callback'
    ], 'id = ?', [$order_id]);
    
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'manual',
        'content' => 'Đặt lịch gọi lại: ' . format_date($callback_time)
    ]);
    
    log_activity('set_callback', 'Set callback time', 'order', $order_id);
    
    json_success('Đã đặt lịch gọi lại');
    
} catch (Exception $e) {
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}