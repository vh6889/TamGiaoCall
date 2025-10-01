<?php
/**
 * API: Update Status - SIMPLE VERSION
 * User đổi nhãn -> core_status tự động theo nhãn
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

check_rate_limit('update-status', get_logged_user()['id']);

$input = get_json_input(["order_id", "status"]);
$order_id = (int)$input['order_id'];
$new_label = $input['status'] ?? '';

if (!$order_id || !$new_label) {
    json_error('Dữ liệu không hợp lệ', 400);
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
    
    // 2. Kiểm tra quyền
    $user = get_logged_user();
    if ($order['assigned_to'] != $user['id'] && !is_admin() && !is_manager()) {
        throw new Exception('Bạn không có quyền sửa đơn này');
    }
    
    // 3. Lấy thông tin nhãn mới
    $label_info = db_get_row(
        "SELECT * FROM order_labels WHERE label_key = ?",
        [$new_label]
    );
    
    if (!$label_info) {
        throw new Exception('Nhãn không tồn tại');
    }
    
    // 4. Update đơn - core_status TỰ ĐỘNG theo nhãn
    $update_data = [
        'primary_label' => $new_label,
        'core_status' => $label_info['core_status'], // TỰ ĐỘNG!
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Nếu là nhãn thành công (label_value = 1) -> khóa đơn
    if ($label_info['label_value'] == 1) {
        $update_data['is_locked'] = 1;
        $update_data['locked_at'] = date('Y-m-d H:i:s');
        $update_data['locked_by'] = $user['id'];
    }
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    // 5. Ghi log
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'system',
        'content' => "Cập nhật nhãn: {$label_info['label_name']}"
    ]);
    
    commit_transaction();
    
    json_success('Đã cập nhật nhãn thành công');
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}
?>