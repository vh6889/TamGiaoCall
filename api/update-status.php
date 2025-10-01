<?php
/**
 * API: Update Order Label (formerly update-status.php)
 * Cập nhật NHÃN (primary_label) cho đơn hàng và tự động khóa nếu là nhãn cuối cùng
 */
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

check_rate_limit('update-status', get_logged_user()['id']);

$input = get_json_input(["order_id", "status"]);
$order_id = (int)$input['order_id'];
$new_label = $input['status'];

if (!$order_id || !$new_label) {
    json_error('Dữ liệu không hợp lệ', 400);
}

if (is_system_status($new_label)) {
    json_error('Không thể chọn trạng thái hệ thống. Chỉ có thể chọn nhãn nghiệp vụ.', 400);
}

if (!validate_status_change($new_label)) {
    json_error('Nhãn không hợp lệ', 400);
}

$order = require_order_access($order_id, true);

if ($order['is_locked']) {
    json_error('Đơn hàng đã khóa, không thể cập nhật', 400);
}

$current_label = $order['primary_label'];
if (!validate_status_transition($current_label, $new_label)) {
    json_error('Không thể thay đổi từ nhãn hiện tại', 400);
}

$current_user = get_logged_user();

try {
    begin_transaction();
    
    $label_info = db_get_row("
        SELECT label_key, label_name, is_final 
        FROM order_labels 
        WHERE label_key = ?
    ", [$new_label]);
    
    if (!$label_info) {
        throw new Exception('Nhãn không tồn tại');
    }
    
    $update_data = [
        'primary_label' => $new_label,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $is_final_label = (bool)$label_info['is_final'];
    
    if ($is_final_label) {
        $update_data['is_locked'] = 1;
        $update_data['locked_at'] = date('Y-m-d H:i:s');
        $update_data['locked_by'] = $current_user['id'];
        $update_data['completed_at'] = date('Y-m-d H:i:s');
        
        // ✅ FIX: Sửa cột 'primary_label' thành 'status'
        db_update('reminders', 
            ['status' => 'cancelled'],
            'order_id = ? AND status = ?',
            [$order_id, 'pending']
        );
    }
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    log_activity(
        'update_status', 
        "Updated label for order #{$order['order_number']} to {$label_info['label_name']}", 
        'order', 
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã cập nhật nhãn thành công', [
        'order_id' => $order_id,
        'new_label' => $new_label,
        'is_locked' => $is_final_label
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[UPDATE_STATUS] Error: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}