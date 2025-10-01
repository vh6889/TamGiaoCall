<?php
/**
 * API: Update Order Label (formerly update-status.php)
 * Cập nhật NHÃN (primary_label) cho đơn hàng và tự động khóa nếu là nhãn cuối cùng
 * 
 * LƯU Ý: Đây KHÔNG PHẢI update system_status (free/assigned)
 * system_status chỉ thay đổi khi claim/release order
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';

header('Content-Type: application/json');

// CSRF Protection
require_csrf();

// Authentication
if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

// Rate limiting
check_rate_limit('update-status', get_logged_user()['id']);

// Get input - giữ tên 'status' để backward compatible với frontend
$input = get_json_input(["order_id", "status"]);
$order_id = (int)$input['order_id'];
$new_label = $input['status']; // Thực ra là label_key, không phải system_status

if (!$order_id || !$new_label) {
    json_error('Dữ liệu không hợp lệ', 400);
}

// Validate: không cho phép set status hệ thống
if (is_system_status($new_label)) {
    json_error('Không thể chọn trạng thái hệ thống. Chỉ có thể chọn nhãn nghiệp vụ.', 400);
}

// Validate label exists
if (!validate_status_change($new_label)) {
    json_error('Nhãn không hợp lệ', 400);
}

// Verify access
$order = require_order_access($order_id, true);

// Check if order is locked
if ($order['is_locked']) {
    json_error('Đơn hàng đã khóa, không thể cập nhật', 400);
}

// Validate transition - kiểm tra có đang ở nhãn final không
$current_label = $order['primary_label'];
if (!validate_status_transition($current_label, $new_label)) {
    json_error('Không thể thay đổi từ nhãn hiện tại', 400);
}

$current_user = get_logged_user();

try {
    // Begin transaction
    begin_transaction();
    
    // Get label info để kiểm tra is_final
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
    
    // Lock order if label is final
    if ($is_final_label) {
        $update_data['is_locked'] = 1;
        $update_data['locked_at'] = date('Y-m-d H:i:s');
        $update_data['locked_by'] = $current_user['id'];
        $update_data['completed_at'] = date('Y-m-d H:i:s');
        
        // Cancel pending reminders
        db_update('reminders', 
            ['status' => 'cancelled', 'completed_at' => date('Y-m-d H:i:s')],
            'order_id = ? AND status = ?',
            [$order_id, 'pending']
        );
    }
    
    // Update order
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    // Trigger sẽ tự động thêm note vào order_notes và ghi vào order_label_history
    // Nhưng ta vẫn log activity
    
    // Log activity
    log_activity(
        'update_label', 
        "Updated order #{$order['order_number']} label to: {$label_info['label_name']}" . ($is_final_label ? ' (locked)' : ''),
        'order', 
        $order_id
    );
    
    // Commit transaction
    commit_transaction();
    
    json_success(
        'Đã cập nhật nhãn' . ($is_final_label ? ' và khóa đơn hàng' : ''), 
        [
            'is_locked' => $is_final_label,
            'label_name' => $label_info['label_name']
        ]
    );
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    error_log('[UPDATE_LABEL] Error: ' . $e->getMessage());
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}