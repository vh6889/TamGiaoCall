<?php
// ============================================
// api/reclaim-order.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'reclaim-order.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';
    
    header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

if (!is_admin()) {
    json_error('Admin only', 403);
}

check_rate_limit('reclaim-order', get_logged_user()['id']);

$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

    
    if (!is_logged_in() || !is_admin()) {
        json_error('Unauthorized', 403);
    }
    
    // Input validated above
    
    if (!$order_id) {
        json_error('Invalid order ID');
    }
    
    try {
        db_update('orders', [
            'assigned_to' => null,
            'assigned_at' => null,
            'status' => 'n-a'
        ], 'id = ?', [$order_id]);
        
        json_success('Đã thu hồi đơn hàng');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}