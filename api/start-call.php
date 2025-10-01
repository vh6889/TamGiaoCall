<?php
// ============================================
// api/start-call.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'start-call.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
    require_once '../functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';
    
    header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('start-call', get_logged_user()['id']);

$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

// Verify user has access to this order
$order = require_order_access($order_id, false);

    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    // Input validated above
    $user = get_logged_user();
    
    if (!$order_id) {
        json_error('Invalid order ID');
    }
    
    try {
        // Create call log
        db_insert('call_logs', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'user_name' => $user['full_name'],
            'start_time' => date('Y-m-d H:i:s'),
            'status' => 'active'
        ]);
        
        // Update order status
        db_update('orders', [
            'status' => get_calling_status_key(),
            'last_call_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        json_success('Đã bắt đầu cuộc gọi');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}