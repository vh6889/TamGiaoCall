<?php
/**
 * API: Reclaim an Order
 * Allows an admin to un-assign an order, returning it to the 'new' pool.
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($input['order_id'] ?? 0);

if (!$order_id) {
    json_error('Invalid Order ID.');
}

$order = get_order($order_id);
if (!$order) {
    json_error('Order not found.', 404);
}

if (empty($order['assigned_to'])) {
    json_error('Order is not currently assigned to anyone.', 400);
}

$current_user = get_logged_user();
$reclaimed_user = get_user($order['assigned_to']);

try {
    // Return the order to the 'new' pool
    db_update('orders', [
        'assigned_to' => null,
        'assigned_at' => null,
        'status' => 'new'
    ], 'id = ?', [$order_id]);

    // Add a system note
    $note_content = "Đơn hàng đã được thu hồi từ nhân viên " . ($reclaimed_user['full_name'] ?? 'N/A') . " bởi " . $current_user['full_name'] . ".";
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'system',
        'content' => $note_content
    ]);

    log_activity('reclaim_order', "Reclaimed order #{$order['order_number']} from user #" . $order['assigned_to'], 'order', $order_id);

    json_success('Đã thu hồi đơn hàng thành công!');

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}