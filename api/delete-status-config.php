<?php
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';
header('Content-Type: application/json');
require_admin();  // Chỉ admin

$input = json_decode(file_get_contents('php://input'), true);
$status_key = sanitize($input['status_key'] ?? '');

if (empty($status_key)) json_error('Invalid key.');

// Check nếu status đang dùng ở orders
$used = db_get_var("SELECT COUNT(*) FROM orders WHERE status = ?", [$status_key]);
if ($used > 0) json_error('Không thể xóa: Trạng thái đang dùng ở ' . $used . ' đơn hàng.');

db_delete('order_status_configs', 'status_key = ?', [$status_key]);
log_activity('delete_status_config', "Deleted status $status_key");

json_success('Xóa thành công!');