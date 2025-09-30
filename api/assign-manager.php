<?php
// ===== api/assign-manager.php =====
/**
 * API: Assign Telesales to Manager
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
$telesale_ids = $input['telesale_ids'] ?? [];

if (!$manager_id || empty($telesale_ids)) {
    json_error('Invalid parameters');
}

// Validate manager
$manager = get_user($manager_id);
if (!$manager || $manager['role'] !== 'manager') {
    json_error('Invalid manager', 400);
}

$current_user = get_logged_user();

try {
    $assigned_count = 0;
    
    foreach ($telesale_ids as $telesale_id) {
        $telesale = get_user($telesale_id);
        if (!$telesale || $telesale['role'] !== 'telesale') {
            continue;
        }
        
        // Check if already assigned
        $exists = db_get_var(
            "SELECT COUNT(*) FROM manager_assignments WHERE manager_id = ? AND telesale_id = ?",
            [$manager_id, $telesale_id]
        );
        
        if (!$exists) {
            db_insert('manager_assignments', [
                'manager_id' => $manager_id,
                'telesale_id' => $telesale_id,
                'assigned_by' => $current_user['id']
            ]);
            $assigned_count++;
        }
    }
    
    log_activity('assign_manager', "Assigned {$assigned_count} telesales to manager #{$manager_id}");
    
    json_success("Đã phân công {$assigned_count} telesale cho manager");
    
} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}
