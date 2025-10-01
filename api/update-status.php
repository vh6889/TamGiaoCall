<?php
/**
 * API: Update Order Label (PATCHED VERSION)
 * ✅ THAY ĐỔI:
 * 1. Check label_value thay vì is_final
 * 2. Tự động lock khi label_value = 1
 * 3. Cancel reminders khi hoàn thành
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

// Verify user has access to this order
$order = require_order_access($order_id, false);

if ($order['is_locked']) {
    json_error('Đơn hàng đã khóa, không thể cập nhật', 400);
}

$current_user = get_logged_user();

try {
    begin_transaction();
    
    // ✅ THAY ĐỔI: Query label_value thay vì is_final
    $label_info = db_get_row("
        SELECT label_key, label_name, label_value 
        FROM order_labels 
        WHERE label_key = ?
    ", [$new_label]);
    
    if (!$label_info) {
        rollback_transaction();
        json_error('Nhãn không tồn tại', 400);
    }
    
    $update_data = [
        'primary_label' => $new_label,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // ✅ THÊM MỚI: Check label_value = 1 để auto-lock
    if ($label_info['label_value'] == 1) {
        $update_data['is_locked'] = 1;
        $update_data['locked_at'] = date('Y-m-d H:i:s');
        $update_data['locked_by'] = $current_user['id'];
        $update_data['completed_at'] = date('Y-m-d H:i:s');
        
        // ✅ THÊM MỚI: Cancel pending reminders
        db_update('reminders', 
            ['status' => 'cancelled'],
            'order_id = ? AND status = ?',
            [$order_id, 'pending']
        );
    }
    
    // Update order
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    // Add note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'system',
        'content' => "Cập nhật nhãn: {$label_info['label_name']}" . 
                     ($label_info['label_value'] == 1 ? ' (Đơn đã hoàn thành và bị khóa)' : '')
    ]);
    
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
        'is_locked' => ($label_info['label_value'] == 1)
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[UPDATE_STATUS] Error: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}
