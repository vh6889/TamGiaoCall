<?php
/**
 * API: Approve or Reject a Manual Order
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

if (!is_admin()) {
    json_error('Admin only', 403);
}

check_rate_limit('approve-order', get_logged_user()['id']);

$input = get_json_input(["order_id","action"]);
$order_id = (int)$input['order_id'];
$action = $input['action'] ?? '';

$current_user_id = get_logged_user()['id'];

if (!in_array($action, ['approve', 'reject'])) {
    json_error('Dữ liệu không hợp lệ.');
}

$order = get_order($order_id);
if (!$order || $order['approval_status'] !== 'pending') {
    json_error('Đơn hàng không hợp lệ hoặc đã được xử lý.', 404);
}

try {
    begin_transaction();
    
    $update_data = [
        'approved_by' => $current_user_id,
        'approved_at' => date('Y-m-d H:i:s')
    ];
    $note_content = '';

    if ($action === 'approve') {
        // Chuyển về trạng thái "Đơn mới" để vào quy trình telesale
        $new_label = get_new_status_key();
        $update_data['system_status'] = 'free';
        $update_data['primary_label'] = $new_label;
        $update_data['approval_status'] = 'approved';
        $note_content = 'Admin đã duyệt đơn hàng.';
        
    } else { // reject
        // Hủy đơn hàng
        $cancelled_labels = get_cancelled_statuses();
        $cancelled_label = $cancelled_labels[0] ?? 'n-a';
        
        $update_data['system_status'] = 'assigned'; // Assign to admin
        $update_data['assigned_to'] = $current_user_id;
        $update_data['primary_label'] = $cancelled_label;
        $update_data['approval_status'] = 'rejected';
        $update_data['is_locked'] = 1;
        $update_data['locked_at'] = date('Y-m-d H:i:s');
        $update_data['locked_by'] = $current_user_id;
        $note_content = 'Admin đã từ chối đơn hàng.';
    }

    db_update('orders', $update_data, 'id = ?', [$order_id]);

    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user_id,
        'note_type' => 'system',
        'content' => $note_content
    ]);

    log_activity($action.'_manual_order', "{$action}d manual order #" . $order['order_number'], 'order', $order_id);

    commit_transaction();
    
    json_success('Đã xử lý đơn hàng thành công!');
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[APPROVE_ORDER] Error: ' . $e->getMessage());
    json_error('Error: ' . $e->getMessage(), 500);
}