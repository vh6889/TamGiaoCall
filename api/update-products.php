<?php
// ============================================
// api/update-products.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'update-products.php') {
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

check_rate_limit('update-products', get_logged_user()['id']);

$input = get_json_input(["order_id","products"]);
$order_id = (int)$input['order_id'];
$products = $input['products'] ?? '';

// Verify user has access to this order
$order = require_order_access($order_id, false);

    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    // Input validated above
    // Input validated above
    
    if (!$order_id || !$products) {
        json_error('Dữ liệu không hợp lệ');
    }
    
    try {
        $products_array = json_decode($products, true);
        
        // Calculate new total
        $total = 0;
        foreach ($products_array as $product) {
            $total += $product['sale_price'] * $product['qty'];
        }
        
        db_update('orders', [
            'products' => $products,
            'total_amount' => $total
        ], 'id = ?', [$order_id]);
        
        json_success('Đã cập nhật sản phẩm');
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    json_error('Error: ' . $e->getMessage(), 500);
}
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}
