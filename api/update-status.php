<?php
/**
 * API: Update Order Status
 * Cập nhật trạng thái đơn hàng và tự động khóa nếu là status cuối cùng
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

// Get input
$input = get_json_input(["order_id", "status"]);
$order_id = (int)$input['order_id'];
$status = $input['status'];

if (!$order_id || !$status) {
    json_error('Dữ liệu không hợp lệ', 400);
}

// Validate: không cho phép set status hệ thống
if (is_system_status($status)) {
    json_error('Không thể chọn trạng thái hệ thống', 400);
}

// Validate status exists
if (!validate_status_change($status)) {
    json_error('Trạng thái không hợp lệ', 400);
}

// Verify access
$order = require_order_access($order_id, true);

// Check if order is locked
if ($order['is_locked']) {
    json_error('Đơn hàng đã khóa, không thể cập nhật', 400);
}

// Validate status transition
if (!validate_status_transition($order['status'], $status)) {
    json_error('Không thể chuyển từ trạng thái hiện tại sang trạng thái mới', 400);
}

$current_user = get_logged_user();

try {
    // Begin transaction
    begin_transaction();
    
    $update_data = [
        'status' => $status,
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Check if status is final (completed or cancelled)
    $confirmed_statuses = get_confirmed_statuses();
    $cancelled_statuses = get_cancelled_statuses();
    $final_statuses = array_merge($confirmed_statuses, $cancelled_statuses);
    
    $is_final_status = in_array($status, $final_statuses);
    
    // Lock order if status is final
    if ($is_final_status) {
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
    
    // Get status label for logging
    $status_label = db_get_var(
        "SELECT label FROM order_status_configs WHERE status_key = ?",
        [$status]
    ) ?: $status;
    
    // Add note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'status',
        'content' => 'Cập nhật trạng thái: ' . $status
    ]);
    
    // Log activity
    log_activity(
        'update_status', 
        "Updated order #{$order['order_number']} status to: {$status_label}" . ($is_final_status ? ' (locked)' : ''),
        'order', 
        $order_id
    );
    
    // Commit transaction
    commit_transaction();
    
    json_success('Đã cập nhật trạng thái' . ($is_final_status ? ' và khóa đơn hàng' : ''), [
        'is_locked' => $is_final_status
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    error_log('[UPDATE_STATUS] Error: ' . $e->getMessage());
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}
