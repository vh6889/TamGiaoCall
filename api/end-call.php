<?php
// ============================================
// api/end-call.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'end-call.php') {
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

check_rate_limit('end-call', get_logged_user()['id']);

$input = get_json_input(["order_id","notes"]);
$order_id = (int)$input['order_id'];
$notes = $input['notes'] ?? '';

// Verify user has access to this order
$order = require_order_access($order_id, false);

    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    // Input validated above
    // Input validated above
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
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_error('Error: ' . $e->getMessage(), 500);
}
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}