<?php
/**
 * API: Claim Order
 * Nhận đơn hàng vào xử lý
 * 
 * Logic MỚI:
 * - Chỉ nhận được đơn có system_status = 'free'
 * - Sau khi nhận: system_status = 'assigned', assigned_to = user_id
 * - primary_label KHÔNG thay đổi (giữ nguyên nhãn hiện tại)
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/status_helper.php';

header('Content-Type: application/json');

// CSRF Protection
require_csrf();

// Authentication
if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

// Rate limiting
check_rate_limit('claim-order', get_logged_user()['id']);

// Get input
$input = get_json_input(["order_id"]);
$order_id = (int)$input['order_id'];

if (!$order_id || $order_id <= 0) {
    json_error('Invalid order ID', 400);
}

$user = get_logged_user();

try {
    // Begin transaction
    begin_transaction();
    
    // Lock row for update to prevent race condition
    $order = db_get_row("
        SELECT * FROM orders 
        WHERE id = ? 
        FOR UPDATE
    ", [$order_id]);
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // Check if order is locked
    if ($order['is_locked']) {
        throw new Exception('Đơn hàng đã bị khóa');
    }
    
    // Check system_status - CHỈ nhận đơn 'free'
    if ($order['system_status'] !== 'free') {
        if ($order['system_status'] === 'assigned') {
            if ($order['assigned_to'] == $user['id']) {
                throw new Exception('Bạn đã nhận đơn này rồi');
            } else {
                $assigned_user = db_get_var(
                    "SELECT full_name FROM users WHERE id = ?", 
                    [$order['assigned_to']]
                );
                throw new Exception("Đơn hàng đã được nhận bởi {$assigned_user}");
            }
        } else {
            throw new Exception('Đơn hàng không thể nhận (trạng thái: ' . $order['system_status'] . ')');
        }
    }
    
    // Update order - Chỉ thay đổi system_status và assigned_to
    // KHÔNG thay đổi primary_label
    db_update('orders', [
        'system_status' => 'assigned',
        'assigned_to' => $user['id'],
        'assigned_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);
    
    // Add system note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'system',
        'content' => "Nhận đơn hàng"
    ]);
    
    // Log activity
    log_activity(
        'claim_order', 
        "Claimed order #{$order['order_number']}", 
        'order', 
        $order_id
    );
    
    // Commit transaction
    commit_transaction();
    
    json_success('Đã nhận đơn hàng thành công!', [
        'order_id' => $order_id,
        'order_number' => $order['order_number']
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    rollback_transaction();
    
    error_log('[CLAIM_ORDER] Error: ' . $e->getMessage());
    json_error($e->getMessage(), 500);
}