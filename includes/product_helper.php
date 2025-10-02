<?php
/**
 * Product Management Helper Functions
 * Shared functions for product-related operations
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Generate SEO-friendly slug from text
 * Handles Vietnamese characters properly
 */
function generate_product_slug($text) {
    // Vietnamese character mapping
    $vietnamese = array(
        'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
        'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
        'ì','í','ị','ỉ','ĩ',
        'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
        'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
        'ỳ','ý','ỵ','ỷ','ỹ',
        'đ',
        'À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ',
        'È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ',
        'Ì','Í','Ị','Ỉ','Ĩ',
        'Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ',
        'Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ',
        'Ỳ','Ý','Ỵ','Ỷ','Ỹ',
        'Đ'
    );
    
    $latin = array(
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y',
        'd',
        'A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A',
        'E','E','E','E','E','E','E','E','E','E','E',
        'I','I','I','I','I',
        'O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O',
        'U','U','U','U','U','U','U','U','U','U','U',
        'Y','Y','Y','Y','Y',
        'D'
    );
    
    $text = str_replace($vietnamese, $latin, $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    $text = preg_replace('/-+/', '-', $text);
    
    return empty($text) ? 'product-' . uniqid() : $text;
}

/**
 * Validate product SKU format
 */
function validate_product_sku($sku) {
    // SKU must be alphanumeric with optional hyphens/underscores
    if (!preg_match('/^[A-Z0-9\-_]+$/i', $sku)) {
        throw new Exception('SKU chỉ được chứa chữ, số, gạch ngang và gạch dưới');
    }
    
    if (strlen($sku) < 3 || strlen($sku) > 50) {
        throw new Exception('SKU phải từ 3-50 ký tự');
    }
    
    return strtoupper($sku);
}

/**
 * Check if SKU is unique
 */
function is_sku_unique($sku, $exclude_id = null) {
    $params = [$sku];
    $sql = "SELECT id FROM products WHERE sku = ?";
    
    if ($exclude_id) {
        $sql .= " AND id != ?";
        $params[] = $exclude_id;
    }
    
    return !db_get_var($sql, $params);
}

/**
 * Calculate product price with discount
 */
function calculate_product_price($regular_price, $sale_price = null, $coupon = null) {
    $final_price = $sale_price ?: $regular_price;
    
    if ($coupon) {
        if ($coupon['discount_type'] === 'percentage') {
            $discount = $final_price * ($coupon['discount_value'] / 100);
            if ($coupon['maximum_discount']) {
                $discount = min($discount, $coupon['maximum_discount']);
            }
            $final_price -= $discount;
        } else {
            $final_price -= $coupon['discount_value'];
        }
    }
    
    return max(0, $final_price);
}

/**
 * Update product stock quantity
 */
function update_product_stock($product_id, $quantity_change, $type = 'adjustment', $reference = null) {
    $product = db_get_row(
        "SELECT id, stock_quantity, manage_stock FROM products WHERE id = ?",
        [$product_id]
    );
    
    if (!$product) {
        throw new Exception('Product not found');
    }
    
    if (!$product['manage_stock']) {
        return true; // Skip if not managing stock
    }
    
    $old_stock = (int)$product['stock_quantity'];
    $new_stock = $old_stock + $quantity_change;
    
    if ($new_stock < 0) {
        throw new Exception('Insufficient stock');
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
        'quantity' => $quantity_change,
        'stock_before' => $old_stock,
        'stock_after' => $new_stock,
        'reference_type' => $reference['type'] ?? null,
        'reference_id' => $reference['id'] ?? null,
        'notes' => $reference['notes'] ?? null,
        'created_by' => $_SESSION['user_id'] ?? null
    ]);
    
    return $new_stock;
}

/**
 * Get product by SKU
 */
function get_product_by_sku($sku) {
    return db_get_row(
        "SELECT p.*, c.name as category_name 
         FROM products p
         LEFT JOIN product_categories c ON p.category_id = c.id
         WHERE p.sku = ?",
        [$sku]
    );
}

/**
 * Get product variants
 */
function get_product_variants($product_id) {
    return db_get_results(
        "SELECT * FROM product_variants 
         WHERE product_id = ? AND is_active = 1
         ORDER BY variant_name",
        [$product_id]
    );
}

/**
 * Get product images
 */
function get_product_images($product_id) {
    return db_get_results(
        "SELECT * FROM product_images 
         WHERE product_id = ?
         ORDER BY is_primary DESC, sort_order, id",
        [$product_id]
    );
}

/**
 * Get product primary image
 */
function get_product_primary_image($product_id) {
    return db_get_var(
        "SELECT image_url FROM product_images 
         WHERE product_id = ? AND is_primary = 1
         LIMIT 1",
        [$product_id]
    ) ?: 'assets/img/no-image.png';
}

/**
 * Upload and process product image
 */
