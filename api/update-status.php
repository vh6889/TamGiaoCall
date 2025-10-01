<?php
// ============================================
// api/update-status.php (Fixed version)
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'update-status.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
require_once '../includes/status_helper.php';
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    $user = get_logged_user();
    
    if (!$order_id || !$status) {
        json_error('Dữ liệu không hợp lệ');
    }
    
    try {
        $update_data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Lock order if status is final
        if (in_array($status, array_merge(get_confirmed_statuses(), db_get_col("SELECT status_key FROM order_status_configs WHERE label LIKE '%hủy%' OR label LIKE '%rejected%'")))) {
            $update_data['is_locked'] = 1;
            $update_data['locked_at'] = date('Y-m-d H:i:s');
            $update_data['locked_by'] = $user['id'];
        }
        
        db_update('orders', $update_data, 'id = ?', [$order_id]);
        
        // Add note
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'note_type' => 'status',
            'content' => "Cập nhật trạng thái: $status"
        ]);
        
        json_success('Đã cập nhật trạng thái');
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}