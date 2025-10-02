<?php
// ============================================
// api/delete-category.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'delete-category.php') {
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
    
    check_rate_limit('delete-category', get_logged_user()['id'], 10, 60);
    
    $input = get_json_input(['category_id']);
    $category_id = validate_id($input['category_id'], 'Category');
    
    try {
        // Check if category has children
        $has_children = db_get_var(
            "SELECT COUNT(*) FROM product_categories WHERE parent_id = ?",
            [$category_id]
        );
        
        if ($has_children > 0) {
            throw new Exception('Không thể xóa danh mục có danh mục con');
        }
        
        // Check if category has products
        $has_products = db_get_var(
            "SELECT COUNT(*) FROM products WHERE category_id = ?",
            [$category_id]
        );
        
        if ($has_products > 0) {
            // Just mark as inactive
            db_update('product_categories', ['is_active' => 0], 'id = ?', [$category_id]);
            $message = 'Đã ẩn danh mục (đang có sản phẩm)';
        } else {
            // Delete category
            db_delete('product_categories', 'id = ?', [$category_id]);
            $message = 'Đã xóa danh mục';
        }
        
        log_activity('delete_category', $message, 'category', $category_id);
        
        json_success($message);
        
    } catch (Exception $e) {
        handle_api_error($e);
    }
}

