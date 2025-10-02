<?php
/**
 * API: Transfer Order
 * Chuyển giao đơn hàng từ user này sang user khác
 * Logic: Thu hồi + Phân công lại
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

// Chỉ admin và manager được chuyển giao
if (!is_admin() && !is_manager()) {
    json_error('Không có quyền chuyển giao đơn hàng', 403);
}

$input = get_json_input(['order_id', 'new_user_id']);
$order_id = (int)($input['order_id'] ?? 0);
$new_user_id = (int)($input['new_user_id'] ?? 0);
$reason = $input['reason'] ?? '';

if (!$order_id || !$new_user_id) {
    json_error('Dữ liệu không hợp lệ', 400);
}

try {
    begin_transaction();
    
    // Lấy thông tin đơn hàng
    $order = db_get_row(
        "SELECT o.*, u.full_name as old_user_name 
         FROM orders o
         LEFT JOIN users u ON o.assigned_to = u.id
         WHERE o.id = ? FOR UPDATE",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // Kiểm tra đơn có đang được xử lý không
    if (!$order['assigned_to']) {
        throw new Exception('Đơn hàng chưa được phân công');
    }
    
    // Kiểm tra user mới
    $new_user = db_get_row(
        "SELECT * FROM users WHERE id = ? AND status = 'active'",
        [$new_user_id]
    );
    
    if (!$new_user) {
        throw new Exception('Nhân viên không hợp lệ');
    }
    
    // Kiểm tra không chuyển cho chính mình
    if ($order['assigned_to'] == $new_user_id) {
        throw new Exception('Không thể chuyển giao cho cùng một người');
    }
    
    // Thực hiện chuyển giao
    $update_data = [
        'assigned_to' => $new_user_id,
        'assigned_at' => date('Y-m-d H:i:s')
    ];
    
    // Nếu đơn đang ở trạng thái new -> chuyển sang processing
    if ($order['core_status'] === 'new') {
        $update_data['core_status'] = 'processing';
        $update_data['primary_label'] = 'lbl_processing';
    }
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    // Ghi log chuyển giao
    $current_user = get_logged_user();
    $note_content = sprintf(
        "%s %s đã chuyển giao đơn hàng từ %s sang %s",
        $current_user['role'] === 'admin' ? 'Admin' : 'Manager',
        $current_user['full_name'],
        $order['old_user_name'] ?? 'N/A',
        $new_user['full_name']
    );
    
    if ($reason) {
        $note_content .= "\nLý do: " . $reason;
    }
    
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'system',
        'content' => $note_content
    ]);
    
    // Log activity
    log_activity(
        'transfer_order',
        "Transferred order #{$order['order_number']} to {$new_user['full_name']}",
        'order',
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã chuyển giao đơn hàng thành công');
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}
?>