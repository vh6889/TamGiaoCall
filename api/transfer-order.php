<?php
// ============================================
// api/transfer-order.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'transfer-order.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
require_once '../includes/security_helper.php';
    
    header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

if (!is_admin()) {
    json_error('Admin only', 403);
}

check_rate_limit('transfer-order', get_logged_user()['id']);

$input = get_json_input(["order_id","target_user_id"]);
$order_id = (int)$input['order_id'];
$target_user_id = (int)$input['target_user_id'];

    
    if (!is_logged_in() || !is_admin()) {
        json_error('Unauthorized', 403);
    }
    
    // Input validated above
    // Input validated above
    
    if (!$order_id || !$target_user_id) {
        json_error('Dữ liệu không hợp lệ');
    }
    
    try {
        db_update('orders', [
            'assigned_to' => $target_user_id,
            'assigned_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        json_success('Đã chuyển giao đơn hàng');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}