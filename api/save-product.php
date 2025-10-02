<?php
/**
 * API: Save Product
 * Fixed version - handles both create and update
 */
define('TSM_ACCESS', true);
require_once '../system/config.php';
require_once '../includes/transaction_helper.php';
require_once '../includes/error_handler.php';
require_once '../system/functions.php';
require_once '../includes/security_helper.php';
require_once '../includes/product_helper.php';

header('Content-Type: application/json');

// Check authentication
if (!is_logged_in()) {
    json_error('Unauthorized', 401);
}

if (!is_admin()) {
    json_error('Admin only', 403);
}

// Verify CSRF
require_csrf();

// Rate limiting
check_rate_limit('save-product', get_logged_user()['id'], 30, 60);

// Get product ID if updating (optional)
$product_id = isset($_POST['product_id']) && $_POST['product_id'] ? (int)$_POST['product_id'] : null;

try {
    begin_transaction();
    
    // Validate required fields
    $name = trim($_POST['name'] ?? '');
    $sku = trim($_POST['sku'] ?? '');
    $regular_price = (float)($_POST['regular_price'] ?? 0);
    
    if (empty($name)) {
        throw new Exception('Tên sản phẩm không được để trống');
    }
    
    if (empty($sku)) {
        throw new Exception('Mã SKU không được để trống');
    }
    
    if ($regular_price < 0) {
        throw new Exception('Giá sản phẩm không hợp lệ');
    }
    
    // Check unique SKU
    $sql = "SELECT id FROM products WHERE sku = ?";
    $params = [$sku];
    
    if ($product_id) {
        $sql .= " AND id != ?";
        $params[] = $product_id;
    }
    
    $existing = db_get_var($sql, $params);
    
    if ($existing) {
        throw new Exception('Mã SKU đã tồn tại');
    }
    
    // Prepare product data
    $product_data = [
        'sku' => $sku,
        'barcode' => sanitize($_POST['barcode'] ?? ''),
        'name' => sanitize($name),
        'slug' => generate_product_slug($name),
        'category_id' => !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
        'description' => sanitize($_POST['description'] ?? ''),
        'short_description' => sanitize($_POST['short_description'] ?? ''),
        'regular_price' => $regular_price,
        'sale_price' => !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : null,
        'cost_price' => (float)($_POST['cost_price'] ?? 0),
        'weight' => !empty($_POST['weight']) ? (float)$_POST['weight'] : null,
        'dimensions' => sanitize($_POST['dimensions'] ?? ''),
        'stock_quantity' => (int)($_POST['stock_quantity'] ?? 0),
        'low_stock_threshold' => (int)($_POST['low_stock_threshold'] ?? 10),
        'manage_stock' => isset($_POST['manage_stock']) ? 1 : 0,
        'in_stock' => 1,
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        'is_digital' => isset($_POST['is_digital']) ? 1 : 0,
        'status' => in_array($_POST['status'] ?? '', ['active', 'inactive', 'draft']) 
                  ? $_POST['status'] : 'active',
        'meta_title' => sanitize($_POST['meta_title'] ?? ''),
        'meta_description' => sanitize($_POST['meta_description'] ?? ''),
        'meta_keywords' => sanitize($_POST['meta_keywords'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Auto set in_stock based on quantity if managing stock
    if ($product_data['manage_stock']) {
        $product_data['in_stock'] = $product_data['stock_quantity'] > 0 ? 1 : 0;
    }
    
    if ($product_id) {
        // UPDATE existing product
        $old_product = db_get_row("SELECT * FROM products WHERE id = ?", [$product_id]);
        if (!$old_product) {
            throw new Exception('Sản phẩm không tồn tại');
        }
        
        // Update product
        db_update('products', $product_data, 'id = ?', [$product_id]);
        
        // Log price change if changed
        if ($old_product['regular_price'] != $regular_price || 
            $old_product['sale_price'] != $product_data['sale_price']) {
            
            db_insert('product_price_history', [
                'product_id' => $product_id,
                'old_regular_price' => $old_product['regular_price'],
                'new_regular_price' => $regular_price,
                'old_sale_price' => $old_product['sale_price'],
                'new_sale_price' => $product_data['sale_price'],
                'changed_by' => get_logged_user()['id'],
                'reason' => 'Manual update',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        $message = 'Đã cập nhật sản phẩm';
        
    } else {
        // CREATE new product
        $product_data['created_by'] = get_logged_user()['id'];
        $product_data['created_at'] = date('Y-m-d H:i:s');
        
        $product_id = db_insert('products', $product_data);
        
        $message = 'Đã tạo sản phẩm mới';
    }
    
    // Handle attributes if provided
    if (isset($_POST['attributes']) && is_array($_POST['attributes'])) {
        // Delete old attributes
        db_delete('product_attribute_values', 'product_id = ?', [$product_id]);
        
        // Insert new attributes
        foreach ($_POST['attributes'] as $attr_id => $value) {
            $value = trim($value);
            if (!empty($value) && is_numeric($attr_id)) {
                db_insert('product_attribute_values', [
                    'product_id' => $product_id,
                    'attribute_id' => (int)$attr_id,
                    'value' => sanitize($value),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
    
    // Handle supplier if provided
    if (!empty($_POST['supplier_id'])) {
        $supplier_id = (int)$_POST['supplier_id'];
        
        // Verify supplier exists
        $supplier_exists = db_get_var("SELECT id FROM suppliers WHERE id = ?", [$supplier_id]);
        
        if ($supplier_exists) {
            // Check if already linked
            $link_exists = db_get_var(
                "SELECT id FROM product_suppliers WHERE product_id = ? AND supplier_id = ?",
                [$product_id, $supplier_id]
            );
            
            if (!$link_exists) {
                db_insert('product_suppliers', [
                    'product_id' => $product_id,
                    'supplier_id' => $supplier_id,
                    'cost_price' => $product_data['cost_price'],
                    'is_primary' => 1,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
    
    // Handle image uploads
    if (!empty($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
        $upload_dir = '../../uploads/products/' . date('Y/m/');
        $web_path = 'uploads/products/' . date('Y/m/');
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        // Delete old images if updating
        if ($old_product ?? false) {
            db_delete('product_images', 'product_id = ?', [$product_id]);
        }
        
        $image_count = 0;
        foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
            if ($_FILES['images']['error'][$key] !== UPLOAD_ERR_OK) {
                continue;
            }
            
            $file_type = $_FILES['images']['type'][$key];
            $file_size = $_FILES['images']['size'][$key];
            $original_name = $_FILES['images']['name'][$key];
            
            // Validate file type
            if (!in_array($file_type, $allowed_types)) {
                continue; // Skip invalid types
            }
            
            // Validate file size
            if ($file_size > $max_size) {
                continue; // Skip large files
            }
            
            // Generate unique filename
            $extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $filename = 'product_' . $product_id . '_' . uniqid() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($tmp_name, $filepath)) {
                // TODO: Generate thumbnail here if needed
                $thumb_filename = 'thumb_' . $filename;
                $thumb_path = $upload_dir . $thumb_filename;
                
                // For now, just copy as thumbnail (you should implement actual resizing)
                copy($filepath, $thumb_path);
                
                // Save to database
                db_insert('product_images', [
                    'product_id' => $product_id,
                    'image_url' => $web_path . $filename,
                    'thumbnail_url' => $web_path . $thumb_filename,
                    'alt_text' => $name,
                    'is_primary' => $image_count == 0 ? 1 : 0, // First image is primary
                    'sort_order' => $image_count,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $image_count++;
            }
        }
    }
    
    // Log activity
    log_activity(
        'save_product', 
        "$message: $name (#$product_id)", 
        'product', 
        $product_id
    );
    
    commit_transaction();
    
    // Return success
    json_success($message, [
        'product_id' => $product_id,
        'sku' => $sku
    ]);
    
} catch (Exception $e) {
    rollback_transaction();
    
    // Log error
    error_log('[SAVE_PRODUCT] Error: ' . $e->getMessage());
    
    // Return error
    json_error($e->getMessage(), 500);
}