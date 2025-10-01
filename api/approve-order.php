<?php
/**
 * API: Approve or Reject a Manual Order
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';
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

$pdo = get_db_connection();
$pdo->beginTransaction();

try {

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$current_user_id = get_logged_user()['id'];

$order_id = (int)($input['order_id'] ?? 0);
$action = $input['action'] ?? ''; // 'approve' or 'reject'

if (!$order_id || !in_array($action, ['approve', 'reject'])) {
    json_error('Dữ liệu không hợp lệ.');
}

$order = get_order($order_id);
if (!$order || $order['approval_status'] !== 'pending') {
    json_error('Đơn hàng không hợp lệ hoặc đã được xử lý.', 404);
}

try {
    $update_data = [
        'approved_by' => $current_user_id,
        'approved_at' => date('Y-m-d H:i:s')
    ];
    $note_content = '';

    if ($action === 'approve') {
        $update_data['status'] = 'new'; // Chuyển về trạng thái "Đơn mới" để vào quy trình telesale
        $update_data['approval_status'] = 'approved';
        $note_content = 'Admin đã duyệt đơn hàng.';
    } else { // reject
        $update_data['status'] = 'cancelled'; // Hủy đơn hàng
        $update_data['approval_status'] = 'rejected';
        $note_content = 'Admin đã từ chối đơn hàng.';
    }

    db_update('orders', $update_data, 'id = ?', [$order_id]);

    // Thêm ghi chú hệ thống
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $current_user_id,
        'note_type' => 'system',
        'content' => $note_content
    ]);

    log_activity($action.'_manual_order', "{$action}d manual order #" . $order['order_number'], 'order', $order_id);

    json_success('Đã xử lý đơn hàng thành công!');
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_error('Error: ' . $e->getMessage(), 500);
}

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}