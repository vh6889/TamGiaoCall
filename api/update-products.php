<?php
/**
 * API: Update Products
 * FIXED VERSION - Sửa lỗi transaction
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

check_rate_limit('update-products', get_logged_user()['id']);

$input = get_json_input(["order_id", "products"]);
$order_id = (int)$input['order_id'];
$products = $input['products'] ?? '';

if (!$order_id || !$products) {
    json_error('Dữ liệu không hợp lệ');
}

// Verify user has access to this order
$order = get_order($order_id);
if (!$order) {
    json_error('Không tìm thấy đơn hàng', 404);
}

$user = get_logged_user();

// Check permission
if ($order['assigned_to'] != $user['id'] && !is_admin() && !is_manager()) {
    json_error('Bạn không có quyền sửa đơn hàng này', 403);
}

try {
    begin_transaction();
    
    $products_array = json_decode($products, true);
    if (!is_array($products_array)) {
        throw new Exception('Dữ liệu sản phẩm không hợp lệ');
    }
    
    // Calculate new total
    $total = 0;
    foreach ($products_array as $product) {
        if (!isset($product['sale_price']) || !isset($product['qty'])) {
            throw new Exception('Thiếu thông tin giá hoặc số lượng');
        }
        $total += floatval($product['sale_price']) * intval($product['qty']);
    }
    
    // Update order
    db_update('orders', [
        'products' => $products,
        'total_amount' => $total,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$order_id]);
    
    // Add note
    db_insert('order_notes', [
        'order_id' => $order_id,
        'user_id' => $user['id'],
        'note_type' => 'system',
        'content' => 'Cập nhật sản phẩm đơn hàng'
    ]);
    
    commit_transaction();
    
    json_success('Đã cập nhật sản phẩm', [
        'total_amount' => $total
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    error_log('[UPDATE_PRODUCTS] Error: ' . $e->getMessage());
    json_error('Lỗi: ' . $e->getMessage());
}
?>