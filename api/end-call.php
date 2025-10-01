<?php
// ============================================
// api/end-call.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'end-call.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $notes = $_POST['notes'] ?? '';
    $callback_time = $_POST['callback_time'] ?? null;
    $user = get_logged_user();
    
    if (!$order_id || !$notes) {
        json_error('Vui lòng nhập ghi chú');
    }
    
    try {
        // Find active call
        $call = db_get_row(
            "SELECT * FROM call_logs 
             WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
            [$order_id, $user['id']]
        );
        
        if (!$call) {
            json_error('Không tìm thấy cuộc gọi');
        }
        
        // End call
        db_update('call_logs', [
            'end_time' => date('Y-m-d H:i:s'),
            'note' => $notes,
            'status' => 'completed'
        ], 'id = ?', [$call['id']]);
        
        // Update order
        $order = get_order($order_id);
        db_update('orders', [
            'call_count' => ($order['call_count'] ?? 0) + 1,
            'last_call_at' => date('Y-m-d H:i:s'),
            'callback_time' => $callback_time
        ], 'id = ?', [$order_id]);
        
        // Add note
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'note_type' => 'call',
            'content' => $notes
        ]);
        
        // Create reminder if callback needed
        if ($callback_time) {
            db_insert('reminders', [
                'order_id' => $order_id,
                'user_id' => $user['id'],
                'type' => 'callback',
                'message' => 'Gọi lại cho khách',
                'due_time' => $callback_time,
                'status' => 'pending'
            ]);
        }
        
        json_success('Đã kết thúc cuộc gọi');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}