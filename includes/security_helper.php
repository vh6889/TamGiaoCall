<?php
/**
 * Security Helper Functions
 * Version: 1.0
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Verify CSRF token from request
 */
function require_csrf() {
    $token = null;
    
    // Check JSON body
    if ($_SERVER['CONTENT_TYPE'] === 'application/json') {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? null;
    } else {
        // Check POST/GET
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
    }
    
    if (!$token || !verify_csrf_token($token)) {
        http_response_code(403);
        if (is_ajax_request()) {
            json_error('CSRF token invalid', 403);
        } else {
            die('CSRF token invalid');
        }
    }
}

/**
 * Check if request is AJAX
 */
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Validate JSON input
 */
function get_json_input($required_fields = []) {
    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        json_error('Content-Type must be application/json', 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_error('Invalid JSON: ' . json_last_error_msg(), 400);
    }
    
    // Validate required fields
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            json_error("Missing required field: $field", 400);
        }
    }
    
    return $input;
}

/**
 * Check if user owns the order
 */
function require_order_access($order_id, $allow_admin = true) {
    $order = get_order($order_id);
    if (!$order) {
        json_error('Order not found', 404);
    }
    
    $user = get_logged_user();
    
    // Admin has full access
    if ($allow_admin && is_admin()) {
        return $order;
    }
    
    // Manager can access assigned telesales' orders
    if (is_manager()) {
        $telesales = get_manager_telesales($user['id']);
        $telesale_ids = array_column($telesales, 'id');
        if (in_array($order['assigned_to'], $telesale_ids)) {
            return $order;
        }
    }
    
    // User can only access their own orders
    if ($order['assigned_to'] != $user['id']) {
        json_error('Access denied to this order', 403);
    }
    
    return $order;
}

/**
 * Validate order status transition
 */
function validate_status_transition($current_status, $new_status) {
    // Get status configs
    $statuses = get_order_status_configs();
    
    if (!isset($statuses[$new_status])) {
        return false;
    }
    
    // Check if order is locked
    $locked_statuses = array_merge(
        get_confirmed_statuses(),
        get_cancelled_statuses()
    );
    
    if (in_array($current_status, $locked_statuses)) {
        return false; // Cannot change locked orders
    }
    
    return true;
}

/**
 * Validate products JSON structure
 */
function validate_products_json($products) {
    if (!is_array($products)) {
        return ['valid' => false, 'error' => 'Products must be an array'];
    }
    
    foreach ($products as $index => $product) {
        if (!isset($product['id']) || !isset($product['name'])) {
            return ['valid' => false, 'error' => "Product at index $index missing id or name"];
        }
        
        if (!isset($product['qty']) || $product['qty'] <= 0) {
            return ['valid' => false, 'error' => "Product at index $index has invalid quantity"];
        }
        
        if (!isset($product['price']) || $product['price'] < 0) {
            return ['valid' => false, 'error' => "Product at index $index has invalid price"];
        }
    }
    
    return ['valid' => true];
}

/**
 * Rate limiting
 */
function check_rate_limit($action, $user_id, $limit = 60, $window = 60) {
    $key = "rate_limit_{$action}_{$user_id}";
    $cache_file = sys_get_temp_dir() . '/' . md5($key) . '.txt';
    
    $attempts = 0;
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        if ($data && $data['time'] > time() - $window) {
            $attempts = $data['attempts'];
        }
    }
    
    if ($attempts >= $limit) {
        json_error('Rate limit exceeded. Please try again later.', 429);
    }
    
    // Increment
    file_put_contents($cache_file, json_encode([
        'attempts' => $attempts + 1,
        'time' => time()
    ]));
}

/**
 * Sanitize array deeply
 */
function sanitize_array($array) {
    $result = [];
    foreach ($array as $key => $value) {
        $clean_key = sanitize($key);
        if (is_array($value)) {
            $result[$clean_key] = sanitize_array($value);
        } else {
            $result[$clean_key] = sanitize($value);
        }
    }
    return $result;
}
?>