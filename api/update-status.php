<?php
/**
 * API: Update Order Status
 * Allows an assigned user or an admin to update the status of an order.
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$new_status = isset($input['status']) ? sanitize($input['status']) : '';

if (!$order_id || empty($new_status)) {
    json_error('Invalid parameters. Order ID and status are required.');
}

// Validate if the new status is a valid key in the ORDER_STATUS constant
if (!array_key_exists($new_status, ORDER_STATUS)) {
    json_error('Invalid status provided.');
}

$order = get_order($order_id);
if (!$order) {
    json_error('Order not found', 404);
}

// Check permission: User must be an admin or the one assigned to the order
$current_user = get_current_user();
if (!is_admin() && $order['assigned_to'] != $current_user['id']) {
    json_error('You do not have permission to update this order.', 403);
}

try {
    // Update the order status
    db_update('orders', [
        'status' => $new_status
    ], 'id = ?', [$order_id]);

    // Add a system note for the status change
    $status_label = ORDER_STATUS[$new_status]['label'];
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'status',
        'content' => 'Đã đổi trạng thái thành: ' . $status_label
    ]);

    log_activity('update_status', "Updated order #{$order['order_number']} status to '{$new_status}'", 'order', $order_id);
    
    // Optional: Add logic here to sync status back to WooCommerce if needed

    json_success('Đã cập nhật trạng thái thành công!');

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}