<?php
/**
 * API: Delete User and Handle Work Handover
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
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

check_rate_limit('delete-user', get_logged_user()['id']);

$input = get_json_input(["user_id"]);
$user_id_to_delete = (int)$input['user_id'];
$handover_option = $input['handover_option'] ?? 'reclaim';
$target_user_id = (int)($input['target_user_id'] ?? 0);

$current_user = get_logged_user();

if (!$user_id_to_delete) {
    json_error('User ID không hợp lệ.');
}

if ($user_id_to_delete === $current_user['id']) {
    json_error('Bạn không thể tự xóa tài khoản của mình.');
}

if ($handover_option === 'transfer' && !$target_user_id) {
    json_error('Vui lòng chọn nhân viên để bàn giao.');
}

try {
    begin_transaction();

    // Lấy đơn hàng chưa hoàn thành (tương tự disable-user.php)
    $final_labels = array_merge(
        get_confirmed_statuses(), 
        get_cancelled_statuses()
    );
    
    $placeholders_labels = implode(',', array_fill(0, count($final_labels), '?'));
    
    $pending_orders = db_get_results(
        "SELECT id, order_number 
         FROM orders 
         WHERE assigned_to = ? 
           AND system_status = 'assigned'
           AND is_locked = 0
           AND primary_label NOT IN ($placeholders_labels)",
        array_merge([$user_id_to_delete], $final_labels)
    );

    if (!empty($pending_orders)) {
        $order_ids = array_column($pending_orders, 'id');
        $placeholders = implode(',', array_fill(0, count($order_ids), '?'));

        if ($handover_option === 'reclaim') {
            $new_label = get_new_status_key();
            db_query(
                "UPDATE orders 
                 SET assigned_to = NULL, 
                     system_status = 'free',
                     primary_label = ?,
                     assigned_at = NULL
                 WHERE id IN ($placeholders)", 
                array_merge([$new_label], $order_ids)
            );
            log_activity('handover_reclaim', 'Reclaimed ' . count($order_ids) . ' orders before deleting user #' . $user_id_to_delete);
            
        } elseif ($handover_option === 'transfer') {
            db_query(
                "UPDATE orders 
                 SET assigned_to = ?,
                     assigned_at = NOW()
                 WHERE id IN ($placeholders)", 
                array_merge([$target_user_id], $order_ids)
            );
            log_activity('handover_transfer', 'Transferred ' . count($order_ids) . ' orders before deleting user #' . $user_id_to_delete . ' to user #' . $target_user_id);
        }
    }

    // Xóa người dùng
    db_delete('users', 'id = ?', [$user_id_to_delete]);
    log_activity('delete_user', 'Deleted user #' . $user_id_to_delete);

    commit_transaction();
    
    json_success('Đã xóa tài khoản và bàn giao công việc thành công.');
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[DELETE_USER] Error: ' . $e->getMessage());
    json_error('Error: ' . $e->getMessage(), 500);
}