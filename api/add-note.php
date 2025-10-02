<?php
/**
 * API: Add Note to Order
 * Allows an assigned user or an admin to add a manual note to an order.
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

// Get JSON input
$engine->evaluate('order', $order_id, 'call_logged');
$input = json_decode(file_get_contents('php://input'), true);
$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$note_content = isset($input['content']) ? sanitize($input['content']) : '';

if (!$order_id || empty($note_content)) {
    json_error('Invalid parameters. Order ID and note content are required.');
}

$order = get_order($order_id);
if (!$order) {
    json_error('Order not found', 404);
}

// Check permission: User must be an admin or the one assigned to the order
$current_user = get_logged_user();
if (!is_admin() && $order['assigned_to'] != $current_user['id']) {
    json_error('You do not have permission to add a note to this order.', 403);
}

try {
    // Insert the new note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'manual', // A note added by a user
        'content' => $note_content
    ]);

    // When a note is added, it's considered a "call attempt", so we update call stats
    db_query(
        "UPDATE orders SET call_count = call_count + 1, last_call_at = ? WHERE id = ?",
        [date('Y-m-d H:i:s'), $order_id]
    );

    log_activity('add_note', "Added a note to order #{$order['order_number']}", 'order', $order_id);
    
    json_success('Đã thêm ghi chú thành công!');

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}