function upload_product_image($file, $product_id) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Validate file
    if (!in_array($file['type'], $allowed_types)) {
        throw new Exception('Invalid image type. Allowed: JPG, PNG, GIF, WEBP');
    }
    
    if ($file['size'] > $max_size) {
        throw new Exception('Image too large. Maximum 5MB');
    }
    
    // Create upload directory
    $upload_dir = '../uploads/products/' . date('Y/m/');
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . $product_id . '_' . uniqid() . '.' . $extension;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new Exception('Failed to upload image');
    }
    
    // Create thumbnail (you would implement actual image resizing here)
    $thumb_path = $upload_dir . 'thumb_' . $filename;
    copy($filepath, $thumb_path); // Placeholder - implement actual thumbnail generation
    
    return [
        'image_url' => str_replace('../', '', $filepath),
        'thumbnail_url' => str_replace('../', '', $thumb_path)
    ];
}

/**
 * Check if product is in use (in orders)
 */
function is_product_in_use($product_id) {
    $product = db_get_row("SELECT sku FROM products WHERE id = ?", [$product_id]);
    if (!$product) return false;
    
    // Check if SKU exists in any order's products JSON
    $count = db_get_var(
        "SELECT COUNT(*) FROM orders WHERE products LIKE ?",
        ['%"sku":"' . $product['sku'] . '"%']
    );
    
    return $count > 0;
}

/**
 * Get product categories tree
 */
function get_categories_tree($parent_id = null, $level = 0) {
    $categories = db_get_results(
        "SELECT * FROM product_categories 
         WHERE parent_id " . ($parent_id ? "= ?" : "IS NULL") . "
         AND is_active = 1
         ORDER BY sort_order, name",
        $parent_id ? [$parent_id] : []
    );
    
    $result = [];
    foreach ($categories as $category) {
        $category['level'] = $level;
        $category['children'] = get_categories_tree($category['id'], $level + 1);
        $result[] = $category;
    }
    
    return $result;
}

/**
 * Format product price for display
 */
function format_product_price($product) {
    if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']) {
        $discount_percent = round((1 - $product['sale_price'] / $product['regular_price']) * 100);
        return [
            'regular' => format_money($product['regular_price']),
            'sale' => format_money($product['sale_price']),
            'discount' => $discount_percent . '%',
            'final' => format_money($product['sale_price'])
        ];
    }
    
    return [
        'regular' => format_money($product['regular_price']),
        'final' => format_money($product['regular_price'])
    ];
}

/**
 * Sync products from supplier API
 */
function sync_supplier_products($supplier_id) {
    $supplier = db_get_row(
        "SELECT * FROM suppliers WHERE id = ? AND sync_enabled = 1",
        [$supplier_id]
    );
    
    if (!$supplier || !$supplier['api_endpoint']) {
        throw new Exception('Supplier not configured for sync');
    }
    
    // Prepare API request
    $ch = curl_init($supplier['api_endpoint']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Add authentication if configured
    if ($supplier['api_key']) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . base64_decode($supplier['api_key']),
            'Content-Type: application/json'
        ]);
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("API returned status $http_code");
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        throw new Exception('Invalid API response');
    }
    
    return process_supplier_products($supplier_id, $data);
}

/**
 * Process synced products from supplier
 */
function process_supplier_products($supplier_id, $data) {
    $count = 0;
    $products = $data['products'] ?? $data['items'] ?? $data;
    
    foreach ($products as $item) {
        $sku = $item['sku'] ?? $item['code'] ?? null;
        if (!$sku) continue;
        
        // Check if product exists
        $existing = db_get_row("SELECT id FROM products WHERE sku = ?", [$sku]);
        
        $product_data = [
            'sku' => $sku,
            'name' => sanitize($item['name'] ?? 'Unknown'),
            'slug' => generate_product_slug($item['name'] ?? $sku),
            'regular_price' => (float)($item['price'] ?? 0),
            'cost_price' => (float)($item['cost'] ?? 0),
            'stock_quantity' => (int)($item['stock'] ?? 0),
            'barcode' => $item['barcode'] ?? null,
            'weight' => $item['weight'] ?? null,
            'status' => 'active',
            'manage_stock' => 1,
            'in_stock' => ($item['stock'] ?? 0) > 0 ? 1 : 0
        ];
        
        if ($existing) {
            db_update('products', $product_data, 'id = ?', [$existing['id']]);
            $product_id = $existing['id'];
        } else {
            $product_data['created_by'] = $_SESSION['user_id'] ?? null;
            $product_id = db_insert('products', $product_data);
        }
        
        // Link to supplier
        $link_exists = db_get_var(
            "SELECT id FROM product_suppliers 
             WHERE product_id = ? AND supplier_id = ?",
            [$product_id, $supplier_id]
        );
        
        if (!$link_exists) {
            db_insert('product_suppliers', [
                'product_id' => $product_id,
                'supplier_id' => $supplier_id,
                'supplier_sku' => $item['supplier_sku'] ?? $sku,
                'cost_price' => $product_data['cost_price']
            ]);
        }
        
        $count++;
    }
    
    // Update last sync time
    db_update('suppliers', [
        'last_sync_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$supplier_id]);
    
    return $count;
}