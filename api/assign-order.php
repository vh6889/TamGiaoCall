<?php
/**
 * API: Assign Order to a Telesale user
 * Allows an admin to manually assign an order to a specific user.
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

// Only Admins can assign orders
if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($input['order_id'] ?? 0);
$assign_to_user_id = (int)($input['user_id'] ?? 0);

if (!$order_id || !$assign_to_user_id) {
    json_error('Dữ liệu không hợp lệ. Vui lòng chọn đơn hàng và nhân viên.');
}

// 1. Validate the order
$order = get_order($order_id);
if (!$order) {
    json_error('Không tìm thấy đơn hàng.', 404);
}

// 2. Validate the user to be assigned
$user_to_assign = get_user($assign_to_user_id);
if (!$user_to_assign || $user_to_assign['role'] !== 'telesale' || $user_to_assign['status'] !== 'active') {
    json_error('Nhân viên được chọn không hợp lệ hoặc không hoạt động.', 400);
}

try {
    // 3. Update the order
    db_update('orders', [
        'assigned_to' => $assign_to_user_id,
        'assigned_at' => date('Y-m-d H:i:s'),
        'status' => 'assigned' // Ensure status is 'assigned'
    ], 'id = ?', [$order_id]);

    // 4. Add a system note for the assignment
    $current_user_name = get_logged_user()['full_name'];
    $note_content = "Đơn hàng được phân công cho " . $user_to_assign['full_name'] . " bởi " . $current_user_name . ".";
    
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => get_logged_user()['id'],
        'note_type' => 'assignment',
        'content' => $note_content
    ]);

    log_activity('assign_order', "Assigned order #{$order['order_number']} to {$user_to_assign['username']}", 'order', $order_id);

    json_success('Đã phân công đơn hàng thành công!');

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}