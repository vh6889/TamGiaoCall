<?php
/**
 * API: Send to Shipping
 * ✅ FIXED VERSION: Query label động thay vì hardcode
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
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

if (!$order_id) {
    json_error('Invalid order ID');
}

// Verify user has access to this order
$order = require_order_access($order_id, false);

try {
    begin_transaction();
    
    // ✅ FIX: Query label "giao hàng" động
    $shipping_label = db_get_var("
        SELECT label_key FROM order_labels 
        WHERE label_name LIKE '%giao%' 
           OR label_name LIKE '%shipping%' 
           OR label_name LIKE '%vận chuyển%'
        ORDER BY sort_order 
        LIMIT 1
    ");
    
    // Fallback nếu không tìm thấy
    if (!$shipping_label) {
        $shipping_label = $order['primary_label']; // Giữ nguyên nhãn hiện tại
    }
    
    // Update status to shipping
    db_update('orders', [
        'primary_label' => $shipping_label,
        'shipped_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);
    
    // Add note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => get_logged_user()['id'],
        'note_type' => 'system',
        'content' => 'Đã gửi đơn sang bộ phận giao vận'
    ]);
    
    log_activity('send_to_shipping', "Sent order #{$order['order_number']} to shipping", 'order', $order_id);
    
    // TODO: Integrate with shipping API/system
    
    commit_transaction();
    json_success('Đã gửi đơn sang giao hàng');
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[SEND_TO_SHIPPING] Error: ' . $e->getMessage());
    json_error('Lỗi: ' . $e->getMessage(), 500);
}