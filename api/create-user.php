<?php
/**
 * API: Create User
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

check_rate_limit('create-user', get_logged_user()['id']);

$input = get_json_input(["username","password","full_name"]);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';
$full_name = $input['full_name'] ?? '';

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate input
$username = sanitize($input['username'] ?? '');
$password = $input['password'] ?? '';
$full_name = sanitize($input['full_name'] ?? '');
$email = sanitize($input['email'] ?? '');
$role = $input['role'] ?? 'telesale';

if (empty($username) || empty($password) || empty($full_name)) {
    json_error('Vui lòng nhập đầy đủ Username, Password và Họ tên.');
}

if (strlen($password) < PASSWORD_MIN_LENGTH) {
    json_error('Mật khẩu phải có ít nhất ' . PASSWORD_MIN_LENGTH . ' ký tự.');
}

// Check if username or email exists
$existing_user = db_get_row("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
if ($existing_user) {
    json_error('Username hoặc Email đã tồn tại.');
}

try {
    $user_data = [
        'username' => $username,
        'password' => hash_password($password),
        'full_name' => $full_name,
        'email' => $email,
        'phone' => sanitize($input['phone'] ?? ''),
        'role' => $role,
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $user_id = db_insert('users', $user_data);
    
    log_activity('create_user', 'Created new user: ' . $username, 'user', $user_id);
    
    json_success('Đã tạo nhân viên thành công!');
    
} catch (Exception $e) {
    json_error('Có lỗi xảy ra: ' . $e->getMessage(), 500);
}