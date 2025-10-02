<?php
// ============================================
// api/save-coupon.php
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'save-coupon.php') {
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
    
    check_rate_limit('save-coupon', get_logged_user()['id'], 10, 60);
    
    $input = get_json_input(['code', 'discount_type', 'discount_value']);
    
    try {
        $code = strtoupper(validate_required_string($input['code'], 'Coupon code', 3, 50));
        
        // Check unique code
        $exists = db_get_var("SELECT id FROM coupons WHERE code = ?", [$code]);
        if ($exists) {
            throw new Exception('Mã giảm giá đã tồn tại');
        }
        
        $discount_type = in_array($input['discount_type'], ['fixed', 'percentage']) 
                        ? $input['discount_type'] : 'fixed';
        $discount_value = max(0, (float)$input['discount_value']);
        
        if ($discount_type === 'percentage' && $discount_value > 100) {
            throw new Exception('Phần trăm giảm không thể lớn hơn 100%');
        }
        
        $coupon_data = [
            'code' => $code,
            'description' => sanitize($input['description'] ?? ''),
            'discount_type' => $discount_type,
            'discount_value' => $discount_value,
            'minimum_amount' => max(0, (float)($input['minimum_amount'] ?? 0)),
            'maximum_discount' => $input['maximum_discount'] ? max(0, (float)$input['maximum_discount']) : null,
            'usage_limit' => $input['usage_limit'] ? max(1, (int)$input['usage_limit']) : null,
            'usage_limit_per_customer' => max(1, (int)($input['usage_limit_per_customer'] ?? 1)),
            'valid_from' => $input['valid_from'] ?? null,
            'valid_to' => $input['valid_to'] ?? null,
            'is_active' => 1,
            'created_by' => get_logged_user()['id']
        ];
        
        // Handle product/category restrictions
        if (!empty($input['product_ids'])) {
            $coupon_data['product_ids'] = json_encode(array_map('intval', $input['product_ids']));
        }
        
        if (!empty($input['category_ids'])) {
            $coupon_data['category_ids'] = json_encode(array_map('intval', $input['category_ids']));
        }
        
        $coupon_id = db_insert('coupons', $coupon_data);
        
        log_activity('create_coupon', "Created coupon: $code", 'coupon', $coupon_id);
        
        json_success('Đã tạo mã giảm giá', ['coupon_id' => $coupon_id]);
        
    } catch (Exception $e) {
        handle_api_error($e, 'Không thể tạo mã giảm giá');
    }
}

// ============================================
// Helper function used by multiple APIs
// ============================================
function generate_slug($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = strtolower(trim($text, '-'));
    return empty($text) ? 'item-' . time() : $text;
}

// ============================================
// Helper: Validate ID with optional requirement
// ============================================
function validate_id($id, $field_name = 'ID', $required = true) {
    $id = (int)$id;
    if ($required && $id <= 0) {
        json_error("Invalid $field_name", 400);
    }
    return $id > 0 ? $id : null;
}
?>