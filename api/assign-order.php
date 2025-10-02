<?php
/**
 * API: Assign Order - FIXED VERSION
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 401);
}

// Lấy input TRƯỚC KHI dùng
$input = get_json_input(['order_id', 'user_id']);
$order_id = (int)($input['order_id'] ?? 0);
$assign_to_user_id = (int)($input['user_id'] ?? 0);

// Debug (comment lại sau khi test xong)
// error_log('ASSIGN DEBUG - Input: ' . json_encode($input));
// error_log('ASSIGN DEBUG - Order ID: ' . $order_id);
// error_log('ASSIGN DEBUG - User ID: ' . $assign_to_user_id);

if (!$order_id || !$assign_to_user_id) {
    json_error('Vui lòng chọn đơn hàng và nhân viên');
}

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
    
    // 2. Kiểm tra user
    $user = db_get_row(
        "SELECT * FROM users WHERE id = ? AND status = 'active'",
        [$assign_to_user_id]
    );
    
    if (!$user) {
        throw new Exception('Nhân viên không hợp lệ');
    }
    
    // 3. Update đơn - LOGIC ĐƠN GIẢN
    $update_data = [
        'system_status' => 'assigned',
        'assigned_to' => $assign_to_user_id,
        'assigned_at' => date('Y-m-d H:i:s')
    ];
    
    // Nếu đơn mới -> set mặc định
    if ($order['core_status'] === 'new') {
        $update_data['core_status'] = 'processing';
        $update_data['primary_label'] = 'lbl_processing';
    }
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    // 4. Ghi log
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => get_logged_user()['id'],
        'note_type' => 'assignment',
        'content' => "Phân công cho {$user['full_name']}"
    ]);
    
    commit_transaction();
    
    json_success('Đã phân công thành công!');
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}
?>