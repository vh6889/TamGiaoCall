<?php
/**
 * API: Claim Order - SIMPLE VERSION  
 * User tự nhận đơn từ kho chung
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

check_rate_limit('claim-order', get_logged_user()['id']);

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
    
    // 2. Kiểm tra có thể nhận không
    if ($order['system_status'] !== 'free') {
        throw new Exception('Đơn hàng đã có người nhận');
    }
    
    if ($order['is_locked']) {
        throw new Exception('Đơn hàng đã bị khóa');
    }
    
    // 3. Update - LOGIC GIỐNG PHÂN CÔNG
    $update_data = [
        'system_status' => 'assigned',
        'assigned_to' => $user['id'],
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
        'user_id' => $user['id'],
        'note_type' => 'system',
        'content' => "Nhận đơn hàng"
    ]);
    
    commit_transaction();
    
    json_success('Đã nhận đơn hàng thành công!');
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}
?>