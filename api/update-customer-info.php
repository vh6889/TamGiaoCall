<?php
// ========================================
// api/update-customer-info.php - Cập nhật thông tin khách
// ========================================
/**
 * API: Update Customer Info
 * Cập nhật thông tin khách hàng trong cuộc gọi
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('update-customer-info', get_logged_user()['id']);

$input = get_json_input(["order_id","customer_name","customer_phone"]);
$order_id = (int)$input['order_id'];
$customer_name = $input['customer_name'] ?? '';
$customer_phone = $input['customer_phone'] ?? '';

// Verify user has access to this order
$order = require_order_access($order_id, false);


if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($input['order_id'] ?? 0);
$customer_name = $input['customer_name'] ?? '';
$customer_phone = $input['customer_phone'] ?? '';
$customer_address = $input['customer_address'] ?? '';
$current_user = get_logged_user();

if (!$order_id || !$customer_name || !$customer_phone) {
    json_error('Thông tin không đầy đủ');
}

// Check if in active call
$active_call = db_get_row(
    "SELECT * FROM call_logs WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
    [$order_id, $current_user['id']]
);

if (!$active_call && !is_admin()) {
    json_error('Bạn cần bắt đầu cuộc gọi để chỉnh sửa thông tin');
}

try {
    // Get old values for logging
    $old_order = get_order($order_id);
    
    // Update customer info
    db_update('orders', [
        'customer_name' => sanitize($customer_name),
        'customer_phone' => sanitize($customer_phone),
        'customer_address' => sanitize($customer_address)
    ], 'id = ?', [$order_id]);
    
    // Log changes
    $changes = [];
    if ($old_order['customer_name'] != $customer_name) {
        $changes[] = "Tên: {$old_order['customer_name']} → $customer_name";
    }
    if ($old_order['customer_phone'] != $customer_phone) {
        $changes[] = "SĐT: {$old_order['customer_phone']} → $customer_phone";
    }
    if ($old_order['customer_address'] != $customer_address) {
        $changes[] = "Địa chỉ đã thay đổi";
    }
    
    if (!empty($changes)) {
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $current_user['id'],
            'note_type' => 'system',
            'content' => 'Cập nhật thông tin khách: ' . implode(', ', $changes)
        ]);
        
        // Log to action_logs for audit
        db_insert('action_logs', [
            'entity_id' => $order_id,
            'entity_type' => 'order',
            'user_id' => $current_user['id'],
            'action_type' => 'update_customer',
            'action_data' => json_encode([
                'old' => [
                    'name' => $old_order['customer_name'],
                    'phone' => $old_order['customer_phone'],
                    'address' => $old_order['customer_address']
                ],
                'new' => [
                    'name' => $customer_name,
                    'phone' => $customer_phone,
                    'address' => $customer_address
                ]
            ])
        ]);
    }
    
    json_success('Đã cập nhật thông tin khách hàng');
    
} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}