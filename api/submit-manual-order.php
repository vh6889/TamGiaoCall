<?php
/**
 * API: Submit Manual Order for Approval
 */
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

check_rate_limit('submit-manual-order', get_logged_user()['id']);

$input = get_json_input(["customer_name","customer_phone","products"]);

$current_user_id = get_logged_user()['id'];

// Validate input
if (empty($input['customer_name']) || empty($input['customer_phone']) || empty($input['products'])) {
    json_error('Vui lòng điền đầy đủ thông tin khách hàng và sản phẩm.');
}

try {
    begin_transaction();
    
    // LỖI CŨ: SELECT label_key FROM ... (dấu phẩy thừa)
    // SỬA THÀNH: Tìm nhãn "chờ duyệt"
    $pending_label = db_get_var("
        SELECT label_key 
        FROM order_labels 
        WHERE label_name LIKE '%duyệt%' 
           OR label_name LIKE '%pending%'
        ORDER BY sort_order 
        LIMIT 1
    ");
    
    // Nếu không tìm thấy, dùng system status
    $system_status = 'free';
    $primary_label = $pending_label ?: 'n-a';
    
    $order_data = [
        'order_number' => 'MANUAL-' . time(),
        'customer_name' => sanitize($input['customer_name']),
        'customer_phone' => sanitize($input['customer_phone']),
        'customer_address' => sanitize($input['customer_address'] ?? ''),
        'customer_notes' => sanitize($input['customer_notes'] ?? ''),
        'total_amount' => (float)($input['total_amount'] ?? 0),
        'products' => json_encode($input['products']),
        'system_status' => $system_status,
        'primary_label' => $primary_label,
        'approval_status' => 'pending',
        'source' => 'manual',
        'created_by' => $current_user_id,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $order_id = db_insert('orders', $order_data);

    // Cập nhật lại mã đơn hàng
    db_update('orders', ['order_number' => 'M-' . $order_id], 'id = ?', [$order_id]);

    log_activity('submit_manual_order', 'Submitted manual order #' . $order_id, 'order', $order_id);

    commit_transaction();
    
    json_success('Đã gửi đơn hàng đi duyệt thành công!', ['order_id' => $order_id]);
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[SUBMIT_MANUAL_ORDER] Error: ' . $e->getMessage());
    json_error('Error: ' . $e->getMessage(), 500);
}
