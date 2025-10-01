<?php
/**
 * API: Complete Reminder
 * Đánh dấu hoàn thành nhắc nhở
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

check_rate_limit('complete-reminder', get_logged_user()['id']);

$input = get_json_input(["reminder_id"]);
$reminder_id = (int)$input['reminder_id'];

if (!$reminder_id) {
    json_error('Invalid reminder ID', 400);
}

$reminder = db_get_row("SELECT * FROM reminders WHERE id = ?", [$reminder_id]);

if (!$reminder) {
    json_error('Reminder not found', 404);
}

$current_user = get_logged_user();

// Check permission
if (!is_admin() && $reminder['user_id'] != $current_user['id']) {
    json_error('Bạn không có quyền hoàn thành nhắc nhở này', 403);
}

try {
    db_update('reminders', [
    'status' => 'completed'
		// Bỏ completed_at vì không tồn tại
		// updated_at sẽ tự động cập nhật nhờ ON UPDATE current_timestamp()
	], 'id = ?', [$reminder_id]);
    
    log_activity('complete_reminder', "Completed reminder #{$reminder_id}", 'reminder', $reminder_id);
    
    json_success('Đã đánh dấu hoàn thành');
    
} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}