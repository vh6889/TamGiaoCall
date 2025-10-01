<?php
/**
 * API: Update User
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

if (!is_admin()) {
    json_error('Admin only', 403);
}

check_rate_limit('update-user', get_logged_user()['id']);

$input = get_json_input(["user_id"]);
$user_id = (int)$input['user_id'];

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$user_id = (int)($input['user_id'] ?? 0);

if (!$user_id) {
    json_error('User ID không hợp lệ.');
}

$user_data = [];
if (isset($input['full_name'])) $user_data['full_name'] = sanitize($input['full_name']);
if (isset($input['email'])) $user_data['email'] = sanitize($input['email']);
if (isset($input['phone'])) $user_data['phone'] = sanitize($input['phone']);
if (isset($input['role'])) $user_data['role'] = $input['role'];
if (isset($input['status'])) $user_data['status'] = $input['status'];

// Handle password change
if (!empty($input['password'])) {
    if (strlen($input['password']) < PASSWORD_MIN_LENGTH) {
        json_error('Mật khẩu phải có ít nhất ' . PASSWORD_MIN_LENGTH . ' ký tự.');
    }
    $user_data['password'] = hash_password($input['password']);
}

try {
    db_update('users', $user_data, 'id = ?', [$user_id]);
    
    log_activity('update_user', 'Updated user info', 'user', $user_id);
    
    json_success('Đã cập nhật thông tin nhân viên!');
    
} catch (Exception $e) {
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}