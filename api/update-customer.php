<?php
// ============================================
// api/update-customer.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'update-customer.php') {
    define('TSM_ACCESS', true);
    require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
    require_once '../system/functions.php';
require_once '../includes/security_helper.php';
    
    header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('update-customer', get_logged_user()['id']);

$input = get_json_input(["order_id","customer_name","customer_phone"]);
$order_id = (int)$input['order_id'];
$customer_name = $input['customer_name'] ?? '';
$customer_phone = $input['customer_phone'] ?? '';

// Verify user has access to this order
$order = require_order_access($order_id, false);

    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    // Input validated above
    $customer_name = sanitize($_POST['customer_name'] ?? '');
    $customer_phone = sanitize($_POST['customer_phone'] ?? '');
    $customer_email = sanitize($_POST['customer_email'] ?? '');
    $customer_address = sanitize($_POST['customer_address'] ?? '');
    
    if (!$order_id || !$customer_name || !$customer_phone) {
        json_error('Thông tin không đầy đủ');
    }
    
    try {
        db_update('orders', [
            'customer_name' => $customer_name,
            'customer_phone' => $customer_phone,
            'customer_email' => $customer_email,
            'customer_address' => $customer_address
        ], 'id = ?', [$order_id]);
        
        // Log activity
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => get_logged_user()['id'],
            'note_type' => 'system',
            'content' => 'Cập nhật thông tin khách hàng'
        ]);
        
        json_success('Đã cập nhật thông tin khách hàng');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}