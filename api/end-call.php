<?php
/**
 * API: End Call - CRM VERSION
 * Kết thúc cuộc gọi - BẮT BUỘC ghi log và có thể đổi status
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

$input = get_json_input(["order_id", "call_note"]); // call_note BẮT BUỘC
$order_id = (int)$input['order_id'];
$call_note = trim($input['call_note'] ?? '');
$new_status = $input['status'] ?? null; // optional
$callback_time = $input['callback_time'] ?? null; // optional

if (!$order_id || empty($call_note)) {
    json_error('Vui lòng nhập ghi chú cuộc gọi', 400);
}

$user = get_logged_user();

try {
    begin_transaction();
    
    // 1. Tìm cuộc gọi active
    $active_call = db_get_row(
        "SELECT * FROM call_logs 
         WHERE order_id = ? AND user_id = ? AND end_time IS NULL
         FOR UPDATE",
        [$order_id, $user['id']]
    );
    
    if (!$active_call) {
        throw new Exception('Không tìm thấy cuộc gọi đang hoạt động');
    }
    
    // 2. Tính thời gian gọi
    $end_time = date('Y-m-d H:i:s');
    $duration = strtotime($end_time) - strtotime($active_call['start_time']);
    
    // 3. Cập nhật call log - GHI ĐẦY ĐỦ THÔNG TIN
    $final_note = $active_call['note'] ?? '';
    $final_note .= "\n\n=== KẾT THÚC CUỘC GỌI ===\n";
    $final_note .= "Nội dung chính: " . $call_note;
    
    db_update('call_logs', [
        'end_time' => $end_time,
        'duration' => $duration,
        'note' => $final_note,
        'status' => 'completed'
    ], 'id = ?', [$active_call['id']]);
    
    // 4. Lấy thông tin đơn
    $order = db_get_row("SELECT * FROM orders WHERE id = ?", [$order_id]);
    
    // 5. Xử lý cập nhật status (nếu có)
    $status_changed = false;
    $old_status = $order['primary_label'];
    $new_label_name = '';
    
    if ($new_status && $new_status !== $old_status) {
        $label_info = db_get_row(
            "SELECT * FROM order_labels WHERE label_key = ?",
            [$new_status]
        );
        
        if ($label_info) {
            $update_order = [
                'primary_label' => $new_status,
                'core_status' => $label_info['core_status']
            ];
            
            // Khóa đơn nếu core_status = 'success'
            if ($label_info['core_status'] === 'success') {
                $update_order['is_locked'] = 1;
                $update_order['locked_at'] = date('Y-m-d H:i:s');
                $update_order['locked_by'] = $user['id'];
                $update_order['completed_at'] = date('Y-m-d H:i:s');
            }
            
            db_update('orders', $update_order, 'id = ?', [$order_id]);
            $status_changed = true;
            $new_label_name = $label_info['label_name'];
        }
    }
    
    // 6. Xử lý hẹn gọi lại (nếu có)
    if ($callback_time) {
        db_update('orders', 
            ['callback_time' => $callback_time],
            'id = ?', 
            [$order_id]
        );
        
        // Tạo reminder
        db_insert('reminders', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'type' => 'callback',
            'due_time' => $callback_time,
            'status' => 'pending'
        ]);
    }
    
    // 7. Ghi log tổng hợp vào order_notes
    $log_content = "=== CUỘC GỌI LẦN {$order['call_count']} ===\n";
    $log_content .= "Thời lượng: " . gmdate("H:i:s", $duration) . "\n";
    $log_content .= "Nội dung: " . $call_note;
    
    if ($status_changed) {
        $log_content .= "\nĐổi trạng thái: {$old_status} → {$new_status} ({$new_label_name})";
    }
    
    if ($callback_time) {
        $log_content .= "\nHẹn gọi lại: " . date('d/m/Y H:i', strtotime($callback_time));
    }
    
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'manual',
        'content' => $log_content
    ]);
    
    // 8. Log activity
    log_activity(
        'end_call',
        sprintf("Call #%d ended - Duration: %s - Status: %s", 
            $order['call_count'],
            gmdate("H:i:s", $duration),
            $new_status ?: 'unchanged'
        ),
        'order',
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã kết thúc cuộc gọi', [
        'duration' => $duration,
        'formatted_duration' => gmdate("H:i:s", $duration),
        'call_count' => $order['call_count'],
        'status_changed' => $status_changed,
        'new_status' => $new_status,
        'callback_scheduled' => !empty($callback_time)
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}
?>