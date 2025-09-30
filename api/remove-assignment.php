<?php
// ===== api/remove-assignment.php =====
/**
 * API: Remove Manager Assignment
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../functions.php';

header('Content-Type: application/json');

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