<?php
/**
 * API: Save Settings
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_error('Invalid data received.');
}

try {
    // Lặp qua các cài đặt được gửi lên và cập nhật vào DB
    foreach ($input as $key => $value) {
        // Sanitize the key to prevent unexpected setting keys
        $allowed_keys = ['site_name', 'woo_api_url', 'woo_consumer_key', 'woo_consumer_secret'];
        if (in_array($key, $allowed_keys)) {
            // Chỉ cập nhật consumer secret nếu nó không trống
            if ($key === 'woo_consumer_secret' && empty($value)) {
                continue;
            }
            update_setting($key, sanitize($value));
        }
    }
    
    log_activity('update_settings', 'System settings have been updated.');
    
    json_success('Cài đặt đã được lưu thành công.');

} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}