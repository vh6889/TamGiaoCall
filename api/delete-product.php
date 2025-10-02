<?php
// ============================================
// api/delete-product.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'delete-product.php') {
    define('TSM_ACCESS', true);
    require_once '../system/config.php';
    require_once '../includes/transaction_helper.php';
    require_once '../includes/error_handler.php';
    require_once '../system/functions.php';
    require_once '../includes/security_helper.php';
	require_once '../includes/product_helper.php';
    
    header('Content-Type: application/json');
    
    require_csrf();
    require_admin();
    
    check_rate_limit('delete-product', get_logged_user()['id'], 10, 60);
    
    $input = get_json_input(['product_id']);
    $product_id = validate_id($input['product_id'], 'Product');
    
    try {
        $product = db_get_row("SELECT * FROM products WHERE id = ?", [$product_id]);
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        // Check if product is used in any orders
        $in_orders = db_get_var(
            "SELECT COUNT(*) FROM orders WHERE products LIKE ?",
            ['%"sku":"' . $product['sku'] . '"%']
        );
        
        if ($in_orders > 0) {
            // Soft delete - just mark as inactive
            db_update('products', ['status' => 'inactive'], 'id = ?', [$product_id]);
            $message = 'Đã ngừng kinh doanh sản phẩm (đang được sử dụng trong đơn hàng)';
        } else {
            // Hard delete
            begin_transaction();
            
            // Delete related data
            db_delete('product_images', 'product_id = ?', [$product_id]);
            db_delete('product_attribute_values', 'product_id = ?', [$product_id]);
            db_delete('product_variants', 'product_id = ?', [$product_id]);
            db_delete('product_suppliers', 'product_id = ?', [$product_id]);
            db_delete('stock_movements', 'product_id = ?', [$product_id]);
            db_delete('product_price_history', 'product_id = ?', [$product_id]);
            
            // Delete product
            db_delete('products', 'id = ?', [$product_id]);
            
            commit_transaction();
            $message = 'Đã xóa sản phẩm';
        }
        
        log_activity('delete_product', "$message: {$product['name']}", 'product', $product_id);
        
        json_success($message);
        
    } catch (Exception $e) {
        rollback_transaction();
        handle_api_error($e, 'Không thể xóa sản phẩm');
    }
}

