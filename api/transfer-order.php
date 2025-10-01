<?php
// ============================================
// api/transfer-order.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'transfer-order.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
    
    header('Content-Type: application/json');
    
    if (!is_logged_in() || !is_admin()) {
        json_error('Unauthorized', 403);
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $target_user_id = (int)($_POST['target_user_id'] ?? 0);
    
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