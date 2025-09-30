<?php
/**
 * API: Claim an Order
 * Allows a telesale user to claim a new, unassigned order.
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// User must be logged in to claim an order
if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;

if (!$order_id) {
    json_error('Invalid order ID');
}

// Fetch the order
$order = get_order($order_id);
if (!$order) {
    json_error('Order not found', 404);
}

// Check if the order is available to be claimed
if ($order['status'] !== 'new' || !empty($order['assigned_to'])) {
    json_error('This order has already been taken or is not new.', 409); // 409 Conflict
}

$current_user = get_current_user();
$user_id = $current_user['id'];

try {
    // Assign the order to the current user
    db_update('orders', [
        'assigned_to' => $user_id,
        'status' => 'assigned', // Change status from 'new' to 'assigned'
        'assigned_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);

    // Add a system note to the order history
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user_id,
        'note_type' => 'system', // System-generated note
        'content' => 'Đã nhận đơn hàng.'
    ]);
    
    // Log this activity in the system logs
    log_activity('claim_order', 'Claimed order #' . $order['order_number'], 'order', $order_id);
    
    json_success('Đã nhận đơn hàng thành công!', ['order_id' => $order_id]);

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}