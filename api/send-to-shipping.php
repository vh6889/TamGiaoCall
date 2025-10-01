<?php
// ============================================
// api/send-to-shipping.php
// ============================================  
if (basename($_SERVER['PHP_SELF']) == 'send-to-shipping.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    
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