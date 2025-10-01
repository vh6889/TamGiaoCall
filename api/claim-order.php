<?php
// ============================================
// api/claim-order.php (Fixed)
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'claim-order.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
require_once '../includes/status_helper.php';
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $user = get_logged_user();
    
    if (!$order_id) {
        json_error('Invalid order ID');
    }
    
    try {
        $order = get_order($order_id);
        
        if ($order['assigned_to']) {
            json_error('Đơn hàng đã được gán');
        }
        
        db_update('orders', [
            'assigned_to' => $user['id'],
            'assigned_at' => date('Y-m-d H:i:s'),
            'status' => db_get_var("SELECT status_key FROM order_status_configs WHERE label LIKE '%nhận%' OR label LIKE '%assigned%' LIMIT 1")
        ], 'id = ?', [$order_id]);
        
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'note_type' => 'system',
            'content' => 'Nhận đơn hàng'
        ]);
        
        json_success('Đã nhận đơn hàng');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}