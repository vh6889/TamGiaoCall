<?php
/**
 * API: Submit Manual Order for Approval
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$current_user_id = get_logged_user()['id'];

// Validate input
if (empty($input['customer_name']) || empty($input['customer_phone']) || empty($input['products'])) {
    json_error('Vui lòng điền đầy đủ thông tin khách hàng và sản phẩm.');
}

try {
    $order_data = [
        'order_number' => 'MANUAL-' . time(), // Tạo mã đơn hàng tạm
        'customer_name' => sanitize($input['customer_name']),
        'customer_phone' => sanitize($input['customer_phone']),
        'customer_address' => sanitize($input['customer_address'] ?? ''),
        'customer_notes' => sanitize($input['customer_notes'] ?? ''),
        'total_amount' => (float)($input['total_amount'] ?? 0),
        'products' => json_encode($input['products']),
        'status' => 'pending_approval',
        'approval_status' => 'pending',
        'source' => 'manual',
        'created_by' => $current_user_id,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $order_id = db_insert('orders', $order_data);

    // Cập nhật lại mã đơn hàng cho chuẩn hơn
    db_update('orders', ['order_number' => 'M-' . $order_id], 'id = ?', [$order_id]);

    log_activity('submit_manual_order', 'Submitted manual order #' . $order_id, 'order', $order_id);

    json_success('Đã gửi đơn hàng đi duyệt thành công!');

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}