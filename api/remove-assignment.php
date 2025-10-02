<?php
// ===== api/remove-assignment.php =====
/**
 * API: Remove Manager Assignment
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';
require_once '../includes/security_helper.php';

header('Content-Type: application/json');

require_csrf();

if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

if (!is_admin()) {
    json_error('Admin only', 403);
}

check_rate_limit('remove-assignment', get_logged_user()['id']);

$input = get_json_input(["manager_id","telesale_id"]);
$manager_id = (int)$input['manager_id'];
$telesale_id = (int)$input['telesale_id'];

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized - Admin only', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$manager_id = (int)($input['manager_id'] ?? 0);
$telesale_id = (int)($input['telesale_id'] ?? 0);

if (!$manager_id || !$telesale_id) {
    json_error('Invalid parameters');
}

try {
    db_delete('manager_assignments', 
        'manager_id = ? AND telesale_id = ?', 
        [$manager_id, $telesale_id]
    );
    
    log_activity('remove_assignment', "Removed telesale #{$telesale_id} from manager #{$manager_id}");
    
    json_success('Đã xóa phân công thành công');
    
} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}