<?php
// ============================================
// api/reclaim-order.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'reclaim-order.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
    
    header('Content-Type: application/json');
    
    if (!is_logged_in() || !is_admin()) {
        json_error('Unauthorized', 403);
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    
    if (!$order_id) {
        json_error('Invalid order ID');
    }
    
    try {
        db_update('orders', [
            'assigned_to' => null,
            'assigned_at' => null,
            'status' => 'new'
        ], 'id = ?', [$order_id]);
        
        json_success('Đã thu hồi đơn hàng');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}