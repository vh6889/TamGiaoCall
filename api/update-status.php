<?php
/**
 * API: Update Status
 * CASE 3: User tự quyết định label/status
 * - Hệ thống KHÔNG can thiệp
 * - Chỉ lưu theo lựa chọn của user
 * - core_status tự động theo label (từ bảng order_labels)
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

check_rate_limit('update-status', get_logged_user()['id']);

$input = get_json_input(["order_id", "status"]);
$order_id = (int)$input['order_id'];
$new_label = $input['status'] ?? '';

if (!$order_id || !$new_label) {
    json_error('Dữ liệu không hợp lệ', 400);
}

try {
    begin_transaction();
    
    // Lấy đơn hàng
    $order = db_get_row(
        "SELECT * FROM orders WHERE id = ? FOR UPDATE",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // Kiểm tra quyền
    $user = get_logged_user();
    $can_update = false;
    
    if (is_admin()) {
        $can_update = true;
    } elseif (is_manager()) {
        // Manager có thể update đơn của team mình
        $team_ids = db_get_col(
            "SELECT telesale_id FROM manager_assignments WHERE manager_id = ?",
            [$user['id']]
        );
        if ($order['assigned_to'] == $user['id'] || in_array($order['assigned_to'], $team_ids)) {
            $can_update = true;
        }
    } elseif ($order['assigned_to'] == $user['id']) {
        // User chỉ update đơn của mình
        $can_update = true;
    }
    
    if (!$can_update) {
        throw new Exception('Bạn không có quyền sửa đơn này');
    }
    
    // Lấy thông tin label
    $label_info = db_get_row(
        "SELECT * FROM order_labels WHERE label_key = ?",
        [$new_label]
    );
    
    if (!$label_info) {
        throw new Exception('Nhãn không tồn tại');
    }
    
    // CASE 3: USER TỰ QUYẾT ĐỊNH - HỆ THỐNG CHỈ LƯU
    $update_data = [
        'primary_label' => $new_label,
        'core_status' => $label_info['core_status'], // Tự động từ bảng order_labels
        // system_status GIỮ NGUYÊN 'assigned' - không thay đổi
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Nếu là status thành công/thất bại -> khóa đơn
    if ($label_info['core_status'] === 'success' || $label_info['core_status'] === 'failed') {
        $update_data['is_locked'] = 1;
        $update_data['locked_at'] = date('Y-m-d H:i:s');
        $update_data['locked_by'] = $user['id'];
    }
    
    db_update('orders', $update_data, 'id = ?', [$order_id]);
    
    // Ghi log thay đổi
    $old_label = db_get_var(
        "SELECT label_name FROM order_labels WHERE label_key = ?",
        [$order['primary_label']]
    );
    
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'status',
        'content' => "Đổi trạng thái: {$old_label} → {$label_info['label_name']}"
    ]);
    
    // Log activity
    log_activity(
        'update_order_status',
        "Updated order #{$order['order_number']} status to {$label_info['label_name']}",
        'order',
        $order_id
    );
    
    commit_transaction();
    
    json_success('Đã cập nhật trạng thái thành công', [
        'new_status' => $new_label,
        'status_name' => $label_info['label_name'],
        'core_status' => $label_info['core_status'],
        'is_locked' => $update_data['is_locked'] ?? 0
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}