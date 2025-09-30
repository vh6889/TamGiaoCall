<?php
// Xóa đơn hàng (Admin only)
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = $input['order_id'] ?? 0;

if (!$order_id) {
    json_error('Invalid order ID');
}

$order = get_order($order_id);
if (!$order) {
    json_error('Order not found', 404);
}

try {
    db_delete('orders', 'id = ?', [$order_id]);
    
    log_activity('delete_order', 'Deleted order #' . $order['order_number'], 'order', $order_id);
    
    json_success('Đã xóa đơn hàng');
    
} catch (Exception $e) {
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}