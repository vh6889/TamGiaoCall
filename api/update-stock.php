<?php
// ============================================
// api/update-stock.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'update-stock.php') {
    define('TSM_ACCESS', true);
    require_once '../config.php';
    require_once '../includes/transaction_helper.php';
    require_once '../includes/error_handler.php';
    require_once '../functions.php';
    require_once '../includes/security_helper.php';
	require_once '../includes/product_helper.php';
    
    header('Content-Type: application/json');
    
    require_csrf();
    require_admin();
    
    check_rate_limit('update-stock', get_logged_user()['id'], 30, 60);
    
    $input = get_json_input(['product_id', 'quantity', 'type']);
    
    try {
        $product_id = validate_id($input['product_id'], 'Product');
        $quantity = (int)$input['quantity'];
        $type = in_array($input['type'], ['in', 'out', 'adjustment', 'return']) 
               ? $input['type'] : 'adjustment';
        
        if ($quantity === 0) {
            throw new Exception('Quantity cannot be zero');
        }
        
        begin_transaction();
        
        // Get current stock
        $product = db_get_row(
            "SELECT id, stock_quantity, manage_stock FROM products WHERE id = ?",
            [$product_id]
        );
        
        if (!$product) {
            throw new Exception('Product not found');
        }
        
        if (!$product['manage_stock']) {
            throw new Exception('Product does not use stock management');
        }
        
        $old_stock = $product['stock_quantity'];
        
        // Calculate new stock
        if ($type === 'adjustment') {
            $new_stock = $quantity; // Direct set
            $movement_qty = $quantity - $old_stock;
        } else {
            $movement_qty = ($type === 'in' || $type === 'return') ? abs($quantity) : -abs($quantity);
            $new_stock = $old_stock + $movement_qty;
        }
        
        if ($new_stock < 0) {
            throw new Exception('Stock cannot be negative');
        }
        
        // Update product stock
        db_update('products', [
            'stock_quantity' => $new_stock,
            'in_stock' => $new_stock > 0 ? 1 : 0
        ], 'id = ?', [$product_id]);
        
        // Log stock movement
        db_insert('stock_movements', [
            'product_id' => $product_id,
            'type' => $type,
            'quantity' => $movement_qty,
            'stock_before' => $old_stock,
            'stock_after' => $new_stock,
            'reference_type' => 'manual',
            'notes' => sanitize($input['notes'] ?? ''),
            'created_by' => get_logged_user()['id']
        ]);
        
        commit_transaction();
        
        log_activity('update_stock', "Stock $type: $movement_qty units", 'product', $product_id);
        
        json_success('Đã cập nhật tồn kho', [
            'old_stock' => $old_stock,
            'new_stock' => $new_stock
        ]);
        
    } catch (Exception $e) {
        rollback_transaction();
        handle_api_error($e, 'Không thể cập nhật tồn kho');
    }
}

