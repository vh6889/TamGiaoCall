<?php
/**
 * API: Start Call - CRM VERSION
 * Bắt đầu cuộc gọi, mở khóa edit, bắt đầu tính giờ
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../functions.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

check_rate_limit('start-call', get_logged_user()['id']);

$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

if (!$order_id) {
    json_error('Invalid order ID', 400);
}

$user = get_logged_user();

try {
    begin_transaction();
    
    // 1. Lấy đơn hàng
    $order = db_get_row(
        "SELECT * FROM orders WHERE id = ? FOR UPDATE",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // 2. Kiểm tra quyền
    if ($order['assigned_to'] != $user['id']) {
        throw new Exception('Bạn không có quyền gọi đơn này');
    }
    
    if ($order['is_locked']) {
        throw new Exception('Đơn hàng đã bị khóa');
    }
    
    // 3. Kiểm tra cuộc gọi đang active
    $active_call = db_get_row(
        "SELECT * FROM call_logs 
         WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
        [$order_id, $user['id']]
    );
    
    if ($active_call) {
        // Trả về call đang active thay vì lỗi
        json_success('Đang trong cuộc gọi', [
            'call_id' => $active_call['id'],
            'start_time' => $active_call['start_time'],
            'duration' => time() - strtotime($active_call['start_time']),
            'can_edit' => true
        ]);
        exit;
    }
    
    // 4. Tạo call log mới
    $call_id = db_insert('call_logs', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'user_name' => $user['full_name'],
        'start_time' => date('Y-m-d H:i:s'),
        'status' => 'active'
    ]);
    
    // 5. Tính số lần gọi mới
    $new_call_count = ($order['call_count'] ?? 0) + 1;
    
    // 6. Update order - tăng call_count và ghi last_call_at
    db_update('orders', [
        'call_count' => $new_call_count,
        'last_call_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);
    
    // 7. Ghi log activity
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'system',
        'content' => "Bắt đầu cuộc gọi lần thứ " . $new_call_count
    ]);
    
    // 8. Tạo message cho log
    $log_message = "Started call #{$new_call_count} for order #{$order['order_number']}";
    
    log_activity(
        'start_call',
        $log_message,
        'order',
        $order_id
    );
    
    commit_transaction();
    
    // 9. Trả về thông tin để UI hiển thị
    json_success('Đã bắt đầu cuộc gọi', [
        'call_id' => $call_id,
        'start_time' => date('Y-m-d H:i:s'),
        'call_number' => $new_call_count,
        'can_edit' => true, // Cho phép sửa trong cuộc gọi
        'customer' => [
            'name' => $order['customer_name'],
            'phone' => $order['customer_phone'],
            'address' => $order['customer_address']
        ]
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}