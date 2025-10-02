<?php
// ============================================
// api/claim-order.php
// CASE 2: User tự nhận đơn lần đầu
// ============================================
/**
 * API: Claim Order
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
    
    // Lấy đơn hàng với lock
    $order = db_get_row(
        "SELECT * FROM orders WHERE id = ? FOR UPDATE",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // Kiểm tra có thể nhận không
    if ($order['system_status'] !== 'free') {
        throw new Exception('Đơn hàng đã có người nhận');
    }
    
    if ($order['is_locked']) {
        throw new Exception('Đơn hàng đã bị khóa');
    }
    
    // CASE 2: LẦN ĐẦU NHẬN ĐƠN
    if ($order['core_status'] === 'new') {
        // Đơn mới -> chuyển sang processing
        db_update('orders', [
            'system_status' => 'assigned',
            'core_status' => 'processing',
            'primary_label' => 'lbl_processing',
            'assigned_to' => $user['id'],
            'assigned_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        $note = "Nhận đơn hàng và bắt đầu xử lý";
    } else {
        // Đơn đã từng được xử lý (bị thu hồi) -> giữ nguyên label
        db_update('orders', [
            'system_status' => 'assigned',
            'assigned_to' => $user['id'],
            'assigned_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        $note = "Nhận lại đơn hàng";
    }
    
    // Ghi log
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'system',
        'content' => $note
    ]);
    
    commit_transaction();
    
    json_success('Đã nhận đơn hàng thành công!');
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}

// ============================================
// api/assign-order.php  
// CASE 2: Admin/Manager phân công đơn lần đầu
// ============================================
/**
 * API: Assign Order
 * Admin/Manager phân công đơn cho user
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

// Chỉ admin và manager được phân công
if (!is_admin() && !is_manager()) {
    json_error('Không có quyền phân công', 403);
}

$input = get_json_input(['order_id', 'user_id']);
$order_id = (int)($input['order_id'] ?? 0);
$assign_to_user_id = (int)($input['user_id'] ?? 0);

if (!$order_id || !$assign_to_user_id) {
    json_error('Vui lòng chọn đơn hàng và nhân viên');
}

try {
    begin_transaction();
    
    // Lấy đơn hàng với lock
    $order = db_get_row(
        "SELECT * FROM orders WHERE id = ? FOR UPDATE",
        [$order_id]
    );
    
    if (!$order) {
        throw new Exception('Không tìm thấy đơn hàng');
    }
    
    // Kiểm tra user được gán
    $user = db_get_row(
        "SELECT * FROM users WHERE id = ? AND status = 'active'",
        [$assign_to_user_id]
    );
    
    if (!$user) {
        throw new Exception('Nhân viên không hợp lệ');
    }
    
    // CASE 2: LẦN ĐẦU PHÂN CÔNG
    if ($order['core_status'] === 'new') {
        // Đơn mới -> chuyển sang processing
        db_update('orders', [
            'system_status' => 'assigned',
            'core_status' => 'processing',
            'primary_label' => 'lbl_processing',
            'assigned_to' => $assign_to_user_id,
            'assigned_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        $note = "Phân công cho {$user['full_name']} và bắt đầu xử lý";
    } else {
        // Đơn đã từng được xử lý -> giữ nguyên label
        db_update('orders', [
            'system_status' => 'assigned',
            'assigned_to' => $assign_to_user_id,
            'assigned_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$order_id]);
        
        $note = "Phân công lại cho {$user['full_name']}";
    }
    
    // Ghi log
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => get_logged_user()['id'],
        'note_type' => 'assignment',
        'content' => $note
    ]);
    
    commit_transaction();
    
    json_success('Đã phân công thành công!');
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}