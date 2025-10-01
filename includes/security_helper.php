<?php
/**
 * Security Helper Functions
 * Version: 2.0 - Enhanced & Fixed
 */

if (!defined('TSM_ACCESS')) {
    die('Direct access not allowed');
}

/**
 * Enhanced CSRF verification
 */
function require_csrf() {
    $token = null;
    $content_type = isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : '';
    
    if (strpos($content_type, 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = isset($input['csrf_token']) ? $input['csrf_token'] : null;
    } else {
        $token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : (isset($_GET['csrf_token']) ? $_GET['csrf_token'] : null);
    }
    
    $origin_valid = true;
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $allowed = parse_url(SITE_URL, PHP_URL_SCHEME) . '://' . parse_url(SITE_URL, PHP_URL_HOST);
        $origin_valid = (strpos($_SERVER['HTTP_ORIGIN'], $allowed) === 0);
    }
    
    if (!$token || !verify_csrf_token($token) || !$origin_valid) {
        http_response_code(403);
        if (is_ajax_request()) {
            json_error('Security validation failed', 403);
        } else {
            die('Security validation failed');
        }
    }
}

/**
 * Check if AJAX request
 */
function is_ajax_request() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Validate JSON input
 */
function get_json_input($required_fields = array()) {
    if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] !== 'application/json') {
        json_error('Content-Type must be application/json', 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        json_error('Invalid JSON: ' . json_last_error_msg(), 400);
    }
    
    foreach ($required_fields as $field) {
        if (!isset($input[$field])) {
            json_error("Missing required field: $field", 400);
        }
    }
    
    return $input;
}


/**
 * Validate status transition
 */
function validate_status_transition($current_status, $new_status) {
    $statuses = get_order_labels(true);
    
    if (!isset($statuses[$new_status])) {
        return false;
    }
    
    $locked_statuses = array_merge(
        get_confirmed_statuses(),
        get_cancelled_statuses()
    );
    
    if (in_array($current_status, $locked_statuses)) {
        return false;
    }
    
    return true;
}

/**
 * Validate products JSON
 */
function validate_products_json($products) {
    if (!is_array($products)) {
        return array('valid' => false, 'error' => 'Products must be an array');
    }
    
    foreach ($products as $index => $product) {
        if (!isset($product['id']) || !isset($product['name'])) {
            return array('valid' => false, 'error' => "Product at index $index missing id or name");
        }
        
        if (!isset($product['qty']) || $product['qty'] <= 0) {
            return array('valid' => false, 'error' => "Product at index $index has invalid quantity");
        }
        
        if (!isset($product['price']) || $product['price'] < 0) {
            return array('valid' => false, 'error' => "Product at index $index has invalid price");
        }
    }
    
    return array('valid' => true);
}

/**
 * Database-based rate limiting
 */
function check_rate_limit($action, $user_id, $limit = 60, $window = 60) {
    $key = "rate_limit_{$action}_{$user_id}";
    $cutoff = date('Y-m-d H:i:s', time() - $window);
    
    if (rand(1, 100) === 1) {
        db_query("DELETE FROM rate_limits WHERE created_at < ?", array($cutoff));
    }
    
    $attempts = db_get_var(
        "SELECT COUNT(*) FROM rate_limits WHERE rate_key = ? AND created_at > ?",
        array($key, $cutoff)
    );
    
    if ($attempts >= $limit) {
        json_error('Rate limit exceeded. Please wait.', 429);
    }
    
    try {
        db_insert('rate_limits', array(
            'rate_key' => $key,
            'user_id' => $user_id,
            'action' => $action,
            'created_at' => date('Y-m-d H:i:s')
        ));
    } catch (Exception $e) {
        error_log('[RATE_LIMIT] ' . $e->getMessage());
    }
}

/**
 * Validate ID
 */
function validate_id($id, $field_name = 'ID') {
    $id = (int)$id;
    if ($id <= 0) {
        json_error("Invalid $field_name", 400);
    }
    return $id;
}

/**
 * Validate required string
 */
function validate_required_string($value, $field_name, $min = 1, $max = 255) {
    $value = trim(sanitize($value));
    $len = mb_strlen($value);
    
    if ($len < $min) {
        json_error("$field_name is required", 400);
    }
    if ($len > $max) {
        json_error("$field_name is too long (max $max)", 400);
    }
    
    return $value;
}

/**
 * Validate phone
 */
function validate_phone($phone) {
    $phone = preg_replace('/[^0-9+]/', '', $phone);
    if (strlen($phone) < 10 || strlen($phone) > 15) {
        json_error('Invalid phone number', 400);
    }
    return $phone;
}

/**
 * Validate email
 */
function validate_email_input($email) {
    if (empty($email)) return null;
    $email = filter_var($email, FILTER_VALIDATE_EMAIL);
    if ($email === false) {
        json_error('Invalid email format', 400);
    }
    return $email;
}


/**
 * Execute with retry
 */
function execute_with_retry($callback, $max_retries = 3) {
    $attempt = 0;
    
    while ($attempt < $max_retries) {
        try {
            return execute_in_transaction($callback);
        } catch (Exception $e) {
            $attempt++;
            
            if (stripos($e->getMessage(), 'deadlock') !== false && $attempt < $max_retries) {
                usleep(100000 * $attempt);
                continue;
            }
            
            throw $e;
        }
    }
}

/**
 * Sanitize array
 */
function sanitize_array($array) {
    $result = array();
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