<?php
/**
 * API: Save KPIs
 * Creates or updates KPI targets for telesale users for a specific month.
 */
define('TSM_ACCESS', true);
require_once '../config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../functions.php';

header('Content-Type: application/json');

if (!is_logged_in() || !is_admin()) {
    json_error('Unauthorized', 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$target_month = $input['target_month'] ?? null;
$kpis = $input['kpis'] ?? [];

if (empty($target_month) || empty($kpis) || !is_array($kpis)) {
    json_error('Dữ liệu không hợp lệ.');
}

try {
    $pdo = get_db_connection();
    
    // Prepare statement for efficient inserting/updating
    $sql = "INSERT INTO kpis (user_id, target_month, target_type, target_value)
            VALUES (:user_id, :target_month, :target_type, :target_value)
            ON DUPLICATE KEY UPDATE target_value = VALUES(target_value)";
    
    $stmt = $pdo->prepare($sql);

    // Loop through each user's KPI data
    foreach ($kpis as $user_id => $targets) {
        // Loop through each target type for the user
        foreach ($targets as $target_type => $target_value) {
            // Validate target type to prevent injection
            if (!in_array($target_type, ['confirmed_orders', 'total_revenue'])) {
                continue; // Skip invalid target types
            }
            
            $stmt->execute([
                ':user_id' => (int)$user_id,
                ':target_month' => $target_month,
                ':target_type' => $target_type,
                ':target_value' => (float)$target_value
            ]);
        }
    }
    
    log_activity('save_kpis', 'Updated KPIs for month ' . $target_month);
    
    json_success('Đã lưu mục tiêu thành công!');
    
} catch (Exception $e) {
    json_error('Database error: ' . $e->getMessage(), 500);
}