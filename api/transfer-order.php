<?php
/**
 * API: Transfer an Order
 * Allows an admin to transfer an order from one user to another.
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
$target_user_id = (int)($input['target_user_id'] ?? 0);

if (!$order_id || !$target_user_id) {
    json_error('Invalid parameters. Order ID and Target User ID are required.');
}

$order = get_order($order_id);
if (!$order) {
    json_error('Order not found.', 404);
}

$target_user = get_user($target_user_id);
if (!$target_user || $target_user['role'] !== 'telesale' || $target_user['status'] !== 'active') {
    json_error('Nhân viên nhận đơn không hợp lệ hoặc không hoạt động.');
}

if ($order['assigned_to'] == $target_user_id) {
    json_error('Đơn hàng đã thuộc về nhân viên này.');
}

$current_user = get_logged_user();
$from_user = $order['assigned_to'] ? get_user($order['assigned_to']) : null;
$from_user_name = $from_user ? $from_user['full_name'] : 'kho đơn mới';

try {
    // Update assignment
    db_update('orders', [
        'assigned_to' => $target_user_id,
        'assigned_at' => date('Y-m-d H:i:s'),
        'status' => 'assigned'
    ], 'id = ?', [$order_id]);

    // Add system note
    $note_content = "Đơn hàng được chuyển từ '{$from_user_name}' sang cho {$target_user['full_name']} bởi {$current_user['full_name']}.";
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'assignment',
        'content' => $note_content
    ]);

    log_activity('transfer_order', "Transferred order #{$order['order_number']} to user #{$target_user_id}", 'order', $order_id);

    json_success('Đã chuyển giao đơn hàng thành công!', ['new_user_name' => $target_user['full_name']]);

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}