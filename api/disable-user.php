<?php
/**
 * API: Disable User and Handle Work Handover
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$current_user = get_logged_user();

$user_id_to_disable = (int)($input['user_id'] ?? 0);
$handover_option = $input['handover_option'] ?? 'reclaim'; // 'reclaim' or 'transfer'
$target_user_id = (int)($input['target_user_id'] ?? 0);

if (!$user_id_to_disable) {
    json_error('User ID không hợp lệ.');
}

if ($user_id_to_disable === $current_user['id']) {
    json_error('Bạn không thể tự vô hiệu hóa tài khoản của mình.');
}

if ($handover_option === 'transfer' && !$target_user_id) {
    json_error('Vui lòng chọn nhân viên để bàn giao.');
}

try {
    $pdo = get_db_connection();
    $pdo->beginTransaction();

    // Lấy danh sách các đơn hàng đang dang dở của nhân viên
    $pending_orders = db_get_results(
        "SELECT id, order_number FROM orders WHERE assigned_to = ? AND status NOT IN (SELECT status_key FROM order_status_configs WHERE label LIKE '%mới%' OR label LIKE '%hoàn%' OR label LIKE '%hủy%')",
        [$user_id_to_disable]
    );

    if (!empty($pending_orders)) {
        $order_ids = array_column($pending_orders, 'id');
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

        if ($handover_option === 'reclaim') {
            // Trả về kho đơn mới
            db_query("UPDATE orders SET assigned_to = NULL, status = 'new' WHERE id IN ($placeholders)", $order_ids);
            log_activity('handover_reclaim', 'Reclaimed ' . count($order_ids) . ' orders from user #' . $user_id_to_disable);
        } elseif ($handover_option === 'transfer') {
            // Bàn giao cho nhân viên khác
            db_query("UPDATE orders SET assigned_to = ? WHERE id IN ($placeholders)", array_merge([$target_user_id], $order_ids));
            log_activity('handover_transfer', 'Transferred ' . count($order_ids) . ' orders from user #' . $user_id_to_disable . ' to user #' . $target_user_id);
        }
    }

    // Vô hiệu hóa tài khoản
    db_update('users', ['status' => 'inactive'], 'id = ?', [$user_id_to_disable]);
    log_activity('disable_user', 'Disabled user #' . $user_id_to_disable);

    $pdo->commit();
    json_success('Đã vô hiệu hóa tài khoản và bàn giao công việc thành công.');

} catch (Exception $e) {
    $pdo->rollBack();
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}