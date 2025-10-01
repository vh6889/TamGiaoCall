<?php
// ============================================
// api/update-products.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'update-products.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../functions.php';
    
    header('Content-Type: application/json');
    
    if (!is_logged_in()) {
        json_error('Unauthorized', 401);
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $products = $_POST['products'] ?? '';
    
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
    } catch (Exception $e) {
        json_error('Lỗi: ' . $e->getMessage());
    }
}
