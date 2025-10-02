<?php
// ============================================
// api/sync-supplier.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'sync-supplier.php') {
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
    
    check_rate_limit('sync-supplier', get_logged_user()['id'], 5, 300); // 5 times per 5 minutes
    
    $input = get_json_input(['supplier_id']);
    $supplier_id = validate_id($input['supplier_id'], 'Supplier');
    
    try {
        $supplier = db_get_row("SELECT * FROM suppliers WHERE id = ? AND sync_enabled = 1", [$supplier_id]);
        
        if (!$supplier) {
            throw new Exception('Supplier not found or sync not enabled');
        }
        
        if (!$supplier['api_endpoint']) {
            throw new Exception('API endpoint not configured');
        }
        
        // Prepare API request
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        if ($supplier['api_key']) {
            $headers[] = 'X-API-Key: ' . base64_decode($supplier['api_key']);
        }
        
        if ($supplier['api_secret']) {
            $headers[] = 'X-API-Secret: ' . base64_decode($supplier['api_secret']);
        }
        
        // Make API request
        $ch = curl_init($supplier['api_endpoint']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For dev only
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("API returned status $http_code");
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['products'])) {
            throw new Exception('Invalid API response format');
        }
        
        // Process products
        $count = 0;
        begin_transaction();
        
        foreach ($data['products'] as $api_product) {
            // Map API fields to our database
            $sku = $api_product['sku'] ?? $api_product['code'] ?? '';
            if (!$sku) continue;
            
            // Check if product exists
            $existing_id = db_get_var("SELECT id FROM products WHERE sku = ?", [$sku]);
            
            $product_data = [
                'sku' => $sku,
                'name' => sanitize($api_product['name'] ?? 'Unknown'),
                'slug' => generate_slug($api_product['name'] ?? $sku),
                'regular_price' => (float)($api_product['price'] ?? 0),
                'cost_price' => (float)($api_product['cost'] ?? 0),
                'stock_quantity' => (int)($api_product['stock'] ?? 0),
                'manage_stock' => 1,
                'in_stock' => ($api_product['stock'] ?? 0) > 0 ? 1 : 0,
                'status' => 'active'
            ];
            
            if ($existing_id) {
                // Update existing product
                db_update('products', $product_data, 'id = ?', [$existing_id]);
                $product_id = $existing_id;
            } else {
                // Create new product
                $product_data['created_by'] = get_logged_user()['id'];
                $product_id = db_insert('products', $product_data);
            }
            
            // Link to supplier
            $link_exists = db_get_var(
                "SELECT id FROM product_suppliers WHERE product_id = ? AND supplier_id = ?",
                [$product_id, $supplier_id]
            );
            
            if (!$link_exists) {
                db_insert('product_suppliers', [
                    'product_id' => $product_id,
                    'supplier_id' => $supplier_id,
                    'supplier_sku' => $api_product['supplier_sku'] ?? $sku,
                    'cost_price' => $product_data['cost_price']
                ]);
            }
            
            $count++;
        }
        
        // Update last sync time
        db_update('suppliers', [
            'last_sync_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$supplier_id]);
        
        commit_transaction();
        
        log_activity('sync_supplier', "Synced $count products from supplier", 'supplier', $supplier_id);
        
        json_success("Đã đồng bộ $count sản phẩm", ['count' => $count]);
        
    } catch (Exception $e) {
        rollback_transaction();
        handle_api_error($e, 'Lỗi đồng bộ: ' . $e->getMessage());
    }
}

