<?php
/**
 * API: Reclaim Order
 * Thu hồi đơn hàng về kho chung
 * FIXED: Sử dụng logic đúng theo yêu cầu
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

if (!is_admin() && !is_manager()) {
    json_error('Chỉ Admin/Manager mới có quyền thu hồi', 403);
}

check_rate_limit('reclaim-order', get_logged_user()['id']);

$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

if (!$order_id || $order_id <= 0) {
    json_error('Invalid order ID', 400);
}

try {
    begin_transaction();
    
    // Lấy thông tin đơn hàng
    $order = db_get_row(
        "SELECT o.*, u.full_name as assigned_user_name
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
        throw new Exception('Đơn hàng đã ở trong kho chung');
    }
    
    // Thu hồi về kho chung
    $update_data = [
        'system_status' => 'free',
        'assigned_to' => NULL,
        'assigned_at' => NULL
    ];
    
    // KHÔNG thay đổi core_status và primary_label
    // Giữ nguyên trạng thái hiện tại của đơn
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    // Ghi log thu hồi
    $current_user = get_logged_user();
    $note_content = sprintf(
        "%s %s đã thu hồi đơn hàng về kho chung từ %s",
        $current_user['role'] === 'admin' ? 'Admin' : 'Manager',
        $current_user['full_name'],
        $order['assigned_user_name'] ?? 'N/A'
    );
    
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user['id'],
        'note_type' => 'system',
        'content' => $note_content
    ]);
    
    // Hủy các reminder đang pending của đơn này
    db_update('reminders', 
        ['status' => 'cancelled'],
        'order_id = ? AND status = ?',
        [$order_id, 'pending']
    );
    
    // Log activity
    log_activity(
        'reclaim_order', 
        "Reclaimed order #{$order['order_number']} to common pool", 
        'order', 
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã thu hồi đơn hàng về kho chung');
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[RECLAIM_ORDER] Error: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}
?>