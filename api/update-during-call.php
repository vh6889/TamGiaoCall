<?php
/**
 * API: Update During Call - CRM VERSION
 * Cập nhật thông tin trong khi gọi (khách hàng, sản phẩm)
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

$input = get_json_input(["order_id", "update_type"]);
$order_id = (int)$input['order_id'];
$update_type = $input['update_type']; // 'customer' hoặc 'products'

if (!$order_id || !$update_type) {
    json_error('Invalid parameters', 400);
}

$user = get_logged_user();

try {
    // Kiểm tra đang trong cuộc gọi
    $active_call = db_get_row(
        "SELECT * FROM call_logs 
         WHERE order_id = ? AND user_id = ? AND end_time IS NULL",
        [$order_id, $user['id']]
    );
    
    if (!$active_call) {
        throw new Exception('Không trong cuộc gọi. Vui lòng bắt đầu cuộc gọi trước.');
    }
    
    begin_transaction();
    
    $changes = [];
    $update_data = [];
    
    // 1. UPDATE THÔNG TIN KHÁCH HÀNG
    if ($update_type === 'customer') {
        $fields = ['customer_name', 'customer_phone', 'customer_email', 'customer_address'];
        
        foreach ($fields as $field) {
            if (isset($input[$field])) {
                $old_value = db_get_var(
                    "SELECT $field FROM orders WHERE id = ?",
                    [$order_id]
                );
                
                if ($old_value != $input[$field]) {
                    $update_data[$field] = $input[$field];
                    $changes[] = "$field: '$old_value' → '{$input[$field]}'";
                }
            }
        }
    }
    
    // 2. UPDATE DANH SÁCH SẢN PHẨM
    else if ($update_type === 'products') {
        if (!isset($input['products']) || !is_array($input['products'])) {
            throw new Exception('Danh sách sản phẩm không hợp lệ');
        }
        
        // Validate từng sản phẩm
        $total_amount = 0;
        foreach ($input['products'] as $product) {
            if (!isset($product['name']) || !isset($product['qty']) || !isset($product['price'])) {
                throw new Exception('Thông tin sản phẩm không đầy đủ');
            }
            $total_amount += $product['qty'] * $product['price'];
        }
        
        $update_data['products'] = json_encode($input['products']);
        $update_data['total_amount'] = $total_amount;
        
        $changes[] = "Cập nhật danh sách sản phẩm (Tổng: " . number_format($total_amount) . "đ)";
    }
    
    // 3. Thực hiện update nếu có thay đổi
    if (!empty($update_data)) {
        db_update('orders', $update_data, 'id = ?', [$order_id]);
        
        // Ghi log thay đổi
        db_insert('order_notes', [
            'order_id' => $order_id,
            'user_id' => $user['id'],
            'note_type' => 'system',
            'content' => "Cập nhật trong cuộc gọi: " . implode(', ', $changes)
        ]);
        
        // Log vào call_logs (append vào note)
        $current_note = $active_call['note'] ?? '';
        $new_note = $current_note . 
                    "\n[" . date('H:i:s') . "] " . 
                    "Updated: " . implode(', ', $changes);
        
        db_update('call_logs', 
            ['note' => $new_note],
            'id = ?', 
            [$active_call['id']]
        );
    }
    
    commit_transaction();
    
    json_success('Đã cập nhật thông tin', [
        'changes' => $changes,
        'update_count' => count($changes)
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    json_error($e->getMessage());
}
?>