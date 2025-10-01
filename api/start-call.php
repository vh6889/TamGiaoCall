<?php
// ============================================
// api/start-call.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'start-call.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
    
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
            'status' => 'dong-goi-sai',
            'last_call_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        json_success('Đã bắt đầu cuộc gọi');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}