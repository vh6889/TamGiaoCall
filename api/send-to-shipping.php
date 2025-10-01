<?php
// ============================================
// api/send-to-shipping.php
// ============================================  
if (basename($_SERVER['PHP_SELF']) == 'send-to-shipping.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
require_once '../includes/security_helper.php';
    
    header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('send-to-shipping', get_logged_user()['id']);

$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

// Verify user has access to this order
$order = require_order_access($order_id, false);

    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    // Input validated above
    
    if (!$order_id) {
        json_error('Invalid order ID');
    }
    
    try {
        // Update status to shipping
        db_update('orders', [
            'status' => 'shipping',
            'shipped_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        // Add note
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => get_logged_user()['id'],
            'note_type' => 'system',
            'content' => 'Đã gửi đơn sang bộ phận giao vận'
        ]);
        
        // TODO: Integrate with shipping API/system
        
        json_success('Đã gửi đơn sang giao hàng');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}