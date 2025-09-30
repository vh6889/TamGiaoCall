<?php
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';
require_once '../RuleEngine.php';
header('Content-Type: application/json');
if (!is_logged_in()) json_error('Unauthorized', 401);
$engine = new RuleEngine($pdo);
$engine->evaluate('order', $order_id, 'status_changed');

$input = json_decode(file_get_contents('php://input'), true);
$order_id = (int)($input['order_id'] ?? 0);
$new_status = sanitize($input['status'] ?? '');

if (!$order_id || empty($new_status)) json_error('Invalid parameters.');

if (!is_valid_status($new_status)) json_error('Invalid status.');

$order = get_order($order_id);
if (!$order) json_error('Order not found', 404);

$current_user = get_logged_user();
if (!is_admin() && $order['assigned_to'] != $current_user['id']) json_error('No permission', 403);

$configs = get_order_status_configs();
$logic = $configs[$new_status]['logic'] ?? [];

// Validate require_note (check recent note trong 1 giờ qua, ví dụ)
if (!empty($logic['require_note'])) {
    $recent_note = db_get_var("SELECT COUNT(*) FROM order_notes WHERE order_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)", [$order_id]);
    if ($recent_note == 0) json_error('Require note before update to this status.');
}

// Validate required_fields nếu có (e.g., for 'da_xac_nhan')
if (!empty($logic['required_fields'])) {
    foreach ($logic['required_fields'] as $field) {
        if (empty($order[$field])) json_error("Field $field is required for this status.");
    }
}

try {
    db_update('orders', ['status' => $new_status], 'id = ?', [$order_id]);
    
    // Apply logic cụ thể
    if (!empty($logic['callback_delay_minutes'])) {
        $callback_time = date('Y-m-d H:i:s', strtotime("+{$logic['callback_delay_minutes']} minutes"));
        db_update('orders', ['callback_time' => $callback_time], 'id = ?', [$order_id]);
        
        $remind_before = $logic['remind_before_minutes'] ?? 0;
        $remind_time = $remind_before > 0 ? date('Y-m-d H:i:s', strtotime($callback_time) - ($remind_before * 60)) : null;
        
        insert_reminder($order_id, $order['assigned_to'], 'callback', $callback_time, $remind_time);
    }
    
    // Check max_attempts (e.g., for 'khong_nghe_may')
    if (!empty($logic['max_attempts']) && $order['call_count'] > $logic['max_attempts']) {
        db_update('orders', ['status' => 'rejected'], 'id = ?', [$order_id]);  // Auto reject
        db_insert('order_notes', ['order_id' => $order_id, 'user_id' => $current_user['id'], 'note_type' => 'system', 'content' => 'Auto rejected after max attempts']);
    }
    
    // Hủy pending reminders nếu action (e.g., status change)
    cancel_pending_reminders($order_id);
    
    // Add system note
    $status_label = $configs[$new_status]['label'];
    db_insert('order_notes', ['order_id' => $order_id, 'user_id' => $current_user['id'], 'note_type' => 'status', 'content' => 'Đổi trạng thái thành: ' . $status_label]);
    
    log_activity('update_status', "Updated order #{$order['order_number']} to '$new_status'", 'order', $order_id);
    
    json_success('Updated successfully!');
} catch (Exception $e) {
    json_error('DB error: ' . $e->getMessage(), 500);
}